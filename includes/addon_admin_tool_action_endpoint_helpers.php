<?php
/**
 * Safe request boundary for one declared administrator add-on action.
 *
 * This helper deliberately does not read HTTP or session globals. The core
 * endpoint validates the administrator session and CSRF token before passing
 * this helper the exact request fields and current actor record id. Browser
 * responses contain only a bounded outcome, never package, plan, state, or
 * target values.
 */

require_once __DIR__ . '/addon_admin_tool_action_execution_helpers.php';

if (!function_exists('red_addon_admin_tool_action_endpoint_target_record_id')) {
    function red_addon_admin_tool_action_endpoint_target_record_id($value)
    {
        if (!is_string($value)
            || preg_match('/\A[1-9][0-9]{0,9}\z/', $value) !== 1
        ) {
            return 0;
        }
        $recordId = (int) $value;
        return (string) $recordId === $value
            && red_addon_admin_tool_action_target_record_id($recordId) > 0
            ? $recordId
            : 0;
    }
}

if (!function_exists('red_addon_admin_tool_action_endpoint_request')) {
    function red_addon_admin_tool_action_endpoint_request(array $post)
    {
        $keys = array_keys($post);
        sort($keys, SORT_STRING);
        $expectedKeys = ['action', 'csrf_token', 'targetRecordId', 'tool'];
        if ($keys !== $expectedKeys
            || !is_string($post['tool'] ?? null)
            || !is_string($post['action'] ?? null)
            || !is_string($post['csrf_token'] ?? null)
            || $post['csrf_token'] === ''
            || !red_addon_valid_capability($post['tool'])
            || !red_addon_valid_capability($post['action'])
        ) {
            return null;
        }
        $targetRecordId = red_addon_admin_tool_action_endpoint_target_record_id(
            $post['targetRecordId'] ?? null
        );
        if ($targetRecordId < 1) {
            return null;
        }
        return [
            'tool' => $post['tool'],
            'action' => $post['action'],
            'targetRecordId' => $targetRecordId,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_action_endpoint_request_valid')) {
    function red_addon_admin_tool_action_endpoint_request_valid(array $request)
    {
        return array_keys($request) === ['tool', 'action', 'targetRecordId']
            && is_string($request['tool'] ?? null)
            && red_addon_valid_capability($request['tool'])
            && is_string($request['action'] ?? null)
            && red_addon_valid_capability($request['action'])
            && is_int($request['targetRecordId'] ?? null)
            && red_addon_admin_tool_action_target_record_id(
                $request['targetRecordId']
            ) > 0;
    }
}

if (!function_exists('red_addon_admin_tool_action_endpoint_result')) {
    function red_addon_admin_tool_action_endpoint_result(
        $httpStatus,
        $ok,
        $status = '',
        $reason = ''
    ) {
        $safeStatus = in_array($status, ['executed', 'unchanged'], true)
            ? $status
            : '';
        $safeReason = in_array(
            $reason,
            [
                'invalid_request',
                'permission_denied',
                'action_unavailable',
                'action_conflict',
                'already_executed',
                'action_failed',
            ],
            true
        ) ? $reason : 'action_unavailable';
        if ($ok === true && $safeStatus !== '') {
            return [
                'httpStatus' => 200,
                'ok' => true,
                'status' => $safeStatus,
                'reason' => '',
            ];
        }
        $safeHttpStatus = in_array($httpStatus, [400, 403, 409, 422, 503], true)
            ? $httpStatus
            : 422;
        return [
            'httpStatus' => $safeHttpStatus,
            'ok' => false,
            'status' => '',
            'reason' => $safeReason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_action_endpoint_failure')) {
    function red_addon_admin_tool_action_endpoint_failure($reason)
    {
        $reason = is_string($reason) ? $reason : '';
        if ($reason === 'invalid_request') {
            return red_addon_admin_tool_action_endpoint_result(
                400,
                false,
                '',
                'invalid_request'
            );
        }
        if ($reason === 'permission_denied') {
            return red_addon_admin_tool_action_endpoint_result(
                403,
                false,
                '',
                'permission_denied'
            );
        }
        if ($reason === 'already_executed') {
            return red_addon_admin_tool_action_endpoint_result(
                409,
                false,
                '',
                'already_executed'
            );
        }
        if ($reason === 'plan_mismatch') {
            return red_addon_admin_tool_action_endpoint_result(
                409,
                false,
                '',
                'action_conflict'
            );
        }
        if ($reason === 'action_failed') {
            return red_addon_admin_tool_action_endpoint_result(
                422,
                false,
                '',
                'action_failed'
            );
        }
        return red_addon_admin_tool_action_endpoint_result(
            422,
            false,
            '',
            'action_unavailable'
        );
    }
}

if (!function_exists('red_addon_admin_tool_action_endpoint_dispatch')) {
    function red_addon_admin_tool_action_endpoint_dispatch(
        $connection,
        array $request,
        $actorRecordId
    ) {
        if (!$connection
            || !red_addon_admin_tool_action_endpoint_request_valid($request)
            || red_addon_admin_tool_action_execution_actor_record_id(
                $actorRecordId
            ) < 1
        ) {
            return red_addon_admin_tool_action_endpoint_failure('invalid_request');
        }
        $preflight = red_addon_admin_tool_action_execution_preflight(
            $connection,
            $request['tool'],
            $request['action'],
            $actorRecordId,
            $request['targetRecordId']
        );
        if (empty($preflight['ready'])
            || !red_addon_valid_sha256($preflight['planSha256'] ?? null)
        ) {
            return red_addon_admin_tool_action_endpoint_failure(
                $preflight['reason'] ?? ''
            );
        }
        $execution = red_addon_admin_tool_action_execute(
            $connection,
            $request['tool'],
            $request['action'],
            $actorRecordId,
            $request['targetRecordId'],
            $preflight['planSha256']
        );
        if (!empty($execution['executed'])) {
            return red_addon_admin_tool_action_endpoint_result(
                200,
                true,
                'executed'
            );
        }
        if (!empty($execution['unchanged'])) {
            return red_addon_admin_tool_action_endpoint_result(
                200,
                true,
                'unchanged'
            );
        }
        return red_addon_admin_tool_action_endpoint_failure(
            $execution['reason'] ?? ''
        );
    }
}

if (!function_exists('red_addon_admin_tool_action_endpoint_public_body')) {
    function red_addon_admin_tool_action_endpoint_public_body(array $result)
    {
        if (($result['ok'] ?? false) === true
            && in_array($result['status'] ?? '', ['executed', 'unchanged'], true)
        ) {
            return [
                'ok' => true,
                'status' => $result['status'],
            ];
        }
        return [
            'ok' => false,
            'reason' => in_array(
                $result['reason'] ?? '',
                [
                    'invalid_request',
                    'permission_denied',
                    'action_unavailable',
                    'action_conflict',
                    'already_executed',
                    'action_failed',
                ],
                true
            ) ? $result['reason'] : 'action_unavailable',
        ];
    }
}

?>
