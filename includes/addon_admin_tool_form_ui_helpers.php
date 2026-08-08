<?php
/**
 * Core-owned display-only markup for administrator tool form schemas.
 *
 * The renderer accepts only validated manifest metadata and emits disabled
 * controls plus collection templates. It does not authorize an actor, load
 * values, invoke package PHP, open a form, render a submit control, read a
 * request, consume CSRF, expose an endpoint, or write state.
 */

require_once __DIR__ . '/addon_manifest_helpers.php';

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
        $describedBy
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
                . '></textarea>';
        }
        if ($fieldType === 'select' || $fieldType === 'boolean') {
            $html = '<select'
                . red_addon_admin_tool_form_ui_attributes($attributes)
                . '><option value="" selected>Choose…</option>';
            $options = $fieldType === 'boolean'
                ? [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ]
                : ($field['options'] ?? []);
            foreach ($options as $option) {
                $html .= '<option value="'
                    . red_addon_admin_tool_form_ui_html(
                        $option['value'] ?? ''
                    )
                    . '">'
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
        return '<input'
            . red_addon_admin_tool_form_ui_attributes($attributes)
            . '>';
    }
}

if (!function_exists('red_addon_admin_tool_form_ui_field')) {
    function red_addon_admin_tool_form_ui_field(
        array $field,
        $idPrefix,
        array $path
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
                . ' items.</p><div class="red-admin-addon-tool-form__item-template"'
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
            $helpId
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
        $instance = 'addon-tool-form'
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
            . 'Preview only. Editing and saving are not available.</p></header>';
        foreach ($contract['fields'] as $field) {
            $html .= red_addon_admin_tool_form_ui_field(
                $field,
                $idPrefix,
                []
            );
        }
        return $html . '</section>';
    }
}

?>
