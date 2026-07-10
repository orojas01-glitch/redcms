<?php
/**
 * Helpers for admin RED_Advanced write endpoints.
 */

require_once __DIR__ . '/admin_area_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';

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

if (!function_exists('red_admin_advanced_update_content')) {
    function red_admin_advanced_update_content($connection, $recordId, $content)
    {
        $recordId = (int) $recordId;
        if ($recordId <= 0 || $content === null) {
            return false;
        }

        try {
            $stmt = mysqli_prepare($connection, 'UPDATE RED_Advanced SET Content=? WHERE RecordID=?');
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 'si', $content, $recordId);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Advanced content update failed: ' . $e->getMessage());
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

?>
