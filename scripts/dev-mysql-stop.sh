#!/bin/sh
set -eu

MYSQL_BASE="/Users/oscarrojas/Documents/red-cms-dev/mysql-8.4.10-macos15-arm64"

"$MYSQL_BASE/bin/mysqladmin" \
  --protocol=tcp \
  -h127.0.0.1 \
  -P3307 \
  -uroot \
  shutdown

echo "MySQL dev server stopped."

