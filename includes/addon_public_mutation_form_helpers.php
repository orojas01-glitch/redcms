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
            } elseif ($type === 'string') {
                if ($keys !== [
                    'format', 'key', 'maxLength', 'minLength', 'required',
                    'type',
                ]
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
                    || !is_int($field['minLength'] ?? null)
                    || !is_int($field['maxLength'] ?? null)
                    || $field['minLength'] < 1
                    || $field['maxLength'] > 2000
                    || $field['minLength'] > $field['maxLength']
                    || ($field['format'] === 'email'
                        && $field['maxLength'] > 254)
                    || ($field['format'] === 'telephone'
                        && $field['maxLength'] > 64)
                    || ($field['format']
                        === 'iso-3166-1-alpha-2-uppercase'
                        && ($field['minLength'] !== 2
                            || $field['maxLength'] !== 2))
                ) {
                    return null;
                }
            } else {
                return null;
            }
            $declared[$key] = $field;
            $previousKey = $key;
        }
        return count($declared) >= 1 && count($declared) <= 16
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

if (!function_exists('red_addon_public_mutation_form_string_valid')) {
    function red_addon_public_mutation_form_string_valid(
        $value,
        $format,
        $minimum,
        $maximum,
        $required
    ) {
        if (!is_string($value)
            || !is_string($format)
            || !is_int($minimum)
            || !is_int($maximum)
            || !is_bool($required)
            || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
            || trim($value) !== $value
        ) {
            return false;
        }
        if ($value === '') {
            return $required === false;
        }
        $length = preg_match_all('/./us', $value, $matches);
        if (!is_int($length)
            || $length < $minimum
            || $length > $maximum
        ) {
            return false;
        }
        if ($format === 'plain-text') {
            return true;
        }
        if ($format === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }
        if ($format === 'telephone') {
            return preg_match('/\A[0-9+(). -]+\z/D', $value) === 1
                && preg_match_all('/[0-9]/', $value, $digits) >= 7;
        }
        return $format === 'iso-3166-1-alpha-2-uppercase'
            && preg_match('/\A[A-Z]{2}\z/D', $value) === 1;
    }
}

if (!function_exists('red_addon_public_mutation_form_value_encode')) {
    /**
     * Mirrors URLSearchParams application/x-www-form-urlencoded encoding.
     */
    function red_addon_public_mutation_form_value_encode($value)
    {
        if (!is_string($value)) {
            return null;
        }
        $encoded = '';
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $byte = ord($value[$index]);
            if (($byte >= 0x30 && $byte <= 0x39)
                || ($byte >= 0x41 && $byte <= 0x5A)
                || ($byte >= 0x61 && $byte <= 0x7A)
                || in_array($byte, [0x2A, 0x2D, 0x2E, 0x5F], true)
            ) {
                $encoded .= $value[$index];
            } elseif ($byte === 0x20) {
                $encoded .= '+';
            } else {
                $encoded .= '%' . strtoupper(str_pad(
                    dechex($byte),
                    2,
                    '0',
                    STR_PAD_LEFT
                ));
            }
        }
        return $encoded;
    }
}

if (!function_exists('red_addon_public_mutation_form_value_decode')) {
    /**
     * Accepts only the canonical bytes emitted by URLSearchParams.
     */
    function red_addon_public_mutation_form_value_decode($value)
    {
        if (!is_string($value)
            || preg_match('/%(?![0-9A-F]{2})/', $value) === 1
        ) {
            return null;
        }
        $decoded = rawurldecode(str_replace('+', ' ', $value));
        return red_addon_public_mutation_form_value_encode($decoded) === $value
            ? $decoded
            : null;
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
                    || strpbrk($pair, '[];') !== false
                ) {
                    return red_addon_public_mutation_form_result();
                }
                [$key, $value] = explode('=', $pair, 2);
                $value = red_addon_public_mutation_form_value_decode($value);
                if ($key === ''
                    || str_contains($key, '%')
                    || str_contains($key, '+')
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
            if ($field['type'] === 'string') {
                if (!red_addon_public_mutation_form_string_valid(
                    $rawFields[$key],
                    $field['format'],
                    $field['minLength'],
                    $field['maxLength'],
                    $field['required']
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
