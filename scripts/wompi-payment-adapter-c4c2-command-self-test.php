<?php
/** Pure C4C2 merchant-read double operator-command source contract. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$commandPath = $projectRoot
    . '/scripts/admin-wompi-merchant-read-double-execute.php';
$helperPath = $projectRoot
    . '/includes/addon_payment_adapter_wompi_merchant_read_preflight_helpers.php';
$source = is_file($commandPath) ? (string) file_get_contents($commandPath) : '';
$helperSource = is_file($helperPath) ? (string) file_get_contents($helperPath) : '';
$assertions = 0;

function red_wompi_c4c2_command_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_wompi_c4c2_command_assert(
        $source !== ''
            && $helperSource !== ''
            && str_contains($source, "PHP_SAPI !== 'cli'")
            && str_contains(
                $source,
                'addon_payment_adapter_wompi_merchant_read_preflight_helpers.php'
            ),
        'command is CLI-only and loads the reviewed C4C2 preflight'
    );
    foreach ([
        '--actor-admin=', '--confirm-database=', '--confirm-package=',
        '--confirm-version=', '--confirm-state=',
        '--confirm-client-scope-sha256=', '--confirm-database-sha256=',
        '--confirm-actor-subject-sha256=', '--confirm-public-key-sha256=',
        '--confirm-setting-state-sha256=',
        '--confirm-reference-state-sha256=',
        '--confirm-merchant-plan-sha256=', '--confirm-preflight-sha256=',
        '--confirm-backup-sha256=', '--confirm-operation=',
        '--confirm-target=', '--confirm-maximum-attempts=',
        '--confirm-retry-authorized=', '--confirm-network-disabled=',
        '--confirm-real-provider-contact-authorized=',
        '--confirm-provider-mutation-authorized=',
        '--confirm-transaction-creation-authorized=',
        '--confirm-payment-authorized=',
        '--confirm-order-mutation-authorized=', '--apply',
    ] as $confirmation) {
        red_wompi_c4c2_command_assert(
            str_contains($source, $confirmation),
            $confirmation . ' is required by the operator contract'
        );
    }
    red_wompi_c4c2_command_assert(
        substr_count(
            $source,
            'red_addon_wompi_merchant_read_preflight('
        ) === 1
            && substr_count(
                $source,
                'new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport_Double()'
            ) === 1
            && substr_count(
                $source,
                'RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::execute('
            ) === 1,
        'command plans once and can invoke only one exact sealed double'
    );
    red_wompi_c4c2_command_assert(
        str_contains($source, "if (!\$options['apply'])")
            && str_contains($source, 'DRY RUN:')
            && str_contains($source, 'No package file or handler was loaded')
            && str_contains($source, 'no network or Wompi contact occurred'),
        'default mode is a no-load, no-contact dry run'
    );
    red_wompi_c4c2_command_assert(
        str_contains($source, "=== '0.1.5'")
            && str_contains(
                $source,
                "'merchant.acceptance-contracts.retrieve-sandbox-double'"
            )
            && str_contains($source, "'core-sealed-no-network-double'"),
        'apply is pinned to exact package, operation, and no-network target'
    );
    red_wompi_c4c2_command_assert(
        str_contains($source, "=== '1'")
            && substr_count($source, "=== 'no'") >= 5
            && str_contains($source, "=== 'yes'")
            && str_contains($source, "str_repeat('0', 64)"),
        'one attempt, denied real effects, and nonzero backup are explicit'
    );
    red_wompi_c4c2_command_assert(
        str_contains($source, 'Durable attempt consumed: no')
            && str_contains($source, 'Replay protection active: no')
            && str_contains($source, 'Real provider contact authorized: no'),
        'non-durable double boundary cannot be mistaken for real one-shot apply'
    );
    foreach ([
        'curl_exec(', 'curl_multi_exec(', 'fsockopen(',
        'stream_socket_client(', 'socket_create(', 'socket_connect(',
        'new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Curl_Transport(',
        'WompiNequiOfflineAdapter.php', 'red_addon_runtime_register_package(',
        'red_addon_adapter_invoke_registered(', '->secret(', 'Authorization:',
        'production.wompi.co', 'prv_test_', 'prv_prod_',
        'test_integrity_', 'test_events_', 'shell_exec(', 'passthru(',
        'sleep(', 'usleep(', 'php://input', '$_POST',
    ] as $forbidden) {
        red_wompi_c4c2_command_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from the command'
        );
    }
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_create(',
        'socket_connect(', 'getenv(', '$_ENV', 'file_put_contents(',
        'red_addon_runtime_register_package(',
        'red_addon_adapter_invoke_registered(', '->secret(',
    ] as $forbidden) {
        red_wompi_c4c2_command_assert(
            !str_contains($helperSource, $forbidden),
            $forbidden . ' is absent from the database preflight helper'
        );
    }
    red_wompi_c4c2_command_assert(
        substr_count($helperSource, 'pub_test_') === 1
            && !str_contains($helperSource, 'pub_prod_')
            && !str_contains($helperSource, 'production.wompi.co')
            && str_contains($helperSource, 'secretValuesResolved')
            && str_contains($helperSource, 'realProviderContactAuthorized'),
        'preflight fixes Sandbox public identity while secrets/contact stay false'
    );
    red_wompi_c4c2_command_assert(
        !file_exists(
            $projectRoot . '/admin/bin/wompi-merchant-read-double-execute.php'
        )
            && !file_exists(
                $projectRoot . '/bin/wompi-merchant-read-double-execute.php'
            ),
        'no browser or public command bridge exists'
    );

    echo 'Wompi C4C2 merchant-read command self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
