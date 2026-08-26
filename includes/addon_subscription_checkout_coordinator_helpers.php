<?php
/**
 * Internal synthetic subscription Checkout coordinator.
 *
 * This helper joins typed Store Lite service and Stripe adapter operations. It
 * does not read a request, emit a response, resolve a secret, contact a
 * provider, or navigate a browser. The accepted hosted URL is transient result
 * data for a later separately authorized public response bridge.
 */

require_once __DIR__ . '/addon_service_helpers.php';
require_once __DIR__ . '/addon_adapter_helpers.php';

if (!function_exists('red_addon_subscription_checkout_result')) {
    function red_addon_subscription_checkout_result($reason = 'invalid_request')
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => '',
            'subjectRecordId' => 0,
            'offerId' => '',
            'intentReference' => '',
            'contractSha256' => '',
            'requestSha256' => '',
            'responseEvidenceSha256' => '',
            'resultSha256' => '',
            'checkoutSessionRefSha256' => '',
            'checkoutUrl' => '',
            'expiresAtEpoch' => 0,
            'httpStatus' => 0,
            'cacheControl' => '',
            'navigationMode' => '',
            'transientOnly' => false,
            'responseEmission' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'subscriptionCreation' => false,
            'browserNavigation' => false,
            'reason' => (string) $reason,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_subscription_intent_reference')) {
    function red_addon_subscription_intent_reference(
        $subjectRecordId,
        $offerId,
        $intentStateSha256,
        $offerStateSha256
    ) {
        if (!is_int($subjectRecordId)
            || $subjectRecordId < 1
            || $subjectRecordId > 2147483647
            || !is_string($offerId)
            || preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/D', $offerId) !== 1
            || !red_addon_valid_sha256($intentStateSha256)
            || !red_addon_valid_sha256($offerStateSha256)
        ) {
            return '';
        }
        $encoded = json_encode([
            'schema' => 1,
            'purpose' => 'subscription-checkout-intent-reference',
            'subjectRecordId' => $subjectRecordId,
            'offerId' => $offerId,
            'intentStateSha256' => $intentStateSha256,
            'offerStateSha256' => $offerStateSha256,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded)
            ? 'sint_' . substr(hash('sha256', $encoded), 0, 32)
            : '';
    }
}

if (!function_exists('red_addon_subscription_checkout_binding_valid')) {
    function red_addon_subscription_checkout_binding_valid($binding)
    {
        return is_array($binding)
            && array_keys($binding) === [
                'storePackageId', 'storePackageVersion', 'storeService',
                'stripePackageId', 'stripePackageVersion', 'stripeAdapter',
            ]
            && ($binding['storePackageId'] ?? '') === 'redcms.store-lite'
            && ($binding['storePackageVersion'] ?? '') === '0.1.50'
            && ($binding['storeService'] ?? '') === 'commerce.subscriptions'
            && ($binding['stripePackageId'] ?? '')
                === 'redcms.store-lite-stripe-checkout'
            && ($binding['stripePackageVersion'] ?? '') === '0.1.11'
            && ($binding['stripeAdapter'] ?? '')
                === 'redcms.store-lite-stripe-checkout/checkout';
    }
}

if (!function_exists('red_addon_subscription_checkout_invocation_valid')) {
    function red_addon_subscription_checkout_invocation_valid(
        $result,
        $package,
        $operation
    ) {
        return is_array($result)
            && ($result['invoked'] ?? null) === true
            && ($result['success'] ?? null) === true
            && ($result['package'] ?? null) === $package
            && ($result['operation'] ?? null) === $operation
            && is_array($result['data'] ?? null)
            && ($result['error'] ?? null) === ''
            && ($result['reason'] ?? null) === 'completed';
    }
}

if (!function_exists('red_addon_subscription_checkout_url_valid')) {
    function red_addon_subscription_checkout_url_valid($url, $sessionRef)
    {
        if (!is_string($url)
            || !is_string($sessionRef)
            || preg_match(
                '/\Acs_test_[A-Za-z0-9_]{16,160}\z/D',
                $sessionRef
            ) !== 1
            || strlen($url) < 1
            || strlen($url) > 4096
            || preg_match('/[\x00-\x1F\x7F]/', $url) === 1
        ) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === 'checkout.stripe.com'
            && !array_key_exists('user', $parts)
            && !array_key_exists('pass', $parts)
            && !array_key_exists('port', $parts)
            && !array_key_exists('query', $parts)
            && ($parts['path'] ?? null) === '/c/pay/' . $sessionRef;
    }
}

if (!function_exists('red_addon_subscription_checkout_coordinate')) {
    function red_addon_subscription_checkout_coordinate(
        $binding,
        $subjectRecordId,
        $offerId,
        $policy,
        $syntheticResponse,
        $serviceInvoker,
        $adapterInvoker
    ) {
        $result = red_addon_subscription_checkout_result();
        if (!red_addon_subscription_checkout_binding_valid($binding)
            || !is_int($subjectRecordId)
            || $subjectRecordId < 1
            || !is_string($offerId)
            || preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/D', $offerId) !== 1
            || !is_array($policy)
            || !is_array($syntheticResponse)
            || array_keys($syntheticResponse) !== ['envelope', 'projection']
            || !is_array($syntheticResponse['envelope'])
            || !is_array($syntheticResponse['projection'])
            || !is_callable($serviceInvoker)
            || !is_callable($adapterInvoker)
        ) {
            return $result;
        }

        try {
            $loaded = $serviceInvoker(
                $binding['storeService'],
                'subscription.checkout.load',
                ['subjectRecordId' => $subjectRecordId, 'offerId' => $offerId]
            );
            if (!red_addon_subscription_checkout_invocation_valid(
                $loaded,
                $binding['storePackageId'],
                'subscription.checkout.load'
            )) {
                $result['reason'] = 'subscription_projection_unavailable';
                return $result;
            }
            $intent = $loaded['data']['intent'] ?? null;
            $offer = $loaded['data']['offer'] ?? null;
            if (!is_array($intent)
                || array_keys($intent) !== [
                    'subjectRecordId', 'offerId', 'intentStateSha256',
                    'offerStateSha256', 'status',
                ]
                || ($intent['subjectRecordId'] ?? null) !== $subjectRecordId
                || ($intent['offerId'] ?? null) !== $offerId
                || ($intent['status'] ?? null) !== 'requested'
                || !red_addon_valid_sha256($intent['intentStateSha256'] ?? '')
                || !red_addon_valid_sha256($intent['offerStateSha256'] ?? '')
                || !is_array($offer)
            ) {
                $result['reason'] = 'subscription_projection_invalid';
                return $result;
            }
            $intentReference = red_addon_subscription_intent_reference(
                $subjectRecordId,
                $offerId,
                $intent['intentStateSha256'],
                $intent['offerStateSha256']
            );
            if ($intentReference === '') {
                $result['reason'] = 'intent_reference_failed';
                return $result;
            }
            $adapterIntent = [
                'intentReference' => $intentReference,
                'intentStateSha256' => $intent['intentStateSha256'],
                'offerStateSha256' => $intent['offerStateSha256'],
                'status' => 'requested',
            ];
            $prepared = $adapterInvoker(
                $binding['stripeAdapter'],
                'subscription.checkout.prepare-sandbox-offline',
                [
                    'contactTarget' => 'stripe-subscription-sandbox-offline',
                    'intent' => $adapterIntent,
                    'offer' => $offer,
                    'policy' => $policy,
                ]
            );
            if (!red_addon_subscription_checkout_invocation_valid(
                $prepared,
                $binding['stripePackageId'],
                'subscription.checkout.prepare-sandbox-offline'
            )
                || ($prepared['data']['valid'] ?? null) !== true
                || ($prepared['data']['intentReference'] ?? '')
                    !== $intentReference
                || !red_addon_valid_sha256(
                    $prepared['data']['contractSha256'] ?? ''
                )
                || !red_addon_valid_sha256(
                    $prepared['data']['requestSha256'] ?? ''
                )
                || !empty($prepared['data']['networkAccess'])
                || !empty($prepared['data']['providerContact'])
                || !empty($prepared['data']['providerMutation'])
                || !empty($prepared['data']['checkoutCreation'])
                || !empty($prepared['data']['subscriptionCreation'])
                || !empty($prepared['data']['browserNavigation'])
            ) {
                $result['reason'] = 'stripe_preparation_refused';
                return $result;
            }
            $accepted = $adapterInvoker(
                $binding['stripeAdapter'],
                'subscription.checkout.accept-sandbox-synthetic',
                [
                    'contactTarget' =>
                        'stripe-subscription-sandbox-synthetic-response',
                    'intent' => $adapterIntent,
                    'offer' => $offer,
                    'policy' => $policy,
                    'envelope' => $syntheticResponse['envelope'],
                    'projection' => $syntheticResponse['projection'],
                ]
            );
            $data = $accepted['data'] ?? [];
            if (!red_addon_subscription_checkout_invocation_valid(
                $accepted,
                $binding['stripePackageId'],
                'subscription.checkout.accept-sandbox-synthetic'
            )
                || ($data['valid'] ?? null) !== true
                || ($data['intentReference'] ?? '') !== $intentReference
                || !red_addon_subscription_checkout_url_valid(
                    $data['checkoutUrl'] ?? null,
                    $data['checkoutSessionRef'] ?? null
                )
                || ($data['navigationMode'] ?? '') !== 'location.assign'
                || ($data['transientOnly'] ?? null) !== true
                || ($data['persistCheckoutUrl'] ?? null) !== false
                || ($data['cacheControl'] ?? '') !== 'no-store'
                || ($data['authorizationRequired'] ?? null) !== true
                || ($data['browserNavigationAuthorized'] ?? null) !== false
                || !red_addon_valid_sha256(
                    $data['responseEvidenceSha256'] ?? ''
                )
                || !red_addon_valid_sha256($data['resultSha256'] ?? '')
                || !is_int($data['expiresAtEpoch'] ?? null)
                || ($data['contractSha256'] ?? '')
                    !== $prepared['data']['contractSha256']
                || !empty($data['networkAccess'])
                || !empty($data['providerContact'])
                || !empty($data['providerMutation'])
                || !empty($data['checkoutCreation'])
                || !empty($data['subscriptionCreation'])
                || !empty($data['browserNavigation'])
            ) {
                $result['reason'] = 'stripe_synthetic_response_refused';
                return $result;
            }
            $sessionRefSha256 = hash(
                'sha256',
                $data['checkoutSessionRef']
            );
            $persisted = $serviceInvoker(
                $binding['storeService'],
                'subscription.checkout.prepare',
                [
                    'intent' => [
                        'subjectRecordId' => $subjectRecordId,
                        'offerId' => $offerId,
                        'intentReference' => $intentReference,
                        'intentStateSha256' => $intent['intentStateSha256'],
                        'offerStateSha256' => $intent['offerStateSha256'],
                    ],
                    'checkout' => [
                        'checkoutSessionRefSha256' => $sessionRefSha256,
                        'responseEvidenceSha256' =>
                            $data['responseEvidenceSha256'],
                        'expiresAtEpoch' => $data['expiresAtEpoch'],
                        'occurredAt' => $policy['createdAtEpoch'] ?? 0,
                    ],
                ]
            );
            if (!red_addon_subscription_checkout_invocation_valid(
                $persisted,
                $binding['storePackageId'],
                'subscription.checkout.prepare'
            )
                || !in_array(
                    $persisted['data']['status'] ?? '',
                    ['prepared', 'replayed'],
                    true
                )
                || ($persisted['data']['subscriptionStatus'] ?? '')
                    !== 'pending'
                || ($persisted['data']['entitlementStatus'] ?? '')
                    !== 'inactive'
                || ($persisted['data']['intentReference'] ?? '')
                    !== $intentReference
                || ($persisted['data']['offerStateSha256'] ?? '')
                    !== $intent['offerStateSha256']
                || ($persisted['data']['providerSubscriptionRefSha256']
                    ?? null) !== null
                || ($persisted['data']['currentPeriodEndEpoch'] ?? null)
                    !== null
                || ($persisted['data']['checkoutSessionRefSha256'] ?? '')
                    !== $sessionRefSha256
                || ($persisted['data']['lastEventEvidenceSha256'] ?? '')
                    !== $data['responseEvidenceSha256']
            ) {
                $result['reason'] = 'subscription_persistence_refused';
                return $result;
            }
            return [
                'valid' => true,
                'ready' => true,
                'status' => 'synthetic_redirect_ready',
                'subjectRecordId' => $subjectRecordId,
                'offerId' => $offerId,
                'intentReference' => $intentReference,
                'contractSha256' => $prepared['data']['contractSha256'],
                'requestSha256' => $prepared['data']['requestSha256'],
                'responseEvidenceSha256' =>
                    $data['responseEvidenceSha256'],
                'resultSha256' => $data['resultSha256'],
                'checkoutSessionRefSha256' => $sessionRefSha256,
                'checkoutUrl' => $data['checkoutUrl'],
                'expiresAtEpoch' => $data['expiresAtEpoch'],
                'httpStatus' => 303,
                'cacheControl' => 'no-store',
                'navigationMode' => 'location.assign',
                'transientOnly' => true,
                'responseEmission' => false,
                'networkAccess' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'checkoutCreation' => false,
                'subscriptionCreation' => false,
                'browserNavigation' => false,
                'reason' => 'synthetic_redirect_ready',
                'errors' => [],
            ];
        } catch (Throwable $throwable) {
            $result['reason'] = 'coordinator_failed';
            return $result;
        }
    }
}

if (!function_exists('red_addon_subscription_checkout_coordinate_current')) {
    function red_addon_subscription_checkout_coordinate_current(
        $subjectRecordId,
        $offerId,
        $policy,
        $syntheticResponse
    ) {
        $storeOwner = red_addon_runtime_owner(
            'services',
            'commerce.subscriptions'
        );
        $stripeAdapter = 'redcms.store-lite-stripe-checkout/checkout';
        $stripeOwner = red_addon_runtime_owner('adapters', $stripeAdapter);
        $storeManifest = is_string($storeOwner)
            ? red_addon_runtime_manifest($storeOwner) : null;
        $stripeManifest = is_string($stripeOwner)
            ? red_addon_runtime_manifest($stripeOwner) : null;
        $binding = [
            'storePackageId' => (string) $storeOwner,
            'storePackageVersion' => is_array($storeManifest)
                ? (string) ($storeManifest['version'] ?? '') : '',
            'storeService' => 'commerce.subscriptions',
            'stripePackageId' => (string) $stripeOwner,
            'stripePackageVersion' => is_array($stripeManifest)
                ? (string) ($stripeManifest['version'] ?? '') : '',
            'stripeAdapter' => $stripeAdapter,
        ];
        return red_addon_subscription_checkout_coordinate(
            $binding,
            $subjectRecordId,
            $offerId,
            $policy,
            $syntheticResponse,
            static fn($service, $operation, $input) =>
                red_addon_service_invoke($service, $operation, $input),
            static fn($adapter, $operation, $input) =>
                red_addon_adapter_invoke($adapter, $operation, $input)
        );
    }
}

?>
