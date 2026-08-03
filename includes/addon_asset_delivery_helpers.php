<?php
/**
 * Read-only immutable add-on asset-delivery preflight.
 *
 * Core resolves only exact checksum-versioned CSS/JavaScript asset URLs from
 * enabled, integrity-validated packages. It does not serve a response,
 * inject markup, execute package PHP, write the database, or change lifecycle.
 */

require_once __DIR__ . '/addon_asset_helpers.php';
require_once __DIR__ . '/addon_registry_helpers.php';

if (!function_exists('red_addon_asset_delivery_result')) {
    function red_addon_asset_delivery_result($reason = 'not_matched')
    {
        return [
            'claimed' => false,
            'resolved' => false,
            'packageId' => '',
            'surfaces' => [],
            'path' => '',
            'type' => '',
            'location' => '',
            'sha256' => '',
            'contentType' => '',
            'byteLength' => 0,
            'filePath' => '',
            'reason' => is_string($reason) ? $reason : 'not_matched',
        ];
    }
}

if (!function_exists('red_addon_asset_delivery_claimed_uri')) {
    function red_addon_asset_delivery_claimed_uri($requestUri)
    {
        return is_string($requestUri)
            && str_starts_with($requestUri, '/_red/addons/');
    }
}

if (!function_exists('red_addon_asset_delivery_request')) {
    function red_addon_asset_delivery_request($requestUri)
    {
        if (!is_string($requestUri)
            || strlen($requestUri) > 2048
            || preg_match('/[\x00-\x20\x7F]/', $requestUri) === 1
        ) {
            return null;
        }
        $pattern =
            '#\A/_red/addons/' .
            '([a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?)/' .
            '([a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?)/' .
            '(assets/(?:[A-Za-z0-9_.-]+/)*[A-Za-z0-9_.-]+\.(?:css|js))' .
            '\?v=([a-f0-9]{64})\z#D';
        if (preg_match($pattern, $requestUri, $matches) !== 1) {
            return null;
        }
        $packageId = $matches[1] . '.' . $matches[2];
        $path = $matches[3];
        $sha256 = $matches[4];
        if (!red_addon_valid_package_id($packageId)
            || red_addon_asset_type($path) === null
            || !red_addon_valid_sha256($sha256)
        ) {
            return null;
        }
        return [
            'packageId' => $packageId,
            'path' => $path,
            'sha256' => $sha256,
            'requestUri' => $requestUri,
        ];
    }
}

if (!function_exists('red_addon_asset_delivery_content_type')) {
    function red_addon_asset_delivery_content_type($type)
    {
        return $type === 'style'
            ? 'text/css; charset=UTF-8'
            : ($type === 'script' ? 'text/javascript; charset=UTF-8' : '');
    }
}

if (!function_exists('red_addon_asset_delivery_file_path')) {
    function red_addon_asset_delivery_file_path($packagePath, $assetPath)
    {
        if (!is_string($packagePath)
            || !is_string($assetPath)
            || red_addon_asset_type($assetPath) === null
        ) {
            return null;
        }
        return red_addon_safe_package_file($packagePath, $assetPath);
    }
}

if (!function_exists('red_addon_asset_delivery_preflight')) {
    function red_addon_asset_delivery_preflight(
        $connection,
        $projectRoot,
        $requestUri
    ) {
        $result = red_addon_asset_delivery_result();
        if (!red_addon_asset_delivery_claimed_uri($requestUri)) {
            return $result;
        }
        $result['claimed'] = true;
        $request = red_addon_asset_delivery_request($requestUri);
        if (!is_array($request)) {
            $result['reason'] = 'request_invalid';
            return $result;
        }
        $result['packageId'] = $request['packageId'];
        $result['path'] = $request['path'];
        $result['sha256'] = $request['sha256'];

        $package = red_addon_validate_manifest(
            $request['packageId'],
            $projectRoot,
            [
                'cmsVersion' => '5.1.0',
                'phpVersion' => PHP_VERSION,
            ]
        );
        if (empty($package['valid'])
            || !is_array($package['manifest'] ?? null)
            || !is_string($package['path'] ?? null)
        ) {
            $result['reason'] = 'package_invalid';
            return $result;
        }
        $registry = red_addon_registry_package_report($connection, $package);
        if (($registry['status'] ?? '') === 'storage_unavailable') {
            $result['reason'] = 'registry_unavailable';
            return $result;
        }
        if (($registry['status'] ?? '') !== 'enabled_current'
            || empty($registry['loadable'])
            || !empty($registry['errors'])
        ) {
            $result['reason'] = 'package_not_enabled';
            return $result;
        }

        $matches = [];
        $pathDeclared = false;
        foreach (['public', 'admin'] as $surface) {
            $plan = red_addon_asset_plan($package['manifest'], $surface);
            if (!red_addon_asset_plan_is_valid($plan)) {
                $result['reason'] = 'asset_plan_invalid';
                return $result;
            }
            foreach ($plan['assets'] as $asset) {
                if ($asset['path'] !== $request['path']) {
                    continue;
                }
                $pathDeclared = true;
                if (!hash_equals($asset['sha256'], $request['sha256'])) {
                    continue;
                }
                if (!hash_equals($asset['url'], $request['requestUri'])) {
                    $result['reason'] = 'request_noncanonical';
                    return $result;
                }
                $matches[$surface] = $asset;
            }
        }
        if ($matches === []) {
            $result['reason'] = $pathDeclared
                ? 'asset_version_mismatch'
                : 'asset_not_declared';
            return $result;
        }

        $asset = reset($matches);
        if (!is_array($asset)) {
            $result['reason'] = 'asset_not_declared';
            return $result;
        }
        $filePath = red_addon_asset_delivery_file_path(
            $package['path'],
            $asset['path']
        );
        if (!is_string($filePath)) {
            $result['reason'] = 'asset_file_unavailable';
            return $result;
        }
        $byteLength = filesize($filePath);
        if (!is_int($byteLength) || $byteLength < 0) {
            $result['reason'] = 'asset_file_unavailable';
            return $result;
        }
        $actualSha256 = hash_file('sha256', $filePath);
        if (!is_string($actualSha256)
            || !red_addon_valid_sha256($actualSha256)
            || !hash_equals($asset['sha256'], $actualSha256)
        ) {
            $result['reason'] = 'asset_checksum_mismatch';
            return $result;
        }
        $contentType = red_addon_asset_delivery_content_type($asset['type']);
        if ($contentType === '') {
            $result['reason'] = 'asset_type_invalid';
            return $result;
        }

        $result['resolved'] = true;
        $result['surfaces'] = array_keys($matches);
        $result['type'] = $asset['type'];
        $result['location'] = $asset['location'];
        $result['contentType'] = $contentType;
        $result['byteLength'] = $byteLength;
        $result['filePath'] = $filePath;
        $result['reason'] = 'resolved';
        return $result;
    }
}

?>
