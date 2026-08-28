<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/addon_subscription_event_webhook_handler_helpers.php';

$adapterRoot = realpath(
    getenv('RED_STRIPE_ADAPTER_ROOT')
        ?: dirname(__DIR__)
            . '/../redcms-store-lite-stripe-checkout/package'
);
if (!is_string($adapterRoot)) {
    fwrite(STDERR, "Stripe adapter package is unavailable.\n");
    exit(1);
}
require_once $adapterRoot . '/StripeBoundedJsonDecoder.php';
require_once $adapterRoot . '/StripeSandboxWebhookSignatureEnvelope.php';
require_once $adapterRoot
    . '/StripeSandboxSubscriptionRawEventProjector.php';
require_once $adapterRoot
    . '/StripeSandboxSubscriptionVerifiedEventContract.php';
require_once $adapterRoot . '/StripeSubscriptionEventReceiptPlanner.php';

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
    'stripePackageVersion' => '0.1.18',
    'stripeAdapter' => 'redcms.store-lite-stripe-checkout/checkout',
];
$secret = 'whsec_synthetic_delivery_coordinator_123456789';
$intent = 'sint_' . str_repeat('1', 32);
$offer = str_repeat('2', 64);
$checkout = 'cs_test_DeliveryCoordinator123456';
$subscription = 'sub_DeliveryCoordinator123456';
$periodEnd = 1790308800;
$receivedAt = 1787630600;

$makeRequest = static function (
    string $eventRef,
    array $metadata,
    string $apiVersion = '2024-09-30.acacia'
) use (
    $secret,
    $intent,
    $offer,
    $checkout,
    $subscription,
    $periodEnd,
    $receivedAt
): array {
    $event = [
        'id' => $eventRef,
        'object' => 'event',
        'api_version' => $apiVersion,
        'created' => 1787630500,
        'data' => ['object' => [
            'id' => $checkout,
            'object' => 'checkout.session',
            'client_reference_id' => $intent,
            'metadata' => $metadata,
            'status' => 'complete',
            'payment_status' => 'paid',
            'subscription' => [
                'id' => $subscription,
                'current_period_end' => $periodEnd,
            ],
            'customer_details' => [
                'email' => 'private@example.test',
                'address' => ['line1' => 'Private'],
            ],
        ]],
        'livemode' => false,
        'type' => 'checkout.session.completed',
    ];
    $rawBody = json_encode(
        $event,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $signature = hash_hmac(
        'sha256',
        $receivedAt . '.' . $rawBody,
        $secret
    );
    return [
        'rawBody' => $rawBody,
        'signatureHeader' => 't=' . $receivedAt . ',v1=' . $signature,
        'receivedAt' => $receivedAt,
    ];
};

$makeDeferredRequest = static function (
    string $eventRef
) use (
    $makeRequest,
    $secret,
    $offer,
    $subscription,
    $receivedAt
): array {
    $request = $makeRequest(
        $eventRef,
        ['redcms_offer_state_sha256' => $offer],
        '2026-07-29.dahlia'
    );
    $event = json_decode(
        $request['rawBody'],
        true,
        64,
        JSON_THROW_ON_ERROR
    );
    $event['data']['object']['subscription'] = $subscription;
    $rawBody = json_encode(
        $event,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $signature = hash_hmac(
        'sha256',
        $receivedAt . '.' . $rawBody,
        $secret
    );
    return [
        'rawBody' => $rawBody,
        'signatureHeader' => 't=' . $receivedAt . ',v1=' . $signature,
        'receivedAt' => $receivedAt,
    ];
};

$resignRequest = static function (
    array $request,
    int $newReceivedAt
) use ($secret): array {
    $rawBody = $request['rawBody'];
    return [
        'rawBody' => $rawBody,
        'signatureHeader' => 't=' . $newReceivedAt . ',v1=' . hash_hmac(
            'sha256',
            $newReceivedAt . '.' . $rawBody,
            $secret
        ),
        'receivedAt' => $newReceivedAt,
    ];
};

$request = $makeRequest(
    'evt_DeliveryCoordinatorApplied123',
    ['redcms_offer_state_sha256' => $offer]
);
$signatureCalls = 0;
$projectorCalls = 0;
$signatureVerifier = static function (
    string $rawBody,
    string $signatureHeader,
    int $received
) use (&$signatureCalls, $secret): array {
    $signatureCalls++;
    return RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
        verify($rawBody, $signatureHeader, $secret, $received);
};
$eventProjector = static function (
    array $envelope,
    string $rawBody
) use (&$projectorCalls): array {
    $projectorCalls++;
    $decoded = RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode(
        $rawBody
    );
    return RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector::
        project($envelope, $decoded['value'] ?? []);
};

$rows = [];
$failCompletionOnce = false;
$journalCalls = [];
$journalInvoker = static function (
    string $operation,
    array $evidence
) use (&$rows, &$failCompletionOnce, &$journalCalls): array {
    $journalCalls[] = $operation;
    $key = $evidence['eventRefSha256'];
    if ($operation === 'inspect') {
        return $rows[$key] ??
            red_addon_subscription_event_journal_result('absent');
    }
    if ($operation === 'claim') {
        if (isset($rows[$key])) {
            return red_addon_subscription_event_journal_result();
        }
        $rows[$key] = red_addon_subscription_event_journal_result(
            'verified'
        );
        $rows[$key]['claimStateSha256'] =
            $evidence['claimStateSha256'];
        return $rows[$key];
    }
    if ($operation === 'complete') {
        if ($failCompletionOnce) {
            $failCompletionOnce = false;
            return red_addon_subscription_event_journal_result();
        }
        if (($rows[$key]['status'] ?? '') !== 'verified') {
            return red_addon_subscription_event_journal_result();
        }
        $rows[$key]['status'] = $evidence['status'];
        $rows[$key]['eventEvidenceSha256'] =
            $evidence['eventEvidenceSha256'];
        $rows[$key]['lifecycleResultSha256'] =
            $evidence['lifecycleResultSha256'];
        return $rows[$key];
    }
    return red_addon_subscription_event_journal_result();
};

$initialState = [
    'status' => 'found',
    'intentReference' => $intent,
    'offerStateSha256' => $offer,
    'subscriptionStatus' => 'pending',
    'entitlementStatus' => 'inactive',
    'providerSubscriptionRefSha256' => null,
    'currentPeriodEndEpoch' => null,
    'checkoutSessionRefSha256' => hash('sha256', $checkout),
    'lastEventEvidenceSha256' => str_repeat('3', 64),
];
$state = $initialState;
$loadCalls = 0;
$applyCalls = 0;
$adapterCalls = 0;
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
$serviceInvoker = static function (
    string $service,
    string $operation,
    array $input
) use (
    &$state,
    &$loadCalls,
    &$applyCalls,
    $invocation
): array {
    if ($operation === 'subscription.lifecycle.load') {
        $loadCalls++;
        return $invocation('redcms.store-lite', $operation, $state);
    }
    $applyCalls++;
    $event = $input['event'];
    $target = [
        'activated' => ['active', 'active'],
        'renewed' => ['active', 'active'],
        'past_due' => ['past_due', 'revoked'],
        'canceled' => ['canceled', 'revoked'],
        'expired' => ['expired', 'inactive'],
    ][$event['outcome']];
    $state = array_replace($state, [
        'status' => 'found',
        'subscriptionStatus' => $target[0],
        'entitlementStatus' => $target[1],
        'providerSubscriptionRefSha256' =>
            $event['providerSubscriptionRefSha256'],
        'currentPeriodEndEpoch' => $event['currentPeriodEndEpoch'],
        'lastEventEvidenceSha256' => $event['eventEvidenceSha256'],
    ]);
    return $invocation('redcms.store-lite', $operation, array_replace(
        $state,
        ['status' => 'applied']
    ));
};
$adapterInvoker = static function (
    string $adapter,
    string $operation,
    array $input
) use (&$adapterCalls, $invocation): array {
    $adapterCalls++;
    $normalized =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize($input['expected'], $input['verifiedEvent']);
    return ($normalized['valid'] ?? false)
        ? $invocation(
            'redcms.store-lite-stripe-checkout',
            $operation,
            $normalized
        )
        : [
            'invoked' => true,
            'success' => false,
            'package' => 'redcms.store-lite-stripe-checkout',
            'operation' => $operation,
            'data' => [],
            'error' => 'subscription_verified_event_refused',
            'reason' => 'handler_failed',
        ];
};

try {
    $source = (string) file_get_contents(
        dirname(__DIR__)
            . '/includes/addon_subscription_event_delivery_coordinator_helpers.php'
    );
    $assert(
        !preg_match(
            '/\$_(?:GET|POST|COOKIE|SERVER|SESSION|ENV)'
                . '|\b(?:mysqli|PDO|curl|fsockopen|getenv|header|setcookie)\b/',
            $source
        ),
        'delivery coordinator has no request global, database, network, secret, or response primitive'
    );
    $handlerSource = (string) file_get_contents(
        dirname(__DIR__)
            . '/includes/addon_subscription_event_webhook_handler_helpers.php'
    );
    $assert(
        !preg_match(
            '/\$_(?:GET|POST|COOKIE|SERVER|SESSION|ENV)'
                . '|\b(?:mysqli|PDO|curl|fsockopen|getenv|header|setcookie)\b/',
            $handlerSource
        ),
        'webhook handler composition has no request global, database, network, or response primitive'
    );

    $dahliaRequest = $makeRequest(
        'evt_DeliveryCoordinatorDahlia123',
        ['redcms_offer_state_sha256' => $offer],
        '2026-07-29.dahlia'
    );
    $dahliaEnvelope = $signatureVerifier(
        $dahliaRequest['rawBody'],
        $dahliaRequest['signatureHeader'],
        $dahliaRequest['receivedAt']
    );
    $assert(
        ($dahliaEnvelope['valid'] ?? false) === true
            && red_addon_subscription_delivery_envelope_valid(
                $dahliaEnvelope,
                $dahliaRequest['rawBody'],
                $dahliaRequest['receivedAt']
            ),
        'delivery coordinator accepts the deployed Dahlia Stripe API version'
    );
    $unsupportedEnvelope = array_replace(
        $dahliaEnvelope,
        ['apiVersion' => '2026-08-27.unsupported']
    );
    $assert(
        !red_addon_subscription_delivery_envelope_valid(
            $unsupportedEnvelope,
            $dahliaRequest['rawBody'],
            $dahliaRequest['receivedAt']
        ),
        'delivery coordinator refuses API versions outside the exact allowlist'
    );
    $signatureCalls = 0;

    $first = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $request,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 10
    );
    $assert(
        $first['valid']
            && $first['status'] === 'applied'
            && $first['stage'] === 'completed'
            && $first['signatureVerified']
            && $first['journalClaimed']
            && $first['eventProjected']
            && $first['lifecycleApplied']
            && $first['journalCompleted']
            && !$first['restartable'],
        'verified delivery claims, projects, applies, and completes once'
    );
    $assert(
        $journalCalls === ['inspect', 'claim', 'complete']
            && $signatureCalls === 1
            && $projectorCalls === 1
            && $loadCalls === 1
            && $adapterCalls === 1
            && $applyCalls === 1,
        'first delivery preserves the exact four-stage call order'
    );
    $envelope = $signatureVerifier(
        $request['rawBody'],
        $request['signatureHeader'],
        $request['receivedAt']
    );
    $plannedClaim = RED_CMS_Store_Lite_Stripe_Subscription_Event_Receipt_Planner::
        claim([
            'eventRefSha256' => $envelope['eventRefSha256'],
            'rawBodySha256' => $envelope['rawBodySha256'],
            'signatureEvidenceSha256' =>
                $envelope['signatureEvidenceSha256'],
            'eventType' => $envelope['eventType'],
            'signedAt' => $envelope['signedAt'],
            'receivedAt' => $envelope['receivedAt'],
        ]);
    $assert(
        ($plannedClaim['valid'] ?? false) === true
            && ($plannedClaim['record']['claimStateSha256'] ?? '')
                === $first['claimStateSha256'],
        'core claim hash is identical to the adapter receipt planner'
    );
    $encoded = json_encode(
        $first,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $assert(
        !str_contains($encoded, $request['rawBody'])
            && !str_contains($encoded, $request['signatureHeader'])
            && !str_contains($encoded, $secret)
            && !str_contains($encoded, 'private@example.test')
            && !$first['rawBodyIncluded']
            && !$first['signatureHeaderIncluded']
            && !$first['secretIncluded']
            && !$first['customerDataIncluded'],
        'result excludes raw request, secret, and customer data'
    );

    $counts = [$projectorCalls, $loadCalls, $adapterCalls, $applyCalls];
    $replay = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $request,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 20
    );
    $assert(
        $replay['valid']
            && $replay['status'] === 'replayed'
            && $replay['journalCompleted']
            && $replay['replayed']
            && $replay['lifecycleResultSha256']
                === $first['lifecycleResultSha256'],
        'completed receipt replays its exact result hash'
    );
    $assert(
        [$projectorCalls, $loadCalls, $adapterCalls, $applyCalls] === $counts,
        'completed replay stops before projection and lifecycle calls'
    );

    $rows = [];
    $journalCalls = [];
    $state = $initialState;
    $recoveryRequest = $makeRequest(
        'evt_DeliveryCoordinatorRecovery123',
        ['redcms_offer_state_sha256' => $offer]
    );
    $failCompletionOnce = true;
    $beforeApply = $applyCalls;
    $interrupted = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $recoveryRequest,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 30
    );
    $assert(
        !$interrupted['valid']
            && $interrupted['status'] === 'verified'
            && $interrupted['stage'] === 'journal_completion'
            && $interrupted['restartable']
            && $interrupted['lifecycleApplied']
            && !$interrupted['journalCompleted'],
        'post-lifecycle completion failure remains explicitly restartable'
    );
    $interruptedHash = $interrupted['lifecycleResultSha256'];
    $beforeRecoveryAdapter = $adapterCalls;
    $recoveryRetryRequest = $resignRequest(
        $recoveryRequest,
        $receivedAt + 120
    );
    $recovered = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $recoveryRetryRequest,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 130
    );
    $assert(
        $recovered['valid']
            && $recovered['status'] === 'applied'
            && $recovered['lifecycleReplayed']
            && !$recovered['lifecycleApplied']
            && $recovered['journalCompleted']
            && $recovered['lifecycleResultSha256'] === $interruptedHash,
        'retry reconstructs the exact lifecycle result and closes the receipt'
    );
    $assert(
        $applyCalls === $beforeApply + 1
            && $adapterCalls === $beforeRecoveryAdapter,
        'recovery performs no second Store Lite mutation or adapter normalization'
    );

    $badSignature = $request;
    $badSignature['signatureHeader'] =
        't=' . $receivedAt . ',v1=' . str_repeat('0', 64);
    $rowCount = count($rows);
    $refused = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $badSignature,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 50
    );
    $assert(
        !$refused['valid']
            && $refused['stage'] === 'signature_verification'
            && !$refused['journalClaimed']
            && count($rows) === $rowCount,
        'invalid signature stops before journal or lifecycle state'
    );

    $malformedRequest = $makeRequest(
        'evt_DeliveryCoordinatorMalformed123',
        ['redcms_offer_state_sha256' => 'bad']
    );
    $malformed = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $malformedRequest,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 60
    );
    $assert(
        !$malformed['valid']
            && $malformed['status'] === 'verified'
            && $malformed['stage'] === 'event_projection'
            && $malformed['restartable']
            && !$malformed['eventProjected']
            && !$malformed['lifecycleApplied'],
        'signature-valid malformed correlation remains claimed and fail-closed'
    );

    $state = array_replace($initialState, [
        'offerStateSha256' => str_repeat('9', 64),
    ]);
    $refusedRequest = $makeRequest(
        'evt_DeliveryCoordinatorRefused1234',
        ['redcms_offer_state_sha256' => $offer]
    );
    $beforeRefusedApply = $applyCalls;
    $lifecycleRefused = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $refusedRequest,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 70
    );
    $assert(
        !$lifecycleRefused['valid']
            && $lifecycleRefused['status'] === 'refused'
            && $lifecycleRefused['journalCompleted']
            && !$lifecycleRefused['restartable']
            && $applyCalls === $beforeRefusedApply,
        'valid projected intent with lifecycle mismatch closes as refused'
    );
    $refusedReplay = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $refusedRequest,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 80
    );
    $assert(
        !$refusedReplay['valid']
            && $refusedReplay['status'] === 'refused'
            && $refusedReplay['replayed']
            && $refusedReplay['reason']
                === 'subscription_event_previously_refused',
        'completed refusal replays without lifecycle access'
    );
    $assert(
        !$first['routeExposure']
            && !$first['networkAccess']
            && !$first['providerContact']
            && !$first['payment']
            && !$first['browserNavigation']
            && !$first['deployment'],
        'coordinator exposes no route or external effect'
    );

    $manifest = json_decode(
        (string) file_get_contents($adapterRoot . '/addon.json'),
        true,
        64,
        JSON_THROW_ON_ERROR
    );
    $route = 'redcms.store-lite-stripe-checkout/provider-events';
    $package = 'redcms.store-lite-stripe-checkout';
    $webhookAccess = new RED_Addon_Runtime_Secret_Access(
        $package,
        ['stripe.webhook-secret' => $secret]
    );
    $webhookSignatureVerifier = static function (
        string $rawBody,
        string $signatureHeader,
        string $endpointSecret,
        int $received
    ): array {
        return RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $rawBody,
                $signatureHeader,
                $endpointSecret,
                $received
            );
    };
    $completedAt = $receivedAt + 90;
    $webhookHandler = static function (
        RED_Addon_Webhook_Request $webhookRequest
    ) use (
        $binding,
        $webhookSignatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $completedAt
    ): RED_Addon_Webhook_Result {
        return red_addon_subscription_event_webhook_handle(
            $webhookRequest,
            $binding,
            $webhookSignatureVerifier,
            $eventProjector,
            $journalInvoker,
            $serviceInvoker,
            $adapterInvoker,
            $completedAt
        );
    };

    $rows = [];
    $state = $initialState;
    $failCompletionOnce = false;
    $routeRequest = $makeRequest(
        'evt_DeliveryCoordinatorRoute123456',
        ['redcms_offer_state_sha256' => $offer]
    );
    $beforeRouteApply = $applyCalls;
    $routed = red_addon_webhook_invoke_registered(
        $route,
        $routeRequest['rawBody'],
        $routeRequest['signatureHeader'],
        $routeRequest['receivedAt'],
        $package,
        $webhookHandler,
        $manifest,
        $webhookAccess
    );
    $assert(
        $routed['invoked']
            && $routed['success']
            && $routed['statusCode'] === 200
            && ($routed['data']['acknowledged'] ?? false)
            && ($routed['data']['outcome'] ?? '')
                === 'subscription_event_applied'
            && ($routed['data']['applied'] ?? false)
            && $applyCalls === $beforeRouteApply + 1,
        'manifest-owned internal boundary applies and acknowledges one event'
    );
    $tamperedDelivery = $first;
    $tamperedDelivery['networkAccess'] = true;
    $foreignTypeDelivery = $first;
    $foreignTypeDelivery['providerEventType'] = 'charge.succeeded';
    $assert(
        red_addon_subscription_event_webhook_data($tamperedDelivery) === null
            && red_addon_subscription_event_webhook_data(
                $foreignTypeDelivery
            ) === null,
        'acknowledgement refuses external-effect claims and foreign event types'
    );
    $routeEncoded = json_encode(
        $routed,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $assert(
        !str_contains($routeEncoded, $routeRequest['rawBody'])
            && !str_contains(
                $routeEncoded,
                $routeRequest['signatureHeader']
            )
            && !str_contains($routeEncoded, $secret)
            && !str_contains($routeEncoded, 'private@example.test')
            && ($routed['data']['rawBodyIncluded'] ?? true) === false
            && ($routed['data']['secretIncluded'] ?? true) === false,
        'acknowledgement contains only bounded hash evidence'
    );
    $routeReplay = red_addon_webhook_invoke_registered(
        $route,
        $routeRequest['rawBody'],
        $routeRequest['signatureHeader'],
        $routeRequest['receivedAt'],
        $package,
        $webhookHandler,
        $manifest,
        $webhookAccess
    );
    $assert(
        $routeReplay['success']
            && ($routeReplay['data']['outcome'] ?? '')
                === 'subscription_event_replayed'
            && ($routeReplay['data']['replayed'] ?? false)
            && $applyCalls === $beforeRouteApply + 1,
        'completed route delivery replays without another lifecycle write'
    );

    $badRouteSignature = $routeRequest;
    $badRouteSignature['signatureHeader'] =
        't=' . $receivedAt . ',v1=' . str_repeat('0', 64);
    $badRoute = red_addon_webhook_invoke_registered(
        $route,
        $badRouteSignature['rawBody'],
        $badRouteSignature['signatureHeader'],
        $badRouteSignature['receivedAt'],
        $package,
        $webhookHandler,
        $manifest,
        $webhookAccess
    );
    $assert(
        $badRoute['invoked']
            && !$badRoute['success']
            && $badRoute['statusCode'] === 400
            && $badRoute['error']
                === 'subscription_webhook_signature_refused',
        'invalid route signature is refused before journal access'
    );
    $expandedAccess = new RED_Addon_Runtime_Secret_Access($package, [
        'stripe.secret-key' => 'rk_test_synthetic_route_scope_123456',
        'stripe.webhook-secret' => $secret,
    ]);
    $assert(
        red_addon_webhook_invoke_registered(
            $route,
            $routeRequest['rawBody'],
            $routeRequest['signatureHeader'],
            $routeRequest['receivedAt'],
            $package,
            $webhookHandler,
            $manifest,
            $expandedAccess
        )['invoked'] === false,
        'route boundary refuses expanded API-key secret scope'
    );
    $apiOnlyAccess = new RED_Addon_Runtime_Secret_Access(
        $package,
        ['stripe.secret-key' => 'rk_test_synthetic_route_scope_123456']
    );
    $apiOnly = red_addon_webhook_invoke_registered(
        $route,
        $routeRequest['rawBody'],
        $routeRequest['signatureHeader'],
        $routeRequest['receivedAt'],
        $package,
        $webhookHandler,
        $manifest,
        $apiOnlyAccess
    );
    $assert(
        $apiOnly['invoked']
            && !$apiOnly['success']
            && $apiOnly['statusCode'] === 500
            && $apiOnly['error']
                === 'subscription_webhook_secret_unavailable',
        'API-key-only scope cannot substitute for the webhook secret'
    );

    $rows = [];
    $state = $initialState;
    $failCompletionOnce = true;
    $routeRecoveryRequest = $makeRequest(
        'evt_DeliveryCoordinatorRouteRecovery123',
        ['redcms_offer_state_sha256' => $offer]
    );
    $beforeRouteRecoveryApply = $applyCalls;
    $routeInterrupted = red_addon_webhook_invoke_registered(
        $route,
        $routeRecoveryRequest['rawBody'],
        $routeRecoveryRequest['signatureHeader'],
        $routeRecoveryRequest['receivedAt'],
        $package,
        $webhookHandler,
        $manifest,
        $webhookAccess
    );
    $routeRecovered = red_addon_webhook_invoke_registered(
        $route,
        $routeRecoveryRequest['rawBody'],
        $routeRecoveryRequest['signatureHeader'],
        $routeRecoveryRequest['receivedAt'],
        $package,
        $webhookHandler,
        $manifest,
        $webhookAccess
    );
    $assert(
        !$routeInterrupted['success']
            && $routeInterrupted['statusCode'] === 500
            && $routeInterrupted['error']
                === 'subscription_webhook_retry_required'
            && $routeRecovered['success']
            && ($routeRecovered['data']['recovered'] ?? false)
            && $applyCalls === $beforeRouteRecoveryApply + 1,
        'retry response recovers a post-lifecycle receipt interruption once'
    );

    $rows = [];
    $state = array_replace($initialState, [
        'offerStateSha256' => str_repeat('8', 64),
    ]);
    $routeRefusedRequest = $makeRequest(
        'evt_DeliveryCoordinatorRouteRefused1234',
        ['redcms_offer_state_sha256' => $offer]
    );
    $routeRefused = red_addon_webhook_invoke_registered(
        $route,
        $routeRefusedRequest['rawBody'],
        $routeRefusedRequest['signatureHeader'],
        $routeRefusedRequest['receivedAt'],
        $package,
        $webhookHandler,
        $manifest,
        $webhookAccess
    );
    $assert(
        $routeRefused['success']
            && $routeRefused['statusCode'] === 200
            && ($routeRefused['data']['outcome'] ?? '')
                === 'subscription_event_refused'
            && ($routeRefused['data']['acknowledged'] ?? false)
            && !($routeRefused['data']['applied'] ?? true),
        'terminal lifecycle refusal is journaled and acknowledged without retry'
    );

    $rows = [];
    $state = $initialState;
    $staleDeferredRequest = $makeDeferredRequest(
        'evt_DeliveryCoordinatorStaleDeferred123'
    );
    $staleDeferred = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $staleDeferredRequest,
        $signatureVerifier,
        static fn (): array => [],
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 10
    );
    $staleDeferredRetry = $resignRequest(
        $staleDeferredRequest,
        $receivedAt + 180
    );
    $closedDeferred = red_addon_subscription_event_delivery_coordinate(
        $binding,
        $staleDeferredRetry,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $receivedAt + 190
    );
    $assert(
        !$staleDeferred['valid']
            && $staleDeferred['status'] === 'verified'
            && $staleDeferred['restartable']
            && !$closedDeferred['valid']
            && $closedDeferred['status'] === 'refused'
            && $closedDeferred['journalCompleted']
            && !$closedDeferred['restartable']
            && $closedDeferred['claimStateSha256']
                === $staleDeferred['claimStateSha256'],
        'freshly signed retry rebinds one verified receipt and closes deferred Checkout without activation'
    );

    $rows = [];
    $state = $initialState;
    $deferredRouteRequest = $makeDeferredRequest(
        'evt_DeliveryCoordinatorDeferred123'
    );
    $deferredEnvelope = $signatureVerifier(
        $deferredRouteRequest['rawBody'],
        $deferredRouteRequest['signatureHeader'],
        $deferredRouteRequest['receivedAt']
    );
    $deferredProjection = $eventProjector(
        $deferredEnvelope,
        $deferredRouteRequest['rawBody']
    );
    $assert(
        ($deferredProjection['valid'] ?? false)
            && red_addon_subscription_delivery_projection_valid(
                $deferredProjection,
                $deferredEnvelope
            )
            && ($deferredProjection['verifiedEvent']['providerStatus'] ?? '')
                === 'complete_paid_deferred',
        'unexpanded completed Checkout reaches the bounded deferred projection'
    );
    $beforeDeferredApply = $applyCalls;
    $deferredRoute = red_addon_webhook_invoke_registered(
        $route,
        $deferredRouteRequest['rawBody'],
        $deferredRouteRequest['signatureHeader'],
        $deferredRouteRequest['receivedAt'],
        $package,
        $webhookHandler,
        $manifest,
        $webhookAccess
    );
    $deferredKey = hash(
        'sha256',
        'evt_DeliveryCoordinatorDeferred123'
    );
    $assert(
        $deferredRoute['success']
            && $deferredRoute['statusCode'] === 200
            && ($deferredRoute['data']['outcome'] ?? '')
                === 'subscription_event_refused'
            && !($deferredRoute['data']['applied'] ?? true)
            && $applyCalls === $beforeDeferredApply
            && ($rows[$deferredKey]['status'] ?? '') === 'refused'
            && $state === $initialState,
        'unexpanded completed Checkout is terminally deferred for paid-invoice activation ('
            . implode(':', [
                $deferredRoute['success'] ? 'success' : 'failed',
                (string) $deferredRoute['statusCode'],
                $deferredRoute['data']['outcome'] ?? 'no-outcome',
                (string) ($applyCalls - $beforeDeferredApply),
                $rows[$deferredKey]['status'] ?? 'no-row',
                $state === $initialState ? 'unchanged' : 'changed',
            ]) . ')'
    );

    $frontController = (string) file_get_contents(
        dirname(__DIR__) . '/index.php'
    );
    $bootstrap = (string) file_get_contents(
        dirname(__DIR__) . '/includes/bootstrap.php'
    );
    $packageEntrypoint = (string) file_get_contents(
        $adapterRoot . '/addon.php'
    );
    $assert(
        !str_contains(
            $frontController,
            'red_addon_subscription_event_webhook_handle'
        )
            && !str_contains(
                $bootstrap,
                'red_addon_subscription_event_webhook_handle'
            )
            && !str_contains(
                $frontController,
                'addon_subscription_event_webhook_handler_helpers.php'
            )
            && !str_contains(
                $bootstrap,
                'addon_subscription_event_webhook_handler_helpers.php'
            )
            && str_contains(
                $packageEntrypoint,
                'p3c4_route_handler_not_operational'
            )
            && !str_contains(
                $packageEntrypoint,
                'red_addon_subscription_event_webhook_handle'
            ),
        'handler remains absent from public bootstrap and package registration'
    );

    echo 'Subscription event delivery coordinator passed '
        . $assertions . " assertions.\n";
    echo "Only the unlinked internal route boundary and synthetic in-memory evidence were used; no public route, database, network, Stripe, payment, browser, or deployment action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

exit(0);
