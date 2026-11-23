package registry

import (
	"context"
	"os"
	"testing"
	"time"
)

func suite(t *testing.T, r Registry) {
	ctx := context.Background()
	e := Entry{Subdomain: "regtest01", TunnelID: "t1", ConnID: "c1", UserID: "u1", LocalPort: 8000, Status: "online"}
	if err := r.Claim(ctx, e, time.Minute); err != nil {
		t.Fatal(err)
	}
	g, err := r.Get(ctx, "regtest01")
	if err != nil || g == nil || g.TunnelID != "t1" || g.UserID != "u1" || g.LocalPort != 8000 {
		t.Fatalf("get: %v %+v", err, g)
	}
	// other user cannot take it
	o := e
	o.UserID, o.TunnelID = "u2", "t2"
	if err := r.Claim(ctx, o, time.Minute); err != ErrTaken {
		t.Fatalf("want ErrTaken got %v", err)
	}
	// same user replaces (reconnect); old tunnel's touch fails
	n := e
	n.TunnelID = "t3"
	if err := r.Claim(ctx, n, time.Minute); err != nil {
		t.Fatal(err)
	}
	if ok, _ := r.Touch(ctx, "regtest01", "t1", time.Minute); ok {
		t.Fatal("stale tunnel touched")
	}
	if ok, _ := r.Touch(ctx, "regtest01", "t3", time.Minute); !ok {
		t.Fatal("touch failed")
	}
	// release by non-owner is a no-op
	r.Release(ctx, "regtest01", "t1")
	if g, _ := r.Get(ctx, "regtest01"); g == nil {
		t.Fatal("released by stale owner")
	}
	r.Release(ctx, "regtest01", "t3")
	if g, _ := r.Get(ctx, "regtest01"); g != nil {
		t.Fatal("not released")
	}
	// expiry
	e.TunnelID = "t4"
	r.Claim(ctx, e, 150*time.Millisecond)
	time.Sleep(300 * time.Millisecond)
	if g, _ := r.Get(ctx, "regtest01"); g != nil {
		t.Fatal("did not expire")
	}
	if err := r.Ping(ctx); err != nil {
		t.Fatal(err)
	}
}

func TestMemory(t *testing.T) { suite(t, NewMemory()) }

func TestRedis(t *testing.T) {
	addr := os.Getenv("REDIS_TEST_ADDR")
	if addr == "" {
		t.Skip("REDIS_TEST_ADDR not set")
	}
	r := NewRedis(addr, "")
	defer r.Close()
	suite(t, r)
}
