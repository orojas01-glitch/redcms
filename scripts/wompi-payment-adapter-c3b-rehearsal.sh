#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

usage() {
    printf 'Usage: %s\n' "$0"
    printf '%s\n' 'Runs the exact Store Lite 0.1.35 plus Wompi 0.1.4 C3B disposable rehearsal.'
}

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        usage
        exit 0
    fi
    usage >&2
    exit 64
fi

# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

STORE_REPOSITORY="${RED_STORE_LITE_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite}"
WOMPI_REPOSITORY="${RED_WOMPI_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite-wompi}"
STORE_REVISION='f7de77eb1694fb6003340632c5018024753fe1fa'
WOMPI_REVISION="${RED_WOMPI_REVISION:-5f372b3a2e35723f638a03cf089deedc238c99a4}"
WOMPI_VERSION="${RED_WOMPI_VERSION:-0.1.4}"
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
SELF_TEST_SCRIPT="${RED_WOMPI_SELF_TEST_SCRIPT:-wompi-payment-adapter-c3b-self-test.php}"
BEFORE_SELF_TEST_SCRIPT="${RED_WOMPI_BEFORE_SELF_TEST_SCRIPT:-}"
AFTER_SELF_TEST_SCRIPT="${RED_WOMPI_AFTER_SELF_TEST_SCRIPT:-}"
RUN_SUFFIX="$(date +%s)_$$"
DATABASE_NAME="${RED_WOMPI_C3B_DATABASE:-redcms_wompi_c3b_$RUN_SUFFIX}"
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
DATABASE_CREATED=0
GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""
RUN_SUCCEEDED=0
CAFFEINATE_PID=0

red_wompi_c3b_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_wompi_c3b_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_wompi_c3b_primary_snapshot() {
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

red_wompi_c3b_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""

    trap - EXIT INT TERM
    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        red_wompi_c3b_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$DATABASE_NAME\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        red_wompi_c3b_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$DATABASE_NAME\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_wompi_c3b_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='$DATABASE_NAME';
        " 2>/dev/null)"
        grant_output="$(red_wompi_c3b_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$DATABASE_NAME\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: disposable C3B database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_wompi_c3b_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: configured primary database changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-wompi-c3b."*
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
        printf '%s\n' 'Wompi C3B cleanup passed: database:0 grant:0 staged-project:0 primary:unchanged'
    fi
    if [[ "$original_status" -ne 0 ]]; then
        exit "$original_status"
    fi
    exit "$cleanup_status"
}

trap red_wompi_c3b_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ ! "$DATABASE_NAME" =~ ^redcms_(wompi_c3b|payment_adapter_db)_[A-Za-z0-9_]+$
    || ${#DATABASE_NAME} -gt 64
    || "$DATABASE_NAME" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe Wompi C3B database name: %s\n' "$DATABASE_NAME" >&2
    exit 64
fi
for repository in "$STORE_REPOSITORY" "$WOMPI_REPOSITORY"; do
    if [[ ! -d "$repository/.git"
        || ! -s "$repository/package/addon.json"
    ]]; then
        printf 'Required package repository is unavailable: %s\n' \
            "$repository" >&2
        exit 66
    fi
done
if [[ ! -x "$FRANKENPHP_BIN" ]]; then
    printf 'FrankenPHP is unavailable: %s\n' "$FRANKENPHP_BIN" >&2
    exit 66
fi
if [[ "$SELF_TEST_SCRIPT" != 'wompi-payment-adapter-c3b-self-test.php'
    && "$SELF_TEST_SCRIPT" != 'wompi-payment-adapter-c3c1-self-test.php'
    && "$SELF_TEST_SCRIPT" != 'wompi-payment-adapter-c4b4b-self-test.php'
    && "$SELF_TEST_SCRIPT" != 'wompi-payment-adapter-c4b4c-self-test.php'
    && "$SELF_TEST_SCRIPT" != 'wompi-payment-adapter-c4c1-self-test.php'
]]; then
    printf 'Unsupported Wompi disposable self-test: %s\n' \
        "$SELF_TEST_SCRIPT" >&2
    exit 64
fi
if [[ ! -s "$RED_PROJECT_ROOT/scripts/$SELF_TEST_SCRIPT" ]]; then
    printf 'Wompi disposable self-test is unavailable: %s\n' \
        "$SELF_TEST_SCRIPT" >&2
    exit 66
fi
if [[ -n "$BEFORE_SELF_TEST_SCRIPT"
    && "$BEFORE_SELF_TEST_SCRIPT" != 'addon-payment-adapter-enable-self-test.php'
]]; then
    printf 'Unsupported Wompi prerequisite self-test: %s\n' \
        "$BEFORE_SELF_TEST_SCRIPT" >&2
    exit 64
fi
if [[ -n "$AFTER_SELF_TEST_SCRIPT"
    && "$AFTER_SELF_TEST_SCRIPT" != 'wompi-payment-adapter-c4c2-operator-inner.sh'
]]; then
    printf 'Unsupported Wompi post-fixture rehearsal: %s\n' \
        "$AFTER_SELF_TEST_SCRIPT" >&2
    exit 64
fi
if [[ -n "$AFTER_SELF_TEST_SCRIPT"
    && ! -s "$RED_PROJECT_ROOT/scripts/$AFTER_SELF_TEST_SCRIPT"
]]; then
    printf 'Wompi post-fixture rehearsal is unavailable: %s\n' \
        "$AFTER_SELF_TEST_SCRIPT" >&2
    exit 66
fi
if [[ -n "$BEFORE_SELF_TEST_SCRIPT"
    && ! -s "$RED_PROJECT_ROOT/scripts/$BEFORE_SELF_TEST_SCRIPT"
]]; then
    printf 'Wompi prerequisite self-test is unavailable: %s\n' \
        "$BEFORE_SELF_TEST_SCRIPT" >&2
    exit 66
fi
if [[ "$(git -C "$STORE_REPOSITORY" rev-parse HEAD)" != "$STORE_REVISION"
    || -n "$(git -C "$STORE_REPOSITORY" status --short)"
]]; then
    printf 'Store Lite must be clean at %s.\n' "$STORE_REVISION" >&2
    exit 65
fi
if [[ !( "$WOMPI_VERSION" == '0.1.4'
          && "$WOMPI_REVISION" == '5f372b3a2e35723f638a03cf089deedc238c99a4' )
    && !( "$WOMPI_VERSION" == '0.1.5'
          && "$WOMPI_REVISION" == 'cc2ddd03ab54f663a089f7d059d802180e555d15' )
]]; then
    printf 'Unsupported Wompi version/revision pair: %s %s.\n' \
        "$WOMPI_VERSION" "$WOMPI_REVISION" >&2
    exit 64
fi
if [[ "$(git -C "$WOMPI_REPOSITORY" rev-parse HEAD)" != "$WOMPI_REVISION"
    || -n "$(git -C "$WOMPI_REPOSITORY" status --short)"
]]; then
    printf 'Wompi must be clean at %s.\n' "$WOMPI_REVISION" >&2
    exit 65
fi
store_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 64, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$STORE_REPOSITORY/package/addon.json")"
wompi_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 64, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$WOMPI_REPOSITORY/package/addon.json")"
if [[ "$store_version" != '0.1.35' || "$wompi_version" != "$WOMPI_VERSION" ]]; then
    printf 'Exact package versions required; Store Lite=%s Wompi=%s.\n' \
        "$store_version" "$wompi_version" >&2
    exit 65
fi

if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ &
    CAFFEINATE_PID=$!
    printf '%s\n' 'Mac sleep prevention is active for this rehearsal only.'
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-wompi-c3b.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p \
    "$STAGED_PROJECT/addons/redcms/store-lite" \
    "$STAGED_PROJECT/addons/redcms/store-lite-wompi"
rsync -a \
    --exclude='.git' \
    --exclude='addons' \
    --exclude='includes/config.local.php' \
    "$RED_PROJECT_ROOT/" "$STAGED_PROJECT/"
rsync -a \
    "$STORE_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite/"
rsync -a \
    "$WOMPI_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite-wompi/"

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-wompi-c3b-admin.XXXXXX")"
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
red_wompi_c3b_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_wompi_c3b_app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application database account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
database_count="$(red_wompi_c3b_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$DATABASE_NAME';
")"
if [[ "$database_count" != '0' ]]; then
    printf '%s\n' 'Refusing to reuse an existing Wompi C3B database.' >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_wompi_c3b_primary_snapshot)"
if [[ -z "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
    printf '%s\n' 'Could not capture the configured primary database.' >&2
    exit 67
fi

red_wompi_c3b_admin_mysql --execute="
    CREATE DATABASE \`$DATABASE_NAME\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
DATABASE_CREATED=1
red_wompi_c3b_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$DATABASE_NAME\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
GRANT_CREATED=1

"$RED_MYSQL_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$DATABASE_NAME" < "$STAGED_PROJECT/db-structure.sql"
RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$DATABASE_NAME" \
    "$STAGED_PROJECT/scripts/db-migrate.sh" "--database=$DATABASE_NAME"

if [[ -n "$BEFORE_SELF_TEST_SCRIPT" ]]; then
    RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
    RED_DB_USER="$RED_DB_USER_RESOLVED" \
    RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
    RED_DB_NAME="$DATABASE_NAME" \
        "$FRANKENPHP_BIN" php-cli \
        "$STAGED_PROJECT/scripts/$BEFORE_SELF_TEST_SCRIPT"
fi

RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$DATABASE_NAME" \
RED_WOMPI_C3B_PROJECT_ROOT="$STAGED_PROJECT" \
    "$FRANKENPHP_BIN" php-cli \
    "$STAGED_PROJECT/scripts/$SELF_TEST_SCRIPT"

if [[ -n "$AFTER_SELF_TEST_SCRIPT" ]]; then
    RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
    RED_DB_USER="$RED_DB_USER_RESOLVED" \
    RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
    RED_DB_NAME="$DATABASE_NAME" \
    RED_WOMPI_C3B_PROJECT_ROOT="$STAGED_PROJECT" \
    FRANKENPHP_BIN="$FRANKENPHP_BIN" \
        "$STAGED_PROJECT/scripts/$AFTER_SELF_TEST_SCRIPT"
fi

RUN_SUCCEEDED=1
printf '%s\n' 'Wompi C3B disposable lifecycle and registrar rehearsal passed before cleanup.'
