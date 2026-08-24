<?php
/**
 * Internal restartable coordinator for one add-on component destination.
 *
 * Each stage retains its own preview rederivation, locks, transaction,
 * reconciliation, and checkpoint. This layer adds no outer lock or transaction;
 * it only advances the exact immutable plan in its declared order and stops at
 * the first contained failure so a later call can resume safely.
 */

require_once __DIR__ . '/addon_component_destination_completion_helpers.php';

if (!function_exists('red_addon_component_destination_coordinator_result')) {
    function red_addon_component_destination_coordinator_result($reason)
    {
        return [
            'completed' => false,
            'resumed' => false,
            'routeCreated' => false,
            'componentCreated' => false,
            'componentPublished' => false,
            'completionRecorded' => false,
            'notificationSucceeded' => false,
            'stageAttempts' => 0,
            'completedStages' => 0,
            'failedStage' => '',
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

if (!function_exists('red_addon_component_destination_coordinator_evidence')) {
    function red_addon_component_destination_coordinator_evidence(
        array $result,
        array $stageResult
    ) {
        foreach ([
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
            'searchNotification',
        ] as $fieldName) {
            if (array_key_exists($fieldName, $stageResult)) {
                $result[$fieldName] = $stageResult[$fieldName];
            }
        }
        if (!empty($stageResult['resumed'])) {
            $result['resumed'] = true;
        }
        return $result;
    }
}

if (!function_exists('red_addon_component_destination_coordinate')) {
    function red_addon_component_destination_coordinate(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        $request,
        $expectedPlanSha256,
        $projectRoot = null
    ) {
        $result = red_addon_component_destination_coordinator_result(
            'invalid_request'
        );
        $request = red_addon_component_destination_route_request($request);
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if (!($connection instanceof mysqli)
            || !is_array($request)
            || !red_addon_valid_package_id($packageId)
            || !red_addon_valid_capability($componentId)
            || filter_var(
                $adminRecordId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) === false
            || !red_addon_valid_sha256($expectedPlanSha256)
        ) {
            return $result;
        }
        $execution = red_addon_component_destination_execution_load(
            $connection,
            $packageId,
            $expectedPlanSha256
        );
        if ($execution === null) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        $startIndex = 0;
        if (is_array($execution)) {
            $result = red_addon_component_destination_coordinator_evidence(
                $result,
                $execution
            );
            $result['resumed'] = true;
            if (!hash_equals($componentId, $execution['component'])
                || (int) $adminRecordId !== $execution['actorRecordId']
                || (int) $request['routeRecordId'] !==
                    $execution['routeRecordId']
                || (int) $request['componentRecordId'] !==
                    $execution['componentRecordId']
            ) {
                $result['reason'] = 'execution_conflict';
                return $result;
            }
            $progress = [
                'planned' => [0, 0],
                'route_created' => [1, 1],
                'component_created' => [2, 2],
                'component_published' => [3, 3],
                'completed' => [3, 3],
            ][$execution['stage']] ?? null;
            if (!is_array($progress)) {
                $result['reason'] = 'execution_unavailable';
                return $result;
            }
            [$startIndex, $result['completedStages']] = $progress;
            $result['routeCreated'] = $result['completedStages'] >= 1;
            $result['componentCreated'] = $result['completedStages'] >= 2;
            $result['componentPublished'] =
                $result['completedStages'] >= 3;
        }
        $stages = [
            [
                'name' => 'route',
                'successField' => 'created',
                'resultField' => 'routeCreated',
                'runner' => static function () use (
                    $connection,
                    $manifest,
                    $componentId,
                    $adminRecordId,
                    $request,
                    $expectedPlanSha256
                ) {
                    return red_addon_component_destination_route_create(
                        $connection,
                        $manifest,
                        $componentId,
                        $adminRecordId,
                        $request,
                        $expectedPlanSha256
                    );
                },
            ],
            [
                'name' => 'component',
                'successField' => 'created',
                'resultField' => 'componentCreated',
                'runner' => static function () use (
                    $connection,
                    $manifest,
                    $componentId,
                    $adminRecordId,
                    $request,
                    $expectedPlanSha256
                ) {
                    return red_addon_component_destination_component_create(
                        $connection,
                        $manifest,
                        $componentId,
                        $adminRecordId,
                        $request,
                        $expectedPlanSha256
                    );
                },
            ],
            [
                'name' => 'publication',
                'successField' => 'published',
                'resultField' => 'componentPublished',
                'runner' => static function () use (
                    $connection,
                    $manifest,
                    $componentId,
                    $adminRecordId,
                    $request,
                    $expectedPlanSha256
                ) {
                    return red_addon_component_destination_component_publish(
                        $connection,
                        $manifest,
                        $componentId,
                        $adminRecordId,
                        $request,
                        $expectedPlanSha256
                    );
                },
            ],
            [
                'name' => 'completion',
                'successField' => 'completed',
                'resultField' => 'completionRecorded',
                'runner' => static function () use (
                    $connection,
                    $manifest,
                    $componentId,
                    $adminRecordId,
                    $request,
                    $expectedPlanSha256,
                    $projectRoot
                ) {
                    return red_addon_component_destination_complete(
                        $connection,
                        $manifest,
                        $componentId,
                        $adminRecordId,
                        $request,
                        $expectedPlanSha256,
                        $projectRoot
                    );
                },
            ],
        ];

        foreach (array_slice($stages, $startIndex) as $stage) {
            $result['stageAttempts']++;
            try {
                $stageResult = ($stage['runner'])();
            } catch (Throwable $throwable) {
                $result['failedStage'] = $stage['name'];
                $result['reason'] = 'stage_exception';
                return $result;
            }
            if (!is_array($stageResult)) {
                $result['failedStage'] = $stage['name'];
                $result['reason'] = 'stage_result_invalid';
                return $result;
            }
            $result = red_addon_component_destination_coordinator_evidence(
                $result,
                $stageResult
            );
            if (empty($stageResult[$stage['successField']])) {
                $result['failedStage'] = $stage['name'];
                $reason = $stageResult['reason'] ?? '';
                $result['reason'] = is_string($reason) && $reason !== ''
                    ? $reason
                    : 'stage_failed';
                return $result;
            }
            $result[$stage['resultField']] = true;
            $result['completedStages']++;
        }

        $result['completed'] = true;
        $result['notificationSucceeded'] =
            $result['searchNotification'] === 'succeeded';
        $result['reason'] = $result['resumed'] ? 'resumed' : 'completed';
        return $result;
    }
}

?>
