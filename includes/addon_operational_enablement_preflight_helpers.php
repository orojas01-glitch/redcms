<?php
/**
 * Read-only readiness evidence for an operational content package.
 *
 * This boundary composes already-accepted manifest and public-mutation
 * evidence for one installed-disabled package. It does not include package
 * PHP, validate a registrar, change lifecycle state, or authorize enablement.
 */

require_once __DIR__ . '/addon_public_mutation_live_data_helpers.php';

if (!function_exists('red_addon_operational_contract')) {
    function red_addon_operational_contract(array $manifest)
    {
        $result = [
            'valid' => false,
            'profileId' => 'operational_content_package',
            'componentCount' => 0,
            'serviceCount' => 0,
            'adminToolCount' => 0,
            'adminFormCount' => 0,
            'migrationCount' => 0,
            'settingCount' => 0,
            'routeCount' => 0,
            'publicMutationCount' => 0,
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
        $editors = is_array($manifest['componentEditors'] ?? null)
            ? $manifest['componentEditors']
            : [];
        $toolContracts = is_array($manifest['adminToolContracts'] ?? null)
            ? $manifest['adminToolContracts']
            : [];
        $formContracts = is_array(
            $manifest['adminToolFormContracts'] ?? null
        ) ? $manifest['adminToolFormContracts'] : [];
        $actionContracts = is_array(
            $manifest['adminToolActionContracts'] ?? null
        ) ? $manifest['adminToolActionContracts'] : [];
        $migrations = is_array($manifest['migrations'] ?? null)
            ? $manifest['migrations']
            : [];
        $settings = is_array($manifest['settings'] ?? null)
            ? $manifest['settings']
            : [];
        $routes = is_array($manifest['routes'] ?? null)
            ? $manifest['routes']
            : [];
        $mutations = is_array($manifest['publicMutationContracts'] ?? null)
            ? $manifest['publicMutationContracts']
            : [];
        $assets = is_array($manifest['assets'] ?? null)
            ? $manifest['assets']
            : [];
        $result['componentCount'] = count($components);
        $result['serviceCount'] = count($services);
        $result['adminToolCount'] = count($adminTools);
        $result['adminFormCount'] = count($formContracts);
        $result['migrationCount'] = count($migrations);
        $result['settingCount'] = count($settings);
        $result['routeCount'] = count($routes);
        $result['publicMutationCount'] = count($mutations);

        if ($result['componentCount'] < 1 || $result['componentCount'] > 16
            || $result['serviceCount'] > 16
            || $result['adminToolCount'] < 1
            || $result['adminToolCount'] > 16
            || $result['adminFormCount'] < 1
            || $result['adminFormCount'] > 16
            || $result['migrationCount'] < 1
            || $result['migrationCount'] > 64
            || $result['settingCount'] < 1
            || $result['settingCount'] > 32
            || $result['routeCount'] < 1
            || $result['routeCount'] > 16
            || $result['publicMutationCount'] !== $result['routeCount']
        ) {
            $result['errors'][] = 'operational_surface_bounds_invalid';
        }
        if ($adapters !== []
            || $actionContracts !== []
            || !empty($manifest['jobs'])
            || !empty($manifest['outboundHosts'])
            || !empty($assets['public'])
            || !empty($assets['admin'])
        ) {
            $result['errors'][] = 'unsupported_operational_surface';
        }

        $editorComponents = [];
        foreach ($editors as $editor) {
            if (!is_array($editor)
                || !is_string($editor['component'] ?? null)
            ) {
                $result['errors'][] = 'component_editor_coverage_invalid';
                break;
            }
            $editorComponents[] = $editor['component'];
        }
        sort($components, SORT_STRING);
        sort($editorComponents, SORT_STRING);
        if ($components !== $editorComponents) {
            $result['errors'][] = 'component_editor_coverage_invalid';
        }

        $contractTools = [];
        foreach ($toolContracts as $contract) {
            if (!is_array($contract)
                || !is_string($contract['tool'] ?? null)
            ) {
                $result['errors'][] = 'admin_tool_coverage_invalid';
                break;
            }
            $contractTools[] = $contract['tool'];
        }
        sort($adminTools, SORT_STRING);
        sort($contractTools, SORT_STRING);
        if ($adminTools !== $contractTools) {
            $result['errors'][] = 'admin_tool_coverage_invalid';
        }
        foreach ($formContracts as $contract) {
            if (!is_array($contract)
                || !is_string($contract['tool'] ?? null)
                || !in_array($contract['tool'], $adminTools, true)
            ) {
                $result['errors'][] = 'admin_form_coverage_invalid';
                break;
            }
        }

        foreach ($settings as $setting) {
            if (!is_array($setting)
                || ($setting['type'] ?? '') === 'secret-reference'
                || !empty($setting['secret'])
                || !array_key_exists('default', $setting)
                || $setting['default'] !== null
            ) {
                $result['errors'][] = 'operational_setting_contract_invalid';
                break;
            }
        }

        $routeIds = [];
        foreach ($routes as $route) {
            if (!is_array($route)
                || !is_string($route['id'] ?? null)
                || ($route['scope'] ?? '') !== 'public'
                || ($route['methods'] ?? null) !== ['POST']
                || ($route['authentication'] ?? '') !== 'public'
                || ($route['csrf'] ?? '') !== 'required'
            ) {
                $result['errors'][] = 'operational_route_contract_invalid';
                break;
            }
            $routeIds[] = $route['id'];
        }
        $mutationRoutes = [];
        $mutationIds = [];
        foreach ($mutations as $mutation) {
            if (!is_array($mutation)
                || !is_string($mutation['route'] ?? null)
                || !is_string($mutation['mutation'] ?? null)
            ) {
                $result['errors'][] = 'public_mutation_coverage_invalid';
                break;
            }
            $mutationRoutes[] = $mutation['route'];
            $mutationIds[] = $mutation['mutation'];
        }
        sort($routeIds, SORT_STRING);
        sort($mutationRoutes, SORT_STRING);
        if ($routeIds !== $mutationRoutes
            || count(array_unique($mutationIds)) !== count($mutationIds)
        ) {
            $result['errors'][] = 'public_mutation_coverage_invalid';
        }
        $result['errors'] = array_values(array_unique($result['errors']));
        sort($result['errors'], SORT_STRING);
        if ($result['errors'] !== []) {
            return $result;
        }
        $material = [
            'profileId' => $result['profileId'],
            'components' => $components,
            'services' => $services,
            'adminTools' => $adminTools,
            'editorComponents' => $editorComponents,
            'contractTools' => $contractTools,
            'adminForms' => array_values(array_map(
                static fn(array $contract): string =>
                    (string) ($contract['form'] ?? ''),
                $formContracts
            )),
            'migrations' => array_values(array_map(
                static fn(array $migration): string =>
                    (string) ($migration['id'] ?? ''),
                $migrations
            )),
            'settings' => array_values(array_map(
                static fn(array $setting): string =>
                    (string) ($setting['key'] ?? ''),
                $settings
            )),
            'routes' => $routeIds,
            'mutations' => $mutationIds,
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

if (!function_exists('red_addon_operational_enablement_result')) {
    function red_addon_operational_enablement_result($packageId)
    {
        return [
            'valid' => false,
            'operationalEvidenceReady' => false,
            'enableReady' => false,
            'activationSupported' => false,
            'stateMutation' => false,
            'runtimeLoad' => false,
            'packageExecution' => false,
            'databaseSha256' => '',
            'packageId' => is_string($packageId) ? $packageId : '',
            'version' => '',
            'currentState' => '',
            'targetState' => 'enabled',
            'baseEnablementSha256' => '',
            'contractSha256' => '',
            'settingsStateSha256' => '',
            'publicMutationEvidenceSha256' => '',
            'migrationCount' => 0,
            'settingCount' => 0,
            'configuredSettingCount' => 0,
            'publicMutationCount' => 0,
            'readyPublicMutationCount' => 0,
            'gates' => [
                'authorization' => 'not_checked',
                'trust' => 'not_checked',
                'registry' => 'not_checked',
                'dependencies' => 'not_checked',
                'capabilityNamespace' => 'not_checked',
                'routeNamespace' => 'not_checked',
                'migrations' => 'not_checked',
                'operationalContract' => 'not_checked',
                'settingsConfiguration' => 'not_checked',
                'publicMutations' => 'not_checked',
                'registrarValidation' => 'not_implemented',
                'atomicEnablement' => 'not_implemented',
            ],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_operational_enablement_fingerprint')) {
    function red_addon_operational_enablement_fingerprint(array $plan)
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

if (!function_exists('red_addon_operational_enablement_preflight')) {
    function red_addon_operational_enablement_preflight(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog
    ) {
        $packageId = is_string($package['id'] ?? null) ? $package['id'] : '';
        $result = red_addon_operational_enablement_result($packageId);
        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $contract = red_addon_operational_contract($manifest);
        if (empty($contract['valid'])) {
            $result['errors'][] = 'operational_contract_invalid';
            return $result;
        }
        $result['contractSha256'] = $contract['contractSha256'];
        $result['publicMutationCount'] = $contract['publicMutationCount'];
        $result['settingCount'] = $contract['settingCount'];
        $result['migrationCount'] = $contract['migrationCount'];
        $result['gates']['operationalContract'] = 'passed';

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

        $evidence = [];
        $configuredSettingCount = null;
        $settingsStateSha256 = null;
        foreach ($manifest['publicMutationContracts'] as $mutation) {
            $plan = red_addon_public_mutation_live_data_preflight(
                $connection,
                $package,
                $actorAdminRecordId,
                $catalog,
                $mutation['route'],
                $mutation['mutation']
            );
            if (empty($plan['valid'])
                || !red_addon_public_mutation_live_data_preflight_is_valid(
                    $plan
                )
            ) {
                $result['errors'][] = 'public_mutation_evidence_invalid';
                return $result;
            }
            if ($result['databaseSha256'] === '') {
                $result['databaseSha256'] = $plan['databaseSha256'];
            } elseif (!hash_equals(
                $result['databaseSha256'],
                (string) $plan['databaseSha256']
            )) {
                $result['errors'][] = 'database_evidence_inconsistent';
                return $result;
            }
            if ($configuredSettingCount === null) {
                $configuredSettingCount = $plan['configuredSettingCount'];
                $settingsStateSha256 = $plan['settingsStateSha256'];
            } elseif ($configuredSettingCount !== $plan['configuredSettingCount']
                || !hash_equals(
                    (string) $settingsStateSha256,
                    (string) $plan['settingsStateSha256']
                )
            ) {
                $result['errors'][] = 'settings_evidence_inconsistent';
                return $result;
            }
            if (!empty($plan['dataEvidenceReady'])
                && $plan['secretSettingCount'] === 0
            ) {
                $result['readyPublicMutationCount']++;
            }
            $evidence[] = [
                'route' => $mutation['route'],
                'mutation' => $mutation['mutation'],
                'planSha256' => $plan['planSha256'],
            ];
        }
        $result['configuredSettingCount'] = (int) $configuredSettingCount;
        $result['settingsStateSha256'] = (string) $settingsStateSha256;
        $encodedEvidence = json_encode(
            $evidence,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encodedEvidence)) {
            $result['errors'][] = 'evidence_encoding_failed';
            return $result;
        }
        $result['publicMutationEvidenceSha256'] = hash(
            'sha256',
            $encodedEvidence
        );
        $result['gates']['settingsConfiguration'] =
            $result['configuredSettingCount'] === $result['settingCount']
                ? 'passed'
                : 'blocked';
        $result['gates']['publicMutations'] =
            $result['readyPublicMutationCount']
                === $result['publicMutationCount']
                    ? 'passed'
                    : 'blocked';
        $passed = [
            'authorization', 'trust', 'registry', 'dependencies',
            'capabilityNamespace', 'routeNamespace', 'migrations',
            'operationalContract', 'settingsConfiguration', 'publicMutations',
        ];
        $result['operationalEvidenceReady'] = true;
        foreach ($passed as $gate) {
            if ($result['gates'][$gate] !== 'passed') {
                $result['operationalEvidenceReady'] = false;
            }
        }
        if (!$result['operationalEvidenceReady']) {
            $result['blockers'][] = [
                'code' => 'operational_evidence_incomplete',
            ];
        }
        $result['blockers'][] = ['code' => 'registrar_validation_required'];
        $result['blockers'][] = [
            'code' => 'atomic_operational_enablement_required',
        ];
        red_addon_enable_preflight_sort_records($result['blockers']);
        $result['planSha256'] =
            red_addon_operational_enablement_fingerprint($result);
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['errors'][] = 'plan_encoding_failed';
            $result['planSha256'] = '';
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_operational_enablement_preflight_is_valid')) {
    function red_addon_operational_enablement_preflight_is_valid($plan)
    {
        $expectedGateKeys = [
            'authorization',
            'trust',
            'registry',
            'dependencies',
            'capabilityNamespace',
            'routeNamespace',
            'migrations',
            'operationalContract',
            'settingsConfiguration',
            'publicMutations',
            'registrarValidation',
            'atomicEnablement',
        ];
        if (!is_array($plan)
            || array_keys($plan) !== array_keys(
                red_addon_operational_enablement_result('')
            )
            || empty($plan['valid'])
            || !is_bool($plan['operationalEvidenceReady'] ?? null)
            || ($plan['enableReady'] ?? null) !== false
            || ($plan['activationSupported'] ?? null) !== false
            || ($plan['stateMutation'] ?? null) !== false
            || ($plan['runtimeLoad'] ?? null) !== false
            || ($plan['packageExecution'] ?? null) !== false
            || !red_addon_valid_sha256($plan['databaseSha256'] ?? null)
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !is_string($plan['version'] ?? null)
            || $plan['version'] === ''
            || ($plan['currentState'] ?? null) !== 'installed_disabled'
            || ($plan['targetState'] ?? null) !== 'enabled'
            || !red_addon_valid_sha256($plan['baseEnablementSha256'] ?? null)
            || !red_addon_valid_sha256($plan['contractSha256'] ?? null)
            || !red_addon_valid_sha256($plan['settingsStateSha256'] ?? null)
            || !red_addon_valid_sha256(
                $plan['publicMutationEvidenceSha256'] ?? null
            )
            || !is_int($plan['migrationCount'] ?? null)
            || $plan['migrationCount'] < 1
            || $plan['migrationCount'] > 64
            || !is_int($plan['settingCount'] ?? null)
            || $plan['settingCount'] < 1
            || $plan['settingCount'] > 32
            || !is_int($plan['configuredSettingCount'] ?? null)
            || $plan['configuredSettingCount'] < 0
            || $plan['configuredSettingCount'] > $plan['settingCount']
            || !is_int($plan['publicMutationCount'] ?? null)
            || $plan['publicMutationCount'] < 1
            || $plan['publicMutationCount'] > 16
            || !is_int($plan['readyPublicMutationCount'] ?? null)
            || $plan['readyPublicMutationCount'] < 0
            || $plan['readyPublicMutationCount']
                > $plan['publicMutationCount']
            || !is_array($plan['gates'] ?? null)
            || !is_array($plan['blockers'] ?? null)
            || ($plan['errors'] ?? null) !== []
            || !red_addon_valid_sha256($plan['planSha256'] ?? null)
            || !hash_equals(
                $plan['planSha256'],
                red_addon_operational_enablement_fingerprint($plan)
            )
        ) {
            return false;
        }
        if (array_keys($plan['gates']) !== $expectedGateKeys
            || $plan['gates']['registrarValidation'] !== 'not_implemented'
            || $plan['gates']['atomicEnablement'] !== 'not_implemented'
        ) {
            return false;
        }
        $passedGates = [
            'authorization', 'trust', 'registry', 'dependencies',
            'capabilityNamespace', 'routeNamespace', 'migrations',
            'operationalContract', 'settingsConfiguration', 'publicMutations',
        ];
        $expectedReady = true;
        foreach ($passedGates as $gate) {
            if ($plan['gates'][$gate] !== 'passed') {
                $expectedReady = false;
            }
        }
        if ($plan['operationalEvidenceReady'] !== $expectedReady) {
            return false;
        }
        $blockerCodes = array_column($plan['blockers'], 'code');
        $expectedBlockerCodes = $expectedReady
            ? [
                'atomic_operational_enablement_required',
                'registrar_validation_required',
            ]
            : [
                'atomic_operational_enablement_required',
                'operational_evidence_incomplete',
                'registrar_validation_required',
            ];
        if ($blockerCodes !== $expectedBlockerCodes) {
            return false;
        }
        if ($expectedReady
            && ($plan['configuredSettingCount'] !== $plan['settingCount']
                || $plan['readyPublicMutationCount']
                    !== $plan['publicMutationCount'])
        ) {
            return false;
        }
        return true;
    }
}
