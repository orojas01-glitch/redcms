<?php
/**
 * Transactional package-owned updates for existing add-on component records.
 *
 * This activation-blocked helper exposes no route or form. It accepts only an
 * enabled, exact runtime owner with a declared editor, current view and edit
 * grants, a matching state hash, schema-valid values, and one registrar-bound
 * writer. Core locks the placement parent and owns the transaction; trusted
 * first-party package code may update only its declared InnoDB tables.
 */

require_once __DIR__ . '/admin_transaction_helpers.php';
require_once __DIR__ . '/addon_component_editor_data_helpers.php';

if (!function_exists('red_addon_component_editor_write_result')) {
    function red_addon_component_editor_write_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $reason
    ) {
        return [
            'updated' => false,
            'unchanged' => false,
            'actorRecordId' => is_int($adminRecordId) ? $adminRecordId : 0,
            'contentRecordId' => is_int($contentRecordId)
                ? $contentRecordId
                : 0,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'package' => '',
            'permission' => '',
            'values' => [],
            'previousStateHash' => '',
            'stateHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_state_hash_valid')) {
    function red_addon_component_editor_state_hash_valid($stateHash)
    {
        return is_string($stateHash)
            && preg_match('/\A[a-f0-9]{64}\z/', $stateHash) === 1;
    }
}

if (!function_exists('red_addon_component_editor_writer_tables')) {
    function red_addon_component_editor_writer_tables($componentId)
    {
        $metadata = red_addon_runtime_metadata(
            'componentDataWriters',
            $componentId
        );
        $tables = is_array($metadata['tables'] ?? null)
            ? $metadata['tables']
            : [];
        if ($tables === [] || count($tables) > 8) {
            return null;
        }
        $normalized = [];
        $reserved = [
            'red_addon_installations',
            'red_addon_migrations',
            'red_addon_activity_log',
        ];
        foreach ($tables as $table) {
            if (!is_string($table)
                || preg_match('/\ARED_Addon_[A-Za-z0-9_]{1,54}\z/', $table)
                    !== 1
                || in_array(strtolower($table), $reserved, true)
                || isset($normalized[$table])
            ) {
                return null;
            }
            $normalized[$table] = true;
        }
        return array_keys($normalized);
    }
}

if (!function_exists('red_addon_component_editor_lock_binding')) {
    function red_addon_component_editor_lock_binding(
        $connection,
        $packageId,
        $componentId,
        $contentRecordId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT a.RecordID, a.Component, i.PackageID
                 FROM RED_Articles a
                 INNER JOIN RED_Addon_Installations i
                   ON i.PackageID=?
                  AND i.LifecycleState=\'enabled\'
                 WHERE a.RecordID=?
                   AND a.Component=?
                 LIMIT 1
                 FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'sis',
                $packageId,
                $contentRecordId,
                $componentId
            );
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            return false;
        }
        return (int) ($row['RecordID'] ?? 0) === $contentRecordId
            && is_string($row['Component'] ?? null)
            && hash_equals($componentId, $row['Component'])
            && is_string($row['PackageID'] ?? null)
            && hash_equals($packageId, $row['PackageID']);
    }
}

if (!function_exists('red_addon_component_editor_transaction_active')) {
    function red_addon_component_editor_transaction_active($connection)
    {
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_component_writer_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_component_writer_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_invoke_writer')) {
    function red_addon_component_editor_invoke_writer(
        $writer,
        $connection,
        array $context,
        array $values
    ) {
        $bufferLevel = ob_get_level();
        $emitted = '';
        try {
            ob_start();
            $written = $writer($connection, $context, $values);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on component data writer altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log(
                'RED-CMS add-on component data writing failed: '
                    . (string) ($context['component'] ?? '')
            );
            return false;
        }
        return $emitted === '' && $written === true;
    }
}

if (!function_exists('red_addon_component_editor_update_values')) {
    function red_addon_component_editor_update_values(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $expectedStateHash,
        $submittedValues
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $contentRecordId = filter_var(
            $contentRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = red_addon_component_editor_write_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $adminRecordId === false
                ? 'invalid_actor'
                : ($contentRecordId === false
                    ? 'invalid_content_record'
                    : 'schema_unavailable')
        );
        if ($adminRecordId === false || $contentRecordId === false) {
            return $result;
        }
        if (!red_addon_component_editor_state_hash_valid($expectedStateHash)) {
            $result['reason'] = 'invalid_state_hash';
            return $result;
        }

        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        $schema = red_addon_component_editor_schema($manifest, $componentId);
        if (!red_addon_valid_package_id($packageId) || !is_array($schema)) {
            $result['reason'] = 'schema_unavailable';
            return $result;
        }
        $validated = red_addon_component_editor_validate_values(
            $manifest,
            $componentId,
            $submittedValues
        );
        if (empty($validated['valid'])
            || !is_array($validated['values'] ?? null)
        ) {
            $result['reason'] = 'invalid_values';
            return $result;
        }
        if (red_addon_runtime_manifest($packageId) !== $manifest) {
            $result['reason'] = 'manifest_mismatch';
            return $result;
        }

        $writerOwner = red_addon_runtime_owner(
            'componentDataWriters',
            $componentId
        );
        $writer = red_addon_runtime_handler(
            'componentDataWriters',
            $componentId
        );
        $tables = red_addon_component_editor_writer_tables($componentId);
        if (!is_string($writerOwner)
            || !hash_equals($packageId, $writerOwner)
            || !is_callable($writer)
            || !is_array($tables)
        ) {
            $result['reason'] = 'writer_unavailable';
            return $result;
        }
        $result['package'] = $packageId;
        if (!red_admin_transaction_tables_supported(
            $connection,
            array_merge(['RED_Articles'], $tables)
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        if (red_addon_component_editor_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }

        if (!mysqli_begin_transaction($connection)) {
            $result['reason'] = 'transaction_failed';
            return $result;
        }
        $transactionReason = 'transaction_failed';
        try {
            if (!red_addon_component_editor_lock_binding(
                $connection,
                $packageId,
                $componentId,
                $contentRecordId
            )) {
                $transactionReason = 'binding_unavailable';
                throw new RuntimeException($transactionReason);
            }

            $authorization = red_addon_component_editor_permission_decision(
                $connection,
                $manifest,
                $componentId,
                'edit',
                $adminRecordId
            );
            $result['permission'] = is_string(
                $authorization['permission'] ?? null
            ) ? $authorization['permission'] : '';
            if (empty($authorization['authorized'])) {
                $transactionReason = 'permission_denied';
                throw new RuntimeException($transactionReason);
            }

            $current = red_addon_component_editor_load_values(
                $connection,
                $manifest,
                $componentId,
                $contentRecordId,
                $adminRecordId
            );
            if (empty($current['loaded'])) {
                $transactionReason = ($current['reason'] ?? '')
                    === 'permission_denied'
                        ? 'view_permission_denied'
                        : 'current_state_unavailable';
                throw new RuntimeException($transactionReason);
            }
            $result['previousStateHash'] = $current['stateHash'];
            if (!hash_equals($current['stateHash'], $expectedStateHash)) {
                $transactionReason = 'stale_state';
                throw new RuntimeException($transactionReason);
            }

            if ($current['values'] === $validated['values']) {
                if (!mysqli_commit($connection)) {
                    $transactionReason = 'transaction_failed';
                    throw new RuntimeException($transactionReason);
                }
                $result['unchanged'] = true;
                $result['values'] = $current['values'];
                $result['stateHash'] = $current['stateHash'];
                $result['reason'] = 'unchanged';
                return $result;
            }

            if (!red_addon_component_editor_invoke_writer(
                $writer,
                $connection,
                [
                    'component' => $componentId,
                    'contentRecordId' => $contentRecordId,
                    'actorRecordId' => $adminRecordId,
                    'previousStateHash' => $current['stateHash'],
                ],
                $validated['values']
            )) {
                $transactionReason = 'writer_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_component_editor_transaction_active($connection)) {
                $transactionReason = 'transaction_lost';
                throw new RuntimeException($transactionReason);
            }

            $saved = red_addon_component_editor_load_values(
                $connection,
                $manifest,
                $componentId,
                $contentRecordId,
                $adminRecordId
            );
            if (empty($saved['loaded'])
                || $saved['values'] !== $validated['values']
                || !red_addon_component_editor_state_hash_valid(
                    $saved['stateHash'] ?? null
                )
            ) {
                $transactionReason = 'postcondition_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_component_editor_transaction_active($connection)) {
                $transactionReason = 'transaction_lost';
                throw new RuntimeException($transactionReason);
            }
            if (!mysqli_commit($connection)) {
                $transactionReason = 'transaction_failed';
                throw new RuntimeException($transactionReason);
            }

            $result['updated'] = true;
            $result['values'] = $saved['values'];
            $result['stateHash'] = $saved['stateHash'];
            $result['reason'] = 'updated';
            return $result;
        } catch (Throwable $throwable) {
            try {
                mysqli_rollback($connection);
            } catch (Throwable $rollbackFailure) {
                error_log('RED-CMS add-on component rollback failed.');
            }
            $result['reason'] = $transactionReason;
            return $result;
        }
    }
}
