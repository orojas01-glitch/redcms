<?php
/** Restartable internal coordinator for one Stripe subscription delivery. */

require_once __DIR__ . '/addon_subscription_event_journal_helpers.php';

if (!function_exists('red_addon_subscription_delivery_result')) {
    function red_addon_subscription_delivery_result($reason = 'invalid')
    {
        return [
            'valid' => false,
            'status' => '',
            'stage' => '',
            'intentReference' => '',
            'providerEventType' => '',
            'eventRefSha256' => '',
            'rawBodySha256' => '',
            'signatureEvidenceSha256' => '',
            'claimStateSha256' => '',
            'eventEvidenceSha256' => '',
            'lifecycleResultSha256' => '',
            'signatureVerified' => false,
            'journalClaimed' => false,
            'eventProjected' => false,
            'lifecycleApplied' => false,
            'lifecycleReplayed' => false,
            'journalCompleted' => false,
            'restartable' => false,
            'replayed' => false,
            'rawBodyIncluded' => false,
            'signatureHeaderIncluded' => false,
            'secretIncluded' => false,
            'customerDataIncluded' => false,
            'paymentMethodDataIncluded' => false,
            'addressDataIncluded' => false,
            'routeExposure' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'payment' => false,
            'browserNavigation' => false,
            'deployment' => false,
            'reason' => (string) $reason,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_subscription_delivery_envelope_valid')) {
    function red_addon_subscription_delivery_envelope_valid(
        $envelope,
        $rawBody,
        $receivedAt
    ) {
        if (!is_array($envelope)
            || array_keys($envelope) !== [
                'valid', 'verification', 'providerEnvironment',
                'apiVersion', 'eventType', 'eventRefSha256',
                'eventCreatedAt', 'objectType',
                'objectProjectionSha256', 'signedAt', 'receivedAt',
                'signatureAgeSeconds', 'signatureCount', 'bodyBytes',
                'rawBodySha256', 'signatureEvidenceSha256',
                'rawBodyIncluded', 'signatureHeaderIncluded',
                'endpointSecretIncluded', 'decodedEventIncluded',
                'customerDataIncluded', 'networkAccess',
                'providerContact', 'routeExposure', 'errors',
            ]
            || ($envelope['valid'] ?? null) !== true
            || ($envelope['verification'] ?? '') !== 'verified'
            || ($envelope['providerEnvironment'] ?? '') !== 'sandbox'
            || !in_array(
                $envelope['apiVersion'] ?? '',
                ['2024-09-30.acacia', '2026-07-29.dahlia'],
                true
            )
            || !red_addon_valid_sha256(
                $envelope['eventRefSha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $envelope['objectProjectionSha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $envelope['rawBodySha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $envelope['signatureEvidenceSha256'] ?? null
            )
            || ($envelope['rawBodySha256'] ?? '') !== hash('sha256', $rawBody)
            || ($envelope['bodyBytes'] ?? 0) !== strlen($rawBody)
            || ($envelope['receivedAt'] ?? 0) !== $receivedAt
            || !is_int($envelope['signedAt'] ?? null)
            || abs($receivedAt - $envelope['signedAt']) > 300
            || !is_int($envelope['eventCreatedAt'] ?? null)
            || $envelope['eventCreatedAt'] > $envelope['signedAt']
            || ($envelope['rawBodyIncluded'] ?? true) !== false
            || ($envelope['signatureHeaderIncluded'] ?? true) !== false
            || ($envelope['endpointSecretIncluded'] ?? true) !== false
            || ($envelope['decodedEventIncluded'] ?? true) !== false
            || ($envelope['customerDataIncluded'] ?? true) !== false
            || ($envelope['networkAccess'] ?? true) !== false
            || ($envelope['providerContact'] ?? true) !== false
            || ($envelope['routeExposure'] ?? true) !== false
            || ($envelope['errors'] ?? null) !== []
        ) {
            return false;
        }
        $types = [
            'checkout.session.completed' => 'checkout.session',
            'invoice.paid' => 'invoice',
            'invoice.payment_failed' => 'invoice',
            'customer.subscription.deleted' => 'subscription',
            'checkout.session.expired' => 'checkout.session',
        ];
        return ($types[$envelope['eventType'] ?? ''] ?? null)
            === ($envelope['objectType'] ?? null);
    }
}

if (!function_exists('red_addon_subscription_delivery_projection_valid')) {
    function red_addon_subscription_delivery_projection_valid(
        $projection,
        $envelope
    ) {
        if (!is_array($projection)
            || array_keys($projection) !== [
                'valid', 'verifiedEvent', 'rawBodySha256',
                'signatureEvidenceSha256', 'customerDataIncluded',
                'paymentMethodDataIncluded', 'addressDataIncluded',
                'rawEventIncluded', 'errors',
            ]
            || ($projection['valid'] ?? null) !== true
            || !red_addon_subscription_event_verified_shape_valid(
                $projection['verifiedEvent'] ?? null
            )
            || ($projection['rawBodySha256'] ?? '')
                !== $envelope['rawBodySha256']
            || ($projection['signatureEvidenceSha256'] ?? '')
                !== $envelope['signatureEvidenceSha256']
            || ($projection['customerDataIncluded'] ?? true) !== false
            || ($projection['paymentMethodDataIncluded'] ?? true) !== false
            || ($projection['addressDataIncluded'] ?? true) !== false
            || ($projection['rawEventIncluded'] ?? true) !== false
            || ($projection['errors'] ?? null) !== []
        ) {
            return false;
        }
        $event = $projection['verifiedEvent'];
        return hash('sha256', $event['eventRef'])
                === $envelope['eventRefSha256']
            && $event['eventType'] === $envelope['eventType']
            && $event['occurredAt'] === $envelope['eventCreatedAt']
            && $event['receivedAt'] === $envelope['receivedAt'];
    }
}

if (!function_exists('red_addon_subscription_delivery_claim')) {
    function red_addon_subscription_delivery_claim($envelope)
    {
        $verified = [
            'eventRefSha256' => $envelope['eventRefSha256'],
            'rawBodySha256' => $envelope['rawBodySha256'],
            'signatureEvidenceSha256' =>
                $envelope['signatureEvidenceSha256'],
            'eventType' => $envelope['eventType'],
            'signedAt' => $envelope['signedAt'],
            'receivedAt' => $envelope['receivedAt'],
        ];
        $claimStateSha256 = hash('sha256', json_encode([
            'purpose' => 'subscription-event-receipt-claim',
            'verified' => $verified,
        ], JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR));
        return [
            'eventRefSha256' => $verified['eventRefSha256'],
            'rawBodySha256' => $verified['rawBodySha256'],
            'signatureEvidenceSha256' =>
                $verified['signatureEvidenceSha256'],
            'providerEventType' => $verified['eventType'],
            'claimStateSha256' => $claimStateSha256,
            'signedAtEpoch' => $verified['signedAt'],
            'receivedAtEpoch' => $verified['receivedAt'],
        ];
    }
}

if (!function_exists('red_addon_subscription_delivery_journal_valid')) {
    function red_addon_subscription_delivery_journal_valid(
        $journal,
        $claim,
        $allowedStatuses
    ) {
        if (!is_array($journal)
            || array_keys($journal) !== [
                'status', 'claimStateSha256',
                'eventEvidenceSha256', 'lifecycleResultSha256',
            ]
            || !in_array($journal['status'] ?? '', $allowedStatuses, true)
        ) {
            return false;
        }
        if ($journal['status'] === 'absent') {
            return $journal['claimStateSha256'] === ''
                && $journal['eventEvidenceSha256'] === ''
                && $journal['lifecycleResultSha256'] === '';
        }
        if (!red_addon_valid_sha256($journal['claimStateSha256'])) {
            return false;
        }
        if ($journal['status'] === 'verified') {
            return $journal['eventEvidenceSha256'] === ''
                && $journal['lifecycleResultSha256'] === '';
        }
        return red_addon_valid_sha256($journal['eventEvidenceSha256'])
            && red_addon_valid_sha256(
                $journal['lifecycleResultSha256']
            );
    }
}

if (!function_exists('red_addon_subscription_event_delivery_coordinate')) {
    function red_addon_subscription_event_delivery_coordinate(
        $binding,
        $request,
        $signatureVerifier,
        $eventProjector,
        $journalInvoker,
        $serviceInvoker,
        $adapterInvoker,
        $completedAtEpoch
    ) {
        $result = red_addon_subscription_delivery_result();
        if (!red_addon_subscription_event_binding_valid($binding)
            || !is_array($request)
            || array_keys($request) !== [
                'rawBody', 'signatureHeader', 'receivedAt',
            ]
            || !is_string($request['rawBody'])
            || strlen($request['rawBody']) < 2
            || strlen($request['rawBody']) > 262144
            || preg_match('//u', $request['rawBody']) !== 1
            || !is_string($request['signatureHeader'])
            || strlen($request['signatureHeader']) < 1
            || strlen($request['signatureHeader']) > 4096
            || preg_match(
                '/[^\x21-\x7E]/',
                $request['signatureHeader']
            ) === 1
            || !is_int($request['receivedAt'])
            || $request['receivedAt'] < 1
            || $request['receivedAt'] > 4102444800
            || !is_int($completedAtEpoch)
            || $completedAtEpoch < $request['receivedAt']
            || $completedAtEpoch > 4102444800
            || !is_callable($signatureVerifier)
            || !is_callable($eventProjector)
            || !is_callable($journalInvoker)
            || !is_callable($serviceInvoker)
            || !is_callable($adapterInvoker)
        ) {
            return $result;
        }
        try {
            $envelope = $signatureVerifier(
                $request['rawBody'],
                $request['signatureHeader'],
                $request['receivedAt']
            );
            if (!red_addon_subscription_delivery_envelope_valid(
                $envelope,
                $request['rawBody'],
                $request['receivedAt']
            )) {
                $result['stage'] = 'signature_verification';
                $result['reason'] = 'subscription_signature_refused';
                return $result;
            }
            $result = array_replace($result, [
                'stage' => 'journal_claim',
                'providerEventType' => $envelope['eventType'],
                'eventRefSha256' => $envelope['eventRefSha256'],
                'rawBodySha256' => $envelope['rawBodySha256'],
                'signatureEvidenceSha256' =>
                    $envelope['signatureEvidenceSha256'],
                'signatureVerified' => true,
            ]);
            $claim = red_addon_subscription_delivery_claim($envelope);
            $result['claimStateSha256'] = $claim['claimStateSha256'];
            $journal = $journalInvoker('inspect', $claim);
            if (!red_addon_subscription_delivery_journal_valid(
                $journal,
                $claim,
                ['absent', 'verified', 'applied', 'refused']
            )) {
                $result['reason'] = 'subscription_journal_unavailable';
                return $result;
            }
            if ($journal['status'] !== 'absent') {
                $claim['claimStateSha256'] =
                    $journal['claimStateSha256'];
                $result['claimStateSha256'] =
                    $journal['claimStateSha256'];
            }
            if ($journal['status'] === 'applied') {
                return array_replace($result, [
                    'valid' => true,
                    'status' => 'replayed',
                    'stage' => 'completed',
                    'eventEvidenceSha256' =>
                        $journal['eventEvidenceSha256'],
                    'lifecycleResultSha256' =>
                        $journal['lifecycleResultSha256'],
                    'journalCompleted' => true,
                    'replayed' => true,
                    'reason' => 'subscription_event_replayed',
                ]);
            }
            if ($journal['status'] === 'refused') {
                return array_replace($result, [
                    'status' => 'refused',
                    'stage' => 'completed',
                    'eventEvidenceSha256' =>
                        $journal['eventEvidenceSha256'],
                    'lifecycleResultSha256' =>
                        $journal['lifecycleResultSha256'],
                    'journalCompleted' => true,
                    'replayed' => true,
                    'reason' => 'subscription_event_previously_refused',
                ]);
            }
            if ($journal['status'] === 'absent') {
                $journal = $journalInvoker('claim', $claim);
                if (!red_addon_subscription_delivery_journal_valid(
                    $journal,
                    $claim,
                    ['verified']
                ) || $journal['claimStateSha256']
                    !== $claim['claimStateSha256']
                ) {
                    $result['reason'] = 'subscription_journal_claim_failed';
                    return $result;
                }
                $result['journalClaimed'] = true;
            }
            $result['restartable'] = true;
            $result['stage'] = 'event_projection';
            $projection = $eventProjector(
                $envelope,
                $request['rawBody']
            );
            if (!red_addon_subscription_delivery_projection_valid(
                $projection,
                $envelope
            )) {
                $result['status'] = 'verified';
                $result['reason'] = 'subscription_projection_refused';
                return $result;
            }
            $verifiedEvent = $projection['verifiedEvent'];
            $result = array_replace($result, [
                'stage' => 'lifecycle_application',
                'intentReference' => $verifiedEvent['intentReference'],
                'eventEvidenceSha256' =>
                    $verifiedEvent['eventEvidenceSha256'],
                'eventProjected' => true,
            ]);
            $coordinated = red_addon_subscription_event_coordinate(
                $binding,
                $verifiedEvent['intentReference'],
                $verifiedEvent,
                $serviceInvoker,
                $adapterInvoker
            );
            $completionStatus = ($coordinated['valid'] ?? false)
                ? 'applied' : 'refused';
            $lifecycleResultSha256 = ($coordinated['valid'] ?? false)
                ? ($coordinated['lifecycleResultSha256'] ?? '')
                : hash('sha256', json_encode([
                    'schema' => 1,
                    'purpose' => 'subscription-event-lifecycle-refusal',
                    'binding' => $binding,
                    'eventRefSha256' => $envelope['eventRefSha256'],
                    'intentReference' =>
                        $verifiedEvent['intentReference'],
                    'eventEvidenceSha256' =>
                        $verifiedEvent['eventEvidenceSha256'],
                    'reason' => $coordinated['reason'] ?? 'invalid',
                ], JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR));
            if (!red_addon_valid_sha256($lifecycleResultSha256)) {
                $result['reason'] = 'subscription_lifecycle_failed';
                return $result;
            }
            $result['lifecycleResultSha256'] = $lifecycleResultSha256;
            $result['lifecycleApplied'] =
                ($coordinated['applied'] ?? false) === true;
            $result['lifecycleReplayed'] =
                ($coordinated['replayed'] ?? false) === true;
            $completion = array_merge($claim, [
                'status' => $completionStatus,
                'intentReference' => $verifiedEvent['intentReference'],
                'eventEvidenceSha256' =>
                    $verifiedEvent['eventEvidenceSha256'],
                'lifecycleResultSha256' => $lifecycleResultSha256,
                'completedAtEpoch' => $completedAtEpoch,
            ]);
            $result['stage'] = 'journal_completion';
            $journal = $journalInvoker('complete', $completion);
            if (!red_addon_subscription_delivery_journal_valid(
                $journal,
                $claim,
                [$completionStatus]
            )
                || $journal['claimStateSha256']
                    !== $claim['claimStateSha256']
                || $journal['eventEvidenceSha256']
                    !== $verifiedEvent['eventEvidenceSha256']
                || $journal['lifecycleResultSha256']
                    !== $lifecycleResultSha256
            ) {
                $result['status'] = 'verified';
                $result['reason'] =
                    'subscription_journal_completion_pending';
                return $result;
            }
            return array_replace($result, [
                'valid' => $completionStatus === 'applied',
                'status' => $completionStatus,
                'stage' => 'completed',
                'journalCompleted' => true,
                'restartable' => false,
                'reason' => $completionStatus === 'applied'
                    ? 'subscription_event_applied'
                    : 'subscription_event_refused',
            ]);
        } catch (Throwable $throwable) {
            $result['reason'] = 'subscription_delivery_failed';
            return $result;
        }
    }
}

?>
