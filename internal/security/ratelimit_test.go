package security

import (
	"testing"
	"time"
)

func TestRateLimiter(t *testing.T) {
	rl := NewRateLimiter(1, 3)
	now := time.Now()
	rl.now = func() time.Time { return now }
	for i := 0; i < 3; i++ {
		if !rl.Allow("a") {
			t.Fatal("burst denied")
		}
	}
	if rl.Allow("a") {
		t.Fatal("over burst allowed")
	}
	if !rl.Allow("b") {
		t.Fatal("keys not independent")
	}
	now = now.Add(2 * time.Second)
	if !rl.Allow("a") {
		t.Fatal("refill failed")
	}
}
