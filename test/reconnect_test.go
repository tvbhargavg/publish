package test

import (
	"context"
	"io"
	"log/slog"
	"net"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/techvoot/mytunnel/internal/authclient"
	"github.com/techvoot/mytunnel/internal/client"
	"github.com/techvoot/mytunnel/internal/config"
	"github.com/techvoot/mytunnel/internal/registry"
	"github.com/techvoot/mytunnel/internal/server"
	"github.com/techvoot/mytunnel/pkg/protocol"
)

// cutProxy is a TCP proxy whose connections can be severed on demand.
type cutProxy struct {
	ln    net.Listener
	mu    sync.Mutex
	conns []net.Conn
}

func newCutProxy(t *testing.T, target string) *cutProxy {
	ln, _ := net.Listen("tcp", "127.0.0.1:0")
	p := &cutProxy{ln: ln}
	go func() {
		for {
			c, err := ln.Accept()
			if err != nil {
				return
			}
			u, err := net.Dial("tcp", target)
			if err != nil {
				c.Close()
				continue
			}
			p.mu.Lock()
			p.conns = append(p.conns, c, u)
			p.mu.Unlock()
			go func() { io.Copy(u, c); u.Close() }()
			go func() { io.Copy(c, u); c.Close() }()
		}
	}()
	t.Cleanup(func() { ln.Close(); p.cut() })
	return p
}

func (p *cutProxy) cut() {
	p.mu.Lock()
	defer p.mu.Unlock()
	for _, c := range p.conns {
		c.Close()
	}
	p.conns = nil
}

func TestReconnectKeepsSubdomain(t *testing.T) {
	e := setup(t)
	proxy := newCutProxy(t, e.ctlAdr)
	app := localApp(t)

	urls := make(chan string, 4)
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	done := make(chan error, 1)
	go func() {
		done <- client.RunForever(ctx, client.Options{
			ServerAddr: proxy.ln.Addr().String(), Token: "secret", LocalPort: app,
			OnConnect: func(r protocol.Registered) { urls <- r.URL },
		})
	}()
	var first string
	select {
	case first = <-urls:
	case <-time.After(5 * time.Second):
		t.Fatal("no initial connect")
	}
	proxy.cut() // simulate network failure
	select {
	case second := <-urls:
		if second != first {
			t.Fatalf("subdomain changed: %s -> %s", first, second)
		}
	case <-time.After(8 * time.Second):
		t.Fatal("did not reconnect")
	}
	resp, body := get(e, subOf(first)+".example.test", "/again", "z")
	if resp == nil || resp.StatusCode != 200 || body != "hello /again z" {
		t.Fatalf("after reconnect: %v %q", resp, body)
	}
	cancel()
	select {
	case <-done:
	case <-time.After(3 * time.Second):
		t.Fatal("RunForever did not stop")
	}
}

func TestBadTokenIsFatalNoRetry(t *testing.T) {
	e := setup(t)
	start := time.Now()
	err := client.RunForever(context.Background(), client.Options{ServerAddr: e.ctlAdr, Token: "bad", LocalPort: 1})
	if err == nil || !strings.Contains(err.Error(), "auth_failed") || time.Since(start) > 3*time.Second {
		t.Fatalf("err=%v after %v", err, time.Since(start))
	}
}

func TestRequestedSubdomainAndOwnership(t *testing.T) {
	e := setup(t)
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	got := make(chan string, 1)
	go client.RunForever(ctx, client.Options{ServerAddr: e.ctlAdr, Token: "secret", LocalPort: localApp(t), RequestedSubdomain: "my-app-1",
		OnConnect: func(r protocol.Registered) { got <- r.Subdomain }})
	if s := <-got; s != "my-app-1" {
		t.Fatalf("got %s", s)
	}
	// reserved / invalid names rejected
	for _, bad := range []string{"admin", "A_b", "x"} {
		err := client.RunForever(context.Background(), client.Options{ServerAddr: e.ctlAdr, Token: "secret", LocalPort: 1, RequestedSubdomain: bad})
		if err == nil || !strings.Contains(err.Error(), "subdomain_unavailable") {
			t.Errorf("%s: %v", bad, err)
		}
	}
}

func TestPerUserTunnelLimit(t *testing.T) {
	t.Setenv("MAX_TUNNELS_PER_USER", "2")
	e := setup(t)
	for i := 0; i < 2; i++ {
		connect(t, e, "secret", localApp(t))
	}
	err := client.RunForever(context.Background(), client.Options{ServerAddr: e.ctlAdr, Token: "secret", LocalPort: 1})
	if err == nil || !strings.Contains(err.Error(), "limit_exceeded") {
		t.Fatalf("err=%v", err)
	}
}

func TestRateLimit(t *testing.T) {
	t.Setenv("RATE_LIMIT_PER_IP_RPS", "2")
	e := setup(t)
	url, _, _ := connect(t, e, "secret", localApp(t))
	host := subOf(url) + ".example.test"
	limited := 0
	for i := 0; i < 20; i++ {
		if r, _ := get(e, host, "/", ""); r != nil && r.StatusCode == 429 {
			limited++
		}
	}
	if limited == 0 {
		t.Fatal("rate limit never triggered")
	}
}

// revocableAuth lets a test revoke a token mid-session.
type revocableAuth struct {
	mu      sync.Mutex
	revoked bool
}

func (r *revocableAuth) Authenticate(_ context.Context, tok string) (authclient.Identity, error) {
	r.mu.Lock()
	defer r.mu.Unlock()
	if tok != "t" || r.revoked {
		return authclient.Identity{}, authclient.ErrUnauthorized
	}
	return authclient.Identity{UserID: "u"}, nil
}
func (r *revocableAuth) Authorize(context.Context, authclient.Identity, authclient.AuthorizeRequest) (authclient.Authorization, error) {
	return authclient.Authorization{}, nil
}
func (r *revocableAuth) Closed(context.Context, string, authclient.Usage) {}

func TestRevocationDropsLiveTunnel(t *testing.T) {
	t.Setenv("BASE_DOMAIN", "example.test")
	t.Setenv("REVALIDATE_INTERVAL", "200ms")
	cfg, _ := config.Load()
	auth := &revocableAuth{}
	srv := server.New(cfg, slog.New(slog.NewTextHandler(io.Discard, nil)), auth, registry.NewMemory())
	ln, _ := net.Listen("tcp", "127.0.0.1:0")
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	go srv.ServeControl(ctx, ln)
	up := make(chan struct{}, 1)
	done := make(chan error, 1)
	go func() {
		done <- client.RunForever(ctx, client.Options{ServerAddr: ln.Addr().String(), Token: "t", LocalPort: 1, OnConnect: func(protocol.Registered) { up <- struct{}{} }})
	}()
	<-up
	auth.mu.Lock()
	auth.revoked = true
	auth.mu.Unlock()
	select {
	case err := <-done:
		if err == nil || !strings.Contains(err.Error(), "auth_failed") {
			t.Fatalf("err=%v", err)
		}
	case <-time.After(5 * time.Second):
		t.Fatal("tunnel not dropped after revocation")
	}
	if srv.ActiveTunnels() != 0 {
		t.Fatal("tunnel still active")
	}
}

func TestRegistryRemovalStopsTraffic(t *testing.T) {
	t.Setenv("BASE_DOMAIN", "example.test")
	cfg, _ := config.Load()
	reg := registry.NewMemory()
	srv := server.New(cfg, slog.New(slog.NewTextHandler(io.Discard, nil)), authclient.NewStatic("secret"), reg)
	ln, _ := net.Listen("tcp", "127.0.0.1:0")
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	go srv.ServeControl(ctx, ln)
	pub := httptest.NewServer(srv.PublicHandler())
	defer pub.Close()
	got := make(chan protocol.Registered, 1)
	go client.RunForever(ctx, client.Options{ServerAddr: ln.Addr().String(), Token: "secret", LocalPort: localApp(t), OnConnect: func(r protocol.Registered) { got <- r }})
	r := <-got
	e := &env{srv: srv, pub: pub}
	if resp, _ := get(e, r.Subdomain+".example.test", "/", ""); resp.StatusCode != 200 {
		t.Fatalf("status %d", resp.StatusCode)
	}
	reg.Release(ctx, r.Subdomain, r.TunnelID) // control plane removes the tunnel
	if resp, _ := get(e, r.Subdomain+".example.test", "/", ""); resp.StatusCode != 404 {
		t.Fatalf("expected 404 after registry removal, got %d", resp.StatusCode)
	}
}
