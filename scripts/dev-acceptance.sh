#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

usage() {
    printf 'Usage: %s\n' "$0"
    printf '%s\n' 'Creates, validates, and removes one disposable RED-CMS acceptance database.'
}

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == "--help" ]]; then
        usage
        exit 0
    fi
    usage >&2
    exit 64
fi

if [[ ! "$RED_DB_NAME_RESOLVED" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf 'Configured primary database name is unsafe: %s\n' "$RED_DB_NAME_RESOLVED" >&2
    exit 64
fi

ACCEPTANCE_DATABASE="${RED_ACCEPTANCE_DATABASE:-redcms_acceptance_$(date +%Y%m%d_%H%M%S)_$$}"
if [[ "$ACCEPTANCE_DATABASE" == "$RED_DB_NAME_RESOLVED" ]]; then
    printf '%s\n' 'Acceptance refused: the configured primary database is protected.' >&2
    exit 65
fi
if [[ ! "$ACCEPTANCE_DATABASE" =~ ^redcms_acceptance_[A-Za-z0-9_]+$ ]]; then
    printf 'Acceptance database must start with redcms_acceptance_ and contain only letters, numbers, and underscores: %s\n' "$ACCEPTANCE_DATABASE" >&2
    exit 64
fi
if [[ ${#ACCEPTANCE_DATABASE} -gt 64 ]]; then
    printf 'Acceptance database name exceeds MySQL limits: %s\n' "$ACCEPTANCE_DATABASE" >&2
    exit 64
fi
INSTALLER_FILE="$RED_PROJECT_ROOT/db-structure.sql"
if [[ ! -s "$INSTALLER_FILE" ]]; then
    printf 'Installer SQL is missing or empty: %s\n' "$INSTALLER_FILE" >&2
    exit 66
fi
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/clean-starter-boundary-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/seo-metadata-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/seo-metadata-migration-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/store-lite-product-contract-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/store-lite-cart-line-contract-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/store-lite-stripe-checkout-contract-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/payment-adapter-colombia-c1-contract-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-payment-adapter-preflight-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-payment-adapter-registrar-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-payment-adapter-server-event-ingress-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-provider-contact-operator-command-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-sandbox-checkout-transport-operator-command-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-sandbox-checkout-real-post-preflight-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-sandbox-checkout-real-operation-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-sandbox-checkout-real-mutation-evidence-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-sandbox-checkout-real-operation-command-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-sandbox-checkout-real-post-operator-command-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-sandbox-checkout-real-post-rehearsal-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-sandbox-checkout-real-operation-rehearsal-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-trust-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-admin-tool-form-create-registration-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-admin-tool-form-initial-value-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-admin-tool-form-create-submission-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-runtime-setting-contract-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-setting-values-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-setting-editor-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-secret-resolution-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-secret-availability-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-asset-plan-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-preflight-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-response-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-response-emitter-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-subject-cookie-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-subject-cookie-emitter-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-form-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-form-ui-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-rich-field-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-component-mutation-presentation-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-browser-controller-contract-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-endpoint-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-http-request-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-route-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-server-request-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-direct-ingress-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-frankenphp-ingress-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-deployment-profile-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-response-owner-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-deployment-review-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-mutation-dispatch-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-component-editor-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-component-editor-renderer-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-admin-tool-form-renderer-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-runtime-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-service-invocation-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-adapter-invocation-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-runtime-secret-self-test.php"
"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR/addon-public-route-dispatch-self-test.php"
FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
if [[ ! -x "$FRANKENPHP_BIN" ]]; then
    printf 'FrankenPHP is missing or not executable: %s\n' "$FRANKENPHP_BIN" >&2
    exit 66
fi

shopt -s nullglob
MIGRATION_FILES=("$RED_PROJECT_ROOT"/database/migrations/*.sql)
shopt -u nullglob
MIGRATION_FILE_COUNT="${#MIGRATION_FILES[@]}"
if [[ "$MIGRATION_FILE_COUNT" -eq 0 ]]; then
    printf '%s\n' 'No migration files were found.' >&2
    exit 66
fi

ADMIN_DEFAULTS_FILE=""
ACCEPTANCE_DATABASE_CREATED=0
ACCEPTANCE_GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""
PRIMARY_SCHEMA_SIGNATURE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
PRIMARY_SCHEMA_MANIFEST=""
ACCEPTANCE_SCHEMA_MANIFEST=""
ACCEPTANCE_PORT=""
ACCEPTANCE_BASE_URL=""
ACCEPTANCE_PHP_PID=0
ACCEPTANCE_PHP_LOG=""
ACCEPTANCE_RESPONSE_DIR=""
ACCEPTANCE_COOKIE_JAR=""
ACCEPTANCE_AUTH_RECORD_ID=2147000901
ACCEPTANCE_AUTH_USERNAME="codex_acceptance_webmaster"
ACCEPTANCE_AUTH_UNKNOWN_USERNAME="codex_acceptance_unknown"
ACCEPTANCE_AUTH_PASSWORD="CodexAcceptance-2026!"
ACCEPTANCE_AUTH_ALIAS="CodexAuthQA"
ACCEPTANCE_AUTH_FIXTURE_CREATED=0
ACCEPTANCE_GUEST_RECORD_ID=2147000902
ACCEPTANCE_GUEST_USERNAME="codex_acceptance_guest"
ACCEPTANCE_GUEST_PASSWORD="CodexGuestAcceptance-2026!"
ACCEPTANCE_GUEST_ALIAS="CodexGuestQA"
ACCEPTANCE_GUEST_FIXTURE_CREATED=0
ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID=2147000908
ACCEPTANCE_SECTION_ARCHIVE_ADMIN_USERNAME="codex_acceptance_section_archive"
ACCEPTANCE_SECTION_ARCHIVE_ADMIN_PASSWORD="CodexSectionArchive-2026!"
ACCEPTANCE_SECTION_ARCHIVE_ADMIN_ALIAS="CodexArc"
ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID=2147000960
ACCEPTANCE_SECTION_ARCHIVE_ALIAS="codex-section-archive"
ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID=2147000961
ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ALIAS="codex-section-archive-active"
ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ID=2147000962
ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ALIAS="codex-section-archive-inactive"
ACCEPTANCE_SECTION_ARCHIVE_FIXTURE_CREATED=0
ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID=2147000903
ACCEPTANCE_ARTICLE_ADMIN_USERNAME="codex_acceptance_article"
ACCEPTANCE_ARTICLE_ADMIN_PASSWORD="CodexArticleAcceptance-2026!"
ACCEPTANCE_ARTICLE_ADMIN_ALIAS="CodexCrud"
ACCEPTANCE_ARTICLE_RECORD_ID=2147000910
ACCEPTANCE_ARTICLE_INITIAL_ALIAS="codex-article-qa"
ACCEPTANCE_ARTICLE_UPDATED_ALIAS="codex-article-updated"
ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME="codex-acceptance-article-new-small-picture-${ACCEPTANCE_DATABASE#redcms_acceptance_}.png"
ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME="codex-acceptance-article-edit-small-picture-${ACCEPTANCE_DATABASE#redcms_acceptance_}.png"
ACCEPTANCE_ARTICLE_NEW_UPLOAD_STORED_NAME="$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME"
ACCEPTANCE_ARTICLE_EDIT_UPLOAD_STORED_NAME="$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME"
ACCEPTANCE_ARTICLE_UPLOAD_SOURCE=""
ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_BEFORE=""
ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_CAPTURED=0
ACCEPTANCE_ARTICLE_FIXTURE_CREATED=0
ACCEPTANCE_FORM_ADMIN_RECORD_ID=2147000904
ACCEPTANCE_FORM_ADMIN_USERNAME="codex_acceptance_form"
ACCEPTANCE_FORM_ADMIN_PASSWORD="CodexFormAcceptance-2026!"
ACCEPTANCE_FORM_ADMIN_ALIAS="CodexForm"
ACCEPTANCE_FORM_ARTICLE_RECORD_ID=2147000920
ACCEPTANCE_FORM_RECORD_ID=2147000921
ACCEPTANCE_FORM_INITIAL_ALIAS="codex-form-qa"
ACCEPTANCE_FORM_UPDATED_ALIAS="codex-form-updated"
ACCEPTANCE_FORM_FIXTURE_CREATED=0
ACCEPTANCE_GALLERY_ADMIN_RECORD_ID=2147000905
ACCEPTANCE_GALLERY_ADMIN_USERNAME="codex_acceptance_gallery"
ACCEPTANCE_GALLERY_ADMIN_PASSWORD="CodexGalleryAcceptance-2026!"
ACCEPTANCE_GALLERY_ADMIN_ALIAS="CodexGal"
ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID=2147000930
ACCEPTANCE_GALLERY_RECORD_ID=2147000931
ACCEPTANCE_GALLERY_INITIAL_ALIAS="codex-gallery-qa"
ACCEPTANCE_GALLERY_UPDATED_ALIAS="codex-gallery-updated"
ACCEPTANCE_GALLERY_FIXTURE_CREATED=0
ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID=2147000906
ACCEPTANCE_GALLERY_UPLOAD_ADMIN_USERNAME="codex_acceptance_gallery_upload"
ACCEPTANCE_GALLERY_UPLOAD_ADMIN_PASSWORD="CodexGalleryUpload-2026!"
ACCEPTANCE_GALLERY_UPLOAD_ADMIN_ALIAS="CodexUp"
ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID=2147000940
ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID=2147000941
ACCEPTANCE_GALLERY_UPLOAD_ALIAS="codex-gallery-upload"
ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME="codex-acceptance-gallery-upload-${ACCEPTANCE_DATABASE#redcms_acceptance_}.png"
ACCEPTANCE_GALLERY_UPLOAD_STORED_NAME="$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME"
ACCEPTANCE_GALLERY_UPLOAD_SOURCE=""
ACCEPTANCE_GALLERY_MEDIA_MANIFEST_BEFORE=""
ACCEPTANCE_GALLERY_MEDIA_MANIFEST_CAPTURED=0
ACCEPTANCE_GALLERY_UPLOAD_FIXTURE_CREATED=0
ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID=2147000907
ACCEPTANCE_ROLLBACK_ADMIN_USERNAME="codex_acceptance_rollback"
ACCEPTANCE_ROLLBACK_ADMIN_PASSWORD="CodexRollbackAcceptance-2026!"
ACCEPTANCE_ROLLBACK_ADMIN_ALIAS="CodexTxn"
ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID=2147000950
ACCEPTANCE_ROLLBACK_RECORD_ID=2147000951
ACCEPTANCE_ROLLBACK_INITIAL_ALIAS="codex-rollback-qa"
ACCEPTANCE_ROLLBACK_UPDATED_ALIAS="codex-rollback-updated"
ACCEPTANCE_ROLLBACK_TRIGGER_NAME="red_acceptance_force_gallery_update"
ACCEPTANCE_ROLLBACK_TRIGGER_CREATED=0
ACCEPTANCE_ROLLBACK_FIXTURE_CREATED=0
ACCEPTANCE_ADDON_ASSET_ENDPOINT_FIXTURE_CREATED=0
ACCEPTANCE_ADDON_ASSET_INJECTION_FIXTURE_CREATED=0
ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_CREATED=0
ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_RECORD_ID=2147000909
ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_USERNAME="codex_acceptance_asset_injection"
ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_PASSWORD="CodexAssetInjection-2026!"
ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_ALIAS="CodexAssets"

red_acceptance_create_admin_defaults() {
    ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-acceptance-admin.XXXXXX")"
    chmod 600 "$ADMIN_DEFAULTS_FILE"
    {
        printf '[client]\n'
        printf 'protocol=tcp\n'
        printf 'host=%s\n' "$RED_DB_HOST_RESOLVED"
        printf 'port=%s\n' "$RED_DB_PORT_RESOLVED"
        printf 'user=%s\n' "${RED_ACCEPTANCE_DB_ADMIN_USER:-root}"
        printf 'password=%s\n' "${RED_ACCEPTANCE_DB_ADMIN_PASS:-}"
        printf 'default-character-set=utf8mb4\n'
    } > "$ADMIN_DEFAULTS_FILE"
}

red_acceptance_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_acceptance_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$ACCEPTANCE_DATABASE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_acceptance_primary_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$RED_DB_NAME_RESOLVED" \
        --batch --raw --skip-column-names \
        "$@"
}

red_acceptance_primary_snapshot() {
    red_acceptance_primary_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE()),
            (SELECT COUNT(*) FROM RED_Schema_Migrations),
            (SELECT COUNT(*) FROM RED_Admin),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, Username, Password, Alias, AdminType, AdminComponents, AdminTools, Email))), 0) FROM RED_Admin),
            (SELECT COUNT(*) FROM RED_Admin_Roles),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', AdminRecordID, RoleName, AssignedByAdminRecordID, AssignedAt))), 0) FROM RED_Admin_Roles),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', AdminRecordID, Capability, GrantedByAdminRecordID, GrantedAt))), 0) FROM RED_Admin_Capabilities),
            (SELECT COUNT(*) FROM RED_Addon_Installations),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', PackageID, PackageVersion, PackageType, ManifestSHA256, InventorySHA256, LifecycleState, InstalledByAdminRecordID, InstalledAt, UpdatedByAdminRecordID, UpdatedAt))), 0) FROM RED_Addon_Installations),
            (SELECT COUNT(*) FROM RED_Addon_Settings),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', PackageID, SettingKey, ValueType, ValueJSON, SecretReference, UpdatedByAdminRecordID, UpdatedAt))), 0) FROM RED_Addon_Settings),
            (SELECT COUNT(*) FROM RED_Addon_Migrations),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', PackageID, MigrationID, MigrationPath, Checksum, AppliedByAdminRecordID, AppliedAt, ExecutionMs))), 0) FROM RED_Addon_Migrations),
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, EventName, PackageID, PackageVersion, ActorAdminRecordID, Result, DetailCode, OccurredAt))), 0) FROM RED_Addon_Activity_Log),
            (SELECT COUNT(*) FROM RED_Addon_Permission_Activity_Log),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, EventName, PackageID, PackageVersion, Permission, TargetAdminRecordID, ActorAdminRecordID, Result, OccurredAt))), 0) FROM RED_Addon_Permission_Activity_Log),
            (SELECT COUNT(*) FROM RED_Addon_Component_Revisions),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RevisionID, ContentRecordID, PackageID, ComponentID, RevisionNumber, Operation, ActorAdminRecordID, ActorAlias, Snapshot, StateHash, RestoredFromRevisionID, CreatedAt))), 0) FROM RED_Addon_Component_Revisions),
            (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', PackageID, ActionID, TargetRecordID, PlanSHA256, ContractSHA256, PreviousStateSHA256, StateSHA256, ActorAdminRecordID, CompletedAt))), 0) FROM RED_Addon_Admin_Action_Executions),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, SubjectTokenSHA256, CreatedAt, ExpiresAt))), 0) FROM RED_Addon_Public_Mutation_Subjects),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, SubjectRecordID, ScopeSHA256, TokenSHA256, CreatedAt, ExpiresAt))), 0) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, SubjectRecordID, ScopeSHA256, WindowStartedAt, RequestCount, ExpiresAt))), 0) FROM RED_Addon_Public_Mutation_Rate_Limits),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, SubjectRecordID, ScopeSHA256, KeySHA256, CreatedAt, ExpiresAt))), 0) FROM RED_Addon_Public_Mutation_Idempotency_Keys),
            (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, IdempotencyRecordID, CommandSHA256, Outcome, PreviousStateSHA256, StateSHA256, CompletedAt))), 0) FROM RED_Addon_Public_Mutation_Executions),
            (SELECT COUNT(*) FROM RED_Articles),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', RecordID, Title, Component, Alias, Sections, Categories, SubCategories, Layout, Active, Updated))), 0) FROM RED_Articles)
        );
    "
}

red_acceptance_schema_manifest() {
    local database="$1"
    red_acceptance_admin_mysql --execute="
        SELECT Definition
        FROM (
            SELECT CONCAT_WS(':', 'T', TABLE_NAME, ENGINE, COALESCE(TABLE_COLLATION, '')) AS Definition
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA='$database'
            UNION ALL
            SELECT CONCAT_WS(
                ':', 'C', TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE,
                IS_NULLABLE, COALESCE(COLUMN_DEFAULT, '<NULL>'), EXTRA, COALESCE(COLLATION_NAME, '')
            ) AS Definition
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA='$database'
            UNION ALL
            SELECT CONCAT_WS(
                ':', 'I', TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX,
                COLUMN_NAME, COALESCE(SUB_PART, ''), COALESCE(COLLATION, '')
            ) AS Definition
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA='$database'
        ) AS SchemaDefinitions
        ORDER BY Definition;
    "
}

red_acceptance_assert_equals() {
    local label="$1"
    local expected="$2"
    local actual="$3"
    if [[ "$actual" != "$expected" ]]; then
        printf 'FAIL: %s (expected %s, got %s)\n' "$label" "$expected" "$actual" >&2
        return 1
    fi
    printf 'PASS: %s = %s\n' "$label" "$actual"
}

red_acceptance_port_in_use() {
    local port="$1"
    if command -v lsof >/dev/null 2>&1; then
        lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
        return $?
    fi

    (echo > "/dev/tcp/127.0.0.1/$port") >/dev/null 2>&1
}

red_acceptance_select_port() {
    local requested_port="${RED_ACCEPTANCE_PORT:-}"
    local attempt=0
    local candidate=0

    if [[ -n "$requested_port" ]]; then
        if [[ ! "$requested_port" =~ ^[0-9]+$ || "$requested_port" -lt 1024 || "$requested_port" -gt 65535 ]]; then
            printf 'Acceptance port must be an integer from 1024 through 65535: %s\n' "$requested_port" >&2
            return 64
        fi
        if [[ "$requested_port" -eq 8055 ]]; then
            printf '%s\n' 'Acceptance port 8055 is reserved for the normal local RED-CMS server.' >&2
            return 65
        fi
        if red_acceptance_port_in_use "$requested_port"; then
            printf 'Acceptance port is already in use: %s\n' "$requested_port" >&2
            return 65
        fi
        ACCEPTANCE_PORT="$requested_port"
        return 0
    fi

    while [[ "$attempt" -lt 50 ]]; do
        candidate=$((18000 + (($$ + attempt) % 20000)))
        if [[ "$candidate" -ne 8055 ]] && ! red_acceptance_port_in_use "$candidate"; then
            ACCEPTANCE_PORT="$candidate"
            return 0
        fi
        attempt=$((attempt + 1))
    done

    printf '%s\n' 'Could not find an unused local acceptance port.' >&2
    return 69
}

red_acceptance_start_php() {
    local attempt=0
    local ready_status=""

    ACCEPTANCE_PHP_LOG="$(mktemp "${TMPDIR:-/tmp}/redcms-acceptance-php.XXXXXX")"
    ACCEPTANCE_RESPONSE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/redcms-acceptance-responses.XXXXXX")"
    ACCEPTANCE_BASE_URL="http://127.0.0.1:$ACCEPTANCE_PORT"

    (
        cd "$RED_PROJECT_ROOT"
        export RED_DB_NAME="$ACCEPTANCE_DATABASE"
        export PORT="$ACCEPTANCE_PORT"
        exec "$SCRIPT_DIR/dev-php-server.sh"
    ) > "$ACCEPTANCE_PHP_LOG" 2>&1 &
    ACCEPTANCE_PHP_PID=$!

    while [[ "$attempt" -lt 100 ]]; do
        if ! kill -0 "$ACCEPTANCE_PHP_PID" 2>/dev/null; then
            printf '%s\n' 'Isolated PHP server exited before becoming ready:' >&2
            sed -n '1,120p' "$ACCEPTANCE_PHP_LOG" >&2
            return 1
        fi

        ready_status="$(curl -sS --max-time 1 -o /dev/null -w '%{http_code}' "$ACCEPTANCE_BASE_URL/" 2>/dev/null || true)"
        if [[ "$ready_status" == "200" ]]; then
            printf 'Isolated PHP server ready on %s.\n' "$ACCEPTANCE_BASE_URL"
            return 0
        fi

        sleep 0.1
        attempt=$((attempt + 1))
    done

    printf 'Isolated PHP server did not become ready on %s:\n' "$ACCEPTANCE_BASE_URL" >&2
    sed -n '1,120p' "$ACCEPTANCE_PHP_LOG" >&2
    return 1
}

red_acceptance_check_route() {
    local label="$1"
    local path="$2"
    local marker_one="$3"
    local marker_two="$4"
    local response_file="$ACCEPTANCE_RESPONSE_DIR/$label.html"
    local metrics=""
    local status=""
    local size=""

    metrics="$(curl -sS --max-time 10 -o "$response_file" -w '%{http_code}:%{size_download}' "$ACCEPTANCE_BASE_URL$path")"
    status="${metrics%%:*}"
    size="${metrics#*:}"

    red_acceptance_assert_equals "$label HTTP status" '200' "$status"
    if [[ ! "$size" =~ ^[0-9]+$ || "$size" -lt 1000 ]]; then
        printf 'FAIL: %s response is unexpectedly small (%s bytes).\n' "$label" "$size" >&2
        return 1
    fi
    if ! grep -Fq "$marker_one" "$response_file"; then
        printf 'FAIL: %s response is missing marker %s.\n' "$label" "$marker_one" >&2
        return 1
    fi
    if ! grep -Fq "$marker_two" "$response_file"; then
        printf 'FAIL: %s response is missing marker %s.\n' "$label" "$marker_two" >&2
        return 1
    fi
    if grep -Eq 'Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]|<b>(Warning|Deprecated|Notice)</b>|PHP (Warning|Deprecated|Notice|Fatal)' "$response_file"; then
        printf 'FAIL: %s response contains a PHP/runtime error marker.\n' "$label" >&2
        grep -En 'Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]|<b>(Warning|Deprecated|Notice)</b>|PHP (Warning|Deprecated|Notice|Fatal)' "$response_file" >&2 || true
        return 1
    fi

    printf 'PASS: %s response = %s bytes with required markers.\n' "$label" "$size"
}

red_acceptance_check_not_found_route() {
    local label="$1"
    local path="$2"
    local forbidden_marker_one="$3"
    local forbidden_marker_two="$4"
    local entity_label="$5"
    local response_file="$ACCEPTANCE_RESPONSE_DIR/$label.html"
    local metrics=""
    local status=""
    local size=""

    metrics="$(curl -sS --max-time 10 -o "$response_file" -w '%{http_code}:%{size_download}' "$ACCEPTANCE_BASE_URL$path")"
    status="${metrics%%:*}"
    size="${metrics#*:}"

    red_acceptance_assert_equals "$label HTTP status" '404' "$status"
    if [[ ! "$size" =~ ^[0-9]+$ || "$size" -lt 1000 ]]; then
        printf 'FAIL: %s 404 response is unexpectedly small (%s bytes).\n' "$label" "$size" >&2
        return 1
    fi
    local missing_marker=''
    if ! tr '\n\r\t' '   ' < "$response_file" \
        | grep -Eq '<title>[[:space:]]*([^<]+[[:space:]]+\|[[:space:]]+)?Page not found[[:space:]]*</title>'; then
        missing_marker='title'
    elif ! grep -Fq 'class="red-public-not-found"' "$response_file"; then
        missing_marker='body'
    elif ! grep -Fq '>Return to the homepage</a>' "$response_file"; then
        missing_marker='homepage action'
    fi
    if [[ -n "$missing_marker" ]]; then
        printf 'FAIL: %s response is missing the fixed not-found %s marker.\n' "$label" "$missing_marker" >&2
        if [[ "$missing_marker" == 'title' ]]; then
            printf 'Observed title: ' >&2
            tr '\n\r\t' '   ' < "$response_file" \
                | grep -Eo '<title>[^<]*</title>' >&2 \
                || printf '(none)\n' >&2
        fi
        return 1
    fi
    if [[ -n "$forbidden_marker_one" ]] && grep -Fq "$forbidden_marker_one" "$response_file"; then
        printf 'FAIL: %s still renders forbidden %s marker %s.\n' "$label" "$entity_label" "$forbidden_marker_one" >&2
        return 1
    fi
    if [[ -n "$forbidden_marker_two" ]] && grep -Fq "$forbidden_marker_two" "$response_file"; then
        printf 'FAIL: %s still renders forbidden %s marker %s.\n' "$label" "$entity_label" "$forbidden_marker_two" >&2
        return 1
    fi
    if grep -Eq 'Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]|<b>(Warning|Deprecated|Notice)</b>|PHP (Warning|Deprecated|Notice|Fatal)' "$response_file"; then
        printf 'FAIL: %s response contains a PHP/runtime error marker.\n' "$label" >&2
        return 1
    fi

    printf 'PASS: %s is an active-theme 404 and no longer renders the disposable %s.\n' "$label" "$entity_label"
}

red_acceptance_addon_asset_endpoint_fixture() {
    RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli \
        "$RED_PROJECT_ROOT/scripts/addon-asset-endpoint-self-test.php" "$1"
}

red_acceptance_addon_asset_endpoint_cleanup() {
    if [[ "$ACCEPTANCE_ADDON_ASSET_ENDPOINT_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_addon_asset_endpoint_fixture --runtime-cleanup
        if [[ -e "$RED_PROJECT_ROOT/addons/redcms/asset-endpoint-fixture" \
            || -e "$RED_PROJECT_ROOT/addons/.redcms-acceptance-asset-endpoint-fixture" ]]; then
            printf '%s\n' 'Cleanup failure: add-on asset endpoint fixture files remain.' >&2
            return 1
        fi
        ACCEPTANCE_ADDON_ASSET_ENDPOINT_FIXTURE_CREATED=0
    fi
}

red_acceptance_addon_asset_endpoint_metadata() {
    local metadata="$1"
    local key="$2"
    local value=""

    value="$(printf '%s\n' "$metadata" | awk -F= -v key="$key" '$1 == key { sub(/^[^=]*=/, ""); print; exit }')"
    printf '%s' "$value"
}

red_acceptance_run_addon_asset_endpoint() {
    local metadata=""
    local css_url=""
    local css_sha256=""
    local css_length=""
    local execution_marker=""
    local stale_sha256=""
    local metrics=""
    local status=""
    local size=""
    local body=""
    local headers=""
    local headers_clean=""
    local head_body=""
    local head_headers=""
    local head_headers_clean=""
    local post_body=""
    local post_headers=""
    local post_headers_clean=""
    local not_found_body=""
    local not_found_headers=""
    local not_found_headers_clean=""
    local traversal_url=""

    ACCEPTANCE_ADDON_ASSET_ENDPOINT_FIXTURE_CREATED=1
    metadata="$(red_acceptance_addon_asset_endpoint_fixture --runtime-setup)"
    css_url="$(red_acceptance_addon_asset_endpoint_metadata "$metadata" cssUrl)"
    css_sha256="$(red_acceptance_addon_asset_endpoint_metadata "$metadata" cssSha256)"
    css_length="$(red_acceptance_addon_asset_endpoint_metadata "$metadata" cssLength)"
    execution_marker="$(red_acceptance_addon_asset_endpoint_metadata "$metadata" executionMarker)"
    if [[ ! "$css_url" =~ ^/_red/addons/redcms/asset-endpoint-fixture/assets/public/endpoint\.css\?v=[a-f0-9]{64}$ \
        || ! "$css_sha256" =~ ^[a-f0-9]{64}$ \
        || ! "$css_length" =~ ^[0-9]+$ \
        || "$execution_marker" != "$RED_PROJECT_ROOT/addons/redcms/asset-endpoint-fixture/.asset-endpoint-executed" ]]; then
        printf '%s\n' 'FAIL: add-on asset endpoint fixture metadata is invalid.' >&2
        return 1
    fi

    body="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint.css"
    headers="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint.headers"
    headers_clean="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint.headers.clean"
    metrics="$(curl -sS --max-time 10 -D "$headers" -o "$body" -w '%{http_code}:%{size_download}' "$ACCEPTANCE_BASE_URL$css_url")"
    status="${metrics%%:*}"
    size="${metrics#*:}"
    red_acceptance_assert_equals 'add-on asset endpoint GET HTTP status' '200' "$status"
    red_acceptance_assert_equals 'add-on asset endpoint GET byte count' "$css_length" "$size"
    red_acceptance_assert_equals 'add-on asset endpoint GET SHA-256' "$css_sha256" "$(red_sha256_file "$body")"
    tr -d '\r' < "$headers" > "$headers_clean"
    for header in \
        'Content-Type: text/css; charset=UTF-8' \
        'Cache-Control: public, max-age=31536000, immutable' \
        'X-Content-Type-Options: nosniff' \
        'Accept-Ranges: none' \
        "Content-Length: $css_length"; do
        if ! grep -Fqx "$header" "$headers_clean"; then
            printf 'FAIL: add-on asset endpoint GET is missing header %s.\n' "$header" >&2
            return 1
        fi
    done
    if grep -Eqi '^Set-Cookie:' "$headers_clean" \
        || [[ -e "$execution_marker" ]]; then
        printf '%s\n' 'FAIL: add-on asset endpoint started a session or executed package PHP.' >&2
        return 1
    fi

    head_body="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-head.body"
    head_headers="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-head.headers"
    head_headers_clean="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-head.headers.clean"
    metrics="$(curl -sS --max-time 10 --head -D "$head_headers" -o "$head_body" -w '%{http_code}:%{size_download}' "$ACCEPTANCE_BASE_URL$css_url")"
    status="${metrics%%:*}"
    size="${metrics#*:}"
    red_acceptance_assert_equals 'add-on asset endpoint HEAD HTTP status' '200' "$status"
    red_acceptance_assert_equals 'add-on asset endpoint HEAD body bytes' '0' "$size"
    tr -d '\r' < "$head_headers" > "$head_headers_clean"
    if ! grep -Fqx "Content-Length: $css_length" "$head_headers_clean" \
        || grep -Eqi '^Set-Cookie:' "$head_headers_clean" \
        || [[ -e "$execution_marker" ]]; then
        printf '%s\n' 'FAIL: add-on asset endpoint HEAD evidence is invalid.' >&2
        return 1
    fi

    post_body="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-post.body"
    post_headers="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-post.headers"
    post_headers_clean="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-post.headers.clean"
    metrics="$(curl -sS --max-time 10 -X POST -D "$post_headers" -o "$post_body" -w '%{http_code}' "$ACCEPTANCE_BASE_URL$css_url")"
    red_acceptance_assert_equals 'add-on asset endpoint POST HTTP status' '405' "$metrics"
    red_acceptance_assert_equals 'add-on asset endpoint POST body' 'Method not allowed.' "$(red_acceptance_response_text "$post_body")"
    tr -d '\r' < "$post_headers" > "$post_headers_clean"
    if ! grep -Fqx 'Allow: GET, HEAD' "$post_headers_clean" \
        || grep -Eqi '^Set-Cookie:' "$post_headers_clean" \
        || [[ -e "$execution_marker" ]]; then
        printf '%s\n' 'FAIL: add-on asset endpoint POST refusal is invalid.' >&2
        return 1
    fi

    stale_sha256="$css_sha256"
    if [[ "${stale_sha256:0:1}" == '0' ]]; then
        stale_sha256="1${stale_sha256:1}"
    else
        stale_sha256="0${stale_sha256:1}"
    fi
    not_found_body="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-not-found.body"
    not_found_headers="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-not-found.headers"
    not_found_headers_clean="$ACCEPTANCE_RESPONSE_DIR/addon-asset-endpoint-not-found.headers.clean"
    metrics="$(curl -sS --max-time 10 -D "$not_found_headers" -o "$not_found_body" -w '%{http_code}' "$ACCEPTANCE_BASE_URL/_red/addons/redcms/asset-endpoint-fixture/assets/public/endpoint.css?v=$stale_sha256")"
    red_acceptance_assert_equals 'add-on asset endpoint stale checksum HTTP status' '404' "$metrics"
    red_acceptance_assert_equals 'add-on asset endpoint stale checksum body' 'Not found.' "$(red_acceptance_response_text "$not_found_body")"
    tr -d '\r' < "$not_found_headers" > "$not_found_headers_clean"
    if ! grep -Fqx 'Cache-Control: no-store' "$not_found_headers_clean" \
        || grep -Eqi '^Set-Cookie:' "$not_found_headers_clean" \
        || [[ -e "$execution_marker" ]]; then
        printf '%s\n' 'FAIL: add-on asset endpoint stale checksum refusal is invalid.' >&2
        return 1
    fi

    traversal_url="/_red/addons/redcms/asset-endpoint-fixture/assets/public/../endpoint.css?v=$css_sha256"
    metrics="$(curl -sS --max-time 10 --path-as-is -o "$not_found_body" -w '%{http_code}' "$ACCEPTANCE_BASE_URL$traversal_url")"
    red_acceptance_assert_equals 'add-on asset endpoint traversal HTTP status' '404' "$metrics"
    red_acceptance_assert_equals 'add-on asset endpoint traversal body' 'Not found.' "$(red_acceptance_response_text "$not_found_body")"

    red_acceptance_addon_asset_endpoint_fixture --runtime-disable
    metrics="$(curl -sS --max-time 10 -o "$not_found_body" -w '%{http_code}' "$ACCEPTANCE_BASE_URL$css_url")"
    red_acceptance_assert_equals 'add-on asset endpoint disabled HTTP status' '404' "$metrics"
    red_acceptance_addon_asset_endpoint_fixture --runtime-enable

    red_acceptance_addon_asset_endpoint_fixture --runtime-tamper
    metrics="$(curl -sS --max-time 10 -o "$not_found_body" -w '%{http_code}' "$ACCEPTANCE_BASE_URL$css_url")"
    red_acceptance_assert_equals 'add-on asset endpoint tampered HTTP status' '404' "$metrics"
    red_acceptance_addon_asset_endpoint_fixture --runtime-restore
    metrics="$(curl -sS --max-time 10 -o "$body" -w '%{http_code}' "$ACCEPTANCE_BASE_URL$css_url")"
    red_acceptance_assert_equals 'add-on asset endpoint restored HTTP status' '200' "$metrics"
    red_acceptance_assert_equals 'add-on asset endpoint restored SHA-256' "$css_sha256" "$(red_sha256_file "$body")"
    if [[ -e "$execution_marker" ]]; then
        printf '%s\n' 'FAIL: add-on asset endpoint lifecycle or integrity checks executed package PHP.' >&2
        return 1
    fi

    red_acceptance_addon_asset_endpoint_cleanup
    printf '%s\n' 'PASS: static immutable add-on asset endpoint served exact bytes and rejected method, checksum, traversal, lifecycle, and integrity drift without sessions or package execution.'
}

red_acceptance_addon_asset_injection_fixture() {
    RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli \
        "$RED_PROJECT_ROOT/scripts/addon-asset-injection-self-test.php" "$1"
}

red_acceptance_addon_asset_injection_metadata() {
    local metadata="$1"
    local key="$2"
    local value=""

    value="$(printf '%s\n' "$metadata" | awk -F= -v key="$key" '$1 == key { sub(/^[^=]*=/, ""); print; exit }')"
    printf '%s' "$value"
}

red_acceptance_addon_asset_injection_admin_artifacts() {
    red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_RECORD_ID
                OR Username='$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_USERNAME'),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_RECORD_ID
                OR (TargetType='administrator'
                    AND TargetRecordID=$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_RECORD_ID))
        );
    "
}

red_acceptance_remove_addon_asset_injection_admin() {
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_RECORD_ID
           OR (TargetType='administrator'
               AND TargetRecordID=$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_RECORD_ID);
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_USERNAME')), 256));
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_RECORD_ID
           OR Username='$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_USERNAME';
    "
}

red_acceptance_addon_asset_injection_cleanup() {
    if [[ "$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_CREATED" -eq 1 ]]; then
        red_acceptance_remove_addon_asset_injection_admin
        if [[ "$(red_acceptance_addon_asset_injection_admin_artifacts)" != '0:0:0' ]]; then
            printf '%s\n' 'Cleanup failure: add-on asset injection administrator fixture remains.' >&2
            return 1
        fi
        ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_CREATED=0
    fi
    if [[ "$ACCEPTANCE_ADDON_ASSET_INJECTION_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_addon_asset_injection_fixture --runtime-cleanup
        if [[ -e "$RED_PROJECT_ROOT/addons/redcms/asset-injection-fixture" \
            || -e "$RED_PROJECT_ROOT/addons" ]]; then
            printf '%s\n' 'Cleanup failure: add-on asset injection fixture files remain.' >&2
            return 1
        fi
        ACCEPTANCE_ADDON_ASSET_INJECTION_FIXTURE_CREATED=0
    fi
}

red_acceptance_run_addon_asset_injection() {
    local metadata=""
    local public_css_url=""
    local public_script_url=""
    local admin_css_url=""
    local admin_script_url=""
    local execution_marker=""
    local anonymous_home="$ACCEPTANCE_RESPONSE_DIR/addon-asset-injection-anonymous.html"
    local authenticated_home="$ACCEPTANCE_RESPONSE_DIR/addon-asset-injection-authenticated.html"
    local metrics=""
    local status=""

    ACCEPTANCE_ADDON_ASSET_INJECTION_FIXTURE_CREATED=1
    metadata="$(red_acceptance_addon_asset_injection_fixture --runtime-setup)"
    public_css_url="$(red_acceptance_addon_asset_injection_metadata "$metadata" publicCssUrl)"
    public_script_url="$(red_acceptance_addon_asset_injection_metadata "$metadata" publicScriptUrl)"
    admin_css_url="$(red_acceptance_addon_asset_injection_metadata "$metadata" adminCssUrl)"
    admin_script_url="$(red_acceptance_addon_asset_injection_metadata "$metadata" adminScriptUrl)"
    execution_marker="$(red_acceptance_addon_asset_injection_metadata "$metadata" executionMarker)"
    if [[ ! "$public_css_url" =~ ^/_red/addons/redcms/asset-injection-fixture/assets/public/injection\.css\?v=[a-f0-9]{64}$ \
        || ! "$public_script_url" =~ ^/_red/addons/redcms/asset-injection-fixture/assets/public/injection\.js\?v=[a-f0-9]{64}$ \
        || ! "$admin_css_url" =~ ^/_red/addons/redcms/asset-injection-fixture/assets/admin/injection\.css\?v=[a-f0-9]{64}$ \
        || ! "$admin_script_url" =~ ^/_red/addons/redcms/asset-injection-fixture/assets/admin/injection\.js\?v=[a-f0-9]{64}$ \
        || -z "$execution_marker" \
        || -e "$execution_marker" ]]; then
        printf '%s\n' 'FAIL: add-on asset injection fixture metadata is invalid.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 -o "$anonymous_home" -w '%{http_code}' "$ACCEPTANCE_BASE_URL/")"
    status="$metrics"
    red_acceptance_assert_equals 'add-on asset injection anonymous homepage HTTP status' '200' "$status"
    if ! grep -Fq "<link rel=\"stylesheet\" href=\"$public_css_url\">" "$anonymous_home" \
        || ! grep -Fq "<script src=\"$public_script_url\" defer></script>" "$anonymous_home" \
        || grep -Fq "$admin_css_url" "$anonymous_home" \
        || grep -Fq "$admin_script_url" "$anonymous_home"; then
        printf '%s\n' 'FAIL: anonymous document did not keep public/admin add-on asset surfaces isolated.' >&2
        return 1
    fi
    if [[ ! -f "$execution_marker" \
        || "$(wc -l < "$execution_marker" | tr -d '[:space:]')" != '1' ]] \
        || ! grep -Fqx 'runtime:redcms.asset-injection-fixture' "$execution_marker"; then
        printf '%s\n' 'FAIL: anonymous document injection did not preserve one normal runtime registration.' >&2
        return 1
    fi

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_RECORD_ID,
            '$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_USERNAME',
            '$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_PASSWORD',
            'Admin',
            '$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_ALIAS',
            'webmaster',
            '100,102,103,104,105,117,107,111,116',
            '1,2',
            'asset-injection-acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
    "
    ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_CREATED=1
    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/addon-asset-injection.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"
    red_acceptance_post_login \
        'add-on-asset-injection-webmaster-login' \
        "$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_USERNAME" \
        "$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_PASSWORD" \
        'yes'
    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$authenticated_home" \
        -w '%{http_code}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="$metrics"
    red_acceptance_assert_equals 'add-on asset injection authenticated homepage HTTP status' '200' "$status"
    if ! grep -Fq "<link rel=\"stylesheet\" href=\"$public_css_url\">" "$authenticated_home" \
        || ! grep -Fq "<script src=\"$public_script_url\" defer></script>" "$authenticated_home" \
        || ! grep -Fq "<link rel=\"stylesheet\" href=\"$admin_css_url\">" "$authenticated_home" \
        || ! grep -Fq "<script src=\"$admin_script_url\" defer></script>" "$authenticated_home" \
        || ! grep -Fq "$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_ALIAS" "$authenticated_home"; then
        printf '%s\n' 'FAIL: authenticated administrator document is missing its core-owned add-on asset references.' >&2
        return 1
    fi
    if [[ "$(wc -l < "$execution_marker" | tr -d '[:space:]')" != '2' ]] \
        || [[ "$(grep -Fxc 'runtime:redcms.asset-injection-fixture' "$execution_marker")" != '2' ]]; then
        printf '%s\n' 'FAIL: administrator document injection caused unexpected package runtime execution.' >&2
        return 1
    fi

    red_acceptance_addon_asset_injection_cleanup
    printf '%s\n' 'PASS: core-owned add-on asset injection keeps anonymous public tags separate from authenticated administrator tags and preserves normal runtime execution.'
}

red_acceptance_response_text() {
    local value=""
    value="$(<"$1")"
    value="${value//$'\r'/}"
    value="${value//$'\n'/}"
    printf '%s' "$value"
}

red_acceptance_post_login() {
    local label="$1"
    local username="$2"
    local password="$3"
    local expected_body="$4"
    local response_file="$ACCEPTANCE_RESPONSE_DIR/$label.txt"
    local metrics=""
    local status=""
    local body=""

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$response_file" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "username=$username" \
        --data-urlencode "password=$password" \
        "$ACCEPTANCE_BASE_URL/bin/login.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$response_file")"

    red_acceptance_assert_equals "$label HTTP status" '200' "$status"
    red_acceptance_assert_equals "$label response" "$expected_body" "$body"
}

red_acceptance_remove_auth_fixture() {
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_AUTH_RECORD_ID
           OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_AUTH_RECORD_ID);
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash IN (
            UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_USERNAME')), 256)),
            UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_UNKNOWN_USERNAME')), 256))
        );
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_AUTH_RECORD_ID
           OR Username='$ACCEPTANCE_AUTH_USERNAME';
    "
}

red_acceptance_auth_artifact_counts() {
    red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_AUTH_RECORD_ID OR Username='$ACCEPTANCE_AUTH_USERNAME'),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash IN (
                 UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_USERNAME')), 256)),
                 UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_UNKNOWN_USERNAME')), 256))
             )),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_AUTH_RECORD_ID
                OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_AUTH_RECORD_ID))
        );
    "
}

red_acceptance_run_authentication() {
    local failure_counts=""
    local password_state=""
    local upgraded_password_hash=""
    local second_password_hash=""
    local metrics=""
    local status=""
    local body=""
    local csrf_token=""
    local auth_home="$ACCEPTANCE_RESPONSE_DIR/authenticated-home.html"
    local protected_view="$ACCEPTANCE_RESPONSE_DIR/authenticated-users.html"
    local csrf_denial="$ACCEPTANCE_RESPONSE_DIR/csrf-denial.txt"
    local addon_action_method_headers="$ACCEPTANCE_RESPONSE_DIR/addon-action-method.headers"
    local addon_action_method_denial="$ACCEPTANCE_RESPONSE_DIR/addon-action-method.json"
    local addon_action_csrf_denial="$ACCEPTANCE_RESPONSE_DIR/addon-action-csrf.txt"
    local addon_action_invalid_request="$ACCEPTANCE_RESPONSE_DIR/addon-action-invalid.json"
    local addon_form_method_headers="$ACCEPTANCE_RESPONSE_DIR/addon-form-method.headers"
    local addon_form_method_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-method.json"
    local addon_form_csrf_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-csrf.txt"
    local addon_form_content_type_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-content-type.json"
    local addon_form_invalid_request="$ACCEPTANCE_RESPONSE_DIR/addon-form-invalid.json"
    local addon_form_edit_method_headers="$ACCEPTANCE_RESPONSE_DIR/addon-form-edit-method.headers"
    local addon_form_edit_method_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-edit-method.txt"
    local addon_form_edit_csrf_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-edit-csrf.txt"
    local addon_form_edit_unavailable="$ACCEPTANCE_RESPONSE_DIR/addon-form-edit-unavailable.html"
    local addon_form_save_method_headers="$ACCEPTANCE_RESPONSE_DIR/addon-form-save-method.headers"
    local addon_form_save_method_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-save-method.json"
    local addon_form_save_csrf_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-save-csrf.txt"
    local addon_form_save_invalid_request="$ACCEPTANCE_RESPONSE_DIR/addon-form-save-invalid.json"
    local addon_form_new_method_headers="$ACCEPTANCE_RESPONSE_DIR/addon-form-new-method.headers"
    local addon_form_new_method_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-new-method.txt"
    local addon_form_new_csrf_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-new-csrf.txt"
    local addon_form_new_unavailable="$ACCEPTANCE_RESPONSE_DIR/addon-form-new-unavailable.html"
    local addon_form_create_method_headers="$ACCEPTANCE_RESPONSE_DIR/addon-form-create-method.headers"
    local addon_form_create_method_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-create-method.json"
    local addon_form_create_csrf_denial="$ACCEPTANCE_RESPONSE_DIR/addon-form-create-csrf.txt"
    local addon_form_create_invalid_request="$ACCEPTANCE_RESPONSE_DIR/addon-form-create-invalid.json"
    local logout_headers="$ACCEPTANCE_RESPONSE_DIR/logout-headers.txt"
    local logout_body="$ACCEPTANCE_RESPONSE_DIR/logout-body.txt"
    local logout_denial="$ACCEPTANCE_RESPONSE_DIR/logout-denial.txt"
    local deleted_denial="$ACCEPTANCE_RESPONSE_DIR/deleted-denial.txt"
    local auth_artifacts=""
    local fixture_state=""

    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/auth.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_AUTH_RECORD_ID,
            '$ACCEPTANCE_AUTH_USERNAME',
            '$ACCEPTANCE_AUTH_PASSWORD',
            'Admin',
            '$ACCEPTANCE_AUTH_ALIAS',
            'webmaster',
            '100,102,103,104,105,117,107,111,116',
            '1,2',
            'acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
    "
    ACCEPTANCE_AUTH_FIXTURE_CREATED=1
    red_acceptance_inject_failure after_auth_fixture

    fixture_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(':', COUNT(*), SUM(Password='$ACCEPTANCE_AUTH_PASSWORD'), SUM(AdminType='webmaster'))
        FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_AUTH_RECORD_ID AND Username='$ACCEPTANCE_AUTH_USERNAME';
    ")"
    red_acceptance_assert_equals 'temporary legacy Webmaster fixture' '1:1:1' "$fixture_state"

    red_acceptance_post_login 'known-user-failure' "$ACCEPTANCE_AUTH_USERNAME" 'DefinitelyWrong-2026!' 'no'
    red_acceptance_post_login 'unknown-user-failure' "$ACCEPTANCE_AUTH_UNKNOWN_USERNAME" 'DefinitelyWrong-2026!' 'no'
    failure_counts="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_UNKNOWN_USERNAME')), 256)))
        );
    ")"
    red_acceptance_assert_equals 'generic failed-login throttle fixtures' '1:1' "$failure_counts"

    red_acceptance_post_login 'legacy-password-login' "$ACCEPTANCE_AUTH_USERNAME" "$ACCEPTANCE_AUTH_PASSWORD" 'yes'
    password_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(':', CHAR_LENGTH(Password), LEFT(Password, 4), Password<>'$ACCEPTANCE_AUTH_PASSWORD')
        FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_AUTH_RECORD_ID;
    ")"
    red_acceptance_assert_equals 'legacy password bcrypt upgrade' '60:$2y$:1' "$password_state"
    upgraded_password_hash="$(red_acceptance_app_mysql --execute="
        SELECT Password FROM RED_Admin WHERE RecordID=$ACCEPTANCE_AUTH_RECORD_ID;
    ")"

    failure_counts="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_UNKNOWN_USERNAME')), 256)))
        );
    ")"
    red_acceptance_assert_equals 'successful login clears only its username failures' '0:1' "$failure_counts"
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_AUTH_UNKNOWN_USERNAME')), 256));
    "
    red_acceptance_assert_equals \
        'temporary throttle cleanup' \
        '0' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Login_Attempts;')"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$auth_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'authenticated homepage HTTP status' '200' "$status"
    if ! grep -Fq "$ACCEPTANCE_AUTH_ALIAS" "$auth_home" || ! grep -Fq 'var RED_CSRF_TOKEN = ' "$auth_home"; then
        printf '%s\n' 'FAIL: authenticated homepage is missing the fixture alias or CSRF bootstrap marker.' >&2
        return 1
    fi
    csrf_token="$(sed -n 's/.*var RED_CSRF_TOKEN = "\([a-f0-9]\{64\}\)";.*/\1/p' "$auth_home" | head -n 1)"
    if [[ ! "$csrf_token" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'FAIL: authenticated homepage did not expose a valid session CSRF token.' >&2
        return 1
    fi
    if grep -Eq 'Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]|<b>(Warning|Deprecated|Notice)</b>|PHP (Warning|Deprecated|Notice|Fatal)' "$auth_home"; then
        printf '%s\n' 'FAIL: authenticated homepage contains a PHP/runtime error marker.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: authenticated overlay and CSRF bootstrap marker rendered cleanly.'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$protected_view" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'view=add' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_admin_users.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'authenticated administrator view HTTP status' '200' "$status"
    if ! grep -Fq 'Administrator Users' "$protected_view" \
        || ! grep -Fq 'name="csrf_token"' "$protected_view" \
        || ! grep -Fq "value=\"$csrf_token\"" "$protected_view"; then
        printf '%s\n' 'FAIL: protected administrator view is missing its matching CSRF form token.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: protected administrator view uses the authenticated session CSRF token.'

    metrics="$(curl -sS --max-time 10 \
        -D "$addon_action_method_headers" \
        -o "$addon_action_method_denial" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/run_addon_tool_action.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_action_method_denial")"
    red_acceptance_assert_equals 'administrator action endpoint method HTTP status' '405' "$status"
    red_acceptance_assert_equals \
        'administrator action endpoint method body' \
        '{"ok":false,"reason":"method_not_allowed"}' \
        "$body"
    if ! grep -Eqi '^Allow:[[:space:]]*POST\r?$' "$addon_action_method_headers" \
        || grep -Eqi '^Set-Cookie:' "$addon_action_method_headers"; then
        printf '%s\n' 'FAIL: administrator action endpoint method refusal is not request-free.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_action_csrf_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "tool=redcms.missing/tool" \
        --data-urlencode "action=redcms.missing/action" \
        --data-urlencode 'targetRecordId=1' \
        "$ACCEPTANCE_BASE_URL/admin/bin/run_addon_tool_action.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_action_csrf_denial")"
    red_acceptance_assert_equals 'administrator action endpoint missing-CSRF HTTP status' '403' "$status"
    red_acceptance_assert_equals 'administrator action endpoint missing-CSRF response' 'csrf' "$body"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_action_invalid_request" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "csrf_token=$csrf_token" \
        --data-urlencode "tool=redcms.missing/tool" \
        "$ACCEPTANCE_BASE_URL/admin/bin/run_addon_tool_action.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_action_invalid_request")"
    red_acceptance_assert_equals 'administrator action endpoint invalid-request HTTP status' '400' "$status"
    red_acceptance_assert_equals \
        'administrator action endpoint invalid-request body' \
        '{"ok":false,"reason":"invalid_request"}' \
        "$body"
    printf '%s\n' 'PASS: unlinked administrator action endpoint enforces POST, current session CSRF, and exact request fields.'

    metrics="$(curl -sS --max-time 10 \
        -D "$addon_form_method_headers" \
        -o "$addon_form_method_denial" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/validate_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_method_denial")"
    red_acceptance_assert_equals 'administrator form JSON endpoint method HTTP status' '405' "$status"
    red_acceptance_assert_equals \
        'administrator form JSON endpoint method body' \
        '{"ok":false,"reason":"method_not_allowed"}' \
        "$body"
    if ! grep -Eqi '^Allow:[[:space:]]*POST\r?$' "$addon_form_method_headers" \
        || grep -Eqi '^Set-Cookie:' "$addon_form_method_headers"; then
        printf '%s\n' 'FAIL: administrator form JSON method refusal is not request-free.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_csrf_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H 'Content-Type: application/json' \
        --data-binary '{}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/validate_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_csrf_denial")"
    red_acceptance_assert_equals 'administrator form JSON endpoint missing-CSRF HTTP status' '403' "$status"
    red_acceptance_assert_equals 'administrator form JSON endpoint missing-CSRF response' 'csrf' "$body"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_content_type_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        -H 'Content-Type: text/plain' \
        --data-binary '{}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/validate_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_content_type_denial")"
    red_acceptance_assert_equals 'administrator form JSON endpoint content-type HTTP status' '415' "$status"
    red_acceptance_assert_equals \
        'administrator form JSON endpoint content-type body' \
        '{"ok":false,"reason":"content_type_invalid"}' \
        "$body"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_invalid_request" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        -H 'Content-Type: application/json' \
        --data-binary '{}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/validate_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_invalid_request")"
    red_acceptance_assert_equals 'administrator form JSON endpoint invalid-request HTTP status' '400' "$status"
    red_acceptance_assert_equals \
        'administrator form JSON endpoint invalid-request body' \
        '{"ok":false,"reason":"invalid_request"}' \
        "$body"
    printf '%s\n' 'PASS: unlinked administrator form JSON endpoint authenticates and verifies header CSRF before bounded body validation.'

    metrics="$(curl -sS --max-time 10 \
        -D "$addon_form_edit_method_headers" \
        -o "$addon_form_edit_method_denial" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_edit_method_denial")"
    red_acceptance_assert_equals 'administrator form editor method HTTP status' '405' "$status"
    red_acceptance_assert_equals 'administrator form editor method body' 'no' "$body"
    if ! grep -Eqi '^Allow:[[:space:]]*POST\r?$' "$addon_form_edit_method_headers" \
        || grep -Eqi '^Set-Cookie:' "$addon_form_edit_method_headers"; then
        printf '%s\n' 'FAIL: administrator form editor method refusal is not request-free.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_edit_csrf_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'tool=redcms.missing/tool' \
        --data-urlencode 'form=redcms.missing/form' \
        --data-urlencode 'targetRecordId=1' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_edit_csrf_denial")"
    red_acceptance_assert_equals 'administrator form editor missing-CSRF HTTP status' '403' "$status"
    red_acceptance_assert_equals 'administrator form editor missing-CSRF body' 'csrf' "$body"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_edit_unavailable" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        --data-urlencode 'tool=redcms.missing/tool' \
        --data-urlencode 'form=redcms.missing/form' \
        --data-urlencode 'targetRecordId=1' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_edit_unavailable")"
    red_acceptance_assert_equals 'administrator form editor unavailable HTTP status' '422' "$status"
    if [[ "$body" != *'data-red-addon-admin-tool-form-unavailable'* ]]; then
        printf '%s\n' 'FAIL: administrator form editor did not return its static unavailable state.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -D "$addon_form_save_method_headers" \
        -o "$addon_form_save_method_denial" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/save_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_save_method_denial")"
    red_acceptance_assert_equals 'administrator form Save method HTTP status' '405' "$status"
    red_acceptance_assert_equals \
        'administrator form Save method body' \
        '{"ok":false,"reason":"method_not_allowed"}' \
        "$body"
    if ! grep -Eqi '^Allow:[[:space:]]*POST\r?$' "$addon_form_save_method_headers" \
        || grep -Eqi '^Set-Cookie:' "$addon_form_save_method_headers"; then
        printf '%s\n' 'FAIL: administrator form Save method refusal is not request-free.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_save_csrf_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H 'Content-Type: application/json' \
        --data-binary '{}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/save_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_save_csrf_denial")"
    red_acceptance_assert_equals 'administrator form Save missing-CSRF HTTP status' '403' "$status"
    red_acceptance_assert_equals 'administrator form Save missing-CSRF body' 'csrf' "$body"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_save_invalid_request" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        -H 'Content-Type: application/json' \
        --data-binary '{}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/save_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_save_invalid_request")"
    red_acceptance_assert_equals 'administrator form Save invalid-request HTTP status' '400' "$status"
    red_acceptance_assert_equals \
        'administrator form Save invalid-request body' \
        '{"ok":false,"reason":"invalid_request"}' \
        "$body"

    metrics="$(curl -sS --max-time 10 \
        -D "$addon_form_new_method_headers" \
        -o "$addon_form_new_method_denial" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/new_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_new_method_denial")"
    red_acceptance_assert_equals 'administrator form Create editor method HTTP status' '405' "$status"
    red_acceptance_assert_equals 'administrator form Create editor method body' 'no' "$body"
    if ! grep -Eqi '^Allow:[[:space:]]*POST\r?$' "$addon_form_new_method_headers" \
        || grep -Eqi '^Set-Cookie:' "$addon_form_new_method_headers"; then
        printf '%s\n' 'FAIL: administrator form Create editor method refusal is not request-free.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_new_csrf_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'tool=redcms.missing/tool' \
        --data-urlencode 'form=redcms.missing/form' \
        "$ACCEPTANCE_BASE_URL/admin/bin/new_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_new_csrf_denial")"
    red_acceptance_assert_equals 'administrator form Create editor missing-CSRF HTTP status' '403' "$status"
    red_acceptance_assert_equals 'administrator form Create editor missing-CSRF body' 'csrf' "$body"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_new_unavailable" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        --data-urlencode 'tool=redcms.missing/tool' \
        --data-urlencode 'form=redcms.missing/form' \
        "$ACCEPTANCE_BASE_URL/admin/bin/new_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_new_unavailable")"
    red_acceptance_assert_equals 'administrator form Create editor unavailable HTTP status' '422' "$status"
    if [[ "$body" != *'data-red-addon-admin-tool-form-unavailable'* ]]; then
        printf '%s\n' 'FAIL: administrator form Create editor did not return its static unavailable state.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -D "$addon_form_create_method_headers" \
        -o "$addon_form_create_method_denial" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/create_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_create_method_denial")"
    red_acceptance_assert_equals 'administrator form Create method HTTP status' '405' "$status"
    red_acceptance_assert_equals \
        'administrator form Create method body' \
        '{"ok":false,"reason":"method_not_allowed"}' \
        "$body"
    if ! grep -Eqi '^Allow:[[:space:]]*POST\r?$' "$addon_form_create_method_headers" \
        || grep -Eqi '^Set-Cookie:' "$addon_form_create_method_headers"; then
        printf '%s\n' 'FAIL: administrator form Create method refusal is not request-free.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_create_csrf_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H 'Content-Type: application/json' \
        --data-binary '{}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/create_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_create_csrf_denial")"
    red_acceptance_assert_equals 'administrator form Create missing-CSRF HTTP status' '403' "$status"
    red_acceptance_assert_equals 'administrator form Create missing-CSRF body' 'csrf' "$body"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$addon_form_create_invalid_request" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        -H 'Content-Type: application/json' \
        --data-binary '{}' \
        "$ACCEPTANCE_BASE_URL/admin/bin/create_addon_tool_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$addon_form_create_invalid_request")"
    red_acceptance_assert_equals 'administrator form Create invalid-request HTTP status' '400' "$status"
    red_acceptance_assert_equals \
        'administrator form Create invalid-request body' \
        '{"ok":false,"reason":"invalid_request"}' \
        "$body"
    printf '%s\n' 'PASS: administrator form edit, Save, draft, and Create endpoints enforce method, session, header CSRF, exact identity, and canonical JSON before runtime work.'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$csrf_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        "$ACCEPTANCE_BASE_URL/admin/bin/update_layout.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$csrf_denial")"
    red_acceptance_assert_equals 'missing-CSRF HTTP status' '403' "$status"
    red_acceptance_assert_equals 'missing-CSRF response' 'csrf' "$body"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -D "$logout_headers" \
        -o "$logout_body" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/bin/logout.php?logout")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'logout HTTP status' '302' "$status"
    if ! grep -Eqi '^Location:[[:space:]]*/[[:space:]]*\r?$' "$logout_headers"; then
        printf '%s\n' 'FAIL: logout response did not redirect to the public homepage.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$logout_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'view=list' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_admin_users.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$logout_denial")"
    red_acceptance_assert_equals 'logged-out protected HTTP status' '403' "$status"
    red_acceptance_assert_equals 'logged-out protected response' 'no' "$body"

    red_acceptance_post_login 'hashed-password-login' "$ACCEPTANCE_AUTH_USERNAME" "$ACCEPTANCE_AUTH_PASSWORD" 'yes'
    second_password_hash="$(red_acceptance_app_mysql --execute="
        SELECT Password FROM RED_Admin WHERE RecordID=$ACCEPTANCE_AUTH_RECORD_ID;
    ")"
    if [[ "$second_password_hash" != "$upgraded_password_hash" ]]; then
        printf '%s\n' 'FAIL: the upgraded password hash changed during the rerun login.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: upgraded password hash remains stable on rerun login.'

    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_AUTH_RECORD_ID AND Username='$ACCEPTANCE_AUTH_USERNAME';
    "
    red_acceptance_assert_equals \
        'temporary administrator deletion' \
        '0' \
        "$(red_acceptance_app_mysql --execute="SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$ACCEPTANCE_AUTH_RECORD_ID OR Username='$ACCEPTANCE_AUTH_USERNAME';")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$deleted_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'view=list' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_admin_users.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$deleted_denial")"
    red_acceptance_assert_equals 'deleted-account session HTTP status' '403' "$status"
    red_acceptance_assert_equals 'deleted-account session response' 'no' "$body"

    red_acceptance_remove_auth_fixture
    auth_artifacts="$(red_acceptance_auth_artifact_counts)"
    red_acceptance_assert_equals 'authentication fixture admin/throttle/activity artifacts' '0:0:0' "$auth_artifacts"
    red_acceptance_assert_equals \
        'authentication activity events remain out of scope' \
        '0' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Admin_Activity_Log;')"
    printf '%s\n' 'PASS: disposable administrator authentication, logout, and deletion invalidation lifecycle.'
}

red_acceptance_remove_guest_fixture() {
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_GUEST_RECORD_ID
           OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_GUEST_RECORD_ID);
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_GUEST_USERNAME')), 256));
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_GUEST_RECORD_ID
           OR Username='$ACCEPTANCE_GUEST_USERNAME';
    "
}

red_acceptance_guest_artifact_counts() {
    red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_GUEST_RECORD_ID OR Username='$ACCEPTANCE_GUEST_USERNAME'),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_GUEST_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_GUEST_RECORD_ID
                OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_GUEST_RECORD_ID))
        );
    "
}

red_acceptance_all_table_checksums() {
    red_acceptance_app_mysql --execute="
        CHECKSUM TABLE
            RED_Admin,
            RED_Admin_Activity_Log,
            RED_Admin_Capabilities,
            RED_Admin_Roles,
            RED_Advanced,
            RED_Addon_Installations,
            RED_Addon_Settings,
            RED_Addon_Migrations,
            RED_Addon_Activity_Log,
            RED_Addon_Permission_Activity_Log,
            RED_Addon_Component_Revisions,
            RED_Addon_Admin_Action_Executions,
            RED_Addon_Public_Mutation_Subjects,
            RED_Addon_Public_Mutation_CSRF_Tokens,
            RED_Addon_Public_Mutation_Rate_Limits,
            RED_Addon_Public_Mutation_Idempotency_Keys,
            RED_Addon_Public_Mutation_Executions,
            RED_Articles,
            RED_C_Form,
            RED_C_Gallery,
            RED_C_Menu,
            RED_Categories,
            RED_Components,
            RED_Content_Revisions,
            RED_Custom_Layout_Revisions,
            RED_Custom_Layouts,
            RED_Features,
            RED_Layouts,
            RED_Login_Attempts,
            RED_Menu,
            RED_Page_SEO,
            RED_Schema_Migrations,
            RED_Sections,
            RED_SubCategories,
            RED_Tools;
    "
}

red_acceptance_assert_denied_response() {
    local label="$1"
    local status="$2"
    local response_file="$3"
    local body=""

    body="$(red_acceptance_response_text "$response_file")"
    red_acceptance_assert_equals "$label HTTP status" '403' "$status"
    red_acceptance_assert_equals "$label response" 'no' "$body"
}

red_acceptance_run_guest_permissions() {
    local fixture_state=""
    local metrics=""
    local status=""
    local csrf_token=""
    local checksum_before=""
    local checksum_after=""
    local guest_artifacts=""
    local guest_home="$ACCEPTANCE_RESPONSE_DIR/guest-home.html"
    local allowed_article="$ACCEPTANCE_RESPONSE_DIR/guest-allowed-article.html"
    local allowed_move="$ACCEPTANCE_RESPONSE_DIR/guest-allowed-move.html"
    local denied_layout="$ACCEPTANCE_RESPONSE_DIR/guest-denied-layout.txt"
    local denied_users="$ACCEPTANCE_RESPONSE_DIR/guest-denied-users.txt"
    local denied_video="$ACCEPTANCE_RESPONSE_DIR/guest-denied-video.txt"
    local denied_filter="$ACCEPTANCE_RESPONSE_DIR/guest-denied-filter.txt"
    local logout_headers="$ACCEPTANCE_RESPONSE_DIR/guest-logout-headers.txt"
    local logout_body="$ACCEPTANCE_RESPONSE_DIR/guest-logout-body.txt"
    local logout_denial="$ACCEPTANCE_RESPONSE_DIR/guest-logout-denial.txt"
    local deleted_denial="$ACCEPTANCE_RESPONSE_DIR/guest-deleted-denial.txt"

    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/guest.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_GUEST_RECORD_ID,
            '$ACCEPTANCE_GUEST_USERNAME',
            '$ACCEPTANCE_GUEST_PASSWORD',
            'Admin',
            '$ACCEPTANCE_GUEST_ALIAS',
            'guest',
            '100',
            '1',
            'guest-acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
    "
    ACCEPTANCE_GUEST_FIXTURE_CREATED=1
    red_acceptance_inject_failure after_guest_fixture

    fixture_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':', COUNT(*), SUM(Password='$ACCEPTANCE_GUEST_PASSWORD'),
            SUM(AdminType='guest'), SUM(AdminComponents='100'), SUM(AdminTools='1')
        )
        FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_GUEST_RECORD_ID AND Username='$ACCEPTANCE_GUEST_USERNAME';
    ")"
    red_acceptance_assert_equals 'temporary narrow Guest fixture' '1:1:1:1:1' "$fixture_state"

    red_acceptance_post_login 'guest-login' "$ACCEPTANCE_GUEST_USERNAME" "$ACCEPTANCE_GUEST_PASSWORD" 'yes'
    red_acceptance_assert_equals \
        'Guest password bcrypt upgrade' \
        '60:$2y$' \
        "$(red_acceptance_app_mysql --execute="SELECT CONCAT(CHAR_LENGTH(Password), ':', LEFT(Password, 4)) FROM RED_Admin WHERE RecordID=$ACCEPTANCE_GUEST_RECORD_ID;")"
    red_acceptance_assert_equals \
        'Guest login throttle cleanup' \
        '0' \
        "$(red_acceptance_app_mysql --execute="SELECT COUNT(*) FROM RED_Login_Attempts WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_GUEST_USERNAME')), 256));")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$guest_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Guest authenticated homepage HTTP status' '200' "$status"
    if ! grep -Fq "$ACCEPTANCE_GUEST_ALIAS" "$guest_home" || ! grep -Fq 'var RED_CSRF_TOKEN = ' "$guest_home"; then
        printf '%s\n' 'FAIL: Guest homepage is missing its authenticated alias or CSRF marker.' >&2
        return 1
    fi
    csrf_token="$(sed -n 's/.*var RED_CSRF_TOKEN = "\([a-f0-9]\{64\}\)";.*/\1/p' "$guest_home" | head -n 1)"
    if [[ ! "$csrf_token" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'FAIL: Guest session did not expose a valid CSRF token.' >&2
        return 1
    fi

    checksum_before="$(red_acceptance_all_table_checksums)"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$allowed_article" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'Type=Article' \
        --data-urlencode 'CountPage=2' \
        --data-urlencode 'Section=home' \
        --data-urlencode 'Category=' \
        --data-urlencode 'SubCategory=' \
        --data-urlencode 'Article=' \
        --data-urlencode 'VarPosition=HomePosition' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Layout=index-2' \
        "$ACCEPTANCE_BASE_URL/admin/bin/new_article.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'assigned Article render HTTP status' '200' "$status"
    if ! grep -Fq 'id="insert_content"' "$allowed_article" || ! grep -Fq 'name="csrf_token"' "$allowed_article"; then
        printf '%s\n' 'FAIL: assigned Article render is missing its form or CSRF marker.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: Guest can render assigned Article component #100.'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$allowed_move" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'Type=Content' \
        --data-urlencode 'CountPage=2' \
        --data-urlencode 'Section=home' \
        --data-urlencode 'Category=' \
        --data-urlencode 'SubCategory=' \
        --data-urlencode 'Article=' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Layout=index-2' \
        --data-urlencode 'cparea=Content' \
        --data-urlencode 'compgroup=' \
        --data-urlencode 'SortBy=' \
        --data-urlencode 'SelectPosition=all' \
        --data-urlencode 'VarPosition=HomePosition' \
        "$ACCEPTANCE_BASE_URL/admin/bin/tool_movecontent.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'assigned Move Content render HTTP status' '200' "$status"
    if ! grep -Fq 'id="toolmove"' "$allowed_move" || ! grep -Fq 'data-red-move-content' "$allowed_move"; then
        printf '%s\n' 'FAIL: assigned Move Content render is missing its expected markers.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: Guest can render assigned Move Content tool #1.'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$denied_layout" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'countpage=2' \
        --data-urlencode 'sections=home' \
        --data-urlencode 'categories=' \
        --data-urlencode 'subcategories=' \
        --data-urlencode 'article=' \
        --data-urlencode 'Layout=index' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/update_layout.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_denied_response 'Guest site-layout write' "$status" "$denied_layout"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$denied_users" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'view=list' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_admin_users.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_denied_response 'Guest Administrator Users render' "$status" "$denied_users"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$denied_video" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'Type=Video' \
        --data-urlencode 'CountPage=2' \
        --data-urlencode 'Section=home' \
        --data-urlencode 'Category=' \
        --data-urlencode 'SubCategory=' \
        --data-urlencode 'Article=' \
        --data-urlencode 'VarPosition=HomePosition' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Layout=index-2' \
        "$ACCEPTANCE_BASE_URL/admin/bin/new_gallery.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_denied_response 'Guest unassigned Video component #107' "$status" "$denied_video"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$denied_filter" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        "$ACCEPTANCE_BASE_URL/admin/bin/tool_filterareas.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_denied_response 'Guest unassigned Filter Areas tool #2' "$status" "$denied_filter"

    checksum_after="$(red_acceptance_all_table_checksums)"
    if [[ "$checksum_after" != "$checksum_before" ]]; then
        printf '%s\n' 'FAIL: Guest permission render/denial requests changed disposable database data.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: allowed and denied Guest permission requests left all disposable tables unchanged.'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -D "$logout_headers" \
        -o "$logout_body" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/bin/logout.php?logout")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Guest logout HTTP status' '302' "$status"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$logout_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'Type=Article' \
        "$ACCEPTANCE_BASE_URL/admin/bin/new_article.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_denied_response 'logged-out assigned Article render' "$status" "$logout_denial"

    red_acceptance_post_login 'guest-rerun-login' "$ACCEPTANCE_GUEST_USERNAME" "$ACCEPTANCE_GUEST_PASSWORD" 'yes'
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_GUEST_RECORD_ID AND Username='$ACCEPTANCE_GUEST_USERNAME';
    "
    red_acceptance_assert_equals \
        'temporary Guest deletion' \
        '0' \
        "$(red_acceptance_app_mysql --execute="SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$ACCEPTANCE_GUEST_RECORD_ID OR Username='$ACCEPTANCE_GUEST_USERNAME';")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$deleted_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode 'Type=Article' \
        "$ACCEPTANCE_BASE_URL/admin/bin/new_article.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_denied_response 'deleted-Guest assigned Article render' "$status" "$deleted_denial"

    red_acceptance_remove_guest_fixture
    guest_artifacts="$(red_acceptance_guest_artifact_counts)"
    red_acceptance_assert_equals 'Guest fixture admin/throttle/activity artifacts' '0:0:0' "$guest_artifacts"
    red_acceptance_assert_equals \
        'permission checks create no activity events' \
        '0' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Admin_Activity_Log;')"
    printf '%s\n' 'PASS: disposable Guest permission, logout, and deletion invalidation lifecycle.'
}

red_acceptance_remove_section_archive_fixture() {
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID
           OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID)
           OR (TargetType IN ('article', 'content')
               AND TargetRecordID IN (
                   $ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID,
                   $ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ID
               ));
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_USERNAME')), 256));
        DELETE FROM RED_Articles
        WHERE RecordID IN (
                $ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID,
                $ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ID
            )
           OR Alias IN (
                '$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ALIAS',
                '$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ALIAS'
            );
        DELETE FROM RED_Sections
        WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID
           OR Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS';
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID
           OR Username='$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_USERNAME';
    "
}

red_acceptance_section_archive_artifact_counts() {
    red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID
                OR Username='$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_USERNAME'),
            (SELECT COUNT(*) FROM RED_Sections
             WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID
                OR Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID IN (
                    $ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID,
                    $ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ID
                 )
                OR Alias IN (
                    '$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ALIAS',
                    '$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ALIAS'
                )),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID
                OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID)
                OR (TargetType IN ('article', 'content')
                    AND TargetRecordID IN (
                        $ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID,
                        $ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ID
                    )))
        );
    "
}

red_acceptance_create_section_archive_article() {
    local record_id="$1"
    local alias="$2"
    local title="$3"
    local active="$4"
    local body_marker="$5"
    local csrf_token="$6"
    local response_file="$7"
    local metrics=""
    local status=""
    local body=""

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$response_file" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$record_id" \
        --data-urlencode 'Component=Article' \
        --data-urlencode "Title=$title" \
        --data-urlencode "Alias=$alias" \
        --data-urlencode "Sections=$ACCEPTANCE_SECTION_ARCHIVE_ALIAS" \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=90' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Layout=index-1' \
        --data-urlencode "Active=$active" \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Tags=codex,section,archive' \
        --data-urlencode "ShortDesc=<p id=\"$body_marker\">$title summary</p>" \
        --data-urlencode "LongDesc=<p id=\"$body_marker\">$title body</p>" \
        --data-urlencode "EditedBy=$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/insert_content.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$response_file")"
    if [[ "$body" != 'yes' ]]; then
        printf 'Section archive fixture create diagnostics for %s:\n' "$title" >&2
        tail -40 "$ACCEPTANCE_PHP_LOG" >&2 || true
    fi
    red_acceptance_assert_equals "$title create HTTP status" '200' "$status"
    red_acceptance_assert_equals "$title create response" 'yes' "$body"
}

red_acceptance_run_section_archive_delete() {
    local fixture_state=""
    local metrics=""
    local status=""
    local body=""
    local csrf_token=""
    local archived_state=""
    local section_archive_artifacts=""
    local archive_home="$ACCEPTANCE_RESPONSE_DIR/section-archive-home.html"
    local section_editor="$ACCEPTANCE_RESPONSE_DIR/section-archive-editor.html"
    local active_create="$ACCEPTANCE_RESPONSE_DIR/section-archive-active-create.txt"
    local inactive_create="$ACCEPTANCE_RESPONSE_DIR/section-archive-inactive-create.txt"
    local csrf_denial="$ACCEPTANCE_RESPONSE_DIR/section-archive-csrf-denial.txt"
	local delete_headers="$ACCEPTANCE_RESPONSE_DIR/section-archive-delete.headers"
	local delete_response="$ACCEPTANCE_RESPONSE_DIR/section-archive-delete.txt"
	local inactive_home="$ACCEPTANCE_RESPONSE_DIR/section-archive-inactive-home.html"
	local move_tool_render="$ACCEPTANCE_RESPONSE_DIR/move-content-render.html"
	local move_to_home_response="$ACCEPTANCE_RESPONSE_DIR/move-content-to-home.txt"
	local move_back_response="$ACCEPTANCE_RESPONSE_DIR/move-content-back.txt"
	local move_invalid_response="$ACCEPTANCE_RESPONSE_DIR/move-content-invalid.txt"
	local move_form_markup=""

    red_acceptance_assert_equals \
        'pre-delete Section archive fixture artifacts' \
        '0:0:0:0:0' \
        "$(red_acceptance_section_archive_artifact_counts)"
    red_acceptance_assert_equals \
        'pre-delete canonical Article/Section counts' \
        '4:3' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_Sections));')"

    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/section-archive.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID,
            '$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_USERNAME',
            '$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_PASSWORD',
            'Admin',
            '$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_ALIAS',
            'webmaster',
            '100,102,103,104,105,117,107,111,116',
            '1,2',
            'section-archive-acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
        INSERT INTO RED_Sections (
            RecordID, Sections, Title, Layout, QueryLimit, AccessLevel,
            Features, Active, Description, Tags, Language
        ) VALUES (
            $ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID,
            '$ACCEPTANCE_SECTION_ARCHIVE_ALIAS',
            'Codex Section Archive',
            'index-1',
            '100',
            'Public',
            '',
            'Y',
            '',
            'codex,section,archive',
            'sp'
        );
    "
    ACCEPTANCE_SECTION_ARCHIVE_FIXTURE_CREATED=1
    red_acceptance_inject_failure after_section_archive_fixture

    fixture_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_RECORD_ID
               AND Username='$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_USERNAME'
               AND Password='$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_PASSWORD'
               AND AdminType='webmaster'),
            (SELECT COUNT(*) FROM RED_Sections
             WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID
               AND Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS'
               AND Active='Y')
        );
    ")"
    red_acceptance_assert_equals 'temporary Section archive Webmaster/Section fixture' '1:1' "$fixture_state"

    red_acceptance_post_login \
        'section-archive-webmaster-login' \
        "$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_USERNAME" \
        "$ACCEPTANCE_SECTION_ARCHIVE_ADMIN_PASSWORD" \
        'yes'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$archive_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Section archive Webmaster homepage HTTP status' '200' "$status"
    csrf_token="$(sed -n 's/.*var RED_CSRF_TOKEN = "\([a-f0-9]\{64\}\)";.*/\1/p' "$archive_home" | head -n 1)"
    if [[ ! "$csrf_token" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'FAIL: Section archive Webmaster session did not expose a valid CSRF token.' >&2
        return 1
    fi

    red_acceptance_create_section_archive_article \
        "$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID" \
        "$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ALIAS" \
        'Codex Section Archive Active' \
        'Y' \
        'codex-section-archive-active-body' \
        "$csrf_token" \
        "$active_create"
    red_acceptance_create_section_archive_article \
        "$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ID" \
        "$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_TWO_ALIAS" \
        'Codex Section Archive Inactive' \
        'N' \
        'codex-section-archive-inactive-body' \
        "$csrf_token" \
        "$inactive_create"

	red_acceptance_assert_equals \
		'pre-delete related Article active state' \
        '2:1:1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT CONCAT_WS(':', COUNT(*), SUM(Active='Y'), SUM(Active='N'))
            FROM RED_Articles
            WHERE Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS' AND Language='sp';
		")"

	metrics="$(curl -sS --max-time 10 \
		-b "$ACCEPTANCE_COOKIE_JAR" \
		-c "$ACCEPTANCE_COOKIE_JAR" \
		-o "$move_tool_render" \
		-w '%{http_code}:%{size_download}' \
		-X POST \
		--data-urlencode 'CountPage=3' \
		--data-urlencode "Section=$ACCEPTANCE_SECTION_ARCHIVE_ALIAS" \
		--data-urlencode 'Category=' \
		--data-urlencode 'SubCategory=' \
		--data-urlencode 'Article=' \
		--data-urlencode 'Language=sp' \
		--data-urlencode 'Layout=index-1' \
		--data-urlencode 'cparea=Content' \
		--data-urlencode 'VarPosition=SectionPosition' \
		"$ACCEPTANCE_BASE_URL/admin/bin/tool_movecontent.php")"
	status="${metrics%%:*}"
	red_acceptance_assert_equals 'Move Content renderer HTTP status' '200' "$status"
	move_form_markup="$(tr '\n' ' ' < "$move_tool_render")"
	if [[ "$move_form_markup" != *'<form'*'id="toolmove"'*'data-red-move-content'*'<fieldset>'*'name="csrf_token"'*'name="Sections"'*'name="Position"'*'data-red-move-map'*'name="submit"'*'name="SourcePositionColumn"'*'</fieldset>'*'</form>'* ]] \
			|| [[ "$move_form_markup" != *'Codex Section Archive Active'* ]]; then
			printf '%s\n' 'FAIL: Move Content renderer does not keep its source, progressive destination, map, CSRF, and submit state inside the toolmove form.' >&2
			return 1
		fi
	printf '%s\n' 'PASS: Move Content renderer keeps its selectable Article, progressive destination, layout map, CSRF, and submit controls in one form.'

	metrics="$(curl -sS --max-time 10 \
		-b "$ACCEPTANCE_COOKIE_JAR" \
		-c "$ACCEPTANCE_COOKIE_JAR" \
		-o "$move_to_home_response" \
		-w '%{http_code}:%{size_download}' \
		-X POST \
		--data-urlencode "Articles_Sel[0]=$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID" \
		--data-urlencode 'Sections=home' \
		--data-urlencode 'Position=1' \
		--data-urlencode 'VarPosition=SectionPosition' \
		--data-urlencode 'SourceCountPage=3' \
		--data-urlencode "SourceSection=$ACCEPTANCE_SECTION_ARCHIVE_ALIAS" \
		--data-urlencode 'SourceCategory=' \
		--data-urlencode 'SourceSubCategory=' \
		--data-urlencode 'SourceArticle=' \
		--data-urlencode 'SourceLanguage=sp' \
		--data-urlencode 'SourcePositionColumn=SectionPosition' \
		--data-urlencode "csrf_token=$csrf_token" \
		"$ACCEPTANCE_BASE_URL/admin/bin/run_tool_movecontent.php")"
	status="${metrics%%:*}"
	body="$(red_acceptance_response_text "$move_to_home_response")"
	red_acceptance_assert_equals 'Move Content Section-to-Home HTTP status' '200' "$status"
	red_acceptance_assert_equals 'Move Content Section-to-Home response' 'yes' "$body"
	red_acceptance_assert_equals \
		'Move Content Section-to-Home exact state' \
		'home:1:0' \
		"$(red_acceptance_app_mysql --execute="
			SELECT CONCAT_WS(':', Sections, HomePosition, SectionPosition)
			FROM RED_Articles
			WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID;
		")"
	red_acceptance_check_route \
		'move-content-home-after-move' \
		'/' \
		'Codex Section Archive Active' \
		'codex-section-archive-active-body'

	metrics="$(curl -sS --max-time 10 \
		-b "$ACCEPTANCE_COOKIE_JAR" \
		-c "$ACCEPTANCE_COOKIE_JAR" \
		-o "$move_back_response" \
		-w '%{http_code}:%{size_download}' \
		-X POST \
		--data-urlencode "Articles_Sel[0]=$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID" \
		--data-urlencode "Sections=$ACCEPTANCE_SECTION_ARCHIVE_ALIAS" \
		--data-urlencode 'Position=2' \
		--data-urlencode 'VarPosition=HomePosition' \
		--data-urlencode 'SourceCountPage=2' \
		--data-urlencode 'SourceSection=home' \
		--data-urlencode 'SourceCategory=' \
		--data-urlencode 'SourceSubCategory=' \
		--data-urlencode 'SourceArticle=' \
		--data-urlencode 'SourceLanguage=sp' \
		--data-urlencode 'SourcePositionColumn=HomePosition' \
		--data-urlencode "csrf_token=$csrf_token" \
		"$ACCEPTANCE_BASE_URL/admin/bin/run_tool_movecontent.php")"
	status="${metrics%%:*}"
	body="$(red_acceptance_response_text "$move_back_response")"
	red_acceptance_assert_equals 'Move Content Home-to-Section HTTP status' '200' "$status"
	red_acceptance_assert_equals 'Move Content Home-to-Section response' 'yes' "$body"
	red_acceptance_assert_equals \
		'Move Content Home-to-Section exact state' \
		"$ACCEPTANCE_SECTION_ARCHIVE_ALIAS:0:2" \
		"$(red_acceptance_app_mysql --execute="
			SELECT CONCAT_WS(':', Sections, HomePosition, SectionPosition)
			FROM RED_Articles
			WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID;
		")"
	red_acceptance_check_route \
		'move-content-section-after-return' \
		"/$ACCEPTANCE_SECTION_ARCHIVE_ALIAS/" \
		'Codex Section Archive Active' \
		'codex-section-archive-active-body'

	metrics="$(curl -sS --max-time 10 \
		-b "$ACCEPTANCE_COOKIE_JAR" \
		-c "$ACCEPTANCE_COOKIE_JAR" \
		-o "$move_invalid_response" \
		-w '%{http_code}:%{size_download}' \
		-X POST \
		--data-urlencode "Articles_Sel[0]=$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID" \
		--data-urlencode 'Sections=home' \
		--data-urlencode 'Position=99' \
		--data-urlencode 'VarPosition=SectionPosition' \
		--data-urlencode 'SourceCountPage=3' \
		--data-urlencode "SourceSection=$ACCEPTANCE_SECTION_ARCHIVE_ALIAS" \
		--data-urlencode 'SourceCategory=' \
		--data-urlencode 'SourceSubCategory=' \
		--data-urlencode 'SourceArticle=' \
		--data-urlencode 'SourceLanguage=sp' \
		--data-urlencode 'SourcePositionColumn=SectionPosition' \
		--data-urlencode "csrf_token=$csrf_token" \
		"$ACCEPTANCE_BASE_URL/admin/bin/run_tool_movecontent.php")"
	status="${metrics%%:*}"
	body="$(red_acceptance_response_text "$move_invalid_response")"
	red_acceptance_assert_equals 'Move Content invalid destination position HTTP status' '200' "$status"
	red_acceptance_assert_equals 'Move Content invalid destination position response' 'no' "$body"
	red_acceptance_assert_equals \
		'Move Content invalid destination position preserves state' \
		"$ACCEPTANCE_SECTION_ARCHIVE_ALIAS:0:2" \
		"$(red_acceptance_app_mysql --execute="
			SELECT CONCAT_WS(':', Sections, HomePosition, SectionPosition)
			FROM RED_Articles
			WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_ARTICLE_ONE_ID;
		")"
	printf '%s\n' 'PASS: Move Content derives the destination position column, clears only the source placement, and rejects undeclared destination positions.'

	red_acceptance_check_route \
		'section-archive-before-delete' \
        "/$ACCEPTANCE_SECTION_ARCHIVE_ALIAS/" \
        'Codex Section Archive Active' \
        'codex-section-archive-active-body'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$section_editor" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID" \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_section.php")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Section archive editor HTTP status' '200' "$status"
    if ! grep -Fq 'Delete this Section and move its 2 related Articles to Inactive Articles?' "$section_editor" \
        || ! grep -Fq "csrf_token: \"$csrf_token\"" "$section_editor"; then
        printf '%s\n' 'FAIL: Section editor is missing its archive count or matching delete CSRF token.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: Section editor exposes the related-Article archive confirmation and matching CSRF token.'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$csrf_denial" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID" \
        --data-urlencode 'T=sections' \
        "$ACCEPTANCE_BASE_URL/admin/bin/delete_label.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$csrf_denial")"
    red_acceptance_assert_equals 'Section archive missing-CSRF HTTP status' '403' "$status"
    red_acceptance_assert_equals 'Section archive missing-CSRF response' 'csrf' "$body"
    red_acceptance_assert_equals \
        'missing-CSRF preserves Section and related Articles' \
        '1:2:1:1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Sections WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID),
                (SELECT COUNT(*) FROM RED_Articles WHERE Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS'),
                (SELECT COUNT(*) FROM RED_Articles WHERE Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS' AND Active='Y'),
                (SELECT COUNT(*) FROM RED_Articles WHERE Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS' AND Active='N')
            );
        ")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -D "$delete_headers" \
        -o "$delete_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID" \
        --data-urlencode 'T=sections' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/delete_label.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$delete_response")"
    red_acceptance_assert_equals 'Section archive delete HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Section archive delete response' 'yes' "$body"
    if ! grep -Eqi '^X-RED-Archived-Articles:[[:space:]]*2\r?$' "$delete_headers"; then
        printf '%s\n' 'FAIL: Section archive response is missing the exact two-Article archive header.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: Section archive response reports both related Articles.'

    archived_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Sections
             WHERE RecordID=$ACCEPTANCE_SECTION_ARCHIVE_RECORD_ID
                OR Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS' AND Language='sp'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS' AND Language='sp' AND Active='N'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE Sections='$ACCEPTANCE_SECTION_ARCHIVE_ALIAS' AND Language='sp' AND Active='Y')
        );
    ")"
    red_acceptance_assert_equals 'atomic Section delete and Article archive state' '0:2:2:0' "$archived_state"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$inactive_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'post-delete Inactive Articles homepage HTTP status' '200' "$status"
    if ! grep -Fq 'Inactive Articles' "$inactive_home" \
        || ! grep -Fq 'Codex Section Archive Active' "$inactive_home" \
        || ! grep -Fq 'Codex Section Archive Inactive' "$inactive_home"; then
        printf '%s\n' 'FAIL: archived Section Articles are not both recoverable through Inactive Articles.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: both archived Articles are recoverable through the authenticated Inactive Articles panel.'

    red_acceptance_check_not_found_route \
        'section-archive-route-after-delete' \
        "/$ACCEPTANCE_SECTION_ARCHIVE_ALIAS/" \
        'Codex Section Archive Active' \
        'codex-section-archive-active-body' \
        'Section'

    red_acceptance_remove_section_archive_fixture
    ACCEPTANCE_SECTION_ARCHIVE_FIXTURE_CREATED=0
    section_archive_artifacts="$(red_acceptance_section_archive_artifact_counts)"
    red_acceptance_assert_equals 'Section archive admin/section/article/throttle/activity artifacts' '0:0:0:0:0' "$section_archive_artifacts"
    red_acceptance_assert_equals \
        'post-delete canonical Article/Section counts' \
        '4:3' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_Sections));')"
    printf '%s\n' 'PASS: disposable Section delete archives all related Articles atomically and exposes them through Inactive Articles.'
}

red_acceptance_article_media_manifest() {
    local article_dir="$RED_PROJECT_ROOT/images/articles"
    local file_path=""
    local relative_name=""

    if [[ ! -d "$article_dir" ]]; then
        printf 'Article media directory is missing: %s\n' "$article_dir" >&2
        return 66
    fi

    while IFS= read -r file_path; do
        relative_name="${file_path#$article_dir/}"
        printf '%s:%s\n' "$relative_name" "$(red_sha256_file "$file_path")"
    done < <(find "$article_dir" -maxdepth 1 -type f -print | LC_ALL=C sort)
}

red_acceptance_remove_article_upload_file() {
    local stored_name="$1"
    local expected_name="$2"
    local target_path=""

    if [[ "$stored_name" != "$expected_name" ]]; then
        printf 'Refusing to remove an unexpected Article upload name: %s\n' "$stored_name" >&2
        return 65
    fi

    target_path="$RED_PROJECT_ROOT/images/articles/$stored_name"
    if [[ -L "$target_path" ]]; then
        printf 'Refusing to remove a symbolic-link Article upload target: %s\n' "$target_path" >&2
        return 65
    fi
    if [[ -f "$target_path" ]]; then
        rm -f -- "$target_path"
    elif [[ -e "$target_path" ]]; then
        printf 'Refusing to remove a non-file Article upload target: %s\n' "$target_path" >&2
        return 65
    fi
}

red_acceptance_remove_article_upload_files() {
    red_acceptance_remove_article_upload_file \
        "$ACCEPTANCE_ARTICLE_NEW_UPLOAD_STORED_NAME" \
        "$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME"
    red_acceptance_remove_article_upload_file \
        "$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_STORED_NAME" \
        "$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME"
}

red_acceptance_remove_article_fixture() {
    red_acceptance_remove_article_upload_files
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID
           OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID)
           OR (TargetType IN ('article', 'content') AND TargetRecordID=$ACCEPTANCE_ARTICLE_RECORD_ID);
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_ARTICLE_ADMIN_USERNAME')), 256));
        DELETE FROM RED_Articles
        WHERE RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID
           OR Alias IN ('$ACCEPTANCE_ARTICLE_INITIAL_ALIAS', '$ACCEPTANCE_ARTICLE_UPDATED_ALIAS');
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID
           OR Username='$ACCEPTANCE_ARTICLE_ADMIN_USERNAME';
    "
}

red_acceptance_article_artifact_counts() {
    local database_counts=""
    local file_count=0
    local new_target_path="$RED_PROJECT_ROOT/images/articles/$ACCEPTANCE_ARTICLE_NEW_UPLOAD_STORED_NAME"
    local edit_target_path="$RED_PROJECT_ROOT/images/articles/$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_STORED_NAME"

    database_counts="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID
                OR Username='$ACCEPTANCE_ARTICLE_ADMIN_USERNAME'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID
                OR Alias IN ('$ACCEPTANCE_ARTICLE_INITIAL_ALIAS', '$ACCEPTANCE_ARTICLE_UPDATED_ALIAS')),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_ARTICLE_ADMIN_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID
                OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID)
                OR (TargetType IN ('article', 'content') AND TargetRecordID=$ACCEPTANCE_ARTICLE_RECORD_ID))
        );
    ")"
    if [[ -e "$new_target_path" || -L "$new_target_path" ]]; then
        file_count=$((file_count + 1))
    fi
    if [[ -e "$edit_target_path" || -L "$edit_target_path" ]]; then
        file_count=$((file_count + 1))
    fi
    printf '%s:%s\n' "$database_counts" "$file_count"
}

red_acceptance_check_article_editor() {
    local label="$1"
    local title="$2"
    local alias="$3"
    local body_marker="$4"
    local csrf_token="$5"
    local response_file="$ACCEPTANCE_RESPONSE_DIR/$label.html"
    local metrics=""
    local status=""
    local size=""

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$response_file" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID" \
        --data-urlencode 'VarPosition=SectionPosition' \
        --data-urlencode "Article=$alias" \
        --data-urlencode 'Layout=index-3' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_article.php")"
    status="${metrics%%:*}"
    size="${metrics#*:}"

    red_acceptance_assert_equals "$label HTTP status" '200' "$status"
    if [[ ! "$size" =~ ^[0-9]+$ || "$size" -lt 1000 ]]; then
        printf 'FAIL: %s response is unexpectedly small (%s bytes).\n' "$label" "$size" >&2
        return 1
    fi
    if ! grep -Fq 'id="update_content"' "$response_file" \
        || ! grep -Fq "value=\"$title\"" "$response_file" \
        || ! grep -Fq "value=\"$alias\"" "$response_file" \
        || ! grep -Fq "$body_marker" "$response_file" \
        || ! grep -Fq 'name="csrf_token"' "$response_file" \
        || ! grep -Fq "value=\"$csrf_token\"" "$response_file"; then
        printf 'FAIL: %s response is missing its Article values, form, or matching CSRF token.\n' "$label" >&2
        return 1
    fi
    if grep -Eq 'Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]|<b>(Warning|Deprecated|Notice)</b>|PHP (Warning|Deprecated|Notice|Fatal)' "$response_file"; then
        printf 'FAIL: %s response contains a PHP/runtime error marker.\n' "$label" >&2
        return 1
    fi

    printf 'PASS: %s rendered %s bytes with saved values and matching CSRF.\n' "$label" "$size"
}

red_acceptance_check_article_route_absent() {
    red_acceptance_check_not_found_route "$1" "$2" "$3" "$4" 'Article'
}

red_acceptance_run_article_crud() {
    local new_target_path="$RED_PROJECT_ROOT/images/articles/$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME"
    local edit_target_path="$RED_PROJECT_ROOT/images/articles/$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME"
    local fixture_state=""
    local metrics=""
    local status=""
    local body=""
    local csrf_token=""
    local source_state=""
    local source_hash=""
    local stored_hash=""
    local served_hash=""
    local upload_status=""
    local stored_name=""
    local article_artifacts=""
    local manifest_after=""
    local article_home="$ACCEPTANCE_RESPONSE_DIR/article-home.html"
    local new_upload_response="$ACCEPTANCE_RESPONSE_DIR/article-new-upload-response.json"
    local edit_upload_response="$ACCEPTANCE_RESPONSE_DIR/article-edit-upload-response.json"
    local new_served_response="$ACCEPTANCE_RESPONSE_DIR/article-new-upload-served.png"
    local edit_served_response="$ACCEPTANCE_RESPONSE_DIR/article-edit-upload-served.png"
    local create_response="$ACCEPTANCE_RESPONSE_DIR/article-create.txt"
    local update_response="$ACCEPTANCE_RESPONSE_DIR/article-update.txt"
    local delete_response="$ACCEPTANCE_RESPONSE_DIR/article-delete.txt"

    if [[ -e "$new_target_path" || -L "$new_target_path" || -e "$edit_target_path" || -L "$edit_target_path" ]]; then
        printf '%s\n' 'Acceptance-owned Article upload path already exists; refusing to reuse it.' >&2
        return 65
    fi
    if [[ "${#ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME}" -le 50 \
        || "${#ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME}" -le 50 ]]; then
        printf '%s\n' 'Article upload acceptance filenames must exceed the legacy 50-character limit.' >&2
        return 65
    fi
    ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_BEFORE="$(red_acceptance_article_media_manifest)"
    ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_CAPTURED=1

    red_acceptance_assert_equals \
        'pre-CRUD Article fixture artifacts' \
        '0:0:0:0:0' \
        "$(red_acceptance_article_artifact_counts)"
    red_acceptance_assert_equals \
        'pre-CRUD canonical Article count' \
        '4' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Articles;')"

    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/article.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID,
            '$ACCEPTANCE_ARTICLE_ADMIN_USERNAME',
            '$ACCEPTANCE_ARTICLE_ADMIN_PASSWORD',
            'Admin',
            '$ACCEPTANCE_ARTICLE_ADMIN_ALIAS',
            'webmaster',
            '100,102,103,104,105,117,107,111,116',
            '1,2',
            'article-acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
    "
    ACCEPTANCE_ARTICLE_FIXTURE_CREATED=1
    red_acceptance_inject_failure after_article_fixture

    fixture_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':', COUNT(*), SUM(Password='$ACCEPTANCE_ARTICLE_ADMIN_PASSWORD'),
            SUM(AdminType='webmaster'), SUM(FIND_IN_SET('100', AdminComponents)>0)
        )
        FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID
          AND Username='$ACCEPTANCE_ARTICLE_ADMIN_USERNAME';
    ")"
    red_acceptance_assert_equals 'temporary Article CRUD Webmaster fixture' '1:1:1:1' "$fixture_state"

    red_acceptance_post_login \
        'article-webmaster-login' \
        "$ACCEPTANCE_ARTICLE_ADMIN_USERNAME" \
        "$ACCEPTANCE_ARTICLE_ADMIN_PASSWORD" \
        'yes'
    red_acceptance_assert_equals \
        'Article Webmaster password bcrypt upgrade' \
        '60:$2y$' \
        "$(red_acceptance_app_mysql --execute="SELECT CONCAT(CHAR_LENGTH(Password), ':', LEFT(Password, 4)) FROM RED_Admin WHERE RecordID=$ACCEPTANCE_ARTICLE_ADMIN_RECORD_ID;")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$article_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Article Webmaster homepage HTTP status' '200' "$status"
    if ! grep -Fq "$ACCEPTANCE_ARTICLE_ADMIN_ALIAS" "$article_home" || ! grep -Fq 'var RED_CSRF_TOKEN = ' "$article_home"; then
        printf '%s\n' 'FAIL: Article Webmaster homepage is missing its authenticated alias or CSRF marker.' >&2
        return 1
    fi
    csrf_token="$(sed -n 's/.*var RED_CSRF_TOKEN = "\([a-f0-9]\{64\}\)";.*/\1/p' "$article_home" | head -n 1)"
    if [[ ! "$csrf_token" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'FAIL: Article Webmaster session did not expose a valid CSRF token.' >&2
        return 1
    fi

    ACCEPTANCE_ARTICLE_UPLOAD_SOURCE="$(mktemp "${TMPDIR:-/tmp}/redcms-acceptance-article-upload.XXXXXX")"
    source_state="$("$RED_PHP_BIN_RESOLVED" -r '
        $data = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=", true);
        $image = is_string($data) ? getimagesizefromstring($data) : false;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (!is_string($data) || !$image || file_put_contents($argv[1], $data) !== strlen($data)) exit(1);
        echo strlen($data), ":", $image[0], "x", $image[1], ":", $image["mime"], ":", $finfo->buffer($data);
    ' "$ACCEPTANCE_ARTICLE_UPLOAD_SOURCE")"
    chmod 600 "$ACCEPTANCE_ARTICLE_UPLOAD_SOURCE"
    red_acceptance_assert_equals 'generated Article upload source' '68:1x1:image/png:image/png' "$source_state"
    source_hash="$(red_sha256_file "$ACCEPTANCE_ARTICLE_UPLOAD_SOURCE")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$new_upload_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        --form "pic=@$ACCEPTANCE_ARTICLE_UPLOAD_SOURCE;filename=$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME;type=image/png" \
        "$ACCEPTANCE_BASE_URL/admin/bin/post_file.php?RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID&UC=SmallPict&Insert=true&AuthComponent=Article&Language=sp")"
    status="${metrics%%:*}"
    upload_status="$("$RED_PHP_BIN_RESOLVED" -r '$data=json_decode(file_get_contents($argv[1]), true); echo is_array($data) ? ($data["status"] ?? "") : "";' "$new_upload_response")"
    stored_name="$("$RED_PHP_BIN_RESOLVED" -r '$data=json_decode(file_get_contents($argv[1]), true); echo is_array($data) ? ($data["stored_name"] ?? "") : "";' "$new_upload_response")"
    red_acceptance_assert_equals 'new Article image upload HTTP status' '200' "$status"
    red_acceptance_assert_equals 'new Article image upload response' 'File was uploaded successfully!' "$upload_status"
    red_acceptance_assert_equals 'new Article image upload stored name' "$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME" "$stored_name"
    ACCEPTANCE_ARTICLE_NEW_UPLOAD_STORED_NAME="$stored_name"
    red_acceptance_inject_failure after_article_new_upload

    red_acceptance_assert_equals \
        'new Article upload complete inactive placeholder state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_Articles
            WHERE RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID
              AND Title=''
              AND Component='Article'
              AND Alias=''
              AND Sections=''
              AND Layout<>''
              AND PagePosition=0
              AND Active='N'
              AND Language='sp'
              AND SmallPict='$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME';
        ")"
    if [[ ! -f "$new_target_path" || -L "$new_target_path" ]]; then
        printf 'FAIL: new Article image upload did not create the expected regular file: %s\n' "$new_target_path" >&2
        return 1
    fi
    stored_hash="$(red_sha256_file "$new_target_path")"
    red_acceptance_assert_equals 'new Article image upload file SHA-256' "$source_hash" "$stored_hash"

    metrics="$(curl -sS --max-time 10 \
        -o "$new_served_response" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/images/articles/$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'new Article uploaded image HTTP status' '200' "$status"
    served_hash="$(red_sha256_file "$new_served_response")"
    red_acceptance_assert_equals 'new Article uploaded image served SHA-256' "$source_hash" "$served_hash"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$create_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID" \
        --data-urlencode 'Component=Article' \
        --data-urlencode 'Title=Codex Article QA Initial' \
        --data-urlencode "Alias=$ACCEPTANCE_ARTICLE_INITIAL_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=90' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Layout=index-3' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Tags=codex,article,initial' \
        --data-urlencode 'ShortDesc=Codex Article Initial Summary' \
        --data-urlencode 'LongDesc=<p id="codex-article-initial">Codex Article Initial Body</p>' \
        --data-urlencode "EditedBy=$ACCEPTANCE_ARTICLE_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/insert_content.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$create_response")"
    red_acceptance_assert_equals 'Article create HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Article create response' 'yes' "$body"
    red_acceptance_inject_failure after_article_create

    red_acceptance_assert_equals \
        'Article create exact saved state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_Articles
            WHERE RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID
              AND Title='Codex Article QA Initial'
              AND Component='Article'
              AND Alias='$ACCEPTANCE_ARTICLE_INITIAL_ALIAS'
              AND Sections='administracion'
              AND SectionPosition=1
              AND SectionPositionOrder=90
              AND Categories=''
              AND SubCategories=''
              AND Layout='index-3'
              AND Active='Y'
              AND Language='sp'
              AND Tags='codex,article,initial'
              AND ShortDesc='Codex Article Initial Summary'
              AND LongDesc='<p id=\"codex-article-initial\">Codex Article Initial Body</p>'
              AND SmallPict='$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME'
              AND EditedBy='$ACCEPTANCE_ARTICLE_ADMIN_ALIAS'
              AND StartDate='2026-01-01 00:00:00'
              AND ExpDate='2099-12-31 23:59:59';
        ")"
    red_acceptance_assert_equals \
        'Article create section/layout relationships' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language
            JOIN RED_Layouts l ON l.UniqueName=a.Layout
            JOIN RED_Components c ON c.UniqueName=a.Component
            WHERE a.RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID
              AND s.Active='Y'
              AND c.RecordID=100;
        ")"
    red_acceptance_assert_equals \
        'post-create Article count' \
        '5' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Articles;')"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$edit_upload_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        --form "pic=@$ACCEPTANCE_ARTICLE_UPLOAD_SOURCE;filename=$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME;type=image/png" \
        "$ACCEPTANCE_BASE_URL/admin/bin/post_file.php?RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID&UC=SmallPict2&Language=sp")"
    status="${metrics%%:*}"
    upload_status="$("$RED_PHP_BIN_RESOLVED" -r '$data=json_decode(file_get_contents($argv[1]), true); echo is_array($data) ? ($data["status"] ?? "") : "";' "$edit_upload_response")"
    stored_name="$("$RED_PHP_BIN_RESOLVED" -r '$data=json_decode(file_get_contents($argv[1]), true); echo is_array($data) ? ($data["stored_name"] ?? "") : "";' "$edit_upload_response")"
    red_acceptance_assert_equals 'existing Article image upload HTTP status' '200' "$status"
    red_acceptance_assert_equals 'existing Article image upload response' 'File was uploaded successfully!' "$upload_status"
    red_acceptance_assert_equals 'existing Article image upload stored name' "$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME" "$stored_name"
    ACCEPTANCE_ARTICLE_EDIT_UPLOAD_STORED_NAME="$stored_name"
    red_acceptance_inject_failure after_article_edit_upload

    red_acceptance_assert_equals \
        'new and existing Article image database persistence' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_Articles
            WHERE RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID
              AND SmallPict='$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME'
              AND SmallPict2='$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME';
        ")"
    if [[ ! -f "$edit_target_path" || -L "$edit_target_path" ]]; then
        printf 'FAIL: existing Article image upload did not create the expected regular file: %s\n' "$edit_target_path" >&2
        return 1
    fi
    stored_hash="$(red_sha256_file "$edit_target_path")"
    red_acceptance_assert_equals 'existing Article image upload file SHA-256' "$source_hash" "$stored_hash"

    metrics="$(curl -sS --max-time 10 \
        -o "$edit_served_response" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/images/articles/$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'existing Article uploaded image HTTP status' '200' "$status"
    served_hash="$(red_sha256_file "$edit_served_response")"
    red_acceptance_assert_equals 'existing Article uploaded image served SHA-256' "$source_hash" "$served_hash"

    red_acceptance_check_article_editor \
        'article-initial-editor' \
        'Codex Article QA Initial' \
        "$ACCEPTANCE_ARTICLE_INITIAL_ALIAS" \
        'codex-article-initial' \
        "$csrf_token"
    if ! grep -Fq "$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME" "$ACCEPTANCE_RESPONSE_DIR/article-initial-editor.html" \
        || ! grep -Fq "$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME" "$ACCEPTANCE_RESPONSE_DIR/article-initial-editor.html"; then
        printf '%s\n' 'FAIL: Article editor is missing one or both persisted image filenames.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: Article editor renders both persisted long image filenames.'
    red_acceptance_check_route \
        'article-initial-public' \
        "/administracion/$ACCEPTANCE_ARTICLE_INITIAL_ALIAS" \
        'Codex Article QA Initial' \
        'id="codex-article-initial"'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$update_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID" \
        --data-urlencode 'Title=Codex Article QA Updated' \
        --data-urlencode "Alias=$ACCEPTANCE_ARTICLE_UPDATED_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=91' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Layout=index-3' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Tags=codex,article,updated' \
        --data-urlencode 'ShortDesc=Codex Article Updated Summary' \
        --data-urlencode 'LongDesc=<p id="codex-article-updated">Codex Article Updated Body</p>' \
        --data-urlencode "EditedBy=$ACCEPTANCE_ARTICLE_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/update_content.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$update_response")"
    red_acceptance_assert_equals 'Article update HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Article update response' 'yes' "$body"

    red_acceptance_assert_equals \
        'Article update exact saved state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_Articles
            WHERE RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID
              AND Title='Codex Article QA Updated'
              AND Component='Article'
              AND Alias='$ACCEPTANCE_ARTICLE_UPDATED_ALIAS'
              AND Sections='administracion'
              AND SectionPosition=1
              AND SectionPositionOrder=91
              AND Categories=''
              AND SubCategories=''
              AND Layout='index-3'
              AND Active='Y'
              AND Language='sp'
              AND Tags='codex,article,updated'
              AND ShortDesc='Codex Article Updated Summary'
              AND LongDesc='<p id=\"codex-article-updated\">Codex Article Updated Body</p>'
              AND SmallPict='$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME'
              AND SmallPict2='$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME'
              AND EditedBy='$ACCEPTANCE_ARTICLE_ADMIN_ALIAS';
        ")"
    red_acceptance_assert_equals \
        'Article update preserves relationships' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language
            JOIN RED_Layouts l ON l.UniqueName=a.Layout
            WHERE a.RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID;
        ")"

    red_acceptance_check_article_route_absent \
        'article-old-route-after-update' \
        "/administracion/$ACCEPTANCE_ARTICLE_INITIAL_ALIAS" \
        'Codex Article QA Initial' \
        'codex-article-initial'
    red_acceptance_check_article_editor \
        'article-updated-editor' \
        'Codex Article QA Updated' \
        "$ACCEPTANCE_ARTICLE_UPDATED_ALIAS" \
        'codex-article-updated' \
        "$csrf_token"
    if ! grep -Fq "$ACCEPTANCE_ARTICLE_NEW_UPLOAD_FILE_NAME" "$ACCEPTANCE_RESPONSE_DIR/article-updated-editor.html" \
        || ! grep -Fq "$ACCEPTANCE_ARTICLE_EDIT_UPLOAD_FILE_NAME" "$ACCEPTANCE_RESPONSE_DIR/article-updated-editor.html"; then
        printf '%s\n' 'FAIL: updated Article editor did not preserve both image filenames.' >&2
        return 1
    fi
    printf '%s\n' 'PASS: Article metadata update preserves both long image filenames.'
    red_acceptance_check_route \
        'article-updated-public' \
        "/administracion/$ACCEPTANCE_ARTICLE_UPDATED_ALIAS" \
        'Codex Article QA Updated' \
        'id="codex-article-updated"'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$delete_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID" \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/delete_label.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$delete_response")"
    red_acceptance_assert_equals 'Article delete HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Article delete response' 'yes' "$body"
    red_acceptance_assert_equals \
        'post-delete canonical Article count' \
        '4' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Articles;')"
    red_acceptance_assert_equals \
        'post-delete Article aliases' \
        '0' \
        "$(red_acceptance_app_mysql --execute="SELECT COUNT(*) FROM RED_Articles WHERE RecordID=$ACCEPTANCE_ARTICLE_RECORD_ID OR Alias IN ('$ACCEPTANCE_ARTICLE_INITIAL_ALIAS', '$ACCEPTANCE_ARTICLE_UPDATED_ALIAS');")"
    red_acceptance_check_article_route_absent \
        'article-updated-route-after-delete' \
        "/administracion/$ACCEPTANCE_ARTICLE_UPDATED_ALIAS" \
        'Codex Article QA Updated' \
        'codex-article-updated'

    red_acceptance_remove_article_upload_files
    if [[ -e "$new_target_path" || -L "$new_target_path" || -e "$edit_target_path" || -L "$edit_target_path" ]]; then
        printf '%s\n' 'FAIL: one or both Article acceptance uploads remain after exact-file cleanup.' >&2
        return 1
    fi
    manifest_after="$(red_acceptance_article_media_manifest)"
    red_acceptance_assert_equals \
        'pre-existing Article media manifest after upload cleanup' \
        "$ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_BEFORE" \
        "$manifest_after"

    red_acceptance_assert_equals \
        'Article CRUD activity events remain out of scope' \
        '0' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Admin_Activity_Log;')"
    red_acceptance_remove_article_fixture
    article_artifacts="$(red_acceptance_article_artifact_counts)"
    red_acceptance_assert_equals 'Article CRUD admin/article/throttle/activity/file artifacts' '0:0:0:0:0' "$article_artifacts"
    manifest_after="$(red_acceptance_article_media_manifest)"
    red_acceptance_assert_equals 'final pre-existing Article media manifest' "$ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_BEFORE" "$manifest_after"
    printf '%s\n' 'PASS: disposable Webmaster Article new/edit image upload, create, editor, public render, update, delete, and exact media cleanup lifecycle.'
}

red_acceptance_remove_form_fixture() {
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_FORM_ADMIN_RECORD_ID
           OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_FORM_ADMIN_RECORD_ID)
           OR (TargetType IN ('article', 'content', 'form')
               AND TargetRecordID IN ($ACCEPTANCE_FORM_ARTICLE_RECORD_ID, $ACCEPTANCE_FORM_RECORD_ID));
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_FORM_ADMIN_USERNAME')), 256));
        DELETE FROM RED_C_Form
        WHERE RecordID=$ACCEPTANCE_FORM_RECORD_ID
           OR RefID='$ACCEPTANCE_FORM_ARTICLE_RECORD_ID'
           OR Alias IN ('$ACCEPTANCE_FORM_INITIAL_ALIAS', '$ACCEPTANCE_FORM_UPDATED_ALIAS');
        DELETE FROM RED_Articles
        WHERE RecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID
           OR Alias IN ('$ACCEPTANCE_FORM_INITIAL_ALIAS', '$ACCEPTANCE_FORM_UPDATED_ALIAS');
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_FORM_ADMIN_RECORD_ID
           OR Username='$ACCEPTANCE_FORM_ADMIN_USERNAME';
    "
}

red_acceptance_form_artifact_counts() {
    red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_FORM_ADMIN_RECORD_ID
                OR Username='$ACCEPTANCE_FORM_ADMIN_USERNAME'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID
                OR Alias IN ('$ACCEPTANCE_FORM_INITIAL_ALIAS', '$ACCEPTANCE_FORM_UPDATED_ALIAS')),
            (SELECT COUNT(*) FROM RED_C_Form
             WHERE RecordID=$ACCEPTANCE_FORM_RECORD_ID
                OR RefID='$ACCEPTANCE_FORM_ARTICLE_RECORD_ID'
                OR Alias IN ('$ACCEPTANCE_FORM_INITIAL_ALIAS', '$ACCEPTANCE_FORM_UPDATED_ALIAS')),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_FORM_ADMIN_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_FORM_ADMIN_RECORD_ID
                OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_FORM_ADMIN_RECORD_ID)
                OR (TargetType IN ('article', 'content', 'form')
                    AND TargetRecordID IN ($ACCEPTANCE_FORM_ARTICLE_RECORD_ID, $ACCEPTANCE_FORM_RECORD_ID)))
        );
    "
}

red_acceptance_check_form_editor() {
    local label="$1"
    local title="$2"
    local alias="$3"
    local definition_marker="$4"
    local subject="$5"
    local csrf_token="$6"
    local response_file="$ACCEPTANCE_RESPONSE_DIR/$label.html"
    local metrics=""
    local status=""
    local size=""

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$response_file" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_FORM_RECORD_ID" \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID" \
        --data-urlencode 'VarPosition=SectionPosition' \
        --data-urlencode 'Layout=index-3' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_form.php")"
    status="${metrics%%:*}"
    size="${metrics#*:}"

    red_acceptance_assert_equals "$label HTTP status" '200' "$status"
    if [[ ! "$size" =~ ^[0-9]+$ || "$size" -lt 1000 ]]; then
        printf 'FAIL: %s response is unexpectedly small (%s bytes).\n' "$label" "$size" >&2
        return 1
    fi
    if ! grep -Fq 'id="update_form"' "$response_file" \
        || ! grep -Fq "value=\"$title\"" "$response_file" \
        || ! grep -Fq "value=\"$alias\"" "$response_file" \
        || ! grep -Fq "$definition_marker" "$response_file" \
        || ! grep -Fq "value=\"$subject\"" "$response_file" \
        || ! grep -Fq "value=\"$ACCEPTANCE_FORM_ARTICLE_RECORD_ID\"" "$response_file" \
        || ! grep -Fq "value=\"$ACCEPTANCE_FORM_RECORD_ID\"" "$response_file" \
        || ! grep -Fq 'name="csrf_token"' "$response_file" \
        || ! grep -Fq "value=\"$csrf_token\"" "$response_file"; then
        printf 'FAIL: %s response is missing its Form values, parent/child IDs, or matching CSRF token.\n' "$label" >&2
        return 1
    fi
    if grep -Eq 'Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]|<b>(Warning|Deprecated|Notice)</b>|PHP (Warning|Deprecated|Notice|Fatal)' "$response_file"; then
        printf 'FAIL: %s response contains a PHP/runtime error marker.\n' "$label" >&2
        return 1
    fi

    printf 'PASS: %s rendered %s bytes with paired Form values and matching CSRF.\n' "$label" "$size"
}

red_acceptance_check_form_route_absent() {
    red_acceptance_check_not_found_route "$1" "$2" "$3" "$4" 'Form'
}

red_acceptance_run_form_crud() {
    local initial_definition='#|question=|name=codex_form_name|type=textfield|required=true|displayname=Codex Initial Name|initialvalue=;#|question=|name=codex_form_message|type=textarea|required=false|displayname=Codex Initial Message|readonly=false|initialvalue=|cols=30|rows=4;#|question=|name=Submit|type=button|displayname=Send Initial'
    local updated_definition='#|question=|name=codex_form_email|type=textfield|required=true|displayname=Codex Updated Email|initialvalue=;#|question=|name=codex_form_note|type=textarea|required=true|displayname=Codex Updated Note|readonly=false|initialvalue=|cols=32|rows=5;#|question=|name=Submit|type=button|displayname=Send Updated'
    local initial_response='<p id="codex-form-response-initial">Initial response</p>'
    local updated_response='<p id="codex-form-response-updated">Updated response</p>'
    local fixture_state=""
    local metrics=""
    local status=""
    local body=""
    local csrf_token=""
    local form_artifacts=""
    local form_home="$ACCEPTANCE_RESPONSE_DIR/form-home.html"
    local create_response="$ACCEPTANCE_RESPONSE_DIR/form-create.txt"
    local update_response="$ACCEPTANCE_RESPONSE_DIR/form-update.txt"
    local delete_response="$ACCEPTANCE_RESPONSE_DIR/form-delete.txt"

    red_acceptance_assert_equals \
        'pre-CRUD Form fixture artifacts' \
        '0:0:0:0:0' \
        "$(red_acceptance_form_artifact_counts)"
    red_acceptance_assert_equals \
        'pre-CRUD canonical Article/Form counts' \
        '4:2' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Form));')"

    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/form.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_FORM_ADMIN_RECORD_ID,
            '$ACCEPTANCE_FORM_ADMIN_USERNAME',
            '$ACCEPTANCE_FORM_ADMIN_PASSWORD',
            'Admin',
            '$ACCEPTANCE_FORM_ADMIN_ALIAS',
            'webmaster',
            '100,102,103,104,105,117,107,111,116',
            '1,2',
            'form-acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
    "
    ACCEPTANCE_FORM_FIXTURE_CREATED=1
    red_acceptance_inject_failure after_form_fixture

    fixture_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':', COUNT(*), SUM(Password='$ACCEPTANCE_FORM_ADMIN_PASSWORD'),
            SUM(AdminType='webmaster'), SUM(FIND_IN_SET('102', AdminComponents)>0)
        )
        FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_FORM_ADMIN_RECORD_ID
          AND Username='$ACCEPTANCE_FORM_ADMIN_USERNAME';
    ")"
    red_acceptance_assert_equals 'temporary Form CRUD Webmaster fixture' '1:1:1:1' "$fixture_state"

    red_acceptance_post_login \
        'form-webmaster-login' \
        "$ACCEPTANCE_FORM_ADMIN_USERNAME" \
        "$ACCEPTANCE_FORM_ADMIN_PASSWORD" \
        'yes'
    red_acceptance_assert_equals \
        'Form Webmaster password bcrypt upgrade' \
        '60:$2y$' \
        "$(red_acceptance_app_mysql --execute="SELECT CONCAT(CHAR_LENGTH(Password), ':', LEFT(Password, 4)) FROM RED_Admin WHERE RecordID=$ACCEPTANCE_FORM_ADMIN_RECORD_ID;")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$form_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Form Webmaster homepage HTTP status' '200' "$status"
    if ! grep -Fq "$ACCEPTANCE_FORM_ADMIN_ALIAS" "$form_home" || ! grep -Fq 'var RED_CSRF_TOKEN = ' "$form_home"; then
        printf '%s\n' 'FAIL: Form Webmaster homepage is missing its authenticated alias or CSRF marker.' >&2
        return 1
    fi
    csrf_token="$(sed -n 's/.*var RED_CSRF_TOKEN = "\([a-f0-9]\{64\}\)";.*/\1/p' "$form_home" | head -n 1)"
    if [[ ! "$csrf_token" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'FAIL: Form Webmaster session did not expose a valid CSRF token.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$create_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID" \
        --data-urlencode "RecordID=$ACCEPTANCE_FORM_RECORD_ID" \
        --data-urlencode 'Component=Form' \
        --data-urlencode 'FormType=Contact' \
        --data-urlencode 'Title=Codex Form QA Initial' \
        --data-urlencode "Alias=$ACCEPTANCE_FORM_INITIAL_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=95' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Layout=index-3' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Tags=codex,form,initial' \
        --data-urlencode 'ShortDesc=Codex Form Initial Summary' \
        --data-urlencode "LongDesc=$initial_definition" \
        --data-urlencode 'Subject=Codex Form Initial Subject' \
        --data-urlencode 'Submitter=sender@example.invalid,Codex Form' \
        --data-urlencode 'Destinatary=recipient@example.invalid,Codex Recipient' \
        --data-urlencode 'CC=cc@example.invalid,Codex CC' \
        --data-urlencode 'BCC=bcc@example.invalid,Codex BCC' \
        --data-urlencode "Response=$initial_response" \
        --data-urlencode 'TableName=' \
        --data-urlencode "EditedBy=$ACCEPTANCE_FORM_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/insert_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$create_response")"
    red_acceptance_assert_equals 'Form create HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Form create response' 'yes' "$body"
    red_acceptance_inject_failure after_form_create

    red_acceptance_assert_equals \
        'Form create exact parent-shell state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_Articles
            WHERE RecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID
              AND Title='Codex Form QA Initial'
              AND Component='Form'
              AND Alias='$ACCEPTANCE_FORM_INITIAL_ALIAS'
              AND Sections='administracion'
              AND SectionPosition=1
              AND SectionPositionOrder=95
              AND Categories=''
              AND SubCategories=''
              AND Layout='index-3'
              AND Active='Y'
              AND Language='sp'
              AND Tags='codex,form,initial'
              AND EditedBy='$ACCEPTANCE_FORM_ADMIN_ALIAS'
              AND StartDate='2026-01-01 00:00:00'
              AND ExpDate='2099-12-31 23:59:59';
        ")"
    red_acceptance_assert_equals \
        'Form create exact child state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_C_Form
            WHERE RecordID=$ACCEPTANCE_FORM_RECORD_ID
              AND RefID='$ACCEPTANCE_FORM_ARTICLE_RECORD_ID'
              AND Title='Codex Form QA Initial'
              AND Alias='$ACCEPTANCE_FORM_INITIAL_ALIAS'
              AND FormType='Contact'
              AND ShortDesc='Codex Form Initial Summary'
              AND LongDesc='$initial_definition'
              AND Subject='Codex Form Initial Subject'
              AND Submitter='sender@example.invalid,Codex Form'
              AND Destinatary='recipient@example.invalid,Codex Recipient'
              AND CC='cc@example.invalid,Codex CC'
              AND BCC='bcc@example.invalid,Codex BCC'
              AND Response='$initial_response'
              AND TableName='';
        ")"
    red_acceptance_assert_equals \
        'Form create parent/child/component/area relationships' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_C_Form f ON CAST(f.RefID AS UNSIGNED)=a.RecordID
            JOIN RED_Components c ON c.UniqueName=a.Component AND c.Layout=f.FormType
            JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language
            JOIN RED_Layouts l ON l.UniqueName=a.Layout
            WHERE a.RecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID
              AND f.RecordID=$ACCEPTANCE_FORM_RECORD_ID
              AND c.RecordID=102
              AND s.Active='Y';
        ")"
    red_acceptance_assert_equals \
        'post-create Article/Form counts' \
        '5:3' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Form));')"

    red_acceptance_check_form_editor \
        'form-initial-editor' \
        'Codex Form QA Initial' \
        "$ACCEPTANCE_FORM_INITIAL_ALIAS" \
        'codex_form_name' \
        'Codex Form Initial Subject' \
        "$csrf_token"
    red_acceptance_check_route \
        'form-initial-public' \
        "/administracion/$ACCEPTANCE_FORM_INITIAL_ALIAS" \
        'Codex Form QA Initial' \
        'id="codex_form_name"'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$update_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID" \
        --data-urlencode "RecordID=$ACCEPTANCE_FORM_RECORD_ID" \
        --data-urlencode 'FormType=Contact' \
        --data-urlencode 'Title=Codex Form QA Updated' \
        --data-urlencode "Alias=$ACCEPTANCE_FORM_UPDATED_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=96' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Tags=codex,form,updated' \
        --data-urlencode 'ShortDesc=Codex Form Updated Summary' \
        --data-urlencode "LongDesc=$updated_definition" \
        --data-urlencode 'Subject=Codex Form Updated Subject' \
        --data-urlencode 'Submitter=updated-sender@example.invalid,Codex Form' \
        --data-urlencode 'Destinatary=updated-recipient@example.invalid,Codex Recipient' \
        --data-urlencode 'CC=updated-cc@example.invalid,Codex CC' \
        --data-urlencode 'BCC=updated-bcc@example.invalid,Codex BCC' \
        --data-urlencode "Response=$updated_response" \
        --data-urlencode 'TableName=' \
        --data-urlencode "EditedBy=$ACCEPTANCE_FORM_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/update_form.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$update_response")"
    red_acceptance_assert_equals 'Form update HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Form update response' 'yes' "$body"

    red_acceptance_assert_equals \
        'Form update exact parent-shell state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_Articles
            WHERE RecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID
              AND Title='Codex Form QA Updated'
              AND Component='Form'
              AND Alias='$ACCEPTANCE_FORM_UPDATED_ALIAS'
              AND Sections='administracion'
              AND SectionPosition=1
              AND SectionPositionOrder=96
              AND Categories=''
              AND SubCategories=''
              AND Layout='index-3'
              AND Active='Y'
              AND Language='sp'
              AND Tags='codex,form,updated'
              AND EditedBy='$ACCEPTANCE_FORM_ADMIN_ALIAS';
        ")"
    red_acceptance_assert_equals \
        'Form update exact child state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_C_Form
            WHERE RecordID=$ACCEPTANCE_FORM_RECORD_ID
              AND RefID='$ACCEPTANCE_FORM_ARTICLE_RECORD_ID'
              AND Title='Codex Form QA Updated'
              AND Alias='$ACCEPTANCE_FORM_UPDATED_ALIAS'
              AND FormType='Contact'
              AND ShortDesc='Codex Form Updated Summary'
              AND LongDesc='$updated_definition'
              AND Subject='Codex Form Updated Subject'
              AND Submitter='updated-sender@example.invalid,Codex Form'
              AND Destinatary='updated-recipient@example.invalid,Codex Recipient'
              AND CC='updated-cc@example.invalid,Codex CC'
              AND BCC='updated-bcc@example.invalid,Codex BCC'
              AND Response='$updated_response'
              AND TableName='';
        ")"
    red_acceptance_assert_equals \
        'Form update preserves paired relationships' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_C_Form f ON CAST(f.RefID AS UNSIGNED)=a.RecordID
            JOIN RED_Components c ON c.UniqueName=a.Component AND c.Layout=f.FormType
            JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language
            JOIN RED_Layouts l ON l.UniqueName=a.Layout
            WHERE a.RecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID
              AND f.RecordID=$ACCEPTANCE_FORM_RECORD_ID
              AND c.RecordID=102;
        ")"

    red_acceptance_check_form_route_absent \
        'form-old-route-after-update' \
        "/administracion/$ACCEPTANCE_FORM_INITIAL_ALIAS" \
        'Codex Form QA Initial' \
        'codex_form_name'
    red_acceptance_check_form_editor \
        'form-updated-editor' \
        'Codex Form QA Updated' \
        "$ACCEPTANCE_FORM_UPDATED_ALIAS" \
        'codex_form_email' \
        'Codex Form Updated Subject' \
        "$csrf_token"
    red_acceptance_check_route \
        'form-updated-public' \
        "/administracion/$ACCEPTANCE_FORM_UPDATED_ALIAS" \
        'Codex Form QA Updated' \
        'id="codex_form_email"'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$delete_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_FORM_RECORD_ID" \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID" \
        --data-urlencode 'T=form' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/delete_label.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$delete_response")"
    red_acceptance_assert_equals 'Form delete HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Form delete response' 'yesyes' "$body"
    red_acceptance_assert_equals \
        'post-delete canonical Article/Form counts' \
        '4:2' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Form));')"
    red_acceptance_assert_equals \
        'post-delete Form parent/child aliases' \
        '0:0' \
        "$(red_acceptance_app_mysql --execute="
            SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID=$ACCEPTANCE_FORM_ARTICLE_RECORD_ID
                    OR Alias IN ('$ACCEPTANCE_FORM_INITIAL_ALIAS', '$ACCEPTANCE_FORM_UPDATED_ALIAS')),
                (SELECT COUNT(*) FROM RED_C_Form
                 WHERE RecordID=$ACCEPTANCE_FORM_RECORD_ID
                    OR RefID='$ACCEPTANCE_FORM_ARTICLE_RECORD_ID'
                    OR Alias IN ('$ACCEPTANCE_FORM_INITIAL_ALIAS', '$ACCEPTANCE_FORM_UPDATED_ALIAS'))
            );
        ")"
    red_acceptance_check_form_route_absent \
        'form-updated-route-after-delete' \
        "/administracion/$ACCEPTANCE_FORM_UPDATED_ALIAS" \
        'Codex Form QA Updated' \
        'codex_form_email'

    red_acceptance_assert_equals \
        'Form CRUD activity events remain out of scope' \
        '0' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Admin_Activity_Log;')"
    red_acceptance_remove_form_fixture
    form_artifacts="$(red_acceptance_form_artifact_counts)"
    red_acceptance_assert_equals 'Form CRUD admin/article/form/throttle/activity artifacts' '0:0:0:0:0' "$form_artifacts"
    printf '%s\n' 'PASS: disposable Webmaster Form parent/child create, editor, public render, update, and delete lifecycle.'
}

red_acceptance_remove_gallery_fixture() {
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_GALLERY_ADMIN_RECORD_ID
           OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_GALLERY_ADMIN_RECORD_ID)
           OR (TargetType IN ('article', 'content', 'gallery')
               AND TargetRecordID IN ($ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID, $ACCEPTANCE_GALLERY_RECORD_ID));
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_GALLERY_ADMIN_USERNAME')), 256));
        DELETE FROM RED_C_Gallery
        WHERE RecordID=$ACCEPTANCE_GALLERY_RECORD_ID
           OR RefID='$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID'
           OR Alias IN ('$ACCEPTANCE_GALLERY_INITIAL_ALIAS', '$ACCEPTANCE_GALLERY_UPDATED_ALIAS');
        DELETE FROM RED_Articles
        WHERE RecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID
           OR Alias IN ('$ACCEPTANCE_GALLERY_INITIAL_ALIAS', '$ACCEPTANCE_GALLERY_UPDATED_ALIAS');
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_GALLERY_ADMIN_RECORD_ID
           OR Username='$ACCEPTANCE_GALLERY_ADMIN_USERNAME';
    "
}

red_acceptance_gallery_artifact_counts() {
    red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_GALLERY_ADMIN_RECORD_ID
                OR Username='$ACCEPTANCE_GALLERY_ADMIN_USERNAME'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID
                OR Alias IN ('$ACCEPTANCE_GALLERY_INITIAL_ALIAS', '$ACCEPTANCE_GALLERY_UPDATED_ALIAS')),
            (SELECT COUNT(*) FROM RED_C_Gallery
             WHERE RecordID=$ACCEPTANCE_GALLERY_RECORD_ID
                OR RefID='$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID'
                OR Alias IN ('$ACCEPTANCE_GALLERY_INITIAL_ALIAS', '$ACCEPTANCE_GALLERY_UPDATED_ALIAS')),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_GALLERY_ADMIN_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_GALLERY_ADMIN_RECORD_ID
                OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_GALLERY_ADMIN_RECORD_ID)
                OR (TargetType IN ('article', 'content', 'gallery')
                    AND TargetRecordID IN ($ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID, $ACCEPTANCE_GALLERY_RECORD_ID)))
        );
    "
}

red_acceptance_check_gallery_editor() {
    local label="$1"
    local title="$2"
    local alias="$3"
    local video_url="$4"
    local csrf_token="$5"
    local response_file="$ACCEPTANCE_RESPONSE_DIR/$label.html"
    local metrics=""
    local status=""
    local size=""

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$response_file" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_GALLERY_RECORD_ID" \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID" \
        --data-urlencode 'VarPosition=SectionPosition' \
        --data-urlencode 'Layout=index-3' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_gallery.php")"
    status="${metrics%%:*}"
    size="${metrics#*:}"

    red_acceptance_assert_equals "$label HTTP status" '200' "$status"
    if [[ ! "$size" =~ ^[0-9]+$ || "$size" -lt 1000 ]]; then
        printf 'FAIL: %s response is unexpectedly small (%s bytes).\n' "$label" "$size" >&2
        return 1
    fi
    if ! grep -Fq 'id="update_gallery"' "$response_file" \
        || ! grep -Fq 'Edit Video' "$response_file" \
        || ! grep -Fq "value=\"$title\"" "$response_file" \
        || ! grep -Fq "value=\"$alias\"" "$response_file" \
        || ! grep -Fq "value=\"$video_url\"" "$response_file" \
        || ! grep -Fq "value=\"$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID\"" "$response_file" \
        || ! grep -Fq "value=\"$ACCEPTANCE_GALLERY_RECORD_ID\"" "$response_file" \
        || ! grep -Fq 'name="csrf_token"' "$response_file" \
        || ! grep -Fq "value=\"$csrf_token\"" "$response_file"; then
        printf 'FAIL: %s response is missing its Video Gallery values, parent/child IDs, or matching CSRF token.\n' "$label" >&2
        return 1
    fi
    if grep -Eq 'Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]|<b>(Warning|Deprecated|Notice)</b>|PHP (Warning|Deprecated|Notice|Fatal)' "$response_file"; then
        printf 'FAIL: %s response contains a PHP/runtime error marker.\n' "$label" >&2
        return 1
    fi

    printf 'PASS: %s rendered %s bytes with paired Video Gallery values and matching CSRF.\n' "$label" "$size"
}

red_acceptance_check_gallery_route_absent() {
    red_acceptance_check_not_found_route "$1" "$2" "$3" "$4" 'Gallery'
}

red_acceptance_run_gallery_crud() {
    local initial_video_url='https://youtu.be/M7lc1UVf-VE'
    local updated_video_url='https://www.youtube.com/watch?v=aqz-KE-bpKQ'
    local initial_summary='<p id="codex-gallery-summary-initial">Codex Gallery Initial Summary</p>'
    local updated_summary='<p id="codex-gallery-summary-updated">Codex Gallery Updated Summary</p>'
    local fixture_state=""
    local metrics=""
    local status=""
    local body=""
    local csrf_token=""
    local gallery_artifacts=""
    local gallery_home="$ACCEPTANCE_RESPONSE_DIR/gallery-home.html"
    local create_response="$ACCEPTANCE_RESPONSE_DIR/gallery-create.txt"
    local update_response="$ACCEPTANCE_RESPONSE_DIR/gallery-update.txt"
    local delete_response="$ACCEPTANCE_RESPONSE_DIR/gallery-delete.txt"

    red_acceptance_assert_equals \
        'pre-CRUD Gallery fixture artifacts' \
        '0:0:0:0:0' \
        "$(red_acceptance_gallery_artifact_counts)"
    red_acceptance_assert_equals \
        'pre-CRUD canonical Article/Gallery counts' \
        '4:1' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"

    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/gallery.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_GALLERY_ADMIN_RECORD_ID,
            '$ACCEPTANCE_GALLERY_ADMIN_USERNAME',
            '$ACCEPTANCE_GALLERY_ADMIN_PASSWORD',
            'Admin',
            '$ACCEPTANCE_GALLERY_ADMIN_ALIAS',
            'webmaster',
            '100,102,103,104,105,117,107,111,116',
            '1,2',
            'gallery-acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
    "
    ACCEPTANCE_GALLERY_FIXTURE_CREATED=1
    red_acceptance_inject_failure after_gallery_fixture

    fixture_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':', COUNT(*), SUM(Password='$ACCEPTANCE_GALLERY_ADMIN_PASSWORD'),
            SUM(AdminType='webmaster'), SUM(FIND_IN_SET('107', AdminComponents)>0)
        )
        FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_GALLERY_ADMIN_RECORD_ID
          AND Username='$ACCEPTANCE_GALLERY_ADMIN_USERNAME';
    ")"
    red_acceptance_assert_equals 'temporary Gallery CRUD Webmaster fixture' '1:1:1:1' "$fixture_state"

    red_acceptance_post_login \
        'gallery-webmaster-login' \
        "$ACCEPTANCE_GALLERY_ADMIN_USERNAME" \
        "$ACCEPTANCE_GALLERY_ADMIN_PASSWORD" \
        'yes'
    red_acceptance_assert_equals \
        'Gallery Webmaster password bcrypt upgrade' \
        '60:$2y$' \
        "$(red_acceptance_app_mysql --execute="SELECT CONCAT(CHAR_LENGTH(Password), ':', LEFT(Password, 4)) FROM RED_Admin WHERE RecordID=$ACCEPTANCE_GALLERY_ADMIN_RECORD_ID;")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$gallery_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Gallery Webmaster homepage HTTP status' '200' "$status"
    if ! grep -Fq "$ACCEPTANCE_GALLERY_ADMIN_ALIAS" "$gallery_home" || ! grep -Fq 'var RED_CSRF_TOKEN = ' "$gallery_home"; then
        printf '%s\n' 'FAIL: Gallery Webmaster homepage is missing its authenticated alias or CSRF marker.' >&2
        return 1
    fi
    csrf_token="$(sed -n 's/.*var RED_CSRF_TOKEN = "\([a-f0-9]\{64\}\)";.*/\1/p' "$gallery_home" | head -n 1)"
    if [[ ! "$csrf_token" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'FAIL: Gallery Webmaster session did not expose a valid CSRF token.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$create_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID" \
        --data-urlencode "RecordID=$ACCEPTANCE_GALLERY_RECORD_ID" \
        --data-urlencode 'Component=Gallery' \
        --data-urlencode 'GalleryType=Video' \
        --data-urlencode 'Title=Codex Gallery QA Initial' \
        --data-urlencode "Alias=$ACCEPTANCE_GALLERY_INITIAL_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=97' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Layout=index-3' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Tags=codex,gallery,initial' \
        --data-urlencode "ShortDesc=$initial_summary" \
        --data-urlencode 'Link=' \
        --data-urlencode "LongDesc=$initial_video_url" \
        --data-urlencode "EditedBy=$ACCEPTANCE_GALLERY_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/insert_gallery.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$create_response")"
    red_acceptance_assert_equals 'Gallery create HTTP status' '200' "$status"
    if [[ "$body" != 'yes' ]]; then
        printf '%s\n' 'Gallery create server diagnostics:' >&2
        tail -n 80 "$ACCEPTANCE_PHP_LOG" >&2 || true
    fi
    red_acceptance_assert_equals 'Gallery create response' 'yes' "$body"
    red_acceptance_inject_failure after_gallery_create

    red_acceptance_assert_equals \
        'Gallery create exact parent-shell state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_Articles
            WHERE RecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID
              AND Title='Codex Gallery QA Initial'
              AND Component='Gallery'
              AND Alias='$ACCEPTANCE_GALLERY_INITIAL_ALIAS'
              AND Sections='administracion'
              AND SectionPosition=1
              AND SectionPositionOrder=97
              AND Categories=''
              AND SubCategories=''
              AND Layout='index-3'
              AND Active='Y'
              AND Language='sp'
              AND Tags='codex,gallery,initial'
              AND EditedBy='$ACCEPTANCE_GALLERY_ADMIN_ALIAS'
              AND StartDate='2026-01-01 00:00:00'
              AND ExpDate='2099-12-31 23:59:59';
        ")"
    red_acceptance_assert_equals \
        'Gallery create exact child state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_C_Gallery
            WHERE RecordID=$ACCEPTANCE_GALLERY_RECORD_ID
              AND RefID='$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID'
              AND Title='Codex Gallery QA Initial'
              AND Alias='$ACCEPTANCE_GALLERY_INITIAL_ALIAS'
              AND GalleryType='Video'
              AND ShortDesc='$initial_summary'
              AND Link=''
              AND LongDesc='$initial_video_url'
              AND NewWindow='';
        ")"
    red_acceptance_assert_equals \
        'Gallery create parent/child/component/area relationships' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_C_Gallery g ON CAST(g.RefID AS UNSIGNED)=a.RecordID
            JOIN RED_Components c ON c.UniqueName=a.Component AND c.Layout=g.GalleryType
            JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language
            JOIN RED_Layouts l ON l.UniqueName=a.Layout
            WHERE a.RecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID
              AND g.RecordID=$ACCEPTANCE_GALLERY_RECORD_ID
              AND c.RecordID=107
              AND s.Active='Y';
        ")"
    red_acceptance_assert_equals \
        'post-create Article/Gallery counts' \
        '5:2' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"

    red_acceptance_check_gallery_editor \
        'gallery-initial-editor' \
        'Codex Gallery QA Initial' \
        "$ACCEPTANCE_GALLERY_INITIAL_ALIAS" \
        "$initial_video_url" \
        "$csrf_token"
    red_acceptance_check_route \
        'gallery-initial-public' \
        "/administracion/$ACCEPTANCE_GALLERY_INITIAL_ALIAS" \
        'Codex Gallery QA Initial' \
        'youtube.com/embed/M7lc1UVf-VE'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$update_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID" \
        --data-urlencode "RecordID=$ACCEPTANCE_GALLERY_RECORD_ID" \
        --data-urlencode 'GalleryType=Video' \
        --data-urlencode 'Title=Codex Gallery QA Updated' \
        --data-urlencode "Alias=$ACCEPTANCE_GALLERY_UPDATED_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=98' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Tags=codex,gallery,updated' \
        --data-urlencode "ShortDesc=$updated_summary" \
        --data-urlencode 'Link=' \
        --data-urlencode "LongDesc=$updated_video_url" \
        --data-urlencode "EditedBy=$ACCEPTANCE_GALLERY_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/update_gallery.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$update_response")"
    red_acceptance_assert_equals 'Gallery update HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Gallery update response' 'yes' "$body"

    red_acceptance_assert_equals \
        'Gallery update exact parent-shell state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_Articles
            WHERE RecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID
              AND Title='Codex Gallery QA Updated'
              AND Component='Gallery'
              AND Alias='$ACCEPTANCE_GALLERY_UPDATED_ALIAS'
              AND Sections='administracion'
              AND SectionPosition=1
              AND SectionPositionOrder=98
              AND Categories=''
              AND SubCategories=''
              AND Layout='index-3'
              AND Active='Y'
              AND Language='sp'
              AND Tags='codex,gallery,updated'
              AND EditedBy='$ACCEPTANCE_GALLERY_ADMIN_ALIAS';
        ")"
    red_acceptance_assert_equals \
        'Gallery update exact child state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_C_Gallery
            WHERE RecordID=$ACCEPTANCE_GALLERY_RECORD_ID
              AND RefID='$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID'
              AND Title='Codex Gallery QA Updated'
              AND Alias='$ACCEPTANCE_GALLERY_UPDATED_ALIAS'
              AND GalleryType='Video'
              AND ShortDesc='$updated_summary'
              AND Link=''
              AND LongDesc='$updated_video_url'
              AND NewWindow='';
        ")"
    red_acceptance_assert_equals \
        'Gallery update preserves paired relationships' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_C_Gallery g ON CAST(g.RefID AS UNSIGNED)=a.RecordID
            JOIN RED_Components c ON c.UniqueName=a.Component AND c.Layout=g.GalleryType
            JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language
            JOIN RED_Layouts l ON l.UniqueName=a.Layout
            WHERE a.RecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID
              AND g.RecordID=$ACCEPTANCE_GALLERY_RECORD_ID
              AND c.RecordID=107;
        ")"

    red_acceptance_check_gallery_route_absent \
        'gallery-old-route-after-update' \
        "/administracion/$ACCEPTANCE_GALLERY_INITIAL_ALIAS" \
        'Codex Gallery QA Initial' \
        'youtube.com/embed/M7lc1UVf-VE'
    red_acceptance_check_gallery_editor \
        'gallery-updated-editor' \
        'Codex Gallery QA Updated' \
        "$ACCEPTANCE_GALLERY_UPDATED_ALIAS" \
        "$updated_video_url" \
        "$csrf_token"
    red_acceptance_check_route \
        'gallery-updated-public' \
        "/administracion/$ACCEPTANCE_GALLERY_UPDATED_ALIAS" \
        'Codex Gallery QA Updated' \
        'youtube.com/embed/aqz-KE-bpKQ'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$delete_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_GALLERY_RECORD_ID" \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID" \
        --data-urlencode 'T=gal' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/delete_label.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$delete_response")"
    red_acceptance_assert_equals 'Gallery delete HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Gallery delete response' 'yesyes' "$body"
    red_acceptance_assert_equals \
        'post-delete canonical Article/Gallery counts' \
        '4:1' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"
    red_acceptance_assert_equals \
        'post-delete Gallery parent/child aliases' \
        '0:0' \
        "$(red_acceptance_app_mysql --execute="
            SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID=$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID
                    OR Alias IN ('$ACCEPTANCE_GALLERY_INITIAL_ALIAS', '$ACCEPTANCE_GALLERY_UPDATED_ALIAS')),
                (SELECT COUNT(*) FROM RED_C_Gallery
                 WHERE RecordID=$ACCEPTANCE_GALLERY_RECORD_ID
                    OR RefID='$ACCEPTANCE_GALLERY_ARTICLE_RECORD_ID'
                    OR Alias IN ('$ACCEPTANCE_GALLERY_INITIAL_ALIAS', '$ACCEPTANCE_GALLERY_UPDATED_ALIAS'))
            );
        ")"
    red_acceptance_check_gallery_route_absent \
        'gallery-updated-route-after-delete' \
        "/administracion/$ACCEPTANCE_GALLERY_UPDATED_ALIAS" \
        'Codex Gallery QA Updated' \
        'youtube.com/embed/aqz-KE-bpKQ'

    red_acceptance_assert_equals \
        'Gallery CRUD activity events remain out of scope' \
        '0' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Admin_Activity_Log;')"
    red_acceptance_remove_gallery_fixture
    gallery_artifacts="$(red_acceptance_gallery_artifact_counts)"
    red_acceptance_assert_equals 'Gallery CRUD admin/article/gallery/throttle/activity artifacts' '0:0:0:0:0' "$gallery_artifacts"
    printf '%s\n' 'PASS: disposable Webmaster Gallery parent/child create, editor, public render, update, and delete lifecycle.'
}

red_acceptance_gallery_media_manifest() {
    local gallery_dir="$RED_PROJECT_ROOT/images/gallery"
    local file_path=""
    local relative_name=""

    if [[ ! -d "$gallery_dir" ]]; then
        printf 'Gallery media directory is missing: %s\n' "$gallery_dir" >&2
        return 66
    fi

    while IFS= read -r file_path; do
        relative_name="${file_path#$gallery_dir/}"
        printf '%s:%s\n' "$relative_name" "$(red_sha256_file "$file_path")"
    done < <(find "$gallery_dir" -maxdepth 1 -type f -print | LC_ALL=C sort)
}

red_acceptance_remove_gallery_upload_file() {
    local stored_name="$ACCEPTANCE_GALLERY_UPLOAD_STORED_NAME"
    local target_path=""

    if [[ "$stored_name" != "$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME" ]]; then
        printf 'Refusing to remove an unexpected Gallery upload name: %s\n' "$stored_name" >&2
        return 65
    fi

    target_path="$RED_PROJECT_ROOT/images/gallery/$stored_name"
    if [[ -L "$target_path" ]]; then
        printf 'Refusing to remove a symbolic-link Gallery upload target: %s\n' "$target_path" >&2
        return 65
    fi
    if [[ -f "$target_path" ]]; then
        rm -f -- "$target_path"
    elif [[ -e "$target_path" ]]; then
        printf 'Refusing to remove a non-file Gallery upload target: %s\n' "$target_path" >&2
        return 65
    fi
}

red_acceptance_remove_gallery_upload_fixture() {
    red_acceptance_remove_gallery_upload_file
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID
           OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID)
           OR (TargetType IN ('article', 'content', 'gallery', 'upload')
               AND TargetRecordID IN ($ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID, $ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID));
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_USERNAME')), 256));
        DELETE FROM RED_C_Gallery
        WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID
           OR RefID='$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID'
           OR Alias='$ACCEPTANCE_GALLERY_UPLOAD_ALIAS';
        DELETE FROM RED_Articles
        WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID
           OR Alias='$ACCEPTANCE_GALLERY_UPLOAD_ALIAS';
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID
           OR Username='$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_USERNAME';
    "
}

red_acceptance_gallery_upload_artifact_counts() {
    local database_counts=""
    local file_count=0
    local target_path="$RED_PROJECT_ROOT/images/gallery/$ACCEPTANCE_GALLERY_UPLOAD_STORED_NAME"

    database_counts="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID
                OR Username='$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_USERNAME'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID
                OR Alias='$ACCEPTANCE_GALLERY_UPLOAD_ALIAS'),
            (SELECT COUNT(*) FROM RED_C_Gallery
             WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID
                OR RefID='$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID'
                OR Alias='$ACCEPTANCE_GALLERY_UPLOAD_ALIAS'),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID
                OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID)
                OR (TargetType IN ('article', 'content', 'gallery', 'upload')
                    AND TargetRecordID IN ($ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID, $ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID)))
        );
    ")"
    if [[ -e "$target_path" || -L "$target_path" ]]; then
        file_count=1
    fi
    printf '%s:%s\n' "$database_counts" "$file_count"
}

red_acceptance_check_gallery_upload_editor() {
    local csrf_token="$1"
    local stored_name="$2"
    local response_file="$ACCEPTANCE_RESPONSE_DIR/gallery-upload-editor.html"
    local metrics=""
    local status=""
    local size=""

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$response_file" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID" \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID" \
        --data-urlencode 'VarPosition=SectionPosition' \
        --data-urlencode 'Layout=index-3' \
        "$ACCEPTANCE_BASE_URL/admin/bin/edit_gallery.php")"
    status="${metrics%%:*}"
    size="${metrics#*:}"

    red_acceptance_assert_equals 'Gallery upload editor HTTP status' '200' "$status"
    if [[ ! "$size" =~ ^[0-9]+$ || "$size" -lt 1000 ]]; then
        printf 'FAIL: Gallery upload editor response is unexpectedly small (%s bytes).\n' "$size" >&2
        return 1
    fi
    if ! grep -Fq 'id="update_gallery"' "$response_file" \
        || ! grep -Fq 'Edit Gallery' "$response_file" \
        || ! grep -Fq 'value="Codex Gallery Upload"' "$response_file" \
        || ! grep -Fq "value=\"$ACCEPTANCE_GALLERY_UPLOAD_ALIAS\"" "$response_file" \
        || ! grep -Fq "value=\"$stored_name\"" "$response_file" \
        || ! grep -Fq "/images/gallery/$stored_name" "$response_file" \
        || ! grep -Fq "value=\"$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID\"" "$response_file" \
        || ! grep -Fq "value=\"$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID\"" "$response_file" \
        || ! grep -Fq 'name="csrf_token"' "$response_file" \
        || ! grep -Fq "value=\"$csrf_token\"" "$response_file"; then
        printf '%s\n' 'FAIL: Gallery upload editor is missing the persisted file, paired IDs, values, or matching CSRF token.' >&2
        return 1
    fi
    if grep -Eq 'Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]|<b>(Warning|Deprecated|Notice)</b>|PHP (Warning|Deprecated|Notice|Fatal)' "$response_file"; then
        printf '%s\n' 'FAIL: Gallery upload editor contains a PHP/runtime error marker.' >&2
        return 1
    fi

    printf 'PASS: Gallery upload editor rendered %s bytes with the persisted image and matching CSRF.\n' "$size"
}

red_acceptance_run_gallery_upload() {
    local target_path="$RED_PROJECT_ROOT/images/gallery/$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME"
    local fixture_state=""
    local metrics=""
    local status=""
    local body=""
    local csrf_token=""
    local source_state=""
    local source_hash=""
    local stored_hash=""
    local served_hash=""
    local upload_status=""
    local stored_name=""
    local upload_artifacts=""
    local manifest_after=""
    local upload_home="$ACCEPTANCE_RESPONSE_DIR/gallery-upload-home.html"
    local create_response="$ACCEPTANCE_RESPONSE_DIR/gallery-upload-create.txt"
    local upload_response="$ACCEPTANCE_RESPONSE_DIR/gallery-upload-response.json"
    local served_response="$ACCEPTANCE_RESPONSE_DIR/gallery-upload-served.png"
    local delete_response="$ACCEPTANCE_RESPONSE_DIR/gallery-upload-delete.txt"

    if [[ -e "$target_path" || -L "$target_path" ]]; then
        printf 'Acceptance-owned Gallery upload path already exists; refusing to reuse it: %s\n' "$target_path" >&2
        return 65
    fi
    ACCEPTANCE_GALLERY_MEDIA_MANIFEST_BEFORE="$(red_acceptance_gallery_media_manifest)"
    ACCEPTANCE_GALLERY_MEDIA_MANIFEST_CAPTURED=1

    red_acceptance_assert_equals \
        'pre-upload Gallery fixture artifacts' \
        '0:0:0:0:0:0' \
        "$(red_acceptance_gallery_upload_artifact_counts)"
    red_acceptance_assert_equals \
        'pre-upload canonical Article/Gallery counts' \
        '4:1' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"

    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/gallery-upload.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID,
            '$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_USERNAME',
            '$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_PASSWORD',
            'Admin',
            '$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_ALIAS',
            'webmaster',
            '100,102,103,104,105,117,106,107,111,116',
            '1,2',
            'gallery-upload-acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
    "
    ACCEPTANCE_GALLERY_UPLOAD_FIXTURE_CREATED=1
    red_acceptance_inject_failure after_gallery_upload_fixture

    fixture_state="$(red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':', COUNT(*), SUM(Password='$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_PASSWORD'),
            SUM(AdminType='webmaster'), SUM(FIND_IN_SET('106', AdminComponents)>0)
        )
        FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID
          AND Username='$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_USERNAME';
    ")"
    red_acceptance_assert_equals 'temporary Gallery upload Webmaster fixture' '1:1:1:1' "$fixture_state"

    red_acceptance_post_login \
        'gallery-upload-webmaster-login' \
        "$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_USERNAME" \
        "$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_PASSWORD" \
        'yes'
    red_acceptance_assert_equals \
        'Gallery upload Webmaster password bcrypt upgrade' \
        '60:$2y$' \
        "$(red_acceptance_app_mysql --execute="SELECT CONCAT(CHAR_LENGTH(Password), ':', LEFT(Password, 4)) FROM RED_Admin WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_RECORD_ID;")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$upload_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Gallery upload Webmaster homepage HTTP status' '200' "$status"
    if ! grep -Fq "$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_ALIAS" "$upload_home" || ! grep -Fq 'var RED_CSRF_TOKEN = ' "$upload_home"; then
        printf '%s\n' 'FAIL: Gallery upload Webmaster homepage is missing its authenticated alias or CSRF marker.' >&2
        return 1
    fi
    csrf_token="$(sed -n 's/.*var RED_CSRF_TOKEN = "\([a-f0-9]\{64\}\)";.*/\1/p' "$upload_home" | head -n 1)"
    if [[ ! "$csrf_token" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'FAIL: Gallery upload Webmaster session did not expose a valid CSRF token.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$create_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID" \
        --data-urlencode "RecordID=$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID" \
        --data-urlencode 'Component=Gallery' \
        --data-urlencode 'GalleryType=Gallery' \
        --data-urlencode 'Title=Codex Gallery Upload' \
        --data-urlencode "Alias=$ACCEPTANCE_GALLERY_UPLOAD_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=99' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Layout=index-3' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Tags=codex,gallery,upload' \
        --data-urlencode 'ShortDesc=Codex Upload Caption;' \
        --data-urlencode 'Link=' \
        --data-urlencode 'LongDesc=' \
        --data-urlencode "EditedBy=$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/insert_gallery.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$create_response")"
    red_acceptance_assert_equals 'Gallery upload metadata create HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Gallery upload metadata create response' 'yes' "$body"

    red_acceptance_assert_equals \
        'Gallery upload metadata exact parent/child state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_C_Gallery g ON CAST(g.RefID AS UNSIGNED)=a.RecordID
            JOIN RED_Components c ON c.UniqueName=a.Component AND c.Layout=g.GalleryType
            JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language
            JOIN RED_Layouts l ON l.UniqueName=a.Layout
            WHERE a.RecordID=$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID
              AND a.Title='Codex Gallery Upload'
              AND a.Alias='$ACCEPTANCE_GALLERY_UPLOAD_ALIAS'
              AND a.Component='Gallery'
              AND a.Sections='administracion'
              AND a.SectionPosition=1
              AND a.SectionPositionOrder=99
              AND a.Layout='index-3'
              AND a.Active='Y'
              AND a.Language='sp'
              AND a.Tags='codex,gallery,upload'
              AND a.EditedBy='$ACCEPTANCE_GALLERY_UPLOAD_ADMIN_ALIAS'
              AND g.RecordID=$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID
              AND g.Title='Codex Gallery Upload'
              AND g.Alias='$ACCEPTANCE_GALLERY_UPLOAD_ALIAS'
              AND g.GalleryType='Gallery'
              AND g.ShortDesc='Codex Upload Caption;'
              AND g.Link=''
              AND g.LongDesc=''
              AND g.NewWindow=''
              AND c.RecordID=106
              AND s.Active='Y';
        ")"
    red_acceptance_assert_equals \
        'post-metadata-create Article/Gallery counts' \
        '5:2' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"

    ACCEPTANCE_GALLERY_UPLOAD_SOURCE="$(mktemp "${TMPDIR:-/tmp}/redcms-acceptance-gallery-upload.XXXXXX")"
    source_state="$("$RED_PHP_BIN_RESOLVED" -r '
        $data = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=", true);
        $image = is_string($data) ? getimagesizefromstring($data) : false;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (!is_string($data) || !$image || file_put_contents($argv[1], $data) !== strlen($data)) exit(1);
        echo strlen($data), ":", $image[0], "x", $image[1], ":", $image["mime"], ":", $finfo->buffer($data);
    ' "$ACCEPTANCE_GALLERY_UPLOAD_SOURCE")"
    chmod 600 "$ACCEPTANCE_GALLERY_UPLOAD_SOURCE"
    red_acceptance_assert_equals 'generated Gallery upload source' '68:1x1:image/png:image/png' "$source_state"
    source_hash="$(red_sha256_file "$ACCEPTANCE_GALLERY_UPLOAD_SOURCE")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$upload_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        -H "X-CSRF-Token: $csrf_token" \
        --form "pic=@$ACCEPTANCE_GALLERY_UPLOAD_SOURCE;filename=$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME;type=image/png" \
        "$ACCEPTANCE_BASE_URL/admin/bin/post_file.php?RecordID=$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID&ArtRecordID=$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID&UC=Gallery")"
    status="${metrics%%:*}"
    upload_status="$("$RED_PHP_BIN_RESOLVED" -r '$data=json_decode(file_get_contents($argv[1]), true); echo is_array($data) ? ($data["status"] ?? "") : "";' "$upload_response")"
    stored_name="$("$RED_PHP_BIN_RESOLVED" -r '$data=json_decode(file_get_contents($argv[1]), true); echo is_array($data) ? ($data["stored_name"] ?? "") : "";' "$upload_response")"
    red_acceptance_assert_equals 'Gallery image upload HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Gallery image upload response' 'File was uploaded successfully!' "$upload_status"
    red_acceptance_assert_equals 'Gallery image upload stored name' "$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME" "$stored_name"
    ACCEPTANCE_GALLERY_UPLOAD_STORED_NAME="$stored_name"
    red_acceptance_inject_failure after_gallery_upload

    red_acceptance_assert_equals \
        'Gallery image upload database persistence' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*) FROM RED_C_Gallery
            WHERE RecordID=$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID
              AND RefID='$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID'
              AND GalleryType='Gallery'
              AND LongDesc='$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME';
        ")"
    if [[ ! -f "$target_path" || -L "$target_path" ]]; then
        printf 'FAIL: Gallery image upload did not create the expected regular file: %s\n' "$target_path" >&2
        return 1
    fi
    stored_hash="$(red_sha256_file "$target_path")"
    red_acceptance_assert_equals 'Gallery image upload file SHA-256' "$source_hash" "$stored_hash"

    metrics="$(curl -sS --max-time 10 \
        -o "$served_response" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/images/gallery/$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'Gallery uploaded image HTTP status' '200' "$status"
    served_hash="$(red_sha256_file "$served_response")"
    red_acceptance_assert_equals 'Gallery uploaded image served SHA-256' "$source_hash" "$served_hash"

    red_acceptance_check_gallery_upload_editor "$csrf_token" "$stored_name"
    red_acceptance_check_route \
        'gallery-upload-public' \
        "/administracion/$ACCEPTANCE_GALLERY_UPLOAD_ALIAS" \
        "$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME" \
        'Codex Upload Caption'

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$delete_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_GALLERY_UPLOAD_RECORD_ID" \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_GALLERY_UPLOAD_ARTICLE_RECORD_ID" \
        --data-urlencode 'T=gal' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/delete_label.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$delete_response")"
    red_acceptance_assert_equals 'Gallery upload metadata delete HTTP status' '200' "$status"
    red_acceptance_assert_equals 'Gallery upload metadata delete response' 'yesyes' "$body"
    red_acceptance_assert_equals \
        'post-upload-delete canonical Article/Gallery counts' \
        '4:1' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"
    red_acceptance_check_gallery_route_absent \
        'gallery-upload-route-after-delete' \
        "/administracion/$ACCEPTANCE_GALLERY_UPLOAD_ALIAS" \
        "$ACCEPTANCE_GALLERY_UPLOAD_FILE_NAME" \
        'Codex Upload Caption'

    red_acceptance_remove_gallery_upload_file
    if [[ -e "$target_path" || -L "$target_path" ]]; then
        printf 'FAIL: Gallery acceptance upload remains after exact-file cleanup: %s\n' "$target_path" >&2
        return 1
    fi
    manifest_after="$(red_acceptance_gallery_media_manifest)"
    red_acceptance_assert_equals 'pre-existing Gallery media manifest after upload cleanup' "$ACCEPTANCE_GALLERY_MEDIA_MANIFEST_BEFORE" "$manifest_after"

    red_acceptance_assert_equals \
        'Gallery upload activity events remain out of scope' \
        '0' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Admin_Activity_Log;')"
    red_acceptance_remove_gallery_upload_fixture
    upload_artifacts="$(red_acceptance_gallery_upload_artifact_counts)"
    red_acceptance_assert_equals 'Gallery upload admin/article/gallery/throttle/activity/file artifacts' '0:0:0:0:0:0' "$upload_artifacts"
    manifest_after="$(red_acceptance_gallery_media_manifest)"
    red_acceptance_assert_equals 'final pre-existing Gallery media manifest' "$ACCEPTANCE_GALLERY_MEDIA_MANIFEST_BEFORE" "$manifest_after"
    printf '%s\n' 'PASS: disposable Gallery metadata, protected image upload, editor/public render, paired delete, and exact media cleanup lifecycle.'
}

red_acceptance_drop_rollback_trigger() {
    red_acceptance_admin_mysql \
        --database="$ACCEPTANCE_DATABASE" \
        --execute="DROP TRIGGER IF EXISTS \`$ACCEPTANCE_ROLLBACK_TRIGGER_NAME\`;"
    ACCEPTANCE_ROLLBACK_TRIGGER_CREATED=0
}

red_acceptance_remove_rollback_fixture() {
    red_acceptance_drop_rollback_trigger
    red_acceptance_app_mysql --execute="
        DELETE FROM RED_Admin_Activity_Log
        WHERE ActorAdminRecordID=$ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID
           OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID)
           OR (TargetType IN ('article', 'content', 'gallery')
               AND TargetRecordID IN ($ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID, $ACCEPTANCE_ROLLBACK_RECORD_ID));
        DELETE FROM RED_Login_Attempts
        WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_ROLLBACK_ADMIN_USERNAME')), 256));
        DELETE FROM RED_C_Gallery
        WHERE RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID
           OR RefID='$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID'
           OR Alias IN ('$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS', '$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS');
        DELETE FROM RED_Articles
        WHERE RecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID
           OR Alias IN ('$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS', '$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS');
        DELETE FROM RED_Admin
        WHERE RecordID=$ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID
           OR Username='$ACCEPTANCE_ROLLBACK_ADMIN_USERNAME';
    "
}

red_acceptance_rollback_artifact_counts() {
    red_acceptance_app_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=$ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID
                OR Username='$ACCEPTANCE_ROLLBACK_ADMIN_USERNAME'),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID
                OR Alias IN ('$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS', '$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS')),
            (SELECT COUNT(*) FROM RED_C_Gallery
             WHERE RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID
                OR RefID='$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID'
                OR Alias IN ('$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS', '$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS')),
            (SELECT COUNT(*) FROM RED_Login_Attempts
             WHERE UsernameHash=UNHEX(SHA2(LOWER(TRIM('$ACCEPTANCE_ROLLBACK_ADMIN_USERNAME')), 256))),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID=$ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID
                OR (TargetType='administrator' AND TargetRecordID=$ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID)
                OR (TargetType IN ('article', 'content', 'gallery')
                    AND TargetRecordID IN ($ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID, $ACCEPTANCE_ROLLBACK_RECORD_ID))),
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS
             WHERE TRIGGER_SCHEMA=DATABASE()
               AND TRIGGER_NAME='$ACCEPTANCE_ROLLBACK_TRIGGER_NAME')
        );
    "
}

red_acceptance_run_forced_rollback() {
    local initial_video_url='https://youtu.be/M7lc1UVf-VE'
    local updated_video_url='https://www.youtube.com/watch?v=aqz-KE-bpKQ'
    local initial_summary='<p id="codex-rollback-initial">Initial rollback state</p>'
    local updated_summary='<p id="codex-rollback-updated">Updated rollback state</p>'
    local metrics=""
    local status=""
    local body=""
    local csrf_token=""
    local checksum_before=""
    local checksum_after=""
    local rollback_artifacts=""
    local rollback_home="$ACCEPTANCE_RESPONSE_DIR/rollback-home.html"
    local create_response="$ACCEPTANCE_RESPONSE_DIR/rollback-create.txt"
    local failed_update_response="$ACCEPTANCE_RESPONSE_DIR/rollback-forced-update.txt"
    local successful_update_response="$ACCEPTANCE_RESPONSE_DIR/rollback-control-update.txt"
    local delete_response="$ACCEPTANCE_RESPONSE_DIR/rollback-delete.txt"

    red_acceptance_assert_equals \
        'pre-rollback Gallery fixture artifacts and trigger' \
        '0:0:0:0:0:0' \
        "$(red_acceptance_rollback_artifact_counts)"
    red_acceptance_assert_equals \
        'pre-rollback canonical Article/Gallery counts' \
        '4:1' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"

    ACCEPTANCE_COOKIE_JAR="$ACCEPTANCE_RESPONSE_DIR/rollback.cookies"
    : > "$ACCEPTANCE_COOKIE_JAR"
    chmod 600 "$ACCEPTANCE_COOKIE_JAR"

    red_acceptance_app_mysql --execute="
        INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
        ) VALUES (
            $ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID,
            '$ACCEPTANCE_ROLLBACK_ADMIN_USERNAME',
            '$ACCEPTANCE_ROLLBACK_ADMIN_PASSWORD',
            'Admin',
            '$ACCEPTANCE_ROLLBACK_ADMIN_ALIAS',
            'webmaster',
            '100,102,103,104,105,117,107,111,116',
            '1,2',
            'rollback-acceptance@example.invalid',
            'N',
            'to',
            'N',
            'to'
        );
    "
    ACCEPTANCE_ROLLBACK_FIXTURE_CREATED=1
    red_acceptance_inject_failure after_rollback_fixture

    red_acceptance_post_login \
        'rollback-webmaster-login' \
        "$ACCEPTANCE_ROLLBACK_ADMIN_USERNAME" \
        "$ACCEPTANCE_ROLLBACK_ADMIN_PASSWORD" \
        'yes'
    red_acceptance_assert_equals \
        'rollback Webmaster password bcrypt upgrade' \
        '60:$2y$' \
        "$(red_acceptance_app_mysql --execute="SELECT CONCAT(CHAR_LENGTH(Password), ':', LEFT(Password, 4)) FROM RED_Admin WHERE RecordID=$ACCEPTANCE_ROLLBACK_ADMIN_RECORD_ID;")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$rollback_home" \
        -w '%{http_code}:%{size_download}' \
        "$ACCEPTANCE_BASE_URL/")"
    status="${metrics%%:*}"
    red_acceptance_assert_equals 'rollback Webmaster homepage HTTP status' '200' "$status"
    if ! grep -Fq "$ACCEPTANCE_ROLLBACK_ADMIN_ALIAS" "$rollback_home" || ! grep -Fq 'var RED_CSRF_TOKEN = ' "$rollback_home"; then
        printf '%s\n' 'FAIL: rollback Webmaster homepage is missing its authenticated alias or CSRF marker.' >&2
        return 1
    fi
    csrf_token="$(sed -n 's/.*var RED_CSRF_TOKEN = "\([a-f0-9]\{64\}\)";.*/\1/p' "$rollback_home" | head -n 1)"
    if [[ ! "$csrf_token" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'FAIL: rollback Webmaster session did not expose a valid CSRF token.' >&2
        return 1
    fi

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$create_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID" \
        --data-urlencode "RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID" \
        --data-urlencode 'Component=Gallery' \
        --data-urlencode 'GalleryType=Video' \
        --data-urlencode 'Title=Codex Rollback Initial' \
        --data-urlencode "Alias=$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=101' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Layout=index-3' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Language=sp' \
        --data-urlencode 'Tags=codex,rollback,initial' \
        --data-urlencode "ShortDesc=$initial_summary" \
        --data-urlencode 'Link=' \
        --data-urlencode "LongDesc=$initial_video_url" \
        --data-urlencode "EditedBy=$ACCEPTANCE_ROLLBACK_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/insert_gallery.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$create_response")"
    red_acceptance_assert_equals 'rollback Gallery create HTTP status' '200' "$status"
    red_acceptance_assert_equals 'rollback Gallery create response' 'yes' "$body"
    red_acceptance_assert_equals \
        'rollback Gallery initial exact parent/child state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_C_Gallery g ON CAST(g.RefID AS UNSIGNED)=a.RecordID
            JOIN RED_Components c ON c.UniqueName=a.Component AND c.Layout=g.GalleryType
            JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language
            JOIN RED_Layouts l ON l.UniqueName=a.Layout
            WHERE a.RecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID
              AND a.Title='Codex Rollback Initial'
              AND a.Alias='$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS'
              AND a.Component='Gallery'
              AND a.Sections='administracion'
              AND a.SectionPosition=1
              AND a.SectionPositionOrder=101
              AND a.Layout='index-3'
              AND a.Active='Y'
              AND a.Language='sp'
              AND a.Tags='codex,rollback,initial'
              AND a.EditedBy='$ACCEPTANCE_ROLLBACK_ADMIN_ALIAS'
              AND g.RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID
              AND g.Title='Codex Rollback Initial'
              AND g.Alias='$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS'
              AND g.GalleryType='Video'
              AND g.ShortDesc='$initial_summary'
              AND g.Link=''
              AND g.LongDesc='$initial_video_url'
              AND g.NewWindow=''
              AND c.RecordID=107
              AND s.Active='Y';
        ")"
    red_acceptance_assert_equals \
        'post-rollback-fixture Article/Gallery counts' \
        '5:2' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"

    checksum_before="$(red_acceptance_all_table_checksums)"
    if [[ -z "$checksum_before" ]]; then
        printf '%s\n' 'FAIL: could not capture the pre-failure all-table checksum snapshot.' >&2
        return 1
    fi
    red_acceptance_admin_mysql --database="$ACCEPTANCE_DATABASE" --execute="
        CREATE TRIGGER \`$ACCEPTANCE_ROLLBACK_TRIGGER_NAME\`
        BEFORE UPDATE ON RED_C_Gallery
        FOR EACH ROW
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Codex acceptance forced Gallery child failure';
    "
    ACCEPTANCE_ROLLBACK_TRIGGER_CREATED=1
    red_acceptance_assert_equals \
        'forced rollback trigger installed only in disposable database' \
        '1' \
        "$(red_acceptance_app_mysql --execute="SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='$ACCEPTANCE_ROLLBACK_TRIGGER_NAME';")"
    red_acceptance_inject_failure after_rollback_trigger

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$failed_update_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID" \
        --data-urlencode "RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID" \
        --data-urlencode 'GalleryType=Video' \
        --data-urlencode 'Title=Codex Rollback Updated' \
        --data-urlencode "Alias=$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=102' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Tags=codex,rollback,updated' \
        --data-urlencode "ShortDesc=$updated_summary" \
        --data-urlencode 'Link=' \
        --data-urlencode "LongDesc=$updated_video_url" \
        --data-urlencode "EditedBy=$ACCEPTANCE_ROLLBACK_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/update_gallery.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$failed_update_response")"
    red_acceptance_assert_equals 'forced late child failure HTTP status' '200' "$status"
    red_acceptance_assert_equals 'forced late child failure legacy response' 'no' "$body"

    red_acceptance_drop_rollback_trigger
    red_acceptance_assert_equals \
        'forced rollback trigger removed after failed request' \
        '0' \
        "$(red_acceptance_app_mysql --execute="SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='$ACCEPTANCE_ROLLBACK_TRIGGER_NAME';")"
    checksum_after="$(red_acceptance_all_table_checksums)"
    red_acceptance_assert_equals 'forced Gallery rollback exact all-table checksum snapshot' "$checksum_before" "$checksum_after"
    red_acceptance_assert_equals \
        'forced Gallery rollback preserves exact initial parent/child state' \
        '1:0:0' \
        "$(red_acceptance_app_mysql --execute="
            SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Articles a
                 JOIN RED_C_Gallery g ON CAST(g.RefID AS UNSIGNED)=a.RecordID
                 WHERE a.RecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID
                   AND a.Title='Codex Rollback Initial'
                   AND a.Alias='$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS'
                   AND a.SectionPositionOrder=101
                   AND a.Tags='codex,rollback,initial'
                   AND g.RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID
                   AND g.Title='Codex Rollback Initial'
                   AND g.Alias='$ACCEPTANCE_ROLLBACK_INITIAL_ALIAS'
                   AND g.ShortDesc='$initial_summary'
                   AND g.LongDesc='$initial_video_url'),
                (SELECT COUNT(*) FROM RED_Articles WHERE Alias='$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS'),
                (SELECT COUNT(*) FROM RED_C_Gallery WHERE Alias='$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS')
            );
        ")"
    red_acceptance_inject_failure after_forced_rollback

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$successful_update_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID" \
        --data-urlencode "RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID" \
        --data-urlencode 'GalleryType=Video' \
        --data-urlencode 'Title=Codex Rollback Updated' \
        --data-urlencode "Alias=$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS" \
        --data-urlencode 'Sections=administracion' \
        --data-urlencode 'SectionPosition=1' \
        --data-urlencode 'SectionPositionOrder=102' \
        --data-urlencode 'Categories=' \
        --data-urlencode 'SubCategories=' \
        --data-urlencode 'Active=Y' \
        --data-urlencode 'Tags=codex,rollback,updated' \
        --data-urlencode "ShortDesc=$updated_summary" \
        --data-urlencode 'Link=' \
        --data-urlencode "LongDesc=$updated_video_url" \
        --data-urlencode "EditedBy=$ACCEPTANCE_ROLLBACK_ADMIN_ALIAS" \
        --data-urlencode 'StartDate=2026-01-01 00:00:00' \
        --data-urlencode 'ExpDate=2099-12-31 23:59:59' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/update_gallery.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$successful_update_response")"
    red_acceptance_assert_equals 'post-trigger control update HTTP status' '200' "$status"
    red_acceptance_assert_equals 'post-trigger control update response' 'yes' "$body"
    red_acceptance_assert_equals \
        'post-trigger control update exact parent/child state' \
        '1' \
        "$(red_acceptance_app_mysql --execute="
            SELECT COUNT(*)
            FROM RED_Articles a
            JOIN RED_C_Gallery g ON CAST(g.RefID AS UNSIGNED)=a.RecordID
            JOIN RED_Components c ON c.UniqueName=a.Component AND c.Layout=g.GalleryType
            WHERE a.RecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID
              AND a.Title='Codex Rollback Updated'
              AND a.Alias='$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS'
              AND a.SectionPositionOrder=102
              AND a.Tags='codex,rollback,updated'
              AND g.RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID
              AND g.Title='Codex Rollback Updated'
              AND g.Alias='$ACCEPTANCE_ROLLBACK_UPDATED_ALIAS'
              AND g.ShortDesc='$updated_summary'
              AND g.LongDesc='$updated_video_url'
              AND c.RecordID=107;
        ")"

    metrics="$(curl -sS --max-time 10 \
        -b "$ACCEPTANCE_COOKIE_JAR" \
        -c "$ACCEPTANCE_COOKIE_JAR" \
        -o "$delete_response" \
        -w '%{http_code}:%{size_download}' \
        -X POST \
        --data-urlencode "RecordID=$ACCEPTANCE_ROLLBACK_RECORD_ID" \
        --data-urlencode "ArtRecordID=$ACCEPTANCE_ROLLBACK_ARTICLE_RECORD_ID" \
        --data-urlencode 'T=gal' \
        --data-urlencode "csrf_token=$csrf_token" \
        "$ACCEPTANCE_BASE_URL/admin/bin/delete_label.php")"
    status="${metrics%%:*}"
    body="$(red_acceptance_response_text "$delete_response")"
    red_acceptance_assert_equals 'rollback Gallery delete HTTP status' '200' "$status"
    red_acceptance_assert_equals 'rollback Gallery delete response' 'yesyes' "$body"
    red_acceptance_assert_equals \
        'post-rollback-delete canonical Article/Gallery counts' \
        '4:1' \
        "$(red_acceptance_app_mysql --execute='SELECT CONCAT_WS(CHAR(58), (SELECT COUNT(*) FROM RED_Articles), (SELECT COUNT(*) FROM RED_C_Gallery));')"
    red_acceptance_assert_equals \
        'rollback activity events remain out of scope' \
        '0' \
        "$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Admin_Activity_Log;')"

    red_acceptance_remove_rollback_fixture
    rollback_artifacts="$(red_acceptance_rollback_artifact_counts)"
    red_acceptance_assert_equals 'rollback admin/article/gallery/throttle/activity/trigger artifacts' '0:0:0:0:0:0' "$rollback_artifacts"
    printf '%s\n' 'PASS: forced late Gallery child-write failure returned legacy no, rolled back both rows exactly, then allowed a clean control update and cleanup.'
}

red_acceptance_inject_failure() {
    local phase="$1"
    if [[ "${RED_ACCEPTANCE_INJECT_FAILURE:-}" == "$phase" ]]; then
        printf 'Injected acceptance failure after %s.\n' "$phase" >&2
        return 70
    fi
}

red_acceptance_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local primary_snapshot_after=""
    local auth_artifacts=""
    local guest_artifacts=""
    local section_archive_artifacts=""
    local article_artifacts=""
    local form_artifacts=""
    local gallery_artifacts=""
    local gallery_upload_artifacts=""
    local rollback_artifacts=""
    local gallery_media_manifest_after=""
    local article_media_manifest_after=""
    local article_upload_source=""
    local grant_output=""

    trap - EXIT INT TERM
    set +e

    if [[ "$ACCEPTANCE_PHP_PID" -gt 0 ]]; then
        if kill -0 "$ACCEPTANCE_PHP_PID" 2>/dev/null; then
            kill -TERM "$ACCEPTANCE_PHP_PID" 2>/dev/null
            stop_attempt=0
            while kill -0 "$ACCEPTANCE_PHP_PID" 2>/dev/null && [[ "$stop_attempt" -lt 50 ]]; do
                sleep 0.1
                stop_attempt=$((stop_attempt + 1))
            done
            if kill -0 "$ACCEPTANCE_PHP_PID" 2>/dev/null; then
                kill -KILL "$ACCEPTANCE_PHP_PID" 2>/dev/null
                printf '%s\n' 'Cleanup warning: isolated PHP server required forced termination.' >&2
                cleanup_status=1
            fi
        fi
        wait "$ACCEPTANCE_PHP_PID" >/dev/null 2>&1
        if red_acceptance_port_in_use "$ACCEPTANCE_PORT"; then
            printf 'Cleanup failure: acceptance port %s is still listening.\n' "$ACCEPTANCE_PORT" >&2
            cleanup_status=1
        fi
        ACCEPTANCE_PHP_PID=0
    fi

    if [[ "$ACCEPTANCE_AUTH_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_remove_auth_fixture >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove disposable authentication fixtures.' >&2
            cleanup_status=1
        else
            auth_artifacts="$(red_acceptance_auth_artifact_counts 2>/dev/null)"
            if [[ "$auth_artifacts" != '0:0:0' ]]; then
                printf 'Cleanup failure: authentication fixtures remain (%s).\n' "$auth_artifacts" >&2
                cleanup_status=1
            fi
        fi
        ACCEPTANCE_AUTH_FIXTURE_CREATED=0
    fi

    if [[ "$ACCEPTANCE_GUEST_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_remove_guest_fixture >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove disposable Guest permission fixtures.' >&2
            cleanup_status=1
        else
            guest_artifacts="$(red_acceptance_guest_artifact_counts 2>/dev/null)"
            if [[ "$guest_artifacts" != '0:0:0' ]]; then
                printf 'Cleanup failure: Guest permission fixtures remain (%s).\n' "$guest_artifacts" >&2
                cleanup_status=1
            fi
        fi
        ACCEPTANCE_GUEST_FIXTURE_CREATED=0
    fi

    if [[ "$ACCEPTANCE_SECTION_ARCHIVE_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_remove_section_archive_fixture >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove disposable Section archive fixtures.' >&2
            cleanup_status=1
        else
            section_archive_artifacts="$(red_acceptance_section_archive_artifact_counts 2>/dev/null)"
            if [[ "$section_archive_artifacts" != '0:0:0:0:0' ]]; then
                printf 'Cleanup failure: Section archive fixtures remain (%s).\n' "$section_archive_artifacts" >&2
                cleanup_status=1
            fi
        fi
        ACCEPTANCE_SECTION_ARCHIVE_FIXTURE_CREATED=0
    fi

    if [[ "$ACCEPTANCE_ARTICLE_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_remove_article_fixture >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove disposable Article CRUD fixtures.' >&2
            cleanup_status=1
        else
            article_artifacts="$(red_acceptance_article_artifact_counts 2>/dev/null)"
            if [[ "$article_artifacts" != '0:0:0:0:0' ]]; then
                printf 'Cleanup failure: Article CRUD fixtures remain (%s).\n' "$article_artifacts" >&2
                cleanup_status=1
            fi
        fi
        ACCEPTANCE_ARTICLE_FIXTURE_CREATED=0
    fi

    if [[ "$ACCEPTANCE_FORM_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_remove_form_fixture >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove disposable Form CRUD fixtures.' >&2
            cleanup_status=1
        else
            form_artifacts="$(red_acceptance_form_artifact_counts 2>/dev/null)"
            if [[ "$form_artifacts" != '0:0:0:0:0' ]]; then
                printf 'Cleanup failure: Form CRUD fixtures remain (%s).\n' "$form_artifacts" >&2
                cleanup_status=1
            fi
        fi
        ACCEPTANCE_FORM_FIXTURE_CREATED=0
    fi

    if [[ "$ACCEPTANCE_GALLERY_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_remove_gallery_fixture >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove disposable Gallery CRUD fixtures.' >&2
            cleanup_status=1
        else
            gallery_artifacts="$(red_acceptance_gallery_artifact_counts 2>/dev/null)"
            if [[ "$gallery_artifacts" != '0:0:0:0:0' ]]; then
                printf 'Cleanup failure: Gallery CRUD fixtures remain (%s).\n' "$gallery_artifacts" >&2
                cleanup_status=1
            fi
        fi
        ACCEPTANCE_GALLERY_FIXTURE_CREATED=0
    fi

    if [[ "$ACCEPTANCE_GALLERY_UPLOAD_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_remove_gallery_upload_fixture >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove disposable Gallery upload fixtures.' >&2
            cleanup_status=1
        else
            gallery_upload_artifacts="$(red_acceptance_gallery_upload_artifact_counts 2>/dev/null)"
            if [[ "$gallery_upload_artifacts" != '0:0:0:0:0:0' ]]; then
                printf 'Cleanup failure: Gallery upload fixtures remain (%s).\n' "$gallery_upload_artifacts" >&2
                cleanup_status=1
            fi
        fi
        ACCEPTANCE_GALLERY_UPLOAD_FIXTURE_CREATED=0
    fi

    if [[ "$ACCEPTANCE_ROLLBACK_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_remove_rollback_fixture >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove disposable forced-rollback fixtures or trigger.' >&2
            cleanup_status=1
        else
            rollback_artifacts="$(red_acceptance_rollback_artifact_counts 2>/dev/null)"
            if [[ "$rollback_artifacts" != '0:0:0:0:0:0' ]]; then
                printf 'Cleanup failure: forced-rollback fixtures or trigger remain (%s).\n' "$rollback_artifacts" >&2
                cleanup_status=1
            fi
        fi
        ACCEPTANCE_ROLLBACK_FIXTURE_CREATED=0
    elif [[ "$ACCEPTANCE_ROLLBACK_TRIGGER_CREATED" -eq 1 ]]; then
        red_acceptance_drop_rollback_trigger >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove the disposable forced-rollback trigger.' >&2
            cleanup_status=1
        fi
    fi

    if [[ "$ACCEPTANCE_ADDON_ASSET_ENDPOINT_FIXTURE_CREATED" -eq 1 ]]; then
        red_acceptance_addon_asset_endpoint_cleanup >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove the add-on asset endpoint fixture.' >&2
            cleanup_status=1
        fi
    fi

    if [[ "$ACCEPTANCE_ADDON_ASSET_INJECTION_FIXTURE_CREATED" -eq 1 \
        || "$ACCEPTANCE_ADDON_ASSET_INJECTION_ADMIN_CREATED" -eq 1 ]]; then
        red_acceptance_addon_asset_injection_cleanup >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup warning: could not remove the add-on asset injection fixtures.' >&2
            cleanup_status=1
        fi
    fi

    if [[ "$ACCEPTANCE_GALLERY_MEDIA_MANIFEST_CAPTURED" -eq 1 ]]; then
        gallery_media_manifest_after="$(red_acceptance_gallery_media_manifest 2>/dev/null)"
        if [[ $? -ne 0 || "$gallery_media_manifest_after" != "$ACCEPTANCE_GALLERY_MEDIA_MANIFEST_BEFORE" ]]; then
            printf '%s\n' 'Cleanup failure: the pre-existing Gallery media manifest changed or could not be re-read.' >&2
            cleanup_status=1
        fi
        ACCEPTANCE_GALLERY_MEDIA_MANIFEST_CAPTURED=0
    fi

    if [[ "$ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_CAPTURED" -eq 1 ]]; then
        article_media_manifest_after="$(red_acceptance_article_media_manifest 2>/dev/null)"
        if [[ $? -ne 0 || "$article_media_manifest_after" != "$ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_BEFORE" ]]; then
            printf '%s\n' 'Cleanup failure: the pre-existing Article media manifest changed or could not be re-read.' >&2
            cleanup_status=1
        fi
        ACCEPTANCE_ARTICLE_MEDIA_MANIFEST_CAPTURED=0
    fi

    if [[ "$ACCEPTANCE_GRANT_CREATED" -eq 1 ]]; then
        red_acceptance_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$ACCEPTANCE_DATABASE\`.* FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf 'Cleanup warning: could not revoke the disposable database grant.\n' >&2
            cleanup_status=1
        fi
    fi

    if [[ "$ACCEPTANCE_DATABASE_CREATED" -eq 1 ]]; then
        red_acceptance_admin_mysql --execute="DROP DATABASE IF EXISTS \`$ACCEPTANCE_DATABASE\`;" >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            printf 'Cleanup failure: could not remove disposable database %s.\n' "$ACCEPTANCE_DATABASE" >&2
            cleanup_status=1
        else
            database_remaining="$(red_acceptance_admin_mysql --execute="
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$ACCEPTANCE_DATABASE';
            " 2>/dev/null)"
            if [[ "$database_remaining" != "0" ]]; then
                printf 'Cleanup failure: disposable database %s still exists.\n' "$ACCEPTANCE_DATABASE" >&2
                cleanup_status=1
            fi
        fi
    fi

    if [[ "$ACCEPTANCE_GRANT_CREATED" -eq 1 ]]; then
        grant_output="$(red_acceptance_admin_mysql --execute="SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';" 2>/dev/null)"
        if [[ $? -ne 0 ]]; then
            printf '%s\n' 'Cleanup failure: could not verify disposable grant removal.' >&2
            cleanup_status=1
        elif [[ "$grant_output" == *"\`$ACCEPTANCE_DATABASE\`.*"* ]]; then
            printf 'Cleanup failure: disposable database grant remains for %s.\n' "$ACCEPTANCE_DATABASE" >&2
            cleanup_status=1
        fi
    fi

    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_acceptance_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0 || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
            printf '%s\n' 'Cleanup failure: the configured primary database snapshot changed or could not be re-read.' >&2
            cleanup_status=1
        fi
    fi

    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f "$ADMIN_DEFAULTS_FILE"
    fi
    if [[ -n "$PRIMARY_SCHEMA_MANIFEST" && -f "$PRIMARY_SCHEMA_MANIFEST" ]]; then
        rm -f "$PRIMARY_SCHEMA_MANIFEST"
    fi
    if [[ -n "$ACCEPTANCE_SCHEMA_MANIFEST" && -f "$ACCEPTANCE_SCHEMA_MANIFEST" ]]; then
        rm -f "$ACCEPTANCE_SCHEMA_MANIFEST"
    fi
    if [[ -n "$ACCEPTANCE_PHP_LOG" && -f "$ACCEPTANCE_PHP_LOG" ]]; then
        rm -f "$ACCEPTANCE_PHP_LOG"
    fi
    if [[ -n "$ACCEPTANCE_GALLERY_UPLOAD_SOURCE" && -f "$ACCEPTANCE_GALLERY_UPLOAD_SOURCE" ]]; then
        gallery_upload_source="$ACCEPTANCE_GALLERY_UPLOAD_SOURCE"
        rm -f -- "$gallery_upload_source"
        if [[ -e "$gallery_upload_source" ]]; then
            printf 'Cleanup failure: generated Gallery upload source remains: %s\n' "$gallery_upload_source" >&2
            cleanup_status=1
        fi
        ACCEPTANCE_GALLERY_UPLOAD_SOURCE=""
    fi
    if [[ -n "$ACCEPTANCE_ARTICLE_UPLOAD_SOURCE" && -f "$ACCEPTANCE_ARTICLE_UPLOAD_SOURCE" ]]; then
        article_upload_source="$ACCEPTANCE_ARTICLE_UPLOAD_SOURCE"
        rm -f -- "$article_upload_source"
        if [[ -e "$article_upload_source" ]]; then
            printf 'Cleanup failure: generated Article upload source remains: %s\n' "$article_upload_source" >&2
            cleanup_status=1
        fi
        ACCEPTANCE_ARTICLE_UPLOAD_SOURCE=""
    fi
    if [[ -n "$ACCEPTANCE_RESPONSE_DIR" && -d "$ACCEPTANCE_RESPONSE_DIR" ]]; then
        response_dir="$ACCEPTANCE_RESPONSE_DIR"
        rm -rf "$response_dir"
        if [[ -e "$response_dir" ]]; then
            printf 'Cleanup failure: temporary response/cookie directory remains: %s\n' "$response_dir" >&2
            cleanup_status=1
        fi
        ACCEPTANCE_RESPONSE_DIR=""
        ACCEPTANCE_COOKIE_JAR=""
    fi
    red_remove_defaults_file

    if [[ "$cleanup_status" -eq 0 && "$ACCEPTANCE_DATABASE_CREATED" -eq 1 ]]; then
        printf 'Cleanup complete: stopped the isolated server and removed database/grant %s.\n' "$ACCEPTANCE_DATABASE"
    fi

    if [[ "$original_status" -eq 0 && "$cleanup_status" -ne 0 ]]; then
        original_status=1
    fi
    exit "$original_status"
}

trap red_acceptance_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

red_acceptance_create_admin_defaults
red_acceptance_admin_mysql --execute='SELECT 1;' >/dev/null
red_acceptance_select_port

APP_ACCOUNT="$(red_acceptance_primary_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$ || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$ ]]; then
    printf 'Application database account is unsafe for disposable grants: %s\n' "$APP_ACCOUNT" >&2
    exit 64
fi

database_exists="$(red_acceptance_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$ACCEPTANCE_DATABASE';
")"
if [[ "$database_exists" != "0" ]]; then
    printf 'Acceptance database already exists; refusing to reuse it: %s\n' "$ACCEPTANCE_DATABASE" >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_acceptance_primary_snapshot)"
PRIMARY_SCHEMA_MANIFEST="$(mktemp "${TMPDIR:-/tmp}/redcms-primary-schema.XXXXXX")"
red_acceptance_schema_manifest "$RED_DB_NAME_RESOLVED" > "$PRIMARY_SCHEMA_MANIFEST"
PRIMARY_SCHEMA_SIGNATURE="$(red_sha256_file "$PRIMARY_SCHEMA_MANIFEST")"
if [[ -z "$PRIMARY_SNAPSHOT_BEFORE" || -z "$PRIMARY_SCHEMA_SIGNATURE" ]]; then
    printf '%s\n' 'Could not capture the configured primary database baseline.' >&2
    exit 67
fi

printf 'Creating disposable database: %s\n' "$ACCEPTANCE_DATABASE"
red_acceptance_admin_mysql --execute="
    CREATE DATABASE \`$ACCEPTANCE_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
ACCEPTANCE_DATABASE_CREATED=1

red_acceptance_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$ACCEPTANCE_DATABASE\`.* TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
ACCEPTANCE_GRANT_CREATED=1

empty_table_count="$(red_acceptance_app_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE();
")"
red_acceptance_assert_equals 'new database table count' '0' "$empty_table_count"
red_acceptance_inject_failure after_database

printf 'Importing installer: %s\n' "$INSTALLER_FILE"
"$RED_MYSQL_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$ACCEPTANCE_DATABASE" < "$INSTALLER_FILE"

installer_table_count="$(red_acceptance_app_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE();
")"
installer_innodb_count="$(red_acceptance_app_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND ENGINE='InnoDB';
")"
installer_non_utf8_count="$(red_acceptance_app_mysql --execute="
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE()
      AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','enum','set')
      AND COLLATION_NAME<>'utf8mb4_unicode_ci';
")"
installer_migration_count="$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Schema_Migrations;')"
installer_admin_seed="$(red_acceptance_app_mysql --execute="
    SELECT CONCAT_WS(':', COUNT(*), SUM(CHAR_LENGTH(Password)=60), SUM(Email='')) FROM RED_Admin;
")"

red_acceptance_assert_equals 'installer table count' '32' "$installer_table_count"
red_acceptance_assert_equals 'installer InnoDB table count' '32' "$installer_innodb_count"
red_acceptance_assert_equals 'installer non-utf8mb4 character columns' '0' "$installer_non_utf8_count"
red_acceptance_assert_equals 'installer migration ledger count' '0' "$installer_migration_count"
red_acceptance_assert_equals 'installer sanitized administrator seeds' '2:2:2' "$installer_admin_seed"
red_acceptance_inject_failure after_import

printf 'Applying %s migration files.\n' "$MIGRATION_FILE_COUNT"
"$SCRIPT_DIR/db-migrate.sh" "--database=$ACCEPTANCE_DATABASE"

applied_migration_count="$(red_acceptance_app_mysql --execute='SELECT COUNT(*) FROM RED_Schema_Migrations;')"
red_acceptance_assert_equals 'applied migration ledger count' "$MIGRATION_FILE_COUNT" "$applied_migration_count"
red_acceptance_inject_failure after_migrations

printf '%s\n' 'Rerunning migrations to prove idempotency.'
rerun_output="$("$SCRIPT_DIR/db-migrate.sh" "--database=$ACCEPTANCE_DATABASE")"
printf '%s\n' "$rerun_output"
if [[ "$rerun_output" != *'No pending migrations.'* ]]; then
    printf '%s\n' 'FAIL: migration rerun did not report a no-op.' >&2
    exit 1
fi
printf '%s\n' 'PASS: migration rerun is a no-op.'

status_output="$("$SCRIPT_DIR/db-migrate.sh" "--database=$ACCEPTANCE_DATABASE" --status)"
status_summary="${status_output##*$'\n'}"
red_acceptance_assert_equals \
    'migration status summary' \
    "Summary: $MIGRATION_FILE_COUNT applied, 0 pending, 0 drifted" \
    "$status_summary"

printf '%s\n' 'Running persisted Owner lifecycle authorization checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-owner-authorization-self-test.php"

printf '%s\n' 'Running Owner-authorized package permission grant and revoke checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-package-permission-self-test.php"

printf '%s\n' 'Running component editor package-permission checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-component-editor-authorization-self-test.php"

printf '%s\n' 'Running add-on setting storage, editor, authorization preflight, atomic writer, and secret replacement checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-setting-storage-preflight-self-test.php"

printf '%s\n' 'Running permission-scoped add-on setting read-model checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-setting-read-model-self-test.php"

printf '%s\n' 'Running permission-scoped administrator tool dispatch checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-admin-tool-dispatch-self-test.php"

printf '%s\n' 'Running non-executing administrator tool form-preflight checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-admin-tool-form-preflight-self-test.php"

printf '%s\n' 'Running permission-scoped administrator tool form value-loader checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-admin-tool-form-value-loader-self-test.php"

printf '%s\n' 'Running protected administrator tool form JSON-adapter checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-admin-tool-form-json-adapter-self-test.php"

printf '%s\n' 'Running atomic administrator tool form writer checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-admin-tool-form-write-self-test.php"

printf '%s\n' 'Running operational administrator tool form Save-bridge checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-admin-tool-form-save-bridge-self-test.php"

printf '%s\n' 'Running non-executing administrator tool action-preflight checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-admin-tool-action-preflight-self-test.php"

printf '%s\n' 'Running atomic internal administrator tool action-runner checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-admin-tool-action-execution-self-test.php"

printf '%s\n' 'Running bounded component editor data-loader checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-component-editor-data-loader-self-test.php"

printf '%s\n' 'Running transactional component editor update checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-component-editor-update-self-test.php"
printf '%s\n' 'Running component creation, parent metadata, atomic placement, and atomic-delete checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-component-editor-create-preflight-self-test.php"

printf '%s\n' 'Running read-only add-on registry reconciliation and asset-delivery preflight checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-registry-self-test.php"

printf '%s\n' 'Running immutable add-on asset endpoint response checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-asset-endpoint-self-test.php"

printf '%s\n' 'Running core-owned add-on asset injection checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-asset-injection-self-test.php"

printf '%s\n' 'Running enabled add-on request bootstrap checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-request-bootstrap-self-test.php"

printf '%s\n' 'Running package-runtime secret consumption checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-runtime-secret-bootstrap-self-test.php"

printf '%s\n' 'Running safe add-on component persistence and dispatch checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-component-dispatch-self-test.php"

printf '%s\n' 'Running Owner-authorized disabled add-on install and recovery checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-install-self-test.php"

printf '%s\n' 'Running Owner-authorized disabled add-on upgrade and recovery checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-upgrade-self-test.php"

printf '%s\n' 'Running read-only Owner-authorized add-on enablement preflight checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-enable-preflight-self-test.php"

printf '%s\n' 'Running read-only payment-adapter database preflight checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-payment-adapter-database-preflight-self-test.php"

printf '%s\n' 'Running Owner-authorized atomic payment-adapter enablement checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-payment-adapter-enable-self-test.php"

printf '%s\n' 'Running Owner-authorized one-time provider-contact authorization checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-provider-contact-authorization-self-test.php"

printf '%s\n' 'Running Owner-authorized atomic provider-contact attempt-claim checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-provider-contact-claim-self-test.php"

printf '%s\n' 'Running claimed loopback-only provider-contact execution checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-provider-contact-execution-self-test.php"

printf '%s\n' 'Running claimed synthetic-package provider-contact execution checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-provider-contact-synthetic-execution-self-test.php"

printf '%s\n' 'Running claimed provider-operation runner checks against an in-memory handler.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-provider-contact-provider-execution-self-test.php"

"$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-sandbox-checkout-synthetic-execution-self-test.php"

printf '%s\n' 'Running Owner-authorized sandbox Checkout mutation-authority checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-sandbox-checkout-mutation-authorization-self-test.php"

printf '%s\n' 'Running Owner-authorized sandbox Checkout mutation-claim checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-sandbox-checkout-mutation-claim-self-test.php"

printf '%s\n' 'Running claimed sandbox Checkout transport-double start/result checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-sandbox-checkout-mutation-transport-double-self-test.php"

printf '%s\n' 'Running fresh D4 Sandbox Checkout authorization and claim checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-sandbox-checkout-real-mutation-lifecycle-self-test.php"

printf '%s\n' 'Running durable D4 Sandbox Checkout start/result checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-sandbox-checkout-real-mutation-execution-self-test.php"

printf '%s\n' 'Running read-only public-mutation live-data preflight checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-public-mutation-live-data-preflight-self-test.php"

printf '%s\n' 'Running read-only operational add-on enablement preflight checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-operational-enablement-preflight-self-test.php"

printf '%s\n' 'Running core-owned public-mutation anonymous subject and CSRF checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-public-mutation-subject-csrf-self-test.php"

printf '%s\n' 'Running core-owned browser subject-cookie lifecycle checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-public-mutation-subject-cookie-lifecycle-self-test.php"

printf '%s\n' 'Running core-owned public-mutation fixed-window rate-limit checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-public-mutation-rate-limit-self-test.php"

printf '%s\n' 'Running core-owned public-mutation idempotency-key checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-public-mutation-idempotency-self-test.php"

printf '%s\n' 'Running core-owned public-mutation form evidence-bootstrap checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-public-mutation-form-bootstrap-self-test.php"

printf '%s\n' 'Running core-owned public component form-integration checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-public-component-form-integration-self-test.php"

printf '%s\n' 'Running atomic core-only public-mutation transaction-runner checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-public-mutation-execution-self-test.php"

printf '%s\n' 'Running Owner-authorized atomic add-on enablement checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-enable-self-test.php"

printf '%s\n' 'Running Owner-authorized atomic add-on disablement checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-disable-self-test.php"

printf '%s\n' 'Running operational add-on enable, disable, and re-enable checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/addon-operational-lifecycle-self-test.php"

printf '%s\n' 'Running SEO metadata persistence, revision, area, and rollback checks.'
RED_SEO_TEST_DATABASE="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/seo-metadata-database-self-test.php"

printf '%s\n' 'Running content revision lifecycle checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/content-revisions-self-test.php"

printf '%s\n' 'Running page layout distribution lifecycle checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/layout-distribution-self-test.php"

printf '%s\n' 'Running visual Layout Builder lifecycle checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$FRANKENPHP_BIN" php-cli "$RED_PROJECT_ROOT/scripts/custom-layout-builder-self-test.php"

final_table_state="$(red_acceptance_app_mysql --execute="
    SELECT CONCAT_WS(
        ':',
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE()),
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND ENGINE='InnoDB'),
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','enum','set')
           AND COLLATION_NAME<>'utf8mb4_unicode_ci')
    );
")"
canonical_counts="$(red_acceptance_app_mysql --execute="
    SELECT CONCAT_WS(
        ':',
        (SELECT COUNT(*) FROM RED_Admin),
        (SELECT COUNT(*) FROM RED_Admin_Roles),
        (SELECT COUNT(*) FROM RED_Admin_Capabilities),
        (SELECT COUNT(*) FROM RED_Addon_Installations),
        (SELECT COUNT(*) FROM RED_Addon_Settings),
        (SELECT COUNT(*) FROM RED_Addon_Migrations),
        (SELECT COUNT(*) FROM RED_Addon_Activity_Log),
        (SELECT COUNT(*) FROM RED_Addon_Permission_Activity_Log),
        (SELECT COUNT(*) FROM RED_Addon_Component_Revisions),
        (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions),
        (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
        (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
        (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits),
        (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys),
        (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions),
        (SELECT COUNT(*) FROM RED_Advanced),
        (SELECT COUNT(*) FROM RED_Articles),
        (SELECT COUNT(*) FROM RED_C_Form),
        (SELECT COUNT(*) FROM RED_C_Gallery),
        (SELECT COUNT(*) FROM RED_Components),
        (SELECT COUNT(*) FROM RED_Features),
        (SELECT COUNT(*) FROM RED_Layouts),
        (SELECT COUNT(*) FROM RED_Menu),
        (SELECT COUNT(*) FROM RED_Sections),
        (SELECT COUNT(*) FROM RED_Tools),
        (SELECT COUNT(*) FROM RED_Categories),
        (SELECT COUNT(*) FROM RED_SubCategories),
        (SELECT COUNT(*) FROM RED_C_Menu),
        (SELECT COUNT(*) FROM RED_Login_Attempts),
        (SELECT COUNT(*) FROM RED_Admin_Activity_Log),
        (SELECT COUNT(*) FROM RED_Content_Revisions),
        (SELECT COUNT(*) FROM RED_Custom_Layouts),
        (SELECT COUNT(*) FROM RED_Custom_Layout_Revisions),
        (SELECT COUNT(*) FROM RED_Page_SEO)
    );
")"
relationship_errors="$(red_acceptance_app_mysql --execute="
    SELECT CONCAT_WS(
        ':',
        (SELECT COUNT(*) FROM RED_C_Form f LEFT JOIN RED_Articles a ON a.RecordID=CAST(f.RefID AS UNSIGNED) WHERE a.RecordID IS NULL),
        (SELECT COUNT(*) FROM RED_C_Gallery g LEFT JOIN RED_Articles a ON a.RecordID=CAST(g.RefID AS UNSIGNED) WHERE a.RecordID IS NULL),
        (SELECT COUNT(*) FROM RED_Articles a LEFT JOIN RED_Sections s ON s.Sections=a.Sections AND s.Language=a.Language WHERE a.Sections<>'' AND s.RecordID IS NULL),
        (SELECT COUNT(*) FROM RED_Articles a LEFT JOIN RED_Categories c ON c.Categories=a.Categories AND c.Language=a.Language WHERE a.Categories<>'' AND c.RecordID IS NULL),
        (SELECT COUNT(*) FROM RED_Articles a LEFT JOIN RED_SubCategories sc ON sc.SubCategories=a.SubCategories AND sc.Language=a.Language WHERE a.SubCategories<>'' AND sc.RecordID IS NULL),
        (SELECT COUNT(*) FROM RED_Articles a LEFT JOIN RED_Layouts l ON l.UniqueName=a.Layout WHERE a.Layout<>'' AND l.UniqueName IS NULL),
        (SELECT COUNT(*) FROM RED_Sections s LEFT JOIN RED_Layouts l ON l.UniqueName=s.Layout WHERE s.Layout<>'' AND l.UniqueName IS NULL),
        (SELECT COUNT(*) FROM RED_Articles a WHERE a.Component NOT IN ('Form','Gallery') AND NOT EXISTS (SELECT 1 FROM RED_Components c WHERE c.UniqueName=a.Component)),
        (SELECT COUNT(*) FROM RED_Articles a JOIN RED_C_Form f ON a.RecordID=CAST(f.RefID AS UNSIGNED) WHERE a.Component='Form' AND NOT EXISTS (SELECT 1 FROM RED_Components c WHERE c.UniqueName='Form' AND c.Layout=f.FormType)),
        (SELECT COUNT(*) FROM RED_Articles a JOIN RED_C_Gallery g ON a.RecordID=CAST(g.RefID AS UNSIGNED) WHERE a.Component='Gallery' AND NOT EXISTS (SELECT 1 FROM RED_Components c WHERE c.UniqueName='Gallery' AND c.Layout=g.GalleryType)),
        (SELECT COUNT(*) FROM RED_Categories c LEFT JOIN RED_Sections s ON s.RecordID=c.SectionRecordID AND s.Language=c.Language WHERE c.SectionRecordID IS NOT NULL AND s.RecordID IS NULL),
        (SELECT COUNT(*) FROM RED_SubCategories sc LEFT JOIN RED_Categories c ON c.RecordID=sc.CategoryRecordID AND c.Language=sc.Language WHERE sc.CategoryRecordID IS NOT NULL AND c.RecordID IS NULL),
        (SELECT COUNT(*) FROM RED_Articles a JOIN RED_Categories c ON c.Categories=a.Categories AND c.Language=a.Language JOIN RED_Sections s ON s.RecordID=c.SectionRecordID AND s.Language=c.Language WHERE a.Categories<>'' AND c.SectionRecordID IS NOT NULL AND s.Sections<>a.Sections),
        (SELECT COUNT(*) FROM RED_Articles a JOIN RED_SubCategories sc ON sc.SubCategories=a.SubCategories AND sc.Language=a.Language JOIN RED_Categories c ON c.RecordID=sc.CategoryRecordID AND c.Language=sc.Language WHERE a.SubCategories<>'' AND sc.CategoryRecordID IS NOT NULL AND (c.Categories<>a.Categories OR c.SectionRecordID<>(SELECT s.RecordID FROM RED_Sections s WHERE s.Sections=a.Sections AND s.Language=a.Language LIMIT 1)))
    );
")"
area_parent_foreign_keys="$(red_acceptance_app_mysql --execute="
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE()
      AND CONSTRAINT_NAME IN ('fk_red_categories_section','fk_red_subcategories_category');
")"
admin_authorization_foreign_keys="$(red_acceptance_app_mysql --execute="
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE()
      AND CONSTRAINT_NAME IN (
        'fk_red_admin_roles_admin',
        'fk_red_admin_capabilities_admin'
      );
")"
addon_registry_foreign_keys="$(red_acceptance_app_mysql --execute="
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE()
      AND CONSTRAINT_NAME IN (
        'fk_red_addon_migrations_installation',
        'fk_red_addon_settings_installation',
        'fk_red_addon_admin_action_execution_installation'
      );
")"

red_acceptance_assert_equals 'final table/engine/charset state' '35:35:0' "$final_table_state"
red_acceptance_assert_equals \
    'canonical installer row counts' \
    '2:0:0:0:0:0:0:0:0:0:0:0:0:0:0:9:4:2:1:11:2:4:2:3:2:0:0:0:0:0:0:0:0:0' \
    "$canonical_counts"
red_acceptance_assert_equals 'relationship error counts' '0:0:0:0:0:0:0:0:0:0:0:0:0:0' "$relationship_errors"
red_acceptance_assert_equals 'area parent foreign keys' '2' "$area_parent_foreign_keys"
red_acceptance_assert_equals 'administrator authorization foreign keys' '2' "$admin_authorization_foreign_keys"
red_acceptance_assert_equals 'add-on registry foreign keys' '3' "$addon_registry_foreign_keys"

printf '%s\n' 'Running disposable theme contract serialization checks.'
RED_DB_NAME="$ACCEPTANCE_DATABASE" "$SCRIPT_DIR/theme-contract-lock-self-test.sh"

ACCEPTANCE_SCHEMA_MANIFEST="$(mktemp "${TMPDIR:-/tmp}/redcms-acceptance-schema.XXXXXX")"
red_acceptance_schema_manifest "$ACCEPTANCE_DATABASE" > "$ACCEPTANCE_SCHEMA_MANIFEST"
acceptance_schema_signature="$(red_sha256_file "$ACCEPTANCE_SCHEMA_MANIFEST")"
if [[ "$acceptance_schema_signature" != "$PRIMARY_SCHEMA_SIGNATURE" ]]; then
    printf '%s\n' 'FAIL: normalized schema differs from the configured primary database:' >&2
    diff -u "$PRIMARY_SCHEMA_MANIFEST" "$ACCEPTANCE_SCHEMA_MANIFEST" >&2 || true
    exit 1
fi
printf 'PASS: normalized schema signature = %s\n' "$acceptance_schema_signature"

primary_snapshot_now="$(red_acceptance_primary_snapshot)"
red_acceptance_assert_equals 'primary database isolation snapshot' "$PRIMARY_SNAPSHOT_BEFORE" "$primary_snapshot_now"

printf '%s\n' 'Starting isolated PHP runtime and canonical public-route checks.'
red_acceptance_start_php
red_acceptance_inject_failure after_server

printf '%s\n' 'Running isolated static immutable add-on asset endpoint lifecycle.'
red_acceptance_run_addon_asset_endpoint

printf '%s\n' 'Running isolated core-owned add-on asset injection lifecycle.'
red_acceptance_run_addon_asset_injection

red_acceptance_check_route 'home' '/' 'starter-navigation' 'Contacto'
red_acceptance_check_route 'contact' '/contacto/' 'id="form_contact"' 'name="message"'
red_acceptance_check_route 'administration' '/administracion/' 'id="form_login"' 'name="password"'
red_acceptance_check_route 'instructions' '/administracion/instructions' 'id="guide-overview"' 'RED-CMS'
red_acceptance_check_route 'test-vimeo' '/administracion/test-vimeo' 'Como Agregar Contenido' 'starter-navigation'
red_acceptance_check_not_found_route 'unmatched-route' '/codex-acceptance-missing-route/' '' '' 'route'

printf '%s\n' 'Running disposable administrator authentication lifecycle.'
red_acceptance_run_authentication
red_acceptance_inject_failure after_authentication

printf '%s\n' 'Running disposable Guest permission-adversarial lifecycle.'
red_acceptance_run_guest_permissions
red_acceptance_inject_failure after_guest_permissions

printf '%s\n' 'Running disposable Move Content, Section delete, and Article archive lifecycle.'
red_acceptance_run_section_archive_delete
red_acceptance_inject_failure after_section_archive_delete

printf '%s\n' 'Running disposable Webmaster Article CRUD lifecycle.'
red_acceptance_run_article_crud
red_acceptance_inject_failure after_article_crud

printf '%s\n' 'Running disposable Webmaster Form CRUD lifecycle.'
red_acceptance_run_form_crud
red_acceptance_inject_failure after_form_crud

printf '%s\n' 'Running disposable Webmaster Gallery CRUD lifecycle.'
red_acceptance_run_gallery_crud
red_acceptance_inject_failure after_gallery_crud

printf '%s\n' 'Running disposable Gallery image upload lifecycle.'
red_acceptance_run_gallery_upload
red_acceptance_inject_failure after_gallery_upload_lifecycle

printf '%s\n' 'Running disposable forced Gallery transaction rollback lifecycle.'
red_acceptance_run_forced_rollback
red_acceptance_inject_failure after_forced_rollback_lifecycle

if grep -Eq 'PHP (Warning|Deprecated|Notice|Fatal)|Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]' "$ACCEPTANCE_PHP_LOG"; then
    printf '%s\n' 'FAIL: isolated PHP server log contains a PHP/runtime error marker:' >&2
    grep -En 'PHP (Warning|Deprecated|Notice|Fatal)|Fatal error|Parse error|Database query failed|Uncaught [A-Za-z]' "$ACCEPTANCE_PHP_LOG" >&2 || true
    exit 1
fi
printf '%s\n' 'PASS: isolated PHP server log has no PHP/runtime error markers.'

printf '%s\n' 'Acceptance database, Store Lite product/variant, server-authoritative cart-line, non-executing payment-adapter profile, read-only payment-adapter database readiness, registration-only payment-adapter registrar, closed server-event ingress, Owner-authorized atomic payment-adapter enablement, one-time provider-contact authorization, atomic provider-contact attempt-claim, loopback-only provider-contact execution, synthetic-package provider-contact execution, provider-operation in-memory runner, synthetic Checkout package/core runner, Sandbox Checkout mutation authorization, one-attempt claim, transport-double start/result runner, real-operation preflight containment and identity, and server-local one-shot operator-command contracts, Owner authorization, atomic package-permission grant/revoke and audit, add-on setting values/editor/secret resolution/availability/asset plan/storage/write preflight/atomic writer/replacement/permission-scoped settings read model, secret-capable service runtime/by-reference access/result redaction, add-on administrator tool form schema/preview/planning/current-value loading/JSON validation/atomic writer/edit-and-Save and target-free Create bridges, add-on component data loading, transactional updates, immutable revision snapshots, atomic revision restore, component creation, parent metadata, atomic public placement, atomic deletion, add-on registry reconciliation/asset-delivery-preflight/static immutable endpoint/core-owned public-admin injection, enabled add-on request bootstrap, add-on component persistence/dispatch, disabled add-on installation/recovery and disabled-package upgrade/recovery, read-only add-on enablement/public-mutation live-data/operational-package preflight/anonymous subject and CSRF/fixed-window rate-limit/idempotency-key/atomic-runner/bounded-response/declared-form/form-UI/HTTP-envelope/route-selector foundations, atomic add-on legacy and operational enablement/disablement/re-enablement, theme-contract serialization, Layout Builder, public runtime, authentication, permission, Move Content, Section archive/delete, Article upload/CRUD, Form CRUD, Gallery CRUD, Gallery upload, and forced transaction rollback checks passed.'
