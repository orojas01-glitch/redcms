#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

ADAPTER_ROOT="${RED_STRIPE_ADAPTER_ROOT:-/Users/oscarrojas/Documents/redcms-store-lite-stripe-checkout}"
STORE_ROOT="${RED_STORE_LITE_ROOT:-/Users/oscarrojas/Documents/redcms-store-lite}"
CORE_REVISION="${RED_CHECKOUT_REAL_POST_CORE_REVISION:-origin/main}"
EXPECTED_ADAPTER_COMMIT="3e61ea4ac74293464db1b779703f79d071eb2d40"
EXPECTED_STORE_COMMIT="f7de77eb1694fb6003340632c5018024753fe1fa"
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
REHEARSAL_DATABASE="${RED_CHECKOUT_REAL_POST_DATABASE:-redcms_acceptance_d4r_$(date +%s)_$$}"
API_REFERENCE="config:p3e9d4c2-stripe-secret-key"
RECOVERY_SECRET_FILE=""
CONFIRM_PROVIDER_RECOVERY=""
RECOVERY_NETWORK_MODE="${RED_D4D_RECOVERY_NETWORK_MODE:-provider}"
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
DATABASE_CREATED=0
GRANT_CREATED=0
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
PRIMARY_SNAPSHOT_BEFORE=""
RUN_SUCCEEDED=0
STEP="initialization"

repository_fingerprint() {
    local repository="$1"
    {
        git -C "$repository" rev-parse HEAD
        git -C "$repository" status --porcelain=v1 --untracked-files=all
    } | shasum -a 256 | awk '{print $1}'
}

tree_fingerprint() {
    local root="$1"
    find "$root" -type f | LC_ALL=C sort | while IFS= read -r file; do
        shasum -a 256 "$file"
    done | shasum -a 256 | awk '{print $1}'
}

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
        SELECT SHA2(CONCAT_WS(':',
            DATABASE(),
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()),
            COALESCE((SELECT GROUP_CONCAT(
                CONCAT(TABLE_NAME, '/', COALESCE(ENGINE, ''), '/',
                    COALESCE(TABLE_ROWS, 0))
                ORDER BY TABLE_NAME SEPARATOR '|')
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()), '')), 256);
    "
}

php_no_contact() {
    env \
        -u RED_ADDON_SECRET_VALUES_JSON \
        -u STRIPE_SECRET_KEY \
        -u STRIPE_API_KEY \
        -u HTTP_PROXY \
        -u HTTPS_PROXY \
        -u ALL_PROXY \
        PHP_INI_SCAN_DIR="$TEMP_ROOT/php-ini" \
        RED_ADDON_SECRET_REFERENCES="$API_REFERENCE" \
        "$FRANKENPHP_BIN" php-cli "$@"
}

php_real_apply() {
    local secret_json=""
    local status=0
    secret_json="$(<"$RECOVERY_SECRET_FILE")"
    env \
        -u STRIPE_SECRET_KEY \
        -u STRIPE_API_KEY \
        -u HTTP_PROXY \
        -u HTTPS_PROXY \
        -u ALL_PROXY \
        PHP_INI_SCAN_DIR="$TEMP_ROOT/php-ini-contact" \
        RED_ADDON_SECRET_REFERENCES="$API_REFERENCE" \
        RED_ADDON_SECRET_VALUES_JSON="$secret_json" \
        "$FRANKENPHP_BIN" php-cli "$@" || status=$?
    secret_json=""
    return "$status"
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
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-real-post-no-contact."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
    fi
    [[ -z "$TEMP_ROOT" || ! -e "$TEMP_ROOT" ]] || cleanup_status=1
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    if [[ "$RECOVERY_SECRET_FILE" == '/private/tmp/redcms-stripe-d4d-recovery-secret-values.json'
        && -f "$RECOVERY_SECRET_FILE"
        && ! -L "$RECOVERY_SECRET_FILE"
    ]]; then
        rm -f -- "$RECOVERY_SECRET_FILE"
    fi
    red_remove_defaults_file
    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        echo 'P3E-9D4D recovery cleanup passed: database:0 grant:0 staged-project:0 evidence:0 secret-file:0 environment:clear source-repositories:unchanged primary:unchanged'
    elif [[ "$original_status" -ne 0 ]]; then
        echo "P3E-9D4C2 rehearsal failed at step: $STEP" >&2
    fi
    [[ "$original_status" -eq 0 ]] || exit "$original_status"
    exit "$cleanup_status"
}
trap cleanup EXIT HUP INT TERM

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 --secret-values-file=/private/tmp/redcms-stripe-d4d-recovery-secret-values.json --confirm-provider-recovery=yes" >&2
    exit 64
fi
for argument in "$@"; do
    case "$argument" in
        --secret-values-file=*)
            RECOVERY_SECRET_FILE="${argument#--secret-values-file=}"
            ;;
        --confirm-provider-recovery=*)
            CONFIRM_PROVIDER_RECOVERY="${argument#--confirm-provider-recovery=}"
            ;;
        *)
            echo 'Unknown D4D recovery argument.' >&2
            exit 64
            ;;
    esac
done
if [[ "$RECOVERY_SECRET_FILE" != '/private/tmp/redcms-stripe-d4d-recovery-secret-values.json'
    || "$CONFIRM_PROVIDER_RECOVERY" != 'yes'
    || ! "$RECOVERY_NETWORK_MODE" =~ ^(provider|offline)$
]]; then
    echo 'Exact D4D recovery secret path and provider confirmation are required.' >&2
    exit 64
fi
if [[ ! "$REHEARSAL_DATABASE" =~ ^redcms_acceptance_d4r_[A-Za-z0-9_]+$
    || ${#REHEARSAL_DATABASE} -gt 56
    || "$REHEARSAL_DATABASE" == "$RED_DB_NAME_RESOLVED"
]]; then
    echo "Unsafe D4D recovery database: $REHEARSAL_DATABASE" >&2
    exit 64
fi
if [[ ! -f "$RECOVERY_SECRET_FILE"
    || -L "$RECOVERY_SECRET_FILE"
    || "$(stat -f '%Lp' "$RECOVERY_SECRET_FILE")" != '600'
    || "$(wc -c < "$RECOVERY_SECRET_FILE" | tr -d ' ')" -gt 8192
]]; then
    echo 'Exact permission-restricted recovery secret inventory is unavailable.' >&2
    exit 66
fi
"$RED_PHP_BIN_RESOLVED" -r '
    $v=json_decode(file_get_contents($argv[1]),true,4,JSON_THROW_ON_ERROR);
    $k="config:p3e9d4c2-stripe-secret-key";
    exit(is_array($v) && array_keys($v)===[$k]
        && is_string($v[$k]) && preg_match("/^rk_test_[A-Za-z0-9_]{20,}$/D",$v[$k])===1 ? 0 : 1);
' "$RECOVERY_SECRET_FILE"
if [[ ! -x "$FRANKENPHP_BIN"
    || ! -d "$ADAPTER_ROOT/.git"
    || ! -d "$STORE_ROOT/.git"
]]; then
    echo 'Exact FrankenPHP or external package source is unavailable.' >&2
    exit 66
fi

CORE_COMMIT="$(git -C "$RED_PROJECT_ROOT" rev-parse "$CORE_REVISION^{commit}")"
git -C "$ADAPTER_ROOT" cat-file -e "$EXPECTED_ADAPTER_COMMIT^{commit}" || {
    echo 'Exact reviewed adapter diagnostic commit is unavailable.' >&2
    exit 65
}
git -C "$STORE_ROOT" cat-file -e "$EXPECTED_STORE_COMMIT^{commit}" || {
    echo 'Exact historical Store Lite 0.1.35 commit is unavailable.' >&2
    exit 65
}
CORE_SOURCE_BEFORE="$(repository_fingerprint "$RED_PROJECT_ROOT")"
ADAPTER_SOURCE_BEFORE="$(repository_fingerprint "$ADAPTER_ROOT")"
STORE_SOURCE_BEFORE="$(repository_fingerprint "$STORE_ROOT")"

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-real-post-no-contact.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p \
    "$STAGED_PROJECT/addons/redcms/store-lite" \
    "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout" \
    "$TEMP_ROOT/php-ini" \
    "$TEMP_ROOT/php-ini-contact"
git -C "$RED_PROJECT_ROOT" archive --format=tar "$CORE_COMMIT" \
    | tar -xf - -C "$STAGED_PROJECT"
git -C "$ADAPTER_ROOT" archive --format=tar \
    "$EXPECTED_ADAPTER_COMMIT:package" \
    | tar -xf - -C "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout"
git -C "$STORE_ROOT" archive --format=tar \
    "$EXPECTED_STORE_COMMIT:package" \
    | tar -xf - -C "$STAGED_PROJECT/addons/redcms/store-lite"

cat > "$TEMP_ROOT/php-ini/99-redcms-no-contact.ini" <<'INI'
allow_url_fopen=0
display_errors=stderr
error_reporting=-1
disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect
INI
if [[ "$RECOVERY_NETWORK_MODE" == 'offline' ]]; then
cat > "$TEMP_ROOT/php-ini-contact/99-redcms-contact.ini" <<'INI'
allow_url_fopen=0
display_errors=stderr
error_reporting=-1
disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect
INI
else
cat > "$TEMP_ROOT/php-ini-contact/99-redcms-contact.ini" <<'INI'
display_errors=stderr
error_reporting=-1
INI
fi
cat > "$TEMP_ROOT/no-contact-probe.php" <<'PHP'
<?php
$disabled = ['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen',
    'stream_socket_client', 'socket_create', 'socket_connect'];
foreach ($disabled as $function) {
    if (function_exists($function)) {
        fwrite(STDERR, "Network function remains available: $function\n");
        exit(1);
    }
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
    fwrite(STDERR, "URL streams remain available.\n");
    exit(1);
}
echo "no-contact-runtime:ready\n";
PHP
STEP="no-contact-runtime"
php_no_contact "$TEMP_ROOT/no-contact-probe.php" \
    > "$TEMP_ROOT/no-contact-runtime.txt"
grep -Fx 'no-contact-runtime:ready' "$TEMP_ROOT/no-contact-runtime.txt" >/dev/null
[[ -z "${RED_ADDON_SECRET_VALUES_JSON:-}" ]]

STEP="staged-source-contract"
ADAPTER_MANIFEST="$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout/addon.json"
STORE_MANIFEST="$STAGED_PROJECT/addons/redcms/store-lite/addon.json"
ADAPTER_VERSION="$($RED_PHP_BIN_RESOLVED -r '$m=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); echo $m["version"] ?? "";' "$ADAPTER_MANIFEST")"
STORE_VERSION="$($RED_PHP_BIN_RESOLVED -r '$m=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); echo $m["version"] ?? "";' "$STORE_MANIFEST")"
[[ "$ADAPTER_VERSION" == '0.1.8' && "$STORE_VERSION" == '0.1.35' ]]
php_no_contact \
    "$STAGED_PROJECT/scripts/addon-sandbox-checkout-real-post-operator-command-self-test.php" \
    > "$TEMP_ROOT/source-contract.txt"
grep -F '74 assertions' "$TEMP_ROOT/source-contract.txt" >/dev/null

STEP="disposable-database"
ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-real-post-admin.XXXXXX")"
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
[[ "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    && "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]] || exit 65
[[ "$(admin_mysql --execute="SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';")" == '0' ]] || exit 65
PRIMARY_SNAPSHOT_BEFORE="$(primary_snapshot)"

admin_mysql --execute="CREATE DATABASE \`$REHEARSAL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
DATABASE_CREATED=1
admin_mysql --execute="GRANT ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.* TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';"
GRANT_CREATED=1
app_mysql "$REHEARSAL_DATABASE" < "$STAGED_PROJECT/db-structure.sql"
RED_DB_HOST="$RED_DB_HOST_PORT" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$REHEARSAL_DATABASE" \
    "$STAGED_PROJECT/scripts/db-migrate.sh" >/dev/null

export RED_DB_HOST="$RED_DB_HOST_PORT"
export RED_DB_USER="$RED_DB_USER_RESOLVED"
export RED_DB_PASS="$RED_DB_PASS_RESOLVED"
export RED_DB_NAME="$REHEARSAL_DATABASE"
export RED_ADDON_SECRET_REFERENCES="$API_REFERENCE"
unset RED_ADDON_SECRET_VALUES_JSON STRIPE_SECRET_KEY STRIPE_API_KEY
unset HTTP_PROXY HTTPS_PROXY ALL_PROXY

STEP="evidence-fixture"
EVIDENCE_FILE="$TEMP_ROOT/evidence.json"
CONFIRMATIONS_FILE="$TEMP_ROOT/confirmations.json"
php_no_contact \
    "$STAGED_PROJECT/scripts/addon-sandbox-checkout-real-post-rehearsal-fixture.php" \
    "$STAGED_PROJECT" "$EVIDENCE_FILE" "$CONFIRMATIONS_FILE" \
    > "$TEMP_ROOT/fixture.txt"
grep -F 'P3E-9D4C2 non-secret dry-run evidence prepared.' \
    "$TEMP_ROOT/fixture.txt" >/dev/null

json_value() {
    "$RED_PHP_BIN_RESOLVED" -r '$v=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); echo $v[$argv[2]] ?? "";' "$CONFIRMATIONS_FILE" "$1"
}
ACTOR_ID="$(json_value actorAdmin)"
DATABASE_SHA="$(json_value databaseSha256)"
PACKAGE="$(json_value package)"
VERSION="$(json_value version)"
STORE_PACKAGE="$(json_value storePackage)"
STORE_VERSION="$(json_value storeVersion)"
PREFLIGHT_PLAN_SHA="$(json_value preflightPlanSha256)"
PREFLIGHT_START_SHA="$(json_value preflightStartIdentitySha256)"
PREFLIGHT_RESULT_SHA="$(json_value preflightResultIdentitySha256)"
INPUT_SHA="$(json_value inputSha256)"
SYNTHETIC_PLAN_SHA="$(json_value syntheticPlanSha256)"
CONTRACT_SHA="$(json_value contractSha256)"
REQUEST_SHA="$(json_value requestSha256)"
ORDER_SHA="$(json_value orderSnapshotSha256)"
AUTH_SHA="$(json_value authorizationSha256)"
AUTH_STATE_SHA="$(json_value authorizationStateSha256)"
CLAIM_SHA="$(json_value claimStateSha256)"
START_SHA="$(json_value executionStartSha256)"
SECRET_AVAILABILITY_SHA="$(json_value secretAvailabilitySha256)"
BACKUP_SHA="$($RED_PHP_BIN_RESOLVED -r 'echo hash("sha256", "p3e9d4c2-disposable-backup");')"
COMMAND="$STAGED_PROJECT/scripts/admin-sandbox-checkout-real-post-execute.php"
STAGED_BEFORE="$(tree_fingerprint "$STAGED_PROJECT")"

STEP="dry-run"
php_no_contact "$COMMAND" \
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE" \
    > "$TEMP_ROOT/dry-run.txt"
grep -F 'DRY RUN: D4 authorization, claim, package, request, secret-availability, and start identities were revalidated.' \
    "$TEMP_ROOT/dry-run.txt" >/dev/null
grep -F 'No secret value was resolved, no registrar or handler ran' \
    "$TEMP_ROOT/dry-run.txt" >/dev/null
! grep -F 'Attempt consumed: yes' "$TEMP_ROOT/dry-run.txt" >/dev/null

STEP="incomplete-apply-refusal"
set +e
php_no_contact "$COMMAND" \
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE" --apply \
    > "$TEMP_ROOT/incomplete-refused.txt" 2>&1
INCOMPLETE_STATUS=$?
set -e
[[ "$INCOMPLETE_STATUS" -eq 64 ]]
grep -F 'Apply requires every exact printed identity' \
    "$TEMP_ROOT/incomplete-refused.txt" >/dev/null

APPLY_ARGS=(
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE_FILE"
    "--confirm-database=$REHEARSAL_DATABASE"
    "--confirm-database-sha256=$DATABASE_SHA"
    "--confirm-package=$PACKAGE" "--confirm-version=$VERSION"
    '--confirm-state=enabled'
    "--confirm-store-package=$STORE_PACKAGE"
    "--confirm-store-version=$STORE_VERSION"
    "--confirm-preflight-plan-sha256=$PREFLIGHT_PLAN_SHA"
    "--confirm-preflight-start-identity-sha256=$PREFLIGHT_START_SHA"
    "--confirm-preflight-result-identity-sha256=$PREFLIGHT_RESULT_SHA"
    "--confirm-input-sha256=$INPUT_SHA"
    "--confirm-synthetic-plan-sha256=$SYNTHETIC_PLAN_SHA"
    "--confirm-contract-sha256=$CONTRACT_SHA"
    "--confirm-request-sha256=$REQUEST_SHA"
    "--confirm-order-snapshot-sha256=$ORDER_SHA"
    "--confirm-authorization-sha256=$AUTH_SHA"
    "--confirm-authorization-state-sha256=$AUTH_STATE_SHA"
    "--confirm-claim-state-sha256=$CLAIM_SHA"
    "--confirm-execution-start-sha256=$START_SHA"
    "--confirm-secret-availability-sha256=$SECRET_AVAILABILITY_SHA"
    "--confirm-backup-sha256=$BACKUP_SHA"
    '--confirm-operation=checkout.create-sandbox-real-post'
    '--confirm-target=stripe-sandbox-real-post'
    '--confirm-maximum-attempts=1'
    '--confirm-provider-contact-authorized=yes'
    '--confirm-provider-mutation-authorized=yes'
    '--confirm-checkout-creation-authorized=yes'
    '--confirm-payment-authorized=no'
    '--confirm-webhook-authorized=no'
    '--confirm-browser-navigation-authorized=no'
    '--confirm-store-lite-mutation-authorized=no'
    '--confirm-session-expiration-authorized=no'
    '--confirm-retry-authorized=no'
    '--confirm-live-mode-authorized=no'
    '--confirm-client-deployment-authorized=no'
    '--apply'
)
STEP="changed-confirmation-refusal"
ALTERED_ARGS=("${APPLY_ARGS[@]}")
for index in "${!ALTERED_ARGS[@]}"; do
    if [[ "${ALTERED_ARGS[$index]}" == --confirm-request-sha256=* ]]; then
        ALTERED_ARGS[$index]="--confirm-request-sha256=$(printf '0%.0s' {1..64})"
    fi
done
set +e
php_no_contact "$COMMAND" "${ALTERED_ARGS[@]}" \
    > "$TEMP_ROOT/changed-refused.txt" 2>&1
CHANGED_STATUS=$?
set -e
[[ "$CHANGED_STATUS" -eq 64 ]]
grep -F 'Apply requires every exact printed identity' \
    "$TEMP_ROOT/changed-refused.txt" >/dev/null

STEP="post-refusal-invariants"
LEDGER_COUNTS="$(app_mysql --database="$REHEARSAL_DATABASE" --execute="
    SELECT CONCAT_WS(':',
        (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
         WHERE PackageID='redcms.store-lite-stripe-checkout'),
        (SELECT COUNT(*) FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.store-lite-stripe-checkout'),
        (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
         WHERE PackageID='redcms.store-lite-stripe-checkout'
           AND (ActionID LIKE 'sandbox-checkout-real-post-start.%'
             OR ActionID LIKE 'sandbox-checkout-real-post-result.%')));
")"
[[ "$LEDGER_COUNTS" == '2:2:0' ]]
! grep -F 'Checkout Session reference:' "$TEMP_ROOT"/*.txt >/dev/null
! grep -F 'Attempt consumed: yes' "$TEMP_ROOT"/*.txt >/dev/null
[[ -z "${RED_ADDON_SECRET_VALUES_JSON:-}" ]]
! rg -n '(gho_[A-Za-z0-9]{8,}|sk_(test|live)_[A-Za-z0-9]{16,}|rk_(test|live)_[A-Za-z0-9]{16,}|whsec_[A-Za-z0-9]{16,})' \
    "$TEMP_ROOT" >/dev/null
[[ "$(tree_fingerprint "$STAGED_PROJECT")" == "$STAGED_BEFORE" ]]
[[ "$(repository_fingerprint "$RED_PROJECT_ROOT")" == "$CORE_SOURCE_BEFORE" ]]
[[ "$(repository_fingerprint "$ADAPTER_ROOT")" == "$ADAPTER_SOURCE_BEFORE" ]]
[[ "$(repository_fingerprint "$STORE_ROOT")" == "$STORE_SOURCE_BEFORE" ]]

STEP="real-apply"
set +e
php_real_apply "$COMMAND" "${APPLY_ARGS[@]}" \
    > "$TEMP_ROOT/apply.txt" 2>&1
APPLY_STATUS=$?
set -e
OUTCOME="$(awk -F': ' '$1 == "Outcome" { print $2; exit }' "$TEMP_ROOT/apply.txt")"
FAILURE_STAGE="$(awk -F': ' '$1 == "Failure stage" { print $2; exit }' "$TEMP_ROOT/apply.txt")"
ADAPTER_INVOCATION_REASON="$(awk -F': ' '$1 == "Adapter invocation reason" { print $2; exit }' "$TEMP_ROOT/apply.txt")"
ADAPTER_ERROR_CODE="$(awk -F': ' '$1 == "Adapter error code" { print $2; exit }' "$TEMP_ROOT/apply.txt")"
grep -Fx 'Attempt consumed: yes' "$TEMP_ROOT/apply.txt" >/dev/null
if [[ "$APPLY_STATUS" -eq 0 ]]; then
    if [[ "$OUTCOME" != 'checkout_session_created'
        || "$FAILURE_STAGE" != 'none'
    ]]; then
        echo 'Created recovery result failed bounded validation.' >&2
        exit 65
    fi
    grep -Fx 'Created one bounded open, unpaid, non-live Sandbox Checkout Session.' \
        "$TEMP_ROOT/apply.txt" >/dev/null
elif [[ "$APPLY_STATUS" -eq 1 ]]; then
    if [[ "$OUTCOME" != 'indeterminate'
        || ! "$FAILURE_STAGE" =~ ^(core_invocation_failed|adapter_invocation_failed|preflight_refused|transport_exchange_failed|exchange_invariant_failed|response_decode_failed|response_acceptance_failed)$
    ]]; then
        echo 'Indeterminate recovery result failed bounded validation.' >&2
        exit 65
    fi
else
    echo "Unexpected D4D recovery command status: $APPLY_STATUS" >&2
    exit 65
fi
if [[ ! "$ADAPTER_INVOCATION_REASON" =~ ^(invalid_request|adapter_output|adapter_failed|invalid_result|secret_disclosure|adapter_error|completed|core_exception|unavailable)$
    || ! "$ADAPTER_ERROR_CODE" =~ ^(none|real_post_input_refused|real_post_preflight_refused|real_post_secret_refused|real_post_failed|unavailable)$
]]; then
    echo 'Adapter diagnostic codes failed bounded validation.' >&2
    exit 65
fi
if [[ "$RECOVERY_NETWORK_MODE" == 'offline' ]]; then
    if [[ "$OUTCOME" != 'indeterminate'
        || "$FAILURE_STAGE" != 'adapter_invocation_failed'
    ]]; then
        echo 'Offline recovery did not fail at the sealed adapter boundary.' >&2
        exit 65
    fi
fi

POST_APPLY_COUNTS="$(app_mysql --database="$REHEARSAL_DATABASE" --execute="
    SELECT CONCAT_WS(':',
        (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
         WHERE PackageID='redcms.store-lite-stripe-checkout'),
        (SELECT COUNT(*) FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.store-lite-stripe-checkout'),
        (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
         WHERE PackageID='redcms.store-lite-stripe-checkout'
           AND (ActionID LIKE 'sandbox-checkout-real-post-start.%'
             OR ActionID LIKE 'sandbox-checkout-real-post-result.%')));
")"
if [[ "$POST_APPLY_COUNTS" != '4:4:2' ]]; then
    echo 'Recovery ledger postcondition failed.' >&2
    exit 65
fi
if rg -n '(gho_[A-Za-z0-9]{8,}|sk_(test|live)_[A-Za-z0-9]{16,}|rk_(test|live)_[A-Za-z0-9]{16,}|whsec_[A-Za-z0-9]{16,})' \
    "$TEMP_ROOT" >/dev/null; then
    echo 'Recovery evidence contains credential-shaped material.' >&2
    exit 65
fi
[[ "$(tree_fingerprint "$STAGED_PROJECT")" == "$STAGED_BEFORE" ]]
[[ "$(repository_fingerprint "$RED_PROJECT_ROOT")" == "$CORE_SOURCE_BEFORE" ]]
[[ "$(repository_fingerprint "$ADAPTER_ROOT")" == "$ADAPTER_SOURCE_BEFORE" ]]
[[ "$(repository_fingerprint "$STORE_ROOT")" == "$STORE_SOURCE_BEFORE" ]]

RUN_SUCCEEDED=1
echo "P3E-9D4D diagnostic recovery completed: core:$CORE_COMMIT adapter:$EXPECTED_ADAPTER_COMMIT store-lite:$EXPECTED_STORE_COMMIT network-mode:$RECOVERY_NETWORK_MODE dry-run:1 real-apply:1 outcome:$OUTCOME failure-stage:$FAILURE_STAGE adapter-reason:$ADAPTER_INVOCATION_REASON adapter-error:$ADAPTER_ERROR_CODE payment:0 webhook:0 browser:0 store-mutation:0 retry:0 live-mode:0 deployment:0 ledger:$POST_APPLY_COUNTS"
