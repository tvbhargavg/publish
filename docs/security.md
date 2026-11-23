# Security
Status: implemented and covered by tests unless marked (manual).

| Area | Control |
|---|---|
| Transport | TLS 1.2+ for agents (`TLS_MODE=file|acme`); production config refuses `TLS_MODE=none` |
| Tokens | Sanctum hashed at rest, optional expiry, revocation; server re-validates every `REVALIDATE_INTERVAL` and drops revoked tunnels; never logged (slog redaction + audit logs store no secrets); CLI stores token with mode 0600 |
| Secrets | All from env; `.env*` git-ignored; production refuses weak/placeholder `INTERNAL_API_SECRET`, `DEV_STATIC_TOKEN` forbidden in production |
| Internal API | `X-Internal-Secret` (constant-time compare, fails closed if unset); `/internal/*` blocked on the public dashboard host; do not expose it on the internet (firewall/compose network) |
| Authorization | Tunnel ownership recorded in the registry; Laravel policy on subdomain reservation/collision, limits, disabled users; users can only list/disconnect/revoke their own resources |
| Routing | Strict Host regex under `BASE_DOMAIN`, IPs/apex/nested labels → 404; registry verified on every request (removed/revoked tunnels stop immediately) |
| SSRF | Client only dials `127.0.0.1:<registered port>`; the server never dials client-supplied addresses |
| Headers | Client-supplied `Forwarded`, `X-Forwarded-*`, `X-Real-IP` stripped and rewritten |
| DoS | Body size limit (413), per-tunnel stream cap (503), per-IP rate limit (429), control-connection rate limit, request/header timeouts, max tunnels per user, max frame size |
| Info leakage | Generic error pages; admin/metrics listener is separate (bind to an internal address) |
| Audit | `audit_logs` for logins, tokens, reservations, user changes, disconnects |
| Sessions/CSRF | Laravel web guard, CSRF on all dashboard forms, login throttling, disabled users blocked |

Known limitations / follow-ups: WebSocket upgrades are not validated end-to-end; plain-HTTP public port is not auto-redirected to HTTPS; revocation latency ≤ `REVALIDATE_INTERVAL`; the rate limiter trusts the TCP peer address (put a trusted LB header handling in place before running behind a proxy); ACME issuance (Cloudflare DNS-01) compiles and is configured but could not be exercised without a real domain (manual); Redis must be private and password-protected in production.
