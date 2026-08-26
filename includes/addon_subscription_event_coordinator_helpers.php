<?php
/**
 * Unlinked coordinator for one already-verified subscription event.
 *
 * The future ingress layer must verify the Stripe signature and construct the
 * bounded event projection before calling this helper. This file reads no
 * request, secret, database, package, or transport state directly.
 */

require_once __DIR__
    . '/addon_subscription_checkout_coordinator_helpers.php';

if (!function_exists('red_addon_subscription_event_result')) {
    function red_addon_subscription_event_result($reason = 'invalid')
    {
        return [
            'valid' => false,
            'status' => '',
            'intentReference' => '',
            'providerEventType' => '',
            'providerEventRefSha256' => '',
            'eventEvidenceSha256' => '',
            'lifecycleResultSha256' => '',
            'subscriptionStatus' => '',
            'entitlementStatus' => '',
            'providerSubscriptionRefSha256' => null,
            'currentPeriodEndEpoch' => null,
            'applied' => false,
            'replayed' => false,
            'signatureVerificationPerformed' => false,
            'webhookIngress' => false,
            'routeExposure' => false,
            'secretResolution' => false,
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

if (!function_exists('red_addon_subscription_event_binding_valid')) {
    function red_addon_subscription_event_binding_valid($binding)
    {
        return red_addon_subscription_checkout_binding_valid($binding)
            && ($binding['storePackageVersion'] ?? '') === '0.1.50'
            && ($binding['stripePackageVersion'] ?? '') === '0.1.13';
    }
}

if (!function_exists('red_addon_subscription_event_current_valid')) {
    function red_addon_subscription_event_current_valid($data, $intentReference)
    {
        return is_array($data)
            && ($data['status'] ?? '') === 'found'
            && ($data['intentReference'] ?? '') === $intentReference
            && red_addon_valid_sha256($data['offerStateSha256'] ?? null)
            && in_array(
                $data['subscriptionStatus'] ?? '',
                ['pending', 'active', 'past_due', 'canceled', 'expired'],
                true
            )
            && in_array(
                $data['entitlementStatus'] ?? '',
                ['inactive', 'active', 'revoked'],
                true
            )
            && (($data['providerSubscriptionRefSha256'] ?? null) === null
                || red_addon_valid_sha256(
                    $data['providerSubscriptionRefSha256']
                ))
            && (($data['currentPeriodEndEpoch'] ?? null) === null
                || (is_int($data['currentPeriodEndEpoch'])
                    && $data['currentPeriodEndEpoch'] >= 1
                    && $data['currentPeriodEndEpoch'] <= 4102444800))
            && red_addon_valid_sha256(
                $data['checkoutSessionRefSha256'] ?? null
            )
            && red_addon_valid_sha256(
                $data['lastEventEvidenceSha256'] ?? null
            );
    }
}

if (!function_exists('red_addon_subscription_event_projection_valid')) {
    function red_addon_subscription_event_projection_valid(
        $data,
        $intentReference,
        $offerStateSha256
    ) {
        $event = is_array($data) ? ($data['event'] ?? null) : null;
        return is_array($data)
            && ($data['valid'] ?? null) === true
            && is_array($event)
            && array_keys($event) === [
                'verification', 'replayStatus', 'intentReference',
                'offerStateSha256', 'outcome',
                'providerSubscriptionRefSha256', 'currentPeriodEndEpoch',
                'eventEvidenceSha256', 'occurredAt',
            ]
            && ($event['verification'] ?? '') === 'verified'
            && ($event['replayStatus'] ?? '') === 'unseen'
            && ($event['intentReference'] ?? '') === $intentReference
            && ($event['offerStateSha256'] ?? '') === $offerStateSha256
            && in_array(
                $event['outcome'] ?? '',
                ['activated', 'renewed', 'past_due', 'canceled', 'expired'],
                true
            )
            && (($event['providerSubscriptionRefSha256'] ?? null) === null
                || red_addon_valid_sha256(
                    $event['providerSubscriptionRefSha256']
                ))
            && (($event['currentPeriodEndEpoch'] ?? null) === null
                || (is_int($event['currentPeriodEndEpoch'])
                    && $event['currentPeriodEndEpoch'] >= 1
                    && $event['currentPeriodEndEpoch'] <= 4102444800))
            && red_addon_valid_sha256(
                $event['eventEvidenceSha256'] ?? null
            )
            && is_int($event['occurredAt'] ?? null)
            && $event['occurredAt'] >= 1
            && $event['occurredAt'] <= 4102444800
            && in_array(
                $data['providerEventType'] ?? '',
                [
                    'checkout.session.completed', 'invoice.paid',
                    'invoice.payment_failed',
                    'customer.subscription.deleted',
                    'checkout.session.expired',
                ],
                true
            )
            && red_addon_valid_sha256(
                $data['providerEventRefSha256'] ?? null
            )
            && ($data['rawProviderReferenceIncluded'] ?? true) === false
            && ($data['rawCheckoutReferenceIncluded'] ?? true) === false
            && ($data['customerDataIncluded'] ?? true) === false
            && ($data['errors'] ?? null) === [];
    }
}

if (!function_exists('red_addon_subscription_event_coordinate')) {
    function red_addon_subscription_event_coordinate(
        $binding,
        $intentReference,
        $verifiedEvent,
        $serviceInvoker,
        $adapterInvoker
    ) {
        $result = red_addon_subscription_event_result();
        if (!red_addon_subscription_event_binding_valid($binding)
            || !is_string($intentReference)
            || preg_match(
                '/\Asint_[a-f0-9]{32}\z/D',
                $intentReference
            ) !== 1
            || !is_array($verifiedEvent)
            || !is_callable($serviceInvoker)
            || !is_callable($adapterInvoker)
        ) {
            return $result;
        }
        try {
            $loaded = $serviceInvoker(
                $binding['storeService'],
                'subscription.lifecycle.load',
                ['intentReference' => $intentReference]
            );
            $currentData = is_array($loaded)
                ? ($loaded['data'] ?? null) : null;
            if (!red_addon_subscription_checkout_invocation_valid(
                $loaded,
                $binding['storePackageId'],
                'subscription.lifecycle.load'
            ) || !red_addon_subscription_event_current_valid(
                $currentData,
                $intentReference
            )) {
                $result['reason'] = 'subscription_current_unavailable';
                return $result;
            }
            $expected = [
                'intentReference' => $currentData['intentReference'],
                'offerStateSha256' => $currentData['offerStateSha256'],
                'subscriptionStatus' =>
                    $currentData['subscriptionStatus'],
                'entitlementStatus' =>
                    $currentData['entitlementStatus'],
                'providerSubscriptionRefSha256' =>
                    $currentData['providerSubscriptionRefSha256'],
                'currentPeriodEndEpoch' =>
                    $currentData['currentPeriodEndEpoch'],
                'checkoutSessionRefSha256' =>
                    $currentData['checkoutSessionRefSha256'],
            ];
            $normalized = $adapterInvoker(
                $binding['stripeAdapter'],
                'subscription.event.normalize-sandbox-verified',
                [
                    'expected' => $expected,
                    'verifiedEvent' => $verifiedEvent,
                ]
            );
            $normalizedData = is_array($normalized)
                ? ($normalized['data'] ?? null) : null;
            if (!red_addon_subscription_checkout_invocation_valid(
                $normalized,
                $binding['stripePackageId'],
                'subscription.event.normalize-sandbox-verified'
            ) || !red_addon_subscription_event_projection_valid(
                $normalizedData,
                $intentReference,
                $currentData['offerStateSha256']
            )) {
                $result['reason'] = 'subscription_event_refused';
                return $result;
            }
            $event = $normalizedData['event'];
            $applied = $serviceInvoker(
                $binding['storeService'],
                'subscription.event.apply',
                [
                    'current' => array_slice($expected, 0, 6, true),
                    'event' => $event,
                ]
            );
            $appliedData = is_array($applied)
                ? ($applied['data'] ?? null) : null;
            $status = is_array($appliedData)
                ? ($appliedData['status'] ?? '') : '';
            $target = [
                'activated' => ['active', 'active'],
                'renewed' => ['active', 'active'],
                'past_due' => ['past_due', 'revoked'],
                'canceled' => ['canceled', 'revoked'],
                'expired' => ['expired', 'inactive'],
            ][$event['outcome']] ?? null;
            if (!red_addon_subscription_checkout_invocation_valid(
                $applied,
                $binding['storePackageId'],
                'subscription.event.apply'
            )
                || !in_array($status, ['applied', 'replayed'], true)
                || !is_array($target)
                || ($appliedData['intentReference'] ?? '')
                    !== $intentReference
                || ($appliedData['offerStateSha256'] ?? '')
                    !== $currentData['offerStateSha256']
                || ($appliedData['subscriptionStatus'] ?? '')
                    !== $target[0]
                || ($appliedData['entitlementStatus'] ?? '')
                    !== $target[1]
                || ($appliedData['providerSubscriptionRefSha256'] ?? null)
                    !== $event['providerSubscriptionRefSha256']
                || ($appliedData['currentPeriodEndEpoch'] ?? null)
                    !== $event['currentPeriodEndEpoch']
                || ($appliedData['lastEventEvidenceSha256'] ?? '')
                    !== $event['eventEvidenceSha256']
            ) {
                $result['reason'] = 'subscription_event_apply_failed';
                return $result;
            }
            $resultSha256 = hash('sha256', json_encode([
                'schema' => 1,
                'purpose' => 'subscription-event-lifecycle-result',
                'binding' => $binding,
                'intentReference' => $intentReference,
                'providerEventType' =>
                    $normalizedData['providerEventType'],
                'providerEventRefSha256' =>
                    $normalizedData['providerEventRefSha256'],
                'event' => $event,
                'status' => $status,
                'subscriptionStatus' => $target[0],
                'entitlementStatus' => $target[1],
            ], JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR));
            return array_replace($result, [
                'valid' => true,
                'status' => $status,
                'intentReference' => $intentReference,
                'providerEventType' =>
                    $normalizedData['providerEventType'],
                'providerEventRefSha256' =>
                    $normalizedData['providerEventRefSha256'],
                'eventEvidenceSha256' =>
                    $event['eventEvidenceSha256'],
                'lifecycleResultSha256' => $resultSha256,
                'subscriptionStatus' => $target[0],
                'entitlementStatus' => $target[1],
                'providerSubscriptionRefSha256' =>
                    $event['providerSubscriptionRefSha256'],
                'currentPeriodEndEpoch' =>
                    $event['currentPeriodEndEpoch'],
                'applied' => $status === 'applied',
                'replayed' => $status === 'replayed',
                'reason' => 'subscription_event_' . $status,
                'errors' => [],
            ]);
        } catch (Throwable $throwable) {
            $result['reason'] = 'subscription_event_failed';
            return $result;
        }
    }
}

?>
