<?php
/**
 * File-based theme discovery, manifest validation, and safe fallback helpers.
 *
 * The live renderer uses these helpers only through the guarded theme runtime.
 * A later Milestone 4 batch can pass a database-backed theme id to
 * red_theme_resolve() without making unvalidated manifest paths executable.
 */

if (!function_exists('red_theme_project_root')) {
    function red_theme_project_root($projectRoot = null)
    {
        $candidate = $projectRoot === null || $projectRoot === ''
            ? dirname(__DIR__)
            : (string) $projectRoot;
        $resolved = realpath($candidate);

        return $resolved !== false ? $resolved : rtrim($candidate, '/\\');
    }
}

if (!function_exists('red_theme_root')) {
    function red_theme_root($projectRoot = null)
    {
        return red_theme_project_root($projectRoot) . DIRECTORY_SEPARATOR . 'themes';
    }
}

if (!function_exists('red_theme_valid_id')) {
    function red_theme_valid_id($themeId)
    {
        return is_string($themeId)
            && preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?\z/', $themeId) === 1;
    }
}

if (!function_exists('red_theme_valid_layout_id')) {
    function red_theme_valid_layout_id($layoutId)
    {
        return is_string($layoutId)
            && preg_match('/\A[a-z](?:[a-z0-9-]{0,62}[a-z0-9])?\z/', $layoutId) === 1;
    }
}

if (!function_exists('red_theme_valid_assigned_layout_id')) {
    function red_theme_valid_assigned_layout_id($layoutId)
    {
        return is_string($layoutId)
            && preg_match('/\A[A-Za-z0-9](?:[A-Za-z0-9_-]{0,62}[A-Za-z0-9])?\z/', $layoutId) === 1;
    }
}

require_once __DIR__ . '/theme_layout_helpers.php';

if (!function_exists('red_theme_valid_relative_path')) {
    function red_theme_valid_relative_path($path)
    {
        if (!is_string($path) || $path === '' || strpos($path, "\0") !== false) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        if ($normalized[0] === '/' || preg_match('/\A[A-Za-z]:\//', $normalized) === 1) {
            return false;
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_valid_php_class_name')) {
    function red_theme_valid_php_class_name($className)
    {
        return is_string($className)
            && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $className) === 1;
    }
}

if (!function_exists('red_theme_existing_path')) {
    function red_theme_existing_path($baseDirectory, $relativePath)
    {
        if (!red_theme_valid_relative_path($relativePath)) {
            return null;
        }

        $base = realpath((string) $baseDirectory);
        if ($base === false || !is_dir($base)) {
            return null;
        }

        $candidate = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $resolved = realpath($candidate);
        if ($resolved === false) {
            return null;
        }

        if ($resolved !== $base && strpos($resolved, $base . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        return $resolved;
    }
}

if (!function_exists('red_theme_manifest_file')) {
    function red_theme_manifest_file($themeId, $projectRoot = null)
    {
        if (!red_theme_valid_id($themeId)) {
            return null;
        }

        return red_theme_root($projectRoot) . DIRECTORY_SEPARATOR . $themeId . DIRECTORY_SEPARATOR . 'theme.json';
    }
}

if (!function_exists('red_theme_validation_result')) {
    function red_theme_validation_result($themeId, $themeDirectory)
    {
        return [
            'valid' => false,
            'theme' => (string) $themeId,
            'path' => (string) $themeDirectory,
            'manifest' => null,
            'errors' => [],
            'warnings' => [],
        ];
    }
}

if (!function_exists('red_theme_add_error')) {
    function red_theme_add_error(array &$result, $message)
    {
        $result['errors'][] = (string) $message;
    }
}

if (!function_exists('red_theme_add_warning')) {
    function red_theme_add_warning(array &$result, $message)
    {
        $result['warnings'][] = (string) $message;
    }
}

if (!function_exists('red_theme_require_string')) {
    function red_theme_require_string(array $manifest, $key, array &$result)
    {
        if (!isset($manifest[$key]) || !is_string($manifest[$key]) || trim($manifest[$key]) === '') {
            red_theme_add_error($result, 'Manifest field "' . $key . '" must be a non-empty string.');
            return '';
        }

        return trim($manifest[$key]);
    }
}

if (!function_exists('red_theme_validate_declared_file')) {
    function red_theme_validate_declared_file(
        array $definition,
        $context,
        $themeType,
        $themeDirectory,
        $projectRoot,
        array &$result
    ) {
        $field = $themeType === 'legacy-adapter' ? 'legacySource' : 'template';
        if (!isset($definition[$field]) || !is_string($definition[$field]) || $definition[$field] === '') {
            red_theme_add_error($result, $context . ' must declare a non-empty "' . $field . '" path.');
            return;
        }

        $base = $themeType === 'legacy-adapter' ? $projectRoot : $themeDirectory;
        if (red_theme_existing_path($base, $definition[$field]) === null) {
            red_theme_add_error($result, $context . ' references a missing or unsafe file: ' . $definition[$field]);
        }
    }
}

if (!function_exists('red_theme_validate_asset_group')) {
    function red_theme_validate_asset_group(
        $groupName,
        $assets,
        $themeType,
        $themeDirectory,
        $projectRoot,
        array &$result
    ) {
        if (!is_array($assets)) {
            red_theme_add_error($result, 'Asset group "' . $groupName . '" must be an array.');
            return;
        }

        $ids = [];
        foreach ($assets as $index => $asset) {
            $context = 'Asset ' . $groupName . '[' . $index . ']';
            if (!is_array($asset)) {
                red_theme_add_error($result, $context . ' must be an object.');
                continue;
            }

            $id = isset($asset['id']) && is_string($asset['id']) ? $asset['id'] : '';
            if (preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $id) !== 1) {
                red_theme_add_error($result, $context . ' must have a lowercase id.');
            } elseif (isset($ids[$id])) {
                red_theme_add_error($result, 'Asset id "' . $id . '" is duplicated in ' . $groupName . '.');
            } else {
                $ids[$id] = true;
            }

            $location = isset($asset['location']) && is_string($asset['location']) ? $asset['location'] : '';
            if (!in_array($location, ['head', 'body-end'], true)) {
                red_theme_add_error($result, $context . ' location must be "head" or "body-end".');
            }

            $hasUrl = isset($asset['url']) && is_string($asset['url']) && $asset['url'] !== '';
            $fileField = $themeType === 'legacy-adapter' ? 'legacySource' : 'path';
            $hasFile = isset($asset[$fileField]) && is_string($asset[$fileField]) && $asset[$fileField] !== '';
            if ($hasUrl === $hasFile) {
                red_theme_add_error(
                    $result,
                    $context . ' must declare exactly one of "url" or "' . $fileField . '".'
                );
                continue;
            }

            if ($hasUrl) {
                if (filter_var($asset['url'], FILTER_VALIDATE_URL) === false
                    || stripos($asset['url'], 'https://') !== 0
                ) {
                    red_theme_add_error($result, $context . ' external URL must use HTTPS.');
                }
                if ($groupName === 'scripts' && empty($asset['integrity'])) {
                    red_theme_add_warning($result, $context . ' is external and has no integrity value.');
                }
                continue;
            }

            $base = $themeType === 'legacy-adapter' ? $projectRoot : $themeDirectory;
            if (red_theme_existing_path($base, $asset[$fileField]) === null) {
                red_theme_add_error(
                    $result,
                    $context . ' references a missing or unsafe file: ' . $asset[$fileField]
                );
            }
        }
    }
}

if (!function_exists('red_theme_standard_breadcrumbs_enabled')) {
    /**
     * Standard themes retain URL-derived breadcrumbs unless their manifest
     * explicitly disables the reserved navigation-level checkbox.
     */
    function red_theme_standard_breadcrumbs_enabled(array $manifest)
    {
        $settings = is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [];
        foreach ($settings as $setting) {
            if (!is_array($setting) || ($setting['key'] ?? '') !== 'navigation.breadcrumbs') {
                continue;
            }

            return ($setting['type'] ?? '') === 'checkbox'
                && array_key_exists('default', $setting)
                && is_bool($setting['default'])
                    ? $setting['default']
                    : true;
        }

        return true;
    }
}

if (!function_exists('red_theme_validate_manifest')) {
    function red_theme_validate_manifest($themeId, $projectRoot = null)
    {
        $projectRoot = red_theme_project_root($projectRoot);
        $themeDirectory = red_theme_root($projectRoot) . DIRECTORY_SEPARATOR . (string) $themeId;
        $result = red_theme_validation_result($themeId, $themeDirectory);

        if (!red_theme_valid_id($themeId)) {
            red_theme_add_error(
                $result,
                'Theme id must use lowercase letters, numbers, and hyphens and must begin and end with a letter or number.'
            );
            return $result;
        }

        $resolvedThemeRoot = realpath(red_theme_root($projectRoot));
        $resolvedThemeDirectory = realpath($themeDirectory);
        if ($resolvedThemeDirectory === false || !is_dir($resolvedThemeDirectory)) {
            red_theme_add_error($result, 'Theme directory does not exist: themes/' . $themeId);
            return $result;
        }
        if ($resolvedThemeRoot === false
            || strpos($resolvedThemeDirectory, $resolvedThemeRoot . DIRECTORY_SEPARATOR) !== 0
        ) {
            red_theme_add_error($result, 'Theme directory resolves outside the RED-CMS themes directory.');
            return $result;
        }
        $result['path'] = $resolvedThemeDirectory;

        $manifestPath = $resolvedThemeDirectory . DIRECTORY_SEPARATOR . 'theme.json';
        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            red_theme_add_error($result, 'Theme manifest is missing or unreadable: themes/' . $themeId . '/theme.json');
            return $result;
        }

        $json = file_get_contents($manifestPath);
        if ($json === false) {
            red_theme_add_error($result, 'Theme manifest could not be read.');
            return $result;
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            red_theme_add_error($result, 'Theme manifest contains invalid JSON: ' . $exception->getMessage());
            return $result;
        }

        if (!is_array($manifest)) {
            red_theme_add_error($result, 'Theme manifest root must be an object.');
            return $result;
        }
        $result['manifest'] = $manifest;

        if (($manifest['schemaVersion'] ?? null) !== 1) {
            red_theme_add_error($result, 'Manifest field "schemaVersion" must be the integer 1.');
        }

        $manifestId = red_theme_require_string($manifest, 'id', $result);
        if ($manifestId !== '' && $manifestId !== $themeId) {
            red_theme_add_error($result, 'Manifest id must match its theme directory name.');
        }

        red_theme_require_string($manifest, 'name', $result);
        red_theme_require_string($manifest, 'description', $result);
        $version = red_theme_require_string($manifest, 'version', $result);
        if ($version !== '' && preg_match('/\A\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?\z/', $version) !== 1) {
            red_theme_add_error($result, 'Manifest version must use semantic version format, for example 1.0.0.');
        }

        $themeType = red_theme_require_string($manifest, 'type', $result);
        if (!in_array($themeType, ['standard', 'legacy-adapter'], true)) {
            red_theme_add_error($result, 'Manifest type must be "standard" or "legacy-adapter".');
        }

        $compatibility = $manifest['compatibility'] ?? null;
        if (!is_array($compatibility)) {
            red_theme_add_error($result, 'Manifest field "compatibility" must be an object.');
        } else {
            red_theme_require_string($compatibility, 'cms', $result);
            red_theme_require_string($compatibility, 'php', $result);
            $layoutAliases = $compatibility['layoutAliases'] ?? [];
            if (!is_array($layoutAliases)) {
                red_theme_add_error($result, 'Compatibility layoutAliases must be an object when declared.');
            } else {
                foreach ($layoutAliases as $assignedLayoutId => $canonicalLayoutId) {
                    if (!is_string($assignedLayoutId)
                        || !red_theme_valid_assigned_layout_id($assignedLayoutId)
                        || !is_string($canonicalLayoutId)
                        || !red_theme_valid_layout_id($canonicalLayoutId)
                    ) {
                        red_theme_add_error($result, 'Compatibility layoutAliases must map safe layout ids.');
                    }
                }
            }
        }

        $preview = red_theme_require_string($manifest, 'preview', $result);
        if ($preview !== '' && red_theme_existing_path($resolvedThemeDirectory, $preview) === null) {
            red_theme_add_error($result, 'Theme preview references a missing or unsafe file: ' . $preview);
        }

        if ($themeType === 'legacy-adapter') {
            $adapter = isset($manifest['adapter']) && is_string($manifest['adapter']) ? $manifest['adapter'] : '';
            $adapterPath = $adapter !== ''
                ? red_theme_existing_path($resolvedThemeDirectory, $adapter)
                : null;
            if ($adapterPath === null
                || !is_file($adapterPath)
                || strtolower(pathinfo($adapterPath, PATHINFO_EXTENSION)) !== 'php'
            ) {
                red_theme_add_error($result, 'Legacy theme must declare an existing local PHP adapter file.');
            }

            $adapterClass = isset($manifest['adapterClass']) && is_string($manifest['adapterClass'])
                ? $manifest['adapterClass']
                : '';
            if (!red_theme_valid_php_class_name($adapterClass)) {
                red_theme_add_error($result, 'Legacy theme must declare a safe adapterClass name.');
            }
            red_theme_add_warning(
                $result,
                'Legacy adapter references current CMS files and is intentionally not a portable standalone theme.'
            );
        }

        $assets = $manifest['assets'] ?? null;
        if (!is_array($assets)) {
            red_theme_add_error($result, 'Manifest field "assets" must be an object.');
        } else {
            red_theme_validate_asset_group(
                'styles',
                $assets['styles'] ?? null,
                $themeType,
                $resolvedThemeDirectory,
                $projectRoot,
                $result
            );
            red_theme_validate_asset_group(
                'scripts',
                $assets['scripts'] ?? null,
                $themeType,
                $resolvedThemeDirectory,
                $projectRoot,
                $result
            );
        }

        $requiredRegions = ['document', 'header', 'navigation', 'hero', 'footer'];
        $regions = $manifest['regions'] ?? null;
        if (!is_array($regions)) {
            red_theme_add_error($result, 'Manifest field "regions" must be an object.');
        } else {
            foreach ($requiredRegions as $regionId) {
                if (!isset($regions[$regionId]) || !is_array($regions[$regionId])) {
                    red_theme_add_error($result, 'Theme must declare the "' . $regionId . '" region.');
                    continue;
                }
                red_theme_require_string($regions[$regionId], 'label', $result);
                red_theme_validate_declared_file(
                    $regions[$regionId],
                    'Region "' . $regionId . '"',
                    $themeType,
                    $resolvedThemeDirectory,
                    $projectRoot,
                    $result
                );
            }
        }

        $layouts = $manifest['layouts'] ?? null;
        if (!is_array($layouts) || $layouts === []) {
            red_theme_add_error($result, 'Manifest field "layouts" must contain at least one layout.');
        } else {
            foreach ($layouts as $layoutId => $layout) {
                if (!is_string($layoutId) || !red_theme_valid_layout_id($layoutId)) {
                    red_theme_add_error($result, 'Layout id "' . $layoutId . '" is not a safe lowercase id.');
                    continue;
                }
                if (!is_array($layout)) {
                    red_theme_add_error($result, 'Layout "' . $layoutId . '" must be an object.');
                    continue;
                }

                red_theme_require_string($layout, 'label', $result);
                red_theme_validate_declared_file(
                    $layout,
                    'Layout "' . $layoutId . '"',
                    $themeType,
                    $resolvedThemeDirectory,
                    $projectRoot,
                    $result
                );

                $positions = $layout['positions'] ?? null;
                if (!is_array($positions) || $positions === []) {
                    red_theme_add_error($result, 'Layout "' . $layoutId . '" must declare at least one position.');
                    continue;
                }

                $positionIds = [];
                $positionLabels = [];
                foreach ($positions as $positionIndex => $position) {
                    if (!is_array($position)) {
                        red_theme_add_error(
                            $result,
                            'Layout "' . $layoutId . '" position ' . $positionIndex . ' must be an object.'
                        );
                        continue;
                    }
                    $positionId = $position['id'] ?? null;
                    if (!is_int($positionId) || $positionId < 1 || $positionId > 99) {
                        red_theme_add_error(
                            $result,
                            'Layout "' . $layoutId . '" position ids must be unique integers from 1 to 99.'
                        );
                    } elseif (isset($positionIds[$positionId])) {
                        red_theme_add_error(
                            $result,
                            'Layout "' . $layoutId . '" repeats position id ' . $positionId . '.'
                        );
                    } else {
                        $positionIds[$positionId] = true;
                        $positionLabels[$positionId] = isset($position['label']) && is_string($position['label'])
                            ? trim($position['label'])
                            : '';
                    }
                    red_theme_require_string($position, 'label', $result);
                }

                if (count($positionIds) === count($positions)) {
                    try {
                        red_theme_layout_preview_rows($layout, $positionLabels);
                    } catch (InvalidArgumentException $exception) {
                        red_theme_add_error(
                            $result,
                            'Layout "' . $layoutId . '" has an invalid adminPreview: ' . $exception->getMessage()
                        );
                    }
                }

                if (!array_key_exists('hiddenPosition', $layout) || $layout['hiddenPosition'] !== 0) {
                    red_theme_add_error($result, 'Layout "' . $layoutId . '" must declare core-owned hiddenPosition 0.');
                }
            }
        }

        if (isset($layoutAliases) && is_array($layoutAliases) && is_array($layouts)) {
            $canonicalLayoutIdsByFold = [];
            foreach (array_keys($layouts) as $canonicalLayoutId) {
                if (is_string($canonicalLayoutId)) {
                    $canonicalLayoutIdsByFold[strtolower($canonicalLayoutId)] = $canonicalLayoutId;
                }
            }
            $aliasIdsByFold = [];
            foreach ($layoutAliases as $assignedLayoutId => $canonicalLayoutId) {
                $assignedLayoutFold = is_string($assignedLayoutId)
                    ? strtolower($assignedLayoutId)
                    : '';
                if (is_string($assignedLayoutId)
                    && is_string($canonicalLayoutId)
                    && (isset($canonicalLayoutIdsByFold[$assignedLayoutFold])
                        || isset($aliasIdsByFold[$assignedLayoutFold])
                        || !isset($layouts[$canonicalLayoutId]))
                ) {
                    red_theme_add_error(
                        $result,
                        'Compatibility layout alias "' . $assignedLayoutId .
                        '" must be case-insensitively distinct and target a declared canonical layout.'
                    );
                }
                if ($assignedLayoutFold !== '') {
                    $aliasIdsByFold[$assignedLayoutFold] = (string) $assignedLayoutId;
                }
            }
        }

        $components = $manifest['components'] ?? null;
        $requiredComponents = ['Article', 'Form', 'Gallery', 'Other'];
        if (!is_array($components)) {
            red_theme_add_error($result, 'Manifest field "components" must be an object.');
        } else {
            $componentIds = array_keys($components);
            sort($componentIds, SORT_STRING);
            $expectedComponentIds = $requiredComponents;
            sort($expectedComponentIds, SORT_STRING);
            if ($componentIds !== $expectedComponentIds) {
                red_theme_add_error(
                    $result,
                    'Theme components must contain exactly Article, Form, Gallery, and Other.'
                );
            }
            foreach ($requiredComponents as $componentId) {
                if (!isset($components[$componentId]) || !is_array($components[$componentId])) {
                    red_theme_add_error($result, 'Theme must declare the "' . $componentId . '" component.');
                    continue;
                }
                red_theme_require_string($components[$componentId], 'label', $result);
                red_theme_validate_declared_file(
                    $components[$componentId],
                    'Component "' . $componentId . '"',
                    $themeType,
                    $resolvedThemeDirectory,
                    $projectRoot,
                    $result
                );
            }
        }

        $features = $manifest['features'] ?? [];
        if (!is_array($features)) {
            red_theme_add_error($result, 'Manifest field "features" must be an object when declared.');
        } else {
            foreach ($features as $featureId => $feature) {
                if (!red_theme_valid_id((string) $featureId)) {
                    red_theme_add_error($result, 'Feature id "' . $featureId . '" is not a safe lowercase id.');
                    continue;
                }
                if (!is_array($feature)) {
                    red_theme_add_error($result, 'Feature "' . $featureId . '" must be an object.');
                    continue;
                }
                red_theme_require_string($feature, 'label', $result);
                red_theme_validate_declared_file(
                    $feature,
                    'Feature "' . $featureId . '"',
                    $themeType,
                    $resolvedThemeDirectory,
                    $projectRoot,
                    $result
                );
            }
        }

        $settings = $manifest['settings'] ?? null;
        if (!is_array($settings)) {
            red_theme_add_error($result, 'Manifest field "settings" must be an array.');
        } else {
            $settingKeys = [];
            $allowedTypes = ['text', 'textarea', 'url', 'image', 'color', 'number', 'select', 'checkbox', 'menu'];
            foreach ($settings as $settingIndex => $setting) {
                $context = 'Setting ' . $settingIndex;
                if (!is_array($setting)) {
                    red_theme_add_error($result, $context . ' must be an object.');
                    continue;
                }
                $key = isset($setting['key']) && is_string($setting['key']) ? $setting['key'] : '';
                if (preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $key) !== 1) {
                    red_theme_add_error($result, $context . ' must have a safe lowercase key.');
                } elseif (isset($settingKeys[$key])) {
                    red_theme_add_error($result, 'Setting key "' . $key . '" is duplicated.');
                } else {
                    $settingKeys[$key] = true;
                }
                red_theme_require_string($setting, 'label', $result);
                $settingType = isset($setting['type']) && is_string($setting['type']) ? $setting['type'] : '';
                if (!in_array($settingType, $allowedTypes, true)) {
                    red_theme_add_error($result, $context . ' has an unsupported type.');
                }
                if ($key === 'navigation.breadcrumbs'
                    && ($settingType !== 'checkbox'
                        || !array_key_exists('default', $setting)
                        || !is_bool($setting['default']))
                ) {
                    red_theme_add_error(
                        $result,
                        'Reserved setting "navigation.breadcrumbs" must be a checkbox with a boolean default.'
                    );
                }
                if ($key === 'branding.logo'
                    && ($settingType !== 'image'
                        || ($setting['legacyItem'] ?? '') !== 'Website_Logo')
                ) {
                    red_theme_add_error(
                        $result,
                        'Reserved setting "branding.logo" must be an image mapped to legacy item "Website_Logo".'
                    );
                }
            }
        }

        $result['valid'] = $result['errors'] === [];
        return $result;
    }
}

if (!function_exists('red_theme_discover')) {
    function red_theme_discover($projectRoot = null)
    {
        $themeRoot = red_theme_root($projectRoot);
        if (!is_dir($themeRoot) || !is_readable($themeRoot)) {
            return [];
        }

        $themes = [];
        $entries = scandir($themeRoot);
        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if (!red_theme_valid_id($entry) || !is_dir($themeRoot . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }
            $themes[$entry] = red_theme_validate_manifest($entry, $projectRoot);
        }
        ksort($themes, SORT_STRING);

        return $themes;
    }
}

if (!function_exists('red_theme_resolve')) {
    function red_theme_resolve($requestedThemeId, $projectRoot = null, $fallbackThemeId = 'legacy-bootstrap')
    {
        $requestedThemeId = is_string($requestedThemeId) ? trim($requestedThemeId) : '';
        if ($requestedThemeId !== '') {
            $requested = red_theme_validate_manifest($requestedThemeId, $projectRoot);
            if ($requested['valid']) {
                $requested['requestedTheme'] = $requestedThemeId;
                $requested['usedFallback'] = false;
                return $requested;
            }
        } else {
            $requested = null;
        }

        $fallback = red_theme_validate_manifest($fallbackThemeId, $projectRoot);
        $fallback['requestedTheme'] = $requestedThemeId;
        $fallback['usedFallback'] = true;
        if ($fallback['valid'] && $requested !== null) {
            array_unshift(
                $fallback['warnings'],
                'Requested theme "' . $requestedThemeId . '" is invalid; using "' . $fallbackThemeId . '".'
            );
            $fallback['requestedErrors'] = $requested['errors'];
        } elseif (!$fallback['valid'] && $requested !== null) {
            $fallback['requestedErrors'] = $requested['errors'];
        }

        return $fallback;
    }
}

if (!function_exists('red_theme_standard_production_validation')) {
    function red_theme_standard_production_validation(array $manifest, $themeDirectory)
    {
        $result = ['valid' => false, 'errors' => [], 'files' => []];
        if (($manifest['type'] ?? '') !== 'standard') {
            $result['errors'][] = 'Production execution requires a standard theme.';
            return $result;
        }

        $themeDirectory = realpath((string) $themeDirectory);
        if ($themeDirectory === false || !is_dir($themeDirectory)) {
            $result['errors'][] = 'Production theme directory is unavailable.';
            return $result;
        }

        $production = $manifest['production'] ?? null;
        if (!is_array($production)) {
            $result['errors'][] = 'Standard theme has no production contract.';
            return $result;
        }
        $productionKeys = array_keys($production);
        sort($productionKeys, SORT_STRING);
        if ($productionKeys !== ['assets', 'components', 'layouts', 'regions']) {
            $result['errors'][] = 'Production contract must contain only assets, regions, layouts, and components.';
            return $result;
        }

        $expectedGroups = [
            'regions' => ['document', 'footer', 'header', 'hero', 'navigation'],
            'layouts' => array_keys(is_array($manifest['layouts'] ?? null) ? $manifest['layouts'] : []),
            'components' => array_keys(is_array($manifest['components'] ?? null) ? $manifest['components'] : []),
        ];
        foreach ($expectedGroups as $group => $expectedIds) {
            sort($expectedIds, SORT_STRING);
            $definitions = $production[$group] ?? null;
            $actualIds = is_array($definitions) ? array_keys($definitions) : [];
            sort($actualIds, SORT_STRING);
            if ($actualIds !== $expectedIds) {
                $result['errors'][] = 'Production ' . $group . ' must exactly cover the portable contract.';
                continue;
            }
            foreach ($definitions as $id => $definition) {
                if (!is_array($definition) || array_keys($definition) !== ['template']) {
                    $result['errors'][] = 'Production ' . $group . ' view "' . $id . '" is malformed.';
                    continue;
                }
                $file = red_theme_existing_path($themeDirectory, $definition['template'] ?? '');
                if ($file === null
                    || !is_file($file)
                    || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php'
                ) {
                    $result['errors'][] = 'Production ' . $group . ' view "' . $id . '" is missing or unsafe.';
                    continue;
                }
                $result['files'][] = $file;
            }
        }

        $assets = $production['assets'] ?? null;
        if (!is_array($assets) || array_keys($assets) !== ['styles', 'scripts']) {
            $result['errors'][] = 'Production assets must contain exact styles and scripts arrays.';
        } else {
            $ids = [];
            foreach (['styles', 'scripts'] as $group) {
                if (!is_array($assets[$group])) {
                    $result['errors'][] = 'Production asset group "' . $group . '" is invalid.';
                    continue;
                }
                foreach ($assets[$group] as $index => $asset) {
                    if (!is_array($asset)) {
                        $result['errors'][] = 'Production asset ' . $group . '[' . $index . '] is invalid.';
                        continue;
                    }
                    $id = $asset['id'] ?? '';
                    $location = $asset['location'] ?? '';
                    $hasPath = isset($asset['path']) && is_string($asset['path']) && $asset['path'] !== '';
                    $hasUrl = isset($asset['url']) && is_string($asset['url']) && $asset['url'] !== '';
                    if (!is_string($id)
                        || preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $id) !== 1
                        || isset($ids[$id])
                        || !in_array($location, ['head', 'body-end'], true)
                        || $hasPath === $hasUrl
                    ) {
                        $result['errors'][] = 'Production asset ' . $group . '[' . $index . '] is malformed.';
                        continue;
                    }
                    $ids[$id] = true;
                    if ($hasPath) {
                        $file = red_theme_existing_path($themeDirectory, $asset['path']);
                        if ($file === null || !is_file($file)) {
                            $result['errors'][] = 'Production asset "' . $id . '" is missing or unsafe.';
                            continue;
                        }
                        $result['files'][] = $file;
                    } elseif (filter_var($asset['url'], FILTER_VALIDATE_URL) === false
                        || stripos($asset['url'], 'https://') !== 0
                    ) {
                        $result['errors'][] = 'Production asset "' . $id . '" must use HTTPS.';
                    }
                }
            }
        }

        $result['files'] = array_values(array_unique($result['files']));
        sort($result['files'], SORT_STRING);
        $result['valid'] = $result['errors'] === [];
        return $result;
    }
}
