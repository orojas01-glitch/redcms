<?php
/**
 * Pure core-owned public-mutation form composition and rendering.
 *
 * A caller supplies one already validated manifest declaration, a bounded
 * package-field presentation model, and already-issued core CSRF/idempotency
 * evidence. Core derives the action and allowed controls from the declaration
 * and returns escaped semantic markup. This helper does not issue evidence,
 * read request globals, access a database, load package code, emit output or
 * headers, link a route, or execute a mutation.
 */

require_once __DIR__ . '/addon_public_mutation_form_helpers.php';
require_once __DIR__ . '/addon_public_mutation_http_request_helpers.php';

if (!function_exists('red_addon_public_mutation_form_ui_result')) {
    function red_addon_public_mutation_form_ui_result(
        $reason = 'form_ui_invalid'
    ) {
        $allowed = [
            'form_ui_invalid',
            'contract_unavailable',
            'instance_invalid',
            'label_invalid',
            'fields_invalid',
            'evidence_invalid',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'form_ui_invalid';
        return [
            'valid' => false,
            'instanceId' => '',
            'action' => '',
            'method' => '',
            'encoding' => '',
            'csrfHeaderName' => '',
            'idempotencyHeaderName' => '',
            'csrfToken' => '',
            'idempotencyKey' => '',
            'submitLabel' => '',
            'fields' => [],
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_form_ui_text')) {
    function red_addon_public_mutation_form_ui_text($value, $maximum)
    {
        if (!is_string($value)
            || !is_int($maximum)
            || $maximum < 1
            || strlen($value) < 1
            || strlen($value) > $maximum
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return '';
        }
        return trim($value) === $value ? $value : '';
    }
}

if (!function_exists('red_addon_public_mutation_form_ui_evidence_valid')) {
    function red_addon_public_mutation_form_ui_evidence_valid(
        $csrf,
        $idempotency
    ) {
        return is_array($csrf)
            && array_keys($csrf) === [
                'valid', 'issued', 'subjectRecordId', 'scopeSha256',
                'token', 'maxAgeSeconds', 'reason',
            ]
            && $csrf['valid'] === true
            && $csrf['issued'] === true
            && is_int($csrf['subjectRecordId'])
            && $csrf['subjectRecordId'] > 0
            && red_addon_valid_sha256($csrf['scopeSha256'])
            && red_addon_public_mutation_valid_opaque_token($csrf['token'])
            && $csrf['maxAgeSeconds'] === 600
            && $csrf['reason'] === 'csrf_issued'
            && is_array($idempotency)
            && array_keys($idempotency) === [
                'valid', 'issued', 'idempotencyRecordId',
                'subjectRecordId', 'scopeSha256', 'key',
                'maxAgeSeconds', 'reason',
            ]
            && $idempotency['valid'] === true
            && $idempotency['issued'] === true
            && is_int($idempotency['idempotencyRecordId'])
            && $idempotency['idempotencyRecordId'] > 0
            && is_int($idempotency['subjectRecordId'])
            && $idempotency['subjectRecordId'] === $csrf['subjectRecordId']
            && red_addon_valid_sha256($idempotency['scopeSha256'])
            && red_addon_public_mutation_valid_opaque_token(
                $idempotency['key']
            )
            && $idempotency['maxAgeSeconds'] === 600
            && $idempotency['reason'] === 'idempotency_issued';
    }
}

if (!function_exists('red_addon_public_mutation_form_ui_field')) {
    /**
     * Validates one package presentation field against one declared field.
     */
    function red_addon_public_mutation_form_ui_field(
        array $declared,
        $presented
    ) {
        if (!is_array($presented)
            || ($presented['key'] ?? null) !== ($declared['key'] ?? null)
            || !is_string($presented['control'] ?? null)
        ) {
            return null;
        }
        $key = $declared['key'];
        $control = $presented['control'];
        if ($declared['type'] === 'string') {
            $expectedControl = [
                'plain-text' => ['text', 'textarea'],
                'email' => ['email'],
                'telephone' => ['tel'],
                'iso-3166-1-alpha-2-uppercase' => ['text'],
            ];
            if (array_keys($presented) !== [
                'key', 'control', 'label', 'required', 'minLength',
                'maxLength', 'format', 'requiredWhen', 'visibleWhen',
            ]
                || !in_array(
                    $control,
                    $expectedControl[$declared['format']] ?? [],
                    true
                )
                || red_addon_public_mutation_form_ui_text(
                    $presented['label'] ?? null,
                    80
                ) === ''
                || ($presented['required'] ?? null)
                    !== $declared['required']
                || ($presented['minLength'] ?? null)
                    !== $declared['minLength']
                || ($presented['maxLength'] ?? null)
                    !== $declared['maxLength']
                || ($presented['format'] ?? null) !== $declared['format']
            ) {
                return null;
            }
            foreach (['requiredWhen', 'visibleWhen'] as $conditionKey) {
                $condition = $presented[$conditionKey];
                if ($condition !== null
                    && (!is_array($condition)
                        || array_keys($condition) !== ['field', 'equals']
                        || !red_addon_valid_component_field_key(
                            $condition['field'] ?? null
                        )
                        || !red_addon_public_mutation_form_identifier_valid(
                            $condition['equals'] ?? null,
                            1,
                            160
                        )
                        || $condition['field'] === $key)
                ) {
                    return null;
                }
            }
            if (($declared['required']
                    && ($presented['requiredWhen'] !== null
                        || $presented['visibleWhen'] !== null))
                || ($presented['requiredWhen'] !== null
                    && $presented['visibleWhen'] !== null
                    && $presented['requiredWhen']
                        !== $presented['visibleWhen'])
            ) {
                return null;
            }
            return [
                'key' => $key,
                'control' => $control,
                'label' => $presented['label'],
                'value' => '',
                'required' => $declared['required'],
                'minLength' => $declared['minLength'],
                'maxLength' => $declared['maxLength'],
                'format' => $declared['format'],
                'requiredWhen' => $presented['requiredWhen'],
                'visibleWhen' => $presented['visibleWhen'],
            ];
        }
        if ($declared['type'] === 'identifier' && $control === 'hidden') {
            if (array_keys($presented) !== ['key', 'control', 'value']
                || !red_addon_public_mutation_form_identifier_valid(
                    $presented['value'] ?? null,
                    $declared['minLength'],
                    $declared['maxLength']
                )
            ) {
                return null;
            }
            return [
                'key' => $key,
                'control' => 'hidden',
                'value' => $presented['value'],
            ];
        }
        if ($declared['type'] === 'positive-integer'
            && $control === 'number'
        ) {
            if (array_keys($presented) !== [
                'key', 'control', 'label', 'value',
            ]
                || red_addon_public_mutation_form_ui_text(
                    $presented['label'] ?? null,
                    80
                ) === ''
                || !is_int($presented['value'] ?? null)
                || $presented['value'] < $declared['minimum']
                || $presented['value'] > $declared['maximum']
            ) {
                return null;
            }
            return [
                'key' => $key,
                'control' => 'number',
                'label' => $presented['label'],
                'value' => $presented['value'],
                'minimum' => $declared['minimum'],
                'maximum' => $declared['maximum'],
            ];
        }
        if ($declared['type'] !== 'identifier' || $control !== 'select') {
            return null;
        }
        if (array_keys($presented) !== [
            'key', 'control', 'label', 'value', 'options',
        ]
            || red_addon_public_mutation_form_ui_text(
                $presented['label'] ?? null,
                80
            ) === ''
            || !red_addon_public_mutation_form_identifier_valid(
                $presented['value'] ?? null,
                $declared['minLength'],
                $declared['maxLength']
            )
            || !is_array($presented['options'] ?? null)
            || !array_is_list($presented['options'])
            || count($presented['options']) < 1
            || count($presented['options']) > 128
        ) {
            return null;
        }
        $options = [];
        $seen = [];
        foreach ($presented['options'] as $option) {
            if (!is_array($option)
                || array_keys($option) !== ['value', 'label']
                || !red_addon_public_mutation_form_identifier_valid(
                    $option['value'] ?? null,
                    $declared['minLength'],
                    $declared['maxLength']
                )
                || red_addon_public_mutation_form_ui_text(
                    $option['label'] ?? null,
                    120
                ) === ''
                || isset($seen[$option['value']])
            ) {
                return null;
            }
            $seen[$option['value']] = true;
            $options[] = $option;
        }
        if (!isset($seen[$presented['value']])) {
            return null;
        }
        return [
            'key' => $key,
            'control' => 'select',
            'label' => $presented['label'],
            'value' => $presented['value'],
            'options' => $options,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_form_ui_fields')) {
    function red_addon_public_mutation_form_ui_fields(
        array $contract,
        $presentedFields
    ) {
        $declared = red_addon_public_mutation_form_declared_fields($contract);
        if (!is_array($declared)
            || !is_array($presentedFields)
            || !array_is_list($presentedFields)
            || count($presentedFields) < 1
            || count($presentedFields) > count($declared)
        ) {
            return null;
        }
        $presentedByKey = [];
        foreach ($presentedFields as $presented) {
            $key = is_array($presented)
                && is_string($presented['key'] ?? null)
                    ? $presented['key']
                    : '';
            if ($key === ''
                || !array_key_exists($key, $declared)
                || array_key_exists($key, $presentedByKey)
            ) {
                return null;
            }
            $presentedByKey[$key] = $presented;
        }
        $normalized = [];
        foreach ($declared as $key => $field) {
            if (!array_key_exists($key, $presentedByKey)) {
                if ($field['required']) {
                    return null;
                }
                continue;
            }
            $normalizedField = red_addon_public_mutation_form_ui_field(
                $field,
                $presentedByKey[$key]
            );
            if (!is_array($normalizedField)) {
                return null;
            }
            $normalized[] = $normalizedField;
        }
        $normalizedByKey = [];
        foreach ($normalized as $field) {
            $normalizedByKey[$field['key']] = $field;
        }
        foreach ($normalized as $field) {
            if (!in_array(
                $field['control'],
                ['text', 'email', 'tel', 'textarea'],
                true
            )) {
                continue;
            }
            foreach (['requiredWhen', 'visibleWhen'] as $conditionKey) {
                $condition = $field[$conditionKey];
                if ($condition === null) {
                    continue;
                }
                $controller = $normalizedByKey[$condition['field']] ?? null;
                if (!is_array($controller)
                    || ($controller['control'] ?? null) !== 'select'
                    || !is_array($controller['options'] ?? null)
                ) {
                    return null;
                }
                $allowed = false;
                foreach ($controller['options'] as $option) {
                    $allowed = $allowed
                        || hash_equals(
                            $condition['equals'],
                            $option['value']
                        );
                }
                if (!$allowed) {
                    return null;
                }
            }
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_public_mutation_form_ui_compose')) {
    function red_addon_public_mutation_form_ui_compose(
        $manifest,
        $routeId,
        $mutationId,
        $instanceId,
        $submitLabel,
        $presentedFields,
        $csrf,
        $idempotency
    ) {
        $contract = red_addon_public_mutation_form_contract(
            $manifest,
            $routeId,
            $mutationId
        );
        if (!is_array($contract)
            || !is_string($contract['path'] ?? null)
            || !red_addon_valid_route_path($contract['path'])
        ) {
            return red_addon_public_mutation_form_ui_result(
                'contract_unavailable'
            );
        }
        if (!is_string($instanceId)
            || preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $instanceId) !== 1
        ) {
            return red_addon_public_mutation_form_ui_result('instance_invalid');
        }
        if (red_addon_public_mutation_form_ui_text($submitLabel, 80) === '') {
            return red_addon_public_mutation_form_ui_result('label_invalid');
        }
        $fields = red_addon_public_mutation_form_ui_fields(
            $contract,
            $presentedFields
        );
        if (!is_array($fields)) {
            return red_addon_public_mutation_form_ui_result('fields_invalid');
        }
        if (!red_addon_public_mutation_form_ui_evidence_valid(
            $csrf,
            $idempotency
        )) {
            return red_addon_public_mutation_form_ui_result('evidence_invalid');
        }
        return [
            'valid' => true,
            'instanceId' => $instanceId,
            'action' => $contract['path'],
            'method' => 'POST',
            'encoding' => 'application/x-www-form-urlencoded',
            'csrfHeaderName' =>
                red_addon_public_mutation_http_request_csrf_header_name(),
            'idempotencyHeaderName' =>
                red_addon_public_mutation_http_request_idempotency_header_name(),
            'csrfToken' => $csrf['token'],
            'idempotencyKey' => $idempotency['key'],
            'submitLabel' => $submitLabel,
            'fields' => $fields,
            'reason' => 'form_ui_ready',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_form_ui_escape')) {
    function red_addon_public_mutation_form_ui_escape($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('red_addon_public_mutation_form_ui_model_valid')) {
    function red_addon_public_mutation_form_ui_model_valid($model)
    {
        if (!is_array($model)
            || ($model['valid'] ?? null) !== true
            || array_keys($model) !== [
                'valid', 'instanceId', 'action', 'method', 'encoding',
                'csrfHeaderName', 'idempotencyHeaderName', 'csrfToken',
                'idempotencyKey', 'submitLabel', 'fields', 'reason',
            ]
            || $model['method'] !== 'POST'
            || $model['encoding'] !== 'application/x-www-form-urlencoded'
            || $model['csrfHeaderName']
                !== red_addon_public_mutation_http_request_csrf_header_name()
            || $model['idempotencyHeaderName']
                !== red_addon_public_mutation_http_request_idempotency_header_name()
            || $model['reason'] !== 'form_ui_ready'
            || !red_addon_valid_route_path($model['action'] ?? null)
            || !is_string($model['instanceId'] ?? null)
            || preg_match(
                '/\A[a-z][a-z0-9-]{0,63}\z/D',
                $model['instanceId']
            ) !== 1
            || red_addon_public_mutation_form_ui_text(
                $model['submitLabel'] ?? null,
                80
            ) === ''
            || !red_addon_public_mutation_valid_opaque_token(
                $model['csrfToken'] ?? null
            )
            || !red_addon_public_mutation_valid_opaque_token(
                $model['idempotencyKey'] ?? null
            )
            || !is_array($model['fields'] ?? null)
            || !array_is_list($model['fields'])
            || count($model['fields']) < 1
            || count($model['fields']) > 16
        ) {
            return false;
        }
        $previousKey = '';
        $modelByKey = [];
        foreach ($model['fields'] as $field) {
            if (!is_array($field)
                || !is_string($field['key'] ?? null)
                || !red_addon_valid_component_field_key($field['key'])
                || in_array(
                    $field['key'],
                    red_addon_public_mutation_reserved_field_keys(),
                    true
                )
                || $field['key'] <= $previousKey
                || !is_string($field['control'] ?? null)
            ) {
                return false;
            }
            $previousKey = $field['key'];
            $modelByKey[$field['key']] = $field;
            if ($field['control'] === 'hidden') {
                if (array_keys($field) !== ['key', 'control', 'value']
                    || !red_addon_public_mutation_form_identifier_valid(
                        $field['value'] ?? null,
                        1,
                        160
                    )
                ) {
                    return false;
                }
                continue;
            }
            if (in_array(
                $field['control'],
                ['text', 'email', 'tel', 'textarea'],
                true
            )) {
                if (array_keys($field) !== [
                    'key', 'control', 'label', 'value', 'required',
                    'minLength', 'maxLength', 'format', 'requiredWhen',
                    'visibleWhen',
                ]
                    || red_addon_public_mutation_form_ui_text(
                        $field['label'] ?? null,
                        80
                    ) === ''
                    || ($field['value'] ?? null) !== ''
                    || !is_bool($field['required'] ?? null)
                    || !is_int($field['minLength'] ?? null)
                    || !is_int($field['maxLength'] ?? null)
                    || $field['minLength'] < 1
                    || $field['maxLength'] > 2000
                    || $field['minLength'] > $field['maxLength']
                    || !in_array(
                        $field['format'] ?? null,
                        [
                            'plain-text',
                            'email',
                            'telephone',
                            'iso-3166-1-alpha-2-uppercase',
                        ],
                        true
                    )
                    || ($field['control'] === 'email'
                        && $field['format'] !== 'email')
                    || ($field['control'] === 'tel'
                        && $field['format'] !== 'telephone')
                    || ($field['control'] === 'textarea'
                        && $field['format'] !== 'plain-text')
                    || ($field['control'] === 'text'
                        && !in_array(
                            $field['format'],
                            [
                                'plain-text',
                                'iso-3166-1-alpha-2-uppercase',
                            ],
                            true
                        ))
                    || ($field['format'] === 'email'
                        && $field['maxLength'] > 254)
                    || ($field['format'] === 'telephone'
                        && $field['maxLength'] > 64)
                    || ($field['format']
                        === 'iso-3166-1-alpha-2-uppercase'
                        && ($field['minLength'] !== 2
                            || $field['maxLength'] !== 2))
                    || ($field['required']
                        && ($field['requiredWhen'] !== null
                            || $field['visibleWhen'] !== null))
                    || ($field['requiredWhen'] !== null
                        && $field['visibleWhen'] !== null
                        && $field['requiredWhen']
                            !== $field['visibleWhen'])
                ) {
                    return false;
                }
                foreach (['requiredWhen', 'visibleWhen'] as $conditionKey) {
                    $condition = $field[$conditionKey];
                    if ($condition !== null
                        && (!is_array($condition)
                            || array_keys($condition)
                                !== ['field', 'equals']
                            || !red_addon_valid_component_field_key(
                                $condition['field'] ?? null
                            )
                            || !red_addon_public_mutation_form_identifier_valid(
                                $condition['equals'] ?? null,
                                1,
                                160
                            )
                            || $condition['field'] === $field['key'])
                    ) {
                        return false;
                    }
                }
                continue;
            }
            if ($field['control'] === 'number') {
                if (array_keys($field) !== [
                    'key', 'control', 'label', 'value', 'minimum', 'maximum',
                ]
                    || red_addon_public_mutation_form_ui_text(
                        $field['label'] ?? null,
                        80
                    ) === ''
                    || !is_int($field['minimum'] ?? null)
                    || !is_int($field['maximum'] ?? null)
                    || !is_int($field['value'] ?? null)
                    || $field['minimum'] < 1
                    || $field['maximum'] > 2147483647
                    || $field['minimum'] > $field['maximum']
                    || $field['value'] < $field['minimum']
                    || $field['value'] > $field['maximum']
                ) {
                    return false;
                }
                continue;
            }
            if ($field['control'] !== 'select'
                || array_keys($field) !== [
                    'key', 'control', 'label', 'value', 'options',
                ]
                || red_addon_public_mutation_form_ui_text(
                    $field['label'] ?? null,
                    80
                ) === ''
                || !red_addon_public_mutation_form_identifier_valid(
                    $field['value'] ?? null,
                    1,
                    160
                )
                || !is_array($field['options'] ?? null)
                || !array_is_list($field['options'])
                || count($field['options']) < 1
                || count($field['options']) > 128
            ) {
                return false;
            }
            $selected = false;
            $seen = [];
            foreach ($field['options'] as $option) {
                if (!is_array($option)
                    || array_keys($option) !== ['value', 'label']
                    || !red_addon_public_mutation_form_identifier_valid(
                        $option['value'] ?? null,
                        1,
                        160
                    )
                    || red_addon_public_mutation_form_ui_text(
                        $option['label'] ?? null,
                        120
                    ) === ''
                    || isset($seen[$option['value']])
                ) {
                    return false;
                }
                $seen[$option['value']] = true;
                $selected = $selected
                    || hash_equals($field['value'], $option['value']);
            }
            if (!$selected) {
                return false;
            }
        }
        foreach ($model['fields'] as $field) {
            if (!in_array(
                $field['control'],
                ['text', 'email', 'tel', 'textarea'],
                true
            )) {
                continue;
            }
            foreach (['requiredWhen', 'visibleWhen'] as $conditionKey) {
                $condition = $field[$conditionKey];
                if ($condition === null) {
                    continue;
                }
                $controller = $modelByKey[$condition['field']] ?? null;
                if (!is_array($controller)
                    || ($controller['control'] ?? null) !== 'select'
                    || !is_array($controller['options'] ?? null)
                ) {
                    return false;
                }
                $allowed = false;
                foreach ($controller['options'] as $option) {
                    $allowed = $allowed
                        || hash_equals(
                            $condition['equals'],
                            $option['value']
                        );
                }
                if (!$allowed) {
                    return false;
                }
            }
        }
        return true;
    }
}

if (!function_exists('red_addon_public_mutation_form_ui_render')) {
    /**
     * Renders a fetch-enhanced form. Security evidence stays outside the form
     * body so the canonical decoder can continue accepting package fields only.
     */
    function red_addon_public_mutation_form_ui_render($model)
    {
        if (!red_addon_public_mutation_form_ui_model_valid($model)) {
            return '';
        }
        $escape = 'red_addon_public_mutation_form_ui_escape';
        $formId = 'red-public-mutation-' . $model['instanceId'];
        $html = '<form id="' . $escape($formId) . '"'
            . ' class="red-addon-public-mutation-form"'
            . ' data-red-addon-public-mutation-form'
            . ' data-red-csrf-header="' . $escape($model['csrfHeaderName']) . '"'
            . ' data-red-csrf-token="' . $escape($model['csrfToken']) . '"'
            . ' data-red-idempotency-header="'
            . $escape($model['idempotencyHeaderName']) . '"'
            . ' data-red-idempotency-key="'
            . $escape($model['idempotencyKey']) . '"'
            . ' action="' . $escape($model['action']) . '"'
            . ' method="post" enctype="' . $escape($model['encoding']) . '">';
        foreach ($model['fields'] as $field) {
            if (!is_array($field)
                || !is_string($field['key'] ?? null)
                || !is_string($field['control'] ?? null)
            ) {
                return '';
            }
            $name = $escape($field['key']);
            if ($field['control'] === 'hidden'
                && array_keys($field) === ['key', 'control', 'value']
            ) {
                $html .= '<input type="hidden" name="' . $name
                    . '" value="' . $escape($field['value']) . '">';
                continue;
            }
            $controlId = $formId . '-' . $field['key'];
            if (in_array(
                $field['control'],
                ['text', 'email', 'tel', 'textarea'],
                true
            )
                && array_keys($field) === [
                    'key', 'control', 'label', 'value', 'required',
                    'minLength', 'maxLength', 'format', 'requiredWhen',
                    'visibleWhen',
                ]
            ) {
                $html .= '<div class="red-addon-public-mutation-form__field"'
                    . ' data-red-addon-public-mutation-field="' . $name . '"';
                if (is_array($field['requiredWhen'])) {
                    $html .= ' data-red-required-when-field="'
                        . $escape($field['requiredWhen']['field']) . '"'
                        . ' data-red-required-when-equals="'
                        . $escape($field['requiredWhen']['equals']) . '"';
                }
                if (is_array($field['visibleWhen'])) {
                    $html .= ' data-red-visible-when-field="'
                        . $escape($field['visibleWhen']['field']) . '"'
                        . ' data-red-visible-when-equals="'
                        . $escape($field['visibleWhen']['equals']) . '"';
                }
                $html .= '><label for="' . $escape($controlId) . '">'
                    . $escape($field['label']) . '</label>';
                $required = $field['required'] ? ' required' : '';
                $lengths = ' minlength="' . $escape($field['minLength']) . '"'
                    . ' maxlength="' . $escape($field['maxLength']) . '"';
                if ($field['control'] === 'textarea') {
                    $html .= '<textarea id="' . $escape($controlId)
                        . '" name="' . $name . '"' . $lengths
                        . $required . '></textarea>';
                } else {
                    $pattern = $field['format']
                        === 'iso-3166-1-alpha-2-uppercase'
                            ? ' pattern="[A-Z]{2}" autocapitalize="characters"'
                            : '';
                    $html .= '<input id="' . $escape($controlId)
                        . '" type="' . $escape($field['control'])
                        . '" name="' . $name . '" value=""' . $lengths
                        . $pattern . $required . '>';
                }
                $html .= '</div>';
                continue;
            }
            if ($field['control'] === 'number'
                && array_keys($field) === [
                    'key', 'control', 'label', 'value', 'minimum', 'maximum',
                ]
            ) {
                $html .= '<label for="' . $escape($controlId) . '">'
                    . $escape($field['label']) . '</label>'
                    . '<input id="' . $escape($controlId)
                    . '" type="number" name="' . $name
                    . '" value="' . $escape($field['value'])
                    . '" min="' . $escape($field['minimum'])
                    . '" max="' . $escape($field['maximum'])
                    . '" step="1" inputmode="numeric" required>';
                continue;
            }
            if ($field['control'] !== 'select'
                || array_keys($field) !== [
                    'key', 'control', 'label', 'value', 'options',
                ]
                || !is_array($field['options'])
                || !array_is_list($field['options'])
            ) {
                return '';
            }
            $html .= '<label for="' . $escape($controlId) . '">'
                . $escape($field['label']) . '</label>'
                . '<select id="' . $escape($controlId) . '" name="'
                . $name . '" required>';
            foreach ($field['options'] as $option) {
                if (!is_array($option)
                    || array_keys($option) !== ['value', 'label']
                ) {
                    return '';
                }
                $selected = hash_equals(
                    (string) $field['value'],
                    (string) $option['value']
                ) ? ' selected' : '';
                $html .= '<option value="' . $escape($option['value']) . '"'
                    . $selected . '>' . $escape($option['label']) . '</option>';
            }
            $html .= '</select>';
        }
        $statusId = $formId . '-status';
        $html .= '<button type="submit" aria-describedby="'
            . $escape($statusId) . '">' . $escape($model['submitLabel'])
            . '</button><p id="' . $escape($statusId)
            . '" class="red-addon-public-mutation-form__status"'
            . ' data-red-addon-public-mutation-status role="status"'
            . ' aria-live="polite" aria-atomic="true"></p>'
            . '<noscript><p>This action requires JavaScript.</p></noscript>'
            . '</form>';
        return $html;
    }
}

?>
