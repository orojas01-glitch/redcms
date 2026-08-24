<?php
/** Source contract for the one-shot P3E-9D4D diagnostic recovery harness. */

$source = (string) file_get_contents(
    __DIR__ . '/sandbox-checkout-real-post-diagnostic-recovery.sh'
);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$assert($source !== '' && str_contains($source, 'set -euo pipefail'),
    'strict shell execution is required');
foreach ([
    '--secret-values-file=', '--confirm-provider-recovery=yes',
    '/private/tmp/redcms-stripe-d4d-recovery-secret-values.json',
    "stat -f '%Lp'", "!= '600'", "-L \"\$RECOVERY_SECRET_FILE\"",
    'rk_test_', 'RED_ADDON_SECRET_REFERENCES=',
    'RED_D4D_RECOVERY_NETWORK_MODE', "^(provider|offline)$",
    'RED_ADDON_SECRET_VALUES_JSON=', '-u STRIPE_SECRET_KEY',
    '-u STRIPE_API_KEY', '-u HTTP_PROXY', '-u HTTPS_PROXY', '-u ALL_PROXY',
] as $boundary) {
    $assert(str_contains($source, $boundary), $boundary . ' is required');
}
foreach ([
    'allow_url_fopen=0', 'disable_functions=curl_exec',
    'no-contact-runtime:ready', 'PHP_INI_SCAN_DIR="$TEMP_ROOT/php-ini"',
    'PHP_INI_SCAN_DIR="$TEMP_ROOT/php-ini-contact"',
    "RECOVERY_NETWORK_MODE\" == 'offline'",
] as $runtime) {
    $assert(str_contains($source, $runtime), $runtime . ' is fixed');
}
$assert(
    str_contains($source, '|| status=$?')
        && str_contains($source, 'return "$status"')
        && str_contains($source, 'APPLY_STATUS=$?'),
    'the real command status survives secret clearing'
);
foreach ([
    '--confirm-maximum-attempts=1',
    '--confirm-provider-contact-authorized=yes',
    '--confirm-provider-mutation-authorized=yes',
    '--confirm-checkout-creation-authorized=yes',
    '--confirm-payment-authorized=no',
    '--confirm-webhook-authorized=no',
    '--confirm-browser-navigation-authorized=no',
    '--confirm-store-lite-mutation-authorized=no',
    '--confirm-session-expiration-authorized=no',
    '--confirm-retry-authorized=no',
    '--confirm-live-mode-authorized=no',
    '--confirm-client-deployment-authorized=no',
] as $confirmation) {
    $assert(str_contains($source, $confirmation), $confirmation . ' is fixed');
}
foreach ([
    'OUTCOME=', 'FAILURE_STAGE=', "APPLY_STATUS\" -eq 0",
    "APPLY_STATUS\" -eq 1", 'checkout_session_created', 'indeterminate',
    'transport_exchange_failed', 'response_decode_failed',
    'adapter_invocation_failed', 'failed bounded validation',
    'Recovery ledger postcondition failed',
    'response_acceptance_failed', 'Attempt consumed: yes',
    "POST_APPLY_COUNTS\" != '4:4:2'",
] as $resultBoundary) {
    $assert(str_contains($source, $resultBoundary),
        $resultBoundary . ' is bounded');
}
foreach ([
    'REVOKE ALL PRIVILEGES', 'DROP DATABASE IF EXISTS',
    'rm -rf -- "$TEMP_ROOT"', 'rm -f -- "$RECOVERY_SECRET_FILE"',
    'source-repositories:unchanged primary:unchanged',
    'rk_(test|live)', 'whsec_',
] as $cleanup) {
    $assert(str_contains($source, $cleanup), $cleanup . ' is enforced');
}
$assert(
    !str_contains($source, 'curl https://api.stripe.com')
        && !str_contains($source, 'Checkout URL:')
        && !str_contains($source, 'cat "$TEMP_ROOT/apply.txt"'),
    'the harness has no alternate provider call or raw result output'
);

echo 'Sandbox Checkout P3E-9D4D recovery harness source contract passed: '
    . $assertions . " assertions.\n";

?>
