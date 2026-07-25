<?php
/**
 * Server-side authorization helpers for RED-CMS administrator actions.
 */

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('red_admin_authorization_scalar')) {
    function red_admin_authorization_scalar($value)
    {
        return is_array($value) ? '' : trim((string) $value);
    }
}

if (!function_exists('red_admin_authorization_denied')) {
    function red_admin_authorization_denied()
    {
        $adminRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
        $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'CLI';
        $endpoint = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : 'unknown';
        error_log('RED-CMS authorization denied for admin ' . $adminRecordId . ' on ' . $method . ' ' . $endpoint);
        http_response_code(403);
        echo 'no';
        exit;
    }
}

if (!function_exists('red_admin_component_registry_row')) {
    function red_admin_component_registry_row($connection, $uniqueName, $subtype = '')
    {
        $uniqueName = red_admin_authorization_scalar($uniqueName);
        $subtype = red_admin_authorization_scalar($subtype);
        if ($uniqueName === '') {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RecordID, UniqueName, Layout, CompGroup FROM RED_Components WHERE UniqueName=? ORDER BY RecordID ASC'
            );
            if (!$stmt) {
                return null;
            }

            mysqli_stmt_bind_param($stmt, 's', $uniqueName);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            $result = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);

            foreach ($rows as $row) {
                if ($subtype !== '' && strcasecmp((string) ($row['Layout'] ?? ''), $subtype) === 0) {
                    return $row;
                }
            }

            if (count($rows) === 1 && (string) ($rows[0]['CompGroup'] ?? '') !== 'Y') {
                return $rows[0];
            }

            return null;
        } catch (mysqli_sql_exception $e) {
            error_log('RED component authorization registry lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_component_selection_allowed')) {
    function red_admin_component_selection_allowed($connection, $uniqueName, $subtype = '')
    {
        $row = red_admin_component_registry_row($connection, $uniqueName, $subtype);
        return $row && red_admin_has_component_access((int) ($row['RecordID'] ?? 0));
    }
}

if (!function_exists('red_admin_require_component_selection')) {
    function red_admin_require_component_selection($connection, $uniqueName, $subtype = '')
    {
        if (!red_admin_component_selection_allowed($connection, $uniqueName, $subtype)) {
            red_admin_authorization_denied();
        }
    }
}

if (!function_exists('red_admin_article_authorization_row')) {
    function red_admin_article_authorization_row($connection, $articleRecordId)
    {
        $articleRecordId = (int) $articleRecordId;
        if ($articleRecordId <= 0) {
            return null;
        }

        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID, Component FROM RED_Articles WHERE RecordID=? LIMIT 1');
            if (!$stmt) {
                return null;
            }
            mysqli_stmt_bind_param($stmt, 'i', $articleRecordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }
            $result = mysqli_stmt_get_result($stmt);
            $article = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            if (!$article) {
                return null;
            }

            $component = red_admin_authorization_scalar($article['Component'] ?? '');
            $subtype = '';
            $componentRecordId = 0;
            $componentTables = [
                'Form' => ['table' => 'RED_C_Form', 'column' => 'FormType'],
                'Gallery' => ['table' => 'RED_C_Gallery', 'column' => 'GalleryType'],
            ];

            if (isset($componentTables[$component])) {
                $config = $componentTables[$component];
                $stmt = mysqli_prepare(
                    $connection,
                    'SELECT RecordID, `' . $config['column'] . '` AS ComponentSubtype FROM `' . $config['table'] . '` WHERE RefID=? LIMIT 1'
                );
                if (!$stmt) {
                    return null;
                }
                $refId = (string) $articleRecordId;
                mysqli_stmt_bind_param($stmt, 's', $refId);
                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    return null;
                }
                $result = mysqli_stmt_get_result($stmt);
                $componentRow = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);
                if (!$componentRow) {
                    return null;
                }
                $subtype = red_admin_authorization_scalar($componentRow['ComponentSubtype'] ?? '');
                $componentRecordId = (int) ($componentRow['RecordID'] ?? 0);
            }

            $registry = red_admin_component_registry_row($connection, $component, $subtype);
            if (!$registry) {
                return null;
            }

            return [
                'article_record_id' => $articleRecordId,
                'component' => $component,
                'subtype' => $subtype,
                'component_record_id' => (int) ($registry['RecordID'] ?? 0),
                'content_record_id' => $componentRecordId,
                'comp_group' => (string) ($registry['CompGroup'] ?? ''),
            ];
        } catch (mysqli_sql_exception $e) {
            error_log('RED article authorization lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_article_access_allowed')) {
    function red_admin_article_access_allowed($connection, $articleRecordId)
    {
        $row = red_admin_article_authorization_row($connection, $articleRecordId);
        return $row && red_admin_has_component_access((int) ($row['component_record_id'] ?? 0));
    }
}

if (!function_exists('red_admin_require_article_access')) {
    function red_admin_require_article_access($connection, $articleRecordId)
    {
        if (!red_admin_article_access_allowed($connection, $articleRecordId)) {
            red_admin_authorization_denied();
        }
    }
}

if (!function_exists('red_admin_authorization_record_ids')) {
    function red_admin_authorization_record_ids($values)
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $ids = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                foreach (red_admin_authorization_record_ids($value) as $nestedId) {
                    $ids[$nestedId] = $nestedId;
                }
                continue;
            }

            $id = (int) $value;
            if ($id <= 0) {
                return [];
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }
}

if (!function_exists('red_admin_require_article_ids_access')) {
    function red_admin_require_article_ids_access($connection, $values)
    {
        $ids = red_admin_authorization_record_ids($values);
        if (empty($ids)) {
            red_admin_authorization_denied();
        }

        foreach ($ids as $id) {
            red_admin_require_article_access($connection, $id);
        }

        return $ids;
    }
}

if (!function_exists('red_admin_filter_authorized_articles')) {
    function red_admin_filter_authorized_articles($connection, array $rows, $recordIdField = 'RecordID')
    {
        $authorized = [];
        foreach ($rows as $row) {
            $recordId = isset($row[$recordIdField]) ? (int) $row[$recordIdField] : 0;
            if ($recordId > 0 && red_admin_article_access_allowed($connection, $recordId)) {
                $authorized[] = $row;
            }
        }
        return $authorized;
    }
}

?>
