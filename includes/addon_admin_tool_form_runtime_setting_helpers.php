<?php
/**
 * Core-owned runtime settings for one exact administrator form binding.
 *
 * The resolver derives the package and keys from the enabled request-local
 * form binding. It exposes no caller-selected package or setting lookup and
 * never returns secret references.
 */

require_once __DIR__ . '/addon_admin_tool_form_preflight_helpers.php';
require_once __DIR__ . '/addon_setting_storage_helpers.php';

if (!class_exists('RED_Addon_Admin_Tool_Form_Runtime_Settings', false)) {
    final class RED_Addon_Admin_Tool_Form_Runtime_Settings
    {
        private array $values;
        private string $stateSha256;

        public function __construct(array $values, string $stateSha256)
        {
            if (!red_addon_valid_sha256($stateSha256)
                || count($values) > 32
                || (array_is_list($values) && $values !== [])
            ) {
                throw new InvalidArgumentException(
                    'Administrator form runtime settings are invalid.'
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
                        'Administrator form runtime setting value is invalid.'
                    );
                }
            }
            $this->values = $values;
            $this->stateSha256 = $stateSha256;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }

        public function value(string $key)
        {
            if (!$this->has($key)) {
                throw new OutOfBoundsException(
                    'Administrator form runtime setting is unavailable.'
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
    }
}

if (!function_exists('red_addon_admin_tool_form_runtime_setting_result')) {
    function red_addon_admin_tool_form_runtime_setting_result($reason)
    {
        return [
            'resolved' => false,
            'settings' => null,
            'stateSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_runtime_setting_hash')) {
    function red_addon_admin_tool_form_runtime_setting_hash(
        $packageId,
        $toolId,
        $formId,
        $contractSha256,
        array $values
    ) {
        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_capability($toolId)
            || !red_addon_valid_capability($formId)
            || !red_addon_valid_sha256($contractSha256)
        ) {
            return '';
        }
        try {
            $encoded = json_encode(
                [
                    'schema' => 1,
                    'package' => $packageId,
                    'tool' => $toolId,
                    'form' => $formId,
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

if (!function_exists('red_addon_admin_tool_form_runtime_settings_resolve')) {
    function red_addon_admin_tool_form_runtime_settings_resolve(
        $connection,
        array $binding
    ) {
        $result = red_addon_admin_tool_form_runtime_setting_result(
            'binding_invalid'
        );
        $packageId = $binding['package'] ?? null;
        $toolId = $binding['tool'] ?? null;
        $formId = $binding['form'] ?? null;
        $contract = $binding['contract'] ?? null;
        $manifest = is_string($packageId)
            ? red_addon_runtime_manifest($packageId)
            : null;
        $currentContract = is_array($manifest)
            ? red_addon_admin_tool_form_contract(
                $manifest,
                $toolId,
                $formId
            )
            : null;
        $contractSha256 = is_array($currentContract)
            ? red_addon_admin_tool_form_contract_fingerprint($currentContract)
            : '';
        if (!$connection
            || !is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || !is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !is_string($formId)
            || !red_addon_valid_capability($formId)
            || !is_array($contract)
            || !is_array($currentContract)
            || !red_addon_valid_sha256($contractSha256)
            || !hash_equals(
                $contractSha256,
                red_addon_admin_tool_form_contract_fingerprint($contract)
            )
            || red_addon_runtime_owner('adminTools', $toolId) !== $packageId
            || red_addon_runtime_owner(
                'adminToolFormValueLoaders',
                $formId
            ) !== $packageId
        ) {
            return $result;
        }

        $keys = $currentContract['runtimeSettings'] ?? [];
        if (!is_array($keys)
            || !array_is_list($keys)
            || count($keys) > 32
        ) {
            return $result;
        }
        if ($keys === []) {
            $stateSha256 = red_addon_admin_tool_form_runtime_setting_hash(
                $packageId,
                $toolId,
                $formId,
                $contractSha256,
                []
            );
            if (!red_addon_valid_sha256($stateSha256)) {
                return $result;
            }
            $result['resolved'] = true;
            $result['settings'] =
                new RED_Addon_Admin_Tool_Form_Runtime_Settings(
                    [],
                    $stateSha256
                );
            $result['stateSha256'] = $stateSha256;
            $result['reason'] = 'resolved';
            return $result;
        }
        if (!red_addon_setting_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }

        $schema = red_addon_settings_schema($manifest);
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
                 ORDER BY SettingKey ASC'
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
            $key = (string) ($row['SettingKey'] ?? '');
            $type = (string) ($row['ValueType'] ?? '');
            $definition = $definitions[$key] ?? null;
            if (!is_array($definition)
                || isset($stored[$key])
                || !hash_equals((string) $definition['type'], $type)
            ) {
                $result['reason'] = 'stored_schema_drift';
                return $result;
            }
            if (($definition['secret'] ?? false) === true) {
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
        $stateSha256 = red_addon_admin_tool_form_runtime_setting_hash(
            $packageId,
            $toolId,
            $formId,
            $contractSha256,
            $values
        );
        if (!red_addon_valid_sha256($stateSha256)) {
            return $result;
        }
        $result['resolved'] = true;
        $result['settings'] =
            new RED_Addon_Admin_Tool_Form_Runtime_Settings(
                $values,
                $stateSha256
            );
        $result['stateSha256'] = $stateSha256;
        $result['reason'] = 'resolved';
        return $result;
    }
}

?>
