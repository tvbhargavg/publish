package authclient

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
	"time"
)

// HTTP talks to the Laravel internal API (server-to-server, shared secret).
type HTTP struct {
	base   string
	secret string
	c      *http.Client
}

func NewHTTP(base, secret string) *HTTP {
	return &HTTP{base: strings.TrimRight(base, "/"), secret: secret, c: &http.Client{Timeout: 5 * time.Second}}
}

func (h *HTTP) post(ctx context.Context, path string, in, out any) (int, error) {
	b, _ := json.Marshal(in)
	req, err := http.NewRequestWithContext(ctx, "POST", h.base+path, bytes.NewReader(b))
	if err != nil {
		return 0, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("X-Internal-Secret", h.secret)
	resp, err := h.c.Do(req)
	if err != nil {
		return 0, err
	}
	defer resp.Body.Close()
	if out != nil && resp.StatusCode == 200 {
		if err := json.NewDecoder(http.MaxBytesReader(nil, resp.Body, 1<<20)).Decode(out); err != nil {
			return resp.StatusCode, err
		}
	}
	return resp.StatusCode, nil
}

func (h *HTTP) Authenticate(ctx context.Context, token string) (Identity, error) {
	var out struct {
		UserID  string `json:"user_id"`
		TokenID string `json:"token_id"`
	}
	code, err := h.post(ctx, "/internal/v1/auth/validate", map[string]string{"token": token}, &out)
	if err != nil {
		return Identity{}, err
	}
	switch code {
	case 200:
		return Identity{UserID: out.UserID, TokenID: out.TokenID}, nil
	case 401, 403:
		return Identity{}, ErrUnauthorized
	}
	return Identity{}, fmt.Errorf("auth backend status %d", code)
}

func (h *HTTP) Authorize(ctx context.Context, id Identity, r AuthorizeRequest) (Authorization, error) {
	var out struct {
		TunnelID  string `json:"tunnel_id"`
		Subdomain string `json:"subdomain"`
	}
	code, err := h.post(ctx, "/internal/v1/tunnels/authorize", map[string]any{
		"user_id": id.UserID, "token_id": id.TokenID, "requested_subdomain": r.RequestedSubdomain, "local_port": r.LocalPort,
	}, &out)
	if err != nil {
		return Authorization{}, err
	}
	switch code {
	case 200:
		return Authorization{TunnelID: out.TunnelID, Subdomain: out.Subdomain}, nil
	case 401, 403:
		return Authorization{}, ErrUnauthorized
	case 409, 422:
		return Authorization{}, ErrSubdomainRejected
	case 429:
		return Authorization{}, ErrLimit
	}
	return Authorization{}, fmt.Errorf("authorize backend status %d", code)
}

func (h *HTTP) Closed(ctx context.Context, tunnelID string, u Usage) {
	_, _ = h.post(ctx, "/internal/v1/tunnels/"+tunnelID+"/closed", u, nil)
}
