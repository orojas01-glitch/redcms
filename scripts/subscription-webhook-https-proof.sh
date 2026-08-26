#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ADAPTER_ROOT="${RED_STRIPE_ADAPTER_ROOT:-$(dirname "$PROJECT_ROOT")/redcms-store-lite-stripe-checkout/package}"
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
CADDYFILE="$PROJECT_ROOT/server-integrations/subscription-webhook-endpoint-proof/Caddyfile"
TEMP_ROOT=""
SERVER_PID=0

fail() {
    printf 'Subscription webhook HTTPS proof failed: %s\n' "$1" >&2
    exit 1
}

cleanup() {
    local status=$?
    trap - EXIT INT TERM
    set +e
    if [[ "$SERVER_PID" -gt 0 ]]; then
        kill -TERM "$SERVER_PID" >/dev/null 2>&1 || true
        wait "$SERVER_PID" >/dev/null 2>&1 || true
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-subscription-webhook-https."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
    fi
    if [[ -n "$TEMP_ROOT" && -e "$TEMP_ROOT" ]]; then
        printf '%s\n' 'Temporary HTTPS proof root remains.' >&2
        status=1
    fi
    exit "$status"
}

trap cleanup EXIT INT TERM

for command in curl openssl; do
    command -v "$command" >/dev/null 2>&1 \
        || fail "$command is required"
done
[[ -x "$FRANKENPHP_BIN"
    && -s "$CADDYFILE"
    && -s "$SCRIPT_DIR/subscription-webhook-https-fixture.php"
    && -s "$ADAPTER_ROOT/StripeBoundedJsonDecoder.php"
    && -s "$ADAPTER_ROOT/StripeSandboxWebhookSignatureEnvelope.php"
]] || fail 'proof prerequisites are unavailable'

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-subscription-webhook-https.XXXXXX")"
PROOF_ROOT="$TEMP_ROOT/root"
CERTIFICATE="$TEMP_ROOT/certificate.pem"
PRIVATE_KEY="$TEMP_ROOT/private-key.pem"
SERVER_LOG="$TEMP_ROOT/server.log"
BODY_FILE="$TEMP_ROOT/event.json"
VALID_BODY="$TEMP_ROOT/valid-response.json"
INVALID_BODY="$TEMP_ROOT/invalid-response.json"
GET_HEADERS="$TEMP_ROOT/get-headers.txt"
CADDY_DATA="$TEMP_ROOT/caddy-data"
CADDY_CONFIG="$TEMP_ROOT/caddy-config"
mkdir -p "$PROOF_ROOT" "$CADDY_DATA" "$CADDY_CONFIG"
cp "$SCRIPT_DIR/subscription-webhook-https-fixture.php" "$PROOF_ROOT/index.php"
chmod 700 "$TEMP_ROOT" "$PROOF_ROOT"

PORT=''
for attempt in $(seq 0 99); do
    candidate=$((19450 + (($$ + attempt) % 12000)))
    if ! curl --silent --show-error --insecure --max-time 1 \
        --output /dev/null "https://localhost:$candidate/" \
        >/dev/null 2>&1; then
        PORT="$candidate"
        break
    fi
done
[[ -n "$PORT" ]] || fail 'no local HTTPS port is available'

openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
    -keyout "$PRIVATE_KEY" \
    -out "$CERTIFICATE" \
    -subj '/CN=localhost' \
    -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1' \
    >/dev/null 2>&1
chmod 600 "$PRIVATE_KEY" "$CERTIFICATE"

SECRET="whsec_synthetic_https_proof_$(openssl rand -hex 16)"
RECEIVED_AT="$(date +%s)"
EVENT_REF="evt_HttpsEndpointProof$(openssl rand -hex 8)"
BODY="{\"id\":\"$EVENT_REF\",\"object\":\"event\",\"api_version\":\"2024-09-30.acacia\",\"created\":$RECEIVED_AT,\"data\":{\"object\":{\"id\":\"cs_test_HttpsEndpointProof123456\",\"object\":\"checkout.session\"}},\"livemode\":false,\"type\":\"checkout.session.completed\"}"
printf '%s' "$BODY" > "$BODY_FILE"
SIGNATURE="$(printf '%s' "$RECEIVED_AT.$BODY" \
    | openssl dgst -sha256 -hmac "$SECRET" \
    | awk '{print $NF}')"

RED_SUBSCRIPTION_WEBHOOK_PROOF_PORT="$PORT" \
RED_SUBSCRIPTION_WEBHOOK_PROOF_CERT="$CERTIFICATE" \
RED_SUBSCRIPTION_WEBHOOK_PROOF_KEY="$PRIVATE_KEY" \
RED_SUBSCRIPTION_WEBHOOK_PROOF_ROOT="$PROOF_ROOT" \
RED_SUBSCRIPTION_WEBHOOK_CORE_ROOT="$PROJECT_ROOT" \
RED_SUBSCRIPTION_WEBHOOK_ADAPTER_ROOT="$ADAPTER_ROOT" \
RED_SUBSCRIPTION_WEBHOOK_PROOF_SECRET="$SECRET" \
XDG_DATA_HOME="$CADDY_DATA" \
XDG_CONFIG_HOME="$CADDY_CONFIG" \
    "$FRANKENPHP_BIN" run --config "$CADDYFILE" --adapter caddyfile \
    >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!

ready=0
for _attempt in $(seq 1 60); do
    status="$(curl --silent --show-error --cacert "$CERTIFICATE" \
        --max-time 1 --output /dev/null --write-out '%{http_code}' \
        "https://localhost:$PORT/health" 2>/dev/null || true)"
    if [[ "$status" == '404' ]]; then
        ready=1
        break
    fi
    sleep 0.1
done
[[ "$ready" -eq 1 ]] || fail 'TLS server did not become ready'

VALID_STATUS="$(curl --silent --show-error --cacert "$CERTIFICATE" \
    --output "$VALID_BODY" --write-out '%{http_code}' \
    --header 'Content-Type: application/json' \
    --header "Stripe-Signature: t=$RECEIVED_AT,v1=$SIGNATURE" \
    --data-binary "@$BODY_FILE" \
    "https://localhost:$PORT/addons/redcms/store-lite-stripe-checkout/provider-events")"
if [[ "$VALID_STATUS" != '200'
    || "$(tr -d '\r\n' < "$VALID_BODY")" != '{"ok":true}'
]]; then
    printf 'Observed valid status: %s\n' "$VALID_STATUS" >&2
    printf 'Observed valid body: %s\n' \
        "$(tr -d '\r\n' < "$VALID_BODY")" >&2
    tail -n 40 "$SERVER_LOG" >&2 || true
    fail 'valid signed HTTPS event was not acknowledged'
fi

INVALID_STATUS="$(curl --silent --show-error --cacert "$CERTIFICATE" \
    --output "$INVALID_BODY" --write-out '%{http_code}' \
    --header 'Content-Type: application/json' \
    --header "Stripe-Signature: t=$RECEIVED_AT,v1=$(printf '0%.0s' {1..64})" \
    --data-binary "@$BODY_FILE" \
    "https://localhost:$PORT/addons/redcms/store-lite-stripe-checkout/provider-events")"
[[ "$INVALID_STATUS" == '400'
    && "$(tr -d '\r\n' < "$INVALID_BODY")" \
        == '{"ok":false,"error":"invalid_signature"}'
]] || fail 'invalid signed HTTPS event did not fail closed'

GET_STATUS="$(curl --silent --show-error --cacert "$CERTIFICATE" \
    --dump-header "$GET_HEADERS" --output /dev/null --write-out '%{http_code}' \
    "https://localhost:$PORT/addons/redcms/store-lite-stripe-checkout/provider-events")"
[[ "$GET_STATUS" == '405' ]] || fail 'GET did not return 405'
grep -Eiq '^Allow: POST\r?$' "$GET_HEADERS" \
    || fail 'GET response omitted Allow: POST'

QUERY_STATUS="$(curl --silent --show-error --cacert "$CERTIFICATE" \
    --output /dev/null --write-out '%{http_code}' \
    "https://localhost:$PORT/addons/redcms/store-lite-stripe-checkout/provider-events?x=1")"
[[ "$QUERY_STATUS" == '404' ]] || fail 'query-bearing target was not refused'

PLAINTEXT_STATUS="$(curl --silent --show-error --max-time 2 \
    --output /dev/null --write-out '%{http_code}' \
    "http://localhost:$PORT/addons/redcms/store-lite-stripe-checkout/provider-events" \
    2>/dev/null || true)"
[[ "$PLAINTEXT_STATUS" != '200'
    && "$PLAINTEXT_STATUS" != '301'
    && "$PLAINTEXT_STATUS" != '302'
    && "$PLAINTEXT_STATUS" != '307'
    && "$PLAINTEXT_STATUS" != '308'
]] || fail 'plaintext request unexpectedly succeeded or redirected'

if grep -Eiq 'panic|fatal|uncaught|parse error|warning' "$SERVER_LOG"; then
    tail -n 80 "$SERVER_LOG" >&2 || true
    fail 'server log contains an error marker'
fi
if grep -Fq '/Library/Application Support/Caddy' "$SERVER_LOG"; then
    tail -n 80 "$SERVER_LOG" >&2 || true
    fail 'server log references non-disposable host application state'
fi
if grep -Fq "$SECRET" "$VALID_BODY" "$INVALID_BODY" "$GET_HEADERS"; then
    fail 'synthetic secret reached response evidence'
fi

SECRET=''
SIGNATURE=''
BODY=''
printf '%s\n' 'Subscription webhook HTTPS proof passed: valid:200 invalid:400 get:405 query:404 plaintext:refused.'
printf '%s\n' 'Cleanup will remove the server, certificate, synthetic secret, logs, responses, and staged root.'
