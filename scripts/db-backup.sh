#!/bin/bash

set -euo pipefail
source "$(cd "$(dirname "$0")" && pwd)/db-common.sh"

if [[ $# -ne 1 ]]; then
    printf 'Usage: %s /absolute/path/backup.sql\n' "$0" >&2
    exit 64
fi

OUTPUT="$1"
if [[ "$OUTPUT" != /* || "$OUTPUT" != *.sql ]]; then
    printf '%s\n' 'Backup path must be absolute and end in .sql.' >&2
    exit 64
fi

mkdir -p "$(dirname "$OUTPUT")"

MYISAM_COUNT="$($RED_MYSQL_BIN --defaults-extra-file="$RED_DB_DEFAULTS_FILE" -N -B "$RED_DB_NAME_RESOLVED" -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND ENGINE='MyISAM'")"
DUMP_MODE=(--single-transaction --quick)
if [[ "$MYISAM_COUNT" -gt 0 ]]; then
    if [[ "${RED_DB_OFFLINE_CONFIRMED:-0}" != "1" ]]; then
        MYISAM_LABEL="tables"
        MYISAM_VERB="require"
        if [[ "$MYISAM_COUNT" -eq 1 ]]; then
            MYISAM_LABEL="table"
            MYISAM_VERB="requires"
        fi
        printf 'Backup refused: %s MyISAM %s %s the PHP application writer to be stopped.\n' "$MYISAM_COUNT" "$MYISAM_LABEL" "$MYISAM_VERB" >&2
        printf '%s\n' 'Stop the PHP server, then rerun with RED_DB_OFFLINE_CONFIRMED=1.' >&2
        exit 65
    fi
    DUMP_MODE=(--skip-lock-tables)
fi

TEMP_OUTPUT="$(mktemp "${OUTPUT}.tmp.XXXXXX")"
cleanup_output() {
    rm -f "$TEMP_OUTPUT"
}
trap 'cleanup_output; red_remove_defaults_file' EXIT

"$RED_MYSQLDUMP_BIN" \
    --defaults-extra-file="$RED_DB_DEFAULTS_FILE" \
    --default-character-set=utf8mb4 \
    --routines --triggers --events --hex-blob --no-tablespaces \
    "${DUMP_MODE[@]}" \
    "$RED_DB_NAME_RESOLVED" > "$TEMP_OUTPUT"

if [[ ! -s "$TEMP_OUTPUT" ]] || ! tail -5 "$TEMP_OUTPUT" | grep -q 'Dump completed'; then
    printf '%s\n' 'Backup validation failed: dump is empty or incomplete.' >&2
    exit 66
fi

mv "$TEMP_OUTPUT" "$OUTPUT"
printf '%s  %s\n' "$(red_sha256_file "$OUTPUT")" "$OUTPUT" > "${OUTPUT}.sha256"
printf 'Backup complete: %s\n' "$OUTPUT"
cat "${OUTPUT}.sha256"
