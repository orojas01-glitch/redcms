#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

MODE="run"
TARGET_DATABASE="$RED_DB_NAME_RESOLVED"

for argument in "$@"; do
    case "$argument" in
        --status) MODE="status" ;;
        --dry-run) MODE="dry-run" ;;
        --database=*) TARGET_DATABASE="${argument#--database=}" ;;
        *)
            printf 'Unknown argument: %s\n' "$argument" >&2
            exit 64
            ;;
    esac
done

if [[ ! "$TARGET_DATABASE" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf 'Invalid database name: %s\n' "$TARGET_DATABASE" >&2
    exit 64
fi

MYSQL_ARGS=(
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE"
    "--database=$TARGET_DATABASE"
    --batch
    --raw
    --skip-column-names
)

TRACKING_TABLE_EXISTS="$("$RED_MYSQL_BIN" "${MYSQL_ARGS[@]}" --execute="
SELECT COUNT(*)
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'RED_Schema_Migrations';
")"

if [[ "$TRACKING_TABLE_EXISTS" -eq 0 && "$MODE" == "run" ]]; then
    "$RED_MYSQL_BIN" "${MYSQL_ARGS[@]}" --execute="
    CREATE TABLE RED_Schema_Migrations (
      Migration varchar(255) NOT NULL,
      Checksum char(64) NOT NULL,
      AppliedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      ExecutionMs int unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY (Migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
    TRACKING_TABLE_EXISTS=1
fi

APPLIED_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-migrations-applied.XXXXXX")"
RUN_FILE=""

cleanup_migration_files() {
    rm -f "$APPLIED_FILE"
    if [[ -n "$RUN_FILE" ]]; then
        rm -f "$RUN_FILE"
    fi
    red_remove_defaults_file
}
trap cleanup_migration_files EXIT

if [[ "$TRACKING_TABLE_EXISTS" -eq 1 ]]; then
    "$RED_MYSQL_BIN" "${MYSQL_ARGS[@]}" --execute="
    SELECT Migration, Checksum, AppliedAt, ExecutionMs
    FROM RED_Schema_Migrations
    ORDER BY Migration;
    " > "$APPLIED_FILE"
fi

MIGRATION_FILES=("$RED_PROJECT_ROOT"/database/migrations/*.sql)
if [[ ! -e "${MIGRATION_FILES[0]}" ]]; then
    MIGRATION_FILES=()
fi

PENDING_FILES=()
PENDING_NAMES=()
PENDING_CHECKSUMS=()
DRIFT_COUNT=0
APPLIED_COUNT=0

for migration_file in "${MIGRATION_FILES[@]}"; do
    migration_name="$(basename "$migration_file")"
    if [[ ! "$migration_name" =~ ^[A-Za-z0-9._-]+\.sql$ ]]; then
        printf 'Invalid migration filename: %s\n' "$migration_name" >&2
        exit 64
    fi

    migration_checksum="$(red_sha256_file "$migration_file")"
    applied_line="$(awk -F '\t' -v name="$migration_name" '$1 == name { print; exit }' "$APPLIED_FILE")"

    if [[ -n "$applied_line" ]]; then
        IFS=$'\t' read -r applied_name applied_checksum applied_at execution_ms <<< "$applied_line"
        APPLIED_COUNT=$((APPLIED_COUNT + 1))
        if [[ "$applied_checksum" != "$migration_checksum" ]]; then
            printf 'drift     %s\n' "$migration_name"
            DRIFT_COUNT=$((DRIFT_COUNT + 1))
        else
            printf 'applied   %s  %s  %sms\n' "$migration_name" "$applied_at" "$execution_ms"
        fi
    else
        printf 'pending   %s\n' "$migration_name"
        PENDING_FILES+=("$migration_file")
        PENDING_NAMES+=("$migration_name")
        PENDING_CHECKSUMS+=("$migration_checksum")
    fi
done

while IFS=$'\t' read -r applied_name applied_checksum applied_at execution_ms; do
    if [[ -n "$applied_name" && ! -f "$RED_PROJECT_ROOT/database/migrations/$applied_name" ]]; then
        printf 'orphaned  %s\n' "$applied_name"
    fi
done < "$APPLIED_FILE"

if [[ "$DRIFT_COUNT" -gt 0 ]]; then
    printf '%s\n' 'Migration checksum drift detected; execution refused.' >&2
    exit 65
fi

PENDING_COUNT="${#PENDING_FILES[@]}"
if [[ "$MODE" != "run" ]]; then
    printf 'Summary: %d applied, %d pending, %d drifted\n' "$APPLIED_COUNT" "$PENDING_COUNT" "$DRIFT_COUNT"
    exit 0
fi

if [[ "$PENDING_COUNT" -eq 0 ]]; then
    printf '%s\n' 'No pending migrations.'
    exit 0
fi

RUN_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-migrations-run.XXXXXX")"
{
    printf "SET @redcms_lock_name = 'redcms_migrations_%s';\n" "$TARGET_DATABASE"
    printf 'SELECT GET_LOCK(@redcms_lock_name, 10) INTO @redcms_lock_acquired;\n'
    printf "SET @redcms_lock_assertion = IF(@redcms_lock_acquired = 1, 'SELECT 1', 'SELECT * FROM RED_Schema_Migration_Lock_Not_Acquired');\n"
    printf 'PREPARE redcms_lock_statement FROM @redcms_lock_assertion;\n'
    printf 'EXECUTE redcms_lock_statement;\n'
    printf 'DEALLOCATE PREPARE redcms_lock_statement;\n'
    printf 'SET @redcms_original_sql_mode = @@SESSION.sql_mode;\n'

    for index in "${!PENDING_FILES[@]}"; do
        printf 'SET @redcms_started_at = CURRENT_TIMESTAMP(6);\n'
        printf 'SOURCE %s;\n' "${PENDING_FILES[$index]}"
        printf 'SET SESSION sql_mode = @redcms_original_sql_mode;\n'
        printf "INSERT INTO RED_Schema_Migrations (Migration, Checksum, ExecutionMs) VALUES ('%s', '%s', ROUND(TIMESTAMPDIFF(MICROSECOND, @redcms_started_at, CURRENT_TIMESTAMP(6)) / 1000));\n" \
            "${PENDING_NAMES[$index]}" "${PENDING_CHECKSUMS[$index]}"
    done

    printf 'SELECT RELEASE_LOCK(@redcms_lock_name);\n'
} > "$RUN_FILE"

"$RED_MYSQL_BIN" "${MYSQL_ARGS[@]}" < "$RUN_FILE" >/dev/null

for migration_name in "${PENDING_NAMES[@]}"; do
    execution_ms="$("$RED_MYSQL_BIN" "${MYSQL_ARGS[@]}" --execute="SELECT ExecutionMs FROM RED_Schema_Migrations WHERE Migration='$migration_name';")"
    printf 'completed %s  %sms\n' "$migration_name" "$execution_ms"
done
printf 'Migration run complete: %d applied.\n' "$PENDING_COUNT"
