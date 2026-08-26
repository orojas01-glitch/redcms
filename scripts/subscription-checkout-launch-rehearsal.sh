#!/bin/bash

set -euo pipefail

CORE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STORE_ROOT="${RED_STORE_LITE_ROOT:-$(dirname "$CORE_ROOT")/redcms-store-lite}"
STRIPE_ROOT="${RED_STRIPE_ADAPTER_ROOT:-$(dirname "$CORE_ROOT")/redcms-store-lite-stripe-checkout}"

# shellcheck source=/dev/null
source "$CORE_ROOT/scripts/db-common.sh"

FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
REHEARSAL_DATABASE="${RED_SUBSCRIPTION_LAUNCH_DATABASE:-redcms_subscription_launch_$(date +%s)_$$}"
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
DATABASE_CREATED=0
GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""

red_subscription_launch_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_subscription_launch_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_subscription_launch_primary_snapshot() {
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

red_subscription_launch_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""

    trap - EXIT INT TERM
    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        red_subscription_launch_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        red_subscription_launch_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$REHEARSAL_DATABASE\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_subscription_launch_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';
        " 2>/dev/null)"
        grant_output="$(red_subscription_launch_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$REHEARSAL_DATABASE\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: disposable database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_subscription_launch_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: configured primary database changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-subscription-launch."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
        [[ ! -e "$TEMP_ROOT" ]] || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    red_remove_defaults_file

    if [[ "$cleanup_status" -eq 0
        && "$DATABASE_CREATED" -eq 1
        && "$GRANT_CREATED" -eq 1
    ]]; then
        printf '%s\n' 'Subscription launch cleanup passed: database:0 grant:0 staged-project:0 primary:unchanged'
    fi
    if [[ "$original_status" -ne 0 ]]; then
        exit "$original_status"
    fi
    exit "$cleanup_status"
}

trap red_subscription_launch_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ $# -ne 0 ]]; then
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi
if [[ ! "$REHEARSAL_DATABASE" =~ ^redcms_subscription_launch_[A-Za-z0-9_]+$
    || ${#REHEARSAL_DATABASE} -gt 64
    || "$REHEARSAL_DATABASE" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe subscription launch database: %s\n' "$REHEARSAL_DATABASE" >&2
    exit 64
fi
if [[ ! -x "$FRANKENPHP_BIN"
    || ! -s "$STORE_ROOT/package/addon.json"
    || ! -s "$STRIPE_ROOT/package/addon.json"
    || -e "$CORE_ROOT/addons"
]]; then
    printf '%s\n' 'Required clean core or external packages are unavailable.' >&2
    exit 66
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-subscription-launch.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p \
    "$STAGED_PROJECT/addons/redcms/store-lite" \
    "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout"
rsync -a \
    --exclude='.git' \
    --exclude='.codex' \
    --exclude='addons' \
    --exclude='hosting and redcms important keys and password.xlsx' \
    --exclude='includes/config.local.php' \
    "$CORE_ROOT/" "$STAGED_PROJECT/"
rsync -a "$STORE_ROOT/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite/"
rsync -a "$STRIPE_ROOT/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout/"

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-subscription-launch-admin.XXXXXX")"
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
red_subscription_launch_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_subscription_launch_app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application database account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
database_count="$(red_subscription_launch_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';
")"
if [[ "$database_count" != '0' ]]; then
    printf 'Refusing to reuse database: %s\n' "$REHEARSAL_DATABASE" >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_subscription_launch_primary_snapshot)"
[[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]] || exit 67
red_subscription_launch_admin_mysql --execute="
    CREATE DATABASE \`$REHEARSAL_DATABASE\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
DATABASE_CREATED=1
red_subscription_launch_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
GRANT_CREATED=1

"$RED_MYSQL_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$REHEARSAL_DATABASE" < "$STAGED_PROJECT/db-structure.sql"
RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$REHEARSAL_DATABASE" \
    "$STAGED_PROJECT/scripts/db-migrate.sh" \
    "--database=$REHEARSAL_DATABASE"

RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$REHEARSAL_DATABASE" \
RED_SUBSCRIPTION_LAUNCH_PROJECT_ROOT="$STAGED_PROJECT" \
    "$FRANKENPHP_BIN" php-cli \
    "$CORE_ROOT/scripts/subscription-checkout-launch-rehearsal.php"

printf '%s\n' 'Subscription launch rehearsal passed before cleanup.'
