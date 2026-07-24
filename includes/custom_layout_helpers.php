<?php
/**
 * Core contract for site-owned visual layouts.
 *
 * Custom layouts are declarative data only. They never contain PHP, HTML, CSS,
 * SQL, or executable template paths. A published definition is rendered by the
 * fixed core grid renderer while packaged theme layouts remain unchanged.
 */

if (!function_exists('red_custom_layout_scalar')) {
    function red_custom_layout_scalar($value)
    {
        return is_array($value) || is_object($value) ? '' : (string) $value;
    }
}

if (!function_exists('red_custom_layout_text')) {
    function red_custom_layout_text($value, $maximum = 120)
    {
        $value = trim(strip_tags(red_custom_layout_scalar($value)));
        $maximum = max(1, (int) $maximum);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maximum, 'UTF-8');
        }

        return substr($value, 0, $maximum);
    }
}

if (!function_exists('red_custom_layout_valid_id')) {
    function red_custom_layout_valid_id($layoutId)
    {
        return is_string($layoutId)
            && preg_match('/\Acustom-[a-z0-9](?:[a-z0-9-]{0,54}[a-z0-9])?\z/', $layoutId) === 1;
    }
}

if (!function_exists('red_custom_layout_slug')) {
    function red_custom_layout_slug($value)
    {
        $value = strtolower(red_custom_layout_text($value, 120));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');
        $value = substr($value, 0, 56);
        $value = trim($value, '-');

        return $value === '' ? '' : 'custom-' . $value;
    }
}

if (!function_exists('red_custom_layout_json_encode')) {
    function red_custom_layout_json_encode(array $value)
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if (!is_string($json)) {
            throw new InvalidArgumentException('The custom layout could not be encoded.');
        }

        return $json;
    }
}

if (!function_exists('red_custom_layout_normalize_definition')) {
    function red_custom_layout_normalize_definition($definition)
    {
        if (is_string($definition)) {
            try {
                $definition = json_decode($definition, true, 64, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                throw new InvalidArgumentException('The layout definition is not valid JSON.');
            }
        }
        if (!is_array($definition)) {
            throw new InvalidArgumentException('The layout definition must be an object.');
        }

        $allowedDefinitionKeys = ['schemaVersion', 'rows', 'mobile'];
        $definitionKeys = array_keys($definition);
        sort($allowedDefinitionKeys, SORT_STRING);
        sort($definitionKeys, SORT_STRING);
        if ($definitionKeys !== $allowedDefinitionKeys
            || ($definition['schemaVersion'] ?? null) !== 1
            || ($definition['mobile'] ?? null) !== 'stack'
            || !is_array($definition['rows'] ?? null)
            || $definition['rows'] === []
            || count($definition['rows']) > 48
        ) {
            throw new InvalidArgumentException(
                'A layout must contain 1–48 rows and use the supported stacked mobile behavior.'
            );
        }

        $normalizedRows = [];
        $seenPositions = [];
        foreach ($definition['rows'] as $rowIndex => $row) {
            if (!is_array($row)
                || array_keys($row) !== ['columns']
                || !is_array($row['columns'])
                || $row['columns'] === []
                || count($row['columns']) > 12
            ) {
                throw new InvalidArgumentException(
                    'Row ' . ((int) $rowIndex + 1) . ' must contain between 1 and 12 columns.'
                );
            }

            $normalizedColumns = [];
            $spanTotal = 0;
            foreach ($row['columns'] as $columnIndex => $column) {
                if (!is_array($column)) {
                    throw new InvalidArgumentException('Every layout column must be an object.');
                }
                $columnKeys = array_keys($column);
                sort($columnKeys, SORT_STRING);
                if ($columnKeys !== ['label', 'position', 'span']) {
                    throw new InvalidArgumentException('Layout columns contain unsupported properties.');
                }

                $position = $column['position'] ?? null;
                $span = $column['span'] ?? null;
                $label = red_custom_layout_text($column['label'] ?? '', 80);
                if (!is_int($position)
                    || $position < 1
                    || $position > 99
                    || isset($seenPositions[$position])
                    || !is_int($span)
                    || $span < 1
                    || $span > 12
                    || $label === ''
                ) {
                    throw new InvalidArgumentException(
                        'Row ' . ((int) $rowIndex + 1) . ', column ' . ((int) $columnIndex + 1) .
                        ' needs a unique position 1–99, a span 1–12, and a label.'
                    );
                }

                $spanTotal += $span;
                if ($spanTotal > 12) {
                    throw new InvalidArgumentException(
                        'The columns in row ' . ((int) $rowIndex + 1) . ' exceed the 12-unit grid.'
                    );
                }
                $seenPositions[$position] = true;
                $normalizedColumns[] = [
                    'position' => $position,
                    'span' => $span,
                    'label' => $label,
                ];
            }
            if ($spanTotal !== 12) {
                throw new InvalidArgumentException(
                    'The columns in row ' . ((int) $rowIndex + 1) . ' must total exactly 12 units.'
                );
            }
            $normalizedRows[] = ['columns' => $normalizedColumns];
        }

        if (count($seenPositions) > 99) {
            throw new InvalidArgumentException('A custom layout cannot contain more than 99 positions.');
        }

        return [
            'schemaVersion' => 1,
            'rows' => $normalizedRows,
            'mobile' => 'stack',
        ];
    }
}

if (!function_exists('red_custom_layout_definition_json')) {
    function red_custom_layout_definition_json($definition)
    {
        return red_custom_layout_json_encode(red_custom_layout_normalize_definition($definition));
    }
}

if (!function_exists('red_custom_layout_definition_hash')) {
    function red_custom_layout_definition_hash($definition)
    {
        return hash('sha256', red_custom_layout_definition_json($definition));
    }
}

if (!function_exists('red_custom_layout_positions')) {
    function red_custom_layout_positions(array $definition)
    {
        $definition = red_custom_layout_normalize_definition($definition);
        $positions = [];
        foreach ($definition['rows'] as $row) {
            foreach ($row['columns'] as $column) {
                $positions[(int) $column['position']] = (string) $column['label'];
            }
        }
        ksort($positions, SORT_NUMERIC);

        return $positions;
    }
}

if (!function_exists('red_custom_layout_preview_rows')) {
    function red_custom_layout_preview_rows(array $definition)
    {
        $definition = red_custom_layout_normalize_definition($definition);
        $rows = [];
        foreach ($definition['rows'] as $row) {
            $previewRow = [];
            foreach ($row['columns'] as $column) {
                $previewRow[] = [
                    'position' => (int) $column['position'],
                    'label' => (string) $column['label'],
                    'weight' => (int) $column['span'],
                ];
            }
            $rows[] = $previewRow;
        }

        return $rows;
    }
}

if (!function_exists('red_custom_layout_catalog_definition')) {
    function red_custom_layout_catalog_definition($layoutId, $label, $definition)
    {
        $layoutId = red_custom_layout_scalar($layoutId);
        $label = red_custom_layout_text($label, 120);
        if (!red_custom_layout_valid_id($layoutId) || $label === '') {
            throw new InvalidArgumentException('The custom layout identity is invalid.');
        }
        $definition = red_custom_layout_normalize_definition($definition);

        return [
            'id' => $layoutId,
            'label' => $label,
            'positions' => red_custom_layout_positions($definition),
            'previewRows' => red_custom_layout_preview_rows($definition),
            'previewIsFallback' => false,
            'hiddenPosition' => 0,
            'custom' => true,
            'grid' => $definition,
            'assignedId' => $layoutId,
            'resolvedId' => $layoutId,
            'usesAlias' => false,
        ];
    }
}

if (!function_exists('red_custom_layout_span_distribution')) {
    function red_custom_layout_span_distribution(array $weights)
    {
        $weights = array_values(array_map(static function ($weight) {
            return max(1, min(12, (int) $weight));
        }, $weights));
        if ($weights === [] || count($weights) > 12) {
            throw new InvalidArgumentException('A reusable row must contain 1–12 columns.');
        }

        $total = array_sum($weights);
        $spans = [];
        $remainders = [];
        foreach ($weights as $index => $weight) {
            $exact = ($weight * 12) / $total;
            $spans[$index] = max(1, (int) floor($exact));
            $remainders[$index] = $exact - floor($exact);
        }

        while (array_sum($spans) > 12) {
            $largestIndex = null;
            $largestSpan = 1;
            foreach ($spans as $index => $span) {
                if ($span > $largestSpan) {
                    $largestIndex = $index;
                    $largestSpan = $span;
                }
            }
            if ($largestIndex === null) {
                break;
            }
            $spans[$largestIndex]--;
        }
        while (array_sum($spans) < 12) {
            $candidateIndexes = array_keys($spans);
            usort($candidateIndexes, static function ($left, $right) use ($remainders, $spans) {
                $remainderCompare = $remainders[$right] <=> $remainders[$left];
                return $remainderCompare !== 0 ? $remainderCompare : ($spans[$left] <=> $spans[$right]);
            });
            $changed = false;
            foreach ($candidateIndexes as $index) {
                if ($spans[$index] >= 12) {
                    continue;
                }
                $spans[$index]++;
                $changed = true;
                break;
            }
            if (!$changed) {
                break;
            }
        }

        return array_values($spans);
    }
}

if (!function_exists('red_custom_layout_definition_from_catalog')) {
    function red_custom_layout_definition_from_catalog(array $catalogDefinition)
    {
        $positions = is_array($catalogDefinition['positions'] ?? null)
            ? $catalogDefinition['positions']
            : [];
        $previewRows = is_array($catalogDefinition['previewRows'] ?? null)
            ? $catalogDefinition['previewRows']
            : [];
        if ($positions === [] || $previewRows === []) {
            throw new InvalidArgumentException('The source layout does not expose reusable positions.');
        }

        $rows = [];
        foreach ($previewRows as $previewRow) {
            if (!is_array($previewRow) || $previewRow === []) {
                continue;
            }
            $weights = [];
            foreach ($previewRow as $cell) {
                $weights[] = is_array($cell) ? (int) ($cell['weight'] ?? 1) : 1;
            }
            $spans = red_custom_layout_span_distribution($weights);
            $columns = [];
            foreach (array_values($previewRow) as $index => $cell) {
                $position = is_array($cell) ? (int) ($cell['position'] ?? 0) : 0;
                if ($position < 1 || !isset($positions[$position])) {
                    throw new InvalidArgumentException('The source layout preview is incomplete.');
                }
                $columns[] = [
                    'position' => $position,
                    'span' => (int) $spans[$index],
                    'label' => (string) $positions[$position],
                ];
            }
            $rows[] = ['columns' => $columns];
        }

        return red_custom_layout_normalize_definition([
            'schemaVersion' => 1,
            'rows' => $rows,
            'mobile' => 'stack',
        ]);
    }
}

if (!function_exists('red_custom_layout_default_definition')) {
    function red_custom_layout_default_definition()
    {
        return [
            'schemaVersion' => 1,
            'rows' => [[
                'columns' => [[
                    'position' => 1,
                    'span' => 12,
                    'label' => 'Full-width row',
                ]],
            ]],
            'mobile' => 'stack',
        ];
    }
}

if (!function_exists('red_custom_layout_tables_available')) {
    function red_custom_layout_tables_available($connection, $includeRevisions = false)
    {
        if (!($connection instanceof mysqli)) {
            return false;
        }
        $required = $includeRevisions
            ? ['RED_Custom_Layouts', 'RED_Custom_Layout_Revisions']
            : ['RED_Custom_Layouts'];
        try {
            $placeholders = implode(',', array_fill(0, count($required), '?'));
            $stmt = mysqli_prepare(
                $connection,
                'SELECT TABLE_NAME FROM information_schema.TABLES ' .
                'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $placeholders . ')'
            );
            if (!$stmt) {
                return false;
            }
            $firstTable = (string) $required[0];
            if (count($required) === 1) {
                mysqli_stmt_bind_param($stmt, 's', $firstTable);
            } else {
                $secondTable = (string) $required[1];
                mysqli_stmt_bind_param($stmt, 'ss', $firstTable, $secondTable);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $found = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $found[(string) $row['TABLE_NAME']] = true;
            }
            mysqli_stmt_close($stmt);
            foreach ($required as $table) {
                if (!isset($found[$table])) {
                    return false;
                }
            }
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('red_custom_layout_state_hash')) {
    function red_custom_layout_state_hash(array $row)
    {
        $state = [
            'layoutId' => (string) ($row['LayoutID'] ?? ''),
            'draftLabel' => (string) ($row['DraftLabel'] ?? ''),
            'draftDefinition' => (string) ($row['DraftDefinition'] ?? ''),
            'draftHash' => (string) ($row['DraftHash'] ?? ''),
            'publishedLabel' => (string) ($row['PublishedLabel'] ?? ''),
            'publishedDefinition' => (string) ($row['PublishedDefinition'] ?? ''),
            'publishedHash' => (string) ($row['PublishedHash'] ?? ''),
            'revisionNumber' => (int) ($row['RevisionNumber'] ?? 0),
            'archived' => (string) ($row['Archived'] ?? 'N'),
        ];

        return hash('sha256', red_custom_layout_json_encode($state));
    }
}

if (!function_exists('red_custom_layout_fetch')) {
    function red_custom_layout_fetch($connection, $layoutId, $forUpdate = false)
    {
        $layoutId = red_custom_layout_scalar($layoutId);
        if (!red_custom_layout_valid_id($layoutId) || !red_custom_layout_tables_available($connection)) {
            return null;
        }
        try {
            $sql = 'SELECT LayoutID, DraftLabel, DraftDefinition, DraftHash, PublishedLabel, ' .
                'PublishedDefinition, PublishedHash, RevisionNumber, Archived, CreatedByAdminRecordID, ' .
                'UpdatedByAdminRecordID, CreatedAt, UpdatedAt, PublishedAt ' .
                'FROM RED_Custom_Layouts WHERE LayoutID=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return null;
            }
            mysqli_stmt_bind_param($stmt, 's', $layoutId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            if (!$row) {
                return null;
            }
            $row['StateHash'] = red_custom_layout_state_hash($row);

            return $row;
        } catch (Throwable $exception) {
            error_log('Custom layout lookup failed: ' . $exception->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_custom_layout_list')) {
    function red_custom_layout_list($connection, $includeArchived = true)
    {
        if (!red_custom_layout_tables_available($connection)) {
            return [];
        }
        try {
            $sql = 'SELECT LayoutID, DraftLabel, DraftDefinition, DraftHash, PublishedLabel, ' .
                'PublishedDefinition, PublishedHash, RevisionNumber, Archived, CreatedByAdminRecordID, ' .
                'UpdatedByAdminRecordID, CreatedAt, UpdatedAt, PublishedAt FROM RED_Custom_Layouts';
            if (!$includeArchived) {
                $sql .= " WHERE Archived='N'";
            }
            $sql .= ' ORDER BY Archived ASC, COALESCE(NULLIF(PublishedLabel,\'\'), DraftLabel) ASC, LayoutID ASC';
            $result = mysqli_query($connection, $sql);
            if (!$result) {
                return [];
            }
            $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_free_result($result);
            foreach ($rows as &$row) {
                $row['StateHash'] = red_custom_layout_state_hash($row);
            }
            unset($row);

            return $rows;
        } catch (Throwable $exception) {
            error_log('Custom layout list failed: ' . $exception->getMessage());
            return [];
        }
    }
}

if (!function_exists('red_custom_layout_published_definition')) {
    function red_custom_layout_published_definition($connection, $layoutId)
    {
        $row = red_custom_layout_fetch($connection, $layoutId);
        if (!$row
            || ($row['Archived'] ?? 'N') !== 'N'
            || trim((string) ($row['PublishedDefinition'] ?? '')) === ''
            || trim((string) ($row['PublishedLabel'] ?? '')) === ''
        ) {
            return null;
        }

        try {
            $definition = red_custom_layout_normalize_definition((string) $row['PublishedDefinition']);
            if (!hash_equals(
                (string) ($row['PublishedHash'] ?? ''),
                red_custom_layout_definition_hash($definition)
            )) {
                throw new RuntimeException('Published custom layout hash mismatch.');
            }

            return red_custom_layout_catalog_definition(
                (string) $row['LayoutID'],
                (string) $row['PublishedLabel'],
                $definition
            );
        } catch (Throwable $exception) {
            error_log(
                'Published custom layout "' . (string) ($row['LayoutID'] ?? '') .
                '" is invalid: ' . $exception->getMessage()
            );
            return null;
        }
    }
}

if (!function_exists('red_custom_layout_published_catalog')) {
    function red_custom_layout_published_catalog($connection)
    {
        $catalog = [];
        foreach (red_custom_layout_list($connection, false) as $row) {
            $definition = red_custom_layout_published_definition(
                $connection,
                (string) ($row['LayoutID'] ?? '')
            );
            if ($definition !== null) {
                $catalog[$definition['id']] = $definition;
            }
        }
        ksort($catalog, SORT_STRING);

        return $catalog;
    }
}

if (!function_exists('red_custom_layout_reserved_ids')) {
    function red_custom_layout_reserved_ids($projectRoot = null)
    {
        require_once __DIR__ . '/theme_helpers.php';
        $reserved = [];
        foreach (red_theme_discover($projectRoot) as $validation) {
            if (empty($validation['valid']) || !is_array($validation['manifest'] ?? null)) {
                continue;
            }
            try {
                foreach (array_keys(red_theme_layout_manifest_catalog($validation['manifest'])) as $layoutId) {
                    $reserved[strtolower((string) $layoutId)] = true;
                }
                foreach (array_keys(red_theme_layout_manifest_aliases($validation['manifest'])) as $layoutId) {
                    $reserved[strtolower((string) $layoutId)] = true;
                }
            } catch (Throwable $exception) {
                continue;
            }
        }

        return $reserved;
    }
}

?>
