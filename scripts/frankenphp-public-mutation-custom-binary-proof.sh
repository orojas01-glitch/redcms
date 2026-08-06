#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
INTEGRATION_DIR="$PROJECT_ROOT/server-integrations/frankenphp-public-mutation-attestation"
PROOF_DIR="$INTEGRATION_DIR/proof"
BUILD_CONTEXT=''
CONTAINER_NAME="redcms-frankenphp-ingress-proof-$$"
IMAGE_TAG="redcms/frankenphp-public-mutation-proof:$$"

if ! command -v docker >/dev/null 2>&1; then
  echo 'Docker is required for the optional custom FrankenPHP binary proof.' >&2
  exit 69
fi

for proof_file in "$PROOF_DIR/Dockerfile" "$PROOF_DIR/Caddyfile" "$PROOF_DIR/index.php"; do
  if [ ! -f "$proof_file" ]; then
    echo "FrankenPHP custom-binary proof file is missing: $proof_file" >&2
    exit 66
  fi
done

cleanup() {
  status=$?
  set +e
  if [ "$status" -ne 0 ]; then
    docker logs "$CONTAINER_NAME" 2>/dev/null || true
  fi
  docker rm -f "$CONTAINER_NAME" >/dev/null 2>&1 || true
  docker image rm -f "$IMAGE_TAG" >/dev/null 2>&1 || true
  if [ -n "$BUILD_CONTEXT" ] && [ -d "$BUILD_CONTEXT" ]; then
    rm -rf "$BUILD_CONTEXT"
  fi
  exit "$status"
}

trap cleanup EXIT HUP INT TERM

BUILD_CONTEXT=$(mktemp -d "${TMPDIR:-/tmp}/redcms-frankenphp-proof.XXXXXX")
mkdir -p "$BUILD_CONTEXT/integration" "$BUILD_CONTEXT/includes"

# Stage only reviewed source files. This keeps local configuration, client
# assets, databases, and other repository files out of the Docker context.
cp -R "$INTEGRATION_DIR/." "$BUILD_CONTEXT/integration/"
for include_file in \
  runtime_config_helpers.php \
  addon_manifest_helpers.php \
  addon_public_mutation_preflight_helpers.php \
  addon_public_mutation_subject_helpers.php \
  addon_public_mutation_http_request_helpers.php \
  addon_public_mutation_server_request_helpers.php \
  addon_public_mutation_frankenphp_ingress_helpers.php; do
  cp "$PROJECT_ROOT/includes/$include_file" "$BUILD_CONTEXT/includes/$include_file"
done

docker build \
  --file "$BUILD_CONTEXT/integration/proof/Dockerfile" \
  --tag "$IMAGE_TAG" \
  "$BUILD_CONTEXT"

module_list=$(docker run --rm --entrypoint /usr/local/bin/frankenphp "$IMAGE_TAG" list-modules)
if ! printf '%s\n' "$module_list" | grep -F -x 'http.handlers.red_public_mutation_attestation' >/dev/null; then
  echo 'Custom FrankenPHP proof binary does not list the RED-CMS attestation module.' >&2
  exit 1
fi

docker run --rm --entrypoint /usr/local/bin/frankenphp "$IMAGE_TAG" \
  adapt --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null

TEST_KEY='0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
SUBJECT_TOKEN='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
CSRF_TOKEN='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
IDEMPOTENCY_KEY='cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc'
BODY='product=proof-item&quantity=2'
BODY_SHA256=$(printf '%s' "$BODY" | shasum -a 256 | awk '{print $1}')

docker run -d \
  --name "$CONTAINER_NAME" \
  -p 127.0.0.1::8080 \
  -e "RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=$TEST_KEY" \
  -e 'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN=https://proof.invalid' \
  "$IMAGE_TAG" >/dev/null

MAPPED_PORT=$(docker port "$CONTAINER_NAME" 8080/tcp | sed -n '1s/.*://p')
case "$MAPPED_PORT" in
  ''|*[!0-9]*)
    echo 'Custom FrankenPHP proof container did not expose a usable local port.' >&2
    exit 1
    ;;
esac
BASE_URL="http://127.0.0.1:$MAPPED_PORT"

attempt=0
while [ "$attempt" -lt 40 ]; do
  status=$(curl --silent --show-error --max-time 1 --output /dev/null \
    --write-out '%{http_code}' "$BASE_URL/healthz" || true)
  if [ "$status" = '204' ]; then
    break
  fi
  attempt=$((attempt + 1))
  sleep 1
done

if [ "${status:-}" != '204' ]; then
  echo 'Custom FrankenPHP proof container did not become ready.' >&2
  exit 1
fi

request_capture() {
  curl --silent --show-error --fail \
    --request POST \
    --header 'Origin: https://proof.invalid' \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
    --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
    --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
    --data-binary "$BODY" \
    "$@" \
    "$BASE_URL/addons/proof"
}

expected_capture=$(printf 'captured\nmethod=POST\ntarget=/addons/proof\nbody_sha256=%s\nheader_count=5' "$BODY_SHA256")
capture=$(request_capture)
if [ "$capture" != "$expected_capture" ]; then
  echo 'Custom FrankenPHP proof did not produce the expected signed capture.' >&2
  exit 1
fi

# A client cannot supply its own internal headers: a valid capture here proves
# that Caddy removed the forged values and replaced them with its own HMAC.
spoofed_capture=$(request_capture \
  --header 'X-RED-Public-Mutation-Capture: v1.forged' \
  --header 'X-RED-Public-Mutation-Signature: sha256=0000000000000000000000000000000000000000000000000000000000000000')
if [ "$spoofed_capture" != "$expected_capture" ]; then
  echo 'Custom FrankenPHP proof did not replace spoofed internal headers.' >&2
  exit 1
fi

expect_refusal() {
  case_name=$1
  shift
  response_file="$BUILD_CONTEXT/$case_name.response"
  response_status=$(curl --silent --show-error \
    --output "$response_file" \
    --write-out '%{http_code}' \
    "$@")
  if [ "$response_status" != '400' ] \
    || [ "$(cat "$response_file")" != 'refused:transport_unavailable' ]; then
    echo "Custom FrankenPHP proof did not refuse $case_name." >&2
    exit 1
  fi
}

expect_refusal 'spoofed-get' \
  --header 'X-RED-Public-Mutation-Capture: v1.forged' \
  --header 'X-RED-Public-Mutation-Signature: sha256=0000000000000000000000000000000000000000000000000000000000000000' \
  "$BASE_URL/addons/proof"

expect_refusal 'duplicate-origin' \
  --request POST \
  --header 'Origin: https://proof.invalid' \
  --header 'Origin: https://duplicate.invalid' \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
  --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
  --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
  --data-binary "$BODY" \
  "$BASE_URL/addons/proof"

expect_refusal 'content-encoding' \
  --request POST \
  --header 'Origin: https://proof.invalid' \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --header 'Content-Encoding: gzip' \
  --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
  --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
  --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
  --data-binary "$BODY" \
  "$BASE_URL/addons/proof"

echo 'Custom FrankenPHP binary ingress proof passed.'
