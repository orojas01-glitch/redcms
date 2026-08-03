<?php
/**
 * Non-executing add-on asset planning.
 *
 * Core turns trusted manifest declarations into canonical namespaced CSS/JS
 * plans. It does not read package files, serve an asset, inject markup into a
 * response, execute package PHP, access a database, or change lifecycle.
 */

require_once __DIR__ . '/addon_manifest_helpers.php';

if (!function_exists('red_addon_asset_plan_result')) {
    function red_addon_asset_plan_result($packageId, $surface)
    {
        return [
            'valid' => false,
            'packageId' => is_string($packageId) ? $packageId : '',
            'surface' => is_string($surface) ? $surface : '',
            'assets' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_asset_plan_error')) {
    function red_addon_asset_plan_error(array &$result, $code)
    {
        $code = is_string($code)
            && preg_match('/\A[a-z][a-z0-9_]{0,79}\z/', $code) === 1
                ? $code
                : 'asset_invalid';
        if (!in_array($code, $result['errors'], true)) {
            $result['errors'][] = $code;
        }
    }
}

if (!function_exists('red_addon_asset_type')) {
    function red_addon_asset_type($path)
    {
        if (!is_string($path)
            || !red_addon_valid_relative_path($path)
            || !str_starts_with($path, 'assets/')
        ) {
            return null;
        }
        if (str_ends_with($path, '.css')) {
            return 'style';
        }
        if (str_ends_with($path, '.js')) {
            return 'script';
        }
        return null;
    }
}

if (!function_exists('red_addon_asset_url')) {
    function red_addon_asset_url($packageId, $path, $sha256)
    {
        $parts = red_addon_package_parts($packageId);
        if ($parts === null
            || red_addon_asset_type($path) === null
            || !red_addon_valid_sha256($sha256)
        ) {
            return null;
        }
        $pathSegments = array_map(
            'rawurlencode',
            explode('/', (string) $path)
        );
        return '/_red/addons/' . rawurlencode($parts[0]) . '/' .
            rawurlencode($parts[1]) . '/' . implode('/', $pathSegments) .
            '?v=' . rawurlencode($sha256);
    }
}

if (!function_exists('red_addon_asset_plan_fingerprint')) {
    function red_addon_asset_plan_fingerprint(
        $packageId,
        $surface,
        array $assets
    ) {
        $encoded = json_encode(
            [
                'packageId' => $packageId,
                'surface' => $surface,
                'assets' => $assets,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_asset_plan')) {
    function red_addon_asset_plan(array $manifest, $surface)
    {
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        $result = red_addon_asset_plan_result($packageId, $surface);
        if (!red_addon_valid_package_id($packageId)) {
            red_addon_asset_plan_error($result, 'package_invalid');
            return $result;
        }
        if (!is_string($surface)
            || !in_array($surface, ['public', 'admin'], true)
        ) {
            red_addon_asset_plan_error($result, 'surface_invalid');
            return $result;
        }
        $assets = $manifest['assets'] ?? null;
        $assetSurfaces = is_array($assets) ? array_keys($assets) : [];
        sort($assetSurfaces, SORT_STRING);
        if (!is_array($assets)
            || $assetSurfaces !== ['admin', 'public']
            || !is_array($assets[$surface] ?? null)
            || !array_is_list($assets[$surface])
            || count($assets[$surface]) > 200
        ) {
            red_addon_asset_plan_error($result, 'assets_invalid');
            return $result;
        }

        $planned = [];
        $seenPaths = [];
        foreach ($assets[$surface] as $asset) {
            $keys = is_array($asset) ? array_keys($asset) : [];
            sort($keys, SORT_STRING);
            if ($keys !== ['location', 'path', 'sha256']) {
                red_addon_asset_plan_error($result, 'asset_invalid');
                continue;
            }
            $path = is_string($asset['path'] ?? null) ? $asset['path'] : '';
            $sha256 = is_string($asset['sha256'] ?? null)
                ? $asset['sha256']
                : '';
            $location = is_string($asset['location'] ?? null)
                ? $asset['location']
                : '';
            $type = red_addon_asset_type($path);
            if ($type === null) {
                red_addon_asset_plan_error($result, 'asset_type_unsupported');
                continue;
            }
            if (!red_addon_valid_sha256($sha256)) {
                red_addon_asset_plan_error($result, 'asset_checksum_invalid');
                continue;
            }
            if (($type === 'style' && $location !== 'head')
                || ($type === 'script' && $location !== 'body-end')
            ) {
                red_addon_asset_plan_error($result, 'asset_location_invalid');
                continue;
            }
            if (isset($seenPaths[$path])) {
                red_addon_asset_plan_error($result, 'asset_duplicate');
                continue;
            }
            $url = red_addon_asset_url($packageId, $path, $sha256);
            if (!is_string($url)) {
                red_addon_asset_plan_error($result, 'asset_invalid');
                continue;
            }
            $seenPaths[$path] = true;
            $planned[] = [
                'path' => $path,
                'type' => $type,
                'location' => $location,
                'sha256' => $sha256,
                'url' => $url,
            ];
        }
        if ($result['errors'] !== []) {
            sort($result['errors'], SORT_STRING);
            return $result;
        }
        usort(
            $planned,
            static function (array $left, array $right): int {
                return strcmp(
                    $left['location'] . "\0" . $left['type'] . "\0" . $left['path'],
                    $right['location'] . "\0" . $right['type'] . "\0" . $right['path']
                );
            }
        );
        $planSha256 = red_addon_asset_plan_fingerprint(
            $packageId,
            $surface,
            $planned
        );
        if ($planSha256 === '') {
            red_addon_asset_plan_error($result, 'plan_unavailable');
            return $result;
        }
        $result['valid'] = true;
        $result['assets'] = $planned;
        $result['planSha256'] = $planSha256;
        return $result;
    }
}

if (!function_exists('red_addon_asset_plan_is_valid')) {
    function red_addon_asset_plan_is_valid($plan)
    {
        if (!is_array($plan)
            || empty($plan['valid'])
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !in_array($plan['surface'] ?? null, ['public', 'admin'], true)
            || !is_array($plan['assets'] ?? null)
            || !is_string($plan['planSha256'] ?? null)
            || !red_addon_valid_sha256($plan['planSha256'])
        ) {
            return false;
        }
        $assets = $plan['assets'];
        $canonical = [];
        $seenPaths = [];
        foreach ($assets as $asset) {
            if (!is_array($asset)
                || array_keys($asset) !== [
                    'path', 'type', 'location', 'sha256', 'url',
                ]
            ) {
                return false;
            }
            $path = $asset['path'];
            $type = $asset['type'];
            $location = $asset['location'];
            $sha256 = $asset['sha256'];
            $url = red_addon_asset_url(
                $plan['packageId'],
                $path,
                $sha256
            );
            if (!is_string($path)
                || !is_string($type)
                || !is_string($location)
                || !is_string($sha256)
                || $type !== red_addon_asset_type($path)
                || (($type === 'style' && $location !== 'head')
                    || ($type === 'script' && $location !== 'body-end'))
                || !red_addon_valid_sha256($sha256)
                || isset($seenPaths[$path])
                || !is_string($asset['url'])
                || !is_string($url)
                || !hash_equals($url, $asset['url'])
            ) {
                return false;
            }
            $seenPaths[$path] = true;
            $canonical[] = $asset;
        }
        usort(
            $canonical,
            static function (array $left, array $right): int {
                return strcmp(
                    $left['location'] . "\0" . $left['type'] . "\0" . $left['path'],
                    $right['location'] . "\0" . $right['type'] . "\0" . $right['path']
                );
            }
        );
        if ($canonical !== $assets) {
            return false;
        }
        return hash_equals(
            red_addon_asset_plan_fingerprint(
                $plan['packageId'],
                $plan['surface'],
                $canonical
            ),
            $plan['planSha256']
        );
    }
}

if (!function_exists('red_addon_asset_plan_html')) {
    function red_addon_asset_plan_html($plan, $location)
    {
        if (!red_addon_asset_plan_is_valid($plan)
            || !is_string($location)
            || !in_array($location, ['head', 'body-end'], true)
        ) {
            return '';
        }
        $html = '';
        foreach ($plan['assets'] as $asset) {
            if ($asset['location'] !== $location) {
                continue;
            }
            $url = htmlspecialchars($asset['url'], ENT_QUOTES, 'UTF-8');
            if ($asset['type'] === 'style') {
                $html .= '<link rel="stylesheet" href="' . $url . '">' . "\n";
            } else {
                $html .= '<script src="' . $url . '" defer></script>' . "\n";
            }
        }
        return $html;
    }
}

?>
