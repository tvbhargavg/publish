// Package client implements the tunnel agent: it connects to the server,
// registers a tunnel and forwards incoming streams to a local port.
package client

import (
	"bufio"
	"context"
	"crypto/tls"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"math/rand/v2"
	"net"
	"net/http"
	"sync/atomic"
	"time"

	"github.com/techvoot/mytunnel/pkg/protocol"
)

type Options struct {
	ServerAddr string // host:port of the control listener
	Token      string
	LocalPort  int
	TLS        *tls.Config // nil = plain TCP (development only)
	Log        *slog.Logger
	OnConnect  func(reg protocol.Registered)
	OnRequest  func(method, path string, status int, d time.Duration)
	Version    string

	RequestedSubdomain string
	HeartbeatEvery     time.Duration       // default 15s
	OnStatus           func(status string) // "connecting", "reconnecting in 2s: ...", "disconnected"
}

// Run connects once and blocks until the session ends or ctx is cancelled.
func Run(ctx context.Context, o Options) error {
	if o.Log == nil {
		o.Log = slog.New(slog.NewTextHandler(io.Discard, nil))
	}
	d := net.Dialer{Timeout: 10 * time.Second}
	var conn net.Conn
	var err error
	if o.TLS != nil {
		conn, err = (&tls.Dialer{NetDialer: &d, Config: o.TLS}).DialContext(ctx, "tcp", o.ServerAddr)
	} else {
		conn, err = d.DialContext(ctx, "tcp", o.ServerAddr)
	}
	if err != nil {
		return fmt.Errorf("connect: %w", err)
	}
	sess, err := protocol.NewClientSession(conn, protocol.MuxConfig{})
	if err != nil {
		conn.Close()
		return err
	}
	defer sess.Close()

	ctl, err := sess.OpenStream()
	if err != nil {
		return err
	}
	defer ctl.Close()
	_ = ctl.SetDeadline(time.Now().Add(15 * time.Second))

	if err := protocol.WriteFrame(ctl, protocol.TypeHello, protocol.Hello{Version: protocol.Version, Token: o.Token, ClientVersion: o.Version}); err != nil {
		return err
	}
	if err := expect(ctl, protocol.TypeHelloOK, nil); err != nil {
		return err
	}
	if err := protocol.WriteFrame(ctl, protocol.TypeRegister, protocol.Register{Proto: "http", LocalPort: o.LocalPort, RequestedSubdomain: o.RequestedSubdomain}); err != nil {
		return err
	}
	var reg protocol.Registered
	if err := expect(ctl, protocol.TypeRegistered, &reg); err != nil {
		return err
	}
	_ = ctl.SetDeadline(time.Time{})
	if o.OnConnect != nil {
		o.OnConnect(reg)
	}

	go func() { <-ctx.Done(); sess.Close() }()
	hb := o.HeartbeatEvery
	if hb <= 0 {
		hb = 15 * time.Second
	}
	var lastPong atomic.Int64
	lastPong.Store(time.Now().UnixNano())
	var ctlErr atomic.Value
	go func() { // read pongs / server-sent errors
		for {
			env, err := protocol.ReadFrame(ctl)
			if err != nil {
				sess.Close()
				return
			}
			switch env.Type {
			case protocol.TypePong:
				lastPong.Store(time.Now().UnixNano())
			case protocol.TypeError:
				var e protocol.Error
				_ = env.Decode(&e)
				ctlErr.Store(&e)
				sess.Close()
				return
			}
		}
	}()
	go heartbeat(ctl, sess, hb, &lastPong)

	local := &http.Transport{
		// Only ever dial the configured loopback port, whatever Host says.
		DialContext: func(ctx context.Context, _, _ string) (net.Conn, error) {
			return (&net.Dialer{Timeout: 5 * time.Second}).DialContext(ctx, "tcp", fmt.Sprintf("127.0.0.1:%d", o.LocalPort))
		},
		DisableKeepAlives: true,
	}
	for {
		st, err := sess.AcceptStream()
		if err != nil {
			if ctx.Err() != nil {
				return nil
			}
			if e, ok := ctlErr.Load().(*protocol.Error); ok {
				return e
			}
			return fmt.Errorf("%w: %v", ErrDisconnected, err)
		}
		go handleStream(st, local, o)
	}
}

func expect(r io.Reader, typ string, out any) error {
	env, err := protocol.ReadFrame(r)
	if err != nil {
		return err
	}
	if env.Type == protocol.TypeError {
		var e protocol.Error
		_ = env.Decode(&e)
		return &e
	}
	if env.Type != typ {
		return fmt.Errorf("unexpected message %q", env.Type)
	}
	if out != nil {
		return env.Decode(out)
	}
	return nil
}

// ErrDisconnected marks a lost connection that is worth retrying.
var ErrDisconnected = errors.New("disconnected")

func heartbeat(ctl protocol.Stream, sess protocol.Session, every time.Duration, lastPong *atomic.Int64) {
	t := time.NewTicker(every)
	defer t.Stop()
	var seq uint64
	for {
		select {
		case <-sess.CloseChan():
			return
		case <-t.C:
			seq++
			if time.Since(time.Unix(0, lastPong.Load())) > 3*every {
				sess.Close() // peer silent: connection is dead
				return
			}
			if protocol.WriteFrame(ctl, protocol.TypePing, protocol.Ping{Seq: seq}) != nil {
				sess.Close()
				return
			}
		}
	}
}

func handleStream(st protocol.Stream, local *http.Transport, o Options) {
	defer st.Close()
	_ = st.SetDeadline(time.Now().Add(10 * time.Second))
	env, err := protocol.ReadFrame(st)
	if err != nil || env.Type != protocol.TypeStream {
		return
	}
	br := bufio.NewReader(st)
	req, err := http.ReadRequest(br)
	if err != nil {
		return
	}
	_ = st.SetDeadline(time.Time{})
	start := time.Now()
	req.RequestURI = ""
	req.URL.Scheme = "http"
	req.URL.Host = fmt.Sprintf("127.0.0.1:%d", o.LocalPort)
	resp, err := local.RoundTrip(req)
	status := http.StatusBadGateway
	if err != nil {
		msg := "Local application unreachable\n"
		fmt.Fprintf(st, "HTTP/1.1 502 Bad Gateway\r\nContent-Type: text/plain\r\nContent-Length: %d\r\nConnection: close\r\n\r\n%s", len(msg), msg)
	} else {
		status = resp.StatusCode
		resp.Close = true
		_ = resp.Write(st)
		resp.Body.Close()
	}
	if o.OnRequest != nil {
		o.OnRequest(req.Method, req.URL.RequestURI(), status, time.Since(start))
	}
}

// fatal reports whether retrying cannot help.
func fatal(err error, everConnected bool) bool {
	var pe *protocol.Error
	if errors.As(err, &pe) {
		switch pe.Code {
		case protocol.ErrAuth, protocol.ErrVersion, protocol.ErrBadRequest:
			return true
		}
		return !everConnected
	}
	return false
}

// RunForever keeps the tunnel up: it reconnects with exponential backoff and
// jitter, and re-requests the previously assigned subdomain so the public URL
// stays stable. It returns on ctx cancellation or a non-retryable error.
func RunForever(ctx context.Context, o Options) error {
	backoff := time.Second
	everConnected := false
	userOnConnect := o.OnConnect
	for {
		var connectedAt time.Time
		oo := o
		oo.OnConnect = func(reg protocol.Registered) {
			everConnected = true
			connectedAt = time.Now()
			o.RequestedSubdomain = reg.Subdomain
			if userOnConnect != nil {
				userOnConnect(reg)
			}
		}
		if o.OnStatus != nil {
			o.OnStatus("connecting")
		}
		err := Run(ctx, oo)
		if ctx.Err() != nil {
			return nil
		}
		if err == nil || fatal(err, everConnected) {
			return err
		}
		if !connectedAt.IsZero() && time.Since(connectedAt) > 30*time.Second {
			backoff = time.Second
		}
		wait := backoff + time.Duration(rand.Int64N(int64(backoff/2)+1))
		if o.OnStatus != nil {
			o.OnStatus(fmt.Sprintf("reconnecting in %s: %v", wait.Round(100*time.Millisecond), err))
		}
		select {
		case <-ctx.Done():
			return nil
		case <-time.After(wait):
		}
		if backoff < 30*time.Second {
			backoff *= 2
		}
	}
}
