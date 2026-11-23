package test

import (
	"io"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestDashboardHostProxy(t *testing.T) {
	var gotHost, gotXFF string
	up := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		gotHost, gotXFF = r.Host, r.Header.Get("X-Forwarded-For")
		w.Write([]byte("laravel:" + r.URL.Path))
	}))
	defer up.Close()
	t.Setenv("DASHBOARD_UPSTREAM", up.URL)
	e := setup(t)

	req, _ := http.NewRequest("GET", e.pub.URL+"/dashboard", nil)
	req.Host = "app.example.test"
	req.Header.Set("X-Forwarded-For", "6.6.6.6")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatal(err)
	}
	b, _ := io.ReadAll(resp.Body)
	if string(b) != "laravel:/dashboard" || gotHost != "app.example.test" || gotXFF == "6.6.6.6" {
		t.Fatalf("body=%q host=%q xff=%q", b, gotHost, gotXFF)
	}
	for _, p := range []string{"/internal/v1/auth/validate", "/INTERNAL/v1/x", "/internal"} {
		req, _ := http.NewRequest("POST", e.pub.URL+p, nil)
		req.Host = "app.example.test"
		r, _ := http.DefaultClient.Do(req)
		if r.StatusCode != 404 {
			t.Errorf("%s reachable via dashboard host: %d", p, r.StatusCode)
		}
	}
}
