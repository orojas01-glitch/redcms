<?php
/**
 * Read-only database readiness evidence for the P3A payment-adapter profile.
 *
 * This boundary composes the closed manifest profile with the established
 * Owner-authorized enablement preflight. It reads only registry identity,
 * dependency, migration-ledger, and information-schema facts. It does not
 * include package PHP, resolve settings or secrets, register a route, mutate
 * lifecycle state, or open a network connection.
 */

require_once __DIR__ . '/addon_payment_adapter_preflight_helpers.php';
require_once __DIR__ . '/addon_enable_preflight_helpers.php';
require_once __DIR__ . '/addon_install_helpers.php';

if (!function_exists('red_addon_payment_adapter_database_result')) {
    function red_addon_payment_adapter_database_result($packageId = '')
    {
        return [
            'valid' => false,
            'databaseEvidenceReady' => false,
            'enableReady' => false,
            'activationSupported' => false,
            'stateMutation' => false,
            'runtimeLoad' => false,
            'packageExecution' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'routeExposure' => false,
            'packageId' => is_string($packageId) ? $packageId : '',
            'version' => '',
            'currentState' => '',
            'targetState' => 'enabled',
            'databaseSha256' => '',
            'contractSha256' => '',
            'baseEnablementSha256' => '',
            'dependencyEvidenceSha256' => '',
            'migrationEvidenceSha256' => '',
            'tableEvidenceSha256' => '',
            'dependencyCount' => 0,
            'migrationCount' => 0,
            'tableCount' => 0,
            'innoDbTableCount' => 0,
            'gates' => [
                'adapterContract' => 'not_checked',
                'authorization' => 'not_checked',
                'trust' => 'not_checked',
                'registry' => 'not_checked',
                'dependencies' => 'not_checked',
                'capabilityNamespace' => 'not_checked',
                'routeNamespace' => 'not_checked',
                'migrations' => 'not_checked',
                'packageTables' => 'not_checked',
                'registrarValidation' => 'not_implemented',
                'serverEventIngress' => 'not_implemented',
                'atomicEnablement' => 'not_implemented',
            ],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_database_fingerprint')) {
    function red_addon_payment_adapter_database_fingerprint(array $plan)
    {
        $material = $plan;
        unset($material['valid'], $material['planSha256']);
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_payment_adapter_hash_material')) {
    function red_addon_payment_adapter_hash_material(array $material)
    {
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_payment_adapter_migration_tables')) {
    function red_addon_payment_adapter_migration_tables(
        array $package,
        &$errorCode = ''
    ) {
        $errorCode = '';
        $snapshot = red_addon_registry_snapshot($package);
        if (!is_array($snapshot)
            || $snapshot['migrations'] === []
            || count($snapshot['migrations']) > 16
        ) {
            $errorCode = 'migration_table_declaration_invalid';
            return [];
        }

        $tables = [];
        $patterns = [
            '/(?:\A|;)\s*CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*(?:ALTER|DROP|TRUNCATE)\s+TABLE\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*TRUNCATE\s+(?!TABLE\b)(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*(?:INSERT|REPLACE)\s+INTO\s+(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*UPDATE\s+(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*DELETE\s+FROM\s+(`?[A-Za-z0-9_]+`?)/i',
            '/\bREFERENCES\s+(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*(?:CREATE|DROP)\s+(?:UNIQUE\s+)?INDEX\b[\s\S]*?\bON\s+(`?[A-Za-z0-9_]+`?)/i',
        ];
        foreach ($snapshot['migrations'] as $migration) {
            $migrationError = '';
            $sql = red_addon_install_migration_sql(
                $package,
                $migration,
                $migrationError
            );
            if (!is_string($sql) || $migrationError !== '') {
                $errorCode = $migrationError !== ''
                    ? $migrationError
                    : 'migration_table_declaration_invalid';
                return [];
            }
            $scopeSql = preg_replace(
                [
                    '/\/\*[\s\S]*?\*\//',
                    '/--[^\r\n]*/',
                    '/\#[^\r\n]*/',
                ],
                ' ',
                $sql
            );
            if (!is_string($scopeSql)) {
                $errorCode = 'migration_table_declaration_invalid';
                return [];
            }
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $scopeSql, $matches);
                foreach ($matches[1] ?? [] as $tableName) {
                    $tableName = trim((string) $tableName, '`');
                    if (!red_addon_valid_public_mutation_table($tableName)) {
                        $errorCode = 'migration_table_namespace_invalid';
                        return [];
                    }
                    $tables[strtolower($tableName)] = $tableName;
                }
            }
        }
        $tables = array_values($tables);
        sort($tables, SORT_STRING);
        if ($tables === [] || count($tables) > 16) {
            $errorCode = 'migration_table_declaration_invalid';
            return [];
        }
        return $tables;
    }
}

if (!function_exists('red_addon_payment_adapter_table_evidence')) {
    function red_addon_payment_adapter_table_evidence(
        $connection,
        $packageId,
        array $tables
    ) {
        $result = [
            'valid' => false,
            'tableCount' => count($tables),
            'innoDbTableCount' => 0,
            'evidenceSha256' => '',
            'errors' => [],
        ];
        if (!($connection instanceof mysqli)
            || !red_addon_valid_package_id($packageId)
            || $tables === []
            || count($tables) > 16
        ) {
            $result['errors'][] = 'package_table_evidence_invalid';
            return $result;
        }
        $normalized = array_values(array_unique($tables));
        sort($normalized, SORT_STRING);
        if ($normalized !== $tables) {
            $result['errors'][] = 'package_table_evidence_invalid';
            return $result;
        }

        $database = red_addon_enable_preflight_database_name($connection);
        if ($database === '') {
            $result['errors'][] = 'package_table_storage_unavailable';
            return $result;
        }
        $material = [];
        foreach ($normalized as $table) {
            if (!red_addon_valid_public_mutation_table($table)) {
                $result['errors'][] = 'package_table_evidence_invalid';
                return $result;
            }
            try {
                $statement = mysqli_prepare(
                    $connection,
                    'SELECT ENGINE
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND BINARY TABLE_NAME=BINARY ?
                     LIMIT 1'
                );
                if (!$statement) {
                    $result['errors'][] = 'package_table_storage_unavailable';
                    return $result;
                }
                $engine = '';
                mysqli_stmt_bind_param($statement, 's', $table);
                mysqli_stmt_bind_result($statement, $engine);
                $executed = mysqli_stmt_execute($statement);
                $found = $executed && mysqli_stmt_fetch($statement) === true;
                mysqli_stmt_close($statement);
            } catch (Throwable $throwable) {
                $result['errors'][] = 'package_table_storage_unavailable';
                return $result;
            }
            if (!$found) {
                $result['errors'][] = 'package_table_unavailable';
                return $result;
            }
            if ($engine === 'InnoDB') {
                $result['innoDbTableCount']++;
            }
            $material[] = ['table' => $table, 'engine' => $engine];
        }
        $result['evidenceSha256'] = red_addon_payment_adapter_hash_material([
            'schema' => 1,
            'database' => $database,
            'packageId' => $packageId,
            'tables' => $material,
        ]);
        if (!red_addon_valid_sha256($result['evidenceSha256'])) {
            $result['errors'][] = 'package_table_evidence_invalid';
            $result['evidenceSha256'] = '';
            return $result;
        }
        if ($result['innoDbTableCount'] !== $result['tableCount']) {
            $result['errors'][] = 'package_table_engine_unsupported';
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_payment_adapter_database_preflight')) {
    function red_addon_payment_adapter_database_preflight(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog
    ) {
        $packageId = is_string($package['id'] ?? null) ? $package['id'] : '';
        $result = red_addon_payment_adapter_database_result($packageId);
        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $contract = red_addon_payment_adapter_profile($manifest);
        if (empty($contract['valid'])
            || !red_addon_payment_adapter_profile_is_valid($contract)
        ) {
            $result['errors'][] = 'payment_adapter_contract_invalid';
            return $result;
        }
        $result['contractSha256'] = $contract['contractSha256'];
        $result['gates']['adapterContract'] = 'passed';

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
                && $base['errors'] !== []
                    ? array_values($base['errors'])
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

        $database = red_addon_enable_preflight_database_name($connection);
        $result['databaseSha256'] = red_addon_payment_adapter_hash_material([
            'schema' => 1,
            'database' => $database,
        ]);
        if ($database === ''
            || !red_addon_valid_sha256($result['databaseSha256'])
        ) {
            $result['errors'][] = 'database_evidence_invalid';
            $result['databaseSha256'] = '';
            return $result;
        }

        $dependencies = is_array($base['requiredDependencies'] ?? null)
            ? $base['requiredDependencies']
            : [];
        $result['dependencyCount'] = count($dependencies);
        if ($result['gates']['dependencies'] === 'passed'
            && $result['dependencyCount'] === 1
            && ($dependencies[0]['id'] ?? null) === 'redcms.store-lite'
            && ($dependencies[0]['lifecycleState'] ?? null) === 'enabled'
        ) {
            $result['dependencyEvidenceSha256'] =
                red_addon_payment_adapter_hash_material($dependencies);
        } elseif ($result['gates']['dependencies'] === 'passed') {
            $result['gates']['dependencies'] = 'blocked';
        }

        $migrations = is_array($base['appliedMigrations'] ?? null)
            ? $base['appliedMigrations']
            : [];
        $result['migrationCount'] = count($migrations);
        if ($result['migrationCount'] === $contract['migrationCount']
            && $result['migrationCount'] >= 1
            && $result['migrationCount'] <= 16
        ) {
            $result['migrationEvidenceSha256'] =
                red_addon_payment_adapter_hash_material($migrations);
            $result['gates']['migrations'] = 'passed';
        } else {
            $result['gates']['migrations'] = 'blocked';
        }

        $tableError = '';
        $tables = red_addon_payment_adapter_migration_tables(
            $package,
            $tableError
        );
        if ($tableError !== '') {
            $result['errors'][] = $tableError;
            return $result;
        }
        $tableEvidence = red_addon_payment_adapter_table_evidence(
            $connection,
            $packageId,
            $tables
        );
        $result['tableCount'] = (int) ($tableEvidence['tableCount'] ?? 0);
        $result['innoDbTableCount'] = (int) (
            $tableEvidence['innoDbTableCount'] ?? 0
        );
        if (empty($tableEvidence['valid'])) {
            $result['errors'] = array_values(
                $tableEvidence['errors'] ?? ['package_table_evidence_invalid']
            );
            return $result;
        }
        $result['tableEvidenceSha256'] = $tableEvidence['evidenceSha256'];
        $result['gates']['packageTables'] = 'passed';

        $readyGates = [
            'adapterContract', 'authorization', 'trust', 'registry',
            'dependencies', 'capabilityNamespace', 'routeNamespace',
            'migrations', 'packageTables',
        ];
        $result['databaseEvidenceReady'] = true;
        foreach ($readyGates as $gate) {
            if ($result['gates'][$gate] !== 'passed') {
                $result['databaseEvidenceReady'] = false;
            }
        }
        if (!$result['databaseEvidenceReady']) {
            $result['blockers'][] = [
                'code' => 'database_payment_adapter_evidence_incomplete',
            ];
        }
        $result['blockers'][] = [
            'code' => 'atomic_payment_adapter_enablement_required',
        ];
        $result['blockers'][] = ['code' => 'registrar_validation_required'];
        $result['blockers'][] = ['code' => 'server_event_ingress_required'];
        red_addon_enable_preflight_sort_records($result['blockers']);

        $result['planSha256'] =
            red_addon_payment_adapter_database_fingerprint($result);
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['errors'][] = 'plan_encoding_failed';
            $result['planSha256'] = '';
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_payment_adapter_database_preflight_is_valid')) {
    function red_addon_payment_adapter_database_preflight_is_valid($plan)
    {
        $expectedGateKeys = [
            'adapterContract', 'authorization', 'trust', 'registry',
            'dependencies', 'capabilityNamespace', 'routeNamespace',
            'migrations', 'packageTables', 'registrarValidation',
            'serverEventIngress', 'atomicEnablement',
        ];
        if (!is_array($plan)
            || array_keys($plan) !== array_keys(
                red_addon_payment_adapter_database_result('')
            )
            || empty($plan['valid'])
            || !is_bool($plan['databaseEvidenceReady'] ?? null)
            || ($plan['enableReady'] ?? null) !== false
            || ($plan['activationSupported'] ?? null) !== false
            || ($plan['stateMutation'] ?? null) !== false
            || ($plan['runtimeLoad'] ?? null) !== false
            || ($plan['packageExecution'] ?? null) !== false
            || ($plan['secretResolution'] ?? null) !== false
            || ($plan['networkAccess'] ?? null) !== false
            || ($plan['routeExposure'] ?? null) !== false
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !red_addon_valid_semantic_version($plan['version'] ?? null)
            || ($plan['currentState'] ?? null) !== 'installed_disabled'
            || ($plan['targetState'] ?? null) !== 'enabled'
            || !red_addon_valid_sha256($plan['databaseSha256'] ?? null)
            || !red_addon_valid_sha256($plan['contractSha256'] ?? null)
            || !red_addon_valid_sha256(
                $plan['baseEnablementSha256'] ?? null
            )
            || !is_int($plan['dependencyCount'] ?? null)
            || $plan['dependencyCount'] < 0
            || $plan['dependencyCount'] > 1
            || !is_int($plan['migrationCount'] ?? null)
            || $plan['migrationCount'] < 1
            || $plan['migrationCount'] > 16
            || !is_int($plan['tableCount'] ?? null)
            || $plan['tableCount'] < 1
            || $plan['tableCount'] > 16
            || !is_int($plan['innoDbTableCount'] ?? null)
            || $plan['innoDbTableCount'] !== $plan['tableCount']
            || !red_addon_valid_sha256($plan['migrationEvidenceSha256'] ?? null)
            || !red_addon_valid_sha256($plan['tableEvidenceSha256'] ?? null)
            || !is_array($plan['gates'] ?? null)
            || array_keys($plan['gates']) !== $expectedGateKeys
            || $plan['gates']['registrarValidation'] !== 'not_implemented'
            || $plan['gates']['serverEventIngress'] !== 'not_implemented'
            || $plan['gates']['atomicEnablement'] !== 'not_implemented'
            || !is_array($plan['blockers'] ?? null)
            || ($plan['errors'] ?? null) !== []
            || !red_addon_valid_sha256($plan['planSha256'] ?? null)
            || !hash_equals(
                $plan['planSha256'],
                red_addon_payment_adapter_database_fingerprint($plan)
            )
        ) {
            return false;
        }
        $readyGates = [
            'adapterContract', 'authorization', 'trust', 'registry',
            'dependencies', 'capabilityNamespace', 'routeNamespace',
            'migrations', 'packageTables',
        ];
        $expectedReady = true;
        foreach ($readyGates as $gate) {
            if ($plan['gates'][$gate] !== 'passed') {
                $expectedReady = false;
            }
        }
        if ($plan['databaseEvidenceReady'] !== $expectedReady) {
            return false;
        }
        if ($expectedReady
            && ($plan['dependencyCount'] !== 1
                || !red_addon_valid_sha256(
                    $plan['dependencyEvidenceSha256'] ?? null
                ))
        ) {
            return false;
        }
        if (!$expectedReady
            && $plan['dependencyEvidenceSha256'] !== ''
            && !red_addon_valid_sha256($plan['dependencyEvidenceSha256'])
        ) {
            return false;
        }
        $expectedBlockers = $expectedReady
            ? [
                'atomic_payment_adapter_enablement_required',
                'registrar_validation_required',
                'server_event_ingress_required',
            ]
            : [
                'atomic_payment_adapter_enablement_required',
                'database_payment_adapter_evidence_incomplete',
                'registrar_validation_required',
                'server_event_ingress_required',
            ];
        return array_column($plan['blockers'], 'code') === $expectedBlockers;
    }
}

?>
