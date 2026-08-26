#!/bin/bash

set -euo pipefail

CORE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STORE_ROOT="${RED_STORE_LITE_ROOT:-$(dirname "$CORE_ROOT")/redcms-store-lite}"
STRIPE_ROOT="${RED_STRIPE_ADAPTER_ROOT:-$(dirname "$CORE_ROOT")/redcms-store-lite-stripe-checkout}"
PHP_BIN="${RED_PHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php}"
LAUNCH_RUNNER="$CORE_ROOT/scripts/subscription-checkout-launch-rehearsal.sh"

MODE='dry-run'
AUTHORIZATION_SHA256=''
MAXIMUM_ATTEMPTS=''
PROVIDER_CONTACT=''
CHECKOUT_CREATION=''
SUBSCRIPTION_CREATION=''
PAYMENT=''
WEBHOOK_ACTIVATION=''
BROWSER_NAVIGATION=''
RETRY=''
LIVE_MODE=''
DEMO_DEPLOYMENT=''
restricted_key=''
secret_json=''

red_subscription_owner_attempt_scrub() {
    restricted_key=''
    secret_json=''
    unset restricted_key secret_json
}

red_subscription_owner_attempt_usage() {
    printf '%s\n' \
        "Usage:" \
        "  $0 --dry-run" \
        "  $0 --apply --authorization-sha256=<sha256> \\" \
        "    --maximum-attempts=1 --provider-contact=yes \\" \
        "    --checkout-creation=yes --subscription-creation=yes \\" \
        "    --payment=no --webhook-activation=no \\" \
        "    --browser-navigation=no --retry=no --live-mode=no \\" \
        "    --demo-deployment=no"
}

red_subscription_owner_attempt_offline_gate() {
    "$PHP_BIN" -l \
        "$CORE_ROOT/scripts/subscription-checkout-launch-rehearsal.php" \
        >/dev/null
    "$PHP_BIN" \
        "$CORE_ROOT/scripts/addon-subscription-checkout-provider-operation-self-test.php"
    PHP_CLI="$PHP_BIN" "$STRIPE_ROOT/scripts/test.sh"
    env \
        -u RED_ADDON_SECRET_REFERENCES \
        -u RED_ADDON_SECRET_VALUES_JSON \
        -u RED_SUBSCRIPTION_LAUNCH_MODE \
        -u RED_SUBSCRIPTION_REAL_ATTEMPT_CONFIRMED \
        -u RED_SUBSCRIPTION_OWNER_AUTHORIZATION_SHA256 \
        "$LAUNCH_RUNNER"
}

trap red_subscription_owner_attempt_scrub EXIT INT TERM

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            MODE='dry-run'
            ;;
        --apply)
            MODE='apply'
            ;;
        --authorization-sha256=*)
            AUTHORIZATION_SHA256="${1#*=}"
            ;;
        --maximum-attempts=*)
            MAXIMUM_ATTEMPTS="${1#*=}"
            ;;
        --provider-contact=*)
            PROVIDER_CONTACT="${1#*=}"
            ;;
        --checkout-creation=*)
            CHECKOUT_CREATION="${1#*=}"
            ;;
        --subscription-creation=*)
            SUBSCRIPTION_CREATION="${1#*=}"
            ;;
        --payment=*)
            PAYMENT="${1#*=}"
            ;;
        --webhook-activation=*)
            WEBHOOK_ACTIVATION="${1#*=}"
            ;;
        --browser-navigation=*)
            BROWSER_NAVIGATION="${1#*=}"
            ;;
        --retry=*)
            RETRY="${1#*=}"
            ;;
        --live-mode=*)
            LIVE_MODE="${1#*=}"
            ;;
        --demo-deployment=*)
            DEMO_DEPLOYMENT="${1#*=}"
            ;;
        -h|--help)
            red_subscription_owner_attempt_usage
            exit 0
            ;;
        *)
            red_subscription_owner_attempt_usage >&2
            exit 64
            ;;
    esac
    shift
done

if [[ ! -x "$PHP_BIN"
    || ! -x "$LAUNCH_RUNNER"
    || ! -x "$STRIPE_ROOT/scripts/test.sh"
    || ! -s "$STORE_ROOT/package/addon.json"
    || ! -s "$STRIPE_ROOT/package/addon.json"
]]; then
    printf '%s\n' 'Owner-attempt prerequisites are unavailable.' >&2
    exit 66
fi

if [[ "$MODE" == 'dry-run' ]]; then
    red_subscription_owner_attempt_offline_gate
    printf '%s\n' \
        '{"ok":true,"mode":"dry-run","networkAccess":false,"providerContact":false,"secretPrompt":false,"checkoutCreation":false,"subscriptionCreation":false,"payment":false,"webhookActivation":false,"browserNavigation":false,"retry":false,"liveMode":false,"demoDeployment":false}'
    exit 0
fi

if [[ ! "$AUTHORIZATION_SHA256" =~ ^[a-f0-9]{64}$
    || "$MAXIMUM_ATTEMPTS" != '1'
    || "$PROVIDER_CONTACT" != 'yes'
    || "$CHECKOUT_CREATION" != 'yes'
    || "$SUBSCRIPTION_CREATION" != 'yes'
    || "$PAYMENT" != 'no'
    || "$WEBHOOK_ACTIVATION" != 'no'
    || "$BROWSER_NAVIGATION" != 'no'
    || "$RETRY" != 'no'
    || "$LIVE_MODE" != 'no'
    || "$DEMO_DEPLOYMENT" != 'no'
]]; then
    printf '%s\n' 'Owner-attempt authority is incomplete or out of scope.' >&2
    exit 64
fi
if [[ -n "${RED_ADDON_SECRET_REFERENCES:-}"
    || -n "${RED_ADDON_SECRET_VALUES_JSON:-}"
    || -n "${RED_SUBSCRIPTION_REAL_ATTEMPT_CONFIRMED:-}"
]]; then
    printf '%s\n' 'Owner-attempt refused ambient secret or attempt state.' >&2
    exit 64
fi
if [[ ! -t 0 || ! -t 1 ]]; then
    printf '%s\n' 'Apply mode requires a private interactive terminal.' >&2
    exit 64
fi

red_subscription_owner_attempt_offline_gate

printf '%s' 'Enter the owner-controlled Stripe restricted Sandbox key (input hidden): '
IFS= read -r -s restricted_key
printf '\n'
if [[ "$restricted_key" != rk_test_*
    || ${#restricted_key} -lt 24
    || ${#restricted_key} -gt 255
    || ! "$restricted_key" =~ ^[[:graph:]]+$
]]; then
    red_subscription_owner_attempt_scrub
    printf '%s\n' 'Restricted Sandbox key was refused before provider contact.' >&2
    exit 65
fi

secret_json="$(
    printf '%s' "$restricted_key" \
        | "$PHP_BIN" -r \
            '$value = stream_get_contents(STDIN); echo json_encode(["config:subscription-launch-stripe-secret-key" => $value], JSON_THROW_ON_ERROR);'
)"
restricted_key=''
unset restricted_key

RED_SUBSCRIPTION_LAUNCH_MODE='real' \
RED_SUBSCRIPTION_REAL_ATTEMPT_CONFIRMED='1' \
RED_SUBSCRIPTION_OWNER_AUTHORIZATION_SHA256="$AUTHORIZATION_SHA256" \
RED_ADDON_SECRET_REFERENCES='config:subscription-launch-stripe-secret-key' \
RED_ADDON_SECRET_VALUES_JSON="$secret_json" \
    "$LAUNCH_RUNNER"

red_subscription_owner_attempt_scrub
printf '%s\n' 'Owner-bound Stripe Sandbox attempt finished; no retry was issued.'
