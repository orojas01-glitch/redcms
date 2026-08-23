#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        printf 'Usage: %s\n' "$0"
        printf '%s\n' 'Runs the exact Wompi C3C1 atomic-enablement rehearsal.'
        exit 0
    fi
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi

RED_WOMPI_C3B_DATABASE="redcms_payment_adapter_db_c1_$$" \
RED_WOMPI_BEFORE_SELF_TEST_SCRIPT='addon-payment-adapter-enable-self-test.php' \
RED_WOMPI_SELF_TEST_SCRIPT='wompi-payment-adapter-c3c1-self-test.php' \
    "$SCRIPT_DIR/wompi-payment-adapter-c3b-rehearsal.sh"

printf '%s\n' 'Wompi C3C1 exact atomic-enablement rehearsal passed.'
