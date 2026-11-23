# Architecture
Components: CLI (Go) ⇄ Tunnel Server (Go, data plane) ⇄ Redis (active registry); Laravel (control plane) + PostgreSQL (source of truth). Laravel is never in the per-request tunnel path; the server calls it only to validate tokens, authorize tunnels and report usage.

```
CLI --TLS+yamux--> tunnel-server :4443            visitor --HTTPS--> tunnel-server :443  (Host: abc123.BASE_DOMAIN)
tunnel-server <-> Redis  tunnel:{subdomain}  (hash, TTL refreshed by heartbeat; verified per request)
tunnel-server --> Laravel /internal/v1/*  --> PostgreSQL (users, tokens, reservations, tunnel history, audit)
browser --> app.BASE_DOMAIN --(tunnel-server host proxy, /internal blocked)--> Laravel dashboard + /api/v1
```
Request flow: rate limit → Host validation → local session lookup → registry verification → per-tunnel stream cap → new yamux stream → client dials 127.0.0.1:port → response streamed back; usage counted per tunnel and reported when it closes.
Control flow: hello(token) → Laravel validate → register → Laravel authorize (policy, subdomain, DB row) → Redis claim → registered. Heartbeats refresh Redis; removing the Redis key (dashboard "disconnect") or revoking the token ends the tunnel.
Config: everything via env (`.env.example`); the domain is `BASE_DOMAIN` only. Package boundaries: `cmd/*` entrypoints; `internal/client` and `internal/server` never import each other; both share `pkg/protocol`, `internal/config`, `internal/logging`. Laravel lives entirely under `api/`.
Multiplexer: hashicorp/yamux behind `protocol.Session` (swappable).
