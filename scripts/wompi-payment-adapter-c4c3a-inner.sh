#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
TEMP_ROOT=""

cleanup() {
    local status=$?
    set +e
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-wompi-c4c3a."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
    fi
    exit "$status"
}
trap cleanup EXIT INT TERM

ENV_PROJECT_ROOT=""
if [[ -n "${RED_WOMPI_C3B_PROJECT_ROOT:-}"
    && -d "$RED_WOMPI_C3B_PROJECT_ROOT"
]]; then
    ENV_PROJECT_ROOT="$(cd "$RED_WOMPI_C3B_PROJECT_ROOT" && pwd)"
fi
if [[ $# -ne 0 || "$ENV_PROJECT_ROOT" != "$PROJECT_ROOT"
    || ! -x "$FRANKENPHP_BIN" ]]; then
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-wompi-c4c3a.XXXXXX")"
mkdir -p "$TEMP_ROOT/php-ini"
printf '%s\n' \
    'allow_url_fopen=0' \
    'display_errors=stderr' \
    'error_reporting=-1' \
    'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect' \
    > "$TEMP_ROOT/php-ini/99-redcms-wompi-c4c3a-no-contact.ini"

php_no_contact() {
    env \
        -u RED_ADDON_SECRET_VALUES_JSON \
        -u WOMPI_PRIVATE_KEY -u WOMPI_INTEGRITY_KEY -u WOMPI_EVENT_SECRET \
        -u HTTP_PROXY -u HTTPS_PROXY -u ALL_PROXY \
        -u http_proxy -u https_proxy -u all_proxy \
        PHP_INI_SCAN_DIR="$TEMP_ROOT/php-ini" \
        "$FRANKENPHP_BIN" php-cli "$@"
}

php_no_contact \
    "$PROJECT_ROOT/scripts/wompi-payment-adapter-c4c3a-self-test.php" \
    > "$TEMP_ROOT/self-test.txt"

grep -F 'Wompi C4C3A durable provider-double self-test passed:' \
    "$TEMP_ROOT/self-test.txt" >/dev/null
cat "$TEMP_ROOT/self-test.txt"

php_no_contact \
    "$PROJECT_ROOT/scripts/wompi-payment-adapter-c4c3a-command-self-test.php" \
    > "$TEMP_ROOT/source-contract.txt"
grep -F 'Wompi C4C3A durable command self-test passed:' \
    "$TEMP_ROOT/source-contract.txt" >/dev/null

EVIDENCE="$TEMP_ROOT/authorization.json"
META="$TEMP_ROOT/meta.json"
php_no_contact \
    "$PROJECT_ROOT/scripts/wompi-payment-adapter-c4c3a-operator-fixture.php" \
    "$EVIDENCE" "$META" > "$TEMP_ROOT/fixture.txt"

COMMAND="$PROJECT_ROOT/scripts/admin-wompi-merchant-read-provider-double-execute.php"
ACTOR_ID=2147000995
php_no_contact "$COMMAND" "--actor-admin=$ACTOR_ID" \
    "--evidence-file=$EVIDENCE" > "$TEMP_ROOT/dry-run.txt"
grep -F 'DRY RUN: current client, Owner, packages, authorization, hashes, and unused durable start were revalidated.' \
    "$TEMP_ROOT/dry-run.txt" >/dev/null
grep -F 'no durable row was written' "$TEMP_ROOT/dry-run.txt" >/dev/null
! grep -F 'pub_test_' "$TEMP_ROOT/dry-run.txt" >/dev/null

value() {
    local label="$1"
    awk -F ': ' -v wanted="$label" \
        '$1 == wanted { print substr($0, length(wanted) + 3); exit }' \
        "$TEMP_ROOT/dry-run.txt"
}
DATABASE="$(value 'Database')"
CLIENT_SCOPE_SHA="$(value 'Client scope SHA-256')"
DATABASE_SHA="$(value 'Database SHA-256')"
ACTOR_SHA="$(value 'Actor subject SHA-256')"
PUBLIC_KEY_SHA="$(value 'Public key SHA-256')"
SETTING_SHA="$(value 'Setting state SHA-256')"
REFERENCE_SHA="$(value 'Reference state SHA-256')"
MERCHANT_PLAN_SHA="$(value 'Merchant plan SHA-256')"
PREFLIGHT_SHA="$(value 'Preflight SHA-256')"
AUTHORIZATION_SHA="$(value 'Authorization SHA-256')"
REQUEST_SHA="$(value 'Request SHA-256')"
START_SHA="$(value 'Start state SHA-256')"
BACKUP_SHA="$(printf '%s' 'c4c3a-disposable-backup' | shasum -a 256 | awk '{print $1}')"
NONCE_SHA="$(awk -F '"' '/authorizationNonceSha256/ { print $4; exit }' "$META")"
BASELINE_AUDITS="$(awk -F ': ' '/baselineAuditCount/ { gsub(/,/, "", $2); print $2; exit }' "$META")"

for value_sha in \
    "$CLIENT_SCOPE_SHA" "$DATABASE_SHA" "$ACTOR_SHA" "$PUBLIC_KEY_SHA" \
    "$SETTING_SHA" "$REFERENCE_SHA" "$MERCHANT_PLAN_SHA" \
    "$PREFLIGHT_SHA" "$AUTHORIZATION_SHA" "$REQUEST_SHA" "$START_SHA" \
    "$BACKUP_SHA" "$NONCE_SHA"
do
    [[ "$value_sha" =~ ^[a-f0-9]{64}$ ]]
done
[[ "$BASELINE_AUDITS" =~ ^[0-9]+$ ]]

STATE_BEFORE="$(php_no_contact \
    "$PROJECT_ROOT/scripts/wompi-payment-adapter-c4c3a-state-check.php" \
    "$NONCE_SHA")"
[[ "$STATE_BEFORE" == "0:${BASELINE_AUDITS}:0:0" ]]

set +e
php_no_contact "$COMMAND" "--actor-admin=$ACTOR_ID" \
    "--evidence-file=$EVIDENCE" --apply \
    > "$TEMP_ROOT/incomplete-refused.txt" 2>&1
INCOMPLETE_STATUS=$?
set -e
[[ "$INCOMPLETE_STATUS" -eq 64 ]]
grep -F 'Apply requires every exact printed confirmation' \
    "$TEMP_ROOT/incomplete-refused.txt" >/dev/null

APPLY_ARGS=(
    "--actor-admin=$ACTOR_ID" "--evidence-file=$EVIDENCE"
    "--confirm-database=$DATABASE"
    '--confirm-package=redcms.store-lite-wompi'
    '--confirm-version=0.1.5' '--confirm-state=enabled'
    "--confirm-client-scope-sha256=$CLIENT_SCOPE_SHA"
    "--confirm-database-sha256=$DATABASE_SHA"
    "--confirm-actor-subject-sha256=$ACTOR_SHA"
    "--confirm-public-key-sha256=$PUBLIC_KEY_SHA"
    "--confirm-setting-state-sha256=$SETTING_SHA"
    "--confirm-reference-state-sha256=$REFERENCE_SHA"
    "--confirm-merchant-plan-sha256=$MERCHANT_PLAN_SHA"
    "--confirm-preflight-sha256=$PREFLIGHT_SHA"
    "--confirm-authorization-sha256=$AUTHORIZATION_SHA"
    "--confirm-request-sha256=$REQUEST_SHA"
    "--confirm-start-state-sha256=$START_SHA"
    "--confirm-backup-sha256=$BACKUP_SHA"
    '--confirm-operation=merchant.acceptance-contracts.provider-double'
    '--confirm-target=core-durable-provider-double'
    '--confirm-maximum-attempts=1' '--confirm-retry-authorized=no'
    '--confirm-network-disabled=yes'
    '--confirm-real-provider-contact-authorized=no'
    '--confirm-provider-mutation-authorized=no'
    '--confirm-transaction-creation-authorized=no'
    '--confirm-payment-authorized=no'
    '--confirm-event-registration-authorized=no'
    '--confirm-order-mutation-authorized=no' '--apply'
)
php_no_contact "$COMMAND" "${APPLY_ARGS[@]}" > "$TEMP_ROOT/apply.txt"
grep -F 'Observed one durable bounded Wompi merchant-read provider-double result.' \
    "$TEMP_ROOT/apply.txt" >/dev/null
grep -F 'Durable attempt consumed: yes' "$TEMP_ROOT/apply.txt" >/dev/null
grep -F 'Replay protection active: yes' "$TEMP_ROOT/apply.txt" >/dev/null
grep -F 'Real provider contact authorized: no' "$TEMP_ROOT/apply.txt" \
    >/dev/null
! grep -F 'pub_test_' "$TEMP_ROOT/apply.txt" >/dev/null

EXPECTED_AUDITS=$((BASELINE_AUDITS + 2))
STATE_AFTER="$(php_no_contact \
    "$PROJECT_ROOT/scripts/wompi-payment-adapter-c4c3a-state-check.php" \
    "$NONCE_SHA")"
[[ "$STATE_AFTER" == "2:${EXPECTED_AUDITS}:0:0" ]]

set +e
php_no_contact "$COMMAND" "${APPLY_ARGS[@]}" \
    > "$TEMP_ROOT/replay-refused.txt" 2>&1
REPLAY_STATUS=$?
set -e
[[ "$REPLAY_STATUS" -eq 65 ]]
grep -F 'execution_already_started' "$TEMP_ROOT/replay-refused.txt" >/dev/null
STATE_REPLAY="$(php_no_contact \
    "$PROJECT_ROOT/scripts/wompi-payment-adapter-c4c3a-state-check.php" \
    "$NONCE_SHA")"
[[ "$STATE_REPLAY" == "$STATE_AFTER" ]]

cat "$TEMP_ROOT/source-contract.txt"
printf '%s\n' 'Wompi C4C3A operator rehearsal passed: dry-run:1 confirmation-refusal:1 durable-provider-double:1 durable-rows:2 replay-refusal:1 real-provider-contact:0 business-rows:0'
