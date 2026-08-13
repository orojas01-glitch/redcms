#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

STORE_REPOSITORY="${RED_STORE_LITE_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite}"
BASELINE_REF="${RED_STORE_LITE_BASELINE_REF:-0f4253b3ec22d5e6b25bfc723a6c1596eea67d90}"
TARGET_REF="${RED_STORE_LITE_TARGET_REF:-96ae2b2986b6805b33b44f21cf454bd18a67a470}"
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
REHEARSAL_DATABASE="${RED_STORE_LITE_UPGRADE_DATABASE:-redcms_sl_upg_$(date +%s)_$$}"
TEMP_ROOT=""
ADMIN_DEFAULTS_FILE=""
DATABASE_CREATED=0
GRANT_CREATED=0
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
PRIMARY_SNAPSHOT_BEFORE=""
RUN_SUCCEEDED=0

usage() {
    printf 'Usage: %s\n' "$0"
    printf '%s\n' 'Runs an isolated Store Lite 0.1.28 to 0.1.29 failure/resume rehearsal.'
}

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == "--help" ]]; then
        usage
        exit 0
    fi
    usage >&2
    exit 64
fi

red_store_lite_upgrade_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_store_lite_upgrade_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_store_lite_upgrade_primary_snapshot() {
    red_store_lite_upgrade_app_mysql \
        "--database=$RED_DB_NAME_RESOLVED" \
        --execute="
            SELECT CONCAT_WS(':',
                COUNT(*),
                COALESCE(SUM(TABLE_NAME LIKE 'RED_Addon_StoreLite\\_%'), 0),
                (SELECT COUNT(*) FROM RED_Schema_Migrations))
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA=DATABASE();
        "
}

red_store_lite_upgrade_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local database_remaining=""
    local grant_output=""
    local primary_snapshot_after=""

    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        red_store_lite_upgrade_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup failure: disposable Store Lite grant could not be revoked.' >&2
            cleanup_status=1
        fi
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        red_store_lite_upgrade_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$REHEARSAL_DATABASE\`;
        " >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf 'Cleanup failure: database %s could not be removed.\n' "$REHEARSAL_DATABASE" >&2
            cleanup_status=1
        else
            database_remaining="$(red_store_lite_upgrade_admin_mysql --execute="
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
                WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';
            " 2>/dev/null)"
            if [[ "$database_remaining" != '0' ]]; then
                printf 'Cleanup failure: database %s remains.\n' "$REHEARSAL_DATABASE" >&2
                cleanup_status=1
            fi
        fi
    fi
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        grant_output="$(red_store_lite_upgrade_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ $? -ne 0 || "$grant_output" == *"\`$REHEARSAL_DATABASE\`.*"* ]]; then
            printf '%s\n' 'Cleanup failure: disposable Store Lite grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_store_lite_upgrade_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0 || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
            printf '%s\n' 'Cleanup failure: configured primary database boundary changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT" \
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-store-lite-upgrade."* \
        && -d "$TEMP_ROOT" ]]; then
        rm -rf -- "$TEMP_ROOT"
        if [[ -e "$TEMP_ROOT" ]]; then
            printf '%s\n' 'Cleanup failure: staged package directory remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    red_remove_defaults_file

    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        printf 'Store Lite upgrade rehearsal cleanup passed: database:0 grant:0 staged-project:0 primary:unchanged\n'
    fi
    if [[ "$original_status" -ne 0 ]]; then
        exit "$original_status"
    fi
    exit "$cleanup_status"
}

trap red_store_lite_upgrade_cleanup EXIT

if [[ ! "$REHEARSAL_DATABASE" =~ ^redcms_sl_upg_[A-Za-z0-9_]+$ \
    || ${#REHEARSAL_DATABASE} -gt 64 \
    || "$REHEARSAL_DATABASE" == "$RED_DB_NAME_RESOLVED" ]]; then
    printf 'Unsafe Store Lite upgrade database name: %s\n' "$REHEARSAL_DATABASE" >&2
    exit 64
fi
if [[ ! -d "$STORE_REPOSITORY/.git" \
    || ! -f "$STORE_REPOSITORY/package/addon.json" ]]; then
    printf 'Store Lite repository is unavailable: %s\n' "$STORE_REPOSITORY" >&2
    exit 66
fi
if [[ ! -x "$FRANKENPHP_BIN" ]]; then
    printf 'FrankenPHP is missing or not executable: %s\n' "$FRANKENPHP_BIN" >&2
    exit 66
fi
if ! git -C "$STORE_REPOSITORY" cat-file -e "$BASELINE_REF^{commit}"; then
    printf 'Store Lite baseline commit is unavailable: %s\n' "$BASELINE_REF" >&2
    exit 66
fi
if ! git -C "$STORE_REPOSITORY" cat-file -e "$TARGET_REF^{commit}"; then
    printf 'Store Lite target commit is unavailable: %s\n' "$TARGET_REF" >&2
    exit 66
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-store-lite-upgrade.XXXXXX")"
BASELINE_SOURCE="$TEMP_ROOT/baseline-source"
TARGET_SOURCE="$TEMP_ROOT/target-source"
BASELINE_PROJECT="$TEMP_ROOT/baseline-project"
TARGET_PROJECT="$TEMP_ROOT/target-project"
mkdir -p \
    "$BASELINE_SOURCE" \
    "$TARGET_SOURCE" \
    "$BASELINE_PROJECT/addons/redcms/store-lite" \
    "$TARGET_PROJECT/addons/redcms/store-lite"
git -C "$STORE_REPOSITORY" archive \
    --format=tar \
    --output="$TEMP_ROOT/store-lite-0.1.28.tar" \
    "$BASELINE_REF" \
    package
tar -xf "$TEMP_ROOT/store-lite-0.1.28.tar" -C "$BASELINE_SOURCE"
git -C "$STORE_REPOSITORY" archive \
    --format=tar \
    --output="$TEMP_ROOT/store-lite-0.1.29.tar" \
    "$TARGET_REF" \
    package
tar -xf "$TEMP_ROOT/store-lite-0.1.29.tar" -C "$TARGET_SOURCE"
rsync -a \
    "$BASELINE_SOURCE/package/" \
    "$BASELINE_PROJECT/addons/redcms/store-lite/"
rsync -a \
    "$TARGET_SOURCE/package/" \
    "$TARGET_PROJECT/addons/redcms/store-lite/"

baseline_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$BASELINE_PROJECT/addons/redcms/store-lite/addon.json")"
target_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$TARGET_PROJECT/addons/redcms/store-lite/addon.json")"
if [[ "$baseline_version" != '0.1.28' || "$target_version" != '0.1.29' ]]; then
    printf 'Expected Store Lite 0.1.28 -> 0.1.29, found %s -> %s.\n' \
        "$baseline_version" "$target_version" >&2
    exit 65
fi

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-store-lite-upgrade-admin.XXXXXX")"
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
red_store_lite_upgrade_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_store_lite_upgrade_app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$ \
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$ ]]; then
    printf 'Application database account is unsafe: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
database_exists="$(red_store_lite_upgrade_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';
")"
if [[ "$database_exists" != '0' ]]; then
    printf 'Refusing to reuse existing database: %s\n' "$REHEARSAL_DATABASE" >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_store_lite_upgrade_primary_snapshot)"
red_store_lite_upgrade_admin_mysql --execute="
    CREATE DATABASE \`$REHEARSAL_DATABASE\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
DATABASE_CREATED=1
red_store_lite_upgrade_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
GRANT_CREATED=1

printf 'Importing clean installer into disposable database: %s\n' "$REHEARSAL_DATABASE"
"$RED_MYSQL_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$REHEARSAL_DATABASE" < "$RED_PROJECT_ROOT/db-structure.sql"
RED_DB_NAME="$REHEARSAL_DATABASE" \
    "$SCRIPT_DIR/db-migrate.sh" "--database=$REHEARSAL_DATABASE"

printf 'Rehearsing real Store Lite %s -> %s upgrade with forced failure and resume.\n' \
    "$baseline_version" "$target_version"
RED_DB_NAME="$REHEARSAL_DATABASE" \
RED_STORE_LITE_BASELINE_PROJECT_ROOT="$BASELINE_PROJECT" \
RED_STORE_LITE_TARGET_PROJECT_ROOT="$TARGET_PROJECT" \
    "$FRANKENPHP_BIN" php-cli \
    "$SCRIPT_DIR/store-lite-upgrade-rehearsal.php"

RUN_SUCCEEDED=1
printf '%s\n' 'Store Lite real-package upgrade rehearsal passed before cleanup.'
