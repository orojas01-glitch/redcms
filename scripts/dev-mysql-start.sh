#!/bin/sh
set -eu

MYSQL_BASE="/Users/oscarrojas/Documents/red-cms-dev/mysql-8.4.10-macos15-arm64"
MYSQL_DATA="/Users/oscarrojas/Documents/red-cms-dev/mysql-data"
MYSQL_RUN="/Users/oscarrojas/Documents/red-cms-dev/mysql-run"
MYSQL_LOGS="/Users/oscarrojas/Documents/red-cms-dev/mysql-logs"

if [ -f "$MYSQL_RUN/mysql.pid" ] && kill -0 "$(cat "$MYSQL_RUN/mysql.pid")" 2>/dev/null; then
  echo "MySQL dev server is already running."
  exit 0
fi

mkdir -p "$MYSQL_RUN" "$MYSQL_LOGS"

"$MYSQL_BASE/bin/mysqld" \
  --daemonize \
  --basedir="$MYSQL_BASE" \
  --datadir="$MYSQL_DATA" \
  --port=3307 \
  --bind-address=127.0.0.1 \
  --socket="$MYSQL_RUN/mysql.sock" \
  --pid-file="$MYSQL_RUN/mysql.pid" \
  --log-error="$MYSQL_LOGS/mysql.err"

echo "MySQL dev server started on 127.0.0.1:3307."

