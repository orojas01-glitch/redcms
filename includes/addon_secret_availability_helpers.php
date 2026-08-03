<?php
/**
 * Non-executing add-on secret-reference availability evidence.
 *
 * Server-local configuration declares only opaque config: references that the
 * operator has provisioned. Core never reads or returns the referenced secret
 * value through this boundary.
 */

require_once __DIR__ . '/addon_setting_helpers.php';
require_once __DIR__ . '/runtime_config_helpers.php';

if (!function_exists('red_addon_secret_declaration_result')) {
    function red_addon_secret_declaration_result()
    {
        return [
            'valid' => false,
            'references' => [],
            'declarationSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_secret_environment_declaration')) {
    function red_addon_secret_environment_declaration()
    {
        foreach (['RED_ADDON_SECRET_REFERENCES'] as $environmentKey) {
            $value = getenv($environmentKey);
            if ($value !== false) {
                return $value;
            }
            if (array_key_exists($environmentKey, $_ENV)) {
                return $_ENV[$environmentKey];
            }
            if (array_key_exists($environmentKey, $_SERVER)) {
                return $_SERVER[$environmentKey];
            }
        }
        return '';
    }
}

if (!function_exists('red_addon_secret_reference_declarations')) {
    function red_addon_secret_reference_declarations(
        $localReferences = null,
        $environmentDeclaration = null
    ) {
        $result = red_addon_secret_declaration_result();
        if ($localReferences === null) {
            $localConfig = red_local_config_values();
            $localReferences = array_key_exists(
                'ADDON_SECRET_REFERENCES',
                $localConfig
            )
                ? $localConfig['ADDON_SECRET_REFERENCES']
                : [];
        }
        if ($environmentDeclaration === null) {
            $environmentDeclaration = red_addon_secret_environment_declaration();
        }
        if (!is_array($localReferences)
            || !array_is_list($localReferences)
            || !is_string($environmentDeclaration)
        ) {
            $result['errors'][] = 'declaration_invalid';
            return $result;
        }

        $environmentReferences = [];
        if ($environmentDeclaration !== '') {
            $environmentReferences = explode(',', $environmentDeclaration);
        }
        if (count($localReferences) + count($environmentReferences) > 200) {
            $result['errors'][] = 'declaration_too_large';
            return $result;
        }

        $references = [];
        foreach (array_merge($localReferences, $environmentReferences) as $reference) {
            if (!red_addon_setting_string_is_valid(
                'secret-reference',
                $reference
            )) {
                $result['errors'][] = 'declaration_invalid';
                return $result;
            }
            $references[$reference] = true;
        }
        $references = array_keys($references);
        sort($references, SORT_STRING);

        $encoded = json_encode(
            $references,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            $result['errors'][] = 'declaration_invalid';
            return $result;
        }
        $result['valid'] = true;
        $result['references'] = $references;
        $result['declarationSha256'] = hash('sha256', $encoded);
        return $result;
    }
}

if (!function_exists('red_addon_secret_availability_result')) {
    function red_addon_secret_availability_result($packageId)
    {
        return [
            'valid' => false,
            'available' => false,
            'packageId' => is_string($packageId) ? $packageId : '',
            'secretSettingCount' => 0,
            'availableCount' => 0,
            'missing' => [],
            'configurationSha256' => '',
            'declarationSha256' => '',
            'evidenceSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_secret_availability_evidence')) {
    function red_addon_secret_availability_evidence(
        array $manifest,
        $configuredValues,
        $packageId,
        $declarations = null
    ) {
        $result = red_addon_secret_availability_result($packageId);
        if (!is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || ($manifest['id'] ?? null) !== $packageId
        ) {
            $result['errors'][] = 'package_invalid';
            return $result;
        }

        $validated = red_addon_settings_validate_values(
            $manifest,
            $configuredValues
        );
        if (empty($validated['valid'])) {
            $result['errors'][] = 'configuration_invalid';
            return $result;
        }
        if ($declarations === null) {
            $declarations = red_addon_secret_reference_declarations();
        }
        if (!is_array($declarations)
            || empty($declarations['valid'])
            || !is_array($declarations['references'] ?? null)
            || !is_string($declarations['declarationSha256'] ?? null)
            || preg_match(
                '/\A[a-f0-9]{64}\z/',
                $declarations['declarationSha256']
            ) !== 1
        ) {
            $result['errors'][] = 'declaration_invalid';
            return $result;
        }
        $canonicalDeclarations = red_addon_secret_reference_declarations(
            $declarations['references'],
            ''
        );
        if (empty($canonicalDeclarations['valid'])
            || !hash_equals(
                $canonicalDeclarations['declarationSha256'],
                $declarations['declarationSha256']
            )
        ) {
            $result['errors'][] = 'declaration_invalid';
            return $result;
        }

        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema)) {
            $result['errors'][] = 'schema_unavailable';
            return $result;
        }
        $declaredReferences = array_fill_keys(
            $canonicalDeclarations['references'],
            true
        );
        $configurationMaterial = [];
        foreach ($validated['values'] as $key => $value) {
            $configurationMaterial[] = [
                'key' => $key,
                'type' => 'value',
                'value' => $value,
            ];
        }
        foreach ($validated['secretReferences'] as $key => $reference) {
            $configurationMaterial[] = [
                'key' => $key,
                'type' => 'secret-reference',
                'value' => $reference,
            ];
        }
        usort(
            $configurationMaterial,
            static function (array $left, array $right): int {
                return strcmp($left['key'], $right['key']);
            }
        );
        $configurationJson = json_encode(
            $configurationMaterial,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($configurationJson)) {
            $result['errors'][] = 'configuration_invalid';
            return $result;
        }
        $result['configurationSha256'] = hash(
            'sha256',
            $configurationJson
        );
        $result['declarationSha256'] = $declarations['declarationSha256'];

        $evidenceMaterial = [];
        foreach ($schema as $definition) {
            if (($definition['secret'] ?? false) !== true) {
                continue;
            }
            $settingKey = $definition['key'];
            $reference = $validated['secretReferences'][$settingKey] ?? null;
            if (!is_string($reference)) {
                $result['errors'][] = 'configuration_invalid';
                return $result;
            }
            $available = isset($declaredReferences[$reference]);
            $result['secretSettingCount']++;
            if ($available) {
                $result['availableCount']++;
            } else {
                $result['missing'][] = $settingKey;
            }
            $evidenceMaterial[] = [
                'setting' => $settingKey,
                'referenceSha256' => hash('sha256', $reference),
                'available' => $available,
            ];
        }
        sort($result['missing'], SORT_STRING);
        $evidenceJson = json_encode(
            [
                'packageId' => $packageId,
                'configurationSha256' => $result['configurationSha256'],
                'declarationSha256' => $result['declarationSha256'],
                'secrets' => $evidenceMaterial,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($evidenceJson)) {
            $result['errors'][] = 'evidence_unavailable';
            return $result;
        }
        $result['valid'] = true;
        $result['available'] = $result['missing'] === [];
        $result['evidenceSha256'] = hash('sha256', $evidenceJson);
        return $result;
    }
}

?>
