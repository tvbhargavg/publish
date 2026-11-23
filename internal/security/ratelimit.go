package security

import (
	"sync"
	"time"
)

// RateLimiter is a per-key token bucket with periodic cleanup.
type RateLimiter struct {
	mu      sync.Mutex
	rate    float64
	burst   float64
	buckets map[string]*bucket
	last    time.Time
	now     func() time.Time
}

type bucket struct {
	tokens float64
	at     time.Time
}

// NewRateLimiter allows rps requests/second per key with the given burst.
func NewRateLimiter(rps float64, burst int) *RateLimiter {
	return &RateLimiter{rate: rps, burst: float64(burst), buckets: map[string]*bucket{}, now: time.Now}
}

func (r *RateLimiter) Allow(key string) bool {
	if r.rate <= 0 {
		return true
	}
	r.mu.Lock()
	defer r.mu.Unlock()
	now := r.now()
	if now.Sub(r.last) > time.Minute { // evict idle buckets
		for k, b := range r.buckets {
			if now.Sub(b.at) > time.Minute {
				delete(r.buckets, k)
			}
		}
		r.last = now
	}
	b := r.buckets[key]
	if b == nil {
		b = &bucket{tokens: r.burst, at: now}
		r.buckets[key] = b
	}
	b.tokens += now.Sub(b.at).Seconds() * r.rate
	if b.tokens > r.burst {
		b.tokens = r.burst
	}
	b.at = now
	if b.tokens < 1 {
		return false
	}
	b.tokens--
	return true
}
