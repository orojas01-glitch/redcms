<?php
/**
 * Core-owned operational browser bridge for administrator add-on forms.
 *
 * Edit reloads one exact permission-scoped form target. Create reloads one
 * target-free package draft. Both render only core-owned controls, and their
 * JSON dispatchers delegate to the corresponding atomic persistence boundary
 * only after the HTTP endpoint authenticates and verifies header CSRF.
 */

require_once __DIR__ . '/addon_admin_tool_form_ui_helpers.php';
require_once __DIR__ . '/addon_admin_tool_form_write_helpers.php';
require_once __DIR__ . '/addon_admin_tool_form_create_helpers.php';

if (!function_exists('red_addon_admin_tool_form_create_endpoint_request')) {
    function red_addon_admin_tool_form_create_endpoint_request(array $post)
    {
        $keys = array_keys($post);
        sort($keys, SORT_STRING);
        if ($keys !== ['form', 'tool']
            || !is_string($post['tool'] ?? null)
            || !red_addon_valid_capability($post['tool'])
            || !is_string($post['form'] ?? null)
            || !red_addon_valid_capability($post['form'])
        ) {
            return null;
        }
        return ['tool' => $post['tool'], 'form' => $post['form']];
    }
}

if (!function_exists('red_addon_admin_tool_form_endpoint_target_record_id')) {
    function red_addon_admin_tool_form_endpoint_target_record_id($value)
    {
        if (!is_string($value)
            || preg_match('/\A[1-9][0-9]{0,9}\z/D', $value) !== 1
        ) {
            return 0;
        }
        $recordId = (int) $value;
        return (string) $recordId === $value
            && $recordId <= 2147483647
                ? $recordId
                : 0;
    }
}

if (!function_exists('red_addon_admin_tool_form_endpoint_request')) {
    function red_addon_admin_tool_form_endpoint_request(array $post)
    {
        $keys = array_keys($post);
        sort($keys, SORT_STRING);
        if ($keys !== ['form', 'targetRecordId', 'tool']
            || !is_string($post['tool'] ?? null)
            || !red_addon_valid_capability($post['tool'])
            || !is_string($post['form'] ?? null)
            || !red_addon_valid_capability($post['form'])
        ) {
            return null;
        }
        $targetRecordId =
            red_addon_admin_tool_form_endpoint_target_record_id(
                $post['targetRecordId'] ?? null
            );
        if ($targetRecordId < 1) {
            return null;
        }
        return [
            'tool' => $post['tool'],
            'form' => $post['form'],
            'targetRecordId' => $targetRecordId,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_endpoint_context_result')) {
    function red_addon_admin_tool_form_endpoint_context_result(
        $toolId,
        $formId,
        $targetRecordId,
        $actorRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'ready' => false,
            'tool' => is_string($toolId) && red_addon_valid_capability($toolId)
                ? $toolId
                : '',
            'form' => is_string($formId) && red_addon_valid_capability($formId)
                ? $formId
                : '',
            'package' => '',
            'targetRecordId' => is_int($targetRecordId)
                && $targetRecordId >= 1
                && $targetRecordId <= 2147483647
                    ? $targetRecordId
                    : 0,
            'actorRecordId' => red_addon_admin_tool_form_actor_record_id(
                $actorRecordId
            ),
            'permission' => '',
            'manifest' => [],
            'contract' => [],
            'values' => [],
            'stateSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_endpoint_context')) {
    function red_addon_admin_tool_form_endpoint_context(
        $connection,
        $toolId,
        $formId,
        $targetRecordId,
        $actorRecordId
    ) {
        $targetRecordId = is_int($targetRecordId) ? $targetRecordId : 0;
        $result = red_addon_admin_tool_form_endpoint_context_result(
            $toolId,
            $formId,
            $targetRecordId,
            $actorRecordId
        );
        if (!$connection
            || $result['tool'] === ''
            || $result['form'] === ''
            || $result['targetRecordId'] < 1
            || $result['actorRecordId'] < 1
        ) {
            return $result;
        }
        $binding = red_addon_admin_tool_form_write_binding(
            $result['tool'],
            $result['form']
        );
        if (!is_array($binding)) {
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        if (!red_admin_transaction_tables_supported(
            $connection,
            array_merge(
                ['RED_Addon_Installations', 'RED_Addon_Activity_Log'],
                $binding['tables']
            )
        )
            || red_addon_admin_tool_form_write_package_version(
                $connection,
                $binding['package'] ?? ''
            ) === ''
        ) {
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        $loaded = red_addon_admin_tool_form_load_values(
            $connection,
            $result['tool'],
            $result['form'],
            $result['targetRecordId'],
            $result['actorRecordId']
        );
        $result['package'] = is_string($loaded['package'] ?? null)
            ? $loaded['package']
            : '';
        $result['permission'] = is_string($loaded['permission'] ?? null)
            ? $loaded['permission']
            : '';
        if (($loaded['loaded'] ?? false) !== true) {
            $result['reason'] = (string) (
                $loaded['reason'] ?? 'form_unavailable'
            );
            return $result;
        }
        if (!hash_equals(
            (string) ($binding['package'] ?? ''),
            $result['package']
        )) {
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        $manifest = red_addon_runtime_manifest($result['package']);
        $contract = is_array($manifest)
            ? red_addon_admin_tool_form_contract(
                $manifest,
                $result['tool'],
                $result['form']
            )
            : null;
        if (!is_array($manifest)
            || !is_array($contract)
            || !is_array($contract['fields'] ?? null)
            || $contract['fields'] === []
            || !is_array($loaded['values'] ?? null)
            || !red_addon_valid_sha256($loaded['stateSha256'] ?? null)
        ) {
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        $result['manifest'] = $manifest;
        $result['contract'] = $contract;
        $result['values'] = $loaded['values'];
        $result['stateSha256'] = $loaded['stateSha256'];
        $result['ready'] = true;
        $result['reason'] = 'ready';
        return $result;
    }
}

if (!function_exists('red_addon_admin_tool_form_create_endpoint_context_result')) {
    function red_addon_admin_tool_form_create_endpoint_context_result(
        $toolId,
        $formId,
        $actorRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'ready' => false,
            'tool' => is_string($toolId) && red_addon_valid_capability($toolId)
                ? $toolId
                : '',
            'form' => is_string($formId) && red_addon_valid_capability($formId)
                ? $formId
                : '',
            'package' => '',
            'actorRecordId' => red_addon_admin_tool_form_actor_record_id(
                $actorRecordId
            ),
            'permission' => '',
            'manifest' => [],
            'contract' => [],
            'values' => [],
            'initialStateSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_create_endpoint_context')) {
    function red_addon_admin_tool_form_create_endpoint_context(
        $connection,
        $toolId,
        $formId,
        $actorRecordId
    ) {
        $result = red_addon_admin_tool_form_create_endpoint_context_result(
            $toolId,
            $formId,
            $actorRecordId
        );
        if (!$connection
            || $result['tool'] === ''
            || $result['form'] === ''
            || $result['actorRecordId'] < 1
        ) {
            return $result;
        }
        $binding = red_addon_admin_tool_form_create_binding(
            $result['tool'],
            $result['form']
        );
        if (!is_array($binding)
            || !red_admin_transaction_tables_supported(
                $connection,
                array_merge(
                    ['RED_Addon_Installations', 'RED_Addon_Activity_Log'],
                    $binding['tables']
                )
            )
            || red_addon_admin_tool_form_write_package_version(
                $connection,
                $binding['package'] ?? ''
            ) === ''
        ) {
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        $loaded = red_addon_admin_tool_form_load_initial_values(
            $connection,
            $result['tool'],
            $result['form'],
            $result['actorRecordId']
        );
        $result['package'] = is_string($loaded['package'] ?? null)
            ? $loaded['package']
            : '';
        $result['permission'] = is_string($loaded['permission'] ?? null)
            ? $loaded['permission']
            : '';
        if (($loaded['loaded'] ?? false) !== true) {
            $result['reason'] = (string) (
                $loaded['reason'] ?? 'form_unavailable'
            );
            return $result;
        }
        if (!hash_equals(
            (string) ($binding['package'] ?? ''),
            $result['package']
        )) {
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        $manifest = red_addon_runtime_manifest($result['package']);
        $contract = is_array($manifest)
            ? red_addon_admin_tool_form_contract(
                $manifest,
                $result['tool'],
                $result['form']
            )
            : null;
        if (!is_array($manifest)
            || !is_array($contract)
            || !is_array($contract['create'] ?? null)
            || !is_array($loaded['values'] ?? null)
            || !red_addon_valid_sha256($loaded['stateSha256'] ?? null)
        ) {
            $result['reason'] = 'form_unavailable';
            return $result;
        }
        $result['manifest'] = $manifest;
        $result['contract'] = $contract;
        $result['values'] = $loaded['values'];
        $result['initialStateSha256'] = $loaded['stateSha256'];
        $result['ready'] = true;
        $result['reason'] = 'ready';
        return $result;
    }
}

if (!function_exists('red_addon_admin_tool_form_edit_control')) {
    function red_addon_admin_tool_form_edit_control(array $field, $value)
    {
        $type = (string) ($field['type'] ?? '');
        $attributes = [
            'data-red-addon-admin-form-control' => true,
            'autocomplete' => 'off',
            'required' => ($field['required'] ?? false) === true ? true : null,
            'aria-required' => ($field['required'] ?? false) === true
                ? 'true'
                : null,
        ];
        if ($type === 'textarea') {
            $attributes['rows'] = '5';
            $attributes['minlength'] = $field['minLength'] ?? null;
            $attributes['maxlength'] = $field['maxLength'] ?? null;
            return '<textarea'
                . red_addon_admin_tool_form_ui_attributes($attributes)
                . '>'
                . red_addon_admin_tool_form_ui_html(
                    is_string($value) ? $value : ''
                )
                . '</textarea>';
        }
        if ($type === 'select' || $type === 'boolean') {
            $html = '<select'
                . red_addon_admin_tool_form_ui_attributes($attributes)
                . '>';
            if (($field['required'] ?? false) !== true) {
                $html .= '<option value=""'
                    . ($value === null ? ' selected' : '')
                    . '>Choose…</option>';
            }
            $options = $type === 'boolean'
                ? [
                    ['value' => 'true', 'label' => 'Yes'],
                    ['value' => 'false', 'label' => 'No'],
                ]
                : ($field['options'] ?? []);
            foreach ($options as $option) {
                $optionValue = (string) ($option['value'] ?? '');
                $selected = $type === 'boolean'
                    ? (is_bool($value)
                        && $optionValue === ($value ? 'true' : 'false'))
                    : (is_string($value)
                        && hash_equals($optionValue, $value));
                $html .= '<option value="'
                    . red_addon_admin_tool_form_ui_html($optionValue)
                    . '"' . ($selected ? ' selected' : '') . '>'
                    . red_addon_admin_tool_form_ui_html(
                        $option['label'] ?? ''
                    )
                    . '</option>';
            }
            return $html . '</select>';
        }
        $typeAttributes = [
            'text' => ['type' => 'text'],
            'integer' => [
                'type' => 'number',
                'step' => '1',
                'inputmode' => 'numeric',
                'min' => $field['minimum'] ?? null,
                'max' => $field['maximum'] ?? null,
            ],
            'url' => ['type' => 'url', 'inputmode' => 'url'],
            'email' => ['type' => 'email', 'inputmode' => 'email'],
            'date' => ['type' => 'date'],
            'datetime' => [
                'type' => 'text',
                'placeholder' => 'YYYY-MM-DDTHH:MM:SS+00:00',
            ],
            'media-reference' => ['type' => 'text'],
        ];
        if (!isset($typeAttributes[$type])) {
            return '';
        }
        $attributes = array_merge($typeAttributes[$type], $attributes);
        if (in_array(
            $type,
            ['text', 'url', 'email', 'media-reference'],
            true
        )) {
            $attributes['minlength'] = $field['minLength'] ?? null;
            $attributes['maxlength'] = $field['maxLength'] ?? null;
        }
        if ($value !== null && (is_string($value) || is_int($value))) {
            $attributes['value'] = $value;
        }
        return '<input'
            . red_addon_admin_tool_form_ui_attributes($attributes)
            . '>';
    }
}

if (!function_exists('red_addon_admin_tool_form_edit_fields')) {
    function red_addon_admin_tool_form_edit_fields(
        array $fields,
        array $values,
        $template = false
    ) {
        $html = '';
        foreach ($fields as $field) {
            $key = is_string($field['key'] ?? null) ? $field['key'] : '';
            if ($key === '') {
                return '';
            }
            $hasValue = !$template && array_key_exists($key, $values);
            $value = $hasValue ? $values[$key] : null;
            if (($field['type'] ?? '') === 'collection') {
                $html .= red_addon_admin_tool_form_edit_collection(
                    $field,
                    is_array($value) && array_is_list($value) ? $value : [],
                    $template
                );
                continue;
            }
            $control = red_addon_admin_tool_form_edit_control($field, $value);
            if ($control === '') {
                return '';
            }
            $html .= '<label class="red-admin-addon-tool-form__field"'
                . ' data-red-addon-admin-form-field data-field-key="'
                . red_addon_admin_tool_form_ui_html($key)
                . '" data-field-type="'
                . red_addon_admin_tool_form_ui_html($field['type'] ?? '')
                . '"><span>'
                . red_addon_admin_tool_form_ui_html($field['label'] ?? '')
                . (($field['required'] ?? false) === true
                    ? ' <span aria-hidden="true">(required)</span>'
                    : '')
                . '</span>' . $control;
            if (isset($field['help'])) {
                $html .= '<small class="red-admin-addon-tool-form__help">'
                    . red_addon_admin_tool_form_ui_html($field['help'])
                    . '</small>';
            }
            $html .= '</label>';
        }
        return $html;
    }
}

if (!function_exists('red_addon_admin_tool_form_edit_item')) {
    function red_addon_admin_tool_form_edit_item(
        array $collection,
        array $values,
        $template = false
    ) {
        $fields = red_addon_admin_tool_form_edit_fields(
            $collection['fields'] ?? [],
            $values,
            $template
        );
        if ($fields === '') {
            return '';
        }
        return '<fieldset class="red-admin-addon-tool-form__item"'
            . ' data-red-addon-admin-form-item><legend>'
            . red_addon_admin_tool_form_ui_html(
                $collection['itemLabel'] ?? 'Item'
            )
            . '</legend><div class="red-admin-addon-tool-form__item-fields"'
            . ' data-red-addon-admin-form-object>' . $fields . '</div>'
            . '<button type="button" class="red-admin-addon-tool-form__remove"'
            . ' data-red-addon-admin-form-remove>Remove</button></fieldset>';
    }
}

if (!function_exists('red_addon_admin_tool_form_edit_collection')) {
    function red_addon_admin_tool_form_edit_collection(
        array $field,
        array $values,
        $template = false
    ) {
        $key = (string) ($field['key'] ?? '');
        $items = '';
        if (!$template) {
            foreach ($values as $itemValues) {
                if (!is_array($itemValues)) {
                    return '';
                }
                $item = red_addon_admin_tool_form_edit_item(
                    $field,
                    $itemValues
                );
                if ($item === '') {
                    return '';
                }
                $items .= $item;
            }
        }
        $itemTemplate = red_addon_admin_tool_form_edit_item($field, [], true);
        if ($itemTemplate === '') {
            return '';
        }
        return '<fieldset class="red-admin-addon-tool-form__collection"'
            . ' data-red-addon-admin-form-collection data-field-key="'
            . red_addon_admin_tool_form_ui_html($key)
            . '" data-min-items="'
            . (int) ($field['minItems'] ?? 0)
            . '" data-max-items="'
            . (int) ($field['maxItems'] ?? 0)
            . '"><legend>'
            . red_addon_admin_tool_form_ui_html($field['label'] ?? '')
            . (($field['required'] ?? false) === true
                ? ' <span aria-hidden="true">(required)</span>'
                : '')
            . '</legend>'
            . (isset($field['help'])
                ? '<p class="red-admin-addon-tool-form__help">'
                    . red_addon_admin_tool_form_ui_html($field['help'])
                    . '</p>'
                : '')
            . '<p class="red-admin-addon-tool-form__limit">Allows '
            . (int) ($field['minItems'] ?? 0) . '–'
            . (int) ($field['maxItems'] ?? 0) . ' items.</p>'
            . '<div class="red-admin-addon-tool-form__items"'
            . ' data-red-addon-admin-form-items>' . $items . '</div>'
            . '<template data-red-addon-admin-form-template>'
            . $itemTemplate . '</template>'
            . '<button type="button" class="red-admin-addon-tool-form__add"'
            . ' data-red-addon-admin-form-add>Add '
            . red_addon_admin_tool_form_ui_html(
                strtolower((string) ($field['itemLabel'] ?? 'item'))
            )
            . '</button></fieldset>';
    }
}

if (!function_exists('red_addon_admin_tool_form_endpoint_render')) {
    function red_addon_admin_tool_form_endpoint_render(array $context)
    {
        $expectedKeys = [
            'ready',
            'tool',
            'form',
            'package',
            'targetRecordId',
            'actorRecordId',
            'permission',
            'manifest',
            'contract',
            'values',
            'stateSha256',
            'reason',
        ];
        if (array_keys($context) !== $expectedKeys
            || ($context['ready'] ?? false) !== true
            || !red_addon_valid_capability($context['tool'] ?? null)
            || !red_addon_valid_capability($context['form'] ?? null)
            || !red_addon_valid_package_id($context['package'] ?? null)
            || !is_int($context['targetRecordId'] ?? null)
            || $context['targetRecordId'] < 1
            || !is_array($context['contract'] ?? null)
            || !is_array($context['values'] ?? null)
            || !red_addon_valid_sha256($context['stateSha256'] ?? null)
        ) {
            return red_addon_admin_tool_form_ui_unavailable();
        }
        $validated = red_addon_admin_tool_form_validate_values(
            $context['contract'],
            $context['values']
        );
        if (($validated['valid'] ?? false) !== true) {
            return red_addon_admin_tool_form_ui_unavailable();
        }
        $fields = red_addon_admin_tool_form_edit_fields(
            $context['contract']['fields'],
            $validated['values']
        );
        if ($fields === '') {
            return red_addon_admin_tool_form_ui_unavailable();
        }
        $escape = 'red_addon_admin_tool_form_ui_html';
        return '<section class="red-admin-addon-tool-form-workspace"'
            . ' data-red-addon-admin-form-workspace>'
            . '<form class="red-admin-addon-tool-form red-admin-addon-tool-form--editable"'
            . ' data-red-addon-admin-form-edit action="/admin/bin/save_addon_tool_form.php"'
            . ' data-edit-action="/admin/bin/edit_addon_tool_form.php"'
            . ' data-tool="' . $escape($context['tool']) . '"'
            . ' data-form="' . $escape($context['form']) . '"'
            . ' data-target-record-id="' . (int) $context['targetRecordId'] . '"'
            . ' data-current-state-sha256="'
            . $escape($context['stateSha256']) . '">'
            . '<header class="red-admin-addon-tool-form__header"><div>'
            . '<span>Add-on form</span><h2>'
            . $escape($context['contract']['label'] ?? '')
            . '</h2><p>'
            . $escape($context['contract']['description'] ?? '')
            . '</p></div><code>Target #'
            . (int) $context['targetRecordId'] . '</code></header>'
            . '<div class="red-admin-addon-tool-form__fields"'
            . ' data-red-addon-admin-form-object>' . $fields . '</div>'
            . '<div class="red-admin-addon-form__actions">'
            . '<span data-red-addon-admin-form-status role="status"'
            . ' aria-live="polite" hidden></span>'
            . '<button type="submit">Save changes</button></div></form></section>';
    }
}

if (!function_exists('red_addon_admin_tool_form_create_endpoint_render')) {
    function red_addon_admin_tool_form_create_endpoint_render(array $context)
    {
        $expectedKeys = [
            'ready',
            'tool',
            'form',
            'package',
            'actorRecordId',
            'permission',
            'manifest',
            'contract',
            'values',
            'initialStateSha256',
            'reason',
        ];
        if (array_keys($context) !== $expectedKeys
            || ($context['ready'] ?? false) !== true
            || !red_addon_valid_capability($context['tool'] ?? null)
            || !red_addon_valid_capability($context['form'] ?? null)
            || !red_addon_valid_package_id($context['package'] ?? null)
            || !is_array($context['contract']['create'] ?? null)
            || !is_array($context['values'] ?? null)
            || !red_addon_valid_sha256(
                $context['initialStateSha256'] ?? null
            )
        ) {
            return red_addon_admin_tool_form_ui_unavailable();
        }
        $validated = red_addon_admin_tool_form_validate_initial_values(
            $context['contract'],
            $context['values']
        );
        if (($validated['valid'] ?? false) !== true) {
            return red_addon_admin_tool_form_ui_unavailable();
        }
        $fields = red_addon_admin_tool_form_edit_fields(
            $context['contract']['fields'],
            $validated['values']
        );
        if ($fields === '') {
            return red_addon_admin_tool_form_ui_unavailable();
        }
        $escape = 'red_addon_admin_tool_form_ui_html';
        $create = $context['contract']['create'];
        return '<section class="red-admin-addon-tool-form-workspace"'
            . ' data-red-addon-admin-form-workspace>'
            . '<form class="red-admin-addon-tool-form red-admin-addon-tool-form--editable"'
            . ' data-red-addon-admin-form-create action="/admin/bin/create_addon_tool_form.php"'
            . ' data-edit-action="/admin/bin/edit_addon_tool_form.php"'
            . ' data-tool="' . $escape($context['tool']) . '"'
            . ' data-form="' . $escape($context['form']) . '"'
            . ' data-initial-state-sha256="'
            . $escape($context['initialStateSha256']) . '">'
            . '<header class="red-admin-addon-tool-form__header"><div>'
            . '<span>New add-on record</span><h2>'
            . $escape($create['label'] ?? '')
            . '</h2><p>'
            . $escape($create['description'] ?? '')
            . '</p></div></header>'
            . '<div class="red-admin-addon-tool-form__fields"'
            . ' data-red-addon-admin-form-object>' . $fields . '</div>'
            . '<div class="red-admin-addon-form__actions">'
            . '<span data-red-addon-admin-form-status role="status"'
            . ' aria-live="polite" hidden></span>'
            . '<button type="submit">'
            . $escape($create['label'] ?? 'Create')
            . '</button></div></form></section>';
    }
}

if (!function_exists('red_addon_admin_tool_form_save_failure')) {
    function red_addon_admin_tool_form_save_failure($reason)
    {
        $mapping = [
            'invalid_request' => [400, 'invalid_request'],
            'permission_denied' => [403, 'permission_denied'],
            'body_too_large' => [413, 'body_too_large'],
            'state_conflict' => [409, 'state_conflict'],
            'plan_mismatch' => [409, 'state_conflict'],
            'invalid_values' => [422, 'invalid_values'],
            'writer_unavailable' => [422, 'form_unavailable'],
            'form_unavailable' => [422, 'form_unavailable'],
            'package_not_enabled' => [422, 'form_unavailable'],
            'transaction_unsupported' => [422, 'form_unavailable'],
            'writer_failed' => [422, 'save_failed'],
            'postcondition_failed' => [422, 'save_failed'],
            'audit_failed' => [422, 'save_failed'],
        ];
        [$status, $publicReason] = $mapping[(string) $reason]
            ?? [503, 'temporary_unavailable'];
        return [
            'httpStatus' => $status,
            'body' => ['ok' => false, 'reason' => $publicReason],
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_save_dispatch')) {
    function red_addon_admin_tool_form_save_dispatch(
        $connection,
        $rawBody,
        $actorRecordId
    ) {
        if (!$connection || !is_string($rawBody)) {
            return red_addon_admin_tool_form_save_failure('invalid_request');
        }
        $preflight = red_addon_admin_tool_form_write_preflight(
            $connection,
            $rawBody,
            $actorRecordId
        );
        if (($preflight['prepared'] ?? false) !== true
            || !red_addon_valid_sha256($preflight['planSha256'] ?? null)
        ) {
            return red_addon_admin_tool_form_save_failure(
                $preflight['reason'] ?? 'form_unavailable'
            );
        }
        $written = red_addon_admin_tool_form_write(
            $connection,
            $rawBody,
            $actorRecordId,
            $preflight['planSha256']
        );
        if (($written['executed'] ?? false) === true) {
            return [
                'httpStatus' => 200,
                'body' => ['ok' => true, 'status' => 'saved'],
            ];
        }
        if (($written['unchanged'] ?? false) === true) {
            return [
                'httpStatus' => 200,
                'body' => ['ok' => true, 'status' => 'unchanged'],
            ];
        }
        return red_addon_admin_tool_form_save_failure(
            $written['reason'] ?? 'save_failed'
        );
    }
}

if (!function_exists('red_addon_admin_tool_form_create_failure')) {
    function red_addon_admin_tool_form_create_failure($reason)
    {
        $mapping = [
            'invalid_request' => [400, 'invalid_request'],
            'permission_denied' => [403, 'permission_denied'],
            'body_too_large' => [413, 'body_too_large'],
            'state_conflict' => [409, 'state_conflict'],
            'plan_mismatch' => [409, 'state_conflict'],
            'invalid_values' => [422, 'invalid_values'],
            'creator_unavailable' => [422, 'form_unavailable'],
            'form_unavailable' => [422, 'form_unavailable'],
            'package_not_enabled' => [422, 'form_unavailable'],
            'transaction_unsupported' => [422, 'form_unavailable'],
            'runtime_settings_unavailable' => [422, 'form_unavailable'],
            'creator_failed' => [422, 'create_failed'],
            'postcondition_failed' => [422, 'create_failed'],
            'audit_failed' => [422, 'create_failed'],
        ];
        [$status, $publicReason] = $mapping[(string) $reason]
            ?? [503, 'temporary_unavailable'];
        return [
            'httpStatus' => $status,
            'body' => ['ok' => false, 'reason' => $publicReason],
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_create_dispatch')) {
    function red_addon_admin_tool_form_create_dispatch(
        $connection,
        $rawBody,
        $actorRecordId
    ) {
        if (!$connection || !is_string($rawBody)) {
            return red_addon_admin_tool_form_create_failure(
                'invalid_request'
            );
        }
        $preflight = red_addon_admin_tool_form_create_preflight(
            $connection,
            $rawBody,
            $actorRecordId
        );
        if (($preflight['prepared'] ?? false) !== true
            || !red_addon_valid_sha256($preflight['planSha256'] ?? null)
        ) {
            return red_addon_admin_tool_form_create_failure(
                $preflight['reason'] ?? 'form_unavailable'
            );
        }
        $created = red_addon_admin_tool_form_create(
            $connection,
            $rawBody,
            $actorRecordId,
            $preflight['planSha256']
        );
        if (($created['executed'] ?? false) === true
            && is_int($created['targetRecordId'] ?? null)
            && $created['targetRecordId'] > 0
        ) {
            return [
                'httpStatus' => 200,
                'body' => [
                    'ok' => true,
                    'status' => 'created',
                    'targetRecordId' => $created['targetRecordId'],
                ],
            ];
        }
        return red_addon_admin_tool_form_create_failure(
            $created['reason'] ?? 'create_failed'
        );
    }
}

?>
