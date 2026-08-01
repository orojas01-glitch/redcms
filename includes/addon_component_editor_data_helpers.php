<?php
/**
 * Bounded package-owned value loading for add-on component editors.
 *
 * Only an already-enabled, trust-validated package may supply the loader. Core
 * requires the exact view permission and persisted placement/runtime owner,
 * contains loader output and failures, and revalidates every returned value
 * against the manifest schema. This helper exposes no endpoint and performs no
 * core write; the trusted first-party loader contract is read-only.
 */

require_once __DIR__ . '/addon_component_editor_authorization_helpers.php';
require_once __DIR__ . '/addon_component_persistence_helpers.php';

if (!function_exists('red_addon_component_editor_data_result')) {
    function red_addon_component_editor_data_result(
        $adminRecordId,
        $contentRecordId,
        $componentId,
        $reason
    ) {
        return [
            'loaded' => false,
            'actorRecordId' => is_int($adminRecordId)
                ? $adminRecordId
                : 0,
            'contentRecordId' => is_int($contentRecordId)
                ? $contentRecordId
                : 0,
            'component' => is_string($componentId)
                && red_addon_valid_capability($componentId)
                    ? $componentId
                    : '',
            'package' => '',
            'permission' => '',
            'values' => [],
            'stateHash' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_component_editor_data_hash')) {
    function red_addon_component_editor_data_hash(
        $packageId,
        $componentId,
        $contentRecordId,
        array $values
    ) {
        if (!is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || !is_string($componentId)
            || !red_addon_valid_capability($componentId)
            || !is_int($contentRecordId)
            || $contentRecordId < 1
        ) {
            return '';
        }
        $json = json_encode(
            [
                'schema' => 1,
                'package' => $packageId,
                'component' => $componentId,
                'contentRecordId' => (string) $contentRecordId,
                'values' => $values,
            ],
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($json) && $json !== ''
            ? hash('sha256', $json)
            : '';
    }
}

if (!function_exists('red_addon_component_editor_load_values')) {
    function red_addon_component_editor_load_values(
        $connection,
        array $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
    ) {
        $adminRecordId = filter_var(
            $adminRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $contentRecordId = filter_var(
            $contentRecordId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = red_addon_component_editor_data_result(
            $adminRecordId === false ? 0 : $adminRecordId,
            $contentRecordId === false ? 0 : $contentRecordId,
            $componentId,
            $adminRecordId === false
                ? 'invalid_actor'
                : ($contentRecordId === false
                    ? 'invalid_content_record'
                    : 'schema_unavailable')
        );
        if ($adminRecordId === false || $contentRecordId === false) {
            return $result;
        }

        $manifestPackageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        if (!red_addon_valid_package_id($manifestPackageId)
            || !is_array(
                red_addon_component_editor_schema($manifest, $componentId)
            )
        ) {
            return $result;
        }

        $authorization = red_addon_component_editor_permission_decision(
            $connection,
            $manifest,
            $componentId,
            'view',
            $adminRecordId
        );
        $result['permission'] = is_string(
            $authorization['permission'] ?? null
        ) ? $authorization['permission'] : '';
        if (empty($authorization['authorized'])) {
            $result['reason'] = (string) (
                $authorization['reason'] ?? 'permission_denied'
            );
            return $result;
        }

        $binding = red_addon_component_persistence_binding(
            $connection,
            $contentRecordId,
            $componentId
        );
        if (!is_array($binding)
            || !is_string($binding['package'] ?? null)
            || !hash_equals($manifestPackageId, $binding['package'])
        ) {
            $result['reason'] = 'binding_unavailable';
            return $result;
        }
        $result['package'] = $binding['package'];

        $runtimeManifest = red_addon_runtime_manifest($binding['package']);
        if (!is_array($runtimeManifest) || $runtimeManifest !== $manifest) {
            $result['reason'] = 'manifest_mismatch';
            return $result;
        }

        $loaderOwner = red_addon_runtime_owner(
            'componentDataLoaders',
            $componentId
        );
        $loader = red_addon_runtime_handler(
            'componentDataLoaders',
            $componentId
        );
        if (!is_string($loaderOwner)
            || !hash_equals($binding['package'], $loaderOwner)
            || !is_callable($loader)
        ) {
            $result['reason'] = 'loader_unavailable';
            return $result;
        }

        $bufferLevel = ob_get_level();
        $values = null;
        $emitted = '';
        try {
            ob_start();
            $values = $loader(
                $connection,
                [
                    'component' => $componentId,
                    'contentRecordId' => $contentRecordId,
                ]
            );
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on component data loader altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log(
                'RED-CMS add-on component data loading failed: '
                    . $componentId
            );
            $result['reason'] = 'loader_failed';
            return $result;
        }
        if ($emitted !== '') {
            $result['reason'] = 'loader_failed';
            return $result;
        }

        $validated = red_addon_component_editor_validate_values(
            $manifest,
            $componentId,
            $values
        );
        if (empty($validated['valid'])
            || !is_array($validated['values'] ?? null)
        ) {
            $result['reason'] = 'invalid_values';
            return $result;
        }
        $stateHash = red_addon_component_editor_data_hash(
            $binding['package'],
            $componentId,
            $contentRecordId,
            $validated['values']
        );
        if ($stateHash === '') {
            $result['reason'] = 'invalid_values';
            return $result;
        }

        $result['loaded'] = true;
        $result['values'] = $validated['values'];
        $result['stateHash'] = $stateHash;
        $result['reason'] = 'loaded';
        return $result;
    }
}
