#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"

if [ -n "${PHP_CLI:-}" ]; then
  if [ ! -x "$PHP_CLI" ]; then
    echo "PHP CLI not found or not executable: $PHP_CLI" >&2
    exit 1
  fi
  exec "$PHP_CLI" "$SCRIPT_DIR/article-upload-self-test.php"
fi

if [ ! -x "$FRANKENPHP_BIN" ]; then
  echo "FrankenPHP CLI not found or not executable: $FRANKENPHP_BIN" >&2
  echo "Set FRANKENPHP_BIN=/path/to/frankenphp or PHP_CLI=/path/to/php-with-mysqli." >&2
  exit 1
fi

exec "$FRANKENPHP_BIN" php-cli "$SCRIPT_DIR/article-upload-self-test.php"

