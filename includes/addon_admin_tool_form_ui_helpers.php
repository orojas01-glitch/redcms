<?php
/**
 * Core-owned display-only markup for administrator tool form schemas.
 *
 * The renderer accepts only validated manifest metadata and optional
 * core-validated current values. It emits disabled controls plus either
 * collection templates or current collection rows. It does not authorize an
 * actor, invoke package PHP, open a form, render a submit control, read a
 * request, consume CSRF, expose an endpoint, or write state.
 */

require_once __DIR__ . '/addon_admin_tool_form_value_helpers.php';

if (!function_exists('red_addon_admin_tool_form_ui_html')) {
    function red_addon_admin_tool_form_ui_html($value)
    {
        return htmlspecialchars(
            is_scalar($value) ? (string) $value : '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('red_addon_admin_tool_form_ui_unavailable')) {
    function red_addon_admin_tool_form_ui_unavailable()
    {
        return '<div class="red-admin-addon-tool-form red-admin-addon-tool-form--unavailable"'
            . ' data-red-addon-admin-tool-form-unavailable role="status">'
            . 'Administrator form preview is unavailable.</div>';
    }
}

if (!function_exists('red_addon_admin_tool_form_ui_attributes')) {
    function red_addon_admin_tool_form_ui_attributes(array $attributes)
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $html .= ' ' . $name;
            if ($value !== true) {
                $html .= '="'
                    . red_addon_admin_tool_form_ui_html($value)
                    . '"';
            }
        }
        return $html;
    }
}

if (!function_exists('red_addon_admin_tool_form_ui_control')) {
    function red_addon_admin_tool_form_ui_control(
        array $field,
        $controlId,
        $describedBy,
        $hasValue = false,
        $value = null
    ) {
        $fieldType = (string) ($field['type'] ?? '');
        $attributes = [
            'id' => $controlId,
            'disabled' => true,
            'aria-disabled' => 'true',
            'aria-required' => ($field['required'] ?? false) === true
                ? 'true'
                : null,
            'aria-describedby' => $describedBy,
            'autocomplete' => 'off',
            'data-red-addon-admin-form-control' => $field['key'] ?? '',
        ];
        if ($fieldType === 'textarea') {
            $attributes['rows'] = '5';
            $attributes['minlength'] = $field['minLength'] ?? null;
            $attributes['maxlength'] = $field['maxLength'] ?? null;
            return '<textarea'
                . red_addon_admin_tool_form_ui_attributes($attributes)
                . '>'
                . ($hasValue && is_string($value)
                    ? red_addon_admin_tool_form_ui_html($value)
                    : '')
                . '</textarea>';
        }
        if ($fieldType === 'select' || $fieldType === 'boolean') {
            $html = '<select'
                . red_addon_admin_tool_form_ui_attributes($attributes)
                . '><option value=""'
                . (!$hasValue || $value === null ? ' selected' : '')
                . '>Choose…</option>';
            $options = $fieldType === 'boolean'
                ? [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ]
                : ($field['options'] ?? []);
            foreach ($options as $option) {
                $optionValue = (string) ($option['value'] ?? '');
                $selected = $fieldType === 'boolean'
                    ? ($hasValue
                        && is_bool($value)
                        && $optionValue === ($value ? '1' : '0'))
                    : ($hasValue
                        && is_string($value)
                        && hash_equals($optionValue, $value));
                $html .= '<option value="'
                    . red_addon_admin_tool_form_ui_html($optionValue)
                    . '"'
                    . ($selected ? ' selected' : '')
                    . '>'
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
                'inputmode' => 'text',
                'placeholder' => 'YYYY-MM-DDTHH:MM:SS+00:00',
            ],
            'media-reference' => ['type' => 'text'],
        ];
        if (!isset($typeAttributes[$fieldType])) {
            return '';
        }
        $attributes = array_merge($typeAttributes[$fieldType], $attributes);
        if (in_array(
            $fieldType,
            ['text', 'url', 'email', 'media-reference'],
            true
        )) {
            $attributes['minlength'] = $field['minLength'] ?? null;
            $attributes['maxlength'] = $field['maxLength'] ?? null;
        }
        if ($hasValue && $value !== null) {
            $attributes['value'] = $value;
        }
        return '<input'
            . red_addon_admin_tool_form_ui_attributes($attributes)
            . '>';
    }
}

if (!function_exists('red_addon_admin_tool_form_ui_field')) {
    function red_addon_admin_tool_form_ui_field(
        array $field,
        $idPrefix,
        array $path,
        $hasValue = false,
        $value = null
    ) {
        $key = (string) ($field['key'] ?? '');
        $path[] = $key;
        $pathToken = implode('-', $path);
        $controlId = $idPrefix . '-' . $pathToken;
        $helpId = isset($field['help']) ? $controlId . '-help' : null;
        if (($field['type'] ?? '') === 'collection') {
            $html = '<fieldset class="red-admin-addon-tool-form__collection"'
                . ' data-red-addon-admin-form-collection="'
                . red_addon_admin_tool_form_ui_html($key)
                . '" data-min-items="'
                . red_addon_admin_tool_form_ui_html($field['minItems'] ?? 0)
                . '" data-max-items="'
                . red_addon_admin_tool_form_ui_html($field['maxItems'] ?? 0)
                . '"><legend>'
                . red_addon_admin_tool_form_ui_html($field['label'] ?? '')
                . (($field['required'] ?? false) === true
                    ? ' <span aria-hidden="true">(required)</span>'
                    : '')
                . '</legend>';
            if ($helpId !== null) {
                $html .= '<p id="'
                    . red_addon_admin_tool_form_ui_html($helpId)
                    . '" class="red-admin-addon-tool-form__help">'
                    . red_addon_admin_tool_form_ui_html($field['help'])
                    . '</p>';
            }
            $html .= '<p class="red-admin-addon-tool-form__limit">Allows '
                . red_addon_admin_tool_form_ui_html($field['minItems'] ?? 0)
                . '–'
                . red_addon_admin_tool_form_ui_html($field['maxItems'] ?? 0)
                . ' items.</p>';
            if ($hasValue) {
                if (!is_array($value) || !array_is_list($value)) {
                    return '';
                }
                if ($value === []) {
                    return $html
                        . '<p class="red-admin-addon-tool-form__empty"'
                        . ' data-red-addon-admin-form-empty>No current items.'
                        . '</p></fieldset>';
                }
                foreach ($value as $itemIndex => $itemValues) {
                    if (!is_array($itemValues)) {
                        return '';
                    }
                    $itemNumber = $itemIndex + 1;
                    $itemName = ($field['itemLabel'] ?? '')
                        . ' ' . $itemNumber;
                    $html .= '<div class="red-admin-addon-tool-form__item"'
                        . ' data-red-addon-admin-form-item="'
                        . red_addon_admin_tool_form_ui_html($itemNumber)
                        . '" aria-label="'
                        . red_addon_admin_tool_form_ui_html($itemName)
                        . '"><p class="red-admin-addon-tool-form__item-label">'
                        . red_addon_admin_tool_form_ui_html($itemName)
                        . '</p>';
                    foreach ($field['fields'] ?? [] as $childField) {
                        $childKey = (string) ($childField['key'] ?? '');
                        $html .= red_addon_admin_tool_form_ui_field(
                            $childField,
                            $idPrefix,
                            array_merge($path, ['item-' . $itemNumber]),
                            array_key_exists($childKey, $itemValues),
                            $itemValues[$childKey] ?? null
                        );
                    }
                    $html .= '</div>';
                }
                return $html . '</fieldset>';
            }
            $html .= '<div class="red-admin-addon-tool-form__item-template"'
                . ' data-red-addon-admin-form-item-template aria-label="'
                . red_addon_admin_tool_form_ui_html(
                    ($field['itemLabel'] ?? '') . ' template'
                )
                . '"><p class="red-admin-addon-tool-form__item-label">'
                . red_addon_admin_tool_form_ui_html(
                    ($field['itemLabel'] ?? '') . ' template'
                )
                . '</p>';
            foreach ($field['fields'] ?? [] as $childField) {
                $html .= red_addon_admin_tool_form_ui_field(
                    $childField,
                    $idPrefix,
                    array_merge($path, ['item'])
                );
            }
            return $html . '</div></fieldset>';
        }

        $html = '<div class="red-admin-addon-tool-form__field"'
            . ' data-red-addon-admin-form-field="'
            . red_addon_admin_tool_form_ui_html($key)
            . '"><label for="'
            . red_addon_admin_tool_form_ui_html($controlId)
            . '">'
            . red_addon_admin_tool_form_ui_html($field['label'] ?? '')
            . (($field['required'] ?? false) === true
                ? ' <span aria-hidden="true">(required)</span>'
                : '')
            . '</label>';
        $html .= red_addon_admin_tool_form_ui_control(
            $field,
            $controlId,
            $helpId,
            $hasValue,
            $value
        );
        if ($helpId !== null) {
            $html .= '<p id="'
                . red_addon_admin_tool_form_ui_html($helpId)
                . '" class="red-admin-addon-tool-form__help">'
                . red_addon_admin_tool_form_ui_html($field['help'])
                . '</p>';
        }
        return $html . '</div>';
    }
}

if (!function_exists('red_addon_admin_tool_form_ui_render')) {
    function red_addon_admin_tool_form_ui_render(
        array $manifest,
        $toolId,
        $formId,
        $instance = 'addon-tool-form',
        $valueResult = null
    ) {
        if (!is_string($instance)
            || preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $instance) !== 1
        ) {
            return red_addon_admin_tool_form_ui_unavailable();
        }
        $contract = red_addon_admin_tool_form_contract(
            $manifest,
            $toolId,
            $formId
        );
        if (!is_array($contract)
            || !isset($contract['fields'])
            || !is_array($contract['fields'])
            || !array_is_list($contract['fields'])
            || $contract['fields'] === []
        ) {
            return red_addon_admin_tool_form_ui_unavailable();
        }
        $currentValues = null;
        if ($valueResult !== null) {
            $manifestId = is_string($manifest['id'] ?? null)
                ? $manifest['id']
                : '';
            $contractSha256 =
                red_addon_admin_tool_form_contract_fingerprint($contract);
            $validated = is_array($valueResult)
                ? red_addon_admin_tool_form_validate_values(
                    $contract,
                    $valueResult['values'] ?? null
                )
                : ['valid' => false];
            $stateSha256 = is_array($valueResult)
                ? red_addon_admin_tool_form_value_state_hash(
                    $manifestId,
                    $toolId,
                    $formId,
                    $valueResult['targetRecordId'] ?? null,
                    $contractSha256,
                    $valueResult['runtimeSettingsSha256'] ?? null,
                    is_array($validated['values'] ?? null)
                        ? $validated['values']
                        : []
                )
                : '';
            if (!is_array($valueResult)
                || ($valueResult['authorized'] ?? false) !== true
                || ($valueResult['invoked'] ?? false) !== true
                || ($valueResult['loaded'] ?? false) !== true
                || !is_string($valueResult['tool'] ?? null)
                || !hash_equals($toolId, $valueResult['tool'])
                || !is_string($valueResult['form'] ?? null)
                || !hash_equals($formId, $valueResult['form'])
                || !is_string($valueResult['package'] ?? null)
                || !hash_equals($manifestId, $valueResult['package'])
                || !is_string($valueResult['contractSha256'] ?? null)
                || !hash_equals(
                    $contractSha256,
                    $valueResult['contractSha256']
                )
                || ($validated['valid'] ?? false) !== true
                || !red_addon_valid_sha256($stateSha256)
                || !is_string($valueResult['stateSha256'] ?? null)
                || !hash_equals($stateSha256, $valueResult['stateSha256'])
            ) {
                return red_addon_admin_tool_form_ui_unavailable();
            }
            $currentValues = $validated['values'];
        }
        $token = substr(
            hash('sha256', $toolId . "\0" . $formId),
            0,
            12
        );
        $idPrefix = $instance . '-' . $token;
        $html = '<section class="red-admin-addon-tool-form"'
            . ' data-red-addon-admin-tool-form="'
            . red_addon_admin_tool_form_ui_html($formId)
            . '" data-red-addon-admin-tool="'
            . red_addon_admin_tool_form_ui_html($toolId)
            . '" aria-labelledby="'
            . red_addon_admin_tool_form_ui_html($idPrefix . '-title')
            . '"><header><h3 id="'
            . red_addon_admin_tool_form_ui_html($idPrefix . '-title')
            . '">'
            . red_addon_admin_tool_form_ui_html($contract['label'] ?? '')
            . '</h3><p>'
            . red_addon_admin_tool_form_ui_html(
                $contract['description'] ?? ''
            )
            . '</p><p class="red-admin-addon-tool-form__notice" role="status">'
            . ($currentValues === null
                ? 'Preview only. Editing and saving are not available.'
                : 'Current values. Editing and saving are not available.')
            . '</p></header>';
        foreach ($contract['fields'] as $field) {
            $fieldKey = (string) ($field['key'] ?? '');
            $html .= red_addon_admin_tool_form_ui_field(
                $field,
                $idPrefix,
                [],
                $currentValues !== null
                    && array_key_exists($fieldKey, $currentValues),
                $currentValues[$fieldKey] ?? null
            );
        }
        return $html . '</section>';
    }
}

?>
