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
        $operation,
        $restoredFromRevisionId = null
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
            || !in_array(
                $operation,
                ['baseline', 'checkpoint', 'save', 'restore'],
                true
            )
            || !red_addon_component_revision_table_available($connection)
        ) {
            return null;
        }
        if ($operation === 'restore') {
            $restoredFromRevisionId = filter_var(
                $restoredFromRevisionId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($restoredFromRevisionId === false) {
                return null;
            }
        } elseif ($restoredFromRevisionId !== null) {
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
            && (!is_string($latest['PackageID'] ?? null)
                || !hash_equals($packageId, $latest['PackageID'])
                || !is_string($latest['ComponentID'] ?? null)
                || !hash_equals($componentId, $latest['ComponentID']))
        ) {
            return null;
        }
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

        if (!is_array($latest) && $operation === 'restore') {
            return null;
        }
        $revisionNumber = is_array($latest)
            ? ((int) ($latest['RevisionNumber'] ?? 0) + 1)
            : 1;
        $operation = is_array($latest) ? $operation : 'baseline';
        try {
            $sql = 'INSERT INTO RED_Addon_Component_Revisions (
                        ContentRecordID, PackageID, ComponentID, RevisionNumber,
                        Operation, ActorAdminRecordID, ActorAlias, Snapshot,
                        StateHash, RestoredFromRevisionID
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)';
            if ($operation === 'restore') {
                $sql = 'INSERT INTO RED_Addon_Component_Revisions (
                            ContentRecordID, PackageID, ComponentID,
                            RevisionNumber, Operation, ActorAdminRecordID,
                            ActorAlias, Snapshot, StateHash,
                            RestoredFromRevisionID
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            }
            $statement = mysqli_prepare($connection, $sql);
            if (!$statement) {
                return null;
            }
            if ($operation === 'restore') {
                mysqli_stmt_bind_param(
                    $statement,
                    'issisisssi',
                    $contentRecordId,
                    $packageId,
                    $componentId,
                    $revisionNumber,
                    $operation,
                    $adminRecordId,
                    $actorAlias,
                    $json,
                    $stateHash,
                    $restoredFromRevisionId
                );
            } else {
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
            }
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
                'restoredFromRevisionId' => $operation === 'restore'
                    ? $restoredFromRevisionId
                    : 0,
            ];
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_component_revision_validated_row')) {
    function red_addon_component_revision_validated_row(
        array $manifest,
        $componentId,
        $contentRecordId,
        array $row
    ) {
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        $operation = is_string($row['Operation'] ?? null)
            ? $row['Operation']
            : '';
        $restoredFromRevisionId = (int) (
            $row['RestoredFromRevisionID'] ?? 0
        );
        $snapshot = isset($row['Snapshot']) && is_string($row['Snapshot'])
            ? json_decode($row['Snapshot'], true)
            : null;
        if (!is_array($snapshot)
            || array_keys($snapshot) !== [
                'schema',
                'package',
                'component',
                'contentRecordId',
                'values',
                'stateHash',
            ]
            || $snapshot['schema'] !== 1
            || $snapshot['package'] !== $packageId
            || $snapshot['component'] !== $componentId
            || $snapshot['contentRecordId'] !== (string) $contentRecordId
            || !is_array($snapshot['values'])
            || !is_string($snapshot['stateHash'])
            || !is_string($row['PackageID'] ?? null)
            || !hash_equals($packageId, $row['PackageID'])
            || !is_string($row['ComponentID'] ?? null)
            || !hash_equals($componentId, $row['ComponentID'])
            || (int) ($row['ContentRecordID'] ?? 0) !== $contentRecordId
            || !is_string($row['StateHash'] ?? null)
            || !hash_equals($snapshot['stateHash'], $row['StateHash'])
            || (int) ($row['RevisionID'] ?? 0) < 1
            || (int) ($row['RevisionNumber'] ?? 0) < 1
            || !in_array(
                $operation,
                ['baseline', 'checkpoint', 'save', 'restore'],
                true
            )
            || ($operation === 'restore'
                ? $restoredFromRevisionId < 1
                : $restoredFromRevisionId !== 0)
            || (int) ($row['ActorAdminRecordID'] ?? 0) < 1
            || !is_string($row['ActorAlias'] ?? null)
            || !is_string($row['CreatedAt'] ?? null)
            || $row['CreatedAt'] === ''
        ) {
            return null;
        }
        $validated = red_addon_component_editor_validate_values(
            $manifest,
            $componentId,
            $snapshot['values']
        );
        $calculated = red_addon_component_editor_data_hash(
            $packageId,
            $componentId,
            $contentRecordId,
            $snapshot['values']
        );
        if (empty($validated['valid'])
            || ($validated['values'] ?? null) !== $snapshot['values']
            || $calculated === ''
            || !hash_equals($calculated, $snapshot['stateHash'])
        ) {
            return null;
        }
        return [
            'revisionId' => (int) ($row['RevisionID'] ?? 0),
            'revisionNumber' => (int) ($row['RevisionNumber'] ?? 0),
            'operation' => $operation,
            'actorRecordId' => (int) ($row['ActorAdminRecordID'] ?? 0),
            'actorAlias' => (string) ($row['ActorAlias'] ?? ''),
            'stateHash' => $snapshot['stateHash'],
            'values' => $snapshot['values'],
            'restoredFromRevisionId' => $restoredFromRevisionId,
            'createdAt' => (string) ($row['CreatedAt'] ?? ''),
        ];
    }
}

if (!function_exists('red_addon_component_revision_history')) {
    function red_addon_component_revision_history(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $limit = 25
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
        $limit = filter_var(
            $limit,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 50]]
        );
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if ($contentRecordId === false
            || $adminRecordId === false
            || $limit === false
            || !red_addon_component_revision_table_available($connection)
            || red_addon_runtime_manifest($packageId) !== $manifest
        ) {
            return [];
        }
        $authorization = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'view',
            $adminRecordId
        );
        $binding = red_addon_component_persistence_binding(
            $connection,
            $contentRecordId,
            $componentId
        );
        if (empty($authorization['authorized'])
            || !is_array($binding)
            || ($binding['package'] ?? '') !== $packageId
        ) {
            return [];
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RevisionID, ContentRecordID, PackageID, ComponentID,
                        RevisionNumber, Operation, ActorAdminRecordID,
                        ActorAlias, Snapshot, StateHash,
                        RestoredFromRevisionID, CreatedAt
                 FROM RED_Addon_Component_Revisions
                 WHERE ContentRecordID=? AND PackageID=? AND ComponentID=?
                 ORDER BY RevisionNumber DESC LIMIT ?'
            );
            if (!$statement) {
                return [];
            }
            mysqli_stmt_bind_param(
                $statement,
                'issi',
                $contentRecordId,
                $packageId,
                $componentId,
                $limit
            );
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);
            $history = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $validated = red_addon_component_revision_validated_row(
                    $manifest,
                    $componentId,
                    $contentRecordId,
                    $row
                );
                if (!is_array($validated)) {
                    $history = [];
                    break;
                }
                unset($validated['values']);
                $history[] = $validated;
            }
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
            return $history;
        } catch (Throwable $throwable) {
            return [];
        }
    }
}

if (!function_exists('red_addon_component_revision_restore_preflight')) {
    function red_addon_component_revision_restore_preflight(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $revisionId,
        $expectedCurrentStateHash
    ) {
        $result = [
            'ready' => false,
            'package' => '',
            'component' => '',
            'contentRecordId' => 0,
            'actorRecordId' => 0,
            'permission' => '',
            'revisionId' => 0,
            'revisionNumber' => 0,
            'currentStateHash' => '',
            'targetStateHash' => '',
            'targetValues' => [],
            'planHash' => '',
            'reason' => 'invalid_evidence',
        ];
        $contentRecordId = filter_var($contentRecordId, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        $adminRecordId = filter_var($adminRecordId, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        $revisionId = filter_var($revisionId, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if ($contentRecordId === false
            || $adminRecordId === false
            || $revisionId === false
            || !is_string($expectedCurrentStateHash)
            || preg_match('/\A[a-f0-9]{64}\z/', $expectedCurrentStateHash)
                !== 1
            || red_addon_runtime_manifest($packageId) !== $manifest
        ) {
            return $result;
        }
        $result['package'] = $packageId;
        $result['component'] = (string) $componentId;
        $result['contentRecordId'] = $contentRecordId;
        $result['actorRecordId'] = $adminRecordId;
        $authorization = red_addon_component_editor_permission_decision(
            $connection, $manifest, $componentId, 'restore', $adminRecordId
        );
        $result['permission'] = (string) ($authorization['permission'] ?? '');
        if (empty($authorization['authorized'])) {
            $result['reason'] = 'permission_denied';
            return $result;
        }
        $current = red_addon_component_editor_load_values(
            $connection, $manifest, $componentId, $contentRecordId,
            $adminRecordId
        );
        if (empty($current['loaded'])) {
            $result['reason'] = 'current_state_unavailable';
            return $result;
        }
        $result['currentStateHash'] = $current['stateHash'];
        if (!hash_equals($current['stateHash'], $expectedCurrentStateHash)) {
            $result['reason'] = 'stale_state';
            return $result;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RevisionID, ContentRecordID, PackageID, ComponentID,
                        RevisionNumber, Operation, ActorAdminRecordID,
                        ActorAlias, Snapshot, StateHash,
                        RestoredFromRevisionID, CreatedAt
                 FROM RED_Addon_Component_Revisions
                 WHERE RevisionID=? AND ContentRecordID=?
                   AND PackageID=? AND ComponentID=? LIMIT 1'
            );
            mysqli_stmt_bind_param(
                $statement, 'iiss', $revisionId, $contentRecordId,
                $packageId, $componentId
            );
            mysqli_stmt_execute($statement);
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $row = null;
        }
        $target = is_array($row)
            ? red_addon_component_revision_validated_row(
                $manifest, $componentId, $contentRecordId, $row
            )
            : null;
        if (!is_array($target)) {
            $result['reason'] = 'revision_unavailable';
            return $result;
        }
        $result['revisionId'] = $target['revisionId'];
        $result['revisionNumber'] = $target['revisionNumber'];
        $result['targetStateHash'] = $target['stateHash'];
        $result['targetValues'] = $target['values'];
        if (hash_equals($current['stateHash'], $target['stateHash'])) {
            $result['reason'] = 'already_current';
            return $result;
        }
        $plan = [
            'schema' => 1,
            'package' => $packageId,
            'component' => $componentId,
            'contentRecordId' => (string) $contentRecordId,
            'actorRecordId' => (string) $adminRecordId,
            'revisionId' => (string) $target['revisionId'],
            'revisionNumber' => (string) $target['revisionNumber'],
            'currentStateHash' => $current['stateHash'],
            'targetStateHash' => $target['stateHash'],
        ];
        $json = json_encode($plan, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            $result['reason'] = 'plan_failed';
            return $result;
        }
        $result['planHash'] = hash('sha256', $json);
        $result['ready'] = true;
        $result['reason'] = 'ready';
        return $result;
    }
}
