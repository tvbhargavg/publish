package test

import (
	"context"
	"io"
	"log/slog"
	"net"
	"net/http"
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

type env struct {
	srv    *server.Server
	pub    *httptest.Server
	ctlAdr string
	cancel context.CancelFunc
}

func setup(t *testing.T) *env {
	t.Helper()
	t.Setenv("BASE_DOMAIN", "example.test")
	t.Setenv("MAX_BODY_BYTES", "1024")
	cfg, err := config.Load()
	if err != nil {
		t.Fatal(err)
	}
	log := slog.New(slog.NewTextHandler(io.Discard, nil))
	srv := server.New(cfg, log, authclient.NewStatic("secret"), registry.NewMemory())
	ln, _ := net.Listen("tcp", "127.0.0.1:0")
	ctx, cancel := context.WithCancel(context.Background())
	go srv.ServeControl(ctx, ln)
	pub := httptest.NewServer(srv.PublicHandler())
	t.Cleanup(func() { cancel(); pub.Close() })
	return &env{srv, pub, ln.Addr().String(), cancel}
}

func connect(t *testing.T, e *env, token string, port int) (url string, errc chan error, stop context.CancelFunc) {
	ctx, cancel := context.WithCancel(context.Background())
	got := make(chan string, 1)
	errc = make(chan error, 1)
	go func() {
		errc <- client.Run(ctx, client.Options{ServerAddr: e.ctlAdr, Token: token, LocalPort: port, OnConnect: func(r protocol.Registered) { got <- r.URL }})
	}()
	select {
	case url = <-got:
	case err := <-errc:
		errc <- err
		cancel()
		return "", errc, cancel
	case <-time.After(5 * time.Second):
		t.Fatal("connect timeout")
	}
	t.Cleanup(cancel)
	return url, errc, cancel
}

func localApp(t *testing.T) (port int) {
	app := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		b, _ := io.ReadAll(r.Body)
		w.Header().Set("X-Seen-XFF", r.Header.Get("X-Forwarded-For"))
		w.Header().Set("X-Seen-Host", r.Host)
		w.Write([]byte("hello " + r.URL.Path + " " + string(b)))
	}))
	t.Cleanup(app.Close)
	return app.Listener.Addr().(*net.TCPAddr).Port
}

func get(e *env, host, path, body string) (*http.Response, string) {
	req, _ := http.NewRequest("POST", e.pub.URL+path, strings.NewReader(body))
	req.Host = host
	req.Header.Set("X-Forwarded-For", "6.6.6.6")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err.Error()
	}
	defer resp.Body.Close()
	b, _ := io.ReadAll(resp.Body)
	return resp, string(b)
}

func subOf(u string) string { return strings.SplitN(strings.TrimPrefix(u, "http://"), ".", 2)[0] }

func TestForwardAndSanitize(t *testing.T) {
	e := setup(t)
	url, _, _ := connect(t, e, "secret", localApp(t))
	host := strings.TrimPrefix(url, "http://")
	if i := strings.Index(host, ":"); i >= 0 {
		host = host[:i]
	}
	resp, body := get(e, host, "/x", "data")
	if resp == nil || resp.StatusCode != 200 || body != "hello /x data" {
		t.Fatalf("got %v %q", resp, body)
	}
	if xff := resp.Header.Get("X-Seen-XFF"); strings.Contains(xff, "6.6.6.6") {
		t.Fatalf("spoofed XFF leaked: %s", xff)
	}
	if resp.Header.Get("X-Seen-Host") != host {
		t.Fatalf("host not preserved: %s", resp.Header.Get("X-Seen-Host"))
	}
}

func TestConcurrentRequests(t *testing.T) {
	e := setup(t)
	url, _, _ := connect(t, e, "secret", localApp(t))
	host := subOf(url) + ".example.test"
	var wg sync.WaitGroup
	for i := 0; i < 50; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			resp, body := get(e, host, "/c", "y")
			if resp == nil || resp.StatusCode != 200 || body != "hello /c y" {
				t.Errorf("bad: %v %q", resp, body)
			}
		}()
	}
	wg.Wait()
}

func TestBadTokenRejected(t *testing.T) {
	e := setup(t)
	_, errc, _ := connect(t, e, "wrong", localApp(t))
	select {
	case err := <-errc:
		if err == nil || !strings.Contains(err.Error(), "auth_failed") {
			t.Fatalf("want auth_failed, got %v", err)
		}
	case <-time.After(3 * time.Second):
		t.Fatal("timeout")
	}
	if e.srv.ActiveTunnels() != 0 {
		t.Fatal("tunnel registered despite bad token")
	}
}

func TestRoutingRejections(t *testing.T) {
	e := setup(t)
	connect(t, e, "secret", localApp(t))
	for _, h := range []string{"nosuchtun.example.test", "example.test", "abc.evil.test", "127.0.0.1"} {
		resp, _ := get(e, h, "/", "")
		if resp == nil || resp.StatusCode != 404 {
			t.Errorf("host %s: %v", h, resp)
		}
	}
}

func TestBodyLimit(t *testing.T) {
	e := setup(t)
	url, _, _ := connect(t, e, "secret", localApp(t))
	resp, _ := get(e, subOf(url)+".example.test", "/", strings.Repeat("a", 5000))
	if resp == nil || resp.StatusCode != http.StatusRequestEntityTooLarge {
		t.Fatalf("got %v", resp)
	}
}

func TestDisconnectCleansUp(t *testing.T) {
	e := setup(t)
	url, _, stop := connect(t, e, "secret", localApp(t))
	stop()
	deadline := time.Now().Add(3 * time.Second)
	for e.srv.ActiveTunnels() != 0 && time.Now().Before(deadline) {
		time.Sleep(20 * time.Millisecond)
	}
	if e.srv.ActiveTunnels() != 0 {
		t.Fatal("tunnel not cleaned up")
	}
	resp, _ := get(e, subOf(url)+".example.test", "/", "")
	if resp.StatusCode != 404 {
		t.Fatalf("got %d", resp.StatusCode)
	}
}

func TestLocalAppDown(t *testing.T) {
	e := setup(t)
	url, _, _ := connect(t, e, "secret", 1) // nothing listens on :1
	resp, _ := get(e, subOf(url)+".example.test", "/", "")
	if resp == nil || resp.StatusCode != http.StatusBadGateway {
		t.Fatalf("got %v", resp)
	}
}
