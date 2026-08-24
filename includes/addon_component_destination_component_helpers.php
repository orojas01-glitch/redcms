<?php
/**
 * Restartable inactive-component creation for add-on destinations.
 *
 * The existing component creator owns its atomic transaction. This
 * coordinator follows it with a separately locked reconciliation and
 * checkpoint transaction. If the process stops between those commits, the
 * retry proves the exact parent, package, revision, route, and plan evidence
 * before advancing the ledger without invoking the package creator again.
 */

require_once __DIR__ . '/addon_component_destination_route_helpers.php';
require_once __DIR__ . '/addon_component_editor_parent_helpers.php';

if (!function_exists('red_addon_component_destination_component_result')) {
    function red_addon_component_destination_component_result($reason)
    {
        return [
            'created' => false,
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
            'parentRevisionId' => 0,
            'packageRevisionId' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_destination_component_state_hash')) {
    function red_addon_component_destination_component_state_hash(
        $parentStateSha256,
        $packageStateSha256
    ) {
        if (!red_addon_valid_sha256($parentStateSha256)
            || !red_addon_valid_sha256($packageStateSha256)
        ) {
            return '';
        }
        return red_addon_component_editor_publish_hash([
            'schema' => 1,
            'parentStateSha256' => $parentStateSha256,
            'packageStateSha256' => $packageStateSha256,
        ]);
    }
}

if (!function_exists('red_addon_component_destination_component_create_plan')) {
    function red_addon_component_destination_component_create_plan(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        array $parentMetadata,
        array $submittedValues
    ) {
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if (!red_addon_valid_package_id($packageId)
            || red_addon_runtime_manifest($packageId) !== $manifest
            || !red_addon_component_editor_create_package_enabled(
                $connection,
                $packageId
            )
        ) {
            return null;
        }
        $authorization = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'create',
            $adminRecordId
        );
        $permission = is_string($authorization['permission'] ?? null)
            ? $authorization['permission']
            : '';
        $creatorOwner = red_addon_runtime_owner(
            'componentDataCreators',
            $componentId
        );
        $creator = red_addon_runtime_handler(
            'componentDataCreators',
            $componentId
        );
        $tables = red_addon_component_editor_creator_tables($componentId);
        $parent = red_addon_component_editor_create_parent_values(
            $connection,
            $componentId,
            $contentRecordId,
            $parentMetadata
        );
        $validated = red_addon_component_editor_validate_values(
            $manifest,
            $componentId,
            $submittedValues
        );
        if (empty($authorization['authorized'])
            || !is_string($creatorOwner)
            || !hash_equals($packageId, $creatorOwner)
            || !is_callable($creator)
            || !is_array($tables)
            || !is_array($parent)
            || empty($validated['valid'])
            || !is_array($validated['values'] ?? null)
            || !red_admin_transaction_tables_supported(
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
            )
        ) {
            return null;
        }
        $plan = red_addon_component_editor_create_plan(
            $packageId,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            $permission,
            $parent,
            $validated['values'],
            $tables
        );
        if (!is_array($plan)) {
            return null;
        }
        return [
            'parentValues' => $parent,
            'values' => $validated['values'],
            'planHash' => $plan['planHash'],
        ];
    }
}

if (!function_exists('red_addon_component_destination_component_plan_hash')) {
    function red_addon_component_destination_component_plan_hash(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        array $request,
        array $preview,
        array $execution,
        array $routeValues
    ) {
        $publish = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'publish',
            $adminRecordId
        );
        $parentMetadata = [
            'title' => $request['title'],
            'layout' => $request['layout'],
            'language' => $request['language'],
        ];
        $component = red_addon_component_destination_component_create_plan(
            $connection,
            $manifest,
            $componentId,
            $execution['componentRecordId'],
            $adminRecordId,
            $parentMetadata,
            $request['componentValues']
        );
        $target = red_addon_component_editor_publish_target(
            $connection,
            $execution['routeRecordId']
        );
        if (empty($publish['authorized'])
            || !is_array($component)
            || !is_array($target)
            || !hash_equals($request['language'], $target['language'])
        ) {
            return '';
        }
        $placement = [
            'Sections' => $target['sections'],
            'Categories' => '',
            'SubCategories' => '',
            'Article' => $request['alias'],
            'PagePosition' => (int) $request['componentPagePosition'],
            'PagePositionOrder' =>
                (int) $request['componentPagePositionOrder'],
            'Active' => 'Y',
        ];
        return red_addon_component_destination_plan_hash([
            'schema' => 1,
            'package' => $execution['package'],
            'component' => $execution['component'],
            'actorRecordId' => (string) $execution['actorRecordId'],
            'packagePlanSha256' => $preview['planSha256'],
            'routeValues' => $routeValues,
            'componentRecordId' => (string) $execution['componentRecordId'],
            'componentParentMetadata' => $parentMetadata,
            'componentValues' => $component['values'],
            'componentCreatePlanHash' => $component['planHash'],
            'targetStateHash' => $target['stateHash'],
            'placementValues' => $placement,
            'operations' => [
                'core.article-route.create',
                'core.addon-component.create',
                'core.addon-component.publish',
                'content.search.refresh',
            ],
        ]);
    }
}

if (!function_exists('red_addon_component_destination_parent_revision')) {
    function red_addon_component_destination_parent_revision(
        $connection,
        $contentRecordId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RevisionID, RevisionNumber, Operation,
                        ActorAdminRecordID, SnapshotHash
                 FROM RED_Content_Revisions
                 WHERE ContentRecordID=?
                 ORDER BY RevisionNumber DESC LIMIT 1'
            );
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param($statement, 'i', $contentRecordId);
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

if (!function_exists('red_addon_component_destination_component_reconcile')) {
    function red_addon_component_destination_component_reconcile(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        array $request,
        array $execution
    ) {
        $parent = red_addon_component_editor_parent_state(
            $connection,
            $manifest,
            $componentId,
            $execution['componentRecordId'],
            $adminRecordId
        );
        $expectedMetadata = [
            'title' => $request['title'],
            'layout' => $request['layout'],
            'language' => $request['language'],
        ];
        $validated = red_addon_component_editor_validate_values(
            $manifest,
            $componentId,
            $request['componentValues']
        );
        $parentRevision = red_addon_component_destination_parent_revision(
            $connection,
            $execution['componentRecordId']
        );
        $packageRevision = red_addon_component_revision_latest(
            $connection,
            $execution['componentRecordId']
        );
        if (empty($parent['loaded'])
            || $parent['parentValues'] !== $expectedMetadata
            || empty($validated['valid'])
            || !is_array($validated['values'] ?? null)
            || $parent['packageStateHash'] !== red_addon_component_editor_data_hash(
                $execution['package'],
                $componentId,
                $execution['componentRecordId'],
                $validated['values']
            )
            || !is_array($parentRevision)
            || (int) ($parentRevision['RevisionNumber'] ?? 0) !== 1
            || (string) ($parentRevision['Operation'] ?? '') !== 'create'
            || (int) ($parentRevision['ActorAdminRecordID'] ?? 0)
                !== $execution['actorRecordId']
            || !hash_equals(
                $parent['stateHash'],
                (string) ($parentRevision['SnapshotHash'] ?? '')
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
                $parent['packageStateHash'],
                (string) ($packageRevision['StateHash'] ?? '')
            )
        ) {
            return null;
        }
        $stateHash = red_addon_component_destination_component_state_hash(
            $parent['stateHash'],
            $parent['packageStateHash']
        );
        return red_addon_valid_sha256($stateHash)
            ? [
                'stateHash' => $stateHash,
                'parentRevisionId' =>
                    (int) ($parentRevision['RevisionID'] ?? 0),
                'packageRevisionId' =>
                    (int) ($packageRevision['RevisionID'] ?? 0),
            ]
            : null;
    }
}

if (!function_exists('red_addon_component_destination_component_checkpoint')) {
    function red_addon_component_destination_component_checkpoint(
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
                            || (int) $adminRecordId
                                !== $execution['actorRecordId']
                            || (int) $request['routeRecordId']
                                !== $execution['routeRecordId']
                            || (int) $request['componentRecordId']
                                !== $execution['componentRecordId']
                            || !hash_equals(
                                $freshPreview['planSha256'],
                                $execution['packagePlanSha256']
                            )
                        ) {
                            $transactionReason = 'execution_unavailable';
                            throw new RuntimeException($transactionReason);
                        }
                        $home = red_addon_component_editor_publish_target(
                            $connection,
                            0,
                            $request['language']
                        );
                        $routeValues = is_array($home) ? [
                            'RecordID' => $execution['routeRecordId'],
                            'Title' => $request['title'],
                            'Component' => 'Article',
                            'Alias' => $request['alias'],
                            'Sections' => $home['sections'],
                            'Categories' => '',
                            'SubCategories' => '',
                            'Layout' => $request['layout'],
                            'PagePosition' =>
                                (int) $request['routePagePosition'],
                            'PagePositionOrder' =>
                                (int) $request['routePagePositionOrder'],
                            'Active' => 'Y',
                            'Language' => $request['language'],
                        ] : [];
                        $planHash = is_array($home)
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
                        $route = is_array($home)
                            ? red_addon_component_destination_route_reconcile(
                                $connection,
                                $execution,
                                $routeValues
                            )
                            : null;
                        $component =
                            red_addon_component_destination_component_reconcile(
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
                            || !is_array($component)
                        ) {
                            $transactionReason = 'component_drift';
                            throw new RuntimeException($transactionReason);
                        }
                        if ($execution['stage'] === 'component_created') {
                            if (!hash_equals(
                                $execution['componentStateSha256'],
                                $component['stateHash']
                            ) || !mysqli_commit($connection)) {
                                $transactionReason = 'component_drift';
                                throw new RuntimeException($transactionReason);
                            }
                            return [
                                'execution' => $execution,
                                'component' => $component,
                                'resumed' => true,
                            ];
                        }
                        if ($execution['stage'] !== 'route_created') {
                            $transactionReason = 'stale_stage';
                            throw new RuntimeException($transactionReason);
                        }
                        $nextStage = 'component_created';
                        $statement = mysqli_prepare(
                            $connection,
                            'UPDATE RED_Addon_Component_Destination_Executions
                             SET Stage=?, ComponentStateSHA256=?
                             WHERE PackageID=? AND PlanSHA256=?
                               AND ActorAdminRecordID=?
                               AND Stage=\'route_created\'
                               AND ComponentStateSHA256 IS NULL
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
                            $component['stateHash'],
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
                            || $after['stage'] !== 'component_created'
                            || !hash_equals(
                                $component['stateHash'],
                                $after['componentStateSha256']
                            )
                            || !mysqli_commit($connection)
                        ) {
                            $transactionReason = 'checkpoint_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        return [
                            'execution' => $after,
                            'component' => $component,
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
            return [
                'reason' => $lockedResult === false
                    && $transactionReason === 'transaction_failed'
                        ? 'theme_lock_failed'
                        : $transactionReason,
            ];
        }
        return array_merge($lockedResult, ['reason' => 'checkpointed']);
    }
}

if (!function_exists('red_addon_component_destination_component_create')) {
    function red_addon_component_destination_component_create(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        $request,
        $expectedPlanSha256
    ) {
        $result = red_addon_component_destination_component_result(
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
            || (int) $adminRecordId !== $execution['actorRecordId']
            || (int) $request['routeRecordId'] !== $execution['routeRecordId']
            || (int) $request['componentRecordId']
                !== $execution['componentRecordId']
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
        if ($execution['stage'] === 'planned') {
            $result['reason'] = 'route_required';
            return $result;
        }
        if (!in_array(
            $execution['stage'],
            ['route_created', 'component_created'],
            true
        )) {
            $result['reason'] = 'stale_stage';
            return $result;
        }

        $componentAlreadyCreated =
            !red_addon_component_editor_create_record_available(
                $connection,
                $execution['componentRecordId']
            );
        $parentMetadata = [
            'title' => $request['title'],
            'layout' => $request['layout'],
            'language' => $request['language'],
        ];
        if ($execution['stage'] === 'route_created'
            && !$componentAlreadyCreated
        ) {
            $preflight = red_addon_component_editor_create_preflight(
                $connection,
                $manifest,
                $componentId,
                $execution['componentRecordId'],
                $adminRecordId,
                $parentMetadata,
                $request['componentValues']
            );
            $home = red_addon_component_editor_publish_target(
                $connection,
                0,
                $request['language']
            );
            $routeValues = is_array($home) ? [
                'RecordID' => $execution['routeRecordId'],
                'Title' => $request['title'],
                'Component' => 'Article',
                'Alias' => $request['alias'],
                'Sections' => $home['sections'],
                'Categories' => '',
                'SubCategories' => '',
                'Layout' => $request['layout'],
                'PagePosition' => (int) $request['routePagePosition'],
                'PagePositionOrder' =>
                    (int) $request['routePagePositionOrder'],
                'Active' => 'Y',
                'Language' => $request['language'],
            ] : [];
            $planHash = is_array($home)
                ? red_addon_component_destination_route_resume_plan_hash(
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
            if (empty($preflight['ready'])
                || !hash_equals($expectedPlanSha256, $planHash)
                || !is_array(
                    red_addon_component_destination_route_reconcile(
                        $connection,
                        $execution,
                        $routeValues
                    )
                )
            ) {
                $result['reason'] = 'stale_plan';
                return $result;
            }
            $creation = red_addon_component_editor_create_values(
                $connection,
                $manifest,
                $componentId,
                $execution['componentRecordId'],
                $adminRecordId,
                $parentMetadata,
                $request['componentValues'],
                $preflight['planHash']
            );
            if (empty($creation['created'])) {
                $result['reason'] = (string) (
                    $creation['reason'] ?? 'component_create_failed'
                );
                return $result;
            }
        }
        $checkpoint = red_addon_component_destination_component_checkpoint(
            $connection,
            $manifest,
            $componentId,
            $adminRecordId,
            $request,
            $expectedPlanSha256
        );
        $result['previewInvoked'] = true;
        if (!is_array($checkpoint['execution'] ?? null)
            || !is_array($checkpoint['component'] ?? null)
        ) {
            $result['reason'] = (string) (
                $checkpoint['reason'] ?? 'checkpoint_failed'
            );
            return $result;
        }
        $after = $checkpoint['execution'];
        $component = $checkpoint['component'];
        $result['created'] = true;
        $result['resumed'] = $componentAlreadyCreated
            || !empty($checkpoint['resumed']);
        $result['stage'] = $after['stage'];
        $result['componentStateSha256'] = $component['stateHash'];
        $result['parentRevisionId'] = $component['parentRevisionId'];
        $result['packageRevisionId'] = $component['packageRevisionId'];
        $result['reason'] = $result['resumed']
            ? 'resumed'
            : 'component_created';
        return $result;
    }
}

?>
