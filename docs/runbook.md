# Runbook
- **Tunnel server restart**: agents reconnect automatically (backoff ≤30s) and keep their subdomain; registry entries expire after `TUNNEL_TTL`.
- **Revoke access**: dashboard → Tokens → Revoke (or Admin → Disable user). Live tunnels drop within `REVALIDATE_INTERVAL` (default 60s). Immediate kill: Admin → Disconnect (drops at next heartbeat ≤15s).
- **Rotate INTERNAL_API_SECRET**: change in `.env.production`, restart `api`, `web`, `tunnel-server` together.
- **Cert problems**: check `docker logs tunnel-server` for `acme:`; use `ACME_STAGING=true` to test; ensure the DNS token scope and port 443 reachability.
- **Stale tunnels listed**: `php artisan tunnels:reap`.
- **Restore DB**: see header of `deploy/backups/backup-postgres.sh`.
- **Upgrade**: `git pull && docker compose -f docker-compose.prod.yml up -d --build && docker compose ... exec api php artisan migrate --force`.
