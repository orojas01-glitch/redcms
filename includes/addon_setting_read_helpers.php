<?php
/**
 * Read-only, permission-scoped current-setting model for trusted add-ons.
 *
 * Core exposes normalized non-secret effective values only to the current
 * administrator with each exact setting permission. Secret-reference settings
 * expose only whether a valid reference is configured. This helper neither
 * renders a control nor accepts a submission, resolves a secret, executes
 * package code, changes package lifecycle state, or writes a database row.
 */

require_once __DIR__ . '/addon_setting_storage_helpers.php';

if (!function_exists('red_addon_setting_read_result')) {
    function red_addon_setting_read_result($adminRecordId, $reason)
    {
        return [
            'readable' => false,
            'actorRecordId' => is_int($adminRecordId) && $adminRecordId > 0
                ? $adminRecordId
                : 0,
            'packageId' => '',
            'version' => '',
            'lifecycleState' => '',
            'settings' => [],
            'modelSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_setting_read_model_sha256')) {
    function red_addon_setting_read_model_sha256(
        $packageId,
        $version,
        $lifecycleState,
        array $settings
    ) {
        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_semantic_version($version)
            || !in_array(
                $lifecycleState,
                ['installed_disabled', 'enabled'],
                true
            )
        ) {
            return '';
        }
        $json = json_encode(
            [
                'schema' => 1,
                'packageId' => $packageId,
                'version' => $version,
                'lifecycleState' => $lifecycleState,
                'settings' => $settings,
            ],
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($json) && $json !== ''
            ? hash('sha256', $json)
            : '';
    }
}

if (!function_exists('red_addon_setting_read_model')) {
    function red_addon_setting_read_model(
        $connection,
        array $package,
        $adminRecordId
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = red_addon_setting_read_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $adminRecordId === false ? 'invalid_actor' : 'package_invalid'
        );
        if ($adminRecordId === false) {
            return $result;
        }

        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        if (!is_array($snapshot) || !is_array($manifest)) {
            return $result;
        }
        $result['packageId'] = $snapshot['id'];
        $result['version'] = $snapshot['version'];

        if (!red_addon_setting_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        $installation = red_addon_registry_installation(
            $connection,
            $snapshot['id']
        );
        if (!is_array($installation)
            || !hash_equals(
                $snapshot['version'],
                (string) ($installation['PackageVersion'] ?? '')
            )
            || !hash_equals(
                $snapshot['type'],
                (string) ($installation['PackageType'] ?? '')
            )
            || !hash_equals(
                $snapshot['manifestSha256'],
                (string) ($installation['ManifestSHA256'] ?? '')
            )
            || !hash_equals(
                $snapshot['inventorySha256'],
                (string) ($installation['InventorySHA256'] ?? '')
            )
        ) {
            $result['reason'] = 'installation_identity_mismatch';
            return $result;
        }
        $lifecycleState = (string) ($installation['LifecycleState'] ?? '');
        $result['lifecycleState'] = $lifecycleState;
        if (!in_array($lifecycleState, ['installed_disabled', 'enabled'], true)) {
            $result['reason'] = 'lifecycle_state_unsupported';
            return $result;
        }

        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema) || $schema === []) {
            $result['reason'] = 'settings_not_declared';
            return $result;
        }

        $authorizedDefinitions = [];
        foreach ($schema as $definition) {
            $settingKey = $definition['key'] ?? null;
            $permissionPlan = red_addon_setting_permission_plan(
                $manifest,
                $settingKey
            );
            if (!is_array($permissionPlan)) {
                $result['reason'] = 'setting_permission_missing';
                return $result;
            }
            $decision = red_addon_setting_permission_decision(
                $connection,
                $manifest,
                $settingKey,
                $adminRecordId
            );
            if (!empty($decision['authorized'])) {
                $authorizedDefinitions[] = $definition;
            }
        }
        if ($authorizedDefinitions === []) {
            $result['reason'] = 'permission_denied';
            return $result;
        }

        $definitions = [];
        foreach ($schema as $definition) {
            $definitions[$definition['key']] = $definition;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=?
                 ORDER BY SettingKey ASC'
            );
            if (!$statement) {
                $result['reason'] = 'storage_unavailable';
                return $result;
            }
            $packageId = $snapshot['id'];
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $result['reason'] = 'storage_unavailable';
                return $result;
            }
            $query = mysqli_stmt_get_result($statement);
            $rows = [];
            while ($query && ($row = mysqli_fetch_assoc($query))) {
                $rows[] = $row;
            }
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }

        $stored = [];
        foreach ($rows as $row) {
            $settingKey = (string) ($row['SettingKey'] ?? '');
            $valueType = (string) ($row['ValueType'] ?? '');
            $definition = $definitions[$settingKey] ?? null;
            if (!is_array($definition)
                || isset($stored[$settingKey])
                || !hash_equals(
                    (string) ($definition['type'] ?? ''),
                    $valueType
                )
            ) {
                $result['reason'] = 'stored_schema_drift';
                return $result;
            }
            if ($valueType === 'secret-reference') {
                $reference = $row['SecretReference'] ?? null;
                if ($row['ValueJSON'] !== null
                    || !red_addon_setting_string_is_valid(
                        'secret-reference',
                        $reference
                    )
                ) {
                    $result['reason'] = 'stored_value_invalid';
                    return $result;
                }
                $stored[$settingKey] = ['configured' => true];
                continue;
            }
            if ($row['SecretReference'] !== null
                || !is_string($row['ValueJSON'] ?? null)
            ) {
                $result['reason'] = 'stored_value_invalid';
                return $result;
            }
            try {
                $value = json_decode(
                    $row['ValueJSON'],
                    true,
                    8,
                    JSON_THROW_ON_ERROR
                );
            } catch (Throwable $throwable) {
                $result['reason'] = 'stored_value_invalid';
                return $result;
            }
            $check = red_addon_setting_value_result();
            $normalized = red_addon_setting_normalize_value(
                $definition,
                $value,
                $check
            );
            if ($check['errors'] !== [] || $normalized !== $value) {
                $result['reason'] = 'stored_value_invalid';
                return $result;
            }
            $stored[$settingKey] = [
                'configured' => true,
                'value' => $value,
            ];
        }

        foreach ($authorizedDefinitions as $definition) {
            $settingKey = $definition['key'];
            $valueType = $definition['type'];
            $current = $stored[$settingKey] ?? null;
            $setting = [
                'key' => $settingKey,
                'type' => $valueType,
                'configured' => is_array($current),
            ];
            if ($valueType === 'secret-reference') {
                $result['settings'][] = $setting;
                continue;
            }
            if (is_array($current)) {
                $setting['source'] = 'stored';
                $setting['value'] = $current['value'];
            } elseif (array_key_exists('default', $definition)
                && $definition['default'] !== null
            ) {
                $setting['source'] = 'default';
                $setting['value'] = $definition['default'];
            } else {
                $setting['source'] = 'unset';
                $setting['value'] = null;
            }
            $result['settings'][] = $setting;
        }

        $modelSha256 = red_addon_setting_read_model_sha256(
            $snapshot['id'],
            $snapshot['version'],
            $lifecycleState,
            $result['settings']
        );
        if (!red_addon_valid_sha256($modelSha256)) {
            $result['settings'] = [];
            $result['reason'] = 'model_encoding_failed';
            return $result;
        }
        $result['modelSha256'] = $modelSha256;
        $result['readable'] = true;
        $result['reason'] = 'readable';
        return $result;
    }
}

?>
