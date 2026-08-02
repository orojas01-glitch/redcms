<?php
/**
 * Read-only public-placement preflight for add-on component editor records.
 *
 * This helper binds an exact inactive package-owned parent state to one
 * existing public Article route and one active-theme page position. It never
 * updates the parent, activates content, invokes a package writer, or opens an
 * endpoint. A later atomic runner must revalidate the complete plan.
 */

require_once __DIR__ . '/addon_component_editor_parent_helpers.php';

if (!function_exists('red_addon_component_editor_publish_result')) {
    function red_addon_component_editor_publish_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $targetPageRecordId,
        $reason
    ) {
        return [
            'ready' => false,
            'actorRecordId' => is_int($adminRecordId) ? $adminRecordId : 0,
            'contentRecordId' => is_int($contentRecordId)
                ? $contentRecordId
                : 0,
            'targetPageRecordId' => is_int($targetPageRecordId)
                ? $targetPageRecordId
                : 0,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'package' => '',
            'viewPermission' => '',
            'publishPermission' => '',
            'parentStateHash' => '',
            'packageStateHash' => '',
            'targetStateHash' => '',
            'placementValues' => [],
            'planHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_publish_hash')) {
    function red_addon_component_editor_publish_hash(array $values)
    {
        $json = json_encode(
            $values,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($json) ? hash('sha256', $json) : '';
    }
}

if (!function_exists('red_addon_component_editor_publish_target')) {
    function red_addon_component_editor_publish_target(
        $connection,
        $targetPageRecordId
    ) {
        $row = red_admin_article_full_record($connection, $targetPageRecordId);
        if (!is_array($row)
            || (int) ($row['RecordID'] ?? 0) !== $targetPageRecordId
            || (string) ($row['Component'] ?? '') !== 'Article'
            || (string) ($row['Active'] ?? '') !== 'Y'
            || (int) ($row['PagePosition'] ?? 0) < 1
            || (int) ($row['PagePosition'] ?? 0) > 99
        ) {
            return null;
        }
        $alias = trim((string) ($row['Alias'] ?? ''));
        $language = (string) ($row['Language'] ?? '');
        if ($alias === ''
            || strlen($alias) > 200
            || preg_match('/\A[a-z]{2}\z/', $language) !== 1
            || !red_admin_article_hierarchy_valid($connection, $row)
        ) {
            return null;
        }

        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT COUNT(*) AS OwnerCount
                 FROM RED_Articles
                 WHERE Component='Article' AND Active='Y' AND PagePosition>0
                   AND BINARY Alias=BINARY ? AND BINARY Language=BINARY ?
                   AND BINARY Sections=BINARY ?
                   AND BINARY Categories=BINARY ?
                   AND BINARY SubCategories=BINARY ?"
            );
            if (!$statement) {
                return null;
            }
            $sections = (string) ($row['Sections'] ?? '');
            $categories = (string) ($row['Categories'] ?? '');
            $subcategories = (string) ($row['SubCategories'] ?? '');
            mysqli_stmt_bind_param(
                $statement,
                'sssss',
                $alias,
                $language,
                $sections,
                $categories,
                $subcategories
            );
            mysqli_stmt_execute($statement);
            $queryResult = mysqli_stmt_get_result($statement);
            $countRow = $queryResult ? mysqli_fetch_assoc($queryResult) : null;
            if ($queryResult) {
                mysqli_free_result($queryResult);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            return null;
        }
        if ((int) ($countRow['OwnerCount'] ?? 0) !== 1) {
            return null;
        }

        $target = [
            'recordId' => $targetPageRecordId,
            'component' => 'Article',
            'active' => 'Y',
            'alias' => $alias,
            'sections' => $sections,
            'categories' => $categories,
            'subCategories' => $subcategories,
            'layout' => (string) ($row['Layout'] ?? ''),
            'language' => $language,
            'pagePosition' => (int) $row['PagePosition'],
        ];
        $target['stateHash'] = red_addon_component_editor_publish_hash($target);
        return $target['stateHash'] === '' ? null : $target;
    }
}

if (!function_exists('red_addon_component_editor_publish_preflight')) {
    function red_addon_component_editor_publish_preflight(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        $pagePosition,
        $pagePositionOrder,
        $expectedParentStateHash,
        $expectedPackageStateHash
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
        $targetPageRecordId = filter_var(
            $targetPageRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $pagePosition = filter_var(
            $pagePosition,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 99]]
        );
        $pagePositionOrder = filter_var(
            $pagePositionOrder,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
        );
        $result = red_addon_component_editor_publish_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $targetPageRecordId === false ? 0 : $targetPageRecordId,
            'invalid_request'
        );
        if ($adminRecordId === false
            || $contentRecordId === false
            || $targetPageRecordId === false
            || $contentRecordId === $targetPageRecordId
            || $pagePosition === false
            || $pagePositionOrder === false
            || !red_addon_component_editor_state_hash_valid(
                $expectedParentStateHash
            )
            || !red_addon_component_editor_state_hash_valid(
                $expectedPackageStateHash
            )
        ) {
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
            $result['reason'] = 'schema_unavailable';
            return $result;
        }
        $result['package'] = $packageId;

        $publish = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'publish',
            $adminRecordId
        );
        $result['publishPermission'] = is_string(
            $publish['permission'] ?? null
        ) ? $publish['permission'] : '';
        if (empty($publish['authorized'])) {
            $result['reason'] = (string) (
                $publish['reason'] ?? 'permission_denied'
            );
            return $result;
        }

        $parent = red_addon_component_editor_parent_state(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId
        );
        $result['viewPermission'] = (string) (
            $parent['viewPermission'] ?? ''
        );
        if (empty($parent['loaded'])) {
            $result['reason'] = (string) (
                $parent['reason'] ?? 'parent_state_unavailable'
            );
            return $result;
        }
        $result['parentStateHash'] = (string) $parent['stateHash'];
        $result['packageStateHash'] = (string) $parent['packageStateHash'];
        if (!hash_equals($parent['stateHash'], $expectedParentStateHash)
            || !hash_equals(
                $parent['packageStateHash'],
                $expectedPackageStateHash
            )
        ) {
            $result['reason'] = 'stale_state';
            return $result;
        }

        $target = red_addon_component_editor_publish_target(
            $connection,
            $targetPageRecordId
        );
        if (!is_array($target)) {
            $result['reason'] = 'target_page_unavailable';
            return $result;
        }
        $result['targetStateHash'] = $target['stateHash'];
        if (!hash_equals(
            (string) ($parent['parentValues']['language'] ?? ''),
            $target['language']
        )) {
            $result['reason'] = 'language_mismatch';
            return $result;
        }

        $existing = red_admin_article_full_record(
            $connection,
            $contentRecordId
        );
        if (!is_array($existing)) {
            $result['reason'] = 'parent_state_unavailable';
            return $result;
        }
        $placement = [
            'Sections' => $target['sections'],
            'Categories' => $target['categories'],
            'SubCategories' => $target['subCategories'],
            'Article' => $target['alias'],
            'PagePosition' => $pagePosition,
            'PagePositionOrder' => $pagePositionOrder,
            'Active' => 'Y',
        ];
        $candidate = array_merge($existing, $placement);
        if (!red_admin_article_hierarchy_valid($connection, $candidate)
            || !red_admin_article_validate_position_changes(
                $connection,
                $candidate,
                $placement,
                false,
                $existing
            )
        ) {
            $result['reason'] = 'placement_unsupported';
            return $result;
        }

        $plan = [
            'actorRecordId' => $adminRecordId,
            'contentRecordId' => $contentRecordId,
            'targetPageRecordId' => $targetPageRecordId,
            'component' => $componentId,
            'package' => $packageId,
            'viewPermission' => $result['viewPermission'],
            'publishPermission' => $result['publishPermission'],
            'parentStateHash' => $result['parentStateHash'],
            'packageStateHash' => $result['packageStateHash'],
            'targetStateHash' => $result['targetStateHash'],
            'placementValues' => $placement,
        ];
        $result['placementValues'] = $placement;
        $result['planHash'] = red_addon_component_editor_publish_hash($plan);
        if ($result['planHash'] === '') {
            $result['reason'] = 'plan_unavailable';
            return $result;
        }
        $result['ready'] = true;
        $result['reason'] = 'ready';
        return $result;
    }
}

if (!function_exists('red_addon_component_editor_publication_result')) {
    function red_addon_component_editor_publication_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $targetPageRecordId,
        $reason
    ) {
        $result = red_addon_component_editor_publish_result(
            $adminRecordId,
            $contentRecordId,
            $componentId,
            $targetPageRecordId,
            $reason
        );
        $result['placed'] = false;
        $result['previousParentStateHash'] = '';
        $result['stateHash'] = '';
        $result['revisionId'] = 0;
        $result['revisionNumber'] = 0;
        return $result;
    }
}

if (!function_exists('red_addon_component_editor_publish_lock_target')) {
    function red_addon_component_editor_publish_lock_target(
        $connection,
        $targetPageRecordId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RecordID FROM RED_Articles WHERE RecordID=? FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 'i', $targetPageRecordId);
            $executed = mysqli_stmt_execute($statement);
            $queryResult = $executed
                ? mysqli_stmt_get_result($statement)
                : null;
            $row = $queryResult ? mysqli_fetch_assoc($queryResult) : null;
            if ($queryResult) {
                mysqli_free_result($queryResult);
            }
            mysqli_stmt_close($statement);
            if (!is_array($row)
                || (int) ($row['RecordID'] ?? 0) !== $targetPageRecordId
            ) {
                return false;
            }
            $target = red_addon_component_editor_publish_target(
                $connection,
                $targetPageRecordId
            );
            if (!is_array($target)) {
                return false;
            }
            $statement = mysqli_prepare(
                $connection,
                "SELECT RecordID FROM RED_Articles
                 WHERE Component='Article' AND Active='Y' AND PagePosition>0
                   AND BINARY Alias=BINARY ? AND BINARY Language=BINARY ?
                   AND BINARY Sections=BINARY ?
                   AND BINARY Categories=BINARY ?
                   AND BINARY SubCategories=BINARY ?
                 FOR UPDATE"
            );
            if (!$statement) {
                return false;
            }
            $alias = $target['alias'];
            $language = $target['language'];
            $sections = $target['sections'];
            $categories = $target['categories'];
            $subCategories = $target['subCategories'];
            mysqli_stmt_bind_param(
                $statement,
                'sssss',
                $alias,
                $language,
                $sections,
                $categories,
                $subCategories
            );
            $executed = mysqli_stmt_execute($statement);
            $queryResult = $executed
                ? mysqli_stmt_get_result($statement)
                : null;
            $owners = [];
            while ($queryResult && ($owner = mysqli_fetch_assoc($queryResult))) {
                $owners[] = (int) ($owner['RecordID'] ?? 0);
            }
            if ($queryResult) {
                mysqli_free_result($queryResult);
            }
            mysqli_stmt_close($statement);
            return $owners === [$targetPageRecordId];
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_publish_parent_row')) {
    function red_addon_component_editor_publish_parent_row(
        $connection,
        $contentRecordId
    ) {
        $row = red_admin_article_full_record($connection, $contentRecordId);
        $defaults = red_admin_article_default_insert_data($contentRecordId);
        if (!is_array($row)) {
            return null;
        }
        $filtered = [];
        foreach (array_keys($defaults) as $fieldName) {
            if (!array_key_exists($fieldName, $row)) {
                return null;
            }
            $filtered[$fieldName] = isset(
                red_admin_article_integer_columns()[$fieldName]
            ) ? (int) $row[$fieldName] : (string) $row[$fieldName];
        }
        return $filtered;
    }
}

if (!function_exists('red_addon_component_editor_publish_parent')) {
    function red_addon_component_editor_publish_parent(
        $connection,
        $componentId,
        $contentRecordId,
        array $placement
    ) {
        if (array_keys($placement) !== [
            'Sections',
            'Categories',
            'SubCategories',
            'Article',
            'PagePosition',
            'PagePositionOrder',
            'Active',
        ]) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE RED_Articles
                 SET Sections=?, Categories=?, SubCategories=?, Article=?,
                     PagePosition=?, PagePositionOrder=?, Active=?
                 WHERE RecordID=? AND Component=?
                   AND Active='N' AND PagePosition=0 AND Alias=''"
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'ssssiisis',
                $placement['Sections'],
                $placement['Categories'],
                $placement['SubCategories'],
                $placement['Article'],
                $placement['PagePosition'],
                $placement['PagePositionOrder'],
                $placement['Active'],
                $contentRecordId,
                $componentId
            );
            $executed = mysqli_stmt_execute($statement);
            $updated = $executed
                && mysqli_stmt_affected_rows($statement) === 1;
            mysqli_stmt_close($statement);
            return $updated;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_editor_publish_values')) {
    function red_addon_component_editor_publish_values(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        $pagePosition,
        $pagePositionOrder,
        $expectedParentStateHash,
        $expectedPackageStateHash,
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
        $targetPageRecordId = filter_var(
            $targetPageRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = red_addon_component_editor_publication_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $targetPageRecordId === false ? 0 : $targetPageRecordId,
            'invalid_request'
        );
        if ($adminRecordId === false
            || $contentRecordId === false
            || $targetPageRecordId === false
            || !red_addon_component_editor_state_hash_valid(
                $expectedParentStateHash
            )
            || !red_addon_component_editor_state_hash_valid(
                $expectedPackageStateHash
            )
            || !red_addon_component_editor_create_hash_valid(
                $expectedPlanHash
            )
        ) {
            return $result;
        }
        $result['previousParentStateHash'] = $expectedParentStateHash;
        $result['packageStateHash'] = $expectedPackageStateHash;
        $result['planHash'] = $expectedPlanHash;
        if (!red_admin_transaction_tables_supported(
            $connection,
            ['RED_Articles', 'RED_Content_Revisions']
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        if (red_addon_component_editor_create_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }

        $preflight = red_addon_component_editor_publish_preflight(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            $targetPageRecordId,
            $pagePosition,
            $pagePositionOrder,
            $expectedParentStateHash,
            $expectedPackageStateHash
        );
        foreach (
            [
                'package',
                'viewPermission',
                'publishPermission',
                'targetStateHash',
                'placementValues',
            ] as $fieldName
        ) {
            $result[$fieldName] = $preflight[$fieldName] ?? $result[$fieldName];
        }
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
                    $targetPageRecordId,
                    $pagePosition,
                    $pagePositionOrder,
                    $expectedParentStateHash,
                    $expectedPackageStateHash,
                    $expectedPlanHash,
                    $packageId,
                    &$transactionReason
                ) {
                    if (!mysqli_begin_transaction($connection)) {
                        return null;
                    }
                    try {
                        if (!red_addon_component_editor_create_lock_installation(
                            $connection,
                            $packageId
                        ) || !red_addon_component_editor_lock_binding(
                            $connection,
                            $packageId,
                            $componentId,
                            $contentRecordId
                        ) || !red_addon_component_editor_publish_lock_target(
                            $connection,
                            $targetPageRecordId
                        )) {
                            $transactionReason = 'binding_unavailable';
                            throw new RuntimeException($transactionReason);
                        }
                        $lockedPlan =
                            red_addon_component_editor_publish_preflight(
                                $connection,
                                $manifest,
                                $componentId,
                                $contentRecordId,
                                $adminRecordId,
                                $targetPageRecordId,
                                $pagePosition,
                                $pagePositionOrder,
                                $expectedParentStateHash,
                                $expectedPackageStateHash
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
                        $beforeShell = red_addon_component_editor_parent_shell(
                            $connection,
                            $componentId,
                            $contentRecordId
                        );
                        $targetBefore =
                            red_addon_component_editor_publish_target(
                                $connection,
                                $targetPageRecordId
                            );
                        if (!is_array($beforeShell)
                            || !is_array($targetBefore)
                            || !hash_equals(
                                $lockedPlan['targetStateHash'],
                                $targetBefore['stateHash']
                            )
                            || !red_addon_component_editor_publish_parent(
                                $connection,
                                $componentId,
                                $contentRecordId,
                                $lockedPlan['placementValues']
                            )
                        ) {
                            $transactionReason = 'parent_update_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        if (!red_addon_component_editor_create_transaction_active(
                            $connection
                        )) {
                            $transactionReason = 'transaction_lost';
                            throw new RuntimeException($transactionReason);
                        }
                        $after = red_addon_component_editor_publish_parent_row(
                            $connection,
                            $contentRecordId
                        );
                        $expectedRow = $beforeShell['row'];
                        foreach (
                            $lockedPlan['placementValues'] as
                                $fieldName => $value
                        ) {
                            $expectedRow[$fieldName] = $value;
                        }
                        if (!is_array($after) || $after !== $expectedRow) {
                            $transactionReason = 'parent_postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $package = red_addon_component_editor_load_values(
                            $connection,
                            $manifest,
                            $componentId,
                            $contentRecordId,
                            $adminRecordId
                        );
                        $targetAfter =
                            red_addon_component_editor_publish_target(
                                $connection,
                                $targetPageRecordId
                            );
                        if (empty($package['loaded'])
                            || !hash_equals(
                                $expectedPackageStateHash,
                                (string) ($package['stateHash'] ?? '')
                            )
                            || !is_array($targetAfter)
                            || !hash_equals(
                                $targetBefore['stateHash'],
                                $targetAfter['stateHash']
                            )
                        ) {
                            $transactionReason = 'postcondition_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $revision =
                            red_addon_component_editor_parent_revision(
                                $connection,
                                $contentRecordId,
                                $adminRecordId,
                                'move'
                            );
                        if (!is_array($revision)) {
                            $transactionReason = 'revision_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        $latest = red_admin_content_revision_latest(
                            $connection,
                            $contentRecordId
                        );
                        if (!is_array($latest)
                            || (int) ($latest['RevisionID'] ?? 0)
                                !== $revision['revisionId']
                            || (string) ($latest['Operation'] ?? '') !== 'move'
                            || !hash_equals(
                                $revision['stateHash'],
                                (string) ($latest['SnapshotHash'] ?? '')
                            )
                            || !red_addon_component_editor_create_transaction_active(
                                $connection
                            )
                            || !mysqli_commit($connection)
                        ) {
                            $transactionReason = 'transaction_failed';
                            throw new RuntimeException($transactionReason);
                        }
                        return [
                            'stateHash' => $revision['stateHash'],
                            'packageStateHash' => $package['stateHash'],
                            'targetStateHash' => $targetAfter['stateHash'],
                            'placementValues' =>
                                $lockedPlan['placementValues'],
                            'revisionId' => $revision['revisionId'],
                            'revisionNumber' => $revision['revisionNumber'],
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
        $result['placed'] = true;
        $result['stateHash'] = $lockedResult['stateHash'];
        $result['packageStateHash'] = $lockedResult['packageStateHash'];
        $result['targetStateHash'] = $lockedResult['targetStateHash'];
        $result['placementValues'] = $lockedResult['placementValues'];
        $result['revisionId'] = $lockedResult['revisionId'];
        $result['revisionNumber'] = $lockedResult['revisionNumber'];
        $result['reason'] = 'placed';
        return $result;
    }
}
