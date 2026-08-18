<?php
/**
 * Pure P3E-8B3C3A server-local operator-command contract checks.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$commandPath = $projectRoot .
    '/scripts/admin-provider-contact-sandbox-execute.php';
$source = is_file($commandPath)
    ? (string) file_get_contents($commandPath)
    : '';
$assertions = 0;

function red_addon_provider_contact_operator_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_addon_provider_contact_operator_assert(
        $source !== ''
            && str_contains($source, "PHP_SAPI !== 'cli'")
            && str_contains(
                $source,
                'addon_provider_contact_provider_execution_helpers.php'
            ),
        'operator command is CLI-only and uses the reviewed core runner'
    );
    foreach ([
        '--actor-admin=', '--evidence-file=', '--confirm-database=',
        '--confirm-package=', '--confirm-version=', '--confirm-state=',
        '--confirm-plan-sha256=', '--confirm-authorization-sha256=',
        '--confirm-claim-state-sha256=',
        '--confirm-execution-start-sha256=',
        '--confirm-secret-availability-sha256=',
        '--confirm-backup-sha256=', '--confirm-operation=',
        '--confirm-target=', '--confirm-credential-mode=',
        '--confirm-maximum-attempts=', '--confirm-retry-authorized=',
        '--confirm-mutation-authorized=', '--apply',
    ] as $confirmation) {
        red_addon_provider_contact_operator_assert(
            str_contains($source, $confirmation),
            $confirmation . ' is required by the operator contract'
        );
    }
    red_addon_provider_contact_operator_assert(
        str_contains(
            $source,
            "'provider-contact.read-only-probe-sandbox'"
        )
            && str_contains($source, "'stripe-sandbox'")
            && str_contains($source, "'restricted_test'")
            && str_contains($source, "=== '0.1.4'")
            && str_contains($source, "=== 'enabled'"),
        'apply is pinned to the exact operation, target, key mode, and state'
    );
    red_addon_provider_contact_operator_assert(
        str_contains($source, "=== '1'")
            && substr_count($source, "=== 'no'") >= 2
            && str_contains($source, 'str_repeat(\'0\', 64)'),
        'one attempt, no retry, no mutation, and nonzero backup are explicit'
    );
    red_addon_provider_contact_operator_assert(
        substr_count(
            $source,
            'red_addon_provider_contact_sandbox_execution_plan('
        ) === 1
            && substr_count(
                $source,
                'red_addon_provider_contact_execute_sandbox('
            ) === 1,
        'command plans once and contains only one execution call site'
    );
    red_addon_provider_contact_operator_assert(
        str_contains($source, 'if (!$options[\'apply\'])')
            && str_contains($source, 'No credential value was resolved')
            && str_contains($source, 'no package handler was invoked')
            && str_contains($source, 'no network or provider contact occurred'),
        'default mode is an explicit non-executing dry run'
    );
    red_addon_provider_contact_operator_assert(
        str_contains($source, "=== 'resource_miss_observed'")
            && str_contains($source, "=== 404")
            && str_contains($source, 'The attempt remains consumed')
            && str_contains($source, 'no retry is authorized'),
        'only the exact resource miss succeeds and every other result burns'
    );
    red_addon_provider_contact_operator_assert(
        !str_contains($source, '--secret-key=')
            && !str_contains($source, 'RED_ADDON_SECRET_VALUES_JSON')
            && !str_contains($source, 'sk_test_')
            && !str_contains($source, 'sk_live_')
            && !str_contains($source, 'rk_test_')
            && !str_contains($source, 'rk_live_'),
        'command accepts and contains no credential value or literal'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST',
        'shell_exec(', 'exec(', 'passthru(', 'sleep(', 'usleep(',
    ] as $forbidden) {
        red_addon_provider_contact_operator_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from the operator command'
        );
    }
    red_addon_provider_contact_operator_assert(
        !file_exists($projectRoot . '/admin/bin/provider-contact-execute.php')
            && !file_exists($projectRoot . '/bin/provider-contact-execute.php')
            && !file_exists(
                $projectRoot . '/admin/provider-contact-execute.php'
            ),
        'no browser or public command bridge exists'
    );

    echo 'Provider contact P3E-8B3C3A operator command self-test passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
