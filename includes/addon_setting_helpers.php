<?php
/**
 * Non-executing add-on setting value normalization.
 *
 * Core validates one closed configuration object against data-only manifest
 * definitions. This boundary does not read or write a database, resolve a
 * secret, execute package code, authorize an actor, render a form, or change
 * package lifecycle state.
 */

require_once __DIR__ . '/addon_manifest_helpers.php';

if (!function_exists('red_addon_setting_value_result')) {
    function red_addon_setting_value_result()
    {
        return [
            'valid' => false,
            'values' => [],
            'secretReferences' => [],
            'missing' => [],
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_setting_value_error')) {
    function red_addon_setting_value_error(array &$result, $key, $code)
    {
        $key = is_string($key) && red_addon_valid_permission($key)
            ? $key
            : null;
        $error = [
            'setting' => $key,
            'code' => (string) $code,
        ];
        if (!in_array($error, $result['errors'], true)) {
            $result['errors'][] = $error;
        }
    }
}

if (!function_exists('red_addon_setting_normalize_value')) {
    function red_addon_setting_normalize_value(
        array $definition,
        $value,
        array &$result
    ) {
        $key = (string) ($definition['key'] ?? '');
        $type = (string) ($definition['type'] ?? '');
        if ($type === 'boolean') {
            if (!is_bool($value)) {
                red_addon_setting_value_error(
                    $result,
                    $key,
                    'invalid_boolean'
                );
                return null;
            }
            return $value;
        }
        if ($type === 'integer') {
            if (!is_int($value)
                || $value < -2147483648
                || $value > 2147483647
            ) {
                red_addon_setting_value_error(
                    $result,
                    $key,
                    'invalid_integer'
                );
                return null;
            }
            return $value;
        }
        if ($type === 'select') {
            if (!is_string($value)
                || !in_array(
                    $value,
                    is_array($definition['options'] ?? null)
                        ? $definition['options']
                        : [],
                    true
                )
            ) {
                red_addon_setting_value_error(
                    $result,
                    $key,
                    'invalid_option'
                );
                return null;
            }
            return $value;
        }
        if (!in_array(
            $type,
            ['text', 'url', 'email', 'secret-reference'],
            true
        ) || !red_addon_setting_string_is_valid($type, $value)) {
            red_addon_setting_value_error(
                $result,
                $key,
                'invalid_' . str_replace('-', '_', $type ?: 'type')
            );
            return null;
        }
        return $value;
    }
}

if (!function_exists('red_addon_settings_validate_values')) {
    function red_addon_settings_validate_values(
        array $manifest,
        $configuredValues
    ) {
        $result = red_addon_setting_value_result();
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema)) {
            red_addon_setting_value_error(
                $result,
                null,
                'schema_unavailable'
            );
            return $result;
        }
        if (!is_array($configuredValues)
            || (array_is_list($configuredValues) && $configuredValues !== [])
        ) {
            red_addon_setting_value_error(
                $result,
                null,
                'invalid_payload'
            );
            return $result;
        }
        if (count($configuredValues) > 200) {
            red_addon_setting_value_error(
                $result,
                null,
                'payload_too_large'
            );
            return $result;
        }

        $definitions = [];
        foreach ($schema as $definition) {
            if (is_array($definition)
                && is_string($definition['key'] ?? null)
            ) {
                $definitions[$definition['key']] = $definition;
            }
        }
        foreach (array_keys($configuredValues) as $configuredKey) {
            if (!is_string($configuredKey)
                || !isset($definitions[$configuredKey])
            ) {
                red_addon_setting_value_error(
                    $result,
                    is_string($configuredKey) ? $configuredKey : null,
                    'unknown_setting'
                );
            }
        }

        $values = [];
        $secretReferences = [];
        foreach ($definitions as $key => $definition) {
            $present = array_key_exists($key, $configuredValues);
            if (!$present
                && array_key_exists('default', $definition)
                && $definition['default'] !== null
            ) {
                $value = $definition['default'];
            } elseif ($present) {
                $value = $configuredValues[$key];
            } else {
                $result['missing'][] = $key;
                red_addon_setting_value_error($result, $key, 'required');
                continue;
            }

            $normalized = red_addon_setting_normalize_value(
                $definition,
                $value,
                $result
            );
            if (($definition['secret'] ?? false) === true) {
                if (is_string($normalized)) {
                    $secretReferences[$key] = $normalized;
                }
            } elseif ($normalized !== null
                || in_array(
                    $definition['type'] ?? null,
                    ['boolean', 'integer'],
                    true
                )
            ) {
                $values[$key] = $normalized;
            }
        }

        sort($result['missing'], SORT_STRING);
        usort(
            $result['errors'],
            static function (array $left, array $right): int {
                return strcmp(
                    ($left['setting'] ?? '') . "\0" . ($left['code'] ?? ''),
                    ($right['setting'] ?? '') . "\0" . ($right['code'] ?? '')
                );
            }
        );
        if ($result['errors'] !== []) {
            return $result;
        }
        $result['valid'] = true;
        $result['values'] = $values;
        $result['secretReferences'] = $secretReferences;
        return $result;
    }
}

?>
