#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
REHEARSAL_DATABASE="${RED_CHECKOUT_OPERATOR_DATABASE:-redcms_acceptance_ops_$(date +%s)_$$}"
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
DATABASE_CREATED=0
GRANT_CREATED=0
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
PRIMARY_SNAPSHOT_BEFORE=""
RUN_SUCCEEDED=0

admin_mysql() {
    "$RED_MYSQL_BIN" --defaults-extra-file="$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

app_mysql() {
    "$RED_MYSQL_BIN" --defaults-extra-file="$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names "$@"
}

primary_snapshot() {
    app_mysql --database="$RED_DB_NAME_RESOLVED" --execute="
        SELECT CONCAT_WS(':', COUNT(*),
            (SELECT COUNT(*) FROM RED_Schema_Migrations),
            COALESCE(SUM(TABLE_ROWS),0))
        FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE();
    "
}

cleanup() {
    local original_status=$?
    local cleanup_status=0
    local snapshot_after=""
    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        admin_mysql --execute="REVOKE ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.* FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';" >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        admin_mysql --execute="DROP DATABASE IF EXISTS \`$REHEARSAL_DATABASE\`;" >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        snapshot_after="$(primary_snapshot 2>/dev/null)"
        [[ "$snapshot_after" == "$PRIMARY_SNAPSHOT_BEFORE" ]] || cleanup_status=1
    fi
    if [[ -n "$TEMP_ROOT" && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-checkout-operator."* && -d "$TEMP_ROOT" ]]; then
        rm -rf -- "$TEMP_ROOT"
        [[ ! -e "$TEMP_ROOT" ]] || cleanup_status=1
    fi
    [[ -z "$ADMIN_DEFAULTS_FILE" || ! -f "$ADMIN_DEFAULTS_FILE" ]] || rm -f -- "$ADMIN_DEFAULTS_FILE"
    red_remove_defaults_file
    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        echo 'Sandbox Checkout operator rehearsal cleanup passed: database:0 grant:0 staged-project:0 primary:unchanged'
    fi
    [[ "$original_status" -eq 0 ]] || exit "$original_status"
    exit "$cleanup_status"
}
trap cleanup EXIT HUP INT TERM

if [[ $# -ne 0 ]]; then
    echo "Usage: $0" >&2
    exit 64
fi
if [[ ! "$REHEARSAL_DATABASE" =~ ^redcms_acceptance_ops_[A-Za-z0-9_]+$ || ${#REHEARSAL_DATABASE} -gt 48 || "$REHEARSAL_DATABASE" == "$RED_DB_NAME_RESOLVED" ]]; then
    echo "Unsafe rehearsal database: $REHEARSAL_DATABASE" >&2
    exit 64
fi
[[ -x "$FRANKENPHP_BIN" ]] || { echo 'FrankenPHP is required.' >&2; exit 66; }

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-checkout-operator.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p "$STAGED_PROJECT"
git -C "$RED_PROJECT_ROOT" archive --format=tar origin/main \
    | tar -xf - -C "$STAGED_PROJECT"

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-checkout-operator-admin.XXXXXX")"
chmod 600 "$ADMIN_DEFAULTS_FILE"
{
    echo '[client]'
    echo 'protocol=tcp'
    echo "host=$RED_DB_HOST_RESOLVED"
    echo "port=$RED_DB_PORT_RESOLVED"
    echo "user=${RED_ACCEPTANCE_DB_ADMIN_USER:-root}"
    echo "password=${RED_ACCEPTANCE_DB_ADMIN_PASS:-}"
    echo 'default-character-set=utf8mb4'
} > "$ADMIN_DEFAULTS_FILE"
admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
[[ "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$ && "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$ ]] || exit 65
[[ "$(admin_mysql --execute="SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';")" == '0' ]] || exit 65
PRIMARY_SNAPSHOT_BEFORE="$(primary_snapshot)"

admin_mysql --execute="CREATE DATABASE \`$REHEARSAL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
DATABASE_CREATED=1
admin_mysql --execute="GRANT ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.* TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';"
GRANT_CREATED=1
app_mysql "$REHEARSAL_DATABASE" < "$STAGED_PROJECT/db-structure.sql"
RED_DB_NAME="$REHEARSAL_DATABASE" "$STAGED_PROJECT/scripts/db-migrate.sh" >/dev/null

export RED_DB_HOST="$RED_DB_HOST_PORT"
export RED_DB_USER="$RED_DB_USER_RESOLVED"
export RED_DB_PASS="$RED_DB_PASS_RESOLVED"
export RED_DB_NAME="$REHEARSAL_DATABASE"

EVIDENCE_FILE="$TEMP_ROOT/evidence.json"
CONFIRMATIONS_FILE="$TEMP_ROOT/confirmations.json"
RED_DB_NAME="$REHEARSAL_DATABASE" "$FRANKENPHP_BIN" php-cli \
    "$SCRIPT_DIR/addon-sandbox-checkout-transport-operator-rehearsal-fixture.php" \
    "$STAGED_PROJECT" "$EVIDENCE_FILE" "$CONFIRMATIONS_FILE"

json_value() {
    "$RED_PHP_BIN_RESOLVED" -r '$v=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); echo $v[$argv[2]] ?? "";' "$CONFIRMATIONS_FILE" "$1"
}
ACTOR_ID="$(json_value actorAdmin)"
PLAN_SHA="$(json_value planSha256)"
INPUT_SHA="$(json_value inputSha256)"
AUTH_SHA="$(json_value authorizationSha256)"
AUTH_STATE_SHA="$(json_value authorizationStateSha256)"
CLAIM_SHA="$(json_value claimStateSha256)"
START_SHA="$(json_value executionStartSha256)"
BACKUP_SHA="$($RED_PHP_BIN_RESOLVED -r 'echo hash("sha256", "p3e9c3b2-disposable-backup");')"
COMMAND="$STAGED_PROJECT/scripts/admin-sandbox-checkout-transport-double-execute.php"

RED_DB_NAME="$REHEARSAL_DATABASE" "$FRANKENPHP_BIN" php-cli "$COMMAND" \
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE" \
    > "$TEMP_ROOT/dry-run.txt"
grep -F 'DRY RUN:' "$TEMP_ROOT/dry-run.txt" >/dev/null
[[ "$(app_mysql --database="$REHEARSAL_DATABASE" --execute="SELECT CONCAT_WS(':',(SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions WHERE PackageID='redcms.store-lite-stripe-checkout'),(SELECT COUNT(*) FROM RED_Addon_Activity_Log WHERE PackageID='redcms.store-lite-stripe-checkout'));")" == '2:2' ]]

set +e
RED_DB_NAME="$REHEARSAL_DATABASE" "$FRANKENPHP_BIN" php-cli "$COMMAND" \
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE" --apply \
    > "$TEMP_ROOT/refused.txt" 2>&1
REFUSED_STATUS=$?
set -e
[[ "$REFUSED_STATUS" -eq 64 ]]

APPLY_ARGS=(
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE"
    "--confirm-database=$REHEARSAL_DATABASE"
    '--confirm-package=redcms.store-lite-stripe-checkout'
    '--confirm-version=0.1.5' '--confirm-state=enabled'
    "--confirm-plan-sha256=$PLAN_SHA" "--confirm-input-sha256=$INPUT_SHA"
    "--confirm-authorization-sha256=$AUTH_SHA"
    "--confirm-authorization-state-sha256=$AUTH_STATE_SHA"
    "--confirm-claim-state-sha256=$CLAIM_SHA"
    "--confirm-execution-start-sha256=$START_SHA"
    "--confirm-backup-sha256=$BACKUP_SHA"
    '--confirm-operation=checkout.create-sandbox-transport-double'
    '--confirm-target=core-in-memory-transport-double'
    '--confirm-maximum-attempts=1' '--confirm-retry-authorized=no'
    '--confirm-network-authorized=no'
    '--confirm-provider-mutation-authorized=no'
    '--confirm-checkout-creation-authorized=no' '--apply'
)
RED_DB_NAME="$REHEARSAL_DATABASE" "$FRANKENPHP_BIN" php-cli "$COMMAND" \
    "${APPLY_ARGS[@]}" > "$TEMP_ROOT/apply.txt"
grep -F 'Observed the exact bounded core-owned transport-double result.' "$TEMP_ROOT/apply.txt" >/dev/null
[[ "$(app_mysql --database="$REHEARSAL_DATABASE" --execute="SELECT CONCAT_WS(':',(SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions WHERE PackageID='redcms.store-lite-stripe-checkout'),(SELECT COUNT(*) FROM RED_Addon_Activity_Log WHERE PackageID='redcms.store-lite-stripe-checkout'),(SELECT COUNT(*) FROM RED_Addon_Stripe_Checkout_Operator_Rehearsal_Fixture));")" == '4:4:0' ]]

set +e
RED_DB_NAME="$REHEARSAL_DATABASE" "$FRANKENPHP_BIN" php-cli "$COMMAND" \
    "${APPLY_ARGS[@]}" > "$TEMP_ROOT/replay.txt" 2>&1
REPLAY_STATUS=$?
set -e
[[ "$REPLAY_STATUS" -eq 65 ]]
[[ ! -e "$TEMP_ROOT/package-executed" ]]

RUN_SUCCEEDED=1
echo 'Sandbox Checkout P3E-9C3B2 operator rehearsal passed: dry-run:1 refusal:1 apply:1 replay-refused:1 provider-effects:0'
