<?php
/**
 * Core-managed raster logo resolution shared by admin and standard themes.
 */

if (!function_exists('red_site_logo_supported_extensions')) {
    function red_site_logo_supported_extensions()
    {
        return ['png', 'jpg', 'jpeg'];
    }
}

if (!function_exists('red_site_logo_fact')) {
    function red_site_logo_fact($projectRoot, $configuredFilename)
    {
        $filename = trim((string) $configuredFilename);
        $fact = [
            'configured' => $filename !== '',
            'valid' => false,
            'active' => false,
            'reason' => $filename === '' ? 'not-configured' : 'invalid',
            'filename' => $filename,
            'url' => '',
            'mime' => '',
            'extension' => '',
            'width' => 0,
            'height' => 0,
            'bytes' => 0,
            'sha256' => '',
        ];

        if ($filename === '') {
            return $fact;
        }

        if (
            preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,199}\z/', $filename) !== 1
            || basename($filename) !== $filename
            || strpos($filename, "\0") !== false
        ) {
            $fact['reason'] = 'unsafe-filename';
            return $fact;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $fact['extension'] = $extension;
        if (!in_array($extension, red_site_logo_supported_extensions(), true)) {
            $fact['reason'] = 'unsupported-type';
            return $fact;
        }

        $projectRoot = rtrim((string) $projectRoot, DIRECTORY_SEPARATOR);
        $managedRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'images');
        $candidate = realpath($projectRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename);
        if (
            $managedRoot === false
            || $candidate === false
            || !is_file($candidate)
            || strpos($candidate, $managedRoot . DIRECTORY_SEPARATOR) !== 0
        ) {
            $fact['reason'] = 'missing';
            return $fact;
        }

        $dimensions = @getimagesize($candidate);
        if (!is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1]) || empty($dimensions['mime'])) {
            $fact['reason'] = 'invalid-image';
            return $fact;
        }

        $expectedMime = $extension === 'png' ? 'image/png' : 'image/jpeg';
        if ((string) $dimensions['mime'] !== $expectedMime) {
            $fact['reason'] = 'mime-mismatch';
            return $fact;
        }

        $sha256 = hash_file('sha256', $candidate);
        $bytes = filesize($candidate);
        if (!is_string($sha256) || $sha256 === '' || $bytes === false) {
            $fact['reason'] = 'unreadable';
            return $fact;
        }

        $fact['valid'] = true;
        $fact['active'] = true;
        $fact['reason'] = 'custom-raster';
        $fact['url'] = '/images/' . rawurlencode($filename);
        $fact['mime'] = $expectedMime;
        $fact['width'] = (int) $dimensions[0];
        $fact['height'] = (int) $dimensions[1];
        $fact['bytes'] = (int) $bytes;
        $fact['sha256'] = $sha256;
        return $fact;
    }
}

if (!function_exists('red_site_logo_public_context')) {
    function red_site_logo_public_context($projectRoot, $configuredFilename)
    {
        $fact = red_site_logo_fact($projectRoot, $configuredFilename);
        if (empty($fact['active'])) {
            return null;
        }

        return [
            'url' => $fact['url'],
            'filename' => $fact['filename'],
            'mime' => $fact['mime'],
            'width' => $fact['width'],
            'height' => $fact['height'],
            'source' => 'advanced.Website_Logo',
        ];
    }
}
