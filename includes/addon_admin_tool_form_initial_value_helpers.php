<?php
/**
 * Typed draft values for one declared administrator-form create workflow.
 *
 * This dependency-free contract allows required scalar controls to begin
 * empty while preserving the exact closed field graph, scalar types, bounds,
 * collection limits, body-size limit, and create declaration. It invokes no
 * package callback and exposes no request, endpoint, transaction, or write.
 */

require_once __DIR__ . '/addon_admin_tool_form_value_helpers.php';

if (!class_exists('RED_Addon_Admin_Tool_Form_Initial_Value_Request', false)) {
    final class RED_Addon_Admin_Tool_Form_Initial_Value_Request
    {
        private string $tool;
        private string $form;
        private RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings;

        public function __construct(
            string $tool,
            string $form,
            ?RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings = null
        ) {
            if (!red_addon_valid_capability($tool)
                || !red_addon_valid_capability($form)
            ) {
                throw new InvalidArgumentException(
                    'Administrator form initial-value request is invalid.'
                );
            }
            $this->tool = $tool;
            $this->form = $form;
            if ($runtimeSettings === null) {
                $runtimeSettings =
                    new RED_Addon_Admin_Tool_Form_Runtime_Settings(
                        [],
                        hash(
                            'sha256',
                            $tool . "\0" . $form .
                                "\0empty-runtime-settings"
                        )
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

        public function runtimeSettings(): RED_Addon_Admin_Tool_Form_Runtime_Settings
        {
            return $this->runtimeSettings;
        }
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Form_Initial_Values', false)) {
    final class RED_Addon_Admin_Tool_Form_Initial_Values
    {
        private array $values;

        private function __construct(array $values)
        {
            $this->values = $values;
        }

        public static function draft(array $values): self
        {
            return new self($values);
        }

        public function values(): array
        {
            return $this->values;
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_initial_fields')) {
    function red_addon_admin_tool_form_initial_fields(array $fields)
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                return [];
            }
            $draftField = $field;
            if (($draftField['type'] ?? '') === 'collection') {
                $childFields = red_addon_admin_tool_form_initial_fields(
                    is_array($draftField['fields'] ?? null)
                        ? $draftField['fields']
                        : []
                );
                if ($childFields === []) {
                    return [];
                }
                $draftField['fields'] = $childFields;
            } else {
                $draftField['required'] = false;
                if (array_key_exists('minLength', $draftField)) {
                    $draftField['minLength'] = 0;
                }
            }
            $normalized[] = $draftField;
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_admin_tool_form_validate_initial_values')) {
    function red_addon_admin_tool_form_validate_initial_values(
        array $contract,
        $values
    ) {
        $result = [
            'valid' => false,
            'values' => [],
            'encodedBytes' => 0,
            'nodes' => 0,
            'reason' => 'create_unavailable',
        ];
        if (!is_array($contract['create'] ?? null)
            || !is_array($contract['fields'] ?? null)
            || $contract['fields'] === []
        ) {
            return $result;
        }
        $fields = red_addon_admin_tool_form_initial_fields(
            $contract['fields']
        );
        if ($fields === []) {
            $result['reason'] = 'schema_unavailable';
            return $result;
        }
        $draftContract = $contract;
        $draftContract['fields'] = $fields;
        $validated = red_addon_admin_tool_form_validate_values(
            $draftContract,
            $values
        );
        if (($validated['valid'] ?? false) !== true) {
            $validated['reason'] = 'invalid_initial_values';
        }
        return $validated;
    }
}

if (!function_exists('red_addon_admin_tool_form_initial_value_state_hash')) {
    function red_addon_admin_tool_form_initial_value_state_hash(
        $packageId,
        $toolId,
        $formId,
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
            || !red_addon_valid_sha256($contractSha256)
            || !red_addon_valid_sha256($runtimeSettingsSha256)
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
                    'runtimeSettingsSha256' => $runtimeSettingsSha256,
                    'values' => $values,
                ],
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_admin_tool_form_initial_value_result')) {
    function red_addon_admin_tool_form_initial_value_result(
        $toolId,
        $formId,
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

if (!function_exists('red_addon_admin_tool_form_load_initial_values')) {
    function red_addon_admin_tool_form_load_initial_values(
        $connection,
        $toolId,
        $formId,
        $actorRecordId
    ) {
        $result = red_addon_admin_tool_form_initial_value_result(
            $toolId,
            $formId,
            $actorRecordId
        );
        if (!$connection
            || $result['tool'] === ''
            || $result['form'] === ''
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
            'adminToolFormInitialValueLoaders',
            $result['form']
        );
        $loader = red_addon_runtime_handler(
            'adminToolFormInitialValueLoaders',
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
            || !is_array($contract['create'] ?? null)
        ) {
            $result['reason'] = 'initial_loader_unavailable';
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
            $request = new RED_Addon_Admin_Tool_Form_Initial_Value_Request(
                $result['tool'],
                $result['form'],
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
                    'Administrator form initial-value loader altered output buffers.'
                );
            }
            if (headers_list() !== $headersBefore
                || http_response_code() !== $statusBefore
            ) {
                throw new RuntimeException(
                    'Administrator form initial-value loader altered HTTP state.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '') {
                $result['reason'] = 'initial_loader_output';
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
            error_log('RED-CMS administrator form initial-value loading failed.');
            $result['reason'] = 'initial_loader_failed';
            return $result;
        }
        if (!$loaded instanceof RED_Addon_Admin_Tool_Form_Initial_Values) {
            $result['reason'] = 'invalid_result';
            return $result;
        }
        $validated = red_addon_admin_tool_form_validate_initial_values(
            $contract,
            $loaded->values()
        );
        if (($validated['valid'] ?? false) !== true
            || !is_array($validated['values'] ?? null)
        ) {
            $result['reason'] = 'invalid_initial_values';
            return $result;
        }
        $stateSha256 = red_addon_admin_tool_form_initial_value_state_hash(
            $result['package'],
            $result['tool'],
            $result['form'],
            $result['contractSha256'],
            $result['runtimeSettingsSha256'],
            $validated['values']
        );
        if (!red_addon_valid_sha256($stateSha256)) {
            $result['reason'] = 'invalid_initial_values';
            return $result;
        }
        $result['loaded'] = true;
        $result['values'] = $validated['values'];
        $result['stateSha256'] = $stateSha256;
        $result['reason'] = 'loaded';
        return $result;
    }
}
