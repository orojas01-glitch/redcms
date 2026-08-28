<?php
/** C4C2 current-client preflight for the sealed merchant-read double. */

require_once __DIR__
    . '/addon_payment_adapter_wompi_no_contact_claim_helpers.php';

if (!function_exists('red_addon_wompi_merchant_read_store_version')) {
    function red_addon_wompi_merchant_read_store_version($version)
    {
        return is_string($version)
            && in_array($version, ['0.1.35', '0.1.50'], true);
    }
}

if (!function_exists('red_addon_wompi_merchant_read_result')) {
    function red_addon_wompi_merchant_read_result($status = 'invalid')
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => (string) $status,
            'packageId' => 'redcms.store-lite-wompi',
            'packageVersion' => '',
            'storePackageId' => 'redcms.store-lite',
            'storePackageVersion' => '',
            'lifecycleState' => '',
            'actorAdminRecordId' => 0,
            'clientScopeSha256' => '',
            'databaseSha256' => '',
            'actorSubjectSha256' => '',
            'publicKeySha256' => '',
            'settingStateSha256' => '',
            'referenceStateSha256' => '',
            'merchantPlanSha256' => '',
            'preflightSha256' => '',
            'ownerAuthorityRevalidated' => false,
            'orderAuthorityRevalidated' => false,
            'packageStateRevalidated' => false,
            'settingStateRevalidated' => false,
            'secretValuesResolved' => false,
            'packageHandlerInvoked' => false,
            'sealedDoubleAvailable' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'transactionCreation' => false,
            'payment' => false,
            'eventRegistration' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'realProviderContactAuthorized' => false,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_wompi_merchant_read_setting_state')) {
    function red_addon_wompi_merchant_read_setting_state($connection)
    {
        $result = [
            'valid' => false,
            'publicKey' => '',
            'publicKeySha256' => '',
            'settingStateSha256' => '',
            'referenceStateSha256' => '',
        ];
        if (!($connection instanceof mysqli)) {
            return $result;
        }
        try {
            $packageId = 'redcms.store-lite-wompi';
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=? ORDER BY SettingKey'
            );
            if (!$statement) {
                return $result;
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
            if (!$executed
                || array_column($rows, 'SettingKey') !== [
                    'wompi.event-secret',
                    'wompi.integrity-key',
                    'wompi.private-key',
                    'wompi.public-key',
                ]
            ) {
                return $result;
            }
            $references = [];
            $publicKey = '';
            foreach ($rows as $row) {
                $key = $row['SettingKey'];
                if ($key === 'wompi.public-key') {
                    try {
                        $decoded = json_decode(
                            (string) $row['ValueJSON'],
                            true,
                            4,
                            JSON_THROW_ON_ERROR
                        );
                    } catch (Throwable $throwable) {
                        return $result;
                    }
                    if (($row['ValueType'] ?? null) !== 'text'
                        || !is_string($decoded)
                        || preg_match(
                            '/\Apub_test_[A-Za-z0-9]{16,128}\z/D',
                            $decoded
                        ) !== 1
                        || ($row['SecretReference'] ?? null) !== null
                    ) {
                        return $result;
                    }
                    $publicKey = $decoded;
                    continue;
                }
                $reference = $row['SecretReference'] ?? null;
                if (($row['ValueType'] ?? null) !== 'secret-reference'
                    || ($row['ValueJSON'] ?? null) !== null
                    || !red_addon_setting_string_is_valid(
                        'secret-reference',
                        $reference
                    )
                ) {
                    return $result;
                }
                $references[] = [
                    'settingKey' => $key,
                    'referenceSha256' => hash('sha256', $reference),
                ];
            }
            if ($publicKey === '' || count($references) !== 3) {
                return $result;
            }
            $publicKeySha256 = hash('sha256', $publicKey);
            $referenceStateSha256 = red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-merchant-read-reference-state',
                'references' => $references,
                'secretValuesResolved' => false,
            ]);
            $settingStateSha256 = red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-merchant-read-setting-state',
                'publicKeySha256' => $publicKeySha256,
                'referenceStateSha256' => $referenceStateSha256,
            ]);
            if (!red_addon_wompi_claim_sha256($publicKeySha256)
                || !red_addon_wompi_claim_sha256($referenceStateSha256)
                || !red_addon_wompi_claim_sha256($settingStateSha256)
            ) {
                return $result;
            }
            return [
                'valid' => true,
                'publicKey' => $publicKey,
                'publicKeySha256' => $publicKeySha256,
                'settingStateSha256' => $settingStateSha256,
                'referenceStateSha256' => $referenceStateSha256,
            ];
        } catch (Throwable $throwable) {
            return $result;
        }
    }
}

if (!function_exists('red_addon_wompi_merchant_read_package_state')) {
    function red_addon_wompi_merchant_read_package_state($connection)
    {
        $result = [
            'valid' => false,
            'packageVersion' => '',
            'storePackageVersion' => '',
        ];
        if (!($connection instanceof mysqli)) {
            return $result;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT PackageID, PackageVersion, LifecycleState
                 FROM RED_Addon_Installations
                 WHERE PackageID IN ('redcms.store-lite',
                                     'redcms.store-lite-wompi')
                 ORDER BY PackageID"
            );
            if (!$statement) {
                return $result;
            }
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
            $storeVersion = $rows[0]['PackageVersion'] ?? null;
            if (!$executed
                || count($rows) !== 2
                || ($rows[0]['PackageID'] ?? null) !== 'redcms.store-lite'
                || !red_addon_wompi_merchant_read_store_version(
                    $storeVersion
                )
                || ($rows[0]['LifecycleState'] ?? null) !== 'enabled'
                || ($rows[1]['PackageID'] ?? null)
                    !== 'redcms.store-lite-wompi'
                || ($rows[1]['PackageVersion'] ?? null) !== '0.1.5'
                || ($rows[1]['LifecycleState'] ?? null) !== 'enabled'
            ) {
                return $result;
            }
            return [
                'valid' => true,
                'packageVersion' => '0.1.5',
                'storePackageVersion' => $storeVersion,
            ];
        } catch (Throwable $throwable) {
            return $result;
        }
    }
}

if (!function_exists('red_addon_wompi_merchant_read_plan_material')) {
    function red_addon_wompi_merchant_read_plan_material($publicKeySha256)
    {
        if (!red_addon_wompi_claim_sha256($publicKeySha256)) {
            return null;
        }
        $plan = [
            'valid' => true,
            'status' => 'merchant_contract_request_planned',
            'provider' => 'wompi',
            'environment' => 'sandbox',
            'operation' => 'merchant.acceptance-contracts.retrieve',
            'targetHost' => 'sandbox.wompi.co',
            'targetPathTemplate' => '/v1/merchants/{public_key}',
            'httpMethod' => 'GET',
            'responseMaxBytes' => 65536,
            'publicKeySettingPresent' => true,
            'publicKeySha256' => $publicKeySha256,
            'planSha256' => '',
            'wirePathConstructed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'payment' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'errors' => [],
        ];
        $fingerprint = $plan;
        unset($fingerprint['valid'], $fingerprint['planSha256']);
        $plan['planSha256'] = red_addon_wompi_claim_hash($fingerprint);
        return red_addon_wompi_claim_sha256($plan['planSha256'])
            ? $plan
            : null;
    }
}

if (!function_exists('red_addon_wompi_merchant_read_preflight')) {
    function red_addon_wompi_merchant_read_preflight(
        $connection,
        $projectRoot,
        $actorAdminRecordId
    ) {
        $result = red_addon_wompi_merchant_read_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || realpath($projectRoot) === false
            || $actorAdminRecordId <= 0
        ) {
            $result['errors'][] = 'request_invalid';
            return $result;
        }
        $catalog = red_addon_discover($projectRoot, [
            'cmsVersion' => '5.1.0',
            'phpVersion' => PHP_VERSION,
        ]);
        $package = $catalog['packages']['redcms.store-lite-wompi'] ?? null;
        $store = $catalog['packages']['redcms.store-lite'] ?? null;
        if (empty($catalog['valid'])
            || !is_array($package)
            || !is_array($store)
            || ($package['manifest']['version'] ?? null) !== '0.1.5'
            || !red_addon_wompi_merchant_read_store_version(
                $store['manifest']['version'] ?? null
            )
        ) {
            $result['errors'][] = 'package_invalid';
            return $result;
        }
        $actor = red_admin_addon_database_actor(
            $connection,
            $actorAdminRecordId
        );
        if (!red_admin_addon_actor_can($actor, 'addons.enable')
            || !red_addon_package_permission_has_exact_grant(
                $connection,
                $actorAdminRecordId,
                'store.orders.manage'
            )
        ) {
            $result['errors'][] = 'owner_authority_refused';
            return $result;
        }
        $packageState = red_addon_wompi_merchant_read_package_state(
            $connection
        );
        $settingState = red_addon_wompi_merchant_read_setting_state(
            $connection
        );
        if (empty($packageState['valid'])
            || empty($settingState['valid'])
            || ($packageState['packageVersion'] ?? null)
                !== ($package['manifest']['version'] ?? null)
            || ($packageState['storePackageVersion'] ?? null)
                !== ($store['manifest']['version'] ?? null)
        ) {
            $result['errors'][] = 'current_state_refused';
            return $result;
        }
        $databaseSha256 = red_addon_wompi_claim_database_sha256($connection);
        $clientScopeSha256 = red_addon_wompi_claim_client_scope_sha256(
            $connection
        );
        $actorSubjectSha256 = red_addon_wompi_claim_actor_subject_sha256(
            $connection,
            $actorAdminRecordId
        );
        $merchantPlan = red_addon_wompi_merchant_read_plan_material(
            $settingState['publicKeySha256']
        );
        if (!red_addon_wompi_claim_sha256($databaseSha256)
            || !red_addon_wompi_claim_sha256($clientScopeSha256)
            || !red_addon_wompi_claim_sha256($actorSubjectSha256)
            || !is_array($merchantPlan)
        ) {
            $result['errors'][] = 'identity_encoding_failed';
            return $result;
        }
        $preflightSha256 = red_addon_wompi_claim_hash([
            'schema' => 1,
            'purpose' => 'wompi-merchant-read-sealed-double-preflight',
            'packageId' => 'redcms.store-lite-wompi',
            'packageVersion' => '0.1.5',
            'storePackageId' => 'redcms.store-lite',
            'storePackageVersion' => $packageState['storePackageVersion'],
            'actorAdminRecordId' => $actorAdminRecordId,
            'clientScopeSha256' => $clientScopeSha256,
            'databaseSha256' => $databaseSha256,
            'actorSubjectSha256' => $actorSubjectSha256,
            'publicKeySha256' => $settingState['publicKeySha256'],
            'settingStateSha256' => $settingState['settingStateSha256'],
            'referenceStateSha256' => $settingState['referenceStateSha256'],
            'merchantPlanSha256' => $merchantPlan['planSha256'],
            'operation' =>
                'merchant.acceptance-contracts.retrieve-sandbox-double',
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
            'networkDisabled' => true,
            'realProviderContactAuthorized' => false,
        ]);
        if (!red_addon_wompi_claim_sha256($preflightSha256)) {
            $result['errors'][] = 'preflight_encoding_failed';
            return $result;
        }
        $result['valid'] = true;
        $result['ready'] = true;
        $result['status'] = 'ready';
        $result['packageVersion'] = $packageState['packageVersion'];
        $result['storePackageVersion'] =
            $packageState['storePackageVersion'];
        $result['lifecycleState'] = 'enabled';
        $result['clientScopeSha256'] = $clientScopeSha256;
        $result['databaseSha256'] = $databaseSha256;
        $result['actorSubjectSha256'] = $actorSubjectSha256;
        $result['publicKeySha256'] = $settingState['publicKeySha256'];
        $result['settingStateSha256'] = $settingState['settingStateSha256'];
        $result['referenceStateSha256'] =
            $settingState['referenceStateSha256'];
        $result['merchantPlanSha256'] = $merchantPlan['planSha256'];
        $result['preflightSha256'] = $preflightSha256;
        $result['ownerAuthorityRevalidated'] = true;
        $result['orderAuthorityRevalidated'] = true;
        $result['packageStateRevalidated'] = true;
        $result['settingStateRevalidated'] = true;
        $result['sealedDoubleAvailable'] = true;
        return $result;
    }
}

?>
