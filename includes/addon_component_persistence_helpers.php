<?php
/**
 * Read-only package component parent-binding resolution.
 *
 * RED_Articles remains the core-owned placement parent. Package-owned tables
 * may reference its numeric RecordID, but this helper never selects a package
 * table or executes package persistence behavior.
 */

require_once __DIR__ . '/addon_runtime_helpers.php';

if (!function_exists('red_addon_component_persistence_storage_available')) {
    function red_addon_component_persistence_storage_available($connection)
    {
        if (!$connection) {
            return false;
        }

        try {
            $result = mysqli_query(
                $connection,
                "SELECT COUNT(*) AS ColumnCount
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Articles'
                   AND (
                     (COLUMN_NAME='RecordID'
                       AND DATA_TYPE='int'
                       AND COLUMN_TYPE LIKE 'int% unsigned'
                       AND IS_NULLABLE='NO')
                     OR
                     (COLUMN_NAME='Component'
                       AND DATA_TYPE='varchar'
                       AND CHARACTER_MAXIMUM_LENGTH>=160
                       AND IS_NULLABLE='NO')
                   )"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return (int) ($row['ColumnCount'] ?? 0) === 2
                && red_addon_registry_storage_available($connection);
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_persistence_binding')) {
    function red_addon_component_persistence_binding(
        $connection,
        $contentRecordId,
        $componentId
    ) {
        if (!red_addon_component_persistence_storage_available($connection)
            || !is_string($componentId)
            || !red_addon_valid_capability($componentId)
        ) {
            return null;
        }

        $contentRecordId = filter_var(
            $contentRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $packageId = red_addon_runtime_owner('components', $componentId);
        if ($contentRecordId === false
            || !is_string($packageId)
            || !red_addon_valid_package_id($packageId)
        ) {
            return null;
        }

        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT a.RecordID, a.Component
                 FROM RED_Articles a
                 INNER JOIN RED_Addon_Installations i
                   ON i.PackageID=?
                  AND i.LifecycleState=\'enabled\'
                 WHERE a.RecordID=?
                   AND a.Component=?
                 LIMIT 1'
            );
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param(
                $statement,
                'sis',
                $packageId,
                $contentRecordId,
                $componentId
            );
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            return null;
        }

        if ((int) ($row['RecordID'] ?? 0) !== (int) $contentRecordId
            || !isset($row['Component'])
            || !is_string($row['Component'])
            || !hash_equals($componentId, $row['Component'])
        ) {
            return null;
        }

        return [
            'contentRecordId' => (int) $contentRecordId,
            'component' => $componentId,
            'package' => $packageId,
        ];
    }
}
