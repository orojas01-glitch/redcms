<?php
/**
 * Pure declared-form decoder for a future public add-on mutation dispatcher.
 *
 * Core accepts an in-memory manifest with one revalidated closed declaration,
 * a route/mutation identity, and raw form bytes supplied by a later dispatcher.
 * The later dispatcher must establish runtime trust before it calls this
 * decoder. It decodes only a closed application/x-www-form-urlencoded
 * package-field shape. It does not
 * inspect an HTTP request, read request/cookie/session globals, access a
 * database, load package code, issue or verify browser evidence, emit HTTP
 * state, or change lifecycle, package, Store Lite, or client state.
 */

require_once __DIR__ . '/addon_manifest_helpers.php';

if (!function_exists('red_addon_public_mutation_form_result')) {
    function red_addon_public_mutation_form_result($reason = 'fields_invalid')
    {
        $reason = is_string($reason)
            && in_array(
                $reason,
                ['contract_unavailable', 'body_too_large', 'fields_invalid'],
                true
            )
                ? $reason
                : 'fields_invalid';
        return [
            'valid' => false,
            'fields' => [],
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_form_contract')) {
    function red_addon_public_mutation_form_contract(
        $manifest,
        $routeId,
        $mutationId
    ) {
        if (!is_array($manifest)
            || !is_string($routeId)
            || !is_string($mutationId)
        ) {
            return null;
        }
        $contract = red_addon_public_mutation_contract(
            $manifest,
            $routeId,
            $mutationId
        );
        if (!is_array($contract)
            || ($contract['route'] ?? null) !== $routeId
            || ($contract['mutation'] ?? null) !== $mutationId
            || ($contract['method'] ?? null) !== 'POST'
            || ($contract['csrf'] ?? null) !== 'required'
            || ($contract['encoding'] ?? null)
                !== 'application/x-www-form-urlencoded'
            || !is_int($contract['maxBodyBytes'] ?? null)
            || $contract['maxBodyBytes'] < 128
            || $contract['maxBodyBytes'] > 8192
            || !is_array($contract['requestFields'] ?? null)
        ) {
            return null;
        }
        return $contract;
    }
}

if (!function_exists('red_addon_public_mutation_form_declared_fields')) {
    function red_addon_public_mutation_form_declared_fields(array $contract)
    {
        $declared = [];
        $previousKey = '';
        foreach ($contract['requestFields'] ?? [] as $field) {
            if (!is_array($field)) {
                return null;
            }
            $key = is_string($field['key'] ?? null) ? $field['key'] : '';
            $type = is_string($field['type'] ?? null) ? $field['type'] : '';
            if (!red_addon_valid_component_field_key($key)
                || in_array(
                    $key,
                    red_addon_public_mutation_reserved_field_keys(),
                    true
                )
                || $key <= $previousKey
                || !is_bool($field['required'] ?? null)
            ) {
                return null;
            }
            $keys = array_keys($field);
            sort($keys, SORT_STRING);
            if ($type === 'identifier') {
                if ($keys !== [
                    'key', 'maxLength', 'minLength', 'required', 'type',
                ]
                    || !is_int($field['minLength'] ?? null)
                    || !is_int($field['maxLength'] ?? null)
                    || $field['minLength'] < 1
                    || $field['maxLength'] > 160
                    || $field['minLength'] > $field['maxLength']
                ) {
                    return null;
                }
            } elseif ($type === 'positive-integer') {
                if ($keys !== [
                    'key', 'maximum', 'minimum', 'required', 'type',
                ]
                    || !is_int($field['minimum'] ?? null)
                    || !is_int($field['maximum'] ?? null)
                    || $field['minimum'] < 1
                    || $field['maximum'] > 2147483647
                    || $field['minimum'] > $field['maximum']
                ) {
                    return null;
                }
            } else {
                return null;
            }
            $declared[$key] = $field;
            $previousKey = $key;
        }
        return count($declared) >= 1 && count($declared) <= 8
            ? $declared
            : null;
    }
}

if (!function_exists('red_addon_public_mutation_form_identifier_valid')) {
    function red_addon_public_mutation_form_identifier_valid(
        $value,
        $minimum,
        $maximum
    ) {
        return is_string($value)
            && is_int($minimum)
            && is_int($maximum)
            && strlen($value) >= $minimum
            && strlen($value) <= $maximum
            && preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._~-]*\z/D',
                $value
            ) === 1;
    }
}

if (!function_exists('red_addon_public_mutation_form_positive_integer')) {
    function red_addon_public_mutation_form_positive_integer(
        $value,
        $minimum,
        $maximum
    ) {
        if (!is_string($value)
            || !is_int($minimum)
            || !is_int($maximum)
            || preg_match('/\A[1-9][0-9]{0,9}\z/D', $value) !== 1
        ) {
            return null;
        }
        if (strlen($value) === 10 && strcmp($value, '2147483647') > 0) {
            return null;
        }
        $number = (int) $value;
        return $number >= $minimum && $number <= $maximum ? $number : null;
    }
}

if (!function_exists('red_addon_public_mutation_form_value_decode')) {
    /**
     * The allowed identifier alphabet needs only canonical %7E form decoding.
     */
    function red_addon_public_mutation_form_value_decode($value)
    {
        if (!is_string($value)
            || str_contains($value, '~')
            || preg_match('/%(?!7E)/', $value) === 1
        ) {
            return null;
        }
        return str_replace('%7E', '~', $value);
    }
}

if (!function_exists('red_addon_public_mutation_form_decode')) {
    /**
     * Decodes only canonical, declared package fields from raw form bytes.
     */
    function red_addon_public_mutation_form_decode(
        $manifest,
        $routeId,
        $mutationId,
        $body
    ) {
        $contract = red_addon_public_mutation_form_contract(
            $manifest,
            $routeId,
            $mutationId
        );
        if (!is_array($contract)) {
            return red_addon_public_mutation_form_result('contract_unavailable');
        }
        $declared = red_addon_public_mutation_form_declared_fields($contract);
        if (!is_array($declared)) {
            return red_addon_public_mutation_form_result('contract_unavailable');
        }
        if (!is_string($body)
            || strlen($body) > $contract['maxBodyBytes']
        ) {
            return red_addon_public_mutation_form_result(
                is_string($body) ? 'body_too_large' : 'fields_invalid'
            );
        }
        if ($body !== '' && preg_match('/[\x00-\x1F\x7F]/', $body) === 1) {
            return red_addon_public_mutation_form_result();
        }

        $rawFields = [];
        if ($body !== '') {
            $pairs = explode('&', $body);
            if (count($pairs) > count($declared)) {
                return red_addon_public_mutation_form_result();
            }
            foreach ($pairs as $pair) {
                if ($pair === ''
                    || substr_count($pair, '=') !== 1
                    || strpbrk($pair, '+[];') !== false
                ) {
                    return red_addon_public_mutation_form_result();
                }
                [$key, $value] = explode('=', $pair, 2);
                $value = red_addon_public_mutation_form_value_decode($value);
                if ($key === ''
                    || str_contains($key, '%')
                    || !is_string($value)
                    || !array_key_exists($key, $declared)
                    || array_key_exists($key, $rawFields)
                ) {
                    return red_addon_public_mutation_form_result();
                }
                $rawFields[$key] = $value;
            }
        }

        $fields = [];
        foreach ($declared as $key => $field) {
            if (!array_key_exists($key, $rawFields)) {
                if ($field['required']) {
                    return red_addon_public_mutation_form_result();
                }
                continue;
            }
            if ($field['type'] === 'identifier') {
                if (!red_addon_public_mutation_form_identifier_valid(
                    $rawFields[$key],
                    $field['minLength'],
                    $field['maxLength']
                )) {
                    return red_addon_public_mutation_form_result();
                }
                $fields[$key] = $rawFields[$key];
                continue;
            }
            $number = red_addon_public_mutation_form_positive_integer(
                $rawFields[$key],
                $field['minimum'],
                $field['maximum']
            );
            if (!is_int($number)) {
                return red_addon_public_mutation_form_result();
            }
            $fields[$key] = $number;
        }
        ksort($fields, SORT_STRING);
        return [
            'valid' => true,
            'fields' => $fields,
            'reason' => 'parsed',
        ];
    }
}

?>
