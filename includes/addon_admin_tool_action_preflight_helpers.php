<?php
/**
 * Read-only preflight for future administrator tool write actions.
 *
 * Core binds a declared POST/CSRF-required action to the exact enabled runtime
 * owner, fresh package permission, and one numeric target record. It returns
 * deterministic evidence only. It does not invoke the registered action,
 * inspect package data, start a transaction, read request/session globals, or
 * expose a form or endpoint.
 */

require_once __DIR__ . '/addon_admin_tool_helpers.php';

if (!function_exists('red_addon_admin_tool_action_target_record_id')) {
    function red_addon_admin_tool_action_target_record_id($value)
    {
        return is_int($value) && $value >= 1 && $value <= 2147483647
            ? $value
            : 0;
    }
}

if (!function_exists('red_addon_admin_tool_action_preflight_result')) {
    function red_addon_admin_tool_action_preflight_result(
        $toolId,
        $actionId,
        $actorRecordId,
        $targetRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'authorized' => false,
            'ready' => false,
            'invoked' => false,
            'tool' => is_string($toolId) && red_addon_valid_capability($toolId)
                ? $toolId
                : '',
            'action' => is_string($actionId)
                && red_addon_valid_capability($actionId)
                    ? $actionId
                    : '',
            'package' => '',
            'actorRecordId' => is_int($actorRecordId) && $actorRecordId > 0
                ? $actorRecordId
                : 0,
            'targetRecordId' => red_addon_admin_tool_action_target_record_id(
                $targetRecordId
            ),
            'permission' => '',
            'method' => '',
            'csrf' => '',
            'idempotency' => '',
            'contractSha256' => '',
            'planSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_action_contract_fingerprint')) {
    function red_addon_admin_tool_action_contract_fingerprint(array $contract)
    {
        $encoded = json_encode(
            [
                'tool' => $contract['tool'] ?? null,
                'action' => $contract['action'] ?? null,
                'label' => $contract['label'] ?? null,
                'description' => $contract['description'] ?? null,
                'permission' => $contract['permission'] ?? null,
                'method' => $contract['method'] ?? null,
                'csrf' => $contract['csrf'] ?? null,
                'idempotency' => $contract['idempotency'] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_admin_tool_action_preflight_fingerprint')) {
    function red_addon_admin_tool_action_preflight_fingerprint(array $plan)
    {
        $encoded = json_encode(
            [
                'version' => 2,
                'package' => $plan['package'] ?? null,
                'tool' => $plan['tool'] ?? null,
                'action' => $plan['action'] ?? null,
                'actorRecordId' => $plan['actorRecordId'] ?? null,
                'targetRecordId' => $plan['targetRecordId'] ?? null,
                'permission' => $plan['permission'] ?? null,
                'method' => $plan['method'] ?? null,
                'csrf' => $plan['csrf'] ?? null,
                'idempotency' => $plan['idempotency'] ?? null,
                'contractSha256' => $plan['contractSha256'] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_admin_tool_action_preflight_binding')) {
    function red_addon_admin_tool_action_preflight_binding($toolId, $actionId)
    {
        if (!is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !is_string($actionId)
            || !red_addon_valid_capability($actionId)
        ) {
            return null;
        }

        $toolOwner = red_addon_runtime_owner('adminTools', $toolId);
        $toolHandler = red_addon_runtime_handler('adminTools', $toolId);
        $actionOwner = red_addon_runtime_owner('adminToolActions', $actionId);
        $actionHandler = red_addon_runtime_handler(
            'adminToolActions',
            $actionId
        );
        if (!is_string($toolOwner)
            || !red_addon_valid_package_id($toolOwner)
            || !is_callable($toolHandler)
            || !is_string($actionOwner)
            || !red_addon_valid_package_id($actionOwner)
            || !hash_equals($toolOwner, $actionOwner)
            || !is_callable($actionHandler)
        ) {
            return null;
        }

        $manifest = red_addon_runtime_manifest($toolOwner);
        $contract = is_array($manifest)
            ? red_addon_admin_tool_action_contract(
                $manifest,
                $toolId,
                $actionId
            )
            : null;
        if (!is_array($manifest)
            || !in_array(
                $toolId,
                $manifest['provides']['adminTools'] ?? [],
                true
            )
            || !is_array($contract)
            || !hash_equals($toolId, (string) ($contract['tool'] ?? ''))
            || !hash_equals($actionId, (string) ($contract['action'] ?? ''))
            || ($contract['method'] ?? null) !== 'POST'
            || ($contract['csrf'] ?? null) !== 'required'
            || ($contract['idempotency'] ?? null) !== 'once-per-target'
        ) {
            return null;
        }

        return [
            'tool' => $toolId,
            'action' => $actionId,
            'package' => $toolOwner,
            'permission' => $contract['permission'],
            'method' => $contract['method'],
            'csrf' => $contract['csrf'],
            'idempotency' => $contract['idempotency'],
            'contract' => $contract,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_action_preflight')) {
    function red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorRecordId,
        $targetRecordId
    ) {
        $result = red_addon_admin_tool_action_preflight_result(
            $toolId,
            $actionId,
            $actorRecordId,
            $targetRecordId
        );
        if ($result['tool'] === ''
            || $result['action'] === ''
            || $result['actorRecordId'] < 1
            || $result['targetRecordId'] < 1
        ) {
            return $result;
        }

        $binding = red_addon_admin_tool_action_preflight_binding(
            $result['tool'],
            $result['action']
        );
        if (!is_array($binding)) {
            $result['reason'] = 'action_unavailable';
            return $result;
        }
        $result['package'] = $binding['package'];
        $result['permission'] = $binding['permission'];
        $result['method'] = $binding['method'];
        $result['csrf'] = $binding['csrf'];
        $result['idempotency'] = $binding['idempotency'];
        if (!red_addon_component_editor_actor_has_permission(
            $connection,
            $result['actorRecordId'],
            $result['permission']
        )) {
            $result['reason'] = 'permission_denied';
            return $result;
        }
        $result['authorized'] = true;

        $result['contractSha256'] =
            red_addon_admin_tool_action_contract_fingerprint(
                $binding['contract']
            );
        if (!red_addon_valid_sha256($result['contractSha256'])) {
            $result['authorized'] = false;
            $result['reason'] = 'contract_invalid';
            return $result;
        }
        $result['planSha256'] = red_addon_admin_tool_action_preflight_fingerprint(
            $result
        );
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['authorized'] = false;
            $result['reason'] = 'plan_invalid';
            return $result;
        }
        $result['ready'] = true;
        $result['reason'] = 'preflight_ready';
        return $result;
    }
}

?>
