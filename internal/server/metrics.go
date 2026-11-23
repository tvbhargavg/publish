package server

import (
	"fmt"
	"io"
	"sync/atomic"
)

// Metrics are simple atomic counters exposed in Prometheus text format.
type Metrics struct {
	ControlConns, AuthFailures, RateLimited, Requests2xx, Requests3xx, Requests4xx, Requests5xx atomic.Int64
	BytesIn, BytesOut                                                                           atomic.Int64
}

func (m *Metrics) record(status int) {
	switch {
	case status >= 500:
		m.Requests5xx.Add(1)
	case status >= 400:
		m.Requests4xx.Add(1)
	case status >= 300:
		m.Requests3xx.Add(1)
	default:
		m.Requests2xx.Add(1)
	}
}

func (m *Metrics) write(w io.Writer, active int) {
	p := func(name, help, typ string, v int64, labels string) {
		fmt.Fprintf(w, "# HELP %s %s\n# TYPE %s %s\n%s%s %d\n", name, help, name, typ, name, labels, v)
	}
	p("publish_tunnels_active", "Active tunnels", "gauge", int64(active), "")
	p("publish_control_connections_total", "Agent connections accepted", "counter", m.ControlConns.Load(), "")
	p("publish_auth_failures_total", "Failed agent authentications", "counter", m.AuthFailures.Load(), "")
	p("publish_rate_limited_total", "Requests rejected by rate limiting", "counter", m.RateLimited.Load(), "")
	p("publish_bytes_in_total", "Request body bytes forwarded", "counter", m.BytesIn.Load(), "")
	p("publish_bytes_out_total", "Response bytes forwarded", "counter", m.BytesOut.Load(), "")
	fmt.Fprintf(w, "# HELP publish_requests_total Public requests by status class\n# TYPE publish_requests_total counter\n")
	for c, v := range map[string]int64{"2xx": m.Requests2xx.Load(), "3xx": m.Requests3xx.Load(), "4xx": m.Requests4xx.Load(), "5xx": m.Requests5xx.Load()} {
		fmt.Fprintf(w, "publish_requests_total{class=%q} %d\n", c, v)
	}
}
