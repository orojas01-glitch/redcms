<?php
/**
 * Read-only readiness evidence for a cross-cutting public utility package.
 *
 * This profile is intentionally narrow: one or more internal services, exact
 * static public GET routes, package-owned migrations, and immutable public
 * CSS/JavaScript. It admits no component, administrator, mutation, secret,
 * job, adapter, admin-asset, or outbound-network surface.
 */

require_once __DIR__ . '/addon_enable_preflight_helpers.php';
require_once __DIR__ . '/addon_asset_helpers.php';

if (!function_exists('red_addon_read_only_utility_contract')) {
    function red_addon_read_only_utility_contract(array $manifest)
    {
        $result = [
            'valid' => false,
            'profileId' => 'read_only_public_utility',
            'serviceCount' => 0,
            'migrationCount' => 0,
            'routeCount' => 0,
            'publicAssetCount' => 0,
            'contractSha256' => '',
            'errors' => [],
        ];
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
        $migrations = is_array($manifest['migrations'] ?? null)
            ? $manifest['migrations']
            : [];
        $routes = is_array($manifest['routes'] ?? null)
            ? $manifest['routes']
            : [];
        $assets = is_array($manifest['assets'] ?? null)
            ? $manifest['assets']
            : [];
        $publicAssets = is_array($assets['public'] ?? null)
            ? $assets['public']
            : [];
        $adminAssets = is_array($assets['admin'] ?? null)
            ? $assets['admin']
            : [];

        $result['serviceCount'] = count($services);
        $result['migrationCount'] = count($migrations);
        $result['routeCount'] = count($routes);
        $result['publicAssetCount'] = count($publicAssets);

        if (($manifest['type'] ?? null) !== 'cross-cutting') {
            $result['errors'][] = 'package_type_invalid';
        }
        if ($result['serviceCount'] < 1 || $result['serviceCount'] > 4
            || $result['migrationCount'] < 1
            || $result['migrationCount'] > 8
            || $result['routeCount'] < 1 || $result['routeCount'] > 4
            || $result['publicAssetCount'] < 1
            || $result['publicAssetCount'] > 8
        ) {
            $result['errors'][] = 'read_only_surface_bounds_invalid';
        }
        if ($components !== []
            || $adminTools !== []
            || $adapters !== []
            || !empty($manifest['permissions'])
            || !empty($manifest['componentEditors'])
            || !empty($manifest['adminToolContracts'])
            || !empty($manifest['adminToolActionContracts'])
            || !empty($manifest['adminToolFormContracts'])
            || !empty($manifest['settings'])
            || !empty($manifest['publicMutationContracts'])
            || !empty($manifest['jobs'])
            || !empty($manifest['outboundHosts'])
            || $adminAssets !== []
        ) {
            $result['errors'][] = 'unsupported_read_only_surface';
        }

        $routeIds = [];
        foreach ($routes as $route) {
            if (!is_array($route)
                || !is_string($route['id'] ?? null)
                || ($route['scope'] ?? '') !== 'public'
                || ($route['methods'] ?? null) !== ['GET']
                || ($route['authentication'] ?? '') !== 'public'
                || ($route['csrf'] ?? '') !== 'not-applicable'
                || !is_string($route['path'] ?? null)
                || str_contains($route['path'], '{')
                || str_contains($route['path'], '}')
            ) {
                $result['errors'][] = 'read_only_route_contract_invalid';
                break;
            }
            $routeIds[] = $route['id'];
        }

        $publicPlan = red_addon_asset_plan($manifest, 'public');
        $adminPlan = red_addon_asset_plan($manifest, 'admin');
        if (!red_addon_asset_plan_is_valid($publicPlan)
            || !red_addon_asset_plan_is_valid($adminPlan)
            || ($adminPlan['assets'] ?? null) !== []
        ) {
            $result['errors'][] = 'read_only_asset_contract_invalid';
        }

        $result['errors'] = array_values(array_unique($result['errors']));
        sort($result['errors'], SORT_STRING);
        if ($result['errors'] !== []) {
            return $result;
        }
        sort($services, SORT_STRING);
        sort($routeIds, SORT_STRING);
        $migrationIds = array_values(array_map(
            static fn(array $migration): string =>
                (string) ($migration['id'] ?? ''),
            $migrations
        ));
        sort($migrationIds, SORT_STRING);
        $material = [
            'profileId' => $result['profileId'],
            'services' => $services,
            'migrations' => $migrationIds,
            'routes' => $routeIds,
            'publicAssetsSha256' => $publicPlan['planSha256'],
        ];
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            $result['errors'][] = 'contract_encoding_failed';
            return $result;
        }
        $result['contractSha256'] = hash('sha256', $encoded);
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_read_only_utility_result')) {
    function red_addon_read_only_utility_result($packageId)
    {
        return [
            'valid' => false,
            'readOnlyEvidenceReady' => false,
            'enableReady' => false,
            'activationSupported' => false,
            'stateMutation' => false,
            'runtimeLoad' => false,
            'packageExecution' => false,
            'packageId' => is_string($packageId) ? $packageId : '',
            'version' => '',
            'currentState' => '',
            'targetState' => 'enabled',
            'baseEnablementSha256' => '',
            'contractSha256' => '',
            'migrationCount' => 0,
            'routeCount' => 0,
            'publicAssetCount' => 0,
            'gates' => [
                'authorization' => 'not_checked',
                'trust' => 'not_checked',
                'registry' => 'not_checked',
                'dependencies' => 'not_checked',
                'capabilityNamespace' => 'not_checked',
                'routeNamespace' => 'not_checked',
                'migrations' => 'not_checked',
                'readOnlyContract' => 'not_checked',
                'registrarValidation' => 'not_implemented',
                'atomicEnablement' => 'not_implemented',
            ],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_read_only_utility_fingerprint')) {
    function red_addon_read_only_utility_fingerprint(array $plan)
    {
        $material = $plan;
        unset($material['planSha256'], $material['valid']);
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_read_only_utility_preflight')) {
    function red_addon_read_only_utility_preflight(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog
    ) {
        $packageId = is_string($package['id'] ?? null) ? $package['id'] : '';
        $result = red_addon_read_only_utility_result($packageId);
        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $contract = red_addon_read_only_utility_contract($manifest);
        if (empty($contract['valid'])) {
            $result['errors'][] = 'read_only_utility_contract_invalid';
            return $result;
        }
        $result['contractSha256'] = $contract['contractSha256'];
        $result['migrationCount'] = $contract['migrationCount'];
        $result['routeCount'] = $contract['routeCount'];
        $result['publicAssetCount'] = $contract['publicAssetCount'];
        $result['gates']['readOnlyContract'] = 'passed';

        $base = red_addon_enable_preflight_plan(
            $connection,
            $package,
            $actorAdminRecordId,
            $catalog
        );
        if (empty($base['valid'])
            || !red_addon_valid_sha256($base['planSha256'] ?? null)
        ) {
            $result['errors'] = is_array($base['errors'] ?? null)
                ? $base['errors']
                : ['base_enablement_invalid'];
            return $result;
        }
        $result['baseEnablementSha256'] = $base['planSha256'];
        $result['version'] = (string) ($base['version'] ?? '');
        $result['currentState'] = (string) ($base['currentState'] ?? '');
        foreach ([
            'authorization', 'trust', 'registry', 'dependencies',
            'capabilityNamespace', 'routeNamespace',
        ] as $gate) {
            $result['gates'][$gate] = (string) (
                $base['gates'][$gate] ?? 'not_checked'
            );
        }
        $result['gates']['migrations'] =
            count($base['appliedMigrations'] ?? []) === $result['migrationCount']
                ? 'passed'
                : 'blocked';

        $requiredPassed = [
            'authorization', 'trust', 'registry', 'dependencies',
            'capabilityNamespace', 'routeNamespace', 'migrations',
            'readOnlyContract',
        ];
        $result['readOnlyEvidenceReady'] = true;
        foreach ($requiredPassed as $gate) {
            if ($result['gates'][$gate] !== 'passed') {
                $result['readOnlyEvidenceReady'] = false;
            }
        }
        if (!$result['readOnlyEvidenceReady']) {
            $result['blockers'][] = [
                'code' => 'read_only_utility_evidence_incomplete',
            ];
        }
        $result['blockers'][] = ['code' => 'registrar_validation_required'];
        $result['blockers'][] = [
            'code' => 'atomic_read_only_utility_enablement_required',
        ];
        red_addon_enable_preflight_sort_records($result['blockers']);
        $result['planSha256'] =
            red_addon_read_only_utility_fingerprint($result);
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['errors'][] = 'plan_encoding_failed';
            $result['planSha256'] = '';
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_read_only_utility_preflight_is_valid')) {
    function red_addon_read_only_utility_preflight_is_valid($plan)
    {
        $expectedGateKeys = [
            'authorization',
            'trust',
            'registry',
            'dependencies',
            'capabilityNamespace',
            'routeNamespace',
            'migrations',
            'readOnlyContract',
            'registrarValidation',
            'atomicEnablement',
        ];
        if (!is_array($plan)
            || array_keys($plan) !== array_keys(
                red_addon_read_only_utility_result('')
            )
            || empty($plan['valid'])
            || !is_bool($plan['readOnlyEvidenceReady'] ?? null)
            || ($plan['enableReady'] ?? null) !== false
            || ($plan['activationSupported'] ?? null) !== false
            || ($plan['stateMutation'] ?? null) !== false
            || ($plan['runtimeLoad'] ?? null) !== false
            || ($plan['packageExecution'] ?? null) !== false
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !is_string($plan['version'] ?? null)
            || $plan['version'] === ''
            || ($plan['currentState'] ?? null) !== 'installed_disabled'
            || ($plan['targetState'] ?? null) !== 'enabled'
            || !red_addon_valid_sha256($plan['baseEnablementSha256'] ?? null)
            || !red_addon_valid_sha256($plan['contractSha256'] ?? null)
            || !is_int($plan['migrationCount'] ?? null)
            || $plan['migrationCount'] < 1
            || $plan['migrationCount'] > 8
            || !is_int($plan['routeCount'] ?? null)
            || $plan['routeCount'] < 1
            || $plan['routeCount'] > 4
            || !is_int($plan['publicAssetCount'] ?? null)
            || $plan['publicAssetCount'] < 1
            || $plan['publicAssetCount'] > 8
            || !is_array($plan['gates'] ?? null)
            || array_keys($plan['gates']) !== $expectedGateKeys
            || !is_array($plan['blockers'] ?? null)
            || ($plan['errors'] ?? null) !== []
            || !red_addon_valid_sha256($plan['planSha256'] ?? null)
            || !hash_equals(
                $plan['planSha256'],
                red_addon_read_only_utility_fingerprint($plan)
            )
        ) {
            return false;
        }
        $requiredPassed = [
            'authorization', 'trust', 'registry', 'dependencies',
            'capabilityNamespace', 'routeNamespace', 'migrations',
            'readOnlyContract',
        ];
        $expectedReady = true;
        foreach ($requiredPassed as $gate) {
            if (($plan['gates'][$gate] ?? null) !== 'passed') {
                $expectedReady = false;
            }
        }
        if ($plan['readOnlyEvidenceReady'] !== $expectedReady
            || ($plan['gates']['registrarValidation'] ?? null)
                !== 'not_implemented'
            || ($plan['gates']['atomicEnablement'] ?? null)
                !== 'not_implemented'
        ) {
            return false;
        }
        $blockerCodes = array_column($plan['blockers'], 'code');
        $expectedBlockerCodes = $expectedReady
            ? [
                'atomic_read_only_utility_enablement_required',
                'registrar_validation_required',
            ]
            : [
                'atomic_read_only_utility_enablement_required',
                'read_only_utility_evidence_incomplete',
                'registrar_validation_required',
            ];
        return $blockerCodes === $expectedBlockerCodes;
    }
}

?>
