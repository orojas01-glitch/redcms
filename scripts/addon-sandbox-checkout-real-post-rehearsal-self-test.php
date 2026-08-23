<?php
/** Pure P3E-9D4C2 cross-repository no-contact rehearsal contract. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$shellPath = $projectRoot
    . '/scripts/sandbox-checkout-real-post-no-contact-rehearsal.sh';
$fixturePath = $projectRoot
    . '/scripts/addon-sandbox-checkout-real-post-rehearsal-fixture.php';
$shell = is_file($shellPath) ? (string) file_get_contents($shellPath) : '';
$fixture = is_file($fixturePath) ? (string) file_get_contents($fixturePath) : '';
$assertions = 0;

function red_checkout_p3e9d4c2_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_checkout_p3e9d4c2_assert(
        $shell !== ''
            && str_contains($shell, 'set -euo pipefail')
            && str_contains($shell, 'origin/main')
            && str_contains(
                $shell,
                '44ed7b3bd8f84f3f24340a6afc39881e8dee8c5d'
            )
            && str_contains(
                $shell,
                'f7de77eb1694fb6003340632c5018024753fe1fa'
            )
            && str_contains($shell, '"$EXPECTED_ADAPTER_COMMIT:package"')
            && str_contains($shell, '"$EXPECTED_STORE_COMMIT:package"'),
        'rehearsal stages exact merged core, adapter, and Store Lite package sources'
    );
    foreach ([
        'allow_url_fopen=0', 'disable_functions=curl_exec',
        'fsockopen', 'stream_socket_client', 'socket_create',
        'PHP_INI_SCAN_DIR=', 'no-contact-runtime:ready',
        '-u RED_ADDON_SECRET_VALUES_JSON', '-u STRIPE_SECRET_KEY',
        '-u STRIPE_API_KEY', '-u HTTP_PROXY', '-u HTTPS_PROXY', '-u ALL_PROXY',
    ] as $boundary) {
        red_checkout_p3e9d4c2_assert(
            str_contains($shell, $boundary),
            $boundary . ' is enforced by the D4C2 no-contact runtime'
        );
    }
    foreach ([
        'database:0', 'grant:0', 'staged-project:0', 'evidence:0',
        'environment:clear', 'source-repositories:unchanged',
        'primary:unchanged',
    ] as $cleanup) {
        red_checkout_p3e9d4c2_assert(
            str_contains($shell, $cleanup),
            $cleanup . ' is required cleanup evidence'
        );
    }
    foreach ([
        'DRY RUN:', 'No secret value was resolved',
        'incomplete-apply-refused:1', 'changed-confirmation-refused:1',
        'real-apply:0', 'start-result:0', 'provider-effects:0',
        "LEDGER_COUNTS\" == '2:2:0'",
    ] as $proof) {
        red_checkout_p3e9d4c2_assert(
            str_contains($shell, $proof),
            $proof . ' is required D4C2 rehearsal evidence'
        );
    }
    foreach ([
        '--confirm-database=', '--confirm-database-sha256=',
        '--confirm-package=', '--confirm-version=', '--confirm-state=enabled',
        '--confirm-store-package=', '--confirm-store-version=',
        '--confirm-preflight-plan-sha256=',
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
        '--confirm-backup-sha256=',
        '--confirm-operation=checkout.create-sandbox-real-post',
        '--confirm-target=stripe-sandbox-real-post',
        '--confirm-maximum-attempts=1',
        '--confirm-provider-contact-authorized=yes',
        '--confirm-provider-mutation-authorized=yes',
        '--confirm-checkout-creation-authorized=yes',
        '--confirm-payment-authorized=no', '--confirm-webhook-authorized=no',
        '--confirm-browser-navigation-authorized=no',
        '--confirm-store-lite-mutation-authorized=no',
        '--confirm-session-expiration-authorized=no',
        '--confirm-retry-authorized=no', '--confirm-live-mode-authorized=no',
        '--confirm-client-deployment-authorized=no', '--apply',
    ] as $confirmation) {
        red_checkout_p3e9d4c2_assert(
            str_contains($shell, $confirmation),
            $confirmation . ' remains explicit in refusal-only apply evidence'
        );
    }
    red_checkout_p3e9d4c2_assert(
        str_contains($shell, 'INCOMPLETE_STATUS')
            && str_contains($shell, '"$INCOMPLETE_STATUS" -eq 64')
            && str_contains($shell, 'ALTERED_ARGS')
            && str_contains($shell, '--confirm-request-sha256=')
            && str_contains($shell, '"$CHANGED_STATUS" -eq 64'),
        'incomplete and one changed confirmation are refused before execution'
    );
    red_checkout_p3e9d4c2_assert(
        str_contains($shell, 'php_no_contact "$COMMAND" "${ALTERED_ARGS[@]}"')
            && !str_contains(
                $shell,
                'php_no_contact "$COMMAND" "${APPLY_ARGS[@]}"'
            )
            && !str_contains($shell, 'Outcome: checkout_session_created')
            && !str_contains($shell, 'Created one bounded open'),
        'the fully confirmed real apply is never invoked or expected'
    );
    red_checkout_p3e9d4c2_assert(
        substr_count($shell, 'repository_fingerprint') >= 5
            && str_contains($shell, 'tree_fingerprint')
            && str_contains($shell, 'CORE_SOURCE_BEFORE')
            && str_contains($shell, 'ADAPTER_SOURCE_BEFORE')
            && str_contains($shell, 'STORE_SOURCE_BEFORE')
            && str_contains($shell, 'STAGED_BEFORE'),
        'staged tree and all three source repositories must remain unchanged'
    );
    red_checkout_p3e9d4c2_assert(
        $fixture !== ''
            && str_contains(
                $fixture,
                'addon-sandbox-checkout-real-mutation-lifecycle-self-test.php'
            )
            && str_contains($fixture, "!== '0.1.8'")
            && str_contains($fixture, "!== '0.1.35'")
            && !str_contains(
                $fixture,
                'red_addon_payment_adapter_db_test_write_package('
            ),
        'fixture consumes exact staged packages and never replaces their source'
    );
    red_checkout_p3e9d4c2_assert(
        str_contains($fixture, "'input' => \$input")
            && str_contains($fixture, "'preflight' => \$preflight")
            && str_contains(
                $fixture,
                "'preflightOutcome' => \$preflightOutcome"
            )
            && str_contains($fixture, "'prepared' => \$prepared")
            && str_contains($fixture, "'syntheticPlan' => \$syntheticPlan"),
        'fixture emits the exact five-object non-secret command evidence'
    );
    red_checkout_p3e9d4c2_assert(
        substr_count(
            $fixture,
            'red_addon_checkout_real_mutation_record_stage('
        ) === 2
            && substr_count(
                $fixture,
                'red_addon_checkout_real_mutation_execute('
            ) === 0
            && str_contains($fixture, "'authorization'")
            && str_contains($fixture, "'claim'")
            && str_contains($fixture, "'execution'"),
        'fixture records only authorization/claim and plans but never executes start'
    );
    foreach ([
        'curl ', 'curl_', 'api.stripe.com/v1', 'Authorization:',
        'php://input', '$_POST', '$_GET', 'shell_exec(', 'exec(', 'passthru(',
        'red_addon_secret_resolve(',
        'red_addon_runtime_secret_access_for_package(',
        'red_addon_runtime_register_package(',
        'red_addon_adapter_invoke_registered(',
    ] as $forbidden) {
        red_checkout_p3e9d4c2_assert(
            !str_contains($fixture, $forbidden),
            $forbidden . ' is absent from the D4C2 evidence fixture'
        );
    }
    foreach (['sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'whsec_']
        as $credential
    ) {
        red_checkout_p3e9d4c2_assert(
            !str_contains($fixture, $credential),
            $credential . ' literal is absent from D4C2 evidence source'
        );
    }
    red_checkout_p3e9d4c2_assert(
        str_contains($shell, 'sk_(test|live)_')
            && str_contains($shell, 'rk_(test|live)_')
            && str_contains($shell, 'whsec_'),
        'all temporary evidence receives a credential-pattern scan'
    );
    red_checkout_p3e9d4c2_assert(
        !file_exists(
            $projectRoot
                . '/admin/bin/sandbox-checkout-real-post-rehearsal.php'
        )
            && !file_exists(
                $projectRoot . '/bin/sandbox-checkout-real-post-rehearsal.php'
            )
            && !file_exists(
                $projectRoot
                    . '/admin/sandbox-checkout-real-post-rehearsal.php'
            ),
        'no public or browser rehearsal bridge exists'
    );

    echo 'Sandbox Checkout P3E-9D4C2 rehearsal source contract passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
