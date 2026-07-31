<?php
/**
 * Data-only add-on component editor value normalization.
 *
 * This helper validates submitted scalar values against an already validated
 * manifest editor schema. It does not execute package code, authorize an
 * administrator, render a form, query a package table, or write any state.
 */

require_once __DIR__ . '/addon_manifest_helpers.php';

if (!function_exists('red_addon_component_editor_value_result')) {
    function red_addon_component_editor_value_result($componentId)
    {
        return [
            'valid' => false,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'values' => [],
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_component_editor_value_error')) {
    function red_addon_component_editor_value_error(
        array &$result,
        $field,
        $code
    ) {
        $field = is_string($field)
            && red_addon_valid_component_field_key($field)
                ? $field
                : null;
        $error = [
            'field' => $field,
            'code' => (string) $code,
        ];
        if (!in_array($error, $result['errors'], true)) {
            $result['errors'][] = $error;
        }
    }
}

if (!function_exists('red_addon_component_editor_string_value')) {
    function red_addon_component_editor_string_value(
        array $field,
        $value,
        array &$result
    ) {
        $fieldKey = (string) ($field['key'] ?? '');
        $fieldType = (string) ($field['type'] ?? '');
        if (!is_string($value)) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'invalid_type'
            );
            return null;
        }
        if (preg_match('//u', $value) !== 1) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'invalid_utf8'
            );
            return null;
        }

        if ($fieldType === 'textarea') {
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            $hasUnsafeControl = preg_match(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                $value
            ) === 1;
        } else {
            $hasUnsafeControl = preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
        }
        if ($hasUnsafeControl) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'invalid_control_character'
            );
            return null;
        }

        $required = ($field['required'] ?? false) === true;
        $minimumLength = is_int($field['minLength'] ?? null)
            ? $field['minLength']
            : 0;
        $maximumLength = is_int($field['maxLength'] ?? null)
            ? $field['maxLength']
            : 0;
        if ($required && $value === '') {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'required'
            );
        }
        if (strlen($value) < $minimumLength) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'too_short'
            );
        }
        if ($maximumLength < 1 || strlen($value) > $maximumLength) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'too_long'
            );
        }
        return $value;
    }
}

if (!function_exists('red_addon_component_editor_integer_value')) {
    function red_addon_component_editor_integer_value(
        array $field,
        $value,
        array &$result
    ) {
        $fieldKey = (string) ($field['key'] ?? '');
        $required = ($field['required'] ?? false) === true;
        if ($value === '' && !$required) {
            return null;
        }
        if ($value === '') {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'required'
            );
            return null;
        }
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value)
            && strlen($value) <= 11
            && preg_match(
                '/\A(?:0|[1-9][0-9]*|-[1-9][0-9]*)\z/',
                $value
            ) === 1
        ) {
            $integer = filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => -2147483648,
                        'max_range' => 2147483647,
                    ],
                ]
            );
            if ($integer === false) {
                red_addon_component_editor_value_error(
                    $result,
                    $fieldKey,
                    'invalid_integer'
                );
                return null;
            }
        } else {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'invalid_integer'
            );
            return null;
        }

        $minimum = $field['minimum'] ?? null;
        $maximum = $field['maximum'] ?? null;
        if (!is_int($minimum) || $integer < $minimum) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'below_minimum'
            );
        }
        if (!is_int($maximum) || $integer > $maximum) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'above_maximum'
            );
        }
        return $integer;
    }
}

if (!function_exists('red_addon_component_editor_boolean_value')) {
    function red_addon_component_editor_boolean_value(
        array $field,
        $value,
        array &$result
    ) {
        $fieldKey = (string) ($field['key'] ?? '');
        $required = ($field['required'] ?? false) === true;
        if ($value === '' && !$required) {
            return null;
        }
        if ($value === '') {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'required'
            );
            return null;
        }
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }
        red_addon_component_editor_value_error(
            $result,
            $fieldKey,
            'invalid_boolean'
        );
        return null;
    }
}

if (!function_exists('red_addon_component_editor_select_value')) {
    function red_addon_component_editor_select_value(
        array $field,
        $value,
        array &$result
    ) {
        $fieldKey = (string) ($field['key'] ?? '');
        $required = ($field['required'] ?? false) === true;
        if ($value === '' && !$required) {
            return null;
        }
        if ($value === '') {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'required'
            );
            return null;
        }
        if (!is_string($value)) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'invalid_type'
            );
            return null;
        }
        $allowedValues = [];
        foreach ($field['options'] ?? [] as $option) {
            if (is_array($option) && is_string($option['value'] ?? null)) {
                $allowedValues[] = $option['value'];
            }
        }
        if (!in_array($value, $allowedValues, true)) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'invalid_option'
            );
            return null;
        }
        return $value;
    }
}

if (!function_exists('red_addon_component_editor_date_value')) {
    function red_addon_component_editor_date_value(
        array $field,
        $value,
        array &$result
    ) {
        $fieldKey = (string) ($field['key'] ?? '');
        $required = ($field['required'] ?? false) === true;
        if ($value === '' && !$required) {
            return null;
        }
        if (!is_string($value)
            || preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) !== 1
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        ) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                $value === '' ? 'required' : 'invalid_date'
            );
            return null;
        }
        return $value;
    }
}

if (!function_exists('red_addon_component_editor_datetime_value')) {
    function red_addon_component_editor_datetime_value(
        array $field,
        $value,
        array &$result
    ) {
        $fieldKey = (string) ($field['key'] ?? '');
        $required = ($field['required'] ?? false) === true;
        if ($value === '' && !$required) {
            return null;
        }
        if (!is_string($value)) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                'invalid_datetime'
            );
            return null;
        }
        $candidate = str_ends_with($value, 'Z')
            ? substr($value, 0, -1) . '+00:00'
            : $value;
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:sP',
            $candidate
        );
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($value === ''
            || $date === false
            || ($dateErrors !== false
                && (
                    ($dateErrors['warning_count'] ?? 0) > 0
                    || ($dateErrors['error_count'] ?? 0) > 0
                ))
            || $date->format('Y-m-d\TH:i:sP') !== $candidate
        ) {
            red_addon_component_editor_value_error(
                $result,
                $fieldKey,
                $value === '' && $required ? 'required' : 'invalid_datetime'
            );
            return null;
        }
        return $candidate;
    }
}

if (!function_exists('red_addon_component_editor_validate_values')) {
    function red_addon_component_editor_validate_values(
        array $manifest,
        $componentId,
        $submittedValues
    ) {
        $result = red_addon_component_editor_value_result($componentId);
        $schema = red_addon_component_editor_schema($manifest, $componentId);
        if (!is_array($schema)) {
            red_addon_component_editor_value_error(
                $result,
                null,
                'schema_unavailable'
            );
            return $result;
        }
        if (!is_array($submittedValues)
            || (array_is_list($submittedValues) && $submittedValues !== [])
        ) {
            red_addon_component_editor_value_error(
                $result,
                null,
                'invalid_payload'
            );
            return $result;
        }
        if (count($submittedValues) > 200) {
            red_addon_component_editor_value_error(
                $result,
                null,
                'payload_too_large'
            );
            return $result;
        }

        $fields = [];
        foreach ($schema['fields'] ?? [] as $field) {
            if (is_array($field) && is_string($field['key'] ?? null)) {
                $fields[$field['key']] = $field;
            }
        }
        foreach (array_keys($submittedValues) as $submittedKey) {
            if (!is_string($submittedKey) || !isset($fields[$submittedKey])) {
                red_addon_component_editor_value_error(
                    $result,
                    is_string($submittedKey) ? $submittedKey : null,
                    'unknown_field'
                );
            }
        }

        $normalized = [];
        foreach ($fields as $fieldKey => $field) {
            $present = array_key_exists($fieldKey, $submittedValues);
            if (!$present) {
                if (($field['required'] ?? false) === true) {
                    red_addon_component_editor_value_error(
                        $result,
                        $fieldKey,
                        'required'
                    );
                }
                $normalized[$fieldKey] = null;
                continue;
            }

            $value = $submittedValues[$fieldKey];
            $fieldType = (string) ($field['type'] ?? '');
            if (in_array(
                $fieldType,
                ['text', 'textarea', 'url', 'email', 'media-reference'],
                true
            )) {
                if ($value === ''
                    && ($field['required'] ?? false) !== true
                    && in_array(
                        $fieldType,
                        ['url', 'email', 'media-reference'],
                        true
                    )
                ) {
                    $value = null;
                } else {
                    $value = red_addon_component_editor_string_value(
                        $field,
                        $value,
                        $result
                    );
                }
                if (is_string($value)) {
                    if (in_array(
                        $fieldType,
                        ['url', 'email', 'media-reference'],
                        true
                    ) && trim($value) !== $value) {
                        red_addon_component_editor_value_error(
                            $result,
                            $fieldKey,
                            'surrounding_whitespace'
                        );
                    }
                    if ($fieldType === 'url' && $value !== '') {
                        $parts = parse_url($value);
                        if (filter_var($value, FILTER_VALIDATE_URL) === false
                            || !is_array($parts)
                            || !in_array(
                                strtolower((string) ($parts['scheme'] ?? '')),
                                ['http', 'https'],
                                true
                            )
                            || !is_string($parts['host'] ?? null)
                            || $parts['host'] === ''
                            || isset($parts['user'])
                            || isset($parts['pass'])
                        ) {
                            red_addon_component_editor_value_error(
                                $result,
                                $fieldKey,
                                'invalid_url'
                            );
                        }
                    } elseif ($fieldType === 'email'
                        && $value !== ''
                        && filter_var($value, FILTER_VALIDATE_EMAIL) === false
                    ) {
                        red_addon_component_editor_value_error(
                            $result,
                            $fieldKey,
                            'invalid_email'
                        );
                    } elseif ($fieldType === 'media-reference'
                        && $value !== ''
                        && preg_match(
                            '/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/',
                            $value
                        ) !== 1
                    ) {
                        red_addon_component_editor_value_error(
                            $result,
                            $fieldKey,
                            'invalid_media_reference'
                        );
                    }
                }
            } elseif ($fieldType === 'integer') {
                $value = red_addon_component_editor_integer_value(
                    $field,
                    $value,
                    $result
                );
            } elseif ($fieldType === 'boolean') {
                $value = red_addon_component_editor_boolean_value(
                    $field,
                    $value,
                    $result
                );
            } elseif ($fieldType === 'select') {
                $value = red_addon_component_editor_select_value(
                    $field,
                    $value,
                    $result
                );
            } elseif ($fieldType === 'date') {
                $value = red_addon_component_editor_date_value(
                    $field,
                    $value,
                    $result
                );
            } elseif ($fieldType === 'datetime') {
                $value = red_addon_component_editor_datetime_value(
                    $field,
                    $value,
                    $result
                );
            } else {
                red_addon_component_editor_value_error(
                    $result,
                    $fieldKey,
                    'unsupported_type'
                );
                $value = null;
            }
            $normalized[$fieldKey] = $value;
        }

        usort(
            $result['errors'],
            static function (array $left, array $right): int {
                $leftKey = ($left['field'] ?? '') . "\0" . ($left['code'] ?? '');
                $rightKey = ($right['field'] ?? '') . "\0" . ($right['code'] ?? '');
                return strcmp($leftKey, $rightKey);
            }
        );
        if ($result['errors'] !== []) {
            return $result;
        }
        $result['valid'] = true;
        $result['values'] = $normalized;
        return $result;
    }
}
