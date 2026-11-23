# Deploying Publish on a server that already hosts other projects

Principle: **do not touch what exists.** Publish gets its own ports on localhost, its own
containers/DB, and the existing web server (nginx/Apache) simply gets two new server blocks.

## 0. Check before you start (read-only)
```
ss -tlnp | grep -E ':(80|443|4443|8000|8080|5432|6379)\b'   # what is already used
docker ps; df -h; free -m
```
Pick free localhost ports. Examples below: dashboard `127.0.0.1:8100`, tunnel public HTTP `127.0.0.1:8180`,
admin/metrics `127.0.0.1:9101`. The ONLY new public port is the control port **4443/tcp**
(use another if taken, then set `TUNNEL_CONTROL_PORT` and rebuild/seed the CLI via `/install.sh`, which reads it live).

## 1. DNS (DNSExit panel)
```
A  app.bhargav.run.place  -> SERVER_PUBLIC_IP
A  tunnel.bhargav.run.place -> SERVER_PUBLIC_IP
A  *.bhargav.run.place    -> SERVER_PUBLIC_IP
```
Verify: `dig +short anything.bhargav.run.place`.

## 2. Code and env
```
git clone <repo> /opt/publish && cd /opt/publish
cp .env.example .env ; cp api/.env.example api/.env     # fill real values, never commit
```
Key values (all dynamic, change later without rebuilding binaries):
`APP_URL=https://app.bhargav.run.place`, `BASE_DOMAIN=bhargav.run.place`,
`TUNNEL_SERVER_HOST=tunnel.bhargav.run.place`, `TUNNEL_CONTROL_PORT=4443`, `ADMIN_EMAIL`, strong `ADMIN_PASSWORD`,
strong `DB_PASSWORD`/`INTERNAL_API_SECRET`, SMTP settings (rotate the Gmail app password).
Use **`docker-compose.shared.yml`** (NOT `docker-compose.prod.yml`, which grabs port 443). Run `docker compose -p publish -f docker-compose.shared.yml --env-file .env.production up -d` using a **project name** (`-p publish`)
so it cannot collide with other compose projects. Bind published ports to `127.0.0.1` only.

## 3. HTTPS with the existing nginx (no change to other sites)
Wildcard cert needs DNS-01. Use acme.sh with the DNSExit plugin (needs `DNSEXIT_API_KEY`, `DNSEXIT_AUTH_USER`, `DNSEXIT_AUTH_PASS`):
```
acme.sh --issue --dns dns_dnsexit -d bhargav.run.place -d '*.bhargav.run.place'
acme.sh --install-cert -d bhargav.run.place --fullchain-file /etc/ssl/publish/fullchain.pem \
        --key-file /etc/ssl/publish/key.pem --reloadcmd "systemctl reload nginx"
```
If the plugin cannot add TXT records, add the `_acme-challenge` TXT manually in the panel (`--dns` manual mode), or ask DNSExit support.
Two nginx server blocks (add as new files; reload with `nginx -t && systemctl reload nginx`):
```
server { listen 443 ssl; server_name app.bhargav.run.place;
  ssl_certificate /etc/ssl/publish/fullchain.pem; ssl_certificate_key /etc/ssl/publish/key.pem;
  location / { proxy_pass https://127.0.0.1:8443; proxy_ssl_verify off; proxy_set_header Host $host;
               proxy_set_header X-Forwarded-Proto https; proxy_set_header X-Forwarded-For $remote_addr; } }
server { listen 443 ssl; server_name *.bhargav.run.place;   # tunnels
  ssl_certificate /etc/ssl/publish/fullchain.pem; ssl_certificate_key /etc/ssl/publish/key.pem;
  client_max_body_size 50m; proxy_read_timeout 300s;
  location / { proxy_pass https://127.0.0.1:8443; proxy_ssl_verify off; proxy_set_header Host $host;
               proxy_set_header Upgrade $http_upgrade; proxy_set_header Connection $connection_upgrade; } }
```
Other sites keep their own `server_name`, so nginx routes by hostname and they are unaffected.
The tunnel server runs with `TLS_MODE=none` for the public side (nginx terminates TLS). The control port 4443
uses the same certificate (`TLS_MODE=file`, cert/key paths above, readable by the container).

## 4. Firewall
Allow only: 80/443 (already open), **4443/tcp**. Keep 8100/8180/9101/5432/6379 on localhost.
`ufw allow 4443/tcp` (do not enable ufw if it is not already managing the box; add the rule to whatever firewall exists).

## 5. First start
```
docker compose -p publish -f docker-compose.prod.yml up -d
docker compose -p publish exec api php artisan migrate --force && php artisan db:seed --force
deploy/release/build-cli.sh tunnel.bhargav.run.place:4443 0.2.0   # binaries; installers read .env live
```
Open `https://app.bhargav.run.place`, log in as admin, then from another PC run
`curl -fsSL https://app.bhargav.run.place/install.sh | sh` (Windows: `irm https://app.bhargav.run.place/install.ps1 | iex`).

## 6. Safety
- Back up Postgres (`deploy/backups/backup-postgres.sh`), cron it.
- Changing domain/IP later: edit `.env` (`APP_URL`, `BASE_DOMAIN`, `TUNNEL_SERVER_HOST`), restart containers;
  the install page and scripts update automatically. Already-installed CLIs: `publish config server host:port`.
- Roll back: `docker compose -p publish down` and delete the two nginx files; nothing else was modified.

## Caveats (not yet tested live)
`docker-compose.prod.yml`, `Dockerfile.api`, ACME via DNSExit, and WebSocket passthrough are unverified on a real server.
Do a dry run on a staging subdomain first.
