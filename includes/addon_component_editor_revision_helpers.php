<?php
/**
 * Immutable core-owned snapshots for add-on component editor values.
 *
 * This helper records normalized package values only. It exposes no endpoint,
 * history UI, restore, delete, activation, or package lifecycle behavior.
 */

require_once __DIR__ . '/addon_component_editor_data_helpers.php';

if (!function_exists('red_addon_component_revision_table_available')) {
    function red_addon_component_revision_table_available($connection)
    {
        if (!($connection instanceof mysqli)) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Component_Revisions'
                   AND ENGINE='InnoDB'"
            );
            if (!$statement || !mysqli_stmt_execute($statement)) {
                if ($statement) {
                    mysqli_stmt_close($statement);
                }
                return false;
            }
            $count = 0;
            mysqli_stmt_bind_result($statement, $count);
            $available = mysqli_stmt_fetch($statement) === true
                && (int) $count === 1;
            mysqli_stmt_close($statement);
            return $available;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_revision_snapshot')) {
    function red_addon_component_revision_snapshot(
        $packageId,
        $componentId,
        $contentRecordId,
        array $values
    ) {
        $stateHash = red_addon_component_editor_data_hash(
            $packageId,
            $componentId,
            $contentRecordId,
            $values
        );
        if ($stateHash === '') {
            return null;
        }
        return [
            'schema' => 1,
            'package' => $packageId,
            'component' => $componentId,
            'contentRecordId' => (string) $contentRecordId,
            'values' => $values,
            'stateHash' => $stateHash,
        ];
    }
}

if (!function_exists('red_addon_component_revision_json')) {
    function red_addon_component_revision_json(array $snapshot)
    {
        $json = json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($json) ? $json : '';
    }
}

if (!function_exists('red_addon_component_revision_latest')) {
    function red_addon_component_revision_latest($connection, $contentRecordId)
    {
        if (!red_addon_component_revision_table_available($connection)) {
            return null;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RevisionID, RevisionNumber, PackageID, ComponentID,
                        Operation, ActorAdminRecordID, ActorAlias, Snapshot,
                        StateHash, RestoredFromRevisionID, CreatedAt
                 FROM RED_Addon_Component_Revisions
                 WHERE ContentRecordID=?
                 ORDER BY RevisionNumber DESC LIMIT 1'
            );
            if (!$statement) {
                return null;
            }
            $contentRecordId = (int) $contentRecordId;
            mysqli_stmt_bind_param($statement, 'i', $contentRecordId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return null;
            }
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
            return is_array($row) ? $row : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_component_revision_actor_alias')) {
    function red_addon_component_revision_actor_alias(
        $connection,
        $adminRecordId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT Alias FROM RED_Admin WHERE RecordID=? LIMIT 1'
            );
            if (!$statement) {
                return null;
            }
            $adminRecordId = (int) $adminRecordId;
            mysqli_stmt_bind_param($statement, 'i', $adminRecordId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return null;
            }
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
            return is_array($row) && is_string($row['Alias'] ?? null)
                ? substr($row['Alias'], 0, 50)
                : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_component_revision_record')) {
    function red_addon_component_revision_record(
        $connection,
        $packageId,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        array $values,
        $operation
    ) {
        $contentRecordId = filter_var(
            $contentRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($contentRecordId === false
            || $adminRecordId === false
            || !is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || !is_string($componentId)
            || !red_addon_valid_capability($componentId)
            || !in_array($operation, ['baseline', 'checkpoint', 'save'], true)
            || !red_addon_component_revision_table_available($connection)
        ) {
            return null;
        }

        $snapshot = red_addon_component_revision_snapshot(
            $packageId,
            $componentId,
            $contentRecordId,
            $values
        );
        $json = is_array($snapshot)
            ? red_addon_component_revision_json($snapshot)
            : '';
        $stateHash = is_array($snapshot)
            ? (string) ($snapshot['stateHash'] ?? '')
            : '';
        $actorAlias = red_addon_component_revision_actor_alias(
            $connection,
            $adminRecordId
        );
        if ($json === ''
            || preg_match('/\A[a-f0-9]{64}\z/', $stateHash) !== 1
            || !is_string($actorAlias)
        ) {
            return null;
        }

        $latest = red_addon_component_revision_latest(
            $connection,
            $contentRecordId
        );
        if (is_array($latest)
            && is_string($latest['StateHash'] ?? null)
            && hash_equals($latest['StateHash'], $stateHash)
        ) {
            return [
                'recorded' => true,
                'inserted' => false,
                'revisionId' => (int) $latest['RevisionID'],
                'revisionNumber' => (int) $latest['RevisionNumber'],
                'operation' => (string) $latest['Operation'],
                'stateHash' => $stateHash,
            ];
        }

        $revisionNumber = is_array($latest)
            ? ((int) ($latest['RevisionNumber'] ?? 0) + 1)
            : 1;
        $operation = is_array($latest) ? $operation : 'baseline';
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Addon_Component_Revisions (
                    ContentRecordID, PackageID, ComponentID, RevisionNumber,
                    Operation, ActorAdminRecordID, ActorAlias, Snapshot,
                    StateHash, RestoredFromRevisionID
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)'
            );
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param(
                $statement,
                'issisisss',
                $contentRecordId,
                $packageId,
                $componentId,
                $revisionNumber,
                $operation,
                $adminRecordId,
                $actorAlias,
                $json,
                $stateHash
            );
            $inserted = mysqli_stmt_execute($statement);
            $revisionId = $inserted
                ? (int) mysqli_insert_id($connection)
                : 0;
            mysqli_stmt_close($statement);
            if (!$inserted || $revisionId < 1) {
                return null;
            }
            return [
                'recorded' => true,
                'inserted' => true,
                'revisionId' => $revisionId,
                'revisionNumber' => $revisionNumber,
                'operation' => $operation,
                'stateHash' => $stateHash,
            ];
        } catch (Throwable $throwable) {
            return null;
        }
    }
}
