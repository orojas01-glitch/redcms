<?php
/**
 * Guarded bridge between validated theme manifests and the live renderer.
 *
 * Standard-theme execution is available only when the caller explicitly opens
 * the production gate. Public requests do that only for a persisted active
 * theme id; preview and compatibility callers retain the legacy fallback.
 */

require_once __DIR__ . '/theme_helpers.php';

if (!function_exists('red_theme_runtime_bootstrap')) {
    function red_theme_runtime_bootstrap(
        $requestedThemeId = 'legacy-bootstrap',
        $projectRoot = null,
        $fallbackThemeId = 'legacy-bootstrap',
        $allowStandard = false
    ) {
        $projectRoot = red_theme_project_root($projectRoot);
        $resolution = red_theme_resolve($requestedThemeId, $projectRoot, $fallbackThemeId);

        if (empty($resolution['valid'])) {
            throw new RuntimeException('No valid compatibility theme is available.');
        }

        $manifest = isset($resolution['manifest']) && is_array($resolution['manifest'])
            ? $resolution['manifest']
            : [];

        $manifestType = (string) ($manifest['type'] ?? '');
        if ($manifestType === 'standard' && $allowStandard === true) {
            try {
                require_once __DIR__ . '/theme_standard_adapter.php';
                $adapter = new RedStandardThemeAdapter($projectRoot, $manifest);
            } catch (Throwable $exception) {
                error_log('RED-CMS standard theme bootstrap failed: ' . $exception->getMessage());
                $unsupportedThemeId = isset($resolution['theme']) ? (string) $resolution['theme'] : '';
                $fallback = red_theme_resolve('', $projectRoot, $fallbackThemeId);
                if (empty($fallback['valid'])
                    || !is_array($fallback['manifest'] ?? null)
                    || ($fallback['manifest']['type'] ?? '') !== 'legacy-adapter'
                ) {
                    throw new RuntimeException('No supported fallback adapter is available.');
                }
                $fallback['requestedTheme'] = is_string($requestedThemeId) ? trim($requestedThemeId) : '';
                $fallback['usedFallback'] = true;
                array_unshift(
                    $fallback['warnings'],
                    'Theme "' . $unsupportedThemeId . '" could not initialize; using "' . $fallbackThemeId . '".'
                );
                $resolution = $fallback;
                $manifest = $resolution['manifest'];
                $manifestType = 'legacy-adapter';
                $adapter = null;
            }
        } elseif ($manifestType !== 'legacy-adapter') {
            $unsupportedThemeId = isset($resolution['theme']) ? (string) $resolution['theme'] : '';
            $fallback = red_theme_resolve('', $projectRoot, $fallbackThemeId);
            if (empty($fallback['valid'])
                || !is_array($fallback['manifest'] ?? null)
                || ($fallback['manifest']['type'] ?? '') !== 'legacy-adapter'
            ) {
                throw new RuntimeException('No supported compatibility adapter is available.');
            }

            $fallback['requestedTheme'] = is_string($requestedThemeId) ? trim($requestedThemeId) : '';
            $fallback['usedFallback'] = true;
            array_unshift(
                $fallback['warnings'],
                'Theme "' . $unsupportedThemeId . '" is not supported by the compatibility runtime; using "' .
                    $fallbackThemeId . '".'
            );
            $resolution = $fallback;
            $manifest = $resolution['manifest'];
            $manifestType = 'legacy-adapter';
            $adapter = null;
        }

        if (!isset($adapter) || !is_object($adapter)) {
            $adapterPath = red_theme_existing_path($resolution['path'], $manifest['adapter'] ?? '');
            $adapterClass = $manifest['adapterClass'] ?? '';
            if ($adapterPath === null
                || !is_file($adapterPath)
                || strtolower(pathinfo($adapterPath, PATHINFO_EXTENSION)) !== 'php'
                || !red_theme_valid_php_class_name($adapterClass)
            ) {
                throw new RuntimeException('The compatibility adapter declaration is invalid.');
            }

            require_once $adapterPath;
            if (!class_exists($adapterClass, false)) {
                throw new RuntimeException('The compatibility adapter class is unavailable.');
            }

            $adapter = new $adapterClass($projectRoot, $manifest);
        }
        foreach (
            [
                'renderDocumentStart',
                'renderHeaderBundle',
                'renderPublicLayout',
                'renderPublicArticleComponent',
                'renderPublicFormComponent',
                'renderPublicGalleryComponent',
                'renderPublicOtherComponent',
                'renderFooter',
                'renderDocumentEnd',
            ]
            as $method
        ) {
            if (!is_callable([$adapter, $method])) {
                throw new RuntimeException('The compatibility adapter contract is incomplete.');
            }
        }

        return [
            'themeId' => $resolution['theme'],
            'themeType' => (string) ($manifest['type'] ?? ''),
            'standardExecutionEnabled' => ($manifest['type'] ?? '') === 'standard' && $allowStandard === true,
            'manifest' => $manifest,
            'resolution' => $resolution,
            'adapter' => $adapter,
        ];
    }
}
