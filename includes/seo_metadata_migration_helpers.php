<?php
/**
 * Validation and reporting helpers for explicit SEO migration manifests.
 */

require_once __DIR__ . '/seo_metadata_helpers.php';

if (!function_exists('red_seo_import_manifest')) {
    function red_seo_import_fail($message)
    {
        throw new InvalidArgumentException((string) $message);
    }

    function red_seo_import_safe_identifier($value, $label)
    {
        $value = trim(red_seo_scalar($value));
        if ($value === '' || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/', $value) !== 1) {
            red_seo_import_fail($label . ' is invalid.');
        }
        return $value;
    }

    function red_seo_import_route_path($value)
    {
        $value = trim(red_seo_scalar($value));
        if (!red_seo_valid_root_path($value)) {
            red_seo_import_fail('A migration route path is invalid.');
        }
        $parts = parse_url($value);
        if (!is_array($parts)
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (string) ($parts['path'] ?? '') !== $value
        ) {
            red_seo_import_fail('Migration route paths must not contain query strings or fragments.');
        }
        return $value;
    }

    function red_seo_import_decisions($value)
    {
        if (!is_array($value) || !array_is_list($value)) {
            red_seo_import_fail('Each migration entry must contain a decision list.');
        }
        $allowedCategories = [
            'derived' => true,
            'skipped' => true,
            'non_importable' => true,
        ];
        $decisions = [];
        foreach ($value as $decision) {
            if (!is_array($decision)) {
                red_seo_import_fail('A migration decision is invalid.');
            }
            $category = trim(red_seo_scalar($decision['category'] ?? ''));
            $field = trim(red_seo_scalar($decision['field'] ?? ''));
            $reason = trim(red_seo_scalar($decision['reason'] ?? ''));
            if (!isset($allowedCategories[$category])
                || $field === ''
                || strlen($field) > 128
                || $reason === ''
                || strlen($reason) > 1000
            ) {
                red_seo_import_fail('A migration decision is incomplete or unsupported.');
            }
            $decisions[] = [
                'category' => $category,
                'field' => $field,
                'sourceValue' => red_seo_trim_to_bytes($decision['sourceValue'] ?? '', 4096),
                'targetValue' => red_seo_trim_to_bytes($decision['targetValue'] ?? '', 4096),
                'reason' => $reason,
            ];
        }
        return $decisions;
    }

    function red_seo_import_manifest($json)
    {
        try {
            $manifest = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            red_seo_import_fail('The SEO migration manifest is not valid JSON.');
        }
        if (!is_array($manifest)
            || ($manifest['schemaVersion'] ?? null) !== 1
            || !is_array($manifest['entries'] ?? null)
            || !array_is_list($manifest['entries'])
            || $manifest['entries'] === []
        ) {
            red_seo_import_fail('The SEO migration manifest header or entry list is invalid.');
        }

        $migrationId = red_seo_import_safe_identifier(
            $manifest['migrationId'] ?? '',
            'Migration identifier'
        );
        $siteOrigin = rtrim(trim(red_seo_scalar($manifest['siteOrigin'] ?? '')), '/');
        if (!red_seo_valid_absolute_url($siteOrigin)) {
            red_seo_import_fail('The migration site origin must be an absolute HTTP or HTTPS URL.');
        }
        $originParts = parse_url($siteOrigin);
        if (!is_array($originParts)
            || !in_array((string) ($originParts['path'] ?? ''), ['', '/'], true)
            || isset($originParts['query'])
            || isset($originParts['fragment'])
        ) {
            red_seo_import_fail('The migration site origin must not contain a path, query, or fragment.');
        }

        $entries = [];
        $owners = [];
        $routes = [];
        foreach ($manifest['entries'] as $index => $entry) {
            if (!is_array($entry)) {
                red_seo_import_fail('Migration entry ' . ($index + 1) . ' is invalid.');
            }
            $source = red_seo_import_safe_identifier(
                $entry['source'] ?? '',
                'Migration source'
            );
            $routePath = red_seo_import_route_path($entry['routePath'] ?? '');
            $owner = $entry['owner'] ?? null;
            if (!is_array($owner)) {
                red_seo_import_fail('Migration entry ' . ($index + 1) . ' has no owner.');
            }
            $ownerType = red_seo_normalize_owner_type($owner['type'] ?? '');
            $alias = trim(red_seo_scalar($owner['alias'] ?? ''));
            $language = trim(red_seo_scalar($owner['language'] ?? ''));
            $recordId = (int) ($owner['recordId'] ?? 0);
            if ($ownerType === ''
                || $alias === ''
                || strlen($alias) > 255
                || preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,19}\z/', $language) !== 1
                || $recordId < 0
            ) {
                red_seo_import_fail('Migration entry ' . ($index + 1) . ' has an invalid owner.');
            }

            $metadata = $entry['metadata'] ?? null;
            if (!is_array($metadata)) {
                red_seo_import_fail('Migration entry ' . ($index + 1) . ' has no metadata object.');
            }
            foreach (array_keys($metadata) as $field) {
                if (!array_key_exists($field, red_seo_field_definitions())) {
                    red_seo_import_fail('Migration entry ' . ($index + 1) . ' contains an unknown metadata field.');
                }
            }
            $input = red_seo_collect_input($metadata);
            if (!$input['present'] || !$input['valid'] || !red_seo_has_overrides($input['values'])) {
                red_seo_import_fail('Migration entry ' . ($index + 1) . ' has invalid or empty metadata.');
            }

            $ownerKey = implode(':', [$ownerType, strtolower($alias), strtolower($language)]);
            if (isset($owners[$ownerKey]) || isset($routes[$routePath])) {
                red_seo_import_fail('The migration manifest repeats an owner or route.');
            }
            $owners[$ownerKey] = true;
            $routes[$routePath] = true;

            $entries[] = [
                'source' => $source,
                'routePath' => $routePath,
                'owner' => [
                    'type' => $ownerType,
                    'alias' => $alias,
                    'language' => $language,
                    'recordId' => $recordId,
                ],
                'metadata' => $input['values'],
                'decisions' => red_seo_import_decisions($entry['decisions'] ?? null),
            ];
        }

        return [
            'schemaVersion' => 1,
            'migrationId' => $migrationId,
            'siteOrigin' => $siteOrigin,
            'entries' => $entries,
        ];
    }

    function red_seo_import_values_equal(array $left, array $right)
    {
        foreach (array_keys(red_seo_field_definitions()) as $field) {
            if (red_seo_scalar($left[$field] ?? '') !== red_seo_scalar($right[$field] ?? '')) {
                return false;
            }
        }
        return true;
    }

    function red_seo_import_values_additive(array $existing, array $desired)
    {
        foreach (array_keys(red_seo_field_definitions()) as $field) {
            $existingValue = red_seo_scalar($existing[$field] ?? '');
            $desiredValue = red_seo_scalar($desired[$field] ?? '');
            if ($existingValue === $desiredValue) {
                continue;
            }
            if ($existingValue === '' && $desiredValue !== '') {
                continue;
            }
            return false;
        }
        return true;
    }

    function red_seo_import_decision_counts(array $entries)
    {
        $counts = [
            'importedFields' => 0,
            'derivedValues' => 0,
            'skippedValues' => 0,
            'nonImportableValues' => 0,
        ];
        foreach ($entries as $entry) {
            foreach ($entry['metadata'] as $value) {
                if (trim(red_seo_scalar($value)) !== '') {
                    $counts['importedFields']++;
                }
            }
            foreach ($entry['decisions'] as $decision) {
                if ($decision['category'] === 'derived') {
                    $counts['derivedValues']++;
                } elseif ($decision['category'] === 'skipped') {
                    $counts['skippedValues']++;
                } elseif ($decision['category'] === 'non_importable') {
                    $counts['nonImportableValues']++;
                }
            }
        }
        return $counts;
    }
}
?>
