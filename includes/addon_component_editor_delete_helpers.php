<?php
/**
 * Read-only delete preflight for add-on component editor records.
 *
 * This helper binds exact permission, inactive parent, package state,
 * immutable revision, runtime deleter, and transaction-table evidence into a
 * deterministic plan. It never invokes the deleter, opens a transaction,
 * removes data, renders a control, or exposes an endpoint.
 */

require_once __DIR__ . '/addon_component_editor_parent_helpers.php';

if (!function_exists('red_addon_component_editor_delete_result')) {
    function red_addon_component_editor_delete_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $reason
    ) {
        return [
            'ready' => false,
            'actorRecordId' => is_int($adminRecordId) ? $adminRecordId : 0,
            'contentRecordId' => is_int($contentRecordId)
                ? $contentRecordId
                : 0,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'package' => '',
            'viewPermission' => '',
            'deletePermission' => '',
            'parentStateHash' => '',
            'packageStateHash' => '',
            'packageRevisionId' => 0,
            'transactionTables' => [],
            'planHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_deleter_tables')) {
    function red_addon_component_editor_deleter_tables($componentId)
    {
        $metadata = red_addon_runtime_metadata(
            'componentDataDeleters',
            $componentId
        );
        $tables = is_array($metadata['tables'] ?? null)
            ? $metadata['tables']
            : [];
        if ($tables === [] || count($tables) > 8) {
            return null;
        }
        $normalized = [];
        $reserved = [
            'red_addon_installations',
            'red_addon_migrations',
            'red_addon_activity_log',
            'red_addon_component_revisions',
        ];
        foreach ($tables as $table) {
            if (!is_string($table)
                || preg_match('/\ARED_Addon_[A-Za-z0-9_]{1,54}\z/', $table)
                    !== 1
                || in_array(strtolower($table), $reserved, true)
                || isset($normalized[$table])
            ) {
                return null;
            }
            $normalized[$table] = true;
        }
        return array_keys($normalized);
    }
}

if (!function_exists('red_addon_component_editor_delete_plan_hash')) {
    function red_addon_component_editor_delete_plan_hash(array $plan)
    {
        $json = json_encode(
            $plan,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($json) && $json !== ''
            ? hash('sha256', $json)
            : '';
    }
}

if (!function_exists('red_addon_component_editor_delete_preflight')) {
    function red_addon_component_editor_delete_preflight(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $expectedParentStateHash,
        $expectedPackageStateHash
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $contentRecordId = filter_var(
            $contentRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = red_addon_component_editor_delete_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $adminRecordId === false
                ? 'invalid_actor'
                : ($contentRecordId === false
                    ? 'invalid_content_record'
                    : 'invalid_state_hash')
        );
        if ($adminRecordId === false
            || $contentRecordId === false
            || !red_addon_component_editor_state_hash_valid(
                $expectedParentStateHash
            )
            || !red_addon_component_editor_state_hash_valid(
                $expectedPackageStateHash
            )
        ) {
            return $result;
        }

        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if (!red_addon_valid_package_id($packageId)
            || !is_array(
                red_addon_component_editor_schema($manifest, $componentId)
            )
        ) {
            $result['reason'] = 'schema_unavailable';
            return $result;
        }
        $result['package'] = $packageId;

        $delete = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'delete',
            $adminRecordId
        );
        $result['deletePermission'] = is_string(
            $delete['permission'] ?? null
        ) ? $delete['permission'] : '';
        if (empty($delete['authorized'])) {
            $result['reason'] = (string) (
                $delete['reason'] ?? 'permission_denied'
            );
            return $result;
        }

        $deleterOwner = red_addon_runtime_owner(
            'componentDataDeleters',
            $componentId
        );
        $deleter = red_addon_runtime_handler(
            'componentDataDeleters',
            $componentId
        );
        $tables = red_addon_component_editor_deleter_tables($componentId);
        if (!is_string($deleterOwner)
            || !hash_equals($packageId, $deleterOwner)
            || !is_callable($deleter)
            || !is_array($tables)
        ) {
            $result['reason'] = 'deleter_unavailable';
            return $result;
        }
        $result['transactionTables'] = $tables;

        $parent = red_addon_component_editor_parent_state(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId
        );
        $result['viewPermission'] = is_string(
            $parent['viewPermission'] ?? null
        ) ? $parent['viewPermission'] : '';
        if (empty($parent['loaded'])) {
            $result['reason'] = (string) (
                $parent['reason'] ?? 'parent_state_unavailable'
            );
            return $result;
        }
        $parentStateHash = is_string($parent['stateHash'] ?? null)
            ? $parent['stateHash']
            : '';
        $packageStateHash = is_string($parent['packageStateHash'] ?? null)
            ? $parent['packageStateHash']
            : '';
        if (!hash_equals($expectedParentStateHash, $parentStateHash)) {
            $result['reason'] = 'stale_parent_state';
            return $result;
        }
        if (!hash_equals($expectedPackageStateHash, $packageStateHash)) {
            $result['reason'] = 'stale_package_state';
            return $result;
        }

        if (!red_admin_transaction_tables_supported(
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
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }

        $history = red_addon_component_revision_history(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            1
        );
        $latest = is_array($history[0] ?? null) ? $history[0] : null;
        if (!is_array($latest)
            || !hash_equals($packageStateHash, $latest['stateHash'])
        ) {
            $result['reason'] = 'revision_state_unavailable';
            return $result;
        }

        $plan = [
            'schema' => 1,
            'package' => $packageId,
            'component' => $componentId,
            'contentRecordId' => (string) $contentRecordId,
            'actorRecordId' => (string) $adminRecordId,
            'viewPermission' => $result['viewPermission'],
            'deletePermission' => $result['deletePermission'],
            'parentStateHash' => $parentStateHash,
            'packageStateHash' => $packageStateHash,
            'packageRevisionId' => (string) $latest['revisionId'],
            'transactionTables' => $tables,
        ];
        $planHash = red_addon_component_editor_delete_plan_hash($plan);
        if ($planHash === '') {
            $result['reason'] = 'plan_unavailable';
            return $result;
        }

        $result['ready'] = true;
        $result['parentStateHash'] = $parentStateHash;
        $result['packageStateHash'] = $packageStateHash;
        $result['packageRevisionId'] = $latest['revisionId'];
        $result['planHash'] = $planHash;
        $result['reason'] = 'ready';
        return $result;
    }
}
