<?php
/**
 * Fresh database-backed package-permission decisions for component editors.
 *
 * This helper resolves one fixed editor operation to the exact permission in
 * a validated manifest and requires that exact grant for the current client
 * administrator. It does not grant permissions, infer Owner access, authorize
 * package activation, execute package code, render an endpoint, or write
 * application state.
 */

require_once __DIR__ . '/addon_component_editor_helpers.php';

if (!function_exists('red_addon_component_editor_operations')) {
    function red_addon_component_editor_operations()
    {
        return [
            'create',
            'view',
            'edit',
            'delete',
            'publish',
            'restore',
        ];
    }
}

if (!function_exists('red_addon_component_editor_permission_plan')) {
    function red_addon_component_editor_permission_plan(
        array $manifest,
        $componentId,
        $operation
    ) {
        if (!is_string($componentId)
            || !is_string($operation)
            || !in_array(
                $operation,
                red_addon_component_editor_operations(),
                true
            )
        ) {
            return null;
        }
        $schema = red_addon_component_editor_schema($manifest, $componentId);
        if (!is_array($schema)
            || !isset($schema['permissions'])
            || !is_array($schema['permissions'])
        ) {
            return null;
        }
        $permission = $schema['permissions'][$operation] ?? null;
        if (!is_string($permission)
            || !red_addon_valid_permission($permission)
        ) {
            return null;
        }
        return [
            'component' => $componentId,
            'operation' => $operation,
            'permission' => $permission,
        ];
    }
}

if (!function_exists('red_addon_component_editor_permission_storage_available')) {
    function red_addon_component_editor_permission_storage_available(
        $connection
    ) {
        if (!$connection) {
            return false;
        }
        try {
            $result = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(
                    ':',
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME IN (
                         'RED_Admin',
                         'RED_Admin_Capabilities'
                       )),
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Admin_Capabilities'
                       AND COLUMN_NAME='Capability'
                       AND DATA_TYPE='varchar'
                       AND CHARACTER_MAXIMUM_LENGTH>=160
                       AND IS_NULLABLE='NO'),
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Admin_Capabilities'
                       AND CONSTRAINT_NAME='fk_red_admin_capabilities_admin'
                       AND REFERENCED_TABLE_NAME='RED_Admin'
                       AND DELETE_RULE='CASCADE'
                       AND UPDATE_RULE='RESTRICT')
                 ) AS StorageState"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return (string) ($row['StorageState'] ?? '') === '2:1:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_actor_has_permission')) {
    function red_addon_component_editor_actor_has_permission(
        $connection,
        $adminRecordId,
        $permission
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($adminRecordId === false
            || !is_string($permission)
            || !red_addon_valid_permission($permission)
            || !red_addon_component_editor_permission_storage_available(
                $connection
            )
        ) {
            return false;
        }

        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT c.Capability
                 FROM RED_Admin a
                 INNER JOIN RED_Admin_Capabilities c
                   ON c.AdminRecordID=a.RecordID
                 WHERE a.RecordID=?
                   AND BINARY c.Capability=BINARY ?
                 LIMIT 1'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'is',
                $adminRecordId,
                $permission
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return false;
            }
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            return false;
        }

        return isset($row['Capability'])
            && is_string($row['Capability'])
            && hash_equals($permission, $row['Capability']);
    }
}

if (!function_exists('red_addon_component_editor_permission_result')) {
    function red_addon_component_editor_permission_result(
        $adminRecordId,
        $componentId,
        $operation,
        $permission = '',
        $reason = 'permission_denied'
    ) {
        return [
            'authorized' => false,
            'actorRecordId' => is_int($adminRecordId)
                ? $adminRecordId
                : 0,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'operation' => is_string($operation)
                && in_array(
                    $operation,
                    red_addon_component_editor_operations(),
                    true
                )
                    ? $operation
                    : '',
            'permission' => is_string($permission)
                && red_addon_valid_permission($permission)
                    ? $permission
                    : '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_permission_decision')) {
    function red_addon_component_editor_permission_decision(
        $connection,
        array $manifest,
        $componentId,
        $operation,
        $adminRecordId
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = red_addon_component_editor_permission_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $componentId,
            $operation,
            '',
            $adminRecordId === false ? 'invalid_actor' : 'schema_unavailable'
        );
        if ($adminRecordId === false) {
            return $result;
        }

        $plan = red_addon_component_editor_permission_plan(
            $manifest,
            $componentId,
            $operation
        );
        if (!is_array($plan)) {
            if (!is_string($operation)
                || !in_array(
                    $operation,
                    red_addon_component_editor_operations(),
                    true
                )
            ) {
                $result['reason'] = 'invalid_operation';
            }
            return $result;
        }
        $result['component'] = $plan['component'];
        $result['operation'] = $plan['operation'];
        $result['permission'] = $plan['permission'];

        if (!red_addon_component_editor_permission_storage_available(
            $connection
        )) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        if (!red_addon_component_editor_actor_has_permission(
            $connection,
            $adminRecordId,
            $plan['permission']
        )) {
            $result['reason'] = 'permission_denied';
            return $result;
        }

        $result['authorized'] = true;
        $result['reason'] = 'authorized';
        return $result;
    }
}
