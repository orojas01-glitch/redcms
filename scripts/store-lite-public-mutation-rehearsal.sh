#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
INTEGRATION_DIR="$PROJECT_ROOT/server-integrations/frankenphp-public-mutation-attestation"
PROOF_DIR="$INTEGRATION_DIR/proof"
STORE_REPOSITORY="${RED_STORE_LITE_REPOSITORY:-$(dirname "$PROJECT_ROOT")/redcms-store-lite}"
STORE_PACKAGE="$STORE_REPOSITORY/package"
EXPECTED_STORE_VERSION='0.1.29'
EXPECTED_STORE_REVISION='96ae2b2986b6805b33b44f21cf454bd18a67a470'
MYSQL_IMAGE="${RED_STORE_LITE_MYSQL_IMAGE:-mysql:8.4}"
NODE_BIN="${RED_NODE_BIN:-/Users/oscarrojas/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node}"
PLAYWRIGHT_MODULE="${RED_PLAYWRIGHT_MODULE:-/Users/oscarrojas/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright}"
CHROME_BIN="${RED_CHROME_BIN:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
OUTPUT_DIR="${RED_STORE_LITE_MUTATION_EVIDENCE_DIR:-${TMPDIR:-/tmp}/redcms-store-lite-mutation-evidence-$$}"
NETWORK_NAME="redcms-store-lite-mutation-$$"
DB_CONTAINER="redcms-store-lite-mutation-db-$$"
APP_CONTAINER="redcms-store-lite-mutation-app-$$"
IMAGE_TAG="redcms/store-lite-mutation-rehearsal:$$"
DB_NAME='redcms_store_lite_browser_mutation'
DB_USER='redcms_store_lite_mutation'
DB_PASSWORD=''
DB_ROOT_PASSWORD=''
INGRESS_KEY=''
TEMP_ROOT=''
BUILD_CONTEXT=''
TLS_DIR=''
HOST_PORT=''
BASE_URL=''
CAFFEINATE_PID=0

fail() {
    printf 'Store Lite supported-server rehearsal failed: %s\n' "$1" >&2
    exit 1
}

usage() {
    printf 'Usage: %s\n' "$0"
    printf '%s\n' 'Runs real Store Lite cart and guest-checkout forms in disposable Docker/MySQL over HTTPS.'
}

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        usage
        exit 0
    fi
    usage >&2
    exit 64
fi

for required_command in docker curl openssl rsync git; do
    command -v "$required_command" >/dev/null 2>&1 \
        || fail "$required_command is required."
done
[[ -x "$NODE_BIN" && -d "$PLAYWRIGHT_MODULE" && -x "$CHROME_BIN" ]] \
    || fail 'Node, Playwright, or Google Chrome is unavailable.'
for required_file in \
    "$PROOF_DIR/store-lite-Dockerfile" \
    "$PROOF_DIR/store-lite-Caddyfile" \
    "$STORE_PACKAGE/addon.json" \
    "$SCRIPT_DIR/store-lite-browser-rehearsal-fixture.php" \
    "$SCRIPT_DIR/store-lite-browser-rehearsal-qa.cjs" \
    "$SCRIPT_DIR/store-lite-public-mutation-browser-qa.cjs"; do
    [[ -s "$required_file" ]] || fail "required file is missing: $required_file"
done

PROJECT_ROOT_REAL="$(cd "$PROJECT_ROOT" && pwd)"
OUTPUT_DIR="$(cd "$(dirname "$OUTPUT_DIR")" && pwd)/$(basename "$OUTPUT_DIR")"
case "$OUTPUT_DIR" in
    "$PROJECT_ROOT_REAL"|"$PROJECT_ROOT_REAL"/*)
        fail 'Evidence output must remain outside the clean starter.'
        ;;
esac
[[ ! -e "$OUTPUT_DIR" ]] || fail "evidence output already exists: $OUTPUT_DIR"

STORE_VERSION="$($NODE_BIN -e '
const fs = require("fs");
const manifest = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
process.stdout.write(typeof manifest.version === "string" ? manifest.version : "");
' "$STORE_PACKAGE/addon.json")"
[[ "$STORE_VERSION" == "$EXPECTED_STORE_VERSION" ]] \
    || fail "Store Lite $EXPECTED_STORE_VERSION is required; found $STORE_VERSION."
STORE_REVISION="$(git -C "$STORE_REPOSITORY" rev-parse HEAD)"
[[ "$STORE_REVISION" == "$EXPECTED_STORE_REVISION" ]] \
    || fail "Store Lite must be pinned at $EXPECTED_STORE_REVISION; found $STORE_REVISION."
[[ -z "$(git -C "$STORE_REPOSITORY" status --short)" ]] \
    || fail 'Store Lite repository must be clean before staging.'

select_port() {
    local candidate
    local attempt=0
    while (( attempt < 100 )); do
        candidate=$((18450 + (($$ + attempt) % 12000)))
        if ! curl --silent --show-error --insecure --max-time 1 \
            --output /dev/null "https://127.0.0.1:$candidate/healthz" \
            >/dev/null 2>&1; then
            HOST_PORT="$candidate"
            BASE_URL="https://localhost:$candidate"
            return 0
        fi
        attempt=$((attempt + 1))
    done
    return 1
}

cleanup() {
    local status=$?
    local cleanup_failed=0
    set +e
    if [[ "$status" -ne 0 ]]; then
        docker logs "$APP_CONTAINER" 2>/dev/null || true
        docker logs "$DB_CONTAINER" 2>/dev/null || true
        printf 'Non-secret evidence retained at: %s\n' "$OUTPUT_DIR" >&2
    fi
    docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
    docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
    docker network rm "$NETWORK_NAME" >/dev/null 2>&1 || true
    docker image rm -f "$IMAGE_TAG" >/dev/null 2>&1 || true
    if [[ -n "$TEMP_ROOT" && -d "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-store-lite-mutation."*
    ]]; then
        rm -rf -- "$TEMP_ROOT"
    fi
    if [[ "$CAFFEINATE_PID" -gt 0 ]]; then
        kill -TERM "$CAFFEINATE_PID" >/dev/null 2>&1 || true
        wait "$CAFFEINATE_PID" >/dev/null 2>&1 || true
    fi
    if docker inspect "$APP_CONTAINER" >/dev/null 2>&1 \
        || docker inspect "$DB_CONTAINER" >/dev/null 2>&1 \
        || docker network inspect "$NETWORK_NAME" >/dev/null 2>&1 \
        || docker image inspect "$IMAGE_TAG" >/dev/null 2>&1 \
        || [[ -n "$TEMP_ROOT" && -e "$TEMP_ROOT" ]]; then
        printf '%s\n' 'Cleanup failure: disposable Docker or build-context state remains.' >&2
        cleanup_failed=1
    fi
    if [[ "$status" -eq 0 && "$cleanup_failed" -eq 0 ]]; then
        printf '%s\n' 'Cleanup complete: removed the disposable app, database, network, image, secrets, and staged project.'
    elif [[ "$status" -eq 0 ]]; then
        status=1
    fi
    exit "$status"
}

trap cleanup EXIT HUP INT TERM

if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ &
    CAFFEINATE_PID=$!
    printf '%s\n' 'Mac sleep prevention is active for this rehearsal only.'
fi

select_port || fail 'could not find an unused local HTTPS port.'
mkdir -p "$OUTPUT_DIR/admin" "$OUTPUT_DIR/public"
chmod 700 "$OUTPUT_DIR" "$OUTPUT_DIR/admin" "$OUTPUT_DIR/public"

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-store-lite-mutation.XXXXXX")"
BUILD_CONTEXT="$TEMP_ROOT/build"
TLS_DIR="$TEMP_ROOT/tls"
mkdir -p "$BUILD_CONTEXT/site/addons/redcms/store-lite" \
    "$BUILD_CONTEXT/integration" "$TLS_DIR"

# Only clean starter files and the pinned external package enter the temporary
# build context. No client configuration, media, database, or add-on directory
# is copied from a retained installation.
rsync -a \
    --exclude='.git' \
    --exclude='addons' \
    --exclude='includes/config.local.php' \
    "$PROJECT_ROOT/" "$BUILD_CONTEXT/site/"
rsync -a "$STORE_PACKAGE/" \
    "$BUILD_CONTEXT/site/addons/redcms/store-lite/"
rsync -a "$INTEGRATION_DIR/" "$BUILD_CONTEXT/integration/"

openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
    -keyout "$TLS_DIR/rehearsal.key" \
    -out "$TLS_DIR/rehearsal.crt" \
    -subj '/CN=localhost' \
    -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1' >/dev/null 2>&1
chmod 600 "$TLS_DIR/rehearsal.key" "$TLS_DIR/rehearsal.crt"

printf '%s\n' 'Building the temporary supported-server image...'
docker build \
    --file "$BUILD_CONTEXT/integration/proof/store-lite-Dockerfile" \
    --tag "$IMAGE_TAG" "$BUILD_CONTEXT" \
    >"$OUTPUT_DIR/docker-build.log" 2>&1 \
    || { tail -n 100 "$OUTPUT_DIR/docker-build.log" >&2 || true; fail 'temporary image did not build.'; }
rm -f "$OUTPUT_DIR/docker-build.log"
docker run --rm --entrypoint /usr/local/bin/frankenphp "$IMAGE_TAG" \
    list-modules \
    | grep -F -x 'http.handlers.red_public_mutation_attestation' >/dev/null \
    || fail 'temporary binary is missing the reviewed attestation module.'
docker run --rm --entrypoint /usr/local/bin/frankenphp "$IMAGE_TAG" \
    adapt --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null

DB_PASSWORD="$(openssl rand -hex 24)"
DB_ROOT_PASSWORD="$(openssl rand -hex 24)"
INGRESS_KEY="$(openssl rand -hex 32)"
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
for _attempt in $(seq 1 60); do
    if docker exec "$DB_CONTAINER" mysqladmin ping -h127.0.0.1 \
        -uroot "-p$DB_ROOT_PASSWORD" --silent >/dev/null 2>&1; then
        db_ready=1
        break
    fi
    sleep 1
done
[[ "$db_ready" -eq 1 ]] || fail 'disposable MySQL did not become ready.'

docker exec -i "$DB_CONTAINER" mysql \
    -uroot "-p$DB_ROOT_PASSWORD" "$DB_NAME" \
    < "$BUILD_CONTEXT/site/db-structure.sql"
shopt -s nullglob
CORE_MIGRATIONS=("$BUILD_CONTEXT/site"/database/migrations/*.sql)
shopt -u nullglob
STORE_MIGRATIONS=()
while IFS= read -r migration_path; do
    [[ "$migration_path" =~ ^migrations/[A-Za-z0-9._-]+\.sql$ ]] \
        || fail "manifest contains an unsafe Store Lite migration path: $migration_path"
    STORE_MIGRATIONS+=(
        "$BUILD_CONTEXT/site/addons/redcms/store-lite/$migration_path"
    )
done < <("$NODE_BIN" -e '
const fs = require("fs");
const manifest = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
for (const migration of manifest.migrations || []) {
    process.stdout.write(`${migration.path}\n`);
}
' "$STORE_PACKAGE/addon.json")
[[ "${#CORE_MIGRATIONS[@]}" -eq 45 ]] \
    || fail "expected 45 core migrations; found ${#CORE_MIGRATIONS[@]}."
[[ "${#STORE_MIGRATIONS[@]}" -eq 10 ]] \
    || fail "expected 10 Store Lite migrations; found ${#STORE_MIGRATIONS[@]}."
for migration in "${STORE_MIGRATIONS[@]}"; do
    [[ -s "$migration" ]] || fail "Store Lite migration is missing: $migration"
done
for migration in "${CORE_MIGRATIONS[@]}" "${STORE_MIGRATIONS[@]}"; do
    docker exec -i "$DB_CONTAINER" mysql \
        -uroot "-p$DB_ROOT_PASSWORD" "$DB_NAME" < "$migration"
done

run_php() {
    docker run --rm \
        --network "$NETWORK_NAME" \
        --entrypoint /usr/local/bin/frankenphp \
        -e "RED_DB_HOST=$DB_CONTAINER:3306" \
        -e "RED_DB_USER=$DB_USER" \
        -e "RED_DB_PASS=$DB_PASSWORD" \
        -e "RED_DB_NAME=$DB_NAME" \
        -e 'RED_STORE_LITE_PROJECT_ROOT=/app/public' \
        "$IMAGE_TAG" php-cli "$@"
}

run_php /app/public/scripts/store-lite-browser-rehearsal-fixture.php prepare \
    > "$OUTPUT_DIR/fixture-prepare.json"

docker run -d \
    --name "$APP_CONTAINER" \
    --network "$NETWORK_NAME" \
    -p "127.0.0.1:${HOST_PORT}:8443" \
    --volume "$TLS_DIR:/etc/caddy/certs:ro" \
    -e "RED_DB_HOST=$DB_CONTAINER:3306" \
    -e "RED_DB_USER=$DB_USER" \
    -e "RED_DB_PASS=$DB_PASSWORD" \
    -e "RED_DB_NAME=$DB_NAME" \
    -e 'RED_PUBLIC_MUTATION_ENDPOINT_ENABLED=1' \
    -e "RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=$INGRESS_KEY" \
    -e "RED_PUBLIC_MUTATION_TRUSTED_ORIGIN=$BASE_URL" \
    "$IMAGE_TAG" >/dev/null

ready_status=''
for _attempt in $(seq 1 60); do
    if [[ "$(docker inspect --format '{{.State.Running}}' \
        "$APP_CONTAINER" 2>/dev/null || true)" != 'true'
    ]]; then
        docker logs "$APP_CONTAINER" > "$OUTPUT_DIR/app-startup.log" 2>&1 \
            || true
        fail 'supported-server app exited before readiness.'
    fi
    ready_status="$(curl --silent --show-error --insecure --max-time 1 \
        --output /dev/null --write-out '%{http_code}' \
        "https://127.0.0.1:$HOST_PORT/healthz" 2>/dev/null || true)"
    [[ "$ready_status" == '204' ]] && break
    sleep 1
done
[[ "$ready_status" == '204' ]] || fail 'supported-server app did not become ready.'

# Reuse the established administrator/product/component rehearsal first. Its
# only extension is permission to trust this disposable self-signed HTTPS host.
RED_STORE_LITE_BASE_URL="$BASE_URL" \
RED_STORE_LITE_EVIDENCE_DIR="$OUTPUT_DIR/admin" \
RED_STORE_LITE_USERNAME='store_lite_browser' \
RED_STORE_LITE_PASSWORD='StoreLiteBrowser-2026!' \
RED_STORE_LITE_IGNORE_HTTPS_ERRORS=1 \
RED_PLAYWRIGHT_MODULE="$PLAYWRIGHT_MODULE" \
RED_CHROME_BIN="$CHROME_BIN" \
    "$NODE_BIN" "$SCRIPT_DIR/store-lite-browser-rehearsal-qa.cjs"

run_php /app/public/scripts/store-lite-browser-rehearsal-fixture.php verify \
    > "$OUTPUT_DIR/fixture-admin-verify.json"

published_count="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute="
        UPDATE RED_Addon_StoreLite_Products
        SET State='published'
        WHERE ProductID='classic-shirt' AND State='draft';
        SELECT ROW_COUNT();
    " 2>/dev/null)"
[[ "$published_count" == '1' ]] \
    || fail 'disposable variable-product publication fixture was not exact.'

RED_STORE_LITE_BASE_URL="$BASE_URL" \
RED_STORE_LITE_EVIDENCE_DIR="$OUTPUT_DIR/public" \
RED_STORE_LITE_USERNAME='store_lite_browser' \
RED_STORE_LITE_PASSWORD='StoreLiteBrowser-2026!' \
RED_PLAYWRIGHT_MODULE="$PLAYWRIGHT_MODULE" \
RED_CHROME_BIN="$CHROME_BIN" \
    "$NODE_BIN" "$SCRIPT_DIR/store-lite-public-mutation-browser-qa.cjs"

cart_state="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute="
        SELECT CONCAT_WS(':',
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Carts),
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Cart_Lines),
            (SELECT COALESCE(SUM(Quantity), 0)
             FROM RED_Addon_StoreLite_Cart_Lines),
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Cart_Activity),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions),
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID='redcms.store-lite'
               AND EventName='addon.public-mutation.completed'));
    " 2>/dev/null)"
[[ "$cart_state" == '2:2:3:10:12:12' ]] \
    || fail "unexpected atomic Store Lite state after browser mutations: $cart_state"

if ! order_state="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute="
        SELECT CONCAT_WS(':',
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders),
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Order_Lines),
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Order_Line_Options),
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Order_Status_History),
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders
             WHERE FulfillmentMethod='pickup'),
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders
             WHERE FulfillmentMethod='delivery'),
            (SELECT COALESCE(SUM(FulfillmentFeeMinor), 0)
             FROM RED_Addon_StoreLite_Orders),
            (SELECT COALESCE(SUM(TotalMinor), 0)
             FROM RED_Addon_StoreLite_Orders));
    " 2>"$OUTPUT_DIR/order-state-query.log")"; then
    cat "$OUTPUT_DIR/order-state-query.log" >&2 || true
    fail 'Store Lite order-state verification query failed.'
fi
rm -f "$OUTPUT_DIR/order-state-query.log"
[[ "$order_state" == '2:2:2:2:1:1:700:4497' ]] \
    || fail "unexpected Store Lite order graph after checkout: $order_state"

if ! order_facts="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute="
        SELECT GROUP_CONCAT(
            CONCAT_WS(':', FulfillmentMethod, QuantityTotal, SubtotalMinor,
                FulfillmentFeeMinor, TotalMinor, PaymentMethod, PaymentKind,
                PaymentStatus,
                IF(DeliveryLine1 IS NULL, 'no-address', 'address'))
            ORDER BY FulfillmentMethod SEPARATOR '|')
        FROM RED_Addon_StoreLite_Orders;
    " 2>"$OUTPUT_DIR/order-facts-query.log")"; then
    cat "$OUTPUT_DIR/order-facts-query.log" >&2 || true
    fail 'Store Lite order-facts verification query failed.'
fi
rm -f "$OUTPUT_DIR/order-facts-query.log"
[[ "$order_facts" == 'delivery:1:2499:700:3199:pay_on_receipt:deferred:due_on_receipt:address|pickup:2:1298:0:1298:pay_on_receipt:deferred:due_on_receipt:no-address' ]] \
    || fail "unexpected immutable Store Lite order facts: $order_facts"

if ! order_line_facts="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute="
        SELECT GROUP_CONCAT(
            CONCAT_WS(':', orders.FulfillmentMethod, order_lines.ProductID,
                COALESCE(order_lines.VariantID, 'simple'), order_lines.SKU,
                order_lines.Quantity, order_lines.UnitPriceMinor,
                order_lines.LineTotalMinor)
            ORDER BY orders.FulfillmentMethod SEPARATOR '|')
        FROM RED_Addon_StoreLite_Order_Lines order_lines
        INNER JOIN RED_Addon_StoreLite_Orders orders
          ON orders.RecordID=order_lines.OrderRecordID;
    " 2>"$OUTPUT_DIR/order-line-query.log")"; then
    cat "$OUTPUT_DIR/order-line-query.log" >&2 || true
    fail 'Store Lite order-line verification query failed.'
fi
rm -f "$OUTPUT_DIR/order-line-query.log"
[[ "$order_line_facts" == 'delivery:classic-shirt:small-black:SHIRT-S-BLACK:1:2499:2499|pickup:banana-bunch:simple:BANANA-BUNCH:2:649:1298' ]] \
    || fail "unexpected immutable Store Lite order lines: $order_line_facts"

if ! order_customer_facts="$(docker exec "$DB_CONTAINER" mysql \
    -u"$DB_USER" "-p$DB_PASSWORD" "$DB_NAME" \
    --batch --skip-column-names --execute="
        SELECT GROUP_CONCAT(
            CONCAT_WS(':', FulfillmentMethod, CustomerName, CustomerEmail,
                COALESCE(CustomerPhone, 'no-phone'),
                COALESCE(DeliveryLine1, 'no-address'),
                COALESCE(DeliveryCountryCode, 'no-country'))
            ORDER BY FulfillmentMethod SEPARATOR '|')
        FROM RED_Addon_StoreLite_Orders;
    " 2>"$OUTPUT_DIR/order-customer-query.log")"; then
    cat "$OUTPUT_DIR/order-customer-query.log" >&2 || true
    fail 'Store Lite order-customer verification query failed.'
fi
rm -f "$OUTPUT_DIR/order-customer-query.log"
[[ "$order_customer_facts" == 'delivery:Mobile Delivery Customer:mobile.delivery@example.com:+15715550128:128 Rehearsal Way:US|pickup:Desktop Pickup Customer:desktop.pickup@example.com:no-phone:no-address:no-country' ]] \
    || fail 'browser checkout fields did not persist as the exact bounded fixture'

if docker logs "$APP_CONTAINER" 2>&1 | grep -Eq \
    'PHP (Warning|Deprecated|Notice|Fatal)|Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]'; then
    docker logs "$APP_CONTAINER" 2>&1 | grep -En \
        'PHP (Warning|Deprecated|Notice|Fatal)|Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]' >&2
    fail 'supported-server log contains a PHP runtime error.'
fi

cat > "$OUTPUT_DIR/rehearsal-summary.json" <<JSON
{
  "schemaVersion": 1,
  "coreRevision": "$(git -C "$PROJECT_ROOT" rev-parse HEAD)",
  "storeLiteVersion": "$STORE_VERSION",
  "storeLiteRevision": "$STORE_REVISION",
  "server": "custom-frankenphp-caddy-https",
  "database": "disposable-mysql-container",
  "adminBrowserReport": "admin/report.json",
  "publicMutationBrowserReport": "public/public-mutation-report.json",
  "cartState": "$cart_state",
  "orderState": "$order_state",
  "hostedDemoChanged": false,
  "clientDataUsed": false,
  "passed": true
}
JSON
chmod 600 "$OUTPUT_DIR/rehearsal-summary.json"

printf '%s\n' 'Store Lite supported-server public-mutation rehearsal passed.'
printf 'Non-secret evidence: %s\n' "$OUTPUT_DIR"
