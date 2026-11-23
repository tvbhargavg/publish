// Package authclient authenticates and authorizes tunnel agents.
// Static (dev only) and HTTP (Laravel internal API) backends are provided.
package authclient

import (
	"context"
	"crypto/sha256"
	"crypto/subtle"
	"errors"
)

var (
	ErrUnauthorized      = errors.New("unauthorized")
	ErrSubdomainRejected = errors.New("subdomain not allowed")
	ErrLimit             = errors.New("limit exceeded")
)

// Identity is the authenticated principal.
type Identity struct {
	UserID  string
	TokenID string
}

type AuthorizeRequest struct {
	RequestedSubdomain string
	LocalPort          int
}

// Authorization is the control plane's decision for a tunnel.
type Authorization struct {
	TunnelID  string // empty: server generates
	Subdomain string // empty: server generates a random one
}

type Usage struct {
	Requests int64  `json:"requests"`
	BytesIn  int64  `json:"bytes_in"`
	BytesOut int64  `json:"bytes_out"`
	Reason   string `json:"reason"`
}

// Backend is the control-plane contract used by the tunnel server.
type Backend interface {
	Authenticate(ctx context.Context, token string) (Identity, error)
	Authorize(ctx context.Context, id Identity, req AuthorizeRequest) (Authorization, error)
	// Closed reports usage when a tunnel ends (best effort).
	Closed(ctx context.Context, tunnelID string, u Usage)
}

// Static accepts a single configured token (development only).
type Static struct{ hash [32]byte }

func NewStatic(token string) *Static { return &Static{hash: sha256.Sum256([]byte(token))} }

func (s *Static) Authenticate(_ context.Context, token string) (Identity, error) {
	h := sha256.Sum256([]byte(token))
	if subtle.ConstantTimeCompare(h[:], s.hash[:]) != 1 {
		return Identity{}, ErrUnauthorized
	}
	return Identity{UserID: "dev"}, nil
}

func (s *Static) Authorize(_ context.Context, _ Identity, r AuthorizeRequest) (Authorization, error) {
	return Authorization{Subdomain: r.RequestedSubdomain}, nil
}

func (s *Static) Closed(context.Context, string, Usage) {}
