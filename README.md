# mytunnel — self-hosted secure tunneling platform
Go CLI + Go tunnel server (yamux over TLS) + Laravel management API/dashboard, PostgreSQL, Redis. See `docs/architecture.md`, `docs/IMPLEMENTATION_PLAN.md`.

Dev: `cp .env.example .env`; `docker compose up -d postgres redis`; Go tests: `docker compose run --rm -e REDIS_TEST_ADDR=redis:6379 go go test -race ./...`; Laravel: `cd api && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan test`.
Quick local tunnel (no Laravel): set `DEV_STATIC_TOKEN` in `.env`, run `tunnel-server`, then `MYTUNNEL_TOKEN=<token> mytunnel http 8000 --no-tls`.
Docs: architecture, protocol, security, api, dns-ssl, vps-setup, runbook.
