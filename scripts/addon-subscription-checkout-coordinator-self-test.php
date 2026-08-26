<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/addon_subscription_checkout_coordinator_helpers.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$binding = static fn (): array => [
    'storePackageId' => 'redcms.store-lite',
    'storePackageVersion' => '0.1.50',
    'storeService' => 'commerce.subscriptions',
    'stripePackageId' => 'redcms.store-lite-stripe-checkout',
    'stripePackageVersion' => '0.1.14',
    'stripeAdapter' => 'redcms.store-lite-stripe-checkout/checkout',
];
$offer = [
    'id' => 'studio-membership-monthly',
    'productId' => 'studio-membership',
    'variantId' => null,
    'title' => 'Studio membership',
    'summary' => 'Monthly member access.',
    'currency' => 'USD',
    'priceMinor' => 2900,
    'billingPeriod' => 'monthly',
    'state' => 'published',
    'availability' => 'available',
    'buttonLabel' => 'Subscribe monthly',
];
$intentState = str_repeat('1', 64);
$offerState = hash('sha256', json_encode(
    $offer,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
));
$policy = [
    'apiVersion' => '2024-09-30.acacia',
    'successUrl' =>
        'https://shop.example.test/subscription/stripe-complete',
    'cancelUrl' => 'https://shop.example.test/subscription',
    'createdAtEpoch' => 1787630400,
    'expiresAtEpoch' => 1787632200,
];
$subjectRecordId = 42;
$offerId = $offer['id'];
$intentReference = red_addon_subscription_intent_reference(
    $subjectRecordId,
    $offerId,
    $intentState,
    $offerState
);
$session = 'cs_test_SubscriptionCoordinator1234';
$checkoutUrl = 'https://checkout.stripe.com/c/pay/' . $session
    . '#synthetic-fragment';
$synthetic = [
    'envelope' => [
        'statusCode' => 200,
        'contentType' => 'application/json',
        'bodyBytes' => 2048,
        'bodySha256' => str_repeat('2', 64),
        'requestId' => 'req_SubscriptionCoordinator',
        'tlsVersion' => 'TLSv1.3',
        'redirectCount' => 0,
    ],
    'projection' => [
        'id' => $session,
        'object' => 'checkout.session',
        'url' => $checkoutUrl,
        'mode' => 'subscription',
        'status' => 'open',
        'payment_status' => 'unpaid',
        'amount_total' => 2900,
        'currency' => 'usd',
        'client_reference_id' => $intentReference,
        'metadata' => [
            'redcms_intent_state_sha256' => $intentState,
            'redcms_offer_state_sha256' => $offerState,
        ],
        'livemode' => false,
        'expires_at' => 1787632200,
        'after_expiration' => null,
    ],
];

$invocation = static function (
    string $package,
    string $operation,
    array $data
): array {
    return [
        'invoked' => true,
        'success' => true,
        'adapter' => str_starts_with($operation, 'subscription.checkout.')
            && $package === 'redcms.store-lite-stripe-checkout'
                ? 'redcms.store-lite-stripe-checkout/checkout' : '',
        'service' => $package === 'redcms.store-lite'
            ? 'commerce.subscriptions' : '',
        'package' => $package,
        'operation' => $operation,
        'data' => $data,
        'error' => '',
        'reason' => 'completed',
    ];
};

try {
    $source = (string) file_get_contents(
        dirname(__DIR__)
            . '/includes/addon_subscription_checkout_coordinator_helpers.php'
    );
    $assert(
        !preg_match(
            '/\b(?:mysqli|PDO|curl|fsockopen|fopen|file_put_contents|getenv|header|setcookie)\b|\$_(?:GET|POST|COOKIE|SERVER|SESSION)/',
            $source
        ),
        'coordinator has no database, request, response, secret, or network path'
    );
    $assert(strlen($intentReference) === 37, 'core derives bounded intent reference');
    $assert(
        $intentReference === red_addon_subscription_intent_reference(
            $subjectRecordId,
            $offerId,
            $intentState,
            $offerState
        ),
        'intent reference is deterministic'
    );

    $serviceCalls = [];
    $adapterCalls = [];
    $serviceInvoker = function (
        string $service,
        string $operation,
        array $input
    ) use (
        &$serviceCalls,
        $invocation,
        $subjectRecordId,
        $offerId,
        $intentState,
        $offerState,
        $offer,
        $intentReference,
        $session
    ): array {
        $serviceCalls[] = [$service, $operation, $input];
        if ($operation === 'subscription.checkout.load') {
            return $invocation('redcms.store-lite', $operation, [
                'intent' => [
                    'subjectRecordId' => $subjectRecordId,
                    'offerId' => $offerId,
                    'intentStateSha256' => $intentState,
                    'offerStateSha256' => $offerState,
                    'status' => 'requested',
                ],
                'offer' => $offer,
            ]);
        }
        return $invocation('redcms.store-lite', $operation, [
            'status' => 'prepared',
            'intentReference' => $intentReference,
            'offerStateSha256' => $offerState,
            'subscriptionStatus' => 'pending',
            'entitlementStatus' => 'inactive',
            'providerSubscriptionRefSha256' => null,
            'currentPeriodEndEpoch' => null,
            'checkoutSessionRefSha256' => hash('sha256', $session),
            'lastEventEvidenceSha256' => str_repeat('4', 64),
        ]);
    };
    $adapterInvoker = function (
        string $adapter,
        string $operation,
        array $input
    ) use (&$adapterCalls, $invocation, $intentReference, $session, $checkoutUrl): array {
        $adapterCalls[] = [$adapter, $operation, $input];
        if ($operation === 'subscription.checkout.prepare-sandbox-offline') {
            return $invocation(
                'redcms.store-lite-stripe-checkout',
                $operation,
                [
                    'valid' => true,
                    'operation' => 'subscription.checkout.prepare-sandbox',
                    'contractSha256' => str_repeat('5', 64),
                    'requestSha256' => str_repeat('6', 64),
                    'intentReference' => $intentReference,
                    'networkAccess' => false,
                    'providerContact' => false,
                    'providerMutation' => false,
                    'checkoutCreation' => false,
                    'subscriptionCreation' => false,
                    'browserNavigation' => false,
                ]
            );
        }
        return $invocation(
            'redcms.store-lite-stripe-checkout',
            $operation,
            [
                'valid' => true,
                'intentReference' => $intentReference,
                'checkoutSessionRef' => $session,
                'checkoutUrl' => $checkoutUrl,
                'expiresAtEpoch' => 1787632200,
                'navigationMode' => 'location.assign',
                'transientOnly' => true,
                'persistCheckoutUrl' => false,
                'cacheControl' => 'no-store',
                'authorizationRequired' => true,
                'browserNavigationAuthorized' => false,
                'contractSha256' => str_repeat('5', 64),
                'responseEvidenceSha256' => str_repeat('4', 64),
                'resultSha256' => str_repeat('7', 64),
                'networkAccess' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'checkoutCreation' => false,
                'subscriptionCreation' => false,
                'browserNavigation' => false,
            ]
        );
    };
    $coordinated = red_addon_subscription_checkout_coordinate(
        $binding(),
        $subjectRecordId,
        $offerId,
        $policy,
        $synthetic,
        $serviceInvoker,
        $adapterInvoker
    );
    $assert(
        ($coordinated['valid'] ?? false) === true
            && ($coordinated['ready'] ?? false) === true
            && $coordinated['status'] === 'synthetic_redirect_ready',
        'exact typed stages produce one synthetic redirect-ready result'
    );
    $assert(
        $coordinated['checkoutUrl'] === $checkoutUrl
            && $coordinated['httpStatus'] === 303
            && $coordinated['cacheControl'] === 'no-store'
            && $coordinated['navigationMode'] === 'location.assign'
            && $coordinated['transientOnly'] === true
            && $coordinated['responseEmission'] === false,
        'redirect is transient no-store data and is not emitted'
    );
    $assert(
        !$coordinated['networkAccess']
            && !$coordinated['providerContact']
            && !$coordinated['providerMutation']
            && !$coordinated['checkoutCreation']
            && !$coordinated['subscriptionCreation']
            && !$coordinated['browserNavigation'],
        'all external and browser effects remain false'
    );
    $assert(
        count($serviceCalls) === 2
            && $serviceCalls[0][1] === 'subscription.checkout.load'
            && $serviceCalls[1][1] === 'subscription.checkout.prepare'
            && count($adapterCalls) === 2
            && $adapterCalls[0][1]
                === 'subscription.checkout.prepare-sandbox-offline'
            && $adapterCalls[1][1]
                === 'subscription.checkout.accept-sandbox-synthetic',
        'coordinator executes the exact four-stage sequence once'
    );
    $assert(
        $serviceCalls[1][2]['checkout']['checkoutSessionRefSha256']
            === hash('sha256', $session)
            && !str_contains(
                serialize($serviceCalls[1][2]),
                'checkout.stripe.com'
            ),
        'Store Lite receives only hashed Session evidence and no Checkout URL'
    );

    $changedBinding = $binding();
    $changedBinding['stripePackageVersion'] = '0.1.8';
    $assert(
        red_addon_subscription_checkout_coordinate(
            $changedBinding,
            $subjectRecordId,
            $offerId,
            $policy,
            $synthetic,
            $serviceInvoker,
            $adapterInvoker
        )['valid'] === false,
        'stale Stripe package identity is refused before invocation'
    );
    $evilAdapter = function (
        string $adapter,
        string $operation,
        array $input
    ) use ($adapterInvoker): array {
        $result = $adapterInvoker($adapter, $operation, $input);
        if ($operation === 'subscription.checkout.accept-sandbox-synthetic') {
            $result['data']['checkoutUrl'] =
                'https://evil.example.test/c/pay/'
                    . $result['data']['checkoutSessionRef'];
        }
        return $result;
    };
    $assert(
        red_addon_subscription_checkout_coordinate(
            $binding(),
            $subjectRecordId,
            $offerId,
            $policy,
            $synthetic,
            $serviceInvoker,
            $evilAdapter
        )['reason'] === 'stripe_synthetic_response_refused',
        'core independently refuses a foreign Checkout URL origin'
    );
    $networkAdapter = function (
        string $adapter,
        string $operation,
        array $input
    ) use ($adapterInvoker): array {
        $result = $adapterInvoker($adapter, $operation, $input);
        $result['data']['networkAccess'] = true;
        return $result;
    };
    $assert(
        red_addon_subscription_checkout_coordinate(
            $binding(),
            $subjectRecordId,
            $offerId,
            $policy,
            $synthetic,
            $serviceInvoker,
            $networkAdapter
        )['valid'] === false,
        'any claimed network effect is refused'
    );

    echo 'Subscription Checkout coordinator self-test passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
