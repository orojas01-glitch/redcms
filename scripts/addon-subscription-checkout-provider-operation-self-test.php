<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/addon_subscription_checkout_provider_operation_helpers.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};
$binding = [
    'storePackageId' => 'redcms.store-lite',
    'storePackageVersion' => '0.1.49',
    'storeService' => 'commerce.subscriptions',
    'stripePackageId' => 'redcms.store-lite-stripe-checkout',
    'stripePackageVersion' => '0.1.10',
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
$offerState = hash('sha256', json_encode(
    $offer,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
));
$intentState = str_repeat('1', 64);
$policy = [
    'apiVersion' => '2024-09-30.acacia',
    'successUrl' => 'https://shop.example.test/subscription/complete',
    'cancelUrl' => 'https://shop.example.test/subscription',
    'createdAtEpoch' => 1787630400,
    'expiresAtEpoch' => 1787632200,
];
$authority = [
    'authorized' => true,
    'authorizationSha256' => str_repeat('2', 64),
    'secretAvailabilitySha256' => str_repeat('3', 64),
    'issuedAtEpoch' => 1787630100,
    'expiresAtEpoch' => 1787630700,
    'maximumAttempts' => 1,
    'retryAuthorized' => false,
];
$subjectRecordId = 42;
$offerId = $offer['id'];
$intentReference = red_addon_subscription_intent_reference(
    $subjectRecordId,
    $offerId,
    $intentState,
    $offerState
);
$session = 'cs_test_SubscriptionProviderOperation1234';
$checkoutUrl = 'https://checkout.stripe.com/c/pay/' . $session
    . '#synthetic-provider-operation';

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
            . '/includes/addon_subscription_checkout_provider_operation_helpers.php'
    );
    $assert(
        !preg_match(
            '/\$_(?:GET|POST|COOKIE|SERVER|SESSION|ENV)|\b(?:mysqli|PDO|curl|fsockopen|getenv|header|setcookie)\b/',
            $source
        ),
        'provider coordinator has no request, database, secret, transport, or response primitive'
    );

    $calls = [];
    $serviceInvoker = function (
        string $service,
        string $operation,
        array $input
    ) use (
        &$calls,
        $invocation,
        $subjectRecordId,
        $offerId,
        $intentState,
        $offerState,
        $offer,
        $intentReference,
        $session
    ): array {
        $calls[] = 'service:' . $operation;
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
            'lastEventEvidenceSha256' => str_repeat('8', 64),
        ]);
    };
    $adapterCalls = 0;
    $adapterInvoker = function (
        string $adapter,
        string $operation,
        array $input
    ) use (
        &$calls,
        &$adapterCalls,
        $invocation,
        $intentReference,
        $session,
        $checkoutUrl
    ): array {
        $calls[] = 'adapter:' . $operation;
        $adapterCalls++;
        return $invocation(
            'redcms.store-lite-stripe-checkout',
            $operation,
            [
                'valid' => true,
                'status' => 'subscription_checkout_session_created',
                'packageId' => 'redcms.store-lite-stripe-checkout',
                'packageVersion' => '0.1.10',
                'operation' => $operation,
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
                'contractSha256' => str_repeat('6', 64),
                'requestSha256' => str_repeat('7', 64),
                'responseEvidenceSha256' => str_repeat('8', 64),
                'resultSha256' => str_repeat('9', 64),
                'networkAccess' => true,
                'providerContact' => true,
                'providerMutation' => true,
                'checkoutCreation' => true,
                'subscriptionCreation' => true,
                'payment' => false,
                'webhook' => false,
                'browserNavigation' => false,
                'retryAuthorized' => false,
            ]
        );
    };
    $journalState = 'absent';
    $journal = function (string $operation, array $evidence) use (
        &$calls,
        &$journalState
    ): array {
        $calls[] = 'journal:' . $operation;
        if ($operation === 'inspect') {
            return ['status' => $journalState];
        }
        if ($operation === 'start' && $journalState === 'absent') {
            $journalState = 'started';
            return [
                'status' => 'started',
                'executionStartStateSha256' =>
                    $evidence['executionStartStateSha256'],
            ];
        }
        if ($operation === 'complete' && $journalState === 'started') {
            $journalState = 'completed';
            return [
                'status' => 'completed',
                'resultSha256' => $evidence['resultSha256'],
            ];
        }
        return ['status' => 'refused'];
    };

    $result = red_addon_subscription_provider_operation(
        $binding,
        $subjectRecordId,
        $offerId,
        $policy,
        $authority,
        $serviceInvoker,
        $adapterInvoker,
        $journal
    );
    $assert(
        $result['valid']
            && $result['ready']
            && $result['status'] === 'real_redirect_ready'
            && $result['reason'] === 'real_redirect_ready',
        'exact authority and sealed provider outcome produce one real redirect-ready result'
    );
    $assert(
        $calls === [
            'service:subscription.checkout.load',
            'journal:inspect',
            'journal:start',
            'adapter:subscription.checkout.create-sandbox-real-post',
            'service:subscription.checkout.prepare',
            'journal:complete',
        ],
        'coordinator preserves the exact durable start, invoke, persist, result order'
    );
    $assert(
        $result['checkoutUrl'] === $checkoutUrl
            && $result['journalStarted']
            && $result['journalCompleted']
            && $result['networkAccess']
            && $result['providerContact']
            && $result['checkoutCreation']
            && $result['subscriptionCreation']
            && !$result['browserNavigation']
            && !$result['retryAuthorized'],
        'successful provider effects are explicit while browser and retry remain false'
    );
    $publicResponse = red_addon_subscription_checkout_public_response(
        [
            'completed' => true,
            'replayed' => false,
            'outcome' => 'accepted',
            'route' => 'redcms.store-lite/subscription-intent',
            'mutation' => 'redcms.store-lite/create-subscription-intent',
            'reason' => 'completed',
        ],
        $subjectRecordId,
        $offerId,
        $result
    );
    $assert(
        red_addon_subscription_checkout_public_response_valid($publicResponse)
            && $publicResponse['checkoutUrl'] === $checkoutUrl,
        'journal-complete real outcome can enter the existing redacted browser handoff'
    );

    $calls = [];
    $spent = red_addon_subscription_provider_operation(
        $binding,
        $subjectRecordId,
        $offerId,
        $policy,
        $authority,
        $serviceInvoker,
        $adapterInvoker,
        $journal
    );
    $assert(
        $spent['status'] === 'attempt_spent'
            && $spent['reason'] === 'attempt_spent_no_retry'
            && $adapterCalls === 1
            && $calls === [
                'service:subscription.checkout.load',
                'journal:inspect',
            ],
        'completed journal replay exposes no URL and never invokes the adapter again'
    );

    $badAuthority = $authority;
    $badAuthority['maximumAttempts'] = 2;
    $assert(
        red_addon_subscription_provider_operation(
            $binding,
            $subjectRecordId,
            $offerId,
            $policy,
            $badAuthority,
            $serviceInvoker,
            $adapterInvoker,
            $journal
        )['valid'] === false,
        'multi-attempt authority is refused before state loading'
    );
    $oldBinding = $binding;
    $oldBinding['stripePackageVersion'] = '0.1.9';
    $assert(
        red_addon_subscription_provider_operation(
            $oldBinding,
            $subjectRecordId,
            $offerId,
            $policy,
            $authority,
            $serviceInvoker,
            $adapterInvoker,
            $journal
        )['valid'] === false,
        'old adapter identity is refused before state loading'
    );

    $indeterminateState = 'absent';
    $indeterminateCalls = 0;
    $indeterminateJournal = function (
        string $operation,
        array $evidence
    ) use (&$indeterminateState): array {
        if ($operation === 'inspect') {
            return ['status' => $indeterminateState];
        }
        if ($operation === 'start') {
            $indeterminateState = 'started';
            return [
                'status' => 'started',
                'executionStartStateSha256' =>
                    $evidence['executionStartStateSha256'],
            ];
        }
        return ['status' => 'refused'];
    };
    $badAdapter = function () use (&$indeterminateCalls): array {
        $indeterminateCalls++;
        return ['invoked' => true, 'success' => false];
    };
    $indeterminate = red_addon_subscription_provider_operation(
        $binding,
        $subjectRecordId,
        $offerId,
        $policy,
        $authority,
        $serviceInvoker,
        $badAdapter,
        $indeterminateJournal
    );
    $again = red_addon_subscription_provider_operation(
        $binding,
        $subjectRecordId,
        $offerId,
        $policy,
        $authority,
        $serviceInvoker,
        $badAdapter,
        $indeterminateJournal
    );
    $assert(
        $indeterminate['status'] === 'indeterminate'
            && $indeterminate['reason']
                === 'provider_outcome_indeterminate'
            && $again['status'] === 'attempt_spent'
            && $indeterminateCalls === 1,
        'malformed post-start outcome permanently spends the single attempt'
    );

    echo 'Subscription Checkout provider operation passed '
        . $assertions . " assertions.\n";
    echo "No database, secret resolution, network, provider, Checkout, or browser action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
