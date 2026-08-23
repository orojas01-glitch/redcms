<?php
/**
 * Best-effort post-commit notification for one enabled public-content index.
 *
 * Core sends only a closed event id and bounded numeric RED_Articles ids to
 * the exact enabled owner of `content.index-sync`. Package failure is logged
 * and contained after the CMS transaction has already committed.
 */

require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_service_helpers.php';

if (!function_exists('red_addon_content_index_sync_events')) {
    function red_addon_content_index_sync_events()
    {
        return [
            'article.created',
            'article.updated',
            'article.deleted',
            'article.restored',
            'article.moved',
        ];
    }
}

if (!function_exists('red_addon_content_index_sync_request')) {
    function red_addon_content_index_sync_request($event, $recordIds)
    {
        if (!is_string($event)
            || !in_array($event, red_addon_content_index_sync_events(), true)
            || !is_array($recordIds)
            || $recordIds === []
            || count($recordIds) > 64
        ) {
            return null;
        }
        $normalized = [];
        foreach ($recordIds as $recordId) {
            if (is_bool($recordId)
                || filter_var(
                    $recordId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                ) === false
            ) {
                return null;
            }
            $normalized[(int) $recordId] = (int) $recordId;
        }
        ksort($normalized, SORT_NUMERIC);
        return [
            'event' => $event,
            'recordIds' => array_values($normalized),
        ];
    }
}

if (!function_exists('red_addon_content_index_sync_result')) {
    function red_addon_content_index_sync_result($event = '')
    {
        return [
            'attempted' => false,
            'completed' => false,
            'event' => is_string($event) ? $event : '',
            'recordCount' => 0,
            'reason' => 'invalid_request',
        ];
    }
}

if (!function_exists('red_addon_content_index_sync_notify')) {
    function red_addon_content_index_sync_notify(
        $connection,
        $event,
        $recordIds,
        $projectRoot = null
    ) {
        $request = red_addon_content_index_sync_request($event, $recordIds);
        $result = red_addon_content_index_sync_result($event);
        if (!$connection instanceof mysqli || !is_array($request)) {
            return $result;
        }
        $result['event'] = $request['event'];
        $result['recordCount'] = count($request['recordIds']);
        $projectRoot = is_string($projectRoot) && $projectRoot !== ''
            ? $projectRoot
            : dirname(__DIR__);
        try {
            red_addon_runtime_request_bootstrap($connection, $projectRoot);
            $owner = red_addon_runtime_owner(
                'services',
                'content.index-sync'
            );
            if (!is_string($owner) || !red_addon_valid_package_id($owner)) {
                $result['reason'] = 'service_unavailable';
                return $result;
            }
            $result['attempted'] = true;
            $invocation = red_addon_service_invoke(
                'content.index-sync',
                'refresh',
                $request
            );
            if (!empty($invocation['success'])
                && ($invocation['reason'] ?? '') === 'completed'
            ) {
                $result['completed'] = true;
                $result['reason'] = 'completed';
                return $result;
            }
            $result['reason'] = 'service_failed';
        } catch (Throwable $throwable) {
            $result['reason'] = 'runtime_failed';
        }
        error_log(
            'RED-CMS public content index synchronization failed after ' .
            $result['event'] . '.'
        );
        return $result;
    }
}

?>
