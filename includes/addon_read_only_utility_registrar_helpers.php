<?php
/**
 * Exact runtime-registration evidence for the read-only public utility profile.
 */

require_once __DIR__ . '/addon_read_only_utility_enablement_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';

if (!function_exists('red_addon_read_only_utility_expected_registrations')) {
    function red_addon_read_only_utility_expected_registrations(
        array $manifest
    ) {
        $contract = red_addon_read_only_utility_contract($manifest);
        if (empty($contract['valid'])) {
            return null;
        }
        $registry = new RED_Addon_Runtime_Registry(
            (string) $manifest['id'],
            $manifest
        );
        $expected = $registry->snapshot()['registrations'] ?? null;
        if (!is_array($expected)) {
            return null;
        }
        $expected['services'] = array_values($manifest['provides']['services']);
        $expected['routes'] = array_values(array_map(
            static fn(array $route): string => (string) $route['id'],
            $manifest['routes']
        ));
        foreach ($expected as &$ids) {
            sort($ids, SORT_STRING);
        }
        unset($ids);
        return $expected;
    }
}

if (!function_exists('red_addon_read_only_utility_registrar_evidence')) {
    function red_addon_read_only_utility_registrar_evidence(
        $registry,
        array $manifest
    ) {
        $result = [
            'valid' => false,
            'profileId' => 'read_only_public_utility',
            'registrationCount' => 0,
            'registrationSha256' => '',
            'errors' => [],
        ];
        $expected = red_addon_read_only_utility_expected_registrations(
            $manifest
        );
        if (!$registry instanceof RED_Addon_Runtime_Registry
            || !is_array($expected)
            || $registry->manifest() !== $manifest
        ) {
            $result['errors'][] = 'read_only_utility_registrar_invalid';
            return $result;
        }
        $snapshot = $registry->snapshot();
        $registrations = is_array($snapshot['registrations'] ?? null)
            ? $snapshot['registrations']
            : [];
        if (array_keys($registrations) !== array_keys($expected)
            || $registrations !== $expected
        ) {
            $result['errors'][] =
                'read_only_utility_registration_shape_invalid';
            return $result;
        }
        foreach ($registrations as $ids) {
            $result['registrationCount'] += count($ids);
        }
        $encoded = json_encode(
            [
                'profileId' => $result['profileId'],
                'contractSha256' => red_addon_read_only_utility_contract(
                    $manifest
                )['contractSha256'],
                'registrations' => $registrations,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            $result['errors'][] = 'registration_encoding_failed';
            return $result;
        }
        $result['registrationSha256'] = hash('sha256', $encoded);
        $result['valid'] = true;
        return $result;
    }
}

?>
