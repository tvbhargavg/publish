# publish — self-hosted secure tunneling platform
Go CLI + Go tunnel server (yamux over TLS) + Laravel management API/dashboard, PostgreSQL, Redis. See `docs/architecture.md`, `docs/IMPLEMENTATION_PLAN.md`.

Dev: `cp .env.example .env`; `docker compose up -d postgres redis`; Go tests: `docker compose run --rm -e REDIS_TEST_ADDR=redis:6379 go go test -race ./...`; Laravel: `cd api && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan test`.
Quick local tunnel (no Laravel): set `DEV_STATIC_TOKEN` in `.env`, run `tunnel-server`, then `PUBLISH_TOKEN=<token> publish http 8000 --no-tls`.
Docs: architecture, protocol, security, api, dns-ssl, vps-setup, runbook.


## Accounts, email verification and plans
- Self-registration is controlled by `ALLOW_REGISTRATION` (off by default; on in the dev example). One account per email (case-insensitive).
- New users must **verify their email** (link sent via SMTP, `MAIL_*` in `api/.env`) before using the dashboard, API or tunnels. Admin-created users and the seeded admin are pre-verified.
- **Free**: 1 tunnel and 1 reserved subdomain (`FREE_MAX_TUNNELS`, `FREE_MAX_RESERVATIONS`); a free user's tunnel always uses their reserved subdomain.
- **Premium** (admin-granted in Admin > Users, 3-dot menu): the per-user tunnel/subdomain numbers an admin sets (default `PREMIUM_MAX_TUNNELS=4`).
- Users manage their name, email and password under **Profile**. Changing the email requires re-verification.
- Seed the first admin: set `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `api/.env`, then `php artisan db:seed`.
