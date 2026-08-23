<?php
/**
 * C4B4B durable Wompi no-contact authorization and one-claim boundary.
 *
 * This helper persists only already-contained C4B4A evidence. It invokes no
 * package code, resolves no secret, and contains no transport or order write.
 */

require_once __DIR__ . '/addon_install_helpers.php';
require_once __DIR__ . '/addon_package_permission_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';

if (!function_exists('red_addon_wompi_claim_exact_keys')) {
    function red_addon_wompi_claim_exact_keys(array $value, array $expected)
    {
        return array_keys($value) === $expected;
    }
}

if (!function_exists('red_addon_wompi_claim_sha256')) {
    function red_addon_wompi_claim_sha256($value)
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}

if (!function_exists('red_addon_wompi_claim_encode')) {
    function red_addon_wompi_claim_encode(array $value)
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

if (!function_exists('red_addon_wompi_claim_hash')) {
    function red_addon_wompi_claim_hash(array $value)
    {
        $encoded = red_addon_wompi_claim_encode($value);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_wompi_claim_database_sha256')) {
    function red_addon_wompi_claim_database_sha256($connection)
    {
        $name = red_addon_install_database_name($connection);
        return $name !== '' ? hash('sha256', $name) : '';
    }
}

if (!function_exists('red_addon_wompi_claim_client_scope_sha256')) {
    function red_addon_wompi_claim_client_scope_sha256($connection)
    {
        $databaseSha256 = red_addon_wompi_claim_database_sha256($connection);
        return red_addon_wompi_claim_sha256($databaseSha256)
            ? red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-no-contact-client-scope',
                'packageId' => 'redcms.store-lite-wompi',
                'databaseSha256' => $databaseSha256,
            ])
            : '';
    }
}

if (!function_exists('red_addon_wompi_claim_actor_subject_sha256')) {
    function red_addon_wompi_claim_actor_subject_sha256(
        $connection,
        $actorAdminRecordId
    ) {
        $databaseSha256 = red_addon_wompi_claim_database_sha256($connection);
        $actorAdminRecordId = (int) $actorAdminRecordId;
        return red_addon_wompi_claim_sha256($databaseSha256)
            && $actorAdminRecordId > 0
            ? red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-no-contact-actor-subject',
                'databaseSha256' => $databaseSha256,
                'actorAdminRecordId' => $actorAdminRecordId,
            ])
            : '';
    }
}

if (!function_exists('red_addon_wompi_claim_secret_availability_sha256')) {
    function red_addon_wompi_claim_secret_availability_sha256($connection)
    {
        if (!($connection instanceof mysqli)) {
            return '';
        }
        try {
            $packageId = 'redcms.store-lite-wompi';
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=?
                 ORDER BY SettingKey'
            );
            if (!$statement) {
                return '';
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            $executed = mysqli_stmt_execute($statement);
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $rows = [];
            while ($query && ($row = mysqli_fetch_assoc($query))) {
                $rows[] = $row;
            }
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            if (!$executed || count($rows) !== 4) {
                return '';
            }
            $expected = [
                'wompi.event-secret',
                'wompi.integrity-key',
                'wompi.private-key',
                'wompi.public-key',
            ];
            if (array_column($rows, 'SettingKey') !== $expected) {
                return '';
            }
            $availability = [];
            foreach ($rows as $row) {
                $key = $row['SettingKey'];
                if ($key === 'wompi.public-key') {
                    $decoded = json_decode((string) $row['ValueJSON'], true);
                    if (($row['ValueType'] ?? null) !== 'text'
                        || !is_string($decoded)
                        || $decoded === ''
                        || ($row['SecretReference'] ?? null) !== null
                    ) {
                        return '';
                    }
                    $availability[] = [
                        'settingKey' => $key,
                        'valueType' => 'text',
                        'available' => true,
                    ];
                    continue;
                }
                $reference = $row['SecretReference'] ?? null;
                if (($row['ValueType'] ?? null) !== 'secret-reference'
                    || ($row['ValueJSON'] ?? null) !== null
                    || !is_string($reference)
                    || preg_match(
                        '/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{0,254}\z/D',
                        $reference
                    ) !== 1
                ) {
                    return '';
                }
                $availability[] = [
                    'settingKey' => $key,
                    'valueType' => 'secret-reference',
                    'available' => true,
                ];
            }
            return red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-setting-reference-availability',
                'settings' => $availability,
                'secretValuesResolved' => false,
            ]);
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_wompi_claim_authorization_valid')) {
    function red_addon_wompi_claim_authorization_valid(
        array $authorization,
        $evaluatedAtEpoch
    ) {
        if (!red_addon_wompi_claim_exact_keys($authorization, [
            'valid', 'status', 'packageId', 'packageVersion',
            'storePackageId', 'minimumStoreVersion', 'provider', 'method',
            'environment', 'operation', 'transportMode', 'orderId',
            'amountMinor', 'currency', 'clientScopeSha256', 'databaseSha256',
            'actorSubjectSha256', 'secretAvailabilitySha256',
            'authorizationNonceSha256', 'planSha256',
            'requestEvidenceSha256', 'wireRequestSha256',
            'wireEvidenceSha256', 'consentEvidenceSha256', 'issuedAtEpoch',
            'expiresAtEpoch', 'maximumAttempts',
            'ownerAuthorityRevalidated', 'orderAuthorityRevalidated',
            'packageEnabled', 'storeEnabled', 'oneAttemptConfirmed',
            'noRetryConfirmed', 'networkDisabledConfirmed',
            'providerContactDenied', 'providerMutationDenied',
            'orderMutationDenied', 'noContactAttemptAuthorized',
            'providerContactAuthorized', 'providerMutationAuthorized',
            'orderMutationAuthorized', 'durableClaimRequired',
            'authorizationPersisted', 'claimPersisted', 'executionStarted',
            'executionPerformed', 'secretResolution', 'networkAccess',
            'providerContact', 'providerMutation', 'paymentVerified',
            'eventAgreement', 'paymentApplied', 'orderMutation',
            'retryAuthorized', 'authorizationSha256', 'errors',
        ])) {
            return false;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'secretAvailabilitySha256', 'authorizationNonceSha256',
            'planSha256', 'requestEvidenceSha256', 'wireRequestSha256',
            'wireEvidenceSha256', 'consentEvidenceSha256',
            'authorizationSha256',
        ] as $key) {
            if (!red_addon_wompi_claim_sha256($authorization[$key] ?? null)) {
                return false;
            }
        }
        foreach ([
            'ownerAuthorityRevalidated', 'orderAuthorityRevalidated',
            'packageEnabled', 'storeEnabled', 'oneAttemptConfirmed',
            'noRetryConfirmed', 'networkDisabledConfirmed',
            'providerContactDenied', 'providerMutationDenied',
            'orderMutationDenied', 'noContactAttemptAuthorized',
            'durableClaimRequired',
        ] as $key) {
            if (($authorization[$key] ?? null) !== true) {
                return false;
            }
        }
        foreach ([
            'providerContactAuthorized', 'providerMutationAuthorized',
            'orderMutationAuthorized', 'authorizationPersisted',
            'claimPersisted', 'executionStarted', 'executionPerformed',
            'secretResolution', 'networkAccess', 'providerContact',
            'providerMutation', 'paymentVerified', 'eventAgreement',
            'paymentApplied', 'orderMutation', 'retryAuthorized',
        ] as $key) {
            if (($authorization[$key] ?? null) !== false) {
                return false;
            }
        }
        $issued = $authorization['issuedAtEpoch'] ?? null;
        $expires = $authorization['expiresAtEpoch'] ?? null;
        if (($authorization['valid'] ?? null) !== true
            || ($authorization['status'] ?? null)
                !== 'authorized_no_contact_attempt'
            || ($authorization['packageId'] ?? null)
                !== 'redcms.store-lite-wompi'
            || ($authorization['packageVersion'] ?? null) !== '0.1.4'
            || ($authorization['storePackageId'] ?? null)
                !== 'redcms.store-lite'
            || ($authorization['minimumStoreVersion'] ?? null) !== '0.1.35'
            || ($authorization['provider'] ?? null) !== 'wompi'
            || ($authorization['method'] ?? null) !== 'nequi'
            || ($authorization['environment'] ?? null) !== 'sandbox'
            || ($authorization['operation'] ?? null)
                !== 'checkout.create-sandbox-no-contact'
            || ($authorization['transportMode'] ?? null)
                !== 'sealed_double_only'
            || !is_string($authorization['orderId'] ?? null)
            || preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $authorization['orderId']
            ) !== 1
            || !is_int($authorization['amountMinor'] ?? null)
            || $authorization['amountMinor'] < 100
            || $authorization['amountMinor'] > 999999999999
            || ($authorization['currency'] ?? null) !== 'COP'
            || !is_int($issued)
            || !is_int($expires)
            || !is_int($evaluatedAtEpoch)
            || $issued < 1
            || $issued > $evaluatedAtEpoch
            || $evaluatedAtEpoch >= $expires
            || $expires <= $issued
            || $expires - $issued > 900
            || ($authorization['maximumAttempts'] ?? null) !== 1
            || ($authorization['errors'] ?? null) !== []
        ) {
            return false;
        }
        $material = $authorization;
        unset($material['valid'], $material['authorizationSha256']);
        return hash_equals(
            $authorization['authorizationSha256'],
            red_addon_wompi_claim_hash($material)
        );
    }
}

if (!function_exists('red_addon_wompi_claim_preparation_valid')) {
    function red_addon_wompi_claim_preparation_valid(
        array $claim,
        array $authorization
    ) {
        if (!red_addon_wompi_claim_exact_keys($claim, [
            'valid', 'status', 'packageId', 'packageVersion',
            'storePackageId', 'minimumStoreVersion', 'provider', 'method',
            'environment', 'operation', 'transportMode', 'orderId',
            'amountMinor', 'currency', 'clientScopeSha256', 'databaseSha256',
            'actorSubjectSha256', 'authorizationSha256',
            'authorizationNonceSha256', 'claimNonceSha256', 'planSha256',
            'wireRequestSha256', 'wireEvidenceSha256', 'issuedAtEpoch',
            'expiresAtEpoch', 'claimedAtEpoch', 'attemptNumber',
            'maximumAttempts', 'remainingAttempts', 'priorClaimCount',
            'oneAttemptConfirmed', 'noRetryConfirmed',
            'durableClaimRequired', 'claimPersisted',
            'replayProtectionActive', 'executionAuthorized',
            'providerContactAuthorized', 'providerMutationAuthorized',
            'orderMutationAuthorized', 'executionStarted',
            'executionPerformed', 'secretResolution', 'networkAccess',
            'providerContact', 'providerMutation', 'paymentVerified',
            'eventAgreement', 'paymentApplied', 'orderMutation',
            'retryAuthorized', 'claimSha256', 'errors',
        ])) {
            return false;
        }
        $claimedAt = $claim['claimedAtEpoch'] ?? null;
        if (!is_int($claimedAt)
            || !red_addon_wompi_claim_authorization_valid(
                $authorization,
                $claimedAt
            )
        ) {
            return false;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'authorizationSha256', 'authorizationNonceSha256',
            'claimNonceSha256', 'planSha256', 'wireRequestSha256',
            'wireEvidenceSha256', 'claimSha256',
        ] as $key) {
            if (!red_addon_wompi_claim_sha256($claim[$key] ?? null)) {
                return false;
            }
        }
        foreach ([
            'oneAttemptConfirmed', 'noRetryConfirmed',
            'durableClaimRequired',
        ] as $key) {
            if (($claim[$key] ?? null) !== true) {
                return false;
            }
        }
        foreach ([
            'claimPersisted', 'replayProtectionActive',
            'executionAuthorized', 'providerContactAuthorized',
            'providerMutationAuthorized', 'orderMutationAuthorized',
            'executionStarted', 'executionPerformed', 'secretResolution',
            'networkAccess', 'providerContact', 'providerMutation',
            'paymentVerified', 'eventAgreement', 'paymentApplied',
            'orderMutation', 'retryAuthorized',
        ] as $key) {
            if (($claim[$key] ?? null) !== false) {
                return false;
            }
        }
        foreach ([
            'packageId', 'packageVersion', 'storePackageId',
            'minimumStoreVersion', 'provider', 'method', 'environment',
            'operation', 'transportMode', 'orderId', 'amountMinor',
            'currency', 'clientScopeSha256', 'databaseSha256',
            'actorSubjectSha256', 'authorizationNonceSha256', 'planSha256',
            'wireRequestSha256', 'wireEvidenceSha256', 'issuedAtEpoch',
            'expiresAtEpoch', 'maximumAttempts',
        ] as $key) {
            if (($claim[$key] ?? null) !== ($authorization[$key] ?? null)) {
                return false;
            }
        }
        if (($claim['valid'] ?? null) !== true
            || ($claim['status'] ?? null)
                !== 'claim_prepared_no_contact_attempt'
            || !hash_equals(
                $claim['authorizationSha256'],
                $authorization['authorizationSha256']
            )
            || hash_equals(
                $claim['claimNonceSha256'],
                $claim['authorizationNonceSha256']
            )
            || $claimedAt < $authorization['issuedAtEpoch']
            || $claimedAt >= $authorization['expiresAtEpoch']
            || ($claim['attemptNumber'] ?? null) !== 1
            || ($claim['maximumAttempts'] ?? null) !== 1
            || ($claim['remainingAttempts'] ?? null) !== 0
            || ($claim['priorClaimCount'] ?? null) !== 0
            || ($claim['errors'] ?? null) !== []
        ) {
            return false;
        }
        $material = $claim;
        unset($material['valid'], $material['claimSha256']);
        return hash_equals(
            $claim['claimSha256'],
            red_addon_wompi_claim_hash($material)
        );
    }
}

if (!function_exists('red_addon_wompi_claim_result')) {
    function red_addon_wompi_claim_result($status = 'invalid')
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => (string) $status,
            'packageId' => 'redcms.store-lite-wompi',
            'packageVersion' => '0.1.4',
            'storePackageId' => 'redcms.store-lite',
            'storePackageVersion' => '0.1.35',
            'lifecycleState' => '',
            'actorAdminRecordId' => 0,
            'clientScopeSha256' => '',
            'databaseSha256' => '',
            'actorSubjectSha256' => '',
            'secretAvailabilitySha256' => '',
            'orderId' => '',
            'amountMinor' => 0,
            'currency' => 'COP',
            'planSha256' => '',
            'wireRequestSha256' => '',
            'wireEvidenceSha256' => '',
            'authorizationSha256' => '',
            'claimSha256' => '',
            'authorizationNonceSha256' => '',
            'claimNonceSha256' => '',
            'authorizationStateSha256' => '',
            'claimStateSha256' => '',
            'authorizationActionId' => '',
            'claimActionId' => '',
            'issuedAtEpoch' => 0,
            'expiresAtEpoch' => 0,
            'claimedAtEpoch' => 0,
            'maximumAttempts' => 0,
            'remainingAttempts' => 0,
            'ownerAuthorityRevalidated' => false,
            'orderAuthorityRevalidated' => false,
            'packageStateRevalidated' => false,
            'settingAvailabilityRevalidated' => false,
            'authorizationAvailable' => false,
            'claimAvailable' => false,
            'authorizationRecorded' => false,
            'claimRecorded' => false,
            'replayProtectionActive' => false,
            'auditRecorded' => false,
            'executionAuthorized' => false,
            'executionStarted' => false,
            'executionPerformed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'paymentVerified' => false,
            'eventAgreement' => false,
            'paymentApplied' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_wompi_claim_action_id')) {
    function red_addon_wompi_claim_action_id($purpose, $nonceSha256)
    {
        if (!in_array($purpose, ['authorize', 'claim'], true)
            || !red_addon_wompi_claim_sha256($nonceSha256)
        ) {
            return '';
        }
        return 'wompi-no-contact-' . $purpose . '.' . $nonceSha256;
    }
}

if (!function_exists('red_addon_wompi_claim_action_rows')) {
    function red_addon_wompi_claim_action_rows(
        $connection,
        $authorizationActionId,
        $claimActionId,
        $lock = false
    ) {
        if (!($connection instanceof mysqli)
            || !is_string($authorizationActionId)
            || $authorizationActionId === ''
            || !is_string($claimActionId)
            || $claimActionId === ''
            || !is_bool($lock)
        ) {
            return null;
        }
        try {
            $packageId = 'redcms.store-lite-wompi';
            $sql = 'SELECT ActionID FROM RED_Addon_Admin_Action_Executions
                    WHERE PackageID=? AND TargetRecordID=1
                      AND ActionID IN (?, ?)
                    ORDER BY ActionID';
            if ($lock) {
                $sql .= ' FOR UPDATE';
            }
            $statement = mysqli_prepare($connection, $sql);
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param(
                $statement,
                'sss',
                $packageId,
                $authorizationActionId,
                $claimActionId
            );
            $executed = mysqli_stmt_execute($statement);
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $rows = [];
            while ($query && ($row = mysqli_fetch_assoc($query))) {
                $rows[] = $row['ActionID'];
            }
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            return $executed ? $rows : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_wompi_claim_package_ready')) {
    function red_addon_wompi_claim_package_ready(
        $connection,
        array $package,
        array $catalog
    ) {
        $snapshot = red_addon_registry_snapshot($package);
        $catalogPackage = $catalog['packages']['redcms.store-lite-wompi']
            ?? null;
        $catalogSnapshot = is_array($catalogPackage)
            ? red_addon_registry_snapshot($catalogPackage)
            : null;
        $report = red_addon_registry_package_report($connection, $package);
        $store = $catalog['packages']['redcms.store-lite'] ?? null;
        $storeSnapshot = is_array($store)
            ? red_addon_registry_snapshot($store)
            : null;
        $storeReport = is_array($store)
            ? red_addon_registry_package_report($connection, $store)
            : null;
        return is_array($snapshot)
            && $catalogSnapshot === $snapshot
            && ($snapshot['id'] ?? null) === 'redcms.store-lite-wompi'
            && ($snapshot['version'] ?? null) === '0.1.4'
            && ($snapshot['type'] ?? null) === 'adapter'
            && ($report['status'] ?? null) === 'enabled_current'
            && !empty($report['loadable'])
            && ($report['errors'] ?? null) === []
            && is_array($storeSnapshot)
            && ($storeSnapshot['id'] ?? null) === 'redcms.store-lite'
            && ($storeSnapshot['version'] ?? null) === '0.1.35'
            && red_addon_package_permission_declared(
                $store,
                'store.orders.manage'
            )
            && ($storeReport['status'] ?? null) === 'enabled_current'
            && !empty($storeReport['loadable'])
            && ($storeReport['errors'] ?? null) === [];
    }
}

if (!function_exists('red_addon_wompi_claim_plan')) {
    function red_addon_wompi_claim_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $authorization,
        array $claim,
        $evaluatedAtEpoch,
        $lockRows = false
    ) {
        $result = red_addon_wompi_claim_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || $actorAdminRecordId <= 0
            || !is_int($evaluatedAtEpoch)
            || !is_bool($lockRows)
            || empty($catalog['valid'])
            || !red_addon_wompi_claim_authorization_valid(
                $authorization,
                $evaluatedAtEpoch
            )
            || !red_addon_wompi_claim_preparation_valid(
                $claim,
                $authorization
            )
            || !red_admin_transaction_tables_supported($connection, [
                'RED_Addon_Admin_Action_Executions',
                'RED_Addon_Activity_Log',
            ])
        ) {
            $result['errors'][] = 'claim_evidence_refused';
            return $result;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'secretAvailabilitySha256', 'orderId', 'amountMinor', 'currency',
            'planSha256', 'wireRequestSha256', 'wireEvidenceSha256',
            'authorizationSha256', 'authorizationNonceSha256',
            'issuedAtEpoch', 'expiresAtEpoch', 'maximumAttempts',
        ] as $key) {
            $result[$key] = $authorization[$key];
        }
        foreach ([
            'claimSha256', 'claimNonceSha256', 'claimedAtEpoch',
            'remainingAttempts',
        ] as $key) {
            $result[$key] = $claim[$key];
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

        $databaseSha256 = red_addon_wompi_claim_database_sha256($connection);
        $clientScopeSha256 =
            red_addon_wompi_claim_client_scope_sha256($connection);
        $actorSubjectSha256 = red_addon_wompi_claim_actor_subject_sha256(
            $connection,
            $actorAdminRecordId
        );
        $availabilitySha256 =
            red_addon_wompi_claim_secret_availability_sha256($connection);
        if (!hash_equals($authorization['databaseSha256'], $databaseSha256)
            || !hash_equals(
                $authorization['clientScopeSha256'],
                $clientScopeSha256
            )
            || !hash_equals(
                $authorization['actorSubjectSha256'],
                $actorSubjectSha256
            )
            || !hash_equals(
                $authorization['secretAvailabilitySha256'],
                $availabilitySha256
            )
        ) {
            $result['errors'][] = 'client_scope_refused';
            return $result;
        }
        $result['settingAvailabilityRevalidated'] = true;
        if (!red_addon_wompi_claim_package_ready(
            $connection,
            $package,
            $catalog
        )) {
            $result['errors'][] = 'package_state_refused';
            return $result;
        }
        $result['packageStateRevalidated'] = true;
        $result['lifecycleState'] = 'enabled';

        $result['authorizationActionId'] =
            red_addon_wompi_claim_action_id(
                'authorize',
                $authorization['authorizationNonceSha256']
            );
        $result['claimActionId'] = red_addon_wompi_claim_action_id(
            'claim',
            $authorization['authorizationNonceSha256']
        );
        $rows = red_addon_wompi_claim_action_rows(
            $connection,
            $result['authorizationActionId'],
            $result['claimActionId'],
            $lockRows
        );
        if (!is_array($rows)) {
            $result['errors'][] = 'claim_ledger_refused';
            return $result;
        }
        if ($rows !== []) {
            $result['status'] = 'attempt_already_claimed';
            $result['errors'][] = 'attempt_already_claimed';
            return $result;
        }

        $result['authorizationStateSha256'] =
            red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-no-contact-durable-authorization',
                'packageId' => $result['packageId'],
                'packageVersion' => $result['packageVersion'],
                'databaseSha256' => $databaseSha256,
                'actorAdminRecordId' => $actorAdminRecordId,
                'actorSubjectSha256' => $actorSubjectSha256,
                'orderId' => $result['orderId'],
                'planSha256' => $result['planSha256'],
                'wireRequestSha256' => $result['wireRequestSha256'],
                'authorizationSha256' => $result['authorizationSha256'],
                'authorizationNonceSha256' =>
                    $result['authorizationNonceSha256'],
                'issuedAtEpoch' => $result['issuedAtEpoch'],
                'expiresAtEpoch' => $result['expiresAtEpoch'],
                'maximumAttempts' => 1,
                'authorizationRecorded' => true,
                'claimRecorded' => false,
                'executionAuthorized' => false,
            ]);
        $result['claimStateSha256'] = red_addon_wompi_claim_hash([
            'schema' => 1,
            'purpose' => 'wompi-no-contact-durable-claim',
            'authorizationStateSha256' =>
                $result['authorizationStateSha256'],
            'claimSha256' => $result['claimSha256'],
            'claimNonceSha256' => $result['claimNonceSha256'],
            'claimedAtEpoch' => $result['claimedAtEpoch'],
            'maximumAttempts' => 1,
            'remainingAttempts' => 0,
            'claimRecorded' => true,
            'replayProtectionActive' => true,
            'executionAuthorized' => false,
        ]);
        if (!red_addon_wompi_claim_sha256(
            $result['authorizationStateSha256']
        ) || !red_addon_wompi_claim_sha256($result['claimStateSha256'])) {
            $result['errors'][] = 'claim_state_encoding_failed';
            return $result;
        }
        $result['authorizationAvailable'] = true;
        $result['claimAvailable'] = true;
        $result['status'] = 'ready';
        $result['ready'] = true;
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_wompi_claim_transaction_active')) {
    function red_addon_wompi_claim_transaction_active($connection)
    {
        try {
            if (!mysqli_query($connection, 'SAVEPOINT redcms_wompi_claim')) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_wompi_claim'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_wompi_claim_lock_state')) {
    function red_addon_wompi_claim_lock_state(
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
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $roleRows = $query ? mysqli_num_rows($query) : 0;
            if ($query) {
                mysqli_free_result($query);
            }
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
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $found = [];
            while ($query && ($row = mysqli_fetch_assoc($query))) {
                $found[] = $row['Capability'];
            }
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            if (!$executed || $found !== $capabilities) {
                return false;
            }
            $wompi = 'redcms.store-lite-wompi';
            $store = 'redcms.store-lite';
            $statement = mysqli_prepare(
                $connection,
                'SELECT PackageID FROM RED_Addon_Installations
                 WHERE PackageID IN (?, ?) ORDER BY PackageID FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 'ss', $store, $wompi);
            $executed = mysqli_stmt_execute($statement);
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $packageRows = $query ? mysqli_num_rows($query) : 0;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            if (!$executed || $packageRows !== 2) {
                return false;
            }
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey FROM RED_Addon_Settings
                 WHERE PackageID=? ORDER BY SettingKey FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 's', $wompi);
            $executed = mysqli_stmt_execute($statement);
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $settingRows = $query ? mysqli_num_rows($query) : 0;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            return $executed && $settingRows === 4;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_wompi_claim_reserve')) {
    function red_addon_wompi_claim_reserve(
        $connection,
        array $plan,
        $purpose
    ) {
        if (!in_array($purpose, ['authorization', 'claim'], true)) {
            return 'failed';
        }
        $actionId = $purpose === 'authorization'
            ? $plan['authorizationActionId']
            : $plan['claimActionId'];
        $contractSha256 = $purpose === 'authorization'
            ? $plan['authorizationSha256']
            : $plan['claimSha256'];
        $previousSha256 = $purpose === 'authorization'
            ? $plan['actorSubjectSha256']
            : $plan['authorizationStateSha256'];
        $stateSha256 = $purpose === 'authorization'
            ? $plan['authorizationStateSha256']
            : $plan['claimStateSha256'];
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
                $contractSha256,
                $previousSha256,
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

if (!function_exists('red_addon_wompi_claim_audit_record')) {
    function red_addon_wompi_claim_audit_record(
        $connection,
        array $plan,
        $purpose
    ) {
        $detail = $purpose === 'authorization'
            ? 'wompi_no_contact_authorized'
            : ($purpose === 'claim'
                ? 'wompi_no_contact_claimed'
                : '');
        return $detail !== '' && red_addon_install_audit_record(
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

if (!function_exists('red_addon_wompi_claim_record')) {
    function red_addon_wompi_claim_record(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $authorization,
        array $claim,
        $expectedAuthorizationStateSha256,
        $expectedClaimStateSha256,
        $evaluatedAtEpoch,
        $auditRecorder = null
    ) {
        $result = red_addon_wompi_claim_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || $actorAdminRecordId <= 0
            || !red_addon_wompi_claim_sha256(
                $expectedAuthorizationStateSha256
            )
            || !red_addon_wompi_claim_sha256($expectedClaimStateSha256)
            || !is_int($evaluatedAtEpoch)
            || red_addon_wompi_claim_transaction_active($connection)
        ) {
            return $result;
        }
        $auditRecorder = $auditRecorder
            ?? 'red_addon_wompi_claim_audit_record';
        if (!is_callable($auditRecorder)) {
            return $result;
        }
        if (!red_addon_lifecycle_lock($connection)) {
            $result['status'] = 'lifecycle_locked';
            return $result;
        }
        $packageId = 'redcms.store-lite-wompi';
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
            $plan = red_addon_wompi_claim_plan(
                $connection,
                $package,
                $catalog,
                $actorAdminRecordId,
                $authorization,
                $claim,
                $evaluatedAtEpoch
            );
            $result = $plan;
            if (empty($plan['ready'])) {
                return $result;
            }
            if (!hash_equals(
                $plan['authorizationStateSha256'],
                $expectedAuthorizationStateSha256
            ) || !hash_equals(
                $plan['claimStateSha256'],
                $expectedClaimStateSha256
            )) {
                $result['ready'] = false;
                $result['authorizationAvailable'] = false;
                $result['claimAvailable'] = false;
                $result['status'] = 'claim_changed';
                return $result;
            }
            if (!mysqli_begin_transaction($connection)) {
                $result['status'] = 'transaction_failed';
                return $result;
            }
            try {
                if (!red_addon_wompi_claim_lock_state(
                    $connection,
                    $actorAdminRecordId
                )) {
                    throw new RuntimeException('authority_lock_failed');
                }
                $lockedPlan = red_addon_wompi_claim_plan(
                    $connection,
                    $package,
                    $catalog,
                    $actorAdminRecordId,
                    $authorization,
                    $claim,
                    $evaluatedAtEpoch,
                    true
                );
                if (empty($lockedPlan['ready'])
                    || !hash_equals(
                        $expectedAuthorizationStateSha256,
                        $lockedPlan['authorizationStateSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedClaimStateSha256,
                        $lockedPlan['claimStateSha256'] ?? ''
                    )
                ) {
                    throw new RuntimeException(
                        ($lockedPlan['status'] ?? '')
                            === 'attempt_already_claimed'
                            ? 'attempt_already_claimed'
                            : 'claim_changed'
                    );
                }
                if (red_addon_wompi_claim_reserve(
                    $connection,
                    $lockedPlan,
                    'authorization'
                ) !== 'reserved') {
                    throw new RuntimeException('authorization_reservation_failed');
                }
                if (!$auditRecorder(
                    $connection,
                    $lockedPlan,
                    'authorization'
                )) {
                    throw new RuntimeException('authorization_audit_failed');
                }
                $claimReservation = red_addon_wompi_claim_reserve(
                    $connection,
                    $lockedPlan,
                    'claim'
                );
                if ($claimReservation !== 'reserved') {
                    throw new RuntimeException(
                        $claimReservation === 'duplicate'
                            ? 'attempt_already_claimed'
                            : 'claim_reservation_failed'
                    );
                }
                if (!$auditRecorder($connection, $lockedPlan, 'claim')) {
                    throw new RuntimeException('claim_audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('commit_failed');
                }
                $result = $lockedPlan;
                $result['ready'] = false;
                $result['status'] = 'claimed';
                $result['authorizationAvailable'] = false;
                $result['claimAvailable'] = false;
                $result['authorizationRecorded'] = true;
                $result['claimRecorded'] = true;
                $result['replayProtectionActive'] = true;
                $result['auditRecorded'] = true;
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['ready'] = false;
                $result['authorizationAvailable'] = false;
                $result['claimAvailable'] = false;
                $result['status'] = $throwable->getMessage();
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
