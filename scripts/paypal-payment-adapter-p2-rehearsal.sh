#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

SELF_TEST_SCRIPT='paypal-payment-adapter-p2-disposable-self-test.php'
if [[ $# -eq 1 && "$1" == '--enable' ]]; then
    SELF_TEST_SCRIPT='paypal-payment-adapter-p3-enable-self-test.php'
elif [[ $# -gt 0 ]]; then
    printf 'Usage: %s [--enable]\n' "$0" >&2
    exit 64
fi

# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

STORE_REPOSITORY="${RED_STORE_LITE_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite}"
PAYPAL_REPOSITORY="${RED_PAYPAL_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite-paypal}"
STORE_REVISION="${RED_STORE_LITE_REVISION:-56727d2de0bbd2c476316f62001a429b354c599f}"
PAYPAL_REVISION="${RED_PAYPAL_REVISION:-aab63cba3631f5f16be486231bd45fd0bfa22354}"
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
RUN_SUFFIX="$(date +%s)_$$"
DATABASE_NAME="${RED_PAYPAL_P2_DATABASE:-redcms_paypal_p2_$RUN_SUFFIX}"
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
DATABASE_CREATED=0
GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""
RUN_SUCCEEDED=0

red_paypal_p2_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

red_paypal_p2_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

red_paypal_p2_primary_snapshot() {
    "$RED_MYSQLDUMP_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --single-transaction --skip-lock-tables --no-tablespaces \
        --skip-comments --compact --hex-blob "$RED_DB_NAME_RESOLVED" \
        | shasum -a 256 | awk '{print $1}'
}

red_paypal_p2_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""

    trap - EXIT INT TERM
    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        red_paypal_p2_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$DATABASE_NAME\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        red_paypal_p2_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$DATABASE_NAME\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_paypal_p2_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='$DATABASE_NAME';
        " 2>/dev/null)"
        grant_output="$(red_paypal_p2_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$DATABASE_NAME\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: PayPal database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_paypal_p2_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: primary database changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-paypal-p2."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
        [[ ! -e "$TEMP_ROOT" ]] || cleanup_status=1
    fi
    [[ -z "$ADMIN_DEFAULTS_FILE" ]] || rm -f -- "$ADMIN_DEFAULTS_FILE"
    red_remove_defaults_file
    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        printf '%s\n' 'PayPal P2 cleanup passed: database:0 grant:0 staged-project:0 primary:unchanged'
    fi
    [[ "$original_status" -eq 0 ]] || exit "$original_status"
    exit "$cleanup_status"
}

trap red_paypal_p2_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ ! "$DATABASE_NAME" =~ ^redcms_paypal_p2_[A-Za-z0-9_]+$
    || ${#DATABASE_NAME} -gt 64
    || "$DATABASE_NAME" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe PayPal P2 database name: %s\n' "$DATABASE_NAME" >&2
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

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-paypal-p2.XXXXXX")"
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

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-paypal-p2-admin.XXXXXX")"
chmod 600 "$ADMIN_DEFAULTS_FILE"
{
    printf '[client]\nprotocol=tcp\nhost=%s\nport=%s\n' \
        "$RED_DB_HOST_RESOLVED" "$RED_DB_PORT_RESOLVED"
    printf 'user=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
        "${RED_ACCEPTANCE_DB_ADMIN_USER:-root}" \
        "${RED_ACCEPTANCE_DB_ADMIN_PASS:-}"
} > "$ADMIN_DEFAULTS_FILE"
red_paypal_p2_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_paypal_p2_app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application database account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
database_count="$(red_paypal_p2_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$DATABASE_NAME';
")"
if [[ "$database_count" != '0' ]]; then
    printf '%s\n' 'Refusing to reuse a PayPal disposable database.' >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_paypal_p2_primary_snapshot)"
[[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]] || exit 67
red_paypal_p2_admin_mysql --execute="
    CREATE DATABASE \`$DATABASE_NAME\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
DATABASE_CREATED=1
red_paypal_p2_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$DATABASE_NAME\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
GRANT_CREATED=1

"$RED_MYSQL_BIN" "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$DATABASE_NAME" < "$STAGED_PROJECT/db-structure.sql"
RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$DATABASE_NAME" \
    "$STAGED_PROJECT/scripts/db-migrate.sh" "--database=$DATABASE_NAME"

RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$DATABASE_NAME" \
RED_PAYPAL_P2_PROJECT_ROOT="$STAGED_PROJECT" \
    "$FRANKENPHP_BIN" php-cli \
    "$STAGED_PROJECT/scripts/$SELF_TEST_SCRIPT"

RUN_SUCCEEDED=1
printf '%s\n' 'PayPal P2 disposable lifecycle passed before cleanup.'
