<?php
/** Pure P3E-9D3A operator-command source contract. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$commandPath = $projectRoot
    . '/scripts/admin-sandbox-checkout-real-operation-preflight.php';
$source = is_file($commandPath) ? (string) file_get_contents($commandPath) : '';
$assertions = 0;

function red_checkout_p3e9d3a_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_checkout_p3e9d3a_assert(
        $source !== ''
            && str_contains($source, "PHP_SAPI !== 'cli'")
            && str_contains(
                $source,
                'addon_sandbox_checkout_real_operation_helpers.php'
            )
            && !str_contains($source, 'includes/config.php')
            && !str_contains($source, 'class_connection.php'),
        'command is CLI-only and loads only the dependency-free D2 helper'
    );
    foreach ([
        '--evidence-file=', '--confirm-package=', '--confirm-version=',
        '--confirm-source-version=', '--confirm-manifest-sha256=',
        '--confirm-inventory-sha256=', '--confirm-plan-sha256=',
        '--confirm-input-sha256=', '--confirm-synthetic-plan-sha256=',
        '--confirm-contract-sha256=', '--confirm-request-sha256=',
        '--confirm-start-identity-sha256=', '--confirm-operation=',
        '--confirm-provider-operation=', '--confirm-maximum-attempts=',
        '--confirm-credential-access-provided=',
        '--confirm-execution-ready=', '--confirm-execution-started=',
        '--confirm-result-recorded=', '--confirm-network-authorized=',
        '--confirm-provider-contact-authorized=',
        '--confirm-provider-mutation-authorized=',
        '--confirm-checkout-creation-authorized=',
        '--confirm-retry-authorized=', '--apply',
    ] as $confirmation) {
        red_checkout_p3e9d3a_assert(
            str_contains($source, $confirmation),
            $confirmation . ' is required by the D3A command contract'
        );
    }
    red_checkout_p3e9d3a_assert(
        str_contains($source, "=== '0.1.7'")
            && str_contains($source, "=== '0.1.5'")
            && str_contains(
                $source,
                "'checkout.create-sandbox-real-post-preflight'"
            )
            && str_contains(
                $source,
                "'checkout.create-sandbox-real-post'"
            ),
        'apply is pinned to exact package, source, and operation identities'
    );
    red_checkout_p3e9d3a_assert(
        str_contains($source, "=== '1'")
            && substr_count($source, "=== 'no'") === 9,
        'one attempt and all nine no-effect confirmations are exact'
    );
    red_checkout_p3e9d3a_assert(
        substr_count(
            $source,
            'red_addon_checkout_real_operation_plan('
        ) === 1
            && substr_count(
                $source,
                'red_addon_checkout_real_operation_execute('
            ) === 1,
        'command plans once and invokes the D2 preflight runner once'
    );
    $dryRunPosition = strpos($source, "if (!\$options['apply'])");
    $executePosition = strpos(
        $source,
        'red_addon_checkout_real_operation_execute('
    );
    red_checkout_p3e9d3a_assert(
        is_int($dryRunPosition)
            && is_int($executePosition)
            && $dryRunPosition < $executePosition
            && str_contains($source, 'DRY RUN:')
            && str_contains($source, 'No registrar or adapter handler ran')
            && str_contains(
                $source,
                'no execution or provider attempt started'
            ),
        'default mode exits before package preflight invocation'
    );
    red_checkout_p3e9d3a_assert(
        str_contains($source, "=== 'request_contract_adopted'")
            && str_contains($source, 'resultIdentityPrepared')
            && str_contains($source, 'Execution started: no')
            && str_contains($source, 'Result recorded: no'),
        'apply accepts only the exact non-persistent D2 result identity'
    );
    foreach ([
        '--actor-admin=', '--confirm-database=', '--confirm-state=',
        '--confirm-backup-sha256=', '--secret-key=', '--credential=',
        'RED_ADDON_SECRET_VALUES_JSON', 'sk_test_', 'sk_live_', 'rk_test_',
        'rk_live_', 'whsec_',
    ] as $forbiddenArgument) {
        red_checkout_p3e9d3a_assert(
            !str_contains($source, $forbiddenArgument),
            $forbiddenArgument . ' is absent from the command vocabulary'
        );
    }
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST',
        '$_GET', 'PDO', 'mysqli', 'getenv(', 'putenv(', 'shell_exec(',
        'exec(', 'passthru(', 'sleep(', 'usleep(',
        'red_addon_secret_resolve', 'red_addon_runtime_secret',
        'RED_Addon_Runtime_Secret_Access', '->secret(',
        'red_addon_runtime_register_package',
        'red_addon_adapter_invoke_registered',
    ] as $forbidden) {
        red_checkout_p3e9d3a_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from the D3A command'
        );
    }
    red_checkout_p3e9d3a_assert(
        !file_exists(
            $projectRoot
                . '/admin/bin/sandbox-checkout-real-operation-preflight.php'
        )
            && !file_exists(
                $projectRoot
                    . '/bin/sandbox-checkout-real-operation-preflight.php'
            )
            && !file_exists(
                $projectRoot
                    . '/admin/sandbox-checkout-real-operation-preflight.php'
            ),
        'no browser or public command bridge exists'
    );

    echo 'Sandbox Checkout P3E-9D3A operator command self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
