<?php
/**
 * Core-owned parent metadata for add-on component editor records.
 *
 * These activation-blocked helpers expose no route or form. Read access
 * requires the exact package view permission, enabled runtime ownership, an
 * inactive hidden parent shell, a valid package loader result, and current
 * core revision evidence. The atomic writer additionally requires the exact
 * edit permission and caller-supplied state hash, then changes only Title,
 * Layout, and Language under the lifecycle and theme locks.
 */

require_once __DIR__ . '/addon_component_editor_create_helpers.php';
require_once __DIR__ . '/addon_component_editor_write_helpers.php';

if (!function_exists('red_addon_component_editor_parent_result')) {
    function red_addon_component_editor_parent_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $reason
    ) {
        return [
            'loaded' => false,
            'updated' => false,
            'unchanged' => false,
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
            'editPermission' => '',
            'parentValues' => [],
            'previousStateHash' => '',
            'stateHash' => '',
            'packageStateHash' => '',
            'revisionId' => 0,
            'revisionNumber' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_parent_metadata_values')) {
    function red_addon_component_editor_parent_metadata_values(
        $connection,
        $componentId,
        $contentRecordId,
        $metadata
    ) {
        $parent = red_addon_component_editor_create_parent_values(
            $connection,
            $componentId,
            $contentRecordId,
            $metadata
        );
        if (!is_array($parent)) {
            return null;
        }
        return [
            'title' => $parent['Title'],
            'layout' => $parent['Layout'],
            'language' => $parent['Language'],
        ];
    }
}

if (!function_exists('red_addon_component_editor_parent_shell')) {
    function red_addon_component_editor_parent_shell(
        $connection,
        $componentId,
        $contentRecordId
    ) {
        $row = red_admin_article_full_record($connection, $contentRecordId);
        if (!is_array($row)) {
            return null;
        }
        $defaults = red_admin_article_default_insert_data($contentRecordId);
        $allowed = [
            'Title' => true,
            'Component' => true,
            'Layout' => true,
            'PagePosition' => true,
            'Active' => true,
            'Language' => true,
        ];
        foreach ($defaults as $fieldName => $defaultValue) {
            if (isset($allowed[$fieldName])) {
                continue;
            }
            if (!array_key_exists($fieldName, $row)
                || (string) $row[$fieldName] !== (string) $defaultValue
            ) {
                return null;
            }
        }
        if ((int) ($row['RecordID'] ?? 0) !== $contentRecordId
            || !is_string($row['Component'] ?? null)
            || !hash_equals($componentId, $row['Component'])
            || (string) ($row['Active'] ?? '') !== 'N'
            || (int) ($row['PagePosition'] ?? -1) !== 0
        ) {
            return null;
        }
        $metadata = [
            'title' => (string) ($row['Title'] ?? ''),
            'layout' => (string) ($row['Layout'] ?? ''),
            'language' => (string) ($row['Language'] ?? ''),
        ];
        $normalized = red_addon_component_editor_parent_metadata_values(
            $connection,
            $componentId,
            $contentRecordId,
            $metadata
        );
        if (!is_array($normalized) || $normalized !== $metadata) {
            return null;
        }
        $filtered = [];
        foreach (array_keys($defaults) as $fieldName) {
            if (!array_key_exists($fieldName, $row)) {
                return null;
            }
            $filtered[$fieldName] = isset(
                red_admin_article_integer_columns()[$fieldName]
            ) ? (int) $row[$fieldName] : (string) $row[$fieldName];
        }
        return [
            'row' => $filtered,
            'metadata' => $metadata,
        ];
    }
}

if (!function_exists('red_addon_component_editor_parent_state')) {
    function red_addon_component_editor_parent_state(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
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
        $result = red_addon_component_editor_parent_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $adminRecordId === false
                ? 'invalid_actor'
                : ($contentRecordId === false
                    ? 'invalid_content_record'
                    : 'schema_unavailable')
        );
        if ($adminRecordId === false || $contentRecordId === false) {
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
            return $result;
        }
        $result['package'] = $packageId;

        $authorization = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'view',
            $adminRecordId
        );
        $result['viewPermission'] = is_string(
            $authorization['permission'] ?? null
        ) ? $authorization['permission'] : '';
        if (empty($authorization['authorized'])) {
            $result['reason'] = (string) (
                $authorization['reason'] ?? 'permission_denied'
            );
            return $result;
        }
        if (red_addon_runtime_manifest($packageId) !== $manifest) {
            $result['reason'] = 'manifest_mismatch';
            return $result;
        }
        $owner = red_addon_runtime_owner('components', $componentId);
        if (!is_string($owner) || !hash_equals($packageId, $owner)) {
            $result['reason'] = 'runtime_binding_unavailable';
            return $result;
        }
        $binding = red_addon_component_persistence_binding(
            $connection,
            $contentRecordId,
            $componentId
        );
        if (!is_array($binding)
            || !is_string($binding['package'] ?? null)
            || !hash_equals($packageId, $binding['package'])
        ) {
            $result['reason'] = 'binding_unavailable';
            return $result;
        }
        $shell = red_addon_component_editor_parent_shell(
            $connection,
            $componentId,
            $contentRecordId
        );
        if (!is_array($shell)) {
            $result['reason'] = 'parent_state_unsupported';
            return $result;
        }
        $package = red_addon_component_editor_load_values(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId
        );
        if (empty($package['loaded'])) {
            $result['reason'] = ($package['reason'] ?? '')
                === 'permission_denied'
                    ? 'permission_denied'
                    : 'package_state_unavailable';
            return $result;
        }
        $snapshot = red_admin_content_revision_capture(
            $connection,
            $contentRecordId
        );
        $stateHash = is_array($snapshot)
            ? red_admin_content_revision_hash($snapshot)
            : '';
        $latest = red_admin_content_revision_latest(
            $connection,
            $contentRecordId
        );
        if (!red_addon_component_editor_state_hash_valid($stateHash)
            || !is_array($latest)
            || !is_string($latest['SnapshotHash'] ?? null)
            || !hash_equals($stateHash, $latest['SnapshotHash'])
        ) {
            $result['reason'] = 'revision_state_unavailable';
            return $result;
        }

        $result['loaded'] = true;
        $result['parentValues'] = $shell['metadata'];
        $result['stateHash'] = $stateHash;
        $result['packageStateHash'] = (string) $package['stateHash'];
        $result['reason'] = 'loaded';
        return $result;
    }
}

if (!function_exists('red_addon_component_editor_parent_update_row')) {
    function red_addon_component_editor_parent_update_row(
        $connection,
        $componentId,
        $contentRecordId,
        array $metadata
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE RED_Articles
                 SET Title=?, Layout=?, Language=?
                 WHERE RecordID=? AND Component=?
                   AND Active='N' AND PagePosition=0"
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'sssis',
                $metadata['title'],
                $metadata['layout'],
                $metadata['language'],
                $contentRecordId,
                $componentId
            );
            $executed = mysqli_stmt_execute($statement);
            $matched = $executed && mysqli_stmt_affected_rows($statement) === 1;
            mysqli_stmt_close($statement);
            return $matched;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_parent_revision')) {
    function red_addon_component_editor_parent_revision(
        $connection,
        $contentRecordId,
        $adminRecordId
    ) {
        $latest = red_admin_content_revision_latest(
            $connection,
            $contentRecordId
        );
        $snapshot = red_admin_content_revision_capture(
            $connection,
            $contentRecordId
        );
        $json = is_array($snapshot)
            ? red_admin_content_revision_json($snapshot)
            : '';
        $snapshotHash = is_array($snapshot)
            ? red_admin_content_revision_hash($snapshot)
            : '';
        $actorAlias = red_addon_component_revision_actor_alias(
            $connection,
            $adminRecordId
        );
        $contentType = is_array($snapshot)
            ? substr((string) ($snapshot['type'] ?? ''), 0, 50)
            : '';
        if (!is_array($latest)
            || $json === ''
            || !red_addon_component_editor_state_hash_valid($snapshotHash)
            || !is_string($actorAlias)
            || $contentType === ''
        ) {
            return null;
        }
        $revisionNumber = (int) ($latest['RevisionNumber'] ?? 0) + 1;
        if ($revisionNumber < 2
            || hash_equals((string) ($latest['SnapshotHash'] ?? ''), $snapshotHash)
        ) {
            return null;
        }
        $operation = 'save';
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Content_Revisions (
                    ContentRecordID, ContentType, RevisionNumber, Operation,
                    ActorAdminRecordID, ActorAlias, Snapshot, SnapshotHash,
                    RestoredFromRevisionID
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)'
            );
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param(
                $statement,
                'isisisss',
                $contentRecordId,
                $contentType,
                $revisionNumber,
                $operation,
                $adminRecordId,
                $actorAlias,
                $json,
                $snapshotHash
            );
            $inserted = mysqli_stmt_execute($statement);
            $revisionId = $inserted
                ? (int) mysqli_insert_id($connection)
                : 0;
            mysqli_stmt_close($statement);
            return $revisionId > 0 ? [
                'revisionId' => $revisionId,
                'revisionNumber' => $revisionNumber,
                'stateHash' => $snapshotHash,
            ] : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_component_editor_parent_update')) {
    function red_addon_component_editor_parent_update(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $expectedStateHash,
        $metadata
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
        $result = red_addon_component_editor_parent_result(
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
                $expectedStateHash
            )
        ) {
            return $result;
        }
        $result['previousStateHash'] = $expectedStateHash;
        if (!red_admin_transaction_tables_supported(
            $connection,
            ['RED_Articles', 'RED_Content_Revisions']
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        if (red_addon_component_editor_create_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        $edit = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'edit',
            $adminRecordId
        );
        $result['editPermission'] = is_string($edit['permission'] ?? null)
            ? $edit['permission']
            : '';
        if (empty($edit['authorized'])) {
            $result['reason'] = (string) (
                $edit['reason'] ?? 'permission_denied'
            );
            return $result;
        }
        $candidate = red_addon_component_editor_parent_metadata_values(
            $connection,
            $componentId,
            $contentRecordId,
            $metadata
        );
        if (!is_array($candidate)) {
            $result['reason'] = 'invalid_parent_values';
            return $result;
        }
        $initial = red_addon_component_editor_parent_state(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId
        );
        $result['package'] = (string) ($initial['package'] ?? '');
        $result['viewPermission'] = (string) (
            $initial['viewPermission'] ?? ''
        );
        if (empty($initial['loaded'])) {
            $result['reason'] = (string) (
                $initial['reason'] ?? 'current_state_unavailable'
            );
            return $result;
        }
        if (!hash_equals($initial['stateHash'], $expectedStateHash)) {
            $result['reason'] = 'stale_state';
            return $result;
        }
        $packageId = (string) $initial['package'];
        if (!red_addon_lifecycle_lock($connection)) {
            $result['reason'] = 'lifecycle_lock_failed';
            return $result;
        }

        $transactionReason = 'transaction_failed';
        $lockedResult = null;
        try {
            $lockedResult = red_admin_with_theme_contract_lock(
                $connection,
                function () use (
                    $connection,
                    $manifest,
                    $componentId,
                    $contentRecordId,
                    $adminRecordId,
                    $expectedStateHash,
                    $candidate,
                    $packageId,
                    &$transactionReason
                ) {
                    if (!mysqli_begin_transaction($connection)) {
                        return null;
                    }
                    try {
                        if (!red_addon_component_editor_create_lock_installation(
                            $connection,
                            $packageId
                        ) || !red_addon_component_editor_lock_binding(
                            $connection,
                            $packageId,
                            $componentId,
                            $contentRecordId
                        )) {
                            $transactionReason = 'binding_unavailable';
                            throw new RuntimeException($transactionReason);
                        }
                        $current = red_addon_component_editor_parent_state(
                            $connection,
                            $manifest,
                            $componentId,
                            $contentRecordId,
                            $adminRecordId
                        );
                        if (empty($current['loaded'])) {
                            $transactionReason = (string) (
                                $current['reason'] ?? 'current_state_unavailable'
                            );
                            throw new RuntimeException($transactionReason);
                        }
                        if (!hash_equals(
                            $current['stateHash'],
                            $expectedStateHash
                        )) {
                            $transactionReason = 'stale_state';
                            throw new RuntimeException($transactionReason);
                        }
                        $edit = red_addon_component_editor_permission_decision(
                            $connection,
                            $manifest,
                            $componentId,
                            'edit',
                            $adminRecordId
                        );
                        if (empty($edit['authorized'])) {
                            $transactionReason = 'permission_denied';
                            throw new RuntimeException($transactionReason);
                        }
                        $normalized =
                            red_addon_component_editor_parent_metadata_values(
                                $connection,
                                $componentId,
                                $contentRecordId,
                                $candidate
                            );
                        if (!is_array($normalized)) {
                            $transactionReason = 'invalid_parent_values';
                            throw new RuntimeException($transactionReason);
                        }
                        if ($current['parentValues'] === $normalized) {
                            if (!mysqli_commit($connection)) {
                                $transactionReason = 'transaction_failed';
                                throw new RuntimeException($transactionReason);
                            }
                            return [
                                'unchanged' => true,
                                'permission' => (string) $edit['permission'],
                                'parentValues' => $current['parentValues'],
                                'stateHash' => $current['stateHash'],
                                'packageStateHash' =>
                                    $current['packageStateHash'],
                                'revisionId' => 0,
                                'revisionNumber' => 0,
                            ];
                        }
                        $beforeShell = red_addon_component_editor_parent_shell(
                            $connection,
                            $componentId,
                            $contentRecordId
                        );
                        if (!is_array($beforeShell)
                            || !red_addon_component_editor_parent_update_row(
                                $connection,
                                $componentId,
                                $contentRecordId,
                                $normalized
                            )
                        ) {
                            $transactionReason = 'parent_update_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            $transactionReason = 'transaction_lost';
                            throw new RuntimeException($transactionReason);
                        }
                        $afterShell = red_addon_component_editor_parent_shell(
                            $connection,
                            $componentId,
                            $contentRecordId
                        );
                        $expectedRow = $beforeShell['row'];
                        $expectedRow['Title'] = $normalized['title'];
                        $expectedRow['Layout'] = $normalized['layout'];
                        $expectedRow['Language'] = $normalized['language'];
                        if (!is_array($afterShell)
                            || $afterShell['metadata'] !== $normalized
                            || $afterShell['row'] !== $expectedRow
                        ) {
                            $transactionReason = 'parent_postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $package = red_addon_component_editor_load_values(
                            $connection,
                            $manifest,
                            $componentId,
                            $contentRecordId,
                            $adminRecordId
                        );
                        if (empty($package['loaded'])
                            || !hash_equals(
                                $current['packageStateHash'],
                                (string) ($package['stateHash'] ?? '')
                            )
                        ) {
                            $transactionReason = 'package_postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $revision = red_addon_component_editor_parent_revision(
                            $connection,
                            $contentRecordId,
                            $adminRecordId
                        );
                        if (!is_array($revision)) {
                            $transactionReason = 'revision_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        ) || !mysqli_commit($connection)) {
                            $transactionReason = 'transaction_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        return [
                            'unchanged' => false,
                            'permission' => (string) $edit['permission'],
                            'parentValues' => $normalized,
                            'stateHash' => $revision['stateHash'],
                            'packageStateHash' => $package['stateHash'],
                            'revisionId' => $revision['revisionId'],
                            'revisionNumber' => $revision['revisionNumber'],
                        ];
                    } catch (Throwable $throwable) {
                        if (red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            mysqli_rollback($connection);
                        }
                        return null;
                    }
                }
            );
        } finally {
            red_addon_lifecycle_unlock($connection);
        }
        if (!is_array($lockedResult)) {
            $result['reason'] = $lockedResult === false
                && $transactionReason === 'transaction_failed'
                    ? 'theme_lock_failed'
                    : $transactionReason;
            return $result;
        }
        $result['loaded'] = true;
        $result['updated'] = empty($lockedResult['unchanged']);
        $result['unchanged'] = !empty($lockedResult['unchanged']);
        $result['editPermission'] = $lockedResult['permission'];
        $result['parentValues'] = $lockedResult['parentValues'];
        $result['stateHash'] = $lockedResult['stateHash'];
        $result['packageStateHash'] = $lockedResult['packageStateHash'];
        $result['revisionId'] = $lockedResult['revisionId'];
        $result['revisionNumber'] = $lockedResult['revisionNumber'];
        $result['reason'] = $result['unchanged'] ? 'unchanged' : 'updated';
        return $result;
    }
}

?>
