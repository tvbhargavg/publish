package client

import "testing"

func TestLocalHost(t *testing.T) {
	for in, want := range map[string]string{"": "abc.example.com", "rewrite": "localhost:8000", "app.test": "app.test"} {
		if got := localHost(Options{HostHeader: in, LocalPort: 8000}, "abc.example.com"); got != want {
			t.Errorf("HostHeader %q: got %q, want %q", in, got, want)
		}
	}
}
