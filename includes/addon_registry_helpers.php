<?php
/**
 * Read-only reconciliation for per-client add-on registry state.
 *
 * This file does not execute package code, apply package SQL, or mutate
 * lifecycle state. It compares validated filesystem packages with the current
 * client database and fails closed on missing code, drift, or unknown state.
 */

require_once __DIR__ . '/addon_manifest_helpers.php';
require_once __DIR__ . '/admin_addon_authorization_helpers.php';

if (!function_exists('red_addon_registry_lifecycle_states')) {
    function red_addon_registry_lifecycle_states()
    {
        return [
            'installing',
            'installation_failed',
            'installed_disabled',
            'enabled',
            'uninstalled',
        ];
    }
}

if (!function_exists('red_addon_registry_valid_lifecycle_state')) {
    function red_addon_registry_valid_lifecycle_state($state)
    {
        return is_string($state)
            && in_array($state, red_addon_registry_lifecycle_states(), true);
    }
}

if (!function_exists('red_addon_registry_transition_capability')) {
    function red_addon_registry_transition_capability($transition)
    {
        $map = [
            'install' => 'addons.install',
            'enable' => 'addons.enable',
            'disable' => 'addons.disable',
            'upgrade' => 'addons.upgrade',
            'uninstall' => 'addons.uninstall',
            'purge' => 'addons.purge',
        ];
        return is_string($transition) && isset($map[$transition])
            ? $map[$transition]
            : '';
    }
}

if (!function_exists('red_addon_registry_actor_can_transition')) {
    function red_addon_registry_actor_can_transition(array $actor, $transition)
    {
        $capability = red_addon_registry_transition_capability($transition);
        return $capability !== ''
            && red_admin_addon_actor_can($actor, $capability);
    }
}

if (!function_exists('red_addon_registry_storage_available')) {
    function red_addon_registry_storage_available($connection)
    {
        if (!$connection) {
            return false;
        }
        try {
            $result = mysqli_query(
                $connection,
                "SELECT COUNT(*) AS TableCount
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME IN (
                     'RED_Addon_Installations',
                     'RED_Addon_Migrations'
                   )"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return (int) ($row['TableCount'] ?? 0) === 2;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_registry_inventory_sha256')) {
    function red_addon_registry_inventory_sha256(array $manifest)
    {
        $files = $manifest['integrity']['files'] ?? null;
        if (!is_array($files) || !array_is_list($files) || $files === []) {
            return '';
        }

        $inventory = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                return '';
            }
            $path = isset($file['path']) && is_string($file['path'])
                ? $file['path']
                : '';
            $sha256 = isset($file['sha256']) && is_string($file['sha256'])
                ? $file['sha256']
                : '';
            if (!red_addon_valid_relative_path($path)
                || $path === 'addon.json'
                || !red_addon_valid_sha256($sha256)
                || isset($inventory[$path])
            ) {
                return '';
            }
            $inventory[$path] = $sha256;
        }

        ksort($inventory, SORT_STRING);
        $serialized = '';
        foreach ($inventory as $path => $sha256) {
            $serialized .= $path . "\0" . $sha256 . "\n";
        }
        return hash('sha256', $serialized);
    }
}

if (!function_exists('red_addon_registry_snapshot')) {
    function red_addon_registry_snapshot(array $package)
    {
        if (empty($package['valid'])
            || empty($package['integrity']['inventoryComplete'])
            || !isset($package['manifest'])
            || !is_array($package['manifest'])
        ) {
            return null;
        }

        $manifest = $package['manifest'];
        $packageId = isset($manifest['id']) && is_string($manifest['id'])
            ? $manifest['id']
            : '';
        $version = isset($manifest['version']) && is_string($manifest['version'])
            ? $manifest['version']
            : '';
        $type = isset($manifest['type']) && is_string($manifest['type'])
            ? $manifest['type']
            : '';
        $packagePath = isset($package['path']) && is_string($package['path'])
            ? $package['path']
            : '';
        $manifestPath = $packagePath !== ''
            ? $packagePath . DIRECTORY_SEPARATOR . 'addon.json'
            : '';
        $manifestSha256 = $manifestPath !== '' && is_file($manifestPath)
            ? hash_file('sha256', $manifestPath)
            : false;
        $inventorySha256 = red_addon_registry_inventory_sha256($manifest);

        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_semantic_version($version)
            || !in_array(
                $type,
                ['component', 'service', 'adapter', 'content-package', 'cross-cutting'],
                true
            )
            || !is_string($manifestSha256)
            || !red_addon_valid_sha256($manifestSha256)
            || !red_addon_valid_sha256($inventorySha256)
        ) {
            return null;
        }

        $migrations = [];
        foreach ($manifest['migrations'] ?? [] as $migration) {
            if (!is_array($migration)) {
                return null;
            }
            $migrationId = isset($migration['id']) && is_string($migration['id'])
                ? $migration['id']
                : '';
            $migrationPath = isset($migration['path']) && is_string($migration['path'])
                ? $migration['path']
                : '';
            $checksum = isset($migration['sha256']) && is_string($migration['sha256'])
                ? $migration['sha256']
                : '';
            if ($migrationId === ''
                || $migrationPath === ''
                || !red_addon_valid_sha256($checksum)
                || isset($migrations[$migrationId])
            ) {
                return null;
            }
            $migrations[$migrationId] = [
                'id' => $migrationId,
                'path' => $migrationPath,
                'sha256' => $checksum,
            ];
        }

        return [
            'id' => $packageId,
            'version' => $version,
            'type' => $type,
            'manifestSha256' => $manifestSha256,
            'inventorySha256' => $inventorySha256,
            'migrations' => $migrations,
        ];
    }
}

if (!function_exists('red_addon_registry_installation')) {
    function red_addon_registry_installation($connection, $packageId)
    {
        if (!$connection
            || !red_addon_valid_package_id($packageId)
            || !red_addon_registry_storage_available($connection)
        ) {
            return null;
        }
        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT PackageID, PackageVersion, PackageType, ManifestSHA256,
                        InventorySHA256, LifecycleState,
                        InstalledByAdminRecordID, InstalledAt,
                        UpdatedByAdminRecordID, UpdatedAt
                 FROM RED_Addon_Installations
                 WHERE PackageID=?
                 LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }
            mysqli_stmt_bind_param($stmt, 's', $packageId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return $row ?: null;
        } catch (Throwable $throwable) {
            error_log('RED-CMS add-on registry lookup failed: ' . $throwable->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_addon_registry_migrations')) {
    function red_addon_registry_migrations($connection, $packageId)
    {
        if (!$connection
            || !red_addon_valid_package_id($packageId)
            || !red_addon_registry_storage_available($connection)
        ) {
            return [];
        }
        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT PackageID, MigrationID, MigrationPath, Checksum,
                        AppliedByAdminRecordID, AppliedAt, ExecutionMs
                 FROM RED_Addon_Migrations
                 WHERE PackageID=?
                 ORDER BY MigrationID ASC'
            );
            if (!$stmt) {
                return [];
            }
            mysqli_stmt_bind_param($stmt, 's', $packageId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return [];
            }
            $result = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[(string) $row['MigrationID']] = $row;
            }
            mysqli_stmt_close($stmt);
            return $rows;
        } catch (Throwable $throwable) {
            error_log('RED-CMS add-on migration ledger lookup failed: ' . $throwable->getMessage());
            return [];
        }
    }
}

if (!function_exists('red_addon_registry_installations')) {
    function red_addon_registry_installations($connection)
    {
        if (!$connection || !red_addon_registry_storage_available($connection)) {
            return [];
        }
        try {
            $result = mysqli_query(
                $connection,
                'SELECT PackageID, PackageVersion, PackageType, ManifestSHA256,
                        InventorySHA256, LifecycleState,
                        InstalledByAdminRecordID, InstalledAt,
                        UpdatedByAdminRecordID, UpdatedAt
                 FROM RED_Addon_Installations
                 ORDER BY PackageID ASC'
            );
            $rows = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[(string) $row['PackageID']] = $row;
            }
            if ($result) {
                mysqli_free_result($result);
            }
            return $rows;
        } catch (Throwable $throwable) {
            error_log('RED-CMS add-on installation catalog lookup failed: ' . $throwable->getMessage());
            return [];
        }
    }
}

if (!function_exists('red_addon_registry_package_report')) {
    function red_addon_registry_package_report($connection, array $package)
    {
        $packageId = isset($package['id']) && is_string($package['id'])
            ? $package['id']
            : '';
        $report = [
            'id' => $packageId,
            'status' => 'invalid',
            'installed' => false,
            'lifecycleState' => '',
            'loadable' => false,
            'pendingMigrations' => [],
            'orphanedMigrations' => [],
            'errors' => [],
            'warnings' => [],
        ];

        if (!red_addon_registry_storage_available($connection)) {
            $report['status'] = 'storage_unavailable';
            $report['errors'][] = 'Add-on registry storage is unavailable.';
            return $report;
        }
        if (empty($package['valid'])) {
            $report['status'] = 'package_invalid';
            $report['errors'] = array_values($package['errors'] ?? []);
            return $report;
        }

        $snapshot = red_addon_registry_snapshot($package);
        if ($snapshot === null) {
            $report['status'] = 'snapshot_invalid';
            $report['errors'][] = 'Validated package could not produce a registry snapshot.';
            return $report;
        }
        $report['id'] = $snapshot['id'];
        $installation = red_addon_registry_installation($connection, $snapshot['id']);
        if ($installation === null) {
            $report['status'] = 'discovered_valid';
            return $report;
        }

        $report['installed'] = true;
        $report['lifecycleState'] = (string) ($installation['LifecycleState'] ?? '');
        if (!red_addon_registry_valid_lifecycle_state($report['lifecycleState'])) {
            $report['errors'][] = 'Registry contains an unknown lifecycle state.';
        }
        $identityChecks = [
            'PackageVersion' => $snapshot['version'],
            'PackageType' => $snapshot['type'],
            'ManifestSHA256' => $snapshot['manifestSha256'],
            'InventorySHA256' => $snapshot['inventorySha256'],
        ];
        foreach ($identityChecks as $field => $expected) {
            $actual = isset($installation[$field]) && is_scalar($installation[$field])
                ? (string) $installation[$field]
                : '';
            if (!hash_equals((string) $expected, $actual)) {
                $report['errors'][] = 'Recorded package identity differs at ' . $field . '.';
            }
        }

        $appliedMigrations = red_addon_registry_migrations($connection, $snapshot['id']);
        foreach ($snapshot['migrations'] as $migrationId => $migration) {
            if (!isset($appliedMigrations[$migrationId])) {
                $report['pendingMigrations'][] = $migrationId;
                continue;
            }
            $applied = $appliedMigrations[$migrationId];
            if (!hash_equals($migration['path'], (string) ($applied['MigrationPath'] ?? ''))
                || !hash_equals($migration['sha256'], (string) ($applied['Checksum'] ?? ''))
            ) {
                $report['errors'][] = 'Applied migration drift detected: ' . $migrationId . '.';
            }
        }
        foreach ($appliedMigrations as $migrationId => $applied) {
            if (!isset($snapshot['migrations'][$migrationId])) {
                $report['orphanedMigrations'][] = $migrationId;
                $report['errors'][] = 'Applied migration is absent from the manifest: ' . $migrationId . '.';
            }
        }

        sort($report['pendingMigrations'], SORT_STRING);
        sort($report['orphanedMigrations'], SORT_STRING);
        if ($report['errors'] !== []) {
            $report['status'] = 'registry_drift';
        } elseif ($report['lifecycleState'] === 'installing') {
            $report['status'] = 'installation_incomplete';
            $report['warnings'][] = 'Installation has not reached its disabled completion state.';
        } elseif ($report['lifecycleState'] === 'installation_failed') {
            $report['status'] = 'installation_failed';
            $report['warnings'][] = 'Installation failed and requires an exact reviewed resume.';
        } elseif ($report['pendingMigrations'] !== []) {
            $report['status'] = 'migration_pending';
        } elseif ($report['lifecycleState'] === 'installed_disabled') {
            $report['status'] = 'installed_disabled_current';
        } elseif ($report['lifecycleState'] === 'uninstalled') {
            $report['status'] = 'uninstalled_current';
        } elseif ($report['lifecycleState'] === 'enabled') {
            $report['status'] = 'enabled_runtime_unavailable';
            $report['warnings'][] = 'Enabled state is recorded, but no package runtime loader exists.';
        }

        return $report;
    }
}

if (!function_exists('red_addon_registry_catalog_report')) {
    function red_addon_registry_catalog_report($connection, array $catalog)
    {
        $catalogErrors = array_values(array_filter(
            array_merge(
                is_array($catalog['errors'] ?? null) ? $catalog['errors'] : [],
                is_array($catalog['dependency']['errors'] ?? null)
                    ? $catalog['dependency']['errors']
                    : []
            ),
            'is_string'
        ));
        $catalogWarnings = array_values(array_filter(
            array_merge(
                is_array($catalog['warnings'] ?? null) ? $catalog['warnings'] : [],
                is_array($catalog['dependency']['warnings'] ?? null)
                    ? $catalog['dependency']['warnings']
                    : []
            ),
            'is_string'
        ));
        $report = [
            'valid' => $catalogErrors === [],
            'packages' => [],
            'errors' => $catalogErrors,
            'warnings' => $catalogWarnings,
        ];
        if (!red_addon_registry_storage_available($connection)) {
            $report['valid'] = false;
            $report['errors'][] = 'Add-on registry storage is unavailable.';
            return $report;
        }

        foreach ($catalog['packages'] ?? [] as $packageId => $package) {
            $packageReport = red_addon_registry_package_report($connection, $package);
            $report['packages'][$packageId] = $packageReport;
            if ($packageReport['errors'] !== []) {
                $report['valid'] = false;
            }
        }

        $installations = red_addon_registry_installations($connection);
        foreach ($installations as $packageId => $installation) {
            if (isset($report['packages'][$packageId])) {
                continue;
            }
            $report['packages'][$packageId] = [
                'id' => $packageId,
                'status' => 'package_code_missing',
                'installed' => true,
                'lifecycleState' => (string) ($installation['LifecycleState'] ?? ''),
                'loadable' => false,
                'pendingMigrations' => [],
                'orphanedMigrations' => [],
                'errors' => ['Installed package code is missing from the server-owned add-on root.'],
                'warnings' => [],
            ];
            $report['valid'] = false;
        }

        ksort($report['packages'], SORT_STRING);
        return $report;
    }
}

?>
