#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
INTEGRATION_DIR="$PROJECT_ROOT/server-integrations/frankenphp-public-mutation-attestation"

if ! command -v go >/dev/null 2>&1; then
  echo 'Go is required for the optional FrankenPHP ingress module test.' >&2
  exit 69
fi

if [ ! -f "$INTEGRATION_DIR/go.mod" ]; then
  echo "FrankenPHP ingress module is missing: $INTEGRATION_DIR" >&2
  exit 66
fi

cd "$INTEGRATION_DIR"
exec go test -mod=readonly ./...
