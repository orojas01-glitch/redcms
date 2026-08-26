<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/addon_subscription_event_coordinator_helpers.php';

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

$binding = [
    'storePackageId' => 'redcms.store-lite',
    'storePackageVersion' => '0.1.50',
    'storeService' => 'commerce.subscriptions',
    'stripePackageId' => 'redcms.store-lite-stripe-checkout',
    'stripePackageVersion' => '0.1.13',
    'stripeAdapter' => 'redcms.store-lite-stripe-checkout/checkout',
];
$intentReference = 'sint_' . str_repeat('1', 32);
$offerState = str_repeat('2', 64);
$checkoutSha256 = str_repeat('3', 64);
$eventEvidence = str_repeat('4', 64);
$providerSha256 = str_repeat('5', 64);
$verifiedEvent = [
    'verification' => 'verified',
    'replayStatus' => 'unseen',
    'eventRef' => 'evt_SubscriptionCoordinator123456',
    'eventType' => 'checkout.session.completed',
    'intentReference' => $intentReference,
    'offerStateSha256' => $offerState,
    'checkoutSessionRef' => 'cs_test_SubscriptionCoordinator123456',
    'providerSubscriptionRef' => 'sub_SubscriptionCoordinator123456',
    'providerStatus' => 'complete_paid',
    'currentPeriodEndEpoch' => 1790308800,
    'eventEvidenceSha256' => $eventEvidence,
    'occurredAt' => 1787630500,
    'receivedAt' => 1787630600,
    'livemode' => false,
];

$invocation = static function (
    string $package,
    string $operation,
    array $data
): array {
    return [
        'invoked' => true,
        'success' => true,
        'adapter' => $package === 'redcms.store-lite-stripe-checkout'
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
            . '/includes/addon_subscription_event_coordinator_helpers.php'
    );
    $assert(
        !preg_match(
            '/\$_(?:GET|POST|COOKIE|SERVER|SESSION|ENV)|\b(?:mysqli|PDO|curl|fsockopen|getenv|header|setcookie)\b/',
            $source
        ),
        'event coordinator has no request, database, secret, transport, or response primitive'
    );

    $calls = [];
    $serviceInvoker = function (
        string $service,
        string $operation,
        array $input
    ) use (
        &$calls,
        $invocation,
        $intentReference,
        $offerState,
        $checkoutSha256,
        $eventEvidence,
        $providerSha256
    ): array {
        $calls[] = 'service:' . $operation;
        if ($operation === 'subscription.lifecycle.load') {
            return $invocation('redcms.store-lite', $operation, [
                'status' => 'found',
                'intentReference' => $intentReference,
                'offerStateSha256' => $offerState,
                'subscriptionStatus' => 'pending',
                'entitlementStatus' => 'inactive',
                'providerSubscriptionRefSha256' => null,
                'currentPeriodEndEpoch' => null,
                'checkoutSessionRefSha256' => $checkoutSha256,
                'lastEventEvidenceSha256' => str_repeat('6', 64),
            ]);
        }
        return $invocation('redcms.store-lite', $operation, [
            'status' => 'applied',
            'intentReference' => $intentReference,
            'offerStateSha256' => $offerState,
            'subscriptionStatus' => 'active',
            'entitlementStatus' => 'active',
            'providerSubscriptionRefSha256' => $providerSha256,
            'currentPeriodEndEpoch' => 1790308800,
            'checkoutSessionRefSha256' => $checkoutSha256,
            'lastEventEvidenceSha256' => $eventEvidence,
        ]);
    };
    $adapterInvoker = function (
        string $adapter,
        string $operation,
        array $input
    ) use (
        &$calls,
        $invocation,
        $intentReference,
        $offerState,
        $eventEvidence,
        $providerSha256
    ): array {
        $calls[] = 'adapter:' . $operation;
        return $invocation(
            'redcms.store-lite-stripe-checkout',
            $operation,
            [
                'valid' => true,
                'event' => [
                    'verification' => 'verified',
                    'replayStatus' => 'unseen',
                    'intentReference' => $intentReference,
                    'offerStateSha256' => $offerState,
                    'outcome' => 'activated',
                    'providerSubscriptionRefSha256' => $providerSha256,
                    'currentPeriodEndEpoch' => 1790308800,
                    'eventEvidenceSha256' => $eventEvidence,
                    'occurredAt' => 1787630500,
                ],
                'providerEventType' => 'checkout.session.completed',
                'providerEventRefSha256' => str_repeat('7', 64),
                'checkoutSessionRefSha256' => str_repeat('3', 64),
                'receivedAt' => 1787630600,
                'rawProviderReferenceIncluded' => false,
                'rawCheckoutReferenceIncluded' => false,
                'customerDataIncluded' => false,
                'errors' => [],
            ]
        );
    };

    $result = red_addon_subscription_event_coordinate(
        $binding,
        $intentReference,
        $verifiedEvent,
        $serviceInvoker,
        $adapterInvoker
    );
    $assert(
        $result['valid']
            && $result['status'] === 'applied'
            && $result['reason'] === 'subscription_event_applied',
        'authoritative current state and normalized event apply once'
    );
    $assert(
        $calls === [
            'service:subscription.lifecycle.load',
            'adapter:subscription.event.normalize-sandbox-verified',
            'service:subscription.event.apply',
        ],
        'event coordinator preserves load, normalize, apply order'
    );
    $assert(
        $result['subscriptionStatus'] === 'active'
            && $result['entitlementStatus'] === 'active'
            && $result['providerSubscriptionRefSha256'] === $providerSha256
            && $result['currentPeriodEndEpoch'] === 1790308800
            && $result['eventEvidenceSha256'] === $eventEvidence
            && red_addon_valid_sha256($result['lifecycleResultSha256']),
        'activation result contains only bounded lifecycle evidence'
    );
    $assert(
        !$result['signatureVerificationPerformed']
            && !$result['webhookIngress']
            && !$result['routeExposure']
            && !$result['secretResolution']
            && !$result['networkAccess']
            && !$result['providerContact']
            && !$result['payment']
            && !$result['browserNavigation']
            && !$result['deployment'],
        'all external and ingress effects remain false'
    );
    $encoded = json_encode(
        $result,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $assert(
        !str_contains($encoded, $verifiedEvent['eventRef'])
            && !str_contains(
                $encoded,
                $verifiedEvent['checkoutSessionRef']
            )
            && !str_contains(
                $encoded,
                $verifiedEvent['providerSubscriptionRef']
            ),
        'result exposes no raw provider event, Checkout, or Subscription reference'
    );

    $oldStore = $binding;
    $oldStore['storePackageVersion'] = '0.1.49';
    $assert(
        red_addon_subscription_event_coordinate(
            $oldStore,
            $intentReference,
            $verifiedEvent,
            $serviceInvoker,
            $adapterInvoker
        )['valid'] === false,
        'old Store Lite identity is refused'
    );
    $oldAdapter = $binding;
    $oldAdapter['stripePackageVersion'] = '0.1.10';
    $assert(
        red_addon_subscription_event_coordinate(
            $oldAdapter,
            $intentReference,
            $verifiedEvent,
            $serviceInvoker,
            $adapterInvoker
        )['valid'] === false,
        'old Stripe adapter identity is refused'
    );

    $loadFailure = static fn (
        string $service,
        string $operation,
        array $input
    ): array => [
        'invoked' => true,
        'success' => false,
        'package' => 'redcms.store-lite',
        'operation' => $operation,
        'data' => [],
        'error' => 'subscription_not_found',
        'reason' => 'handler_failed',
    ];
    $assert(
        red_addon_subscription_event_coordinate(
            $binding,
            $intentReference,
            $verifiedEvent,
            $loadFailure,
            $adapterInvoker
        )['reason'] === 'subscription_current_unavailable',
        'missing authoritative lifecycle stops before adapter invocation'
    );

    $adapterFailure = static fn (
        string $adapter,
        string $operation,
        array $input
    ): array => [
        'invoked' => true,
        'success' => false,
        'package' => 'redcms.store-lite-stripe-checkout',
        'operation' => $operation,
        'data' => [],
        'error' => 'subscription_verified_event_refused',
        'reason' => 'handler_failed',
    ];
    $assert(
        red_addon_subscription_event_coordinate(
            $binding,
            $intentReference,
            $verifiedEvent,
            $serviceInvoker,
            $adapterFailure
        )['reason'] === 'subscription_event_refused',
        'adapter refusal prevents Store Lite lifecycle mutation'
    );

    $badApplied = function (
        string $service,
        string $operation,
        array $input
    ) use ($serviceInvoker, $invocation): array {
        if ($operation === 'subscription.lifecycle.load') {
            return $serviceInvoker($service, $operation, $input);
        }
        return $invocation('redcms.store-lite', $operation, [
            'status' => 'applied',
            'intentReference' => $input['current']['intentReference'],
            'offerStateSha256' => $input['current']['offerStateSha256'],
            'subscriptionStatus' => 'active',
            'entitlementStatus' => 'inactive',
            'providerSubscriptionRefSha256' =>
                $input['event']['providerSubscriptionRefSha256'],
            'currentPeriodEndEpoch' =>
                $input['event']['currentPeriodEndEpoch'],
            'checkoutSessionRefSha256' => str_repeat('3', 64),
            'lastEventEvidenceSha256' =>
                $input['event']['eventEvidenceSha256'],
        ]);
    };
    $assert(
        red_addon_subscription_event_coordinate(
            $binding,
            $intentReference,
            $verifiedEvent,
            $badApplied,
            $adapterInvoker
        )['reason'] === 'subscription_event_apply_failed',
        'malformed Store Lite postcondition is contained'
    );

    echo 'Subscription event coordinator passed '
        . $assertions . " assertions.\n";
    echo "No request, signature verification, secret, route, database, network, Stripe, webhook, payment, browser, or deployment action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

exit(0);
