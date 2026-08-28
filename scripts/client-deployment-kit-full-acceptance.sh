#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

ANCHOR_DATABASE="${RED_CLIENT_KIT_ANCHOR_DATABASE:-redcms_client_kit_anchor_$(date +%Y%m%d_%H%M%S)_$$}"
ADMIN_DEFAULTS_FILE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
ANCHOR_CREATED=0
ANCHOR_GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""
RUN_SUCCEEDED=0

usage() {
    printf 'Usage: %s\n' "$0"
    printf '%s\n' 'Creates a disposable current-schema anchor, runs full acceptance, and removes the anchor and grant.'
}

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        usage
        exit 0
    fi
    usage >&2
    exit 64
fi

if [[ ! "$ANCHOR_DATABASE" =~ ^redcms_client_kit_anchor_[A-Za-z0-9_]+$
    || ${#ANCHOR_DATABASE} -gt 64
    || "$ANCHOR_DATABASE" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe client-kit anchor database: %s\n' "$ANCHOR_DATABASE" >&2
    exit 64
fi

red_client_kit_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_client_kit_primary_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$RED_DB_NAME_RESOLVED" \
        --batch --raw --skip-column-names \
        "$@"
}

red_client_kit_primary_snapshot() {
    "$RED_MYSQLDUMP_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --single-transaction \
        --skip-lock-tables \
        --no-tablespaces \
        --skip-comments \
        --compact \
        --hex-blob \
        "$RED_DB_NAME_RESOLVED" \
        | shasum -a 256 \
        | awk '{print $1}'
}

red_client_kit_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""

    trap - EXIT INT TERM
    set +e
    if [[ "$ANCHOR_GRANT_CREATED" -eq 1 ]]; then
        red_client_kit_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$ANCHOR_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$ANCHOR_CREATED" -eq 1 ]]; then
        red_client_kit_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$ANCHOR_DATABASE\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_client_kit_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='$ANCHOR_DATABASE';
        " 2>/dev/null)"
        grant_output="$(red_client_kit_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$ANCHOR_DATABASE\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: client-kit anchor database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_client_kit_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: configured primary database changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    red_remove_defaults_file

    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        printf '%s\n' 'Client-kit full acceptance cleanup passed: anchor:0 grant:0 primary:unchanged'
    fi
    if [[ "$original_status" -ne 0 ]]; then
        exit "$original_status"
    fi
    exit "$cleanup_status"
}

trap red_client_kit_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-client-kit-admin.XXXXXX")"
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
red_client_kit_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_client_kit_primary_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application database account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
if [[ "$(red_client_kit_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$ANCHOR_DATABASE';
")" != '0' ]]; then
    printf 'Refusing existing client-kit anchor database: %s\n' "$ANCHOR_DATABASE" >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_client_kit_primary_snapshot)"
if [[ -z "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
    printf '%s\n' 'Could not capture configured primary database.' >&2
    exit 67
fi

red_client_kit_admin_mysql --execute="
    CREATE DATABASE \`$ANCHOR_DATABASE\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
ANCHOR_CREATED=1
red_client_kit_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$ANCHOR_DATABASE\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
ANCHOR_GRANT_CREATED=1

"$RED_MYSQL_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$ANCHOR_DATABASE" < "$RED_PROJECT_ROOT/db-structure.sql"

RED_DB_NAME="$ANCHOR_DATABASE" "$SCRIPT_DIR/db-migrate.sh"
RED_DB_NAME="$ANCHOR_DATABASE" "$SCRIPT_DIR/dev-acceptance.sh"

RUN_SUCCEEDED=1
printf '%s\n' 'Client-kit full acceptance passed before cleanup.'
