<?php
/**
 * Core-owned administrator orchestration for creating add-on components.
 *
 * Runtime registrations and the enabled package manifest are authoritative.
 * Browser input may select one advertised component and submit its core-owned
 * form, but it cannot choose a package handler, permission, table, or record
 * identifier.
 */

require_once __DIR__ . '/addon_component_editor_create_helpers.php';
require_once __DIR__ . '/addon_component_editor_ui_helpers.php';

if (!function_exists('red_addon_component_editor_create_binding')) {
    function red_addon_component_editor_create_binding(
        $connection,
        $componentId,
        $actorRecordId
    ) {
        $actorRecordId = filter_var(
            $actorRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if (!($connection instanceof mysqli)
            || !is_string($componentId)
            || !red_addon_valid_capability($componentId)
            || $actorRecordId === false
        ) {
            return null;
        }
        $packageId = red_addon_runtime_owner('components', $componentId);
        $manifest = is_string($packageId)
            ? red_addon_runtime_manifest($packageId)
            : null;
        $schema = is_array($manifest)
            ? red_addon_component_editor_schema($manifest, $componentId)
            : null;
        if (!is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || !is_array($manifest)
            || !is_array($schema)
            || !red_addon_component_editor_create_package_enabled(
                $connection,
                $packageId
            )
        ) {
            return null;
        }
        foreach (['componentDataLoaders', 'componentDataCreators'] as $type) {
            $owner = red_addon_runtime_owner($type, $componentId);
            if (!is_string($owner)
                || !hash_equals($packageId, $owner)
                || !is_callable(red_addon_runtime_handler($type, $componentId))
            ) {
                return null;
            }
        }
        if (!is_array(
            red_addon_component_editor_creator_tables($componentId)
        )) {
            return null;
        }
        $decision = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'create',
            $actorRecordId
        );
        if (empty($decision['authorized'])) {
            return null;
        }
        return [
            'component' => $componentId,
            'package' => $packageId,
            'label' => (string) $schema['label'],
            'description' => (string) $schema['description'],
            'icon' => (string) $schema['icon'],
            'permission' => (string) $decision['permission'],
            'manifest' => $manifest,
        ];
    }
}

if (!function_exists('red_addon_component_editor_create_catalog')) {
    function red_addon_component_editor_create_catalog(
        $connection,
        $actorRecordId
    ) {
        $context = red_addon_runtime_current_context();
        if (!($connection instanceof mysqli)
            || !$context instanceof RED_Addon_Runtime_Context
        ) {
            return [];
        }
        $snapshot = $context->snapshot();
        $components = array_keys(
            is_array($snapshot['registrations']['components'] ?? null)
                ? $snapshot['registrations']['components']
                : []
        );
        sort($components, SORT_STRING);
        $catalog = [];
        foreach ($components as $componentId) {
            $binding = red_addon_component_editor_create_binding(
                $connection,
                $componentId,
                $actorRecordId
            );
            if (!is_array($binding)) {
                continue;
            }
            unset($binding['manifest']);
            $catalog[] = $binding;
        }
        usort($catalog, static function (array $left, array $right) {
            $label = strcasecmp($left['label'], $right['label']);
            return $label !== 0
                ? $label
                : strcmp($left['component'], $right['component']);
        });
        return $catalog;
    }
}

if (!function_exists('red_addon_component_editor_create_form_render')) {
    function red_addon_component_editor_create_form_render(
        array $binding,
        $layout,
        $language,
        $csrfToken
    ) {
        if (array_keys($binding) !== [
                'component', 'package', 'label', 'description', 'icon',
                'permission', 'manifest',
            ]
            || !is_string($layout)
            || $layout === ''
            || strlen($layout) > 64
            || !is_string($language)
            || preg_match('/\A[a-z]{2}\z/D', $language) !== 1
            || !is_string($csrfToken)
            || preg_match('/\A[a-f0-9]{64}\z/D', $csrfToken) !== 1
        ) {
            return red_addon_component_editor_ui_unavailable();
        }
        $editor = red_addon_component_editor_ui_render(
            $binding['manifest'],
            $binding['component'],
            null,
            'red-addon-create-editor'
        );
        if ($editor === red_addon_component_editor_ui_unavailable()) {
            return $editor;
        }
        $escape = static function ($value) {
            return htmlspecialchars(
                (string) $value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        };
        return '<section class="red-admin-addon-workspace"'
            . ' data-red-addon-component-create-workspace>'
            . '<div class="cp_viewall red-admin-addon-form__return">'
            . '<button type="button" data-red-addon-create-return>Show content types</button>'
            . '<span aria-hidden="true"> / </span>'
            . '<span aria-current="page">Add component</span></div>'
            . '<form class="red-admin-addon-form"'
            . ' data-red-addon-component-create-form method="post"'
            . ' action="/admin/bin/create_addon_component.php">'
            . '<header class="red-admin-addon-form__header"><div>'
            . '<span>Add-on component</span><h2>Add '
            . $escape($binding['label']) . '</h2></div><code>'
            . $escape($binding['component']) . '</code></header>'
            . '<label>Component title<input type="text" name="Title"'
            . ' maxlength="200" autocomplete="off" required /></label>'
            . $editor
            . '<input type="hidden" name="Component" value="'
            . $escape($binding['component']) . '" />'
            . '<input type="hidden" name="Layout" value="'
            . $escape($layout) . '" />'
            . '<input type="hidden" name="Language" value="'
            . $escape($language) . '" />'
            . '<input type="hidden" name="csrf_token" value="'
            . $escape($csrfToken) . '" />'
            . '<div class="red-admin-addon-form__actions">'
            . '<span data-red-addon-create-status role="status"'
            . ' aria-live="polite" hidden></span>'
            . '<button type="submit">Create component</button></div>'
            . '</form></section>';
    }
}

if (!function_exists('red_addon_component_editor_create_record_id')) {
    function red_addon_component_editor_create_record_id($connection)
    {
        if (!($connection instanceof mysqli)) {
            return 0;
        }
        try {
            for ($attempt = 0; $attempt < 32; $attempt++) {
                $recordId = random_int(1, 2147483647);
                if (red_addon_component_editor_create_record_available(
                    $connection,
                    $recordId
                )) {
                    return $recordId;
                }
            }
        } catch (Throwable $throwable) {
            return 0;
        }
        return 0;
    }
}

?>
