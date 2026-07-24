#!/bin/sh
set -eu

PHP_CLI="${PHP_CLI:-/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php}"
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

if [ ! -x "$PHP_CLI" ]; then
  echo "PHP CLI not found or not executable: $PHP_CLI" >&2
  echo "Set PHP_CLI=/path/to/php to use a different runtime." >&2
  exit 1
fi

exec "$PHP_CLI" "$SCRIPT_DIR/theme-validate.php" "$@"

