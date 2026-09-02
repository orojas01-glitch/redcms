#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $# -gt 0 ]]; then
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi

# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

STORE_REPOSITORY="${RED_STORE_LITE_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite}"
PAYPAL_REPOSITORY="${RED_PAYPAL_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite-paypal}"
STORE_REVISION='56727d2de0bbd2c476316f62001a429b354c599f'
PAYPAL_REVISION='ab474be3d7075be07abcec340bc712a8da00460f'
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
RUN_SUFFIX="$(date +%s)_$$"
CLIENT_A_DATABASE="${RED_PAYPAL_P4_CLIENT_A_DATABASE:-redcms_paypal_p4_a_$RUN_SUFFIX}"
CLIENT_B_DATABASE="${RED_PAYPAL_P4_CLIENT_B_DATABASE:-redcms_paypal_p4_b_$RUN_SUFFIX}"
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

red_paypal_p4_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

red_paypal_p4_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

red_paypal_p4_primary_snapshot() {
    "$RED_MYSQLDUMP_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --single-transaction --skip-lock-tables --no-tablespaces \
        --skip-comments --compact --hex-blob "$RED_DB_NAME_RESOLVED" \
        | shasum -a 256 | awk '{print $1}'
}

red_paypal_p4_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""

    trap - EXIT INT TERM
    set +e
    if [[ "$CLIENT_A_GRANT_CREATED" -eq 1 ]]; then
        red_paypal_p4_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$CLIENT_A_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$CLIENT_B_GRANT_CREATED" -eq 1 ]]; then
        red_paypal_p4_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$CLIENT_B_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$CLIENT_A_CREATED" -eq 1 ]]; then
        red_paypal_p4_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$CLIENT_A_DATABASE\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$CLIENT_B_CREATED" -eq 1 ]]; then
        red_paypal_p4_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$CLIENT_B_DATABASE\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_paypal_p4_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME IN (
              '$CLIENT_A_DATABASE', '$CLIENT_B_DATABASE'
            );
        " 2>/dev/null)"
        grant_output="$(red_paypal_p4_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$CLIENT_A_DATABASE\`.*"*
            || "$grant_output" == *"\`$CLIENT_B_DATABASE\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: PayPal P4 database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_paypal_p4_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: primary database changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-paypal-p4."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
        [[ ! -e "$TEMP_ROOT" ]] || cleanup_status=1
    fi
    [[ -z "$ADMIN_DEFAULTS_FILE" ]] || rm -f -- "$ADMIN_DEFAULTS_FILE"
    red_remove_defaults_file
    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        printf '%s\n' 'PayPal P4 cleanup passed: databases:0 grants:0 staged-project:0 primary:unchanged'
    fi
    [[ "$original_status" -eq 0 ]] || exit "$original_status"
    exit "$cleanup_status"
}

trap red_paypal_p4_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ ! "$CLIENT_A_DATABASE" =~ ^redcms_paypal_p4_a_[A-Za-z0-9_]+$
    || ! "$CLIENT_B_DATABASE" =~ ^redcms_paypal_p4_b_[A-Za-z0-9_]+$
    || ${#CLIENT_A_DATABASE} -gt 64
    || ${#CLIENT_B_DATABASE} -gt 64
    || "$CLIENT_A_DATABASE" == "$CLIENT_B_DATABASE"
    || "$CLIENT_A_DATABASE" == "$RED_DB_NAME_RESOLVED"
    || "$CLIENT_B_DATABASE" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe PayPal P4 database names: %s %s\n' \
        "$CLIENT_A_DATABASE" "$CLIENT_B_DATABASE" >&2
    exit 64
fi
for repository in "$STORE_REPOSITORY" "$PAYPAL_REPOSITORY"; do
    if [[ ! -d "$repository/.git"
        || ! -s "$repository/package/addon.json"
        || -n "$(git -C "$repository" status --short)"
    ]]; then
        printf 'Required clean package repository unavailable: %s\n' \
            "$repository" >&2
        exit 65
    fi
done
if [[ "$(git -C "$STORE_REPOSITORY" rev-parse HEAD)" != "$STORE_REVISION"
    || "$(git -C "$PAYPAL_REPOSITORY" rev-parse HEAD)" != "$PAYPAL_REVISION"
]]; then
    printf '%s\n' 'Pinned Store Lite or PayPal revision mismatch.' >&2
    exit 65
fi
if [[ ! -x "$FRANKENPHP_BIN" ]]; then
    printf '%s\n' 'FrankenPHP is unavailable.' >&2
    exit 66
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-paypal-p4.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p \
    "$STAGED_PROJECT/addons/redcms/store-lite" \
    "$STAGED_PROJECT/addons/redcms/store-lite-paypal"
rsync -a \
    --exclude='.git' --exclude='addons' \
    --exclude='includes/config.local.php' \
    "$RED_PROJECT_ROOT/" "$STAGED_PROJECT/"
rsync -a "$STORE_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite/"
rsync -a "$PAYPAL_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite-paypal/"

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-paypal-p4-admin.XXXXXX")"
chmod 600 "$ADMIN_DEFAULTS_FILE"
{
    printf '[client]\nprotocol=tcp\nhost=%s\nport=%s\n' \
        "$RED_DB_HOST_RESOLVED" "$RED_DB_PORT_RESOLVED"
    printf 'user=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
        "${RED_ACCEPTANCE_DB_ADMIN_USER:-root}" \
        "${RED_ACCEPTANCE_DB_ADMIN_PASS:-}"
} > "$ADMIN_DEFAULTS_FILE"
red_paypal_p4_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_paypal_p4_app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application database account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
database_count="$(red_paypal_p4_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME IN ('$CLIENT_A_DATABASE', '$CLIENT_B_DATABASE');
")"
if [[ "$database_count" != '0' ]]; then
    printf '%s\n' 'Refusing to reuse a PayPal P4 database.' >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_paypal_p4_primary_snapshot)"
[[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]] || exit 67
for database_name in "$CLIENT_A_DATABASE" "$CLIENT_B_DATABASE"; do
    red_paypal_p4_admin_mysql --execute="
        CREATE DATABASE \`$database_name\`
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    "
    if [[ "$database_name" == "$CLIENT_A_DATABASE" ]]; then
        CLIENT_A_CREATED=1
    else
        CLIENT_B_CREATED=1
    fi
    red_paypal_p4_admin_mysql --execute="
        GRANT ALL PRIVILEGES ON \`$database_name\`.*
        TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
    "
    if [[ "$database_name" == "$CLIENT_A_DATABASE" ]]; then
        CLIENT_A_GRANT_CREATED=1
    else
        CLIENT_B_GRANT_CREATED=1
    fi
    "$RED_MYSQL_BIN" "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
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
RED_PAYPAL_P2_PROJECT_ROOT="$STAGED_PROJECT" \
RED_PAYPAL_P4_CLIENT_B_DATABASE="$CLIENT_B_DATABASE" \
    "$FRANKENPHP_BIN" php-cli \
    "$STAGED_PROJECT/scripts/paypal-payment-adapter-p4-two-client-self-test.php"

RUN_SUCCEEDED=1
printf '%s\n' 'PayPal P4 two-client isolation passed before cleanup.'
