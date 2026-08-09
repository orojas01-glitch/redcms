<?php
/**
 * Atomic internal persistence for one validated administrator add-on form.
 *
 * This helper is deliberately disconnected from the validation-only endpoint
 * and administrator UI. It re-prepares one exact canonical submission under
 * lifecycle/package locks, invokes only the exact registrar-bound writer, and
 * requires the reloaded package values to match before one value-free audit
 * fact commits with the package-owned InnoDB mutation.
 */

require_once __DIR__ . '/addon_admin_tool_form_submission_helpers.php';
require_once __DIR__ . '/addon_install_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';

if (!class_exists('RED_Addon_Admin_Tool_Form_Write_Request', false)) {
    final class RED_Addon_Admin_Tool_Form_Write_Request
    {
        private string $package;
        private string $packageVersion;
        private string $tool;
        private string $form;
        private int $actorRecordId;
        private int $targetRecordId;
        private string $previousStateSha256;
        private string $planSha256;
        private array $values;
        private RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings;

        public function __construct(
            string $package,
            string $packageVersion,
            string $tool,
            string $form,
            int $actorRecordId,
            int $targetRecordId,
            string $previousStateSha256,
            string $planSha256,
            array $values,
            RED_Addon_Admin_Tool_Form_Runtime_Settings $runtimeSettings
        ) {
            if (!red_addon_valid_package_id($package)
                || !red_addon_valid_semantic_version($packageVersion)
                || !red_addon_valid_capability($tool)
                || !red_addon_valid_capability($form)
                || red_addon_admin_tool_form_actor_record_id($actorRecordId) < 1
                || $targetRecordId < 1
                || $targetRecordId > 2147483647
                || !red_addon_valid_sha256($previousStateSha256)
                || !red_addon_valid_sha256($planSha256)
                || $values === []
                || array_is_list($values)
            ) {
                throw new InvalidArgumentException(
                    'Administrator form write request is invalid.'
                );
            }
            $this->package = $package;
            $this->packageVersion = $packageVersion;
            $this->tool = $tool;
            $this->form = $form;
            $this->actorRecordId = $actorRecordId;
            $this->targetRecordId = $targetRecordId;
            $this->previousStateSha256 = $previousStateSha256;
            $this->planSha256 = $planSha256;
            $this->values = $values;
            $this->runtimeSettings = $runtimeSettings;
        }

        public function package(): string
        {
            return $this->package;
        }

        public function packageVersion(): string
        {
            return $this->packageVersion;
        }

        public function tool(): string
        {
            return $this->tool;
        }

        public function form(): string
        {
            return $this->form;
        }

        public function actorRecordId(): int
        {
            return $this->actorRecordId;
        }

        public function targetRecordId(): int
        {
            return $this->targetRecordId;
        }

        public function previousStateSha256(): string
        {
            return $this->previousStateSha256;
        }

        public function planSha256(): string
        {
            return $this->planSha256;
        }

        public function values(): array
        {
            return $this->values;
        }

        public function runtimeSettings(): RED_Addon_Admin_Tool_Form_Runtime_Settings
        {
            return $this->runtimeSettings;
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_write_result')) {
    function red_addon_admin_tool_form_write_result(
        $actorRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'authorized' => false,
            'prepared' => false,
            'writerInvoked' => false,
            'executed' => false,
            'unchanged' => false,
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
            'previousStateSha256' => '',
            'submittedValuesSha256' => '',
            'submissionPlanSha256' => '',
            'planSha256' => '',
            'stateSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_form_write_copy_preparation')) {
    function red_addon_admin_tool_form_write_copy_preparation(
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
                'targetRecordId',
                'actorRecordId',
                'permission',
                'contractSha256',
                'runtimeSettingsSha256',
                'submittedValuesSha256',
            ] as $key
        ) {
            if (array_key_exists($key, $prepared)) {
                $result[$key] = $prepared[$key];
            }
        }
        $result['previousStateSha256'] = is_string(
            $prepared['currentStateSha256'] ?? null
        ) ? $prepared['currentStateSha256'] : '';
        $result['submissionPlanSha256'] = is_string(
            $prepared['planSha256'] ?? null
        ) ? $prepared['planSha256'] : '';
        $result['reason'] = is_string($prepared['reason'] ?? null)
            ? $prepared['reason']
            : 'form_unavailable';
    }
}

if (!function_exists('red_addon_admin_tool_form_write_plan_sha256')) {
    function red_addon_admin_tool_form_write_plan_sha256(
        array $result,
        array $tables
    ) {
        try {
            $encoded = json_encode(
                [
                    'schema' => 2,
                    'package' => $result['package'] ?? null,
                    'packageVersion' => $result['packageVersion'] ?? null,
                    'tool' => $result['tool'] ?? null,
                    'form' => $result['form'] ?? null,
                    'targetRecordId' => $result['targetRecordId'] ?? null,
                    'actorRecordId' => $result['actorRecordId'] ?? null,
                    'permission' => $result['permission'] ?? null,
                    'contractSha256' => $result['contractSha256'] ?? null,
                    'runtimeSettingsSha256' =>
                        $result['runtimeSettingsSha256'] ?? null,
                    'previousStateSha256' =>
                        $result['previousStateSha256'] ?? null,
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

if (!function_exists('red_addon_admin_tool_form_write_transaction_active')) {
    function red_addon_admin_tool_form_write_transaction_active($connection)
    {
        if (!$connection) {
            return false;
        }
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_addon_admin_form_writer_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_addon_admin_form_writer_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_write_tables')) {
    function red_addon_admin_tool_form_write_tables($formId)
    {
        $metadata = red_addon_runtime_metadata('adminToolFormWriters', $formId);
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

if (!function_exists('red_addon_admin_tool_form_write_binding')) {
    function red_addon_admin_tool_form_write_binding($toolId, $formId)
    {
        $binding = red_addon_admin_tool_form_preflight_binding(
            $toolId,
            $formId
        );
        if (!is_array($binding)) {
            return null;
        }
        $packageId = $binding['package'] ?? null;
        $loaderOwner = red_addon_runtime_owner(
            'adminToolFormValueLoaders',
            $formId
        );
        $loader = red_addon_runtime_handler(
            'adminToolFormValueLoaders',
            $formId
        );
        $writerOwner = red_addon_runtime_owner(
            'adminToolFormWriters',
            $formId
        );
        $writer = red_addon_runtime_handler('adminToolFormWriters', $formId);
        $tables = red_addon_admin_tool_form_write_tables($formId);
        if (!is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || !is_string($loaderOwner)
            || !hash_equals($packageId, $loaderOwner)
            || !is_callable($loader)
            || !is_string($writerOwner)
            || !hash_equals($packageId, $writerOwner)
            || !is_callable($writer)
            || !is_array($tables)
        ) {
            return null;
        }
        $binding['writer'] = $writer;
        $binding['tables'] = $tables;
        return $binding;
    }
}

if (!function_exists('red_addon_admin_tool_form_write_package_version')) {
    function red_addon_admin_tool_form_write_package_version(
        $connection,
        $packageId,
        $forUpdate = false
    ) {
        if (!$connection || !red_addon_valid_package_id($packageId)) {
            return '';
        }
        $sql = "SELECT PackageVersion FROM RED_Addon_Installations
                WHERE PackageID=? AND LifecycleState='enabled' LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        try {
            $statement = mysqli_prepare($connection, $sql);
            if (!$statement) {
                return '';
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return '';
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            $version = is_string($row['PackageVersion'] ?? null)
                ? $row['PackageVersion']
                : '';
            return red_addon_valid_semantic_version($version) ? $version : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_write_invoke')) {
    function red_addon_admin_tool_form_write_invoke(
        $writer,
        $connection,
        RED_Addon_Admin_Tool_Form_Write_Request $request
    ) {
        if (!is_callable($writer)) {
            return false;
        }
        $bufferLevel = ob_get_level();
        $headersBefore = headers_list();
        $statusBefore = http_response_code();
        try {
            ob_start();
            $written = $writer($connection, $request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Administrator form writer altered output buffers.'
                );
            }
            if (headers_list() !== $headersBefore
                || http_response_code() !== $statusBefore
            ) {
                throw new RuntimeException(
                    'Administrator form writer altered HTTP state.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '' || $written !== true) {
                return false;
            }
            return true;
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            red_addon_admin_tool_restore_http_state(
                $headersBefore,
                $statusBefore
            );
            error_log('RED-CMS administrator form writer failed.');
            return false;
        }
    }
}

if (!function_exists('red_addon_admin_tool_form_write_preflight')) {
    function red_addon_admin_tool_form_write_preflight(
        $connection,
        $rawBody,
        $actorRecordId
    ) {
        $result = red_addon_admin_tool_form_write_result($actorRecordId);
        if (!$connection || $result['actorRecordId'] < 1) {
            return $result;
        }
        if (red_addon_admin_tool_form_write_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        $prepared = red_addon_admin_tool_form_submission_prepare(
            $connection,
            $rawBody,
            $result['actorRecordId']
        );
        red_addon_admin_tool_form_write_copy_preparation($result, $prepared);
        if (($prepared['prepared'] ?? false) !== true) {
            return $result;
        }
        $binding = red_addon_admin_tool_form_write_binding(
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
            $result['reason'] = 'writer_unavailable';
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
            red_addon_admin_tool_form_write_plan_sha256(
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

if (!function_exists('red_addon_admin_tool_form_write')) {
    function red_addon_admin_tool_form_write(
        $connection,
        $rawBody,
        $actorRecordId,
        $expectedPlanSha256
    ) {
        $result = red_addon_admin_tool_form_write_result($actorRecordId);
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

        $initial = red_addon_admin_tool_form_write_preflight(
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
        $binding = red_addon_admin_tool_form_write_binding(
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
            $result['reason'] = 'writer_unavailable';
            return $result;
        }
        $packageVersion = $result['packageVersion'];
        if ($packageVersion === '') {
            $result['prepared'] = false;
            $result['reason'] = 'package_not_enabled';
            return $result;
        }
        $result['packageVersion'] = $packageVersion;
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

        $lifecycleLocked = false;
        $packageLocked = false;
        $lockedPackageId = $result['package'];
        $transactionStarted = false;
        $transactionReason = 'transaction_failed';
        try {
            if (!red_addon_lifecycle_lock($connection)) {
                $result['prepared'] = false;
                $result['reason'] = 'lifecycle_lock_failed';
                return $result;
            }
            $lifecycleLocked = true;
            if (!red_addon_install_lock($connection, $lockedPackageId)) {
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
            $locked = red_addon_admin_tool_form_submission_prepare(
                $connection,
                $rawBody,
                $result['actorRecordId']
            );
            red_addon_admin_tool_form_write_copy_preparation($result, $locked);
            $result['packageVersion'] = $lockedVersion;
            if (($locked['prepared'] ?? false) !== true) {
                $transactionReason = is_string($locked['reason'] ?? null)
                    ? $locked['reason']
                    : 'form_unavailable';
                throw new RuntimeException($transactionReason);
            }
            $lockedBinding = red_addon_admin_tool_form_write_binding(
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
                $transactionReason = 'writer_unavailable';
                throw new RuntimeException($transactionReason);
            }
            $result['planSha256'] =
                red_addon_admin_tool_form_write_plan_sha256(
                    $result,
                    $lockedBinding['tables']
                );
            if (!red_addon_valid_sha256($result['planSha256'])
                || !hash_equals($result['planSha256'], $expectedPlanSha256)
                || !hash_equals($packageVersion, $lockedVersion)
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
                || !is_string($runtimeSettings['stateSha256'] ?? null)
                || !hash_equals(
                    $result['runtimeSettingsSha256'],
                    $runtimeSettings['stateSha256']
                )
            ) {
                $transactionReason = 'runtime_settings_unavailable';
                throw new RuntimeException($transactionReason);
            }
            $submittedStateSha256 = red_addon_admin_tool_form_value_state_hash(
                $result['package'],
                $result['tool'],
                $result['form'],
                $result['targetRecordId'],
                $result['contractSha256'],
                $result['runtimeSettingsSha256'],
                $locked['values']
            );
            if (!red_addon_valid_sha256($submittedStateSha256)) {
                $transactionReason = 'invalid_values';
                throw new RuntimeException($transactionReason);
            }
            if (hash_equals(
                $result['previousStateSha256'],
                $submittedStateSha256
            )) {
                if (!mysqli_rollback($connection)) {
                    $transactionReason = 'transaction_failed';
                    throw new RuntimeException($transactionReason);
                }
                $transactionStarted = false;
                $result['unchanged'] = true;
                $result['stateSha256'] = $submittedStateSha256;
                $result['reason'] = 'unchanged';
                return $result;
            }
            $request = new RED_Addon_Admin_Tool_Form_Write_Request(
                $result['package'],
                $lockedVersion,
                $result['tool'],
                $result['form'],
                $result['actorRecordId'],
                $result['targetRecordId'],
                $result['previousStateSha256'],
                $result['planSha256'],
                $locked['values'],
                $runtimeSettings['settings']
            );
            $result['writerInvoked'] = true;
            if (!red_addon_admin_tool_form_write_invoke(
                $lockedBinding['writer'],
                $connection,
                $request
            ) || !red_addon_admin_tool_form_write_transaction_active($connection)
            ) {
                $transactionReason = 'writer_failed';
                throw new RuntimeException($transactionReason);
            }
            $saved = red_addon_admin_tool_form_load_values(
                $connection,
                $result['tool'],
                $result['form'],
                $result['targetRecordId'],
                $result['actorRecordId']
            );
            if (($saved['loaded'] ?? false) !== true
                || ($saved['values'] ?? null) !== $locked['values']
                || !hash_equals(
                    (string) ($saved['stateSha256'] ?? ''),
                    $submittedStateSha256
                )
                || !red_addon_admin_tool_form_write_transaction_active(
                    $connection
                )
            ) {
                $transactionReason = 'postcondition_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_install_audit_record(
                $connection,
                'addon.form.saved',
                $result['package'],
                $lockedVersion,
                $result['actorRecordId'],
                'succeeded',
                'form_saved'
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
            $result['stateSha256'] = $submittedStateSha256;
            $result['reason'] = 'executed';
            return $result;
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                try {
                    mysqli_rollback($connection);
                } catch (Throwable $rollbackFailure) {
                    error_log('RED-CMS administrator form rollback failed.');
                }
            }
            $result['prepared'] = false;
            $result['reason'] = $transactionReason;
            return $result;
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock($connection, $lockedPackageId);
            }
            if ($lifecycleLocked) {
                red_addon_lifecycle_unlock($connection);
            }
        }
    }
}

?>
