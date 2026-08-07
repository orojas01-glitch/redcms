<?php
/**
 * Core-owned authenticated replacement of opaque add-on secret references.
 *
 * The submitted object contains references only. Core resolves each proposed
 * reference server-locally to prove it is provisioned, then delegates the
 * complete typed configuration to the existing atomic settings writer. No
 * secret value is persisted, returned, logged, or passed to package code.
 */

require_once __DIR__ . '/addon_setting_editor_helpers.php';
require_once __DIR__ . '/addon_secret_resolution_helpers.php';

if (!function_exists('red_addon_secret_replacement_request_result')) {
    function red_addon_secret_replacement_request_result($reason)
    {
        return [
            'valid' => false,
            'packageId' => '',
            'settingReferences' => [],
            'expectedPlanSha256' => '',
            'csrfToken' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_secret_replacement_request')) {
    function red_addon_secret_replacement_request(array $post)
    {
        $result = red_addon_secret_replacement_request_result(
            'invalid_request'
        );
        $expectedKeys = [
            'ExpectedPlanSha256',
            'PackageID',
            'SecretReferences',
            'csrf_token',
        ];
        $keys = array_keys($post);
        sort($keys, SORT_STRING);
        sort($expectedKeys, SORT_STRING);
        if ($keys !== $expectedKeys
            || !is_string($post['PackageID'] ?? null)
            || !red_addon_valid_package_id($post['PackageID'])
            || !is_string($post['ExpectedPlanSha256'] ?? null)
            || !red_addon_valid_sha256($post['ExpectedPlanSha256'])
            || !is_string($post['csrf_token'] ?? null)
            || !red_addon_valid_sha256($post['csrf_token'])
            || !is_array($post['SecretReferences'])
            || $post['SecretReferences'] === []
            || array_is_list($post['SecretReferences'])
            || count($post['SecretReferences']) > 200
        ) {
            return $result;
        }

        $references = [];
        foreach ($post['SecretReferences'] as $key => $reference) {
            if (!is_string($key)
                || !red_addon_valid_permission($key)
                || !is_string($reference)
                || !red_addon_setting_string_is_valid(
                    'secret-reference',
                    $reference
                )
            ) {
                return $result;
            }
            $references[$key] = $reference;
        }
        ksort($references, SORT_STRING);
        $result['valid'] = true;
        $result['packageId'] = $post['PackageID'];
        $result['settingReferences'] = $references;
        $result['expectedPlanSha256'] = $post['ExpectedPlanSha256'];
        $result['csrfToken'] = $post['csrf_token'];
        $result['reason'] = 'valid';
        return $result;
    }
}

if (!function_exists('red_addon_secret_replacement_result')) {
    function red_addon_secret_replacement_result($reason = 'settings_unavailable')
    {
        return [
            'ok' => false,
            'status' => '',
            'reason' => (string) $reason,
            'stateSha256' => '',
        ];
    }
}

if (!function_exists('red_addon_secret_replacement_audit_record')) {
    function red_addon_secret_replacement_audit_record(
        $connection,
        $packageId,
        $packageVersion,
        $adminRecordId
    ) {
        return red_addon_install_audit_record(
            $connection,
            'addon.settings.updated',
            $packageId,
            $packageVersion,
            $adminRecordId,
            'succeeded',
            'secret_reference_replaced'
        );
    }
}

if (!function_exists('red_addon_secret_replacement_target')) {
    function red_addon_secret_replacement_target(
        $connection,
        array $package,
        $adminRecordId,
        array $settingReferences
    ) {
        $result = [
            'valid' => false,
            'configuredValues' => [],
            'plan' => [],
            'reason' => 'settings_unavailable',
        ];
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        if (!is_array($snapshot) || !is_array($manifest)) {
            $result['reason'] = 'package_invalid';
            return $result;
        }
        if (!is_array($settingReferences)
            || $settingReferences === []
            || array_is_list($settingReferences)
            || count($settingReferences) > 200
        ) {
            $result['reason'] = 'invalid_values';
            return $result;
        }
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema) || $schema === []) {
            $result['reason'] = 'settings_unavailable';
            return $result;
        }
        $definitions = [];
        foreach ($schema as $definition) {
            $key = $definition['key'] ?? null;
            if (is_string($key)) {
                $definitions[$key] = $definition;
            }
        }
        foreach ($settingReferences as $key => $reference) {
            $definition = $definitions[$key] ?? null;
            if (!is_array($definition)
                || ($definition['type'] ?? '') !== 'secret-reference'
            ) {
                $result['reason'] = 'invalid_values';
                return $result;
            }
            if (!is_string($reference)
                || !red_addon_setting_string_is_valid(
                    'secret-reference',
                    $reference
                )
            ) {
                $result['reason'] = 'invalid_values';
                return $result;
            }
        }

        $current = red_addon_setting_editor_current_configuration(
            $connection,
            $manifest,
            $snapshot['id'],
            true
        );
        if (empty($current['valid'])) {
            $result['reason'] = (string) ($current['errors'][0]
                ?? 'settings_unavailable');
            return $result;
        }
        $declarations = red_addon_secret_reference_declarations();
        $inventory = red_addon_secret_value_inventory();
        foreach ($settingReferences as $reference) {
            $resolved = red_addon_secret_resolve(
                $reference,
                $declarations,
                $inventory
            );
            if (empty($resolved['valid']) || empty($resolved['resolved'])) {
                $result['reason'] = 'secret_unavailable';
                return $result;
            }
        }

        $configured = $current['configuredValues'];
        foreach ($settingReferences as $key => $reference) {
            $configured[$key] = $reference;
        }
        $validated = red_addon_settings_validate_values(
            $manifest,
            $configured
        );
        if (empty($validated['valid'])) {
            $result['reason'] = 'secret_unconfigured';
            return $result;
        }
        $plan = red_addon_setting_write_preflight(
            $connection,
            $package,
            $adminRecordId,
            $configured
        );
        if (empty($plan['valid'])) {
            $result['reason'] = (string) ($plan['errors'][0]
                ?? 'settings_unavailable');
            return $result;
        }
        return [
            'valid' => true,
            'configuredValues' => $configured,
            'plan' => $plan,
            'reason' => 'ready',
        ];
    }
}

if (!function_exists('red_addon_secret_replacement_update')) {
    function red_addon_secret_replacement_update(
        $connection,
        array $package,
        $adminRecordId,
        array $settingReferences,
        $expectedPlanSha256
    ) {
        $failure = red_addon_secret_replacement_result();
        if (!red_addon_valid_sha256($expectedPlanSha256)) {
            $failure['reason'] = 'invalid_values';
            return $failure;
        }
        $target = red_addon_secret_replacement_target(
            $connection,
            $package,
            $adminRecordId,
            $settingReferences
        );
        if (empty($target['valid'])) {
            $failure['reason'] = (string) ($target['reason']
                ?? 'settings_unavailable');
            return $failure;
        }
        $written = red_addon_setting_write(
            $connection,
            $package,
            $adminRecordId,
            $target['configuredValues'],
            $expectedPlanSha256,
            'red_addon_secret_replacement_audit_record'
        );
        if (($written['status'] ?? '') === 'updated') {
            return [
                'ok' => true,
                'status' => 'updated',
                'reason' => '',
                'stateSha256' => (string) ($written['stateSha256'] ?? ''),
            ];
        }
        if (($written['status'] ?? '') === 'unchanged') {
            return [
                'ok' => true,
                'status' => 'unchanged',
                'reason' => '',
                'stateSha256' => (string) ($written['stateSha256'] ?? ''),
            ];
        }
        return [
            'ok' => false,
            'status' => '',
            'reason' => ($written['status'] ?? '') === 'plan_changed'
                ? 'stale_plan'
                : 'settings_unavailable',
            'stateSha256' => '',
        ];
    }
}

?>
