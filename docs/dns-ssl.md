# Wildcard DNS and SSL
## DNS (no per-tunnel records)
```
A   mydomain.com      -> SERVER_IPV4
A   *.mydomain.com    -> SERVER_IPV4     # covers every tunnel and app.mydomain.com
AAAA (optional, if the server has IPv6)
```
Set `BASE_DOMAIN=mydomain.com`. Dashboard is served at `app.mydomain.com` (override with `DASHBOARD_HOST`).
## Wildcard certificate (DNS-01 is mandatory for wildcards)
Option A (built in): `TLS_MODE=acme`, `ACME_EMAIL`, `ACME_DNS_PROVIDER=cloudflare`, `ACME_DNS_API_TOKEN` (Cloudflare token scoped to Zone:DNS:Edit for that zone only), `ACME_STORAGE=/certs` (persist this volume). Start with `ACME_STAGING=true` to avoid rate limits, then switch to false. Certificates renew automatically.
Option B: any ACME client (certbot/acme.sh with your DNS provider) → `TLS_MODE=file`, `TLS_CERT_FILE`, `TLS_KEY_FILE` (full chain covering `*.mydomain.com` and `mydomain.com`); restart the server after renewal.
## Local development (no domain)
`BASE_DOMAIN=localhost`, `TLS_MODE=none`, run the CLI with `--no-tls`. `*.localhost` resolves to 127.0.0.1 in browsers; with curl use `-H 'Host: abc.localhost:8080'`. For TLS locally, mkcert a wildcard (`mkcert "*.localhost" localhost`) with `TLS_MODE=file` and `mytunnel http 8000 --ca-file rootCA.pem`.
