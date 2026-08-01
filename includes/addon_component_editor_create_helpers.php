<?php
/**
 * Read-only creation preflight for add-on component editor records.
 *
 * This helper validates an inactive, hidden core parent shell and package-owned
 * values, then returns a deterministic plan for a future atomic creator. It
 * does not invoke package code, reserve an identifier, write application data,
 * expose an endpoint, activate content, or choose public placement.
 */

require_once __DIR__ . '/admin_article_helpers.php';
require_once __DIR__ . '/addon_component_editor_authorization_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';

if (!function_exists('red_addon_component_editor_create_result')) {
    function red_addon_component_editor_create_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $reason
    ) {
        return [
            'ready' => false,
            'actorRecordId' => is_int($adminRecordId) ? $adminRecordId : 0,
            'contentRecordId' => is_int($contentRecordId)
                ? $contentRecordId
                : 0,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'package' => '',
            'permission' => '',
            'parentValues' => [],
            'values' => [],
            'transactionTables' => [],
            'planHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_create_parent_values')) {
    function red_addon_component_editor_create_parent_values(
        $connection,
        $componentId,
        $contentRecordId,
        $metadata
    ) {
        if (!is_array($metadata)
            || array_keys($metadata) !== ['title', 'layout', 'language']
        ) {
            return null;
        }
        $title = $metadata['title'];
        $layout = $metadata['layout'];
        $language = $metadata['language'];
        if (!is_string($title)
            || preg_match('//u', $title) !== 1
            || $title === ''
            || strlen($title) > 200
            || preg_match('/[\x00-\x1F\x7F]/', $title) === 1
            || !is_string($layout)
            || $layout === ''
            || strlen($layout) > 64
            || preg_match('//u', $layout) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $layout) === 1
            || red_admin_area_layout_definition($connection, $layout) === null
            || !is_string($language)
            || preg_match('/\A[a-z]{2}\z/', $language) !== 1
        ) {
            return null;
        }

        $parent = red_admin_article_default_insert_data($contentRecordId);
        $parent['Title'] = $title;
        $parent['Component'] = $componentId;
        $parent['Layout'] = $layout;
        $parent['PagePosition'] = 0;
        $parent['Active'] = 'N';
        $parent['Language'] = $language;
        return $parent;
    }
}

if (!function_exists('red_addon_component_editor_creator_tables')) {
    function red_addon_component_editor_creator_tables($componentId)
    {
        $metadata = red_addon_runtime_metadata(
            'componentDataCreators',
            $componentId
        );
        $tables = is_array($metadata['tables'] ?? null)
            ? $metadata['tables']
            : [];
        if ($tables === [] || count($tables) > 8) {
            return null;
        }
        $normalized = [];
        $reserved = [
            'red_addon_installations',
            'red_addon_migrations',
            'red_addon_activity_log',
            'red_addon_component_revisions',
        ];
        foreach ($tables as $table) {
            if (!is_string($table)
                || preg_match('/\ARED_Addon_[A-Za-z0-9_]{1,54}\z/', $table)
                    !== 1
                || in_array(strtolower($table), $reserved, true)
                || isset($normalized[$table])
            ) {
                return null;
            }
            $normalized[$table] = true;
        }
        return array_keys($normalized);
    }
}

if (!function_exists('red_addon_component_editor_create_record_available')) {
    function red_addon_component_editor_create_record_available(
        $connection,
        $contentRecordId
    ) {
        $queries = [
            'SELECT RecordID FROM RED_Articles WHERE RecordID=? LIMIT 1',
            'SELECT ContentRecordID FROM RED_Content_Revisions '
                . 'WHERE ContentRecordID=? LIMIT 1',
            'SELECT ContentRecordID FROM RED_Addon_Component_Revisions '
                . 'WHERE ContentRecordID=? LIMIT 1',
            "SELECT OwnerRecordID FROM RED_Page_SEO WHERE OwnerType='article' "
                . 'AND OwnerRecordID=? LIMIT 1',
        ];
        try {
            foreach ($queries as $sql) {
                $statement = mysqli_prepare($connection, $sql);
                if (!$statement) {
                    return false;
                }
                mysqli_stmt_bind_param($statement, 'i', $contentRecordId);
                if (!mysqli_stmt_execute($statement)) {
                    mysqli_stmt_close($statement);
                    return false;
                }
                mysqli_stmt_store_result($statement);
                $found = mysqli_stmt_num_rows($statement) > 0;
                mysqli_stmt_close($statement);
                if ($found) {
                    return false;
                }
            }
        } catch (Throwable $throwable) {
            return false;
        }
        return true;
    }
}

if (!function_exists('red_addon_component_editor_create_package_enabled')) {
    function red_addon_component_editor_create_package_enabled(
        $connection,
        $packageId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID=? AND LifecycleState='enabled'"
            );
            if (!$statement) {
                return false;
            }
            $count = 0;
            mysqli_stmt_bind_param($statement, 's', $packageId);
            mysqli_stmt_bind_result($statement, $count);
            $matched = mysqli_stmt_execute($statement)
                && mysqli_stmt_fetch($statement) === true
                && $count === 1;
            mysqli_stmt_close($statement);
            return $matched;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_create_plan_hash')) {
    function red_addon_component_editor_create_plan_hash(array $plan)
    {
        $json = json_encode(
            $plan,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($json) && $json !== ''
            ? hash('sha256', $json)
            : '';
    }
}

if (!function_exists('red_addon_component_editor_create_preflight')) {
    function red_addon_component_editor_create_preflight(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $contentRecordId = filter_var(
            $contentRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = red_addon_component_editor_create_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $adminRecordId === false
                ? 'invalid_actor'
                : ($contentRecordId === false
                    ? 'invalid_content_record'
                    : 'schema_unavailable')
        );
        if ($adminRecordId === false || $contentRecordId === false) {
            return $result;
        }

        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if (!red_addon_valid_package_id($packageId)
            || !is_array(
                red_addon_component_editor_schema($manifest, $componentId)
            )
        ) {
            return $result;
        }
        $result['package'] = $packageId;

        $authorization = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'create',
            $adminRecordId
        );
        $result['permission'] = is_string(
            $authorization['permission'] ?? null
        ) ? $authorization['permission'] : '';
        if (empty($authorization['authorized'])) {
            $result['reason'] = (string) (
                $authorization['reason'] ?? 'permission_denied'
            );
            return $result;
        }

        if (!red_addon_component_editor_create_package_enabled(
            $connection,
            $packageId
        )) {
            $result['reason'] = 'package_not_enabled';
            return $result;
        }
        if (red_addon_runtime_manifest($packageId) !== $manifest) {
            $result['reason'] = 'manifest_mismatch';
            return $result;
        }
        foreach (['components', 'componentDataLoaders'] as $type) {
            $owner = red_addon_runtime_owner($type, $componentId);
            if (!is_string($owner) || !hash_equals($packageId, $owner)) {
                $result['reason'] = 'runtime_binding_unavailable';
                return $result;
            }
        }
        $creatorOwner = red_addon_runtime_owner(
            'componentDataCreators',
            $componentId
        );
        $creator = red_addon_runtime_handler(
            'componentDataCreators',
            $componentId
        );
        $tables = red_addon_component_editor_creator_tables($componentId);
        if (!is_string($creatorOwner)
            || !hash_equals($packageId, $creatorOwner)
            || !is_callable($creator)
            || !is_array($tables)
        ) {
            $result['reason'] = 'creator_unavailable';
            return $result;
        }
        $result['transactionTables'] = $tables;
        if (!red_admin_transaction_tables_supported(
            $connection,
            array_merge(
                [
                    'RED_Articles',
                    'RED_Content_Revisions',
                    'RED_Addon_Component_Revisions',
                    'RED_Page_SEO',
                ],
                $tables
            )
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        if (!red_addon_component_editor_create_record_available(
            $connection,
            $contentRecordId
        )) {
            $result['reason'] = 'record_id_unavailable';
            return $result;
        }

        $parent = red_addon_component_editor_create_parent_values(
            $connection,
            $componentId,
            $contentRecordId,
            $parentMetadata
        );
        if (!is_array($parent)) {
            $result['reason'] = 'invalid_parent_values';
            return $result;
        }
        $validated = red_addon_component_editor_validate_values(
            $manifest,
            $componentId,
            $submittedValues
        );
        if (empty($validated['valid'])
            || !is_array($validated['values'] ?? null)
        ) {
            $result['reason'] = 'invalid_values';
            return $result;
        }

        $plan = [
            'schema' => 1,
            'package' => $packageId,
            'component' => $componentId,
            'contentRecordId' => (string) $contentRecordId,
            'actorRecordId' => (string) $adminRecordId,
            'permission' => $result['permission'],
            'parentValues' => $parent,
            'values' => $validated['values'],
            'transactionTables' => $tables,
        ];
        $planHash = red_addon_component_editor_create_plan_hash($plan);
        if ($planHash === '') {
            $result['reason'] = 'plan_unavailable';
            return $result;
        }

        $result['ready'] = true;
        $result['parentValues'] = $parent;
        $result['values'] = $validated['values'];
        $result['planHash'] = $planHash;
        $result['reason'] = 'ready';
        return $result;
    }
}

?>
