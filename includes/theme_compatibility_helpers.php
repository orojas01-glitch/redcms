<?php
/**
 * Read-only inventory and compatibility reporting for portable RED-CMS themes.
 *
 * These helpers never select, activate, install, or persist theme data. The
 * database-facing boundary issues only fixed SELECT statements against the
 * current layout/component inventory.
 */

require_once __DIR__ . '/theme_helpers.php';

if (!function_exists('red_theme_compatibility_empty_inventory')) {
    function red_theme_compatibility_empty_inventory()
    {
        return [
            'layouts' => [
                'assigned' => [],
                'sources' => [],
                'catalog' => [],
                'custom' => [],
                'requiredPositions' => [],
                'positionSources' => [],
            ],
            'components' => [
                'assigned' => [],
                'sources' => [],
            ],
        ];
    }
}

if (!function_exists('red_theme_compatibility_inventory_from_rows')) {
    function red_theme_compatibility_inventory_from_rows(
        array $layoutRows,
        array $componentRows,
        array $catalogRows = [],
        array $positionRows = []
    ) {
        $inventory = red_theme_compatibility_empty_inventory();

        foreach ($layoutRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $layoutId = trim((string) ($row['layout_id'] ?? ''));
            $source = trim((string) ($row['source_table'] ?? $row['source'] ?? ''));
            $assignments = (int) ($row['assignments'] ?? 0);
            if ($layoutId === '' || $source === '' || $assignments < 1) {
                continue;
            }

            $inventory['layouts']['assigned'][$layoutId] =
                ($inventory['layouts']['assigned'][$layoutId] ?? 0) + $assignments;
            if (!isset($inventory['layouts']['sources'][$source])) {
                $inventory['layouts']['sources'][$source] = [];
            }
            $inventory['layouts']['sources'][$source][$layoutId] =
                ($inventory['layouts']['sources'][$source][$layoutId] ?? 0) + $assignments;
        }

        foreach ($componentRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $componentId = trim((string) ($row['component_id'] ?? ''));
            $source = trim((string) ($row['source_table'] ?? $row['source'] ?? ''));
            $assignments = (int) ($row['assignments'] ?? 0);
            if ($componentId === '' || $source === '' || $assignments < 1) {
                continue;
            }

            $inventory['components']['assigned'][$componentId] =
                ($inventory['components']['assigned'][$componentId] ?? 0) + $assignments;
            if (!isset($inventory['components']['sources'][$source])) {
                $inventory['components']['sources'][$source] = [];
            }
            $inventory['components']['sources'][$source][$componentId] =
                ($inventory['components']['sources'][$source][$componentId] ?? 0) + $assignments;
        }

        foreach ($catalogRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $layoutId = trim((string) ($row['layout_id'] ?? ''));
            $positions = (int) ($row['positions'] ?? 0);
            if ($layoutId === '' || $positions < 1) {
                continue;
            }
            $inventory['layouts']['catalog'][$layoutId] = ['positions' => $positions];
        }

        foreach ($positionRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $layoutId = trim((string) ($row['layout_id'] ?? ''));
            $source = trim((string) ($row['source_table'] ?? $row['source'] ?? ''));
            $positionId = (int) ($row['position_id'] ?? 0);
            $assignments = (int) ($row['assignments'] ?? 0);
            if ($layoutId === '' || $source === '' || $positionId < 1 || $assignments < 1) {
                continue;
            }

            if (!isset($inventory['layouts']['requiredPositions'][$layoutId])) {
                $inventory['layouts']['requiredPositions'][$layoutId] = [];
            }
            $inventory['layouts']['requiredPositions'][$layoutId][$positionId] =
                ($inventory['layouts']['requiredPositions'][$layoutId][$positionId] ?? 0) + $assignments;
            if (!isset($inventory['layouts']['positionSources'][$source])) {
                $inventory['layouts']['positionSources'][$source] = [];
            }
            if (!isset($inventory['layouts']['positionSources'][$source][$layoutId])) {
                $inventory['layouts']['positionSources'][$source][$layoutId] = [];
            }
            $inventory['layouts']['positionSources'][$source][$layoutId][$positionId] =
                ($inventory['layouts']['positionSources'][$source][$layoutId][$positionId] ?? 0)
                + $assignments;
        }

        ksort($inventory['layouts']['assigned'], SORT_STRING);
        ksort($inventory['layouts']['sources'], SORT_STRING);
        foreach ($inventory['layouts']['sources'] as &$layouts) {
            ksort($layouts, SORT_STRING);
        }
        unset($layouts);
        ksort($inventory['layouts']['catalog'], SORT_STRING);
        ksort($inventory['layouts']['requiredPositions'], SORT_STRING);
        foreach ($inventory['layouts']['requiredPositions'] as &$positions) {
            ksort($positions, SORT_NUMERIC);
        }
        unset($positions);
        ksort($inventory['layouts']['positionSources'], SORT_STRING);
        foreach ($inventory['layouts']['positionSources'] as &$sourceLayouts) {
            ksort($sourceLayouts, SORT_STRING);
            foreach ($sourceLayouts as &$positions) {
                ksort($positions, SORT_NUMERIC);
            }
            unset($positions);
        }
        unset($sourceLayouts);
        ksort($inventory['components']['assigned'], SORT_STRING);
        ksort($inventory['components']['sources'], SORT_STRING);
        foreach ($inventory['components']['sources'] as &$components) {
            ksort($components, SORT_STRING);
        }
        unset($components);

        return $inventory;
    }
}

if (!function_exists('red_theme_compatibility_query_rows')) {
    function red_theme_compatibility_query_rows($connection, $query, $label)
    {
        $result = mysqli_query($connection, $query);
        if ($result === false) {
            throw new RuntimeException(
                'Could not read the ' . $label . ' inventory: ' . mysqli_error($connection)
            );
        }

        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);

        return $rows;
    }
}

if (!function_exists('red_theme_compatibility_live_inventory')) {
    function red_theme_compatibility_live_inventory($connection)
    {
        if (!($connection instanceof mysqli)) {
            throw new InvalidArgumentException('A live mysqli connection is required for theme preflight.');
        }

        $layoutRows = red_theme_compatibility_query_rows(
            $connection,
            "SELECT source_table, CAST(layout_id AS BINARY) AS layout_id, COUNT(*) AS assignments\n" .
                "FROM (\n" .
                "    SELECT 'RED_Sections' AS source_table, Layout AS layout_id FROM RED_Sections\n" .
                "    UNION ALL\n" .
                "    SELECT 'RED_Categories', Layout FROM RED_Categories\n" .
                "    UNION ALL\n" .
                "    SELECT 'RED_SubCategories', Layout FROM RED_SubCategories\n" .
                "    UNION ALL\n" .
                "    SELECT 'RED_Articles', Layout FROM RED_Articles\n" .
                ") AS assigned_layouts\n" .
                "WHERE TRIM(layout_id) <> ''\n" .
                "GROUP BY source_table, CAST(layout_id AS BINARY)\n" .
                "ORDER BY source_table, CAST(layout_id AS BINARY)",
            'assigned layout'
        );
        $componentRows = red_theme_compatibility_query_rows(
            $connection,
            "SELECT 'RED_Articles' AS source_table, CAST(Component AS BINARY) AS component_id, COUNT(*) AS assignments\n" .
                "FROM RED_Articles\n" .
                "WHERE TRIM(Component) <> ''\n" .
                "GROUP BY CAST(Component AS BINARY)\n" .
                "ORDER BY CAST(Component AS BINARY)",
            'assigned component'
        );
        $catalogRows = red_theme_compatibility_query_rows(
            $connection,
            "SELECT UniqueName AS layout_id, Positions AS positions\n" .
                "FROM RED_Layouts\n" .
                "ORDER BY UniqueName",
            'layout catalog'
        );

        $positionRows = red_theme_compatibility_query_rows(
            $connection,
            "SELECT source_table, CAST(layout_id AS BINARY) AS layout_id, position_id, COUNT(*) AS assignments\n" .
                "FROM (\n" .
                "    SELECT 'RED_Sections.HomePosition' AS source_table, home_section.Layout AS layout_id, article.HomePosition AS position_id\n" .
                "    FROM RED_Sections AS home_section\n" .
                "    INNER JOIN RED_Articles AS article ON article.HomePosition > 0 AND article.Language = home_section.Language\n" .
                "    WHERE LOWER(home_section.Sections) = 'home'\n" .
                "    UNION ALL\n" .
                "    SELECT 'RED_Sections.SectionPosition', section_area.Layout, article.SectionPosition\n" .
                "    FROM RED_Sections AS section_area\n" .
                "    INNER JOIN RED_Articles AS article ON article.Sections = section_area.Sections AND article.Language = section_area.Language\n" .
                "    WHERE LOWER(section_area.Sections) <> 'home' AND article.SectionPosition > 0\n" .
                "    UNION ALL\n" .
                "    SELECT 'RED_Categories.CategoryPosition', category_area.Layout, article.CategoryPosition\n" .
                "    FROM RED_Categories AS category_area\n" .
                "    INNER JOIN RED_Articles AS article ON article.Categories = category_area.Categories AND article.Language = category_area.Language\n" .
                "    WHERE article.CategoryPosition > 0\n" .
                "    UNION ALL\n" .
                "    SELECT 'RED_SubCategories.SubCategoryPosition', subcategory_area.Layout, article.SubCategoryPosition\n" .
                "    FROM RED_SubCategories AS subcategory_area\n" .
                "    INNER JOIN RED_Articles AS article ON article.SubCategories = subcategory_area.SubCategories AND article.Language = subcategory_area.Language\n" .
                "    WHERE article.SubCategoryPosition > 0\n" .
                "    UNION ALL\n" .
                "    SELECT 'RED_Articles.PagePosition.Self', article.Layout, article.PagePosition\n" .
                "    FROM RED_Articles AS article\n" .
                "    WHERE article.PagePosition > 0\n" .
                "    UNION ALL\n" .
                "    SELECT 'RED_Articles.PagePosition.Parent', page_owner.Layout, article.PagePosition\n" .
                "    FROM RED_Articles AS article\n" .
                "    INNER JOIN RED_Articles AS page_owner ON TRIM(article.Article) <> ''\n" .
                "        AND TRIM(page_owner.Alias) <> ''\n" .
                "        AND article.Article LIKE CONCAT('%', page_owner.Alias, '%')\n" .
                "        AND page_owner.Language = article.Language\n" .
                "        AND (\n" .
                "            LOWER(page_owner.Sections) = 'home'\n" .
                "            OR (TRIM(page_owner.SubCategories) <> '' AND article.Sections = page_owner.Sections AND article.Categories = page_owner.Categories AND article.SubCategories = page_owner.SubCategories)\n" .
                "            OR (TRIM(page_owner.SubCategories) = '' AND TRIM(page_owner.Categories) <> '' AND article.Sections = page_owner.Sections AND article.Categories = page_owner.Categories)\n" .
                "            OR (TRIM(page_owner.SubCategories) = '' AND TRIM(page_owner.Categories) = '' AND LOWER(page_owner.Sections) <> 'home' AND article.Sections = page_owner.Sections)\n" .
                "        )\n" .
                "    WHERE article.PagePosition > 0\n" .
                ") AS assigned_positions\n" .
                "WHERE TRIM(layout_id) <> ''\n" .
                "GROUP BY source_table, CAST(layout_id AS BINARY), position_id\n" .
                "ORDER BY source_table, CAST(layout_id AS BINARY), position_id",
            'assigned layout position'
        );

        $inventory = red_theme_compatibility_inventory_from_rows(
            $layoutRows,
            $componentRows,
            $catalogRows,
            $positionRows
        );
        foreach (red_custom_layout_published_catalog($connection) as $layoutId => $definition) {
            $positionIds = array_values(array_map(
                'intval',
                array_keys($definition['positions'] ?? [])
            ));
            sort($positionIds, SORT_NUMERIC);
            $inventory['layouts']['custom'][$layoutId] = [
                'label' => (string) ($definition['label'] ?? $layoutId),
                'positions' => $positionIds,
            ];
        }
        ksort($inventory['layouts']['custom'], SORT_STRING);

        return $inventory;
    }
}

if (!function_exists('red_theme_compatibility_string_keys')) {
    function red_theme_compatibility_string_keys($value)
    {
        if (!is_array($value)) {
            return [];
        }

        $keys = [];
        foreach (array_keys($value) as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }
        sort($keys, SORT_STRING);

        return $keys;
    }
}

if (!function_exists('red_theme_compatibility_report_from_validation')) {
    function red_theme_compatibility_report_from_validation(array $validation, array $inventory)
    {
        $manifest = isset($validation['manifest']) && is_array($validation['manifest'])
            ? $validation['manifest']
            : [];
        $themeType = isset($manifest['type']) && is_string($manifest['type'])
            ? $manifest['type']
            : '';
        $requiredLayouts = red_theme_compatibility_string_keys(
            $inventory['layouts']['assigned'] ?? []
        );
        $requiredComponents = red_theme_compatibility_string_keys(
            $inventory['components']['assigned'] ?? []
        );
        $providedLayouts = red_theme_compatibility_string_keys($manifest['layouts'] ?? []);
        $providedComponents = red_theme_compatibility_string_keys($manifest['components'] ?? []);
        $customLayouts = red_theme_compatibility_string_keys(
            $inventory['layouts']['custom'] ?? []
        );
        $requiredLayoutPositions = [];
        foreach (($inventory['layouts']['requiredPositions'] ?? []) as $assignedLayoutId => $positions) {
            if (!is_string($assignedLayoutId) || !is_array($positions)) {
                continue;
            }
            $positionIds = array_values(array_map('intval', array_keys($positions)));
            sort($positionIds, SORT_NUMERIC);
            $requiredLayoutPositions[$assignedLayoutId] = $positionIds;
        }
        ksort($requiredLayoutPositions, SORT_STRING);
        $providedLayoutPositions = [];
        $layoutAliases = [];
        $acceptedLayouts = $providedLayouts;
        $resolvedRequiredLayouts = [];
        try {
            if ($manifest !== []) {
                foreach (red_theme_layout_manifest_catalog($manifest) as $canonicalLayoutId => $definition) {
                    $positionIds = array_values(array_map(
                        'intval',
                        array_keys($definition['positions'] ?? [])
                    ));
                    sort($positionIds, SORT_NUMERIC);
                    $providedLayoutPositions[$canonicalLayoutId] = $positionIds;
                }
                ksort($providedLayoutPositions, SORT_STRING);
                $layoutAliases = red_theme_layout_manifest_aliases($manifest);
                ksort($layoutAliases, SORT_STRING);
                $acceptedLayouts = red_theme_layout_accepted_ids($manifest);
                foreach ($requiredLayouts as $requiredLayout) {
                    if (isset($inventory['layouts']['custom'][$requiredLayout])) {
                        $resolvedRequiredLayouts[$requiredLayout] = $requiredLayout;
                        continue;
                    }
                    $resolved = red_theme_layout_resolve_id($manifest, $requiredLayout);
                    if ($resolved !== null) {
                        $resolvedRequiredLayouts[$requiredLayout] = $resolved;
                    }
                }
            }
        } catch (Throwable $exception) {
            $providedLayoutPositions = [];
            $layoutAliases = [];
            $acceptedLayouts = $providedLayouts;
            $resolvedRequiredLayouts = [];
        }
        foreach ($customLayouts as $customLayoutId) {
            $customPositionIds = array_values(array_map(
                'intval',
                $inventory['layouts']['custom'][$customLayoutId]['positions'] ?? []
            ));
            sort($customPositionIds, SORT_NUMERIC);
            $providedLayoutPositions[$customLayoutId] = $customPositionIds;
            if (in_array($customLayoutId, $requiredLayouts, true)) {
                $resolvedRequiredLayouts[$customLayoutId] = $customLayoutId;
            }
        }
        $acceptedLayouts = array_values(array_unique(array_merge($acceptedLayouts, $customLayouts)));
        sort($acceptedLayouts, SORT_STRING);
        ksort($providedLayoutPositions, SORT_STRING);
        ksort($resolvedRequiredLayouts, SORT_STRING);
        $missingLayouts = array_values(array_diff($requiredLayouts, $acceptedLayouts));
        $missingComponents = array_values(array_diff($requiredComponents, $providedComponents));
        $missingLayoutPositions = [];
        foreach ($requiredLayoutPositions as $assignedLayoutId => $positionIds) {
            $resolvedLayoutId = $resolvedRequiredLayouts[$assignedLayoutId] ?? null;
            if (!is_string($resolvedLayoutId) || $resolvedLayoutId === '') {
                continue;
            }
            $availablePositionIds = $providedLayoutPositions[$resolvedLayoutId] ?? [];
            foreach ($positionIds as $positionId) {
                if (!in_array($positionId, $availablePositionIds, true)) {
                    $missingLayoutPositions[] = [
                        'layoutId' => $assignedLayoutId,
                        'resolvedLayoutId' => $resolvedLayoutId,
                        'positionId' => $positionId,
                    ];
                }
            }
        }
        $usedCanonicalLayouts = array_values(array_unique(array_values($resolvedRequiredLayouts)));
        sort($usedCanonicalLayouts, SORT_STRING);
        $unusedLayouts = array_values(array_diff($providedLayouts, $usedCanonicalLayouts));
        $unusedComponents = array_values(array_diff($providedComponents, $requiredComponents));
        $checks = [
            'manifestValid' => !empty($validation['valid']),
            'standardTheme' => $themeType === 'standard',
            'assignedLayoutsCovered' => $missingLayouts === [],
            'assignedLayoutPositionsCovered' => $missingLayoutPositions === [],
            'assignedComponentsCovered' => $missingComponents === [],
        ];
        $blockingReasons = [];
        if (!$checks['manifestValid']) {
            $blockingReasons[] = 'The theme manifest or one of its declared files is invalid.';
        }
        if (!$checks['standardTheme']) {
            $blockingReasons[] = 'Compatibility preflight accepts only portable standard themes.';
        }
        if (!$checks['assignedLayoutsCovered']) {
            $blockingReasons[] = 'The theme is missing one or more layout ids currently assigned to content.';
        }
        if (!$checks['assignedLayoutPositionsCovered']) {
            $blockingReasons[] = 'The theme is missing one or more numbered positions currently used by content.';
        }
        if (!$checks['assignedComponentsCovered']) {
            $blockingReasons[] = 'The theme is missing one or more component views currently assigned to content.';
        }

        $compatible = !in_array(false, $checks, true);

        return [
            'schemaVersion' => 1,
            'mode' => 'read-only',
            'compatible' => $compatible,
            'theme' => [
                'id' => (string) ($validation['theme'] ?? ''),
                'name' => (string) ($manifest['name'] ?? ''),
                'version' => (string) ($manifest['version'] ?? ''),
                'type' => $themeType,
            ],
            'checks' => $checks,
            'requirements' => [
                'layouts' => $requiredLayouts,
                'layoutPositions' => $requiredLayoutPositions,
                'components' => $requiredComponents,
            ],
            'coverage' => [
                'providedLayouts' => $providedLayouts,
                'coreCustomLayouts' => $customLayouts,
                'acceptedLayouts' => $acceptedLayouts,
                'layoutAliases' => $layoutAliases,
                'resolvedRequiredLayouts' => $resolvedRequiredLayouts,
                'providedLayoutPositions' => $providedLayoutPositions,
                'providedComponents' => $providedComponents,
                'missingLayouts' => $missingLayouts,
                'missingLayoutPositions' => $missingLayoutPositions,
                'missingComponents' => $missingComponents,
                'unusedLayouts' => $unusedLayouts,
                'unusedComponents' => $unusedComponents,
            ],
            'inventory' => $inventory,
            'validation' => [
                'valid' => !empty($validation['valid']),
                'errors' => array_values($validation['errors'] ?? []),
                'warnings' => array_values($validation['warnings'] ?? []),
            ],
            'blockingReasons' => $blockingReasons,
            'changes' => [
                'databaseWrites' => 0,
                'themeSelectionWrites' => 0,
                'settingWrites' => 0,
                'previewSessionWrites' => 0,
            ],
        ];
    }
}

if (!function_exists('red_theme_compatibility_report')) {
    function red_theme_compatibility_report($themeId, array $inventory, $projectRoot = null)
    {
        return red_theme_compatibility_report_from_validation(
            red_theme_validate_manifest($themeId, $projectRoot),
            $inventory
        );
    }
}

if (!function_exists('red_theme_compatibility_live_preflight')) {
    function red_theme_compatibility_live_preflight($themeId, $connection, $projectRoot = null)
    {
        return red_theme_compatibility_report(
            $themeId,
            red_theme_compatibility_live_inventory($connection),
            $projectRoot
        );
    }
}
