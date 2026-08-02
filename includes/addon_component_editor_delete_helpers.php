<?php
/**
 * Read-only delete preflight for add-on component editor records.
 *
 * This helper binds exact permission, inactive parent, package state,
 * immutable revision, runtime deleter, and transaction-table evidence into a
 * deterministic plan. It never invokes the deleter, opens a transaction,
 * removes data, renders a control, or exposes an endpoint.
 */

require_once __DIR__ . '/addon_component_editor_parent_helpers.php';

if (!function_exists('red_addon_component_editor_delete_result')) {
    function red_addon_component_editor_delete_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $reason
    ) {
        return [
            'ready' => false,
            'actorRecordId' => is_int($adminRecordId) ? $adminRecordId : 0,
            'contentRecordId' => is_int($contentRecordId)
                ? $contentRecordId
                : 0,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'package' => '',
            'viewPermission' => '',
            'deletePermission' => '',
            'parentStateHash' => '',
            'packageStateHash' => '',
            'packageRevisionId' => 0,
            'transactionTables' => [],
            'planHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_deleter_tables')) {
    function red_addon_component_editor_deleter_tables($componentId)
    {
        $metadata = red_addon_runtime_metadata(
            'componentDataDeleters',
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
            'red_addon_component_revisions',
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

if (!function_exists('red_addon_component_editor_delete_plan_hash')) {
    function red_addon_component_editor_delete_plan_hash(array $plan)
    {
        $json = json_encode(
            $plan,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($json) && $json !== ''
            ? hash('sha256', $json)
            : '';
    }
}

if (!function_exists('red_addon_component_editor_delete_preflight')) {
    function red_addon_component_editor_delete_preflight(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $expectedParentStateHash,
        $expectedPackageStateHash
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
        $result = red_addon_component_editor_delete_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $adminRecordId === false
                ? 'invalid_actor'
                : ($contentRecordId === false
                    ? 'invalid_content_record'
                    : 'invalid_state_hash')
        );
        if ($adminRecordId === false
            || $contentRecordId === false
            || !red_addon_component_editor_state_hash_valid(
                $expectedParentStateHash
            )
            || !red_addon_component_editor_state_hash_valid(
                $expectedPackageStateHash
            )
        ) {
            return $result;
        }

        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if (!red_addon_valid_package_id($packageId)
            || !is_array(
                red_addon_component_editor_schema($manifest, $componentId)
            )
        ) {
            $result['reason'] = 'schema_unavailable';
            return $result;
        }
        $result['package'] = $packageId;

        $delete = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'delete',
            $adminRecordId
        );
        $result['deletePermission'] = is_string(
            $delete['permission'] ?? null
        ) ? $delete['permission'] : '';
        if (empty($delete['authorized'])) {
            $result['reason'] = (string) (
                $delete['reason'] ?? 'permission_denied'
            );
            return $result;
        }

        $deleterOwner = red_addon_runtime_owner(
            'componentDataDeleters',
            $componentId
        );
        $deleter = red_addon_runtime_handler(
            'componentDataDeleters',
            $componentId
        );
        $tables = red_addon_component_editor_deleter_tables($componentId);
        if (!is_string($deleterOwner)
            || !hash_equals($packageId, $deleterOwner)
            || !is_callable($deleter)
            || !is_array($tables)
        ) {
            $result['reason'] = 'deleter_unavailable';
            return $result;
        }
        $result['transactionTables'] = $tables;

        $parent = red_addon_component_editor_parent_state(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId
        );
        $result['viewPermission'] = is_string(
            $parent['viewPermission'] ?? null
        ) ? $parent['viewPermission'] : '';
        if (empty($parent['loaded'])) {
            $result['reason'] = (string) (
                $parent['reason'] ?? 'parent_state_unavailable'
            );
            return $result;
        }
        $parentStateHash = is_string($parent['stateHash'] ?? null)
            ? $parent['stateHash']
            : '';
        $packageStateHash = is_string($parent['packageStateHash'] ?? null)
            ? $parent['packageStateHash']
            : '';
        if (!hash_equals($expectedParentStateHash, $parentStateHash)) {
            $result['reason'] = 'stale_parent_state';
            return $result;
        }
        if (!hash_equals($expectedPackageStateHash, $packageStateHash)) {
            $result['reason'] = 'stale_package_state';
            return $result;
        }

        if (!red_admin_transaction_tables_supported(
            $connection,
            array_merge(
                [
                    'RED_Articles',
                    'RED_Content_Revisions',
                    'RED_Addon_Component_Revisions',
                    'RED_Page_SEO',
                ],
                $tables
            )
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }

        $history = red_addon_component_revision_history(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            1
        );
        $latest = is_array($history[0] ?? null) ? $history[0] : null;
        if (!is_array($latest)
            || !hash_equals($packageStateHash, $latest['stateHash'])
        ) {
            $result['reason'] = 'revision_state_unavailable';
            return $result;
        }

        $plan = [
            'schema' => 1,
            'package' => $packageId,
            'component' => $componentId,
            'contentRecordId' => (string) $contentRecordId,
            'actorRecordId' => (string) $adminRecordId,
            'viewPermission' => $result['viewPermission'],
            'deletePermission' => $result['deletePermission'],
            'parentStateHash' => $parentStateHash,
            'packageStateHash' => $packageStateHash,
            'packageRevisionId' => (string) $latest['revisionId'],
            'transactionTables' => $tables,
        ];
        $planHash = red_addon_component_editor_delete_plan_hash($plan);
        if ($planHash === '') {
            $result['reason'] = 'plan_unavailable';
            return $result;
        }

        $result['ready'] = true;
        $result['parentStateHash'] = $parentStateHash;
        $result['packageStateHash'] = $packageStateHash;
        $result['packageRevisionId'] = $latest['revisionId'];
        $result['planHash'] = $planHash;
        $result['reason'] = 'ready';
        return $result;
    }
}

if (!function_exists('red_addon_component_editor_deletion_result')) {
    function red_addon_component_editor_deletion_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $reason
    ) {
        return [
            'deleted' => false,
            'actorRecordId' => is_int($adminRecordId) ? $adminRecordId : 0,
            'contentRecordId' => is_int($contentRecordId)
                ? $contentRecordId
                : 0,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'package' => '',
            'viewPermission' => '',
            'deletePermission' => '',
            'parentStateHash' => '',
            'packageStateHash' => '',
            'parentRevisionId' => 0,
            'packageRevisionId' => 0,
            'planHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_delete_invoke_deleter')) {
    function red_addon_component_editor_delete_invoke_deleter(
        $deleter,
        $connection,
        array $context
    ) {
        $bufferLevel = ob_get_level();
        $emitted = '';
        try {
            ob_start();
            $deleted = $deleter($connection, $context);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on component deleter altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log(
                'RED-CMS add-on component deletion failed: '
                    . (string) ($context['component'] ?? '')
            );
            return false;
        }
        return $emitted === '' && $deleted === true;
    }
}

if (!function_exists('red_addon_component_editor_delete_parent_revision')) {
    function red_addon_component_editor_delete_parent_revision(
        $connection,
        $contentRecordId,
        $adminRecordId
    ) {
        $snapshot = red_admin_content_revision_capture(
            $connection,
            $contentRecordId
        );
        $latest = red_admin_content_revision_latest(
            $connection,
            $contentRecordId
        );
        $json = is_array($snapshot)
            ? red_admin_content_revision_json($snapshot)
            : '';
        $snapshotHash = is_array($snapshot)
            ? red_admin_content_revision_hash($snapshot)
            : '';
        $actorAlias = red_addon_component_revision_actor_alias(
            $connection,
            $adminRecordId
        );
        $contentType = is_array($snapshot)
            ? substr((string) ($snapshot['type'] ?? ''), 0, 50)
            : '';
        $revisionNumber = is_array($latest)
            ? ((int) ($latest['RevisionNumber'] ?? 0) + 1)
            : 0;
        if ($json === ''
            || !red_addon_component_editor_create_hash_valid($snapshotHash)
            || !is_string($actorAlias)
            || $contentType === ''
            || $revisionNumber < 2
        ) {
            return 0;
        }
        $operation = 'delete';
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Content_Revisions (
                    ContentRecordID, ContentType, RevisionNumber, Operation,
                    ActorAdminRecordID, ActorAlias, Snapshot, SnapshotHash,
                    RestoredFromRevisionID
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)'
            );
            if (!$statement) {
                return 0;
            }
            mysqli_stmt_bind_param(
                $statement,
                'isisisss',
                $contentRecordId,
                $contentType,
                $revisionNumber,
                $operation,
                $adminRecordId,
                $actorAlias,
                $json,
                $snapshotHash
            );
            $inserted = mysqli_stmt_execute($statement);
            $revisionId = $inserted
                ? (int) mysqli_insert_id($connection)
                : 0;
            mysqli_stmt_close($statement);
            return $revisionId;
        } catch (Throwable $throwable) {
            return 0;
        }
    }
}

if (!function_exists('red_addon_component_editor_delete_package_absent')) {
    function red_addon_component_editor_delete_package_absent(
        $connection,
        array $tables,
        $contentRecordId
    ) {
        foreach ($tables as $table) {
            try {
                $statement = mysqli_prepare(
                    $connection,
                    'SELECT COUNT(*) AS RowCount FROM `' . $table
                        . '` WHERE ContentRecordID=?'
                );
                if (!$statement) {
                    return false;
                }
                mysqli_stmt_bind_param($statement, 'i', $contentRecordId);
                if (!mysqli_stmt_execute($statement)) {
                    mysqli_stmt_close($statement);
                    return false;
                }
                $queryResult = mysqli_stmt_get_result($statement);
                $row = $queryResult ? mysqli_fetch_assoc($queryResult) : null;
                if ($queryResult) {
                    mysqli_free_result($queryResult);
                }
                mysqli_stmt_close($statement);
                if (!is_array($row) || (int) ($row['RowCount'] ?? -1) !== 0) {
                    return false;
                }
            } catch (Throwable $throwable) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('red_addon_component_editor_delete_parent')) {
    function red_addon_component_editor_delete_parent(
        $connection,
        $componentId,
        $contentRecordId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                "DELETE FROM RED_Articles
                 WHERE RecordID=? AND Component=?
                   AND Active='N' AND PagePosition=0"
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'is',
                $contentRecordId,
                $componentId
            );
            $deleted = mysqli_stmt_execute($statement)
                && mysqli_stmt_affected_rows($statement) === 1;
            mysqli_stmt_close($statement);
            return $deleted;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_delete_revision_matches')) {
    function red_addon_component_editor_delete_revision_matches(
        $connection,
        $table,
        $revisionId,
        $contentRecordId,
        $stateHash
    ) {
        $idColumn = $table === 'RED_Content_Revisions'
            ? 'RevisionID'
            : ($table === 'RED_Addon_Component_Revisions'
                ? 'RevisionID'
                : '');
        $hashColumn = $table === 'RED_Content_Revisions'
            ? 'SnapshotHash'
            : ($table === 'RED_Addon_Component_Revisions'
                ? 'StateHash'
                : '');
        if ($idColumn === '' || $hashColumn === '') {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT Operation, `$hashColumn` AS StateHash
                 FROM `$table`
                 WHERE `$idColumn`=? AND ContentRecordID=? LIMIT 1"
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'ii',
                $revisionId,
                $contentRecordId
            );
            mysqli_stmt_execute($statement);
            $queryResult = mysqli_stmt_get_result($statement);
            $row = $queryResult ? mysqli_fetch_assoc($queryResult) : null;
            if ($queryResult) {
                mysqli_free_result($queryResult);
            }
            mysqli_stmt_close($statement);
            return is_array($row)
                && ($row['Operation'] ?? '') === 'delete'
                && is_string($row['StateHash'] ?? null)
                && hash_equals($stateHash, $row['StateHash']);
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_delete_values')) {
    function red_addon_component_editor_delete_values(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $expectedParentStateHash,
        $expectedPackageStateHash,
        $expectedPlanHash
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
        $result = red_addon_component_editor_deletion_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $adminRecordId === false
                ? 'invalid_actor'
                : ($contentRecordId === false
                    ? 'invalid_content_record'
                    : 'invalid_plan_hash')
        );
        if ($adminRecordId === false
            || $contentRecordId === false
            || !red_addon_component_editor_state_hash_valid(
                $expectedParentStateHash
            )
            || !red_addon_component_editor_state_hash_valid(
                $expectedPackageStateHash
            )
            || !red_addon_component_editor_create_hash_valid(
                $expectedPlanHash
            )
        ) {
            return $result;
        }
        $result['parentStateHash'] = $expectedParentStateHash;
        $result['packageStateHash'] = $expectedPackageStateHash;
        $result['planHash'] = $expectedPlanHash;
        if (red_addon_component_editor_create_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }

        $preflight = red_addon_component_editor_delete_preflight(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            $expectedParentStateHash,
            $expectedPackageStateHash
        );
        $result['package'] = (string) ($preflight['package'] ?? '');
        $result['viewPermission'] = (string) (
            $preflight['viewPermission'] ?? ''
        );
        $result['deletePermission'] = (string) (
            $preflight['deletePermission'] ?? ''
        );
        if (empty($preflight['ready'])
            || !hash_equals(
                (string) ($preflight['planHash'] ?? ''),
                $expectedPlanHash
            )
        ) {
            $result['reason'] = empty($preflight['ready'])
                ? (string) ($preflight['reason'] ?? 'preflight_failed')
                : 'stale_plan';
            return $result;
        }
        $packageId = (string) $preflight['package'];
        $deleter = red_addon_runtime_handler(
            'componentDataDeleters',
            $componentId
        );
        $loader = red_addon_runtime_handler(
            'componentDataLoaders',
            $componentId
        );
        if (!is_callable($deleter) || !is_callable($loader)) {
            $result['reason'] = 'runtime_binding_unavailable';
            return $result;
        }
        if (!red_addon_lifecycle_lock($connection)) {
            $result['reason'] = 'lifecycle_lock_failed';
            return $result;
        }

        $transactionReason = 'transaction_failed';
        $lockedResult = null;
        try {
            $lockedResult = red_admin_with_theme_contract_lock(
                $connection,
                function () use (
                    $connection,
                    $manifest,
                    $componentId,
                    $contentRecordId,
                    $adminRecordId,
                    $expectedParentStateHash,
                    $expectedPackageStateHash,
                    $expectedPlanHash,
                    $packageId,
                    $deleter,
                    $loader,
                    &$transactionReason
                ) {
                    if (!mysqli_begin_transaction($connection)) {
                        return null;
                    }
                    try {
                        if (!red_addon_component_editor_create_lock_installation(
                            $connection,
                            $packageId
                        ) || !red_addon_component_editor_lock_binding(
                            $connection,
                            $packageId,
                            $componentId,
                            $contentRecordId
                        )) {
                            $transactionReason = 'binding_unavailable';
                            throw new RuntimeException($transactionReason);
                        }
                        $lockedPlan = red_addon_component_editor_delete_preflight(
                            $connection,
                            $manifest,
                            $componentId,
                            $contentRecordId,
                            $adminRecordId,
                            $expectedParentStateHash,
                            $expectedPackageStateHash
                        );
                        if (empty($lockedPlan['ready'])
                            || !hash_equals(
                                (string) ($lockedPlan['planHash'] ?? ''),
                                $expectedPlanHash
                            )
                        ) {
                            $transactionReason = empty($lockedPlan['ready'])
                                ? (string) (
                                    $lockedPlan['reason'] ?? 'preflight_failed'
                                )
                                : 'stale_plan';
                            throw new RuntimeException($transactionReason);
                        }
                        $values = red_addon_component_editor_create_load_values(
                            $loader,
                            $connection,
                            $manifest,
                            $componentId,
                            $contentRecordId
                        );
                        $stateHash = is_array($values)
                            ? red_addon_component_editor_data_hash(
                                $packageId,
                                $componentId,
                                $contentRecordId,
                                $values
                            )
                            : '';
                        if (!is_array($values)
                            || !hash_equals(
                                $expectedPackageStateHash,
                                $stateHash
                            )
                        ) {
                            $transactionReason = 'stale_package_state';
                            throw new RuntimeException($transactionReason);
                        }
                        $packageRevision = red_addon_component_revision_record(
                            $connection,
                            $packageId,
                            $componentId,
                            $contentRecordId,
                            $adminRecordId,
                            $values,
                            'delete',
                            null,
                            true
                        );
                        if (empty($packageRevision['recorded'])
                            || empty($packageRevision['inserted'])
                        ) {
                            $transactionReason = 'package_revision_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $parentRevisionId =
                            red_addon_component_editor_delete_parent_revision(
                                $connection,
                                $contentRecordId,
                                $adminRecordId
                            );
                        if ($parentRevisionId < 1) {
                            $transactionReason = 'parent_revision_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $context = [
                            'component' => $componentId,
                            'contentRecordId' => $contentRecordId,
                            'actorRecordId' => $adminRecordId,
                            'planHash' => $expectedPlanHash,
                        ];
                        if (!red_addon_component_editor_delete_invoke_deleter(
                            $deleter,
                            $connection,
                            $context
                        )) {
                            $transactionReason = 'deleter_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            $transactionReason = 'transaction_lost';
                            throw new RuntimeException($transactionReason);
                        }
                        $tables = $lockedPlan['transactionTables'];
                        if (!red_addon_component_editor_delete_package_absent(
                            $connection,
                            $tables,
                            $contentRecordId
                        )) {
                            $transactionReason = 'package_postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_seo_delete_metadata(
                            $connection,
                            'article',
                            $contentRecordId
                        )) {
                            $transactionReason = 'seo_delete_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_delete_parent(
                            $connection,
                            $componentId,
                            $contentRecordId
                        ) || is_array(red_admin_article_full_record(
                            $connection,
                            $contentRecordId
                        ))) {
                            $transactionReason = 'parent_delete_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $packageRevisionId = (int) (
                            $packageRevision['revisionId'] ?? 0
                        );
                        if (!red_addon_component_editor_delete_revision_matches(
                            $connection,
                            'RED_Addon_Component_Revisions',
                            $packageRevisionId,
                            $contentRecordId,
                            $expectedPackageStateHash
                        ) || !red_addon_component_editor_delete_revision_matches(
                            $connection,
                            'RED_Content_Revisions',
                            $parentRevisionId,
                            $contentRecordId,
                            $expectedParentStateHash
                        )) {
                            $transactionReason = 'revision_postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        ) || !mysqli_commit($connection)) {
                            $transactionReason = 'transaction_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        return [
                            'parentRevisionId' => $parentRevisionId,
                            'packageRevisionId' => $packageRevisionId,
                        ];
                    } catch (Throwable $throwable) {
                        if (red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            mysqli_rollback($connection);
                        }
                        return null;
                    }
                }
            );
        } finally {
            red_addon_lifecycle_unlock($connection);
        }
        if (!is_array($lockedResult)) {
            $result['reason'] = $lockedResult === false
                && $transactionReason === 'transaction_failed'
                ? 'theme_lock_failed'
                : $transactionReason;
            return $result;
        }
        $result['deleted'] = true;
        $result['parentRevisionId'] = $lockedResult['parentRevisionId'];
        $result['packageRevisionId'] = $lockedResult['packageRevisionId'];
        $result['reason'] = 'deleted';
        return $result;
    }
}
