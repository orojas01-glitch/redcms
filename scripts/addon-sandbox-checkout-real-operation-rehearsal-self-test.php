<?php
/** Pure P3E-9D3B cross-repository rehearsal source contract. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$shellPath = $projectRoot
    . '/scripts/sandbox-checkout-real-operation-no-contact-rehearsal.sh';
$fixturePath = $projectRoot
    . '/scripts/addon-sandbox-checkout-real-operation-rehearsal-fixture.php';
$shell = is_file($shellPath) ? (string) file_get_contents($shellPath) : '';
$fixture = is_file($fixturePath) ? (string) file_get_contents($fixturePath) : '';
$assertions = 0;

function red_checkout_p3e9d3b_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_checkout_p3e9d3b_assert(
        $shell !== ''
            && str_contains($shell, 'set -euo pipefail')
            && str_contains($shell, 'origin/main')
            && str_contains(
                $shell,
                'a441588193cc1e32f707dd03e7d5caa6f2c49e1a'
            )
            && str_contains(
                $shell,
                'f7de77eb1694fb6003340632c5018024753fe1fa'
            )
            && str_contains($shell, '"$EXPECTED_ADAPTER_COMMIT:package"')
            && str_contains($shell, '"$EXPECTED_STORE_COMMIT:package"'),
        'rehearsal stages core main plus exact adapter and Store Lite packages'
    );
    red_checkout_p3e9d3b_assert(
        str_contains($shell, 'redcms-real-operation-no-contact.')
            && str_contains($shell, 'staged-project:0')
            && str_contains($shell, 'evidence:0')
            && str_contains($shell, 'source-repositories:unchanged')
            && str_contains($shell, 'database:not-opened'),
        'one guarded temporary root owns all disposable evidence and cleanup'
    );
    foreach ([
        'allow_url_fopen=0', 'disable_functions=curl_exec',
        'fsockopen', 'stream_socket_client', 'socket_create',
        '-u RED_ADDON_SECRET_VALUES_JSON', '-u STRIPE_SECRET_KEY',
        '-u STRIPE_API_KEY', '-u HTTP_PROXY', '-u HTTPS_PROXY',
        '-u ALL_PROXY',
    ] as $boundary) {
        red_checkout_p3e9d3b_assert(
            str_contains($shell, $boundary),
            $boundary . ' is enforced by the no-contact PHP launcher'
        );
    }
    foreach ([
        'DRY RUN:', 'No registrar or adapter handler ran',
        'changed-confirmation-refused:1',
        'Apply requires every exact printed identity',
        'Outcome: request_contract_adopted',
        'Adapter preflight invoked: yes',
        'contained-apply:1', 'provider-effects:0',
    ] as $proof) {
        red_checkout_p3e9d3b_assert(
            str_contains($shell, $proof),
            $proof . ' is required rehearsal evidence'
        );
    }
    foreach ([
        '--confirm-package=', '--confirm-version=',
        '--confirm-source-version=', '--confirm-manifest-sha256=',
        '--confirm-inventory-sha256=', '--confirm-plan-sha256=',
        '--confirm-input-sha256=', '--confirm-synthetic-plan-sha256=',
        '--confirm-contract-sha256=', '--confirm-request-sha256=',
        '--confirm-start-identity-sha256=', '--confirm-operation=',
        '--confirm-provider-operation=', '--confirm-maximum-attempts=1',
        '--confirm-credential-access-provided=no',
        '--confirm-execution-ready=no',
        '--confirm-execution-started=no',
        '--confirm-result-recorded=no',
        '--confirm-network-authorized=no',
        '--confirm-provider-contact-authorized=no',
        '--confirm-provider-mutation-authorized=no',
        '--confirm-checkout-creation-authorized=no',
        '--confirm-retry-authorized=no', '--apply',
    ] as $confirmation) {
        red_checkout_p3e9d3b_assert(
            str_contains($shell, $confirmation),
            $confirmation . ' remains explicit in the D3B apply boundary'
        );
    }
    red_checkout_p3e9d3b_assert(
        str_contains($shell, 'ALTERED_ARGS')
            && str_contains($shell, '--confirm-plan-sha256=')
            && str_contains($shell, 'REFUSED_STATUS')
            && str_contains($shell, '"$REFUSED_STATUS" -eq 64'),
        'one changed plan confirmation is refused before adapter invocation'
    );
    red_checkout_p3e9d3b_assert(
        str_contains($shell, 'tree_fingerprint')
            && substr_count($shell, 'repository_fingerprint') >= 5
            && str_contains($shell, 'STAGED_BEFORE')
            && str_contains($shell, 'CORE_SOURCE_BEFORE')
            && str_contains($shell, 'ADAPTER_SOURCE_BEFORE')
            && str_contains($shell, 'STORE_SOURCE_BEFORE'),
        'staged and all three source repositories must remain byte-stable'
    );
    red_checkout_p3e9d3b_assert(
        $fixture !== ''
            && str_contains(
                $fixture,
                'addon_sandbox_checkout_real_operation_helpers.php'
            )
            && str_contains($fixture, "!== '0.1.7'")
            && str_contains($fixture, "'packageVersion' => '0.1.5'")
            && str_contains($fixture, "'input' => \$input")
            && str_contains($fixture, "'preflight' => \$preflight")
            && str_contains($fixture, "'syntheticPlan' => \$syntheticPlan"),
        'fixture builds exact adapter, source-plan, and three-object evidence'
    );
    red_checkout_p3e9d3b_assert(
        substr_count(
            $fixture,
            'red_addon_checkout_real_operation_plan('
        ) === 1
            && !str_contains(
                $fixture,
                'red_addon_checkout_real_operation_execute('
            ),
        'fixture plans once and never invokes the adapter'
    );
    foreach ([
        'includes/config.php', 'includes/config.local.php', 'db-common.sh',
        'mysqli', 'PDO', 'mysql ', 'mysqldump', 'RED_DB_', 'curl ',
        'api.stripe.com/v1', 'Authorization:', 'php://input', '$_POST',
        '$_GET', 'red_addon_secret_resolve', '->secret(',
    ] as $forbidden) {
        red_checkout_p3e9d3b_assert(
            !str_contains($shell . $fixture, $forbidden),
            $forbidden . ' is absent from rehearsal and fixture sources'
        );
    }
    foreach (['sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'whsec_']
        as $literal
    ) {
        red_checkout_p3e9d3b_assert(
            !str_contains($fixture, $literal),
            $literal . ' credential literal is absent from fixture evidence'
        );
    }
    red_checkout_p3e9d3b_assert(
        str_contains($shell, 'sk_(test|live)_')
            && str_contains($shell, 'rk_(test|live)_')
            && str_contains($shell, 'whsec_'),
        'temporary evidence receives a credential-pattern scan'
    );
    red_checkout_p3e9d3b_assert(
        !file_exists(
            $projectRoot
                . '/admin/bin/sandbox-checkout-real-operation-rehearsal.php'
        )
            && !file_exists(
                $projectRoot
                    . '/bin/sandbox-checkout-real-operation-rehearsal.php'
            ),
        'no browser or public rehearsal bridge exists'
    );

    echo 'Sandbox Checkout P3E-9D3B rehearsal source contract passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
