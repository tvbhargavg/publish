// Package registry stores active tunnel mappings (subdomain -> tunnel/session).
// Redis is the production store; Memory is used for dev/tests. PostgreSQL
// (via the Laravel API) remains the source of truth for accounts.
package registry

import (
	"context"
	"errors"
	"sync"
	"time"
)

var ErrTaken = errors.New("subdomain held by another user")

type Entry struct {
	Subdomain     string
	TunnelID      string
	ConnID        string
	UserID        string
	LocalPort     int
	Status        string // "online"
	CreatedAt     time.Time
	LastHeartbeat time.Time
}

type Registry interface {
	// Claim registers e. It fails with ErrTaken if the subdomain is held by a
	// different user; the same user replaces its own (reconnect).
	Claim(ctx context.Context, e Entry, ttl time.Duration) error
	// Touch refreshes the heartbeat/TTL. Returns false if the entry is gone or
	// now belongs to a different tunnel (revoked, replaced or expired).
	Touch(ctx context.Context, sub, tunnelID string, ttl time.Duration) (bool, error)
	Get(ctx context.Context, sub string) (*Entry, error)
	// Release deletes the entry only if it still belongs to tunnelID.
	Release(ctx context.Context, sub, tunnelID string) error
	Ping(ctx context.Context) error
}

// Memory is an in-process Registry.
type Memory struct {
	mu sync.Mutex
	m  map[string]memEntry
}

type memEntry struct {
	e   Entry
	exp time.Time
}

func NewMemory() *Memory { return &Memory{m: map[string]memEntry{}} }

func (r *Memory) live(sub string) (memEntry, bool) {
	me, ok := r.m[sub]
	if ok && time.Now().After(me.exp) {
		delete(r.m, sub)
		return memEntry{}, false
	}
	return me, ok
}

func (r *Memory) Claim(_ context.Context, e Entry, ttl time.Duration) error {
	r.mu.Lock()
	defer r.mu.Unlock()
	if cur, ok := r.live(e.Subdomain); ok && cur.e.UserID != e.UserID {
		return ErrTaken
	}
	e.LastHeartbeat = time.Now()
	r.m[e.Subdomain] = memEntry{e, time.Now().Add(ttl)}
	return nil
}

func (r *Memory) Touch(_ context.Context, sub, tid string, ttl time.Duration) (bool, error) {
	r.mu.Lock()
	defer r.mu.Unlock()
	cur, ok := r.live(sub)
	if !ok || cur.e.TunnelID != tid {
		return false, nil
	}
	cur.e.LastHeartbeat = time.Now()
	cur.exp = time.Now().Add(ttl)
	r.m[sub] = cur
	return true, nil
}

func (r *Memory) Get(_ context.Context, sub string) (*Entry, error) {
	r.mu.Lock()
	defer r.mu.Unlock()
	cur, ok := r.live(sub)
	if !ok {
		return nil, nil
	}
	e := cur.e
	return &e, nil
}

func (r *Memory) Release(_ context.Context, sub, tid string) error {
	r.mu.Lock()
	defer r.mu.Unlock()
	if cur, ok := r.live(sub); ok && cur.e.TunnelID == tid {
		delete(r.m, sub)
	}
	return nil
}

func (r *Memory) Ping(context.Context) error { return nil }
