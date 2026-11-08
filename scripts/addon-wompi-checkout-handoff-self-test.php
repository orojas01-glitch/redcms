<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__)
    . '/includes/addon_public_mutation_endpoint_helpers.php';
require_once dirname(__DIR__)
    . '/includes/addon_wompi_checkout_return_helpers.php';
require_once dirname(__DIR__) . '/includes/addon_runtime_secret_helpers.php';

$assertions = 0;
$assert = static function (
    bool $condition,
    string $message
) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

// Synthetic values only. None of these is a real Wompi key.
$publicKey = 'pub_test_' . str_repeat('P', 16);
$integrity = 'test_integrity_' . str_repeat('I', 16);
$origin = 'https://shop.example.co';
$now = 1794000000;
$subjectRecordId = 42;
$orderId = 'ord_' . str_repeat('cd', 16);
$order = [
    'orderId' => $orderId,
    'snapshotSha256' => hash('sha256', 'synthetic-order'),
    'paymentMethod' => 'wompi_checkout',
    'paymentKind' => 'hosted',
    'currency' => 'COP',
    'totalMinor' => 2490000,
    'orderStatus' => 'pending',
    'paymentStatus' => 'pending',
    'fulfillmentStatus' => 'unfulfilled',
];
$access = new RED_Addon_Runtime_Secret_Access(
    'redcms.store-lite-wompi',
    ['wompi.integrity-key' => $integrity]
);
$calls = [];
$invoker = static function (?array $found) use (&$calls): Closure {
    return static function ($service, $operation, $input) use (
        &$calls,
        $found
    ): array {
        $calls[] = [$service, $operation, $input];
        return [
            'invoked' => true,
            'success' => true,
            'data' => [
                'found' => $found !== null,
                'order' => $found ?? [],
            ],
            'error' => '',
            'reason' => 'completed',
        ];
    };
};
// Mirrors the W1 planner contract so the composition is tested without the
// package; the real planner is exercised below when its root is supplied.
$syntheticPlanner = static function (
    string $environment,
    string $key,
    string $secret,
    array $planOrder,
    string $redirectUrl,
    ?string $expirationTime,
    int $time
): array {
    $amount = (string) $planOrder['amountMinor'];
    $fields = [
        ['public-key', $key],
        ['currency', 'COP'],
        ['amount-in-cents', $amount],
        ['reference', $planOrder['orderId']],
        ['signature:integrity', hash(
            'sha256',
            $planOrder['orderId'] . $amount . 'COP' . $expirationTime . $secret
        )],
        ['redirect-url', $redirectUrl],
        ['expiration-time', $expirationTime],
    ];
    return [
        'valid' => $environment === 'sandbox',
        'environment' => $environment,
        'action' => 'https://checkout.wompi.co/p/',
        'fields' => $fields,
        'errors' => [],
    ];
};

// Targets and gates.
$assert(
    red_addon_wompi_checkout_public_runtime_target(
        '/addons/redcms/store-lite/guest-checkout'
    )
        && !red_addon_wompi_checkout_public_runtime_target(
            '/addons/redcms/store-lite/subscription-intent'
        )
        && !red_addon_wompi_checkout_public_runtime_target(null),
    'only the guest-checkout route is a Wompi handoff target'
);
putenv('RED_WOMPI_CHECKOUT_SANDBOX_ENABLED');
$assert(
    !red_addon_wompi_checkout_public_runtime_enabled(),
    'the Sandbox handoff is disabled by default'
);
$assert(
    red_addon_wompi_checkout_public_runtime_prepare(
        null,
        dirname(__DIR__),
        '/addons/redcms/store-lite/subscription-intent',
        []
    )['applies'] === false,
    'other routes never enter the Wompi pre-dispatch check'
);

// Happy path composition.
$calls = [];
$handoff = red_addon_wompi_checkout_public_runtime_handoff(
    $subjectRecordId,
    $publicKey,
    $access,
    $origin,
    $invoker($order),
    $syntheticPlanner,
    $now
);
$response = $handoff['response'];
$assert(
    $handoff['reason'] === 'handoff_ready'
        && red_addon_wompi_checkout_public_response_valid($response)
        && red_addon_public_mutation_endpoint_response_valid($response),
    'a pending COP Wompi order yields one valid closed handoff response'
);
$assert(
    $calls === [[
        'commerce.orders',
        'payment.order.handoff',
        ['subjectRecordId' => $subjectRecordId],
    ]],
    'the handoff reads exactly one order through payment.order.handoff'
);
$query = [];
parse_str((string) parse_url($response['checkoutUrl'], PHP_URL_QUERY), $query);
$expiry = gmdate('Y-m-d\TH:i:s', $now + 1800) . '.000Z';
$assert(
    str_starts_with($response['checkoutUrl'], 'https://checkout.wompi.co/p/?')
        && ($query['public-key'] ?? null) === $publicKey
        && ($query['currency'] ?? null) === 'COP'
        && ($query['amount-in-cents'] ?? null) === '2490000'
        && ($query['reference'] ?? null) === $orderId
        && ($query['redirect-url'] ?? null)
            === $origin . '/checkout/wompi-return'
        && ($query['expiration-time'] ?? null) === $expiry
        && ($query['signature:integrity'] ?? null) === hash(
            'sha256',
            $orderId . '2490000COP' . $expiry . $integrity
        ),
    'the checkout URL carries the exact order, return URL, expiry and signature'
);
$assert(
    !str_contains($response['body'], $integrity)
        && !str_contains(serialize($response), $integrity)
        && json_decode($response['body'], true) === [
            'ok' => true,
            'outcome' => 'wompi_checkout_ready',
            'checkoutUrl' => $response['checkoutUrl'],
            'navigationMode' => 'location.assign',
        ]
        && $response['headers']['Cache-Control'] === 'no-store',
    'the browser receives the signature but never the integrity key'
);

// Refusals.
foreach ([
    'not found' => null,
    'other currency' => array_replace($order, ['currency' => 'USD']),
    'other method' => array_replace(
        $order,
        ['paymentMethod' => 'pay_on_receipt', 'paymentKind' => 'deferred']
    ),
    'already paid' => array_replace(
        $order,
        ['orderStatus' => 'paid', 'paymentStatus' => 'paid']
    ),
] as $label => $candidate) {
    $refused = red_addon_wompi_checkout_public_runtime_handoff(
        $subjectRecordId,
        $publicKey,
        $access,
        $origin,
        $invoker($candidate),
        $syntheticPlanner,
        $now
    );
    $assert(
        $refused === ['response' => null, 'reason' => 'order_unavailable'],
        'handoff refuses an order that is ' . $label
    );
}
$assert(
    red_addon_wompi_checkout_public_runtime_handoff(
        $subjectRecordId,
        $publicKey,
        new RED_Addon_Runtime_Secret_Access(
            'redcms.store-lite-wompi',
            ['wompi.private-key' => 'prv_test_' . str_repeat('X', 16)]
        ),
        $origin,
        $invoker($order),
        $syntheticPlanner,
        $now
    )['reason'] === 'integrity_key_unavailable',
    'handoff refuses without the integrity key'
);
$assert(
    red_addon_wompi_checkout_public_runtime_handoff(
        $subjectRecordId,
        $publicKey,
        $access,
        $origin,
        $invoker($order),
        static fn (...$arguments): array => [
            'valid' => false,
            'errors' => ['public_key_environment_mismatch'],
        ],
        $now
    )['reason'] === 'plan_public_key_environment_mismatch',
    'a refused plan is reported by its first planner error'
);

// URL validation.
$url = $response['checkoutUrl'];
foreach ([
    'production key' => str_replace('pub_test_', 'pub_prod_', $url),
    'other host' => str_replace('checkout.wompi.co', 'evil.example', $url),
    'extra field' => $url . '&x=1',
    'foreign return' => str_replace(
        rawurlencode('/checkout/wompi-return'),
        rawurlencode('/elsewhere'),
        $url
    ),
] as $label => $candidate) {
    $assert(
        !red_addon_wompi_checkout_public_url_valid($candidate)
            && !red_addon_wompi_checkout_public_response_valid(
                array_replace($response, ['checkoutUrl' => $candidate])
            ),
        'checkout URL validation refuses a ' . $label
    );
}

// Return page.
$assert(
    red_addon_wompi_checkout_return(
        'GET',
        '/checkout/wompi-return?id=1-2-3&env=test'
    )['claimed'] === false,
    'the return page stays unclaimed while the gate is off'
);
putenv('RED_WOMPI_CHECKOUT_SANDBOX_ENABLED=1');
$return = red_addon_wompi_checkout_return(
    'GET',
    '/checkout/wompi-return?id=12345-1794000000-99999&env=test'
);
$assert(
    red_addon_subscription_checkout_return_valid($return)
        && !str_contains($return['body'], '12345-1794000000-99999')
        && str_contains($return['body'], 'Payment in review')
        && red_addon_wompi_checkout_return('POST', '/checkout/wompi-return')
            ['claimed'] === false
        && red_addon_wompi_checkout_return('GET', '/checkout/wompi-returnx')
            ['claimed'] === false,
    'the return page is a fixed no-store page that ignores the Wompi query'
);
putenv('RED_WOMPI_CHECKOUT_SANDBOX_ENABLED');

// Browser controller.
$controller = (string) file_get_contents(
    dirname(__DIR__) . '/js/public-addon-mutation.js'
);
$assert(
    str_contains($controller, "payload.outcome === 'wompi_checkout_ready'")
        && str_contains($controller, "url.hostname !== 'checkout.wompi.co'")
        && str_contains($controller, "redirect.pathname !== '/checkout/wompi-return'")
        && str_contains($controller, "redirect.origin !== window.location.origin"),
    'the browser follows only a Sandbox Wompi URL that returns to this site'
);

// Real W1 planner, when the package checkout is supplied.
$packageRoot = getenv('RED_WOMPI_PACKAGE_ROOT');
if (is_string($packageRoot) && $packageRoot !== '') {
    require_once rtrim($packageRoot, '/') . '/WompiWebCheckoutPlanner.php';
    $real = red_addon_wompi_checkout_public_runtime_handoff(
        $subjectRecordId,
        $publicKey,
        $access,
        $origin,
        $invoker($order),
        ['RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner', 'plan'],
        $now
    );
    $assert(
        $real['reason'] === 'handoff_ready'
            && $real['response']['checkoutUrl'] === $response['checkoutUrl'],
        'the real W1 planner produces the same checkout URL'
    );
}

echo 'Wompi checkout handoff self-test passed: ' . $assertions
    . " assertions.\n";
echo "No database, secret resolution, network, Wompi contact, payment, "
    . "browser navigation, deployment, or live-mode action occurred.\n";
