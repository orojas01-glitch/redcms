<?php
/** Atomic persistence for one complete validated per-client setting object. */

require_once __DIR__ . '/addon_setting_storage_helpers.php';
require_once __DIR__ . '/addon_install_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';

if (!function_exists('red_addon_setting_transaction_active')) {
    function red_addon_setting_transaction_active($connection)
    {
        try {
            if (!mysqli_query($connection, 'SAVEPOINT redcms_setting_guard')) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_setting_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_setting_write_audit_record')) {
    function red_addon_setting_write_audit_record(
        $connection,
        $packageId,
        $packageVersion,
        $adminRecordId
    ) {
        return red_addon_install_audit_record(
            $connection,
            'addon.settings.updated',
            $packageId,
            $packageVersion,
            $adminRecordId,
            'succeeded',
            'settings_updated'
        );
    }
}

if (!function_exists('red_addon_setting_lock_rows')) {
    function red_addon_setting_lock_rows($connection, $packageId)
    {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT PackageID FROM RED_Addon_Installations
                 WHERE PackageID=? FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            $locked = mysqli_stmt_execute($statement);
            $query = $locked ? mysqli_stmt_get_result($statement) : false;
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            if (!is_array($row)
                || !hash_equals($packageId, (string) ($row['PackageID'] ?? ''))
            ) {
                return false;
            }
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey FROM RED_Addon_Settings
                 WHERE PackageID=? ORDER BY SettingKey ASC FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            $locked = mysqli_stmt_execute($statement);
            $query = $locked ? mysqli_stmt_get_result($statement) : false;
            if ($query) {
                while (mysqli_fetch_assoc($query)) {
                    // Consume every locked row.
                }
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            return $locked;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_setting_replace_rows')) {
    function red_addon_setting_replace_rows(
        $connection,
        $packageId,
        $adminRecordId,
        array $rows
    ) {
        $statement = mysqli_prepare(
            $connection,
            'DELETE FROM RED_Addon_Settings WHERE PackageID=?'
        );
        if (!$statement) {
            return false;
        }
        mysqli_stmt_bind_param($statement, 's', $packageId);
        $deleted = mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        if (!$deleted) {
            return false;
        }

        $insert = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Settings (
                PackageID, SettingKey, ValueType, ValueJSON,
                SecretReference, UpdatedByAdminRecordID
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$insert) {
            return false;
        }
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== 3) {
                mysqli_stmt_close($insert);
                return false;
            }
            [$key, $type, $value] = $row;
            $valueJson = null;
            $secretReference = null;
            if ($type === 'secret-reference') {
                $secretReference = $value;
            } else {
                $valueJson = json_encode(
                    $value,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
                if (!is_string($valueJson)) {
                    mysqli_stmt_close($insert);
                    return false;
                }
            }
            mysqli_stmt_bind_param(
                $insert,
                'sssssi',
                $packageId,
                $key,
                $type,
                $valueJson,
                $secretReference,
                $adminRecordId
            );
            if (!mysqli_stmt_execute($insert)) {
                mysqli_stmt_close($insert);
                return false;
            }
        }
        mysqli_stmt_close($insert);
        return true;
    }
}

if (!function_exists('red_addon_setting_write')) {
    function red_addon_setting_write(
        $connection,
        array $package,
        $adminRecordId,
        $configuredValues,
        $expectedPlanSha256,
        $auditRecorder = null,
        $afterReplace = null
    ) {
        $result = [
            'status' => 'invalid',
            'packageId' => '',
            'version' => '',
            'previousStateSha256' => '',
            'stateSha256' => '',
            'planSha256' => '',
        ];
        $snapshot = red_addon_registry_snapshot($package);
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if (!is_array($snapshot)
            || $adminRecordId === false
            || !red_addon_valid_sha256($expectedPlanSha256)
            || !red_admin_transaction_tables_supported($connection, [
                'RED_Addon_Installations',
                'RED_Addon_Settings',
                'RED_Addon_Activity_Log',
            ])
            || red_addon_setting_transaction_active($connection)
        ) {
            return $result;
        }
        $result['packageId'] = $snapshot['id'];
        $result['version'] = $snapshot['version'];
        $auditRecorder = $auditRecorder ?? 'red_addon_setting_write_audit_record';
        if (!is_callable($auditRecorder)
            || ($afterReplace !== null && !is_callable($afterReplace))
        ) {
            return $result;
        }
        if (!red_addon_lifecycle_lock($connection)) {
            $result['status'] = 'locked';
            return $result;
        }
        try {
            if (!red_addon_install_lock($connection, $snapshot['id'])) {
                $result['status'] = 'locked';
                return $result;
            }
            try {
                if (!mysqli_begin_transaction($connection)) {
                    $result['status'] = 'transaction_failed';
                    return $result;
                }
                $reason = 'transaction_failed';
                try {
                    if (!red_addon_setting_lock_rows(
                        $connection,
                        $snapshot['id']
                    )) {
                        $reason = 'storage_unavailable';
                        throw new RuntimeException($reason);
                    }
                    $plan = red_addon_setting_write_preflight(
                        $connection,
                        $package,
                        $adminRecordId,
                        $configuredValues
                    );
                    if (empty($plan['valid'])) {
                        $reason = $plan['errors'][0] ?? 'plan_invalid';
                        throw new RuntimeException($reason);
                    }
                    $result['planSha256'] = $plan['planSha256'];
                    $result['previousStateSha256'] =
                        $plan['currentStateSha256'];
                    if (!hash_equals(
                        $expectedPlanSha256,
                        $plan['planSha256']
                    )) {
                        $reason = 'plan_changed';
                        throw new RuntimeException($reason);
                    }
                    if (hash_equals(
                        $plan['currentStateSha256'],
                        $plan['targetStateSha256']
                    )) {
                        if (!mysqli_commit($connection)) {
                            throw new RuntimeException($reason);
                        }
                        $result['status'] = 'unchanged';
                        $result['stateSha256'] = $plan['currentStateSha256'];
                        return $result;
                    }
                    $validated = red_addon_settings_validate_values(
                        $package['manifest'],
                        $configuredValues
                    );
                    $target = red_addon_setting_target_state(
                        $package['manifest'],
                        $validated
                    );
                    if (empty($target['valid'])
                        || !hash_equals(
                            $plan['targetStateSha256'],
                            $target['stateSha256']
                        )
                    ) {
                        $reason = 'target_changed';
                        throw new RuntimeException($reason);
                    }
                    if (!red_addon_setting_replace_rows(
                        $connection,
                        $snapshot['id'],
                        $adminRecordId,
                        $target['rows']
                    )) {
                        $reason = 'write_failed';
                        throw new RuntimeException($reason);
                    }
                    if ($afterReplace !== null
                        && $afterReplace($connection, $snapshot['id']) !== true
                    ) {
                        $reason = 'injected_failure';
                        throw new RuntimeException($reason);
                    }
                    $post = red_addon_setting_current_state(
                        $connection,
                        $package['manifest'],
                        $snapshot['id']
                    );
                    if (empty($post['valid'])
                        || !hash_equals(
                            $target['stateSha256'],
                            $post['stateSha256']
                        )
                        || (int) $post['rowCount'] !== count($target['rows'])
                    ) {
                        $reason = 'postcondition_failed';
                        throw new RuntimeException($reason);
                    }
                    if ($auditRecorder(
                        $connection,
                        $snapshot['id'],
                        $snapshot['version'],
                        $adminRecordId
                    ) !== true) {
                        $reason = 'audit_failed';
                        throw new RuntimeException($reason);
                    }
                    if (!mysqli_commit($connection)) {
                        throw new RuntimeException($reason);
                    }
                    $result['status'] = 'updated';
                    $result['stateSha256'] = $post['stateSha256'];
                    return $result;
                } catch (Throwable $throwable) {
                    mysqli_rollback($connection);
                    $result['status'] = $reason;
                    return $result;
                }
            } finally {
                red_addon_install_unlock($connection, $snapshot['id']);
            }
        } finally {
            red_addon_lifecycle_unlock($connection);
        }
    }
}

?>
