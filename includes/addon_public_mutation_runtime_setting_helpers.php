<?php
/**
 * Core-owned runtime settings for one exact public-mutation binding.
 *
 * The resolver derives the package, route, mutation, and setting keys from a
 * current enabled runtime binding. A package never selects a client, package,
 * setting key, or core storage table. The runner calls this only inside its
 * lifecycle/package-locked transaction and receives an immutable, typed,
 * non-secret value object for its registered state loader and handler.
 */

require_once __DIR__ . '/addon_public_mutation_preflight_helpers.php';
require_once __DIR__ . '/addon_setting_storage_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';

if (!class_exists('RED_Addon_Public_Mutation_Runtime_Settings', false)) {
    final class RED_Addon_Public_Mutation_Runtime_Settings
    {
        private array $values;
        private string $stateSha256;
        private bool $declared;

        public function __construct(
            array $values,
            string $stateSha256,
            bool $declared
        ) {
            if (!red_addon_valid_sha256($stateSha256)
                || count($values) > 16
                || (array_is_list($values) && $values !== [])
                || ($declared && $values === [])
                || (!$declared && $values !== [])
            ) {
                throw new InvalidArgumentException(
                    'Public mutation runtime settings are invalid.'
                );
            }
            foreach ($values as $key => $value) {
                if (!is_string($key)
                    || !red_addon_valid_permission($key)
                    || !(is_string($value)
                        || is_bool($value)
                        || is_int($value))
                ) {
                    throw new InvalidArgumentException(
                        'Public mutation runtime setting value is invalid.'
                    );
                }
            }
            $this->values = $values;
            $this->stateSha256 = $stateSha256;
            $this->declared = $declared;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }

        public function value(string $key)
        {
            if (!$this->has($key)) {
                throw new OutOfBoundsException(
                    'Public mutation runtime setting is unavailable.'
                );
            }
            return $this->values[$key];
        }

        public function values(): array
        {
            return $this->values;
        }

        public function stateSha256(): string
        {
            return $this->stateSha256;
        }

        public function declared(): bool
        {
            return $this->declared;
        }
    }
}

if (!function_exists('red_addon_public_mutation_runtime_setting_result')) {
    function red_addon_public_mutation_runtime_setting_result($reason)
    {
        return [
            'resolved' => false,
            'settings' => null,
            'stateSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_runtime_setting_transaction_active')) {
    function red_addon_public_mutation_runtime_setting_transaction_active(
        $connection
    ) {
        try {
            if (!$connection instanceof mysqli
                || !mysqli_query(
                    $connection,
                    'SAVEPOINT redcms_public_mutation_runtime_settings_guard'
                )
            ) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_public_mutation_runtime_settings_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_runtime_setting_hash')) {
    function red_addon_public_mutation_runtime_setting_hash(
        $packageId,
        $routeId,
        $mutationId,
        $contractSha256,
        array $values
    ) {
        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_capability($routeId)
            || !red_addon_valid_capability($mutationId)
            || !red_addon_valid_sha256($contractSha256)
        ) {
            return '';
        }
        try {
            $encoded = json_encode(
                [
                    'schema' => 1,
                    'purpose' => 'public-mutation-runtime-settings',
                    'package' => $packageId,
                    'route' => $routeId,
                    'mutation' => $mutationId,
                    'contractSha256' => $contractSha256,
                    'values' => $values,
                ],
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_public_mutation_runtime_settings_resolve')) {
    function red_addon_public_mutation_runtime_settings_resolve(
        $connection,
        array $binding
    ) {
        $result = red_addon_public_mutation_runtime_setting_result(
            'binding_invalid'
        );
        $packageId = $binding['packageId'] ?? null;
        $routeId = $binding['route'] ?? null;
        $mutationId = $binding['mutation'] ?? null;
        $manifest = $binding['manifest'] ?? null;
        $contract = $binding['contract'] ?? null;
        $currentManifest = is_string($packageId)
            ? red_addon_runtime_manifest($packageId)
            : null;
        $currentContract = is_array($currentManifest)
            ? red_addon_public_mutation_contract(
                $currentManifest,
                $routeId,
                $mutationId
            )
            : null;
        $contractSha256 = is_array($currentContract)
            ? red_addon_public_mutation_contract_fingerprint($currentContract)
            : '';
        if (!$connection instanceof mysqli
            || !is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || !is_string($routeId)
            || !red_addon_valid_capability($routeId)
            || !is_string($mutationId)
            || !red_addon_valid_capability($mutationId)
            || !is_array($manifest)
            || !is_array($contract)
            || !is_array($currentManifest)
            || $currentManifest !== $manifest
            || !is_array($currentContract)
            || !red_addon_valid_sha256($contractSha256)
            || !hash_equals(
                $contractSha256,
                red_addon_public_mutation_contract_fingerprint($contract)
            )
            || red_addon_runtime_owner(
                'publicMutationHandlers',
                $mutationId
            ) !== $packageId
            || red_addon_runtime_owner(
                'publicMutationStateLoaders',
                $mutationId
            ) !== $packageId
            || !red_addon_public_mutation_runtime_setting_transaction_active(
                $connection
            )
        ) {
            return $result;
        }

        $keys = $currentContract['runtimeSettings'] ?? [];
        if (!is_array($keys)
            || !array_is_list($keys)
            || count($keys) > 16
        ) {
            return $result;
        }
        if ($keys === []) {
            $stateSha256 = red_addon_public_mutation_runtime_setting_hash(
                $packageId,
                $routeId,
                $mutationId,
                $contractSha256,
                []
            );
            if (!red_addon_valid_sha256($stateSha256)) {
                return $result;
            }
            $result['resolved'] = true;
            $result['settings'] =
                new RED_Addon_Public_Mutation_Runtime_Settings(
                    [],
                    $stateSha256,
                    false
                );
            $result['stateSha256'] = $stateSha256;
            $result['reason'] = 'resolved';
            return $result;
        }
        if (!red_addon_setting_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }

        $schema = red_addon_settings_schema($currentManifest);
        if (!is_array($schema)) {
            return $result;
        }
        $definitions = [];
        foreach ($schema as $definition) {
            if (is_array($definition)
                && is_string($definition['key'] ?? null)
            ) {
                $definitions[$definition['key']] = $definition;
            }
        }
        foreach ($keys as $key) {
            $definition = is_string($key)
                ? ($definitions[$key] ?? null)
                : null;
            if (!is_array($definition)
                || ($definition['secret'] ?? false) === true
                || ($definition['type'] ?? '') === 'secret-reference'
                || (array_key_exists('default', $definition)
                    && $definition['default'] !== null)
            ) {
                return $result;
            }
        }

        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=?
                 ORDER BY SettingKey ASC FOR UPDATE'
            );
            if (!$statement) {
                $result['reason'] = 'storage_unavailable';
                return $result;
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $result['reason'] = 'storage_unavailable';
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
            $result['reason'] = 'storage_unavailable';
            return $result;
        }

        $stored = [];
        foreach ($rows as $row) {
            $key = is_string($row['SettingKey'] ?? null)
                ? $row['SettingKey']
                : '';
            $type = is_string($row['ValueType'] ?? null)
                ? $row['ValueType']
                : '';
            $definition = $definitions[$key] ?? null;
            if (!is_array($definition)
                || isset($stored[$key])
                || !hash_equals((string) ($definition['type'] ?? ''), $type)
            ) {
                $result['reason'] = 'stored_schema_drift';
                return $result;
            }
            if (($definition['secret'] ?? false) === true) {
                // A separately configured secret may coexist with the exact
                // non-secret keys declared by this mutation. It is never
                // normalized, included in the state hash, or exposed here.
                continue;
            }
            if ($row['SecretReference'] !== null
                || !is_string($row['ValueJSON'] ?? null)
            ) {
                $result['reason'] = 'stored_value_invalid';
                return $result;
            }
            try {
                $value = json_decode(
                    $row['ValueJSON'],
                    true,
                    8,
                    JSON_THROW_ON_ERROR
                );
            } catch (Throwable $throwable) {
                $result['reason'] = 'stored_value_invalid';
                return $result;
            }
            $check = red_addon_setting_value_result();
            $normalized = red_addon_setting_normalize_value(
                $definition,
                $value,
                $check
            );
            if ($check['errors'] !== [] || $normalized !== $value) {
                $result['reason'] = 'stored_value_invalid';
                return $result;
            }
            $stored[$key] = $normalized;
        }

        $values = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $stored)) {
                $result['reason'] = 'setting_unconfigured';
                return $result;
            }
            $values[$key] = $stored[$key];
        }
        $stateSha256 = red_addon_public_mutation_runtime_setting_hash(
            $packageId,
            $routeId,
            $mutationId,
            $contractSha256,
            $values
        );
        if (!red_addon_valid_sha256($stateSha256)) {
            return $result;
        }
        $result['resolved'] = true;
        $result['settings'] = new RED_Addon_Public_Mutation_Runtime_Settings(
            $values,
            $stateSha256,
            true
        );
        $result['stateSha256'] = $stateSha256;
        $result['reason'] = 'resolved';
        return $result;
    }
}

?>
