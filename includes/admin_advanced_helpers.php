<?php
/**
 * Helpers for admin RED_Advanced write endpoints.
 */

require_once __DIR__ . '/admin_area_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';
require_once __DIR__ . '/theme_activation_helpers.php';

if (!function_exists('red_admin_advanced_scalar')) {
    function red_admin_advanced_scalar($value)
    {
        return is_array($value) ? '' : (string) $value;
    }
}

if (!function_exists('red_admin_advanced_language')) {
    function red_admin_advanced_language($value)
    {
        $language = strtolower(substr(red_admin_text(red_admin_advanced_scalar($value)), 0, 2));
        return preg_match('/^[a-z]{2}$/', $language) ? $language : '';
    }
}

if (!function_exists('red_admin_advanced_insert_items')) {
    function red_admin_advanced_insert_items()
    {
        return [
            'Website_Title' => '',
            'Website_Slogan' => '',
            'Website_Logo' => '',
            'Website_Footer' => '',
            'Website_Red_Sphere_Credit' => 'Y',
            'Website_Header' => null,
            'Website_CSS' => '',
        ];
    }
}

if (!function_exists('red_admin_advanced_language_exists')) {
    function red_admin_advanced_language_exists($connection, $language)
    {
        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_Advanced WHERE Language=? LIMIT 1');
            if (!$stmt) {
                return true;
            }

            mysqli_stmt_bind_param($stmt, 's', $language);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return true;
            }

            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            return $exists;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Advanced language lookup failed: ' . $e->getMessage());
            return true;
        }
    }
}

if (!function_exists('red_admin_advanced_header_template')) {
    function red_admin_advanced_header_template($connection)
    {
        try {
            $stmt = mysqli_prepare($connection, "SELECT Content FROM RED_Advanced WHERE Item='Website_Header' ORDER BY RecordID ASC LIMIT 1");
            if (!$stmt || !mysqli_stmt_execute($stmt)) {
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
                return '';
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            return $row ? (string) $row['Content'] : '';
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Advanced header template lookup failed: ' . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('red_admin_advanced_create_language')) {
    function red_admin_advanced_create_language($connection, $language)
    {
        $language = red_admin_advanced_language($language);
        if ($language === '') {
            return 'invalid';
        }

        if (red_admin_advanced_language_exists($connection, $language)) {
            return 'exists';
        }

        $headerContent = red_admin_advanced_header_template($connection);
        $items = red_admin_advanced_insert_items();
        $items['Website_Header'] = $headerContent;

        $created = red_admin_write_transaction($connection, function () use ($connection, $items, $language) {
            try {
                $stmt = mysqli_prepare($connection, 'INSERT INTO RED_Advanced (Item, Content, Language) VALUES (?, ?, ?)');
                if (!$stmt) {
                    return false;
                }

                $item = '';
                $content = '';
                mysqli_stmt_bind_param($stmt, 'sss', $item, $content, $language);

                foreach ($items as $itemName => $itemContent) {
                    $item = $itemName;
                    $content = (string) $itemContent;
                    if (!mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        return false;
                    }
                }

                mysqli_stmt_close($stmt);
                return true;
            } catch (mysqli_sql_exception $e) {
                error_log('RED_Advanced language insert failed: ' . $e->getMessage());
                return false;
            }
        }, ['RED_Advanced']);

        return $created ? 'created' : 'error';
    }
}

if (!function_exists('red_admin_advanced_content_from_post')) {
    function red_admin_advanced_content_from_post($post)
    {
        if (array_key_exists('Content', $post)) {
            return red_admin_advanced_scalar($post['Content']);
        }

        if (array_key_exists('ShortLine', $post)) {
            return red_admin_advanced_scalar($post['ShortLine']);
        }

        return null;
    }
}

if (!function_exists('red_admin_advanced_row_is_mutable')) {
    function red_admin_advanced_row_is_mutable($row, $expectedItem)
    {
        if (!is_array($row) || !is_string($expectedItem) || $expectedItem === '') {
            return false;
        }

        $item = (string) ($row['Item'] ?? '');
        $language = red_admin_advanced_language($row['Language'] ?? '');
        return $item === $expectedItem
            && $language !== ''
            && array_key_exists($item, red_admin_advanced_insert_items());
    }
}

if (!function_exists('red_admin_advanced_update_content')) {
    function red_admin_advanced_update_content($connection, $recordId, $expectedItem, $content)
    {
        $recordId = (int) $recordId;
        $expectedItem = red_admin_text(red_admin_advanced_scalar($expectedItem));
        if ($recordId <= 0 || $expectedItem === '' || $content === null) {
            return false;
        }

        $row = red_admin_advanced_record($connection, $recordId);
        if (!red_admin_advanced_row_is_mutable($row, $expectedItem)) {
            return false;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                "UPDATE RED_Advanced SET Content=? WHERE RecordID=? AND Item=? AND Language<>''"
            );
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 'sis', $content, $recordId, $expectedItem);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Advanced content update failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_advanced_update_logo')) {
    function red_admin_advanced_update_logo($connection, $recordId, $storedName)
    {
        $recordId = (int) $recordId;
        $storedName = red_admin_advanced_scalar($storedName);
        if ($recordId <= 0 || $storedName === '') {
            return false;
        }

        $row = red_admin_advanced_record($connection, $recordId);
        if (!red_admin_advanced_row_is_mutable($row, 'Website_Logo')) {
            return false;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                "UPDATE RED_Advanced SET Content=? " .
                    "WHERE RecordID=? AND Item='Website_Logo' AND Language<>''"
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'si', $storedName, $recordId);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $exception) {
            error_log('RED_Advanced logo update failed: ' . $exception->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_advanced_css_path')) {
    function red_admin_advanced_css_path($fileName)
    {
        $fileName = red_admin_text(red_admin_advanced_scalar($fileName));
        if (
            $fileName === ''
            || strpos($fileName, '/') !== false
            || strpos($fileName, '\\') !== false
            || strpos($fileName, "\0") !== false
            || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'css'
        ) {
            return '';
        }

        $cssDirectory = realpath($_SERVER['DOCUMENT_ROOT'] . '/css');
        if (!$cssDirectory || !is_dir($cssDirectory)) {
            return '';
        }

        $path = realpath($cssDirectory . '/' . $fileName);
        if (!$path || !is_file($path)) {
            return '';
        }

        $insideCssDirectory = $path === $cssDirectory || strpos($path, $cssDirectory . DIRECTORY_SEPARATOR) === 0;
        return $insideCssDirectory ? $path : '';
    }
}

if (!function_exists('red_admin_advanced_html')) {
    function red_admin_advanced_html($value)
    {
        return red_admin_area_html($value);
    }
}

if (!function_exists('red_admin_advanced_record')) {
    function red_admin_advanced_record($connection, $recordId)
    {
        $recordId = (int) red_admin_advanced_scalar($recordId);
        if ($recordId <= 0) {
            return null;
        }

        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID, Item, Content, Language FROM RED_Advanced WHERE RecordID=? LIMIT 1');
            if (!$stmt) {
                return null;
            }

            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            return $row ?: null;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Advanced record lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_advanced_list_rows')) {
    function red_admin_advanced_list_rows($connection, $language)
    {
        $language = red_admin_advanced_language($language);
        if ($language === '') {
            return [];
        }

        return red_admin_area_fetch_all(
            $connection,
            'SELECT RecordID, Item FROM RED_Advanced WHERE Language=? ORDER BY RecordID ASC',
            's',
            [$language],
            'RED_Advanced admin list lookup failed'
        );
    }
}

if (!function_exists('red_admin_advanced_css_files')) {
    function red_admin_advanced_css_files()
    {
        $cssDirectory = realpath($_SERVER['DOCUMENT_ROOT'] . '/css');
        if (!$cssDirectory || !is_dir($cssDirectory)) {
            return [];
        }

        $files = [];
        foreach (scandir($cssDirectory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (red_admin_advanced_css_path($entry) !== '') {
                $files[] = $entry;
            }
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }
}

if (!function_exists('red_admin_advanced_css_target_from_validation')) {
    /**
     * Resolve the one local stylesheet exposed by Advanced > Website CSS.
     *
     * The caller supplies a validated, production-supported theme package.
     * Standard themes use the first local top-level stylesheet so the author
     * can intentionally place the editor-facing cascade before production
     * compatibility styles. The current legacy adapter retains style.css.
     */
    function red_admin_advanced_css_target_from_validation(array $validation, $projectRoot = null)
    {
        if (empty($validation['valid']) || !is_array($validation['manifest'] ?? null)) {
            return null;
        }

        $projectRoot = red_theme_project_root($projectRoot);
        $manifest = $validation['manifest'];
        $themeId = (string) ($manifest['id'] ?? '');
        $themeType = (string) ($manifest['type'] ?? '');
        if (!red_theme_valid_id($themeId) || !in_array($themeType, ['standard', 'legacy-adapter'], true)) {
            return null;
        }

        $themeDirectory = realpath((string) ($validation['path'] ?? ''));
        if ($themeDirectory === false || !is_dir($themeDirectory)) {
            return null;
        }

        $styleGroups = [];
        if (is_array($manifest['assets']['styles'] ?? null)) {
            $styleGroups[] = $manifest['assets']['styles'];
        }
        if ($themeType === 'standard' && is_array($manifest['production']['assets']['styles'] ?? null)) {
            $styleGroups[] = $manifest['production']['assets']['styles'];
        }

        $fileField = $themeType === 'legacy-adapter' ? 'legacySource' : 'path';
        $baseDirectory = $themeType === 'legacy-adapter' ? $projectRoot : $themeDirectory;
        $candidates = [];
        foreach ($styleGroups as $groupIndex => $styles) {
            foreach ($styles as $styleIndex => $style) {
                if (!is_array($style)
                    || !isset($style[$fileField])
                    || !is_string($style[$fileField])
                    || strtolower(pathinfo($style[$fileField], PATHINFO_EXTENSION)) !== 'css'
                ) {
                    continue;
                }

                $path = red_theme_existing_path($baseDirectory, $style[$fileField]);
                if ($path === null
                    || !is_file($path)
                    || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'css'
                ) {
                    continue;
                }

                $candidates[] = [
                    'assetId' => (string) ($style['id'] ?? ''),
                    'relativePath' => str_replace('\\', '/', $style[$fileField]),
                    'absolutePath' => $path,
                    'groupIndex' => $groupIndex,
                    'styleIndex' => $styleIndex,
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        $candidate = $candidates[0];
        if ($themeType === 'legacy-adapter') {
            foreach ($candidates as $legacyCandidate) {
                if ($legacyCandidate['assetId'] === 'theme-style'
                    || $legacyCandidate['relativePath'] === 'css/style.css'
                ) {
                    $candidate = $legacyCandidate;
                    break;
                }
            }
        }

        $displayPath = $themeType === 'standard'
            ? 'themes/' . $themeId . '/' . $candidate['relativePath']
            : $candidate['relativePath'];

        return [
            'themeId' => $themeId,
            'themeName' => (string) ($manifest['name'] ?? $themeId),
            'themeType' => $themeType,
            'assetId' => $candidate['assetId'],
            'relativePath' => $candidate['relativePath'],
            'displayPath' => $displayPath,
            'absolutePath' => $candidate['absolutePath'],
        ];
    }
}

if (!function_exists('red_admin_advanced_active_css_target')) {
    /**
     * Resolve the effective public theme before exposing an editable file.
     * Invalid or unavailable activation state follows the public runtime's
     * hard legacy fallback instead of accepting a path from the browser.
     */
    function red_admin_advanced_active_css_target($connection, $projectRoot = null)
    {
        $projectRoot = red_theme_project_root($projectRoot);
        $requestedThemeId = 'legacy-bootstrap';
        $usedFallback = false;

        try {
            $state = red_theme_activation_read_state($connection, false, true);
            if (!empty($state['persisted'])) {
                $requestedThemeId = (string) $state['activeThemeId'];
            }
            $validation = red_theme_activation_validate_candidate($requestedThemeId, $projectRoot);
        } catch (Throwable $exception) {
            $usedFallback = true;
            error_log(
                'RED-CMS active Website CSS target fell back to legacy-bootstrap: ' .
                $exception->getMessage()
            );
            try {
                $validation = red_theme_activation_validate_candidate('legacy-bootstrap', $projectRoot);
            } catch (Throwable $fallbackException) {
                error_log(
                    'RED-CMS legacy Website CSS target is unavailable: ' .
                    $fallbackException->getMessage()
                );
                return null;
            }
        }

        $target = red_admin_advanced_css_target_from_validation($validation, $projectRoot);
        if ($target === null) {
            return null;
        }

        $target['requestedThemeId'] = $requestedThemeId;
        $target['usedFallback'] = $usedFallback;
        return $target;
    }
}

if (!function_exists('red_admin_advanced_css_target_token')) {
    /**
     * Bind a form to both the active target and the exact bytes it displayed.
     * This prevents an old editor tab from writing after activation or another
     * CSS edit has changed the server-side target.
     */
    function red_admin_advanced_css_target_token(array $target)
    {
        $path = (string) ($target['absolutePath'] ?? '');
        $themeId = (string) ($target['themeId'] ?? '');
        $relativePath = (string) ($target['relativePath'] ?? '');
        if ($path === '' || $themeId === '' || $relativePath === '' || !is_file($path)) {
            return '';
        }

        $contentHash = hash_file('sha256', $path);
        if (!is_string($contentHash) || $contentHash === '') {
            return '';
        }

        return hash('sha256', $themeId . "\n" . $relativePath . "\n" . $contentHash);
    }
}

if (!function_exists('red_admin_advanced_css_read')) {
    function red_admin_advanced_css_read(array $target)
    {
        $path = (string) ($target['absolutePath'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);
        return $content === false ? null : $content;
    }
}

if (!function_exists('red_admin_advanced_css_write')) {
    /**
     * Return yes, stale, or no so the admin UI can distinguish a safe retry.
     */
    function red_admin_advanced_css_write(array $target, $expectedToken, $css)
    {
        if (is_array($css)) {
            return 'no';
        }

        $expectedToken = red_admin_advanced_scalar($expectedToken);
        $currentToken = red_admin_advanced_css_target_token($target);
        if ($expectedToken === '' || $currentToken === '' || !hash_equals($currentToken, $expectedToken)) {
            return 'stale';
        }

        $path = (string) ($target['absolutePath'] ?? '');
        if ($path === '' || !is_file($path) || !is_writable($path)) {
            return 'no';
        }

        $css = red_admin_advanced_scalar($css);
        $written = file_put_contents($path, $css, LOCK_EX);
        return is_int($written) && $written === strlen($css) ? 'yes' : 'no';
    }
}

?>
