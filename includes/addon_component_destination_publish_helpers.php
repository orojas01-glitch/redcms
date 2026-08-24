<?php
/**
 * Restartable public placement for add-on component destinations.
 *
 * The existing publisher owns the atomic placement/revision/audit commit.
 * This coordinator follows it with a separately locked reconciliation and
 * checkpoint transaction. A retry across that commit gap proves the exact
 * route, component, package, revisions, audit, and original plan before
 * advancing the ledger without publishing twice.
 */

require_once __DIR__ . '/addon_component_destination_component_helpers.php';

if (!function_exists('red_addon_component_destination_publish_result')) {
    function red_addon_component_destination_publish_result($reason)
    {
        return [
            'published' => false,
            'resumed' => false,
            'previewInvoked' => false,
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
            'revisionId' => 0,
            'revisionNumber' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_destination_publish_route_values')) {
    function red_addon_component_destination_publish_route_values(
        $connection,
        array $request,
        array $execution
    ) {
        $home = red_addon_component_editor_publish_target(
            $connection,
            0,
            $request['language']
        );
        return is_array($home) ? [
            'RecordID' => $execution['routeRecordId'],
            'Title' => $request['title'],
            'Component' => 'Article',
            'Alias' => $request['alias'],
            'Sections' => $home['sections'],
            'Categories' => '',
            'SubCategories' => '',
            'Layout' => $request['layout'],
            'PagePosition' => (int) $request['routePagePosition'],
            'PagePositionOrder' => (int) $request['routePagePositionOrder'],
            'Active' => 'Y',
            'Language' => $request['language'],
        ] : null;
    }
}

if (!function_exists('red_addon_component_destination_publish_placement')) {
    function red_addon_component_destination_publish_placement(
        $connection,
        array $request,
        array $execution
    ) {
        $target = red_addon_component_editor_publish_target(
            $connection,
            $execution['routeRecordId']
        );
        if (!is_array($target)
            || !hash_equals($request['language'], $target['language'])
        ) {
            return null;
        }
        return [
            'target' => $target,
            'values' => [
                'Sections' => $target['sections'],
                'Categories' => '',
                'SubCategories' => '',
                'Article' => $request['alias'],
                'PagePosition' => (int) $request['componentPagePosition'],
                'PagePositionOrder' =>
                    (int) $request['componentPagePositionOrder'],
                'Active' => 'Y',
            ],
        ];
    }
}

if (!function_exists('red_addon_component_destination_publish_revision')) {
    function red_addon_component_destination_publish_revision(
        $connection,
        $contentRecordId,
        $revisionNumber
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RevisionID, RevisionNumber, Operation,
                        ActorAdminRecordID, SnapshotHash
                 FROM RED_Content_Revisions
                 WHERE ContentRecordID=? AND RevisionNumber=? LIMIT 1'
            );
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param(
                $statement,
                'ii',
                $contentRecordId,
                $revisionNumber
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return null;
            }
            $queryResult = mysqli_stmt_get_result($statement);
            $row = $queryResult ? mysqli_fetch_assoc($queryResult) : null;
            if ($queryResult) {
                mysqli_free_result($queryResult);
            }
            mysqli_stmt_close($statement);
            return is_array($row) ? $row : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_component_destination_publish_audit_count')) {
    function red_addon_component_destination_publish_audit_count(
        $connection,
        $contentRecordId,
        $actorRecordId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE EventName='component.public_placed'
                   AND TargetType='component'
                   AND TargetRecordID=? AND ActorAdminRecordID=?"
            );
            if (!$statement) {
                return -1;
            }
            $count = -1;
            mysqli_stmt_bind_param(
                $statement,
                'ii',
                $contentRecordId,
                $actorRecordId
            );
            mysqli_stmt_bind_result($statement, $count);
            $loaded = mysqli_stmt_execute($statement)
                && mysqli_stmt_fetch($statement) === true;
            mysqli_stmt_close($statement);
            return $loaded ? (int) $count : -1;
        } catch (Throwable $throwable) {
            return -1;
        }
    }
}

if (!function_exists('red_addon_component_destination_publish_state_hash')) {
    function red_addon_component_destination_publish_state_hash(
        $parentStateSha256,
        $packageStateSha256,
        $targetStateSha256,
        array $placementValues
    ) {
        if (!red_addon_valid_sha256($parentStateSha256)
            || !red_addon_valid_sha256($packageStateSha256)
            || !red_addon_valid_sha256($targetStateSha256)
        ) {
            return '';
        }
        return red_addon_component_editor_publish_hash([
            'schema' => 1,
            'parentStateSha256' => $parentStateSha256,
            'packageStateSha256' => $packageStateSha256,
            'targetStateSha256' => $targetStateSha256,
            'placementValues' => $placementValues,
        ]);
    }
}

if (!function_exists('red_addon_component_destination_publish_reconcile')) {
    function red_addon_component_destination_publish_reconcile(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        array $request,
        array $execution
    ) {
        $metadata = [
            'title' => $request['title'],
            'layout' => $request['layout'],
            'language' => $request['language'],
        ];
        $createPlan = red_addon_component_destination_component_create_plan(
            $connection,
            $manifest,
            $componentId,
            $execution['componentRecordId'],
            $adminRecordId,
            $metadata,
            $request['componentValues']
        );
        $placement = red_addon_component_destination_publish_placement(
            $connection,
            $request,
            $execution
        );
        if (!is_array($createPlan) || !is_array($placement)) {
            return null;
        }
        $expectedParent = $createPlan['parentValues'];
        foreach ($placement['values'] as $fieldName => $value) {
            $expectedParent[$fieldName] = $value;
        }
        $parent = red_addon_component_editor_publish_parent_row(
            $connection,
            $execution['componentRecordId']
        );
        $package = red_addon_component_editor_load_values(
            $connection,
            $manifest,
            $componentId,
            $execution['componentRecordId'],
            $adminRecordId
        );
        $createRevision = red_addon_component_destination_publish_revision(
            $connection,
            $execution['componentRecordId'],
            1
        );
        $moveRevision = red_addon_component_destination_publish_revision(
            $connection,
            $execution['componentRecordId'],
            2
        );
        $packageRevision = red_addon_component_revision_latest(
            $connection,
            $execution['componentRecordId']
        );
        $parentStateHash = red_admin_content_revision_current_hash(
            $connection,
            $execution['componentRecordId']
        );
        if ($parent !== $expectedParent
            || empty($package['loaded'])
            || $package['values'] !== $createPlan['values']
            || !is_array($createRevision)
            || (string) ($createRevision['Operation'] ?? '') !== 'create'
            || (int) ($createRevision['ActorAdminRecordID'] ?? 0)
                !== $execution['actorRecordId']
            || !is_array($moveRevision)
            || (string) ($moveRevision['Operation'] ?? '') !== 'move'
            || (int) ($moveRevision['ActorAdminRecordID'] ?? 0)
                !== $execution['actorRecordId']
            || !hash_equals(
                $parentStateHash,
                (string) ($moveRevision['SnapshotHash'] ?? '')
            )
            || !is_array($packageRevision)
            || (int) ($packageRevision['RevisionNumber'] ?? 0) !== 1
            || (string) ($packageRevision['Operation'] ?? '') !== 'baseline'
            || (int) ($packageRevision['ActorAdminRecordID'] ?? 0)
                !== $execution['actorRecordId']
            || !hash_equals(
                $execution['package'],
                (string) ($packageRevision['PackageID'] ?? '')
            )
            || !hash_equals(
                $componentId,
                (string) ($packageRevision['ComponentID'] ?? '')
            )
            || !hash_equals(
                (string) $package['stateHash'],
                (string) ($packageRevision['StateHash'] ?? '')
            )
            || !hash_equals(
                $execution['componentStateSha256'],
                red_addon_component_destination_component_state_hash(
                    (string) ($createRevision['SnapshotHash'] ?? ''),
                    (string) $package['stateHash']
                )
            )
            || red_addon_component_destination_publish_audit_count(
                $connection,
                $execution['componentRecordId'],
                $execution['actorRecordId']
            ) !== 1
        ) {
            return null;
        }
        $stateHash = red_addon_component_destination_publish_state_hash(
            $parentStateHash,
            (string) $package['stateHash'],
            $placement['target']['stateHash'],
            $placement['values']
        );
        return red_addon_valid_sha256($stateHash) ? [
            'stateHash' => $stateHash,
            'parentStateHash' => $parentStateHash,
            'packageStateHash' => (string) $package['stateHash'],
            'targetStateHash' => $placement['target']['stateHash'],
            'placementValues' => $placement['values'],
            'revisionId' => (int) ($moveRevision['RevisionID'] ?? 0),
            'revisionNumber' => (int) ($moveRevision['RevisionNumber'] ?? 0),
        ] : null;
    }
}

if (!function_exists('red_addon_component_destination_publish_checkpoint')) {
    function red_addon_component_destination_publish_checkpoint(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        array $request,
        $expectedPlanSha256
    ) {
        if (!red_addon_lifecycle_lock($connection)) {
            return ['reason' => 'lifecycle_lock_failed'];
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
                    $adminRecordId,
                    $request,
                    $expectedPlanSha256,
                    &$transactionReason
                ) {
                    $freshPreview =
                        red_addon_component_destination_route_preview(
                            $manifest,
                            $request
                        );
                    if (!is_array($freshPreview)) {
                        $transactionReason = 'preview_unavailable';
                        return null;
                    }
                    if (!mysqli_begin_transaction($connection)) {
                        return null;
                    }
                    try {
                        $packageId = (string) ($manifest['id'] ?? '');
                        if (!red_addon_component_editor_create_lock_installation(
                            $connection,
                            $packageId
                        )) {
                            $transactionReason = 'package_not_enabled';
                            throw new RuntimeException($transactionReason);
                        }
                        $execution =
                            red_addon_component_destination_execution_load(
                                $connection,
                                $packageId,
                                $expectedPlanSha256,
                                true
                            );
                        if (!is_array($execution)
                            || !hash_equals($componentId, $execution['component'])
                            || $execution['actorRecordId'] !==
                                (int) $adminRecordId
                            || $execution['routeRecordId'] !==
                                (int) $request['routeRecordId']
                            || $execution['componentRecordId'] !==
                                (int) $request['componentRecordId']
                            || !hash_equals(
                                $freshPreview['planSha256'],
                                $execution['packagePlanSha256']
                            )
                        ) {
                            $transactionReason = 'execution_unavailable';
                            throw new RuntimeException($transactionReason);
                        }
                        $routeValues =
                            red_addon_component_destination_publish_route_values(
                                $connection,
                                $request,
                                $execution
                            );
                        $planHash = is_array($routeValues)
                            ? red_addon_component_destination_component_plan_hash(
                                $connection,
                                $manifest,
                                $componentId,
                                $adminRecordId,
                                $request,
                                $freshPreview,
                                $execution,
                                $routeValues
                            )
                            : '';
                        $route = is_array($routeValues)
                            ? red_addon_component_destination_route_reconcile(
                                $connection,
                                $execution,
                                $routeValues
                            )
                            : null;
                        $published =
                            red_addon_component_destination_publish_reconcile(
                                $connection,
                                $manifest,
                                $componentId,
                                $adminRecordId,
                                $request,
                                $execution
                            );
                        if (!red_addon_valid_sha256($planHash)
                            || !hash_equals($expectedPlanSha256, $planHash)
                            || !is_array($route)
                            || !is_array($published)
                        ) {
                            $transactionReason = 'placement_drift';
                            throw new RuntimeException($transactionReason);
                        }
                        if (in_array(
                            $execution['stage'],
                            ['component_published', 'completed'],
                            true
                        )) {
                            if (!hash_equals(
                                $execution['placementStateSha256'],
                                $published['stateHash']
                            ) || !mysqli_commit($connection)) {
                                $transactionReason = 'placement_drift';
                                throw new RuntimeException($transactionReason);
                            }
                            return [
                                'execution' => $execution,
                                'published' => $published,
                                'resumed' => true,
                            ];
                        }
                        if ($execution['stage'] !== 'component_created') {
                            $transactionReason = 'stale_stage';
                            throw new RuntimeException($transactionReason);
                        }
                        $nextStage = 'component_published';
                        $statement = mysqli_prepare(
                            $connection,
                            'UPDATE RED_Addon_Component_Destination_Executions
                             SET Stage=?, PlacementStateSHA256=?
                             WHERE PackageID=? AND PlanSHA256=?
                               AND ActorAdminRecordID=?
                               AND Stage=\'component_created\'
                               AND PlacementStateSHA256 IS NULL
                               AND SearchNotification=\'pending\''
                        );
                        if (!$statement) {
                            $transactionReason = 'checkpoint_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        mysqli_stmt_bind_param(
                            $statement,
                            'ssssi',
                            $nextStage,
                            $published['stateHash'],
                            $execution['package'],
                            $execution['planSha256'],
                            $execution['actorRecordId']
                        );
                        $transactionReason = 'checkpoint_failed';
                        $updated = mysqli_stmt_execute($statement)
                            && mysqli_stmt_affected_rows($statement) === 1;
                        mysqli_stmt_close($statement);
                        $after = $updated
                            ? red_addon_component_destination_execution_load(
                                $connection,
                                $execution['package'],
                                $execution['planSha256'],
                                true
                            )
                            : null;
                        if (!is_array($after)
                            || $after['stage'] !== 'component_published'
                            || !hash_equals(
                                $published['stateHash'],
                                $after['placementStateSha256']
                            )
                            || !mysqli_commit($connection)
                        ) {
                            throw new RuntimeException($transactionReason);
                        }
                        return [
                            'execution' => $after,
                            'published' => $published,
                            'resumed' => false,
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
            return ['reason' => $lockedResult === false
                && $transactionReason === 'transaction_failed'
                    ? 'theme_lock_failed'
                    : $transactionReason];
        }
        return array_merge($lockedResult, ['reason' => 'checkpointed']);
    }
}

if (!function_exists('red_addon_component_destination_component_publish')) {
    function red_addon_component_destination_component_publish(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        $request,
        $expectedPlanSha256
    ) {
        $result = red_addon_component_destination_publish_result(
            'invalid_request'
        );
        $request = red_addon_component_destination_route_request($request);
        if (!($connection instanceof mysqli)
            || !is_array($request)
            || !red_addon_valid_sha256($expectedPlanSha256)
            || red_addon_component_editor_create_transaction_active($connection)
        ) {
            $result['reason'] = red_addon_component_editor_create_transaction_active(
                $connection
            ) ? 'transaction_already_active' : 'invalid_request';
            return $result;
        }
        $preview = red_addon_component_destination_route_preview(
            $manifest,
            $request
        );
        $result['previewInvoked'] = true;
        if (!is_array($preview)) {
            $result['reason'] = 'preview_unavailable';
            return $result;
        }
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        $execution = red_addon_component_destination_execution_load(
            $connection,
            $packageId,
            $expectedPlanSha256
        );
        if (!is_array($execution)
            || !hash_equals($componentId, $execution['component'])
            || $execution['actorRecordId'] !== (int) $adminRecordId
            || $execution['routeRecordId'] !== (int) $request['routeRecordId']
            || $execution['componentRecordId'] !==
                (int) $request['componentRecordId']
        ) {
            $result['reason'] = $execution === null
                ? 'storage_unavailable'
                : 'execution_unavailable';
            return $result;
        }
        foreach (
            [
                'package',
                'component',
                'planSha256',
                'packagePlanSha256',
                'routeRecordId',
                'componentRecordId',
                'actorRecordId',
                'stage',
                'routeStateSha256',
                'componentStateSha256',
                'placementStateSha256',
            ] as $fieldName
        ) {
            $result[$fieldName] = $execution[$fieldName] ?? $result[$fieldName];
        }
        if (!hash_equals(
            $preview['planSha256'],
            $execution['packagePlanSha256']
        )) {
            $result['reason'] = 'stale_plan';
            return $result;
        }
        if (!red_admin_transaction_tables_supported(
            $connection,
            [
                'RED_Addon_Installations',
                'RED_Addon_Component_Destination_Executions',
                'RED_Articles',
                'RED_Content_Revisions',
                'RED_Admin_Activity_Log',
            ]
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        if (!in_array(
            $execution['stage'],
            ['component_created', 'component_published'],
            true
        )) {
            $result['reason'] = 'component_required';
            return $result;
        }

        $alreadyPublished = !is_array(
            red_addon_component_editor_parent_shell(
                $connection,
                $componentId,
                $execution['componentRecordId']
            )
        );
        if ($execution['stage'] === 'component_created'
            && !$alreadyPublished
        ) {
            $routeValues =
                red_addon_component_destination_publish_route_values(
                    $connection,
                    $request,
                    $execution
                );
            $component =
                red_addon_component_destination_component_reconcile(
                    $connection,
                    $manifest,
                    $componentId,
                    $adminRecordId,
                    $request,
                    $execution
                );
            $planHash = is_array($routeValues)
                ? red_addon_component_destination_component_plan_hash(
                    $connection,
                    $manifest,
                    $componentId,
                    $adminRecordId,
                    $request,
                    $preview,
                    $execution,
                    $routeValues
                )
                : '';
            $placement = red_addon_component_destination_publish_placement(
                $connection,
                $request,
                $execution
            );
            $preflight = is_array($component)
                ? red_addon_component_editor_publish_preflight(
                    $connection,
                    $manifest,
                    $componentId,
                    $execution['componentRecordId'],
                    $adminRecordId,
                    $execution['routeRecordId'],
                    (int) $request['componentPagePosition'],
                    (int) $request['componentPagePositionOrder'],
                    $component['parentStateHash'] ?? '',
                    $component['packageStateHash'] ?? ''
                )
                : [];
            if (!is_array($component)
                || !hash_equals(
                    $execution['componentStateSha256'],
                    $component['stateHash']
                )
                || !hash_equals($expectedPlanSha256, $planHash)
                || !is_array($placement)
                || empty($preflight['ready'])
                || $preflight['placementValues'] !== $placement['values']
                || !hash_equals(
                    $preflight['targetStateHash'],
                    $placement['target']['stateHash']
                )
            ) {
                $result['reason'] = 'stale_plan';
                return $result;
            }
            $publication = red_addon_component_editor_publish_values(
                $connection,
                $manifest,
                $componentId,
                $execution['componentRecordId'],
                $adminRecordId,
                $execution['routeRecordId'],
                (int) $request['componentPagePosition'],
                (int) $request['componentPagePositionOrder'],
                $component['parentStateHash'],
                $component['packageStateHash'],
                $preflight['planHash']
            );
            if (empty($publication['placed'])) {
                $result['reason'] = (string) (
                    $publication['reason'] ?? 'publish_failed'
                );
                return $result;
            }
        }
        $checkpoint = red_addon_component_destination_publish_checkpoint(
            $connection,
            $manifest,
            $componentId,
            $adminRecordId,
            $request,
            $expectedPlanSha256
        );
        if (!is_array($checkpoint['execution'] ?? null)
            || !is_array($checkpoint['published'] ?? null)
        ) {
            $result['reason'] = (string) (
                $checkpoint['reason'] ?? 'checkpoint_failed'
            );
            return $result;
        }
        $after = $checkpoint['execution'];
        $published = $checkpoint['published'];
        $result['published'] = true;
        $result['resumed'] = $alreadyPublished
            || !empty($checkpoint['resumed']);
        $result['stage'] = $after['stage'];
        $result['placementStateSha256'] = $published['stateHash'];
        $result['revisionId'] = $published['revisionId'];
        $result['revisionNumber'] = $published['revisionNumber'];
        $result['reason'] = $result['resumed']
            ? 'resumed'
            : 'component_published';
        return $result;
    }
}

?>
