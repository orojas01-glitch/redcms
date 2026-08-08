<?php
/**
 * Atomic internal creation for one validated administrator add-on form.
 *
 * Core re-prepares the exact target-free submission under lifecycle/package
 * locks, invokes only the declared creator, requires one positive record id,
 * reloads that record through the existing value loader, and commits only when
 * the reloaded values equal the strict submitted graph.
 */

require_once __DIR__ . '/addon_admin_tool_form_create_submission_helpers.php';
require_once __DIR__ . '/addon_admin_tool_form_write_helpers.php';

if (!class_exists('RED_Addon_Admin_Tool_Form_Create_Request', false)) {
    final class RED_Addon_Admin_Tool_Form_Create_Request
    {
        private string $package;
        private string $packageVersion;
        private string $tool;
        private string $form;
        private int $actorRecordId;
        private string $initialStateSha256;
        private string $planSha256;
        private array $values;
        private RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings;

        public function __construct(
            string $package,
            string $packageVersion,
            string $tool,
            string $form,
            int $actorRecordId,
            string $initialStateSha256,
            string $planSha256,
            array $values,
            RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings
        ) {
            if (!red_addon_valid_package_id($package)
                || !red_addon_valid_semantic_version($packageVersion)
                || !red_addon_valid_capability($tool)
                || !red_addon_valid_capability($form)
                || red_addon_admin_tool_form_actor_record_id($actorRecordId) < 1
                || !red_addon_valid_sha256($initialStateSha256)
                || !red_addon_valid_sha256($planSha256)
                || $values === []
                || array_is_list($values)
            ) {
                throw new InvalidArgumentException(
                    'Administrator form create request is invalid.'
                );
            }
            $this->package = $package;
            $this->packageVersion = $packageVersion;
            $this->tool = $tool;
            $this->form = $form;
            $this->actorRecordId = $actorRecordId;
            $this->initialStateSha256 = $initialStateSha256;
            $this->planSha256 = $planSha256;
            $this->values = $values;
            $this->runtimeSettings = $runtimeSettings;
        }

        public function package(): string { return $this->package; }
        public function packageVersion(): string { return $this->packageVersion; }
        public function tool(): string { return $this->tool; }
        public function form(): string { return $this->form; }
        public function actorRecordId(): int { return $this->actorRecordId; }
        public function initialStateSha256(): string { return $this->initialStateSha256; }
        public function planSha256(): string { return $this->planSha256; }
        public function values(): array { return $this->values; }
        public function runtimeSettings(): RED_Addon_Admin_Tool_Form_Runtime_Settings
        {
            return $this->runtimeSettings;
        }
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Form_Created_Record', false)) {
    final class RED_Addon_Admin_Tool_Form_Created_Record
    {
        private int $recordId;

        private function __construct(int $recordId)
        {
            if ($recordId < 1 || $recordId > 2147483647) {
                throw new InvalidArgumentException(
                    'Administrator form created record id is invalid.'
                );
            }
            $this->recordId = $recordId;
        }

        public static function created(int $recordId): self
        {
            return new self($recordId);
        }

        public function recordId(): int
        {
            return $this->recordId;
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_create_result')) {
    function red_addon_admin_tool_form_create_result(
        $actorRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'authorized' => false,
            'prepared' => false,
            'creatorInvoked' => false,
            'executed' => false,
            'tool' => '',
            'form' => '',
            'package' => '',
            'packageVersion' => '',
            'targetRecordId' => 0,
            'actorRecordId' => red_addon_admin_tool_form_actor_record_id(
                $actorRecordId
            ),
            'permission' => '',
            'contractSha256' => '',
            'runtimeSettingsSha256' => '',
            'initialStateSha256' => '',
            'submittedValuesSha256' => '',
            'submissionPlanSha256' => '',
            'planSha256' => '',
            'stateSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_create_copy_preparation')) {
    function red_addon_admin_tool_form_create_copy_preparation(
        array &$result,
        array $prepared
    ) {
        foreach (
            [
                'authorized',
                'prepared',
                'tool',
                'form',
                'package',
                'actorRecordId',
                'permission',
                'contractSha256',
                'runtimeSettingsSha256',
                'initialStateSha256',
                'submittedValuesSha256',
            ] as $key
        ) {
            if (array_key_exists($key, $prepared)) {
                $result[$key] = $prepared[$key];
            }
        }
        $result['submissionPlanSha256'] = is_string(
            $prepared['planSha256'] ?? null
        ) ? $prepared['planSha256'] : '';
        $result['reason'] = is_string($prepared['reason'] ?? null)
            ? $prepared['reason']
            : 'form_unavailable';
    }
}

if (!function_exists('red_addon_admin_tool_form_create_tables')) {
    function red_addon_admin_tool_form_create_tables($formId)
    {
        $metadata = red_addon_runtime_metadata(
            'adminToolFormCreators',
            $formId
        );
        $tables = is_array($metadata['tables'] ?? null)
            ? $metadata['tables']
            : [];
        if ($tables === [] || count($tables) > 8) {
            return null;
        }
        $reserved = [
            'red_addon_installations',
            'red_addon_migrations',
            'red_addon_activity_log',
            'red_addon_component_revisions',
            'red_addon_admin_action_executions',
        ];
        $normalized = [];
        foreach ($tables as $table) {
            if (!is_string($table)
                || preg_match('/\ARED_Addon_[A-Za-z0-9_]{1,54}\z/', $table)
                    !== 1
                || in_array(strtolower($table), $reserved, true)
                || isset($normalized[$table])
            ) {
                return null;
            }
            $normalized[$table] = true;
        }
        $tables = array_keys($normalized);
        sort($tables, SORT_STRING);
        return $tables;
    }
}

if (!function_exists('red_addon_admin_tool_form_create_binding')) {
    function red_addon_admin_tool_form_create_binding($toolId, $formId)
    {
        $binding = red_addon_admin_tool_form_preflight_binding(
            $toolId,
            $formId
        );
        if (!is_array($binding)
            || !is_array($binding['contract']['create'] ?? null)
        ) {
            return null;
        }
        $packageId = $binding['package'] ?? null;
        $types = [
            'adminToolFormValueLoaders',
            'adminToolFormInitialValueLoaders',
            'adminToolFormCreators',
        ];
        foreach ($types as $type) {
            $owner = red_addon_runtime_owner($type, $formId);
            $handler = red_addon_runtime_handler($type, $formId);
            if (!is_string($packageId)
                || !red_addon_valid_package_id($packageId)
                || !is_string($owner)
                || !hash_equals($packageId, $owner)
                || !is_callable($handler)
            ) {
                return null;
            }
        }
        $tables = red_addon_admin_tool_form_create_tables($formId);
        if (!is_array($tables)) {
            return null;
        }
        $binding['creator'] = red_addon_runtime_handler(
            'adminToolFormCreators',
            $formId
        );
        $binding['tables'] = $tables;
        return $binding;
    }
}

if (!function_exists('red_addon_admin_tool_form_create_plan_sha256')) {
    function red_addon_admin_tool_form_create_plan_sha256(
        array $result,
        array $tables
    ) {
        try {
            $encoded = json_encode(
                [
                    'schema' => 1,
                    'package' => $result['package'] ?? null,
                    'packageVersion' => $result['packageVersion'] ?? null,
                    'tool' => $result['tool'] ?? null,
                    'form' => $result['form'] ?? null,
                    'actorRecordId' => $result['actorRecordId'] ?? null,
                    'permission' => $result['permission'] ?? null,
                    'contractSha256' => $result['contractSha256'] ?? null,
                    'runtimeSettingsSha256' =>
                        $result['runtimeSettingsSha256'] ?? null,
                    'initialStateSha256' =>
                        $result['initialStateSha256'] ?? null,
                    'submittedValuesSha256' =>
                        $result['submittedValuesSha256'] ?? null,
                    'submissionPlanSha256' =>
                        $result['submissionPlanSha256'] ?? null,
                    'tables' => array_values($tables),
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

if (!function_exists('red_addon_admin_tool_form_create_invoke')) {
    function red_addon_admin_tool_form_create_invoke(
        $creator,
        $connection,
        RED_Addon_Admin_Tool_Form_Create_Request $request
    ) {
        if (!is_callable($creator)) {
            return null;
        }
        $bufferLevel = ob_get_level();
        $headersBefore = headers_list();
        $statusBefore = http_response_code();
        try {
            ob_start();
            $created = $creator($connection, $request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Administrator form creator altered output buffers.'
                );
            }
            if (headers_list() !== $headersBefore
                || http_response_code() !== $statusBefore
            ) {
                throw new RuntimeException(
                    'Administrator form creator altered HTTP state.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== ''
                || !$created instanceof
                    RED_Addon_Admin_Tool_Form_Created_Record
            ) {
                return null;
            }
            return $created;
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            red_addon_admin_tool_restore_http_state(
                $headersBefore,
                $statusBefore
            );
            error_log('RED-CMS administrator form creator failed.');
            return null;
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_create_preflight')) {
    function red_addon_admin_tool_form_create_preflight(
        $connection,
        $rawBody,
        $actorRecordId
    ) {
        $result = red_addon_admin_tool_form_create_result($actorRecordId);
        if (!$connection || $result['actorRecordId'] < 1) {
            return $result;
        }
        if (red_addon_admin_tool_form_write_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        $prepared = red_addon_admin_tool_form_create_submission_prepare(
            $connection,
            $rawBody,
            $result['actorRecordId']
        );
        red_addon_admin_tool_form_create_copy_preparation($result, $prepared);
        if (($prepared['prepared'] ?? false) !== true) {
            return $result;
        }
        $binding = red_addon_admin_tool_form_create_binding(
            $result['tool'],
            $result['form']
        );
        if (!is_array($binding)
            || !hash_equals(
                $result['package'],
                (string) ($binding['package'] ?? '')
            )
        ) {
            $result['prepared'] = false;
            $result['reason'] = 'creator_unavailable';
            return $result;
        }
        $result['packageVersion'] =
            red_addon_admin_tool_form_write_package_version(
                $connection,
                $result['package']
            );
        if ($result['packageVersion'] === '') {
            $result['prepared'] = false;
            $result['reason'] = 'package_not_enabled';
            return $result;
        }
        if (!red_admin_transaction_tables_supported(
            $connection,
            array_merge(
                ['RED_Addon_Installations', 'RED_Addon_Activity_Log'],
                $binding['tables']
            )
        )) {
            $result['prepared'] = false;
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        $result['planSha256'] =
            red_addon_admin_tool_form_create_plan_sha256(
                $result,
                $binding['tables']
            );
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['prepared'] = false;
            $result['reason'] = 'plan_invalid';
            return $result;
        }
        $result['reason'] = 'preflight_ready';
        return $result;
    }
}

if (!function_exists('red_addon_admin_tool_form_create')) {
    function red_addon_admin_tool_form_create(
        $connection,
        $rawBody,
        $actorRecordId,
        $expectedPlanSha256
    ) {
        $result = red_addon_admin_tool_form_create_result($actorRecordId);
        if (!$connection || $result['actorRecordId'] < 1) {
            return $result;
        }
        if (!red_addon_valid_sha256($expectedPlanSha256)) {
            $result['reason'] = 'invalid_plan_hash';
            return $result;
        }
        if (red_addon_admin_tool_form_write_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        $initial = red_addon_admin_tool_form_create_preflight(
            $connection,
            $rawBody,
            $result['actorRecordId']
        );
        $result = $initial;
        if (($initial['prepared'] ?? false) !== true) {
            return $result;
        }
        if (!hash_equals(
            (string) ($initial['planSha256'] ?? ''),
            $expectedPlanSha256
        )) {
            $result['prepared'] = false;
            $result['reason'] = 'plan_mismatch';
            return $result;
        }
        $binding = red_addon_admin_tool_form_create_binding(
            $result['tool'],
            $result['form']
        );
        if (!is_array($binding)) {
            $result['prepared'] = false;
            $result['reason'] = 'creator_unavailable';
            return $result;
        }

        $lifecycleLocked = false;
        $packageLocked = false;
        $transactionStarted = false;
        $transactionReason = 'transaction_failed';
        try {
            if (!red_addon_lifecycle_lock($connection)) {
                $result['prepared'] = false;
                $result['reason'] = 'lifecycle_lock_failed';
                return $result;
            }
            $lifecycleLocked = true;
            if (!red_addon_install_lock($connection, $result['package'])) {
                $result['prepared'] = false;
                $result['reason'] = 'package_lock_failed';
                return $result;
            }
            $packageLocked = true;
            if (red_addon_admin_tool_form_write_transaction_active($connection)
                || !mysqli_begin_transaction($connection)
            ) {
                $result['prepared'] = false;
                $result['reason'] = 'transaction_failed';
                return $result;
            }
            $transactionStarted = true;
            $lockedVersion = red_addon_admin_tool_form_write_package_version(
                $connection,
                $result['package'],
                true
            );
            if ($lockedVersion === '') {
                $transactionReason = 'package_not_enabled';
                throw new RuntimeException($transactionReason);
            }
            $locked = red_addon_admin_tool_form_create_submission_prepare(
                $connection,
                $rawBody,
                $result['actorRecordId']
            );
            red_addon_admin_tool_form_create_copy_preparation($result, $locked);
            $result['packageVersion'] = $lockedVersion;
            if (($locked['prepared'] ?? false) !== true) {
                $transactionReason = (string) (
                    $locked['reason'] ?? 'form_unavailable'
                );
                throw new RuntimeException($transactionReason);
            }
            $lockedBinding = red_addon_admin_tool_form_create_binding(
                $result['tool'],
                $result['form']
            );
            if (!is_array($lockedBinding)
                || $lockedBinding['tables'] !== $binding['tables']
                || !hash_equals(
                    $result['package'],
                    (string) ($lockedBinding['package'] ?? '')
                )
            ) {
                $transactionReason = 'creator_unavailable';
                throw new RuntimeException($transactionReason);
            }
            $result['planSha256'] =
                red_addon_admin_tool_form_create_plan_sha256(
                    $result,
                    $lockedBinding['tables']
                );
            if (!red_addon_valid_sha256($result['planSha256'])
                || !hash_equals($result['planSha256'], $expectedPlanSha256)
                || !hash_equals(
                    (string) ($initial['packageVersion'] ?? ''),
                    $lockedVersion
                )
            ) {
                $transactionReason = 'plan_mismatch';
                throw new RuntimeException($transactionReason);
            }
            $runtimeSettings =
                red_addon_admin_tool_form_runtime_settings_resolve(
                    $connection,
                    $lockedBinding
                );
            if (($runtimeSettings['resolved'] ?? false) !== true
                || !($runtimeSettings['settings']
                    instanceof RED_Addon_Admin_Tool_Form_Runtime_Settings)
                || !hash_equals(
                    $result['runtimeSettingsSha256'],
                    (string) ($runtimeSettings['stateSha256'] ?? '')
                )
            ) {
                $transactionReason = 'runtime_settings_unavailable';
                throw new RuntimeException($transactionReason);
            }
            $request = new RED_Addon_Admin_Tool_Form_Create_Request(
                $result['package'],
                $lockedVersion,
                $result['tool'],
                $result['form'],
                $result['actorRecordId'],
                $result['initialStateSha256'],
                $result['planSha256'],
                $locked['values'],
                $runtimeSettings['settings']
            );
            $result['creatorInvoked'] = true;
            $created = red_addon_admin_tool_form_create_invoke(
                $lockedBinding['creator'],
                $connection,
                $request
            );
            if (!$created instanceof RED_Addon_Admin_Tool_Form_Created_Record
                || !red_addon_admin_tool_form_write_transaction_active(
                    $connection
                )
            ) {
                $transactionReason = 'creator_failed';
                throw new RuntimeException($transactionReason);
            }
            $result['targetRecordId'] = $created->recordId();
            $saved = red_addon_admin_tool_form_load_values(
                $connection,
                $result['tool'],
                $result['form'],
                $result['targetRecordId'],
                $result['actorRecordId']
            );
            if (($saved['loaded'] ?? false) !== true
                || ($saved['values'] ?? null) !== $locked['values']
                || !red_addon_valid_sha256($saved['stateSha256'] ?? null)
                || !red_addon_admin_tool_form_write_transaction_active(
                    $connection
                )
            ) {
                $transactionReason = 'postcondition_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_install_audit_record(
                $connection,
                'addon.form.created',
                $result['package'],
                $lockedVersion,
                $result['actorRecordId'],
                'succeeded',
                'form_created'
            )) {
                $transactionReason = 'audit_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_admin_tool_form_write_transaction_active($connection)
                || !mysqli_commit($connection)
            ) {
                $transactionReason = 'transaction_failed';
                throw new RuntimeException($transactionReason);
            }
            $transactionStarted = false;
            $result['executed'] = true;
            $result['stateSha256'] = $saved['stateSha256'];
            $result['reason'] = 'executed';
            return $result;
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                try {
                    mysqli_rollback($connection);
                } catch (Throwable $rollbackFailure) {
                    error_log('RED-CMS administrator form create rollback failed.');
                }
            }
            $result['prepared'] = false;
            $result['targetRecordId'] = 0;
            $result['reason'] = $transactionReason;
            return $result;
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock($connection, $result['package']);
            }
            if ($lifecycleLocked) {
                red_addon_lifecycle_unlock($connection);
            }
        }
    }
}

?>
