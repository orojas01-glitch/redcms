#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

usage() {
    printf 'Usage: %s\n' "$0"
    printf '%s\n' 'Runs an authenticated Store Lite Products-to-Save rehearsal in one disposable database.'
}

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == "--help" ]]; then
        usage
        exit 0
    fi
    usage >&2
    exit 64
fi

STORE_REPOSITORY="${RED_STORE_LITE_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite}"
STORE_PACKAGE="$STORE_REPOSITORY/package"
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
NODE_BIN="${RED_NODE_BIN:-/Users/oscarrojas/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node}"
PLAYWRIGHT_MODULE="${RED_PLAYWRIGHT_MODULE:-/Users/oscarrojas/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright}"
CHROME_BIN="${RED_CHROME_BIN:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
REHEARSAL_DATABASE="${RED_STORE_LITE_DATABASE:-redcms_store_lite_browser_$$}"
REHEARSAL_USERNAME='store_lite_browser'
REHEARSAL_PASSWORD='StoreLiteBrowser-2026!'
TEMP_ROOT=""
STAGED_PROJECT=""
EVIDENCE_DIR="${RED_STORE_LITE_EVIDENCE_DIR:-}"
ADMIN_DEFAULTS_FILE=""
SERVER_PID=0
SERVER_LOG=""
REHEARSAL_PORT=""
DATABASE_CREATED=0
GRANT_CREATED=0
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
PRIMARY_BEFORE=""
CAFFEINATE_PID=0

if [[ ! -d "$STORE_PACKAGE" || ! -s "$STORE_PACKAGE/addon.json" ]]; then
    printf 'Store Lite package was not found: %s\n' "$STORE_PACKAGE" >&2
    exit 66
fi
if [[ ! -x "$FRANKENPHP_BIN" ]]; then
    printf 'FrankenPHP is missing or not executable: %s\n' "$FRANKENPHP_BIN" >&2
    exit 66
fi
if [[ ! -x "$NODE_BIN" || ! -d "$PLAYWRIGHT_MODULE" || ! -x "$CHROME_BIN" ]]; then
    printf '%s\n' 'Node, Playwright, or Google Chrome is unavailable.' >&2
    exit 66
fi
if [[ "$REHEARSAL_DATABASE" == "$RED_DB_NAME_RESOLVED"
    || ! "$REHEARSAL_DATABASE" =~ ^redcms_store_lite_browser_[A-Za-z0-9_]+$
    || ${#REHEARSAL_DATABASE} -gt 64
]]; then
    printf 'Store Lite rehearsal database name is unsafe: %s\n' "$REHEARSAL_DATABASE" >&2
    exit 64
fi

red_store_lite_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_store_lite_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$REHEARSAL_DATABASE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_store_lite_primary_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$RED_DB_NAME_RESOLVED" \
        --batch --raw --skip-column-names \
        "$@"
}

red_store_lite_port_in_use() {
    local port="$1"
    if command -v lsof >/dev/null 2>&1; then
        lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
        return $?
    fi
    (echo > "/dev/tcp/127.0.0.1/$port") >/dev/null 2>&1
}

red_store_lite_select_port() {
    local attempt=0
    local candidate=0
    while [[ "$attempt" -lt 50 ]]; do
        candidate=$((24000 + (($$ + attempt) % 16000)))
        if ! red_store_lite_port_in_use "$candidate"; then
            REHEARSAL_PORT="$candidate"
            return 0
        fi
        attempt=$((attempt + 1))
    done
    printf '%s\n' 'Could not find an unused Store Lite rehearsal port.' >&2
    return 69
}

red_store_lite_primary_snapshot() {
    red_store_lite_primary_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()),
            (SELECT COUNT(*) FROM RED_Admin),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS(
                '#', RecordID, Username, Password, Alias, AdminType
             ))), 0) FROM RED_Admin),
            (SELECT COUNT(*) FROM RED_Articles)
        );
    "
}

red_store_lite_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_after=""

    trap - EXIT INT TERM
    set +e
    if [[ "$SERVER_PID" -gt 0 ]]; then
        kill -TERM "$SERVER_PID" >/dev/null 2>&1
        wait "$SERVER_PID" >/dev/null 2>&1
        SERVER_PID=0
    fi
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        red_store_lite_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1
        GRANT_CREATED=0
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        red_store_lite_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$REHEARSAL_DATABASE\`;
        " >/dev/null 2>&1
        DATABASE_CREATED=0
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_store_lite_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';
        " 2>/dev/null)"
        grant_output="$(red_store_lite_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"$REHEARSAL_DATABASE"*
        ]]; then
            printf '%s\n' 'Cleanup failure: disposable schema or scoped grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_BEFORE" ]]; then
        primary_after="$(red_store_lite_primary_snapshot 2>/dev/null)"
        if [[ "$primary_after" != "$PRIMARY_BEFORE" ]]; then
            printf '%s\n' 'Cleanup failure: configured primary database fingerprint changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-store-lite-browser."*
    ]]; then
        rm -rf -- "$TEMP_ROOT"
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    if [[ -n "$SERVER_LOG" ]]; then
        rm -f -- "$SERVER_LOG"
    fi
    red_remove_defaults_file
    if [[ "$CAFFEINATE_PID" -gt 0 ]]; then
        kill -TERM "$CAFFEINATE_PID" >/dev/null 2>&1
        wait "$CAFFEINATE_PID" >/dev/null 2>&1
    fi
    if [[ "$cleanup_status" -eq 0 ]]; then
        printf 'Cleanup complete: removed database/grant %s and the staged project.\n' \
            "$REHEARSAL_DATABASE"
    fi
    if [[ "$original_status" -eq 0 && "$cleanup_status" -ne 0 ]]; then
        original_status=1
    fi
    exit "$original_status"
}

trap red_store_lite_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ &
    CAFFEINATE_PID=$!
    printf '%s\n' 'Mac sleep prevention is active for this rehearsal only.'
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-store-lite-browser.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
if [[ -z "$EVIDENCE_DIR" ]]; then
    EVIDENCE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/redcms-store-lite-browser-evidence.XXXXXX")"
fi
if [[ ! "$EVIDENCE_DIR" = /* ]]; then
    printf 'Evidence directory must be an absolute path: %s\n' "$EVIDENCE_DIR" >&2
    exit 64
fi
mkdir -p "$STAGED_PROJECT/addons/redcms/store-lite" "$EVIDENCE_DIR"
chmod 700 "$TEMP_ROOT" "$EVIDENCE_DIR"

rsync -a \
    --exclude='.git' \
    --exclude='addons' \
    --exclude='includes/config.local.php' \
    "$RED_PROJECT_ROOT/" "$STAGED_PROJECT/"
rsync -a "$STORE_PACKAGE/" "$STAGED_PROJECT/addons/redcms/store-lite/"
printf 'Staged clean core plus Store Lite at: %s\n' "$STAGED_PROJECT"

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-store-lite-admin.XXXXXX")"
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
red_store_lite_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_store_lite_primary_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Application database account is unsafe: %s\n' "$APP_ACCOUNT" >&2
    exit 64
fi
if [[ "$(red_store_lite_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';
")" != '0' ]]; then
    printf 'Disposable database already exists; refusing reuse: %s\n' \
        "$REHEARSAL_DATABASE" >&2
    exit 65
fi

PRIMARY_BEFORE="$(red_store_lite_primary_snapshot)"
if [[ -z "$PRIMARY_BEFORE" ]]; then
    printf '%s\n' 'Could not capture the configured primary database fingerprint.' >&2
    exit 67
fi

red_store_lite_admin_mysql --execute="
    CREATE DATABASE \`$REHEARSAL_DATABASE\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
DATABASE_CREATED=1
red_store_lite_admin_mysql --execute="
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
    "$STAGED_PROJECT/scripts/db-migrate.sh"

for migration in \
    '2026-08-07-create-catalog.sql' \
    '2026-08-07-align-media-reference-contract.sql' \
    '2026-08-08-create-product-activity.sql'; do
    red_store_lite_app_mysql < \
        "$STAGED_PROJECT/addons/redcms/store-lite/migrations/$migration"
done

RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$REHEARSAL_DATABASE" \
RED_STORE_LITE_PROJECT_ROOT="$STAGED_PROJECT" \
    "$FRANKENPHP_BIN" php-cli \
    "$SCRIPT_DIR/store-lite-browser-rehearsal-fixture.php" prepare

red_store_lite_select_port
SERVER_LOG="$(mktemp "${TMPDIR:-/tmp}/redcms-store-lite-server.XXXXXX")"
(
    cd "$STAGED_PROJECT"
    export RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED"
    export RED_DB_USER="$RED_DB_USER_RESOLVED"
    export RED_DB_PASS="$RED_DB_PASS_RESOLVED"
    export RED_DB_NAME="$REHEARSAL_DATABASE"
    exec "$FRANKENPHP_BIN" php-server \
        --root "$STAGED_PROJECT" \
        --listen "127.0.0.1:$REHEARSAL_PORT"
) > "$SERVER_LOG" 2>&1 &
SERVER_PID=$!

ready_status=''
for _attempt in $(seq 1 100); do
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
        printf '%s\n' 'Store Lite isolated server exited before readiness:' >&2
        sed -n '1,160p' "$SERVER_LOG" >&2
        exit 1
    fi
    ready_status="$(curl -sS --max-time 1 -o /dev/null -w '%{http_code}' \
        "http://127.0.0.1:$REHEARSAL_PORT/" 2>/dev/null || true)"
    if [[ "$ready_status" == '200' ]]; then
        break
    fi
    sleep 0.1
done
if [[ "$ready_status" != '200' ]]; then
    printf '%s\n' 'Store Lite isolated server did not become ready.' >&2
    sed -n '1,160p' "$SERVER_LOG" >&2
    exit 1
fi

RED_STORE_LITE_BASE_URL="http://127.0.0.1:$REHEARSAL_PORT" \
RED_STORE_LITE_EVIDENCE_DIR="$EVIDENCE_DIR" \
RED_STORE_LITE_USERNAME="$REHEARSAL_USERNAME" \
RED_STORE_LITE_PASSWORD="$REHEARSAL_PASSWORD" \
RED_PLAYWRIGHT_MODULE="$PLAYWRIGHT_MODULE" \
RED_CHROME_BIN="$CHROME_BIN" \
    "$NODE_BIN" "$SCRIPT_DIR/store-lite-browser-rehearsal-qa.cjs"

RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$REHEARSAL_DATABASE" \
RED_STORE_LITE_PROJECT_ROOT="$STAGED_PROJECT" \
    "$FRANKENPHP_BIN" php-cli \
    "$SCRIPT_DIR/store-lite-browser-rehearsal-fixture.php" verify

if grep -Eq \
    'PHP (Warning|Deprecated|Notice|Fatal)|Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]' \
    "$SERVER_LOG"; then
    printf '%s\n' 'Store Lite isolated server log contains a runtime error:' >&2
    grep -En \
        'PHP (Warning|Deprecated|Notice|Fatal)|Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]' \
        "$SERVER_LOG" >&2
    exit 1
fi

printf 'Store Lite authenticated browser rehearsal passed. Evidence: %s\n' \
    "$EVIDENCE_DIR"
