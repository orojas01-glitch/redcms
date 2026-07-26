<?php
/**
 * Immutable aggregate snapshots for administrator-editable content.
 *
 * RED_Articles is the stable content identity. Gallery/Banner/Video and Form
 * snapshots include their paired child row so one revision always represents
 * one complete editable component.
 */

require_once __DIR__ . '/admin_article_helpers.php';
require_once __DIR__ . '/admin_gallery_helpers.php';
require_once __DIR__ . '/admin_form_helpers.php';
require_once __DIR__ . '/admin_seo_helpers.php';

if (!function_exists('red_admin_content_revision_scalar')) {
    function red_admin_content_revision_scalar($value)
    {
        return is_array($value) ? '' : (string) $value;
    }
}

if (!function_exists('red_admin_content_revision_table_available')) {
    function red_admin_content_revision_table_available($connection)
    {
        if (!($connection instanceof mysqli)) {
            return false;
        }

        static $availability = [];
        $key = spl_object_id($connection);
        if (array_key_exists($key, $availability)) {
            return $availability[$key];
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Content_Revisions'"
            );
            if (!$stmt || !mysqli_stmt_execute($stmt)) {
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
                return $availability[$key] = false;
            }
            $count = 0;
            mysqli_stmt_bind_result($stmt, $count);
            $found = mysqli_stmt_fetch($stmt) === true && (int) $count === 1;
            mysqli_stmt_close($stmt);
            return $availability[$key] = $found;
        } catch (Throwable $exception) {
            error_log('Content revision table lookup failed: ' . $exception->getMessage());
            return $availability[$key] = false;
        }
    }
}

if (!function_exists('red_admin_content_revision_filter_row')) {
    function red_admin_content_revision_filter_row(array $row, array $columns)
    {
        $filtered = [];
        foreach (array_keys($columns) as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }
            $filtered[$column] = red_admin_content_revision_scalar($row[$column]);
        }
        return $filtered;
    }
}

if (!function_exists('red_admin_content_revision_child_row')) {
    function red_admin_content_revision_child_row($connection, $table, $recordId)
    {
        $recordId = (int) $recordId;
        $allowed = [
            'RED_C_Gallery' => red_admin_gallery_default_insert_data(0, 0),
            'RED_C_Form' => red_admin_form_columns(),
        ];
        if ($recordId <= 0 || !isset($allowed[$table])) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                "SELECT * FROM `$table` WHERE RefID=? ORDER BY RecordID ASC LIMIT 1"
            );
            if (!$stmt) {
                return null;
            }
            $refId = (string) $recordId;
            mysqli_stmt_bind_param($stmt, 's', $refId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            if (!$row) {
                return null;
            }

            $columns = [];
            foreach (array_keys($allowed[$table]) as $column) {
                $columns[$column] = true;
            }
            return red_admin_content_revision_filter_row($row, $columns);
        } catch (Throwable $exception) {
            error_log('Content revision child lookup failed: ' . $exception->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_content_revision_type')) {
    function red_admin_content_revision_type(array $article, $childTable, $childRow)
    {
        $component = red_admin_content_revision_scalar($article['Component'] ?? '');
        if ($childTable === 'RED_C_Gallery' && is_array($childRow)) {
            $subtype = red_admin_gallery_clean_type($childRow['GalleryType'] ?? '');
            return $subtype !== '' ? $subtype : 'Gallery';
        }
        if ($childTable === 'RED_C_Form' && is_array($childRow)) {
            $subtype = red_admin_form_clean_type($childRow['FormType'] ?? '');
            return $subtype !== '' ? 'Form ' . $subtype : 'Form';
        }
        return $component !== '' ? $component : 'Content';
    }
}

if (!function_exists('red_admin_content_revision_capture')) {
    function red_admin_content_revision_capture($connection, $contentRecordId)
    {
        $contentRecordId = (int) $contentRecordId;
        if ($contentRecordId <= 0) {
            return null;
        }

        $article = red_admin_article_full_record($connection, $contentRecordId);
        if (!$article) {
            return null;
        }
        $article = red_admin_content_revision_filter_row($article, red_admin_article_columns());
        $component = red_admin_content_revision_scalar($article['Component'] ?? '');
        $childTable = null;
        $childRow = null;
        if ($component === 'Gallery') {
            $childTable = 'RED_C_Gallery';
            $childRow = red_admin_content_revision_child_row($connection, $childTable, $contentRecordId);
        } elseif ($component === 'Form') {
            $childTable = 'RED_C_Form';
            $childRow = red_admin_content_revision_child_row($connection, $childTable, $contentRecordId);
        }

        $seoAvailable = red_seo_table_available($connection);
        $snapshot = [
            'schema' => $seoAvailable ? 2 : 1,
            'contentRecordId' => (string) $contentRecordId,
            'type' => red_admin_content_revision_type($article, $childTable, $childRow),
            'article' => $article,
            'child' => $childTable === null ? null : [
                'table' => $childTable,
                'row' => $childRow,
            ],
        ];
        if ($seoAvailable) {
            $seo = red_seo_metadata_row($connection, 'article', $contentRecordId);
            $snapshot['seo'] = $seo ? array_merge(red_seo_empty_values(), $seo) : null;
        }

        return $snapshot;
    }
}

if (!function_exists('red_admin_content_revision_json')) {
    function red_admin_content_revision_json(array $snapshot)
    {
        $json = json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($json) ? $json : '';
    }
}

if (!function_exists('red_admin_content_revision_hash')) {
    function red_admin_content_revision_hash(array $snapshot)
    {
        $json = red_admin_content_revision_json($snapshot);
        return $json !== '' ? hash('sha256', $json) : '';
    }
}

if (!function_exists('red_admin_content_revision_current_hash')) {
    function red_admin_content_revision_current_hash($connection, $contentRecordId)
    {
        $snapshot = red_admin_content_revision_capture($connection, $contentRecordId);
        return is_array($snapshot) ? red_admin_content_revision_hash($snapshot) : '';
    }
}

if (!function_exists('red_admin_content_revision_latest')) {
    function red_admin_content_revision_latest($connection, $contentRecordId)
    {
        if (!red_admin_content_revision_table_available($connection)) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RevisionID, RevisionNumber, SnapshotHash FROM RED_Content_Revisions '
                . 'WHERE ContentRecordID=? ORDER BY RevisionNumber DESC LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }
            $contentRecordId = (int) $contentRecordId;
            mysqli_stmt_bind_param($stmt, 'i', $contentRecordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return $row ?: null;
        } catch (Throwable $exception) {
            error_log('Content revision latest lookup failed: ' . $exception->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_content_revision_insert_snapshot')) {
    function red_admin_content_revision_insert_snapshot(
        $connection,
        array $snapshot,
        $operation,
        $restoredFromRevisionId = 0,
        $allowDuplicate = false
    ) {
        if (!red_admin_content_revision_table_available($connection)) {
            return true;
        }

        $allowedOperations = ['baseline', 'create', 'save', 'checkpoint', 'upload', 'move', 'order', 'restore', 'delete'];
        $operation = red_admin_content_revision_scalar($operation);
        $contentRecordId = (int) ($snapshot['contentRecordId'] ?? 0);
        $contentType = substr(red_admin_content_revision_scalar($snapshot['type'] ?? ''), 0, 50);
        $json = red_admin_content_revision_json($snapshot);
        $hash = $json !== '' ? hash('sha256', $json) : '';
        $actorAdminRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
        $actorAlias = substr(red_admin_content_revision_scalar($_SESSION['alias'] ?? ''), 0, 50);
        $restoredFromRevisionId = (int) $restoredFromRevisionId;
        if ($contentRecordId <= 0
            || $contentType === ''
            || $json === ''
            || $hash === ''
            || $actorAdminRecordId <= 0
            || !in_array($operation, $allowedOperations, true)
        ) {
            return false;
        }

        $latest = red_admin_content_revision_latest($connection, $contentRecordId);
        if (!$allowDuplicate && $latest && hash_equals((string) $latest['SnapshotHash'], $hash)) {
            return true;
        }
        $revisionNumber = $latest ? ((int) $latest['RevisionNumber'] + 1) : 1;
        $restoredFrom = $restoredFromRevisionId > 0 ? $restoredFromRevisionId : null;

        try {
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Content_Revisions '
                . '(ContentRecordID, ContentType, RevisionNumber, Operation, ActorAdminRecordID, ActorAlias, Snapshot, SnapshotHash, RestoredFromRevisionID) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'isisisssi',
                $contentRecordId,
                $contentType,
                $revisionNumber,
                $operation,
                $actorAdminRecordId,
                $actorAlias,
                $json,
                $hash,
                $restoredFrom
            );
            $inserted = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $inserted;
        } catch (Throwable $exception) {
            error_log('Content revision insert failed: ' . $exception->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_content_revision_checkpoint')) {
    function red_admin_content_revision_checkpoint($connection, $contentRecordId, $operation = 'checkpoint')
    {
        if (!red_admin_content_revision_table_available($connection)) {
            return true;
        }
        $snapshot = red_admin_content_revision_capture($connection, $contentRecordId);
        if (!$snapshot) {
            return false;
        }
        $latest = red_admin_content_revision_latest($connection, $contentRecordId);
        if ($latest && hash_equals((string) $latest['SnapshotHash'], red_admin_content_revision_hash($snapshot))) {
            return true;
        }
        return red_admin_content_revision_insert_snapshot(
            $connection,
            $snapshot,
            $latest ? $operation : 'baseline'
        );
    }
}

if (!function_exists('red_admin_content_revision_record_current')) {
    function red_admin_content_revision_record_current(
        $connection,
        $contentRecordId,
        $operation,
        $restoredFromRevisionId = 0,
        $allowDuplicate = false
    ) {
        if (!red_admin_content_revision_table_available($connection)) {
            return true;
        }
        $snapshot = red_admin_content_revision_capture($connection, $contentRecordId);
        return $snapshot
            ? red_admin_content_revision_insert_snapshot(
                $connection,
                $snapshot,
                $operation,
                $restoredFromRevisionId,
                $allowDuplicate
            )
            : false;
    }
}

if (!function_exists('red_admin_content_revision_write')) {
    function red_admin_content_revision_write($connection, $contentRecordId, $callback, $operation = 'save')
    {
        if (!is_callable($callback)) {
            return false;
        }
        if (!red_admin_content_revision_table_available($connection)) {
            return (bool) call_user_func($callback);
        }
        if (!red_admin_content_revision_checkpoint($connection, $contentRecordId)) {
            return false;
        }
        if (!(bool) call_user_func($callback)) {
            return false;
        }
        return red_admin_content_revision_record_current($connection, $contentRecordId, $operation);
    }
}

if (!function_exists('red_admin_content_revision_tables')) {
    function red_admin_content_revision_tables($connection, array $tables)
    {
        $tables = array_values(array_unique($tables));
        if (red_admin_content_revision_table_available($connection)) {
            $tables[] = 'RED_Content_Revisions';
        }
        return array_values(array_unique($tables));
    }
}

if (!function_exists('red_admin_content_revision_transaction')) {
    function red_admin_content_revision_transaction(
        $connection,
        $contentRecordId,
        $callback,
        array $tables,
        $operation = 'save'
    ) {
        return red_admin_theme_contract_write_transaction(
            $connection,
            function () use ($connection, $contentRecordId, $callback, $operation) {
                return red_admin_content_revision_write(
                    $connection,
                    $contentRecordId,
                    $callback,
                    $operation
                );
            },
            red_admin_content_revision_tables($connection, $tables)
        );
    }
}

if (!function_exists('red_admin_content_revision_create_transaction')) {
    function red_admin_content_revision_create_transaction($connection, $contentRecordId, $callback, array $tables)
    {
        return red_admin_theme_contract_write_transaction(
            $connection,
            function () use ($connection, $contentRecordId, $callback) {
                if (!(bool) call_user_func($callback)) {
                    return false;
                }
                return red_admin_content_revision_record_current($connection, $contentRecordId, 'create');
            },
            red_admin_content_revision_tables($connection, $tables)
        );
    }
}

if (!function_exists('red_admin_content_revision_delete_transaction')) {
    function red_admin_content_revision_delete_transaction($connection, $contentRecordId, $callback, array $tables)
    {
        return red_admin_theme_contract_write_transaction(
            $connection,
            function () use ($connection, $contentRecordId, $callback) {
                if (!red_admin_content_revision_record_current(
                    $connection,
                    $contentRecordId,
                    'delete',
                    0,
                    true
                )) {
                    return false;
                }
                return (bool) call_user_func($callback);
            },
            red_admin_content_revision_tables($connection, $tables)
        );
    }
}

if (!function_exists('red_admin_content_revision_row')) {
    function red_admin_content_revision_row($connection, $contentRecordId, $revisionId)
    {
        if (!red_admin_content_revision_table_available($connection)) {
            return null;
        }
        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RevisionID, ContentRecordID, ContentType, RevisionNumber, Operation, ActorAlias, Snapshot, SnapshotHash, RestoredFromRevisionID, CreatedAt '
                . 'FROM RED_Content_Revisions WHERE RevisionID=? AND ContentRecordID=? LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }
            $revisionId = (int) $revisionId;
            $contentRecordId = (int) $contentRecordId;
            mysqli_stmt_bind_param($stmt, 'ii', $revisionId, $contentRecordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return $row ?: null;
        } catch (Throwable $exception) {
            error_log('Content revision lookup failed: ' . $exception->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_content_revision_decode')) {
    function red_admin_content_revision_decode($json)
    {
        $snapshot = json_decode(red_admin_content_revision_scalar($json), true);
        if (!is_array($snapshot)
            || !in_array((int) ($snapshot['schema'] ?? 0), [1, 2], true)
            || !is_array($snapshot['article'] ?? null)
        ) {
            return null;
        }
        return $snapshot;
    }
}

if (!function_exists('red_admin_content_revision_same_shape')) {
    function red_admin_content_revision_same_shape(array $current, array $target)
    {
        if ((int) ($current['contentRecordId'] ?? 0) !== (int) ($target['contentRecordId'] ?? 0)) {
            return false;
        }
        $currentComponent = red_admin_content_revision_scalar($current['article']['Component'] ?? '');
        $targetComponent = red_admin_content_revision_scalar($target['article']['Component'] ?? '');
        if ($currentComponent === '' || $currentComponent !== $targetComponent) {
            return false;
        }
        $currentChild = $current['child'] ?? null;
        $targetChild = $target['child'] ?? null;
        if (($currentChild === null) !== ($targetChild === null)) {
            return false;
        }
        if ($currentChild === null) {
            return true;
        }
        if (!is_array($currentChild) || !is_array($targetChild)) {
            return false;
        }
        if (($currentChild['table'] ?? '') !== ($targetChild['table'] ?? '')) {
            return false;
        }
        $currentRow = $currentChild['row'] ?? null;
        $targetRow = $targetChild['row'] ?? null;
        if (!is_array($currentRow) || !is_array($targetRow)) {
            return false;
        }
        if ((int) ($currentRow['RecordID'] ?? 0) !== (int) ($targetRow['RecordID'] ?? 0)
            || (string) ($currentRow['RefID'] ?? '') !== (string) ($targetRow['RefID'] ?? '')
        ) {
            return false;
        }
        if (($currentChild['table'] ?? '') === 'RED_C_Gallery') {
            return red_admin_gallery_clean_type($currentRow['GalleryType'] ?? '')
                === red_admin_gallery_clean_type($targetRow['GalleryType'] ?? '');
        }
        if (($currentChild['table'] ?? '') === 'RED_C_Form') {
            return red_admin_form_clean_type($currentRow['FormType'] ?? '')
                === red_admin_form_clean_type($targetRow['FormType'] ?? '');
        }
        return false;
    }
}

if (!function_exists('red_admin_content_revision_changed_values')) {
    function red_admin_content_revision_changed_values(array $current, array $target, array $excluded = [])
    {
        $changes = [];
        foreach ($target as $field => $value) {
            if (isset($excluded[$field])) {
                continue;
            }
            $targetValue = red_admin_content_revision_scalar($value);
            if (red_admin_content_revision_scalar($current[$field] ?? '') !== $targetValue) {
                $changes[$field] = $targetValue;
            }
        }
        return $changes;
    }
}

if (!function_exists('red_admin_content_revision_apply')) {
    function red_admin_content_revision_apply($connection, array $current, array $target)
    {
        if (!red_admin_content_revision_same_shape($current, $target)) {
            return false;
        }
        $contentRecordId = (int) ($current['contentRecordId'] ?? 0);
        $articleTarget = $target['article'];
        $articleTarget['EditedBy'] = red_admin_content_revision_scalar($_SESSION['alias'] ?? '');
        $articleData = red_admin_content_revision_changed_values(
            $current['article'],
            $articleTarget,
            ['RecordID' => true, 'Component' => true, 'Language' => true]
        );
        foreach (['StartDate', 'EventDate', 'ExpDate'] as $dateField) {
            if (array_key_exists($dateField, $articleData)) {
                $articleData[$dateField] = red_admin_article_clean_value($dateField, $articleData[$dateField]);
            }
        }
        if ($articleData !== []
            && !red_admin_article_update($connection, $contentRecordId, $articleData)
        ) {
            return false;
        }

        if ((int) ($target['schema'] ?? 1) >= 2) {
            $targetSeo = is_array($target['seo'] ?? null)
                ? array_merge(red_seo_empty_values(), $target['seo'])
                : red_seo_empty_values();
            if (!red_admin_seo_save($connection, 'article', $contentRecordId, $targetSeo)) {
                return false;
            }
        }

        $child = $target['child'] ?? null;
        if ($child === null) {
            return true;
        }
        $currentChildRow = $current['child']['row'];
        $targetChildRow = $child['row'];
        $childRecordId = (int) ($currentChildRow['RecordID'] ?? 0);
        if (($child['table'] ?? '') === 'RED_C_Gallery') {
            $galleryType = red_admin_gallery_clean_type($currentChildRow['GalleryType'] ?? '');
            if ($galleryType === 'Video' && !is_array(red_video_url_data($targetChildRow['LongDesc'] ?? ''))) {
                return false;
            }
            $galleryData = red_admin_content_revision_changed_values(
                $currentChildRow,
                $targetChildRow,
                ['RecordID' => true, 'RefID' => true, 'GalleryType' => true]
            );
            return $galleryData === []
                || red_admin_gallery_update($connection, $childRecordId, $galleryData);
        }
        if (($child['table'] ?? '') === 'RED_C_Form') {
            $formType = red_admin_form_clean_type($currentChildRow['FormType'] ?? '');
            if (red_admin_form_schema_is_locked($formType)
                && red_admin_form_scalar($targetChildRow['LongDesc'] ?? '')
                    !== red_admin_form_scalar($currentChildRow['LongDesc'] ?? '')
            ) {
                return false;
            }
            $formData = red_admin_content_revision_changed_values(
                $currentChildRow,
                $targetChildRow,
                ['RecordID' => true, 'RefID' => true, 'FormType' => true, 'TableName' => true]
            );
            $effective = array_merge($currentChildRow, $formData);
            if (!red_admin_form_data_is_safe($effective)) {
                return false;
            }
            return $formData === []
                || red_admin_form_update($connection, $childRecordId, $formData);
        }
        return false;
    }
}

if (!function_exists('red_admin_content_revision_restore')) {
    function red_admin_content_revision_restore(
        $connection,
        $contentRecordId,
        $revisionId,
        $expectedCurrentHash = ''
    ) {
        $contentRecordId = (int) $contentRecordId;
        $revisionId = (int) $revisionId;
        $expectedCurrentHash = red_admin_content_revision_scalar($expectedCurrentHash);
        $row = red_admin_content_revision_row($connection, $contentRecordId, $revisionId);
        $target = $row ? red_admin_content_revision_decode($row['Snapshot'] ?? '') : null;
        $current = red_admin_content_revision_capture($connection, $contentRecordId);
        if (!$row || !$target || !$current || !red_admin_content_revision_same_shape($current, $target)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        $currentHash = red_admin_content_revision_hash($current);
        if ($expectedCurrentHash !== '' && !hash_equals($currentHash, $expectedCurrentHash)) {
            return ['ok' => false, 'reason' => 'conflict'];
        }
        if (hash_equals($currentHash, (string) ($row['SnapshotHash'] ?? ''))) {
            return ['ok' => false, 'reason' => 'current'];
        }

        $tables = ['RED_Articles'];
        if (is_array($current['child'] ?? null)) {
            $tables[] = (string) $current['child']['table'];
        }
        if (red_seo_table_available($connection)) {
            $tables[] = 'RED_Page_SEO';
        }
        $success = red_admin_theme_contract_write_transaction(
            $connection,
            function () use ($connection, $contentRecordId, $revisionId, $target, $expectedCurrentHash) {
                $lockedCurrent = red_admin_content_revision_capture($connection, $contentRecordId);
                if (!$lockedCurrent) {
                    return false;
                }
                $lockedHash = red_admin_content_revision_hash($lockedCurrent);
                if ($expectedCurrentHash !== '' && !hash_equals($lockedHash, $expectedCurrentHash)) {
                    return false;
                }
                if (!red_admin_content_revision_checkpoint($connection, $contentRecordId)) {
                    return false;
                }
                if (!red_admin_content_revision_apply($connection, $lockedCurrent, $target)) {
                    return false;
                }
                return red_admin_content_revision_record_current(
                    $connection,
                    $contentRecordId,
                    'restore',
                    $revisionId,
                    true
                );
            },
            red_admin_content_revision_tables($connection, $tables)
        );

        return [
            'ok' => $success,
            'reason' => $success ? 'restored' : 'failed',
            'currentHash' => $success
                ? red_admin_content_revision_current_hash($connection, $contentRecordId)
                : $currentHash,
        ];
    }
}

if (!function_exists('red_admin_content_revision_flatten')) {
    function red_admin_content_revision_flatten(array $snapshot)
    {
        $flat = [];
        foreach (($snapshot['article'] ?? []) as $key => $value) {
            if ($key !== 'RecordID') {
                $flat['article.' . $key] = red_admin_content_revision_scalar($value);
            }
        }
        $child = $snapshot['child']['row'] ?? null;
        if (is_array($child)) {
            foreach ($child as $key => $value) {
                if ($key !== 'RecordID' && $key !== 'RefID') {
                    $flat['child.' . $key] = red_admin_content_revision_scalar($value);
                }
            }
        }
        $seo = $snapshot['seo'] ?? null;
        if (is_array($seo)) {
            foreach (array_keys(red_seo_field_definitions()) as $key) {
                if (array_key_exists($key, $seo)) {
                    $flat['seo.' . $key] = red_admin_content_revision_scalar($seo[$key]);
                }
            }
        }
        return $flat;
    }
}

if (!function_exists('red_admin_content_revision_field_label')) {
    function red_admin_content_revision_field_label($field)
    {
        $field = preg_replace('/^(article|child|seo)\./', '', (string) $field);
        $labels = [
            'Title' => 'title',
            'Alias' => 'page address',
            'ShortDesc' => 'summary',
            'LongDesc' => 'content',
            'SliderDesc' => 'slider summary',
            'Active' => 'visibility',
            'Layout' => 'layout',
            'Sections' => 'section',
            'Categories' => 'category',
            'SubCategories' => 'subcategory',
            'Article' => 'parent page',
            'PagePosition' => 'position',
            'PagePositionOrder' => 'order',
            'HomePosition' => 'home position',
            'SectionPosition' => 'section position',
            'CategoryPosition' => 'category position',
            'SubCategoryPosition' => 'subcategory position',
            'Tags' => 'search metadata',
            'StartDate' => 'start date',
            'EventDate' => 'event date',
            'ExpDate' => 'expiration date',
            'BigPict' => 'feature image',
            'SmallPict' => 'summary image',
            'SmallPict2' => 'content image',
            'Link' => 'link',
            'NewWindow' => 'link or presentation',
            'Subject' => 'email subject',
            'Submitter' => 'sender address',
            'Destinatary' => 'recipient address',
            'CC' => 'CC recipients',
            'BCC' => 'BCC recipients',
            'Response' => 'response content',
            'HomeFeature' => 'home feature',
            'EditedBy' => 'editor',
            'SEO_Title' => 'SEO title',
            'MetaDescription' => 'meta description',
            'CanonicalURL' => 'canonical URL',
            'RobotsIndex' => 'robots indexing',
            'RobotsFollow' => 'robots following',
            'OGTitle' => 'Open Graph title',
            'OGDescription' => 'Open Graph description',
            'OGImage' => 'Open Graph image',
            'OGImageAlt' => 'Open Graph image description',
            'OGType' => 'Open Graph type',
            'OGLocale' => 'Open Graph locale',
            'XCard' => 'X card type',
            'XTitle' => 'X title',
            'XDescription' => 'X description',
            'XImage' => 'X image',
            'SchemaType' => 'structured data type',
            'SchemaIdentityType' => 'structured data identity type',
            'SchemaIdentityName' => 'structured data identity name',
            'SchemaIdentityURL' => 'structured data identity URL',
            'SchemaMainEntityName' => 'main Course name',
            'SchemaEducationalLevel' => 'Course educational level',
            'SchemaCourseMode' => 'Course delivery mode',
            'SchemaCourseWorkload' => 'Course workload',
            'SchemaInstructorName' => 'Course instructor',
            'SchemaTeaches' => 'Course topics',
            'SchemaServiceType' => 'Service type',
        ];
        return $labels[$field] ?? strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $field));
    }
}

if (!function_exists('red_admin_content_revision_changes')) {
    function red_admin_content_revision_changes($previous, array $current)
    {
        if (!is_array($previous)) {
            return ['initial version'];
        }
        $before = red_admin_content_revision_flatten($previous);
        $after = red_admin_content_revision_flatten($current);
        $labels = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $field) {
            if (($before[$field] ?? null) === ($after[$field] ?? null)) {
                continue;
            }
            $label = red_admin_content_revision_field_label($field);
            $labels[$label] = $label;
        }
        return array_values($labels);
    }
}

if (!function_exists('red_admin_content_revision_list')) {
    function red_admin_content_revision_list($connection, $contentRecordId, $limit = 50)
    {
        $contentRecordId = (int) $contentRecordId;
        $limit = max(1, min(100, (int) $limit));
        if (!red_admin_content_revision_table_available($connection) || $contentRecordId <= 0) {
            return [
                'available' => false,
                'currentHash' => red_admin_content_revision_current_hash($connection, $contentRecordId),
                'total' => 0,
                'revisions' => [],
            ];
        }

        try {
            $countStmt = mysqli_prepare(
                $connection,
                'SELECT COUNT(*) FROM RED_Content_Revisions WHERE ContentRecordID=?'
            );
            if (!$countStmt) {
                return ['available' => true, 'currentHash' => '', 'total' => 0, 'revisions' => []];
            }
            mysqli_stmt_bind_param($countStmt, 'i', $contentRecordId);
            if (!mysqli_stmt_execute($countStmt)) {
                mysqli_stmt_close($countStmt);
                return ['available' => true, 'currentHash' => '', 'total' => 0, 'revisions' => []];
            }
            $total = 0;
            mysqli_stmt_bind_result($countStmt, $total);
            mysqli_stmt_fetch($countStmt);
            mysqli_stmt_close($countStmt);

            // Fetch one predecessor with the latest page so field-change labels
            // remain accurate when a long history is trimmed for the editor.
            $fetchLimit = $limit + 1;
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RevisionID, RevisionNumber, ContentType, Operation, ActorAlias, Snapshot, SnapshotHash, RestoredFromRevisionID, CreatedAt '
                . 'FROM RED_Content_Revisions WHERE ContentRecordID=? ORDER BY RevisionNumber DESC LIMIT ?'
            );
            if (!$stmt) {
                return ['available' => true, 'currentHash' => '', 'total' => (int) $total, 'revisions' => []];
            }
            mysqli_stmt_bind_param($stmt, 'ii', $contentRecordId, $fetchLimit);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return ['available' => true, 'currentHash' => '', 'total' => (int) $total, 'revisions' => []];
            }
            $result = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
            $rows = array_reverse($rows);

            $currentHash = red_admin_content_revision_current_hash($connection, $contentRecordId);
            $previous = null;
            if (count($rows) > $limit) {
                $predecessor = array_shift($rows);
                $previous = red_admin_content_revision_decode($predecessor['Snapshot'] ?? '');
            }
            $currentRevisionNumber = 0;
            if ($currentHash !== '') {
                foreach ($rows as $row) {
                    if (hash_equals($currentHash, (string) ($row['SnapshotHash'] ?? ''))) {
                        $currentRevisionNumber = (int) ($row['RevisionNumber'] ?? 0);
                    }
                }
            }
            $revisions = [];
            foreach ($rows as $row) {
                $snapshot = red_admin_content_revision_decode($row['Snapshot'] ?? '');
                if (!$snapshot) {
                    continue;
                }
                $changes = red_admin_content_revision_changes($previous, $snapshot);
                $operation = red_admin_content_revision_scalar($row['Operation'] ?? '');
                $summary = '';
                if ($operation === 'restore') {
                    $summary = 'Restored from an earlier version';
                } elseif ($operation === 'create') {
                    $summary = 'Initial saved version';
                } elseif ($operation === 'baseline') {
                    $summary = 'Original version';
                } elseif ($operation === 'delete') {
                    $summary = 'Saved before deletion';
                } elseif ($operation === 'upload') {
                    $summary = 'Media updated';
                } elseif ($operation === 'move') {
                    $summary = 'Location or position updated';
                } elseif ($operation === 'order') {
                    $summary = 'Display order updated';
                } elseif ($changes !== []) {
                    $summary = ucfirst(implode(', ', array_slice($changes, 0, 3))) . (count($changes) > 3 ? ' and more' : '');
                } else {
                    $summary = 'Saved version';
                }
                $createdAt = red_admin_content_revision_scalar($row['CreatedAt'] ?? '');
                $timestamp = strtotime($createdAt);
                $revisions[] = [
                    'revisionId' => (int) $row['RevisionID'],
                    'revisionNumber' => (int) $row['RevisionNumber'],
                    'contentType' => red_admin_content_revision_scalar($row['ContentType'] ?? ''),
                    'operation' => $operation,
                    'actorAlias' => red_admin_content_revision_scalar($row['ActorAlias'] ?? '') ?: 'Administrator',
                    'createdAt' => $createdAt,
                    'createdLabel' => $timestamp ? date('M j, Y · g:i a', $timestamp) : $createdAt,
                    'summary' => $summary,
                    'changes' => array_slice($changes, 0, 8),
                    'isCurrent' => $currentRevisionNumber > 0
                        && $currentRevisionNumber === (int) ($row['RevisionNumber'] ?? 0),
                    'restoredFromRevisionId' => (int) ($row['RestoredFromRevisionID'] ?? 0),
                ];
                $previous = $snapshot;
            }

            return [
                'available' => true,
                'currentHash' => $currentHash,
                'total' => (int) $total,
                'revisions' => array_reverse($revisions),
            ];
        } catch (Throwable $exception) {
            error_log('Content revision history lookup failed: ' . $exception->getMessage());
            return ['available' => true, 'currentHash' => '', 'total' => 0, 'revisions' => []];
        }
    }
}

if (!function_exists('red_admin_content_revision_response_headers')) {
    function red_admin_content_revision_response_headers($connection, $contentRecordId)
    {
        if (headers_sent()) {
            return;
        }
        $hash = red_admin_content_revision_current_hash($connection, $contentRecordId);
        if ($hash !== '') {
            header('X-RED-Content-Record: ' . (int) $contentRecordId);
            header('X-RED-Revision-Hash: ' . $hash);
        }
    }
}

?>
