<?php
/**
 * First restartable write stage for add-on component destinations.
 *
 * Core rederives a write-disabled package preview through the typed service
 * boundary, reserves the exact composite plan, and atomically creates one
 * Article route with revision, audit, and route checkpoint evidence.
 */

require_once __DIR__ . '/addon_component_destination_execution_helpers.php';
require_once __DIR__ . '/addon_service_helpers.php';
require_once __DIR__ . '/admin_audit_helpers.php';

if (!function_exists('red_addon_component_destination_route_result')) {
    function red_addon_component_destination_route_result($reason)
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
            'revisionId' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_destination_route_request')) {
    function red_addon_component_destination_route_request($request)
    {
        $keys = [
            'previewService',
            'previewOperation',
            'previewInput',
            'routeRecordId',
            'componentRecordId',
            'title',
            'alias',
            'language',
            'layout',
            'routePagePosition',
            'routePagePositionOrder',
            'componentPagePosition',
            'componentPagePositionOrder',
            'componentValues',
        ];
        if (!is_array($request)
            || array_keys($request) !== $keys
            || !is_string($request['previewService'])
            || !red_addon_valid_capability($request['previewService'])
            || !is_string($request['previewOperation'])
            || preg_match(
                '/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D',
                $request['previewOperation']
            ) !== 1
            || strlen($request['previewOperation']) > 80
            || !is_array($request['previewInput'])
        ) {
            return null;
        }
        return $request;
    }
}

if (!function_exists('red_addon_component_destination_route_preview')) {
    function red_addon_component_destination_route_preview(
        array $manifest,
        array $request
    ) {
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if (!red_addon_valid_package_id($packageId)
            || !in_array(
                $request['previewService'],
                $manifest['provides']['services'] ?? [],
                true
            )
        ) {
            return null;
        }
        $invocation = red_addon_service_invoke(
            $request['previewService'],
            $request['previewOperation'],
            $request['previewInput']
        );
        if (empty($invocation['invoked'])
            || empty($invocation['success'])
            || !hash_equals(
                $packageId,
                (string) ($invocation['package'] ?? '')
            )
            || !is_array($invocation['data'] ?? null)
        ) {
            return null;
        }
        return red_addon_component_destination_preview(
            $invocation['data'],
            $request['alias']
        );
    }
}

if (!function_exists('red_addon_component_destination_route_preflight_request')) {
    function red_addon_component_destination_route_preflight_request(
        array $request,
        array $preview
    ) {
        return [
            'packagePreview' => $preview,
            'routeRecordId' => $request['routeRecordId'],
            'componentRecordId' => $request['componentRecordId'],
            'title' => $request['title'],
            'alias' => $request['alias'],
            'language' => $request['language'],
            'layout' => $request['layout'],
            'routePagePosition' => $request['routePagePosition'],
            'routePagePositionOrder' =>
                $request['routePagePositionOrder'],
            'componentPagePosition' => $request['componentPagePosition'],
            'componentPagePositionOrder' =>
                $request['componentPagePositionOrder'],
            'componentValues' => $request['componentValues'],
        ];
    }
}

if (!function_exists('red_addon_component_destination_route_matches')) {
    function red_addon_component_destination_route_matches(
        $connection,
        array $routeValues
    ) {
        $recordId = (int) ($routeValues['RecordID'] ?? 0);
        $row = red_admin_article_full_record($connection, $recordId);
        if (!is_array($row)) {
            return false;
        }
        foreach ($routeValues as $fieldName => $value) {
            if (!array_key_exists($fieldName, $row)
                || (string) $row[$fieldName] !== (string) $value
            ) {
                return false;
            }
        }
        return (string) ($row['Article'] ?? '') === '';
    }
}

if (!function_exists('red_addon_component_destination_route_audit_count')) {
    function red_addon_component_destination_route_audit_count(
        $connection,
        $routeRecordId,
        $actorRecordId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE EventName='article.created' AND TargetType='article'
                   AND TargetRecordID=? AND ActorAdminRecordID=?"
            );
            if (!$statement) {
                return -1;
            }
            $count = -1;
            mysqli_stmt_bind_param(
                $statement,
                'ii',
                $routeRecordId,
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

if (!function_exists('red_addon_component_destination_route_checkpoint')) {
    function red_addon_component_destination_route_checkpoint(
        $connection,
        array $execution,
        $routeStateSha256
    ) {
        if (($execution['stage'] ?? null) !== 'planned'
            || !red_addon_valid_sha256($routeStateSha256)
        ) {
            return false;
        }
        try {
            $nextStage = 'route_created';
            $statement = mysqli_prepare(
                $connection,
                'UPDATE RED_Addon_Component_Destination_Executions
                 SET Stage=?, RouteStateSHA256=?
                 WHERE PackageID=? AND PlanSHA256=? AND ActorAdminRecordID=?
                   AND Stage=\'planned\' AND RouteStateSHA256 IS NULL
                   AND ComponentStateSHA256 IS NULL
                   AND PlacementStateSHA256 IS NULL
                   AND SearchNotification=\'pending\''
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'ssssi',
                $nextStage,
                $routeStateSha256,
                $execution['package'],
                $execution['planSha256'],
                $execution['actorRecordId']
            );
            $updated = mysqli_stmt_execute($statement)
                && mysqli_stmt_affected_rows($statement) === 1;
            mysqli_stmt_close($statement);
            return $updated;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_destination_route_reconcile')) {
    function red_addon_component_destination_route_reconcile(
        $connection,
        array $execution,
        array $routeValues
    ) {
        $stages = red_addon_component_destination_execution_stages();
        if (array_search($execution['stage'], $stages, true)
                < array_search('route_created', $stages, true)
            || !red_addon_component_destination_route_matches(
                $connection,
                $routeValues
            )
        ) {
            return null;
        }
        $stateHash = red_admin_content_revision_current_hash(
            $connection,
            $execution['routeRecordId']
        );
        $latest = red_admin_content_revision_latest(
            $connection,
            $execution['routeRecordId']
        );
        if (!red_addon_valid_sha256($stateHash)
            || !hash_equals($execution['routeStateSha256'], $stateHash)
            || !is_array($latest)
            || (string) ($latest['Operation'] ?? '') !== 'create'
            || !hash_equals(
                $stateHash,
                (string) ($latest['SnapshotHash'] ?? '')
            )
            || red_addon_component_destination_route_audit_count(
                $connection,
                $execution['routeRecordId'],
                $execution['actorRecordId']
            ) !== 1
        ) {
            return null;
        }
        return [
            'stateHash' => $stateHash,
            'revisionId' => (int) ($latest['RevisionID'] ?? 0),
        ];
    }
}

if (!function_exists('red_addon_component_destination_route_resume_plan_hash')) {
    function red_addon_component_destination_route_resume_plan_hash(
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
        if (empty($publish['authorized'])) {
            return '';
        }
        $parentMetadata = [
            'title' => $request['title'],
            'layout' => $request['layout'],
            'language' => $request['language'],
        ];
        $component = red_addon_component_editor_create_preflight(
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
        if (empty($component['ready'])
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

if (!function_exists('red_addon_component_destination_route_create')) {
    function red_addon_component_destination_route_create(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        $request,
        $expectedPlanSha256
    ) {
        $result = red_addon_component_destination_route_result(
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
        $preflightRequest =
            red_addon_component_destination_route_preflight_request(
                $request,
                $preview
            );
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        $existing = red_addon_component_destination_execution_load(
            $connection,
            $packageId,
            $expectedPlanSha256
        );
        if ($existing === null) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        if (is_array($existing)) {
            if (!hash_equals(
                $preview['planSha256'],
                $existing['packagePlanSha256']
            )) {
                $result['reason'] = 'stale_plan';
                return $result;
            }
            if (!hash_equals($componentId, $existing['component'])
                || (int) $request['routeRecordId']
                    !== $existing['routeRecordId']
                || (int) $request['componentRecordId']
                    !== $existing['componentRecordId']
                || (int) $adminRecordId !== $existing['actorRecordId']
            ) {
                $result['reason'] = 'execution_conflict';
                return $result;
            }
            $reservation = array_merge($existing, [
                'reserved' => true,
                'resumed' => true,
            ]);
        } else {
            $reservation = null;
        }
        if (!is_array($reservation)
            || $reservation['stage'] === 'planned'
        ) {
            $preflight = red_addon_component_destination_preflight(
                $connection,
                $manifest,
                $componentId,
                $adminRecordId,
                $preflightRequest
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
            if (!is_array($reservation)) {
                $reservation =
                    red_addon_component_destination_execution_reserve(
                        $connection,
                        $manifest,
                        $componentId,
                        $adminRecordId,
                        $preflightRequest,
                        $expectedPlanSha256
                    );
                if (empty($reservation['reserved'])) {
                    $result['reason'] = (string) (
                        $reservation['reason'] ?? 'reservation_failed'
                    );
                    return $result;
                }
            }
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
            ] as $fieldName
        ) {
            $result[$fieldName] = $reservation[$fieldName] ?? $result[$fieldName];
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
                    $adminRecordId,
                    $request,
                    $expectedPlanSha256,
                    $reservation,
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
                    if (!hash_equals(
                        $reservation['packagePlanSha256'],
                        $freshPreview['planSha256']
                    )) {
                        $transactionReason = 'stale_plan';
                        return null;
                    }
                    $freshRequest =
                        red_addon_component_destination_route_preflight_request(
                            $request,
                            $freshPreview
                        );
                    if (!mysqli_begin_transaction($connection)) {
                        return null;
                    }
                    try {
                        if (!red_addon_component_editor_create_lock_installation(
                            $connection,
                            $reservation['package']
                        )) {
                            $transactionReason = 'package_not_enabled';
                            throw new RuntimeException($transactionReason);
                        }
                        $execution =
                            red_addon_component_destination_execution_load(
                                $connection,
                                $reservation['package'],
                                $expectedPlanSha256,
                                true
                            );
                        if (!is_array($execution)
                            || !red_addon_component_destination_execution_matches_plan(
                                $execution,
                                [
                                    'package' => $reservation['package'],
                                    'component' => $reservation['component'],
                                    'planHash' => $expectedPlanSha256,
                                    'packagePlanSha256' =>
                                        $reservation['packagePlanSha256'],
                                    'routeRecordId' =>
                                        $reservation['routeRecordId'],
                                    'componentRecordId' =>
                                        $reservation['componentRecordId'],
                                    'actorRecordId' =>
                                        $reservation['actorRecordId'],
                                ]
                            )
                        ) {
                            $transactionReason = 'execution_unavailable';
                            throw new RuntimeException($transactionReason);
                        }
                        if ($execution['stage'] !== 'planned') {
                            $home = red_addon_component_editor_publish_target(
                                $connection,
                                0,
                                $request['language']
                            );
                            if (!is_array($home)) {
                                $transactionReason = 'route_drift';
                                throw new RuntimeException($transactionReason);
                            }
                            $expectedRoute = [
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
                            ];
                            $resumePlanHash =
                                red_addon_component_destination_route_resume_plan_hash(
                                    $connection,
                                    $manifest,
                                    $componentId,
                                    $adminRecordId,
                                    $request,
                                    $freshPreview,
                                    $execution,
                                    $expectedRoute
                                );
                            if (!red_addon_valid_sha256($resumePlanHash)
                                || !hash_equals(
                                    $expectedPlanSha256,
                                    $resumePlanHash
                                )
                            ) {
                                $transactionReason = 'stale_plan';
                                throw new RuntimeException($transactionReason);
                            }
                            $reconciled =
                                red_addon_component_destination_route_reconcile(
                                    $connection,
                                    $execution,
                                    $expectedRoute
                                );
                            if (!is_array($reconciled)
                                || !mysqli_commit($connection)
                            ) {
                                $transactionReason = 'route_drift';
                                throw new RuntimeException($transactionReason);
                            }
                            return [
                                'execution' => $execution,
                                'stateHash' => $reconciled['stateHash'],
                                'revisionId' => $reconciled['revisionId'],
                                'resumed' => true,
                            ];
                        }
                        $lockedPlan = red_addon_component_destination_preflight(
                            $connection,
                            $manifest,
                            $componentId,
                            $adminRecordId,
                            $freshRequest
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
                        $route = array_merge(
                            red_admin_article_default_insert_data(
                                $execution['routeRecordId']
                            ),
                            $lockedPlan['routeValues'],
                            ['Article' => '']
                        );
                        if (!red_admin_article_insert_unlocked(
                            $connection,
                            $execution['routeRecordId'],
                            $route
                        ) || !red_addon_component_destination_route_matches(
                            $connection,
                            $lockedPlan['routeValues']
                        )) {
                            $transactionReason = 'route_insert_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $revisionId =
                            red_addon_component_editor_create_parent_revision(
                                $connection,
                                $execution['routeRecordId'],
                                $execution['actorRecordId']
                            );
                        if ($revisionId < 1) {
                            $transactionReason = 'revision_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_admin_audit_record(
                            $connection,
                            'article.created',
                            'article',
                            $execution['routeRecordId'],
                            $execution['actorRecordId']
                        )) {
                            $transactionReason = 'audit_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $stateHash = red_admin_content_revision_current_hash(
                            $connection,
                            $execution['routeRecordId']
                        );
                        $latest = red_admin_content_revision_latest(
                            $connection,
                            $execution['routeRecordId']
                        );
                        if (!red_addon_valid_sha256($stateHash)
                            || !is_array($latest)
                            || (int) ($latest['RevisionID'] ?? 0) !== $revisionId
                            || (string) ($latest['Operation'] ?? '') !== 'create'
                            || !hash_equals(
                                $stateHash,
                                (string) ($latest['SnapshotHash'] ?? '')
                            )
                            || red_addon_component_destination_route_audit_count(
                                $connection,
                                $execution['routeRecordId'],
                                $execution['actorRecordId']
                            ) !== 1
                            || !red_addon_component_destination_route_checkpoint(
                                $connection,
                                $execution,
                                $stateHash
                            )
                        ) {
                            $transactionReason = 'postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $after =
                            red_addon_component_destination_execution_load(
                                $connection,
                                $execution['package'],
                                $execution['planSha256'],
                                true
                            );
                        if (!is_array($after)
                            || $after['stage'] !== 'route_created'
                            || !hash_equals(
                                $stateHash,
                                $after['routeStateSha256']
                            )
                            || !red_addon_component_editor_create_transaction_active(
                                $connection
                            )
                            || !mysqli_commit($connection)
                        ) {
                            $transactionReason = 'transaction_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        return [
                            'execution' => $after,
                            'stateHash' => $stateHash,
                            'revisionId' => $revisionId,
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
        if (!is_array($lockedResult)
            || !is_array($lockedResult['execution'] ?? null)
        ) {
            $result['reason'] = $lockedResult === false
                && $transactionReason === 'transaction_failed'
                    ? 'theme_lock_failed'
                    : $transactionReason;
            return $result;
        }
        $execution = $lockedResult['execution'];
        $result['created'] = true;
        $result['resumed'] = !empty($lockedResult['resumed']);
        $result['stage'] = $execution['stage'];
        $result['routeStateSha256'] = $lockedResult['stateHash'];
        $result['revisionId'] = $lockedResult['revisionId'];
        $result['reason'] = $result['resumed'] ? 'resumed' : 'route_created';
        return $result;
    }
}

?>
