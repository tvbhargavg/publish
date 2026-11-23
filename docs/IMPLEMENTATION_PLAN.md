# Implementation Plan & Checklist
A task is checked only when its tests pass. Dev is Docker-first: `docker compose run --rm -e REDIS_TEST_ADDR=redis:6379 go go test -race ./...` (needs `docker compose up -d redis postgres`); Laravel tests: `cd api && php artisan test` (Postgres db `mytunnel_test`).

## Phase 1 – Foundation: DONE
## Phase 2 – Basic prototype: DONE (e2e with real binaries verified)
## Phase 3 – Concurrent protocol: DONE
- [x] stream-per-request multiplexing, 2000-request load test, -race clean
- [x] heartbeats + pong reader, dead-peer detection, stream cap, timeouts
- [x] auto-reconnect with backoff/jitter keeping the same subdomain (severed-connection test)
- [x] CLI `status` / `stop` via per-PID state files
## Phase 4 – Public routing: DONE (ACME live issuance untested – needs a domain)
- [x] requested/random subdomains, reserved names, ownership-aware Redis registry (Lua; Redis integration test)
- [x] registry verified per request; removal stops traffic immediately
- [x] TLS file mode (tested with generated wildcard cert), ACME DNS-01 (certmagic+Cloudflare) wired and compiled
- [x] dashboard host proxy (app.<domain>), /internal blocked
## Phase 5 – Auth & security: DONE
- [x] Laravel-backed auth (validate/authorize/closed), periodic re-validation → revocation drops tunnels
- [x] per-IP + control rate limits, body limit, per-user tunnel limit, header sanitizing, negative tests
## Phase 6 – Management API: DONE (20 Laravel tests)
## Phase 7 – Dashboard (Blade, no Livewire needed): DONE (9 tests)
## Phase 8 – Production: files DONE, live full-stack run PENDING
- [x] Dockerfiles (server image built and config-guard verified), docker-compose.prod.yml, systemd units, ufw script, Postgres backup script, Prometheus config + alerts, /metrics + /healthz, docs (vps-setup, runbook, dns-ssl, security, api, protocol)
- [x] Live full-stack run (real Laravel + Redis + Go server + CLI): tunnel, Host routing, dashboard proxy, /internal blocked, bad token rejected, revoked token drops tunnel (verified 2026-11-23)
- [ ] Build/run `Dockerfile.api` and `docker-compose.prod.yml` on a real host with a domain
