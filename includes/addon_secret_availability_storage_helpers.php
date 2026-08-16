<?php
/**
 * Read-only secret-reference availability evidence from per-client storage.
 *
 * This bridge rebuilds typed configuration from the current package rows and
 * delegates to the value-free declaration check. It never resolves or returns
 * a secret value, executes package code, or changes stored configuration.
 */

require_once __DIR__ . '/addon_secret_availability_helpers.php';
require_once __DIR__ . '/addon_setting_storage_helpers.php';

if (!function_exists('red_addon_secret_availability_storage_evidence')) {
    function red_addon_secret_availability_storage_evidence(
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

?>
