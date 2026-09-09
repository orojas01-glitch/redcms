#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_CLI="${RED_PHP_BIN:-${PHP_CLI:-php}}"
# Match the dependency-free pre-database gate in the full suite.
while IFS= read -r test; do
    "$PHP_CLI" "$SCRIPT_DIR/$test"
done < <(sed -n '/^FRANKENPHP_BIN=/q; s/^"$RED_PHP_BIN_RESOLVED" "$SCRIPT_DIR\/\([^"]*\)"$/\1/p' "$SCRIPT_DIR/dev-acceptance.sh")
for test in theme-contract theme-layout-extensibility theme-preflight theme-readiness theme-region-context; do
    "$PHP_CLI" "$SCRIPT_DIR/$test-self-test.php"
done
