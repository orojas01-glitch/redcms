<?php
/** Pure C4B4D Wompi no-contact operator-command source contract. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$commandPath = $projectRoot
    . '/scripts/admin-wompi-no-contact-transport-double-execute.php';
$source = is_file($commandPath) ? (string) file_get_contents($commandPath) : '';
$assertions = 0;

function red_wompi_c4b4d_command_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_wompi_c4b4d_command_assert(
        $source !== ''
            && str_contains($source, "PHP_SAPI !== 'cli'")
            && str_contains(
                $source,
                'addon_payment_adapter_wompi_transport_double_helpers.php'
            ),
        'command is CLI-only and loads only the reviewed C4B4C runner'
    );
    foreach ([
        '--actor-admin=', '--evidence-file=', '--confirm-database=',
        '--confirm-package=', '--confirm-version=', '--confirm-state=',
        '--confirm-client-scope-sha256=', '--confirm-database-sha256=',
        '--confirm-actor-subject-sha256=', '--confirm-order-sha256=',
        '--confirm-plan-sha256=', '--confirm-wire-request-sha256=',
        '--confirm-authorization-sha256=',
        '--confirm-authorization-state-sha256=',
        '--confirm-claim-sha256=', '--confirm-claim-state-sha256=',
        '--confirm-request-sha256=', '--confirm-execution-start-sha256=',
        '--confirm-backup-sha256=', '--confirm-operation=',
        '--confirm-target=', '--confirm-maximum-attempts=',
        '--confirm-retry-authorized=', '--confirm-network-disabled=',
        '--confirm-provider-contact-denied=',
        '--confirm-provider-mutation-denied=',
        '--confirm-transaction-creation-denied=', '--confirm-payment-denied=',
        '--confirm-order-mutation-denied=', '--apply',
    ] as $confirmation) {
        red_wompi_c4b4d_command_assert(
            str_contains($source, $confirmation),
            $confirmation . ' is required by the operator contract'
        );
    }
    red_wompi_c4b4d_command_assert(
        str_contains($source, "=== '0.1.4'")
            && str_contains($source, "=== 'enabled'")
            && str_contains(
                $source,
                "'checkout.create-sandbox-no-contact-double'"
            )
            && str_contains($source, "'core-sealed-in-memory-double'"),
        'apply is pinned to exact package, state, operation, and target'
    );
    red_wompi_c4b4d_command_assert(
        str_contains($source, "=== '1'")
            && substr_count($source, "=== 'no'") >= 1
            && substr_count($source, "=== 'yes'") >= 6
            && str_contains($source, "str_repeat('0', 64)"),
        'one attempt, denied effects, and a nonzero backup are explicit'
    );
    red_wompi_c4b4d_command_assert(
        substr_count($source, 'red_addon_wompi_transport_plan(') === 1
            && substr_count($source, 'red_addon_wompi_transport_execute(')
                === 1
            && substr_count(
                $source,
                'new RED_Addon_Wompi_No_Contact_Transport_Double('
            ) === 1,
        'command plans once, constructs one final double, and calls once'
    );
    red_wompi_c4b4d_command_assert(
        str_contains($source, "if (!\$options['apply'])")
            && str_contains($source, 'DRY RUN:')
            && str_contains($source, 'No double ran')
            && str_contains($source, 'no network, Wompi, transaction'),
        'default mode is an explicit non-executing dry run'
    );
    red_wompi_c4b4d_command_assert(
        str_contains($source, "=== 'sealed_double_completed'")
            && str_contains($source, 'The attempt remains consumed')
            && str_contains($source, 'no retry is authorized'),
        'only the bounded result succeeds and every post-start failure burns'
    );
    red_wompi_c4b4d_command_assert(
        !str_contains($source, '--private-key=')
            && !str_contains($source, '--integrity-key=')
            && !str_contains($source, '--event-secret=')
            && !str_contains($source, '--credential=')
            && !str_contains($source, 'RED_ADDON_SECRET_VALUES_JSON')
            && !str_contains($source, 'prv_test_')
            && !str_contains($source, 'prv_prod_'),
        'command accepts and contains no credential value or literal'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'sandbox.wompi.co', 'production.wompi.co', 'Authorization:',
        'php://input', '$_POST', 'shell_exec(', 'exec(', 'passthru(',
        'sleep(', 'usleep(', 'red_addon_secret_resolve',
        'red_addon_runtime_register_package',
        'red_addon_adapter_invoke_registered',
    ] as $forbidden) {
        red_wompi_c4b4d_command_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from the operator command'
        );
    }
    red_wompi_c4b4d_command_assert(
        !file_exists(
            $projectRoot . '/admin/bin/wompi-no-contact-transport-execute.php'
        )
            && !file_exists(
                $projectRoot . '/bin/wompi-no-contact-transport-execute.php'
            )
            && !file_exists(
                $projectRoot . '/admin/wompi-no-contact-transport-execute.php'
            ),
        'no browser or public command bridge exists'
    );

    echo 'Wompi C4B4D operator command self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
