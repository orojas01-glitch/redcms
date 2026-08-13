#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

STORE_REPOSITORY="${RED_STORE_LITE_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite}"
STORE_REVISION='96ae2b2986b6805b33b44f21cf454bd18a67a470'
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
RUN_SUFFIX="$(date +%s)_$$"
CLIENT_A_DATABASE="${RED_STORE_LITE_CLIENT_A_DATABASE:-redcms_sl_iso_a_$RUN_SUFFIX}"
CLIENT_B_DATABASE="${RED_STORE_LITE_CLIENT_B_DATABASE:-redcms_sl_iso_b_$RUN_SUFFIX}"
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
CLIENT_A_CREATED=0
CLIENT_B_CREATED=0
CLIENT_A_GRANT_CREATED=0
CLIENT_B_GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""
RUN_SUCCEEDED=0
CAFFEINATE_PID=0

usage() {
    printf 'Usage: %s\n' "$0"
    printf '%s\n' 'Runs the disposable Store Lite 0.1.29 two-client isolation rehearsal.'
}

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        usage
        exit 0
    fi
    usage >&2
    exit 64
fi

red_store_lite_c3_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_store_lite_c3_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_store_lite_c3_primary_snapshot() {
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

red_store_lite_c3_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""

    trap - EXIT INT TERM
    set +e
    if [[ "$CLIENT_A_GRANT_CREATED" -eq 1 ]]; then
        red_store_lite_c3_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$CLIENT_A_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$CLIENT_B_GRANT_CREATED" -eq 1 ]]; then
        red_store_lite_c3_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$CLIENT_B_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$CLIENT_A_CREATED" -eq 1 ]]; then
        red_store_lite_c3_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$CLIENT_A_DATABASE\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$CLIENT_B_CREATED" -eq 1 ]]; then
        red_store_lite_c3_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$CLIENT_B_DATABASE\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_store_lite_c3_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME IN ('$CLIENT_A_DATABASE', '$CLIENT_B_DATABASE');
        " 2>/dev/null)"
        grant_output="$(red_store_lite_c3_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$CLIENT_A_DATABASE\`.*"*
            || "$grant_output" == *"\`$CLIENT_B_DATABASE\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: a disposable client database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_store_lite_c3_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: configured primary database changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-store-lite-c3."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
        [[ ! -e "$TEMP_ROOT" ]] || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    red_remove_defaults_file
    if [[ "$CAFFEINATE_PID" -gt 0 ]]; then
        kill -TERM "$CAFFEINATE_PID" >/dev/null 2>&1 || true
        wait "$CAFFEINATE_PID" >/dev/null 2>&1 || true
    fi
    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        printf '%s\n' 'Store Lite C3 cleanup passed: databases:0 grants:0 staged-project:0 primary:unchanged'
    fi
    if [[ "$original_status" -ne 0 ]]; then
        exit "$original_status"
    fi
    exit "$cleanup_status"
}

trap red_store_lite_c3_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ ! "$CLIENT_A_DATABASE" =~ ^redcms_sl_iso_a_[A-Za-z0-9_]+$
    || ! "$CLIENT_B_DATABASE" =~ ^redcms_sl_iso_b_[A-Za-z0-9_]+$
    || ${#CLIENT_A_DATABASE} -gt 64
    || ${#CLIENT_B_DATABASE} -gt 64
    || "$CLIENT_A_DATABASE" == "$RED_DB_NAME_RESOLVED"
    || "$CLIENT_B_DATABASE" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe Store Lite C3 database names: %s %s\n' \
        "$CLIENT_A_DATABASE" "$CLIENT_B_DATABASE" >&2
    exit 64
fi
if [[ "$CLIENT_A_DATABASE" == "$CLIENT_B_DATABASE" ]]; then
    printf '%s\n' 'The two client databases must be different.' >&2
    exit 64
fi
if [[ ! -d "$STORE_REPOSITORY/.git"
    || ! -s "$STORE_REPOSITORY/package/addon.json"
    || ! -x "$FRANKENPHP_BIN"
]]; then
    printf '%s\n' 'Store Lite repository or FrankenPHP is unavailable.' >&2
    exit 66
fi
if [[ "$(git -C "$STORE_REPOSITORY" rev-parse HEAD)" != "$STORE_REVISION"
    || -n "$(git -C "$STORE_REPOSITORY" status --short)"
]]; then
    printf 'Store Lite must be clean at %s.\n' "$STORE_REVISION" >&2
    exit 65
fi
store_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$STORE_REPOSITORY/package/addon.json")"
if [[ "$store_version" != '0.1.29' ]]; then
    printf 'Store Lite 0.1.29 is required; found %s.\n' "$store_version" >&2
    exit 65
fi

if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ &
    CAFFEINATE_PID=$!
    printf '%s\n' 'Mac sleep prevention is active for this rehearsal only.'
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-store-lite-c3.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p "$STAGED_PROJECT/addons/redcms/store-lite"
rsync -a \
    --exclude='.git' \
    --exclude='addons' \
    --exclude='includes/config.local.php' \
    "$RED_PROJECT_ROOT/" "$STAGED_PROJECT/"
rsync -a \
    "$STORE_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite/"

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-store-lite-c3-admin.XXXXXX")"
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
red_store_lite_c3_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_store_lite_c3_app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application database account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
database_count="$(red_store_lite_c3_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME IN ('$CLIENT_A_DATABASE', '$CLIENT_B_DATABASE');
")"
if [[ "$database_count" != '0' ]]; then
    printf '%s\n' 'Refusing to reuse an existing Store Lite C3 database.' >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_store_lite_c3_primary_snapshot)"
if [[ -z "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
    printf '%s\n' 'Could not capture the configured primary database.' >&2
    exit 67
fi

red_store_lite_c3_admin_mysql --execute="
    CREATE DATABASE \`$CLIENT_A_DATABASE\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
CLIENT_A_CREATED=1
red_store_lite_c3_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$CLIENT_A_DATABASE\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
CLIENT_A_GRANT_CREATED=1
red_store_lite_c3_admin_mysql --execute="
    CREATE DATABASE \`$CLIENT_B_DATABASE\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
CLIENT_B_CREATED=1
red_store_lite_c3_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$CLIENT_B_DATABASE\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
CLIENT_B_GRANT_CREATED=1

for database_name in "$CLIENT_A_DATABASE" "$CLIENT_B_DATABASE"; do
    printf 'Preparing isolated client database: %s\n' "$database_name"
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "$database_name" < "$STAGED_PROJECT/db-structure.sql"
    RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
    RED_DB_USER="$RED_DB_USER_RESOLVED" \
    RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
    RED_DB_NAME="$database_name" \
        "$STAGED_PROJECT/scripts/db-migrate.sh" "--database=$database_name"
done

RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$CLIENT_A_DATABASE" \
RED_STORE_LITE_CLIENT_B_DATABASE="$CLIENT_B_DATABASE" \
RED_STORE_LITE_PROJECT_ROOT="$STAGED_PROJECT" \
    "$FRANKENPHP_BIN" php-cli \
    "$STAGED_PROJECT/scripts/store-lite-two-client-isolation-rehearsal.php"

RUN_SUCCEEDED=1
printf '%s\n' 'Store Lite Release C3 two-client isolation passed before cleanup.'
