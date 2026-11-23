# VPS deployment
Requirements: Linux VPS (2 vCPU/2 GB is plenty to start), public IPv4, a domain whose DNS you control (Cloudflare API token if using built-in ACME), Docker + compose plugin.
1. **DNS**: create the two A records (docs/dns-ssl.md).
2. **Server**: create a non-root sudo user, SSH keys only, `apt install docker.io docker-compose-v2 ufw`, enable unattended upgrades.
3. **Code**: clone to `/opt/mytunnel`. Create `.env.production` from `.env.example` + `api/.env.example` values: `APP_ENV=production`, `BASE_DOMAIN`, `TUNNEL_SERVER_HOST=tunnel.<domain>` (or the apex), `PUBLIC_HTTPS_PORT=443`, `TLS_MODE=acme` + ACME vars, strong `DB_PASSWORD`, `INTERNAL_API_SECRET` (`openssl rand -hex 32`), Laravel `APP_KEY` (`php artisan key:generate --show`), `APP_DEBUG=false`, `TUNNEL_BASE_DOMAIN`, `TUNNEL_TLS=true`, `TUNNEL_PUBLIC_PORT=443`, `TUNNEL_SERVER_HOST`, `TUNNEL_CONTROL_PORT=4443`. Do not set `DEV_STATIC_TOKEN`. `chmod 600 .env.production`.
4. **Firewall**: `sudo deploy/firewall/ufw.sh` (opens SSH, 443, 4443 only).
5. **Start**: `docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build`, then `docker compose ... exec api php artisan migrate --force` and `... exec api php artisan mytunnel:create-user you@domain --admin`.
6. **Verify**: `https://app.<domain>` loads the dashboard; create a token; `mytunnel login`; `mytunnel http 8000 --server tunnel.<domain>:4443`.
7. **Backups**: cron `deploy/backups/backup-postgres.sh` (daily, 14-day retention); test a restore. Redis holds only ephemeral registry data.
8. **Monitoring**: Prometheus scraping `tunnel-server:9090/metrics` with `deploy/monitoring/alerts.yml`; `/healthz` for uptime checks; ship container logs (JSON) to your log store.
Bare-metal alternative: `deploy/systemd/*.service`, binaries in `/usr/local/bin`, env in `/etc/mytunnel/server.env`.
