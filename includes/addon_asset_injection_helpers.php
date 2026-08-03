<?php
/**
 * Core-owned document injection for immutable add-on assets.
 *
 * This layer reconciles only trusted manifest plans and current registry
 * evidence. It never includes add-on PHP, serves a response, starts a
 * session, or changes lifecycle state. A failed plan simply yields no markup.
 */

require_once __DIR__ . '/addon_asset_helpers.php';
require_once __DIR__ . '/addon_registry_helpers.php';

if (!function_exists('red_addon_asset_injection_result')) {
    function red_addon_asset_injection_result($includeAdmin = false)
    {
        return [
            'valid' => false,
            'includeAdmin' => is_bool($includeAdmin) ? $includeAdmin : false,
            'packages' => [],
            'assets' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_asset_injection_error')) {
    function red_addon_asset_injection_error(array &$result, $code)
    {
        $code = is_string($code)
            && preg_match('/\A[a-z][a-z0-9_]{0,79}\z/', $code) === 1
                ? $code
                : 'injection_invalid';
        if (!in_array($code, $result['errors'], true)) {
            $result['errors'][] = $code;
        }
    }
}

if (!function_exists('red_addon_asset_injection_surfaces')) {
    function red_addon_asset_injection_surfaces($includeAdmin)
    {
        return $includeAdmin === true
            ? ['public', 'admin']
            : ['public'];
    }
}

if (!function_exists('red_addon_asset_injection_asset_compare')) {
    function red_addon_asset_injection_asset_compare(
        array $left,
        array $right
    ): int {
        $locationOrder = ['head' => '0', 'body-end' => '1'];
        $surfaceOrder = ['public' => '0', 'admin' => '1'];
        return strcmp(
            ($locationOrder[$left['location']] ?? '9') . "\0" .
                ($surfaceOrder[$left['surface']] ?? '9') . "\0" .
                $left['packageId'] . "\0" . $left['type'] . "\0" .
                $left['path'],
            ($locationOrder[$right['location']] ?? '9') . "\0" .
                ($surfaceOrder[$right['surface']] ?? '9') . "\0" .
                $right['packageId'] . "\0" . $right['type'] . "\0" .
                $right['path']
        );
    }
}

if (!function_exists('red_addon_asset_injection_assets')) {
    function red_addon_asset_injection_assets(array $packages)
    {
        $assets = [];
        foreach ($packages as $package) {
            if (!is_array($package)
                || !is_string($package['packageId'] ?? null)
                || !is_array($package['plans'] ?? null)
            ) {
                return null;
            }
            foreach ($package['plans'] as $surface => $plan) {
                if (!in_array($surface, ['public', 'admin'], true)
                    || !red_addon_asset_plan_is_valid($plan)
                ) {
                    return null;
                }
                foreach ($plan['assets'] as $asset) {
                    $assets[] = [
                        'packageId' => $package['packageId'],
                        'surface' => $surface,
                        'path' => $asset['path'],
                        'type' => $asset['type'],
                        'location' => $asset['location'],
                        'sha256' => $asset['sha256'],
                        'url' => $asset['url'],
                    ];
                }
            }
        }
        usort($assets, 'red_addon_asset_injection_asset_compare');

        $unique = [];
        $seenUrls = [];
        foreach ($assets as $asset) {
            if (isset($seenUrls[$asset['url']])) {
                continue;
            }
            $seenUrls[$asset['url']] = true;
            $unique[] = $asset;
        }
        return $unique;
    }
}

if (!function_exists('red_addon_asset_injection_fingerprint')) {
    function red_addon_asset_injection_fingerprint(
        $includeAdmin,
        array $packages,
        array $assets
    ) {
        $encoded = json_encode(
            [
                'includeAdmin' => $includeAdmin,
                'packages' => $packages,
                'assets' => $assets,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_asset_injection_complete')) {
    function red_addon_asset_injection_complete(array $result)
    {
        $assets = red_addon_asset_injection_assets($result['packages']);
        if (!is_array($assets)) {
            red_addon_asset_injection_error($result, 'asset_plan_invalid');
            return $result;
        }
        $fingerprint = red_addon_asset_injection_fingerprint(
            $result['includeAdmin'],
            $result['packages'],
            $assets
        );
        if (!red_addon_valid_sha256($fingerprint)) {
            red_addon_asset_injection_error($result, 'plan_unavailable');
            return $result;
        }
        $result['assets'] = $assets;
        $result['planSha256'] = $fingerprint;
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_asset_injection_plan')) {
    function red_addon_asset_injection_plan(
        $connection,
        $projectRoot,
        $includeAdmin
    ) {
        $result = red_addon_asset_injection_result($includeAdmin);
        if (!is_bool($includeAdmin)) {
            red_addon_asset_injection_error($result, 'admin_context_invalid');
            return $result;
        }

        try {
            $projectRoot = red_addon_project_root($projectRoot);
            $addonRoot = red_addon_root($projectRoot);
            if (!red_addon_registry_storage_available($connection)) {
                if (!file_exists($addonRoot)) {
                    return red_addon_asset_injection_complete($result);
                }
                red_addon_asset_injection_error($result, 'registry_unavailable');
                return $result;
            }

            $catalog = red_addon_discover(
                $projectRoot,
                [
                    'cmsVersion' => '5.1.0',
                    'phpVersion' => PHP_VERSION,
                ]
            );
            if (empty($catalog['valid'])
                || !is_array($catalog['packages'] ?? null)
            ) {
                red_addon_asset_injection_error($result, 'catalog_invalid');
                return $result;
            }

            $registry = red_addon_registry_catalog_report($connection, $catalog);
            if (empty($registry['valid'])
                || !is_array($registry['packages'] ?? null)
            ) {
                red_addon_asset_injection_error($result, 'registry_invalid');
                return $result;
            }

            $surfaces = red_addon_asset_injection_surfaces($includeAdmin);
            $packageIds = array_keys($catalog['packages']);
            sort($packageIds, SORT_STRING);
            foreach ($packageIds as $packageId) {
                $package = $catalog['packages'][$packageId] ?? null;
                $packageReport = $registry['packages'][$packageId] ?? null;
                if (!is_array($package)
                    || !is_array($packageReport)
                    || ($packageReport['status'] ?? '') !== 'enabled_current'
                ) {
                    continue;
                }
                if (empty($packageReport['loadable'])
                    || !empty($packageReport['errors'])
                    || !is_array($package['manifest'] ?? null)
                ) {
                    red_addon_asset_injection_error($result, 'registry_invalid');
                    return $result;
                }

                $validatedPlans = [];
                foreach (['public', 'admin'] as $surface) {
                    $plan = red_addon_asset_plan($package['manifest'], $surface);
                    if (!red_addon_asset_plan_is_valid($plan)) {
                        red_addon_asset_injection_error(
                            $result,
                            'asset_plan_invalid'
                        );
                        return $result;
                    }
                    $validatedPlans[$surface] = $plan;
                }
                $plans = [];
                foreach ($surfaces as $surface) {
                    $plans[$surface] = $validatedPlans[$surface];
                }
                $result['packages'][] = [
                    'packageId' => $packageId,
                    'plans' => $plans,
                ];
            }
            return red_addon_asset_injection_complete($result);
        } catch (Throwable $throwable) {
            red_addon_asset_injection_error($result, 'plan_unavailable');
            return $result;
        }
    }
}

if (!function_exists('red_addon_asset_injection_plan_is_valid')) {
    function red_addon_asset_injection_plan_is_valid($plan)
    {
        if (!is_array($plan)
            || array_keys($plan) !== [
                'valid', 'includeAdmin', 'packages', 'assets', 'planSha256',
                'errors',
            ]
            || ($plan['valid'] ?? null) !== true
            || !is_bool($plan['includeAdmin'] ?? null)
            || !is_array($plan['packages'] ?? null)
            || !array_is_list($plan['packages'])
            || !is_array($plan['assets'] ?? null)
            || !array_is_list($plan['assets'])
            || !is_string($plan['planSha256'] ?? null)
            || !red_addon_valid_sha256($plan['planSha256'])
            || ($plan['errors'] ?? null) !== []
        ) {
            return false;
        }

        $surfaces = red_addon_asset_injection_surfaces(
            $plan['includeAdmin']
        );
        $previousPackageId = '';
        foreach ($plan['packages'] as $package) {
            if (!is_array($package)
                || array_keys($package) !== ['packageId', 'plans']
                || !red_addon_valid_package_id($package['packageId'] ?? null)
                || !is_array($package['plans'] ?? null)
                || array_keys($package['plans']) !== $surfaces
                || ($previousPackageId !== ''
                    && strcmp($previousPackageId, $package['packageId']) >= 0)
            ) {
                return false;
            }
            foreach ($surfaces as $surface) {
                $surfacePlan = $package['plans'][$surface] ?? null;
                if (!red_addon_asset_plan_is_valid($surfacePlan)
                    || $surfacePlan['packageId'] !== $package['packageId']
                    || $surfacePlan['surface'] !== $surface
                ) {
                    return false;
                }
            }
            $previousPackageId = $package['packageId'];
        }

        $assets = red_addon_asset_injection_assets($plan['packages']);
        if (!is_array($assets) || $assets !== $plan['assets']) {
            return false;
        }
        foreach ($assets as $asset) {
            if (!is_array($asset)
                || array_keys($asset) !== [
                    'packageId', 'surface', 'path', 'type', 'location',
                    'sha256', 'url',
                ]
                || !red_addon_valid_package_id($asset['packageId'] ?? null)
                || !in_array($asset['surface'] ?? null, $surfaces, true)
                || !is_string($asset['path'] ?? null)
                || !is_string($asset['type'] ?? null)
                || !is_string($asset['location'] ?? null)
                || !is_string($asset['sha256'] ?? null)
                || !is_string($asset['url'] ?? null)
                || $asset['type'] !== red_addon_asset_type($asset['path'])
                || (($asset['type'] === 'style'
                        && $asset['location'] !== 'head')
                    || ($asset['type'] === 'script'
                        && $asset['location'] !== 'body-end'))
                || !red_addon_valid_sha256($asset['sha256'])
                || !hash_equals(
                    (string) red_addon_asset_url(
                        $asset['packageId'],
                        $asset['path'],
                        $asset['sha256']
                    ),
                    $asset['url']
                )
            ) {
                return false;
            }
        }

        return hash_equals(
            red_addon_asset_injection_fingerprint(
                $plan['includeAdmin'],
                $plan['packages'],
                $assets
            ),
            $plan['planSha256']
        );
    }
}

if (!function_exists('red_addon_asset_injection_plan_html')) {
    function red_addon_asset_injection_plan_html($plan, $location)
    {
        if (!red_addon_asset_injection_plan_is_valid($plan)
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

if (!function_exists('red_addon_asset_injection_insert_document')) {
    function red_addon_asset_injection_insert_document(
        $document,
        $plan,
        $location
    ) {
        if (!is_string($document)
            || !in_array($location, ['head', 'body-end'], true)
        ) {
            return is_string($document) ? $document : '';
        }
        $html = red_addon_asset_injection_plan_html($plan, $location);
        if ($html === '') {
            return $document;
        }
        $pattern = $location === 'head'
            ? '#</head\s*>#iD'
            : '#</body\s*>#iD';
        $matches = [];
        if (preg_match_all($pattern, $document, $matches) !== 1) {
            return $document;
        }
        if ($location === 'head'
            && preg_match_all('#<head(?:\s[^>]*)?>#iD', $document, $matches) !== 1
        ) {
            return $document;
        }
        $injected = preg_replace_callback(
            $pattern,
            static function (array $match) use ($html) {
                return $html . $match[0];
            },
            $document,
            1
        );
        return is_string($injected) ? $injected : $document;
    }
}

?>
