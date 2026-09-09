#!/bin/bash
# Fresh current-schema reference avoids assuming the operator's primary is current.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=db-common.sh
source "$SCRIPT_DIR/db-common.sh"
[[ "$RED_DB_HOST_RESOLVED" == 127.0.0.1 || "$RED_DB_HOST_RESOLVED" == localhost ]] || exit 64
BASELINE="redcms_ci_base_$(date +%Y%m%d_%H%M%S)_$$"
ADMIN_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-ci-admin.XXXXXX")"
chmod 600 "$ADMIN_FILE"
{
    printf '[client]\nprotocol=tcp\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
        "$RED_DB_HOST_RESOLVED" "$RED_DB_PORT_RESOLVED" \
        "${RED_ACCEPTANCE_DB_ADMIN_USER:-root}" "${RED_ACCEPTANCE_DB_ADMIN_PASS:-}"
} > "$ADMIN_FILE"
admin_mysql() { "$RED_MYSQL_BIN" "--defaults-extra-file=$ADMIN_FILE" --batch --skip-column-names "$@"; }
CREATED=0
GRANTED=0
cleanup() {
    local result=$?
    trap - EXIT
    if [[ "$GRANTED" == 1 ]]; then
        admin_mysql --execute="REVOKE ALL PRIVILEGES ON \`$BASELINE\`.* FROM '$APP_USER'@'$APP_HOST';" || result=1
    fi
    if [[ "$CREATED" == 1 ]]; then
        admin_mysql --execute="DROP DATABASE \`$BASELINE\`;" || result=1
        [[ "$(admin_mysql --execute="SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$BASELINE';")" == 0 ]] || result=1
    fi
    rm -f "$ADMIN_FILE"
    red_remove_defaults_file
    printf 'Fresh baseline cleanup completed; exit=%s\n' "$result"
    exit "$result"
}
trap cleanup EXIT
APP_ACCOUNT="$("$RED_MYSQL_BIN" "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" --batch --skip-column-names --execute='SELECT CURRENT_USER();')"
APP_USER="${APP_ACCOUNT%@*}"
APP_HOST="${APP_ACCOUNT#*@}"
[[ "$APP_USER" =~ ^[A-Za-z0-9_.-]+$ && "$APP_HOST" =~ ^[A-Za-z0-9_.:%-]+$ ]] || exit 64
admin_mysql --execute="CREATE DATABASE \`$BASELINE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
CREATED=1
admin_mysql --execute="GRANT ALL PRIVILEGES ON \`$BASELINE\`.* TO '$APP_USER'@'$APP_HOST';"
GRANTED=1
"$RED_MYSQL_BIN" "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" "$BASELINE" < "$RED_PROJECT_ROOT/db-structure.sql"
"$SCRIPT_DIR/db-migrate.sh" "--database=$BASELINE"
RED_DB_NAME="$BASELINE" "$SCRIPT_DIR/dev-acceptance.sh"
