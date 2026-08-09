<?php
/**
 * Core-owned secret access for enabled package service requests.
 *
 * The helper resolves only the current package's declared secret-reference
 * settings from server-local values. The access object keeps the bytes in
 * private request memory, refuses serialization/debug disclosure, and exposes
 * only an internal by-reference lookup to the typed service boundary.
 */

require_once __DIR__ . '/addon_secret_resolution_helpers.php';
require_once __DIR__ . '/addon_setting_storage_helpers.php';

if (!class_exists('RED_Addon_Runtime_Secret_Access', false)) {
    final class RED_Addon_Runtime_Secret_Access
    {
        private string $packageId;
        private array $values;

        public function __construct(string $packageId, array $values)
        {
            if (!red_addon_valid_package_id($packageId)
                || $values === []
                || array_is_list($values)
                || count($values) > 200
            ) {
                throw new InvalidArgumentException(
                    'Runtime secret access configuration is invalid.'
                );
            }
            foreach ($values as $settingKey => $value) {
                if (!is_string($settingKey)
                    || !red_addon_valid_permission($settingKey)
                    || !is_string($value)
                    || $value === ''
                    || strlen($value) > 8192
                    || strpos($value, "\0") !== false
                ) {
                    throw new InvalidArgumentException(
                        'Runtime secret access value is invalid.'
                    );
                }
            }
            ksort($values, SORT_STRING);
            $this->packageId = $packageId;
            $this->values = $values;
        }

        public function packageId(): string
        {
            return $this->packageId;
        }

        public function settingCount(): int
        {
            return count($this->values);
        }

        /**
         * Resolve one package setting only through the internal by-reference
         * output. The returned result never contains secret bytes.
         */
        public function resolve(string $settingKey, &$resolvedValue = null): array
        {
            $resolvedValue = null;
            if (!red_addon_valid_permission($settingKey)) {
                return [
                    'valid' => false,
                    'resolved' => false,
                    'reason' => 'setting_invalid',
                ];
            }
            if (!array_key_exists($settingKey, $this->values)) {
                return [
                    'valid' => false,
                    'resolved' => false,
                    'reason' => 'secret_unavailable',
                ];
            }
            $resolvedValue = $this->values[$settingKey];
            return [
                'valid' => true,
                'resolved' => true,
                'reason' => 'resolved',
            ];
        }

        /**
         * Core uses this to reject secret bytes in a typed service result.
         * It returns only a boolean and does not expose the matching value.
         */
        public function containsValue(string $candidate): bool
        {
            if ($candidate === '') {
                return false;
            }
            foreach ($this->values as $value) {
                if (str_contains($candidate, $value)) {
                    return true;
                }
            }
            return false;
        }

        public function __serialize(): array
        {
            throw new LogicException(
                'Runtime secret access cannot be serialized.'
            );
        }

        public function __debugInfo(): array
        {
            return [
                'packageId' => $this->packageId,
                'settingCount' => count($this->values),
            ];
        }
    }
}

if (!function_exists('red_addon_runtime_secret_access_result')) {
    function red_addon_runtime_secret_access_result($reason)
    {
        return [
            'valid' => false,
            'packageId' => '',
            'settingCount' => 0,
            'resolvedCount' => 0,
            'stateSha256' => '',
            'access' => null,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_runtime_secret_access_rows')) {
    function red_addon_runtime_secret_access_rows(
        $connection,
        $packageId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=?
                 ORDER BY SettingKey ASC'
            );
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return null;
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
            return $rows;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_runtime_secret_access_for_package')) {
    /**
     * Build one immutable request-local access object for an enabled package.
     * The result is value-free; only the private access object carries bytes.
     */
    function red_addon_runtime_secret_access_for_package(
        $connection,
        array $package,
        $requireEnabled = true
    ) {
        $result = red_addon_runtime_secret_access_result(
            'runtime_secret_unavailable'
        );
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        if (!is_array($snapshot) || !is_array($manifest)) {
            $result['reason'] = 'package_invalid';
            return $result;
        }
        $result['packageId'] = $snapshot['id'];
        $schema = red_addon_settings_schema($manifest);
        if (!is_array($schema)) {
            $result['reason'] = 'settings_unavailable';
            return $result;
        }
        $secretDefinitions = [];
        $definitions = [];
        foreach ($schema as $definition) {
            $key = $definition['key'] ?? null;
            if (!is_string($key)) {
                $result['reason'] = 'settings_unavailable';
                return $result;
            }
            $definitions[$key] = $definition;
            if (($definition['type'] ?? '') === 'secret-reference') {
                $secretDefinitions[$key] = $definition;
            }
        }
        $result['settingCount'] = count($secretDefinitions);
        if ($secretDefinitions === []) {
            $result['valid'] = true;
            $result['reason'] = 'not_applicable';
            return $result;
        }
        if (!red_addon_setting_storage_available($connection)) {
            $result['reason'] = 'settings_storage_unavailable';
            return $result;
        }
        $installation = red_addon_registry_installation(
            $connection,
            $snapshot['id']
        );
        if (!is_array($installation)
            || !in_array(
                (string) ($installation['LifecycleState'] ?? ''),
                $requireEnabled
                    ? ['enabled']
                    : ['installed_disabled', 'enabled'],
                true
            )
            || !hash_equals(
                $snapshot['version'],
                (string) ($installation['PackageVersion'] ?? '')
            )
            || !hash_equals(
                $snapshot['type'],
                (string) ($installation['PackageType'] ?? '')
            )
            || !hash_equals(
                $snapshot['manifestSha256'],
                (string) ($installation['ManifestSHA256'] ?? '')
            )
            || !hash_equals(
                $snapshot['inventorySha256'],
                (string) ($installation['InventorySHA256'] ?? '')
            )
        ) {
            $result['reason'] = 'installation_identity_mismatch';
            return $result;
        }

        $rows = red_addon_runtime_secret_access_rows(
            $connection,
            $snapshot['id']
        );
        if (!is_array($rows)) {
            $result['reason'] = 'settings_storage_unavailable';
            return $result;
        }
        $configured = [];
        $secretReferences = [];
        foreach ($rows as $row) {
            $key = (string) ($row['SettingKey'] ?? '');
            $type = (string) ($row['ValueType'] ?? '');
            $definition = $definitions[$key] ?? null;
            if (!is_array($definition) || array_key_exists($key, $configured)
                || $type !== ($definition['type'] ?? '')
            ) {
                $result['reason'] = 'stored_schema_drift';
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
                    $result['reason'] = 'stored_value_invalid';
                    return $result;
                }
                $configured[$key] = $reference;
                $secretReferences[$key] = $reference;
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
            $configured[$key] = $value;
        }
        foreach ($schema as $definition) {
            $key = $definition['key'];
            if (array_key_exists($key, $configured)) {
                continue;
            }
            if (array_key_exists('default', $definition)
                && $definition['default'] !== null
            ) {
                $configured[$key] = $definition['default'];
                continue;
            }
            $result['reason'] = ($definition['type'] ?? '') === 'secret-reference'
                ? 'secret_unconfigured'
                : 'settings_unconfigured';
            return $result;
        }
        $validated = red_addon_settings_validate_values(
            $manifest,
            $configured
        );
        if (empty($validated['valid'])) {
            $result['reason'] = 'settings_unconfigured';
            return $result;
        }
        $declarations = red_addon_secret_reference_declarations();
        $inventory = red_addon_secret_value_inventory();
        if (empty($declarations['valid']) || empty($inventory['valid'])) {
            $result['reason'] = 'secret_unavailable';
            return $result;
        }
        $resolvedValues = [];
        foreach ($secretReferences as $key => $reference) {
            $resolvedValue = null;
            $resolved = red_addon_secret_resolve(
                $reference,
                $declarations,
                $inventory,
                $resolvedValue
            );
            if (empty($resolved['valid'])
                || empty($resolved['resolved'])
                || !is_string($resolvedValue)
                || $resolvedValue === ''
            ) {
                $result['reason'] = 'secret_unavailable';
                return $result;
            }
            $resolvedValues[$key] = $resolvedValue;
        }
        $state = red_addon_setting_current_state(
            $connection,
            $manifest,
            $snapshot['id']
        );
        if (empty($state['valid'])
            || !red_addon_valid_sha256($state['stateSha256'] ?? '')
        ) {
            $result['reason'] = 'settings_state_invalid';
            return $result;
        }
        try {
            $access = new RED_Addon_Runtime_Secret_Access(
                $snapshot['id'],
                $resolvedValues
            );
        } catch (Throwable $throwable) {
            $result['reason'] = 'secret_access_invalid';
            return $result;
        }
        $result['valid'] = true;
        $result['resolvedCount'] = count($resolvedValues);
        $result['stateSha256'] = $state['stateSha256'];
        $result['access'] = $access;
        $result['reason'] = 'ready';
        return $result;
    }
}

if (!function_exists('red_addon_runtime_secret_preflight_evidence')) {
    /**
     * Return only non-secret enablement evidence; never return the access
     * object or any resolved bytes through this diagnostic boundary.
     */
    function red_addon_runtime_secret_preflight_evidence(
        $connection,
        array $package
    ) {
        $accessResult = red_addon_runtime_secret_access_for_package(
            $connection,
            $package,
            false
        );
        $evidence = [
            'valid' => !empty($accessResult['valid']),
            'ready' => false,
            'packageId' => (string) ($accessResult['packageId'] ?? ''),
            'settingCount' => (int) ($accessResult['settingCount'] ?? 0),
            'resolvedCount' => (int) ($accessResult['resolvedCount'] ?? 0),
            'stateSha256' => (string) ($accessResult['stateSha256'] ?? ''),
            'reason' => (string) ($accessResult['reason'] ?? 'unavailable'),
        ];
        $access = $accessResult['access'] ?? null;
        $evidence['ready'] = $access instanceof RED_Addon_Runtime_Secret_Access;
        unset($accessResult['access'], $access);
        return $evidence;
    }
}

if (!function_exists('red_addon_runtime_secret_data_is_safe')) {
    function red_addon_runtime_secret_data_is_safe(
        $value,
        ?RED_Addon_Runtime_Secret_Access $access,
        $depth = 0,
        &$nodes = 0
    ): bool {
        if ($depth > 4 || ++$nodes > 128) {
            return false;
        }
        if ($value === null || is_bool($value) || is_int($value)) {
            return true;
        }
        if (is_string($value)) {
            return $access === null || !$access->containsValue($value);
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $key => $item) {
            if (!is_int($key)
                && (!is_string($key)
                    || ($access !== null && $access->containsValue($key)))
            ) {
                return false;
            }
            if (!red_addon_runtime_secret_data_is_safe(
                $item,
                $access,
                $depth + 1,
                $nodes
            )) {
                return false;
            }
        }
        return true;
    }
}

?>
