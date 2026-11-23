// Package server implements the tunnel server: the control listener that
// agents connect to and the public HTTP handler that routes visitors.
package server

import (
	"context"
	"crypto/rand"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net"
	"net/http"
	"net/http/httputil"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/techvoot/mytunnel/internal/authclient"
	"github.com/techvoot/mytunnel/internal/config"
	"github.com/techvoot/mytunnel/internal/registry"
	"github.com/techvoot/mytunnel/internal/security"
	"github.com/techvoot/mytunnel/pkg/protocol"
)

type tunnel struct {
	id, sub, userID     string
	sess                protocol.Session
	proxy               *httputil.ReverseProxy
	sem                 chan struct{}
	created             time.Time
	requests, bIn, bOut atomic.Int64
}

type Server struct {
	cfg     *config.Config
	log     *slog.Logger
	auth    authclient.Backend
	reg     registry.Registry
	Metrics Metrics

	dash     http.Handler
	pubLimit *security.RateLimiter
	ctlLimit *security.RateLimiter
	mu       sync.RWMutex
	tunnels  map[string]*tunnel
}

func New(cfg *config.Config, log *slog.Logger, auth authclient.Backend, reg registry.Registry) *Server {
	s := &Server{cfg: cfg, log: log, auth: auth, reg: reg, tunnels: map[string]*tunnel{},
		pubLimit: security.NewRateLimiter(cfg.RateLimitPerIP, int(cfg.RateLimitPerIP*2)+1),
		ctlLimit: security.NewRateLimiter(2, 10)}
	s.dash = newDashboardProxy(cfg)
	return s
}

// ActiveTunnels returns the number of registered tunnels.
func (s *Server) ActiveTunnels() int {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return len(s.tunnels)
}

func ipOf(addr string) string {
	h, _, err := net.SplitHostPort(addr)
	if err != nil {
		return addr
	}
	return h
}

// ServeControl accepts agent connections until ctx is cancelled.
func (s *Server) ServeControl(ctx context.Context, ln net.Listener) error {
	go func() { <-ctx.Done(); ln.Close() }()
	for {
		conn, err := ln.Accept()
		if err != nil {
			if ctx.Err() != nil || errors.Is(err, net.ErrClosed) {
				return nil
			}
			return err
		}
		if !s.ctlLimit.Allow(ipOf(conn.RemoteAddr().String())) {
			s.Metrics.RateLimited.Add(1)
			conn.Close()
			continue
		}
		s.Metrics.ControlConns.Add(1)
		go s.handleAgent(ctx, conn)
	}
}

func (s *Server) publicURL(sub string) string {
	scheme, port := "http", s.cfg.PublicHTTPPort
	if s.cfg.TLSMode != "none" {
		scheme, port = "https", s.cfg.PublicHTTPSPort
	}
	host := sub + "." + s.cfg.BaseDomain
	if (scheme == "http" && port != 80) || (scheme == "https" && port != 443) {
		host = fmt.Sprintf("%s:%d", host, port)
	}
	return scheme + "://" + host
}

type ctlWriter struct {
	mu sync.Mutex
	w  io.Writer
}

func (c *ctlWriter) send(typ string, v any) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	return protocol.WriteFrame(c.w, typ, v)
}

func (c *ctlWriter) fail(code, msg string) {
	_ = c.send(protocol.TypeError, protocol.Error{Code: code, Message: msg})
}

func (s *Server) handleAgent(ctx context.Context, conn net.Conn) {
	defer conn.Close()
	remote := conn.RemoteAddr().String()
	sess, err := protocol.NewServerSession(conn, protocol.MuxConfig{})
	if err != nil {
		return
	}
	defer sess.Close()
	go func() {
		select {
		case <-ctx.Done():
			sess.Close()
		case <-sess.CloseChan():
		}
	}()

	ctl, err := acceptWithTimeout(sess, 10*time.Second)
	if err != nil {
		return
	}
	defer ctl.Close()
	out := &ctlWriter{w: ctl}
	_ = ctl.SetDeadline(time.Now().Add(15 * time.Second))

	env, err := protocol.ReadFrame(ctl)
	if err != nil || env.Type != protocol.TypeHello {
		out.fail(protocol.ErrBadRequest, "expected hello")
		return
	}
	var hello protocol.Hello
	if env.Decode(&hello) != nil {
		out.fail(protocol.ErrBadRequest, "bad hello")
		return
	}
	if hello.Version != protocol.Version {
		out.fail(protocol.ErrVersion, "unsupported protocol version")
		return
	}
	id, err := s.auth.Authenticate(ctx, hello.Token)
	if err != nil {
		if errors.Is(err, authclient.ErrUnauthorized) {
			s.Metrics.AuthFailures.Add(1)
			s.log.Warn("agent auth failed", "remote", remote)
			out.fail(protocol.ErrAuth, "authentication failed")
		} else {
			s.log.Error("auth backend error", "error", err)
			out.fail(protocol.ErrInternal, "temporarily unavailable")
		}
		return
	}
	_ = out.send(protocol.TypeHelloOK, protocol.HelloOK{ServerTime: time.Now().Unix()})

	env, err = protocol.ReadFrame(ctl)
	if err != nil || env.Type != protocol.TypeRegister {
		out.fail(protocol.ErrBadRequest, "expected register")
		return
	}
	var reg protocol.Register
	if env.Decode(&reg) != nil || reg.Proto != "http" || reg.LocalPort < 1 || reg.LocalPort > 65535 {
		out.fail(protocol.ErrBadRequest, "invalid register request")
		return
	}
	if rs := reg.RequestedSubdomain; rs != "" && (!security.ValidSubdomain(rs) || security.Reserved(rs)) {
		out.fail(protocol.ErrSubdomain, "subdomain not available")
		return
	}

	t, err := s.register(ctx, id, reg, sess)
	if err != nil {
		switch {
		case errors.Is(err, registry.ErrTaken), errors.Is(err, authclient.ErrSubdomainRejected):
			out.fail(protocol.ErrSubdomain, "subdomain not available")
		case errors.Is(err, authclient.ErrLimit), errors.Is(err, errUserLimit):
			out.fail(protocol.ErrLimit, "tunnel limit reached")
		case errors.Is(err, authclient.ErrUnauthorized):
			out.fail(protocol.ErrAuth, "authentication failed")
		default:
			s.log.Error("register failed", "error", err)
			out.fail(protocol.ErrInternal, "could not allocate tunnel")
		}
		return
	}
	reason := "closed"
	defer func() {
		s.removeTunnel(t)
		_ = s.reg.Release(context.Background(), t.sub, t.id)
		cctx, c := context.WithTimeout(context.Background(), 5*time.Second)
		defer c()
		s.auth.Closed(cctx, t.id, authclient.Usage{Requests: t.requests.Load(), BytesIn: t.bIn.Load(), BytesOut: t.bOut.Load(), Reason: reason})
		s.log.Info("tunnel closed", "tunnel_id", t.id, "subdomain", t.sub, "reason", reason)
	}()
	s.log.Info("tunnel registered", "tunnel_id", t.id, "subdomain", t.sub, "user_id", t.userID, "local_port", reg.LocalPort, "remote", remote)
	if err := out.send(protocol.TypeRegistered, protocol.Registered{TunnelID: t.id, Subdomain: t.sub, URL: s.publicURL(t.sub)}); err != nil {
		return
	}

	// Periodic re-validation so token revocation drops live tunnels.
	go func() {
		tk := time.NewTicker(s.cfg.RevalidateEvery)
		defer tk.Stop()
		for {
			select {
			case <-sess.CloseChan():
				return
			case <-tk.C:
				if _, err := s.auth.Authenticate(ctx, hello.Token); errors.Is(err, authclient.ErrUnauthorized) {
					reason = "token revoked"
					out.fail(protocol.ErrAuth, "token revoked")
					sess.Close()
					return
				}
			}
		}
	}()

	for {
		_ = ctl.SetDeadline(time.Now().Add(s.cfg.TunnelTTL))
		env, err := protocol.ReadFrame(ctl)
		if err != nil {
			if reason == "closed" {
				reason = "disconnected"
			}
			return
		}
		if env.Type != protocol.TypePing {
			continue
		}
		ok, err := s.reg.Touch(ctx, t.sub, t.id, s.cfg.TunnelTTL)
		if err != nil {
			s.log.Error("registry touch failed", "error", err)
		} else if !ok {
			reason = "removed from registry"
			out.fail(protocol.ErrAuth, "tunnel removed")
			return
		}
		var p protocol.Ping
		_ = env.Decode(&p)
		if out.send(protocol.TypePong, p) != nil {
			return
		}
	}
}

func acceptWithTimeout(sess protocol.Session, d time.Duration) (protocol.Stream, error) {
	type res struct {
		st  protocol.Stream
		err error
	}
	ch := make(chan res, 1)
	go func() { st, err := sess.AcceptStream(); ch <- res{st, err} }()
	select {
	case r := <-ch:
		return r.st, r.err
	case <-time.After(d):
		sess.Close()
		return nil, errors.New("timeout")
	}
}

const alphabet = "abcdefghijklmnopqrstuvwxyz0123456789"

func randString(n int) string {
	b := make([]byte, n)
	if _, err := rand.Read(b); err != nil {
		panic(err)
	}
	for i := range b {
		b[i] = alphabet[int(b[i])%len(alphabet)]
	}
	return string(b)
}

var errUserLimit = errors.New("per-user tunnel limit")

// register authorizes, allocates a subdomain, claims it in the registry and
// installs the tunnel in the local routing table.
func (s *Server) register(ctx context.Context, id authclient.Identity, reg protocol.Register, sess protocol.Session) (*tunnel, error) {
	az, err := s.auth.Authorize(ctx, id, authclient.AuthorizeRequest{RequestedSubdomain: reg.RequestedSubdomain, LocalPort: reg.LocalPort})
	if err != nil {
		return nil, err
	}
	tid := az.TunnelID
	if tid == "" {
		tid = randString(16)
	}
	sub := az.Subdomain
	explicit := sub != ""
	if explicit && (!security.ValidSubdomain(sub) || security.Reserved(sub)) {
		return nil, authclient.ErrSubdomainRejected
	}
	for attempt := 0; attempt < 10; attempt++ {
		if !explicit {
			sub = randString(8)
		}
		e := registry.Entry{Subdomain: sub, TunnelID: tid, ConnID: randString(12), UserID: id.UserID, LocalPort: reg.LocalPort, Status: "online", CreatedAt: time.Now()}
		err := s.reg.Claim(ctx, e, s.cfg.TunnelTTL)
		if errors.Is(err, registry.ErrTaken) && !explicit {
			continue
		}
		if err != nil {
			return nil, err
		}
		t, err := s.install(&tunnel{id: tid, sub: sub, userID: id.UserID, sess: sess, created: time.Now()})
		if err != nil {
			_ = s.reg.Release(ctx, sub, tid)
			return nil, err
		}
		return t, nil
	}
	return nil, errors.New("subdomain allocation failed")
}

func (s *Server) install(t *tunnel) (*tunnel, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	n := 0
	for sub, o := range s.tunnels {
		if o.userID == t.userID && sub != t.sub {
			n++
		}
	}
	if s.cfg.MaxTunnelsPerUser > 0 && n >= s.cfg.MaxTunnelsPerUser {
		return nil, errUserLimit
	}
	if old := s.tunnels[t.sub]; old != nil {
		old.sess.Close() // same user reconnecting: replace stale session
	}
	t.sem = make(chan struct{}, s.cfg.MaxStreams)
	t.proxy = s.newProxy(t)
	s.tunnels[t.sub] = t
	return t, nil
}

func (s *Server) removeTunnel(t *tunnel) {
	s.mu.Lock()
	if s.tunnels[t.sub] == t {
		delete(s.tunnels, t.sub)
	}
	s.mu.Unlock()
}

// lookup finds the tunnel for host and verifies it against the registry
// (so revoked/removed tunnels stop receiving traffic immediately).
func (s *Server) lookup(ctx context.Context, host string) (*tunnel, int) {
	sub, ok := security.TunnelSubdomain(host, s.cfg.BaseDomain)
	if !ok {
		return nil, http.StatusNotFound
	}
	s.mu.RLock()
	t := s.tunnels[sub]
	s.mu.RUnlock()
	if t == nil {
		return nil, http.StatusNotFound
	}
	e, err := s.reg.Get(ctx, sub)
	if err != nil {
		return nil, http.StatusServiceUnavailable
	}
	if e == nil || e.TunnelID != t.id {
		return nil, http.StatusNotFound
	}
	return t, 0
}

func (s *Server) newProxy(t *tunnel) *httputil.ReverseProxy {
	tr := &http.Transport{
		DialContext: func(ctx context.Context, _, _ string) (net.Conn, error) {
			st, err := t.sess.OpenStream()
			if err != nil {
				return nil, err
			}
			if err := protocol.WriteFrame(st, protocol.TypeStream, protocol.StreamHeader{RequestID: randString(12)}); err != nil {
				st.Close()
				return nil, err
			}
			return st, nil
		},
		DisableKeepAlives:     true,
		ResponseHeaderTimeout: s.cfg.RequestTimeout,
	}
	return &httputil.ReverseProxy{
		Rewrite: func(pr *httputil.ProxyRequest) {
			// Never trust client-supplied forwarding headers.
			for _, h := range []string{"Forwarded", "X-Forwarded-For", "X-Forwarded-Host", "X-Forwarded-Proto", "X-Real-Ip"} {
				pr.Out.Header.Del(h)
			}
			pr.Out.URL.Scheme = "http"
			pr.Out.URL.Host = "tunnel"
			pr.Out.Host = pr.In.Host
			pr.SetXForwarded()
		},
		Transport: tr,
		ErrorHandler: func(w http.ResponseWriter, r *http.Request, err error) {
			var mbe *http.MaxBytesError
			switch {
			case errors.As(err, &mbe):
				http.Error(w, "Request too large", http.StatusRequestEntityTooLarge)
			case errors.Is(err, context.DeadlineExceeded) || strings.Contains(err.Error(), "timeout"):
				http.Error(w, "Gateway timeout", http.StatusGatewayTimeout)
			default:
				http.Error(w, "Tunnel unavailable", http.StatusBadGateway)
			}
		},
	}
}

type countingBody struct {
	io.ReadCloser
	n *atomic.Int64
}

func (c countingBody) Read(p []byte) (int, error) {
	n, err := c.ReadCloser.Read(p)
	c.n.Add(int64(n))
	return n, err
}

type statusWriter struct {
	http.ResponseWriter
	status int
	n      int64
}

func (w *statusWriter) WriteHeader(c int) {
	if w.status == 0 {
		w.status = c
	}
	w.ResponseWriter.WriteHeader(c)
}
func (w *statusWriter) Write(b []byte) (int, error) {
	if w.status == 0 {
		w.status = 200
	}
	n, err := w.ResponseWriter.Write(b)
	w.n += int64(n)
	return n, err
}
func (w *statusWriter) Unwrap() http.ResponseWriter { return w.ResponseWriter }

// PublicHandler routes visitor requests by Host header to the right tunnel.
func (s *Server) PublicHandler() http.Handler {
	return http.HandlerFunc(func(rw http.ResponseWriter, r *http.Request) {
		w := &statusWriter{ResponseWriter: rw}
		defer func() {
			if w.status == 0 {
				w.status = 200
			}
			s.Metrics.record(w.status)
			s.Metrics.BytesOut.Add(w.n)
		}()
		if !s.pubLimit.Allow(ipOf(r.RemoteAddr)) {
			s.Metrics.RateLimited.Add(1)
			w.Header().Set("Retry-After", "1")
			http.Error(w, "Too many requests", http.StatusTooManyRequests)
			return
		}
		if s.dash != nil && hostOnly(r.Host) == s.cfg.DashboardHost {
			s.dash.ServeHTTP(w, r)
			return
		}
		t, code := s.lookup(r.Context(), r.Host)
		if t == nil {
			http.Error(w, http.StatusText(code), code)
			return
		}
		select {
		case t.sem <- struct{}{}:
			defer func() { <-t.sem }()
		default:
			http.Error(w, "Too many concurrent requests", http.StatusServiceUnavailable)
			return
		}
		t.requests.Add(1)
		var in atomic.Int64
		r.Body = countingBody{http.MaxBytesReader(w, r.Body, s.cfg.MaxBodyBytes), &in}
		defer func() { t.bOut.Add(w.n); t.bIn.Add(in.Load()); s.Metrics.BytesIn.Add(in.Load()) }()
		t.proxy.ServeHTTP(w, r)
	})
}

// AdminHandler exposes health and metrics (bind it to an internal address).
func (s *Server) AdminHandler() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, r *http.Request) {
		ctx, c := context.WithTimeout(r.Context(), 2*time.Second)
		defer c()
		if err := s.reg.Ping(ctx); err != nil {
			http.Error(w, "registry unavailable", http.StatusServiceUnavailable)
			return
		}
		fmt.Fprintf(w, "ok tunnels=%d\n", s.ActiveTunnels())
	})
	mux.HandleFunc("/metrics", func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("Content-Type", "text/plain; version=0.0.4")
		s.Metrics.write(w, s.ActiveTunnels())
	})
	return mux
}
