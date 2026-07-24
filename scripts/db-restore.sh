#!/bin/bash

set -euo pipefail
source "$(cd "$(dirname "$0")" && pwd)/db-common.sh"

if [[ $# -ne 2 ]]; then
    printf 'Usage: %s /absolute/path/backup.sql target_database\n' "$0" >&2
    exit 64
fi

DUMP_FILE="$1"
TARGET_DATABASE="$2"

if [[ ! -s "$DUMP_FILE" ]]; then
    printf '%s\n' 'Restore dump does not exist or is empty.' >&2
    exit 64
fi
if [[ ! "$TARGET_DATABASE" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf '%s\n' 'Target database name contains unsupported characters.' >&2
    exit 64
fi
if [[ "$TARGET_DATABASE" == "$RED_DB_NAME_RESOLVED" && "${RED_DB_ALLOW_PRIMARY_RESTORE:-0}" != "1" ]]; then
    printf '%s\n' 'Restore refused: the configured primary database is protected.' >&2
    exit 65
fi

DATABASE_EXISTS="$($RED_MYSQL_BIN --defaults-extra-file="$RED_DB_DEFAULTS_FILE" -N -B -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$TARGET_DATABASE'")"
if [[ "$DATABASE_EXISTS" -ne 1 ]]; then
    printf 'Restore refused: target database %s must already exist and be granted to this account.\n' "$TARGET_DATABASE" >&2
    exit 65
fi

TABLE_COUNT="$($RED_MYSQL_BIN --defaults-extra-file="$RED_DB_DEFAULTS_FILE" -N -B -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$TARGET_DATABASE'")"
if [[ "$TABLE_COUNT" -ne 0 && "${RED_DB_ALLOW_NONEMPTY_RESTORE:-0}" != "1" ]]; then
    printf 'Restore refused: target database %s contains %s tables.\n' "$TARGET_DATABASE" "$TABLE_COUNT" >&2
    exit 65
fi

"$RED_MYSQL_BIN" --defaults-extra-file="$RED_DB_DEFAULTS_FILE" "$TARGET_DATABASE" < "$DUMP_FILE"
RESTORED_TABLES="$($RED_MYSQL_BIN --defaults-extra-file="$RED_DB_DEFAULTS_FILE" -N -B -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$TARGET_DATABASE'")"
printf 'Restore complete: %s tables in %s\n' "$RESTORED_TABLES" "$TARGET_DATABASE"

