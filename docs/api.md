# Management API (Laravel)
Base: `/api/v1`. Auth: `Authorization: Bearer <Sanctum token>` (tokens hashed at rest, revocable, optional expiry). JSON only. Rate limits: login 5/min per email+IP, API 120/min.

## Public
- `GET /config` — `{base_domain, server_host, control_port, tls}` (no secrets)
- `POST /auth/login` `{email,password,token_name?}` → `{token, token_id}` (token shown once)
- `POST /auth/register` — disabled unless `ALLOW_REGISTRATION=true`

## Authenticated
- `POST /auth/logout`, `GET /me`
- `GET|POST /tokens`, `DELETE /tokens/{id}` (own tokens only; plaintext shown once on create). Revoking drops live tunnels within `REVALIDATE_INTERVAL`.
- `GET|POST /reservations`, `DELETE /reservations/{id}` (limit: `max_reservations`)
- `GET /tunnels` (active + live heartbeat from Redis), `GET /tunnels/history`, `DELETE /tunnels/{uuid}` (disconnect), `GET /usage`

## Admin (`is_admin`)
- `GET|POST /admin/users`, `PATCH /admin/users/{id}` (`max_tunnels`, `max_reservations`, `monthly_bytes_limit`, `disabled`; disabling deletes the user's tokens)
- `GET /admin/tunnels`, `DELETE /admin/tunnels/{uuid}`, `GET /admin/audit-logs`

## Internal (tunnel server only, header `X-Internal-Secret`, never public)
- `POST /internal/v1/auth/validate` `{token}` → `{user_id, token_id}` | 401
- `POST /internal/v1/tunnels/authorize` `{user_id, token_id, requested_subdomain?, local_port}` → `{tunnel_id, subdomain}` | 403 disabled, 409 unavailable, 422 invalid, 429 limit
- `POST /internal/v1/tunnels/{id}/closed` `{requests, bytes_in, bytes_out, reason}`
The tunnel server proxies `app.<BASE_DOMAIN>` to the API but returns 404 for `/internal/*`.

## Policies
Reserved names (`config/tunnel.php`), per-user tunnel and reservation limits, optional monthly bandwidth cap, `TUNNEL_ALLOW_UNRESERVED_CUSTOM=false` to require reservation before using a custom name. Stale "active" rows are closed by `tunnels:reap` (every minute) and lazily on authorize/list.

## Bootstrap
`php artisan mytunnel:create-user you@example.com --admin` (prints a generated password).
