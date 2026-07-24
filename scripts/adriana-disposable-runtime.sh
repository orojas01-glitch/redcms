#!/bin/bash

set -euo pipefail
umask 077

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"

STATE_FILE="${RED_ADRIANA_STATE_FILE:-/private/tmp/redcms-adriana-28-current.state}"
FRANKENPHP_BIN_RESOLVED="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
PACKAGE_RELATIVE_PATH="content-migrations/adriana-granobles-v4"
MANIFEST_RELATIVE_PATH="$PACKAGE_RELATIVE_PATH/routes.json"
PUBLIC_MEDIA_RELATIVE_PATH="images/articles/adriana-granobles-v4"
RUNTIME_MARKER_NAME=".redcms-adriana-28-runtime"
APPROVED_MANIFEST_SHA256="018fb1a336a7635c85fe883ec94feaad5ff447153d819d4497bd30b9c498937c"

OPERATION=""
ADMIN_DEFAULTS_FILE=""
PRIMARY_DATABASE="$RED_DB_NAME_RESOLVED"
PRIMARY_SNAPSHOT_BEFORE=""
DISPOSABLE_DATABASE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
RUN_ROOT=""
WEBROOT=""
PHP_LOG=""
PHP_PID=0
PORT=0
MANIFEST_SHA256=""
CREATED_AT=0

RUN_ROOT_CREATED=0
DATABASE_CREATED=0
GRANT_CREATED=0
SERVER_CREATED=0
STATE_WRITTEN=0
CREATE_SUCCEEDED=0

red_adriana_usage() {
    printf 'Usage: %s create|serve|status|destroy\n' "$0"
    printf '%s\n' '  create   Clone the configured primary database and leave an isolated 28-route runtime running.'
    printf '%s\n' '  serve    Reattach the validated disposable runtime to a foreground PHP server.'
    printf '%s\n' '  status   Verify the recorded database, process, routes, media, and primary-database isolation.'
    printf '%s\n' '  destroy  Stop and remove only the exact disposable runtime recorded in the state file.'
}

red_adriana_validate_state_path() {
    if [[ ! "$STATE_FILE" =~ ^/private/tmp/redcms-adriana-28-[A-Za-z0-9_.-]+\.state$ ]]; then
        printf 'State path must be a simple file under /private/tmp with the redcms-adriana-28- prefix: %s\n' "$STATE_FILE" >&2
        return 64
    fi
}

red_adriana_validate_database_name() {
    local database="$1"

    if [[ ! "$database" =~ ^redcms_adriana_28_[A-Za-z0-9_]+$ ]]; then
        printf 'Disposable database name is outside the required prefix: %s\n' "$database" >&2
        return 64
    fi
    if [[ ${#database} -gt 64 ]]; then
        printf 'Disposable database name exceeds the MySQL identifier limit: %s\n' "$database" >&2
        return 64
    fi
    if [[ "$database" == "$PRIMARY_DATABASE" ]]; then
        printf '%s\n' 'Disposable database name resolves to the protected primary database.' >&2
        return 65
    fi
}

red_adriana_create_admin_defaults() {
    ADMIN_DEFAULTS_FILE="$(mktemp "/private/tmp/redcms-adriana-28-admin.XXXXXX")"
    chmod 600 "$ADMIN_DEFAULTS_FILE"
    {
        printf '[client]\n'
        printf 'protocol=tcp\n'
        printf 'host=%s\n' "$RED_DB_HOST_RESOLVED"
        printf 'port=%s\n' "$RED_DB_PORT_RESOLVED"
        printf 'user=%s\n' "${RED_ADRIANA_DB_ADMIN_USER:-${RED_ACCEPTANCE_DB_ADMIN_USER:-root}}"
        printf 'password=%s\n' "${RED_ADRIANA_DB_ADMIN_PASS:-${RED_ACCEPTANCE_DB_ADMIN_PASS:-}}"
        printf 'default-character-set=utf8mb4\n'
    } > "$ADMIN_DEFAULTS_FILE"
}

red_adriana_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_adriana_primary_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$PRIMARY_DATABASE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_adriana_target_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        "--database=$DISPOSABLE_DATABASE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_adriana_primary_snapshot() (
    set -euo pipefail
    local manifest_file table_file table_name

    manifest_file="$(mktemp "/private/tmp/redcms-adriana-28-primary-manifest.XXXXXX")"
    table_file="$(mktemp "/private/tmp/redcms-adriana-28-primary-tables.XXXXXX")"
    trap 'rm -f -- "$manifest_file" "$table_file"' EXIT

    red_adriana_primary_mysql --execute="
        SELECT CONCAT_WS(
            '|', 'T', TABLE_NAME, ENGINE, COALESCE(TABLE_COLLATION, ''),
            COALESCE(AUTO_INCREMENT, 0), COALESCE(CREATE_OPTIONS, '')
        )
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA=DATABASE()
        ORDER BY TABLE_NAME;
        SELECT CONCAT_WS(
            '|', 'C', TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE,
            IS_NULLABLE, HEX(COALESCE(COLUMN_DEFAULT, '<NULL>')), EXTRA,
            COALESCE(COLLATION_NAME, '')
        )
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE()
        ORDER BY TABLE_NAME, ORDINAL_POSITION;
        SELECT CONCAT_WS(
            '|', 'R', TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE,
            ACTION_TIMING, SHA2(ACTION_STATEMENT, 256)
        )
        FROM INFORMATION_SCHEMA.TRIGGERS
        WHERE TRIGGER_SCHEMA=DATABASE()
        ORDER BY TRIGGER_NAME;
    " > "$manifest_file"

    red_adriana_primary_mysql --execute="
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'
        ORDER BY TABLE_NAME;
    " > "$table_file"

    while IFS= read -r table_name; do
        if [[ ! "$table_name" =~ ^[A-Za-z0-9_]+$ ]]; then
            printf 'Unsafe primary table name encountered while creating the isolation snapshot: %s\n' "$table_name" >&2
            exit 64
        fi
        red_adriana_primary_mysql --execute="CHECKSUM TABLE \`$table_name\`;" >> "$manifest_file"
    done < "$table_file"

    red_sha256_file "$manifest_file"
)

red_adriana_port_in_use() {
    local candidate="$1"

    if command -v lsof >/dev/null 2>&1; then
        lsof -nP -iTCP:"$candidate" -sTCP:LISTEN >/dev/null 2>&1
        return $?
    fi
    if command -v nc >/dev/null 2>&1; then
        nc -z 127.0.0.1 "$candidate" >/dev/null 2>&1
        return $?
    fi

    (echo > "/dev/tcp/127.0.0.1/$candidate") >/dev/null 2>&1
}

red_adriana_select_port() {
    local requested_port="${RED_ADRIANA_PORT:-}"
    local candidate=8060

    if [[ -n "$requested_port" ]]; then
        if [[ ! "$requested_port" =~ ^[0-9]+$ ]] \
            || [[ "$requested_port" -lt 8060 ]] \
            || [[ "$requested_port" -gt 65535 ]]; then
            printf 'Disposable port must be an integer from 8060 through 65535: %s\n' "$requested_port" >&2
            return 64
        fi
        if [[ "$requested_port" -eq 8055 ]]; then
            printf '%s\n' 'Port 8055 is reserved for the primary RED-CMS runtime.' >&2
            return 65
        fi
        if red_adriana_port_in_use "$requested_port"; then
            printf 'Requested disposable port is already in use: %s\n' "$requested_port" >&2
            return 65
        fi
        PORT="$requested_port"
        return 0
    fi

    while [[ "$candidate" -le 8159 ]]; do
        if [[ "$candidate" -ne 8055 ]] && ! red_adriana_port_in_use "$candidate"; then
            PORT="$candidate"
            return 0
        fi
        candidate=$((candidate + 1))
    done

    printf '%s\n' 'No unused disposable port was found from 8060 through 8159.' >&2
    return 69
}

red_adriana_process_matches_state() {
    local process_command process_cwd

    if [[ ! "$PHP_PID" =~ ^[0-9]+$ ]] || [[ "$PHP_PID" -le 1 ]] || ! kill -0 "$PHP_PID" 2>/dev/null; then
        return 1
    fi

    process_command="$(ps -ww -p "$PHP_PID" -o command= 2>/dev/null || true)"
    if [[ "$process_command" != *frankenphp* ]] \
        || [[ "$process_command" != *php-server* ]] \
        || [[ "$process_command" != *"127.0.0.1:$PORT"* ]]; then
        return 1
    fi

    if command -v lsof >/dev/null 2>&1; then
        process_cwd="$(lsof -a -p "$PHP_PID" -d cwd -Fn 2>/dev/null | sed -n 's/^n//p' | head -1)"
        if [[ "$process_cwd" != "$WEBROOT" ]]; then
            return 1
        fi
    fi

    return 0
}

red_adriana_stop_server() {
    local attempt=0

    if [[ ! "$PHP_PID" =~ ^[0-9]+$ ]] || [[ "$PHP_PID" -le 1 ]] || ! kill -0 "$PHP_PID" 2>/dev/null; then
        return 0
    fi

    kill -TERM "$PHP_PID" 2>/dev/null || return 1
    while kill -0 "$PHP_PID" 2>/dev/null && [[ "$attempt" -lt 50 ]]; do
        sleep 0.1
        attempt=$((attempt + 1))
    done
    if kill -0 "$PHP_PID" 2>/dev/null; then
        kill -KILL "$PHP_PID" 2>/dev/null || return 1
        attempt=0
        while kill -0 "$PHP_PID" 2>/dev/null && [[ "$attempt" -lt 20 ]]; do
            sleep 0.1
            attempt=$((attempt + 1))
        done
    fi

    ! kill -0 "$PHP_PID" 2>/dev/null
}

red_adriana_path_metadata() {
    local path="$1"

    if [[ "$(uname -s)" == 'Darwin' ]]; then
        stat -f '%u:%Lp' "$path"
    else
        stat -c '%u:%a' "$path"
    fi
}

red_adriana_validate_run_root_identity() {
    local marker_file marker_database

    if [[ ! "$RUN_ROOT" =~ ^/private/tmp/redcms-adriana-28-runtime\.[A-Za-z0-9]+$ ]]; then
        printf 'Runtime root is outside the guarded disposable prefix: %s\n' "$RUN_ROOT" >&2
        return 65
    fi
    if [[ ! -d "$RUN_ROOT" || -L "$RUN_ROOT" ]]; then
        printf 'Runtime root is missing, not a directory, or is a symbolic link: %s\n' "$RUN_ROOT" >&2
        return 65
    fi
    if [[ "$(red_adriana_path_metadata "$RUN_ROOT")" != "$(id -u):700" ]]; then
        printf '%s\n' 'Runtime root must be owned by this user with mode 700.' >&2
        return 65
    fi
    if [[ "$WEBROOT" != "$RUN_ROOT/webroot" || "$PHP_LOG" != "$RUN_ROOT/php.log" ]]; then
        printf '%s\n' 'Runtime paths do not match the guarded root layout.' >&2
        return 65
    fi

    marker_file="$RUN_ROOT/$RUNTIME_MARKER_NAME"
    if [[ ! -f "$marker_file" || -L "$marker_file" ]]; then
        printf 'Disposable runtime marker is missing or unsafe: %s\n' "$marker_file" >&2
        return 65
    fi
    marker_database="$(sed -n 's/^database=//p' "$marker_file")"
    if [[ "$(sed -n '1p' "$marker_file")" != 'redcms-adriana-28-runtime-v1' ]] \
        || [[ "$marker_database" != "$DISPOSABLE_DATABASE" ]]; then
        printf '%s\n' 'Disposable runtime marker does not match the recorded database.' >&2
        return 65
    fi
}

red_adriana_validate_run_root() {
    local config_file

    red_adriana_validate_run_root_identity || return 1
    if [[ ! -d "$WEBROOT" || -L "$WEBROOT" ]]; then
        printf 'Disposable webroot is missing or unsafe: %s\n' "$WEBROOT" >&2
        return 65
    fi
    if [[ "$(red_adriana_path_metadata "$WEBROOT")" != "$(id -u):700" ]]; then
        printf '%s\n' 'Disposable webroot must be owned by this user with mode 700.' >&2
        return 65
    fi
    if [[ -e "$WEBROOT/.git" || -L "$WEBROOT/.git" ]]; then
        printf '%s\n' 'Disposable webroot unexpectedly contains repository metadata.' >&2
        return 65
    fi

    config_file="$WEBROOT/includes/config.local.php"
    if [[ -e "$config_file" || -L "$config_file" ]]; then
        if [[ ! -f "$config_file" || -L "$config_file" ]] \
            || [[ "$(red_adriana_path_metadata "$config_file")" != "$(id -u):600" ]]; then
            printf '%s\n' 'Disposable local configuration is not a protected mode-600 regular file.' >&2
            return 65
        fi
    fi

}

red_adriana_safe_remove_run_root() {
    red_adriana_validate_run_root_identity || return 1
    rm -rf -- "$RUN_ROOT"
    [[ ! -e "$RUN_ROOT" ]]
}

red_adriana_manifest_summary() {
    local manifest_file="$1"

    "$RED_PHP_BIN_RESOLVED" -r '
        $manifest = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($manifest) || !isset($manifest["counts"]) || !is_array($manifest["counts"])) {
            fwrite(STDERR, "Invalid Adriana content manifest.\n");
            exit(1);
        }
        printf(
            "%d:%d:%d:%s",
            (int) ($manifest["counts"]["routes"] ?? -1),
            (int) ($manifest["counts"]["nonHomeAliases"] ?? -1),
            (int) ($manifest["counts"]["mediaFiles"] ?? -1),
            (string) ($manifest["migrationId"] ?? "")
        );
    ' "$manifest_file"
}

red_adriana_build_media_ledger() {
    local manifest_file="$1"
    local ledger_file="$2"

    "$RED_PHP_BIN_RESOLVED" -r '
        $manifest = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($manifest) || !isset($manifest["media"]) || !is_array($manifest["media"])) {
            fwrite(STDERR, "Manifest media inventory is invalid.\n");
            exit(1);
        }
        foreach ($manifest["media"] as $item) {
            if (!is_array($item)
                || !isset($item["target"], $item["publicPath"], $item["bytes"], $item["sha256"])) {
                fwrite(STDERR, "Manifest media entry is incomplete.\n");
                exit(1);
            }
            printf(
                "%s\t%s\t%d\t%s\n",
                (string) $item["target"],
                (string) $item["publicPath"],
                (int) $item["bytes"],
                (string) $item["sha256"]
            );
        }
    ' "$manifest_file" > "$ledger_file"
}

red_adriana_validate_media_entry() {
    local target="$1"
    local public_path="$2"
    local byte_count="$3"
    local sha256="$4"
    local file_name

    if [[ ! "$target" =~ ^media/[A-Za-z0-9][A-Za-z0-9._-]*$ ]]; then
        printf 'Unsafe staged media target in manifest: %s\n' "$target" >&2
        return 65
    fi
    file_name="${target#media/}"
    if [[ "$public_path" != "/$PUBLIC_MEDIA_RELATIVE_PATH/$file_name" ]]; then
        printf 'Media public path does not match its guarded target: %s\n' "$public_path" >&2
        return 65
    fi
    if [[ ! "$byte_count" =~ ^[0-9]+$ ]] || [[ "$byte_count" -le 0 ]]; then
        printf 'Invalid staged media byte count for %s: %s\n' "$target" "$byte_count" >&2
        return 65
    fi
    if [[ ! "$sha256" =~ ^[a-f0-9]{64}$ ]]; then
        printf 'Invalid staged media SHA-256 for %s.\n' "$target" >&2
        return 65
    fi
}

red_adriana_copy_and_verify_media() {
    local source_package="$RED_PROJECT_ROOT/$PACKAGE_RELATIVE_PATH"
    local manifest_file="$source_package/routes.json"
    local ledger_file="$RUN_ROOT/media-ledger.tsv"
    local public_root="$WEBROOT/$PUBLIC_MEDIA_RELATIVE_PATH"
    local target public_path byte_count sha256 source_file destination_file actual_bytes actual_sha256
    local copied_count=0

    red_adriana_build_media_ledger "$manifest_file" "$ledger_file"
    if [[ "$(wc -l < "$ledger_file" | tr -d '[:space:]')" != '42' ]]; then
        printf '%s\n' 'Staged media ledger must contain exactly 42 files.' >&2
        return 66
    fi

    if [[ -L "$WEBROOT/images" || -L "$WEBROOT/images/articles" ]]; then
        printf '%s\n' 'Disposable media parent directories may not be symbolic links.' >&2
        return 65
    fi
    mkdir -p "$public_root"
    if [[ -L "$public_root" ]]; then
        printf '%s\n' 'Disposable public media directory may not be a symbolic link.' >&2
        return 65
    fi

    while IFS=$'\t' read -r target public_path byte_count sha256; do
        red_adriana_validate_media_entry "$target" "$public_path" "$byte_count" "$sha256"
        source_file="$source_package/$target"
        destination_file="$WEBROOT$public_path"
        if [[ ! -f "$source_file" || -L "$source_file" ]]; then
            printf 'Staged media source is missing or unsafe: %s\n' "$source_file" >&2
            return 66
        fi
        if [[ -e "$destination_file" || -L "$destination_file" ]]; then
            printf 'Disposable media destination unexpectedly exists: %s\n' "$destination_file" >&2
            return 65
        fi

        actual_bytes="$(wc -c < "$source_file" | tr -d '[:space:]')"
        actual_sha256="$(red_sha256_file "$source_file")"
        if [[ "$actual_bytes" != "$byte_count" || "$actual_sha256" != "$sha256" ]]; then
            printf 'Staged media source failed its manifest checksum: %s\n' "$source_file" >&2
            return 66
        fi

        cp "$source_file" "$destination_file"
        chmod 644 "$destination_file"
        actual_bytes="$(wc -c < "$destination_file" | tr -d '[:space:]')"
        actual_sha256="$(red_sha256_file "$destination_file")"
        if [[ "$actual_bytes" != "$byte_count" || "$actual_sha256" != "$sha256" ]]; then
            printf 'Copied media failed its manifest checksum: %s\n' "$destination_file" >&2
            return 66
        fi
        copied_count=$((copied_count + 1))
    done < "$ledger_file"

    if [[ "$copied_count" -ne 42 ]]; then
        printf 'Copied media count is not 42: %s\n' "$copied_count" >&2
        return 66
    fi
    if [[ "$(red_sha256_file "$WEBROOT/$MANIFEST_RELATIVE_PATH")" != "$MANIFEST_SHA256" ]]; then
        printf '%s\n' 'Manifest hash changed while copying the disposable webroot.' >&2
        return 66
    fi
}

red_adriana_verify_public_media() (
    set -euo pipefail
    local manifest_file="$WEBROOT/$MANIFEST_RELATIVE_PATH"
    local ledger_file target public_path byte_count sha256 public_file actual_bytes actual_sha256 verified_count

    ledger_file="$(mktemp "/private/tmp/redcms-adriana-28-media-ledger.XXXXXX")"
    trap 'rm -f -- "$ledger_file"' EXIT
    if [[ ! -f "$manifest_file" || -L "$manifest_file" ]]; then
        printf 'Disposable manifest is missing or unsafe: %s\n' "$manifest_file" >&2
        exit 66
    fi
    if [[ "$(red_sha256_file "$manifest_file")" != "$MANIFEST_SHA256" ]]; then
        printf '%s\n' 'Disposable manifest no longer matches its recorded SHA-256.' >&2
        exit 66
    fi

    red_adriana_build_media_ledger "$manifest_file" "$ledger_file"
    verified_count=0
    while IFS=$'\t' read -r target public_path byte_count sha256; do
        red_adriana_validate_media_entry "$target" "$public_path" "$byte_count" "$sha256"
        public_file="$WEBROOT$public_path"
        if [[ ! -f "$public_file" || -L "$public_file" ]]; then
            printf 'Disposable public media is missing or unsafe: %s\n' "$public_file" >&2
            exit 66
        fi
        actual_bytes="$(wc -c < "$public_file" | tr -d '[:space:]')"
        actual_sha256="$(red_sha256_file "$public_file")"
        if [[ "$actual_bytes" != "$byte_count" || "$actual_sha256" != "$sha256" ]]; then
            printf 'Disposable public media checksum drifted: %s\n' "$public_file" >&2
            exit 66
        fi
        verified_count=$((verified_count + 1))
    done < "$ledger_file"

    if [[ "$verified_count" -ne 42 ]]; then
        printf 'Disposable public media count is not 42: %s\n' "$verified_count" >&2
        exit 66
    fi
)

red_adriana_run_clone_php() {
    local php_script="$1"
    shift

    (
        cd "$WEBROOT"
        export RED_DB_HOST="$RED_DB_HOST_PORT"
        export RED_DB_USER="$RED_DB_USER_RESOLVED"
        export RED_DB_PASS="$RED_DB_PASS_RESOLVED"
        export RED_DB_NAME="$DISPOSABLE_DATABASE"
        export RED_PRIMARY_DB_NAME="$PRIMARY_DATABASE"
        exec "$FRANKENPHP_BIN_RESOLVED" php-cli "$php_script" "$@"
    )
}

red_adriana_run_preflight() {
    (
        cd "$WEBROOT"
        export RED_DB_HOST="$RED_DB_HOST_PORT"
        export RED_DB_USER="$RED_DB_USER_RESOLVED"
        export RED_DB_PASS="$RED_DB_PASS_RESOLVED"
        export RED_DB_NAME="$DISPOSABLE_DATABASE"
        export RED_PRIMARY_DB_NAME="$PRIMARY_DATABASE"
        export FRANKENPHP_BIN="$FRANKENPHP_BIN_RESOLVED"
        exec "$WEBROOT/scripts/theme-preflight.sh" adriana-granobles --json
    )
}

red_adriana_verify_migration_rerun() {
    local first_report="$1"
    local rerun_report="$2"

    "$RED_PHP_BIN_RESOLVED" -r '
        function fail_report($message) {
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }
        $first = json_decode((string) file_get_contents($argv[1]), true);
        $rerun = json_decode((string) file_get_contents($argv[2]), true);
        if (!is_array($first) || !is_array($rerun)) {
            fail_report("Migration evidence is not valid JSON.");
        }
        if (($first["ok"] ?? false) !== true || ($first["status"] ?? "") !== "changed") {
            fail_report("Initial migration report did not record a successful changed import.");
        }
        if (($rerun["ok"] ?? false) !== true || ($rerun["status"] ?? "") !== "unchanged") {
            fail_report("Immediate migration rerun was not a verified no-op.");
        }
        foreach (["targetDatabase", "primaryDatabaseGuard", "manifestSha256", "package", "verification", "idContract"] as $key) {
            if (($first[$key] ?? null) !== ($rerun[$key] ?? null)) {
                fail_report("Migration rerun evidence drifted at " . $key . ".");
            }
        }
        $firstContact = $first["contactTemplateSource"]["sha256"] ?? "";
        $rerunContact = $rerun["contactTemplateSource"]["sha256"] ?? "";
        if (!is_string($firstContact) || $firstContact === "" || !hash_equals($firstContact, (string) $rerunContact)) {
            fail_report("Migration rerun Contact template fingerprint drifted.");
        }
    ' "$first_report" "$rerun_report"
}

red_adriana_verify_pagewise_rerun() {
    local first_report="$1"
    local rerun_report="$2"

    "$RED_PHP_BIN_RESOLVED" -r '
        function fail_report($message) {
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }
        if ($argc !== 3) {
            fail_report("Pagewise rerun verifier received an invalid argument count.");
        }
        $first = json_decode((string) file_get_contents($argv[1]), true);
        $rerun = json_decode((string) file_get_contents($argv[2]), true);
        if (!is_array($first) || !is_array($rerun) || empty($first["ok"]) || empty($rerun["ok"])) {
            fail_report("Pagewise migration evidence is not valid JSON.");
        }
        if (($first["status"] ?? "") !== "applied" || ($rerun["status"] ?? "") !== "unchanged") {
            fail_report("Pagewise migration did not prove applied-then-unchanged behavior.");
        }
        foreach (["operation", "targetDatabase", "primaryDatabaseGuard", "mappingSha256", "verification"] as $key) {
            if (($first[$key] ?? null) !== ($rerun[$key] ?? null)) {
                fail_report("Pagewise rerun evidence drifted at " . $key . ".");
            }
        }
    ' "$first_report" "$rerun_report"
}

red_adriana_verify_target_database() {
    local stage="${1:-pagewise}" active_theme imported_counts position_counts structure_counts

    active_theme="$(red_adriana_target_mysql --execute="
        SELECT Content
        FROM RED_Advanced
        WHERE Language='' AND Item='System_Active_Theme'
        LIMIT 1;
    ")"
    if [[ "$active_theme" != 'adriana-granobles' ]]; then
        printf 'Disposable active theme is not adriana-granobles: %s\n' "$active_theme" >&2
        return 1
    fi

    if [[ "$stage" == 'prototype' ]]; then
        imported_counts="$(red_adriana_target_mysql --execute="
            SELECT CONCAT_WS(
                ':',
                COUNT(*),
                COALESCE(SUM(Component='Other'), 0),
                COALESCE(SUM(Component='Form'), 0),
                COALESCE(SUM(Component='Other' AND Alias<>''), 0)
            )
            FROM RED_Articles
            WHERE EditedBy='adriana28';
        ")"
        if [[ "$imported_counts" != '29:28:1:27' ]]; then
            printf 'Disposable 28-route prototype inventory is invalid: %s\n' "$imported_counts" >&2
            return 1
        fi
        return 0
    fi
    if [[ "$stage" != 'pagewise' ]]; then
        printf 'Disposable inventory stage is unsupported: %s\n' "$stage" >&2
        return 64
    fi

    imported_counts="$(red_adriana_target_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            COUNT(*),
            COALESCE(SUM(EditedBy='adriana28'), 0),
            COALESCE(SUM(EditedBy='adrianaPW'), 0),
            COALESCE(SUM(EditedBy='adrianaPW' AND Component='Article' AND PagePosition=0 AND LongDesc=''), 0),
            COALESCE(SUM(EditedBy='adrianaPW' AND Component='Other'), 0),
            COALESCE(SUM(EditedBy='adrianaPW' AND Component='Form'), 0),
            COALESCE(SUM(EditedBy='adrianaPW' AND Component='Other' AND LongDesc=''), 0),
            COALESCE(SUM(EditedBy='adrianaPW' AND Component='Other' AND ShortDesc LIKE '%data-redcms-source-section=%'), 0)
        )
        FROM RED_Articles
        WHERE EditedBy IN ('adriana28','adrianaPW');
    ")"
    if [[ "$imported_counts" != '178:0:178:24:153:1:153:153' ]]; then
        printf 'Disposable 28-route pagewise inventory is invalid: %s\n' "$imported_counts" >&2
        return 1
    fi
    structure_counts="$(red_adriana_target_mysql --execute="
        SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Sections WHERE Language='sp' AND Active='Y'
                AND Sections IN ('clases-de-musica','estudio-de-grabacion','voz-y-transformacion')),
            (SELECT COUNT(*) FROM RED_Menu WHERE Language='sp' AND Active='Y'),
            (SELECT COUNT(*) FROM RED_Advanced WHERE Item='Website_Footer' AND Language='sp'
                AND Content NOT LIKE '%.html%')
        );
    ")"
    if [[ "$structure_counts" != '3:28:1' ]]; then
        printf 'Disposable canonical area/navigation inventory is invalid: %s\n' "$structure_counts" >&2
        return 1
    fi
    position_counts="$(red_adriana_target_mysql --execute="
        SELECT GROUP_CONCAT(CONCAT(PagePosition, '=', position_count) ORDER BY PagePosition SEPARATOR ':')
        FROM (
            SELECT PagePosition, COUNT(*) AS position_count
            FROM RED_Articles
            WHERE EditedBy='adrianaPW' AND Component='Other' AND Article='programa-cuda'
            GROUP BY PagePosition
        ) AS cuda_positions;
    ")"
    if [[ "$position_counts" != '1=2:2=2:3=2:4=3:5=2' ]]; then
        printf 'Disposable CUDA five-position distribution is invalid: %s\n' "$position_counts" >&2
        return 1
    fi
}

red_adriana_start_server() {
    local attempt=0 http_status="" response_file="$RUN_ROOT/readiness-home.html"

    : > "$PHP_LOG"
    chmod 600 "$PHP_LOG"
    (
        trap '' HUP
        cd "$WEBROOT"
        export RED_DB_HOST="$RED_DB_HOST_PORT"
        export RED_DB_USER="$RED_DB_USER_RESOLVED"
        export RED_DB_PASS="$RED_DB_PASS_RESOLVED"
        export RED_DB_NAME="$DISPOSABLE_DATABASE"
        export RED_PRIMARY_DB_NAME="$PRIMARY_DATABASE"
        export PORT="$PORT"
        export FRANKENPHP_BIN="$FRANKENPHP_BIN_RESOLVED"
        unset RED_ADRIANA_DB_ADMIN_PASS RED_ACCEPTANCE_DB_ADMIN_PASS
        exec "$WEBROOT/scripts/dev-php-server.sh"
    ) </dev/null >> "$PHP_LOG" 2>&1 &
    PHP_PID=$!
    SERVER_CREATED=1

    while [[ "$attempt" -lt 100 ]]; do
        if ! kill -0 "$PHP_PID" 2>/dev/null; then
            printf '%s\n' 'Disposable PHP server exited before becoming ready:' >&2
            sed -n '1,160p' "$PHP_LOG" >&2
            return 1
        fi
        http_status="$(curl -sS --max-time 1 -o "$response_file" -w '%{http_code}' "http://127.0.0.1:$PORT/" 2>/dev/null || true)"
        if [[ "$http_status" == '200' ]] && grep -Fq 'data-redcms-source-page="home"' "$response_file"; then
            if ! red_adriana_process_matches_state; then
                printf '%s\n' 'Disposable PHP process does not match its expected port and webroot.' >&2
                return 1
            fi
            return 0
        fi
        sleep 0.1
        attempt=$((attempt + 1))
    done

    printf 'Disposable PHP server did not become ready at http://127.0.0.1:%s/.\n' "$PORT" >&2
    sed -n '1,160p' "$PHP_LOG" >&2
    return 1
}

red_adriana_write_state() {
    local temporary_state

    temporary_state="$(mktemp "${STATE_FILE}.tmp.XXXXXX")"
    chmod 600 "$temporary_state"
    {
        printf 'STATE_VERSION=1\n'
        printf 'STATE_MARKER=redcms-adriana-28-runtime\n'
        printf 'DATABASE=%s\n' "$DISPOSABLE_DATABASE"
        printf 'PRIMARY_DATABASE=%s\n' "$PRIMARY_DATABASE"
        printf 'APP_ACCOUNT_USER=%s\n' "$APP_ACCOUNT_USER"
        printf 'APP_ACCOUNT_HOST=%s\n' "$APP_ACCOUNT_HOST"
        printf 'RUN_ROOT=%s\n' "$RUN_ROOT"
        printf 'WEBROOT=%s\n' "$WEBROOT"
        printf 'PHP_LOG=%s\n' "$PHP_LOG"
        printf 'PHP_PID=%s\n' "$PHP_PID"
        printf 'PORT=%s\n' "$PORT"
        printf 'PRIMARY_SNAPSHOT=%s\n' "$PRIMARY_SNAPSHOT_BEFORE"
        printf 'MANIFEST_SHA256=%s\n' "$MANIFEST_SHA256"
        printf 'CREATED_AT=%s\n' "$CREATED_AT"
    } > "$temporary_state"

    if ! ln "$temporary_state" "$STATE_FILE" 2>/dev/null; then
        rm -f -- "$temporary_state"
        printf 'State file appeared concurrently; refusing to overwrite it: %s\n' "$STATE_FILE" >&2
        return 65
    fi
    rm -f -- "$temporary_state"
    STATE_WRITTEN=1
}

red_adriana_load_state() {
    local state_version="" state_marker="" database="" primary_database=""
    local app_account_user="" app_account_host="" run_root="" webroot="" php_log=""
    local php_pid="" state_port="" primary_snapshot="" manifest_sha256="" created_at=""
    local key value metadata

    if [[ ! -f "$STATE_FILE" || -L "$STATE_FILE" ]]; then
        printf 'Disposable runtime state file is missing or unsafe: %s\n' "$STATE_FILE" >&2
        return 66
    fi
    metadata="$(red_adriana_path_metadata "$STATE_FILE")"
    if [[ "$metadata" != "$(id -u):600" ]]; then
        printf 'Disposable runtime state must be owned by this user with mode 600 (got %s).\n' "$metadata" >&2
        return 65
    fi

    while IFS='=' read -r key value; do
        if [[ -z "$key" || -z "$value" ]]; then
            printf '%s\n' 'Disposable runtime state contains an empty key or value.' >&2
            return 65
        fi
        case "$key" in
            STATE_VERSION) [[ -z "$state_version" ]] || return 65; state_version="$value" ;;
            STATE_MARKER) [[ -z "$state_marker" ]] || return 65; state_marker="$value" ;;
            DATABASE) [[ -z "$database" ]] || return 65; database="$value" ;;
            PRIMARY_DATABASE) [[ -z "$primary_database" ]] || return 65; primary_database="$value" ;;
            APP_ACCOUNT_USER) [[ -z "$app_account_user" ]] || return 65; app_account_user="$value" ;;
            APP_ACCOUNT_HOST) [[ -z "$app_account_host" ]] || return 65; app_account_host="$value" ;;
            RUN_ROOT) [[ -z "$run_root" ]] || return 65; run_root="$value" ;;
            WEBROOT) [[ -z "$webroot" ]] || return 65; webroot="$value" ;;
            PHP_LOG) [[ -z "$php_log" ]] || return 65; php_log="$value" ;;
            PHP_PID) [[ -z "$php_pid" ]] || return 65; php_pid="$value" ;;
            PORT) [[ -z "$state_port" ]] || return 65; state_port="$value" ;;
            PRIMARY_SNAPSHOT) [[ -z "$primary_snapshot" ]] || return 65; primary_snapshot="$value" ;;
            MANIFEST_SHA256) [[ -z "$manifest_sha256" ]] || return 65; manifest_sha256="$value" ;;
            CREATED_AT) [[ -z "$created_at" ]] || return 65; created_at="$value" ;;
            *) printf 'Unknown key in disposable runtime state: %s\n' "$key" >&2; return 65 ;;
        esac
    done < "$STATE_FILE"

    if [[ "$state_version" != '1' || "$state_marker" != 'redcms-adriana-28-runtime' ]]; then
        printf '%s\n' 'Disposable runtime state version or marker is invalid.' >&2
        return 65
    fi
    if [[ ! "$primary_database" =~ ^[A-Za-z0-9_]+$ || "$primary_database" != "$PRIMARY_DATABASE" ]]; then
        printf 'Recorded primary database does not match the current protected configuration: %s\n' "$primary_database" >&2
        return 65
    fi
    if [[ ! "$app_account_user" =~ ^[A-Za-z0-9_.-]+$ ]] \
        || [[ ! "$app_account_host" =~ ^[A-Za-z0-9_.:%-]+$ ]]; then
        printf '%s\n' 'Recorded application database account is unsafe.' >&2
        return 65
    fi
    if [[ ! "$php_pid" =~ ^[0-9]+$ || "$php_pid" -le 1 ]] \
        || [[ ! "$state_port" =~ ^[0-9]+$ || "$state_port" -lt 8060 || "$state_port" -gt 65535 ]] \
        || [[ ! "$primary_snapshot" =~ ^[a-f0-9]{64}$ ]] \
        || [[ "$manifest_sha256" != "$APPROVED_MANIFEST_SHA256" ]] \
        || [[ ! "$created_at" =~ ^[0-9]+$ ]]; then
        printf '%s\n' 'Recorded disposable runtime numeric or checksum fields are invalid.' >&2
        return 65
    fi

    DISPOSABLE_DATABASE="$database"
    APP_ACCOUNT_USER="$app_account_user"
    APP_ACCOUNT_HOST="$app_account_host"
    RUN_ROOT="$run_root"
    WEBROOT="$webroot"
    PHP_LOG="$php_log"
    PHP_PID="$php_pid"
    PORT="$state_port"
    PRIMARY_SNAPSHOT_BEFORE="$primary_snapshot"
    MANIFEST_SHA256="$manifest_sha256"
    CREATED_AT="$created_at"

    red_adriana_validate_database_name "$DISPOSABLE_DATABASE"
    red_adriana_validate_run_root
}

red_adriana_replace_state_pid() (
    set -euo pipefail
    local expected_pid="$1"
    local replacement_pid="$2"
    local lock_directory="${STATE_FILE}.lock"
    local temporary_state=""
    local state_line=""
    local replaced_count=0

    if [[ ! "$expected_pid" =~ ^[0-9]+$ || "$expected_pid" -le 1 ]] \
        || [[ ! "$replacement_pid" =~ ^[0-9]+$ || "$replacement_pid" -le 1 ]]; then
        printf '%s\n' 'State PID replacement received an unsafe process identifier.' >&2
        return 64
    fi
    if ! mkdir "$lock_directory" 2>/dev/null; then
        printf 'Disposable state is already locked for an update: %s\n' "$lock_directory" >&2
        return 65
    fi

    red_adriana_state_pid_cleanup() {
        if [[ -n "$temporary_state" && -f "$temporary_state" && ! -L "$temporary_state" ]]; then
            rm -f -- "$temporary_state"
        fi
        rmdir -- "$lock_directory" 2>/dev/null || true
    }
    trap red_adriana_state_pid_cleanup EXIT

    red_adriana_load_state
    if [[ "$PHP_PID" != "$expected_pid" ]]; then
        printf '%s\n' 'Disposable state PID changed concurrently; refusing to overwrite it.' >&2
        return 65
    fi
    if kill -0 "$PHP_PID" 2>/dev/null; then
        printf 'Recorded disposable PID became active during the state update: %s\n' "$PHP_PID" >&2
        return 65
    fi
    if red_adriana_port_in_use "$PORT"; then
        printf 'Recorded disposable port became occupied during the state update: %s\n' "$PORT" >&2
        return 65
    fi

    temporary_state="$(mktemp "${STATE_FILE}.tmp.XXXXXX")"
    chmod 600 "$temporary_state"
    while IFS= read -r state_line || [[ -n "$state_line" ]]; do
        if [[ "$state_line" == PHP_PID=* ]]; then
            if [[ "$state_line" != "PHP_PID=$expected_pid" || "$replaced_count" -ne 0 ]]; then
                printf '%s\n' 'Disposable state PID line is inconsistent with its validated value.' >&2
                return 65
            fi
            printf 'PHP_PID=%s\n' "$replacement_pid" >> "$temporary_state"
            replaced_count=$((replaced_count + 1))
        else
            printf '%s\n' "$state_line" >> "$temporary_state"
        fi
    done < "$STATE_FILE"
    if [[ "$replaced_count" -ne 1 ]]; then
        printf '%s\n' 'Disposable state did not contain exactly one replaceable PID line.' >&2
        return 65
    fi

    mv -f -- "$temporary_state" "$STATE_FILE"
    temporary_state=""
    if [[ "$(red_adriana_path_metadata "$STATE_FILE")" != "$(id -u):600" ]]; then
        printf '%s\n' 'Atomically replaced disposable state does not retain mode 600.' >&2
        return 65
    fi
    red_adriana_load_state
    if [[ "$PHP_PID" != "$replacement_pid" ]]; then
        printf '%s\n' 'Atomically replaced disposable state did not retain the new PID.' >&2
        return 65
    fi
)

red_adriana_database_exists() {
    red_adriana_admin_mysql --execute="
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.SCHEMATA
        WHERE SCHEMA_NAME='$DISPOSABLE_DATABASE';
    "
}

red_adriana_failure_cleanup() {
    local cleanup_database_remaining=""

    set +e
    if [[ "$SERVER_CREATED" -eq 1 && "$PHP_PID" -gt 1 ]]; then
        red_adriana_stop_server >/dev/null 2>&1
    fi
    if [[ "$GRANT_CREATED" -eq 1 && -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        red_adriana_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$DISPOSABLE_DATABASE\`.* FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 && -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        red_adriana_admin_mysql --execute="DROP DATABASE IF EXISTS \`$DISPOSABLE_DATABASE\`;" >/dev/null 2>&1
        cleanup_database_remaining="$(red_adriana_database_exists 2>/dev/null)"
        if [[ "$cleanup_database_remaining" != '0' ]]; then
            printf 'Cleanup warning: disposable database may remain: %s\n' "$DISPOSABLE_DATABASE" >&2
        fi
    fi
    if [[ "$STATE_WRITTEN" -eq 1 && -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then
        rm -f -- "$STATE_FILE"
    fi
    if [[ "$RUN_ROOT_CREATED" -eq 1 && -n "$RUN_ROOT" ]]; then
        red_adriana_safe_remove_run_root >/dev/null 2>&1 || \
            printf 'Cleanup warning: guarded runtime root may remain: %s\n' "$RUN_ROOT" >&2
    fi
}

red_adriana_on_exit() {
    local original_status=$?

    trap - EXIT INT TERM
    if [[ "$OPERATION" == 'create' && "$CREATE_SUCCEEDED" -ne 1 ]]; then
        red_adriana_failure_cleanup
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    red_remove_defaults_file
    exit "$original_status"
}

red_adriana_create() {
    local database_exists app_account manifest_summary primary_snapshot_after
    local dump_file source_config package_self_test_output

    if [[ -e "$STATE_FILE" || -L "$STATE_FILE" ]]; then
        printf 'A disposable runtime state already exists; use status or destroy first: %s\n' "$STATE_FILE" >&2
        return 65
    fi
    if [[ ! "$PRIMARY_DATABASE" =~ ^[A-Za-z0-9_]+$ ]]; then
        printf 'Configured primary database name is unsafe: %s\n' "$PRIMARY_DATABASE" >&2
        return 64
    fi
    if [[ ! -x "$FRANKENPHP_BIN_RESOLVED" ]]; then
        printf 'FrankenPHP CLI is missing or not executable: %s\n' "$FRANKENPHP_BIN_RESOLVED" >&2
        return 66
    fi
    for required_file in \
        "$RED_PROJECT_ROOT/$MANIFEST_RELATIVE_PATH" \
        "$RED_PROJECT_ROOT/scripts/adriana-content-package-self-test.sh" \
        "$RED_PROJECT_ROOT/scripts/theme-preflight.sh" \
        "$RED_PROJECT_ROOT/scripts/adriana-content-activate.php" \
        "$RED_PROJECT_ROOT/scripts/adriana-content-migrate.php" \
        "$RED_PROJECT_ROOT/scripts/adriana-page-content-migrate.php" \
        "$RED_PROJECT_ROOT/content-migrations/adriana-granobles-v4/pages/programa-cuda.json" \
        "$RED_PROJECT_ROOT/scripts/dev-php-server.sh"; do
        if [[ ! -f "$required_file" || -L "$required_file" ]]; then
            printf 'Required disposable migration file is missing or unsafe: %s\n' "$required_file" >&2
            return 66
        fi
    done

    manifest_summary="$(red_adriana_manifest_summary "$RED_PROJECT_ROOT/$MANIFEST_RELATIVE_PATH")"
    if [[ "$manifest_summary" != '28:27:42:adriana-granobles-v4' ]]; then
        printf 'Staged content manifest does not match the fixed 28-route contract: %s\n' "$manifest_summary" >&2
        return 66
    fi
    MANIFEST_SHA256="$(red_sha256_file "$RED_PROJECT_ROOT/$MANIFEST_RELATIVE_PATH")"
    if [[ "$MANIFEST_SHA256" != "$APPROVED_MANIFEST_SHA256" ]]; then
        printf 'Staged manifest SHA-256 is not the approved migration digest (got %s).\n' "$MANIFEST_SHA256" >&2
        return 66
    fi
    printf '%s\n' 'Running the read-only 28-route package self-test before creating any clone resources.'
    package_self_test_output="$(
        RED_PHP_BIN="$RED_PHP_BIN_RESOLVED" \
            "$RED_PROJECT_ROOT/scripts/adriana-content-package-self-test.sh"
    )"
    if [[ "$package_self_test_output" != *"$APPROVED_MANIFEST_SHA256"* ]]; then
        printf '%s\n' 'Package self-test did not report the approved manifest digest.' >&2
        return 66
    fi
    printf '%s\n' "$package_self_test_output"

    DISPOSABLE_DATABASE="${RED_ADRIANA_DATABASE:-redcms_adriana_28_$(date +%Y%m%d_%H%M%S)_$$}"
    red_adriana_validate_database_name "$DISPOSABLE_DATABASE"
    red_adriana_select_port
    red_adriana_create_admin_defaults
    red_adriana_admin_mysql --execute='SELECT 1;' >/dev/null

    app_account="$(red_adriana_primary_mysql --execute='SELECT CURRENT_USER();')"
    APP_ACCOUNT_USER="${app_account%@*}"
    APP_ACCOUNT_HOST="${app_account#*@}"
    if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$ ]] \
        || [[ ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$ ]]; then
        printf 'Application database account is unsafe for a scoped disposable grant: %s\n' "$app_account" >&2
        return 64
    fi

    database_exists="$(red_adriana_database_exists)"
    if [[ "$database_exists" != '0' ]]; then
        printf 'Disposable database already exists; refusing to reuse it: %s\n' "$DISPOSABLE_DATABASE" >&2
        return 65
    fi

    PRIMARY_SNAPSHOT_BEFORE="$(red_adriana_primary_snapshot)"
    if [[ ! "$PRIMARY_SNAPSHOT_BEFORE" =~ ^[a-f0-9]{64}$ ]]; then
        printf '%s\n' 'Could not capture the protected primary-database snapshot.' >&2
        return 67
    fi

    RUN_ROOT="$(mktemp -d "/private/tmp/redcms-adriana-28-runtime.XXXXXX")"
    chmod 700 "$RUN_ROOT"
    RUN_ROOT_CREATED=1
    WEBROOT="$RUN_ROOT/webroot"
    PHP_LOG="$RUN_ROOT/php.log"
    CREATED_AT="$(date +%s)"
    {
        printf '%s\n' 'redcms-adriana-28-runtime-v1'
        printf 'database=%s\n' "$DISPOSABLE_DATABASE"
    } > "$RUN_ROOT/$RUNTIME_MARKER_NAME"
    chmod 600 "$RUN_ROOT/$RUNTIME_MARKER_NAME"
    mkdir "$WEBROOT"
    chmod 700 "$WEBROOT"
    printf '%s\n' "$package_self_test_output" > "$RUN_ROOT/package-self-test.json"
    chmod 600 "$RUN_ROOT/package-self-test.json"

    dump_file="$RUN_ROOT/primary.sql"
    printf 'Backing up protected primary database %s for an isolated clone.\n' "$PRIMARY_DATABASE"
    RED_DB_HOST="$RED_DB_HOST_PORT" \
    RED_DB_USER="$RED_DB_USER_RESOLVED" \
    RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
    RED_DB_NAME="$PRIMARY_DATABASE" \
    RED_DB_OFFLINE_CONFIRMED=0 \
        "$SCRIPT_DIR/db-backup.sh" "$dump_file"

    printf 'Creating isolated database %s.\n' "$DISPOSABLE_DATABASE"
    red_adriana_admin_mysql --execute="
        CREATE DATABASE \`$DISPOSABLE_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    "
    DATABASE_CREATED=1
    red_adriana_admin_mysql --execute="
        GRANT ALL PRIVILEGES ON \`$DISPOSABLE_DATABASE\`.* TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
    "
    GRANT_CREATED=1

    RED_DB_HOST="$RED_DB_HOST_PORT" \
    RED_DB_USER="$RED_DB_USER_RESOLVED" \
    RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
    RED_DB_NAME="$PRIMARY_DATABASE" \
        "$SCRIPT_DIR/db-restore.sh" "$dump_file" "$DISPOSABLE_DATABASE"
    rm -f -- "$dump_file" "${dump_file}.sha256"

    printf '%s\n' 'Creating the mode-0700 disposable webroot.'
    /usr/bin/rsync -a \
        --exclude='.git/' \
        --exclude="$PUBLIC_MEDIA_RELATIVE_PATH/" \
        "$RED_PROJECT_ROOT/" "$WEBROOT/"
    chmod 700 "$WEBROOT"
    if [[ -e "$WEBROOT/.git" || -L "$WEBROOT/.git" ]]; then
        printf '%s\n' 'Disposable webroot copy unexpectedly contains repository metadata.' >&2
        return 65
    fi
    source_config="$WEBROOT/includes/config.local.php"
    if [[ -e "$source_config" ]]; then
        if [[ ! -f "$source_config" || -L "$source_config" ]]; then
            printf 'Copied local configuration is unsafe: %s\n' "$source_config" >&2
            return 65
        fi
        chmod 600 "$source_config"
    fi
    red_adriana_copy_and_verify_media

    printf '%s\n' 'Running read-only Adriana theme preflight against the clone.'
    red_adriana_run_preflight > "$RUN_ROOT/theme-preflight.json"
    printf '%s\n' 'Activating Adriana only in the clone.'
    red_adriana_run_clone_php "$WEBROOT/scripts/adriana-content-activate.php" > "$RUN_ROOT/theme-activation.json"
    printf '%s\n' 'Migrating the deterministic 28-route package only into the clone.'
    red_adriana_run_clone_php "$WEBROOT/scripts/adriana-content-migrate.php" > "$RUN_ROOT/content-migration.json"
    red_adriana_verify_target_database prototype
    printf '%s\n' 'Re-running the importer to prove the verified package is a no-op.'
    red_adriana_run_clone_php "$WEBROOT/scripts/adriana-content-migrate.php" > "$RUN_ROOT/content-migration-rerun.json"
    red_adriana_verify_migration_rerun \
        "$RUN_ROOT/content-migration.json" \
        "$RUN_ROOT/content-migration-rerun.json"
    printf '%s\n' 'Validating the complete 28-route pagewise site package in the clone.'
    red_adriana_run_clone_php \
        "$WEBROOT/scripts/adriana-page-content-migrate.php" \
        --package-only > "$RUN_ROOT/pagewise-site-package-self-test.json"
    printf '%s\n' 'Distributing all 153 source sections into editable RED-CMS positions.'
    red_adriana_run_clone_php \
        "$WEBROOT/scripts/adriana-page-content-migrate.php" > "$RUN_ROOT/pagewise-site-migration.json"
    red_adriana_verify_target_database pagewise
    printf '%s\n' 'Re-running the complete pagewise importer to prove it is a no-op.'
    red_adriana_run_clone_php \
        "$WEBROOT/scripts/adriana-page-content-migrate.php" > "$RUN_ROOT/pagewise-site-migration-rerun.json"
    red_adriana_verify_pagewise_rerun \
        "$RUN_ROOT/pagewise-site-migration.json" \
        "$RUN_ROOT/pagewise-site-migration-rerun.json"
    printf '%s\n' 'Running the final live theme preflight against the imported clone.'
    red_adriana_run_preflight > "$RUN_ROOT/theme-preflight-after-import.json"
    red_adriana_verify_target_database

    primary_snapshot_after="$(red_adriana_primary_snapshot)"
    if [[ "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        printf '%s\n' 'Primary-database isolation check failed after clone migration.' >&2
        return 1
    fi

    printf 'Starting isolated PHP on http://127.0.0.1:%s/.\n' "$PORT"
    red_adriana_start_server
    red_adriana_verify_public_media
    primary_snapshot_after="$(red_adriana_primary_snapshot)"
    if [[ "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        printf '%s\n' 'Primary-database isolation check failed after runtime startup.' >&2
        return 1
    fi

    red_adriana_write_state
    CREATE_SUCCEEDED=1

    printf '%s\n' 'Disposable Adriana migration runtime is ready and remains running.'
    printf '  URL: http://127.0.0.1:%s/\n' "$PORT"
    printf '  Database: %s\n' "$DISPOSABLE_DATABASE"
    printf '  Webroot: %s\n' "$WEBROOT"
    printf '  PHP PID: %s\n' "$PHP_PID"
    printf '  PHP log: %s\n' "$PHP_LOG"
    printf '  Migration evidence: %s\n' "$RUN_ROOT/content-migration.json"
    printf '  No-op rerun evidence: %s\n' "$RUN_ROOT/content-migration-rerun.json"
    if [[ -f "$RUN_ROOT/pagewise-site-package-self-test.json" ]]; then
        printf '  Pagewise site package self-test: %s\n' "$RUN_ROOT/pagewise-site-package-self-test.json"
    fi
    if [[ -f "$RUN_ROOT/pagewise-site-migration.json" ]]; then
        printf '  Pagewise site migration evidence: %s\n' "$RUN_ROOT/pagewise-site-migration.json"
    fi
    if [[ -f "$RUN_ROOT/pagewise-site-migration-rerun.json" ]]; then
        printf '  Pagewise site no-op rerun evidence: %s\n' "$RUN_ROOT/pagewise-site-migration-rerun.json"
    fi
    printf '  Final preflight: %s\n' "$RUN_ROOT/theme-preflight-after-import.json"
    printf '  State: %s\n' "$STATE_FILE"
}

red_adriana_status() {
    local failures=0 database_exists active_account grant_output
    local primary_snapshot_after http_status response_file

    red_adriana_load_state
    red_adriana_create_admin_defaults

    database_exists="$(red_adriana_database_exists)"
    if [[ "$database_exists" != '1' ]]; then
        printf 'FAIL: disposable database is not present exactly once: %s\n' "$DISPOSABLE_DATABASE" >&2
        failures=$((failures + 1))
    elif ! red_adriana_verify_target_database; then
        printf '%s\n' 'FAIL: disposable database content contract did not verify.' >&2
        failures=$((failures + 1))
    else
        printf '%s\n' 'PASS: all 28 routes and 153 source sections use the pagewise editable model.'
    fi

    active_account="$(red_adriana_primary_mysql --execute='SELECT CURRENT_USER();' 2>/dev/null || true)"
    if [[ "$active_account" != "$APP_ACCOUNT_USER@$APP_ACCOUNT_HOST" ]]; then
        printf 'FAIL: configured application database account changed (got %s).\n' "$active_account" >&2
        failures=$((failures + 1))
    fi
    if ! grant_output="$(red_adriana_admin_mysql --execute="SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';" 2>/dev/null)"; then
        printf '%s\n' 'FAIL: recorded application account grants could not be read.' >&2
        failures=$((failures + 1))
        grant_output=""
    elif [[ "$grant_output" != *"\`$DISPOSABLE_DATABASE\`.*"* ]]; then
        printf '%s\n' 'FAIL: exact disposable database grant is missing.' >&2
        failures=$((failures + 1))
    else
        printf '%s\n' 'PASS: exact disposable database grant is present.'
    fi

    if ! red_adriana_process_matches_state; then
        printf '%s\n' 'FAIL: recorded PHP PID does not match the disposable port and webroot.' >&2
        failures=$((failures + 1))
    elif ! red_adriana_port_in_use "$PORT"; then
        printf 'FAIL: disposable port is not listening: %s\n' "$PORT" >&2
        failures=$((failures + 1))
    else
        printf 'PASS: isolated PHP process %s owns the recorded runtime on port %s.\n' "$PHP_PID" "$PORT"
    fi

    response_file="$(mktemp "/private/tmp/redcms-adriana-28-status-response.XXXXXX")"
    http_status="$(curl -sS --max-time 3 -o "$response_file" -w '%{http_code}' "http://127.0.0.1:$PORT/" 2>/dev/null || true)"
    if [[ "$http_status" != '200' ]] || ! grep -Fq 'data-redcms-source-page="home"' "$response_file"; then
        printf 'FAIL: disposable home route is not ready (HTTP %s).\n' "$http_status" >&2
        failures=$((failures + 1))
    else
        printf '%s\n' 'PASS: disposable home route returns HTTP 200 with its source-page marker.'
    fi
    rm -f -- "$response_file"

    if red_adriana_verify_public_media; then
        printf '%s\n' 'PASS: all 42 disposable public media files match the manifest.'
    else
        printf '%s\n' 'FAIL: disposable public media verification failed.' >&2
        failures=$((failures + 1))
    fi

    primary_snapshot_after="$(red_adriana_primary_snapshot 2>/dev/null || true)"
    if [[ "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        printf '%s\n' 'FAIL: protected primary-database snapshot differs from the creation baseline.' >&2
        failures=$((failures + 1))
    else
        printf 'PASS: protected primary database remains isolated (%s).\n' "$PRIMARY_DATABASE"
    fi

    printf '  URL: http://127.0.0.1:%s/\n' "$PORT"
    printf '  Database: %s\n' "$DISPOSABLE_DATABASE"
    printf '  Run root: %s\n' "$RUN_ROOT"
    printf '  Webroot: %s\n' "$WEBROOT"
    printf '  Package self-test: %s\n' "$RUN_ROOT/package-self-test.json"
    printf '  Initial preflight: %s\n' "$RUN_ROOT/theme-preflight.json"
    printf '  Theme activation: %s\n' "$RUN_ROOT/theme-activation.json"
    printf '  Migration evidence: %s\n' "$RUN_ROOT/content-migration.json"
    printf '  No-op rerun evidence: %s\n' "$RUN_ROOT/content-migration-rerun.json"
    if [[ -f "$RUN_ROOT/pagewise-site-package-self-test.json" ]]; then
        printf '  Pagewise site package self-test: %s\n' "$RUN_ROOT/pagewise-site-package-self-test.json"
    fi
    if [[ -f "$RUN_ROOT/pagewise-site-migration.json" ]]; then
        printf '  Pagewise site migration evidence: %s\n' "$RUN_ROOT/pagewise-site-migration.json"
    fi
    if [[ -f "$RUN_ROOT/pagewise-site-migration-rerun.json" ]]; then
        printf '  Pagewise site no-op rerun evidence: %s\n' "$RUN_ROOT/pagewise-site-migration-rerun.json"
    fi
    printf '  Final preflight: %s\n' "$RUN_ROOT/theme-preflight-after-import.json"
    printf '  Media ledger: %s\n' "$RUN_ROOT/media-ledger.tsv"
    printf '  Readiness response: %s\n' "$RUN_ROOT/readiness-home.html"
    printf '  PHP log: %s\n' "$PHP_LOG"
    printf '  State: %s\n' "$STATE_FILE"

    [[ "$failures" -eq 0 ]]
}

red_adriana_serve() {
    local recorded_pid database_exists primary_snapshot_after

    red_adriana_load_state
    recorded_pid="$PHP_PID"

    if kill -0 "$recorded_pid" 2>/dev/null; then
        if red_adriana_process_matches_state; then
            printf 'Serve refused: the recorded disposable server is already running as PID %s.\n' "$recorded_pid" >&2
        else
            printf 'Serve refused: recorded PID %s is alive but does not match the disposable runtime.\n' "$recorded_pid" >&2
        fi
        return 65
    fi
    if red_adriana_port_in_use "$PORT"; then
        printf 'Serve refused: recorded disposable port %s is already in use.\n' "$PORT" >&2
        return 65
    fi
    if [[ ! -x "$FRANKENPHP_BIN_RESOLVED" ]]; then
        printf 'Serve refused: FrankenPHP is missing or not executable: %s\n' "$FRANKENPHP_BIN_RESOLVED" >&2
        return 66
    fi
    if [[ ! -f "$WEBROOT/scripts/dev-php-server.sh" \
        || -L "$WEBROOT/scripts/dev-php-server.sh" \
        || ! -x "$WEBROOT/scripts/dev-php-server.sh" ]]; then
        printf '%s\n' 'Serve refused: disposable PHP launcher is missing, unsafe, or not executable.' >&2
        return 66
    fi
    if [[ ! -f "$PHP_LOG" || -L "$PHP_LOG" ]] \
        || [[ "$(red_adriana_path_metadata "$PHP_LOG")" != "$(id -u):600" ]]; then
        printf '%s\n' 'Serve refused: disposable PHP log is missing or unsafe.' >&2
        return 65
    fi

    red_adriana_create_admin_defaults
    database_exists="$(red_adriana_database_exists)"
    if [[ "$database_exists" != '1' ]]; then
        printf 'Serve refused: disposable database is not present exactly once: %s\n' "$DISPOSABLE_DATABASE" >&2
        return 66
    fi
    red_adriana_verify_target_database
    red_adriana_verify_public_media
    primary_snapshot_after="$(red_adriana_primary_snapshot)"
    if [[ "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        printf '%s\n' 'Serve refused: protected primary-database snapshot differs from the creation baseline.' >&2
        return 65
    fi

    rm -f -- "$ADMIN_DEFAULTS_FILE"
    ADMIN_DEFAULTS_FILE=""
    red_remove_defaults_file
    RED_DB_DEFAULTS_FILE=""
    unset RED_ADRIANA_DB_ADMIN_PASS RED_ACCEPTANCE_DB_ADMIN_PASS

    red_adriana_replace_state_pid "$recorded_pid" "$$"
    PHP_PID="$$"
    printf 'Serving the validated disposable runtime in the foreground at http://127.0.0.1:%s/ (PID %s).\n' \
        "$PORT" "$PHP_PID"

    trap - EXIT INT TERM
    cd "$WEBROOT"
    export RED_DB_HOST="$RED_DB_HOST_PORT"
    export RED_DB_USER="$RED_DB_USER_RESOLVED"
    export RED_DB_PASS="$RED_DB_PASS_RESOLVED"
    export RED_DB_NAME="$DISPOSABLE_DATABASE"
    export RED_PRIMARY_DB_NAME="$PRIMARY_DATABASE"
    export PORT="$PORT"
    export FRANKENPHP_BIN="$FRANKENPHP_BIN_RESOLVED"
    exec "$WEBROOT/scripts/dev-php-server.sh" >> "$PHP_LOG" 2>&1
}

red_adriana_destroy() {
    local database_exists grant_output primary_snapshot_after

    red_adriana_load_state
    red_adriana_create_admin_defaults

    if kill -0 "$PHP_PID" 2>/dev/null && ! red_adriana_process_matches_state; then
        printf '%s\n' 'Destroy refused: recorded PID is alive but does not match the disposable port and webroot.' >&2
        return 65
    fi

    if ! red_adriana_stop_server; then
        printf '%s\n' 'Destroy stopped before database cleanup because the exact disposable PHP process could not be stopped.' >&2
        return 1
    fi
    if red_adriana_port_in_use "$PORT"; then
        printf 'Destroy stopped because disposable port %s is still listening.\n' "$PORT" >&2
        return 1
    fi

    if ! grant_output="$(red_adriana_admin_mysql --execute="SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';" 2>/dev/null)"; then
        printf '%s\n' 'Destroy stopped because the recorded application account grants could not be read.' >&2
        return 1
    fi
    if [[ "$grant_output" == *"\`$DISPOSABLE_DATABASE\`.*"* ]]; then
        if ! red_adriana_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$DISPOSABLE_DATABASE\`.* FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        "; then
            printf '%s\n' 'Destroy stopped because the exact disposable grant could not be revoked.' >&2
            return 1
        fi
    fi

    database_exists="$(red_adriana_database_exists)"
    if [[ "$database_exists" == '1' ]]; then
        if ! red_adriana_admin_mysql --execute="DROP DATABASE \`$DISPOSABLE_DATABASE\`;"; then
            printf 'Destroy stopped because disposable database %s could not be dropped.\n' "$DISPOSABLE_DATABASE" >&2
            return 1
        fi
    elif [[ "$database_exists" != '0' ]]; then
        printf 'Destroy stopped because database existence was ambiguous: %s\n' "$database_exists" >&2
        return 1
    fi

    if [[ "$(red_adriana_database_exists)" != '0' ]]; then
        printf 'Destroy verification found disposable database still present: %s\n' "$DISPOSABLE_DATABASE" >&2
        return 1
    fi
    if ! grant_output="$(red_adriana_admin_mysql --execute="SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';" 2>/dev/null)"; then
        printf '%s\n' 'Destroy verification could not re-read the recorded application account grants.' >&2
        return 1
    fi
    if [[ "$grant_output" == *"\`$DISPOSABLE_DATABASE\`.*"* ]]; then
        printf '%s\n' 'Destroy verification found the disposable grant still present.' >&2
        return 1
    fi

    primary_snapshot_after="$(red_adriana_primary_snapshot 2>/dev/null || true)"
    if [[ "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        printf '%s\n' 'WARNING: protected primary snapshot differs from the creation baseline; exact disposable cleanup will continue.' >&2
    fi

    if ! red_adriana_safe_remove_run_root; then
        printf 'Destroy could not remove the exact guarded runtime root: %s\n' "$RUN_ROOT" >&2
        return 1
    fi
    if [[ ! -f "$STATE_FILE" || -L "$STATE_FILE" ]]; then
        printf 'Destroy refused to remove an unsafe state path: %s\n' "$STATE_FILE" >&2
        return 65
    fi
    rm -f -- "$STATE_FILE"
    if [[ -e "$STATE_FILE" || -L "$STATE_FILE" ]]; then
        printf 'Destroy could not remove disposable state: %s\n' "$STATE_FILE" >&2
        return 1
    fi

    printf 'Destroyed exact disposable runtime %s on former port %s.\n' "$DISPOSABLE_DATABASE" "$PORT"
    printf '%s\n' 'Primary MySQL and the primary PHP runtime on port 8055 were not stopped.'
}

if [[ $# -ne 1 ]]; then
    red_adriana_usage >&2
    exit 64
fi

OPERATION="$1"
red_adriana_validate_state_path
trap red_adriana_on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

case "$OPERATION" in
    create) red_adriana_create ;;
    serve) red_adriana_serve ;;
    status) red_adriana_status ;;
    destroy) red_adriana_destroy ;;
    --help|-h|help) red_adriana_usage ;;
    *) red_adriana_usage >&2; exit 64 ;;
esac
