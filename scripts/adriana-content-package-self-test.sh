#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PHP_BIN="${RED_PHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php}"

exec "$PHP_BIN" "$SCRIPT_DIR/adriana-content-package-self-test.php"
