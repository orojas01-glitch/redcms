<?php
/**
 * Colombia C1 initiation-mode and Wompi/Nequi offline contract fixture.
 *
 * This CLI-only fixture loads one dependency-free core normalizer. It opens no
 * configuration or database, loads no package, resolves no secret, publishes
 * no route, and performs no network or provider operation.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__)
    . '/includes/addon_payment_initiation_helpers.php';

$assertions = 0;

function red_colombia_c1_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_colombia_c1_refusal($reason)
{
    return ['accepted' => false, 'reason' => $reason, 'value' => null];
}

function red_colombia_c1_exact_keys(array $value, array $expected)
{
    return array_keys($value) === $expected;
}

function red_colombia_c1_sha256($value)
{
    return is_string($value)
        && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
}

function red_colombia_c1_identifier($value)
{
    return is_string($value)
        && preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $value) === 1;
}

function red_colombia_c1_hash(array $value)
{
    try {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );
    } catch (Throwable $throwable) {
        return '';
    }
    return hash('sha256', $encoded);
}

function red_colombia_c1_order_valid(array $order)
{
    return red_colombia_c1_exact_keys($order, [
        'clientId', 'orderId', 'state', 'amountMinor', 'currency',
        'snapshotSha256', 'idempotencySha256', 'customerEmail',
        'customerPhone',
    ])
        && red_colombia_c1_identifier($order['clientId'] ?? null)
        && is_string($order['orderId'] ?? null)
        && preg_match('/\Aord_[a-f0-9]{32}\z/D', $order['orderId']) === 1
        && ($order['state'] ?? null) === 'awaiting_payment'
        && is_int($order['amountMinor'] ?? null)
        && $order['amountMinor'] >= 100
        && $order['amountMinor'] <= 999999999999
        && ($order['currency'] ?? null) === 'COP'
        && red_colombia_c1_sha256($order['snapshotSha256'] ?? null)
        && red_colombia_c1_sha256($order['idempotencySha256'] ?? null)
        && is_string($order['customerEmail'] ?? null)
        && filter_var($order['customerEmail'], FILTER_VALIDATE_EMAIL) !== false
        && strlen($order['customerEmail']) <= 254
        && is_string($order['customerPhone'] ?? null)
        && preg_match('/\A3[0-9]{9}\z/D', $order['customerPhone']) === 1;
}

function red_colombia_c1_config_valid(array $config)
{
    return red_colombia_c1_exact_keys($config, [
        'clientId', 'provider', 'method', 'currency', 'environment',
        'publicKeySettingPresent', 'enabled',
    ])
        && red_colombia_c1_identifier($config['clientId'] ?? null)
        && ($config['provider'] ?? null) === 'wompi'
        && ($config['method'] ?? null) === 'nequi'
        && ($config['currency'] ?? null) === 'COP'
        && ($config['environment'] ?? null) === 'sandbox'
        && ($config['publicKeySettingPresent'] ?? null) === true
        && ($config['enabled'] ?? null) === true;
}

function red_colombia_c1_acceptance_valid(array $acceptance)
{
    return red_colombia_c1_exact_keys($acceptance, [
        'privacyAccepted', 'personalDataAccepted', 'acceptanceToken',
        'personalAuthToken', 'contractsSha256',
    ])
        && ($acceptance['privacyAccepted'] ?? null) === true
        && ($acceptance['personalDataAccepted'] ?? null) === true
        && is_string($acceptance['acceptanceToken'] ?? null)
        && strlen($acceptance['acceptanceToken']) >= 16
        && strlen($acceptance['acceptanceToken']) <= 4096
        && is_string($acceptance['personalAuthToken'] ?? null)
        && strlen($acceptance['personalAuthToken']) >= 16
        && strlen($acceptance['personalAuthToken']) <= 4096
        && red_colombia_c1_sha256($acceptance['contractsSha256'] ?? null);
}

function red_colombia_c1_secret_refs_valid(array $refs)
{
    if (!red_colombia_c1_exact_keys($refs, [
        'privateKey', 'integrityKey', 'eventSecret',
    ])) {
        return false;
    }
    foreach ($refs as $ref) {
        if (!is_string($ref)
            || preg_match('/\Aconfig:[a-z][a-z0-9._-]{2,79}\z/D', $ref) !== 1
        ) {
            return false;
        }
    }
    return count(array_unique(array_values($refs), SORT_STRING)) === 3;
}

function red_colombia_c1_plan_request(
    array $order,
    array $config,
    array $acceptance,
    array $secretRefs,
    $integritySignature
) {
    if (!red_colombia_c1_order_valid($order)
        || !red_colombia_c1_config_valid($config)
        || !red_colombia_c1_acceptance_valid($acceptance)
        || !red_colombia_c1_secret_refs_valid($secretRefs)
        || !red_colombia_c1_sha256($integritySignature)
        || $order['clientId'] !== $config['clientId']
        || $order['currency'] !== $config['currency']
    ) {
        return red_colombia_c1_refusal('request_invalid');
    }

    $transientRequest = [
        'amount_in_cents' => $order['amountMinor'],
        'currency' => 'COP',
        'customer_email' => $order['customerEmail'],
        'payment_method' => [
            'type' => 'NEQUI',
            'phone_number' => $order['customerPhone'],
        ],
        'payment_method_type' => 'NEQUI',
        'reference' => $order['orderId'],
        'signature' => $integritySignature,
        'acceptance_token' => $acceptance['acceptanceToken'],
        'accept_personal_auth' => $acceptance['personalAuthToken'],
    ];
    $requestSha256 = red_colombia_c1_hash($transientRequest);
    $acceptanceSha256 = red_colombia_c1_hash([
        'acceptanceTokenSha256' => hash(
            'sha256',
            $acceptance['acceptanceToken']
        ),
        'personalAuthTokenSha256' => hash(
            'sha256',
            $acceptance['personalAuthToken']
        ),
        'contractsSha256' => $acceptance['contractsSha256'],
    ]);
    if (!red_colombia_c1_sha256($requestSha256)
        || !red_colombia_c1_sha256($acceptanceSha256)
    ) {
        return red_colombia_c1_refusal('request_hash_failed');
    }

    return [
        'accepted' => true,
        'reason' => 'request_planned',
        'value' => [
            'provider' => 'wompi',
            'method' => 'nequi',
            'environment' => 'sandbox',
            'orderId' => $order['orderId'],
            'amountMinor' => $order['amountMinor'],
            'currency' => 'COP',
            'requestSha256' => $requestSha256,
            'acceptanceSha256' => $acceptanceSha256,
            'privateKeyAvailable' => true,
            'integrityKeyAvailable' => true,
            'eventSecretAvailable' => true,
            'providerContact' => false,
            'providerMutation' => false,
            'payment' => false,
            'orderMutation' => false,
        ],
    ];
}

function red_colombia_c1_event_properties()
{
    return [
        'transaction.id',
        'transaction.status',
        'transaction.amount_in_cents',
    ];
}

function red_colombia_c1_event_values(array $event)
{
    $transaction = $event['data']['transaction'] ?? null;
    $properties = $event['signature']['properties'] ?? null;
    if (!is_array($transaction)
        || !is_array($properties)
        || !array_is_list($properties)
        || count($properties) < 1
        || count($properties) > 16
    ) {
        return null;
    }
    $allowed = [
        'id', 'status', 'amount_in_cents', 'reference', 'currency',
        'payment_method_type',
    ];
    $values = [];
    $seen = [];
    foreach ($properties as $property) {
        if (!is_string($property)
            || preg_match(
                '/\Atransaction\.([a-z][a-z0-9_]{0,63})\z/D',
                $property,
                $match
            ) !== 1
            || !in_array($match[1], $allowed, true)
            || isset($seen[$property])
            || !array_key_exists($match[1], $transaction)
        ) {
            return null;
        }
        $value = $transaction[$match[1]];
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $seen[$property] = true;
        $values[] = $value;
    }
    return $values;
}

function red_colombia_c1_event_checksum(array $event, $eventSecret)
{
    $values = red_colombia_c1_event_values($event);
    if (!is_array($values)
        || !is_string($eventSecret)
        || !is_int($event['timestamp'] ?? null)
    ) {
        return '';
    }
    foreach ($values as $value) {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }
    }
    return hash(
        'sha256',
        implode('', array_map('strval', $values))
            . (string) $event['timestamp']
            . $eventSecret
    );
}

function red_colombia_c1_event_evidence_hash(array $event)
{
    return red_colombia_c1_hash([
        'event' => $event['event'] ?? null,
        'environment' => $event['environment'] ?? null,
        'properties' => $event['signature']['properties'] ?? null,
        'checksum' => $event['signature']['checksum'] ?? null,
        'timestamp' => $event['timestamp'] ?? null,
    ]);
}

function red_colombia_c1_event_boundary(array $event, $eventSecret, $now)
{
    if (!red_colombia_c1_exact_keys($event, [
        'event', 'data', 'environment', 'signature', 'timestamp',
        'sentAtEpoch',
    ])
        || ($event['event'] ?? null) !== 'transaction.updated'
        || ($event['environment'] ?? null) !== 'test'
        || !is_array($event['data'] ?? null)
        || !is_array($event['signature'] ?? null)
        || !red_colombia_c1_exact_keys($event['signature'], [
            'properties', 'checksum',
        ])
        || !is_array($event['signature']['properties'] ?? null)
        || !array_is_list($event['signature']['properties'])
        || !red_colombia_c1_sha256(
            $event['signature']['checksum'] ?? null
        )
        || !is_int($event['timestamp'] ?? null)
        || !is_int($event['sentAtEpoch'] ?? null)
        || !is_int($now)
        || $event['timestamp'] > $now
        || $event['timestamp'] < $now - 90000
        || $event['sentAtEpoch'] < $event['timestamp']
        || $event['sentAtEpoch'] > $now
        || !is_string($eventSecret)
        || strlen($eventSecret) < 32
    ) {
        return red_colombia_c1_refusal('event_boundary_invalid');
    }
    $values = red_colombia_c1_event_values($event);
    if (!is_array($values)) {
        return red_colombia_c1_refusal('event_boundary_invalid');
    }
    foreach ($values as $value) {
        if (!is_string($value) && !is_int($value)) {
            return red_colombia_c1_refusal('event_boundary_invalid');
        }
    }
    $expected = red_colombia_c1_event_checksum($event, $eventSecret);
    if (!hash_equals($expected, $event['signature']['checksum'])) {
        return red_colombia_c1_refusal('event_checksum_invalid');
    }
    return [
        'accepted' => true,
        'reason' => 'event_verified',
        'value' => [
            'eventEvidenceSha256' => red_colombia_c1_event_evidence_hash(
                $event
            ),
            'receivedAtEpoch' => $event['sentAtEpoch'],
        ],
    ];
}

function red_colombia_c1_transaction_valid(array $transaction)
{
    return red_colombia_c1_exact_keys($transaction, [
        'id', 'status', 'amount_in_cents', 'reference', 'currency',
        'payment_method_type',
    ])
        && red_addon_payment_initiation_reference_valid(
            $transaction['id'] ?? null
        )
        && in_array(
            $transaction['status'] ?? null,
            ['PENDING', 'APPROVED', 'DECLINED', 'ERROR'],
            true
        )
        && is_int($transaction['amount_in_cents'] ?? null)
        && is_string($transaction['reference'] ?? null)
        && is_string($transaction['currency'] ?? null)
        && preg_match(
            '/\A[A-Z]{3}\z/D',
            $transaction['currency']
        ) === 1
        && ($transaction['payment_method_type'] ?? null) === 'NEQUI';
}

function red_colombia_c1_reconcile(
    array $order,
    array $initiation,
    array $boundary,
    array $event,
    array $lookup,
    array $seenEvidence
) {
    if (!red_colombia_c1_order_valid($order)
        || ($initiation['accepted'] ?? null) !== true
        || ($initiation['mode'] ?? null) !== 'out_of_band_confirmation'
        || !is_array($initiation['value'] ?? null)
        || ($boundary['accepted'] ?? null) !== true
        || !is_array($boundary['value'] ?? null)
        || !red_colombia_c1_sha256(
            $boundary['value']['eventEvidenceSha256'] ?? null
        )
        || !array_is_list($seenEvidence)
    ) {
        return red_colombia_c1_refusal('reconciliation_invalid');
    }
    foreach ($seenEvidence as $evidenceSha256) {
        if (!red_colombia_c1_sha256($evidenceSha256)) {
            return red_colombia_c1_refusal('replay_evidence_invalid');
        }
    }
    $evidenceSha256 = $boundary['value']['eventEvidenceSha256'];
    if (in_array($evidenceSha256, $seenEvidence, true)) {
        return red_colombia_c1_refusal('event_replayed');
    }
    if (!hash_equals(
        $evidenceSha256,
        red_colombia_c1_event_evidence_hash($event)
    )) {
        return red_colombia_c1_refusal('event_evidence_mismatch');
    }
    $eventTransaction = $event['data']['transaction'] ?? null;
    if (!is_array($eventTransaction)
        || !red_colombia_c1_transaction_valid($eventTransaction)
        || !red_colombia_c1_transaction_valid($lookup)
        || $eventTransaction !== $lookup
    ) {
        return red_colombia_c1_refusal('event_lookup_mismatch');
    }
    if (($initiation['value']['providerReference'] ?? null)
            !== $eventTransaction['id']
        || $eventTransaction['reference'] !== $order['orderId']
    ) {
        return red_colombia_c1_refusal('transaction_identity_mismatch');
    }
    if ($eventTransaction['amount_in_cents'] !== $order['amountMinor']) {
        return red_colombia_c1_refusal('transaction_amount_mismatch');
    }
    if ($eventTransaction['currency'] !== $order['currency']) {
        return red_colombia_c1_refusal('transaction_currency_mismatch');
    }
    $outcomes = [
        'APPROVED' => ['paid', true],
        'DECLINED' => ['failed', false],
        'ERROR' => ['failed', false],
    ];
    if (!array_key_exists($eventTransaction['status'], $outcomes)) {
        return red_colombia_c1_refusal('transaction_not_final');
    }
    [$outcome, $paymentVerified] = $outcomes[$eventTransaction['status']];
    return [
        'accepted' => true,
        'reason' => 'transaction_reconciled',
        'value' => [
            'providerReference' => $eventTransaction['id'],
            'eventEvidenceSha256' => $evidenceSha256,
            'orderId' => $order['orderId'],
            'outcome' => $outcome,
            'amountMinor' => $eventTransaction['amount_in_cents'],
            'currency' => 'COP',
            'paymentVerified' => $paymentVerified,
            'orderMutationAuthorized' => false,
            'source' => 'signed_event_and_lookup',
        ],
    ];
}

function red_colombia_c1_order_fixture()
{
    return [
        'clientId' => 'demo-red-sphere',
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'state' => 'awaiting_payment',
        'amountMinor' => 12500000,
        'currency' => 'COP',
        'snapshotSha256' => str_repeat('1', 64),
        'idempotencySha256' => str_repeat('2', 64),
        'customerEmail' => 'buyer@example.test',
        'customerPhone' => '3991111111',
    ];
}

function red_colombia_c1_config_fixture()
{
    return [
        'clientId' => 'demo-red-sphere',
        'provider' => 'wompi',
        'method' => 'nequi',
        'currency' => 'COP',
        'environment' => 'sandbox',
        'publicKeySettingPresent' => true,
        'enabled' => true,
    ];
}

function red_colombia_c1_acceptance_fixture()
{
    return [
        'privacyAccepted' => true,
        'personalDataAccepted' => true,
        'acceptanceToken' => 'fixture-acceptance-token-current',
        'personalAuthToken' => 'fixture-personal-auth-token-current',
        'contractsSha256' => str_repeat('3', 64),
    ];
}

function red_colombia_c1_secret_refs_fixture()
{
    return [
        'privateKey' => 'config:wompi-private-key',
        'integrityKey' => 'config:wompi-integrity-key',
        'eventSecret' => 'config:wompi-event-secret',
    ];
}

function red_colombia_c1_event_fixture(
    array $order,
    $providerReference,
    $status,
    $eventSecret,
    $now
) {
    $transaction = [
        'id' => $providerReference,
        'status' => $status,
        'amount_in_cents' => $order['amountMinor'],
        'reference' => $order['orderId'],
        'currency' => 'COP',
        'payment_method_type' => 'NEQUI',
    ];
    $event = [
        'event' => 'transaction.updated',
        'data' => ['transaction' => $transaction],
        'environment' => 'test',
        'signature' => [
            'properties' => red_colombia_c1_event_properties(),
            'checksum' => '',
        ],
        'timestamp' => $now - 5,
        'sentAtEpoch' => $now,
    ];
    $event['signature']['checksum'] = red_colombia_c1_event_checksum(
        $event,
        $eventSecret
    );
    return $event;
}

try {
    $hostedValue = [
        'providerReference' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
        'checkoutUrl' => 'https://checkout.stripe.com/c/pay/'
            . 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
    ];
    $hosted = red_addon_payment_initiation_normalize(
        'hosted_redirect',
        $hostedValue
    );
    red_colombia_c1_assert(
        ($hosted['accepted'] ?? false) === true,
        'the existing hosted initiation shape remains accepted'
    );
    red_colombia_c1_assert(
        ($hosted['mode'] ?? '') === 'hosted_redirect',
        'hosted initiation retains its closed mode'
    );
    red_colombia_c1_assert(
        ($hosted['value'] ?? null) === $hostedValue,
        'the existing hosted reference and URL value is unchanged'
    );
    red_colombia_c1_assert(
        red_addon_payment_initiation_normalize('hosted_redirect', [
            'providerReference' => $hostedValue['providerReference'],
            'checkoutUrl' => 'http://checkout.stripe.com/c/pay/example',
        ])['reason'] === 'hosted_redirect_invalid',
        'non-HTTPS hosted initiation is refused'
    );
    red_colombia_c1_assert(
        red_addon_payment_initiation_normalize('hosted_redirect', [
            'providerReference' => $hostedValue['providerReference'],
            'checkoutUrl' => $hostedValue['checkoutUrl'] . '?status=paid',
        ])['reason'] === 'hosted_redirect_invalid',
        'query-bearing hosted initiation is refused'
    );
    red_colombia_c1_assert(
        red_addon_payment_initiation_normalize('hosted_redirect', [
            'providerReference' => $hostedValue['providerReference'],
        ])['reason'] === 'hosted_redirect_invalid',
        'hosted initiation without its URL is refused'
    );
    red_colombia_c1_assert(
        red_addon_payment_initiation_normalize('hosted_redirect', [
            'providerReference' => $hostedValue['providerReference'],
            'checkoutUrl' => $hostedValue['checkoutUrl'],
            'state' => 'pending',
        ])['reason'] === 'hosted_redirect_invalid',
        'unknown hosted fields are refused'
    );

    $providerReference = '1234-1700000000-56789';
    $outOfBandValue = [
        'providerReference' => $providerReference,
        'state' => 'pending',
        'customerAction' => 'approve_in_provider_app',
    ];
    $outOfBand = red_addon_payment_initiation_normalize(
        'out_of_band_confirmation',
        $outOfBandValue
    );
    red_colombia_c1_assert(
        ($outOfBand['accepted'] ?? false) === true,
        'out-of-band provider-app approval is accepted'
    );
    red_colombia_c1_assert(
        ($outOfBand['value'] ?? null) === $outOfBandValue,
        'out-of-band output is exact and bounded'
    );
    red_colombia_c1_assert(
        !array_key_exists('checkoutUrl', $outOfBand['value']),
        'out-of-band initiation contains no checkout URL'
    );
    red_colombia_c1_assert(
        red_addon_payment_initiation_normalize(
            'out_of_band_confirmation',
            $outOfBandValue + ['checkoutUrl' => $hostedValue['checkoutUrl']]
        )['reason'] === 'out_of_band_confirmation_invalid',
        'URL-bearing out-of-band initiation is refused'
    );
    $paidInitiation = $outOfBandValue;
    $paidInitiation['state'] = 'paid';
    red_colombia_c1_assert(
        red_addon_payment_initiation_normalize(
            'out_of_band_confirmation',
            $paidInitiation
        )['reason'] === 'out_of_band_confirmation_invalid',
        'out-of-band initiation cannot claim paid state'
    );
    $providerNamedAction = $outOfBandValue;
    $providerNamedAction['customerAction'] = 'approve_in_nequi';
    red_colombia_c1_assert(
        red_addon_payment_initiation_normalize(
            'out_of_band_confirmation',
            $providerNamedAction
        )['reason'] === 'out_of_band_confirmation_invalid',
        'provider-named core customer actions are refused'
    );
    red_colombia_c1_assert(
        red_addon_payment_initiation_normalize(
            'nequi_push',
            $outOfBandValue
        )['reason'] === 'initiation_mode_invalid',
        'provider-named initiation modes are refused'
    );

    $order = red_colombia_c1_order_fixture();
    $config = red_colombia_c1_config_fixture();
    $acceptance = red_colombia_c1_acceptance_fixture();
    $secretRefs = red_colombia_c1_secret_refs_fixture();
    $integritySignature = hash('sha256', 'sealed-c1-integrity-fixture');
    $plan = red_colombia_c1_plan_request(
        $order,
        $config,
        $acceptance,
        $secretRefs,
        $integritySignature
    );
    red_colombia_c1_assert(
        ($plan['accepted'] ?? false) === true,
        'the exact COP and Nequi request is planned offline'
    );
    red_colombia_c1_assert(
        red_colombia_c1_exact_keys($plan['value'], [
            'provider', 'method', 'environment', 'orderId', 'amountMinor',
            'currency', 'requestSha256', 'acceptanceSha256',
            'privateKeyAvailable', 'integrityKeyAvailable',
            'eventSecretAvailable', 'providerContact', 'providerMutation',
            'payment', 'orderMutation',
        ]),
        'the request plan exposes only its exact bounded evidence'
    );
    red_colombia_c1_assert(
        red_colombia_c1_sha256($plan['value']['requestSha256'])
            && red_colombia_c1_sha256(
                $plan['value']['acceptanceSha256']
            ),
        'transient request and acceptance material are represented by hashes'
    );
    red_colombia_c1_assert(
        $plan['value']['providerContact'] === false
            && $plan['value']['providerMutation'] === false
            && $plan['value']['payment'] === false
            && $plan['value']['orderMutation'] === false,
        'offline planning records every provider and business effect false'
    );
    $serializedPlan = json_encode($plan, JSON_UNESCAPED_SLASHES);
    foreach ([
        $order['customerEmail'],
        $order['customerPhone'],
        $acceptance['acceptanceToken'],
        $acceptance['personalAuthToken'],
        $integritySignature,
        ...array_values($secretRefs),
    ] as $forbiddenValue) {
        red_colombia_c1_assert(
            !str_contains($serializedPlan, $forbiddenValue),
            'request evidence excludes personal, token, signature, and secret-reference inputs'
        );
    }

    $wrongCurrency = $order;
    $wrongCurrency['currency'] = 'USD';
    red_colombia_c1_assert(
        red_colombia_c1_plan_request(
            $wrongCurrency,
            $config,
            $acceptance,
            $secretRefs,
            $integritySignature
        ) === red_colombia_c1_refusal('request_invalid'),
        'non-COP order is refused before provider access'
    );
    $wrongPhone = $order;
    $wrongPhone['customerPhone'] = '2025550100';
    red_colombia_c1_assert(
        red_colombia_c1_plan_request(
            $wrongPhone,
            $config,
            $acceptance,
            $secretRefs,
            $integritySignature
        ) === red_colombia_c1_refusal('request_invalid'),
        'non-Colombian phone shape is refused'
    );
    $missingAcceptance = $acceptance;
    $missingAcceptance['personalDataAccepted'] = false;
    red_colombia_c1_assert(
        red_colombia_c1_plan_request(
            $order,
            $config,
            $missingAcceptance,
            $secretRefs,
            $integritySignature
        ) === red_colombia_c1_refusal('request_invalid'),
        'missing personal-data acceptance is refused'
    );
    $duplicateRefs = $secretRefs;
    $duplicateRefs['eventSecret'] = $duplicateRefs['integrityKey'];
    red_colombia_c1_assert(
        red_colombia_c1_plan_request(
            $order,
            $config,
            $acceptance,
            $duplicateRefs,
            $integritySignature
        ) === red_colombia_c1_refusal('request_invalid'),
        'secret-reference class reuse is refused'
    );
    $disabled = $config;
    $disabled['enabled'] = false;
    red_colombia_c1_assert(
        red_colombia_c1_plan_request(
            $order,
            $disabled,
            $acceptance,
            $secretRefs,
            $integritySignature
        ) === red_colombia_c1_refusal('request_invalid'),
        'disabled adapter configuration is refused'
    );

    $now = 1787443200;
    $eventSecret = hash('sha256', 'sealed-c1-event-fixture');
    $approvedEvent = red_colombia_c1_event_fixture(
        $order,
        $providerReference,
        'APPROVED',
        $eventSecret,
        $now
    );
    $boundary = red_colombia_c1_event_boundary(
        $approvedEvent,
        $eventSecret,
        $now
    );
    red_colombia_c1_assert(
        ($boundary['accepted'] ?? false) === true
            && red_colombia_c1_sha256(
                $boundary['value']['eventEvidenceSha256'] ?? null
            ),
        'the exact signed Sandbox transaction event is verified'
    );
    $serializedBoundary = json_encode(
        $boundary,
        JSON_UNESCAPED_SLASHES
    );
    red_colombia_c1_assert(
        !str_contains($serializedBoundary, $eventSecret)
            && !str_contains($serializedBoundary, $order['customerEmail'])
            && !str_contains($serializedBoundary, $order['customerPhone']),
        'event boundary output excludes secret and personal data'
    );
    $badChecksum = $approvedEvent;
    $badChecksum['signature']['checksum'] = str_repeat('0', 64);
    red_colombia_c1_assert(
        red_colombia_c1_event_boundary(
            $badChecksum,
            $eventSecret,
            $now
        ) === red_colombia_c1_refusal('event_checksum_invalid'),
        'invalid event checksum is refused'
    );
    $staleEvent = $approvedEvent;
    $staleEvent['timestamp'] = $now - 90001;
    red_colombia_c1_assert(
        red_colombia_c1_event_boundary(
            $staleEvent,
            $eventSecret,
            $now
        ) === red_colombia_c1_refusal('event_boundary_invalid'),
        'stale event is refused before parsed outcome use'
    );
    $variedProperties = $approvedEvent;
    $variedProperties['signature']['properties'] = [
        'transaction.amount_in_cents',
        'transaction.id',
        'transaction.status',
    ];
    $variedProperties['signature']['checksum']
        = red_colombia_c1_event_checksum($variedProperties, $eventSecret);
    red_colombia_c1_assert(
        red_colombia_c1_event_boundary(
            $variedProperties,
            $eventSecret,
            $now
        )['accepted'] === true,
        'provider-supplied signed properties are resolved in declared order'
    );
    $unknownProperties = $approvedEvent;
    $unknownProperties['signature']['properties'][] = 'customer.email';
    red_colombia_c1_assert(
        red_colombia_c1_event_boundary(
            $unknownProperties,
            $eventSecret,
            $now
        ) === red_colombia_c1_refusal('event_boundary_invalid'),
        'unknown signed-event properties are refused'
    );
    $productionEvent = $approvedEvent;
    $productionEvent['environment'] = 'prod';
    red_colombia_c1_assert(
        red_colombia_c1_event_boundary(
            $productionEvent,
            $eventSecret,
            $now
        ) === red_colombia_c1_refusal('event_boundary_invalid'),
        'production event is refused by the Sandbox-only fixture'
    );

    $lookup = $approvedEvent['data']['transaction'];
    $paid = red_colombia_c1_reconcile(
        $order,
        $outOfBand,
        $boundary,
        $approvedEvent,
        $lookup,
        []
    );
    red_colombia_c1_assert(
        ($paid['accepted'] ?? false) === true
            && ($paid['value']['outcome'] ?? '') === 'paid'
            && ($paid['value']['paymentVerified'] ?? false) === true,
        'matching signed event and lookup propose one verified paid outcome'
    );
    red_colombia_c1_assert(
        ($paid['value']['orderMutationAuthorized'] ?? true) === false,
        'C1 verification never authorizes an order mutation'
    );
    $serializedPaid = json_encode($paid, JSON_UNESCAPED_SLASHES);
    foreach ([
        $order['customerEmail'],
        $order['customerPhone'],
        $eventSecret,
        $acceptance['acceptanceToken'],
        $acceptance['personalAuthToken'],
    ] as $forbiddenValue) {
        red_colombia_c1_assert(
            !str_contains($serializedPaid, $forbiddenValue),
            'normalized paid evidence excludes personal and secret material'
        );
    }
    red_colombia_c1_assert(
        red_colombia_c1_reconcile(
            $order,
            $outOfBand,
            $boundary,
            $approvedEvent,
            $lookup,
            [$boundary['value']['eventEvidenceSha256']]
        ) === red_colombia_c1_refusal('event_replayed'),
        'replayed event evidence is refused'
    );
    $wrongAmountLookup = $lookup;
    $wrongAmountLookup['amount_in_cents']++;
    red_colombia_c1_assert(
        red_colombia_c1_reconcile(
            $order,
            $outOfBand,
            $boundary,
            $approvedEvent,
            $wrongAmountLookup,
            []
        ) === red_colombia_c1_refusal('event_lookup_mismatch'),
        'event and lookup amount disagreement is refused'
    );
    $wrongAmountOrder = $order;
    $wrongAmountOrder['amountMinor']++;
    $wrongAmountEvent = red_colombia_c1_event_fixture(
        $wrongAmountOrder,
        $providerReference,
        'APPROVED',
        $eventSecret,
        $now
    );
    $wrongAmountBoundary = red_colombia_c1_event_boundary(
        $wrongAmountEvent,
        $eventSecret,
        $now
    );
    red_colombia_c1_assert(
        red_colombia_c1_reconcile(
            $order,
            $outOfBand,
            $wrongAmountBoundary,
            $wrongAmountEvent,
            $wrongAmountEvent['data']['transaction'],
            []
        ) === red_colombia_c1_refusal('transaction_amount_mismatch'),
        'matching event and lookup with a changed amount are refused'
    );
    $wrongCurrencyEvent = $approvedEvent;
    $wrongCurrencyEvent['data']['transaction']['currency'] = 'USD';
    $wrongCurrencyEvent['signature']['checksum']
        = red_colombia_c1_event_checksum($wrongCurrencyEvent, $eventSecret);
    $wrongCurrencyBoundary = red_colombia_c1_event_boundary(
        $wrongCurrencyEvent,
        $eventSecret,
        $now
    );
    red_colombia_c1_assert(
        red_colombia_c1_reconcile(
            $order,
            $outOfBand,
            $wrongCurrencyBoundary,
            $wrongCurrencyEvent,
            $wrongCurrencyEvent['data']['transaction'],
            []
        ) === red_colombia_c1_refusal('transaction_currency_mismatch'),
        'matching event and lookup with non-COP currency are refused'
    );
    $wrongOrderBoundary = red_colombia_c1_event_boundary(
        red_colombia_c1_event_fixture(
            array_replace($order, [
                'orderId' => 'ord_ffffffffffffffffffffffffffffffff',
            ]),
            $providerReference,
            'APPROVED',
            $eventSecret,
            $now
        ),
        $eventSecret,
        $now
    );
    red_colombia_c1_assert(
        red_colombia_c1_reconcile(
            $order,
            $outOfBand,
            $wrongOrderBoundary,
            red_colombia_c1_event_fixture(
                array_replace($order, [
                    'orderId' => 'ord_ffffffffffffffffffffffffffffffff',
                ]),
                $providerReference,
                'APPROVED',
                $eventSecret,
                $now
            ),
            red_colombia_c1_event_fixture(
                array_replace($order, [
                    'orderId' => 'ord_ffffffffffffffffffffffffffffffff',
                ]),
                $providerReference,
                'APPROVED',
                $eventSecret,
                $now
            )['data']['transaction'],
            []
        ) === red_colombia_c1_refusal('transaction_identity_mismatch'),
        'changed order reference is refused after event verification'
    );
    $wrongProviderInitiation = red_addon_payment_initiation_normalize(
        'out_of_band_confirmation',
        array_replace($outOfBandValue, [
            'providerReference' => '9999-1700000000-00000',
        ])
    );
    red_colombia_c1_assert(
        red_colombia_c1_reconcile(
            $order,
            $wrongProviderInitiation,
            $boundary,
            $approvedEvent,
            $lookup,
            []
        ) === red_colombia_c1_refusal('transaction_identity_mismatch'),
        'changed provider reference is refused'
    );
    $declinedEventForSwap = red_colombia_c1_event_fixture(
        $order,
        $providerReference,
        'DECLINED',
        $eventSecret,
        $now
    );
    red_colombia_c1_assert(
        red_colombia_c1_reconcile(
            $order,
            $outOfBand,
            $boundary,
            $declinedEventForSwap,
            $declinedEventForSwap['data']['transaction'],
            []
        ) === red_colombia_c1_refusal('event_evidence_mismatch'),
        'a verified boundary cannot be paired with a different parsed event'
    );

    foreach (['DECLINED', 'ERROR'] as $failedStatus) {
        $failedEvent = red_colombia_c1_event_fixture(
            $order,
            $providerReference,
            $failedStatus,
            $eventSecret,
            $now
        );
        $failedBoundary = red_colombia_c1_event_boundary(
            $failedEvent,
            $eventSecret,
            $now
        );
        $failed = red_colombia_c1_reconcile(
            $order,
            $outOfBand,
            $failedBoundary,
            $failedEvent,
            $failedEvent['data']['transaction'],
            []
        );
        red_colombia_c1_assert(
            ($failed['accepted'] ?? false) === true
                && ($failed['value']['outcome'] ?? '') === 'failed'
                && ($failed['value']['paymentVerified'] ?? true) === false
                && ($failed['value']['orderMutationAuthorized'] ?? true)
                    === false,
            $failedStatus . ' is normalized as failure without mutation'
        );
    }

    $pendingEvent = red_colombia_c1_event_fixture(
        $order,
        $providerReference,
        'PENDING',
        $eventSecret,
        $now
    );
    $pendingBoundary = red_colombia_c1_event_boundary(
        $pendingEvent,
        $eventSecret,
        $now
    );
    red_colombia_c1_assert(
        red_colombia_c1_reconcile(
            $order,
            $outOfBand,
            $pendingBoundary,
            $pendingEvent,
            $pendingEvent['data']['transaction'],
            []
        ) === red_colombia_c1_refusal('transaction_not_final'),
        'pending status cannot propose a payment outcome'
    );

    echo 'Colombia C1 payment-initiation contract self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
