#!/bin/bash

set -euo pipefail

RED_PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RED_LOCAL_CONFIG="${RED_LOCAL_CONFIG:-$RED_PROJECT_ROOT/includes/config.local.php}"

red_find_php() {
    if [[ -n "${RED_PHP_BIN:-}" && -x "${RED_PHP_BIN}" ]]; then
        printf '%s' "$RED_PHP_BIN"
    elif command -v php >/dev/null 2>&1; then
        command -v php
    elif [[ -x "/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php" ]]; then
        printf '%s' "/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php"
    else
        printf '%s\n' "PHP CLI was not found. Set RED_PHP_BIN." >&2
        return 1
    fi
}

RED_PHP_BIN_RESOLVED="$(red_find_php)"

red_config_value() {
    local key="$1"
    local environment_value=""

    case "$key" in
        DBHOST) environment_value="${RED_DB_HOST:-}" ;;
        DBUSER) environment_value="${RED_DB_USER:-}" ;;
        DBPASS) environment_value="${RED_DB_PASS:-}" ;;
        DBNAME) environment_value="${RED_DB_NAME:-}" ;;
    esac

    if [[ -n "$environment_value" ]]; then
        printf '%s' "$environment_value"
        return
    fi

    if [[ ! -f "$RED_LOCAL_CONFIG" ]]; then
        printf '%s\n' "Database configuration was not found. Set RED_DB_HOST, RED_DB_USER, RED_DB_PASS, and RED_DB_NAME." >&2
        return 1
    fi

    "$RED_PHP_BIN_RESOLVED" -r '$config = require $argv[1]; echo isset($config[$argv[2]]) ? $config[$argv[2]] : "";' "$RED_LOCAL_CONFIG" "$key"
}

RED_DB_HOST_PORT="$(red_config_value DBHOST)"
RED_DB_USER_RESOLVED="$(red_config_value DBUSER)"
RED_DB_PASS_RESOLVED="$(red_config_value DBPASS)"
RED_DB_NAME_RESOLVED="$(red_config_value DBNAME)"
RED_DB_HOST_RESOLVED="$RED_DB_HOST_PORT"
RED_DB_PORT_RESOLVED="${RED_DB_PORT:-3306}"

if [[ "$RED_DB_HOST_PORT" == *:* ]]; then
    RED_DB_HOST_RESOLVED="${RED_DB_HOST_PORT%:*}"
    RED_DB_PORT_RESOLVED="${RED_DB_HOST_PORT##*:}"
fi

red_find_mysql_tool() {
    local tool="$1"
    local configured_dir="${RED_MYSQL_BIN_DIR:-}"
    local local_dir="/Users/oscarrojas/Documents/red-cms-dev/mysql-8.4.10-macos15-arm64/bin"

    if [[ -n "$configured_dir" && -x "$configured_dir/$tool" ]]; then
        printf '%s' "$configured_dir/$tool"
    elif command -v "$tool" >/dev/null 2>&1; then
        command -v "$tool"
    elif [[ -x "$local_dir/$tool" ]]; then
        printf '%s' "$local_dir/$tool"
    else
        printf '%s\n' "$tool was not found. Set RED_MYSQL_BIN_DIR." >&2
        return 1
    fi
}

red_sha256_file() {
    local file="$1"

    if command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$file" | awk '{print $1}'
    elif command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$file" | awk '{print $1}'
    elif command -v openssl >/dev/null 2>&1; then
        openssl dgst -sha256 "$file" | awk '{print $NF}'
    else
        printf '%s\n' 'No SHA-256 command was found (shasum, sha256sum, or openssl).' >&2
        return 1
    fi
}

RED_MYSQL_BIN="$(red_find_mysql_tool mysql)"
RED_MYSQLDUMP_BIN="$(red_find_mysql_tool mysqldump)"
RED_DB_DEFAULTS_FILE=""

red_create_defaults_file() {
    RED_DB_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-mysql.XXXXXX")"
    chmod 600 "$RED_DB_DEFAULTS_FILE"
    {
        printf '[client]\n'
        printf 'protocol=tcp\n'
        printf 'host=%s\n' "$RED_DB_HOST_RESOLVED"
        printf 'port=%s\n' "$RED_DB_PORT_RESOLVED"
        printf 'user=%s\n' "$RED_DB_USER_RESOLVED"
        printf 'password=%s\n' "$RED_DB_PASS_RESOLVED"
        printf 'default-character-set=utf8mb4\n'
    } > "$RED_DB_DEFAULTS_FILE"
}

red_remove_defaults_file() {
    if [[ -n "$RED_DB_DEFAULTS_FILE" && -f "$RED_DB_DEFAULTS_FILE" ]]; then
        rm -f "$RED_DB_DEFAULTS_FILE"
    fi
}

trap red_remove_defaults_file EXIT
red_create_defaults_file
