#!/usr/bin/env bash
# Build the `publish` CLI for every platform, with your server address baked in.
# Usage:  deploy/release/build-cli.sh tunnel.example.com:4443 [version]
# Output: dist/cli/publish-<os>-<arch>[.exe] and SHA256SUMS (served by the dashboard at /install).
set -euo pipefail
SERVER="${1:?usage: build-cli.sh <server host:port> [version]}"
VERSION="${2:-0.1.0}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
mkdir -p "$ROOT/dist/cli"
docker run --rm -v "$ROOT":/src -w /src -e SERVER="$SERVER" -e VERSION="$VERSION" golang:1.26 sh -ec '
  for t in linux/amd64 linux/arm64 darwin/amd64 darwin/arm64 windows/amd64; do
    os=${t%/*}; arch=${t#*/}; ext=""; [ "$os" = windows ] && ext=".exe"
    CGO_ENABLED=0 GOOS=$os GOARCH=$arch go build -buildvcs=false -trimpath \
      -ldflags "-s -w -X main.defaultServer=$SERVER -X main.version=$VERSION" \
      -o dist/cli/publish-$os-$arch$ext ./cmd/publish
  done
  cd dist/cli && sha256sum publish-* > SHA256SUMS && chmod 644 publish-* SHA256SUMS'
echo "Built into $ROOT/dist/cli:"; ls -lh "$ROOT/dist/cli"
