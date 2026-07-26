<?php
/**
 * Read-only readiness planning for a future add-on enable transition.
 *
 * This helper never includes package PHP, mutates registry state, or claims
 * that runtime activation is available. It builds deterministic evidence for
 * an installed-disabled package and fails closed on authorization, trust,
 * registry, dependency, capability, or route ambiguity.
 */

require_once __DIR__ . '/addon_registry_helpers.php';

if (!function_exists('red_addon_enable_preflight_database_name')) {
    function red_addon_enable_preflight_database_name($connection)
    {
        if (!$connection) {
            return '';
        }
        try {
            $result = mysqli_query(
                $connection,
                'SELECT DATABASE() AS DatabaseName'
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return isset($row['DatabaseName']) && is_string($row['DatabaseName'])
                ? $row['DatabaseName']
                : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_enable_preflight_sort_records')) {
    function red_addon_enable_preflight_sort_records(array &$records)
    {
        usort(
            $records,
            static function ($left, $right) {
                $leftJson = json_encode(
                    $left,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
                $rightJson = json_encode(
                    $right,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
                return strcmp((string) $leftJson, (string) $rightJson);
            }
        );
    }
}

if (!function_exists('red_addon_enable_preflight_provides')) {
    function red_addon_enable_preflight_provides(array $manifest)
    {
        $provides = [];
        foreach (['components', 'services', 'adminTools', 'adapters'] as $type) {
            $values = is_array($manifest['provides'][$type] ?? null)
                ? $manifest['provides'][$type]
                : [];
            $values = array_values(array_unique(array_filter(
                $values,
                'red_addon_valid_capability'
            )));
            sort($values, SORT_STRING);
            $provides[$type] = $values;
        }
        return $provides;
    }
}

if (!function_exists('red_addon_enable_preflight_routes')) {
    function red_addon_enable_preflight_routes(array $manifest)
    {
        $routes = [];
        foreach ($manifest['routes'] ?? [] as $route) {
            if (!is_array($route)) {
                continue;
            }
            $methods = is_array($route['methods'] ?? null)
                ? array_values(array_unique(array_filter(
                    $route['methods'],
                    static function ($method) {
                        return is_string($method);
                    }
                )))
                : [];
            sort($methods, SORT_STRING);
            $routes[] = [
                'id' => isset($route['id']) && is_string($route['id'])
                    ? $route['id']
                    : '',
                'scope' => isset($route['scope']) && is_string($route['scope'])
                    ? $route['scope']
                    : '',
                'path' => isset($route['path']) && is_string($route['path'])
                    ? $route['path']
                    : '',
                'methods' => $methods,
            ];
        }
        red_addon_enable_preflight_sort_records($routes);
        return $routes;
    }
}

if (!function_exists('red_addon_enable_preflight_capability_conflicts')) {
    function red_addon_enable_preflight_capability_conflicts(
        array $targetManifest,
        array $enabledPackages
    ) {
        $conflicts = [];
        $targetProvides = red_addon_enable_preflight_provides($targetManifest);
        foreach ($enabledPackages as $packageId => $package) {
            $manifest = is_array($package['manifest'] ?? null)
                ? $package['manifest']
                : [];
            $enabledProvides = red_addon_enable_preflight_provides($manifest);
            foreach ($targetProvides as $type => $capabilities) {
                foreach (array_values(array_intersect(
                    $capabilities,
                    $enabledProvides[$type] ?? []
                )) as $capability) {
                    $conflicts[] = [
                        'code' => 'provided_capability_conflict',
                        'type' => $type,
                        'capability' => $capability,
                        'packageId' => (string) $packageId,
                    ];
                }
            }
        }
        red_addon_enable_preflight_sort_records($conflicts);
        return $conflicts;
    }
}

if (!function_exists('red_addon_enable_preflight_route_conflicts')) {
    function red_addon_enable_preflight_route_conflicts(
        array $targetManifest,
        array $enabledPackages
    ) {
        $conflicts = [];
        $targetRoutes = red_addon_enable_preflight_routes($targetManifest);
        foreach ($enabledPackages as $packageId => $package) {
            $manifest = is_array($package['manifest'] ?? null)
                ? $package['manifest']
                : [];
            foreach ($targetRoutes as $targetRoute) {
                foreach (red_addon_enable_preflight_routes($manifest) as $enabledRoute) {
                    if ($targetRoute['id'] !== ''
                        && hash_equals($targetRoute['id'], $enabledRoute['id'])
                    ) {
                        $conflicts[] = [
                            'code' => 'route_id_conflict',
                            'routeId' => $targetRoute['id'],
                            'packageId' => (string) $packageId,
                        ];
                    }

                    if ($targetRoute['scope'] === ''
                        || $targetRoute['path'] === ''
                        || !hash_equals(
                            $targetRoute['scope'],
                            $enabledRoute['scope']
                        )
                        || !hash_equals(
                            $targetRoute['path'],
                            $enabledRoute['path']
                        )
                    ) {
                        continue;
                    }
                    $methods = array_values(array_intersect(
                        $targetRoute['methods'],
                        $enabledRoute['methods']
                    ));
                    sort($methods, SORT_STRING);
                    foreach ($methods as $method) {
                        $conflicts[] = [
                            'code' => 'route_path_method_conflict',
                            'scope' => $targetRoute['scope'],
                            'path' => $targetRoute['path'],
                            'method' => $method,
                            'packageId' => (string) $packageId,
                        ];
                    }
                }
            }
        }
        red_addon_enable_preflight_sort_records($conflicts);
        return $conflicts;
    }
}

if (!function_exists('red_addon_enable_preflight_runtime_inventory')) {
    function red_addon_enable_preflight_runtime_inventory(array $manifest)
    {
        $provides = red_addon_enable_preflight_provides($manifest);
        $assets = is_array($manifest['assets'] ?? null)
            ? $manifest['assets']
            : [];
        return [
            'provides' => [
                'components' => count($provides['components']),
                'services' => count($provides['services']),
                'adminTools' => count($provides['adminTools']),
                'adapters' => count($provides['adapters']),
            ],
            'permissions' => count(
                is_array($manifest['permissions'] ?? null)
                    ? $manifest['permissions']
                    : []
            ),
            'settings' => count(
                is_array($manifest['settings'] ?? null)
                    ? $manifest['settings']
                    : []
            ),
            'routes' => count(
                is_array($manifest['routes'] ?? null)
                    ? $manifest['routes']
                    : []
            ),
            'jobs' => count(
                is_array($manifest['jobs'] ?? null)
                    ? $manifest['jobs']
                    : []
            ),
            'publicAssets' => count(
                is_array($assets['public'] ?? null)
                    ? $assets['public']
                    : []
            ),
            'adminAssets' => count(
                is_array($assets['admin'] ?? null)
                    ? $assets['admin']
                    : []
            ),
        ];
    }
}

if (!function_exists('red_addon_enable_preflight_plan')) {
    function red_addon_enable_preflight_plan(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog
    ) {
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $plan = [
            'valid' => false,
            'enableReady' => false,
            'activationSupported' => false,
            'database' => red_addon_enable_preflight_database_name($connection),
            'packageId' => isset($package['id']) && is_string($package['id'])
                ? $package['id']
                : '',
            'version' => '',
            'currentState' => '',
            'targetState' => 'enabled',
            'stateMutation' => false,
            'runtimeLoad' => false,
            'requiredDependencies' => [],
            'enabledPackages' => [],
            'appliedMigrations' => [],
            'capabilityConflicts' => [],
            'routeConflicts' => [],
            'runtimeInventory' => [],
            'gates' => [
                'authorization' => 'not_checked',
                'trust' => 'not_checked',
                'registry' => 'not_checked',
                'dependencies' => 'not_checked',
                'capabilityNamespace' => 'not_checked',
                'routeNamespace' => 'not_checked',
                'themeCompatibility' => 'not_implemented',
                'settings' => 'not_implemented',
                'liveData' => 'not_implemented',
                'runtimeRegistration' => 'unavailable',
            ],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];

        if (!red_addon_registry_storage_available($connection)
            || $plan['database'] === ''
        ) {
            $plan['errors'][] = 'registry_storage_unavailable';
            return $plan;
        }

        $actor = red_admin_addon_database_actor(
            $connection,
            $actorAdminRecordId
        );
        if (!red_addon_registry_actor_can_transition($actor, 'enable')) {
            $plan['errors'][] = 'owner_enable_capability_required';
            return $plan;
        }
        $plan['gates']['authorization'] = 'passed';

        if (empty($catalog['valid'])
            || empty($package['valid'])
        ) {
            $plan['errors'][] = 'package_trust_invalid';
            return $plan;
        }
        $snapshot = red_addon_registry_snapshot($package);
        if ($snapshot === null
            || !isset($catalog['packages'][$snapshot['id']])
            || !is_array($catalog['packages'][$snapshot['id']])
        ) {
            $plan['errors'][] = 'package_snapshot_invalid';
            return $plan;
        }
        $catalogSnapshot = red_addon_registry_snapshot(
            $catalog['packages'][$snapshot['id']]
        );
        if ($catalogSnapshot === null
            || !hash_equals(
                $snapshot['manifestSha256'],
                $catalogSnapshot['manifestSha256']
            )
            || !hash_equals(
                $snapshot['inventorySha256'],
                $catalogSnapshot['inventorySha256']
            )
        ) {
            $plan['errors'][] = 'package_catalog_mismatch';
            return $plan;
        }
        $plan['packageId'] = $snapshot['id'];
        $plan['version'] = $snapshot['version'];
        $plan['gates']['trust'] = 'passed';

        $registryCatalog = red_addon_registry_catalog_report(
            $connection,
            $catalog
        );
        if (empty($registryCatalog['valid'])) {
            $plan['errors'][] = 'registry_catalog_invalid';
            return $plan;
        }
        $targetReport = $registryCatalog['packages'][$snapshot['id']] ?? null;
        if (!is_array($targetReport)
            || ($targetReport['status'] ?? '') !== 'installed_disabled_current'
            || ($targetReport['lifecycleState'] ?? '') !== 'installed_disabled'
            || !empty($targetReport['errors'])
            || !empty($targetReport['pendingMigrations'])
        ) {
            $plan['errors'][] = 'package_not_installed_disabled_current';
            return $plan;
        }
        $plan['currentState'] = 'installed_disabled';
        $plan['gates']['registry'] = 'passed';

        $appliedMigrations = red_addon_registry_migrations(
            $connection,
            $snapshot['id']
        );
        foreach ($snapshot['migrations'] as $migrationId => $migration) {
            $applied = $appliedMigrations[$migrationId] ?? null;
            if (!is_array($applied)) {
                $plan['errors'][] = 'migration_evidence_incomplete';
                return $plan;
            }
            $plan['appliedMigrations'][] = [
                'id' => $migrationId,
                'path' => $migration['path'],
                'sha256' => $migration['sha256'],
            ];
        }
        red_addon_enable_preflight_sort_records($plan['appliedMigrations']);

        $enabledPackageResults = [];
        foreach ($registryCatalog['packages'] as $packageId => $packageReport) {
            if ($packageId === $snapshot['id']
                || !is_array($packageReport)
                || ($packageReport['lifecycleState'] ?? '') !== 'enabled'
            ) {
                continue;
            }
            if (($packageReport['status'] ?? '') !== 'enabled_runtime_unavailable'
                || !isset($catalog['packages'][$packageId])
                || !is_array($catalog['packages'][$packageId])
            ) {
                $plan['errors'][] = 'enabled_package_not_current';
                return $plan;
            }
            $enabledSnapshot = red_addon_registry_snapshot(
                $catalog['packages'][$packageId]
            );
            if ($enabledSnapshot === null) {
                $plan['errors'][] = 'enabled_package_snapshot_invalid';
                return $plan;
            }
            $enabledPackageResults[$packageId] = $catalog['packages'][$packageId];
            $plan['enabledPackages'][] = [
                'id' => $enabledSnapshot['id'],
                'version' => $enabledSnapshot['version'],
                'manifestSha256' => $enabledSnapshot['manifestSha256'],
                'inventorySha256' => $enabledSnapshot['inventorySha256'],
                'lifecycleState' => 'enabled',
            ];
        }
        red_addon_enable_preflight_sort_records($plan['enabledPackages']);
        ksort($enabledPackageResults, SORT_STRING);

        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $required = is_array($manifest['dependencies']['required'] ?? null)
            ? $manifest['dependencies']['required']
            : [];
        red_addon_enable_preflight_sort_records($required);
        foreach ($required as $dependency) {
            $dependencyId = is_array($dependency)
                && isset($dependency['id'])
                && is_string($dependency['id'])
                ? $dependency['id']
                : '';
            $versionRange = is_array($dependency)
                && isset($dependency['version'])
                && is_string($dependency['version'])
                ? $dependency['version']
                : '';
            $dependencyPackage = $catalog['packages'][$dependencyId] ?? null;
            $dependencySnapshot = is_array($dependencyPackage)
                ? red_addon_registry_snapshot($dependencyPackage)
                : null;
            $dependencyReport = $registryCatalog['packages'][$dependencyId] ?? null;
            if ($dependencySnapshot === null
                || !red_addon_version_satisfies(
                    $dependencySnapshot['version'],
                    $versionRange
                )
            ) {
                $plan['errors'][] = 'required_dependency_invalid';
                return $plan;
            }
            if (!is_array($dependencyReport)
                || ($dependencyReport['status'] ?? '')
                    !== 'enabled_runtime_unavailable'
                || ($dependencyReport['lifecycleState'] ?? '') !== 'enabled'
            ) {
                $plan['blockers'][] = [
                    'code' => 'required_dependency_not_enabled',
                    'packageId' => $dependencyId,
                    'versionRange' => $versionRange,
                ];
                continue;
            }
            $plan['requiredDependencies'][] = [
                'id' => $dependencyId,
                'versionRange' => $versionRange,
                'installedVersion' => $dependencySnapshot['version'],
                'manifestSha256' => $dependencySnapshot['manifestSha256'],
                'inventorySha256' => $dependencySnapshot['inventorySha256'],
                'lifecycleState' => 'enabled',
            ];
        }
        red_addon_enable_preflight_sort_records($plan['requiredDependencies']);

        $plan['capabilityConflicts'] =
            red_addon_enable_preflight_capability_conflicts(
                $manifest,
                $enabledPackageResults
            );
        foreach ($plan['capabilityConflicts'] as $conflict) {
            $plan['blockers'][] = $conflict;
        }
        $plan['routeConflicts'] = red_addon_enable_preflight_route_conflicts(
            $manifest,
            $enabledPackageResults
        );
        foreach ($plan['routeConflicts'] as $conflict) {
            $plan['blockers'][] = $conflict;
        }

        $dependencyBlocked = false;
        foreach ($plan['blockers'] as $blocker) {
            if (($blocker['code'] ?? '') === 'required_dependency_not_enabled') {
                $dependencyBlocked = true;
                break;
            }
        }
        $plan['gates']['dependencies'] = $dependencyBlocked
            ? 'blocked'
            : 'passed';
        $plan['gates']['capabilityNamespace'] =
            $plan['capabilityConflicts'] === [] ? 'passed' : 'blocked';
        $plan['gates']['routeNamespace'] =
            $plan['routeConflicts'] === [] ? 'passed' : 'blocked';

        $plan['runtimeInventory'] =
            red_addon_enable_preflight_runtime_inventory($manifest);
        $plan['blockers'][] = [
            'code' => 'runtime_contract_unavailable',
        ];
        red_addon_enable_preflight_sort_records($plan['blockers']);

        $planMaterial = [
            'database' => $plan['database'],
            'actorAdminRecordId' => $actorAdminRecordId,
            'package' => [
                'id' => $snapshot['id'],
                'version' => $snapshot['version'],
                'type' => $snapshot['type'],
                'manifestSha256' => $snapshot['manifestSha256'],
                'inventorySha256' => $snapshot['inventorySha256'],
            ],
            'currentState' => $plan['currentState'],
            'targetState' => $plan['targetState'],
            'appliedMigrations' => $plan['appliedMigrations'],
            'requiredDependencies' => $plan['requiredDependencies'],
            'enabledPackages' => $plan['enabledPackages'],
            'capabilityConflicts' => $plan['capabilityConflicts'],
            'routeConflicts' => $plan['routeConflicts'],
            'runtimeInventory' => $plan['runtimeInventory'],
            'gates' => $plan['gates'],
            'blockers' => $plan['blockers'],
            'stateMutation' => false,
            'runtimeLoad' => false,
        ];
        $encoded = json_encode(
            $planMaterial,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            $plan['errors'][] = 'plan_encoding_failed';
            return $plan;
        }

        $plan['planSha256'] = hash('sha256', $encoded);
        $plan['valid'] = true;
        return $plan;
    }
}

?>
