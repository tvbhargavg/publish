#!/usr/bin/env bash
# Default-deny firewall for the tunnel host. Review before running; run as root.
set -euo pipefail
SSH_PORT="${SSH_PORT:-22}"
ufw default deny incoming
ufw default allow outgoing
ufw limit "${SSH_PORT}/tcp" comment 'ssh (rate limited)'
ufw allow 443/tcp comment 'public https (tunnels + dashboard)'
ufw allow "${TUNNEL_CONTROL_PORT:-4443}/tcp" comment 'agent control connections'
# Postgres, Redis, admin/metrics (9090) and the internal API are NOT opened: internal network only.
ufw --force enable
ufw status verbose
