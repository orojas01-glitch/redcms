#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

STORE_REPOSITORY="${RED_STORE_LITE_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite}"
WOMPI_REPOSITORY="${RED_WOMPI_REPOSITORY:-$(dirname "$RED_PROJECT_ROOT")/redcms-store-lite-wompi}"
STORE_REVISION='f7de77eb1694fb6003340632c5018024753fe1fa'
WOMPI_REVISION='5f372b3a2e35723f638a03cf089deedc238c99a4'
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
RUN_SUFFIX="$(date +%s)_$$"
DATABASE_NAME="${RED_WOMPI_C4B4D_DATABASE:-redcms_payment_adapter_db_c4d_$RUN_SUFFIX}"
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
DATABASE_CREATED=0
GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""
CORE_SOURCE_BEFORE=""
STORE_SOURCE_BEFORE=""
WOMPI_SOURCE_BEFORE=""
RUN_SUCCEEDED=0
CAFFEINATE_PID=0
STEP='initialization'

repository_fingerprint() {
    local repository="$1"
    {
        git -C "$repository" rev-parse HEAD
        git -C "$repository" status --porcelain=v1 --untracked-files=all
    } | shasum -a 256 | awk '{print $1}'
}

admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

primary_snapshot() {
    "$RED_MYSQLDUMP_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --single-transaction --skip-lock-tables --no-tablespaces \
        --skip-comments --compact --hex-blob "$RED_DB_NAME_RESOLVED" \
        | shasum -a 256 | awk '{print $1}'
}

php_no_contact() {
    env \
        -u RED_ADDON_SECRET_VALUES_JSON \
        -u WOMPI_PRIVATE_KEY \
        -u WOMPI_INTEGRITY_KEY \
        -u WOMPI_EVENT_SECRET \
        -u HTTP_PROXY \
        -u HTTPS_PROXY \
        -u ALL_PROXY \
        -u http_proxy \
        -u https_proxy \
        -u all_proxy \
        PHP_INI_SCAN_DIR="$TEMP_ROOT/php-ini" \
        "$FRANKENPHP_BIN" php-cli "$@"
}

cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""
    trap - EXIT INT TERM
    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$DATABASE_NAME\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$DATABASE_NAME\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='$DATABASE_NAME';
        " 2>/dev/null)"
        grant_output="$(admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$DATABASE_NAME\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: disposable C4B4D database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: configured primary database changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-wompi-c4b4d."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
    fi
    [[ -z "$TEMP_ROOT" || ! -e "$TEMP_ROOT" ]] || cleanup_status=1
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    red_remove_defaults_file
    if [[ "$CAFFEINATE_PID" -gt 0 ]]; then
        kill -TERM "$CAFFEINATE_PID" >/dev/null 2>&1 || true
        wait "$CAFFEINATE_PID" >/dev/null 2>&1 || true
    fi
    if [[ -n "$CORE_SOURCE_BEFORE"
        && "$(repository_fingerprint "$RED_PROJECT_ROOT")" != "$CORE_SOURCE_BEFORE"
    ]]; then
        printf '%s\n' 'Cleanup failure: core source changed during rehearsal.' >&2
        cleanup_status=1
    fi
    if [[ -n "$STORE_SOURCE_BEFORE"
        && "$(repository_fingerprint "$STORE_REPOSITORY")" != "$STORE_SOURCE_BEFORE"
    ]]; then
        printf '%s\n' 'Cleanup failure: Store Lite source changed during rehearsal.' >&2
        cleanup_status=1
    fi
    if [[ -n "$WOMPI_SOURCE_BEFORE"
        && "$(repository_fingerprint "$WOMPI_REPOSITORY")" != "$WOMPI_SOURCE_BEFORE"
    ]]; then
        printf '%s\n' 'Cleanup failure: Wompi source changed during rehearsal.' >&2
        cleanup_status=1
    fi
    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        printf '%s\n' 'Wompi C4B4D cleanup passed: database:0 grant:0 staged-project:0 evidence:0 environment:clear source-repositories:unchanged primary:unchanged'
    elif [[ "$original_status" -ne 0 ]]; then
        printf 'Wompi C4B4D rehearsal failed at step: %s\n' "$STEP" >&2
    fi
    if [[ "$original_status" -ne 0 ]]; then
        exit "$original_status"
    fi
    exit "$cleanup_status"
}

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        printf 'Usage: %s\n' "$0"
        printf '%s\n' 'Runs the C4B4D dry-run-first Wompi command in a network-disabled disposable environment.'
        exit 0
    fi
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi
if [[ ! "$DATABASE_NAME" =~ ^redcms_payment_adapter_db_c4d_[A-Za-z0-9_]+$
    || ${#DATABASE_NAME} -gt 64
    || "$DATABASE_NAME" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe Wompi C4B4D database name: %s\n' "$DATABASE_NAME" >&2
    exit 64
fi
for repository in "$STORE_REPOSITORY" "$WOMPI_REPOSITORY"; do
    if ! git -C "$repository" rev-parse --git-dir >/dev/null 2>&1 \
        || [[ ! -s "$repository/package/addon.json" ]]; then
        printf 'Required package repository is unavailable: %s\n' "$repository" >&2
        exit 66
    fi
done
if [[ ! -x "$FRANKENPHP_BIN" ]]; then
    printf 'FrankenPHP is unavailable: %s\n' "$FRANKENPHP_BIN" >&2
    exit 66
fi
if [[ "$(git -C "$STORE_REPOSITORY" rev-parse HEAD)" != "$STORE_REVISION"
    || -n "$(git -C "$STORE_REPOSITORY" status --short)"
]]; then
    printf 'Store Lite must be clean at %s.\n' "$STORE_REVISION" >&2
    exit 65
fi
if [[ "$(git -C "$WOMPI_REPOSITORY" rev-parse HEAD)" != "$WOMPI_REVISION"
    || -n "$(git -C "$WOMPI_REPOSITORY" status --short)"
]]; then
    printf 'Wompi must be clean at %s.\n' "$WOMPI_REVISION" >&2
    exit 65
fi
CORE_SOURCE_BEFORE="$(repository_fingerprint "$RED_PROJECT_ROOT")"
STORE_SOURCE_BEFORE="$(repository_fingerprint "$STORE_REPOSITORY")"
WOMPI_SOURCE_BEFORE="$(repository_fingerprint "$WOMPI_REPOSITORY")"

if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ &
    CAFFEINATE_PID=$!
    printf '%s\n' 'Mac sleep prevention is active for this rehearsal only.'
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-wompi-c4b4d.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p \
    "$STAGED_PROJECT/addons/redcms/store-lite" \
    "$STAGED_PROJECT/addons/redcms/store-lite-wompi" \
    "$TEMP_ROOT/php-ini"
rsync -a \
    --exclude='.git' --exclude='addons' \
    --exclude='includes/config.local.php' \
    "$RED_PROJECT_ROOT/" "$STAGED_PROJECT/"
rsync -a "$STORE_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite/"
rsync -a "$WOMPI_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite-wompi/"
printf '%s\n' \
    'allow_url_fopen=0' \
    'display_errors=stderr' \
    'error_reporting=-1' \
    'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect' \
    > "$TEMP_ROOT/php-ini/99-redcms-wompi-no-contact.ini"

STEP='network-disabled-runtime'
php_no_contact \
    "$STAGED_PROJECT/scripts/wompi-payment-adapter-c4b4d-network-probe.php" \
    > "$TEMP_ROOT/network-probe.txt"
grep -Fx 'wompi-c4b4d-no-contact-runtime:ready' \
    "$TEMP_ROOT/network-probe.txt" >/dev/null

STEP='pure-command-contract'
php_no_contact \
    "$STAGED_PROJECT/scripts/wompi-payment-adapter-c4b4d-command-self-test.php" \
    > "$TEMP_ROOT/command-contract.txt"
grep -F '55 assertions' "$TEMP_ROOT/command-contract.txt" >/dev/null

STEP='disposable-database'
ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-wompi-c4b4d-admin.XXXXXX")"
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
APP_ACCOUNT="$(app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application database account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
if [[ "$(admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$DATABASE_NAME';
")" != '0' ]]; then
    printf '%s\n' 'Refusing to reuse an existing C4B4D database.' >&2
    exit 65
fi
PRIMARY_SNAPSHOT_BEFORE="$(primary_snapshot)"
if [[ -z "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
    printf '%s\n' 'Could not capture configured primary database.' >&2
    exit 67
fi
admin_mysql --execute="
    CREATE DATABASE \`$DATABASE_NAME\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
DATABASE_CREATED=1
admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$DATABASE_NAME\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
GRANT_CREATED=1
app_mysql "$DATABASE_NAME" < "$STAGED_PROJECT/db-structure.sql"
RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$DATABASE_NAME" \
    "$STAGED_PROJECT/scripts/db-migrate.sh" "--database=$DATABASE_NAME" \
    >/dev/null

export RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED"
export RED_DB_USER="$RED_DB_USER_RESOLVED"
export RED_DB_PASS="$RED_DB_PASS_RESOLVED"
export RED_DB_NAME="$DATABASE_NAME"
export RED_WOMPI_C3B_PROJECT_ROOT="$STAGED_PROJECT"

STEP='durable-evidence-fixture'
EVIDENCE_FILE="$TEMP_ROOT/evidence.json"
CONFIRMATIONS_FILE="$TEMP_ROOT/confirmations.json"
php_no_contact \
    "$STAGED_PROJECT/scripts/wompi-payment-adapter-c4b4d-rehearsal-fixture.php" \
    "$EVIDENCE_FILE" "$CONFIRMATIONS_FILE" \
    > "$TEMP_ROOT/fixture.txt"
grep -F 'Wompi C4B4D disposable evidence fixture prepared.' \
    "$TEMP_ROOT/fixture.txt" >/dev/null

json_value() {
    "$RED_PHP_BIN_RESOLVED" -r \
        '$v=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); echo $v[$argv[2]] ?? "";' \
        "$CONFIRMATIONS_FILE" "$1"
}
ACTOR_ID="$(json_value actorAdmin)"
DATABASE="$(json_value database)"
PACKAGE="$(json_value package)"
VERSION="$(json_value version)"
CLIENT_SCOPE_SHA="$(json_value clientScopeSha256)"
DATABASE_SHA="$(json_value databaseSha256)"
ACTOR_SHA="$(json_value actorSubjectSha256)"
ORDER_SHA="$(json_value orderSha256)"
PLAN_SHA="$(json_value planSha256)"
WIRE_SHA="$(json_value wireRequestSha256)"
AUTH_SHA="$(json_value authorizationSha256)"
AUTH_STATE_SHA="$(json_value authorizationStateSha256)"
CLAIM_SHA="$(json_value claimSha256)"
CLAIM_STATE_SHA="$(json_value claimStateSha256)"
REQUEST_SHA="$(json_value requestSha256)"
START_SHA="$(json_value executionStartSha256)"
BACKUP_SHA="$("$RED_PHP_BIN_RESOLVED" -r \
    'echo hash("sha256", "c4b4d-disposable-backup");')"
COMMAND="$STAGED_PROJECT/scripts/admin-wompi-no-contact-transport-double-execute.php"

STEP='default-dry-run'
php_no_contact "$COMMAND" \
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE" \
    > "$TEMP_ROOT/dry-run.txt"
grep -F 'DRY RUN: durable authorization, claim, package, and start evidence were revalidated.' \
    "$TEMP_ROOT/dry-run.txt" >/dev/null
if [[ "$(app_mysql --database="$DATABASE_NAME" --execute="
    SELECT CONCAT_WS(':',
      (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
       WHERE PackageID='redcms.store-lite-wompi'
         AND ActionID LIKE 'wompi-no-contact-%'),
      (SELECT COUNT(*) FROM RED_Addon_Activity_Log
       WHERE PackageID='redcms.store-lite-wompi'
         AND DetailCode LIKE 'wompi_no_contact_%'));
")" != '2:2' ]]; then
    printf '%s\n' 'Dry run changed durable evidence.' >&2
    exit 1
fi

STEP='incomplete-apply-refusal'
set +e
php_no_contact "$COMMAND" \
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE" --apply \
    > "$TEMP_ROOT/incomplete-refused.txt" 2>&1
INCOMPLETE_STATUS=$?
set -e
[[ "$INCOMPLETE_STATUS" -eq 64 ]]
grep -F 'Apply requires every exact printed confirmation' \
    "$TEMP_ROOT/incomplete-refused.txt" >/dev/null

APPLY_ARGS=(
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE"
    "--confirm-database=$DATABASE"
    "--confirm-package=$PACKAGE" "--confirm-version=$VERSION"
    '--confirm-state=enabled'
    "--confirm-client-scope-sha256=$CLIENT_SCOPE_SHA"
    "--confirm-database-sha256=$DATABASE_SHA"
    "--confirm-actor-subject-sha256=$ACTOR_SHA"
    "--confirm-order-sha256=$ORDER_SHA"
    "--confirm-plan-sha256=$PLAN_SHA"
    "--confirm-wire-request-sha256=$WIRE_SHA"
    "--confirm-authorization-sha256=$AUTH_SHA"
    "--confirm-authorization-state-sha256=$AUTH_STATE_SHA"
    "--confirm-claim-sha256=$CLAIM_SHA"
    "--confirm-claim-state-sha256=$CLAIM_STATE_SHA"
    "--confirm-request-sha256=$REQUEST_SHA"
    "--confirm-execution-start-sha256=$START_SHA"
    "--confirm-backup-sha256=$BACKUP_SHA"
    '--confirm-operation=checkout.create-sandbox-no-contact-double'
    '--confirm-target=core-sealed-in-memory-double'
    '--confirm-maximum-attempts=1' '--confirm-retry-authorized=no'
    '--confirm-network-disabled=yes'
    '--confirm-provider-contact-denied=yes'
    '--confirm-provider-mutation-denied=yes'
    '--confirm-transaction-creation-denied=yes'
    '--confirm-payment-denied=yes'
    '--confirm-order-mutation-denied=yes' '--apply'
)

STEP='sealed-double-apply'
php_no_contact "$COMMAND" "${APPLY_ARGS[@]}" \
    > "$TEMP_ROOT/apply.txt"
grep -F 'Observed the exact bounded core-owned Wompi sealed-double result.' \
    "$TEMP_ROOT/apply.txt" >/dev/null
grep -F 'No credential, network request, Wompi contact/mutation, transaction, payment, event, order mutation, retry, or client action occurred.' \
    "$TEMP_ROOT/apply.txt" >/dev/null
if [[ "$(app_mysql --database="$DATABASE_NAME" --execute="
    SELECT CONCAT_WS(':',
      (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
       WHERE PackageID='redcms.store-lite-wompi'
         AND ActionID LIKE 'wompi-no-contact-%'),
      (SELECT COUNT(*) FROM RED_Addon_Activity_Log
       WHERE PackageID='redcms.store-lite-wompi'
         AND DetailCode LIKE 'wompi_no_contact_%'),
      (SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Payment_Attempts),
      (SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Event_Receipts));
")" != '4:4:0:0' ]]; then
    printf '%s\n' 'Sealed-double apply produced an unexpected effect.' >&2
    exit 1
fi

STEP='replay-refusal'
set +e
php_no_contact "$COMMAND" "${APPLY_ARGS[@]}" \
    > "$TEMP_ROOT/replay-refused.txt" 2>&1
REPLAY_STATUS=$?
set -e
[[ "$REPLAY_STATUS" -eq 65 ]]
grep -F 'execution_already_started' "$TEMP_ROOT/replay-refused.txt" >/dev/null

RUN_SUCCEEDED=1
printf '%s\n' 'Wompi C4B4D network-disabled operator rehearsal passed: runtime-disabled:1 source-contract:55 dry-run:1 confirmation-refusal:1 sealed-double-apply:1 replay-refused:1 durable-rows:4 audits:4 provider-effects:0'
