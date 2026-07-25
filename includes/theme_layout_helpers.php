<?php
/**
 * Theme-defined layout catalogs and compatibility aliases.
 *
 * Standard themes own their canonical layout ids, labels, and numbered slots.
 * Stored content may retain an older id only when the active package declares
 * an explicit alias to one of those canonical layouts.
 */

require_once __DIR__ . '/custom_layout_helpers.php';

if (!function_exists('red_theme_layout_preview_rows')) {
    function red_theme_layout_preview_rows(array $layout, array $positions)
    {
        if (!array_key_exists('adminPreview', $layout)) {
            $fallbackRows = [];
            foreach ($positions as $positionId => $positionLabel) {
                $fallbackRows[] = [[
                    'position' => (int) $positionId,
                    'label' => (string) $positionLabel,
                    'weight' => 1,
                ]];
            }

            return $fallbackRows;
        }

        $preview = $layout['adminPreview'];
        $declaredRows = is_array($preview) ? ($preview['rows'] ?? null) : null;
        if (!is_array($declaredRows) || $declaredRows === []) {
            throw new InvalidArgumentException('Admin preview must declare at least one row.');
        }

        $previewRows = [];
        $seenPositions = [];
        foreach ($declaredRows as $row) {
            if (!is_array($row) || $row === []) {
                throw new InvalidArgumentException('Admin preview rows must contain at least one position.');
            }

            $previewRow = [];
            $rowWeight = 0;
            foreach ($row as $cell) {
                $positionId = is_array($cell) ? ($cell['position'] ?? null) : null;
                $weight = is_array($cell) ? ($cell['weight'] ?? null) : null;
                if (!is_int($positionId)
                    || !isset($positions[$positionId])
                    || isset($seenPositions[$positionId])
                    || !is_int($weight)
                    || $weight < 1
                    || $weight > 12
                ) {
                    throw new InvalidArgumentException(
                        'Admin preview positions must be unique declared ids with integer weights from 1 to 12.'
                    );
                }

                $rowWeight += $weight;
                if ($rowWeight > 12) {
                    throw new InvalidArgumentException('Admin preview row weights cannot total more than 12.');
                }

                $seenPositions[$positionId] = true;
                $previewRow[] = [
                    'position' => $positionId,
                    'label' => (string) $positions[$positionId],
                    'weight' => $weight,
                ];
            }
            $previewRows[] = $previewRow;
        }

        if (count($seenPositions) !== count($positions)) {
            throw new InvalidArgumentException('Admin preview must include every declared position exactly once.');
        }

        return $previewRows;
    }
}

if (!function_exists('red_theme_layout_manifest_catalog')) {
    function red_theme_layout_manifest_catalog(array $manifest)
    {
        $layouts = $manifest['layouts'] ?? null;
        if (!is_array($layouts) || $layouts === []) {
            throw new InvalidArgumentException('Theme layout catalog is unavailable.');
        }

        $catalog = [];
        foreach ($layouts as $layoutId => $layout) {
            $layoutId = is_string($layoutId) ? $layoutId : '';
            if (!red_theme_valid_layout_id($layoutId) || !is_array($layout)) {
                throw new InvalidArgumentException('Theme layout catalog contains an invalid layout.');
            }

            $label = isset($layout['label']) && is_string($layout['label'])
                ? trim($layout['label'])
                : '';
            $declaredPositions = $layout['positions'] ?? null;
            if ($label === '' || !is_array($declaredPositions) || $declaredPositions === []) {
                throw new InvalidArgumentException('Theme layout catalog contains an incomplete layout.');
            }

            $positions = [];
            foreach ($declaredPositions as $position) {
                $positionId = is_array($position) ? ($position['id'] ?? null) : null;
                $positionLabel = is_array($position) && isset($position['label']) && is_string($position['label'])
                    ? trim($position['label'])
                    : '';
                if (!is_int($positionId)
                    || $positionId < 1
                    || $positionId > 99
                    || $positionLabel === ''
                    || isset($positions[$positionId])
                ) {
                    throw new InvalidArgumentException('Theme layout catalog contains an invalid position.');
                }
                $positions[$positionId] = $positionLabel;
            }

            $hiddenPosition = array_key_exists('hiddenPosition', $layout)
                ? $layout['hiddenPosition']
                : null;
            if ($hiddenPosition !== 0) {
                throw new InvalidArgumentException('Theme layouts must declare core-owned hidden position 0.');
            }

            $catalog[$layoutId] = [
                'id' => $layoutId,
                'label' => $label,
                'positions' => $positions,
                'previewRows' => red_theme_layout_preview_rows($layout, $positions),
                'previewIsFallback' => !array_key_exists('adminPreview', $layout),
                'hiddenPosition' => $hiddenPosition,
            ];
        }

        return $catalog;
    }
}

if (!function_exists('red_theme_layout_manifest_aliases')) {
    function red_theme_layout_manifest_aliases(array $manifest)
    {
        $compatibility = $manifest['compatibility'] ?? [];
        $aliases = is_array($compatibility) ? ($compatibility['layoutAliases'] ?? []) : [];
        if (!is_array($aliases)) {
            throw new InvalidArgumentException('Theme layout aliases must be an object.');
        }

        $catalog = red_theme_layout_manifest_catalog($manifest);
        $canonicalIdsByFold = [];
        foreach (array_keys($catalog) as $canonicalLayoutId) {
            $canonicalIdsByFold[strtolower($canonicalLayoutId)] = $canonicalLayoutId;
        }
        $validated = [];
        $aliasIdsByFold = [];
        foreach ($aliases as $assignedLayoutId => $canonicalLayoutId) {
            $assignedLayoutFold = is_string($assignedLayoutId)
                ? strtolower($assignedLayoutId)
                : '';
            if (!is_string($assignedLayoutId)
                || !red_theme_valid_assigned_layout_id($assignedLayoutId)
                || !is_string($canonicalLayoutId)
                || !red_theme_valid_layout_id($canonicalLayoutId)
                || isset($canonicalIdsByFold[$assignedLayoutFold])
                || isset($aliasIdsByFold[$assignedLayoutFold])
                || !isset($catalog[$canonicalLayoutId])
            ) {
                throw new InvalidArgumentException('Theme layout alias is invalid or does not target a canonical layout.');
            }
            $validated[$assignedLayoutId] = $canonicalLayoutId;
            $aliasIdsByFold[$assignedLayoutFold] = $assignedLayoutId;
        }

        return $validated;
    }
}

if (!function_exists('red_theme_layout_resolve_id')) {
    function red_theme_layout_resolve_id(array $manifest, $layoutId)
    {
        $layoutId = is_string($layoutId) ? trim($layoutId) : '';
        if (!red_theme_valid_assigned_layout_id($layoutId)) {
            return null;
        }

        $catalog = red_theme_layout_manifest_catalog($manifest);
        if (red_theme_valid_layout_id($layoutId) && isset($catalog[$layoutId])) {
            return $layoutId;
        }

        $aliases = red_theme_layout_manifest_aliases($manifest);
        return $aliases[$layoutId] ?? null;
    }
}

if (!function_exists('red_theme_layout_accepted_ids')) {
    function red_theme_layout_accepted_ids(array $manifest)
    {
        $ids = array_merge(
            array_keys(red_theme_layout_manifest_catalog($manifest)),
            array_keys(red_theme_layout_manifest_aliases($manifest))
        );
        sort($ids, SORT_STRING);

        return $ids;
    }
}

if (!function_exists('red_theme_layout_definition')) {
    function red_theme_layout_definition(array $manifest, $layoutId)
    {
        $resolvedLayoutId = red_theme_layout_resolve_id($manifest, $layoutId);
        if ($resolvedLayoutId === null) {
            return null;
        }

        $catalog = red_theme_layout_manifest_catalog($manifest);
        $definition = $catalog[$resolvedLayoutId];
        $definition['assignedId'] = (string) $layoutId;
        $definition['resolvedId'] = $resolvedLayoutId;
        $definition['usesAlias'] = $resolvedLayoutId !== (string) $layoutId;

        return $definition;
    }
}

if (!function_exists('red_theme_active_layout_contract')) {
    function red_theme_active_layout_contract($connection, $projectRoot = null)
    {
        if (!($connection instanceof mysqli)) {
            throw new InvalidArgumentException('Active theme layouts require a mysqli connection.');
        }

        require_once __DIR__ . '/theme_activation_helpers.php';
        $projectRoot = red_theme_project_root($projectRoot);
        $state = red_theme_activation_read_state($connection, false, true);
        $requestedThemeId = !empty($state['persisted'])
            ? (string) $state['activeThemeId']
            : 'legacy-bootstrap';
        require_once __DIR__ . '/theme_runtime.php';
        $runtime = red_theme_runtime_bootstrap(
            $requestedThemeId,
            $projectRoot,
            'legacy-bootstrap',
            true
        );
        $resolution = isset($runtime['resolution']) && is_array($runtime['resolution'])
            ? $runtime['resolution']
            : [];
        $manifest = isset($runtime['manifest']) && is_array($runtime['manifest'])
            ? $runtime['manifest']
            : [];
        if ($manifest === [] || empty($runtime['themeId'])) {
            throw new RuntimeException('No valid active theme layout contract is available.');
        }

        $catalog = red_theme_layout_manifest_catalog($manifest);
        $aliases = red_theme_layout_manifest_aliases($manifest);
        $customCatalog = [];
        if ((string) ($runtime['themeType'] ?? ($manifest['type'] ?? '')) === 'standard') {
            foreach (red_custom_layout_published_catalog($connection) as $layoutId => $definition) {
                $foldedId = strtolower((string) $layoutId);
                $collision = false;
                foreach (array_keys($catalog + $aliases) as $existingId) {
                    if (strtolower((string) $existingId) === $foldedId) {
                        $collision = true;
                        break;
                    }
                }
                if ($collision) {
                    error_log(
                        'Custom layout "' . (string) $layoutId .
                        '" is hidden because it conflicts with the active theme contract.'
                    );
                    continue;
                }
                $customCatalog[$layoutId] = $definition;
            }
            $catalog += $customCatalog;
        }

        return [
            'requestedThemeId' => $requestedThemeId,
            'themeId' => (string) $runtime['themeId'],
            'themeType' => (string) ($runtime['themeType'] ?? ($manifest['type'] ?? '')),
            'usedFallback' => !empty($resolution['usedFallback']),
            'manifest' => $manifest,
            'catalog' => $catalog,
            'customCatalog' => $customCatalog,
            'aliases' => $aliases,
        ];
    }
}

if (!function_exists('red_theme_active_layout_definition')) {
    function red_theme_active_layout_definition($connection, $layoutId, $projectRoot = null)
    {
        $contract = red_theme_active_layout_contract($connection, $projectRoot);
        $layoutId = is_string($layoutId) ? trim($layoutId) : '';
        $definition = isset($contract['catalog'][$layoutId])
            ? $contract['catalog'][$layoutId]
            : red_theme_layout_definition($contract['manifest'], $layoutId);
        if ($definition !== null) {
            if (!isset($definition['assignedId'])) {
                $definition['assignedId'] = $layoutId;
            }
            if (!isset($definition['resolvedId'])) {
                $definition['resolvedId'] = (string) ($definition['id'] ?? $layoutId);
            }
            if (!isset($definition['usesAlias'])) {
                $definition['usesAlias'] = false;
            }
            $definition['themeId'] = $contract['themeId'];
            $definition['themeType'] = $contract['themeType'];
        }

        return $definition;
    }
}
