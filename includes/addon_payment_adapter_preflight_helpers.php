<?php
/**
 * Data-only P3A-1 profile for one future Store Lite Stripe Checkout adapter.
 *
 * This boundary recognizes a deliberately closed manifest surface. It does
 * not inspect a database, resolve a secret, include package PHP, register a
 * route, open a network connection, or authorize lifecycle activation.
 */

require_once __DIR__ . '/addon_manifest_helpers.php';

if (!function_exists('red_addon_payment_adapter_profile_result')) {
    function red_addon_payment_adapter_profile_result($packageId = '')
    {
        return [
            'valid' => false,
            'profileId' => 'store_lite_stripe_checkout_adapter_v1',
            'contractReady' => false,
            'activationSupported' => false,
            'stateMutation' => false,
            'runtimeLoad' => false,
            'packageExecution' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'routeExposure' => false,
            'packageId' => is_string($packageId) ? $packageId : '',
            'adapter' => '',
            'dependencyPackageId' => '',
            'serverEventRoute' => '',
            'serverEventPath' => '',
            'migrationCount' => 0,
            'ordinarySettingCount' => 0,
            'secretSettingCount' => 0,
            'outboundHost' => '',
            'contractSha256' => '',
            'blockers' => [],
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_profile_fingerprint')) {
    function red_addon_payment_adapter_profile_fingerprint(array $profile)
    {
        $material = $profile;
        unset(
            $material['valid'],
            $material['contractReady'],
            $material['contractSha256']
        );
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_payment_adapter_exact_route')) {
    function red_addon_payment_adapter_exact_route($route, $packageId)
    {
        if (!is_array($route)
            || !is_string($packageId)
            || !red_addon_valid_package_id($packageId)
        ) {
            return null;
        }
        $keys = array_keys($route);
        sort($keys, SORT_STRING);
        if ($keys !== [
            'authentication',
            'csrf',
            'id',
            'methods',
            'path',
            'scope',
        ]) {
            return null;
        }
        $parts = red_addon_package_parts($packageId);
        $routeId = is_string($route['id'] ?? null) ? $route['id'] : '';
        $path = is_string($route['path'] ?? null) ? $route['path'] : '';
        $prefix = is_array($parts)
            ? '/addons/' . $parts[0] . '/' . $parts[1]
            : '';
        if (!red_addon_valid_capability($routeId)
            || strpos($routeId, $packageId . '/') !== 0
            || ($route['scope'] ?? null) !== 'public'
            || ($route['methods'] ?? null) !== ['POST']
            || ($route['authentication'] ?? null) !== 'server-signature'
            || ($route['csrf'] ?? null) !== 'not-applicable'
            || !red_addon_valid_route_path($path)
            || strpbrk($path, '{}') !== false
            || $prefix === ''
            || strpos($path, $prefix . '/') !== 0
        ) {
            return null;
        }
        return [
            'id' => $routeId,
            'path' => $path,
            'scope' => 'public',
            'method' => 'POST',
            'authentication' => 'server-signature',
            'csrf' => 'not-applicable',
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_profile')) {
    function red_addon_payment_adapter_profile(array $manifest)
    {
        $packageId = is_string($manifest['id'] ?? null)
            ? $manifest['id']
            : '';
        $result = red_addon_payment_adapter_profile_result($packageId);
        $provides = is_array($manifest['provides'] ?? null)
            ? $manifest['provides']
            : [];
        $components = is_array($provides['components'] ?? null)
            ? $provides['components']
            : [];
        $services = is_array($provides['services'] ?? null)
            ? $provides['services']
            : [];
        $adminTools = is_array($provides['adminTools'] ?? null)
            ? $provides['adminTools']
            : [];
        $adapters = is_array($provides['adapters'] ?? null)
            ? $provides['adapters']
            : [];

        if (!red_addon_valid_package_id($packageId)) {
            $result['errors'][] = 'package_identity_invalid';
        }
        if (($manifest['type'] ?? null) !== 'adapter') {
            $result['errors'][] = 'package_type_invalid';
        }
        if ($components !== []
            || $services !== []
            || $adminTools !== []
            || count($adapters) !== 1
            || !is_string($adapters[0] ?? null)
            || !red_addon_valid_capability($adapters[0])
            || strpos($adapters[0], $packageId . '/') !== 0
        ) {
            $result['errors'][] = 'capability_surface_invalid';
        } else {
            $result['adapter'] = $adapters[0];
        }

        $assets = is_array($manifest['assets'] ?? null)
            ? $manifest['assets']
            : [];
        foreach (['componentEditors', 'adminToolContracts',
            'adminToolFormContracts', 'adminToolActionContracts',
            'publicMutationContracts'] as $optionalSurface) {
            if (!empty($manifest[$optionalSurface])) {
                $result['errors'][] = 'unsupported_profile_surface';
            }
        }
        if (!empty($manifest['permissions'])
            || !empty($manifest['jobs'])
            || !empty($assets['public'])
            || !empty($assets['admin'])
        ) {
            $result['errors'][] = 'unsupported_profile_surface';
        }

        $dependencies = is_array($manifest['dependencies'] ?? null)
            ? $manifest['dependencies']
            : [];
        $required = is_array($dependencies['required'] ?? null)
            ? $dependencies['required']
            : [];
        $optional = is_array($dependencies['optional'] ?? null)
            ? $dependencies['optional']
            : [];
        $dependency = $required[0] ?? null;
        if (count($required) !== 1
            || $optional !== []
            || !is_array($dependency)
            || ($dependency['id'] ?? null) !== 'redcms.store-lite'
            || !red_addon_version_range_valid($dependency['version'] ?? null)
        ) {
            $result['errors'][] = 'dependency_contract_invalid';
        } else {
            $result['dependencyPackageId'] = 'redcms.store-lite';
        }

        $settings = red_addon_settings_schema($manifest);
        $ordinarySettingKeys = [];
        $secretSettingKeys = [];
        if (!is_array($settings)
            || count($settings) < 2
            || count($settings) > 8
        ) {
            $result['errors'][] = 'setting_contract_invalid';
        } else {
            foreach ($settings as $setting) {
                if (!is_array($setting)
                    || !is_string($setting['key'] ?? null)
                    || array_key_exists('permission', $setting)
                ) {
                    $result['errors'][] = 'setting_contract_invalid';
                    break;
                }
                if (($setting['type'] ?? null) === 'secret-reference'
                    && ($setting['secret'] ?? null) === true
                    && !array_key_exists('default', $setting)
                ) {
                    $secretSettingKeys[] = $setting['key'];
                    continue;
                }
                if (($setting['secret'] ?? null) !== false
                    || !array_key_exists('default', $setting)
                    || $setting['default'] !== null
                ) {
                    $result['errors'][] = 'setting_contract_invalid';
                    break;
                }
                $ordinarySettingKeys[] = $setting['key'];
            }
            if (count($secretSettingKeys) !== 2
                || count($ordinarySettingKeys) > 6
            ) {
                $result['errors'][] = 'setting_contract_invalid';
            }
        }
        sort($ordinarySettingKeys, SORT_STRING);
        sort($secretSettingKeys, SORT_STRING);
        $result['ordinarySettingCount'] = count($ordinarySettingKeys);
        $result['secretSettingCount'] = count($secretSettingKeys);

        $migrations = is_array($manifest['migrations'] ?? null)
            ? $manifest['migrations']
            : [];
        if (count($migrations) < 1 || count($migrations) > 16) {
            $result['errors'][] = 'migration_contract_invalid';
        } else {
            foreach ($migrations as $migration) {
                if (!is_array($migration)
                    || !is_string($migration['id'] ?? null)
                    || preg_match(
                        '/\A\d{4}-\d{2}-\d{2}-[a-z0-9][a-z0-9-]*\z/',
                        $migration['id']
                    ) !== 1
                    || !is_string($migration['path'] ?? null)
                    || preg_match(
                        '/\Amigrations\/[A-Za-z0-9_.-]+\.sql\z/',
                        $migration['path']
                    ) !== 1
                    || !red_addon_valid_sha256($migration['sha256'] ?? null)
                ) {
                    $result['errors'][] = 'migration_contract_invalid';
                    break;
                }
            }
        }
        $result['migrationCount'] = count($migrations);

        $routes = is_array($manifest['routes'] ?? null)
            ? $manifest['routes']
            : [];
        $serverEventRoute = count($routes) === 1
            ? red_addon_payment_adapter_exact_route($routes[0], $packageId)
            : null;
        if (!is_array($serverEventRoute)) {
            $result['errors'][] = 'server_event_route_invalid';
        } else {
            $result['serverEventRoute'] = $serverEventRoute['id'];
            $result['serverEventPath'] = $serverEventRoute['path'];
        }

        if (($manifest['outboundHosts'] ?? null) !== ['api.stripe.com']) {
            $result['errors'][] = 'outbound_host_invalid';
        } else {
            $result['outboundHost'] = 'api.stripe.com';
        }

        $result['errors'] = array_values(array_unique($result['errors']));
        sort($result['errors'], SORT_STRING);
        if ($result['errors'] !== []) {
            return $result;
        }

        $result['blockers'] = [
            ['code' => 'atomic_payment_adapter_enablement_required'],
            ['code' => 'database_bound_adapter_preflight_required'],
            ['code' => 'registrar_validation_required'],
            ['code' => 'server_event_ingress_required'],
        ];
        $result['contractSha256'] =
            red_addon_payment_adapter_profile_fingerprint($result);
        if (!red_addon_valid_sha256($result['contractSha256'])) {
            $result['errors'][] = 'contract_encoding_failed';
            $result['contractSha256'] = '';
            return $result;
        }
        $result['contractReady'] = true;
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_payment_adapter_profile_is_valid')) {
    function red_addon_payment_adapter_profile_is_valid($profile)
    {
        if (!is_array($profile)
            || array_keys($profile) !== array_keys(
                red_addon_payment_adapter_profile_result('')
            )
            || empty($profile['valid'])
            || empty($profile['contractReady'])
            || ($profile['profileId'] ?? null)
                !== 'store_lite_stripe_checkout_adapter_v1'
            || ($profile['activationSupported'] ?? null) !== false
            || ($profile['stateMutation'] ?? null) !== false
            || ($profile['runtimeLoad'] ?? null) !== false
            || ($profile['packageExecution'] ?? null) !== false
            || ($profile['secretResolution'] ?? null) !== false
            || ($profile['networkAccess'] ?? null) !== false
            || ($profile['routeExposure'] ?? null) !== false
            || !red_addon_valid_package_id($profile['packageId'] ?? null)
            || !red_addon_valid_capability($profile['adapter'] ?? null)
            || ($profile['dependencyPackageId'] ?? null)
                !== 'redcms.store-lite'
            || !red_addon_valid_capability(
                $profile['serverEventRoute'] ?? null
            )
            || !red_addon_valid_route_path(
                $profile['serverEventPath'] ?? null
            )
            || !is_int($profile['migrationCount'] ?? null)
            || $profile['migrationCount'] < 1
            || $profile['migrationCount'] > 16
            || !is_int($profile['ordinarySettingCount'] ?? null)
            || $profile['ordinarySettingCount'] < 0
            || $profile['ordinarySettingCount'] > 6
            || ($profile['secretSettingCount'] ?? null) !== 2
            || ($profile['outboundHost'] ?? null) !== 'api.stripe.com'
            || ($profile['blockers'] ?? null) !== [
                ['code' => 'atomic_payment_adapter_enablement_required'],
                ['code' => 'database_bound_adapter_preflight_required'],
                ['code' => 'registrar_validation_required'],
                ['code' => 'server_event_ingress_required'],
            ]
            || ($profile['errors'] ?? null) !== []
            || !red_addon_valid_sha256($profile['contractSha256'] ?? null)
            || !hash_equals(
                $profile['contractSha256'],
                red_addon_payment_adapter_profile_fingerprint($profile)
            )
        ) {
            return false;
        }
        return true;
    }
}

?>
