<?php
/**
 * Core-owned P3E-9C1 authorization for one future sandbox Checkout mutation.
 *
 * This boundary consumes only exact P3E-9B synthetic plan evidence, requires
 * current Owner lifecycle and Store Lite order-management authority, and
 * records one nonce-bound authorization plus one value-free audit fact. It
 * resolves no secret, invokes no package, and performs no provider work.
 */

require_once __DIR__
    . '/addon_sandbox_checkout_synthetic_execution_helpers.php';
require_once __DIR__ . '/addon_provider_contact_authorization_helpers.php';
require_once __DIR__ . '/addon_package_permission_helpers.php';

if (!function_exists('red_addon_checkout_mutation_result')) {
    function red_addon_checkout_mutation_result()
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => 'invalid',
            'packageId' => '',
            'packageVersion' => '',
            'storePackageId' => '',
            'storePackageVersion' => '',
            'lifecycleState' => '',
            'actorAdminRecordId' => 0,
            'ownerSubjectSha256' => '',
            'syntheticPlanSha256' => '',
            'inputSha256' => '',
            'authorizationSha256' => '',
            'authorizationNonceSha256' => '',
            'authorizationStateSha256' => '',
            'issuedAtUtc' => '',
            'expiresAtUtc' => '',
            'maximumAttempts' => 0,
            'ownerAuthorityRevalidated' => false,
            'orderAuthorityRevalidated' => false,
            'nonceConsumed' => false,
            'auditRecorded' => false,
            'mutationAuthorityRecorded' => false,
            'claimRecorded' => false,
            'executionStarted' => false,
            'executionPerformed' => false,
            'secretResolution' => false,
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

if (!function_exists('red_addon_checkout_mutation_synthetic_plan_valid')) {
    function red_addon_checkout_mutation_synthetic_plan_valid(array $plan)
    {
        return red_addon_checkout_synthetic_exact_keys($plan, [
            'valid', 'ready', 'status', 'packageId', 'packageVersion',
            'adapterId', 'operation', 'manifestSha256', 'inventorySha256',
            'inputSha256', 'planSha256', 'adapterInvoked', 'boundedOutcome',
            'outcomeSha256', 'executionPerformed', 'networkAccess',
            'providerContact', 'providerMutation', 'checkoutCreation',
            'payment', 'webhook', 'browserNavigation', 'orderMutation',
            'retryAuthorized', 'clientDeployment', 'errors',
        ])
            && ($plan['valid'] ?? null) === true
            && ($plan['ready'] ?? null) === true
            && ($plan['status'] ?? null) === 'ready'
            && ($plan['packageId'] ?? null)
                === 'redcms.store-lite-stripe-checkout'
            && ($plan['packageVersion'] ?? null) === '0.1.5'
            && ($plan['adapterId'] ?? null)
                === 'redcms.store-lite-stripe-checkout/checkout'
            && ($plan['operation'] ?? null)
                === 'checkout.create-sandbox-synthetic'
            && red_addon_checkout_synthetic_sha256(
                $plan['manifestSha256'] ?? null
            )
            && red_addon_checkout_synthetic_sha256(
                $plan['inventorySha256'] ?? null
            )
            && red_addon_checkout_synthetic_sha256(
                $plan['inputSha256'] ?? null
            )
            && red_addon_checkout_synthetic_sha256(
                $plan['planSha256'] ?? null
            )
            && ($plan['adapterInvoked'] ?? null) === false
            && ($plan['boundedOutcome'] ?? null) === null
            && ($plan['outcomeSha256'] ?? null) === ''
            && ($plan['executionPerformed'] ?? null) === false
            && ($plan['networkAccess'] ?? null) === false
            && ($plan['providerContact'] ?? null) === false
            && ($plan['providerMutation'] ?? null) === false
            && ($plan['checkoutCreation'] ?? null) === false
            && ($plan['payment'] ?? null) === false
            && ($plan['webhook'] ?? null) === false
            && ($plan['browserNavigation'] ?? null) === false
            && ($plan['orderMutation'] ?? null) === false
            && ($plan['retryAuthorized'] ?? null) === false
            && ($plan['clientDeployment'] ?? null) === false
            && ($plan['errors'] ?? null) === [];
    }
}

if (!function_exists('red_addon_checkout_mutation_prepare')) {
    function red_addon_checkout_mutation_prepare(
        array $syntheticPlan,
        $operatorSubjectSha256,
        $authorizationNonceSha256,
        $issuedAtUtc,
        $expiresAtUtc
    ) {
        $invalid = [
            'prepared' => false,
            'authorization' => null,
            'authorizationSha256' => '',
            'ownerAuthorityRevalidationRequired' => true,
            'orderAuthorityRevalidationRequired' => true,
            'nonceConsumptionRequired' => true,
            'mutationAuthorityRecorded' => false,
            'executionPerformed' => false,
            'errors' => ['authorization_evidence_refused'],
        ];
        if (!red_addon_checkout_mutation_synthetic_plan_valid($syntheticPlan)
            || !red_addon_provider_contact_sha256($operatorSubjectSha256)
            || !red_addon_provider_contact_sha256(
                $authorizationNonceSha256
            )
            || !is_string($issuedAtUtc)
            || !is_string($expiresAtUtc)
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
            'action' => 'authorize-stripe-sandbox-checkout-creation',
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'packageVersion' => '0.1.5',
            'storePackageId' => 'redcms.store-lite',
            'storePackageVersion' => '0.1.35',
            'syntheticPlanSha256' => $syntheticPlan['planSha256'],
            'inputSha256' => $syntheticPlan['inputSha256'],
            'operatorSubjectSha256' => $operatorSubjectSha256,
            'authorizationNonceSha256' => $authorizationNonceSha256,
            'issuedAtUtc' => $issuedAtUtc,
            'expiresAtUtc' => $expiresAtUtc,
            'maximumAttempts' => 1,
            'oneTimeConsumptionRequired' => true,
            'ownerAuthorityRevalidationRequired' => true,
            'orderAuthorityRevalidationRequired' => true,
            'requiredLifecycleCapability' => 'addons.enable',
            'requiredOrderCapability' => 'store.orders.manage',
            'restrictedTestWriteKeyRequired' => true,
            'providerMutationAuthorized' => true,
            'checkoutCreationAuthorized' => true,
            'retryAuthorized' => false,
            'paymentAuthorized' => false,
            'webhookAuthorized' => false,
            'liveModeAuthorized' => false,
            'clientDeploymentAuthorized' => false,
            'credentialValueIncluded' => false,
            'claimRecorded' => false,
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
            'ownerAuthorityRevalidationRequired' => true,
            'orderAuthorityRevalidationRequired' => true,
            'nonceConsumptionRequired' => true,
            'mutationAuthorityRecorded' => false,
            'executionPerformed' => false,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_checkout_mutation_prepared_valid')) {
    function red_addon_checkout_mutation_prepared_valid(
        array $prepared,
        array $syntheticPlan,
        $evaluatedAtUtc
    ) {
        if (!red_addon_checkout_synthetic_exact_keys($prepared, [
            'prepared', 'authorization', 'authorizationSha256',
            'ownerAuthorityRevalidationRequired',
            'orderAuthorityRevalidationRequired',
            'nonceConsumptionRequired', 'mutationAuthorityRecorded',
            'executionPerformed', 'errors',
        ])
            || ($prepared['prepared'] ?? null) !== true
            || !is_array($prepared['authorization'] ?? null)
            || !red_addon_provider_contact_sha256(
                $prepared['authorizationSha256'] ?? null
            )
            || ($prepared['ownerAuthorityRevalidationRequired'] ?? null)
                !== true
            || ($prepared['orderAuthorityRevalidationRequired'] ?? null)
                !== true
            || ($prepared['nonceConsumptionRequired'] ?? null) !== true
            || ($prepared['mutationAuthorityRecorded'] ?? null) !== false
            || ($prepared['executionPerformed'] ?? null) !== false
            || ($prepared['errors'] ?? null) !== []
        ) {
            return false;
        }
        $authorization = $prepared['authorization'];
        if (!red_addon_checkout_synthetic_exact_keys($authorization, [
            'action', 'packageId', 'packageVersion', 'storePackageId',
            'storePackageVersion', 'syntheticPlanSha256', 'inputSha256',
            'operatorSubjectSha256', 'authorizationNonceSha256',
            'issuedAtUtc', 'expiresAtUtc', 'maximumAttempts',
            'oneTimeConsumptionRequired',
            'ownerAuthorityRevalidationRequired',
            'orderAuthorityRevalidationRequired',
            'requiredLifecycleCapability', 'requiredOrderCapability',
            'restrictedTestWriteKeyRequired', 'providerMutationAuthorized',
            'checkoutCreationAuthorized', 'retryAuthorized',
            'paymentAuthorized', 'webhookAuthorized', 'liveModeAuthorized',
            'clientDeploymentAuthorized', 'credentialValueIncluded',
            'claimRecorded', 'executionStarted', 'executionPerformed',
        ])) {
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
            && red_addon_checkout_mutation_synthetic_plan_valid($syntheticPlan)
            && ($authorization['action'] ?? null)
                === 'authorize-stripe-sandbox-checkout-creation'
            && ($authorization['packageId'] ?? null)
                === $syntheticPlan['packageId']
            && ($authorization['packageVersion'] ?? null)
                === $syntheticPlan['packageVersion']
            && ($authorization['storePackageId'] ?? null)
                === 'redcms.store-lite'
            && ($authorization['storePackageVersion'] ?? null) === '0.1.35'
            && hash_equals(
                $authorization['syntheticPlanSha256'],
                $syntheticPlan['planSha256']
            )
            && hash_equals(
                $authorization['inputSha256'],
                $syntheticPlan['inputSha256']
            )
            && red_addon_provider_contact_sha256(
                $authorization['operatorSubjectSha256'] ?? null
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
            && ($authorization['oneTimeConsumptionRequired'] ?? null) === true
            && ($authorization['ownerAuthorityRevalidationRequired'] ?? null)
                === true
            && ($authorization['orderAuthorityRevalidationRequired'] ?? null)
                === true
            && ($authorization['requiredLifecycleCapability'] ?? null)
                === 'addons.enable'
            && ($authorization['requiredOrderCapability'] ?? null)
                === 'store.orders.manage'
            && ($authorization['restrictedTestWriteKeyRequired'] ?? null)
                === true
            && ($authorization['providerMutationAuthorized'] ?? null) === true
            && ($authorization['checkoutCreationAuthorized'] ?? null) === true
            && ($authorization['retryAuthorized'] ?? null) === false
            && ($authorization['paymentAuthorized'] ?? null) === false
            && ($authorization['webhookAuthorized'] ?? null) === false
            && ($authorization['liveModeAuthorized'] ?? null) === false
            && ($authorization['clientDeploymentAuthorized'] ?? null) === false
            && ($authorization['credentialValueIncluded'] ?? null) === false
            && ($authorization['claimRecorded'] ?? null) === false
            && ($authorization['executionStarted'] ?? null) === false
            && ($authorization['executionPerformed'] ?? null) === false;
    }
}

if (!function_exists('red_addon_checkout_mutation_authorization_plan')) {
    function red_addon_checkout_mutation_authorization_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $input,
        array $prepared,
        $evaluatedAtUtc
    ) {
        $result = red_addon_checkout_mutation_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        $syntheticPlan = red_addon_checkout_synthetic_plan($package, $input);
        if (!($connection instanceof mysqli)
            || $actorAdminRecordId <= 0
            || empty($catalog['valid'])
            || !red_addon_checkout_mutation_synthetic_plan_valid(
                $syntheticPlan
            )
            || !red_addon_checkout_mutation_prepared_valid(
                $prepared,
                $syntheticPlan,
                $evaluatedAtUtc
            )
            || !red_admin_transaction_tables_supported($connection, [
                'RED_Addon_Admin_Action_Executions',
                'RED_Addon_Activity_Log',
            ])
        ) {
            $result['errors'][] = 'authorization_evidence_refused';
            return $result;
        }

        $authorization = $prepared['authorization'];
        $result['packageId'] = $syntheticPlan['packageId'];
        $result['packageVersion'] = $syntheticPlan['packageVersion'];
        $result['storePackageId'] = 'redcms.store-lite';
        $result['storePackageVersion'] = '0.1.35';
        $result['syntheticPlanSha256'] = $syntheticPlan['planSha256'];
        $result['inputSha256'] = $syntheticPlan['inputSha256'];
        $result['authorizationSha256'] = $prepared['authorizationSha256'];
        $result['authorizationNonceSha256'] =
            $authorization['authorizationNonceSha256'];
        $result['issuedAtUtc'] = $authorization['issuedAtUtc'];
        $result['expiresAtUtc'] = $authorization['expiresAtUtc'];
        $result['maximumAttempts'] = 1;

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
        $result['ownerSubjectSha256'] =
            red_addon_provider_contact_owner_subject_sha256(
                $connection,
                $actorAdminRecordId
            );
        if (!red_addon_provider_contact_sha256(
            $result['ownerSubjectSha256']
        ) || !hash_equals(
            $result['ownerSubjectSha256'],
            $authorization['operatorSubjectSha256']
        )) {
            $result['errors'][] = 'owner_subject_refused';
            return $result;
        }

        $snapshot = red_addon_registry_snapshot($package);
        $catalogPackage = $catalog['packages'][$result['packageId']] ?? null;
        $catalogSnapshot = is_array($catalogPackage)
            ? red_addon_registry_snapshot($catalogPackage)
            : null;
        $report = red_addon_registry_package_report($connection, $package);
        $storePackage = $catalog['packages'][$result['storePackageId']] ?? null;
        $storeSnapshot = is_array($storePackage)
            ? red_addon_registry_snapshot($storePackage)
            : null;
        $storeReport = is_array($storePackage)
            ? red_addon_registry_package_report($connection, $storePackage)
            : null;
        if (!is_array($snapshot)
            || !is_array($catalogSnapshot)
            || $catalogSnapshot !== $snapshot
            || ($snapshot['id'] ?? null) !== $result['packageId']
            || ($snapshot['version'] ?? null) !== $result['packageVersion']
            || ($snapshot['type'] ?? null) !== 'adapter'
            || ($report['status'] ?? null) !== 'enabled_current'
            || empty($report['loadable'])
            || ($report['errors'] ?? null) !== []
            || !is_array($storeSnapshot)
            || ($storeSnapshot['version'] ?? null)
                !== $result['storePackageVersion']
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
        $result['lifecycleState'] = 'enabled';

        $stateMaterial = [
            'schema' => 1,
            'purpose' => 'sandbox-checkout-mutation-authorization',
            'packageId' => $result['packageId'],
            'packageVersion' => $result['packageVersion'],
            'storePackageId' => $result['storePackageId'],
            'storePackageVersion' => $result['storePackageVersion'],
            'actorAdminRecordId' => $actorAdminRecordId,
            'ownerSubjectSha256' => $result['ownerSubjectSha256'],
            'syntheticPlanSha256' => $result['syntheticPlanSha256'],
            'inputSha256' => $result['inputSha256'],
            'authorizationSha256' => $result['authorizationSha256'],
            'authorizationNonceSha256' =>
                $result['authorizationNonceSha256'],
            'issuedAtUtc' => $result['issuedAtUtc'],
            'expiresAtUtc' => $result['expiresAtUtc'],
            'maximumAttempts' => 1,
            'mutationAuthorityRecorded' => true,
            'claimRecorded' => false,
            'executionStarted' => false,
            'executionPerformed' => false,
        ];
        $encoded = red_addon_provider_contact_encode($stateMaterial);
        if (!is_string($encoded)) {
            $result['errors'][] = 'authorization_state_encoding_failed';
            return $result;
        }
        $result['authorizationStateSha256'] = hash('sha256', $encoded);
        $result['status'] = 'ready';
        $result['ready'] = true;
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_checkout_mutation_action_id')) {
    function red_addon_checkout_mutation_action_id($nonceSha256)
    {
        return red_addon_provider_contact_sha256($nonceSha256)
            ? 'sandbox-checkout-mutation-authorize.' . $nonceSha256
            : '';
    }
}

if (!function_exists('red_addon_checkout_mutation_reserve')) {
    function red_addon_checkout_mutation_reserve($connection, array $plan)
    {
        $actionId = red_addon_checkout_mutation_action_id(
            $plan['authorizationNonceSha256'] ?? null
        );
        if ($actionId === '') {
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
                $plan['syntheticPlanSha256'],
                $plan['authorizationSha256'],
                $plan['ownerSubjectSha256'],
                $plan['authorizationStateSha256'],
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

if (!function_exists('red_addon_checkout_mutation_lock_state')) {
    function red_addon_checkout_mutation_lock_state(
        $connection,
        $actorAdminRecordId
    ) {
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!($connection instanceof mysqli) || $actorAdminRecordId <= 0) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RoleName FROM RED_Admin_Roles
                 WHERE AdminRecordID=? FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 'i', $actorAdminRecordId);
            $executed = mysqli_stmt_execute($statement);
            $result = $executed ? mysqli_stmt_get_result($statement) : false;
            $roleRows = $result ? mysqli_num_rows($result) : 0;
            mysqli_stmt_close($statement);
            if (!$executed || $roleRows !== 1) {
                return false;
            }

            $capabilities = ['addons.enable', 'store.orders.manage'];
            $statement = mysqli_prepare(
                $connection,
                'SELECT Capability FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=? AND Capability IN (?, ?)
                 ORDER BY Capability FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'iss',
                $actorAdminRecordId,
                $capabilities[0],
                $capabilities[1]
            );
            $executed = mysqli_stmt_execute($statement);
            $result = $executed ? mysqli_stmt_get_result($statement) : false;
            $capabilityRows = $result ? mysqli_num_rows($result) : 0;
            mysqli_stmt_close($statement);
            if (!$executed || $capabilityRows !== 2) {
                return false;
            }

            $adapterId = 'redcms.store-lite-stripe-checkout';
            $storeId = 'redcms.store-lite';
            $statement = mysqli_prepare(
                $connection,
                'SELECT PackageID FROM RED_Addon_Installations
                 WHERE PackageID IN (?, ?) ORDER BY PackageID FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'ss',
                $adapterId,
                $storeId
            );
            $executed = mysqli_stmt_execute($statement);
            $result = $executed ? mysqli_stmt_get_result($statement) : false;
            $packageRows = $result ? mysqli_num_rows($result) : 0;
            mysqli_stmt_close($statement);
            return $executed && $packageRows === 2;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_checkout_mutation_audit_record')) {
    function red_addon_checkout_mutation_audit_record(
        $connection,
        array $plan
    ) {
        return red_addon_install_audit_record(
            $connection,
            'addon.action.completed',
            $plan['packageId'],
            $plan['packageVersion'],
            $plan['actorAdminRecordId'],
            'succeeded',
            'sandbox_checkout_mutation_authorized'
        );
    }
}

if (!function_exists('red_addon_checkout_mutation_authorize')) {
    function red_addon_checkout_mutation_authorize(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $input,
        array $prepared,
        $expectedAuthorizationSha256,
        $evaluatedAtUtc = null,
        $auditRecorder = null
    ) {
        $result = red_addon_checkout_mutation_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || $actorAdminRecordId <= 0
            || !red_addon_provider_contact_sha256(
                $expectedAuthorizationSha256
            )
            || red_addon_provider_contact_transaction_active($connection)
        ) {
            return $result;
        }
        $evaluatedAtUtc = $evaluatedAtUtc === null
            ? gmdate('Y-m-d\TH:i:s\Z')
            : $evaluatedAtUtc;
        $auditRecorder = $auditRecorder
            ?? 'red_addon_checkout_mutation_audit_record';
        if (!is_callable($auditRecorder)) {
            return $result;
        }

        if (!red_addon_lifecycle_lock($connection)) {
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
            $plan = red_addon_checkout_mutation_authorization_plan(
                $connection,
                $package,
                $catalog,
                $actorAdminRecordId,
                $input,
                $prepared,
                $evaluatedAtUtc
            );
            $result = $plan;
            if (empty($plan['ready'])) {
                return $result;
            }
            if (!hash_equals(
                $plan['authorizationSha256'],
                $expectedAuthorizationSha256
            )) {
                $result['ready'] = false;
                $result['status'] = 'authorization_changed';
                return $result;
            }
            if (!mysqli_begin_transaction($connection)) {
                $result['ready'] = false;
                $result['status'] = 'transaction_failed';
                return $result;
            }
            try {
                if (!red_addon_checkout_mutation_lock_state(
                    $connection,
                    $actorAdminRecordId
                )) {
                    throw new RuntimeException('authorization_lock_failed');
                }
                $lockedPlan = red_addon_checkout_mutation_authorization_plan(
                    $connection,
                    $package,
                    $catalog,
                    $actorAdminRecordId,
                    $input,
                    $prepared,
                    $evaluatedAtUtc
                );
                if (empty($lockedPlan['ready'])
                    || !hash_equals(
                        $plan['authorizationStateSha256'],
                        $lockedPlan['authorizationStateSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedAuthorizationSha256,
                        $lockedPlan['authorizationSha256'] ?? ''
                    )
                ) {
                    throw new RuntimeException('authorization_changed');
                }
                $reservation = red_addon_checkout_mutation_reserve(
                    $connection,
                    $lockedPlan
                );
                if ($reservation !== 'reserved') {
                    throw new RuntimeException(
                        $reservation === 'duplicate'
                            ? 'nonce_already_consumed'
                            : 'nonce_consumption_failed'
                    );
                }
                if (!$auditRecorder($connection, $lockedPlan)) {
                    throw new RuntimeException('audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('commit_failed');
                }
                $result = $lockedPlan;
                $result['status'] = 'authorized';
                $result['ready'] = false;
                $result['nonceConsumed'] = true;
                $result['auditRecorded'] = true;
                $result['mutationAuthorityRecorded'] = true;
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['ready'] = false;
                $result['status'] = $throwable->getMessage();
                $result['nonceConsumed'] = false;
                $result['auditRecorded'] = false;
                $result['mutationAuthorityRecorded'] = false;
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
