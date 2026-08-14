#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
INTEGRATION_DIR="$PROJECT_ROOT/server-integrations/apache-public-mutation-direct-ingress"
APACHE_BIN="${RED_APACHE_BIN:-/usr/sbin/httpd}"
PHP_BIN="${PHP_CLI:-/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php}"
PHP_CGI="${RED_PHP_CGI:-/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php-cgi}"
SERVER_ROOT=''
TLS_DIR=''
APACHE_PID=''
PHP_CGI_PID=''

for executable in "$APACHE_BIN" "$PHP_BIN" "$PHP_CGI"; do
  if [ ! -x "$executable" ]; then
    echo "Apache proof executable is missing: $executable" >&2
    exit 69
  fi
done
if ! command -v curl >/dev/null 2>&1 \
  || ! command -v openssl >/dev/null 2>&1; then
  echo 'curl and OpenSSL are required for the Apache direct-ingress proof.' >&2
  exit 69
fi

NODE_BIN=${RED_NODE_BIN:-}
if [ -z "$NODE_BIN" ] && command -v node >/dev/null 2>&1; then
  NODE_BIN=$(command -v node)
fi
if [ -z "$NODE_BIN" ]; then
  candidate_node='/Users/oscarrojas/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node'
  if [ -x "$candidate_node" ]; then
    NODE_BIN=$candidate_node
  fi
fi
if [ -z "$NODE_BIN" ] || [ ! -x "$NODE_BIN" ]; then
  echo 'Node.js with Playwright is required for browser deployment evidence.' >&2
  exit 69
fi

for proof_file in \
  "$INTEGRATION_DIR/proof/apache-host.conf.template" \
  "$INTEGRATION_DIR/proof/index.php"; do
  if [ ! -f "$proof_file" ]; then
    echo "Apache direct-ingress proof file is missing: $proof_file" >&2
    exit 66
  fi
done

EVIDENCE_DIR=${RED_APACHE_DIRECT_INGRESS_PROOF_OUTPUT:-}
if [ -z "$EVIDENCE_DIR" ]; then
  EVIDENCE_DIR=$(mktemp -d "${TMPDIR:-/tmp}/redcms-apache-direct-evidence.XXXXXX")
else
  case "$EVIDENCE_DIR" in
    /*) ;;
    *)
      echo 'Apache proof evidence requires an absolute output path.' >&2
      exit 64
      ;;
  esac
  case "$EVIDENCE_DIR" in
    "$PROJECT_ROOT"|"$PROJECT_ROOT"/*)
      echo 'Apache proof evidence must remain outside the starter.' >&2
      exit 64
      ;;
  esac
  if [ -e "$EVIDENCE_DIR" ]; then
    echo 'Apache proof evidence output must not already exist.' >&2
    exit 64
  fi
  mkdir -p "$EVIDENCE_DIR"
fi
chmod 700 "$EVIDENCE_DIR"

cleanup() {
  run_result=$?
  set +e
  if [ -n "$APACHE_PID" ]; then
    kill "$APACHE_PID" >/dev/null 2>&1 || true
    wait "$APACHE_PID" >/dev/null 2>&1 || true
  fi
  if [ -n "$PHP_CGI_PID" ]; then
    kill "$PHP_CGI_PID" >/dev/null 2>&1 || true
    wait "$PHP_CGI_PID" >/dev/null 2>&1 || true
  fi
  if [ "$run_result" -ne 0 ] && [ -n "$SERVER_ROOT" ]; then
    sed -n '1,160p' "$SERVER_ROOT/logs/error.log" 2>/dev/null || true
    sed -n '1,160p' "$SERVER_ROOT/logs/access.log" 2>/dev/null || true
    sed -n '1,160p' "$SERVER_ROOT/logs/stdout.log" 2>/dev/null || true
    sed -n '1,160p' "$SERVER_ROOT/logs/php-cgi.log" 2>/dev/null || true
  fi
  if [ -n "$SERVER_ROOT" ] && [ -d "$SERVER_ROOT" ]; then
    rm -rf "$SERVER_ROOT"
  fi
  if [ -n "$TLS_DIR" ] && [ -d "$TLS_DIR" ]; then
    rm -rf "$TLS_DIR"
  fi
  if [ "$run_result" -ne 0 ]; then
    echo "Non-secret Apache proof evidence retained at: $EVIDENCE_DIR" >&2
  fi
  exit "$run_result"
}
trap cleanup EXIT HUP INT TERM

SERVER_ROOT=$(mktemp -d "${TMPDIR:-/tmp}/redcms-apache-direct-server.XXXXXX")
TLS_DIR=$(mktemp -d "${TMPDIR:-/tmp}/redcms-apache-direct-tls.XXXXXX")
DOCUMENT_ROOT="$SERVER_ROOT/htdocs"
mkdir -p "$DOCUMENT_ROOT/includes" "$SERVER_ROOT/logs"
cp "$INTEGRATION_DIR/proof/index.php" "$DOCUMENT_ROOT/index.php"

for include_file in \
  runtime_config_helpers.php \
  addon_manifest_helpers.php \
  addon_public_mutation_preflight_helpers.php \
  addon_public_mutation_subject_helpers.php \
  addon_public_mutation_http_request_helpers.php \
  addon_public_mutation_server_request_helpers.php \
  addon_public_mutation_direct_ingress_helpers.php; do
  cp "$PROJECT_ROOT/includes/$include_file" \
    "$DOCUMENT_ROOT/includes/$include_file"
done

openssl req -x509 -newkey rsa:2048 -sha256 -nodes -days 1 \
  -subj '/CN=localhost' \
  -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1' \
  -keyout "$TLS_DIR/tls.key" \
  -out "$TLS_DIR/tls.crt" >/dev/null 2>&1
chmod 600 "$TLS_DIR/tls.key" "$TLS_DIR/tls.crt"
cp "$TLS_DIR/tls.crt" "$EVIDENCE_DIR/certificate.pem"

free_port() {
  "$PHP_BIN" -r '
    $socket = stream_socket_server("tcp://127.0.0.1:0", $errno, $error);
    if ($socket === false) { exit(1); }
    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    $parts = explode(":", $name);
    echo end($parts);
  '
}

HTTP_PORT=$(free_port)
HTTPS_PORT=$(free_port)
FCGI_PORT=$(free_port)
while [ "$HTTP_PORT" = "$HTTPS_PORT" ] \
  || [ "$HTTP_PORT" = "$FCGI_PORT" ] \
  || [ "$HTTPS_PORT" = "$FCGI_PORT" ]; do
  HTTPS_PORT=$(free_port)
  FCGI_PORT=$(free_port)
done
ORIGIN="https://127.0.0.1:$HTTPS_PORT"
APACHE_CONFIG="$SERVER_ROOT/httpd.conf"

sed \
  -e "s|@SERVER_ROOT@|$SERVER_ROOT|g" \
  -e "s|@PID_FILE@|$SERVER_ROOT/httpd.pid|g" \
  -e "s|@HTTP_PORT@|$HTTP_PORT|g" \
  -e "s|@HTTPS_PORT@|$HTTPS_PORT|g" \
  -e "s|@FCGI_PORT@|$FCGI_PORT|g" \
  -e "s|@ERROR_LOG@|$SERVER_ROOT/logs/error.log|g" \
  -e "s|@ACCESS_LOG@|$SERVER_ROOT/logs/access.log|g" \
  -e "s|@DOCUMENT_ROOT@|$DOCUMENT_ROOT|g" \
  -e "s|@SSL_CACHE@|$SERVER_ROOT/logs/ssl_scache|g" \
  -e "s|@CERTIFICATE@|$TLS_DIR/tls.crt|g" \
  -e "s|@PRIVATE_KEY@|$TLS_DIR/tls.key|g" \
  "$INTEGRATION_DIR/proof/apache-host.conf.template" \
  > "$APACHE_CONFIG"

export RED_PUBLIC_MUTATION_TRUSTED_ORIGIN="$ORIGIN"
"$APACHE_BIN" -t -f "$APACHE_CONFIG"
cp "$APACHE_CONFIG" "$EVIDENCE_DIR/apache-host.conf"

"$PHP_CGI" -b "127.0.0.1:$FCGI_PORT" \
  > "$SERVER_ROOT/logs/php-cgi.log" 2>&1 &
PHP_CGI_PID=$!

set -m
"$APACHE_BIN" -f "$APACHE_CONFIG" -DFOREGROUND \
  > "$SERVER_ROOT/logs/stdout.log" 2>&1 &
APACHE_PID=$!
set +m

attempt=0
status=''
while [ "$attempt" -lt 10 ]; do
  status=$(curl --insecure --silent --max-time 1 \
    --output /dev/null --write-out '%{http_code}' \
    "$ORIGIN/healthz" || true)
  if [ "$status" = '204' ]; then
    break
  fi
  if ! kill -0 "$APACHE_PID" >/dev/null 2>&1; then
    break
  fi
  attempt=$((attempt + 1))
  sleep 1
done
if [ "$status" != '204' ]; then
  echo "Apache direct-ingress proof server did not become ready (last status: $status)." >&2
  exit 1
fi

APACHE_VERSION=$("$APACHE_BIN" -v \
  | sed -n 's/^Server version: Apache\/\([0-9][0-9.]*\).*/\1/p' \
  | sed -n '1p')
PHP_VERSION=$("$PHP_CGI" -v \
  | sed -n 's/^PHP \([0-9][0-9.]*\).*/\1/p' \
  | sed -n '1p')
case "$APACHE_VERSION:$PHP_VERSION" in
  2.4.*:8.[2-5].*) ;;
  *)
    echo "Unsupported Apache/PHP proof runtime: $APACHE_VERSION / $PHP_VERSION" >&2
    exit 1
    ;;
esac

SUBJECT_TOKEN='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
CSRF_TOKEN='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
IDEMPOTENCY_KEY='cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc'
BODY='product=proof-item&quantity=2'
BODY_SHA256=$(printf '%s' "$BODY" | shasum -a 256 | awk '{print $1}')

request_capture() {
  curl --insecure --silent --show-error --fail \
    --request POST \
    --header "Origin: $ORIGIN" \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
    --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
    --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
    --data-binary "$BODY" \
    "$@" \
    "$ORIGIN/addons/proof"
}

expected_capture=$(printf 'captured\nmethod=POST\ntarget=/addons/proof\nbody_sha256=%s\nheader_count=6\nhttps=on\nsapi=cgi-fcgi' "$BODY_SHA256")
capture=$(request_capture)
if [ "$capture" != "$expected_capture" ]; then
  echo 'Apache did not produce the expected direct-PHP capture.' >&2
  exit 1
fi

spoofed_capture=$(request_capture \
  --header 'Host: attacker.example.test' \
  --header 'X-Forwarded-Proto: http' \
  --header 'X-Forwarded-Host: attacker.example.test')
if [ "$spoofed_capture" != "$expected_capture" ]; then
  echo 'Host or forwarding values changed the direct-PHP capture.' >&2
  exit 1
fi

expect_refusal() {
  case_name=$1
  shift
  response_file="$EVIDENCE_DIR/$case_name.response"
  response_status=$(curl --insecure --silent --show-error \
    --output "$response_file" --write-out '%{http_code}' "$@")
  if [ "$response_status" != '400' ] \
    || [ "$(sed -n '1p' "$response_file")" != 'refused:transport_unavailable' ]; then
    echo "Apache direct ingress did not refuse $case_name." >&2
    exit 1
  fi
}

expect_refusal duplicate-origin \
  --request POST \
  --header "Origin: $ORIGIN" \
  --header 'Origin: https://duplicate.invalid' \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
  --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
  --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
  --data-binary "$BODY" "$ORIGIN/addons/proof"

expect_refusal duplicate-csrf \
  --request POST \
  --header "Origin: $ORIGIN" \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
  --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
  --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
  --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
  --data-binary "$BODY" "$ORIGIN/addons/proof"

expect_refusal duplicate-cookie \
  --request POST \
  --header "Origin: $ORIGIN" \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
  --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
  --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
  --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
  --data-binary "$BODY" "$ORIGIN/addons/proof"

expect_refusal content-encoding \
  --request POST \
  --header "Origin: $ORIGIN" \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --header 'Content-Encoding: gzip' \
  --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
  --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
  --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
  --data-binary "$BODY" "$ORIGIN/addons/proof"

transfer_capture=$(request_capture --header 'Transfer-Encoding: chunked')
if [ "$transfer_capture" != "$expected_capture" ]; then
  echo 'Apache did not normalize chunk framing into the measured PHP body.' >&2
  exit 1
fi

expect_refusal forwarded-https-over-http \
  --request POST \
  --header "Origin: $ORIGIN" \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --header 'X-Forwarded-Proto: https' \
  --header "Cookie: redcms_public_mutation_subject=$SUBJECT_TOKEN" \
  --header "X-RED-CMS-CSRF: $CSRF_TOKEN" \
  --header "Idempotency-Key: $IDEMPOTENCY_KEY" \
  --data-binary "$BODY" "http://127.0.0.1:$HTTP_PORT/addons/proof"

printf '{\n  "schemaVersion": 1,\n  "apacheVersion": "%s",\n  "phpVersion": "%s",\n  "sapi": "cgi-fcgi",\n  "httpsProjection": true,\n  "canonicalRequestCaptured": true,\n  "spoofedHostForwardingIgnored": true,\n  "duplicateOriginRefused": true,\n  "duplicateCsrfRefused": true,\n  "duplicateCookieRefused": true,\n  "contentEncodingRefused": true,\n  "transferEncodingNormalized": true,\n  "forwardedHttpsOverHttpRefused": true,\n  "clientStateChanged": false,\n  "passed": true\n}\n' \
  "$APACHE_VERSION" "$PHP_VERSION" \
  > "$EVIDENCE_DIR/projection-report.json"

printf '{\n  "schemaVersion": 1,\n  "apacheBinary": "%s",\n  "phpCgiBinary": "%s",\n  "apacheVersion": "%s",\n  "phpVersion": "%s",\n  "sapi": "cgi-fcgi",\n  "databaseOpened": false,\n  "packageLoaded": false,\n  "dispatcherLinked": false\n}\n' \
  "$APACHE_BIN" "$PHP_CGI" "$APACHE_VERSION" "$PHP_VERSION" \
  > "$EVIDENCE_DIR/runtime-evidence.json"

printf '{\n  "clientId": "demo-red-sphere",\n  "databaseName": "orojas_demo_redsphere",\n  "trustedOrigin": "%s",\n  "server": {\n    "runtime": "apache_php",\n    "apacheVersion": "%s",\n    "phpVersion": "%s",\n    "sapi": "cgi-fcgi",\n    "tlsMode": "https",\n    "proxyMode": "none"\n  },\n  "ingress": {\n    "profile": "direct_php",\n    "projectionVersion": "v1",\n    "trustedOriginSource": "process_environment",\n    "routeOrder": ["apache_https", "php_server_projection", "red_direct_php_ingress"],\n    "directHttpsRequired": true,\n    "hostIgnored": true,\n    "forwardedHeadersIgnored": true,\n    "hmacRequired": false\n  },\n  "response": {\n    "owner": "core",\n    "emitter": "core_public_mutation_response_emitter",\n    "browserCookieOwner": "core",\n    "packageMayEmitHeaders": false,\n    "frontControllerLinked": false\n  },\n  "subjectCookie": {\n    "name": "redcms_public_mutation_subject",\n    "domain": "",\n    "path": "/",\n    "secure": true,\n    "httpOnly": true,\n    "sameSite": "Strict",\n    "maxAgeSeconds": 1800\n  },\n  "isolation": {\n    "databaseScoped": true,\n    "configurationOutsideStarter": true,\n    "binaryOutsideStarter": true,\n    "secretsOutsideStarter": true,\n    "mediaOutsideStarter": true\n  },\n  "activation": {\n    "dispatcherLinked": false,\n    "dispatcherEnabled": false,\n    "packageEnabled": false,\n    "storeLiteEnabled": false\n  }\n}\n' \
  "$ORIGIN" "$APACHE_VERSION" "$PHP_VERSION" \
  > "$EVIDENCE_DIR/profile.json"

"$NODE_BIN" "$PROJECT_ROOT/scripts/public-mutation-deployment-browser-qa.mjs" \
  --base-url "$ORIGIN" --output-dir "$EVIDENCE_DIR/browser"

"$PHP_BIN" \
  "$PROJECT_ROOT/scripts/addon-public-mutation-direct-deployment-review-build.php" \
  --profile "$EVIDENCE_DIR/profile.json" \
  --browser-report "$EVIDENCE_DIR/browser/report.json" \
  --projection-report "$EVIDENCE_DIR/projection-report.json" \
  --apache-config "$EVIDENCE_DIR/apache-host.conf" \
  --runtime-evidence "$EVIDENCE_DIR/runtime-evidence.json" \
  --certificate "$EVIDENCE_DIR/certificate.pem" \
  --output "$EVIDENCE_DIR/deployment-review.json"

find "$EVIDENCE_DIR" -type f -exec chmod 600 {} \;

kill "$APACHE_PID" >/dev/null 2>&1 || true
wait "$APACHE_PID" >/dev/null 2>&1 || true
APACHE_PID=''
kill "$PHP_CGI_PID" >/dev/null 2>&1 || true
wait "$PHP_CGI_PID" >/dev/null 2>&1 || true
PHP_CGI_PID=''
rm -rf "$SERVER_ROOT" "$TLS_DIR"
SERVER_ROOT=''
TLS_DIR=''
trap - EXIT HUP INT TERM
echo "Apache direct-ingress proof passed: $EVIDENCE_DIR"
