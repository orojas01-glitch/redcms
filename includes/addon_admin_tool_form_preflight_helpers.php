<?php
/**
 * Read-only preflight for future operational administrator tool forms.
 *
 * Core binds one closed form declaration to the exact enabled tool owner and
 * a fresh package permission. It returns deterministic evidence only. It does
 * not invoke a package callback, render controls, parse a request body,
 * consume CSRF, open an endpoint, start a transaction, or write state.
 */

require_once __DIR__ . '/addon_admin_tool_helpers.php';

if (!function_exists('red_addon_admin_tool_form_actor_record_id')) {
    function red_addon_admin_tool_form_actor_record_id($value)
    {
        return is_int($value) && $value >= 1 && $value <= 2147483647
            ? $value
            : 0;
    }
}

if (!function_exists('red_addon_admin_tool_form_preflight_result')) {
    function red_addon_admin_tool_form_preflight_result(
        $toolId,
        $formId,
        $actorRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'authorized' => false,
            'ready' => false,
            'invoked' => false,
            'tool' => is_string($toolId)
                && red_addon_valid_capability($toolId)
                    ? $toolId
                    : '',
            'form' => is_string($formId)
                && red_addon_valid_capability($formId)
                    ? $formId
                    : '',
            'package' => '',
            'actorRecordId' => red_addon_admin_tool_form_actor_record_id(
                $actorRecordId
            ),
            'permission' => '',
            'method' => '',
            'csrf' => '',
            'encoding' => '',
            'maxBodyBytes' => 0,
            'contractSha256' => '',
            'planSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_contract_fingerprint')) {
    function red_addon_admin_tool_form_contract_fingerprint(array $contract)
    {
        $encoded = json_encode(
            [
                'tool' => $contract['tool'] ?? null,
                'form' => $contract['form'] ?? null,
                'label' => $contract['label'] ?? null,
                'description' => $contract['description'] ?? null,
                'permission' => $contract['permission'] ?? null,
                'method' => $contract['method'] ?? null,
                'csrf' => $contract['csrf'] ?? null,
                'encoding' => $contract['encoding'] ?? null,
                'maxBodyBytes' => $contract['maxBodyBytes'] ?? null,
                'fields' => $contract['fields'] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_admin_tool_form_plan_fingerprint')) {
    function red_addon_admin_tool_form_plan_fingerprint(array $plan)
    {
        $encoded = json_encode(
            [
                'version' => 1,
                'package' => $plan['package'] ?? null,
                'tool' => $plan['tool'] ?? null,
                'form' => $plan['form'] ?? null,
                'actorRecordId' => $plan['actorRecordId'] ?? null,
                'permission' => $plan['permission'] ?? null,
                'method' => $plan['method'] ?? null,
                'csrf' => $plan['csrf'] ?? null,
                'encoding' => $plan['encoding'] ?? null,
                'maxBodyBytes' => $plan['maxBodyBytes'] ?? null,
                'contractSha256' => $plan['contractSha256'] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_admin_tool_form_preflight_binding')) {
    function red_addon_admin_tool_form_preflight_binding($toolId, $formId)
    {
        if (!is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !is_string($formId)
            || !red_addon_valid_capability($formId)
        ) {
            return null;
        }
        $owner = red_addon_runtime_owner('adminTools', $toolId);
        $handler = red_addon_runtime_handler('adminTools', $toolId);
        $manifest = is_string($owner)
            ? red_addon_runtime_manifest($owner)
            : null;
        $contract = is_array($manifest)
            ? red_addon_admin_tool_form_contract(
                $manifest,
                $toolId,
                $formId
            )
            : null;
        if (!is_string($owner)
            || !red_addon_valid_package_id($owner)
            || !is_callable($handler)
            || !is_array($manifest)
            || !in_array(
                $toolId,
                $manifest['provides']['adminTools'] ?? [],
                true
            )
            || !is_array($contract)
            || !hash_equals($toolId, (string) ($contract['tool'] ?? ''))
            || !hash_equals($formId, (string) ($contract['form'] ?? ''))
            || ($contract['method'] ?? null) !== 'POST'
            || ($contract['csrf'] ?? null) !== 'required'
            || ($contract['encoding'] ?? null) !== 'application/json'
            || !is_int($contract['maxBodyBytes'] ?? null)
            || $contract['maxBodyBytes'] < 1
            || $contract['maxBodyBytes'] > 262144
        ) {
            return null;
        }
        return [
            'tool' => $toolId,
            'form' => $formId,
            'package' => $owner,
            'permission' => $contract['permission'],
            'method' => $contract['method'],
            'csrf' => $contract['csrf'],
            'encoding' => $contract['encoding'],
            'maxBodyBytes' => $contract['maxBodyBytes'],
            'contract' => $contract,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_preflight')) {
    function red_addon_admin_tool_form_preflight(
        $connection,
        $toolId,
        $formId,
        $actorRecordId
    ) {
        $result = red_addon_admin_tool_form_preflight_result(
            $toolId,
            $formId,
            $actorRecordId
        );
        if (!$connection
            || $result['tool'] === ''
            || $result['form'] === ''
            || $result['actorRecordId'] < 1
        ) {
            return $result;
        }
        $binding = red_addon_admin_tool_form_preflight_binding(
            $result['tool'],
            $result['form']
        );
        if (!is_array($binding)) {
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        $result['package'] = $binding['package'];
        $result['permission'] = $binding['permission'];
        $result['method'] = $binding['method'];
        $result['csrf'] = $binding['csrf'];
        $result['encoding'] = $binding['encoding'];
        $result['maxBodyBytes'] = $binding['maxBodyBytes'];
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
            red_addon_admin_tool_form_contract_fingerprint(
                $binding['contract']
            );
        if (!red_addon_valid_sha256($result['contractSha256'])) {
            $result['authorized'] = false;
            $result['reason'] = 'contract_invalid';
            return $result;
        }
        $result['planSha256'] = red_addon_admin_tool_form_plan_fingerprint(
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
