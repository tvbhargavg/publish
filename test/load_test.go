package test

import (
	"strings"
	"sync"
	"sync/atomic"
	"testing"
)

func TestLoad(t *testing.T) {
	t.Setenv("RATE_LIMIT_PER_IP_RPS", "0") // disabled
	t.Setenv("MAX_STREAMS_PER_TUNNEL", "500")
	e := setup(t)
	url, _, _ := connect(t, e, "secret", localApp(t))
	host := subOf(url) + ".example.test"
	var ok, bad atomic.Int64
	var wg sync.WaitGroup
	sem := make(chan struct{}, 100)
	for i := 0; i < 2000; i++ {
		wg.Add(1)
		sem <- struct{}{}
		go func() {
			defer wg.Done()
			defer func() { <-sem }()
			resp, body := get(e, host, "/l", strings.Repeat("x", 100))
			if resp != nil && resp.StatusCode == 200 && strings.HasPrefix(body, "hello /l") {
				ok.Add(1)
			} else {
				bad.Add(1)
			}
		}()
	}
	wg.Wait()
	if bad.Load() != 0 {
		t.Fatalf("ok=%d bad=%d", ok.Load(), bad.Load())
	}
}
