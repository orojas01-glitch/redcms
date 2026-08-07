<?php
/**
 * Core-internal server-local add-on secret-reference resolution.
 *
 * Secret values may come only from the ignored per-server configuration file
 * or an operating-system environment JSON object. The configured opaque
 * reference inventory remains a separate allowlist. This helper never reads a
 * request array, database, package file, or package PHP, and callers must not
 * serialize, log, or render its resolved value.
 */

require_once __DIR__ . '/addon_secret_availability_helpers.php';

if (!function_exists('red_addon_secret_resolution_result')) {
    function red_addon_secret_resolution_result($reason = 'secret_unavailable')
    {
        return [
            'valid' => false,
            'resolved' => false,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_secret_value_inventory_result')) {
    function red_addon_secret_value_inventory_result($reason = 'inventory_invalid')
    {
        return [
            'valid' => false,
            'values' => [],
            'valueCount' => 0,
            'totalBytes' => 0,
            'errors' => [(string) $reason],
        ];
    }
}

if (!function_exists('red_addon_secret_environment_values_json')) {
    function red_addon_secret_environment_values_json()
    {
        $value = getenv('RED_ADDON_SECRET_VALUES_JSON');
        return is_string($value) ? $value : '';
    }
}

if (!function_exists('red_addon_secret_validate_value_map')) {
    function red_addon_secret_validate_value_map($map)
    {
        $result = red_addon_secret_value_inventory_result();
        if (!is_array($map)
            || (array_is_list($map) && $map !== [])
            || count($map) > 200
        ) {
            return $result;
        }

        $totalBytes = 0;
        foreach ($map as $reference => $value) {
            if (!is_string($reference)
                || !red_addon_setting_string_is_valid(
                    'secret-reference',
                    $reference
                )
                || !is_string($value)
                || $value === ''
                || strlen($value) > 8192
                || strpos($value, "\0") !== false
            ) {
                return $result;
            }
            $totalBytes += strlen($value);
            if ($totalBytes > 1048576) {
                return $result;
            }
        }

        ksort($map, SORT_STRING);
        $result['valid'] = true;
        $result['values'] = $map;
        $result['valueCount'] = count($map);
        $result['totalBytes'] = $totalBytes;
        $result['errors'] = [];
        return $result;
    }
}

if (!function_exists('red_addon_secret_decode_environment_values')) {
    function red_addon_secret_decode_environment_values($json)
    {
        if (!is_string($json) || $json === '') {
            return [
                'valid' => true,
                'values' => [],
                'errors' => [],
            ];
        }
        $trimmed = trim($json);
        if ($trimmed === ''
            || $trimmed[0] !== '{'
            || substr($trimmed, -1) !== '}'
        ) {
            return [
                'valid' => false,
                'values' => [],
                'errors' => ['environment_json_invalid'],
            ];
        }
        try {
            $decoded = json_decode(
                $json,
                true,
                8,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return [
                'valid' => false,
                'values' => [],
                'errors' => ['environment_json_invalid'],
            ];
        }
        $validated = red_addon_secret_validate_value_map($decoded);
        return [
            'valid' => !empty($validated['valid']),
            'values' => $validated['values'],
            'errors' => $validated['errors'],
        ];
    }
}

if (!function_exists('red_addon_secret_value_inventory')) {
    function red_addon_secret_value_inventory(
        $localValues = null,
        $environmentJson = null
    ) {
        $result = red_addon_secret_value_inventory_result();
        if ($localValues === null) {
            $localConfig = red_local_config_values();
            $localValues = array_key_exists(
                'ADDON_SECRET_VALUES',
                $localConfig
            ) ? $localConfig['ADDON_SECRET_VALUES'] : [];
        }
        if ($environmentJson === null) {
            $environmentJson = red_addon_secret_environment_values_json();
        }
        $local = red_addon_secret_validate_value_map($localValues);
        if (empty($local['valid'])) {
            return $result;
        }
        $environment = red_addon_secret_decode_environment_values(
            $environmentJson
        );
        if (empty($environment['valid'])) {
            $result['errors'] = $environment['errors'];
            return $result;
        }

        $values = $local['values'];
        foreach ($environment['values'] as $reference => $value) {
            if (array_key_exists($reference, $values)
                && !hash_equals($values[$reference], $value)
            ) {
                $result['errors'] = ['duplicate_value_conflict'];
                return $result;
            }
            $values[$reference] = $value;
        }
        $merged = red_addon_secret_validate_value_map($values);
        if (empty($merged['valid'])) {
            return $result;
        }
        return $merged;
    }
}

if (!function_exists('red_addon_secret_resolve')) {
    /**
     * Resolve one declared opaque reference for core-internal use only.
     *
     * The resolved value is returned only through the final by-reference
     * argument. It must never reach a response, audit detail, log, browser,
     * package manifest, or rendered administrator context.
     */
    function red_addon_secret_resolve(
        $reference,
        $declarations = null,
        $inventory = null,
        &$resolvedValue = null
    ) {
        $result = red_addon_secret_resolution_result();
        $resolvedValue = null;
        if (!is_string($reference)
            || !red_addon_setting_string_is_valid(
                'secret-reference',
                $reference
            )
        ) {
            $result['reason'] = 'reference_invalid';
            return $result;
        }
        if ($declarations === null) {
            $declarations = red_addon_secret_reference_declarations();
        }
        if (!is_array($declarations)
            || empty($declarations['valid'])
            || !is_array($declarations['references'] ?? null)
            || !in_array($reference, $declarations['references'], true)
        ) {
            $result['reason'] = 'reference_not_declared';
            return $result;
        }
        if ($inventory === null) {
            $inventory = red_addon_secret_value_inventory();
        }
        if (!is_array($inventory)
            || empty($inventory['valid'])
            || !is_array($inventory['values'] ?? null)
            || !array_key_exists($reference, $inventory['values'])
        ) {
            $result['reason'] = 'secret_unavailable';
            return $result;
        }
        $value = $inventory['values'][$reference];
        if (!is_string($value) || $value === '') {
            $result['reason'] = 'secret_unavailable';
            return $result;
        }
        $resolvedValue = $value;
        return [
            'valid' => true,
            'resolved' => true,
            'reason' => 'resolved',
        ];
    }
}

?>
