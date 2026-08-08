<?php
/**
 * Permission-scoped current-value loading for administrator tool forms.
 *
 * Core invokes only the exact enabled form-value loader after the existing
 * form preflight, contains its output/HTTP state, validates a complete typed
 * value graph against the closed manifest schema, and computes deterministic
 * state evidence. The trusted first-party loader is read-only. This helper
 * exposes no endpoint, editable control, request body, CSRF operation, or
 * write path.
 */

require_once __DIR__ . '/addon_admin_tool_form_runtime_setting_helpers.php';

if (!class_exists('RED_Addon_Admin_Tool_Form_Value_Request', false)) {
    final class RED_Addon_Admin_Tool_Form_Value_Request
    {
        private string $tool;
        private string $form;
        private int $targetRecordId;
        private RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings;

        public function __construct(
            string $tool,
            string $form,
            int $targetRecordId,
            ?RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings = null
        ) {
            if (!red_addon_valid_capability($tool)
                || !red_addon_valid_capability($form)
                || $targetRecordId < 1
                || $targetRecordId > 2147483647
            ) {
                throw new InvalidArgumentException(
                    'Administrator form value request is invalid.'
                );
            }
            $this->tool = $tool;
            $this->form = $form;
            $this->targetRecordId = $targetRecordId;
            if ($runtimeSettings === null) {
                $stateSha256 = hash(
                    'sha256',
                    $tool . "\0" . $form . "\0empty-runtime-settings"
                );
                $runtimeSettings =
                    new RED_Addon_Admin_Tool_Form_Runtime_Settings(
                        [],
                        $stateSha256
                    );
            }
            $this->runtimeSettings = $runtimeSettings;
        }

        public function tool(): string
        {
            return $this->tool;
        }

        public function form(): string
        {
            return $this->form;
        }

        public function targetRecordId(): int
        {
            return $this->targetRecordId;
        }

        public function runtimeSettings(): RED_Addon_Admin_Tool_Form_Runtime_Settings
        {
            return $this->runtimeSettings;
        }
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Form_Values', false)) {
    final class RED_Addon_Admin_Tool_Form_Values
    {
        private array $values;

        private function __construct(array $values)
        {
            $this->values = $values;
        }

        public static function current(array $values): self
        {
            return new self($values);
        }

        public function values(): array
        {
            return $this->values;
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_value_scalar')) {
    function red_addon_admin_tool_form_value_scalar(array $field, $value)
    {
        $required = ($field['required'] ?? false) === true;
        if ($value === null) {
            return $required ? [false, null] : [true, null];
        }
        $type = (string) ($field['type'] ?? '');
        if ($type === 'integer') {
            return is_int($value)
                && $value >= ($field['minimum'] ?? PHP_INT_MAX)
                && $value <= ($field['maximum'] ?? PHP_INT_MIN)
                    ? [true, $value]
                    : [false, null];
        }
        if ($type === 'boolean') {
            return is_bool($value)
                ? [true, $value]
                : [false, null];
        }
        if (!is_string($value)
            || preg_match('//u', $value) !== 1
        ) {
            return [false, null];
        }
        $allowsNewlines = $type === 'textarea';
        $unsafe = preg_match(
            $allowsNewlines
                ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/'
                : '/[\x00-\x1F\x7F]/',
            $value
        ) === 1;
        if ($unsafe) {
            return [false, null];
        }
        if (in_array(
            $type,
            ['text', 'textarea', 'url', 'email', 'media-reference'],
            true
        )) {
            $minimum = is_int($field['minLength'] ?? null)
                ? $field['minLength']
                : 0;
            $maximum = $field['maxLength'] ?? 0;
            if (!is_int($maximum)
                || strlen($value) < $minimum
                || strlen($value) > $maximum
                || ($required && $value === '')
            ) {
                return [false, null];
            }
            if ($type === 'url') {
                $parts = parse_url($value);
                if (trim($value) !== $value
                    || filter_var($value, FILTER_VALIDATE_URL) === false
                    || !is_array($parts)
                    || !in_array(
                        strtolower((string) ($parts['scheme'] ?? '')),
                        ['http', 'https'],
                        true
                    )
                    || !is_string($parts['host'] ?? null)
                    || $parts['host'] === ''
                    || isset($parts['user'])
                    || isset($parts['pass'])
                ) {
                    return [false, null];
                }
            } elseif ($type === 'email'
                && (trim($value) !== $value
                    || filter_var($value, FILTER_VALIDATE_EMAIL) === false)
            ) {
                return [false, null];
            } elseif ($type === 'media-reference'
                && preg_match(
                    '/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/D',
                    $value
                ) !== 1
            ) {
                return [false, null];
            }
            return [true, $value];
        }
        if ($type === 'select') {
            $allowed = [];
            foreach ($field['options'] ?? [] as $option) {
                if (is_array($option)
                    && is_string($option['value'] ?? null)
                ) {
                    $allowed[] = $option['value'];
                }
            }
            return in_array($value, $allowed, true)
                ? [true, $value]
                : [false, null];
        }
        if ($type === 'date') {
            return preg_match(
                '/\A(\d{4})-(\d{2})-(\d{2})\z/D',
                $value,
                $parts
            ) === 1
                && checkdate(
                    (int) $parts[2],
                    (int) $parts[3],
                    (int) $parts[1]
                )
                    ? [true, $value]
                    : [false, null];
        }
        if ($type === 'datetime') {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i:sP',
                $value
            );
            $errors = DateTimeImmutable::getLastErrors();
            return $date !== false
                && ($errors === false
                    || (($errors['warning_count'] ?? 0) === 0
                        && ($errors['error_count'] ?? 0) === 0))
                && $date->format('Y-m-d\TH:i:sP') === $value
                    ? [true, $value]
                    : [false, null];
        }
        return [false, null];
    }
}

if (!function_exists('red_addon_admin_tool_form_value_object')) {
    function red_addon_admin_tool_form_value_object(
        array $fields,
        $values,
        int &$nodes
    ) {
        if (!is_array($values)
            || (array_is_list($values) && $values !== [])
        ) {
            return null;
        }
        $expectedKeys = [];
        foreach ($fields as $field) {
            if (!is_array($field)
                || !is_string($field['key'] ?? null)
            ) {
                return null;
            }
            $expectedKeys[] = $field['key'];
        }
        $actualKeys = array_keys($values);
        sort($expectedKeys, SORT_STRING);
        sort($actualKeys, SORT_STRING);
        if ($expectedKeys !== $actualKeys) {
            return null;
        }

        $normalized = [];
        foreach ($fields as $field) {
            $nodes++;
            if ($nodes > 4096) {
                return null;
            }
            $key = $field['key'];
            $value = $values[$key];
            if (($field['type'] ?? '') === 'collection') {
                if (!is_array($value)
                    || !array_is_list($value)
                    || count($value) < ($field['minItems'] ?? PHP_INT_MAX)
                    || count($value) > ($field['maxItems'] ?? -1)
                ) {
                    return null;
                }
                $items = [];
                foreach ($value as $item) {
                    $nodes++;
                    if ($nodes > 4096) {
                        return null;
                    }
                    $normalizedItem = red_addon_admin_tool_form_value_object(
                        $field['fields'] ?? [],
                        $item,
                        $nodes
                    );
                    if (!is_array($normalizedItem)) {
                        return null;
                    }
                    $items[] = $normalizedItem;
                }
                $normalized[$key] = $items;
                continue;
            }
            [$valid, $normalizedValue] =
                red_addon_admin_tool_form_value_scalar($field, $value);
            if (!$valid) {
                return null;
            }
            $normalized[$key] = $normalizedValue;
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_admin_tool_form_validate_values')) {
    function red_addon_admin_tool_form_validate_values(
        array $contract,
        $values
    ) {
        $result = [
            'valid' => false,
            'values' => [],
            'encodedBytes' => 0,
            'nodes' => 0,
            'reason' => 'invalid_values',
        ];
        if (!is_array($contract['fields'] ?? null)
            || $contract['fields'] === []
            || !is_int($contract['maxBodyBytes'] ?? null)
            || $contract['maxBodyBytes'] < 1
            || $contract['maxBodyBytes'] > 262144
        ) {
            $result['reason'] = 'schema_unavailable';
            return $result;
        }
        $nodes = 0;
        $normalized = red_addon_admin_tool_form_value_object(
            $contract['fields'],
            $values,
            $nodes
        );
        if (!is_array($normalized)) {
            $result['nodes'] = $nodes;
            return $result;
        }
        try {
            $encoded = json_encode(
                $normalized,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return $result;
        }
        if (!is_string($encoded)
            || strlen($encoded) > $contract['maxBodyBytes']
        ) {
            $result['nodes'] = $nodes;
            $result['encodedBytes'] = is_string($encoded)
                ? strlen($encoded)
                : 0;
            return $result;
        }
        $result['valid'] = true;
        $result['values'] = $normalized;
        $result['encodedBytes'] = strlen($encoded);
        $result['nodes'] = $nodes;
        $result['reason'] = 'valid';
        return $result;
    }
}

if (!function_exists('red_addon_admin_tool_form_value_state_hash')) {
    function red_addon_admin_tool_form_value_state_hash(
        $packageId,
        $toolId,
        $formId,
        $targetRecordId,
        $contractSha256,
        $runtimeSettingsSha256,
        array $values
    ) {
        if (!is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || !is_string($toolId)
            || !red_addon_valid_capability($toolId)
            || !is_string($formId)
            || !red_addon_valid_capability($formId)
            || !is_int($targetRecordId)
            || $targetRecordId < 1
            || $targetRecordId > 2147483647
            || !red_addon_valid_sha256($contractSha256)
            || !red_addon_valid_sha256($runtimeSettingsSha256)
        ) {
            return '';
        }
        $encoded = json_encode(
            [
                'schema' => 2,
                'package' => $packageId,
                'tool' => $toolId,
                'form' => $formId,
                'targetRecordId' => (string) $targetRecordId,
                'contractSha256' => $contractSha256,
                'runtimeSettingsSha256' => $runtimeSettingsSha256,
                'values' => $values,
            ],
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_admin_tool_form_value_result')) {
    function red_addon_admin_tool_form_value_result(
        $toolId,
        $formId,
        $targetRecordId,
        $actorRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'authorized' => false,
            'invoked' => false,
            'loaded' => false,
            'tool' => is_string($toolId)
                && red_addon_valid_capability($toolId)
                    ? $toolId
                    : '',
            'form' => is_string($formId)
                && red_addon_valid_capability($formId)
                    ? $formId
                    : '',
            'package' => '',
            'targetRecordId' => is_int($targetRecordId)
                && $targetRecordId >= 1
                && $targetRecordId <= 2147483647
                    ? $targetRecordId
                    : 0,
            'actorRecordId' => red_addon_admin_tool_form_actor_record_id(
                $actorRecordId
            ),
            'permission' => '',
            'contractSha256' => '',
            'planSha256' => '',
            'runtimeSettingsSha256' => '',
            'stateSha256' => '',
            'values' => [],
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_load_values')) {
    function red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        $targetRecordId,
        $actorRecordId
    ) {
        $result = red_addon_admin_tool_form_value_result(
            $toolId,
            $formId,
            $targetRecordId,
            $actorRecordId
        );
        if (!$connection
            || $result['tool'] === ''
            || $result['form'] === ''
            || $result['targetRecordId'] < 1
            || $result['actorRecordId'] < 1
        ) {
            return $result;
        }
        $preflight = red_addon_admin_tool_form_preflight(
            $connection,
            $result['tool'],
            $result['form'],
            $result['actorRecordId']
        );
        $result['authorized'] = ($preflight['authorized'] ?? false) === true;
        $result['package'] = is_string($preflight['package'] ?? null)
            ? $preflight['package']
            : '';
        $result['permission'] = is_string($preflight['permission'] ?? null)
            ? $preflight['permission']
            : '';
        $result['contractSha256'] =
            is_string($preflight['contractSha256'] ?? null)
                ? $preflight['contractSha256']
                : '';
        $result['planSha256'] =
            is_string($preflight['planSha256'] ?? null)
                ? $preflight['planSha256']
                : '';
        if (($preflight['ready'] ?? false) !== true) {
            $result['reason'] = (string) (
                $preflight['reason'] ?? 'preflight_failed'
            );
            return $result;
        }

        $loaderOwner = red_addon_runtime_owner(
            'adminToolFormValueLoaders',
            $result['form']
        );
        $loader = red_addon_runtime_handler(
            'adminToolFormValueLoaders',
            $result['form']
        );
        $manifest = red_addon_runtime_manifest($result['package']);
        $contract = is_array($manifest)
            ? red_addon_admin_tool_form_contract(
                $manifest,
                $result['tool'],
                $result['form']
            )
            : null;
        if (!is_string($loaderOwner)
            || !hash_equals($result['package'], $loaderOwner)
            || !is_callable($loader)
            || !is_array($contract)
            || !is_array($contract['fields'] ?? null)
            || $contract['fields'] === []
        ) {
            $result['reason'] = 'loader_unavailable';
            return $result;
        }
        $binding = red_addon_admin_tool_form_preflight_binding(
            $result['tool'],
            $result['form']
        );
        $runtimeSettings = is_array($binding)
            ? red_addon_admin_tool_form_runtime_settings_resolve(
                $connection,
                $binding
            )
            : ['resolved' => false, 'reason' => 'binding_invalid'];
        if (($runtimeSettings['resolved'] ?? false) !== true
            || !($runtimeSettings['settings']
                instanceof RED_Addon_Admin_Tool_Form_Runtime_Settings)
            || !red_addon_valid_sha256(
                $runtimeSettings['stateSha256'] ?? null
            )
        ) {
            $result['reason'] = 'runtime_settings_unavailable';
            return $result;
        }
        $result['runtimeSettingsSha256'] =
            $runtimeSettings['stateSha256'];
        try {
            $request = new RED_Addon_Admin_Tool_Form_Value_Request(
                $result['tool'],
                $result['form'],
                $result['targetRecordId'],
                $runtimeSettings['settings']
            );
        } catch (Throwable $throwable) {
            $result['reason'] = 'invalid_request';
            return $result;
        }

        $bufferLevel = ob_get_level();
        $headersBefore = headers_list();
        $statusBefore = http_response_code();
        try {
            ob_start();
            $result['invoked'] = true;
            $loaded = $loader($connection, $request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Administrator form value loader altered output buffers.'
                );
            }
            if (headers_list() !== $headersBefore
                || http_response_code() !== $statusBefore
            ) {
                throw new RuntimeException(
                    'Administrator form value loader altered HTTP state.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '') {
                $result['reason'] = 'loader_output';
                return $result;
            }
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            red_addon_admin_tool_restore_http_state(
                $headersBefore,
                $statusBefore
            );
            error_log('RED-CMS administrator form value loading failed.');
            $result['reason'] = 'loader_failed';
            return $result;
        }
        if (!$loaded instanceof RED_Addon_Admin_Tool_Form_Values) {
            $result['reason'] = 'invalid_result';
            return $result;
        }
        $validated = red_addon_admin_tool_form_validate_values(
            $contract,
            $loaded->values()
        );
        if (($validated['valid'] ?? false) !== true
            || !is_array($validated['values'] ?? null)
        ) {
            $result['reason'] = 'invalid_values';
            return $result;
        }
        $stateSha256 = red_addon_admin_tool_form_value_state_hash(
            $result['package'],
            $result['tool'],
            $result['form'],
            $result['targetRecordId'],
            $result['contractSha256'],
            $result['runtimeSettingsSha256'],
            $validated['values']
        );
        if (!red_addon_valid_sha256($stateSha256)) {
            $result['reason'] = 'invalid_values';
            return $result;
        }
        $result['loaded'] = true;
        $result['values'] = $validated['values'];
        $result['stateSha256'] = $stateSha256;
        $result['reason'] = 'loaded';
        return $result;
    }
}

?>
