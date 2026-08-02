<?php
/**
 * Core-owned orchestration for the authenticated existing-record editor.
 *
 * Browser input supplies only a core content record id and submitted values.
 * The component, package, manifest, loader, writer, permissions, and history
 * are derived again from persisted state and the enabled runtime registry.
 */

require_once __DIR__ . '/addon_component_editor_write_helpers.php';
require_once __DIR__ . '/addon_component_editor_ui_helpers.php';
require_once __DIR__ . '/addon_component_editor_revision_ui_helpers.php';

if (!function_exists('red_addon_component_editor_endpoint_result')) {
    function red_addon_component_editor_endpoint_result($reason)
    {
        return [
            'ready' => false,
            'contentRecordId' => 0,
            'actorRecordId' => 0,
            'component' => '',
            'package' => '',
            'title' => '',
            'label' => '',
            'manifest' => [],
            'values' => [],
            'stateHash' => '',
            'history' => [],
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_endpoint_context')) {
    function red_addon_component_editor_endpoint_context(
        $connection,
        $contentRecordId,
        $adminRecordId
    ) {
        $result = red_addon_component_editor_endpoint_result('invalid_request');
        $contentRecordId = filter_var($contentRecordId, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        $adminRecordId = filter_var($adminRecordId, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        if (!($connection instanceof mysqli)
            || $contentRecordId === false
            || $adminRecordId === false
        ) {
            return $result;
        }
        $result['contentRecordId'] = $contentRecordId;
        $result['actorRecordId'] = $adminRecordId;

        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT Component, Title FROM RED_Articles WHERE RecordID=? LIMIT 1'
            );
            if (!$statement) {
                $result['reason'] = 'record_unavailable';
                return $result;
            }
            mysqli_stmt_bind_param($statement, 'i', $contentRecordId);
            mysqli_stmt_execute($statement);
            $queryResult = mysqli_stmt_get_result($statement);
            $row = $queryResult ? mysqli_fetch_assoc($queryResult) : null;
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $row = null;
        }
        if (!is_array($row)) {
            $result['reason'] = 'record_unavailable';
            return $result;
        }

        $componentId = is_string($row['Component'] ?? null)
            ? trim($row['Component'])
            : '';
        $binding = red_addon_component_persistence_binding(
            $connection,
            $contentRecordId,
            $componentId
        );
        if (!is_array($binding)) {
            $result['reason'] = 'binding_unavailable';
            return $result;
        }
        $manifest = red_addon_runtime_manifest($binding['package'] ?? '');
        if (!is_array($manifest)) {
            $result['reason'] = 'manifest_unavailable';
            return $result;
        }
        $schema = red_addon_component_editor_schema($manifest, $componentId);
        if (!is_array($schema)) {
            $result['reason'] = 'schema_unavailable';
            return $result;
        }

        foreach (['view', 'edit'] as $operation) {
            $decision = red_addon_component_editor_permission_decision(
                $connection,
                $manifest,
                $componentId,
                $operation,
                $adminRecordId
            );
            if (empty($decision['authorized'])) {
                $result['reason'] = $operation . '_permission_denied';
                return $result;
            }
        }

        $loaded = red_addon_component_editor_load_values(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId
        );
        if (empty($loaded['loaded'])) {
            $result['reason'] = (string) ($loaded['reason'] ?? 'load_failed');
            return $result;
        }

        $result['ready'] = true;
        $result['component'] = $componentId;
        $result['package'] = (string) $binding['package'];
        $result['title'] = trim((string) ($row['Title'] ?? ''));
        $result['label'] = (string) $schema['label'];
        $result['manifest'] = $manifest;
        $result['values'] = $loaded['values'];
        $result['stateHash'] = $loaded['stateHash'];
        $result['history'] = red_addon_component_revision_history(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            25
        );
        $result['reason'] = 'ready';
        return $result;
    }
}

if (!function_exists('red_addon_component_editor_endpoint_render')) {
    function red_addon_component_editor_endpoint_render(
        array $context,
        $csrfToken
    ) {
        $expectedKeys = [
            'ready',
            'contentRecordId',
            'actorRecordId',
            'component',
            'package',
            'title',
            'label',
            'manifest',
            'values',
            'stateHash',
            'history',
            'reason',
        ];
        if (array_keys($context) !== $expectedKeys
            || ($context['ready'] ?? null) !== true
            || !is_int($context['contentRecordId'])
            || $context['contentRecordId'] < 1
            || !is_int($context['actorRecordId'])
            || $context['actorRecordId'] < 1
            || !is_string($context['component'])
            || !is_string($context['package'])
            || !is_string($context['title'])
            || !is_string($context['label'])
            || !is_array($context['manifest'])
            || !is_array($context['values'])
            || !is_string($context['stateHash'])
            || !red_addon_component_editor_state_hash_valid(
                $context['stateHash']
            )
            || !is_array($context['history'])
            || !is_string($csrfToken)
            || preg_match('/\A[a-f0-9]{64}\z/D', $csrfToken) !== 1
        ) {
            return red_addon_component_editor_ui_unavailable();
        }
        $valueResult = [
            'valid' => true,
            'component' => $context['component'],
            'values' => $context['values'],
            'errors' => [],
        ];
        $editor = red_addon_component_editor_ui_render(
            $context['manifest'],
            $context['component'],
            $valueResult,
            'red-addon-operational-editor'
        );
        if ($editor === red_addon_component_editor_ui_unavailable()) {
            return $editor;
        }

        $escape = static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $title = $context['title'] !== '' ? $context['title'] : $context['label'];
        $html = '<div class="cp_viewall red-admin-addon-form__return">'
            . '<button type="button" onclick="showdiv(\'edit_content_grid\'); return false;">Show content</button>'
            . '<span aria-hidden="true"> / </span><span aria-current="page">Edit component</span></div>'
            . '<form class="red-admin-addon-form" data-red-addon-component-form method="post"'
            . ' action="/admin/bin/update_addon_component.php">'
            . '<header class="red-admin-addon-form__header"><div><span>Add-on component</span><h2>'
            . $escape($title) . '</h2></div><code>' . $escape($context['component'])
            . '</code></header>' . $editor
            . '<input type="hidden" name="ContentRecordID" value="'
            . (int) $context['contentRecordId'] . '" />'
            . '<input type="hidden" name="CurrentStateHash" value="'
            . $escape($context['stateHash']) . '" data-red-addon-state-hash />'
            . '<input type="hidden" name="csrf_token" value="'
            . $escape($csrfToken) . '" />'
            . '<div class="red-admin-addon-form__actions">'
            . '<span data-red-addon-form-status role="status" aria-live="polite" hidden></span>'
            . '<button type="submit">Save changes</button></div></form>';

        if ($context['history'] !== []) {
            $html .= red_addon_component_revision_ui_render(
                $context['history'],
                $context['stateHash'],
                $context['label'],
                'red-addon-operational-history'
            );
        }
        return '<section class="red-admin-addon-workspace" data-red-addon-component-workspace>'
            . $html . '</section>';
    }
}
