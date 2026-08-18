<?php
/**
 * Core-owned P3E-9B2 synthetic Checkout package integration.
 *
 * This boundary is dependency-free and non-persistent. It validates one exact
 * adapter 0.1.5 package plus P3E-9A input, registers the integrity-checked
 * handler, and invokes only checkout.create-sandbox-synthetic. It creates no
 * authorization, claim, execution ledger, database, network, or provider path.
 */

require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_adapter_helpers.php';

if (!function_exists('red_addon_checkout_synthetic_result')) {
    function red_addon_checkout_synthetic_result($status = 'invalid')
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => (string) $status,
            'packageId' => '',
            'packageVersion' => '',
            'adapterId' => '',
            'operation' => '',
            'manifestSha256' => '',
            'inventorySha256' => '',
            'inputSha256' => '',
            'planSha256' => '',
            'adapterInvoked' => false,
            'boundedOutcome' => null,
            'outcomeSha256' => '',
            'executionPerformed' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'clientDeployment' => false,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_checkout_synthetic_exact_keys')) {
    function red_addon_checkout_synthetic_exact_keys(
        array $value,
        array $expected
    ) {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }
}

if (!function_exists('red_addon_checkout_synthetic_sha256')) {
    function red_addon_checkout_synthetic_sha256($value)
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}

if (!function_exists('red_addon_checkout_synthetic_canonical')) {
    function red_addon_checkout_synthetic_canonical($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(
                'red_addon_checkout_synthetic_canonical',
                $value
            );
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = red_addon_checkout_synthetic_canonical($item);
        }
        return $value;
    }
}

if (!function_exists('red_addon_checkout_synthetic_hash')) {
    function red_addon_checkout_synthetic_hash(array $value)
    {
        try {
            $encoded = json_encode(
                red_addon_checkout_synthetic_canonical($value),
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

if (!function_exists('red_addon_checkout_synthetic_profile_valid')) {
    function red_addon_checkout_synthetic_profile_valid(array $profile)
    {
        return red_addon_checkout_synthetic_exact_keys($profile, [
            'packageId', 'contractVersion', 'operation', 'contactTarget',
            'credentialMode', 'providerContact', 'providerMutation',
            'checkoutCreation', 'payment', 'webhook', 'browserNavigation',
            'orderMutation', 'clientDeployment', 'oneAttempt',
            'automaticRetry',
        ])
            && ($profile['packageId'] ?? null)
                === 'redcms.store-lite-stripe-checkout'
            && ($profile['contractVersion'] ?? null) === 'p3e9a-v1'
            && ($profile['operation'] ?? null) === 'checkout.create-sandbox'
            && ($profile['contactTarget'] ?? null) === 'stripe-sandbox'
            && ($profile['credentialMode'] ?? null)
                === 'restricted_test_write'
            && ($profile['providerContact'] ?? null) === true
            && ($profile['providerMutation'] ?? null) === true
            && ($profile['checkoutCreation'] ?? null) === true
            && ($profile['payment'] ?? null) === false
            && ($profile['webhook'] ?? null) === false
            && ($profile['browserNavigation'] ?? null) === false
            && ($profile['orderMutation'] ?? null) === false
            && ($profile['clientDeployment'] ?? null) === false
            && ($profile['oneAttempt'] ?? null) === true
            && ($profile['automaticRetry'] ?? null) === false;
    }
}

if (!function_exists('red_addon_checkout_synthetic_checkout_valid')) {
    function red_addon_checkout_synthetic_checkout_valid(array $checkout)
    {
        if (!red_addon_checkout_synthetic_exact_keys($checkout, [
            'orderId', 'orderSnapshotSha256', 'paymentMethod', 'amountMinor',
            'currency', 'idempotencySha256', 'lineItems',
        ])
            || !is_string($checkout['orderId'] ?? null)
            || preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $checkout['orderId']
            ) !== 1
            || !red_addon_checkout_synthetic_sha256(
                $checkout['orderSnapshotSha256'] ?? null
            )
            || ($checkout['paymentMethod'] ?? null) !== 'stripe_checkout'
            || !is_int($checkout['amountMinor'] ?? null)
            || $checkout['amountMinor'] < 1
            || $checkout['amountMinor'] > 2400999997599
            || ($checkout['currency'] ?? null) !== 'USD'
            || !red_addon_checkout_synthetic_sha256(
                $checkout['idempotencySha256'] ?? null
            )
            || !is_array($checkout['lineItems'] ?? null)
            || !array_is_list($checkout['lineItems'])
            || count($checkout['lineItems']) < 1
            || count($checkout['lineItems']) > 24
        ) {
            return false;
        }
        $total = 0;
        foreach ($checkout['lineItems'] as $line) {
            if (!is_array($line)
                || !red_addon_checkout_synthetic_exact_keys($line, [
                    'name', 'quantity', 'unitAmountMinor', 'lineTotalMinor',
                ])
                || !is_string($line['name'] ?? null)
                || strlen($line['name']) < 1
                || strlen($line['name']) > 160
                || trim($line['name']) !== $line['name']
                || preg_match('//u', $line['name']) !== 1
                || preg_match('/[\x00-\x1F\x7F]/', $line['name']) === 1
                || !is_int($line['quantity'] ?? null)
                || $line['quantity'] < 1
                || $line['quantity'] > 100
                || !is_int($line['unitAmountMinor'] ?? null)
                || $line['unitAmountMinor'] < 0
                || !is_int($line['lineTotalMinor'] ?? null)
                || $line['lineTotalMinor']
                    !== $line['unitAmountMinor'] * $line['quantity']
            ) {
                return false;
            }
            $total += $line['lineTotalMinor'];
            if ($total > 2400999997599) {
                return false;
            }
        }
        return $total === $checkout['amountMinor'];
    }
}

if (!function_exists('red_addon_checkout_synthetic_return_url_valid')) {
    function red_addon_checkout_synthetic_return_url_valid($value)
    {
        if (!is_string($value)
            || strlen($value) < 1
            || strlen($value) > 2048
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }
        $url = parse_url($value);
        return is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && is_string($url['host'] ?? null)
            && preg_match(
                '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/D',
                $url['host']
            ) === 1
            && !array_key_exists('user', $url)
            && !array_key_exists('pass', $url)
            && !array_key_exists('port', $url)
            && !array_key_exists('query', $url)
            && !array_key_exists('fragment', $url)
            && is_string($url['path'] ?? null)
            && str_starts_with($url['path'], '/')
            && $url['path'] !== '/';
    }
}

if (!function_exists('red_addon_checkout_synthetic_policy_valid')) {
    function red_addon_checkout_synthetic_policy_valid(array $policy)
    {
        if (!red_addon_checkout_synthetic_exact_keys($policy, [
            'apiVersion', 'successUrl', 'cancelUrl', 'createdAtEpoch',
            'expiresAtEpoch',
        ])
            || !is_string($policy['apiVersion'] ?? null)
            || preg_match(
                '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\.[a-z][a-z0-9_]{1,31}\z/D',
                $policy['apiVersion']
            ) !== 1
            || !red_addon_checkout_synthetic_return_url_valid(
                $policy['successUrl'] ?? null
            )
            || !red_addon_checkout_synthetic_return_url_valid(
                $policy['cancelUrl'] ?? null
            )
            || !is_int($policy['createdAtEpoch'] ?? null)
            || $policy['createdAtEpoch'] < 1
            || !is_int($policy['expiresAtEpoch'] ?? null)
            || $policy['expiresAtEpoch'] <= $policy['createdAtEpoch']
        ) {
            return false;
        }
        $success = parse_url($policy['successUrl']);
        $cancel = parse_url($policy['cancelUrl']);
        $duration = $policy['expiresAtEpoch'] - $policy['createdAtEpoch'];
        return is_array($success)
            && is_array($cancel)
            && ($success['scheme'] ?? null) === ($cancel['scheme'] ?? null)
            && ($success['host'] ?? null) === ($cancel['host'] ?? null)
            && $duration >= 1800
            && $duration <= 86400;
    }
}

if (!function_exists('red_addon_checkout_synthetic_input_valid')) {
    function red_addon_checkout_synthetic_input_valid(array $input)
    {
        return red_addon_checkout_synthetic_exact_keys($input, [
            'checkout', 'contactTarget', 'contractSha256', 'policy', 'profile',
        ])
            && ($input['contactTarget'] ?? null)
                === 'synthetic-checkout-package'
            && is_array($input['checkout'] ?? null)
            && red_addon_checkout_synthetic_checkout_valid($input['checkout'])
            && is_array($input['policy'] ?? null)
            && red_addon_checkout_synthetic_policy_valid($input['policy'])
            && is_array($input['profile'] ?? null)
            && red_addon_checkout_synthetic_profile_valid($input['profile'])
            && red_addon_checkout_synthetic_sha256(
                $input['contractSha256'] ?? null
            );
    }
}

if (!function_exists('red_addon_checkout_synthetic_plan')) {
    function red_addon_checkout_synthetic_plan(
        array $package,
        array $input
    ) {
        $result = red_addon_checkout_synthetic_result('contract_refused');
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        $adapterId = 'redcms.store-lite-stripe-checkout/checkout';
        if (!is_array($snapshot)
            || ($snapshot['id'] ?? null)
                !== 'redcms.store-lite-stripe-checkout'
            || ($snapshot['version'] ?? null) !== '0.1.5'
            || ($snapshot['type'] ?? null) !== 'adapter'
            || !is_array($manifest)
            || ($manifest['id'] ?? null) !== $snapshot['id']
            || ($manifest['version'] ?? null) !== $snapshot['version']
            || ($manifest['provides']['adapters'] ?? null) !== [$adapterId]
            || ($manifest['dependencies']['required'] ?? null) !== [[
                'id' => 'redcms.store-lite',
                'version' => '>=0.1.35 <1.0',
            ]]
            || !red_addon_checkout_synthetic_input_valid($input)
        ) {
            $result['errors'] = ['contract_refused'];
            return $result;
        }

        $inputSha256 = red_addon_checkout_synthetic_hash($input);
        $material = [
            'schema' => 1,
            'purpose' => 'sandbox-checkout-synthetic-package-execution',
            'packageId' => $snapshot['id'],
            'packageVersion' => $snapshot['version'],
            'adapterId' => $adapterId,
            'operation' => 'checkout.create-sandbox-synthetic',
            'manifestSha256' => $snapshot['manifestSha256'],
            'inventorySha256' => $snapshot['inventorySha256'],
            'inputSha256' => $inputSha256,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'clientDeployment' => false,
            'executionPerformed' => false,
        ];
        $planSha256 = red_addon_checkout_synthetic_hash($material);
        if (!red_addon_checkout_synthetic_sha256($inputSha256)
            || !red_addon_checkout_synthetic_sha256($planSha256)
        ) {
            $result['status'] = 'plan_encoding_failed';
            $result['errors'] = ['plan_encoding_failed'];
            return $result;
        }
        $result['valid'] = true;
        $result['ready'] = true;
        $result['status'] = 'ready';
        $result['packageId'] = $snapshot['id'];
        $result['packageVersion'] = $snapshot['version'];
        $result['adapterId'] = $adapterId;
        $result['operation'] = 'checkout.create-sandbox-synthetic';
        $result['manifestSha256'] = $snapshot['manifestSha256'];
        $result['inventorySha256'] = $snapshot['inventorySha256'];
        $result['inputSha256'] = $inputSha256;
        $result['planSha256'] = $planSha256;
        return $result;
    }
}

if (!function_exists('red_addon_checkout_synthetic_outcome')) {
    function red_addon_checkout_synthetic_outcome(
        array $invocation,
        array $input
    ) {
        $data = $invocation['data'] ?? null;
        if (empty($invocation['invoked'])
            || empty($invocation['success'])
            || ($invocation['reason'] ?? null) !== 'completed'
            || !is_array($data)
            || !red_addon_checkout_synthetic_exact_keys($data, [
                'valid', 'contactTarget', 'outcome', 'checkoutSessionRef',
                'expiresAtEpoch', 'contractSha256',
                'responseEvidenceSha256', 'resultSha256',
                'responseBodyIncluded', 'responseHeadersIncluded',
                'checkoutUrlIncluded', 'credentialIncluded',
                'retryAuthorized', 'mutationAuthorized', 'networkAccess',
                'providerContact', 'providerMutation', 'checkoutCreation',
                'payment', 'webhook', 'browserNavigation', 'orderMutation',
                'clientDeployment', 'executionPerformed', 'errors',
            ])
            || ($data['valid'] ?? null) !== true
            || ($data['contactTarget'] ?? null)
                !== 'synthetic-checkout-package'
            || ($data['outcome'] ?? null)
                !== 'checkout_contract_accepted'
            || !is_string($data['checkoutSessionRef'] ?? null)
            || preg_match(
                '/\Acs_test_[A-Za-z0-9_]{16,160}\z/D',
                $data['checkoutSessionRef']
            ) !== 1
            || ($data['expiresAtEpoch'] ?? null)
                !== ($input['policy']['expiresAtEpoch'] ?? null)
            || !hash_equals(
                (string) ($input['contractSha256'] ?? ''),
                (string) ($data['contractSha256'] ?? '')
            )
            || !red_addon_checkout_synthetic_sha256(
                $data['responseEvidenceSha256'] ?? null
            )
            || !red_addon_checkout_synthetic_sha256(
                $data['resultSha256'] ?? null
            )
            || ($data['responseBodyIncluded'] ?? null) !== false
            || ($data['responseHeadersIncluded'] ?? null) !== false
            || ($data['checkoutUrlIncluded'] ?? null) !== false
            || ($data['credentialIncluded'] ?? null) !== false
            || ($data['retryAuthorized'] ?? null) !== false
            || ($data['mutationAuthorized'] ?? null) !== false
            || ($data['networkAccess'] ?? null) !== false
            || ($data['providerContact'] ?? null) !== false
            || ($data['providerMutation'] ?? null) !== false
            || ($data['checkoutCreation'] ?? null) !== false
            || ($data['payment'] ?? null) !== false
            || ($data['webhook'] ?? null) !== false
            || ($data['browserNavigation'] ?? null) !== false
            || ($data['orderMutation'] ?? null) !== false
            || ($data['clientDeployment'] ?? null) !== false
            || ($data['executionPerformed'] ?? null) !== true
            || ($data['errors'] ?? null) !== []
        ) {
            return null;
        }
        return $data;
    }
}

if (!function_exists('red_addon_checkout_synthetic_execute')) {
    function red_addon_checkout_synthetic_execute(
        array $package,
        array $input,
        $secretAccess,
        $expectedPlanSha256
    ) {
        $result = red_addon_checkout_synthetic_result('execution_refused');
        if (!($secretAccess instanceof RED_Addon_Runtime_Secret_Access)
            || $secretAccess->packageId()
                !== 'redcms.store-lite-stripe-checkout'
            || $secretAccess->settingCount() !== 1
            || !red_addon_checkout_synthetic_sha256($expectedPlanSha256)
        ) {
            $result['errors'] = ['execution_refused'];
            return $result;
        }
        $plan = red_addon_checkout_synthetic_plan($package, $input);
        if (empty($plan['ready'])
            || !hash_equals(
                $expectedPlanSha256,
                (string) ($plan['planSha256'] ?? '')
            )
        ) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'execution_changed';
            $plan['errors'] = ['execution_changed'];
            return $plan;
        }

        try {
            $registry = red_addon_runtime_register_package($package);
            $handler = $registry->handler('adapters', $plan['adapterId']);
            if (!is_callable($handler)) {
                throw new RuntimeException('registrar_invalid');
            }
            $invocation = red_addon_adapter_invoke_registered(
                $plan['adapterId'],
                $plan['operation'],
                $input,
                $plan['packageId'],
                $handler,
                $package['manifest'],
                $secretAccess
            );
        } catch (Throwable $throwable) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'package_execution_refused';
            $plan['errors'] = ['package_execution_refused'];
            return $plan;
        }
        unset($registry, $handler);

        $outcome = red_addon_checkout_synthetic_outcome(
            is_array($invocation) ? $invocation : [],
            $input
        );
        if (!is_array($outcome)) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'package_outcome_refused';
            $plan['adapterInvoked'] = !empty($invocation['invoked']);
            $plan['errors'] = ['package_outcome_refused'];
            return $plan;
        }
        $outcomeSha256 = red_addon_checkout_synthetic_hash([
            'planSha256' => $plan['planSha256'],
            'outcome' => $outcome,
        ]);
        if (!red_addon_checkout_synthetic_sha256($outcomeSha256)) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'outcome_encoding_failed';
            $plan['errors'] = ['outcome_encoding_failed'];
            return $plan;
        }

        $plan['ready'] = false;
        $plan['status'] = 'checkout_contract_accepted';
        $plan['adapterInvoked'] = true;
        $plan['boundedOutcome'] = $outcome;
        $plan['outcomeSha256'] = $outcomeSha256;
        $plan['executionPerformed'] = true;
        $plan['errors'] = [];
        return $plan;
    }
}

?>
