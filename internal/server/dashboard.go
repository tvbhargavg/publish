package server

import (
	"net"
	"net/http"
	"net/http/httputil"
	"net/url"
	"strings"

	"github.com/techvoot/mytunnel/internal/config"
)

func hostOnly(h string) string {
	h = strings.ToLower(h)
	if hh, _, err := net.SplitHostPort(h); err == nil {
		h = hh
	}
	return strings.TrimSuffix(h, ".")
}

// newDashboardProxy forwards the dashboard hostname (DASHBOARD_HOST) to the
// Laravel upstream so one wildcard certificate and one public IP serve both
// tunnels and the management UI. /internal/* is never reachable this way.
// Returns nil when DASHBOARD_UPSTREAM is unset.
func newDashboardProxy(cfg *config.Config) http.Handler {
	if cfg.DashboardUpstream == "" {
		return nil
	}
	u, err := url.Parse(cfg.DashboardUpstream)
	if err != nil || u.Host == "" {
		return nil
	}
	proxy := &httputil.ReverseProxy{
		Rewrite: func(pr *httputil.ProxyRequest) {
			pr.SetURL(u)
			pr.Out.Host = pr.In.Host // Laravel builds absolute URLs from the public host
			for _, h := range []string{"Forwarded", "X-Forwarded-For", "X-Forwarded-Host", "X-Forwarded-Proto", "X-Real-Ip"} {
				pr.Out.Header.Del(h)
			}
			pr.SetXForwarded()
			if cfg.TLSMode != "none" {
				pr.Out.Header.Set("X-Forwarded-Proto", "https")
			}
		},
		ErrorHandler: func(w http.ResponseWriter, _ *http.Request, _ error) {
			http.Error(w, "Dashboard unavailable", http.StatusBadGateway)
		},
	}
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		p := strings.ToLower(r.URL.Path)
		if strings.HasPrefix(p, "/internal") || strings.Contains(p, "..") {
			http.NotFound(w, r)
			return
		}
		r.Body = http.MaxBytesReader(w, r.Body, 5<<20)
		proxy.ServeHTTP(w, r)
	})
}
