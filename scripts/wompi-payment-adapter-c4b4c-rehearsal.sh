#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        printf 'Usage: %s\n' "$0"
        printf '%s\n' 'Runs exact Wompi C4B4C sealed transport-double rehearsal.'
        exit 0
    fi
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi

RED_WOMPI_C3B_DATABASE="redcms_payment_adapter_db_c4b4c_$$" \
RED_WOMPI_SELF_TEST_SCRIPT='wompi-payment-adapter-c4b4c-self-test.php' \
    "$SCRIPT_DIR/wompi-payment-adapter-c3b-rehearsal.sh"

printf '%s\n' 'Wompi C4B4C sealed transport-double rehearsal passed.'
