#!/bin/sh
set -eu

FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
PORT="${PORT:-8055}"

if [ ! -x "$FRANKENPHP_BIN" ]; then
  echo "FrankenPHP not found or not executable: $FRANKENPHP_BIN" >&2
  echo "Set FRANKENPHP_BIN=/path/to/frankenphp to use a different runtime." >&2
  exit 1
fi

exec "$FRANKENPHP_BIN" php-server --root . --listen "127.0.0.1:$PORT"

