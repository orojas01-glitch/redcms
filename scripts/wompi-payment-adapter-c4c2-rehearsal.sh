#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $# -gt 0 ]]; then
    if [[ $# -eq 1 && "$1" == '--help' ]]; then
        printf 'Usage: %s\n' "$0"
        printf '%s\n' 'Runs C4C2 dry-run/refusal/sealed-double apply in a disposable network-disabled environment.'
        exit 0
    fi
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi

RED_WOMPI_REVISION='cc2ddd03ab54f663a089f7d059d802180e555d15' \
RED_WOMPI_VERSION='0.1.5' \
RED_STORE_LITE_REVISION='56727d2de0bbd2c476316f62001a429b354c599f' \
RED_STORE_LITE_VERSION='0.1.50' \
RED_WOMPI_C3B_DATABASE="redcms_payment_adapter_db_c4c2_$$" \
RED_WOMPI_SELF_TEST_SCRIPT='wompi-payment-adapter-c4c1-self-test.php' \
RED_WOMPI_AFTER_SELF_TEST_SCRIPT='wompi-payment-adapter-c4c2-operator-inner.sh' \
    "$SCRIPT_DIR/wompi-payment-adapter-c3b-rehearsal.sh"

printf '%s\n' 'Wompi C4C2 merchant-read operator rehearsal passed.'
