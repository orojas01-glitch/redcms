<?php
/**
 * Store Lite Stripe Checkout P2 non-network contract fixture.
 *
 * This CLI-only fixture is not payment integration. It has no database,
 * filesystem, request-global, package, lifecycle, session, SDK, secret, or
 * network path. A separately distributed adapter will own later P3 behavior.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$assertions = 0;

function red_store_lite_stripe_p2_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_store_lite_stripe_p2_refusal($reason)
{
    return ['accepted' => false, 'reason' => $reason, 'value' => null];
}

function red_store_lite_stripe_p2_exact_keys(array $input, array $keys)
{
    return array_keys($input) === $keys;
}

function red_store_lite_stripe_p2_identifier($value)
{
    return is_string($value)
        && preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $value) === 1;
}

function red_store_lite_stripe_p2_hash($value)
{
    return is_string($value)
        && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
}

function red_store_lite_stripe_p2_session_ref($value)
{
    return is_string($value)
        && preg_match('/\Acs_test_[A-Za-z0-9]{16,120}\z/D', $value) === 1;
}

function red_store_lite_stripe_p2_order(array $order)
{
    return red_store_lite_stripe_p2_exact_keys($order, [
        'clientId', 'orderId', 'state', 'amountMinor', 'currency',
        'snapshotSha256', 'idempotencyKey',
    ])
        && red_store_lite_stripe_p2_identifier($order['clientId'])
        && red_store_lite_stripe_p2_identifier($order['orderId'])
        && in_array($order['state'], ['awaiting_payment', 'paid'], true)
        && is_int($order['amountMinor'])
        && $order['amountMinor'] >= 0
        && $order['amountMinor'] <= 999999999
        && $order['currency'] === 'USD'
        && red_store_lite_stripe_p2_hash($order['snapshotSha256'])
        && red_store_lite_stripe_p2_hash($order['idempotencyKey']);
}

function red_store_lite_stripe_p2_config(array $config)
{
    return red_store_lite_stripe_p2_exact_keys($config, [
        'clientId', 'provider', 'currency', 'mode', 'checkoutShape',
        'returnStatusOrigin', 'enabled',
    ])
        && red_store_lite_stripe_p2_identifier($config['clientId'])
        && $config['provider'] === 'stripe'
        && $config['currency'] === 'USD'
        && $config['mode'] === 'payment'
        && $config['checkoutShape'] === 'hosted_full_page'
        && $config['returnStatusOrigin'] === 'https://demo.example.test'
        && $config['enabled'] === true;
}

function red_store_lite_stripe_p2_plan_checkout(array $order, array $config, $clientId)
{
    if (!red_store_lite_stripe_p2_config($config)) {
        return red_store_lite_stripe_p2_refusal('configuration_invalid');
    }
    if (!red_store_lite_stripe_p2_order($order)
        || !red_store_lite_stripe_p2_identifier($clientId)
        || $order['clientId'] !== $clientId
        || $config['clientId'] !== $clientId) {
        return red_store_lite_stripe_p2_refusal('client_or_order_invalid');
    }
    if ($order['state'] !== 'awaiting_payment') {
        return red_store_lite_stripe_p2_refusal('order_not_awaiting_payment');
    }
    if ($order['currency'] !== $config['currency']) {
        return red_store_lite_stripe_p2_refusal('currency_mismatch');
    }
    return [
        'accepted' => true,
        'reason' => 'planned',
        'value' => [
            'provider' => 'stripe',
            'orderId' => $order['orderId'],
            'amountMinor' => $order['amountMinor'],
            'currency' => 'USD',
            'mode' => 'payment',
            'checkoutShape' => 'hosted_full_page',
            'idempotencyKey' => $order['idempotencyKey'],
            'returnStatusUrl' => $config['returnStatusOrigin']
                . '/_red/addons/store-lite/checkout/status',
        ],
    ];
}

function red_store_lite_stripe_p2_checkout_response(array $plan, array $response)
{
    if (!($plan['accepted'] ?? false)
        || !is_array($plan['value'] ?? null)
        || !red_store_lite_stripe_p2_exact_keys($plan['value'], [
            'provider', 'orderId', 'amountMinor', 'currency', 'mode',
            'checkoutShape', 'idempotencyKey', 'returnStatusUrl',
        ])
        || !red_store_lite_stripe_p2_exact_keys($response, [
            'provider', 'checkoutSessionRef', 'checkoutUrl',
        ])
        || $response['provider'] !== 'stripe'
        || !red_store_lite_stripe_p2_session_ref($response['checkoutSessionRef'])
        || !is_string($response['checkoutUrl'])) {
        return red_store_lite_stripe_p2_refusal('checkout_response_invalid');
    }
    $url = parse_url($response['checkoutUrl']);
    if (!is_array($url)
        || ($url['scheme'] ?? null) !== 'https'
        || ($url['host'] ?? null) !== 'checkout.stripe.com'
        || array_key_exists('user', $url)
        || array_key_exists('pass', $url)
        || array_key_exists('port', $url)
        || array_key_exists('query', $url)
        || array_key_exists('fragment', $url)
        || ($url['path'] ?? null) !== '/c/pay/' . $response['checkoutSessionRef']) {
        return red_store_lite_stripe_p2_refusal('checkout_url_invalid');
    }
    return [
        'accepted' => true,
        'reason' => 'checkout_response_accepted',
        'value' => [
            'checkoutSessionRef' => $response['checkoutSessionRef'],
            'checkoutUrl' => $response['checkoutUrl'],
        ],
    ];
}

function red_store_lite_stripe_p2_signature_boundary(array $rawEnvelope, $now)
{
    if (!red_store_lite_stripe_p2_exact_keys($rawEnvelope, [
        'rawBody', 'signatureVerified', 'receivedAt',
    ])
        || !is_string($rawEnvelope['rawBody'])
        || $rawEnvelope['rawBody'] === ''
        || strlen($rawEnvelope['rawBody']) > 262144
        || $rawEnvelope['signatureVerified'] !== true
        || !is_int($rawEnvelope['receivedAt'])
        || !is_int($now)
        || $rawEnvelope['receivedAt'] > $now
        || $rawEnvelope['receivedAt'] < $now - 300) {
        return red_store_lite_stripe_p2_refusal('signature_boundary_invalid');
    }
    return [
        'accepted' => true,
        'reason' => 'signature_boundary_verified',
        'value' => [
            'rawBodySha256' => hash('sha256', $rawEnvelope['rawBody']),
            'receivedAt' => $rawEnvelope['receivedAt'],
        ],
    ];
}

function red_store_lite_stripe_p2_normalize_event(
    array $order,
    array $config,
    array $checkout,
    array $signatureBoundary,
    array $event,
    array $seenEventRefs,
    $clientId,
    $now
) {
    // This check intentionally precedes all parsed event and order facts.
    if (!($signatureBoundary['accepted'] ?? false)
        || !is_array($signatureBoundary['value'] ?? null)
        || !red_store_lite_stripe_p2_exact_keys($signatureBoundary['value'], [
            'rawBodySha256', 'receivedAt',
        ])
        || !red_store_lite_stripe_p2_hash($signatureBoundary['value']['rawBodySha256'])
        || !is_int($signatureBoundary['value']['receivedAt'])) {
        return red_store_lite_stripe_p2_refusal('signature_not_verified');
    }
    if (!red_store_lite_stripe_p2_config($config)
        || !red_store_lite_stripe_p2_order($order)
        || !($checkout['accepted'] ?? false)
        || !is_array($checkout['value'] ?? null)
        || !red_store_lite_stripe_p2_exact_keys($checkout['value'], [
            'checkoutSessionRef', 'checkoutUrl',
        ])
        || !red_store_lite_stripe_p2_identifier($clientId)
        || $config['clientId'] !== $clientId
        || $order['clientId'] !== $clientId) {
        return red_store_lite_stripe_p2_refusal('configuration_or_client_invalid');
    }
    if (!red_store_lite_stripe_p2_exact_keys($event, [
        'provider', 'eventRef', 'checkoutSessionRef', 'eventType', 'amountMinor',
        'currency', 'orderId', 'clientId', 'occurredAt',
    ])
        || $event['provider'] !== 'stripe'
        || !red_store_lite_stripe_p2_identifier($event['eventRef'])
        || !red_store_lite_stripe_p2_session_ref($event['checkoutSessionRef'])
        || !is_string($event['eventType'])
        || !is_int($event['amountMinor'])
        || !is_string($event['currency'])
        || !red_store_lite_stripe_p2_identifier($event['orderId'])
        || !red_store_lite_stripe_p2_identifier($event['clientId'])
        || !is_int($event['occurredAt'])) {
        return red_store_lite_stripe_p2_refusal('event_invalid');
    }
    if ($event['clientId'] !== $clientId) {
        return red_store_lite_stripe_p2_refusal('event_client_mismatch');
    }
    if ($event['orderId'] !== $order['orderId']
        || $event['checkoutSessionRef'] !== $checkout['value']['checkoutSessionRef']) {
        return red_store_lite_stripe_p2_refusal('event_order_mismatch');
    }
    if ($event['amountMinor'] !== $order['amountMinor']) {
        return red_store_lite_stripe_p2_refusal('event_amount_mismatch');
    }
    if ($event['currency'] !== 'USD' || $event['currency'] !== $order['currency']) {
        return red_store_lite_stripe_p2_refusal('event_currency_mismatch');
    }
    if (!is_int($now)
        || $event['occurredAt'] > $signatureBoundary['value']['receivedAt']
        || $event['occurredAt'] < $signatureBoundary['value']['receivedAt'] - 300
        || $signatureBoundary['value']['receivedAt'] > $now) {
        return red_store_lite_stripe_p2_refusal('event_timestamp_invalid');
    }
    if (!array_is_list($seenEventRefs)) {
        return red_store_lite_stripe_p2_refusal('replay_evidence_invalid');
    }
    foreach ($seenEventRefs as $seenEventRef) {
        if (!red_store_lite_stripe_p2_identifier($seenEventRef)) {
            return red_store_lite_stripe_p2_refusal('replay_evidence_invalid');
        }
    }
    if (in_array($event['eventRef'], $seenEventRefs, true)) {
        return red_store_lite_stripe_p2_refusal('event_replayed');
    }
    $outcomes = [
        'checkout.session.completed' => ['awaiting_payment', 'paid'],
        'checkout.session.async_payment_failed' => ['awaiting_payment', 'failed'],
        'checkout.session.expired' => ['awaiting_payment', 'expired'],
        'charge.refunded' => ['paid', 'refund_confirmed'],
        'charge.dispute.created' => ['paid', 'reversal_reported'],
    ];
    if (!array_key_exists($event['eventType'], $outcomes)) {
        return red_store_lite_stripe_p2_refusal('event_type_invalid');
    }
    if ($order['state'] !== $outcomes[$event['eventType']][0]) {
        return red_store_lite_stripe_p2_refusal('event_state_invalid');
    }
    return [
        'accepted' => true,
        'reason' => 'event_normalized',
        'value' => [
            'provider' => 'stripe',
            'eventRef' => $event['eventRef'],
            'checkoutSessionRef' => $event['checkoutSessionRef'],
            'outcome' => $outcomes[$event['eventType']][1],
            'amountMinor' => $event['amountMinor'],
            'currency' => 'USD',
            'orderId' => $event['orderId'],
            'receivedAt' => $signatureBoundary['value']['receivedAt'],
            'rawBodySha256' => $signatureBoundary['value']['rawBodySha256'],
        ],
    ];
}

function red_store_lite_stripe_p2_browser_return(array $query)
{
    if (!red_store_lite_stripe_p2_exact_keys($query, ['checkout', 'status'])
        || !red_store_lite_stripe_p2_session_ref($query['checkout'])
        || !in_array($query['status'], ['complete', 'cancelled', 'unknown'], true)) {
        return red_store_lite_stripe_p2_refusal('return_invalid');
    }
    return [
        'accepted' => true,
        'reason' => 'return_status_only',
        'value' => ['checkoutSessionRef' => $query['checkout'], 'status' => $query['status']],
    ];
}

function red_store_lite_stripe_p2_order_fixture($state = 'awaiting_payment')
{
    return [
        'clientId' => 'demo-red-sphere', 'orderId' => 'order-1042',
        'state' => $state, 'amountMinor' => 5897, 'currency' => 'USD',
        'snapshotSha256' => str_repeat('a', 64),
        'idempotencyKey' => str_repeat('b', 64),
    ];
}

function red_store_lite_stripe_p2_config_fixture()
{
    return [
        'clientId' => 'demo-red-sphere', 'provider' => 'stripe',
        'currency' => 'USD', 'mode' => 'payment',
        'checkoutShape' => 'hosted_full_page',
        'returnStatusOrigin' => 'https://demo.example.test', 'enabled' => true,
    ];
}

function red_store_lite_stripe_p2_response_fixture()
{
    $ref = 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx';
    return [
        'provider' => 'stripe', 'checkoutSessionRef' => $ref,
        'checkoutUrl' => 'https://checkout.stripe.com/c/pay/' . $ref,
    ];
}

function red_store_lite_stripe_p2_event_fixture($type = 'checkout.session.completed')
{
    return [
        'provider' => 'stripe', 'eventRef' => 'evt-1042',
        'checkoutSessionRef' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
        'eventType' => $type, 'amountMinor' => 5897, 'currency' => 'USD',
        'orderId' => 'order-1042', 'clientId' => 'demo-red-sphere',
        'occurredAt' => 1735689590,
    ];
}

try {
    red_store_lite_stripe_p2_assert(
        get_included_files() === [__FILE__],
        'fixture loads no core runtime, package, provider SDK, or external dependency'
    );
    $now = 1735689600;
    $order = red_store_lite_stripe_p2_order_fixture();
    $config = red_store_lite_stripe_p2_config_fixture();
    $plan = red_store_lite_stripe_p2_plan_checkout($order, $config, 'demo-red-sphere');
    red_store_lite_stripe_p2_assert(
        $plan['accepted']
            && $plan['value'] === [
                'provider' => 'stripe', 'orderId' => 'order-1042',
                'amountMinor' => 5897, 'currency' => 'USD', 'mode' => 'payment',
                'checkoutShape' => 'hosted_full_page',
                'idempotencyKey' => str_repeat('b', 64),
                'returnStatusUrl' => 'https://demo.example.test/_red/addons/store-lite/checkout/status',
            ],
        'plan derives exact immutable USD order facts and one hosted shape'
    );
    red_store_lite_stripe_p2_assert(
        !array_key_exists('checkoutUrl', $plan['value'])
            && !array_key_exists('paymentState', $plan['value'])
            && !array_key_exists('customer', $plan['value']),
        'plan creates neither provider session, payment state, nor customer data'
    );
    $checkout = red_store_lite_stripe_p2_checkout_response($plan, red_store_lite_stripe_p2_response_fixture());
    red_store_lite_stripe_p2_assert(
        $checkout['accepted']
            && $checkout['value'] === [
                'checkoutSessionRef' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
                'checkoutUrl' => 'https://checkout.stripe.com/c/pay/cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
            ],
        'only opaque session reference and approved HTTPS redirect survive validation'
    );
    $boundary = red_store_lite_stripe_p2_signature_boundary([
        'rawBody' => '{"fixture":"signed-after-p3"}', 'signatureVerified' => true,
        'receivedAt' => $now,
    ], $now);
    red_store_lite_stripe_p2_assert(
        $boundary['accepted'] && red_store_lite_stripe_p2_hash($boundary['value']['rawBodySha256']),
        'only an already-verified raw-body boundary can proceed to parsed event normalization'
    );
    $normalized = red_store_lite_stripe_p2_normalize_event(
        $order, $config, $checkout, $boundary, red_store_lite_stripe_p2_event_fixture(), [],
        'demo-red-sphere', $now
    );
    red_store_lite_stripe_p2_assert(
        $normalized['accepted'] && $normalized['value']['outcome'] === 'paid'
            && !array_key_exists('orderState', $normalized['value']),
        'verified event normalizes a proposal but never transitions an order'
    );
    $browserReturn = red_store_lite_stripe_p2_browser_return([
        'checkout' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx', 'status' => 'complete',
    ]);
    red_store_lite_stripe_p2_assert(
        $browserReturn['accepted'] && $browserReturn['reason'] === 'return_status_only'
            && array_keys($browserReturn['value']) === ['checkoutSessionRef', 'status'],
        'browser return can display status but carries no order or fulfillment authority'
    );

    $planRefusals = [];
    $wrongClientOrder = red_store_lite_stripe_p2_order_fixture();
    $wrongClientOrder['clientId'] = 'other-client';
    $planRefusals['client'] = [$wrongClientOrder, $config, 'demo-red-sphere', 'client_or_order_invalid'];
    $wrongCurrencyOrder = red_store_lite_stripe_p2_order_fixture();
    $wrongCurrencyOrder['currency'] = 'COP';
    $planRefusals['currency'] = [$wrongCurrencyOrder, $config, 'demo-red-sphere', 'client_or_order_invalid'];
    $wrongMode = red_store_lite_stripe_p2_config_fixture();
    $wrongMode['mode'] = 'subscription';
    $planRefusals['mode'] = [$order, $wrongMode, 'demo-red-sphere', 'configuration_invalid'];
    $wrongShape = red_store_lite_stripe_p2_config_fixture();
    $wrongShape['checkoutShape'] = 'embedded';
    $planRefusals['shape'] = [$order, $wrongShape, 'demo-red-sphere', 'configuration_invalid'];
    $disabled = red_store_lite_stripe_p2_config_fixture();
    $disabled['enabled'] = false;
    $planRefusals['disabled'] = [$order, $disabled, 'demo-red-sphere', 'configuration_invalid'];
    $notAwaiting = red_store_lite_stripe_p2_order_fixture('paid');
    $planRefusals['state'] = [$notAwaiting, $config, 'demo-red-sphere', 'order_not_awaiting_payment'];
    foreach ($planRefusals as $name => $case) {
        red_store_lite_stripe_p2_assert(
            red_store_lite_stripe_p2_plan_checkout($case[0], $case[1], $case[2])
                === red_store_lite_stripe_p2_refusal($case[3]),
            $name . ' checkout-plan mismatch fails closed with no partial plan'
        );
    }
    $badResponse = red_store_lite_stripe_p2_response_fixture();
    $badResponse['checkoutUrl'] .= '?redirect=elsewhere';
    red_store_lite_stripe_p2_assert(
        red_store_lite_stripe_p2_checkout_response($plan, $badResponse)
            === red_store_lite_stripe_p2_refusal('checkout_url_invalid'),
        'checkout redirect rejects query, redirect, and URL ambiguity'
    );
    $otherHostResponse = red_store_lite_stripe_p2_response_fixture();
    $otherHostResponse['checkoutUrl'] = 'https://example.test/c/pay/' . $otherHostResponse['checkoutSessionRef'];
    red_store_lite_stripe_p2_assert(
        red_store_lite_stripe_p2_checkout_response($plan, $otherHostResponse)
            === red_store_lite_stripe_p2_refusal('checkout_url_invalid'),
        'checkout redirect refuses unapproved hosts'
    );
    $invalidBoundary = red_store_lite_stripe_p2_signature_boundary([
        'rawBody' => '{"fixture":"unsigned"}', 'signatureVerified' => false, 'receivedAt' => $now,
    ], $now);
    red_store_lite_stripe_p2_assert(
        red_store_lite_stripe_p2_normalize_event(
            $order, $config, $checkout, $invalidBoundary, red_store_lite_stripe_p2_event_fixture(), [],
            'demo-red-sphere', $now
        ) === red_store_lite_stripe_p2_refusal('signature_not_verified'),
        'unverified raw body is refused before parsed event facts are considered'
    );
    $eventRefusals = [];
    $wrongProviderEvent = red_store_lite_stripe_p2_event_fixture();
    $wrongProviderEvent['provider'] = 'paypal';
    $eventRefusals['provider'] = [$order, $wrongProviderEvent, [], 'event_invalid'];
    $wrongOrderEvent = red_store_lite_stripe_p2_event_fixture();
    $wrongOrderEvent['orderId'] = 'order-9999';
    $eventRefusals['order'] = [$order, $wrongOrderEvent, [], 'event_order_mismatch'];
    $wrongAmountEvent = red_store_lite_stripe_p2_event_fixture();
    $wrongAmountEvent['amountMinor'] = 1;
    $eventRefusals['amount'] = [$order, $wrongAmountEvent, [], 'event_amount_mismatch'];
    $wrongCurrencyEvent = red_store_lite_stripe_p2_event_fixture();
    $wrongCurrencyEvent['currency'] = 'COP';
    $eventRefusals['currency'] = [$order, $wrongCurrencyEvent, [], 'event_currency_mismatch'];
    $wrongClientEvent = red_store_lite_stripe_p2_event_fixture();
    $wrongClientEvent['clientId'] = 'other-client';
    $eventRefusals['client'] = [$order, $wrongClientEvent, [], 'event_client_mismatch'];
    $staleEvent = red_store_lite_stripe_p2_event_fixture();
    $staleEvent['occurredAt'] = $now - 301;
    $eventRefusals['timestamp'] = [$order, $staleEvent, [], 'event_timestamp_invalid'];
    $unknownEvent = red_store_lite_stripe_p2_event_fixture('payment_intent.succeeded');
    $eventRefusals['outcome'] = [$order, $unknownEvent, [], 'event_type_invalid'];
    $eventRefusals['replay'] = [$order, red_store_lite_stripe_p2_event_fixture(), ['evt-1042'], 'event_replayed'];
    foreach ($eventRefusals as $name => $case) {
        red_store_lite_stripe_p2_assert(
            red_store_lite_stripe_p2_normalize_event(
                $case[0], $config, $checkout, $boundary, $case[1], $case[2], 'demo-red-sphere', $now
            ) === red_store_lite_stripe_p2_refusal($case[3]),
            $name . ' event mismatch fails closed with no normalized event'
        );
    }
    $refund = red_store_lite_stripe_p2_normalize_event(
        red_store_lite_stripe_p2_order_fixture('paid'), $config, $checkout, $boundary,
        red_store_lite_stripe_p2_event_fixture('charge.refunded'), [], 'demo-red-sphere', $now
    );
    red_store_lite_stripe_p2_assert(
        $refund['accepted'] && $refund['value']['outcome'] === 'refund_confirmed',
        'refund is normalized only for an eligible paid snapshot and does not transition it'
    );
    $reversal = red_store_lite_stripe_p2_normalize_event(
        red_store_lite_stripe_p2_order_fixture('paid'), $config, $checkout, $boundary,
        red_store_lite_stripe_p2_event_fixture('charge.dispute.created'), [], 'demo-red-sphere', $now
    );
    red_store_lite_stripe_p2_assert(
        $reversal['accepted'] && $reversal['value']['outcome'] === 'reversal_reported',
        'reversal is normalized as risk evidence only, not an automatic fulfillment action'
    );
    red_store_lite_stripe_p2_assert(
        red_store_lite_stripe_p2_normalize_event(
            $order, $config, $checkout, $boundary,
            red_store_lite_stripe_p2_event_fixture('charge.refunded'), [], 'demo-red-sphere', $now
        ) === red_store_lite_stripe_p2_refusal('event_state_invalid'),
        'refund against a non-paid snapshot is refused'
    );
    red_store_lite_stripe_p2_assert(
        red_store_lite_stripe_p2_normalize_event(
            $order, $config, $checkout, $boundary,
            red_store_lite_stripe_p2_event_fixture('charge.dispute.created'), [], 'demo-red-sphere', $now
        ) === red_store_lite_stripe_p2_refusal('event_state_invalid'),
        'reversal against a non-paid snapshot is refused'
    );
    red_store_lite_stripe_p2_assert(
        red_store_lite_stripe_p2_browser_return([
            'checkout' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx', 'status' => 'paid',
        ]) === red_store_lite_stripe_p2_refusal('return_invalid'),
        'browser cannot claim a payment outcome'
    );
    echo 'Store Lite Stripe Checkout P2 contract self-test passed: ' . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
