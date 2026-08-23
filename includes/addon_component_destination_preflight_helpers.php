<?php
/**
 * Read-only composite preflight for one future add-on component destination.
 *
 * The caller is trusted core code. It must derive record identifiers on the
 * server and revalidate package preview evidence before invoking this helper.
 * This boundary validates the future Article route, component creation, and
 * placement projections but never starts a transaction or writes state.
 */

require_once __DIR__ . '/addon_component_editor_create_helpers.php';
require_once __DIR__ . '/addon_component_editor_publish_helpers.php';

if (!function_exists('red_addon_component_destination_result')) {
    function red_addon_component_destination_result(
        $adminRecordId,
        $componentId,
        $reason
    ) {
        return [
            'ready' => false,
            'actorRecordId' => is_int($adminRecordId) ? $adminRecordId : 0,
            'package' => '',
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'createPermission' => '',
            'publishPermission' => '',
            'packagePlanSha256' => '',
            'routeRecordId' => 0,
            'componentRecordId' => 0,
            'routeValues' => [],
            'componentParentMetadata' => [],
            'componentValues' => [],
            'componentCreatePlanHash' => '',
            'targetStateHash' => '',
            'placementValues' => [],
            'operations' => [],
            'planHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_destination_preview')) {
    function red_addon_component_destination_preview($value, $alias)
    {
        if (!is_array($value)
            || array_keys($value) !== [
                'schema',
                'planSha256',
                'intent',
                'ready',
                'requiresConfirmation',
                'writesEnabled',
                'path',
            ]
            || $value['schema'] !== 1
            || !red_addon_valid_sha256($value['planSha256'] ?? null)
            || ($value['intent'] ?? null) !== 'provision'
            || ($value['ready'] ?? null) !== true
            || ($value['requiresConfirmation'] ?? null) !== true
            || ($value['writesEnabled'] ?? null) !== false
            || !is_string($alias)
            || ($value['path'] ?? null) !== '/' . rawurlencode($alias)
        ) {
            return null;
        }
        return $value;
    }
}

if (!function_exists('red_addon_component_destination_route_available')) {
    function red_addon_component_destination_route_available(
        $connection,
        $alias,
        $language,
        array $home
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT COUNT(*) FROM RED_Articles
                 WHERE Component='Article'
                   AND BINARY Alias=BINARY ?
                   AND BINARY Language=BINARY ?
                   AND BINARY Sections=BINARY ?
                   AND BINARY Categories=BINARY ''
                   AND BINARY SubCategories=BINARY ''"
            );
            if (!$statement) {
                return false;
            }
            $sections = (string) ($home['sections'] ?? '');
            $count = 0;
            mysqli_stmt_bind_param(
                $statement,
                'sss',
                $alias,
                $language,
                $sections
            );
            mysqli_stmt_bind_result($statement, $count);
            $available = mysqli_stmt_execute($statement)
                && mysqli_stmt_fetch($statement) === true
                && $count === 0;
            mysqli_stmt_close($statement);
            return $available;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_component_destination_preflight')) {
    function red_addon_component_destination_preflight(
        $connection,
        array $manifest,
        $componentId,
        $adminRecordId,
        $request
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
        );
        $result = red_addon_component_destination_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $componentId,
            $adminRecordId === false ? 'invalid_actor' : 'invalid_request'
        );
        if (!($connection instanceof mysqli)
            || $adminRecordId === false
            || !is_array($request)
            || array_keys($request) !== [
                'packagePreview',
                'routeRecordId',
                'componentRecordId',
                'title',
                'alias',
                'language',
                'layout',
                'routePagePosition',
                'routePagePositionOrder',
                'componentPagePosition',
                'componentPagePositionOrder',
                'componentValues',
            ]
        ) {
            return $result;
        }

        $routeRecordId = filter_var(
            $request['routeRecordId'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
        );
        $componentRecordId = filter_var(
            $request['componentRecordId'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
        );
        $routePagePosition = filter_var(
            $request['routePagePosition'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 99]]
        );
        $routePagePositionOrder = filter_var(
            $request['routePagePositionOrder'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
        );
        $componentPagePosition = filter_var(
            $request['componentPagePosition'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 99]]
        );
        $componentPagePositionOrder = filter_var(
            $request['componentPagePositionOrder'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
        );
        $title = $request['title'];
        $alias = $request['alias'];
        $language = $request['language'];
        $layout = $request['layout'];
        if ($routeRecordId === false
            || $componentRecordId === false
            || $routeRecordId === $componentRecordId
            || $routePagePosition === false
            || $routePagePositionOrder === false
            || $componentPagePosition === false
            || $componentPagePositionOrder === false
            || !is_string($title)
            || $title === ''
            || trim($title) !== $title
            || strlen($title) > 200
            || preg_match('//u', $title) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $title) === 1
            || !is_string($alias)
            || preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $alias) !== 1
            || !is_string($language)
            || preg_match('/\A[a-z]{2}\z/D', $language) !== 1
            || !is_string($layout)
            || $layout === ''
            || strlen($layout) > 64
            || !is_array($request['componentValues'])
        ) {
            return $result;
        }
        $result['routeRecordId'] = $routeRecordId;
        $result['componentRecordId'] = $componentRecordId;

        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if (!red_addon_valid_package_id($packageId)
            || !is_array(
                red_addon_component_editor_schema($manifest, $componentId)
            )
            || red_addon_runtime_manifest($packageId) !== $manifest
        ) {
            $result['reason'] = 'schema_unavailable';
            return $result;
        }
        $result['package'] = $packageId;

        $preview = red_addon_component_destination_preview(
            $request['packagePreview'],
            $alias
        );
        if (!is_array($preview)) {
            $result['reason'] = 'preview_invalid';
            return $result;
        }
        $result['packagePlanSha256'] = $preview['planSha256'];

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
                $publish['reason'] ?? 'publish_permission_denied'
            );
            return $result;
        }

        if (!red_addon_component_editor_create_record_available(
            $connection,
            $routeRecordId
        ) || !red_addon_component_editor_create_record_available(
            $connection,
            $componentRecordId
        )) {
            $result['reason'] = 'record_id_unavailable';
            return $result;
        }
        $home = red_addon_component_editor_publish_target(
            $connection,
            0,
            $language
        );
        if (!is_array($home)) {
            $result['reason'] = 'home_target_unavailable';
            return $result;
        }
        if (!red_addon_component_destination_route_available(
            $connection,
            $alias,
            $language,
            $home
        )) {
            $result['reason'] = 'route_alias_unavailable';
            return $result;
        }
        if (red_admin_area_layout_definition($connection, $layout) === null
            || !red_admin_area_layout_supports_positions(
                $connection,
                $layout,
                [$routePagePosition, $componentPagePosition]
            )
        ) {
            $result['reason'] = 'layout_unsupported';
            return $result;
        }

        $route = red_admin_article_default_insert_data($routeRecordId);
        $route = array_merge($route, [
            'Title' => $title,
            'Component' => 'Article',
            'Alias' => $alias,
            'Sections' => (string) $home['sections'],
            'Categories' => '',
            'SubCategories' => '',
            'Layout' => $layout,
            'Article' => '',
            'PagePosition' => $routePagePosition,
            'PagePositionOrder' => $routePagePositionOrder,
            'Active' => 'Y',
            'Language' => $language,
        ]);
        $routePositions = red_admin_article_route_page_positions(
            $connection,
            $route
        );
        $routePositions[] = $routePagePosition;
        if (!red_admin_article_hierarchy_valid($connection, $route)
            || !red_admin_area_layout_supports_positions(
                $connection,
                $layout,
                array_values(array_unique($routePositions))
            )
            || !red_admin_article_validate_position_changes(
                $connection,
                $route,
                $route,
                true
            )
        ) {
            $result['reason'] = 'route_values_invalid';
            return $result;
        }

        $parentMetadata = [
            'title' => $title,
            'layout' => $layout,
            'language' => $language,
        ];
        $component = red_addon_component_editor_create_preflight(
            $connection,
            $manifest,
            $componentId,
            $componentRecordId,
            $adminRecordId,
            $parentMetadata,
            $request['componentValues']
        );
        $result['createPermission'] = is_string(
            $component['permission'] ?? null
        ) ? $component['permission'] : '';
        if (empty($component['ready'])) {
            $result['reason'] = 'component_' . (string) (
                $component['reason'] ?? 'preflight_failed'
            );
            return $result;
        }

        $target = [
            'recordId' => $routeRecordId,
            'component' => 'Article',
            'active' => 'Y',
            'alias' => $alias,
            'sections' => (string) $home['sections'],
            'categories' => '',
            'subCategories' => '',
            'layout' => $layout,
            'language' => $language,
            'pagePosition' => $routePagePosition,
        ];
        $targetStateHash = red_addon_component_editor_publish_hash($target);
        if (!red_addon_valid_sha256($targetStateHash)) {
            $result['reason'] = 'target_state_unavailable';
            return $result;
        }
        $placement = [
            'Sections' => (string) $home['sections'],
            'Categories' => '',
            'SubCategories' => '',
            'Article' => $alias,
            'PagePosition' => $componentPagePosition,
            'PagePositionOrder' => $componentPagePositionOrder,
            'Active' => 'Y',
        ];
        $routeValues = [
            'RecordID' => $routeRecordId,
            'Title' => $title,
            'Component' => 'Article',
            'Alias' => $alias,
            'Sections' => (string) $home['sections'],
            'Categories' => '',
            'SubCategories' => '',
            'Layout' => $layout,
            'PagePosition' => $routePagePosition,
            'PagePositionOrder' => $routePagePositionOrder,
            'Active' => 'Y',
            'Language' => $language,
        ];
        $operations = [
            'core.article-route.create',
            'core.addon-component.create',
            'core.addon-component.publish',
            'content.search.refresh',
        ];
        $plan = [
            'schema' => 1,
            'package' => $packageId,
            'component' => $componentId,
            'actorRecordId' => (string) $adminRecordId,
            'packagePlanSha256' => $preview['planSha256'],
            'routeValues' => $routeValues,
            'componentRecordId' => (string) $componentRecordId,
            'componentParentMetadata' => $parentMetadata,
            'componentValues' => $component['values'],
            'componentCreatePlanHash' => $component['planHash'],
            'targetStateHash' => $targetStateHash,
            'placementValues' => $placement,
            'operations' => $operations,
        ];
        $planHash = red_addon_component_editor_publish_hash($plan);
        if (!red_addon_valid_sha256($planHash)) {
            $result['reason'] = 'plan_unavailable';
            return $result;
        }

        $result['ready'] = true;
        $result['routeValues'] = $routeValues;
        $result['componentParentMetadata'] = $parentMetadata;
        $result['componentValues'] = $component['values'];
        $result['componentCreatePlanHash'] = $component['planHash'];
        $result['targetStateHash'] = $targetStateHash;
        $result['placementValues'] = $placement;
        $result['operations'] = $operations;
        $result['planHash'] = $planHash;
        $result['reason'] = 'ready';
        return $result;
    }
}

?>
