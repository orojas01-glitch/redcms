#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
INTEGRATION_DIR="$PROJECT_ROOT/server-integrations/frankenphp-public-mutation-attestation"
PROOF_DIR="$INTEGRATION_DIR/proof"
BUILD_CONTEXT=''
NETWORK_NAME="redcms-public-dispatch-proof-$$"
DB_CONTAINER="redcms-public-dispatch-db-$$"
APP_CONTAINER="redcms-public-dispatch-app-$$"
IMAGE_TAG="redcms/frankenphp-public-dispatch-proof:$$"
MYSQL_IMAGE="${RED_DISPATCH_MYSQL_IMAGE:-mysql:8.4}"
DB_NAME='redcms_dispatch_fixture'
DB_USER='redcms_dispatch_fixture'
DB_PASSWORD='RedCMS-Dispatch-Fixture-2026!'
DB_ROOT_PASSWORD='RedCMS-Dispatch-Root-2026!'
BOOTSTRAP_SECRET='redcms-dispatch-bootstrap-2026'
INGRESS_KEY='0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
BASE_URL=''
RESPONSE_FILE=''
HEADERS_FILE=''
DISPATCH_STATUS=''

if ! command -v docker >/dev/null 2>&1; then
    echo 'Docker is required for the supported-server dispatcher proof.' >&2
    exit 69
fi
if ! command -v curl >/dev/null 2>&1; then
    echo 'curl is required for the supported-server dispatcher proof.' >&2
    exit 69
fi

for proof_file in \
    "$PROOF_DIR/Dockerfile" \
    "$PROOF_DIR/dispatch-Caddyfile" \
    "$PROOF_DIR/dispatch-index.php"; do
    if [[ ! -f "$proof_file" ]]; then
        echo "Dispatcher proof file is missing: $proof_file" >&2
        exit 66
    fi
done

cleanup() {
    status=$?
    set +e
    if [[ "$status" -ne 0 ]]; then
        docker logs "$APP_CONTAINER" 2>/dev/null || true
        docker logs "$DB_CONTAINER" 2>/dev/null || true
    fi
    docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
    docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
    docker network rm "$NETWORK_NAME" >/dev/null 2>&1 || true
    docker image rm -f "$IMAGE_TAG" >/dev/null 2>&1 || true
    if [[ -n "$BUILD_CONTEXT" && -d "$BUILD_CONTEXT" ]]; then
        rm -rf "$BUILD_CONTEXT"
    fi
    exit "$status"
}

trap cleanup EXIT HUP INT TERM

subject_cookie_from_headers() {
    local headers_file="$1"
    tr -d '\r' < "$headers_file" \
        | sed -n 's/^Set-Cookie: redcms_public_mutation_subject=\([a-f0-9]\{64\}\);.*/\1/p' \
        | head -n 1
}

subject_cookie_header_count() {
    local headers_file="$1"
    tr -d '\r' < "$headers_file" \
        | grep -c '^Set-Cookie: redcms_public_mutation_subject=' || true
}

subject_clear_header_count() {
    local headers_file="$1"
    tr -d '\r' < "$headers_file" \
        | grep -c '^Set-Cookie: redcms_public_mutation_subject=; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Strict$' || true
}

BUILD_CONTEXT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-public-dispatch.XXXXXX")"
mkdir -p "$BUILD_CONTEXT/integration" "$BUILD_CONTEXT/includes"
cp -R "$INTEGRATION_DIR/." "$BUILD_CONTEXT/integration/"
cp "$PROOF_DIR/dispatch-Caddyfile" "$BUILD_CONTEXT/integration/proof/Caddyfile"
cp "$PROOF_DIR/dispatch-index.php" "$BUILD_CONTEXT/integration/proof/index.php"

# Stage only the reviewed core and integration boundary.
for include_file in \
    runtime_config_helpers.php \
    addon_manifest_helpers.php \
    admin_audit_helpers.php \
    admin_addon_authorization_helpers.php \
    addon_registry_helpers.php \
    addon_runtime_helpers.php \
    addon_runtime_secret_helpers.php \
    addon_secret_resolution_helpers.php \
    addon_secret_availability_helpers.php \
    addon_setting_helpers.php \
    addon_setting_storage_helpers.php \
    addon_service_helpers.php \
    addon_install_helpers.php \
    admin_transaction_helpers.php \
    addon_public_mutation_preflight_helpers.php \
    addon_public_mutation_runtime_setting_helpers.php \
    addon_public_mutation_subject_helpers.php \
    addon_public_mutation_subject_cookie_helpers.php \
    addon_public_mutation_subject_cookie_lifecycle_helpers.php \
    addon_public_mutation_http_request_helpers.php \
    addon_public_mutation_route_helpers.php \
    addon_public_mutation_server_request_helpers.php \
    addon_public_mutation_frankenphp_ingress_helpers.php \
    addon_public_mutation_direct_ingress_helpers.php \
    addon_public_mutation_form_helpers.php \
    addon_public_mutation_rate_limit_helpers.php \
    addon_public_mutation_idempotency_helpers.php \
    addon_public_mutation_execution_helpers.php \
    addon_public_mutation_response_helpers.php \
    addon_public_mutation_response_emitter_helpers.php \
    addon_public_mutation_dispatch_helpers.php \
    addon_public_mutation_endpoint_helpers.php \
    addon_public_mutation_subject_cookie_emitter_helpers.php; do
    cp "$PROJECT_ROOT/includes/$include_file" "$BUILD_CONTEXT/includes/$include_file"
done

docker build \
    --file "$BUILD_CONTEXT/integration/proof/Dockerfile" \
    --tag "$IMAGE_TAG" \
    "$BUILD_CONTEXT"

docker run --rm --entrypoint /usr/local/bin/frankenphp "$IMAGE_TAG" \
    list-modules | grep -F -x 'http.handlers.red_public_mutation_attestation' >/dev/null
PHP_MODULES="$(docker run --rm --entrypoint /usr/local/bin/php "$IMAGE_TAG" -m)"
if ! printf '%s\n' "$PHP_MODULES" | grep -F 'mysqli' >/dev/null; then
    echo 'Dispatcher proof image does not expose mysqli.' >&2
    printf '%s\n' "$PHP_MODULES" >&2
    exit 1
fi
docker run --rm --entrypoint /usr/local/bin/frankenphp "$IMAGE_TAG" \
    adapt --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null

docker network create "$NETWORK_NAME" >/dev/null
docker run -d \
    --name "$DB_CONTAINER" \
    --network "$NETWORK_NAME" \
    -e "MYSQL_DATABASE=$DB_NAME" \
    -e "MYSQL_ROOT_PASSWORD=$DB_ROOT_PASSWORD" \
    -e "MYSQL_USER=$DB_USER" \
    -e "MYSQL_PASSWORD=$DB_PASSWORD" \
    "$MYSQL_IMAGE" \
    --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci >/dev/null

db_ready=0
attempt=0
while [[ "$attempt" -lt 60 ]]; do
    if docker exec "$DB_CONTAINER" \
        mysqladmin ping -h127.0.0.1 -uroot \
        "-p$DB_ROOT_PASSWORD" --silent >/dev/null 2>&1; then
        db_ready=1
        break
    fi
    attempt=$((attempt + 1))
    sleep 1
done
if [[ "$db_ready" -ne 1 ]]; then
    echo 'Disposable MySQL fixture did not become ready.' >&2
    exit 1
fi

docker exec -i "$DB_CONTAINER" mysql \
    -uroot "-p$DB_ROOT_PASSWORD" "$DB_NAME" \
    < "$PROJECT_ROOT/db-structure.sql"

shopt -s nullglob
MIGRATIONS=("$PROJECT_ROOT"/database/migrations/*.sql)
shopt -u nullglob
if [[ "${#MIGRATIONS[@]}" -ne 45 ]]; then
    echo "Expected 45 migrations, found ${#MIGRATIONS[@]}." >&2
    exit 1
fi
for migration in "${MIGRATIONS[@]}"; do
    docker exec -i "$DB_CONTAINER" mysql \
        -uroot "-p$DB_ROOT_PASSWORD" "$DB_NAME" \
        < "$migration"
done

docker exec -i "$DB_CONTAINER" mysql \
    -uroot "-p$DB_ROOT_PASSWORD" "$DB_NAME" <<'SQL'
CREATE TABLE RED_Addon_Dispatch_Fixture_Carts (
  RecordID int unsigned NOT NULL AUTO_INCREMENT,
  SubjectRecordID int unsigned NOT NULL,
  Product varchar(120) NOT NULL,
  Quantity int unsigned NOT NULL,
  PRIMARY KEY (RecordID),
  UNIQUE KEY uq_red_dispatch_fixture_cart_item
    (SubjectRecordID,Product),
  KEY idx_red_dispatch_fixture_cart_subject
    (SubjectRecordID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO RED_Addon_Installations (
  PackageID, PackageVersion, PackageType, ManifestSHA256,
  InventorySHA256, LifecycleState, InstalledByAdminRecordID,
  UpdatedByAdminRecordID
) VALUES (
  'redcms.dispatch-fixture', '1.0.0', 'service', REPEAT('0', 64),
  REPEAT('0', 64), 'enabled', 0, 0
);
SQL

docker run -d \
    --name "$APP_CONTAINER" \
    --network "$NETWORK_NAME" \
    -p 127.0.0.1::8080 \
    -e "RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=$INGRESS_KEY" \
    -e 'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN=https://proof.invalid' \
    -e "RED_FIXTURE_DB_HOST=$DB_CONTAINER" \
    -e 'RED_FIXTURE_DB_PORT=3306' \
    -e "RED_FIXTURE_DB_USER=$DB_USER" \
    -e "RED_FIXTURE_DB_PASS=$DB_PASSWORD" \
    -e "RED_FIXTURE_DB_NAME=$DB_NAME" \
    -e "RED_FIXTURE_BOOTSTRAP_SECRET=$BOOTSTRAP_SECRET" \
    "$IMAGE_TAG" >/dev/null

MAPPED_PORT="$(docker port "$APP_CONTAINER" 8080/tcp | sed -n '1s/.*://p')"
if [[ ! "$MAPPED_PORT" =~ ^[0-9]+$ ]]; then
    echo 'Dispatcher proof container did not expose a usable local port.' >&2
    exit 1
fi
BASE_URL="http://127.0.0.1:$MAPPED_PORT"

attempt=0
health_status=''
while [[ "$attempt" -lt 40 ]]; do
    health_status="$(curl --silent --show-error --max-time 1 \
        --output /dev/null --write-out '%{http_code}' \
        "$BASE_URL/healthz" || true)"
    if [[ "$health_status" == '204' ]]; then
        break
    fi
    attempt=$((attempt + 1))
    sleep 1
done
if [[ "$health_status" != '204' ]]; then
    echo 'Dispatcher proof container did not become ready.' >&2
    exit 1
fi

BOOTSTRAP_FILE="$BUILD_CONTEXT/bootstrap.json"
BOOTSTRAP_HEADERS="$BUILD_CONTEXT/bootstrap.headers"
bootstrap_status="$(curl --silent --show-error --max-time 5 \
    --dump-header "$BOOTSTRAP_HEADERS" \
    --output "$BOOTSTRAP_FILE" --write-out '%{http_code}' \
    --header "X-RED-CMS-Fixture-Secret: $BOOTSTRAP_SECRET" \
    "$BASE_URL/__fixture/bootstrap")"
if [[ "$bootstrap_status" != '200' ]]; then
    echo "Dispatcher fixture bootstrap failed with HTTP $bootstrap_status." >&2
    sed -n '1,8p' "$BOOTSTRAP_FILE" >&2 || true
    exit 1
fi

SUBJECT_TOKEN="$(subject_cookie_from_headers "$BOOTSTRAP_HEADERS")"
CSRF_TOKEN="$(sed -n 's/.*"csrfToken":"\([a-f0-9]\{64\}\)".*/\1/p' "$BOOTSTRAP_FILE")"
IDEMPOTENCY_KEY="$(sed -n 's/.*"idempotencyKey":"\([a-f0-9]\{64\}\)".*/\1/p' "$BOOTSTRAP_FILE")"
if [[ ! "$SUBJECT_TOKEN" =~ ^[a-f0-9]{64}$ \
    || ! "$CSRF_TOKEN" =~ ^[a-f0-9]{64}$ \
    || ! "$IDEMPOTENCY_KEY" =~ ^[a-f0-9]{64}$ ]]; then
    echo 'Dispatcher fixture did not return bounded opaque bootstrap values.' >&2
    sed -n '1,8p' "$BOOTSTRAP_FILE" >&2 || true
    exit 1
fi
if [[ "$(subject_cookie_header_count "$BOOTSTRAP_HEADERS")" != '1' \
    || "$(subject_clear_header_count "$BOOTSTRAP_HEADERS")" != '0' ]]; then
    echo 'Dispatcher fixture did not issue exactly one non-disclosing subject cookie.' >&2
    sed -n '1,12p' "$BOOTSTRAP_HEADERS" >&2 || true
    sed -n '1,8p' "$BOOTSTRAP_FILE" >&2 || true
    exit 1
fi
if grep -q '"subjectToken"' "$BOOTSTRAP_FILE"; then
    echo 'Dispatcher fixture disclosed the subject token in its JSON bootstrap response.' >&2
    exit 1
fi

BODY='product=proof-item&quantity=2'
RESPONSE_FILE="$BUILD_CONTEXT/accepted.response"
HEADERS_FILE="$BUILD_CONTEXT/accepted.headers"

dispatch_request() {
    local body="$1"
    local response_file="$2"
    local headers_file="$3"
    shift 3
    DISPATCH_STATUS="$(curl --silent --show-error --max-time 5 \
        --dump-header "$headers_file" \
        --output "$response_file" \
        --write-out '%{http_code}' \
        --request POST \
        --header 'Origin: https://proof.invalid' \
        --header 'Content-Type: application/x-www-form-urlencoded' \
        --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
        --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
        --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
        --data-binary "$body" \
        "$@" \
        "$BASE_URL/addons/redcms/dispatch-fixture/cart-intent")"
}

dispatch_request "$BODY" "$RESPONSE_FILE" "$HEADERS_FILE"
if [[ "$DISPATCH_STATUS" != '200' \
    || "$(<"$RESPONSE_FILE")" != '{"ok":true,"outcome":"accepted"}' ]]; then
    echo 'Supported-server dispatcher did not accept the attested mutation.' >&2
    exit 1
fi
if grep -i '^Set-Cookie:' "$HEADERS_FILE" >/dev/null 2>&1; then
    echo 'Supported-server dispatcher unexpectedly emitted a browser cookie.' >&2
    exit 1
fi

# A client-supplied internal header cannot replace the attester's signed facts.
REPLAY_FILE="$BUILD_CONTEXT/replay.response"
REPLAY_HEADERS_FILE="$BUILD_CONTEXT/replay.headers"
dispatch_request "$BODY" "$REPLAY_FILE" "$REPLAY_HEADERS_FILE" \
    --header 'X-RED-Public-Mutation-Capture: v1.forged' \
    --header 'X-RED-Public-Mutation-Signature: sha256=0000000000000000000000000000000000000000000000000000000000000000'
if [[ "$DISPATCH_STATUS" != '200' \
    || "$(<"$REPLAY_FILE")" != '{"ok":true,"outcome":"accepted"}' ]]; then
    echo 'Supported-server dispatcher did not preserve the replay result after forged-header replacement.' >&2
    exit 1
fi

GET_FILE="$BUILD_CONTEXT/get.response"
GET_STATUS="$(curl --silent --show-error --max-time 5 \
    --output "$GET_FILE" --write-out '%{http_code}' \
    "$BASE_URL/addons/redcms/dispatch-fixture/cart-intent")"
if [[ "$GET_STATUS" != '405' \
    || "$(<"$GET_FILE")" != '{"ok":false,"reason":"method_not_allowed"}' ]]; then
    echo 'Supported-server dispatcher did not refuse a non-POST mutation.' >&2
    exit 1
fi

NO_CAPTURE_FILE="$BUILD_CONTEXT/no-capture.response"
NO_CAPTURE_STATUS="$(curl --silent --show-error --max-time 5 \
    --output "$NO_CAPTURE_FILE" --write-out '%{http_code}' \
    --request POST \
    --header 'Origin: https://proof.invalid' \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --header 'Content-Encoding: gzip' \
    --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
    --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
    --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
    --data-binary "$BODY" \
    "$BASE_URL/addons/redcms/dispatch-fixture/cart-intent")"
if [[ "$NO_CAPTURE_STATUS" != '503' \
    || "$(<"$NO_CAPTURE_FILE")" != '{"ok":false,"reason":"temporarily_unavailable"}' ]]; then
    echo 'Supported-server dispatcher did not fail closed when attestation was withheld.' >&2
    exit 1
fi

CONFLICT_FILE="$BUILD_CONTEXT/conflict.response"
dispatch_request 'product=proof-item&quantity=3' "$CONFLICT_FILE" "$BUILD_CONTEXT/conflict.headers"
if [[ "$DISPATCH_STATUS" != '409' \
    || "$(<"$CONFLICT_FILE")" != '{"ok":false,"reason":"request_conflict"}' ]]; then
    echo 'Supported-server dispatcher did not refuse an idempotency conflict.' >&2
    exit 1
fi

ROTATE_FILE="$BUILD_CONTEXT/rotate.response"
ROTATE_HEADERS_FILE="$BUILD_CONTEXT/rotate.headers"
ROTATE_STATUS="$(curl --silent --show-error --max-time 5 \
    --dump-header "$ROTATE_HEADERS_FILE" \
    --output "$ROTATE_FILE" --write-out '%{http_code}' \
    --request POST \
    --header "X-RED-CMS-Fixture-Secret: $BOOTSTRAP_SECRET" \
    --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
    "$BASE_URL/__fixture/subject/rotate")"
if [[ "$ROTATE_STATUS" != '200' \
    || "$(<"$ROTATE_FILE")" != '{"ok":true,"state":"rotated"}' \
    || "$(subject_cookie_header_count "$ROTATE_HEADERS_FILE")" != '2' \
    || "$(subject_clear_header_count "$ROTATE_HEADERS_FILE")" != '1' ]]; then
    echo 'Supported-server subject rotation did not emit one fixed clear and one new cookie.' >&2
    sed -n '1,16p' "$ROTATE_HEADERS_FILE" >&2 || true
    sed -n '1,8p' "$ROTATE_FILE" >&2 || true
    exit 1
fi
ROTATED_SUBJECT_TOKEN="$(subject_cookie_from_headers "$ROTATE_HEADERS_FILE")"
if [[ ! "$ROTATED_SUBJECT_TOKEN" =~ ^[a-f0-9]{64}$ \
    || "$ROTATED_SUBJECT_TOKEN" == "$SUBJECT_TOKEN" ]]; then
    echo 'Supported-server subject rotation did not return a distinct opaque token.' >&2
    exit 1
fi

OLD_RESOLVE_FILE="$BUILD_CONTEXT/old-resolve.response"
OLD_RESOLVE_HEADERS_FILE="$BUILD_CONTEXT/old-resolve.headers"
OLD_RESOLVE_STATUS="$(curl --silent --show-error --max-time 5 \
    --dump-header "$OLD_RESOLVE_HEADERS_FILE" \
    --output "$OLD_RESOLVE_FILE" --write-out '%{http_code}' \
    --header "X-RED-CMS-Fixture-Secret: $BOOTSTRAP_SECRET" \
    --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
    "$BASE_URL/__fixture/subject/resolve")"
if [[ "$OLD_RESOLVE_STATUS" != '200' \
    || "$(<"$OLD_RESOLVE_FILE")" != '{"ok":false,"state":"invalid"}' \
    || "$(subject_cookie_header_count "$OLD_RESOLVE_HEADERS_FILE")" != '0' ]]; then
    echo 'Supported-server subject rotation did not invalidate the old token without reissuing.' >&2
    exit 1
fi

NEW_RESOLVE_FILE="$BUILD_CONTEXT/new-resolve.response"
NEW_RESOLVE_HEADERS_FILE="$BUILD_CONTEXT/new-resolve.headers"
NEW_RESOLVE_STATUS="$(curl --silent --show-error --max-time 5 \
    --dump-header "$NEW_RESOLVE_HEADERS_FILE" \
    --output "$NEW_RESOLVE_FILE" --write-out '%{http_code}' \
    --header "X-RED-CMS-Fixture-Secret: $BOOTSTRAP_SECRET" \
    --header "Cookie: redcms_public_mutation_subject=$ROTATED_SUBJECT_TOKEN" \
    "$BASE_URL/__fixture/subject/resolve")"
if [[ "$NEW_RESOLVE_STATUS" != '200' \
    || "$(<"$NEW_RESOLVE_FILE")" != '{"ok":true,"state":"resolved"}' \
    || "$(subject_cookie_header_count "$NEW_RESOLVE_HEADERS_FILE")" != '0' ]]; then
    echo 'Supported-server subject rotation did not resolve the new token without reissuing.' >&2
    exit 1
fi

ROTATED_BOOTSTRAP_FILE="$BUILD_CONTEXT/rotated-bootstrap.json"
ROTATED_BOOTSTRAP_HEADERS="$BUILD_CONTEXT/rotated-bootstrap.headers"
rotated_bootstrap_status="$(curl --silent --show-error --max-time 5 \
    --dump-header "$ROTATED_BOOTSTRAP_HEADERS" \
    --output "$ROTATED_BOOTSTRAP_FILE" --write-out '%{http_code}' \
    --header "X-RED-CMS-Fixture-Secret: $BOOTSTRAP_SECRET" \
    --header "Cookie: redcms_public_mutation_subject=$ROTATED_SUBJECT_TOKEN" \
    "$BASE_URL/__fixture/bootstrap")"
if [[ "$rotated_bootstrap_status" != '200' \
    || "$(subject_cookie_header_count "$ROTATED_BOOTSTRAP_HEADERS")" != '0' \
    || "$(subject_clear_header_count "$ROTATED_BOOTSTRAP_HEADERS")" != '0' ]]; then
    echo 'Supported-server bootstrap unexpectedly reissued a valid rotated subject cookie.' >&2
    exit 1
fi
CSRF_TOKEN="$(sed -n 's/.*"csrfToken":"\([a-f0-9]\{64\}\)".*/\1/p' "$ROTATED_BOOTSTRAP_FILE")"
IDEMPOTENCY_KEY="$(sed -n 's/.*"idempotencyKey":"\([a-f0-9]\{64\}\)".*/\1/p' "$ROTATED_BOOTSTRAP_FILE")"
if [[ ! "$CSRF_TOKEN" =~ ^[a-f0-9]{64}$ \
    || ! "$IDEMPOTENCY_KEY" =~ ^[a-f0-9]{64}$ ]]; then
    echo 'Supported-server bootstrap did not issue fresh bounded mutation evidence after rotation.' >&2
    exit 1
fi

CLEAR_FILE="$BUILD_CONTEXT/clear.response"
CLEAR_HEADERS_FILE="$BUILD_CONTEXT/clear.headers"
CLEAR_STATUS="$(curl --silent --show-error --max-time 5 \
    --dump-header "$CLEAR_HEADERS_FILE" \
    --output "$CLEAR_FILE" --write-out '%{http_code}' \
    --request POST \
    --header "X-RED-CMS-Fixture-Secret: $BOOTSTRAP_SECRET" \
    --header "Cookie: redcms_public_mutation_subject=$ROTATED_SUBJECT_TOKEN" \
    "$BASE_URL/__fixture/subject/clear")"
if [[ "$CLEAR_STATUS" != '200' \
    || "$(<"$CLEAR_FILE")" != '{"ok":true,"state":"cleared"}' \
    || "$(subject_cookie_header_count "$CLEAR_HEADERS_FILE")" != '1' \
    || "$(subject_clear_header_count "$CLEAR_HEADERS_FILE")" != '1' ]]; then
    echo 'Supported-server subject clear did not emit exactly one fixed deletion cookie.' >&2
    sed -n '1,16p' "$CLEAR_HEADERS_FILE" >&2 || true
    sed -n '1,8p' "$CLEAR_FILE" >&2 || true
    exit 1
fi

CLEARED_RESOLVE_FILE="$BUILD_CONTEXT/cleared-resolve.response"
CLEARED_RESOLVE_HEADERS_FILE="$BUILD_CONTEXT/cleared-resolve.headers"
CLEARED_RESOLVE_STATUS="$(curl --silent --show-error --max-time 5 \
    --dump-header "$CLEARED_RESOLVE_HEADERS_FILE" \
    --output "$CLEARED_RESOLVE_FILE" --write-out '%{http_code}' \
    --header "X-RED-CMS-Fixture-Secret: $BOOTSTRAP_SECRET" \
    --header "Cookie: redcms_public_mutation_subject=$ROTATED_SUBJECT_TOKEN" \
    "$BASE_URL/__fixture/subject/resolve")"
if [[ "$CLEARED_RESOLVE_STATUS" != '200' \
    || "$(<"$CLEARED_RESOLVE_FILE")" != '{"ok":false,"state":"invalid"}' \
    || "$(subject_cookie_header_count "$CLEARED_RESOLVE_HEADERS_FILE")" != '0' ]]; then
    echo 'Supported-server subject clear did not invalidate the browser token without reissuing.' >&2
    exit 1
fi

cart_state="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute="
        SELECT CONCAT(COUNT(*), ':', COALESCE(SUM(Quantity), 0))
        FROM RED_Addon_Dispatch_Fixture_Carts;
    " 2>/dev/null)"
execution_count="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute='SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions;' 2>/dev/null)"
activity_count="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute='SELECT COUNT(*) FROM RED_Addon_Activity_Log;' 2>/dev/null)"
subject_count="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute='SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects;' 2>/dev/null)"
csrf_count="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute='SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens;' 2>/dev/null)"
idempotency_count="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute='SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys;' 2>/dev/null)"
rate_count="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute='SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits;' 2>/dev/null)"
if [[ "$cart_state" != '1:2' \
    || "$execution_count" != '0' \
    || "$activity_count" != '1' \
    || "$subject_count" != '1' \
    || "$csrf_count" != '1' \
    || "$idempotency_count" != '1' \
    || "$rate_count" != '0' ]]; then
    echo "Supported-server dispatcher fixture state is unexpected: cart=$cart_state execution=$execution_count activity=$activity_count subject=$subject_count csrf=$csrf_count idempotency=$idempotency_count rate=$rate_count" >&2
    exit 1
fi

echo 'Supported-server public-mutation dispatcher proof passed.'
