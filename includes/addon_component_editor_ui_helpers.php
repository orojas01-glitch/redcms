<?php
/**
 * Display-only administrator markup for add-on component editor schemas.
 *
 * This helper renders only core-owned, escaped form controls from an already
 * validated data-only manifest schema. It does not authorize an actor, open a
 * form, render a submit control, execute package code, load package data, or
 * write state.
 */

require_once __DIR__ . '/addon_component_editor_helpers.php';

if (!function_exists('red_addon_component_editor_ui_html')) {
    function red_addon_component_editor_ui_html($value)
    {
        return htmlspecialchars(
            is_scalar($value) ? (string) $value : '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('red_addon_component_editor_ui_unavailable')) {
    function red_addon_component_editor_ui_unavailable()
    {
        return '<div class="red-admin-addon-editor red-admin-addon-editor--unavailable"'
            . ' data-red-addon-component-editor-unavailable role="status">'
            . 'Component editor is unavailable.</div>';
    }
}

if (!function_exists('red_addon_component_editor_ui_error_messages')) {
    function red_addon_component_editor_ui_error_messages()
    {
        return [
            'required' => 'This field is required.',
            'invalid_type' => 'Enter a supported scalar value.',
            'invalid_utf8' => 'Enter valid text characters.',
            'invalid_control_character' => 'Remove unsupported control characters.',
            'too_short' => 'Enter a longer value.',
            'too_long' => 'Enter a shorter value.',
            'invalid_integer' => 'Enter a whole number.',
            'below_minimum' => 'Enter a value at or above the minimum.',
            'above_maximum' => 'Enter a value at or below the maximum.',
            'invalid_boolean' => 'Choose Yes or No.',
            'invalid_option' => 'Choose one of the available options.',
            'invalid_url' => 'Enter a complete HTTP or HTTPS URL.',
            'invalid_email' => 'Enter a valid email address.',
            'surrounding_whitespace' => 'Remove spaces before or after the value.',
            'invalid_date' => 'Enter a valid calendar date.',
            'invalid_datetime' => 'Enter a valid date, time, seconds, and UTC offset.',
            'invalid_media_reference' => 'Enter a valid media reference.',
            'unknown_field' => 'The submitted fields did not match this editor.',
            'invalid_payload' => 'The submitted values could not be read.',
            'payload_too_large' => 'The submitted values exceeded the editor limit.',
            'schema_unavailable' => 'The component editor schema is unavailable.',
            'unsupported_type' => 'The component editor contains an unsupported field.',
        ];
    }
}

if (!function_exists('red_addon_component_editor_ui_state')) {
    function red_addon_component_editor_ui_state(
        array $manifest,
        $componentId,
        array $schema,
        $valueResult
    ) {
        if (!is_string($componentId)
            || !isset($schema['fields'])
            || !is_array($schema['fields'])
            || !array_is_list($schema['fields'])
        ) {
            return null;
        }
        $emptyValues = [];
        foreach ($schema['fields'] as $field) {
            $emptyValues[$field['key']] = null;
        }
        $state = [
            'valid' => true,
            'values' => $emptyValues,
            'fieldErrors' => [],
            'globalErrors' => [],
        ];
        if ($valueResult === null) {
            return $state;
        }
        if (!is_array($valueResult)
            || array_keys($valueResult) !== ['valid', 'component', 'values', 'errors']
            || !is_bool($valueResult['valid'])
            || !is_string($valueResult['component'])
            || !hash_equals($componentId, $valueResult['component'])
            || !is_array($valueResult['values'])
            || !is_array($valueResult['errors'])
            || !array_is_list($valueResult['errors'])
        ) {
            return null;
        }

        if ($valueResult['valid']) {
            if ($valueResult['errors'] !== []) {
                return null;
            }
            $verified = red_addon_component_editor_validate_values(
                $manifest,
                $componentId,
                $valueResult['values']
            );
            if (empty($verified['valid'])
                || $verified['errors'] !== []
                || $verified['values'] !== $valueResult['values']
            ) {
                return null;
            }
            $state['values'] = $verified['values'];
            return $state;
        }

        if ($valueResult['values'] !== [] || $valueResult['errors'] === []) {
            return null;
        }
        $messages = red_addon_component_editor_ui_error_messages();
        $fieldKeys = array_fill_keys(array_keys($emptyValues), true);
        foreach ($valueResult['errors'] as $error) {
            if (!is_array($error)
                || array_keys($error) !== ['field', 'code']
                || (!is_string($error['field']) && $error['field'] !== null)
                || !is_string($error['code'])
                || !isset($messages[$error['code']])
            ) {
                return null;
            }
            $message = $messages[$error['code']];
            if (is_string($error['field'])
                && isset($fieldKeys[$error['field']])
            ) {
                if (!isset($state['fieldErrors'][$error['field']])) {
                    $state['fieldErrors'][$error['field']] = [];
                }
                if (!in_array(
                    $message,
                    $state['fieldErrors'][$error['field']],
                    true
                )) {
                    $state['fieldErrors'][$error['field']][] = $message;
                }
            } elseif (!in_array($message, $state['globalErrors'], true)) {
                $state['globalErrors'][] = $message;
            }
        }
        return $state;
    }
}

if (!function_exists('red_addon_component_editor_ui_attributes')) {
    function red_addon_component_editor_ui_attributes(array $attributes)
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $html .= ' ' . $name;
            if ($value !== true) {
                $html .= '="' . red_addon_component_editor_ui_html($value) . '"';
            }
        }
        return $html;
    }
}

if (!function_exists('red_addon_component_editor_ui_control')) {
    function red_addon_component_editor_ui_control(
        array $field,
        $value,
        $controlId,
        $describedBy,
        $hasError
    ) {
        $fieldType = $field['type'];
        $required = $field['required'] === true;
        $attributes = [
            'name' => 'componentValues[' . $field['key'] . ']',
            'id' => $controlId,
            'required' => $required,
            'aria-required' => $required ? 'true' : null,
            'aria-describedby' => $describedBy,
            'aria-invalid' => $hasError ? 'true' : null,
            'autocomplete' => 'off',
        ];

        if ($fieldType === 'textarea') {
            $attributes['rows'] = '5';
            $attributes['minlength'] = $field['minLength'] ?? null;
            $attributes['maxlength'] = $field['maxLength'];
            return '<textarea'
                . red_addon_component_editor_ui_attributes($attributes)
                . '>'
                . red_addon_component_editor_ui_html($value)
                . '</textarea>';
        }

        if ($fieldType === 'select' || $fieldType === 'boolean') {
            $html = '<select'
                . red_addon_component_editor_ui_attributes($attributes)
                . '>';
            $blankSelected = $value === null;
            $html .= '<option value=""'
                . ($blankSelected ? ' selected' : '')
                . ($required ? ' disabled' : '')
                . '>Choose…</option>';
            $options = $fieldType === 'boolean'
                ? [
                    ['value' => '1', 'label' => 'Yes', 'normalized' => true],
                    ['value' => '0', 'label' => 'No', 'normalized' => false],
                ]
                : array_map(
                    static function (array $option): array {
                        return $option + ['normalized' => $option['value']];
                    },
                    $field['options']
                );
            foreach ($options as $option) {
                $selected = $value !== null
                    && $value === $option['normalized'];
                $html .= '<option value="'
                    . red_addon_component_editor_ui_html($option['value'])
                    . '"'
                    . ($selected ? ' selected' : '')
                    . '>'
                    . red_addon_component_editor_ui_html($option['label'])
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
                'pattern' => '\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})',
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
            $attributes['maxlength'] = $field['maxLength'];
        }
        $attributes['value'] = $value === null
            ? ''
            : ($value === true ? '1' : ($value === false ? '0' : $value));
        return '<input'
            . red_addon_component_editor_ui_attributes($attributes)
            . ' />';
    }
}

if (!function_exists('red_addon_component_editor_ui_render')) {
    function red_addon_component_editor_ui_render(
        array $manifest,
        $componentId,
        $valueResult = null,
        $idPrefix = 'red-addon-component-editor'
    ) {
        if (!is_string($componentId)
            || !is_string($idPrefix)
            || preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $idPrefix) !== 1
        ) {
            return red_addon_component_editor_ui_unavailable();
        }
        $schema = red_addon_component_editor_schema($manifest, $componentId);
        if (!is_array($schema)
            || !isset($schema['fields'])
            || !is_array($schema['fields'])
        ) {
            return red_addon_component_editor_ui_unavailable();
        }
        $state = red_addon_component_editor_ui_state(
            $manifest,
            $componentId,
            $schema,
            $valueResult
        );
        if (!is_array($state)) {
            return red_addon_component_editor_ui_unavailable();
        }

        $componentToken = substr(hash('sha256', $componentId), 0, 12);
        $domPrefix = $idPrefix . '-' . $componentToken;
        $descriptionId = $domPrefix . '-description';
        $html = '<fieldset class="red-admin-addon-editor"'
            . ' data-red-addon-component-editor="'
            . red_addon_component_editor_ui_html($componentId)
            . '" aria-describedby="' . $descriptionId . '">';
        $html .= '<legend class="red-admin-addon-editor__legend">'
            . red_addon_component_editor_ui_html($schema['label'])
            . '</legend>';
        $html .= '<p class="red-admin-addon-editor__description" id="'
            . $descriptionId . '">'
            . red_addon_component_editor_ui_html($schema['description'])
            . '</p>';

        if ($state['globalErrors'] !== []) {
            $html .= '<div class="red-admin-addon-editor__summary" role="alert"><ul>';
            foreach ($state['globalErrors'] as $message) {
                $html .= '<li>'
                    . red_addon_component_editor_ui_html($message)
                    . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '<div class="red-admin-field-grid red-admin-addon-editor__fields">';
        foreach ($schema['fields'] as $field) {
            $fieldKey = $field['key'];
            $controlId = $domPrefix . '-' . $fieldKey;
            $helpId = $controlId . '-help';
            $errorId = $controlId . '-error';
            $fieldErrors = $state['fieldErrors'][$fieldKey] ?? [];
            $describedBy = [];
            if (isset($field['help'])) {
                $describedBy[] = $helpId;
            }
            if ($fieldErrors !== []) {
                $describedBy[] = $errorId;
            }
            $html .= '<div class="red-admin-field red-admin-addon-editor__field"'
                . ' data-red-addon-editor-field="'
                . red_addon_component_editor_ui_html($fieldKey)
                . '">';
            $html .= '<label for="' . $controlId . '">'
                . red_addon_component_editor_ui_html($field['label']);
            if ($field['required'] === true) {
                $html .= ' <span aria-hidden="true">*</span>';
            }
            $html .= '</label>';
            $html .= red_addon_component_editor_ui_control(
                $field,
                $state['values'][$fieldKey],
                $controlId,
                $describedBy === [] ? null : implode(' ', $describedBy),
                $fieldErrors !== []
            );
            if (isset($field['help'])) {
                $html .= '<span class="red-admin-field__help" id="'
                    . $helpId . '">'
                    . red_addon_component_editor_ui_html($field['help'])
                    . '</span>';
            }
            if ($fieldErrors !== []) {
                $html .= '<span class="red-admin-field__error" id="'
                    . $errorId . '">'
                    . red_addon_component_editor_ui_html(
                        implode(' ', $fieldErrors)
                    )
                    . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '</div></fieldset>';
        return $html;
    }
}
