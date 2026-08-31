<?php
/**
 * Unlinked core coordinator for one real Stripe Sandbox subscription attempt.
 *
 * The caller must supply a durable journal implementation and separately
 * authorized runtime invokers. This helper reads no request/global/database or
 * secret state and contains no transport. A started journal entry permanently
 * spends the attempt unless the same call records its terminal result.
 */

require_once __DIR__ . '/addon_subscription_checkout_public_response_helpers.php';
require_once __DIR__ . '/addon_subscription_catalog_binding_helpers.php';

if (!function_exists('red_addon_subscription_provider_result')) {
    function red_addon_subscription_provider_result($reason = 'invalid')
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => '',
            'subjectRecordId' => 0,
            'offerId' => '',
            'intentReference' => '',
            'planSha256' => '',
            'claimStateSha256' => '',
            'executionStartStateSha256' => '',
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
            'journalStarted' => false,
            'journalCompleted' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'subscriptionCreation' => false,
            'browserNavigation' => false,
            'retryAuthorized' => false,
            'reason' => (string) $reason,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_subscription_provider_binding_valid')) {
    function red_addon_subscription_provider_binding_valid($binding)
    {
        return red_addon_subscription_checkout_binding_valid($binding)
            && in_array(
                $binding['stripePackageVersion'] ?? '',
                ['0.1.18', '0.1.19', '0.1.20'],
                true
            );
    }
}

if (!function_exists('red_addon_subscription_provider_authority_valid')) {
    function red_addon_subscription_provider_authority_valid($authority)
    {
        return is_array($authority)
            && array_keys($authority) === [
                'authorized', 'authorizationSha256',
                'secretAvailabilitySha256', 'issuedAtEpoch',
                'expiresAtEpoch', 'maximumAttempts', 'retryAuthorized',
            ]
            && ($authority['authorized'] ?? null) === true
            && red_addon_valid_sha256(
                $authority['authorizationSha256'] ?? null
            )
            && red_addon_valid_sha256(
                $authority['secretAvailabilitySha256'] ?? null
            )
            && is_int($authority['issuedAtEpoch'] ?? null)
            && is_int($authority['expiresAtEpoch'] ?? null)
            && $authority['issuedAtEpoch'] >= 1
            && $authority['expiresAtEpoch']
                > $authority['issuedAtEpoch']
            && $authority['expiresAtEpoch']
                <= $authority['issuedAtEpoch'] + 900
            && ($authority['maximumAttempts'] ?? null) === 1
            && ($authority['retryAuthorized'] ?? null) === false;
    }
}

if (!function_exists('red_addon_subscription_provider_hash')) {
    function red_addon_subscription_provider_hash(array $material)
    {
        try {
            $encoded = json_encode(
                $material,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return hash('sha256', $encoded);
    }
}

if (!function_exists('red_addon_subscription_provider_operation')) {
    function red_addon_subscription_provider_operation(
        $binding,
        $subjectRecordId,
        $offerId,
        $policy,
        $authority,
        $serviceInvoker,
        $adapterInvoker,
        $journal,
        $providerCatalog = null
    ) {
        $result = red_addon_subscription_provider_result();
        $stripeVersion = is_array($binding)
            ? ($binding['stripePackageVersion'] ?? '') : '';
        $catalog = $providerCatalog === null
            ? null
            : red_addon_subscription_catalog_binding_normalize(
                $providerCatalog
            );
        if (!red_addon_subscription_provider_binding_valid($binding)
            || !is_int($subjectRecordId)
            || $subjectRecordId < 1
            || !is_string($offerId)
            || preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/D', $offerId) !== 1
            || !is_array($policy)
            || !red_addon_subscription_provider_authority_valid($authority)
            || !is_callable($serviceInvoker)
            || !is_callable($adapterInvoker)
            || !is_callable($journal)
            || ($stripeVersion === '0.1.18' && $providerCatalog !== null)
            || (in_array($stripeVersion, ['0.1.19', '0.1.20'], true)
                && $providerCatalog !== null
                && !is_array($catalog))
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
                || !is_array($offer)
                || ($intent['subjectRecordId'] ?? null) !== $subjectRecordId
                || ($intent['offerId'] ?? null) !== $offerId
                || ($intent['status'] ?? null) !== 'requested'
                || !red_addon_valid_sha256(
                    $intent['intentStateSha256'] ?? null
                )
                || !red_addon_valid_sha256(
                    $intent['offerStateSha256'] ?? null
                )
            ) {
                $result['reason'] = 'subscription_projection_invalid';
                return $result;
            }
            if (is_array($catalog)
                && !red_addon_subscription_catalog_binding_matches_offer(
                    $catalog,
                    $offer
                )
            ) {
                $result['reason'] = 'provider_catalog_mismatch';
                return $result;
            }
            $intentReference = red_addon_subscription_intent_reference(
                $subjectRecordId,
                $offerId,
                $intent['intentStateSha256'],
                $intent['offerStateSha256']
            );
            $adapterIntent = [
                'intentReference' => $intentReference,
                'intentStateSha256' => $intent['intentStateSha256'],
                'offerStateSha256' => $intent['offerStateSha256'],
                'status' => 'requested',
            ];
            $planMaterial = [
                'schema' => 1,
                'purpose' => 'subscription-checkout-provider-operation',
                'binding' => $binding,
                'subjectRecordId' => $subjectRecordId,
                'offerId' => $offerId,
                'intent' => $adapterIntent,
                'offer' => $offer,
                'policy' => $policy,
                'authorizationSha256' =>
                    $authority['authorizationSha256'],
                'secretAvailabilitySha256' =>
                    $authority['secretAvailabilitySha256'],
                'maximumAttempts' => 1,
                'retryAuthorized' => false,
            ];
            if (is_array($catalog)) {
                $planMaterial['providerCatalog'] = $catalog;
            }
            $planSha256 = red_addon_subscription_provider_hash($planMaterial);
            if ($intentReference === ''
                || !red_addon_valid_sha256($planSha256)
            ) {
                $result['reason'] = 'provider_plan_failed';
                return $result;
            }
            $journalCurrent = $journal('inspect', [
                'subjectRecordId' => $subjectRecordId,
                'offerId' => $offerId,
                'intentReference' => $intentReference,
                'planSha256' => $planSha256,
            ]);
            if (!is_array($journalCurrent)
                || !in_array(
                    $journalCurrent['status'] ?? '',
                    ['absent', 'started', 'completed'],
                    true
                )
            ) {
                $result['reason'] = 'provider_journal_unavailable';
                return $result;
            }
            if (($journalCurrent['status'] ?? '') !== 'absent') {
                $result['status'] = 'attempt_spent';
                $result['reason'] = 'attempt_spent_no_retry';
                $result['journalStarted'] = true;
                $result['journalCompleted'] =
                    $journalCurrent['status'] === 'completed';
                return $result;
            }
            $startStateSha256 = red_addon_subscription_provider_hash([
                'schema' => 1,
                'purpose' => 'subscription-checkout-provider-start',
                'planSha256' => $planSha256,
                'claimStateSha256' => $authority['authorizationSha256'],
                'maximumAttempts' => 1,
                'retryAuthorized' => false,
            ]);
            $started = $journal('start', [
                'subjectRecordId' => $subjectRecordId,
                'offerId' => $offerId,
                'intentReference' => $intentReference,
                'planSha256' => $planSha256,
                'claimStateSha256' => $authority['authorizationSha256'],
                'executionStartStateSha256' => $startStateSha256,
            ]);
            if (!is_array($started)
                || ($started['status'] ?? '') !== 'started'
                || ($started['executionStartStateSha256'] ?? '')
                    !== $startStateSha256
            ) {
                $result['reason'] = 'provider_start_refused';
                return $result;
            }
            $result['journalStarted'] = true;
            $adapterInput = [
                'contactTarget' =>
                    'stripe-subscription-sandbox-real-post',
                'intent' => $adapterIntent,
                'offer' => $offer,
                'policy' => $policy,
                'execution' => [
                    'planSha256' => $planSha256,
                    'claimStateSha256' =>
                        $authority['authorizationSha256'],
                    'executionStartStateSha256' => $startStateSha256,
                ],
            ];
            if (is_array($catalog)) {
                $adapterInput['providerCatalog'] = $catalog;
            }
            $invocation = $adapterInvoker(
                $binding['stripeAdapter'],
                'subscription.checkout.create-sandbox-real-post',
                $adapterInput
            );
            $data = is_array($invocation) ? ($invocation['data'] ?? null) : null;
            if (!red_addon_subscription_checkout_invocation_valid(
                $invocation,
                $binding['stripePackageId'],
                'subscription.checkout.create-sandbox-real-post'
            )
                || !is_array($data)
                || ($data['status'] ?? '')
                    !== 'subscription_checkout_session_created'
                || ($data['intentReference'] ?? '') !== $intentReference
                || !red_addon_subscription_checkout_public_url_valid(
                    $data['checkoutUrl'] ?? null
                )
                || ($data['transientOnly'] ?? null) !== true
                || ($data['persistCheckoutUrl'] ?? null) !== false
                || ($data['browserNavigationAuthorized'] ?? null) !== false
                || !red_addon_valid_sha256(
                    $data['contractSha256'] ?? null
                )
                || !red_addon_valid_sha256(
                    $data['requestSha256'] ?? null
                )
                || !red_addon_valid_sha256(
                    $data['responseEvidenceSha256'] ?? null
                )
                || !red_addon_valid_sha256(
                    $data['resultSha256'] ?? null
                )
                || empty($data['networkAccess'])
                || empty($data['providerContact'])
                || empty($data['providerMutation'])
                || empty($data['checkoutCreation'])
                || empty($data['subscriptionCreation'])
                || !empty($data['payment'])
                || !empty($data['webhook'])
                || !empty($data['browserNavigation'])
                || !empty($data['retryAuthorized'])
            ) {
                $result['status'] = 'indeterminate';
                $result['reason'] = 'provider_outcome_indeterminate';
                $result['networkAccess'] = true;
                $result['providerContact'] = true;
                $result['providerMutation'] = true;
                $result['checkoutCreation'] = true;
                $result['subscriptionCreation'] = true;
                return $result;
            }
            $sessionSha256 = hash('sha256', $data['checkoutSessionRef']);
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
                        'checkoutSessionRefSha256' => $sessionSha256,
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
            ) {
                $result['status'] = 'indeterminate';
                $result['reason'] = 'store_persistence_indeterminate';
                return $result;
            }
            $completed = $journal('complete', [
                'subjectRecordId' => $subjectRecordId,
                'offerId' => $offerId,
                'intentReference' => $intentReference,
                'planSha256' => $planSha256,
                'executionStartStateSha256' => $startStateSha256,
                'resultSha256' => $data['resultSha256'],
                'checkoutSessionRefSha256' => $sessionSha256,
            ]);
            if (!is_array($completed)
                || ($completed['status'] ?? '') !== 'completed'
                || ($completed['resultSha256'] ?? '')
                    !== $data['resultSha256']
            ) {
                $result['status'] = 'indeterminate';
                $result['reason'] = 'provider_result_journal_failed';
                return $result;
            }
            return array_replace($result, [
                'valid' => true,
                'ready' => true,
                'status' => 'real_redirect_ready',
                'subjectRecordId' => $subjectRecordId,
                'offerId' => $offerId,
                'intentReference' => $intentReference,
                'planSha256' => $planSha256,
                'claimStateSha256' => $authority['authorizationSha256'],
                'executionStartStateSha256' => $startStateSha256,
                'contractSha256' => $data['contractSha256'],
                'requestSha256' => $data['requestSha256'],
                'responseEvidenceSha256' =>
                    $data['responseEvidenceSha256'],
                'resultSha256' => $data['resultSha256'],
                'checkoutSessionRefSha256' => $sessionSha256,
                'checkoutUrl' => $data['checkoutUrl'],
                'expiresAtEpoch' => $data['expiresAtEpoch'],
                'httpStatus' => 303,
                'cacheControl' => 'no-store',
                'navigationMode' => 'location.assign',
                'transientOnly' => true,
                'responseEmission' => false,
                'journalStarted' => true,
                'journalCompleted' => true,
                'networkAccess' => true,
                'providerContact' => true,
                'providerMutation' => true,
                'checkoutCreation' => true,
                'subscriptionCreation' => true,
                'browserNavigation' => false,
                'retryAuthorized' => false,
                'reason' => 'real_redirect_ready',
                'errors' => [],
            ]);
        } catch (Throwable $throwable) {
            $result['status'] = !empty($result['journalStarted'])
                ? 'indeterminate' : '';
            $result['reason'] = !empty($result['journalStarted'])
                ? 'provider_execution_indeterminate'
                : 'provider_operation_failed';
            return $result;
        }
    }
}

?>
