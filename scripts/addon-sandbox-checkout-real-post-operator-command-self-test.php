<?php
/** Pure P3E-9D4C1 real-POST operator-command source contract. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$commandPath = $projectRoot
    . '/scripts/admin-sandbox-checkout-real-post-execute.php';
$source = is_file($commandPath) ? (string) file_get_contents($commandPath) : '';
$assertions = 0;

function red_checkout_p3e9d4c1_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_checkout_p3e9d4c1_assert(
        $source !== ''
            && str_contains($source, "PHP_SAPI !== 'cli'")
            && str_contains($source, 'includes/config.php')
            && str_contains($source, 'class/class_connection.php')
            && str_contains(
                $source,
                'addon_sandbox_checkout_real_mutation_helpers.php'
            ),
        'command is CLI-only and loads only reviewed configuration, database, and D4B helpers'
    );
    foreach ([
        '--actor-admin=', '--evidence-file=', '--confirm-database=',
        '--confirm-database-sha256=', '--confirm-package=',
        '--confirm-version=', '--confirm-state=', '--confirm-store-package=',
        '--confirm-store-version=', '--confirm-preflight-plan-sha256=',
        '--confirm-preflight-start-identity-sha256=',
        '--confirm-preflight-result-identity-sha256=',
        '--confirm-input-sha256=', '--confirm-synthetic-plan-sha256=',
        '--confirm-contract-sha256=', '--confirm-request-sha256=',
        '--confirm-order-snapshot-sha256=',
        '--confirm-authorization-sha256=',
        '--confirm-authorization-state-sha256=',
        '--confirm-claim-state-sha256=',
        '--confirm-execution-start-sha256=',
        '--confirm-secret-availability-sha256=',
        '--confirm-backup-sha256=', '--confirm-operation=',
        '--confirm-target=', '--confirm-maximum-attempts=',
        '--confirm-provider-contact-authorized=',
        '--confirm-provider-mutation-authorized=',
        '--confirm-checkout-creation-authorized=',
        '--confirm-payment-authorized=', '--confirm-webhook-authorized=',
        '--confirm-browser-navigation-authorized=',
        '--confirm-store-lite-mutation-authorized=',
        '--confirm-session-expiration-authorized=',
        '--confirm-retry-authorized=', '--confirm-live-mode-authorized=',
        '--confirm-client-deployment-authorized=', '--apply',
    ] as $confirmation) {
        red_checkout_p3e9d4c1_assert(
            str_contains($source, $confirmation),
            $confirmation . ' is required by the D4C1 command contract'
        );
    }
    red_checkout_p3e9d4c1_assert(
        str_contains($source, "=== '0.1.8'")
            && str_contains($source, "=== '0.1.35'")
            && str_contains($source, "=== 'enabled'")
            && str_contains(
                $source,
                "'checkout.create-sandbox-real-post'"
            )
            && str_contains($source, "'stripe-sandbox-real-post'"),
        'apply is pinned to exact package, state, operation, and target'
    );
    red_checkout_p3e9d4c1_assert(
        str_contains($source, "=== '1'")
            && substr_count($source, "=== 'yes'") === 3
            && substr_count($source, "=== 'no'") === 8
            && str_contains($source, "str_repeat('0', 64)"),
        'one attempt, three intended effects, eight exclusions, and nonzero backup are exact'
    );
    red_checkout_p3e9d4c1_assert(
        substr_count(
            $source,
            'red_addon_checkout_real_mutation_plan('
        ) === 1
            && substr_count(
                $source,
                'red_addon_checkout_real_mutation_execute('
            ) === 1,
        'command plans once and has one D4B2 execution call site'
    );
    $dryRunPosition = strpos($source, "if (!\$options['apply'])");
    $executePosition = strpos(
        $source,
        'red_addon_checkout_real_mutation_execute('
    );
    red_checkout_p3e9d4c1_assert(
        is_int($dryRunPosition)
            && is_int($executePosition)
            && $dryRunPosition < $executePosition
            && str_contains($source, 'DRY RUN:')
            && str_contains($source, 'No secret value was resolved')
            && str_contains($source, 'no registrar or handler ran')
            && str_contains($source, 'no network or provider request occurred'),
        'default mode exits before the single provider-capable call site'
    );
    red_checkout_p3e9d4c1_assert(
        str_contains($source, "=== 'checkout_session_created'")
            && str_contains($source, "=== 'open'")
            && str_contains($source, "=== 'unpaid'")
            && str_contains($source, 'Checkout Session reference:')
            && str_contains($source, 'The Checkout URL was discarded')
            && !str_contains($source, 'success_url')
            && !str_contains($source, 'checkout.url'),
        'success output is bounded and never prints the Checkout URL'
    );
    red_checkout_p3e9d4c1_assert(
        str_contains($source, 'The attempt remains consumed')
            && str_contains($source, 'no retry is authorized')
            && str_contains($source, 'Stripe Sandbox request log'),
        'every non-success result is a consumed no-retry attempt'
    );
    red_checkout_p3e9d4c1_assert(
        str_contains(
            $source,
            "'input', 'preflight', 'preflightOutcome', 'prepared',"
        )
            && str_contains($source, "'syntheticPlan',")
            && str_contains($source, 'filesize($evidenceRealPath) > 131072'),
        'one bounded exact five-object evidence file is required'
    );
    foreach ([
        '--secret-key=', '--credential=', '--secret-value=',
        'RED_ADDON_SECRET_VALUES_JSON', 'sk_test_', 'sk_live_', 'rk_test_',
        'rk_live_', 'whsec_',
    ] as $forbiddenCredential) {
        red_checkout_p3e9d4c1_assert(
            !str_contains($source, $forbiddenCredential),
            $forbiddenCredential . ' is absent from command vocabulary'
        );
    }
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST', '$_GET',
        'shell_exec(', 'exec(', 'passthru(', 'sleep(', 'usleep(',
        'red_addon_secret_resolve(', 'red_addon_runtime_secret_access_for_package(',
        'red_addon_runtime_register_package(',
        'red_addon_adapter_invoke_registered(',
    ] as $forbidden) {
        red_checkout_p3e9d4c1_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from the D4C1 command source'
        );
    }
    red_checkout_p3e9d4c1_assert(
        !file_exists(
            $projectRoot . '/admin/bin/sandbox-checkout-real-post-execute.php'
        )
            && !file_exists(
                $projectRoot . '/bin/sandbox-checkout-real-post-execute.php'
            )
            && !file_exists(
                $projectRoot . '/admin/sandbox-checkout-real-post-execute.php'
            ),
        'no browser or public command bridge exists'
    );

    echo 'Sandbox Checkout P3E-9D4C1 operator command self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
