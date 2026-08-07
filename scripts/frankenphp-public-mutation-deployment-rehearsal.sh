#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
INTEGRATION_DIR="$PROJECT_ROOT/server-integrations/frankenphp-public-mutation-attestation"
PROOF_DIR="$INTEGRATION_DIR/proof"
BUILD_CONTEXT=''
REHEARSAL_DIR=''
APP_CONTAINER="redcms-public-deployment-rehearsal-$$"
IMAGE_TAG="redcms/frankenphp-public-deployment-rehearsal:$$"
HOST_PORT="${RED_DEPLOYMENT_REHEARSAL_PORT:-18443}"
BASE_URL="https://localhost:$HOST_PORT"
OUTPUT_DIR="${RED_DEPLOYMENT_REHEARSAL_OUTPUT:-${TMPDIR:-/tmp}/redcms-deployment-evidence-$$}"
OUTPUT_DIR="$(cd "$(dirname "$OUTPUT_DIR")" && pwd)/$(basename "$OUTPUT_DIR")"

fail() {
    echo "Deployment rehearsal failed: $1" >&2
    exit 1
}

case "$HOST_PORT" in
    ''|*[!0-9]*) fail 'RED_DEPLOYMENT_REHEARSAL_PORT must be numeric.' ;;
esac
if (( HOST_PORT < 1024 || HOST_PORT > 65535 )); then
    fail 'RED_DEPLOYMENT_REHEARSAL_PORT must be between 1024 and 65535.'
fi

for required_command in docker curl openssl shasum node; do
    command -v "$required_command" >/dev/null 2>&1 || fail "$required_command is required for the isolated rehearsal."
done

PROJECT_ROOT_REAL="$(cd "$PROJECT_ROOT" && pwd)"
case "$OUTPUT_DIR" in
    "$PROJECT_ROOT_REAL"|"$PROJECT_ROOT_REAL"/*) fail 'Evidence output must remain outside the RED-CMS starter.' ;;
esac
if [[ -e "$OUTPUT_DIR" ]]; then
    fail "Evidence output already exists: $OUTPUT_DIR"
fi
mkdir -p "$OUTPUT_DIR/browser"
chmod 700 "$OUTPUT_DIR"

cleanup() {
    local status=$?
    set +e
    docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
    docker image rm -f "$IMAGE_TAG" >/dev/null 2>&1 || true
    if [[ -n "$REHEARSAL_DIR" && -d "$REHEARSAL_DIR" ]]; then
        rm -rf "$REHEARSAL_DIR"
    fi
    if [[ "$status" -ne 0 ]]; then
        echo "Non-secret evidence retained at: $OUTPUT_DIR" >&2
    fi
    exit "$status"
}

trap cleanup EXIT HUP INT TERM

if curl --silent --show-error --insecure --max-time 1 --output /dev/null "$BASE_URL/healthz" >/dev/null 2>&1; then
    fail "Local rehearsal port is already in use: $HOST_PORT"
fi

REHEARSAL_DIR="$(mktemp -d "${TMPDIR:-/tmp}/redcms-deployment-rehearsal.XXXXXX")"
BUILD_CONTEXT="$REHEARSAL_DIR/build"
TLS_DIR="$REHEARSAL_DIR/tls"
mkdir -p "$BUILD_CONTEXT/integration" "$BUILD_CONTEXT/includes" "$TLS_DIR"

# Stage only the reviewed integration and its fixture. No starter files,
# client files, media, databases, or local configuration enter Docker.
cp -R "$INTEGRATION_DIR/." "$BUILD_CONTEXT/integration/"
cp "$PROOF_DIR/deployment-Caddyfile" "$BUILD_CONTEXT/integration/proof/Caddyfile"
cp "$PROOF_DIR/deployment-index.php" "$BUILD_CONTEXT/integration/proof/index.php"
# Avoid an unnecessary remote Dockerfile-frontend lookup. The staged context
# uses only the local Dockerfile syntax supported by the installed engine.
sed -i.bak '/^# syntax=docker\/dockerfile:1$/d' "$BUILD_CONTEXT/integration/proof/Dockerfile"
rm -f "$BUILD_CONTEXT/integration/proof/Dockerfile.bak"

openssl req -x509 -newkey rsa:2048 -nodes -days 1 -keyout "$TLS_DIR/rehearsal.key" -out "$TLS_DIR/rehearsal.crt" -subj '/CN=localhost' -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1' >/dev/null 2>&1
chmod 600 "$TLS_DIR/rehearsal.key" "$TLS_DIR/rehearsal.crt"
cp "$BUILD_CONTEXT/integration/proof/Caddyfile" "$OUTPUT_DIR/Caddyfile"
cp "$TLS_DIR/rehearsal.crt" "$OUTPUT_DIR/certificate-chain.pem"
chmod 600 "$OUTPUT_DIR/Caddyfile" "$OUTPUT_DIR/certificate-chain.pem"

if ! docker build --file "$BUILD_CONTEXT/integration/proof/Dockerfile" --tag "$IMAGE_TAG" "$BUILD_CONTEXT" >"$OUTPUT_DIR/docker-build.log" 2>&1; then
    tail -n 80 "$OUTPUT_DIR/docker-build.log" >&2 || true
    fail 'temporary deployment image did not build.'
fi
rm -f "$OUTPUT_DIR/docker-build.log"
docker run --rm --entrypoint /usr/local/bin/frankenphp "$IMAGE_TAG" list-modules | grep -F -x 'http.handlers.red_public_mutation_attestation' >/dev/null || fail 'temporary binary does not contain the reviewed attestation module.'
docker run --rm --entrypoint /usr/local/bin/frankenphp "$IMAGE_TAG" adapt --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null

INITIAL_KEY="$(openssl rand -hex 32)"
ROTATED_KEY="$(openssl rand -hex 32)"
[[ "$INITIAL_KEY" != "$ROTATED_KEY" ]] || fail 'rehearsal key generations unexpectedly matched.'

start_container() {
    local ingress_key="$1"
    docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
    docker run -d --name "$APP_CONTAINER" -p "127.0.0.1:${HOST_PORT}:8443" --volume "$TLS_DIR:/etc/caddy/certs:ro" -e "RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=$ingress_key" -e "RED_PUBLIC_MUTATION_TRUSTED_ORIGIN=$BASE_URL" "$IMAGE_TAG" >/dev/null
}

wait_ready() {
    local attempt=0
    local status=''
    while (( attempt < 40 )); do
        status="$(curl --silent --show-error --insecure --max-time 1 --output /dev/null --write-out '%{http_code}' "$BASE_URL/healthz" || true)"
        [[ "$status" == '204' ]] && return 0
        attempt=$((attempt + 1))
        sleep 1
    done
    return 1
}

verify_static_page() {
    local phase="$1"
    local headers="$REHEARSAL_DIR/$phase.headers"
    local body="$REHEARSAL_DIR/$phase.body"
    local status
    status="$(curl --silent --show-error --insecure --max-time 5 --dump-header "$headers" --output "$body" --write-out '%{http_code}' "$BASE_URL/")"
    [[ "$status" == '200' ]] || fail "$phase static HTTPS page returned HTTP $status."
    grep -F 'RED-CMS deployment rehearsal' "$body" >/dev/null || fail "$phase static page marker is missing."
    grep -F 'Dispatcher remains unlinked.' "$body" >/dev/null || fail "$phase dispatcher boundary marker is missing."
    tr -d '\r' < "$headers" | grep -Eiq '^Cache-Control: no-store$' || fail "$phase cache policy is not no-store."
    tr -d '\r' < "$headers" | grep -Eiq '^X-Content-Type-Options: nosniff$' || fail "$phase content-type policy is not nosniff."
    if tr -d '\r' < "$headers" | grep -Eiq '^Set-Cookie:'; then
        fail "$phase static page unexpectedly set a cookie."
    fi
    if grep -Eiq 'redcms_public_mutation_subject|[a-f0-9]{64}' "$body"; then
        fail "$phase static page disclosed an opaque token."
    fi
}

start_container "$INITIAL_KEY"
wait_ready || fail 'initial HTTPS container did not become ready.'
verify_static_page 'initial'

# Restart the same installation-shaped container with a new process key. The
# old key is deliberately absent after restart; no mutation endpoint is used.
docker rm -f "$APP_CONTAINER" >/dev/null
start_container "$ROTATED_KEY"
wait_ready || fail 'rotated HTTPS container did not become ready.'
verify_static_page 'rotated'

environment_snapshot="$(docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' "$APP_CONTAINER")"
[[ "$environment_snapshot" == *"RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=$ROTATED_KEY"* ]] || fail 'rotated process environment did not contain the active key.'
[[ "$environment_snapshot" != *"RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=$INITIAL_KEY"* ]] || fail 'previous process key remained after rotation.'

cat >"$OUTPUT_DIR/rotation-evidence.json" <<'JSON'
{
  "schemaVersion": 1,
  "initialKeyProvisioned": true,
  "rotatedKeyProvisioned": true,
  "previousKeyAbsentAfterRestart": true,
  "rotationVerified": true
}
JSON
chmod 600 "$OUTPUT_DIR/rotation-evidence.json"

docker cp "$APP_CONTAINER:/usr/local/bin/frankenphp" "$REHEARSAL_DIR/frankenphp"
chmod 600 "$REHEARSAL_DIR/frankenphp"

node "$PROJECT_ROOT/scripts/public-mutation-deployment-browser-qa.mjs" --base-url "$BASE_URL" --output-dir "$OUTPUT_DIR/browser"

cat >"$OUTPUT_DIR/profile.json" <<JSON
{
  "clientId": "deployment-rehearsal",
  "databaseName": "redcms_deployment_rehearsal",
  "trustedOrigin": "$BASE_URL",
  "server": {
    "runtime": "frankenphp",
    "frankenphpVersion": "1.12.4",
    "caddyVersion": "2.11.4",
    "tlsMode": "https",
    "proxyMode": "none"
  },
  "ingress": {
    "captureVersion": "v1",
    "hmacKeyEnvironment": "RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY",
    "hmacKeySource": "process_environment",
    "trustedOriginSource": "process_environment",
    "routeOrder": [
      "red_public_mutation_attestation",
      "php_server"
    ],
    "keyRotation": "operator_owned"
  },
  "response": {
    "owner": "core",
    "emitter": "core_public_mutation_response_emitter",
    "browserCookieOwner": "core",
    "packageMayEmitHeaders": false,
    "frontControllerLinked": false
  },
  "subjectCookie": {
    "name": "redcms_public_mutation_subject",
    "domain": "",
    "path": "/",
    "secure": true,
    "httpOnly": true,
    "sameSite": "Strict",
    "maxAgeSeconds": 1800
  },
  "isolation": {
    "databaseScoped": true,
    "configurationOutsideStarter": true,
    "binaryOutsideStarter": true,
    "secretsOutsideStarter": true,
    "mediaOutsideStarter": true
  },
  "activation": {
    "dispatcherLinked": false,
    "dispatcherEnabled": false,
    "packageEnabled": false,
    "storeLiteEnabled": false
  }
}
JSON
chmod 600 "$OUTPUT_DIR/profile.json"

PHP_BIN="${RED_PHP_BIN:-}"
[[ -n "$PHP_BIN" ]] || PHP_BIN="$(command -v php || true)"
[[ -n "$PHP_BIN" && -x "$PHP_BIN" ]] || fail 'A PHP CLI is required to build the deployment review packet.'

"$PHP_BIN" "$PROJECT_ROOT/scripts/addon-public-mutation-deployment-review-build.php" --profile "$OUTPUT_DIR/profile.json" --browser-report "$OUTPUT_DIR/browser/report.json" --rotation-evidence "$OUTPUT_DIR/rotation-evidence.json" --caddyfile "$OUTPUT_DIR/Caddyfile" --binary "$REHEARSAL_DIR/frankenphp" --certificate "$OUTPUT_DIR/certificate-chain.pem" --output "$OUTPUT_DIR/deployment-review.json"

echo 'Isolated per-client HTTPS deployment rehearsal passed.'
echo "Non-secret evidence: $OUTPUT_DIR/deployment-review.json"
