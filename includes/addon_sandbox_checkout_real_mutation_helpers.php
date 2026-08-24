<?php
/**
 * P3E-9D4B durable core boundary for one Sandbox Checkout real-POST attempt.
 *
 * D4B creates fresh operation/version-bound authority and claim evidence,
 * commits start before package or secret access, and records only a bounded
 * result after one exact invocation. It contains no transport primitive and
 * has no public or browser caller.
 */

require_once __DIR__ . '/addon_sandbox_checkout_real_operation_helpers.php';
require_once __DIR__ . '/addon_runtime_secret_helpers.php';
require_once __DIR__ . '/addon_package_permission_helpers.php';

if (!function_exists('red_addon_checkout_real_mutation_result')) {
    function red_addon_checkout_real_mutation_result($status = 'invalid')
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => (string) $status,
            'stage' => '',
            'packageId' => '',
            'packageVersion' => '',
            'storePackageId' => '',
            'storePackageVersion' => '',
            'adapterId' => '',
            'operation' => '',
            'providerOperation' => '',
            'actorAdminRecordId' => 0,
            'ownerSubjectSha256' => '',
            'databaseSha256' => '',
            'orderSnapshotSha256' => '',
            'inputSha256' => '',
            'syntheticPlanSha256' => '',
            'contractSha256' => '',
            'requestSha256' => '',
            'preflightPlanSha256' => '',
            'preflightStartIdentitySha256' => '',
            'preflightResultIdentitySha256' => '',
            'secretAvailabilitySha256' => '',
            'authorizationSha256' => '',
            'authorizationStateSha256' => '',
            'claimStateSha256' => '',
            'executionStartStateSha256' => '',
            'outcomeStateSha256' => '',
            'authorizationNonceSha256' => '',
            'authorizationActionId' => '',
            'claimActionId' => '',
            'executionStartActionId' => '',
            'outcomeActionId' => '',
            'issuedAtUtc' => '',
            'expiresAtUtc' => '',
            'maximumAttempts' => 0,
            'ownerAuthorityRevalidated' => false,
            'orderAuthorityRevalidated' => false,
            'authorizationRecorded' => false,
            'claimRecorded' => false,
            'executionStartAvailable' => false,
            'executionStarted' => false,
            'startAuditRecorded' => false,
            'registrarValidated' => false,
            'secretResolution' => false,
            'adapterInvoked' => false,
            'boundedOutcome' => null,
            'outcomeRecorded' => false,
            'outcomeAuditRecorded' => false,
            'executionPerformed' => false,
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
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_checkout_real_mutation_preflight_outcome')) {
    function red_addon_checkout_real_mutation_preflight_outcome(
        array $outcome,
        array $input,
        array $preflight
    ) {
        $typedRequest = red_addon_checkout_real_operation_typed_request(
            $preflight
        );
        if (!is_array($typedRequest)) {
            return false;
        }
        $expected = [
            'valid' => true,
            'adopted' => true,
            'status' => 'request_contract_adopted',
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'packageVersion' => '0.1.8',
            'sourcePackageVersion' => '0.1.7',
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
        return $outcome === $expected;
    }
}

if (!function_exists('red_addon_checkout_real_mutation_preflight_evidence')) {
    function red_addon_checkout_real_mutation_preflight_evidence(
        array $package,
        array $syntheticPlan,
        array $input,
        array $preflight,
        array $preflightOutcome
    ) {
        $invalid = [
            'valid' => false,
            'packageId' => '',
            'packageVersion' => '',
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
            'startIdentitySha256' => '',
            'resultIdentitySha256' => '',
            'errors' => ['preflight_evidence_refused'],
        ];
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        $adapterId = 'redcms.store-lite-stripe-checkout/checkout';
        $expectedPreflight = red_addon_checkout_real_post_preflight(
            $syntheticPlan,
            $input
        );
        if (!is_array($snapshot)
            || ($snapshot['id'] ?? null)
                !== 'redcms.store-lite-stripe-checkout'
            || ($snapshot['version'] ?? null) !== '0.1.8'
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
            || !red_addon_checkout_real_mutation_preflight_outcome(
                $preflightOutcome,
                $input,
                $preflight
            )
        ) {
            return $invalid;
        }
        $material = [
            'schema' => 1,
            'purpose' => 'sandbox-checkout-real-operation-preflight-runner',
            'packageId' => $snapshot['id'],
            'packageVersion' => $snapshot['version'],
            'sourcePackageVersion' => '0.1.7',
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
        $identityPlan = $material;
        $identityPlan['planSha256'] = $planSha256;
        $startIdentity = red_addon_checkout_real_operation_start_identity(
            $identityPlan
        );
        $resultIdentity = red_addon_checkout_synthetic_hash([
            'schema' => 1,
            'purpose' => 'sandbox-checkout-real-operation-result-identity',
            'planSha256' => $planSha256,
            'executionStartIdentitySha256' => $startIdentity,
            'outcome' => $preflightOutcome,
            'resultRecorded' => false,
            'executionPerformed' => false,
        ]);
        if (!red_addon_checkout_synthetic_sha256($planSha256)
            || !red_addon_checkout_synthetic_sha256($startIdentity)
            || !red_addon_checkout_synthetic_sha256($resultIdentity)
        ) {
            $invalid['errors'] = ['preflight_identity_encoding_failed'];
            return $invalid;
        }
        return [
            'valid' => true,
            'packageId' => $snapshot['id'],
            'packageVersion' => $snapshot['version'],
            'adapterId' => $adapterId,
            'operation' => $material['operation'],
            'providerOperation' => $material['providerOperation'],
            'manifestSha256' => $snapshot['manifestSha256'],
            'inventorySha256' => $snapshot['inventorySha256'],
            'inputSha256' => $material['inputSha256'],
            'syntheticPlanSha256' => $material['syntheticPlanSha256'],
            'contractSha256' => $material['contractSha256'],
            'requestSha256' => $material['requestSha256'],
            'planSha256' => $planSha256,
            'startIdentitySha256' => $startIdentity,
            'resultIdentitySha256' => $resultIdentity,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_checkout_real_mutation_prepare')) {
    function red_addon_checkout_real_mutation_prepare(
        array $preflightEvidence,
        $operatorSubjectSha256,
        $databaseSha256,
        $orderSnapshotSha256,
        $secretAvailabilitySha256,
        $authorizationNonceSha256,
        $issuedAtUtc,
        $expiresAtUtc
    ) {
        $invalid = [
            'prepared' => false,
            'authorization' => null,
            'authorizationSha256' => '',
            'errors' => ['authorization_evidence_refused'],
        ];
        foreach ([$operatorSubjectSha256, $databaseSha256,
            $orderSnapshotSha256, $secretAvailabilitySha256,
            $authorizationNonceSha256] as $sha256
        ) {
            if (!red_addon_provider_contact_sha256($sha256)) {
                return $invalid;
            }
        }
        if (($preflightEvidence['valid'] ?? null) !== true
            || !red_addon_provider_contact_sha256(
                $preflightEvidence['planSha256'] ?? null
            )
            || !red_addon_provider_contact_sha256(
                $preflightEvidence['startIdentitySha256'] ?? null
            )
            || !red_addon_provider_contact_sha256(
                $preflightEvidence['resultIdentitySha256'] ?? null
            )
        ) {
            return $invalid;
        }
        $issued = red_addon_provider_contact_utc($issuedAtUtc);
        $expires = red_addon_provider_contact_utc($expiresAtUtc);
        if (!($issued instanceof DateTimeImmutable)
            || !($expires instanceof DateTimeImmutable)
            || $expires->getTimestamp() <= $issued->getTimestamp()
            || $expires->getTimestamp() - $issued->getTimestamp() > 900
        ) {
            return $invalid;
        }
        $authorization = [
            'action' => 'authorize-stripe-sandbox-real-post',
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'packageVersion' => '0.1.8',
            'storePackageId' => 'redcms.store-lite',
            'storePackageVersion' => '0.1.35',
            'adapterId' => 'redcms.store-lite-stripe-checkout/checkout',
            'operation' => 'checkout.create-sandbox-real-post',
            'contactTarget' => 'stripe-sandbox-real-post',
            'preflightPlanSha256' => $preflightEvidence['planSha256'],
            'preflightStartIdentitySha256' =>
                $preflightEvidence['startIdentitySha256'],
            'preflightResultIdentitySha256' =>
                $preflightEvidence['resultIdentitySha256'],
            'inputSha256' => $preflightEvidence['inputSha256'],
            'syntheticPlanSha256' =>
                $preflightEvidence['syntheticPlanSha256'],
            'contractSha256' => $preflightEvidence['contractSha256'],
            'requestSha256' => $preflightEvidence['requestSha256'],
            'operatorSubjectSha256' => $operatorSubjectSha256,
            'databaseSha256' => $databaseSha256,
            'orderSnapshotSha256' => $orderSnapshotSha256,
            'secretAvailabilitySha256' => $secretAvailabilitySha256,
            'authorizationNonceSha256' => $authorizationNonceSha256,
            'issuedAtUtc' => $issuedAtUtc,
            'expiresAtUtc' => $expiresAtUtc,
            'maximumAttempts' => 1,
            'requiredLifecycleCapability' => 'addons.enable',
            'requiredOrderCapability' => 'store.orders.manage',
            'restrictedTestWriteKeyRequired' => true,
            'providerMutationAuthorized' => true,
            'checkoutCreationAuthorized' => true,
            'paymentAuthorized' => false,
            'webhookAuthorized' => false,
            'browserNavigationAuthorized' => false,
            'storeLiteMutationAuthorized' => false,
            'retryAuthorized' => false,
            'liveModeAuthorized' => false,
            'clientDeploymentAuthorized' => false,
            'credentialValueIncluded' => false,
            'executionStarted' => false,
            'executionPerformed' => false,
        ];
        $encoded = red_addon_provider_contact_encode($authorization);
        if (!is_string($encoded)) {
            return $invalid;
        }
        return [
            'prepared' => true,
            'authorization' => $authorization,
            'authorizationSha256' => hash('sha256', $encoded),
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_checkout_real_mutation_database_sha256')) {
    function red_addon_checkout_real_mutation_database_sha256($connection)
    {
        if (!($connection instanceof mysqli)) {
            return '';
        }
        try {
            $query = mysqli_query($connection, 'SELECT DATABASE() AS Name');
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            $name = is_array($row) ? ($row['Name'] ?? null) : null;
            return is_string($name) && $name !== ''
                ? hash('sha256', $name)
                : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_checkout_real_mutation_secret_evidence')) {
    /** Return only opaque-reference availability evidence; resolve no value. */
    function red_addon_checkout_real_mutation_secret_evidence(
        $connection,
        array $package,
        $declarations = null
    ) {
        $result = [
            'valid' => false,
            'available' => false,
            'settingKey' => 'stripe.secret-key',
            'declarationSha256' => '',
            'evidenceSha256' => '',
            'errors' => [],
        ];
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        if (!($connection instanceof mysqli)
            || !is_array($snapshot)
            || !is_array($manifest)
            || ($snapshot['id'] ?? null)
                !== 'redcms.store-lite-stripe-checkout'
            || ($snapshot['version'] ?? null) !== '0.1.8'
        ) {
            $result['errors'][] = 'package_invalid';
            return $result;
        }
        $definition = null;
        foreach (red_addon_settings_schema($manifest) ?? [] as $candidate) {
            if (($candidate['key'] ?? null) === 'stripe.secret-key') {
                if ($definition !== null) {
                    $result['errors'][] = 'setting_schema_invalid';
                    return $result;
                }
                $definition = $candidate;
            }
        }
        if (!is_array($definition)
            || ($definition['type'] ?? null) !== 'secret-reference'
            || ($definition['secret'] ?? null) !== true
        ) {
            $result['errors'][] = 'setting_schema_invalid';
            return $result;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=? AND SettingKey=?'
            );
            if (!$statement) {
                $result['errors'][] = 'setting_storage_unavailable';
                return $result;
            }
            $packageId = $snapshot['id'];
            $settingKey = 'stripe.secret-key';
            mysqli_stmt_bind_param(
                $statement,
                'ss',
                $packageId,
                $settingKey
            );
            $executed = mysqli_stmt_execute($statement);
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $row = $query ? mysqli_fetch_assoc($query) : null;
            $rows = $query ? mysqli_num_rows($query) : 0;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $result['errors'][] = 'setting_storage_unavailable';
            return $result;
        }
        $reference = is_array($row) ? ($row['SecretReference'] ?? null) : null;
        if (!$executed
            || $rows !== 1
            || ($row['ValueType'] ?? null) !== 'secret-reference'
            || ($row['ValueJSON'] ?? null) !== null
            || !red_addon_setting_string_is_valid(
                'secret-reference',
                $reference
            )
        ) {
            $result['errors'][] = 'setting_configuration_invalid';
            return $result;
        }
        if ($declarations === null) {
            $declarations = red_addon_secret_reference_declarations();
        }
        if (!is_array($declarations)
            || empty($declarations['valid'])
            || !is_array($declarations['references'] ?? null)
            || !red_addon_provider_contact_sha256(
                $declarations['declarationSha256'] ?? null
            )
        ) {
            $result['errors'][] = 'secret_declaration_invalid';
            return $result;
        }
        $canonical = red_addon_secret_reference_declarations(
            $declarations['references'],
            ''
        );
        if (empty($canonical['valid'])
            || !hash_equals(
                $canonical['declarationSha256'],
                $declarations['declarationSha256']
            )
            || !in_array($reference, $canonical['references'], true)
        ) {
            $result['errors'][] = 'secret_unavailable';
            return $result;
        }
        $encoded = red_addon_provider_contact_encode([
            'schema' => 1,
            'packageId' => $snapshot['id'],
            'packageVersion' => $snapshot['version'],
            'settingKey' => 'stripe.secret-key',
            'referenceSha256' => hash('sha256', $reference),
            'declarationSha256' => $canonical['declarationSha256'],
            'available' => true,
            'valueIncluded' => false,
            'valueSha256Included' => false,
        ]);
        if (!is_string($encoded)) {
            $result['errors'][] = 'secret_evidence_encoding_failed';
            return $result;
        }
        $result['valid'] = true;
        $result['available'] = true;
        $result['declarationSha256'] = $canonical['declarationSha256'];
        $result['evidenceSha256'] = hash('sha256', $encoded);
        return $result;
    }
}

if (!function_exists('red_addon_checkout_real_mutation_prepared_valid')) {
    function red_addon_checkout_real_mutation_prepared_valid(
        array $prepared,
        array $evidence,
        $ownerSubjectSha256,
        $databaseSha256,
        $orderSnapshotSha256,
        $secretAvailabilitySha256,
        $evaluatedAtUtc
    ) {
        if (!red_addon_checkout_synthetic_exact_keys($prepared, [
            'prepared', 'authorization', 'authorizationSha256', 'errors',
        ])
            || ($prepared['prepared'] ?? null) !== true
            || !is_array($prepared['authorization'] ?? null)
            || !red_addon_provider_contact_sha256(
                $prepared['authorizationSha256'] ?? null
            )
            || ($prepared['errors'] ?? null) !== []
        ) {
            return false;
        }
        $authorization = $prepared['authorization'];
        $expectedKeys = [
            'action', 'packageId', 'packageVersion', 'storePackageId',
            'storePackageVersion', 'adapterId', 'operation', 'contactTarget',
            'preflightPlanSha256', 'preflightStartIdentitySha256',
            'preflightResultIdentitySha256', 'inputSha256',
            'syntheticPlanSha256', 'contractSha256', 'requestSha256',
            'operatorSubjectSha256', 'databaseSha256', 'orderSnapshotSha256',
            'secretAvailabilitySha256', 'authorizationNonceSha256',
            'issuedAtUtc', 'expiresAtUtc', 'maximumAttempts',
            'requiredLifecycleCapability', 'requiredOrderCapability',
            'restrictedTestWriteKeyRequired', 'providerMutationAuthorized',
            'checkoutCreationAuthorized', 'paymentAuthorized',
            'webhookAuthorized', 'browserNavigationAuthorized',
            'storeLiteMutationAuthorized', 'retryAuthorized',
            'liveModeAuthorized', 'clientDeploymentAuthorized',
            'credentialValueIncluded', 'executionStarted',
            'executionPerformed',
        ];
        if (!red_addon_checkout_synthetic_exact_keys(
            $authorization,
            $expectedKeys
        )) {
            return false;
        }
        $encoded = red_addon_provider_contact_encode($authorization);
        $issued = red_addon_provider_contact_utc(
            $authorization['issuedAtUtc'] ?? null
        );
        $expires = red_addon_provider_contact_utc(
            $authorization['expiresAtUtc'] ?? null
        );
        $evaluated = red_addon_provider_contact_utc($evaluatedAtUtc);
        return is_string($encoded)
            && hash_equals(
                $prepared['authorizationSha256'],
                hash('sha256', $encoded)
            )
            && ($evidence['valid'] ?? null) === true
            && ($authorization['action'] ?? null)
                === 'authorize-stripe-sandbox-real-post'
            && ($authorization['packageId'] ?? null)
                === 'redcms.store-lite-stripe-checkout'
            && ($authorization['packageVersion'] ?? null) === '0.1.8'
            && ($authorization['storePackageId'] ?? null)
                === 'redcms.store-lite'
            && ($authorization['storePackageVersion'] ?? null) === '0.1.35'
            && ($authorization['adapterId'] ?? null)
                === 'redcms.store-lite-stripe-checkout/checkout'
            && ($authorization['operation'] ?? null)
                === 'checkout.create-sandbox-real-post'
            && ($authorization['contactTarget'] ?? null)
                === 'stripe-sandbox-real-post'
            && hash_equals(
                $evidence['planSha256'],
                $authorization['preflightPlanSha256']
            )
            && hash_equals(
                $evidence['startIdentitySha256'],
                $authorization['preflightStartIdentitySha256']
            )
            && hash_equals(
                $evidence['resultIdentitySha256'],
                $authorization['preflightResultIdentitySha256']
            )
            && hash_equals(
                $evidence['inputSha256'],
                $authorization['inputSha256']
            )
            && hash_equals(
                $evidence['syntheticPlanSha256'],
                $authorization['syntheticPlanSha256']
            )
            && hash_equals(
                $evidence['contractSha256'],
                $authorization['contractSha256']
            )
            && hash_equals(
                $evidence['requestSha256'],
                $authorization['requestSha256']
            )
            && hash_equals(
                $ownerSubjectSha256,
                $authorization['operatorSubjectSha256']
            )
            && hash_equals($databaseSha256, $authorization['databaseSha256'])
            && hash_equals(
                $orderSnapshotSha256,
                $authorization['orderSnapshotSha256']
            )
            && hash_equals(
                $secretAvailabilitySha256,
                $authorization['secretAvailabilitySha256']
            )
            && red_addon_provider_contact_sha256(
                $authorization['authorizationNonceSha256'] ?? null
            )
            && $issued instanceof DateTimeImmutable
            && $expires instanceof DateTimeImmutable
            && $evaluated instanceof DateTimeImmutable
            && $expires->getTimestamp() - $issued->getTimestamp() <= 900
            && $evaluated->getTimestamp() >= $issued->getTimestamp()
            && $evaluated->getTimestamp() < $expires->getTimestamp()
            && ($authorization['maximumAttempts'] ?? null) === 1
            && ($authorization['requiredLifecycleCapability'] ?? null)
                === 'addons.enable'
            && ($authorization['requiredOrderCapability'] ?? null)
                === 'store.orders.manage'
            && ($authorization['restrictedTestWriteKeyRequired'] ?? null)
                === true
            && ($authorization['providerMutationAuthorized'] ?? null) === true
            && ($authorization['checkoutCreationAuthorized'] ?? null) === true
            && ($authorization['paymentAuthorized'] ?? null) === false
            && ($authorization['webhookAuthorized'] ?? null) === false
            && ($authorization['browserNavigationAuthorized'] ?? null) === false
            && ($authorization['storeLiteMutationAuthorized'] ?? null) === false
            && ($authorization['retryAuthorized'] ?? null) === false
            && ($authorization['liveModeAuthorized'] ?? null) === false
            && ($authorization['clientDeploymentAuthorized'] ?? null) === false
            && ($authorization['credentialValueIncluded'] ?? null) === false
            && ($authorization['executionStarted'] ?? null) === false
            && ($authorization['executionPerformed'] ?? null) === false;
    }
}

if (!function_exists('red_addon_checkout_real_mutation_action_id')) {
    function red_addon_checkout_real_mutation_action_id($stage, $nonceSha256)
    {
        $prefixes = [
            'authorization' => 'sandbox-checkout-real-post-authorization.',
            'claim' => 'sandbox-checkout-real-post-claim.',
            'execution' => 'sandbox-checkout-real-post-start.',
            'result' => 'sandbox-checkout-real-post-result.',
        ];
        return is_string($stage)
            && isset($prefixes[$stage])
            && red_addon_provider_contact_sha256($nonceSha256)
                ? $prefixes[$stage] . $nonceSha256
                : '';
    }
}

if (!function_exists('red_addon_checkout_real_mutation_row')) {
    function red_addon_checkout_real_mutation_row(
        $connection,
        $packageId,
        $actionId,
        $lock = false
    ) {
        if (!($connection instanceof mysqli)
            || !is_string($packageId)
            || $packageId === ''
            || !is_string($actionId)
            || $actionId === ''
            || !is_bool($lock)
        ) {
            return ['valid' => false, 'found' => false, 'row' => null];
        }
        try {
            $sql = 'SELECT PlanSHA256, ContractSHA256, PreviousStateSHA256,
                           StateSHA256, ActorAdminRecordID
                    FROM RED_Addon_Admin_Action_Executions
                    WHERE PackageID=? AND ActionID=? AND TargetRecordID=1';
            if ($lock) {
                $sql .= ' FOR UPDATE';
            }
            $statement = mysqli_prepare($connection, $sql);
            if (!$statement) {
                return ['valid' => false, 'found' => false, 'row' => null];
            }
            mysqli_stmt_bind_param($statement, 'ss', $packageId, $actionId);
            $executed = mysqli_stmt_execute($statement);
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $row = $query ? mysqli_fetch_assoc($query) : null;
            $rows = $query ? mysqli_num_rows($query) : 0;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            if (!$executed || $rows > 1) {
                return ['valid' => false, 'found' => false, 'row' => null];
            }
            return [
                'valid' => true,
                'found' => $rows === 1,
                'row' => $rows === 1 ? $row : null,
            ];
        } catch (Throwable $throwable) {
            return ['valid' => false, 'found' => false, 'row' => null];
        }
    }
}

if (!function_exists('red_addon_checkout_real_mutation_state_sha256')) {
    function red_addon_checkout_real_mutation_state_sha256(
        $stage,
        array $plan
    ) {
        $previous = match ($stage) {
            'authorization' => $plan['ownerSubjectSha256'] ?? '',
            'claim' => $plan['authorizationStateSha256'] ?? '',
            'execution' => $plan['claimStateSha256'] ?? '',
            default => '',
        };
        if (!in_array($stage, ['authorization', 'claim', 'execution'], true)
            || !red_addon_provider_contact_sha256($previous)
        ) {
            return '';
        }
        $material = [
            'schema' => 1,
            'purpose' => 'sandbox-checkout-real-post-' . $stage,
            'packageId' => $plan['packageId'] ?? '',
            'packageVersion' => $plan['packageVersion'] ?? '',
            'storePackageId' => $plan['storePackageId'] ?? '',
            'storePackageVersion' => $plan['storePackageVersion'] ?? '',
            'adapterId' => $plan['adapterId'] ?? '',
            'operation' => $plan['operation'] ?? '',
            'actorAdminRecordId' => (int) ($plan['actorAdminRecordId'] ?? 0),
            'ownerSubjectSha256' => $plan['ownerSubjectSha256'] ?? '',
            'databaseSha256' => $plan['databaseSha256'] ?? '',
            'orderSnapshotSha256' => $plan['orderSnapshotSha256'] ?? '',
            'inputSha256' => $plan['inputSha256'] ?? '',
            'syntheticPlanSha256' => $plan['syntheticPlanSha256'] ?? '',
            'contractSha256' => $plan['contractSha256'] ?? '',
            'requestSha256' => $plan['requestSha256'] ?? '',
            'preflightPlanSha256' => $plan['preflightPlanSha256'] ?? '',
            'preflightStartIdentitySha256' =>
                $plan['preflightStartIdentitySha256'] ?? '',
            'preflightResultIdentitySha256' =>
                $plan['preflightResultIdentitySha256'] ?? '',
            'secretAvailabilitySha256' =>
                $plan['secretAvailabilitySha256'] ?? '',
            'authorizationSha256' => $plan['authorizationSha256'] ?? '',
            'authorizationNonceSha256' =>
                $plan['authorizationNonceSha256'] ?? '',
            'previousStateSha256' => $previous,
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
            'executionStarted' => $stage === 'execution',
            'executionPerformed' => false,
        ];
        foreach ([
            'ownerSubjectSha256', 'databaseSha256', 'orderSnapshotSha256',
            'inputSha256', 'syntheticPlanSha256', 'contractSha256',
            'requestSha256', 'preflightPlanSha256',
            'preflightStartIdentitySha256', 'preflightResultIdentitySha256',
            'secretAvailabilitySha256', 'authorizationSha256',
            'authorizationNonceSha256', 'previousStateSha256',
        ] as $key) {
            if (!red_addon_provider_contact_sha256($material[$key])) {
                return '';
            }
        }
        if ($material['packageId'] !== 'redcms.store-lite-stripe-checkout'
            || $material['packageVersion'] !== '0.1.8'
            || $material['storePackageId'] !== 'redcms.store-lite'
            || $material['storePackageVersion'] !== '0.1.35'
            || $material['adapterId']
                !== 'redcms.store-lite-stripe-checkout/checkout'
            || $material['operation']
                !== 'checkout.create-sandbox-real-post'
            || $material['actorAdminRecordId'] <= 0
        ) {
            return '';
        }
        $encoded = red_addon_provider_contact_encode($material);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_checkout_real_mutation_row_matches')) {
    function red_addon_checkout_real_mutation_row_matches(
        array $row,
        array $plan,
        $previousStateSha256,
        $stateSha256
    ) {
        return red_addon_checkout_synthetic_exact_keys($row, [
            'PlanSHA256', 'ContractSHA256', 'PreviousStateSHA256',
            'StateSHA256', 'ActorAdminRecordID',
        ])
            && hash_equals(
                (string) ($plan['preflightPlanSha256'] ?? ''),
                (string) ($row['PlanSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($plan['authorizationSha256'] ?? ''),
                (string) ($row['ContractSHA256'] ?? '')
            )
            && hash_equals(
                (string) $previousStateSha256,
                (string) ($row['PreviousStateSHA256'] ?? '')
            )
            && hash_equals(
                (string) $stateSha256,
                (string) ($row['StateSHA256'] ?? '')
            )
            && (int) ($row['ActorAdminRecordID'] ?? 0)
                === (int) ($plan['actorAdminRecordId'] ?? 0);
    }
}

if (!function_exists('red_addon_checkout_real_mutation_reserve')) {
    function red_addon_checkout_real_mutation_reserve(
        $connection,
        array $plan,
        $actionId,
        $previousStateSha256,
        $stateSha256
    ) {
        if (!is_string($actionId)
            || $actionId === ''
            || !red_addon_provider_contact_sha256($previousStateSha256)
            || !red_addon_provider_contact_sha256($stateSha256)
        ) {
            return 'failed';
        }
        $targetRecordId = 1;
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Addon_Admin_Action_Executions (
                    PackageID, ActionID, TargetRecordID, PlanSHA256,
                    ContractSHA256, PreviousStateSHA256, StateSHA256,
                    ActorAdminRecordID
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$statement) {
                return 'failed';
            }
            mysqli_stmt_bind_param(
                $statement,
                'ssissssi',
                $plan['packageId'],
                $actionId,
                $targetRecordId,
                $plan['preflightPlanSha256'],
                $plan['authorizationSha256'],
                $previousStateSha256,
                $stateSha256,
                $plan['actorAdminRecordId']
            );
            $inserted = mysqli_stmt_execute($statement);
            $errno = mysqli_stmt_errno($statement);
            mysqli_stmt_close($statement);
            if ($inserted) {
                return 'reserved';
            }
            return $errno === 1062 ? 'duplicate' : 'failed';
        } catch (mysqli_sql_exception $exception) {
            return (int) $exception->getCode() === 1062
                ? 'duplicate'
                : 'failed';
        } catch (Throwable $throwable) {
            return 'failed';
        }
    }
}

if (!function_exists('red_addon_checkout_real_mutation_plan')) {
    function red_addon_checkout_real_mutation_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $syntheticPlan,
        array $input,
        array $preflight,
        array $preflightOutcome,
        array $prepared,
        $evaluatedAtUtc,
        $stage,
        $lockRows = false,
        $declarations = null
    ) {
        $result = red_addon_checkout_real_mutation_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        $result['stage'] = is_string($stage) ? $stage : '';
        if (!($connection instanceof mysqli)
            || !in_array($stage, ['authorization', 'claim', 'execution'], true)
            || !is_bool($lockRows)
            || $actorAdminRecordId <= 0
        ) {
            $result['errors'][] = 'lifecycle_evidence_refused';
            return $result;
        }
        $evidence = red_addon_checkout_real_mutation_preflight_evidence(
            $package,
            $syntheticPlan,
            $input,
            $preflight,
            $preflightOutcome
        );
        $ownerSubjectSha256 =
            red_addon_provider_contact_owner_subject_sha256(
                $connection,
                $actorAdminRecordId
            );
        $databaseSha256 =
            red_addon_checkout_real_mutation_database_sha256($connection);
        $orderSnapshotSha256 =
            $input['checkout']['orderSnapshotSha256'] ?? '';
        $secretEvidence = red_addon_checkout_real_mutation_secret_evidence(
            $connection,
            $package,
            $declarations
        );
        $secretAvailabilitySha256 =
            $secretEvidence['evidenceSha256'] ?? '';
        if (empty($evidence['valid'])
            || !red_addon_provider_contact_sha256($ownerSubjectSha256)
            || !red_addon_provider_contact_sha256($databaseSha256)
            || !red_addon_provider_contact_sha256($orderSnapshotSha256)
            || empty($secretEvidence['valid'])
            || empty($secretEvidence['available'])
            || !red_addon_provider_contact_sha256(
                $secretAvailabilitySha256
            )
            || !red_addon_checkout_real_mutation_prepared_valid(
                $prepared,
                $evidence,
                $ownerSubjectSha256,
                $databaseSha256,
                $orderSnapshotSha256,
                $secretAvailabilitySha256,
                $evaluatedAtUtc
            )
            || !red_admin_transaction_tables_supported($connection, [
                'RED_Addon_Admin_Action_Executions',
                'RED_Addon_Activity_Log',
            ])
        ) {
            $result['errors'][] = 'lifecycle_evidence_refused';
            return $result;
        }
        $actor = red_admin_addon_database_actor(
            $connection,
            $actorAdminRecordId
        );
        if (!red_admin_addon_actor_can($actor, 'addons.enable')) {
            $result['errors'][] = 'owner_authority_refused';
            return $result;
        }
        $result['ownerAuthorityRevalidated'] = true;
        if (!red_addon_package_permission_has_exact_grant(
            $connection,
            $actorAdminRecordId,
            'store.orders.manage'
        )) {
            $result['errors'][] = 'order_authority_refused';
            return $result;
        }
        $result['orderAuthorityRevalidated'] = true;

        $snapshot = red_addon_registry_snapshot($package);
        $catalogPackage = $catalog['packages'][
            'redcms.store-lite-stripe-checkout'
        ] ?? null;
        $catalogSnapshot = is_array($catalogPackage)
            ? red_addon_registry_snapshot($catalogPackage)
            : null;
        $report = red_addon_registry_package_report($connection, $package);
        $storePackage = $catalog['packages']['redcms.store-lite'] ?? null;
        $storeSnapshot = is_array($storePackage)
            ? red_addon_registry_snapshot($storePackage)
            : null;
        $storeReport = is_array($storePackage)
            ? red_addon_registry_package_report($connection, $storePackage)
            : null;
        if (empty($catalog['valid'])
            || !is_array($snapshot)
            || !is_array($catalogSnapshot)
            || $catalogSnapshot !== $snapshot
            || ($snapshot['id'] ?? null)
                !== 'redcms.store-lite-stripe-checkout'
            || ($snapshot['version'] ?? null) !== '0.1.8'
            || ($snapshot['type'] ?? null) !== 'adapter'
            || ($report['status'] ?? null) !== 'enabled_current'
            || empty($report['loadable'])
            || ($report['errors'] ?? null) !== []
            || !is_array($storeSnapshot)
            || ($storeSnapshot['id'] ?? null) !== 'redcms.store-lite'
            || ($storeSnapshot['version'] ?? null) !== '0.1.35'
            || !red_addon_package_permission_declared(
                $storePackage,
                'store.orders.manage'
            )
            || !is_array($storeReport)
            || ($storeReport['status'] ?? null) !== 'enabled_current'
            || empty($storeReport['loadable'])
            || ($storeReport['errors'] ?? null) !== []
        ) {
            $result['errors'][] = 'package_state_refused';
            return $result;
        }

        $authorization = $prepared['authorization'];
        $result['packageId'] = $snapshot['id'];
        $result['packageVersion'] = $snapshot['version'];
        $result['storePackageId'] = $storeSnapshot['id'];
        $result['storePackageVersion'] = $storeSnapshot['version'];
        $result['adapterId'] = $evidence['adapterId'];
        $result['operation'] = 'checkout.create-sandbox-real-post';
        $result['providerOperation'] = $evidence['providerOperation'];
        $result['ownerSubjectSha256'] = $ownerSubjectSha256;
        $result['databaseSha256'] = $databaseSha256;
        $result['orderSnapshotSha256'] = $orderSnapshotSha256;
        $result['inputSha256'] = $evidence['inputSha256'];
        $result['syntheticPlanSha256'] =
            $evidence['syntheticPlanSha256'];
        $result['contractSha256'] = $evidence['contractSha256'];
        $result['requestSha256'] = $evidence['requestSha256'];
        $result['preflightPlanSha256'] = $evidence['planSha256'];
        $result['preflightStartIdentitySha256'] =
            $evidence['startIdentitySha256'];
        $result['preflightResultIdentitySha256'] =
            $evidence['resultIdentitySha256'];
        $result['secretAvailabilitySha256'] =
            $secretAvailabilitySha256;
        $result['authorizationSha256'] =
            $prepared['authorizationSha256'];
        $result['authorizationNonceSha256'] =
            $authorization['authorizationNonceSha256'];
        $result['issuedAtUtc'] = $authorization['issuedAtUtc'];
        $result['expiresAtUtc'] = $authorization['expiresAtUtc'];
        $result['maximumAttempts'] = 1;
        foreach (['authorization', 'claim', 'execution', 'result'] as $name) {
            $key = match ($name) {
                'authorization' => 'authorizationActionId',
                'claim' => 'claimActionId',
                'execution' => 'executionStartActionId',
                default => 'outcomeActionId',
            };
            $result[$key] = red_addon_checkout_real_mutation_action_id(
                $name,
                $result['authorizationNonceSha256']
            );
        }
        $result['authorizationStateSha256'] =
            red_addon_checkout_real_mutation_state_sha256(
                'authorization',
                $result
            );
        $result['claimStateSha256'] =
            red_addon_checkout_real_mutation_state_sha256('claim', $result);
        $result['executionStartStateSha256'] =
            red_addon_checkout_real_mutation_state_sha256(
                'execution',
                $result
            );
        foreach (['authorizationStateSha256', 'claimStateSha256',
            'executionStartStateSha256'] as $key
        ) {
            if (!red_addon_provider_contact_sha256($result[$key])) {
                $result['errors'][] = 'state_identity_refused';
                return $result;
            }
        }

        $rows = [];
        foreach ([
            'authorization' => 'authorizationActionId',
            'claim' => 'claimActionId',
            'execution' => 'executionStartActionId',
            'result' => 'outcomeActionId',
        ] as $name => $key) {
            $rows[$name] = red_addon_checkout_real_mutation_row(
                $connection,
                $result['packageId'],
                $result[$key],
                $lockRows
            );
            if (empty($rows[$name]['valid'])) {
                $result['status'] = 'lifecycle_state_refused';
                $result['errors'][] = 'lifecycle_state_refused';
                return $result;
            }
        }
        $authorizationMatches = !empty($rows['authorization']['found'])
            && is_array($rows['authorization']['row'])
            && red_addon_checkout_real_mutation_row_matches(
                $rows['authorization']['row'],
                $result,
                $result['ownerSubjectSha256'],
                $result['authorizationStateSha256']
            );
        $claimMatches = !empty($rows['claim']['found'])
            && is_array($rows['claim']['row'])
            && red_addon_checkout_real_mutation_row_matches(
                $rows['claim']['row'],
                $result,
                $result['authorizationStateSha256'],
                $result['claimStateSha256']
            );
        if ($stage === 'authorization') {
            if (!empty($rows['authorization']['found'])
                || !empty($rows['claim']['found'])
                || !empty($rows['execution']['found'])
                || !empty($rows['result']['found'])
            ) {
                $result['status'] = 'authorization_already_recorded';
                $result['errors'][] = 'authorization_already_recorded';
                return $result;
            }
        } elseif ($stage === 'claim') {
            if (!$authorizationMatches) {
                $result['status'] = 'authorization_record_refused';
                $result['errors'][] = 'authorization_record_refused';
                return $result;
            }
            $result['authorizationRecorded'] = true;
            if (!empty($rows['claim']['found'])
                || !empty($rows['execution']['found'])
                || !empty($rows['result']['found'])
            ) {
                $result['status'] = 'attempt_already_claimed';
                $result['errors'][] = 'attempt_already_claimed';
                return $result;
            }
        } else {
            if (!$authorizationMatches || !$claimMatches) {
                $result['status'] = !$authorizationMatches
                    ? 'authorization_record_refused'
                    : 'claim_record_refused';
                $result['errors'][] = $result['status'];
                return $result;
            }
            $result['authorizationRecorded'] = true;
            $result['claimRecorded'] = true;
            if (!empty($rows['execution']['found'])
                || !empty($rows['result']['found'])
            ) {
                $result['status'] = 'execution_already_started';
                $result['errors'][] = 'execution_already_started';
                return $result;
            }
            $result['executionStartAvailable'] = true;
        }
        $result['valid'] = true;
        $result['ready'] = true;
        $result['status'] = 'ready';
        return $result;
    }
}

if (!function_exists('red_addon_checkout_real_mutation_audit')) {
    function red_addon_checkout_real_mutation_audit(
        $connection,
        array $plan,
        $stage
    ) {
        $details = [
            'authorization' => 'sandbox_checkout_real_post_authorized',
            'claim' => 'sandbox_checkout_real_post_attempt_claimed',
            'execution' => 'sandbox_checkout_real_post_execution_started',
        ];
        return isset($details[$stage])
            && red_addon_install_audit_record(
                $connection,
                'addon.action.completed',
                $plan['packageId'],
                $plan['packageVersion'],
                $plan['actorAdminRecordId'],
                'succeeded',
                $details[$stage]
            );
    }
}

if (!function_exists('red_addon_checkout_real_mutation_record_stage')) {
    function red_addon_checkout_real_mutation_record_stage(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $syntheticPlan,
        array $input,
        array $preflight,
        array $preflightOutcome,
        array $prepared,
        $stage,
        $expectedAuthorizationSha256,
        $expectedAuthorizationStateSha256,
        $expectedClaimStateSha256 = '',
        $evaluatedAtUtc = null,
        $auditRecorder = null,
        $declarations = null
    ) {
        $result = red_addon_checkout_real_mutation_result();
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || (int) $actorAdminRecordId <= 0
            || !in_array($stage, ['authorization', 'claim'], true)
            || !red_addon_provider_contact_sha256(
                $expectedAuthorizationSha256
            )
            || !red_addon_provider_contact_sha256(
                $expectedAuthorizationStateSha256
            )
            || ($stage === 'claim'
                && !red_addon_provider_contact_sha256(
                    $expectedClaimStateSha256
                ))
            || red_addon_provider_contact_transaction_active($connection)
        ) {
            return $result;
        }
        $evaluatedAtUtc = $evaluatedAtUtc === null
            ? gmdate('Y-m-d\TH:i:s\Z')
            : $evaluatedAtUtc;
        $auditRecorder = $auditRecorder
            ?? static function ($db, array $plan) use ($stage) {
                return red_addon_checkout_real_mutation_audit(
                    $db,
                    $plan,
                    $stage
                );
            };
        if (!is_callable($auditRecorder)
            || !red_addon_lifecycle_lock($connection)
        ) {
            $result['status'] = 'lifecycle_locked';
            return $result;
        }
        $packageId = 'redcms.store-lite-stripe-checkout';
        $packageLocked = false;
        try {
            if (!red_addon_install_lock($connection, $packageId)) {
                $result['status'] = 'package_locked';
                return $result;
            }
            $packageLocked = true;
            $catalog = red_addon_discover($projectRoot, [
                'cmsVersion' => '5.1.0',
                'phpVersion' => PHP_VERSION,
            ]);
            $package = $catalog['packages'][$packageId] ?? null;
            if (empty($catalog['valid']) || !is_array($package)) {
                $result['status'] = 'package_invalid';
                return $result;
            }
            if (!mysqli_begin_transaction($connection)) {
                $result['status'] = 'transaction_failed';
                return $result;
            }
            try {
                if (!red_addon_checkout_mutation_lock_state(
                    $connection,
                    (int) $actorAdminRecordId
                )) {
                    throw new RuntimeException('lifecycle_lock_failed');
                }
                $plan = red_addon_checkout_real_mutation_plan(
                    $connection,
                    $package,
                    $catalog,
                    (int) $actorAdminRecordId,
                    $syntheticPlan,
                    $input,
                    $preflight,
                    $preflightOutcome,
                    $prepared,
                    $evaluatedAtUtc,
                    $stage,
                    true,
                    $declarations
                );
                if (empty($plan['ready'])
                    || !hash_equals(
                        $expectedAuthorizationSha256,
                        $plan['authorizationSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedAuthorizationStateSha256,
                        $plan['authorizationStateSha256'] ?? ''
                    )
                    || ($stage === 'claim' && !hash_equals(
                        $expectedClaimStateSha256,
                        $plan['claimStateSha256'] ?? ''
                    ))
                ) {
                    throw new RuntimeException(
                        ($plan['status'] ?? '') === 'attempt_already_claimed'
                            ? 'attempt_already_claimed'
                            : 'lifecycle_changed'
                    );
                }
                $actionId = $stage === 'authorization'
                    ? $plan['authorizationActionId']
                    : $plan['claimActionId'];
                $previous = $stage === 'authorization'
                    ? $plan['ownerSubjectSha256']
                    : $plan['authorizationStateSha256'];
                $state = $stage === 'authorization'
                    ? $plan['authorizationStateSha256']
                    : $plan['claimStateSha256'];
                $reserved = red_addon_checkout_real_mutation_reserve(
                    $connection,
                    $plan,
                    $actionId,
                    $previous,
                    $state
                );
                if ($reserved !== 'reserved') {
                    throw new RuntimeException(
                        $reserved === 'duplicate'
                            ? ($stage === 'authorization'
                                ? 'authorization_already_recorded'
                                : 'attempt_already_claimed')
                            : 'reservation_failed'
                    );
                }
                if (!$auditRecorder($connection, $plan)) {
                    throw new RuntimeException('audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('commit_failed');
                }
                $result = $plan;
                $result['ready'] = false;
                $result['status'] = $stage === 'authorization'
                    ? 'authorized'
                    : 'claimed';
                $result['authorizationRecorded'] = true;
                $result['claimRecorded'] = $stage === 'claim';
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result = isset($plan) && is_array($plan) ? $plan : $result;
                $result['ready'] = false;
                $result['status'] = $throwable->getMessage();
                $result['authorizationRecorded'] = false;
                $result['claimRecorded'] = false;
                return $result;
            }
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock($connection, $packageId);
            }
            red_addon_lifecycle_unlock($connection);
        }
    }
}

if (!function_exists('red_addon_checkout_real_mutation_typed_input')) {
    function red_addon_checkout_real_mutation_typed_input(
        array $input,
        array $preflight,
        array $plan
    ) {
        $projection = $preflight;
        unset($projection['formFields']);
        $typed = [
            'contactTarget' => 'stripe-sandbox-real-post',
            'checkout' => $input['checkout'] ?? null,
            'policy' => $input['policy'] ?? null,
            'profile' => $input['profile'] ?? null,
            'contractSha256' => $input['contractSha256'] ?? null,
            'realPostPreflight' => $projection,
            'execution' => [
                'planSha256' => $plan['preflightPlanSha256'] ?? null,
                'claimStateSha256' => $plan['claimStateSha256'] ?? null,
                'executionStartStateSha256' =>
                    $plan['executionStartStateSha256'] ?? null,
            ],
        ];
        return red_addon_service_payload($typed) === $typed ? $typed : null;
    }
}

if (!function_exists('red_addon_checkout_real_mutation_bounded_outcome')) {
    function red_addon_checkout_real_mutation_bounded_outcome(
        array $invocation,
        array $plan,
        array $input,
        array $preflight
    ) {
        $invoked = !empty($invocation['invoked']);
        $execution = [
            'planSha256' => $plan['preflightPlanSha256'] ?? '',
            'claimStateSha256' => $plan['claimStateSha256'] ?? '',
            'executionStartStateSha256' =>
                $plan['executionStartStateSha256'] ?? '',
        ];
        $failureStage = !$invoked
            ? 'core_invocation_failed'
            : 'adapter_invocation_failed';
        $data = $invocation['data'] ?? null;
        $allowedFailureStages = [
            'none', 'preflight_refused', 'transport_exchange_failed',
            'exchange_invariant_failed', 'response_decode_failed',
            'response_acceptance_failed',
        ];
        if (is_array($data)
            && in_array(
                $data['failureStage'] ?? null,
                $allowedFailureStages,
                true
            )
        ) {
            $failureStage = $data['failureStage'];
        }
        $indeterminate = [
            'valid' => true,
            'status' => 'indeterminate',
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'packageVersion' => '0.1.8',
            'sourcePackageVersion' => '0.1.7',
            'operation' => 'checkout.create-sandbox-real-post',
            'providerOperation' => 'checkout.create-sandbox-real-post',
            'execution' => $execution,
            'inputSha256' => $preflight['inputSha256'] ?? '',
            'syntheticPlanSha256' =>
                $preflight['syntheticPlanSha256'] ?? '',
            'contractSha256' => $input['contractSha256'] ?? '',
            'requestSha256' => $preflight['requestSha256'] ?? '',
            'checkout' => null,
            'responseEvidenceSha256' => '',
            'resultSha256' => '',
            'restrictedTestWriteKeyRequired' => true,
            'credentialValueIncluded' => false,
            'authorizationHeaderIncluded' => false,
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'checkoutUrlIncluded' => false,
            'networkAccess' => $invoked,
            'providerContact' => $invoked,
            'providerMutation' => $invoked,
            'checkoutCreation' => $invoked,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'storeLiteMutation' => false,
            'retryAuthorized' => false,
            'liveMode' => false,
            'clientDeployment' => false,
            'executionPerformed' => $invoked,
            'failureStage' => $failureStage,
            'errors' => ['provider_execution_indeterminate'],
        ];
        if (!$invoked
            || empty($invocation['success'])
            || ($invocation['reason'] ?? null) !== 'completed'
            || !is_array($data)
            || !red_addon_checkout_synthetic_exact_keys(
                $data,
                array_keys($indeterminate)
            )
            || ($data['valid'] ?? null) !== true
            || ($data['status'] ?? null) !== 'checkout_session_created'
            || ($data['failureStage'] ?? null) !== 'none'
            || ($data['packageId'] ?? null)
                !== 'redcms.store-lite-stripe-checkout'
            || ($data['packageVersion'] ?? null) !== '0.1.8'
            || ($data['sourcePackageVersion'] ?? null) !== '0.1.7'
            || ($data['operation'] ?? null)
                !== 'checkout.create-sandbox-real-post'
            || ($data['providerOperation'] ?? null)
                !== 'checkout.create-sandbox-real-post'
            || ($data['execution'] ?? null) !== $execution
            || ($data['inputSha256'] ?? null)
                !== ($preflight['inputSha256'] ?? null)
            || ($data['syntheticPlanSha256'] ?? null)
                !== ($preflight['syntheticPlanSha256'] ?? null)
            || ($data['contractSha256'] ?? null)
                !== ($input['contractSha256'] ?? null)
            || ($data['requestSha256'] ?? null)
                !== ($preflight['requestSha256'] ?? null)
            || !is_array($data['checkout'] ?? null)
            || !red_addon_checkout_synthetic_exact_keys(
                $data['checkout'],
                [
                    'checkoutSessionRef', 'checkoutUrlValidated', 'mode',
                    'status', 'paymentStatus', 'amountMinor', 'currency',
                    'expiresAtEpoch', 'recoveryEnabled', 'livemode',
                ]
            )
            || !is_string($data['checkout']['checkoutSessionRef'] ?? null)
            || preg_match(
                '/\Acs_test_[A-Za-z0-9_]{8,200}\z/D',
                $data['checkout']['checkoutSessionRef']
            ) !== 1
            || ($data['checkout']['checkoutUrlValidated'] ?? null) !== true
            || ($data['checkout']['mode'] ?? null) !== 'payment'
            || ($data['checkout']['status'] ?? null) !== 'open'
            || ($data['checkout']['paymentStatus'] ?? null) !== 'unpaid'
            || ($data['checkout']['amountMinor'] ?? null)
                !== ($input['checkout']['amountMinor'] ?? null)
            || ($data['checkout']['currency'] ?? null) !== 'usd'
            || ($data['checkout']['expiresAtEpoch'] ?? null)
                !== ($input['policy']['expiresAtEpoch'] ?? null)
            || ($data['checkout']['recoveryEnabled'] ?? null) !== false
            || ($data['checkout']['livemode'] ?? null) !== false
            || !red_addon_provider_contact_sha256(
                $data['responseEvidenceSha256'] ?? null
            )
            || !red_addon_provider_contact_sha256(
                $data['resultSha256'] ?? null
            )
            || ($data['restrictedTestWriteKeyRequired'] ?? null) !== true
            || ($data['credentialValueIncluded'] ?? null) !== false
            || ($data['authorizationHeaderIncluded'] ?? null) !== false
            || ($data['responseBodyIncluded'] ?? null) !== false
            || ($data['responseHeadersIncluded'] ?? null) !== false
            || ($data['checkoutUrlIncluded'] ?? null) !== false
            || ($data['networkAccess'] ?? null) !== true
            || ($data['providerContact'] ?? null) !== true
            || ($data['providerMutation'] ?? null) !== true
            || ($data['checkoutCreation'] ?? null) !== true
            || ($data['payment'] ?? null) !== false
            || ($data['webhook'] ?? null) !== false
            || ($data['browserNavigation'] ?? null) !== false
            || ($data['storeLiteMutation'] ?? null) !== false
            || ($data['retryAuthorized'] ?? null) !== false
            || ($data['liveMode'] ?? null) !== false
            || ($data['clientDeployment'] ?? null) !== false
            || ($data['executionPerformed'] ?? null) !== true
            || ($data['errors'] ?? null) !== []
        ) {
            return $indeterminate;
        }
        return $data;
    }
}

if (!function_exists('red_addon_checkout_real_mutation_outcome_sha256')) {
    function red_addon_checkout_real_mutation_outcome_sha256(
        array $plan,
        array $outcome
    ) {
        $encoded = red_addon_provider_contact_encode([
            'schema' => 1,
            'purpose' => 'sandbox-checkout-real-post-result',
            'packageId' => $plan['packageId'] ?? '',
            'packageVersion' => $plan['packageVersion'] ?? '',
            'actorAdminRecordId' =>
                (int) ($plan['actorAdminRecordId'] ?? 0),
            'databaseSha256' => $plan['databaseSha256'] ?? '',
            'orderSnapshotSha256' =>
                $plan['orderSnapshotSha256'] ?? '',
            'preflightPlanSha256' =>
                $plan['preflightPlanSha256'] ?? '',
            'claimStateSha256' => $plan['claimStateSha256'] ?? '',
            'executionStartStateSha256' =>
                $plan['executionStartStateSha256'] ?? '',
            'outcome' => $outcome,
        ]);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_checkout_real_mutation_outcome_audit')) {
    function red_addon_checkout_real_mutation_outcome_audit(
        $connection,
        array $plan,
        array $outcome
    ) {
        $detail = ($outcome['status'] ?? '')
            === 'checkout_session_created'
                ? 'sandbox_checkout_real_post_session_created'
                : 'sandbox_checkout_real_post_indeterminate';
        return red_addon_install_audit_record(
            $connection,
            'addon.action.completed',
            $plan['packageId'],
            $plan['packageVersion'],
            $plan['actorAdminRecordId'],
            'succeeded',
            $detail
        );
    }
}

if (!function_exists('red_addon_checkout_real_mutation_execute')) {
    function red_addon_checkout_real_mutation_execute(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $syntheticPlan,
        array $input,
        array $preflight,
        array $preflightOutcome,
        array $prepared,
        $expectedAuthorizationSha256,
        $expectedAuthorizationStateSha256,
        $expectedClaimStateSha256,
        $expectedExecutionStartStateSha256,
        $evaluatedAtUtc = null,
        $startAuditRecorder = null,
        $outcomeAuditRecorder = null,
        $declarations = null
    ) {
        $result = red_addon_checkout_real_mutation_result();
        foreach ([
            $expectedAuthorizationSha256,
            $expectedAuthorizationStateSha256,
            $expectedClaimStateSha256,
            $expectedExecutionStartStateSha256,
        ] as $sha256) {
            if (!red_addon_provider_contact_sha256($sha256)) {
                return $result;
            }
        }
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || (int) $actorAdminRecordId <= 0
            || red_addon_provider_contact_transaction_active($connection)
        ) {
            return $result;
        }
        $evaluatedAtUtc = $evaluatedAtUtc === null
            ? gmdate('Y-m-d\TH:i:s\Z')
            : $evaluatedAtUtc;
        $startAuditRecorder = $startAuditRecorder
            ?? static fn ($db, array $plan) =>
                red_addon_checkout_real_mutation_audit(
                    $db,
                    $plan,
                    'execution'
                );
        $outcomeAuditRecorder = $outcomeAuditRecorder
            ?? 'red_addon_checkout_real_mutation_outcome_audit';
        if (!is_callable($startAuditRecorder)
            || !is_callable($outcomeAuditRecorder)
            || !red_addon_lifecycle_lock($connection)
        ) {
            $result['status'] = 'lifecycle_locked';
            return $result;
        }
        $packageId = 'redcms.store-lite-stripe-checkout';
        $packageLocked = false;
        try {
            if (!red_addon_install_lock($connection, $packageId)) {
                $result['status'] = 'package_locked';
                return $result;
            }
            $packageLocked = true;
            $catalog = red_addon_discover($projectRoot, [
                'cmsVersion' => '5.1.0',
                'phpVersion' => PHP_VERSION,
            ]);
            $package = $catalog['packages'][$packageId] ?? null;
            if (empty($catalog['valid']) || !is_array($package)) {
                $result['status'] = 'package_invalid';
                return $result;
            }
            if (!mysqli_begin_transaction($connection)) {
                $result['status'] = 'transaction_failed';
                return $result;
            }
            try {
                if (!red_addon_checkout_mutation_lock_state(
                    $connection,
                    (int) $actorAdminRecordId
                )) {
                    throw new RuntimeException('execution_lock_failed');
                }
                $plan = red_addon_checkout_real_mutation_plan(
                    $connection,
                    $package,
                    $catalog,
                    (int) $actorAdminRecordId,
                    $syntheticPlan,
                    $input,
                    $preflight,
                    $preflightOutcome,
                    $prepared,
                    $evaluatedAtUtc,
                    'execution',
                    true,
                    $declarations
                );
                if (empty($plan['ready'])
                    || !hash_equals(
                        $expectedAuthorizationSha256,
                        $plan['authorizationSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedAuthorizationStateSha256,
                        $plan['authorizationStateSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedClaimStateSha256,
                        $plan['claimStateSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedExecutionStartStateSha256,
                        $plan['executionStartStateSha256'] ?? ''
                    )
                ) {
                    throw new RuntimeException(
                        ($plan['status'] ?? '') === 'execution_already_started'
                            ? 'execution_already_started'
                            : 'execution_changed'
                    );
                }
                $reserved = red_addon_checkout_real_mutation_reserve(
                    $connection,
                    $plan,
                    $plan['executionStartActionId'],
                    $plan['claimStateSha256'],
                    $plan['executionStartStateSha256']
                );
                if ($reserved !== 'reserved') {
                    throw new RuntimeException(
                        $reserved === 'duplicate'
                            ? 'execution_already_started'
                            : 'execution_start_failed'
                    );
                }
                if (!$startAuditRecorder($connection, $plan)) {
                    throw new RuntimeException('execution_start_audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('execution_start_commit_failed');
                }
                $result = $plan;
                $result['ready'] = false;
                $result['executionStartAvailable'] = false;
                $result['executionStarted'] = true;
                $result['startAuditRecorded'] = true;
                $result['status'] = 'execution_started';
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result = isset($plan) && is_array($plan) ? $plan : $result;
                $result['ready'] = false;
                $result['executionStartAvailable'] = false;
                $result['status'] = $throwable->getMessage();
                $result['executionStarted'] = false;
                $result['startAuditRecorded'] = false;
                return $result;
            }

            $invocation = [];
            $access = null;
            try {
                $registry = red_addon_runtime_register_package($package);
                $handler = $registry->handler(
                    'adapters',
                    $plan['adapterId']
                );
                if (!is_callable($handler)) {
                    throw new RuntimeException('registrar_invalid');
                }
                $result['registrarValidated'] = true;
                $secret = red_addon_runtime_secret_access_for_package(
                    $connection,
                    $package,
                    true,
                    ['stripe.secret-key']
                );
                if (empty($secret['valid'])
                    || (int) ($secret['settingCount'] ?? 0) !== 1
                    || (int) ($secret['resolvedCount'] ?? 0) !== 1
                    || !(($secret['access'] ?? null)
                        instanceof RED_Addon_Runtime_Secret_Access)
                ) {
                    throw new RuntimeException('secret_resolution_failed');
                }
                $access = $secret['access'];
                $result['secretResolution'] = true;
                $typedInput = red_addon_checkout_real_mutation_typed_input(
                    $input,
                    $preflight,
                    $plan
                );
                if (!is_array($typedInput)) {
                    throw new RuntimeException('typed_input_invalid');
                }
                $invocation = red_addon_adapter_invoke_registered(
                    $plan['adapterId'],
                    $plan['operation'],
                    $typedInput,
                    $plan['packageId'],
                    $handler,
                    $package['manifest'],
                    $access
                );
                $result['adapterInvoked'] = !empty($invocation['invoked']);
            } catch (Throwable $throwable) {
                $invocation = [];
            }
            unset($access, $secret, $registry, $handler, $typedInput);

            $outcome = red_addon_checkout_real_mutation_bounded_outcome(
                is_array($invocation) ? $invocation : [],
                $plan,
                $input,
                $preflight
            );
            $result['boundedOutcome'] = $outcome;
            foreach ([
                'executionPerformed', 'networkAccess', 'providerContact',
                'providerMutation', 'checkoutCreation',
            ] as $key) {
                $result[$key] = ($outcome[$key] ?? false) === true;
            }
            $result['outcomeStateSha256'] =
                red_addon_checkout_real_mutation_outcome_sha256(
                    $plan,
                    $outcome
                );
            if (!red_addon_provider_contact_sha256(
                $result['outcomeStateSha256']
            )) {
                $result['status'] = 'outcome_encoding_failed';
                return $result;
            }
            if (!mysqli_begin_transaction($connection)) {
                $result['status'] = 'outcome_transaction_failed';
                return $result;
            }
            try {
                $startRow = red_addon_checkout_real_mutation_row(
                    $connection,
                    $plan['packageId'],
                    $plan['executionStartActionId'],
                    true
                );
                $outcomeRow = red_addon_checkout_real_mutation_row(
                    $connection,
                    $plan['packageId'],
                    $plan['outcomeActionId'],
                    true
                );
                if (empty($startRow['valid'])
                    || empty($startRow['found'])
                    || !is_array($startRow['row'])
                    || !red_addon_checkout_real_mutation_row_matches(
                        $startRow['row'],
                        $plan,
                        $plan['claimStateSha256'],
                        $plan['executionStartStateSha256']
                    )
                    || empty($outcomeRow['valid'])
                    || !empty($outcomeRow['found'])
                ) {
                    throw new RuntimeException('outcome_state_changed');
                }
                $reserved = red_addon_checkout_real_mutation_reserve(
                    $connection,
                    $plan,
                    $plan['outcomeActionId'],
                    $plan['executionStartStateSha256'],
                    $result['outcomeStateSha256']
                );
                if ($reserved !== 'reserved') {
                    throw new RuntimeException('outcome_reservation_failed');
                }
                if (!$outcomeAuditRecorder($connection, $plan, $outcome)) {
                    throw new RuntimeException('outcome_audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('outcome_commit_failed');
                }
                $result['valid'] = true;
                $result['status'] = $outcome['status'];
                $result['outcomeRecorded'] = true;
                $result['outcomeAuditRecorded'] = true;
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['status'] = $throwable->getMessage();
                $result['outcomeRecorded'] = false;
                $result['outcomeAuditRecorded'] = false;
                return $result;
            }
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock($connection, $packageId);
            }
            red_addon_lifecycle_unlock($connection);
        }
    }
}

?>
