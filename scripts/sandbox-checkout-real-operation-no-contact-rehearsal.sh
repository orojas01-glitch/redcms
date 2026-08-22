#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ADAPTER_ROOT="${RED_STRIPE_ADAPTER_ROOT:-/Users/oscarrojas/Documents/redcms-store-lite-stripe-checkout}"
STORE_ROOT="${RED_STORE_LITE_ROOT:-/Users/oscarrojas/Documents/redcms-store-lite}"
CORE_REVISION="${RED_CHECKOUT_REAL_OPERATION_CORE_REVISION:-origin/main}"
EXPECTED_ADAPTER_COMMIT="a441588193cc1e32f707dd03e7d5caa6f2c49e1a"
EXPECTED_STORE_COMMIT="f7de77eb1694fb6003340632c5018024753fe1fa"
TEMP_ROOT=""
STAGED_PROJECT=""
RUN_SUCCEEDED=0

find_php() {
    if [[ -n "${RED_PHP_BIN:-}" && -x "${RED_PHP_BIN}" ]]; then
        printf '%s' "$RED_PHP_BIN"
    elif command -v php >/dev/null 2>&1; then
        command -v php
    elif [[ -x "/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php" ]]; then
        printf '%s' "/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php"
    else
        return 1
    fi
}

PHP_BIN="$(find_php)" || {
    echo 'A PHP CLI is required for the D3B rehearsal.' >&2
    exit 66
}

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

php_no_contact() {
    env \
        -u RED_ADDON_SECRET_VALUES_JSON \
        -u STRIPE_SECRET_KEY \
        -u STRIPE_API_KEY \
        -u HTTP_PROXY \
        -u HTTPS_PROXY \
        -u ALL_PROXY \
        "$PHP_BIN" \
        -d display_errors=stderr \
        -d error_reporting=-1 \
        -d allow_url_fopen=0 \
        -d 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect' \
        "$@"
}

cleanup() {
    local original_status=$?
    local cleanup_status=0
    set +e
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-real-operation-no-contact."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
    fi
    [[ -z "$TEMP_ROOT" || ! -e "$TEMP_ROOT" ]] || cleanup_status=1
    if [[ "$RUN_SUCCEEDED" -eq 1 && "$cleanup_status" -eq 0 ]]; then
        echo 'P3E-9D3B cleanup passed: staged-project:0 evidence:0 source-repositories:unchanged database:not-opened'
    fi
    [[ "$original_status" -eq 0 ]] || exit "$original_status"
    exit "$cleanup_status"
}
trap cleanup EXIT HUP INT TERM

if [[ $# -ne 0 ]]; then
    echo "Usage: $0" >&2
    exit 64
fi
if [[ ! -d "$ADAPTER_ROOT/.git"
    || ! -d "$STORE_ROOT/.git"
    || ! -f "$PROJECT_ROOT/includes/addon_sandbox_checkout_real_operation_helpers.php"
    || ! -f "$PROJECT_ROOT/scripts/admin-sandbox-checkout-real-operation-preflight.php"
]]; then
    echo 'Exact core or external adapter source is unavailable.' >&2
    exit 66
fi

CORE_COMMIT="$(git -C "$PROJECT_ROOT" rev-parse "$CORE_REVISION^{commit}")"
ADAPTER_MAIN="$(git -C "$ADAPTER_ROOT" rev-parse 'main^{commit}')"
STORE_MAIN="$(git -C "$STORE_ROOT" rev-parse 'main^{commit}')"
if [[ "$ADAPTER_MAIN" != "$EXPECTED_ADAPTER_COMMIT" ]]; then
    echo "Adapter main is not the reviewed 0.1.7 commit: $ADAPTER_MAIN" >&2
    exit 65
fi
if [[ "$STORE_MAIN" != "$EXPECTED_STORE_COMMIT" ]]; then
    echo "Store Lite main is not the reviewed 0.1.35 commit: $STORE_MAIN" >&2
    exit 65
fi
CORE_SOURCE_BEFORE="$(repository_fingerprint "$PROJECT_ROOT")"
ADAPTER_SOURCE_BEFORE="$(repository_fingerprint "$ADAPTER_ROOT")"
STORE_SOURCE_BEFORE="$(repository_fingerprint "$STORE_ROOT")"

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-real-operation-no-contact.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p \
    "$STAGED_PROJECT/addons/redcms/store-lite" \
    "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout"
git -C "$PROJECT_ROOT" archive --format=tar "$CORE_COMMIT" \
    | tar -xf - -C "$STAGED_PROJECT"
git -C "$ADAPTER_ROOT" archive --format=tar \
    "$EXPECTED_ADAPTER_COMMIT:package" \
    | tar -xf - -C "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout"
git -C "$STORE_ROOT" archive --format=tar \
    "$EXPECTED_STORE_COMMIT:package" \
    | tar -xf - -C "$STAGED_PROJECT/addons/redcms/store-lite"

MANIFEST="$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout/addon.json"
VERSION="$($PHP_BIN -r '$m=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); echo $m["version"] ?? "";' "$MANIFEST")"
[[ "$VERSION" == '0.1.7' ]]
php_no_contact "$STAGED_PROJECT/scripts/addon-sandbox-checkout-real-operation-command-self-test.php" \
    > "$TEMP_ROOT/source-contract.txt"
grep -F '68 assertions' "$TEMP_ROOT/source-contract.txt" >/dev/null

STAGED_BEFORE="$(tree_fingerprint "$STAGED_PROJECT")"
EVIDENCE_FILE="$TEMP_ROOT/evidence.json"
CONFIRMATIONS_FILE="$TEMP_ROOT/confirmations.json"
php_no_contact \
    "$SCRIPT_DIR/addon-sandbox-checkout-real-operation-rehearsal-fixture.php" \
    "$STAGED_PROJECT" "$EVIDENCE_FILE" "$CONFIRMATIONS_FILE" \
    > "$TEMP_ROOT/fixture.txt"
grep -F 'P3E-9D3B non-secret evidence prepared.' \
    "$TEMP_ROOT/fixture.txt" >/dev/null

json_value() {
    "$PHP_BIN" -r '$v=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); echo $v[$argv[2]] ?? "";' "$CONFIRMATIONS_FILE" "$1"
}
PACKAGE="$(json_value package)"
VERSION="$(json_value version)"
SOURCE_VERSION="$(json_value sourceVersion)"
MANIFEST_SHA="$(json_value manifestSha256)"
INVENTORY_SHA="$(json_value inventorySha256)"
PLAN_SHA="$(json_value planSha256)"
INPUT_SHA="$(json_value inputSha256)"
SYNTHETIC_PLAN_SHA="$(json_value syntheticPlanSha256)"
CONTRACT_SHA="$(json_value contractSha256)"
REQUEST_SHA="$(json_value requestSha256)"
START_SHA="$(json_value startIdentitySha256)"
OPERATION="$(json_value operation)"
PROVIDER_OPERATION="$(json_value providerOperation)"
COMMAND="$STAGED_PROJECT/scripts/admin-sandbox-checkout-real-operation-preflight.php"

php_no_contact "$COMMAND" "--evidence-file=$EVIDENCE_FILE" \
    > "$TEMP_ROOT/dry-run.txt"
grep -F 'DRY RUN: package, D0 request, and D2 identity evidence were revalidated.' \
    "$TEMP_ROOT/dry-run.txt" >/dev/null
grep -F 'No registrar or adapter handler ran' "$TEMP_ROOT/dry-run.txt" >/dev/null
! grep -F 'Adapter preflight invoked: yes' "$TEMP_ROOT/dry-run.txt" >/dev/null

APPLY_ARGS=(
    "--evidence-file=$EVIDENCE_FILE"
    "--confirm-package=$PACKAGE"
    "--confirm-version=$VERSION"
    "--confirm-source-version=$SOURCE_VERSION"
    "--confirm-manifest-sha256=$MANIFEST_SHA"
    "--confirm-inventory-sha256=$INVENTORY_SHA"
    "--confirm-plan-sha256=$PLAN_SHA"
    "--confirm-input-sha256=$INPUT_SHA"
    "--confirm-synthetic-plan-sha256=$SYNTHETIC_PLAN_SHA"
    "--confirm-contract-sha256=$CONTRACT_SHA"
    "--confirm-request-sha256=$REQUEST_SHA"
    "--confirm-start-identity-sha256=$START_SHA"
    "--confirm-operation=$OPERATION"
    "--confirm-provider-operation=$PROVIDER_OPERATION"
    '--confirm-maximum-attempts=1'
    '--confirm-credential-access-provided=no'
    '--confirm-execution-ready=no'
    '--confirm-execution-started=no'
    '--confirm-result-recorded=no'
    '--confirm-network-authorized=no'
    '--confirm-provider-contact-authorized=no'
    '--confirm-provider-mutation-authorized=no'
    '--confirm-checkout-creation-authorized=no'
    '--confirm-retry-authorized=no'
    '--apply'
)

ALTERED_ARGS=("${APPLY_ARGS[@]}")
for index in "${!ALTERED_ARGS[@]}"; do
    if [[ "${ALTERED_ARGS[$index]}" == --confirm-plan-sha256=* ]]; then
        ALTERED_ARGS[$index]="--confirm-plan-sha256=$(printf '0%.0s' {1..64})"
    fi
done
set +e
php_no_contact "$COMMAND" "${ALTERED_ARGS[@]}" \
    > "$TEMP_ROOT/refused.txt" 2>&1
REFUSED_STATUS=$?
set -e
[[ "$REFUSED_STATUS" -eq 64 ]]
grep -F 'Apply requires every exact printed identity and no-effect confirmation.' \
    "$TEMP_ROOT/refused.txt" >/dev/null
! grep -F 'Adapter preflight invoked: yes' "$TEMP_ROOT/refused.txt" >/dev/null

php_no_contact "$COMMAND" "${APPLY_ARGS[@]}" > "$TEMP_ROOT/apply.txt"
grep -F 'Outcome: request_contract_adopted' "$TEMP_ROOT/apply.txt" >/dev/null
[[ "$(grep -Fc 'Adapter preflight invoked: yes' "$TEMP_ROOT/apply.txt")" -eq 1 ]]
grep -F 'Observed the exact bounded adapter preflight and non-persistent result identity.' \
    "$TEMP_ROOT/apply.txt" >/dev/null
grep -F 'No credential, database, network request, provider mutation, Checkout Session, payment, webhook, Store Lite mutation, or client action occurred.' \
    "$TEMP_ROOT/apply.txt" >/dev/null
grep -Eq '^Result identity SHA-256: [a-f0-9]{64}$' "$TEMP_ROOT/apply.txt"
for marker in \
    'Credential access provided: no' \
    'Execution started: no' \
    'Result recorded: no' \
    'Network authorized: no' \
    'Provider contact authorized: no' \
    'Provider mutation authorized: no' \
    'Checkout creation authorized: no' \
    'Retry authorized: no'; do
    grep -F "$marker" "$TEMP_ROOT/apply.txt" >/dev/null
done

! rg -n '(gho_[A-Za-z0-9]{8,}|sk_(test|live)_[A-Za-z0-9]{16,}|rk_(test|live)_[A-Za-z0-9]{16,}|whsec_[A-Za-z0-9]{16,})' \
    "$TEMP_ROOT" >/dev/null
[[ "$(tree_fingerprint "$STAGED_PROJECT")" == "$STAGED_BEFORE" ]]
[[ "$(repository_fingerprint "$PROJECT_ROOT")" == "$CORE_SOURCE_BEFORE" ]]
[[ "$(repository_fingerprint "$ADAPTER_ROOT")" == "$ADAPTER_SOURCE_BEFORE" ]]
[[ "$(repository_fingerprint "$STORE_ROOT")" == "$STORE_SOURCE_BEFORE" ]]

RUN_SUCCEEDED=1
echo "P3E-9D3B no-contact rehearsal passed: core:$CORE_COMMIT adapter:$EXPECTED_ADAPTER_COMMIT store-lite:$EXPECTED_STORE_COMMIT dry-run:1 changed-confirmation-refused:1 contained-apply:1 provider-effects:0"
