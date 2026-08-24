<?php
/**
 * Best-effort search notification and durable completion for one destination.
 *
 * Published content is reconciled before the post-commit index notification.
 * Search success or failure is then recorded as the terminal checkpoint. A
 * retry after notification but before checkpointing may refresh the same route
 * again, but it never recreates or republishes content.
 */

require_once __DIR__ . '/addon_component_destination_publish_helpers.php';
require_once __DIR__ . '/addon_content_index_sync_helpers.php';

if (!function_exists('red_addon_component_destination_completion_result')) {
    function red_addon_component_destination_completion_result($reason)
    {
        return [
            'completed' => false,
            'resumed' => false,
            'previewInvoked' => false,
            'notificationAttempted' => false,
            'notificationSucceeded' => false,
            'notificationEvent' => '',
            'notificationRecordCount' => 0,
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

if (!function_exists('red_addon_component_destination_completion_search')) {
    function red_addon_component_destination_completion_search(array $result)
    {
        if (array_keys($result) !== [
                'attempted',
                'completed',
                'event',
                'recordCount',
                'reason',
            ]
            || !is_bool($result['attempted'])
            || !is_bool($result['completed'])
            || $result['event'] !== 'article.created'
            || $result['recordCount'] !== 1
            || !is_string($result['reason'])
            || $result['reason'] === ''
        ) {
            return null;
        }
        return [
            'attempted' => $result['attempted'],
            'succeeded' => $result['completed'],
            'event' => 'article.created',
            'recordCount' => 1,
            'checkpoint' => $result['completed'] ? 'succeeded' : 'failed',
        ];
    }
}

if (!function_exists('red_addon_component_destination_complete')) {
    function red_addon_component_destination_complete(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        $request,
        $expectedPlanSha256,
        $projectRoot = null
    ) {
        $result = red_addon_component_destination_completion_result(
            'invalid_request'
        );
        $request = red_addon_component_destination_route_request($request);
        if (!($connection instanceof mysqli)
            || !is_array($request)
            || !red_addon_valid_sha256($expectedPlanSha256)
            || red_addon_component_editor_create_transaction_active($connection)
        ) {
            $result['reason'] =
                red_addon_component_editor_create_transaction_active($connection)
                    ? 'transaction_already_active'
                    : 'invalid_request';
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
            $result[$fieldName] = $execution[$fieldName] ?? $result[$fieldName];
        }
        if (!hash_equals(
            $preview['planSha256'],
            $execution['packagePlanSha256']
        )) {
            $result['reason'] = 'stale_plan';
            return $result;
        }
        if (!in_array(
            $execution['stage'],
            ['component_published', 'completed'],
            true
        )) {
            $result['reason'] = 'publication_required';
            return $result;
        }

        $published = red_addon_component_destination_publish_checkpoint(
            $connection,
            $manifest,
            $componentId,
            $adminRecordId,
            $request,
            $expectedPlanSha256
        );
        if (!is_array($published['execution'] ?? null)
            || !is_array($published['published'] ?? null)
        ) {
            $result['reason'] = (string) (
                $published['reason'] ?? 'placement_drift'
            );
            return $result;
        }
        $execution = $published['execution'];
        if ($execution['stage'] === 'completed') {
            $result['completed'] = true;
            $result['resumed'] = true;
            $result['stage'] = 'completed';
            $result['searchNotification'] =
                $execution['searchNotification'];
            $result['notificationSucceeded'] =
                $execution['searchNotification'] === 'succeeded';
            $result['notificationEvent'] = 'article.created';
            $result['notificationRecordCount'] = 1;
            $result['reason'] = 'resumed';
            return $result;
        }

        $notification = red_addon_content_index_sync_notify(
            $connection,
            'article.created',
            [$execution['routeRecordId']],
            $projectRoot
        );
        $search = is_array($notification)
            ? red_addon_component_destination_completion_search($notification)
            : null;
        if (!is_array($search)) {
            $search = [
                'attempted' => false,
                'succeeded' => false,
                'event' => 'article.created',
                'recordCount' => 1,
                'checkpoint' => 'failed',
            ];
        }
        $result['notificationAttempted'] = $search['attempted'];
        $result['notificationSucceeded'] = $search['succeeded'];
        $result['notificationEvent'] = $search['event'];
        $result['notificationRecordCount'] = $search['recordCount'];

        $checkpoint = red_addon_component_destination_execution_checkpoint(
            $connection,
            $execution['package'],
            $execution['planSha256'],
            $execution['actorRecordId'],
            'component_published',
            'completed',
            '',
            $search['checkpoint']
        );
        if (empty($checkpoint['checkpointed'])) {
            $result['reason'] = (string) (
                $checkpoint['reason'] ?? 'checkpoint_failed'
            );
            return $result;
        }
        $result['completed'] = true;
        $result['resumed'] = !empty($checkpoint['resumed']);
        $result['stage'] = $checkpoint['stage'];
        $result['searchNotification'] =
            $checkpoint['searchNotification'];
        $result['reason'] = $result['resumed'] ? 'resumed' : 'completed';
        return $result;
    }
}

?>
