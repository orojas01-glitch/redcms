<?php
/** Pure P3E-9D0 real Checkout POST preflight contract checks. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__)
    . '/includes/addon_sandbox_checkout_real_post_preflight_helpers.php';

$assertions = 0;
function red_checkout_real_post_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    $input = [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => [
            'orderId' => 'ord_0123456789abcdef0123456789abcdef',
            'orderSnapshotSha256' => str_repeat('a', 64),
            'paymentMethod' => 'stripe_checkout',
            'amountMinor' => 5897,
            'currency' => 'USD',
            'idempotencySha256' => str_repeat('b', 64),
            'lineItems' => [[
                'name' => 'Dog scarf - Small / Red',
                'quantity' => 2,
                'unitAmountMinor' => 1999,
                'lineTotalMinor' => 3998,
            ], [
                'name' => 'Delivery fee',
                'quantity' => 1,
                'unitAmountMinor' => 1899,
                'lineTotalMinor' => 1899,
            ]],
        ],
        'policy' => [
            'apiVersion' => '2024-09-30.acacia',
            'successUrl' => 'https://shop.example.test/checkout/stripe-complete',
            'cancelUrl' => 'https://shop.example.test/checkout',
            'createdAtEpoch' => 1787025600,
            'expiresAtEpoch' => 1787027400,
        ],
        'profile' => [
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'contractVersion' => 'p3e9a-v1',
            'operation' => 'checkout.create-sandbox',
            'contactTarget' => 'stripe-sandbox',
            'credentialMode' => 'restricted_test_write',
            'providerContact' => true,
            'providerMutation' => true,
            'checkoutCreation' => true,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'clientDeployment' => false,
            'oneAttempt' => true,
            'automaticRetry' => false,
        ],
        'contractSha256' => str_repeat('c', 64),
    ];
    $inputSha = red_addon_checkout_synthetic_hash($input);
    $plan = [
        'valid' => true, 'ready' => true, 'status' => 'ready',
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'packageVersion' => '0.1.5',
        'adapterId' => 'redcms.store-lite-stripe-checkout/checkout',
        'operation' => 'checkout.create-sandbox-synthetic',
        'manifestSha256' => str_repeat('d', 64),
        'inventorySha256' => str_repeat('e', 64),
        'inputSha256' => $inputSha,
        'planSha256' => str_repeat('f', 64),
        'adapterInvoked' => false, 'boundedOutcome' => null,
        'outcomeSha256' => '', 'executionPerformed' => false,
        'networkAccess' => false, 'providerContact' => false,
        'providerMutation' => false, 'checkoutCreation' => false,
        'payment' => false, 'webhook' => false,
        'browserNavigation' => false, 'orderMutation' => false,
        'retryAuthorized' => false, 'clientDeployment' => false,
        'errors' => [],
    ];
    $preflight = red_addon_checkout_real_post_preflight($plan, $input);
    red_checkout_real_post_assert(
        !empty($preflight['ready'])
            && ($preflight['method'] ?? '') === 'POST'
            && ($preflight['host'] ?? '') === 'api.stripe.com'
            && ($preflight['path'] ?? '') === '/v1/checkout/sessions',
        'exact future Stripe Checkout create target is closed'
    );
    red_checkout_real_post_assert(
        ($preflight['operation'] ?? '') === 'checkout.create-sandbox-real-post'
            && ($preflight['packageVersion'] ?? '') === '0.1.5'
            && red_addon_provider_contact_sha256(
                $preflight['requestSha256'] ?? null
            ),
        'request binds exact package, operation, and canonical hash'
    );
    red_checkout_real_post_assert(
        ($preflight['idempotencyKey'] ?? '')
            === 'redcms-checkout-' . str_repeat('b', 64)
            && strlen($preflight['idempotencyKey']) <= 255,
        'one high-entropy bounded idempotency key is mandatory'
    );
    red_checkout_real_post_assert(
        ($preflight['formFields']['mode'] ?? '') === 'payment'
            && ($preflight['formFields']['expires_at'] ?? 0) === 1787027400
            && ($preflight['formFields']['client_reference_id'] ?? '')
                === $input['checkout']['orderId'],
        'payment mode, bounded expiry, and client reference are exact'
    );
    red_checkout_real_post_assert(
        ($preflight['formFields']['line_items[0][price_data][unit_amount]'] ?? 0)
                === 1999
            && ($preflight['formFields']['line_items[0][quantity]'] ?? 0) === 2
            && ($preflight['formFields']['line_items[1][price_data][unit_amount]'] ?? 0)
                === 1899,
        'closed line items preserve exact minor-unit arithmetic'
    );
    red_checkout_real_post_assert(
        !empty($preflight['restrictedTestWriteKeyRequired'])
            && empty($preflight['credentialValueIncluded'])
            && empty($preflight['networkAccess'])
            && empty($preflight['providerContact'])
            && empty($preflight['providerMutation'])
            && empty($preflight['checkoutCreation'])
            && empty($preflight['executionPerformed']),
        'preflight declares future write authority but performs no effect'
    );
    $readOnly = $input;
    $readOnly['profile']['credentialMode'] = 'restricted_test_read';
    red_checkout_real_post_assert(
        empty(red_addon_checkout_real_post_preflight($plan, $readOnly)['ready']),
        'read-only profile cannot enter the real POST preflight'
    );
    $changed = $input;
    $changed['checkout']['amountMinor']++;
    red_checkout_real_post_assert(
        empty(red_addon_checkout_real_post_preflight($plan, $changed)['ready']),
        'changed input cannot borrow the synthetic plan hash'
    );
    $source = (string) file_get_contents(
        dirname(__DIR__)
            . '/includes/addon_sandbox_checkout_real_post_preflight_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'Authorization:', 'getenv(', 'putenv(', '$_SERVER', '$_POST',
        'php://input', 'shell_exec(', 'exec(', 'sleep(', 'usleep(',
        'red_addon_secret_resolve', 'red_addon_runtime_register_package',
        'red_addon_adapter_invoke_registered',
    ] as $forbidden) {
        red_checkout_real_post_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from pure real-POST preflight source'
        );
    }
    echo 'Sandbox Checkout P3E-9D0 real POST preflight self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
