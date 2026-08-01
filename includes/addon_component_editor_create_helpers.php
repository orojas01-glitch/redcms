<?php
/**
 * Read-only creation preflight for add-on component editor records.
 *
 * The preflight validates an inactive, hidden core parent shell and
 * package-owned values, then returns a deterministic plan. The separate
 * activation-blocked runner revalidates that exact plan under the lifecycle
 * and theme locks and creates the parent, package row, and initial revision
 * evidence atomically. Neither helper exposes an endpoint, activates content,
 * or chooses public placement.
 */

require_once __DIR__ . '/admin_article_helpers.php';
require_once __DIR__ . '/addon_component_editor_authorization_helpers.php';
require_once __DIR__ . '/addon_component_editor_revision_helpers.php';
require_once __DIR__ . '/admin_content_revision_helpers.php';
require_once __DIR__ . '/addon_install_helpers.php';
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

if (!function_exists('red_addon_component_editor_creation_result')) {
    function red_addon_component_editor_creation_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $reason
    ) {
        return [
            'created' => false,
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
            'stateHash' => '',
            'parentRevisionId' => 0,
            'packageRevisionId' => 0,
            'planHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_create_hash_valid')) {
    function red_addon_component_editor_create_hash_valid($hash)
    {
        return is_string($hash)
            && preg_match('/\A[a-f0-9]{64}\z/', $hash) === 1;
    }
}

if (!function_exists('red_addon_component_editor_create_transaction_active')) {
    function red_addon_component_editor_create_transaction_active($connection)
    {
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_component_creator_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_component_creator_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_create_lock_installation')) {
    function red_addon_component_editor_create_lock_installation(
        $connection,
        $packageId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT PackageID FROM RED_Addon_Installations
                 WHERE PackageID=? AND LifecycleState='enabled'
                 LIMIT 1 FOR UPDATE"
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return false;
            }
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
            return is_array($row)
                && is_string($row['PackageID'] ?? null)
                && hash_equals($packageId, $row['PackageID']);
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_create_insert_parent')) {
    function red_addon_component_editor_create_insert_parent(
        $connection,
        array $parent
    ) {
        if (array_keys($parent) !== array_keys(
            red_admin_article_default_insert_data(
                (int) ($parent['RecordID'] ?? 0)
            )
        )) {
            return false;
        }
        $columns = [];
        $placeholders = [];
        $types = '';
        $values = [];
        foreach ($parent as $fieldName => $value) {
            if (!isset(red_admin_article_columns()[$fieldName])) {
                return false;
            }
            $columns[] = "`$fieldName`";
            $placeholders[] = '?';
            $types .= red_admin_article_param_type($fieldName);
            $values[] = $value;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Articles (' . implode(', ', $columns)
                    . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            if (!$statement
                || !red_admin_article_bind_values($statement, $types, $values)
            ) {
                if ($statement) {
                    mysqli_stmt_close($statement);
                }
                return false;
            }
            $inserted = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            return $inserted;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_create_parent_matches')) {
    function red_addon_component_editor_create_parent_matches(
        $connection,
        array $expected
    ) {
        $row = red_admin_article_full_record(
            $connection,
            (int) ($expected['RecordID'] ?? 0)
        );
        if (!is_array($row)) {
            return false;
        }
        foreach ($expected as $fieldName => $value) {
            if (!array_key_exists($fieldName, $row)
                || (string) $row[$fieldName] !== (string) $value
            ) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('red_addon_component_editor_create_invoke_creator')) {
    function red_addon_component_editor_create_invoke_creator(
        $creator,
        $connection,
        array $context,
        array $values
    ) {
        $bufferLevel = ob_get_level();
        $emitted = '';
        try {
            ob_start();
            $created = $creator($connection, $context, $values);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on component creator altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log(
                'RED-CMS add-on component creation failed: '
                    . (string) ($context['component'] ?? '')
            );
            return false;
        }
        return $emitted === '' && $created === true;
    }
}

if (!function_exists('red_addon_component_editor_create_load_values')) {
    function red_addon_component_editor_create_load_values(
        $loader,
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId
    ) {
        $bufferLevel = ob_get_level();
        $emitted = '';
        $values = null;
        try {
            ob_start();
            $values = $loader(
                $connection,
                [
                    'component' => $componentId,
                    'contentRecordId' => $contentRecordId,
                ]
            );
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on component creation loader altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log(
                'RED-CMS add-on component creation reload failed: '
                    . (string) $componentId
            );
            return null;
        }
        if ($emitted !== '' || !is_array($values)) {
            return null;
        }
        $validated = red_addon_component_editor_validate_values(
            $manifest,
            $componentId,
            $values
        );
        return !empty($validated['valid'])
            && is_array($validated['values'] ?? null)
                ? $validated['values']
                : null;
    }
}

if (!function_exists('red_addon_component_editor_create_parent_revision')) {
    function red_addon_component_editor_create_parent_revision(
        $connection,
        $contentRecordId,
        $adminRecordId
    ) {
        if (red_admin_content_revision_latest(
            $connection,
            $contentRecordId
        ) !== null) {
            return 0;
        }
        $snapshot = red_admin_content_revision_capture(
            $connection,
            $contentRecordId
        );
        $json = is_array($snapshot)
            ? red_admin_content_revision_json($snapshot)
            : '';
        $snapshotHash = is_array($snapshot)
            ? red_admin_content_revision_hash($snapshot)
            : '';
        $actorAlias = red_addon_component_revision_actor_alias(
            $connection,
            $adminRecordId
        );
        $contentType = is_array($snapshot)
            ? substr((string) ($snapshot['type'] ?? ''), 0, 50)
            : '';
        if ($json === ''
            || !red_addon_component_editor_create_hash_valid($snapshotHash)
            || !is_string($actorAlias)
            || $contentType === ''
        ) {
            return 0;
        }
        $revisionNumber = 1;
        $operation = 'create';
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Content_Revisions (
                    ContentRecordID, ContentType, RevisionNumber, Operation,
                    ActorAdminRecordID, ActorAlias, Snapshot, SnapshotHash,
                    RestoredFromRevisionID
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)'
            );
            if (!$statement) {
                return 0;
            }
            mysqli_stmt_bind_param(
                $statement,
                'isisisss',
                $contentRecordId,
                $contentType,
                $revisionNumber,
                $operation,
                $adminRecordId,
                $actorAlias,
                $json,
                $snapshotHash
            );
            $inserted = mysqli_stmt_execute($statement);
            $revisionId = $inserted
                ? (int) mysqli_insert_id($connection)
                : 0;
            mysqli_stmt_close($statement);
            return $revisionId;
        } catch (Throwable $throwable) {
            return 0;
        }
    }
}

if (!function_exists('red_addon_component_editor_create_values')) {
    function red_addon_component_editor_create_values(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues,
        $expectedPlanHash
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
        $result = red_addon_component_editor_creation_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $adminRecordId === false
                ? 'invalid_actor'
                : ($contentRecordId === false
                    ? 'invalid_content_record'
                    : 'invalid_plan_hash')
        );
        if ($adminRecordId === false
            || $contentRecordId === false
            || !red_addon_component_editor_create_hash_valid(
                $expectedPlanHash
            )
        ) {
            return $result;
        }
        $result['planHash'] = $expectedPlanHash;
        if (red_addon_component_editor_create_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }

        $preflight = red_addon_component_editor_create_preflight(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            $parentMetadata,
            $submittedValues
        );
        $result['package'] = (string) ($preflight['package'] ?? '');
        $result['permission'] = (string) ($preflight['permission'] ?? '');
        if (empty($preflight['ready'])
            || !hash_equals(
                (string) ($preflight['planHash'] ?? ''),
                $expectedPlanHash
            )
        ) {
            $result['reason'] = empty($preflight['ready'])
                ? (string) ($preflight['reason'] ?? 'preflight_failed')
                : 'stale_plan';
            return $result;
        }
        $packageId = (string) $preflight['package'];
        $creator = red_addon_runtime_handler(
            'componentDataCreators',
            $componentId
        );
        $loader = red_addon_runtime_handler(
            'componentDataLoaders',
            $componentId
        );
        if (!is_callable($creator) || !is_callable($loader)) {
            $result['reason'] = 'runtime_binding_unavailable';
            return $result;
        }
        if (!red_addon_lifecycle_lock($connection)) {
            $result['reason'] = 'lifecycle_lock_failed';
            return $result;
        }

        $transactionReason = 'transaction_failed';
        $lockedResult = null;
        try {
            $lockedResult = red_admin_with_theme_contract_lock(
                $connection,
                function () use (
                    $connection,
                    $manifest,
                    $componentId,
                    $contentRecordId,
                    $adminRecordId,
                    $parentMetadata,
                    $submittedValues,
                    $expectedPlanHash,
                    $packageId,
                    $creator,
                    $loader,
                    &$transactionReason
                ) {
                    if (!mysqli_begin_transaction($connection)) {
                        $transactionReason = 'transaction_failed';
                        return null;
                    }
                    try {
                        if (!red_addon_component_editor_create_lock_installation(
                            $connection,
                            $packageId
                        )) {
                            $transactionReason = 'package_not_enabled';
                            throw new RuntimeException($transactionReason);
                        }
                        $lockedPlan = red_addon_component_editor_create_preflight(
                            $connection,
                            $manifest,
                            $componentId,
                            $contentRecordId,
                            $adminRecordId,
                            $parentMetadata,
                            $submittedValues
                        );
                        if (empty($lockedPlan['ready'])
                            || !hash_equals(
                                (string) ($lockedPlan['planHash'] ?? ''),
                                $expectedPlanHash
                            )
                        ) {
                            $transactionReason = empty($lockedPlan['ready'])
                                ? (string) (
                                    $lockedPlan['reason'] ?? 'preflight_failed'
                                )
                                : 'stale_plan';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_insert_parent(
                            $connection,
                            $lockedPlan['parentValues']
                        )) {
                            $transactionReason = 'parent_insert_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            $transactionReason = 'transaction_lost';
                            throw new RuntimeException($transactionReason);
                        }
                        $context = [
                            'component' => $componentId,
                            'contentRecordId' => $contentRecordId,
                            'actorRecordId' => $adminRecordId,
                            'planHash' => $expectedPlanHash,
                        ];
                        if (!red_addon_component_editor_create_invoke_creator(
                            $creator,
                            $connection,
                            $context,
                            $lockedPlan['values']
                        )) {
                            $transactionReason = 'creator_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            $transactionReason = 'transaction_lost';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_parent_matches(
                            $connection,
                            $lockedPlan['parentValues']
                        )) {
                            $transactionReason = 'parent_postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $loaded = red_addon_component_editor_create_load_values(
                            $loader,
                            $connection,
                            $manifest,
                            $componentId,
                            $contentRecordId
                        );
                        if (!is_array($loaded)
                            || $loaded !== $lockedPlan['values']
                        ) {
                            $transactionReason = 'package_postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            $transactionReason = 'transaction_lost';
                            throw new RuntimeException($transactionReason);
                        }
                        $packageRevision = red_addon_component_revision_record(
                            $connection,
                            $packageId,
                            $componentId,
                            $contentRecordId,
                            $adminRecordId,
                            $loaded,
                            'baseline'
                        );
                        if (empty($packageRevision['recorded'])
                            || empty($packageRevision['inserted'])
                        ) {
                            $transactionReason = 'package_revision_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $parentRevisionId =
                            red_addon_component_editor_create_parent_revision(
                                $connection,
                                $contentRecordId,
                                $adminRecordId
                            );
                        if ($parentRevisionId < 1) {
                            $transactionReason = 'parent_revision_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            $transactionReason = 'transaction_lost';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!mysqli_commit($connection)) {
                            $transactionReason = 'transaction_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        return [
                            'parentValues' => $lockedPlan['parentValues'],
                            'values' => $loaded,
                            'stateHash' => (string) (
                                $packageRevision['stateHash'] ?? ''
                            ),
                            'parentRevisionId' => $parentRevisionId,
                            'packageRevisionId' => (int) (
                                $packageRevision['revisionId'] ?? 0
                            ),
                        ];
                    } catch (Throwable $throwable) {
                        if (red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            mysqli_rollback($connection);
                        }
                        return null;
                    }
                }
            );
        } finally {
            red_addon_lifecycle_unlock($connection);
        }

        if (!is_array($lockedResult)) {
            $result['reason'] = $lockedResult === false
                && $transactionReason === 'transaction_failed'
                ? 'theme_lock_failed'
                : $transactionReason;
            return $result;
        }
        $result['created'] = true;
        $result['parentValues'] = $lockedResult['parentValues'];
        $result['values'] = $lockedResult['values'];
        $result['stateHash'] = $lockedResult['stateHash'];
        $result['parentRevisionId'] = $lockedResult['parentRevisionId'];
        $result['packageRevisionId'] = $lockedResult['packageRevisionId'];
        $result['reason'] = 'created';
        return $result;
    }
}

?>
