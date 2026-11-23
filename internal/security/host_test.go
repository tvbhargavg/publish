package security

import "testing"

func TestTunnelSubdomain(t *testing.T) {
	cases := []struct {
		host string
		sub  string
		ok   bool
	}{
		{"abc123.example.com", "abc123", true},
		{"ABC123.Example.com:8443", "abc123", true},
		{"abc123.example.com.", "abc123", true},
		{"example.com", "", false},
		{"a.b.example.com", "", false},
		{"ab.example.com", "", false},
		{"-bad-.example.com", "", false},
		{"abc123.evil.com", "", false},
		{"127.0.0.1", "", false},
		{"abc123.example.com.evil.com", "", false},
		{"", "", false},
	}
	for _, c := range cases {
		s, ok := TunnelSubdomain(c.host, "example.com")
		if s != c.sub || ok != c.ok {
			t.Errorf("%q => %q,%v", c.host, s, ok)
		}
	}
}

func TestReserved(t *testing.T) {
	if !Reserved("admin") || Reserved("myapp123") {
		t.Fatal("reserved list wrong")
	}
}
