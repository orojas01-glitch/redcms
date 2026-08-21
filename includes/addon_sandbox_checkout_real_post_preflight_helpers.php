<?php
/** Pure P3E-9D0 request contract for one future real Stripe Sandbox POST. */

require_once __DIR__
    . '/addon_sandbox_checkout_mutation_authorization_helpers.php';

if (!function_exists('red_addon_checkout_real_post_preflight')) {
    function red_addon_checkout_real_post_preflight(
        array $syntheticPlan,
        array $input
    ) {
        $result = [
            'valid' => false,
            'ready' => false,
            'status' => 'invalid',
            'packageId' => '',
            'packageVersion' => '',
            'operation' => '',
            'method' => '',
            'host' => '',
            'path' => '',
            'apiVersion' => '',
            'contentType' => '',
            'idempotencyKey' => '',
            'inputSha256' => '',
            'syntheticPlanSha256' => '',
            'requestSha256' => '',
            'formFields' => null,
            'restrictedTestWriteKeyRequired' => true,
            'credentialValueIncluded' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'storeLiteMutation' => false,
            'retryAuthorized' => false,
            'liveMode' => false,
            'clientDeployment' => false,
            'executionPerformed' => false,
            'errors' => [],
        ];
        if (!red_addon_checkout_mutation_synthetic_plan_valid($syntheticPlan)
            || red_addon_checkout_synthetic_hash($input)
                !== ($syntheticPlan['inputSha256'] ?? '')
        ) {
            $result['errors'][] = 'synthetic_evidence_refused';
            return $result;
        }
        $checkout = $input['checkout'] ?? null;
        $policy = $input['policy'] ?? null;
        $profile = $input['profile'] ?? null;
        if (!is_array($checkout)
            || !is_array($policy)
            || !is_array($profile)
            || ($profile['operation'] ?? null) !== 'checkout.create-sandbox'
            || ($profile['credentialMode'] ?? null)
                !== 'restricted_test_write'
            || ($profile['providerMutation'] ?? null) !== true
            || ($profile['checkoutCreation'] ?? null) !== true
            || ($profile['payment'] ?? null) !== false
            || ($profile['webhook'] ?? null) !== false
            || ($profile['automaticRetry'] ?? null) !== false
        ) {
            $result['errors'][] = 'mutation_profile_refused';
            return $result;
        }
        $fields = [
            'mode' => 'payment',
            'success_url' => $policy['successUrl'],
            'cancel_url' => $policy['cancelUrl'],
            'expires_at' => $policy['expiresAtEpoch'],
            'client_reference_id' => $checkout['orderId'],
            'metadata[order_snapshot_sha256]' =>
                $checkout['orderSnapshotSha256'],
            'metadata[input_sha256]' => $syntheticPlan['inputSha256'],
        ];
        foreach ($checkout['lineItems'] as $index => $line) {
            $prefix = 'line_items[' . $index . ']';
            $fields[$prefix . '[price_data][currency]'] = 'usd';
            $fields[$prefix . '[price_data][product_data][name]'] =
                $line['name'];
            $fields[$prefix . '[price_data][unit_amount]'] =
                $line['unitAmountMinor'];
            $fields[$prefix . '[quantity]'] = $line['quantity'];
        }
        $request = [
            'method' => 'POST',
            'host' => 'api.stripe.com',
            'path' => '/v1/checkout/sessions',
            'apiVersion' => $policy['apiVersion'],
            'contentType' => 'application/x-www-form-urlencoded',
            'idempotencyKey' =>
                'redcms-checkout-' . $checkout['idempotencySha256'],
            'formFields' => $fields,
        ];
        $encoded = red_addon_provider_contact_encode($request);
        if (!is_string($encoded) || strlen($request['idempotencyKey']) > 255) {
            $result['errors'][] = 'request_encoding_failed';
            return $result;
        }
        $result['packageId'] = $syntheticPlan['packageId'];
        $result['packageVersion'] = $syntheticPlan['packageVersion'];
        $result['operation'] = 'checkout.create-sandbox-real-post';
        $result['method'] = $request['method'];
        $result['host'] = $request['host'];
        $result['path'] = $request['path'];
        $result['apiVersion'] = $request['apiVersion'];
        $result['contentType'] = $request['contentType'];
        $result['idempotencyKey'] = $request['idempotencyKey'];
        $result['inputSha256'] = $syntheticPlan['inputSha256'];
        $result['syntheticPlanSha256'] = $syntheticPlan['planSha256'];
        $result['requestSha256'] = hash('sha256', $encoded);
        $result['formFields'] = $fields;
        $result['status'] = 'ready';
        $result['ready'] = true;
        $result['valid'] = true;
        return $result;
    }
}

?>
