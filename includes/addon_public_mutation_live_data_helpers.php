<?php
/**
 * Read-only public-mutation live-data preflight.
 *
 * This boundary joins already-trusted, installed-disabled package evidence to
 * a declared public mutation without accepting an HTTP request or loading
 * package code. It reports only counts and fingerprints for a future richer
 * enablement review. It never issues an anonymous subject, CSRF token, or
 * idempotency key; reads a cookie or session; invokes a registrar; starts a
 * transaction; or changes lifecycle, package, setting, or audit state.
 */

require_once __DIR__ . '/addon_enable_preflight_helpers.php';
require_once __DIR__ . '/addon_public_mutation_preflight_helpers.php';
require_once __DIR__ . '/addon_secret_availability_helpers.php';
require_once __DIR__ . '/addon_setting_storage_helpers.php';
require_once __DIR__ . '/addon_public_mutation_subject_helpers.php';

if (!function_exists('red_addon_public_mutation_live_data_result')) {
    function red_addon_public_mutation_live_data_result(
        $packageId,
        $routeId,
        $mutationId
    ) {
        return [
            'valid' => false,
            'dataEvidenceReady' => false,
            'enableReady' => false,
            'activationSupported' => false,
            'requestDispatch' => false,
            'invoked' => false,
            'stateMutation' => false,
            'runtimeLoad' => false,
            'packageExecution' => false,
            'secretResolution' => false,
            'databaseSha256' => '',
            'packageId' => is_string($packageId)
                && red_addon_valid_package_id($packageId)
                    ? $packageId
                    : '',
            'version' => '',
            'route' => is_string($routeId)
                && red_addon_valid_capability($routeId)
                    ? $routeId
                    : '',
            'mutation' => is_string($mutationId)
                && red_addon_valid_capability($mutationId)
                    ? $mutationId
                    : '',
            'currentState' => '',
            'targetState' => 'enabled',
            'declarationSha256' => '',
            'enablementEvidenceSha256' => '',
            'tableEvidenceSha256' => '',
            'settingsStateSha256' => '',
            'secretAvailabilitySha256' => '',
            'migrationCount' => 0,
            'tableCount' => 0,
            'innoDbTableCount' => 0,
            'settingCount' => 0,
            'configuredSettingCount' => 0,
            'secretSettingCount' => 0,
            'availableSecretCount' => 0,
            'missingSecretSettingCount' => 0,
            'gates' => [
                'authorization' => 'not_checked',
                'trust' => 'not_checked',
                'registry' => 'not_checked',
                'dependencies' => 'not_checked',
                'capabilityNamespace' => 'not_checked',
                'routeNamespace' => 'not_checked',
                'declaration' => 'not_checked',
                'migrations' => 'not_checked',
                'packageTables' => 'not_checked',
                'settingsConfiguration' => 'not_checked',
                'secretAvailability' => 'not_checked',
                'settingsEndpoint' => 'not_implemented',
                'secretLookup' => 'not_implemented',
                'anonymousSubject' => 'not_implemented',
                'csrf' => 'not_implemented',
                'idempotency' => 'not_implemented',
                'rateLimit' => 'not_implemented',
                'transactionRunner' => 'not_implemented',
                'responseRedaction' => 'not_implemented',
                'richerEnablement' => 'blocked',
            ],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_public_mutation_live_data_error')) {
    function red_addon_public_mutation_live_data_error(array &$result, $code)
    {
        $code = is_string($code)
            && preg_match('/\A[a-z][a-z0-9_]{0,79}\z/', $code) === 1
                ? $code
                : 'preflight_invalid';
        if (!in_array($code, $result['errors'], true)) {
            $result['errors'][] = $code;
        }
    }
}

if (!function_exists('red_addon_public_mutation_live_data_sort_records')) {
    function red_addon_public_mutation_live_data_sort_records(array &$records)
    {
        usort(
            $records,
            static function (array $left, array $right): int {
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

if (!function_exists('red_addon_public_mutation_live_data_blocker')) {
    function red_addon_public_mutation_live_data_blocker($code, $count = null)
    {
        $blocker = ['code' => (string) $code];
        if (is_int($count) && $count >= 0 && $count <= 200) {
            $blocker['count'] = $count;
        }
        return $blocker;
    }
}

if (!function_exists('red_addon_public_mutation_live_data_table_evidence')) {
    function red_addon_public_mutation_live_data_table_evidence(
        $connection,
        $packageId,
        $routeId,
        $mutationId,
        array $tables
    ) {
        $result = [
            'inspected' => false,
            'valid' => false,
            'tableCount' => 0,
            'innoDbTableCount' => 0,
            'evidenceSha256' => '',
            'errors' => [],
        ];
        if (!($connection instanceof mysqli)
            || !red_addon_valid_package_id($packageId)
            || !red_addon_valid_capability($routeId)
            || !red_addon_valid_capability($mutationId)
            || $tables === []
            || count($tables) > 8
        ) {
            $result['errors'][] = 'table_evidence_invalid';
            return $result;
        }
        $normalizedTables = array_values(array_unique($tables));
        sort($normalizedTables, SORT_STRING);
        if ($normalizedTables !== $tables) {
            $result['errors'][] = 'table_evidence_invalid';
            return $result;
        }
        foreach ($normalizedTables as $table) {
            if (!red_addon_valid_public_mutation_table($table)) {
                $result['errors'][] = 'table_evidence_invalid';
                return $result;
            }
        }
        $result['tableCount'] = count($normalizedTables);

        $database = red_addon_enable_preflight_database_name($connection);
        if ($database === '') {
            $result['errors'][] = 'table_storage_unavailable';
            return $result;
        }
        $material = [];
        foreach ($normalizedTables as $table) {
            try {
                $statement = mysqli_prepare(
                    $connection,
                    'SELECT ENGINE
                     FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND BINARY TABLE_NAME=BINARY ?
                     LIMIT 1'
                );
                if (!$statement) {
                    $result['errors'][] = 'table_storage_unavailable';
                    return $result;
                }
                $engine = '';
                mysqli_stmt_bind_param($statement, 's', $table);
                mysqli_stmt_bind_result($statement, $engine);
                $executed = mysqli_stmt_execute($statement);
                $found = $executed && mysqli_stmt_fetch($statement) === true;
                mysqli_stmt_close($statement);
            } catch (Throwable $throwable) {
                $result['errors'][] = 'table_storage_unavailable';
                return $result;
            }
            $result['inspected'] = true;
            if (!$found) {
                $result['errors'][] = 'declared_table_unavailable';
                return $result;
            }
            if ($engine === 'InnoDB') {
                $result['innoDbTableCount']++;
            }
            $material[] = [
                'table' => $table,
                'engine' => $engine,
            ];
        }

        $encoded = json_encode(
            [
                'schema' => 1,
                'database' => $database,
                'packageId' => $packageId,
                'route' => $routeId,
                'mutation' => $mutationId,
                'tables' => $material,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            $result['errors'][] = 'table_evidence_invalid';
            return $result;
        }
        $result['evidenceSha256'] = hash('sha256', $encoded);
        $result['valid'] = $result['innoDbTableCount'] === $result['tableCount'];
        if (!$result['valid']) {
            $result['errors'][] = 'declared_table_engine_unsupported';
        }
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_live_data_secret_availability_storage_evidence')) {
    /**
     * Rebuilds typed configuration only inside the live-data boundary so the
     * dependency-free secret-availability helper remains database-free.
     */
    function red_addon_public_mutation_live_data_secret_availability_storage_evidence(
        $connection,
        array $manifest,
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
        if (!red_addon_setting_storage_available($connection)) {
            $result['errors'][] = 'storage_unavailable';
            return $result;
        }
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema)) {
            $result['errors'][] = 'schema_unavailable';
            return $result;
        }
        $definitions = [];
        foreach ($schema as $definition) {
            if (!is_array($definition)
                || !is_string($definition['key'] ?? null)
                || !is_string($definition['type'] ?? null)
                || isset($definitions[$definition['key']])
            ) {
                $result['errors'][] = 'schema_unavailable';
                return $result;
            }
            $definitions[$definition['key']] = $definition;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=?
                 ORDER BY SettingKey ASC'
            );
            if (!$statement) {
                $result['errors'][] = 'storage_unavailable';
                return $result;
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $result['errors'][] = 'storage_unavailable';
                return $result;
            }
            $query = mysqli_stmt_get_result($statement);
            $rows = [];
            while ($query && ($row = mysqli_fetch_assoc($query))) {
                $rows[] = $row;
            }
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $result['errors'][] = 'storage_unavailable';
            return $result;
        }
        if (count($rows) !== count($definitions)) {
            $result['errors'][] = 'configuration_invalid';
            return $result;
        }

        $configuredValues = [];
        foreach ($rows as $row) {
            $key = is_string($row['SettingKey'] ?? null)
                ? $row['SettingKey']
                : '';
            $type = is_string($row['ValueType'] ?? null)
                ? $row['ValueType']
                : '';
            $definition = $definitions[$key] ?? null;
            if (!is_array($definition)
                || isset($configuredValues[$key])
                || !hash_equals((string) $definition['type'], $type)
            ) {
                $result['errors'][] = 'configuration_invalid';
                return $result;
            }
            if ($type === 'secret-reference') {
                $reference = $row['SecretReference'] ?? null;
                if ($row['ValueJSON'] !== null
                    || !red_addon_setting_string_is_valid(
                        'secret-reference',
                        $reference
                    )
                ) {
                    $result['errors'][] = 'configuration_invalid';
                    return $result;
                }
                $configuredValues[$key] = $reference;
                continue;
            }
            if ($row['SecretReference'] !== null
                || !is_string($row['ValueJSON'] ?? null)
            ) {
                $result['errors'][] = 'configuration_invalid';
                return $result;
            }
            try {
                $configuredValues[$key] = json_decode(
                    $row['ValueJSON'],
                    true,
                    8,
                    JSON_THROW_ON_ERROR
                );
            } catch (Throwable $throwable) {
                $result['errors'][] = 'configuration_invalid';
                return $result;
            }
        }
        $evidence = red_addon_secret_availability_evidence(
            $manifest,
            $configuredValues,
            $packageId,
            $declarations
        );
        return is_array($evidence) ? $evidence : $result;
    }
}

if (!function_exists('red_addon_public_mutation_live_data_settings_evidence')) {
    function red_addon_public_mutation_live_data_settings_evidence(
        $connection,
        array $manifest,
        $packageId,
        $declarations = null
    ) {
        $result = [
            'inspected' => false,
            'valid' => false,
            'complete' => false,
            'secretAvailable' => false,
            'settingCount' => 0,
            'configuredSettingCount' => 0,
            'secretSettingCount' => 0,
            'availableSecretCount' => 0,
            'missingSecretSettingCount' => 0,
            'stateSha256' => '',
            'secretAvailabilitySha256' => '',
            'errors' => [],
        ];
        if (!red_addon_valid_package_id($packageId)
            || ($manifest['id'] ?? null) !== $packageId
        ) {
            $result['errors'][] = 'settings_evidence_invalid';
            return $result;
        }
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema)) {
            $result['errors'][] = 'settings_evidence_invalid';
            return $result;
        }
        $result['settingCount'] = count($schema);
        foreach ($schema as $definition) {
            if (!is_array($definition)
                || !is_string($definition['key'] ?? null)
                || !is_string($definition['type'] ?? null)
            ) {
                $result['errors'][] = 'settings_evidence_invalid';
                return $result;
            }
            if (($definition['secret'] ?? false) === true) {
                $result['secretSettingCount']++;
            }
        }
        if ($result['settingCount'] === 0) {
            $result['inspected'] = true;
            $result['valid'] = true;
            $result['complete'] = true;
            $result['secretAvailable'] = true;
            $result['stateSha256'] = hash('sha256', '[]');
            $result['secretAvailabilitySha256'] = hash('sha256', '[]');
            return $result;
        }
        if (!red_addon_setting_storage_available($connection)) {
            $result['errors'][] = 'settings_storage_unavailable';
            return $result;
        }
        $state = red_addon_setting_current_state(
            $connection,
            $manifest,
            $packageId
        );
        if (!is_array($state) || empty($state['valid'])) {
            $result['errors'][] = 'settings_storage_invalid';
            return $result;
        }
        $result['inspected'] = true;
        $result['valid'] = true;
        $result['configuredSettingCount'] = (int) ($state['rowCount'] ?? 0);
        $result['stateSha256'] = is_string($state['stateSha256'] ?? null)
            ? $state['stateSha256']
            : '';
        if (!red_addon_valid_sha256($result['stateSha256'])) {
            $result['valid'] = false;
            $result['errors'][] = 'settings_storage_invalid';
            return $result;
        }
        if ($result['configuredSettingCount'] !== $result['settingCount']) {
            $result['errors'][] = 'settings_configuration_incomplete';
            return $result;
        }
        $result['complete'] = true;
        if ($result['secretSettingCount'] === 0) {
            $result['secretAvailable'] = true;
            $result['secretAvailabilitySha256'] = hash('sha256', '[]');
            return $result;
        }
        $availability =
            red_addon_public_mutation_live_data_secret_availability_storage_evidence(
            $connection,
            $manifest,
            $packageId,
            $declarations
        );
        if (!is_array($availability) || empty($availability['valid'])) {
            $result['errors'][] = 'secret_availability_invalid';
            return $result;
        }
        $result['availableSecretCount'] = (int) (
            $availability['availableCount'] ?? 0
        );
        $result['missingSecretSettingCount'] = count(
            is_array($availability['missing'] ?? null)
                ? $availability['missing']
                : []
        );
        $result['secretAvailabilitySha256'] = is_string(
            $availability['evidenceSha256'] ?? null
        ) ? $availability['evidenceSha256'] : '';
        if (!red_addon_valid_sha256($result['secretAvailabilitySha256'])) {
            $result['errors'][] = 'secret_availability_invalid';
            return $result;
        }
        $result['secretAvailable'] = !empty($availability['available']);
        if (!$result['secretAvailable']) {
            $result['errors'][] = 'secret_reference_unavailable';
        }
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_live_data_fingerprint')) {
    function red_addon_public_mutation_live_data_fingerprint(array $plan)
    {
        $encoded = json_encode(
            [
                'schema' => 1,
                'databaseSha256' => $plan['databaseSha256'] ?? null,
                'packageId' => $plan['packageId'] ?? null,
                'version' => $plan['version'] ?? null,
                'route' => $plan['route'] ?? null,
                'mutation' => $plan['mutation'] ?? null,
                'currentState' => $plan['currentState'] ?? null,
                'targetState' => $plan['targetState'] ?? null,
                'declarationSha256' => $plan['declarationSha256'] ?? null,
                'enablementEvidenceSha256' =>
                    $plan['enablementEvidenceSha256'] ?? null,
                'tableEvidenceSha256' => $plan['tableEvidenceSha256'] ?? null,
                'settingsStateSha256' => $plan['settingsStateSha256'] ?? null,
                'secretAvailabilitySha256' =>
                    $plan['secretAvailabilitySha256'] ?? null,
                'migrationCount' => $plan['migrationCount'] ?? null,
                'tableCount' => $plan['tableCount'] ?? null,
                'innoDbTableCount' => $plan['innoDbTableCount'] ?? null,
                'settingCount' => $plan['settingCount'] ?? null,
                'configuredSettingCount' =>
                    $plan['configuredSettingCount'] ?? null,
                'secretSettingCount' => $plan['secretSettingCount'] ?? null,
                'availableSecretCount' =>
                    $plan['availableSecretCount'] ?? null,
                'missingSecretSettingCount' =>
                    $plan['missingSecretSettingCount'] ?? null,
                'gates' => $plan['gates'] ?? null,
                'blockers' => $plan['blockers'] ?? null,
                'dataEvidenceReady' => $plan['dataEvidenceReady'] ?? null,
                'enableReady' => false,
                'activationSupported' => false,
                'requestDispatch' => false,
                'invoked' => false,
                'stateMutation' => false,
                'runtimeLoad' => false,
                'packageExecution' => false,
                'secretResolution' => false,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_public_mutation_live_data_preflight')) {
    function red_addon_public_mutation_live_data_preflight(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog,
        $routeId,
        $mutationId,
        $secretDeclarations = null
    ) {
        $packageId = is_string($package['id'] ?? null) ? $package['id'] : '';
        $result = red_addon_public_mutation_live_data_result(
            $packageId,
            $routeId,
            $mutationId
        );
        if ($result['packageId'] === ''
            || $result['route'] === ''
            || $result['mutation'] === ''
            || strpos($result['route'], $result['packageId'] . '/') !== 0
            || strpos($result['mutation'], $result['packageId'] . '/') !== 0
        ) {
            red_addon_public_mutation_live_data_error(
                $result,
                'identity_invalid'
            );
            return $result;
        }
        $database = red_addon_enable_preflight_database_name($connection);
        if ($database === '') {
            red_addon_public_mutation_live_data_error(
                $result,
                'registry_storage_unavailable'
            );
            return $result;
        }
        $result['databaseSha256'] = hash('sha256', $database);
        if (red_addon_public_mutation_subject_storage_available($connection)) {
            $result['gates']['anonymousSubject'] = 'passed';
            $result['gates']['csrf'] = 'passed';
        } else {
            $result['gates']['anonymousSubject'] = 'blocked';
            $result['gates']['csrf'] = 'blocked';
            $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                'anonymous_subject_storage_unavailable'
            );
            $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                'public_csrf_storage_unavailable'
            );
        }

        $enablement = red_addon_enable_preflight_plan(
            $connection,
            $package,
            $actorAdminRecordId,
            $catalog
        );
        foreach ([
            'authorization',
            'trust',
            'registry',
            'dependencies',
            'capabilityNamespace',
            'routeNamespace',
        ] as $gate) {
            $status = $enablement['gates'][$gate] ?? null;
            if (is_string($status)) {
                $result['gates'][$gate] = $status;
            }
        }
        if (empty($enablement['valid'])
            || !red_addon_valid_sha256($enablement['planSha256'] ?? null)
        ) {
            foreach ($enablement['errors'] ?? [] as $error) {
                red_addon_public_mutation_live_data_error($result, $error);
            }
            if ($result['errors'] === []) {
                red_addon_public_mutation_live_data_error(
                    $result,
                    'enablement_evidence_invalid'
                );
            }
            return $result;
        }
        $result['version'] = is_string($enablement['version'] ?? null)
            ? $enablement['version']
            : '';
        $result['currentState'] = is_string($enablement['currentState'] ?? null)
            ? $enablement['currentState']
            : '';
        if ($result['version'] === ''
            || $result['currentState'] !== 'installed_disabled'
        ) {
            red_addon_public_mutation_live_data_error(
                $result,
                'enablement_evidence_invalid'
            );
            return $result;
        }
        $result['enablementEvidenceSha256'] = $enablement['planSha256'];
        $result['migrationCount'] = count(
            is_array($enablement['appliedMigrations'] ?? null)
                ? $enablement['appliedMigrations']
                : []
        );
        $result['gates']['migrations'] = 'passed';

        if ($result['gates']['dependencies'] !== 'passed') {
            $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                'required_dependency_not_enabled'
            );
        }
        if ($result['gates']['capabilityNamespace'] !== 'passed') {
            $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                'capability_namespace_conflict'
            );
        }
        if ($result['gates']['routeNamespace'] !== 'passed') {
            $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                'route_namespace_conflict'
            );
        }

        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $declaration = red_addon_public_mutation_declaration_preflight(
            $manifest,
            $result['route'],
            $result['mutation']
        );
        if (empty($declaration['valid'])
            || !red_addon_public_mutation_declaration_preflight_is_valid(
                $declaration
            )
        ) {
            red_addon_public_mutation_live_data_error(
                $result,
                'declaration_invalid'
            );
            return $result;
        }
        $result['declarationSha256'] = $declaration['contractSha256'];
        $result['gates']['declaration'] = 'passed';

        $contract = red_addon_public_mutation_contract(
            $manifest,
            $result['route'],
            $result['mutation']
        );
        if (!is_array($contract)
            || !is_array($contract['tables'] ?? null)
        ) {
            red_addon_public_mutation_live_data_error(
                $result,
                'declaration_invalid'
            );
            return $result;
        }
        $tables = red_addon_public_mutation_live_data_table_evidence(
            $connection,
            $result['packageId'],
            $result['route'],
            $result['mutation'],
            $contract['tables']
        );
        if (empty($tables['inspected'])) {
            red_addon_public_mutation_live_data_error(
                $result,
                'table_storage_unavailable'
            );
            return $result;
        }
        $result['tableCount'] = (int) ($tables['tableCount'] ?? 0);
        $result['innoDbTableCount'] = (int) (
            $tables['innoDbTableCount'] ?? 0
        );
        if (red_addon_valid_sha256($tables['evidenceSha256'] ?? null)) {
            $result['tableEvidenceSha256'] = $tables['evidenceSha256'];
        }
        if (!empty($tables['valid'])
            && $result['tableEvidenceSha256'] !== ''
        ) {
            $result['gates']['packageTables'] = 'passed';
        } else {
            $result['gates']['packageTables'] = 'blocked';
            $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                'declared_package_tables_unavailable',
                count($contract['tables'])
            );
        }

        $settings = red_addon_public_mutation_live_data_settings_evidence(
            $connection,
            $manifest,
            $result['packageId'],
            $secretDeclarations
        );
        if (empty($settings['inspected']) || empty($settings['valid'])) {
            red_addon_public_mutation_live_data_error(
                $result,
                'settings_storage_unavailable'
            );
            return $result;
        }
        $result['settingCount'] = (int) ($settings['settingCount'] ?? 0);
        $result['configuredSettingCount'] = (int) (
            $settings['configuredSettingCount'] ?? 0
        );
        $result['secretSettingCount'] = (int) (
            $settings['secretSettingCount'] ?? 0
        );
        $result['availableSecretCount'] = (int) (
            $settings['availableSecretCount'] ?? 0
        );
        $result['missingSecretSettingCount'] = (int) (
            $settings['missingSecretSettingCount'] ?? 0
        );
        $result['settingsStateSha256'] = is_string(
            $settings['stateSha256'] ?? null
        ) ? $settings['stateSha256'] : '';
        $result['secretAvailabilitySha256'] = is_string(
            $settings['secretAvailabilitySha256'] ?? null
        ) ? $settings['secretAvailabilitySha256'] : '';
        if ($result['settingCount'] === 0) {
            $result['gates']['settingsConfiguration'] = 'not_applicable';
            $result['gates']['secretAvailability'] = 'not_applicable';
            $result['gates']['settingsEndpoint'] = 'not_applicable';
            $result['gates']['secretLookup'] = 'not_applicable';
        } else {
            if (!empty($settings['complete'])) {
                $result['gates']['settingsConfiguration'] = 'passed';
            } else {
                $result['gates']['settingsConfiguration'] = 'blocked';
                $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                    'settings_configuration_incomplete',
                    $result['settingCount']
                );
            }
            $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                'settings_endpoint_contract_required',
                $result['settingCount']
            );
            if ($result['secretSettingCount'] === 0) {
                $result['gates']['secretAvailability'] = 'not_applicable';
                $result['gates']['secretLookup'] = 'not_applicable';
            } elseif (!empty($settings['complete'])
                && !empty($settings['secretAvailable'])
            ) {
                $result['gates']['secretAvailability'] = 'passed';
                $result['blockers'][] =
                    red_addon_public_mutation_live_data_blocker(
                        'secret_lookup_contract_required',
                        $result['secretSettingCount']
                    );
            } else {
                $result['gates']['secretAvailability'] = 'blocked';
                $result['blockers'][] = red_addon_public_mutation_live_data_blocker(
                    'secret_reference_unavailable',
                    $result['missingSecretSettingCount']
                );
                $result['blockers'][] =
                    red_addon_public_mutation_live_data_blocker(
                        'secret_lookup_contract_required',
                        $result['secretSettingCount']
                    );
            }
        }

        foreach ([
            ['idempotency', 'public_idempotency_contract_required'],
            ['rateLimit', 'public_rate_limit_contract_required'],
            ['transactionRunner', 'public_mutation_transaction_runner_required'],
            ['responseRedaction', 'public_response_redaction_contract_required'],
            ['richerEnablement', 'richer_enablement_contract_required'],
        ] as [$gate, $code]) {
            $result['blockers'][] =
                red_addon_public_mutation_live_data_blocker($code);
        }

        $result['dataEvidenceReady'] =
            $result['gates']['authorization'] === 'passed'
            && $result['gates']['trust'] === 'passed'
            && $result['gates']['registry'] === 'passed'
            && $result['gates']['dependencies'] === 'passed'
            && $result['gates']['capabilityNamespace'] === 'passed'
            && $result['gates']['routeNamespace'] === 'passed'
            && $result['gates']['declaration'] === 'passed'
            && $result['gates']['migrations'] === 'passed'
            && $result['gates']['packageTables'] === 'passed'
            && $result['gates']['anonymousSubject'] === 'passed'
            && $result['gates']['csrf'] === 'passed'
            && in_array(
                $result['gates']['settingsConfiguration'],
                ['passed', 'not_applicable'],
                true
            )
            && in_array(
                $result['gates']['secretAvailability'],
                ['passed', 'not_applicable'],
                true
            );
        red_addon_public_mutation_live_data_sort_records($result['blockers']);
        $result['planSha256'] = red_addon_public_mutation_live_data_fingerprint(
            $result
        );
        if (!red_addon_valid_sha256($result['planSha256'])) {
            red_addon_public_mutation_live_data_error(
                $result,
                'plan_encoding_failed'
            );
            $result['planSha256'] = '';
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_live_data_preflight_is_valid')) {
    function red_addon_public_mutation_live_data_preflight_is_valid($plan)
    {
        $expectedKeys = [
            'activationSupported',
            'availableSecretCount',
            'blockers',
            'configuredSettingCount',
            'currentState',
            'dataEvidenceReady',
            'databaseSha256',
            'declarationSha256',
            'enableReady',
            'enablementEvidenceSha256',
            'errors',
            'gates',
            'innoDbTableCount',
            'invoked',
            'migrationCount',
            'missingSecretSettingCount',
            'mutation',
            'packageExecution',
            'packageId',
            'planSha256',
            'requestDispatch',
            'route',
            'runtimeLoad',
            'secretAvailabilitySha256',
            'secretResolution',
            'secretSettingCount',
            'settingCount',
            'settingsStateSha256',
            'stateMutation',
            'tableCount',
            'tableEvidenceSha256',
            'targetState',
            'valid',
            'version',
        ];
        if (!is_array($plan)) {
            return false;
        }
        $keys = array_keys($plan);
        sort($keys, SORT_STRING);
        if ($keys !== $expectedKeys
            || empty($plan['valid'])
            || !is_bool($plan['dataEvidenceReady'] ?? null)
            || ($plan['enableReady'] ?? null) !== false
            || ($plan['activationSupported'] ?? null) !== false
            || ($plan['requestDispatch'] ?? null) !== false
            || ($plan['invoked'] ?? null) !== false
            || ($plan['stateMutation'] ?? null) !== false
            || ($plan['runtimeLoad'] ?? null) !== false
            || ($plan['packageExecution'] ?? null) !== false
            || ($plan['secretResolution'] ?? null) !== false
            || !red_addon_valid_sha256($plan['databaseSha256'] ?? null)
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !is_string($plan['version'] ?? null)
            || $plan['version'] === ''
            || !red_addon_valid_capability($plan['route'] ?? null)
            || !red_addon_valid_capability($plan['mutation'] ?? null)
            || strpos($plan['route'], $plan['packageId'] . '/') !== 0
            || strpos($plan['mutation'], $plan['packageId'] . '/') !== 0
            || ($plan['currentState'] ?? null) !== 'installed_disabled'
            || ($plan['targetState'] ?? null) !== 'enabled'
            || !red_addon_valid_sha256($plan['declarationSha256'] ?? null)
            || !red_addon_valid_sha256(
                $plan['enablementEvidenceSha256'] ?? null
            )
            || !is_int($plan['migrationCount'] ?? null)
            || $plan['migrationCount'] < 0
            || $plan['migrationCount'] > 200
            || !is_int($plan['tableCount'] ?? null)
            || $plan['tableCount'] < 1
            || $plan['tableCount'] > 8
            || !is_int($plan['innoDbTableCount'] ?? null)
            || $plan['innoDbTableCount'] < 0
            || $plan['innoDbTableCount'] > $plan['tableCount']
            || !is_int($plan['settingCount'] ?? null)
            || $plan['settingCount'] < 0
            || $plan['settingCount'] > 200
            || !is_int($plan['configuredSettingCount'] ?? null)
            || $plan['configuredSettingCount'] < 0
            || $plan['configuredSettingCount'] > $plan['settingCount']
            || !is_int($plan['secretSettingCount'] ?? null)
            || $plan['secretSettingCount'] < 0
            || $plan['secretSettingCount'] > $plan['settingCount']
            || !is_int($plan['availableSecretCount'] ?? null)
            || $plan['availableSecretCount'] < 0
            || $plan['availableSecretCount'] > $plan['secretSettingCount']
            || !is_int($plan['missingSecretSettingCount'] ?? null)
            || $plan['missingSecretSettingCount'] < 0
            || $plan['missingSecretSettingCount'] > $plan['secretSettingCount']
            || !is_array($plan['gates'] ?? null)
            || !is_array($plan['blockers'] ?? null)
            || ($plan['errors'] ?? null) !== []
            || !red_addon_valid_sha256($plan['planSha256'] ?? null)
        ) {
            return false;
        }
        $expectedGates = [
            'anonymousSubject',
            'authorization',
            'capabilityNamespace',
            'csrf',
            'declaration',
            'dependencies',
            'idempotency',
            'migrations',
            'packageTables',
            'rateLimit',
            'registry',
            'responseRedaction',
            'richerEnablement',
            'routeNamespace',
            'secretAvailability',
            'secretLookup',
            'settingsConfiguration',
            'settingsEndpoint',
            'transactionRunner',
            'trust',
        ];
        $gateKeys = array_keys($plan['gates']);
        sort($gateKeys, SORT_STRING);
        if ($gateKeys !== $expectedGates) {
            return false;
        }
        foreach ($plan['gates'] as $status) {
            if (!in_array(
                $status,
                ['passed', 'blocked', 'not_applicable', 'not_implemented'],
                true
            )) {
                return false;
            }
        }
        foreach ($plan['blockers'] as $blocker) {
            if (!is_array($blocker)) {
                return false;
            }
            $blockerKeys = array_keys($blocker);
            sort($blockerKeys, SORT_STRING);
            if (!in_array($blockerKeys, [['code'], ['code', 'count']], true)
                || !is_string($blocker['code'] ?? null)
                || preg_match('/\A[a-z][a-z0-9_]{0,79}\z/', $blocker['code']) !== 1
                || (isset($blocker['count'])
                    && (!is_int($blocker['count'])
                        || $blocker['count'] < 0
                        || $blocker['count'] > 200))
            ) {
                return false;
            }
        }
        if (!empty($plan['dataEvidenceReady'])
            && ($plan['gates']['authorization'] !== 'passed'
                || $plan['gates']['trust'] !== 'passed'
                || $plan['gates']['registry'] !== 'passed'
                || $plan['gates']['dependencies'] !== 'passed'
                || $plan['gates']['capabilityNamespace'] !== 'passed'
                || $plan['gates']['routeNamespace'] !== 'passed'
                || $plan['gates']['declaration'] !== 'passed'
                || $plan['gates']['migrations'] !== 'passed'
                || $plan['gates']['packageTables'] !== 'passed'
                || !in_array(
                    $plan['gates']['settingsConfiguration'],
                    ['passed', 'not_applicable'],
                    true
                )
                || !in_array(
                    $plan['gates']['secretAvailability'],
                    ['passed', 'not_applicable'],
                    true
                ))
        ) {
            return false;
        }
        return hash_equals(
            $plan['planSha256'],
            red_addon_public_mutation_live_data_fingerprint($plan)
        );
    }
}

?>
