#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

DATABASE="redcms_addon_destination_completion_$$"
ADMIN_DEFAULTS_FILE=""
DATABASE_CREATED=0
GRANT_CREATED=0
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
PRIMARY_BEFORE=""

admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$DATABASE" \
        --batch --raw --skip-column-names "$@"
}

primary_fingerprint() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$RED_DB_NAME_RESOLVED" \
        --batch --raw --skip-column-names \
        --execute="SELECT CONCAT_WS(':',
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()),
            (SELECT COUNT(*) FROM RED_Admin),
            (SELECT COUNT(*) FROM RED_Articles),
            (SELECT COUNT(*) FROM RED_Schema_Migrations));"
}

cleanup() {
    local status=$?
    local cleanup_status=0
    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        admin_mysql --execute="REVOKE ALL PRIVILEGES ON \`$DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';" >/dev/null 2>&1
        [[ $? -eq 0 ]] || cleanup_status=1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        admin_mysql --execute="DROP DATABASE IF EXISTS \`$DATABASE\`;" \
            >/dev/null 2>&1
        [[ $? -eq 0 ]] || cleanup_status=1
    fi
    if [[ -n "$PRIMARY_BEFORE" ]]; then
        [[ "$(primary_fingerprint 2>/dev/null)" == "$PRIMARY_BEFORE" ]] \
            || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    red_remove_defaults_file
    if [[ "$cleanup_status" -eq 0 && "$DATABASE_CREATED" -eq 1 ]]; then
        printf '%s\n' 'Destination completion cleanup passed: database:0 grant:0 primary:unchanged'
    fi
    if [[ "$status" -eq 0 && "$cleanup_status" -ne 0 ]]; then
        status=1
    fi
    exit "$status"
}
trap cleanup EXIT INT TERM

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        printf 'Usage: %s\n' "$0"
        printf '%s\n' 'Runs the destination completion lifecycle in one disposable current-schema database.'
        exit 0
    fi
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi
if [[ ! "$DATABASE" =~ ^redcms_addon_destination_completion_[0-9]+$ \
    || "$DATABASE" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf '%s\n' 'Unsafe destination completion database identity.' >&2
    exit 65
fi

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-destination-admin.XXXXXX")"
chmod 600 "$ADMIN_DEFAULTS_FILE"
{
    printf '[client]\n'
    printf 'protocol=tcp\n'
    printf 'host=%s\n' "$RED_DB_HOST_RESOLVED"
    printf 'port=%s\n' "$RED_DB_PORT_RESOLVED"
    printf 'user=%s\n' "${RED_ACCEPTANCE_DB_ADMIN_USER:-root}"
    printf 'password=%s\n' "${RED_ACCEPTANCE_DB_ADMIN_PASS:-}"
    printf 'default-character-set=utf8mb4\n'
} > "$ADMIN_DEFAULTS_FILE"

admin_mysql --execute='SELECT 1;' >/dev/null
APP_ACCOUNT="$($RED_MYSQL_BIN \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "--database=$RED_DB_NAME_RESOLVED" \
    --batch --raw --skip-column-names \
    --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$ \
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
PRIMARY_BEFORE="$(primary_fingerprint)"

admin_mysql --execute="CREATE DATABASE \`$DATABASE\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
DATABASE_CREATED=1
admin_mysql --execute="GRANT ALL PRIVILEGES ON \`$DATABASE\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';"
GRANT_CREATED=1

"$RED_MYSQL_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$DATABASE" < "$PROJECT_ROOT/db-structure.sql"
RED_DB_NAME="$DATABASE" "$SCRIPT_DIR/db-migrate.sh" \
    "--database=$DATABASE" >/dev/null

MIGRATION_FILES=("$PROJECT_ROOT"/database/migrations/*.sql)
MIGRATION_COUNT="${#MIGRATION_FILES[@]}"
APPLIED_COUNT="$(app_mysql --execute='SELECT COUNT(*) FROM RED_Schema_Migrations;')"
[[ "$APPLIED_COUNT" == "$MIGRATION_COUNT" ]]

FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
if [[ ! -x "$FRANKENPHP_BIN" ]]; then
    printf '%s\n' 'FrankenPHP is required for the focused rehearsal.' >&2
    exit 66
fi
set +e
OUTPUT="$(RED_DB_HOST="$RED_DB_HOST_PORT" \
    RED_DB_USER="$RED_DB_USER_RESOLVED" \
    RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
    RED_DB_NAME="$DATABASE" \
    "$FRANKENPHP_BIN" php-cli \
    "$PROJECT_ROOT/scripts/addon-component-editor-create-preflight-self-test.php" \
    2>&1)"
TEST_STATUS=$?
set -e
printf '%s\n' "$OUTPUT"
[[ "$TEST_STATUS" -eq 0 ]]
grep -F 'passed (132 assertions).' <<< "$OUTPUT" >/dev/null

printf 'Destination completion rehearsal passed: migrations:%s assertions:132 search:best-effort terminal:durable coordinator:restartable\n' \
    "$MIGRATION_COUNT"
