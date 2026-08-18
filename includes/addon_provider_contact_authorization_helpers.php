<?php
/**
 * Core-owned P3E-7 authorization for one future read-only provider contact.
 *
 * This helper validates the closed P3E-6 readiness/envelope evidence,
 * revalidates an enabled exact package and database-backed Owner, and records
 * one nonce-bound authorization atomically. It never resolves credentials,
 * opens a network connection, invokes package PHP, or performs provider work.
 */

require_once __DIR__ . '/addon_install_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';

if (!function_exists('red_addon_provider_contact_exact_keys')) {
    function red_addon_provider_contact_exact_keys(array $value, array $expected)
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }
}

if (!function_exists('red_addon_provider_contact_sha256')) {
    function red_addon_provider_contact_sha256($value)
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}

if (!function_exists('red_addon_provider_contact_encode')) {
    function red_addon_provider_contact_encode(array $value)
    {
        try {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
            return is_string($encoded) ? $encoded : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_provider_contact_utc')) {
    function red_addon_provider_contact_utc($value)
    {
        if (!is_string($value)
            || preg_match(
                '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T'
                    . '[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/D',
                $value
            ) !== 1
        ) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $value,
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable
            || (is_array($errors)
                && ($errors['warning_count'] !== 0
                    || $errors['error_count'] !== 0))
            || $date->format('Y-m-d\TH:i:s\Z') !== $value
        ) {
            return null;
        }
        return $date;
    }
}

if (!function_exists('red_addon_provider_contact_owner_subject_sha256')) {
    function red_addon_provider_contact_owner_subject_sha256(
        $connection,
        $actorAdminRecordId
    ) {
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $database = red_addon_install_database_name($connection);
        if ($database === '' || $actorAdminRecordId <= 0) {
            return '';
        }
        $encoded = red_addon_provider_contact_encode([
            'schema' => 1,
            'purpose' => 'provider-contact-owner-subject',
            'databaseSha256' => hash('sha256', $database),
            'actorAdminRecordId' => $actorAdminRecordId,
        ]);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_provider_contact_result')) {
    function red_addon_provider_contact_result()
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => 'invalid',
            'packageId' => '',
            'packageVersion' => '',
            'lifecycleState' => '',
            'actorAdminRecordId' => 0,
            'ownerSubjectSha256' => '',
            'planSha256' => '',
            'authorizationSha256' => '',
            'authorizationNonceSha256' => '',
            'authorizationStateSha256' => '',
            'issuedAtUtc' => '',
            'expiresAtUtc' => '',
            'maximumAttempts' => 0,
            'ownerAuthorityRevalidated' => false,
            'nonceConsumed' => false,
            'auditRecorded' => false,
            'contactAuthorized' => false,
            'executionPerformed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'storeLiteMutation' => false,
            'clientDeployment' => false,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_provider_contact_readiness_valid')) {
    function red_addon_provider_contact_readiness_valid(array $readiness)
    {
        if (!red_addon_provider_contact_exact_keys($readiness, [
            'ready', 'contactPlan', 'planSha256', 'executionPerformed',
            'errors',
        ])
            || ($readiness['ready'] ?? null) !== true
            || !is_array($readiness['contactPlan'] ?? null)
            || !red_addon_provider_contact_sha256(
                $readiness['planSha256'] ?? null
            )
            || ($readiness['executionPerformed'] ?? null) !== false
            || ($readiness['errors'] ?? null) !== []
        ) {
            return false;
        }
        $plan = $readiness['contactPlan'];
        if (!red_addon_provider_contact_exact_keys($plan, [
            'operation', 'packageId', 'packageVersion',
            'packageArtifactSha256', 'runtimeProviderTransport', 'method',
            'url', 'expectedEffect', 'responseBodyProjection',
            'credentialSettingKey', 'credentialMode', 'credentialSource',
            'credentialValueIncluded', 'credentialValueSha256Included',
            'credentialEvidenceSha256', 'minimumTlsVersion', 'verifyPeer',
            'verifyHost', 'proxyMode', 'followRedirects', 'maximumRedirects',
            'connectTimeoutMilliseconds', 'totalTimeoutMilliseconds',
            'maximumResponseBytes', 'maximumAttempts',
            'oneTimeAuthorizationRequired', 'retryAuthorized',
            'mutationAuthorized', 'checkoutCreationAuthorized',
            'paymentAuthorized', 'webhookAuthorized', 'liveModeAuthorized',
            'clientDeploymentAuthorized', 'executionPerformed',
        ])) {
            return false;
        }
        $encoded = red_addon_provider_contact_encode($plan);
        $profileValid = (($plan['packageVersion'] ?? null) === '0.1.1'
                && ($plan['runtimeProviderTransport'] ?? null) === 'disabled')
            || (($plan['packageVersion'] ?? null) === '0.1.3'
                && ($plan['runtimeProviderTransport'] ?? null)
                    === 'synthetic_only')
            || (($plan['packageVersion'] ?? null) === '0.1.4'
                && ($plan['runtimeProviderTransport'] ?? null)
                    === 'provider_read_only');
        return is_string($encoded)
            && hash_equals(
                $readiness['planSha256'],
                hash('sha256', $encoded)
            )
            && ($plan['operation'] ?? null)
                === 'stripe.sandbox.read-only-resource-miss-probe'
            && ($plan['packageId'] ?? null)
                === 'redcms.store-lite-stripe-checkout'
            && $profileValid
            && red_addon_provider_contact_sha256(
                $plan['packageArtifactSha256'] ?? null
            )
            && ($plan['method'] ?? null) === 'GET'
            && ($plan['url'] ?? null)
                === 'https://api.stripe.com/v1/checkout/sessions/'
                    . 'cs_test_redcms_readiness_probe'
            && ($plan['expectedEffect'] ?? null)
                === 'read-only-resource-miss'
            && ($plan['responseBodyProjection'] ?? null) === 'none'
            && ($plan['credentialSettingKey'] ?? null) === 'stripe.secret-key'
            && ($plan['credentialMode'] ?? null) === 'restricted_test'
            && ($plan['credentialSource'] ?? null) === 'process_environment'
            && ($plan['credentialValueIncluded'] ?? null) === false
            && ($plan['credentialValueSha256Included'] ?? null) === false
            && red_addon_provider_contact_sha256(
                $plan['credentialEvidenceSha256'] ?? null
            )
            && ($plan['minimumTlsVersion'] ?? null) === '1.2'
            && ($plan['verifyPeer'] ?? null) === true
            && ($plan['verifyHost'] ?? null) === true
            && ($plan['proxyMode'] ?? null) === 'disabled'
            && ($plan['followRedirects'] ?? null) === false
            && ($plan['maximumRedirects'] ?? null) === 0
            && ($plan['connectTimeoutMilliseconds'] ?? null) === 5000
            && ($plan['totalTimeoutMilliseconds'] ?? null) === 15000
            && ($plan['maximumResponseBytes'] ?? null) === 65536
            && ($plan['maximumAttempts'] ?? null) === 1
            && ($plan['oneTimeAuthorizationRequired'] ?? null) === true
            && ($plan['retryAuthorized'] ?? null) === false
            && ($plan['mutationAuthorized'] ?? null) === false
            && ($plan['checkoutCreationAuthorized'] ?? null) === false
            && ($plan['paymentAuthorized'] ?? null) === false
            && ($plan['webhookAuthorized'] ?? null) === false
            && ($plan['liveModeAuthorized'] ?? null) === false
            && ($plan['clientDeploymentAuthorized'] ?? null) === false
            && ($plan['executionPerformed'] ?? null) === false;
    }
}

if (!function_exists('red_addon_provider_contact_prepared_valid')) {
    function red_addon_provider_contact_prepared_valid(
        array $prepared,
        array $readiness,
        $evaluatedAtUtc
    ) {
        if (!red_addon_provider_contact_exact_keys($prepared, [
            'prepared', 'authorization', 'authorizationSha256',
            'ownerAuthorityRevalidationRequired', 'nonceConsumptionRequired',
            'contactAuthorized', 'executionPerformed', 'errors',
        ])
            || ($prepared['prepared'] ?? null) !== true
            || !is_array($prepared['authorization'] ?? null)
            || !red_addon_provider_contact_sha256(
                $prepared['authorizationSha256'] ?? null
            )
            || ($prepared['ownerAuthorityRevalidationRequired'] ?? null)
                !== true
            || ($prepared['nonceConsumptionRequired'] ?? null) !== true
            || ($prepared['contactAuthorized'] ?? null) !== false
            || ($prepared['executionPerformed'] ?? null) !== false
            || ($prepared['errors'] ?? null) !== []
        ) {
            return false;
        }
        $authorization = $prepared['authorization'];
        if (!red_addon_provider_contact_exact_keys($authorization, [
            'action', 'planSha256', 'operatorSubjectSha256',
            'authorizationNonceSha256', 'issuedAtUtc', 'expiresAtUtc',
            'maximumAttempts', 'oneTimeConsumptionRequired',
            'ownerAuthorityRevalidationRequired', 'restrictedTestKeyRequired',
            'readOnlyGetAuthorized', 'retryAuthorized', 'mutationAuthorized',
            'checkoutCreationAuthorized', 'paymentAuthorized',
            'webhookAuthorized', 'liveModeAuthorized',
            'clientDeploymentAuthorized', 'credentialValueIncluded',
            'contactAuthorized', 'executionPerformed',
        ])) {
            return false;
        }
        $issuedAt = red_addon_provider_contact_utc(
            $authorization['issuedAtUtc'] ?? null
        );
        $expiresAt = red_addon_provider_contact_utc(
            $authorization['expiresAtUtc'] ?? null
        );
        $evaluatedAt = red_addon_provider_contact_utc($evaluatedAtUtc);
        $encoded = red_addon_provider_contact_encode($authorization);
        return is_string($encoded)
            && hash_equals(
                $prepared['authorizationSha256'],
                hash('sha256', $encoded)
            )
            && ($authorization['action'] ?? null)
                === 'authorize-stripe-sandbox-read-only-probe'
            && hash_equals(
                $readiness['planSha256'],
                (string) ($authorization['planSha256'] ?? '')
            )
            && red_addon_provider_contact_sha256(
                $authorization['operatorSubjectSha256'] ?? null
            )
            && red_addon_provider_contact_sha256(
                $authorization['authorizationNonceSha256'] ?? null
            )
            && $issuedAt instanceof DateTimeImmutable
            && $expiresAt instanceof DateTimeImmutable
            && $evaluatedAt instanceof DateTimeImmutable
            && $expiresAt->getTimestamp() > $issuedAt->getTimestamp()
            && $expiresAt->getTimestamp() - $issuedAt->getTimestamp() <= 900
            && $evaluatedAt->getTimestamp() >= $issuedAt->getTimestamp()
            && $evaluatedAt->getTimestamp() < $expiresAt->getTimestamp()
            && ($authorization['maximumAttempts'] ?? null) === 1
            && ($authorization['oneTimeConsumptionRequired'] ?? null) === true
            && ($authorization['ownerAuthorityRevalidationRequired'] ?? null)
                === true
            && ($authorization['restrictedTestKeyRequired'] ?? null) === true
            && ($authorization['readOnlyGetAuthorized'] ?? null) === true
            && ($authorization['retryAuthorized'] ?? null) === false
            && ($authorization['mutationAuthorized'] ?? null) === false
            && ($authorization['checkoutCreationAuthorized'] ?? null) === false
            && ($authorization['paymentAuthorized'] ?? null) === false
            && ($authorization['webhookAuthorized'] ?? null) === false
            && ($authorization['liveModeAuthorized'] ?? null) === false
            && ($authorization['clientDeploymentAuthorized'] ?? null) === false
            && ($authorization['credentialValueIncluded'] ?? null) === false
            && ($authorization['contactAuthorized'] ?? null) === false
            && ($authorization['executionPerformed'] ?? null) === false;
    }
}

if (!function_exists('red_addon_provider_contact_authorization_plan')) {
    function red_addon_provider_contact_authorization_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $readiness,
        array $prepared,
        $evaluatedAtUtc
    ) {
        $result = red_addon_provider_contact_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || $actorAdminRecordId <= 0
            || empty($catalog['valid'])
            || !red_addon_provider_contact_readiness_valid($readiness)
            || !red_addon_provider_contact_prepared_valid(
                $prepared,
                $readiness,
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

        $contactPlan = $readiness['contactPlan'];
        $authorization = $prepared['authorization'];
        $result['packageId'] = $contactPlan['packageId'];
        $result['packageVersion'] = $contactPlan['packageVersion'];
        $result['planSha256'] = $readiness['planSha256'];
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
        $result['ownerAuthorityRevalidated'] = true;

        $snapshot = red_addon_registry_snapshot($package);
        $catalogPackage = $catalog['packages'][$result['packageId']] ?? null;
        $catalogSnapshot = is_array($catalogPackage)
            ? red_addon_registry_snapshot($catalogPackage)
            : null;
        $report = red_addon_registry_package_report($connection, $package);
        $storePackage = $catalog['packages']['redcms.store-lite'] ?? null;
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
            'purpose' => 'provider-contact-authorization',
            'packageId' => $result['packageId'],
            'packageVersion' => $result['packageVersion'],
            'actorAdminRecordId' => $actorAdminRecordId,
            'ownerSubjectSha256' => $result['ownerSubjectSha256'],
            'planSha256' => $result['planSha256'],
            'authorizationSha256' => $result['authorizationSha256'],
            'authorizationNonceSha256' =>
                $result['authorizationNonceSha256'],
            'issuedAtUtc' => $result['issuedAtUtc'],
            'expiresAtUtc' => $result['expiresAtUtc'],
            'maximumAttempts' => 1,
            'contactAuthorized' => true,
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

if (!function_exists('red_addon_provider_contact_action_id')) {
    function red_addon_provider_contact_action_id($nonceSha256)
    {
        return red_addon_provider_contact_sha256($nonceSha256)
            ? 'provider-contact-authorize.' . $nonceSha256
            : '';
    }
}

if (!function_exists('red_addon_provider_contact_transaction_active')) {
    function red_addon_provider_contact_transaction_active($connection)
    {
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_provider_contact_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_provider_contact_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_provider_contact_reserve')) {
    function red_addon_provider_contact_reserve($connection, array $plan)
    {
        $actionId = red_addon_provider_contact_action_id(
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
                $plan['planSha256'],
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

if (!function_exists('red_addon_provider_contact_lock_state')) {
    function red_addon_provider_contact_lock_state(
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
                'SELECT RoleName
                 FROM RED_Admin_Roles
                 WHERE AdminRecordID=?
                 FOR UPDATE'
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

            $capability = 'addons.enable';
            $statement = mysqli_prepare(
                $connection,
                'SELECT Capability
                 FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=? AND BINARY Capability=BINARY ?
                 FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'is',
                $actorAdminRecordId,
                $capability
            );
            $executed = mysqli_stmt_execute($statement);
            $result = $executed ? mysqli_stmt_get_result($statement) : false;
            $capabilityRows = $result ? mysqli_num_rows($result) : 0;
            mysqli_stmt_close($statement);
            if (!$executed || $capabilityRows !== 1) {
                return false;
            }

            $adapterId = 'redcms.store-lite-stripe-checkout';
            $storeId = 'redcms.store-lite';
            $statement = mysqli_prepare(
                $connection,
                'SELECT PackageID
                 FROM RED_Addon_Installations
                 WHERE PackageID IN (?, ?)
                 ORDER BY PackageID
                 FOR UPDATE'
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

if (!function_exists('red_addon_provider_contact_audit_record')) {
    function red_addon_provider_contact_audit_record(
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
            'provider_contact_authorized'
        );
    }
}

if (!function_exists('red_addon_provider_contact_authorize')) {
    function red_addon_provider_contact_authorize(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $readiness,
        array $prepared,
        $expectedAuthorizationSha256,
        $evaluatedAtUtc = null,
        $auditRecorder = null
    ) {
        $result = red_addon_provider_contact_result();
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
            ?? 'red_addon_provider_contact_audit_record';
        if (!is_callable($auditRecorder)) {
            return $result;
        }

        if (!red_addon_lifecycle_lock($connection)) {
            $result['status'] = 'lifecycle_locked';
            return $result;
        }
        $packageLocked = false;
        try {
            $packageId = 'redcms.store-lite-stripe-checkout';
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
            $plan = red_addon_provider_contact_authorization_plan(
                $connection,
                $package,
                $catalog,
                $actorAdminRecordId,
                $readiness,
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
                if (!red_addon_provider_contact_lock_state(
                    $connection,
                    $actorAdminRecordId
                )) {
                    throw new RuntimeException('authorization_lock_failed');
                }
                $lockedPlan = red_addon_provider_contact_authorization_plan(
                    $connection,
                    $package,
                    $catalog,
                    $actorAdminRecordId,
                    $readiness,
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
                $reservation = red_addon_provider_contact_reserve(
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
                $result['contactAuthorized'] = true;
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['ready'] = false;
                $result['status'] = $throwable->getMessage();
                $result['nonceConsumed'] = false;
                $result['auditRecorded'] = false;
                $result['contactAuthorized'] = false;
                return $result;
            }
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock(
                    $connection,
                    'redcms.store-lite-stripe-checkout'
                );
            }
            red_addon_lifecycle_unlock($connection);
        }
    }
}

?>
