<?php
/**
 * Durable checkpoint storage for future component destination coordination.
 *
 * This internal boundary reserves an exact read-only provisioning plan and
 * advances only its bounded stage/hash evidence. It does not create routes,
 * components, placements, revisions, audits, or search documents.
 */

require_once __DIR__ . '/addon_component_destination_preflight_helpers.php';

if (!function_exists('red_addon_component_destination_execution_stages')) {
    function red_addon_component_destination_execution_stages()
    {
        return [
            'planned',
            'route_created',
            'component_created',
            'component_published',
            'completed',
        ];
    }
}

if (!function_exists('red_addon_component_destination_execution_result')) {
    function red_addon_component_destination_execution_result($reason)
    {
        return [
            'reserved' => false,
            'checkpointed' => false,
            'resumed' => false,
            'package' => '',
            'component' => '',
            'planSha256' => '',
            'packagePlanSha256' => '',
            'routeRecordId' => 0,
            'componentRecordId' => 0,
            'actorRecordId' => 0,
            'stage' => '',
            'routeStateSha256' => '',
            'componentStateSha256' => '',
            'placementStateSha256' => '',
            'searchNotification' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_destination_execution_storage_available')) {
    function red_addon_component_destination_execution_storage_available(
        $connection
    ) {
        if (!($connection instanceof mysqli)
            || !red_addon_install_storage_available($connection)
        ) {
            return false;
        }
        try {
            $query = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME=
                         'RED_Addon_Component_Destination_Executions'),
                    (SELECT COUNT(*)=14
                       AND SUM(COLUMN_NAME='PackageID'
                         AND COLUMN_TYPE='varchar(127)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='PlanSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ComponentID'
                         AND COLUMN_TYPE='varchar(160)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='PackagePlanSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='RouteRecordID'
                         AND COLUMN_TYPE='int unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ComponentRecordID'
                         AND COLUMN_TYPE='int unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ActorAdminRecordID'
                         AND COLUMN_TYPE='int unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='Stage'
                         AND COLUMN_TYPE='varchar(32)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='RouteStateSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='YES')=1
                       AND SUM(COLUMN_NAME='ComponentStateSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='YES')=1
                       AND SUM(COLUMN_NAME='PlacementStateSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='YES')=1
                       AND SUM(COLUMN_NAME='SearchNotification'
                         AND COLUMN_TYPE='varchar(16)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='CreatedAt'
                         AND DATA_TYPE='timestamp'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='UpdatedAt'
                         AND DATA_TYPE='timestamp'
                         AND IS_NULLABLE='NO')=1
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME=
                         'RED_Addon_Component_Destination_Executions'),
                    (SELECT GROUP_CONCAT(COLUMN_NAME
                       ORDER BY SEQ_IN_INDEX SEPARATOR ',')
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME=
                         'RED_Addon_Component_Destination_Executions'
                       AND INDEX_NAME='PRIMARY'),
                    (SELECT GROUP_CONCAT(COLUMN_NAME
                       ORDER BY SEQ_IN_INDEX SEPARATOR ',')
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME=
                         'RED_Addon_Component_Destination_Executions'
                       AND INDEX_NAME='idx_red_addon_destination_route'),
                    (SELECT GROUP_CONCAT(COLUMN_NAME
                       ORDER BY SEQ_IN_INDEX SEPARATOR ',')
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME=
                         'RED_Addon_Component_Destination_Executions'
                       AND INDEX_NAME='idx_red_addon_destination_component'),
                    (SELECT GROUP_CONCAT(COLUMN_NAME
                       ORDER BY SEQ_IN_INDEX SEPARATOR ',')
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME=
                         'RED_Addon_Component_Destination_Executions'
                       AND INDEX_NAME='idx_red_addon_destination_stage'),
                    (SELECT COUNT(*)=1
                       AND SUM(CONSTRAINT_NAME=
                         'fk_red_addon_destination_installation'
                         AND TABLE_NAME=
                           'RED_Addon_Component_Destination_Executions'
                         AND REFERENCED_TABLE_NAME='RED_Addon_Installations'
                         AND DELETE_RULE='RESTRICT'
                         AND UPDATE_RULE='RESTRICT')=1
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND CONSTRAINT_NAME=
                         'fk_red_addon_destination_installation')
                 ) AS StorageState"
            );
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            return (string) ($row['StorageState'] ?? '')
                === '1:1:PackageID,PlanSHA256:RouteRecordID:ComponentRecordID:PackageID,Stage,UpdatedAt:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_destination_execution_row')) {
    function red_addon_component_destination_execution_row(array $row)
    {
        $stage = (string) ($row['Stage'] ?? '');
        $search = (string) ($row['SearchNotification'] ?? '');
        $routeState = $row['RouteStateSHA256'] ?? null;
        $componentState = $row['ComponentStateSHA256'] ?? null;
        $placementState = $row['PlacementStateSHA256'] ?? null;
        if (!red_addon_valid_package_id($row['PackageID'] ?? null)
            || !red_addon_valid_capability($row['ComponentID'] ?? null)
            || !red_addon_valid_sha256($row['PlanSHA256'] ?? null)
            || !red_addon_valid_sha256($row['PackagePlanSHA256'] ?? null)
            || !in_array(
                $stage,
                red_addon_component_destination_execution_stages(),
                true
            )
            || !in_array($search, ['pending', 'succeeded', 'failed'], true)
            || ($routeState !== null && !red_addon_valid_sha256($routeState))
            || ($componentState !== null
                && !red_addon_valid_sha256($componentState))
            || ($placementState !== null
                && !red_addon_valid_sha256($placementState))
        ) {
            return null;
        }
        $stageShapeValid = match ($stage) {
            'planned' => $routeState === null
                && $componentState === null
                && $placementState === null
                && $search === 'pending',
            'route_created' => is_string($routeState)
                && $componentState === null
                && $placementState === null
                && $search === 'pending',
            'component_created' => is_string($routeState)
                && is_string($componentState)
                && $placementState === null
                && $search === 'pending',
            'component_published' => is_string($routeState)
                && is_string($componentState)
                && is_string($placementState)
                && $search === 'pending',
            'completed' => is_string($routeState)
                && is_string($componentState)
                && is_string($placementState)
                && in_array($search, ['succeeded', 'failed'], true),
            default => false,
        };
        if (!$stageShapeValid) {
            return null;
        }
        $result = red_addon_component_destination_execution_result('loaded');
        $result['package'] = $row['PackageID'];
        $result['component'] = $row['ComponentID'];
        $result['planSha256'] = $row['PlanSHA256'];
        $result['packagePlanSha256'] = $row['PackagePlanSHA256'];
        $result['routeRecordId'] = (int) ($row['RouteRecordID'] ?? 0);
        $result['componentRecordId'] = (int) ($row['ComponentRecordID'] ?? 0);
        $result['actorRecordId'] = (int) ($row['ActorAdminRecordID'] ?? 0);
        $result['stage'] = $stage;
        $result['routeStateSha256'] = is_string($routeState)
            ? $routeState
            : '';
        $result['componentStateSha256'] = is_string($componentState)
            ? $componentState
            : '';
        $result['placementStateSha256'] = is_string($placementState)
            ? $placementState
            : '';
        $result['searchNotification'] = $search;
        if ($result['routeRecordId'] < 1
            || $result['componentRecordId'] < 1
            || $result['routeRecordId'] === $result['componentRecordId']
            || $result['actorRecordId'] < 1
        ) {
            return null;
        }
        return $result;
    }
}

if (!function_exists('red_addon_component_destination_execution_load')) {
    function red_addon_component_destination_execution_load(
        $connection,
        $packageId,
        $planSha256,
        $forUpdate = false
    ) {
        if (!($connection instanceof mysqli)
            || !red_addon_valid_package_id($packageId)
            || !red_addon_valid_sha256($planSha256)
            || !is_bool($forUpdate)
        ) {
            return null;
        }
        $sql = 'SELECT PackageID, PlanSHA256, ComponentID,
                       PackagePlanSHA256, RouteRecordID, ComponentRecordID,
                       ActorAdminRecordID, Stage, RouteStateSHA256,
                       ComponentStateSHA256, PlacementStateSHA256,
                       SearchNotification
                FROM RED_Addon_Component_Destination_Executions
                WHERE PackageID=? AND PlanSHA256=? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        try {
            $statement = mysqli_prepare($connection, $sql);
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param(
                $statement,
                'ss',
                $packageId,
                $planSha256
            );
            $executed = mysqli_stmt_execute($statement);
            $queryResult = $executed
                ? mysqli_stmt_get_result($statement)
                : null;
            $row = $queryResult ? mysqli_fetch_assoc($queryResult) : null;
            if ($queryResult) {
                mysqli_free_result($queryResult);
            }
            mysqli_stmt_close($statement);
            return is_array($row)
                ? red_addon_component_destination_execution_row($row)
                : false;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_component_destination_execution_matches_plan')) {
    function red_addon_component_destination_execution_matches_plan(
        array $row,
        array $plan
    ) {
        return hash_equals($plan['package'], $row['package'])
            && hash_equals($plan['component'], $row['component'])
            && hash_equals($plan['planHash'], $row['planSha256'])
            && hash_equals(
                $plan['packagePlanSha256'],
                $row['packagePlanSha256']
            )
            && $plan['routeRecordId'] === $row['routeRecordId']
            && $plan['componentRecordId'] === $row['componentRecordId']
            && $plan['actorRecordId'] === $row['actorRecordId'];
    }
}

if (!function_exists('red_addon_component_destination_execution_reserve')) {
    function red_addon_component_destination_execution_reserve(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        $request,
        $expectedPlanSha256
    ) {
        $result = red_addon_component_destination_execution_result(
            'invalid_request'
        );
        if (!($connection instanceof mysqli)
            || !red_addon_valid_sha256($expectedPlanSha256)
            || !red_addon_component_destination_execution_storage_available(
                $connection
            )
            || red_addon_component_editor_create_transaction_active($connection)
        ) {
            $result['reason'] = !red_addon_valid_sha256($expectedPlanSha256)
                ? 'invalid_plan_hash'
                : (!red_addon_component_destination_execution_storage_available(
                    $connection
                ) ? 'storage_unavailable' : 'transaction_already_active');
            return $result;
        }
        $preflight = red_addon_component_destination_preflight(
            $connection,
            $manifest,
            $componentId,
            $adminRecordId,
            $request
        );
        if (empty($preflight['ready'])
            || !hash_equals(
                (string) ($preflight['planHash'] ?? ''),
                $expectedPlanSha256
            )
        ) {
            $result['reason'] = empty($preflight['ready'])
                ? (string) ($preflight['reason'] ?? 'preflight_failed')
                : 'stale_plan';
            return $result;
        }
        $plan = [
            'package' => (string) $preflight['package'],
            'component' => (string) $preflight['component'],
            'planHash' => $expectedPlanSha256,
            'packagePlanSha256' =>
                (string) $preflight['packagePlanSha256'],
            'routeRecordId' => (int) $preflight['routeRecordId'],
            'componentRecordId' => (int) $preflight['componentRecordId'],
            'actorRecordId' => (int) $preflight['actorRecordId'],
        ];
        if (!red_admin_transaction_tables_supported(
            $connection,
            [
                'RED_Addon_Installations',
                'RED_Addon_Component_Destination_Executions',
            ]
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        if (!red_addon_lifecycle_lock($connection)) {
            $result['reason'] = 'lifecycle_lock_failed';
            return $result;
        }

        $transactionReason = 'transaction_failed';
        $locked = null;
        try {
            $locked = red_admin_with_theme_contract_lock(
                $connection,
                function () use (
                    $connection,
                    $manifest,
                    $componentId,
                    $adminRecordId,
                    $request,
                    $expectedPlanSha256,
                    $plan,
                    &$transactionReason
                ) {
                    if (!mysqli_begin_transaction($connection)) {
                        return null;
                    }
                    try {
                        if (!red_addon_component_editor_create_lock_installation(
                            $connection,
                            $plan['package']
                        )) {
                            $transactionReason = 'package_not_enabled';
                            throw new RuntimeException($transactionReason);
                        }
                        $lockedPlan = red_addon_component_destination_preflight(
                            $connection,
                            $manifest,
                            $componentId,
                            $adminRecordId,
                            $request
                        );
                        if (empty($lockedPlan['ready'])
                            || !hash_equals(
                                (string) ($lockedPlan['planHash'] ?? ''),
                                $expectedPlanSha256
                            )
                        ) {
                            $transactionReason = empty($lockedPlan['ready'])
                                ? (string) (
                                    $lockedPlan['reason'] ?? 'preflight_failed'
                                )
                                : 'stale_plan';
                            throw new RuntimeException($transactionReason);
                        }
                        $existing =
                            red_addon_component_destination_execution_load(
                                $connection,
                                $plan['package'],
                                $expectedPlanSha256,
                                true
                            );
                        if (is_array($existing)) {
                            if (!red_addon_component_destination_execution_matches_plan(
                                $existing,
                                $plan
                            )) {
                                $transactionReason = 'execution_conflict';
                                throw new RuntimeException($transactionReason);
                            }
                            if (!mysqli_commit($connection)) {
                                $transactionReason = 'transaction_failed';
                                throw new RuntimeException($transactionReason);
                            }
                            return ['row' => $existing, 'resumed' => true];
                        }
                        if ($existing === null) {
                            $transactionReason = 'storage_unavailable';
                            throw new RuntimeException($transactionReason);
                        }
                        $stage = 'planned';
                        $search = 'pending';
                        $statement = mysqli_prepare(
                            $connection,
                            'INSERT INTO
                               RED_Addon_Component_Destination_Executions (
                                 PackageID, PlanSHA256, ComponentID,
                                 PackagePlanSHA256, RouteRecordID,
                                 ComponentRecordID, ActorAdminRecordID, Stage,
                                 SearchNotification
                               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        if (!$statement) {
                            $transactionReason = 'reservation_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        mysqli_stmt_bind_param(
                            $statement,
                            'ssssiiiss',
                            $plan['package'],
                            $plan['planHash'],
                            $plan['component'],
                            $plan['packagePlanSha256'],
                            $plan['routeRecordId'],
                            $plan['componentRecordId'],
                            $plan['actorRecordId'],
                            $stage,
                            $search
                        );
                        $inserted = mysqli_stmt_execute($statement);
                        $errno = mysqli_stmt_errno($statement);
                        mysqli_stmt_close($statement);
                        if (!$inserted) {
                            $transactionReason = $errno === 1062
                                ? 'record_id_reserved'
                                : 'reservation_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $row = red_addon_component_destination_execution_load(
                            $connection,
                            $plan['package'],
                            $plan['planHash'],
                            true
                        );
                        if (!is_array($row)
                            || !red_addon_component_destination_execution_matches_plan(
                                $row,
                                $plan
                            )
                            || !mysqli_commit($connection)
                        ) {
                            $transactionReason = 'reservation_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        return ['row' => $row, 'resumed' => false];
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
        if (!is_array($locked) || !is_array($locked['row'] ?? null)) {
            $result['reason'] = $locked === false
                && $transactionReason === 'transaction_failed'
                    ? 'theme_lock_failed'
                    : $transactionReason;
            return $result;
        }
        $result = array_merge($locked['row'], [
            'reserved' => true,
            'resumed' => !empty($locked['resumed']),
            'reason' => !empty($locked['resumed']) ? 'resumed' : 'reserved',
        ]);
        return $result;
    }
}

if (!function_exists('red_addon_component_destination_execution_transition')) {
    function red_addon_component_destination_execution_transition(
        $expectedStage,
        $nextStage,
        $stateSha256,
        $searchNotification
    ) {
        $transitions = [
            'planned' => ['route_created', 'RouteStateSHA256'],
            'route_created' => ['component_created', 'ComponentStateSHA256'],
            'component_created' => [
                'component_published',
                'PlacementStateSHA256',
            ],
            'component_published' => ['completed', ''],
        ];
        if (!is_string($expectedStage)
            || !is_string($nextStage)
            || !isset($transitions[$expectedStage])
            || $transitions[$expectedStage][0] !== $nextStage
        ) {
            return null;
        }
        if ($nextStage === 'completed') {
            return $stateSha256 === ''
                && in_array(
                    $searchNotification,
                    ['succeeded', 'failed'],
                    true
                )
                    ? ['column' => '', 'search' => $searchNotification]
                    : null;
        }
        return red_addon_valid_sha256($stateSha256)
            && $searchNotification === 'pending'
                ? [
                    'column' => $transitions[$expectedStage][1],
                    'search' => 'pending',
                ]
                : null;
    }
}

if (!function_exists('red_addon_component_destination_execution_checkpoint')) {
    function red_addon_component_destination_execution_checkpoint(
        $connection,
        $packageId,
        $planSha256,
        $actorRecordId,
        $expectedStage,
        $nextStage,
        $stateSha256 = '',
        $searchNotification = 'pending'
    ) {
        $result = red_addon_component_destination_execution_result(
            'invalid_request'
        );
        $actorRecordId = filter_var(
            $actorRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
        );
        $transition = red_addon_component_destination_execution_transition(
            $expectedStage,
            $nextStage,
            $stateSha256,
            $searchNotification
        );
        if (!($connection instanceof mysqli)
            || !red_addon_valid_package_id($packageId)
            || !red_addon_valid_sha256($planSha256)
            || $actorRecordId === false
            || !is_array($transition)
        ) {
            return $result;
        }
        if (!red_addon_component_destination_execution_storage_available(
            $connection
        )) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        if (red_addon_component_editor_create_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        if (!red_addon_lifecycle_lock($connection)) {
            $result['reason'] = 'lifecycle_lock_failed';
            return $result;
        }

        $transactionReason = 'transaction_failed';
        $checkpoint = null;
        try {
            if (!mysqli_begin_transaction($connection)) {
                return $result;
            }
            try {
                if (!red_addon_component_editor_create_lock_installation(
                    $connection,
                    $packageId
                )) {
                    $transactionReason = 'package_not_enabled';
                    throw new RuntimeException($transactionReason);
                }
                $row = red_addon_component_destination_execution_load(
                    $connection,
                    $packageId,
                    $planSha256,
                    true
                );
                if (!is_array($row)) {
                    $transactionReason = $row === false
                        ? 'execution_unavailable'
                        : 'storage_unavailable';
                    throw new RuntimeException($transactionReason);
                }
                if ($row['actorRecordId'] !== $actorRecordId) {
                    $transactionReason = 'actor_mismatch';
                    throw new RuntimeException($transactionReason);
                }
                $currentHash = $transition['column'] === ''
                    ? ''
                    : match ($transition['column']) {
                        'RouteStateSHA256' => $row['routeStateSha256'],
                        'ComponentStateSHA256' =>
                            $row['componentStateSha256'],
                        'PlacementStateSHA256' =>
                            $row['placementStateSha256'],
                        default => '',
                    };
                if ($row['stage'] === $nextStage
                    && ($transition['column'] === ''
                        ? $row['searchNotification'] === $searchNotification
                        : hash_equals($currentHash, $stateSha256))
                ) {
                    if (!mysqli_commit($connection)) {
                        $transactionReason = 'transaction_failed';
                        throw new RuntimeException($transactionReason);
                    }
                    $checkpoint = ['row' => $row, 'resumed' => true];
                } elseif ($row['stage'] !== $expectedStage) {
                    $transactionReason = 'stale_stage';
                    throw new RuntimeException($transactionReason);
                } else {
                    $column = $transition['column'];
                    if ($column === '') {
                        $statement = mysqli_prepare(
                            $connection,
                            'UPDATE RED_Addon_Component_Destination_Executions
                             SET Stage=?, SearchNotification=?
                             WHERE PackageID=? AND PlanSHA256=? AND Stage=?
                               AND SearchNotification=\'pending\''
                        );
                        if ($statement) {
                            mysqli_stmt_bind_param(
                                $statement,
                                'sssss',
                                $nextStage,
                                $searchNotification,
                                $packageId,
                                $planSha256,
                                $expectedStage
                            );
                        }
                    } else {
                        $statement = mysqli_prepare(
                            $connection,
                            'UPDATE RED_Addon_Component_Destination_Executions
                             SET Stage=?, `' . $column . '`=?
                             WHERE PackageID=? AND PlanSHA256=? AND Stage=?
                               AND `' . $column . '` IS NULL
                               AND SearchNotification=\'pending\''
                        );
                        if ($statement) {
                            mysqli_stmt_bind_param(
                                $statement,
                                'sssss',
                                $nextStage,
                                $stateSha256,
                                $packageId,
                                $planSha256,
                                $expectedStage
                            );
                        }
                    }
                    if (!$statement) {
                        $transactionReason = 'checkpoint_failed';
                        throw new RuntimeException($transactionReason);
                    }
                    $transactionReason = 'checkpoint_failed';
                    $updated = mysqli_stmt_execute($statement)
                        && mysqli_stmt_affected_rows($statement) === 1;
                    mysqli_stmt_close($statement);
                    $after = $updated
                        ? red_addon_component_destination_execution_load(
                            $connection,
                            $packageId,
                            $planSha256,
                            true
                        )
                        : null;
                    if (!is_array($after)
                        || $after['stage'] !== $nextStage
                        || !mysqli_commit($connection)
                    ) {
                        $transactionReason = 'checkpoint_failed';
                        throw new RuntimeException($transactionReason);
                    }
                    $checkpoint = ['row' => $after, 'resumed' => false];
                }
            } catch (Throwable $throwable) {
                if (red_addon_component_editor_create_transaction_active(
                    $connection
                )) {
                    mysqli_rollback($connection);
                }
            }
        } finally {
            red_addon_lifecycle_unlock($connection);
        }
        if (!is_array($checkpoint)
            || !is_array($checkpoint['row'] ?? null)
        ) {
            $result['reason'] = $transactionReason;
            return $result;
        }
        return array_merge($checkpoint['row'], [
            'checkpointed' => true,
            'resumed' => !empty($checkpoint['resumed']),
            'reason' => !empty($checkpoint['resumed'])
                ? 'resumed'
                : 'checkpointed',
        ]);
    }
}

?>
