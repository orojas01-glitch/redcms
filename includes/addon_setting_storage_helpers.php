<?php
/**
 * Read-only per-client add-on setting storage and authorization preflight.
 *
 * This boundary inspects generic storage, exact installed package identity,
 * normalized values, and fresh package permissions. It does not write a row,
 * resolve a secret, render a form, execute package code, or change lifecycle
 * state.
 */

require_once __DIR__ . '/addon_setting_helpers.php';
require_once __DIR__ . '/addon_registry_helpers.php';

if (!function_exists('red_addon_setting_database_name')) {
    function red_addon_setting_database_name($connection)
    {
        if (!$connection) {
            return '';
        }
        try {
            $query = mysqli_query($connection, 'SELECT DATABASE() AS Name');
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            $name = is_string($row['Name'] ?? null) ? $row['Name'] : '';
            return preg_match('/\A[A-Za-z0-9_]+\z/', $name) === 1
                ? $name
                : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_setting_permission_plan')) {
    function red_addon_setting_permission_plan(array $manifest, $settingKey)
    {
        if (!is_string($settingKey)
            || !red_addon_valid_permission($settingKey)
        ) {
            return null;
        }
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema)) {
            return null;
        }
        foreach ($schema as $definition) {
            if (($definition['key'] ?? null) !== $settingKey) {
                continue;
            }
            $permission = $definition['permission'] ?? null;
            if (!is_string($permission)
                || !red_addon_valid_permission($permission)
            ) {
                return null;
            }
            return [
                'setting' => $settingKey,
                'operation' => 'manage',
                'permission' => $permission,
            ];
        }
        return null;
    }
}

if (!function_exists('red_addon_setting_storage_available')) {
    function red_addon_setting_storage_available($connection)
    {
        if (!$connection) {
            return false;
        }
        try {
            $result = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(
                    ':',
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME IN (
                         'RED_Addon_Installations',
                         'RED_Addon_Settings',
                         'RED_Admin',
                         'RED_Admin_Capabilities'
                       )),
                    (SELECT COUNT(*)=7
                       AND SUM(COLUMN_NAME='PackageID'
                         AND COLUMN_TYPE='varchar(127)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='SettingKey'
                         AND COLUMN_TYPE='varchar(160)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ValueType'
                         AND COLUMN_TYPE='varchar(32)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ValueJSON'
                         AND DATA_TYPE='text'
                         AND IS_NULLABLE='YES')=1
                       AND SUM(COLUMN_NAME='SecretReference'
                         AND COLUMN_TYPE='varchar(160)'
                         AND IS_NULLABLE='YES')=1
                       AND SUM(COLUMN_NAME='UpdatedByAdminRecordID'
                         AND DATA_TYPE='int'
                         AND COLUMN_TYPE LIKE 'int% unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='UpdatedAt'
                         AND DATA_TYPE='timestamp'
                         AND IS_NULLABLE='NO')=1
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Settings'),
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Admin_Capabilities'
                       AND COLUMN_NAME='Capability'
                       AND DATA_TYPE='varchar'
                       AND CHARACTER_MAXIMUM_LENGTH>=160
                       AND IS_NULLABLE='NO'),
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND CONSTRAINT_NAME=
                         'fk_red_addon_settings_installation'
                       AND TABLE_NAME='RED_Addon_Settings'
                       AND REFERENCED_TABLE_NAME='RED_Addon_Installations'
                       AND DELETE_RULE='RESTRICT'
                       AND UPDATE_RULE='RESTRICT')
                 ) AS StorageState"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return (string) ($row['StorageState'] ?? '') === '4:1:1:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_setting_actor_has_permission')) {
    function red_addon_setting_actor_has_permission(
        $connection,
        $adminRecordId,
        $permission
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($adminRecordId === false
            || !is_string($permission)
            || !red_addon_valid_permission($permission)
            || !red_addon_setting_storage_available($connection)
        ) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT c.Capability
                 FROM RED_Admin a
                 INNER JOIN RED_Admin_Capabilities c
                   ON c.AdminRecordID=a.RecordID
                 WHERE a.RecordID=?
                   AND BINARY c.Capability=BINARY ?
                 LIMIT 1'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'is',
                $adminRecordId,
                $permission
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return false;
            }
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            return false;
        }
        return isset($row['Capability'])
            && is_string($row['Capability'])
            && hash_equals($permission, $row['Capability']);
    }
}

if (!function_exists('red_addon_setting_permission_decision')) {
    function red_addon_setting_permission_decision(
        $connection,
        array $manifest,
        $settingKey,
        $adminRecordId
    ) {
        $result = [
            'authorized' => false,
            'actorRecordId' => is_int($adminRecordId) ? $adminRecordId : 0,
            'setting' => is_string($settingKey) ? $settingKey : '',
            'operation' => 'manage',
            'permission' => '',
            'reason' => 'schema_unavailable',
        ];
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($adminRecordId === false) {
            $result['reason'] = 'invalid_actor';
            return $result;
        }
        $result['actorRecordId'] = $adminRecordId;
        $plan = red_addon_setting_permission_plan($manifest, $settingKey);
        if (!is_array($plan)) {
            return $result;
        }
        $result['setting'] = $plan['setting'];
        $result['permission'] = $plan['permission'];
        if (!red_addon_setting_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        if (!red_addon_setting_actor_has_permission(
            $connection,
            $adminRecordId,
            $plan['permission']
        )) {
            $result['reason'] = 'permission_denied';
            return $result;
        }
        $result['authorized'] = true;
        $result['reason'] = 'authorized';
        return $result;
    }
}

if (!function_exists('red_addon_setting_current_state')) {
    function red_addon_setting_current_state(
        $connection,
        array $manifest,
        $packageId
    ) {
        $invalid = [
            'valid' => false,
            'configured' => false,
            'rowCount' => 0,
            'stateSha256' => '',
            'errors' => ['storage_unavailable'],
        ];
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema)
            || !red_addon_valid_package_id($packageId)
            || !red_addon_setting_storage_available($connection)
        ) {
            return $invalid;
        }
        $definitions = [];
        foreach ($schema as $definition) {
            $definitions[$definition['key']] = $definition;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType, ValueJSON, SecretReference,
                        UpdatedByAdminRecordID, UpdatedAt
                 FROM RED_Addon_Settings
                 WHERE PackageID=?
                 ORDER BY SettingKey ASC'
            );
            if (!$statement) {
                return $invalid;
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return $invalid;
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
            return $invalid;
        }

        $material = [];
        foreach ($rows as $row) {
            $key = (string) ($row['SettingKey'] ?? '');
            $type = (string) ($row['ValueType'] ?? '');
            $definition = $definitions[$key] ?? null;
            if (!is_array($definition)
                || $type !== ($definition['type'] ?? null)
            ) {
                $invalid['errors'] = ['stored_schema_drift'];
                return $invalid;
            }
            if ($type === 'secret-reference') {
                $reference = $row['SecretReference'] ?? null;
                if ($row['ValueJSON'] !== null
                    || !red_addon_setting_string_is_valid(
                        'secret-reference',
                        $reference
                    )
                ) {
                    $invalid['errors'] = ['stored_value_invalid'];
                    return $invalid;
                }
                $value = $reference;
            } else {
                if ($row['SecretReference'] !== null
                    || !is_string($row['ValueJSON'] ?? null)
                ) {
                    $invalid['errors'] = ['stored_value_invalid'];
                    return $invalid;
                }
                try {
                    $value = json_decode(
                        $row['ValueJSON'],
                        true,
                        8,
                        JSON_THROW_ON_ERROR
                    );
                } catch (Throwable $throwable) {
                    $invalid['errors'] = ['stored_value_invalid'];
                    return $invalid;
                }
                $check = red_addon_setting_value_result();
                $normalized = red_addon_setting_normalize_value(
                    $definition,
                    $value,
                    $check
                );
                if ($check['errors'] !== [] || $normalized !== $value) {
                    $invalid['errors'] = ['stored_value_invalid'];
                    return $invalid;
                }
            }
            $material[] = [$key, $type, $value];
        }
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            $invalid['errors'] = ['state_encoding_failed'];
            return $invalid;
        }
        return [
            'valid' => true,
            'configured' => $rows !== [],
            'rowCount' => count($rows),
            'stateSha256' => hash('sha256', $encoded),
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_setting_target_state')) {
    function red_addon_setting_target_state(
        array $manifest,
        array $validated
    ) {
        $invalid = ['valid' => false, 'rows' => [], 'stateSha256' => ''];
        if (empty($validated['valid'])
            || !is_array($validated['values'] ?? null)
            || !is_array($validated['secretReferences'] ?? null)
        ) {
            return $invalid;
        }
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema) || $schema === []) {
            return $invalid;
        }
        $rows = [];
        foreach ($schema as $definition) {
            $key = $definition['key'] ?? null;
            $type = $definition['type'] ?? null;
            if (!is_string($key) || !is_string($type)) {
                return $invalid;
            }
            if ($type === 'secret-reference') {
                if (!array_key_exists($key, $validated['secretReferences'])) {
                    return $invalid;
                }
                $value = $validated['secretReferences'][$key];
            } else {
                if (!array_key_exists($key, $validated['values'])) {
                    return $invalid;
                }
                $value = $validated['values'][$key];
            }
            $rows[$key] = [$key, $type, $value];
        }
        ksort($rows, SORT_STRING);
        $rows = array_values($rows);
        $encoded = json_encode(
            $rows,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            return $invalid;
        }
        return [
            'valid' => true,
            'rows' => $rows,
            'stateSha256' => hash('sha256', $encoded),
        ];
    }
}

if (!function_exists('red_addon_setting_write_preflight')) {
    function red_addon_setting_write_preflight(
        $connection,
        array $package,
        $adminRecordId,
        $configuredValues
    ) {
        $result = [
            'valid' => false,
            'writeReady' => false,
            'packageId' => '',
            'version' => '',
            'lifecycleState' => '',
            'permissions' => [],
            'currentStateSha256' => '',
            'targetStateSha256' => '',
            'planSha256' => '',
            'stateMutation' => false,
            'packageExecution' => false,
            'secretResolution' => false,
            'errors' => [],
        ];
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        if (!is_array($snapshot) || !is_array($manifest)) {
            $result['errors'][] = 'package_invalid';
            return $result;
        }
        $result['packageId'] = $snapshot['id'];
        $result['version'] = $snapshot['version'];
        if (!red_addon_setting_storage_available($connection)) {
            $result['errors'][] = 'storage_unavailable';
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
            $result['errors'][] = 'installation_identity_mismatch';
            return $result;
        }
        $state = (string) ($installation['LifecycleState'] ?? '');
        $result['lifecycleState'] = $state;
        if (!in_array($state, ['installed_disabled', 'enabled'], true)) {
            $result['errors'][] = 'lifecycle_state_unsupported';
            return $result;
        }
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema) || $schema === []) {
            $result['errors'][] = 'settings_not_declared';
            return $result;
        }
        $validated = red_addon_settings_validate_values(
            $manifest,
            $configuredValues
        );
        if (empty($validated['valid'])) {
            $result['errors'][] = 'values_invalid';
            return $result;
        }
        $permissions = [];
        foreach ($schema ?? [] as $definition) {
            $decision = red_addon_setting_permission_decision(
                $connection,
                $manifest,
                $definition['key'] ?? '',
                $adminRecordId
            );
            if (empty($decision['authorized'])) {
                $result['errors'][] = $decision['reason'] ===
                    'schema_unavailable'
                    ? 'setting_permission_missing'
                    : 'permission_denied';
                return $result;
            }
            $permissions[$decision['permission']] = $decision['permission'];
        }
        $permissions = array_values($permissions);
        sort($permissions, SORT_STRING);
        $result['permissions'] = $permissions;

        $current = red_addon_setting_current_state(
            $connection,
            $manifest,
            $snapshot['id']
        );
        if (empty($current['valid'])) {
            $result['errors'][] = $current['errors'][0]
                ?? 'current_state_invalid';
            return $result;
        }
        $result['currentStateSha256'] = $current['stateSha256'];
        $target = red_addon_setting_target_state($manifest, $validated);
        if (empty($target['valid'])) {
            $result['errors'][] = 'target_encoding_failed';
            return $result;
        }
        $result['targetStateSha256'] = $target['stateSha256'];
        $planJson = json_encode([
            'database' => red_addon_setting_database_name($connection),
            'actorAdminRecordId' => (int) $adminRecordId,
            'packageId' => $snapshot['id'],
            'version' => $snapshot['version'],
            'manifestSha256' => $snapshot['manifestSha256'],
            'inventorySha256' => $snapshot['inventorySha256'],
            'lifecycleState' => $state,
            'permissions' => $permissions,
            'currentStateSha256' => $result['currentStateSha256'],
            'targetStateSha256' => $result['targetStateSha256'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($planJson)) {
            $result['errors'][] = 'plan_encoding_failed';
            return $result;
        }
        $result['planSha256'] = hash('sha256', $planJson);
        $result['writeReady'] = true;
        $result['valid'] = true;
        return $result;
    }
}

?>
