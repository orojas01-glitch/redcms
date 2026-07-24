<?php
/**
 * Webmaster-only write and history helpers for the visual Layout Builder.
 */

require_once __DIR__ . '/custom_layout_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';
require_once __DIR__ . '/theme_activation_helpers.php';
require_once __DIR__ . '/theme_compatibility_helpers.php';

if (!function_exists('red_admin_custom_layout_actor')) {
    function red_admin_custom_layout_actor()
    {
        return [
            'recordId' => max(0, (int) ($_SESSION['AdminRecordID'] ?? 0)),
            'alias' => red_custom_layout_text($_SESSION['alias'] ?? '', 50),
        ];
    }
}

if (!function_exists('red_admin_custom_layout_snapshot')) {
    function red_admin_custom_layout_snapshot(array $row)
    {
        return [
            'layoutId' => (string) ($row['LayoutID'] ?? ''),
            'draftLabel' => (string) ($row['DraftLabel'] ?? ''),
            'draftDefinition' => (string) ($row['DraftDefinition'] ?? ''),
            'draftHash' => (string) ($row['DraftHash'] ?? ''),
            'publishedLabel' => (string) ($row['PublishedLabel'] ?? ''),
            'publishedDefinition' => (string) ($row['PublishedDefinition'] ?? ''),
            'publishedHash' => (string) ($row['PublishedHash'] ?? ''),
            'archived' => (string) ($row['Archived'] ?? 'N'),
        ];
    }
}

if (!function_exists('red_admin_custom_layout_insert_revision')) {
    function red_admin_custom_layout_insert_revision(
        $connection,
        array $row,
        $operation,
        $restoredFromRevisionId = null
    ) {
        $actor = red_admin_custom_layout_actor();
        $layoutId = (string) ($row['LayoutID'] ?? '');
        $revisionNumber = (int) ($row['RevisionNumber'] ?? 0);
        $operation = red_custom_layout_text($operation, 16);
        if ($actor['recordId'] <= 0
            || !red_custom_layout_valid_id($layoutId)
            || $revisionNumber < 1
            || $operation === ''
        ) {
            return false;
        }

        $snapshot = red_custom_layout_json_encode(red_admin_custom_layout_snapshot($row));
        $snapshotHash = hash('sha256', $snapshot);
        $restoredFromRevisionId = $restoredFromRevisionId === null
            ? null
            : max(1, (int) $restoredFromRevisionId);

        try {
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Custom_Layout_Revisions ' .
                '(LayoutID, RevisionNumber, Operation, ActorAdminRecordID, ActorAlias, Snapshot, SnapshotHash, RestoredFromRevisionID) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'sisisssi',
                $layoutId,
                $revisionNumber,
                $operation,
                $actor['recordId'],
                $actor['alias'],
                $snapshot,
                $snapshotHash,
                $restoredFromRevisionId
            );
            $inserted = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $inserted;
        } catch (Throwable $exception) {
            error_log('Custom layout revision insert failed: ' . $exception->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_custom_layout_assignment_inventory')) {
    function red_admin_custom_layout_assignment_inventory($connection, $layoutId)
    {
        $layoutId = red_custom_layout_scalar($layoutId);
        $inventory = [
            'count' => 0,
            'sources' => [],
            'requiredPositions' => [],
        ];
        if (!red_custom_layout_valid_id($layoutId)) {
            return $inventory;
        }

        try {
            $compatibility = red_theme_compatibility_live_inventory($connection);
            $inventory['count'] = (int) ($compatibility['layouts']['assigned'][$layoutId] ?? 0);
            foreach (($compatibility['layouts']['sources'] ?? []) as $source => $layouts) {
                $count = is_array($layouts) ? (int) ($layouts[$layoutId] ?? 0) : 0;
                if ($count > 0) {
                    $inventory['sources'][(string) $source] = $count;
                }
            }
            $positions = array_keys($compatibility['layouts']['requiredPositions'][$layoutId] ?? []);
            $positions = array_values(array_filter(array_map('intval', $positions), static function ($position) {
                return $position >= 1 && $position <= 99;
            }));
            sort($positions, SORT_NUMERIC);
            $inventory['requiredPositions'] = $positions;
        } catch (Throwable $exception) {
            error_log('Custom layout assignment inventory failed: ' . $exception->getMessage());
            $inventory['count'] = -1;
        }

        return $inventory;
    }
}

if (!function_exists('red_admin_custom_layout_history')) {
    function red_admin_custom_layout_history($connection, $layoutId, $limit = 12)
    {
        $layoutId = red_custom_layout_scalar($layoutId);
        $limit = max(1, min(50, (int) $limit));
        if (!red_custom_layout_valid_id($layoutId)
            || !red_custom_layout_tables_available($connection, true)
        ) {
            return [];
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RevisionID, RevisionNumber, Operation, ActorAdminRecordID, ActorAlias, ' .
                'RestoredFromRevisionID, CreatedAt FROM RED_Custom_Layout_Revisions ' .
                'WHERE LayoutID=? ORDER BY RevisionNumber DESC, RevisionID DESC LIMIT ?'
            );
            if (!$stmt) {
                return [];
            }
            mysqli_stmt_bind_param($stmt, 'si', $layoutId, $limit);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $history = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $history[] = [
                    'revisionId' => (int) $row['RevisionID'],
                    'revisionNumber' => (int) $row['RevisionNumber'],
                    'operation' => (string) $row['Operation'],
                    'actorRecordId' => (int) $row['ActorAdminRecordID'],
                    'actorAlias' => (string) $row['ActorAlias'],
                    'restoredFromRevisionId' => $row['RestoredFromRevisionID'] === null
                        ? null
                        : (int) $row['RestoredFromRevisionID'],
                    'createdAt' => (string) $row['CreatedAt'],
                ];
            }
            mysqli_stmt_close($stmt);

            return $history;
        } catch (Throwable $exception) {
            error_log('Custom layout history lookup failed: ' . $exception->getMessage());
            return [];
        }
    }
}

if (!function_exists('red_admin_custom_layout_client_row')) {
    function red_admin_custom_layout_client_row($connection, array $row, $includeHistory = false)
    {
        $draftDefinition = null;
        $publishedDefinition = null;
        try {
            $draftDefinition = red_custom_layout_normalize_definition(
                (string) ($row['DraftDefinition'] ?? '')
            );
        } catch (Throwable $exception) {
            $draftDefinition = red_custom_layout_default_definition();
        }
        if (trim((string) ($row['PublishedDefinition'] ?? '')) !== '') {
            try {
                $publishedDefinition = red_custom_layout_normalize_definition(
                    (string) $row['PublishedDefinition']
                );
            } catch (Throwable $exception) {
                $publishedDefinition = null;
            }
        }
        $layoutId = (string) ($row['LayoutID'] ?? '');
        $assignments = red_admin_custom_layout_assignment_inventory($connection, $layoutId);

        return [
            'layoutId' => $layoutId,
            'draftLabel' => (string) ($row['DraftLabel'] ?? ''),
            'draftDefinition' => $draftDefinition,
            'draftHash' => (string) ($row['DraftHash'] ?? ''),
            'publishedLabel' => (string) ($row['PublishedLabel'] ?? ''),
            'publishedDefinition' => $publishedDefinition,
            'publishedHash' => (string) ($row['PublishedHash'] ?? ''),
            'revisionNumber' => (int) ($row['RevisionNumber'] ?? 0),
            'archived' => (string) ($row['Archived'] ?? 'N') === 'Y',
            'stateHash' => (string) ($row['StateHash'] ?? red_custom_layout_state_hash($row)),
            'published' => $publishedDefinition !== null,
            'hasUnpublishedChanges' => $publishedDefinition === null
                || !hash_equals(
                    (string) ($row['PublishedHash'] ?? ''),
                    (string) ($row['DraftHash'] ?? '')
                )
                || (string) ($row['PublishedLabel'] ?? '') !== (string) ($row['DraftLabel'] ?? ''),
            'assignments' => $assignments,
            'createdAt' => (string) ($row['CreatedAt'] ?? ''),
            'updatedAt' => (string) ($row['UpdatedAt'] ?? ''),
            'publishedAt' => (string) ($row['PublishedAt'] ?? ''),
            'history' => $includeHistory
                ? red_admin_custom_layout_history($connection, $layoutId)
                : [],
        ];
    }
}

if (!function_exists('red_admin_custom_layout_active_theme_is_standard')) {
    function red_admin_custom_layout_active_theme_is_standard($connection)
    {
        try {
            $contract = red_theme_active_layout_contract($connection);
            return ($contract['themeType'] ?? '') === 'standard';
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('red_admin_custom_layout_id_available')) {
    function red_admin_custom_layout_id_available($connection, $layoutId, $projectRoot = null)
    {
        if (!red_custom_layout_valid_id($layoutId)) {
            return false;
        }
        $reserved = red_custom_layout_reserved_ids($projectRoot);
        if (isset($reserved[strtolower($layoutId)])) {
            return false;
        }

        return red_custom_layout_fetch($connection, $layoutId) === null;
    }
}

if (!function_exists('red_admin_custom_layout_operation_result')) {
    function red_admin_custom_layout_operation_result(
        $connection,
        $ok,
        $reason,
        $message,
        $layoutId = '',
        $changed = false
    ) {
        $row = $ok && red_custom_layout_valid_id((string) $layoutId)
            ? red_custom_layout_fetch($connection, (string) $layoutId)
            : null;

        return [
            'ok' => (bool) $ok,
            'reason' => (string) $reason,
            'message' => (string) $message,
            'changed' => (bool) $changed,
            'layout' => $row ? red_admin_custom_layout_client_row($connection, $row, true) : null,
        ];
    }
}

if (!function_exists('red_admin_custom_layout_save_draft')) {
    function red_admin_custom_layout_save_draft(
        $connection,
        $layoutId,
        $label,
        $definition,
        $expectedStateHash = '',
        $projectRoot = null
    ) {
        $layoutId = red_custom_layout_scalar($layoutId);
        $label = red_custom_layout_text($label, 120);
        $expectedStateHash = red_custom_layout_scalar($expectedStateHash);
        try {
            $definition = red_custom_layout_normalize_definition($definition);
            $definitionJson = red_custom_layout_definition_json($definition);
            $definitionHash = hash('sha256', $definitionJson);
        } catch (Throwable $exception) {
            return red_admin_custom_layout_operation_result(
                $connection,
                false,
                'definition',
                $exception->getMessage()
            );
        }
        if (!red_custom_layout_valid_id($layoutId) || $label === '') {
            return red_admin_custom_layout_operation_result(
                $connection,
                false,
                'identity',
                'Enter a layout name and a valid custom layout ID.'
            );
        }
        if (!red_custom_layout_tables_available($connection, true)) {
            return red_admin_custom_layout_operation_result(
                $connection,
                false,
                'migration',
                'The Layout Builder database migration has not been applied.'
            );
        }

        $reason = 'write';
        $message = 'The draft could not be saved.';
        $changed = false;
        $actor = red_admin_custom_layout_actor();
        $saved = red_admin_theme_contract_write_transaction(
            $connection,
            function () use (
                $connection,
                $layoutId,
                $label,
                $definitionJson,
                $definitionHash,
                $expectedStateHash,
                $projectRoot,
                $actor,
                &$reason,
                &$message,
                &$changed
            ) {
                $row = red_custom_layout_fetch($connection, $layoutId, true);
                if ($row === null) {
                    if ($expectedStateHash !== ''
                        || !red_admin_custom_layout_id_available(
                            $connection,
                            $layoutId,
                            $projectRoot
                        )
                    ) {
                        $reason = 'identity';
                        $message = 'That layout ID is already reserved or unavailable.';
                        return false;
                    }
                    try {
                        $stmt = mysqli_prepare(
                            $connection,
                            'INSERT INTO RED_Custom_Layouts ' .
                            '(LayoutID, DraftLabel, DraftDefinition, DraftHash, RevisionNumber, Archived, ' .
                            'CreatedByAdminRecordID, UpdatedByAdminRecordID) VALUES (?, ?, ?, ?, 1, \'N\', ?, ?)'
                        );
                        if (!$stmt) {
                            return false;
                        }
                        mysqli_stmt_bind_param(
                            $stmt,
                            'ssssii',
                            $layoutId,
                            $label,
                            $definitionJson,
                            $definitionHash,
                            $actor['recordId'],
                            $actor['recordId']
                        );
                        $inserted = mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        if (!$inserted) {
                            return false;
                        }
                        $row = red_custom_layout_fetch($connection, $layoutId, true);
                        if (!$row || !red_admin_custom_layout_insert_revision($connection, $row, 'create')) {
                            return false;
                        }
                        $reason = '';
                        $message = 'Layout draft created.';
                        $changed = true;
                        return true;
                    } catch (Throwable $exception) {
                        error_log('Custom layout insert failed: ' . $exception->getMessage());
                        return false;
                    }
                }

                if (!hash_equals((string) $row['StateHash'], $expectedStateHash)) {
                    $reason = 'conflict';
                    $message = 'This layout changed in another editor. Reload it before saving.';
                    return false;
                }
                if (($row['Archived'] ?? 'N') === 'Y') {
                    $reason = 'archived';
                    $message = 'Restore this archived layout before editing it.';
                    return false;
                }
                if ((string) $row['DraftLabel'] === $label
                    && hash_equals((string) $row['DraftHash'], $definitionHash)
                ) {
                    $reason = '';
                    $message = 'The draft is already up to date.';
                    return true;
                }

                $revisionNumber = (int) $row['RevisionNumber'] + 1;
                try {
                    $stmt = mysqli_prepare(
                        $connection,
                        'UPDATE RED_Custom_Layouts SET DraftLabel=?, DraftDefinition=?, DraftHash=?, ' .
                        'RevisionNumber=?, UpdatedByAdminRecordID=?, UpdatedAt=CURRENT_TIMESTAMP ' .
                        'WHERE LayoutID=?'
                    );
                    if (!$stmt) {
                        return false;
                    }
                    mysqli_stmt_bind_param(
                        $stmt,
                        'sssiis',
                        $label,
                        $definitionJson,
                        $definitionHash,
                        $revisionNumber,
                        $actor['recordId'],
                        $layoutId
                    );
                    $updated = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    $row = $updated ? red_custom_layout_fetch($connection, $layoutId, true) : null;
                    if (!$row || !red_admin_custom_layout_insert_revision($connection, $row, 'draft')) {
                        return false;
                    }
                    $reason = '';
                    $message = 'Draft saved. The public layout is unchanged until you publish.';
                    $changed = true;
                    return true;
                } catch (Throwable $exception) {
                    error_log('Custom layout draft update failed: ' . $exception->getMessage());
                    return false;
                }
            },
            ['RED_Custom_Layouts', 'RED_Custom_Layout_Revisions']
        );

        return red_admin_custom_layout_operation_result(
            $connection,
            $saved,
            $saved ? '' : $reason,
            $saved ? $message : $message,
            $layoutId,
            $changed
        );
    }
}

if (!function_exists('red_admin_custom_layout_publish')) {
    function red_admin_custom_layout_publish(
        $connection,
        $layoutId,
        $expectedStateHash,
        $projectRoot = null
    ) {
        $layoutId = red_custom_layout_scalar($layoutId);
        $expectedStateHash = red_custom_layout_scalar($expectedStateHash);
        if (!red_custom_layout_valid_id($layoutId)
            || !red_custom_layout_tables_available($connection, true)
        ) {
            return red_admin_custom_layout_operation_result(
                $connection,
                false,
                'identity',
                'The layout cannot be published.'
            );
        }

        $reason = 'write';
        $message = 'The layout could not be published.';
        $changed = false;
        $actor = red_admin_custom_layout_actor();
        $published = red_admin_theme_contract_write_transaction(
            $connection,
            function () use (
                $connection,
                $layoutId,
                $expectedStateHash,
                $projectRoot,
                $actor,
                &$reason,
                &$message,
                &$changed
            ) {
                $row = red_custom_layout_fetch($connection, $layoutId, true);
                if (!$row || !hash_equals((string) $row['StateHash'], $expectedStateHash)) {
                    $reason = 'conflict';
                    $message = 'This layout changed in another editor. Reload it before publishing.';
                    return false;
                }
                if (($row['Archived'] ?? 'N') === 'Y') {
                    $reason = 'archived';
                    $message = 'Restore this archived layout before publishing it.';
                    return false;
                }
                if (!red_admin_custom_layout_active_theme_is_standard($connection)) {
                    $reason = 'theme';
                    $message = 'Custom layouts can be published while a standard theme is active.';
                    return false;
                }
                $reserved = red_custom_layout_reserved_ids($projectRoot);
                if (isset($reserved[strtolower($layoutId)])) {
                    $reason = 'identity';
                    $message = 'That layout ID now conflicts with an installed theme layout.';
                    return false;
                }

                try {
                    $definition = red_custom_layout_normalize_definition((string) $row['DraftDefinition']);
                } catch (Throwable $exception) {
                    $reason = 'definition';
                    $message = $exception->getMessage();
                    return false;
                }
                $positions = red_custom_layout_positions($definition);
                $assignments = red_admin_custom_layout_assignment_inventory($connection, $layoutId);
                if ($assignments['count'] < 0) {
                    $reason = 'inventory';
                    $message = 'Current layout assignments could not be verified.';
                    return false;
                }
                foreach ($assignments['requiredPositions'] as $position) {
                    if (!isset($positions[$position])) {
                        $reason = 'positions';
                        $message = 'Position ' . $position .
                            ' still contains content. Keep it in the layout or move that content first.';
                        return false;
                    }
                }

                if ((string) ($row['PublishedLabel'] ?? '') === (string) $row['DraftLabel']
                    && hash_equals(
                        (string) ($row['PublishedHash'] ?? ''),
                        (string) $row['DraftHash']
                    )
                ) {
                    $reason = '';
                    $message = 'This version is already published.';
                    return true;
                }

                $revisionNumber = (int) $row['RevisionNumber'] + 1;
                $publishedAtSql = trim((string) ($row['PublishedAt'] ?? '')) === ''
                    ? 'CURRENT_TIMESTAMP'
                    : 'PublishedAt';
                try {
                    $stmt = mysqli_prepare(
                        $connection,
                        'UPDATE RED_Custom_Layouts SET PublishedLabel=DraftLabel, ' .
                        'PublishedDefinition=DraftDefinition, PublishedHash=DraftHash, RevisionNumber=?, ' .
                        'UpdatedByAdminRecordID=?, UpdatedAt=CURRENT_TIMESTAMP, PublishedAt=' .
                        $publishedAtSql . ' WHERE LayoutID=?'
                    );
                    if (!$stmt) {
                        return false;
                    }
                    mysqli_stmt_bind_param(
                        $stmt,
                        'iis',
                        $revisionNumber,
                        $actor['recordId'],
                        $layoutId
                    );
                    $updated = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    $row = $updated ? red_custom_layout_fetch($connection, $layoutId, true) : null;
                    if (!$row || !red_admin_custom_layout_insert_revision($connection, $row, 'publish')) {
                        return false;
                    }
                    $reason = '';
                    $message = 'Layout published and available in page layout selectors.';
                    $changed = true;
                    return true;
                } catch (Throwable $exception) {
                    error_log('Custom layout publish failed: ' . $exception->getMessage());
                    return false;
                }
            },
            ['RED_Custom_Layouts', 'RED_Custom_Layout_Revisions']
        );

        return red_admin_custom_layout_operation_result(
            $connection,
            $published,
            $published ? '' : $reason,
            $message,
            $layoutId,
            $changed
        );
    }
}

if (!function_exists('red_admin_custom_layout_set_archived')) {
    function red_admin_custom_layout_set_archived(
        $connection,
        $layoutId,
        $expectedStateHash,
        $archived,
        $projectRoot = null
    ) {
        $layoutId = red_custom_layout_scalar($layoutId);
        $expectedStateHash = red_custom_layout_scalar($expectedStateHash);
        $archived = (bool) $archived;
        if (!red_custom_layout_valid_id($layoutId)
            || !red_custom_layout_tables_available($connection, true)
        ) {
            return red_admin_custom_layout_operation_result(
                $connection,
                false,
                'identity',
                'The layout archive state cannot be changed.'
            );
        }

        $reason = 'write';
        $message = $archived ? 'The layout could not be archived.' : 'The layout could not be restored.';
        $changed = false;
        $actor = red_admin_custom_layout_actor();
        $saved = red_admin_theme_contract_write_transaction(
            $connection,
            function () use (
                $connection,
                $layoutId,
                $expectedStateHash,
                $archived,
                $projectRoot,
                $actor,
                &$reason,
                &$message,
                &$changed
            ) {
                $row = red_custom_layout_fetch($connection, $layoutId, true);
                if (!$row || !hash_equals((string) $row['StateHash'], $expectedStateHash)) {
                    $reason = 'conflict';
                    $message = 'This layout changed in another editor. Reload it before continuing.';
                    return false;
                }
                $current = ($row['Archived'] ?? 'N') === 'Y';
                if ($current === $archived) {
                    $reason = '';
                    $message = $archived ? 'This layout is already archived.' : 'This layout is already active.';
                    return true;
                }
                if ($archived) {
                    $assignments = red_admin_custom_layout_assignment_inventory($connection, $layoutId);
                    if ($assignments['count'] !== 0) {
                        $reason = 'assigned';
                        $message = $assignments['count'] < 0
                            ? 'Current layout assignments could not be verified.'
                            : 'This layout is assigned to ' . $assignments['count'] .
                                ' site record(s). Reassign them before archiving.';
                        return false;
                    }
                } else {
                    $reserved = red_custom_layout_reserved_ids($projectRoot);
                    if (isset($reserved[strtolower($layoutId)])) {
                        $reason = 'identity';
                        $message = 'This layout now conflicts with an installed theme layout.';
                        return false;
                    }
                }

                $revisionNumber = (int) $row['RevisionNumber'] + 1;
                $archiveValue = $archived ? 'Y' : 'N';
                try {
                    $stmt = mysqli_prepare(
                        $connection,
                        'UPDATE RED_Custom_Layouts SET Archived=?, RevisionNumber=?, ' .
                        'UpdatedByAdminRecordID=?, UpdatedAt=CURRENT_TIMESTAMP WHERE LayoutID=?'
                    );
                    if (!$stmt) {
                        return false;
                    }
                    mysqli_stmt_bind_param(
                        $stmt,
                        'siis',
                        $archiveValue,
                        $revisionNumber,
                        $actor['recordId'],
                        $layoutId
                    );
                    $updated = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    $row = $updated ? red_custom_layout_fetch($connection, $layoutId, true) : null;
                    $operation = $archived ? 'archive' : 'unarchive';
                    if (!$row || !red_admin_custom_layout_insert_revision($connection, $row, $operation)) {
                        return false;
                    }
                    $reason = '';
                    $message = $archived
                        ? 'Layout archived. Its history remains available.'
                        : 'Layout restored to the library.';
                    $changed = true;
                    return true;
                } catch (Throwable $exception) {
                    error_log('Custom layout archive update failed: ' . $exception->getMessage());
                    return false;
                }
            },
            ['RED_Custom_Layouts', 'RED_Custom_Layout_Revisions']
        );

        return red_admin_custom_layout_operation_result(
            $connection,
            $saved,
            $saved ? '' : $reason,
            $message,
            $layoutId,
            $changed
        );
    }
}

if (!function_exists('red_admin_custom_layout_restore_revision')) {
    function red_admin_custom_layout_restore_revision(
        $connection,
        $layoutId,
        $revisionId,
        $expectedStateHash
    ) {
        $layoutId = red_custom_layout_scalar($layoutId);
        $revisionId = (int) $revisionId;
        $expectedStateHash = red_custom_layout_scalar($expectedStateHash);
        if (!red_custom_layout_valid_id($layoutId)
            || $revisionId < 1
            || !red_custom_layout_tables_available($connection, true)
        ) {
            return red_admin_custom_layout_operation_result(
                $connection,
                false,
                'revision',
                'The selected layout version is unavailable.'
            );
        }

        $reason = 'write';
        $message = 'The selected version could not be restored.';
        $changed = false;
        $actor = red_admin_custom_layout_actor();
        $saved = red_admin_theme_contract_write_transaction(
            $connection,
            function () use (
                $connection,
                $layoutId,
                $revisionId,
                $expectedStateHash,
                $actor,
                &$reason,
                &$message,
                &$changed
            ) {
                $row = red_custom_layout_fetch($connection, $layoutId, true);
                if (!$row || !hash_equals((string) $row['StateHash'], $expectedStateHash)) {
                    $reason = 'conflict';
                    $message = 'This layout changed in another editor. Reload it before restoring.';
                    return false;
                }

                try {
                    $stmt = mysqli_prepare(
                        $connection,
                        'SELECT Snapshot, SnapshotHash FROM RED_Custom_Layout_Revisions ' .
                        'WHERE RevisionID=? AND LayoutID=? LIMIT 1'
                    );
                    if (!$stmt) {
                        return false;
                    }
                    mysqli_stmt_bind_param($stmt, 'is', $revisionId, $layoutId);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $revision = $result ? mysqli_fetch_assoc($result) : null;
                    mysqli_stmt_close($stmt);
                    if (!$revision
                        || !hash_equals(
                            (string) $revision['SnapshotHash'],
                            hash('sha256', (string) $revision['Snapshot'])
                        )
                    ) {
                        $reason = 'revision';
                        $message = 'The selected layout version failed its integrity check.';
                        return false;
                    }
                    $snapshot = json_decode(
                        (string) $revision['Snapshot'],
                        true,
                        64,
                        JSON_THROW_ON_ERROR
                    );
                    $draftLabel = red_custom_layout_text($snapshot['draftLabel'] ?? '', 120);
                    $draftDefinition = red_custom_layout_normalize_definition(
                        (string) ($snapshot['draftDefinition'] ?? '')
                    );
                    if ($draftLabel === '') {
                        throw new InvalidArgumentException('The selected version has no layout name.');
                    }
                    $draftJson = red_custom_layout_definition_json($draftDefinition);
                    $draftHash = hash('sha256', $draftJson);
                    if ((string) $row['DraftLabel'] === $draftLabel
                        && hash_equals((string) $row['DraftHash'], $draftHash)
                        && ($row['Archived'] ?? 'N') === 'N'
                    ) {
                        $reason = '';
                        $message = 'That version already matches the current draft.';
                        return true;
                    }

                    $revisionNumber = (int) $row['RevisionNumber'] + 1;
                    $stmt = mysqli_prepare(
                        $connection,
                        'UPDATE RED_Custom_Layouts SET DraftLabel=?, DraftDefinition=?, DraftHash=?, ' .
                        'Archived=\'N\', RevisionNumber=?, UpdatedByAdminRecordID=?, ' .
                        'UpdatedAt=CURRENT_TIMESTAMP WHERE LayoutID=?'
                    );
                    if (!$stmt) {
                        return false;
                    }
                    mysqli_stmt_bind_param(
                        $stmt,
                        'sssiis',
                        $draftLabel,
                        $draftJson,
                        $draftHash,
                        $revisionNumber,
                        $actor['recordId'],
                        $layoutId
                    );
                    $updated = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    $row = $updated ? red_custom_layout_fetch($connection, $layoutId, true) : null;
                    if (!$row
                        || !red_admin_custom_layout_insert_revision(
                            $connection,
                            $row,
                            'restore',
                            $revisionId
                        )
                    ) {
                        return false;
                    }
                    $reason = '';
                    $message = 'Version restored as a draft. Publish when you are ready.';
                    $changed = true;
                    return true;
                } catch (Throwable $exception) {
                    error_log('Custom layout version restore failed: ' . $exception->getMessage());
                    $reason = 'revision';
                    $message = 'The selected layout version is invalid.';
                    return false;
                }
            },
            ['RED_Custom_Layouts', 'RED_Custom_Layout_Revisions']
        );

        return red_admin_custom_layout_operation_result(
            $connection,
            $saved,
            $saved ? '' : $reason,
            $message,
            $layoutId,
            $changed
        );
    }
}

?>
