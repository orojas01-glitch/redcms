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
