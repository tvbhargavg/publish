#!/usr/bin/env bash
# Daily PostgreSQL backup with retention. Cron: 15 2 * * * /opt/publish/deploy/backups/backup-postgres.sh
# Credentials come from the environment (PGHOST, PGUSER, PGPASSWORD, PGDATABASE) or ~/.pgpass. Never hardcode them.
set -euo pipefail
DEST="${BACKUP_DIR:-/var/backups/publish}"
KEEP_DAYS="${KEEP_DAYS:-14}"
umask 077
mkdir -p "$DEST"
f="$DEST/publish-$(date +%F-%H%M).sql.gz"
pg_dump --no-owner --format=plain "${PGDATABASE:-mytunnel}" | gzip -9 > "$f"
gzip -t "$f"
find "$DEST" -name 'publish-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
echo "backup ok: $f"
# Restore: gunzip -c FILE | psql -d mytunnel_restore    (test restores regularly)
