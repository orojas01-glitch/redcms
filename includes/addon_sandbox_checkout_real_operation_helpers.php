<?php
/**
 * Core P3E-9D2 containment for adapter real-POST preflight adoption.
 *
 * This helper recognizes exact adapter 0.1.7, invokes only its non-executing
 * preflight operation, and derives deterministic start/result identities.
 * It has no credential, database, transport, provider, or durable-write path.
 */

require_once __DIR__
    . '/addon_sandbox_checkout_real_post_preflight_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_adapter_helpers.php';

if (!function_exists('red_addon_checkout_real_operation_result')) {
    function red_addon_checkout_real_operation_result($status = 'invalid')
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => (string) $status,
            'packageId' => '',
            'packageVersion' => '',
            'sourcePackageVersion' => '',
            'adapterId' => '',
            'operation' => '',
            'providerOperation' => '',
            'manifestSha256' => '',
            'inventorySha256' => '',
            'inputSha256' => '',
            'syntheticPlanSha256' => '',
            'contractSha256' => '',
            'requestSha256' => '',
            'planSha256' => '',
            'executionStartIdentitySha256' => '',
            'resultIdentitySha256' => '',
            'startIdentityPrepared' => false,
            'resultIdentityPrepared' => false,
            'adapterInvoked' => false,
            'boundedOutcome' => null,
            'restrictedTestWriteKeyRequired' => true,
            'credentialAccessProvided' => false,
            'credentialValueIncluded' => false,
            'authorizationHeaderIncluded' => false,
            'executionReady' => false,
            'executionStarted' => false,
            'resultRecorded' => false,
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
    }
}

if (!function_exists('red_addon_checkout_real_operation_typed_input')) {
    function red_addon_checkout_real_operation_typed_input(
        array $input,
        array $preflight
    ) {
        $projection = $preflight;
        unset($projection['formFields']);
        $typed = [
            'contactTarget' => 'stripe-sandbox-real-post-preflight',
            'checkout' => $input['checkout'] ?? null,
            'policy' => $input['policy'] ?? null,
            'profile' => $input['profile'] ?? null,
            'contractSha256' => $input['contractSha256'] ?? null,
            'realPostPreflight' => $projection,
        ];
        return red_addon_service_payload($typed) === $typed ? $typed : null;
    }
}

if (!function_exists('red_addon_checkout_real_operation_typed_request')) {
    function red_addon_checkout_real_operation_typed_request(array $preflight)
    {
        if (!is_array($preflight['formFields'] ?? null)) {
            return null;
        }
        $fields = [];
        foreach ($preflight['formFields'] as $name => $value) {
            if (!is_string($name)
                || (!is_string($value) && !is_int($value))
            ) {
                return null;
            }
            $fields[] = ['name' => $name, 'value' => $value];
        }
        $request = [
            'method' => $preflight['method'] ?? null,
            'host' => $preflight['host'] ?? null,
            'path' => $preflight['path'] ?? null,
            'apiVersion' => $preflight['apiVersion'] ?? null,
            'contentType' => $preflight['contentType'] ?? null,
            'idempotencyKey' => $preflight['idempotencyKey'] ?? null,
            'formFields' => $fields,
        ];
        return red_addon_service_payload($request) === $request
            ? $request
            : null;
    }
}

if (!function_exists('red_addon_checkout_real_operation_start_identity')) {
    function red_addon_checkout_real_operation_start_identity(array $plan)
    {
        $material = [
            'schema' => 1,
            'purpose' => 'sandbox-checkout-real-operation-start-identity',
            'packageId' => $plan['packageId'] ?? '',
            'packageVersion' => $plan['packageVersion'] ?? '',
            'sourcePackageVersion' => $plan['sourcePackageVersion'] ?? '',
            'adapterId' => $plan['adapterId'] ?? '',
            'operation' => $plan['operation'] ?? '',
            'providerOperation' => $plan['providerOperation'] ?? '',
            'manifestSha256' => $plan['manifestSha256'] ?? '',
            'inventorySha256' => $plan['inventorySha256'] ?? '',
            'inputSha256' => $plan['inputSha256'] ?? '',
            'syntheticPlanSha256' => $plan['syntheticPlanSha256'] ?? '',
            'contractSha256' => $plan['contractSha256'] ?? '',
            'requestSha256' => $plan['requestSha256'] ?? '',
            'planSha256' => $plan['planSha256'] ?? '',
            'maximumAttempts' => 1,
            'restrictedTestWriteKeyRequired' => true,
            'credentialAccessProvided' => false,
            'executionStarted' => false,
            'retryAuthorized' => false,
        ];
        foreach ([
            'manifestSha256', 'inventorySha256', 'inputSha256',
            'syntheticPlanSha256', 'contractSha256', 'requestSha256',
            'planSha256',
        ] as $key) {
            if (!red_addon_checkout_synthetic_sha256($material[$key])) {
                return '';
            }
        }
        return red_addon_checkout_synthetic_hash($material);
    }
}

if (!function_exists('red_addon_checkout_real_operation_plan')) {
    function red_addon_checkout_real_operation_plan(
        array $package,
        array $syntheticPlan,
        array $input,
        array $preflight
    ) {
        $result = red_addon_checkout_real_operation_result(
            'contract_refused'
        );
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        $adapterId = 'redcms.store-lite-stripe-checkout/checkout';
        $expectedPreflight = red_addon_checkout_real_post_preflight(
            $syntheticPlan,
            $input
        );
        $typedInput = red_addon_checkout_real_operation_typed_input(
            $input,
            $preflight
        );
        $typedRequest = red_addon_checkout_real_operation_typed_request(
            $preflight
        );
        if (!is_array($snapshot)
            || ($snapshot['id'] ?? null)
                !== 'redcms.store-lite-stripe-checkout'
            || ($snapshot['version'] ?? null) !== '0.1.7'
            || ($snapshot['type'] ?? null) !== 'adapter'
            || !is_array($manifest)
            || ($manifest['id'] ?? null) !== $snapshot['id']
            || ($manifest['version'] ?? null) !== $snapshot['version']
            || ($manifest['provides']['adapters'] ?? null) !== [$adapterId]
            || ($manifest['dependencies']['required'] ?? null) !== [[
                'id' => 'redcms.store-lite',
                'version' => '>=0.1.35 <1.0',
            ]]
            || !red_addon_checkout_mutation_synthetic_plan_valid(
                $syntheticPlan
            )
            || !red_addon_checkout_synthetic_input_valid($input)
            || empty($expectedPreflight['ready'])
            || $expectedPreflight !== $preflight
            || !is_array($typedInput)
            || !is_array($typedRequest)
        ) {
            $result['errors'] = ['contract_refused'];
            return $result;
        }

        $material = [
            'schema' => 1,
            'purpose' => 'sandbox-checkout-real-operation-preflight-runner',
            'packageId' => $snapshot['id'],
            'packageVersion' => $snapshot['version'],
            'sourcePackageVersion' => $preflight['packageVersion'],
            'adapterId' => $adapterId,
            'operation' => 'checkout.create-sandbox-real-post-preflight',
            'providerOperation' => 'checkout.create-sandbox-real-post',
            'manifestSha256' => $snapshot['manifestSha256'],
            'inventorySha256' => $snapshot['inventorySha256'],
            'inputSha256' => $preflight['inputSha256'],
            'syntheticPlanSha256' => $preflight['syntheticPlanSha256'],
            'contractSha256' => $input['contractSha256'],
            'requestSha256' => $preflight['requestSha256'],
            'restrictedTestWriteKeyRequired' => true,
            'credentialAccessProvided' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'executionPerformed' => false,
        ];
        $planSha256 = red_addon_checkout_synthetic_hash($material);
        if (!red_addon_checkout_synthetic_sha256($planSha256)) {
            $result['status'] = 'plan_encoding_failed';
            $result['errors'] = ['plan_encoding_failed'];
            return $result;
        }
        foreach ($material as $key => $value) {
            if (array_key_exists($key, $result)) {
                $result[$key] = $value;
            }
        }
        $result['planSha256'] = $planSha256;
        $result['executionStartIdentitySha256'] =
            red_addon_checkout_real_operation_start_identity($result);
        if (!red_addon_checkout_synthetic_sha256(
            $result['executionStartIdentitySha256']
        )) {
            $result['status'] = 'start_identity_encoding_failed';
            $result['errors'] = ['start_identity_encoding_failed'];
            return $result;
        }
        $result['startIdentityPrepared'] = true;
        $result['valid'] = true;
        $result['ready'] = true;
        $result['status'] = 'ready';
        $result['errors'] = [];
        return $result;
    }
}

if (!function_exists('red_addon_checkout_real_operation_outcome')) {
    function red_addon_checkout_real_operation_outcome(
        array $invocation,
        array $input,
        array $preflight
    ) {
        $typedRequest = red_addon_checkout_real_operation_typed_request(
            $preflight
        );
        if (!is_array($typedRequest)) {
            return null;
        }
        $expected = [
            'valid' => true,
            'adopted' => true,
            'status' => 'request_contract_adopted',
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'packageVersion' => '0.1.7',
            'sourcePackageVersion' => '0.1.5',
            'operation' => 'checkout.create-sandbox-real-post-preflight',
            'providerOperation' => 'checkout.create-sandbox-real-post',
            'request' => $typedRequest,
            'inputSha256' => $preflight['inputSha256'] ?? '',
            'syntheticPlanSha256' =>
                $preflight['syntheticPlanSha256'] ?? '',
            'contractSha256' => $input['contractSha256'] ?? '',
            'requestSha256' => $preflight['requestSha256'] ?? '',
            'restrictedTestWriteKeyRequired' => true,
            'credentialValueIncluded' => false,
            'authorizationHeaderIncluded' => false,
            'executionReady' => false,
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
        return !empty($invocation['invoked'])
            && !empty($invocation['success'])
            && ($invocation['reason'] ?? null) === 'completed'
            && ($invocation['data'] ?? null) === $expected
                ? $expected
                : null;
    }
}

if (!function_exists('red_addon_checkout_real_operation_execute')) {
    function red_addon_checkout_real_operation_execute(
        array $package,
        array $syntheticPlan,
        array $input,
        array $preflight,
        $expectedPlanSha256,
        $expectedStartIdentitySha256
    ) {
        $result = red_addon_checkout_real_operation_result(
            'execution_refused'
        );
        if (!red_addon_checkout_synthetic_sha256($expectedPlanSha256)
            || !red_addon_checkout_synthetic_sha256(
                $expectedStartIdentitySha256
            )
        ) {
            $result['errors'] = ['execution_refused'];
            return $result;
        }
        $plan = red_addon_checkout_real_operation_plan(
            $package,
            $syntheticPlan,
            $input,
            $preflight
        );
        if (empty($plan['ready'])
            || !hash_equals(
                $expectedPlanSha256,
                (string) ($plan['planSha256'] ?? '')
            )
            || !hash_equals(
                $expectedStartIdentitySha256,
                (string) ($plan['executionStartIdentitySha256'] ?? '')
            )
        ) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'execution_identity_changed';
            $plan['errors'] = ['execution_identity_changed'];
            return $plan;
        }
        $typedInput = red_addon_checkout_real_operation_typed_input(
            $input,
            $preflight
        );
        if (!is_array($typedInput)) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'execution_input_refused';
            $plan['errors'] = ['execution_input_refused'];
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
                $typedInput,
                $plan['packageId'],
                $handler,
                $package['manifest'],
                null
            );
        } catch (Throwable $throwable) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'package_execution_refused';
            $plan['errors'] = ['package_execution_refused'];
            return $plan;
        }
        unset($registry, $handler);

        $outcome = red_addon_checkout_real_operation_outcome(
            is_array($invocation) ? $invocation : [],
            $input,
            $preflight
        );
        if (!is_array($outcome)) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'package_outcome_refused';
            $plan['adapterInvoked'] = !empty($invocation['invoked']);
            $plan['errors'] = ['package_outcome_refused'];
            return $plan;
        }
        $resultIdentitySha256 = red_addon_checkout_synthetic_hash([
            'schema' => 1,
            'purpose' => 'sandbox-checkout-real-operation-result-identity',
            'planSha256' => $plan['planSha256'],
            'executionStartIdentitySha256' =>
                $plan['executionStartIdentitySha256'],
            'outcome' => $outcome,
            'resultRecorded' => false,
            'executionPerformed' => false,
        ]);
        if (!red_addon_checkout_synthetic_sha256($resultIdentitySha256)) {
            $plan['valid'] = false;
            $plan['ready'] = false;
            $plan['status'] = 'result_identity_encoding_failed';
            $plan['errors'] = ['result_identity_encoding_failed'];
            return $plan;
        }

        $plan['ready'] = false;
        $plan['status'] = 'request_contract_adopted';
        $plan['adapterInvoked'] = true;
        $plan['boundedOutcome'] = $outcome;
        $plan['resultIdentitySha256'] = $resultIdentitySha256;
        $plan['resultIdentityPrepared'] = true;
        $plan['valid'] = true;
        $plan['errors'] = [];
        return $plan;
    }
}

?>
