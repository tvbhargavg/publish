# Deploying Publish on a shared server (other projects keep running)

Rehearsed on a dev PC with `docker-compose.shared.yml`: first install, migrations, admin seed, installer,
downloads and a TLS tunnel. NOT yet rehearsed: the DNSExit certificate, the host nginx files, WebSockets.

## What Publish uses
| Port | Reachable from | Purpose |
|---|---|---|
| 8010 | internet (stage 1), localhost (stage 2) | dashboard |
| 47443 | internet | CLI connections |
| 18443 | localhost | tunnels, behind host nginx |
Postgres and Redis are private containers with no host ports. Existing nginx, Postgres, Redis and apps are not changed.
Always use project name `publish`. In every command below:
```
cd /var/www/publish
dc() { docker compose -p publish -f docker-compose.shared.yml --env-file .env.production "$@"; }
```

## Stage 1: dashboard on http://72.61.224.2:8010 (testing only, plain HTTP)
```
git pull origin master
ss -tln | grep -E ':(8010|47443|18443)\b' || echo free
docker ps -a --filter label=com.docker.compose.project=publish   # expect empty on first install
cp -n .env.production.example .env.production && chmod 600 .env.production
```
Edit `.env.production`: DB_PASSWORD, INTERNAL_API_SECRET (`openssl rand -hex 24`), ADMIN_EMAIL (not
admin@example.com), ADMIN_PASSWORD, MAIL_*. Generate APP_KEY: `echo "base64:$(openssl rand -base64 32)"`.
```
dc config | grep -nE 'published|host_ip'        # check before starting
nice -n 15 docker compose -p publish -f docker-compose.shared.yml --env-file .env.production build api
dc up -d postgres redis api web scheduler
dc exec api php artisan migrate --force
dc exec api php artisan db:seed --force
nice -n 15 deploy/release/build-cli.sh tunnel.bg.run.place:47443 0.2.1
curl -sI http://127.0.0.1:8010/login | head -1
```
The Install page shows commands now, but tunnels work only after stage 2.

## Stage 2: domain, HTTPS and tunnels
1. DNS (DNSExit): A records `bg.run.place` and `*` -> 72.61.224.2. This ends the local wifi setup on that domain.
   `dig +short test.bg.run.place @ns10.dnsexit.com`
2. Wildcard certificate (acme.sh, DNSExit plugin: DNSEXIT_API_KEY, DNSEXIT_AUTH_USER, DNSEXIT_AUTH_PASS):
```
mkdir -p /etc/ssl/publish
acme.sh --issue --server letsencrypt --dns dns_dnsexit -d bg.run.place -d '*.bg.run.place'
acme.sh --install-cert -d bg.run.place --fullchain-file /etc/ssl/publish/fullchain.pem \
  --key-file /etc/ssl/publish/key.pem \
  --reloadcmd "chgrp 65532 /etc/ssl/publish/key.pem && chmod 640 /etc/ssl/publish/key.pem && nginx -t && systemctl reload nginx; docker restart publish-tunnel-server-1 || true"
```
   The chgrp is required: the tunnel server runs as user 65532 and otherwise fails with "permission denied".
3. `dc up -d --build tunnel-server && dc logs --tail 5 tunnel-server`
4. New file `/etc/nginx/conf.d/publish.conf` (check first that conf.d is included and no other file uses these names):
```
map $http_upgrade $publish_connection { default upgrade; '' close; }
server {
    listen 443 ssl; listen [::]:443 ssl; server_name app.bg.run.place;
    ssl_certificate /etc/ssl/publish/fullchain.pem; ssl_certificate_key /etc/ssl/publish/key.pem;
    location ^~ /internal { return 404; }
    location / { proxy_pass http://127.0.0.1:8010; proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https; proxy_set_header X-Forwarded-For $remote_addr; }
}
server {
    listen 443 ssl; listen [::]:443 ssl; server_name *.bg.run.place;
    ssl_certificate /etc/ssl/publish/fullchain.pem; ssl_certificate_key /etc/ssl/publish/key.pem;
    client_max_body_size 10m;
    location / { proxy_pass https://127.0.0.1:18443; proxy_ssl_verify off; proxy_http_version 1.1;
        proxy_read_timeout 300s; proxy_set_header Host $host;
        proxy_set_header Upgrade $http_upgrade; proxy_set_header Connection $publish_connection; }
}
server { listen 80; listen [::]:80; server_name bg.run.place *.bg.run.place; return 301 https://$host$request_uri; }
```
   `nginx -t` and only if OK: `systemctl reload nginx`.
5. In `.env.production`: `APP_URL=https://app.bg.run.place`, `PUBLISH_PORTAL_BIND=127.0.0.1`; then
   `dc up -d api scheduler web`.
6. If ufw is active: `ufw allow 47443/tcp`. Do not enable ufw if it is off.
7. From another network: `curl -fsSL https://app.bg.run.place/install.sh | sh`, `publish login`, `publish http 5003`.

## Operate
- Update: `git pull && dc up -d --build && dc exec api php artisan migrate --force` (stage 1: name the services).
- Backup: `dc exec -T postgres pg_dump -U publish publish | gzip > publish-$(date +%F).sql.gz`
- Remove: `dc down`, delete `/etc/nginx/conf.d/publish.conf`, reload nginx. `dc down -v` also DELETES the database.
- Known limit: behind nginx all tunnel visitors share one per-IP rate limit; raise RATE_LIMIT_PER_IP_RPS if needed.
