<?php
/**
 * Atomic internal execution for one bounded administrator add-on action.
 *
 * This is deliberately not an HTTP endpoint. A future core-owned endpoint
 * must authenticate the administrator and validate CSRF before calling this
 * helper. The helper accepts only scalar identities and a previously returned
 * exact plan hash; it never reads request or session globals.
 */

require_once __DIR__ . '/addon_admin_tool_action_preflight_helpers.php';
require_once __DIR__ . '/addon_install_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';
require_once __DIR__ . '/addon_service_helpers.php';

if (!function_exists('red_addon_admin_tool_action_execution_actor_record_id')) {
    function red_addon_admin_tool_action_execution_actor_record_id($value)
    {
        return is_int($value) && $value >= 1 && $value <= 2147483647
            ? $value
            : 0;
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_canonical_state')) {
    function red_addon_admin_tool_action_execution_canonical_state(array $state)
    {
        $normalized = red_addon_service_payload($state);
        if (!is_array($normalized)) {
            return null;
        }
        $canonicalize = static function (array $value) use (&$canonicalize) {
            if (array_is_list($value)) {
                $result = [];
                foreach ($value as $item) {
                    $result[] = is_array($item) ? $canonicalize($item) : $item;
                }
                return $result;
            }
            ksort($value, SORT_STRING);
            foreach ($value as $key => $item) {
                $value[$key] = is_array($item) ? $canonicalize($item) : $item;
            }
            return $value;
        };
        return $canonicalize($normalized);
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Action_Target_State', false)) {
    final class RED_Addon_Admin_Tool_Action_Target_State
    {
        private int $targetRecordId;
        private array $state;

        public function __construct(int $targetRecordId, array $state)
        {
            if (red_addon_admin_tool_action_target_record_id($targetRecordId) < 1) {
                throw new InvalidArgumentException('Administrator action target is invalid.');
            }
            $normalized = red_addon_admin_tool_action_execution_canonical_state($state);
            if (!is_array($normalized)) {
                throw new InvalidArgumentException('Administrator action state is invalid.');
            }
            $this->targetRecordId = $targetRecordId;
            $this->state = $normalized;
        }

        public function targetRecordId(): int
        {
            return $this->targetRecordId;
        }

        public function state(): array
        {
            return $this->state;
        }
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Action_Target_Request', false)) {
    final class RED_Addon_Admin_Tool_Action_Target_Request
    {
        private string $tool;
        private string $action;
        private string $package;
        private int $actorRecordId;
        private int $targetRecordId;

        public function __construct(
            string $tool,
            string $action,
            string $package,
            int $actorRecordId,
            int $targetRecordId
        ) {
            if (!red_addon_valid_capability($tool)
                || !red_addon_valid_capability($action)
                || !red_addon_valid_package_id($package)
                || red_addon_admin_tool_action_execution_actor_record_id(
                    $actorRecordId
                ) < 1
                || red_addon_admin_tool_action_target_record_id($targetRecordId) < 1
            ) {
                throw new InvalidArgumentException('Administrator action request is invalid.');
            }
            $this->tool = $tool;
            $this->action = $action;
            $this->package = $package;
            $this->actorRecordId = $actorRecordId;
            $this->targetRecordId = $targetRecordId;
        }

        public function tool(): string
        {
            return $this->tool;
        }

        public function action(): string
        {
            return $this->action;
        }

        public function package(): string
        {
            return $this->package;
        }

        public function actorRecordId(): int
        {
            return $this->actorRecordId;
        }

        public function targetRecordId(): int
        {
            return $this->targetRecordId;
        }
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Action_Execution_Request', false)) {
    final class RED_Addon_Admin_Tool_Action_Execution_Request
    {
        private RED_Addon_Admin_Tool_Action_Target_Request $target;
        private string $previousStateSha256;
        private string $planSha256;

        public function __construct(
            RED_Addon_Admin_Tool_Action_Target_Request $target,
            string $previousStateSha256,
            string $planSha256
        ) {
            if (!red_addon_valid_sha256($previousStateSha256)
                || !red_addon_valid_sha256($planSha256)
            ) {
                throw new InvalidArgumentException('Administrator action evidence is invalid.');
            }
            $this->target = $target;
            $this->previousStateSha256 = $previousStateSha256;
            $this->planSha256 = $planSha256;
        }

        public function tool(): string
        {
            return $this->target->tool();
        }

        public function action(): string
        {
            return $this->target->action();
        }

        public function package(): string
        {
            return $this->target->package();
        }

        public function actorRecordId(): int
        {
            return $this->target->actorRecordId();
        }

        public function targetRecordId(): int
        {
            return $this->target->targetRecordId();
        }

        public function previousStateSha256(): string
        {
            return $this->previousStateSha256;
        }

        public function planSha256(): string
        {
            return $this->planSha256;
        }
    }
}

if (!class_exists('RED_Addon_Admin_Tool_Action_Execution_Result', false)) {
    final class RED_Addon_Admin_Tool_Action_Execution_Result
    {
        private bool $changed;
        private RED_Addon_Admin_Tool_Action_Target_State $state;

        private function __construct(
            bool $changed,
            RED_Addon_Admin_Tool_Action_Target_State $state
        ) {
            $this->changed = $changed;
            $this->state = $state;
        }

        public static function changed(
            RED_Addon_Admin_Tool_Action_Target_State $state
        ): self {
            return new self(true, $state);
        }

        public static function unchanged(
            RED_Addon_Admin_Tool_Action_Target_State $state
        ): self {
            return new self(false, $state);
        }

        public function hasChanged(): bool
        {
            return $this->changed;
        }

        public function state(): RED_Addon_Admin_Tool_Action_Target_State
        {
            return $this->state;
        }
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_result')) {
    function red_addon_admin_tool_action_execution_result(
        $toolId,
        $actionId,
        $actorRecordId,
        $targetRecordId,
        $reason = 'invalid_request'
    ) {
        return [
            'authorized' => false,
            'ready' => false,
            'stateLoaderInvoked' => false,
            'executed' => false,
            'unchanged' => false,
            'tool' => is_string($toolId) && red_addon_valid_capability($toolId)
                ? $toolId
                : '',
            'action' => is_string($actionId)
                && red_addon_valid_capability($actionId)
                    ? $actionId
                    : '',
            'package' => '',
            'packageVersion' => '',
            'actorRecordId' => red_addon_admin_tool_action_execution_actor_record_id(
                $actorRecordId
            ),
            'targetRecordId' => red_addon_admin_tool_action_target_record_id(
                $targetRecordId
            ),
            'permission' => '',
            'method' => '',
            'csrf' => '',
            'idempotency' => '',
            'contractSha256' => '',
            'metadataPlanSha256' => '',
            'previousStateSha256' => '',
            'stateSha256' => '',
            'planSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_transaction_active')) {
    function red_addon_admin_tool_action_execution_transaction_active($connection)
    {
        if (!$connection) {
            return false;
        }
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_addon_admin_action_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_addon_admin_action_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_storage_available')) {
    function red_addon_admin_tool_action_execution_storage_available($connection)
    {
        if (!$connection || !red_addon_install_storage_available($connection)) {
            return false;
        }
        try {
            $query = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Admin_Action_Executions'),
                    (SELECT COUNT(*)=9
                       AND SUM(COLUMN_NAME='PackageID'
                         AND COLUMN_TYPE='varchar(127)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ActionID'
                         AND COLUMN_TYPE='varchar(160)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='TargetRecordID'
                         AND DATA_TYPE='int'
                         AND COLUMN_TYPE LIKE 'int% unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='PlanSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ContractSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='PreviousStateSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='StateSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ActorAdminRecordID'
                         AND DATA_TYPE='int'
                         AND COLUMN_TYPE LIKE 'int% unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='CompletedAt'
                         AND DATA_TYPE='timestamp'
                         AND IS_NULLABLE='NO')=1
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Admin_Action_Executions'),
                    (SELECT GROUP_CONCAT(COLUMN_NAME
                       ORDER BY SEQ_IN_INDEX SEPARATOR ',')
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Admin_Action_Executions'
                       AND INDEX_NAME='PRIMARY'),
                    (SELECT GROUP_CONCAT(COLUMN_NAME
                       ORDER BY SEQ_IN_INDEX SEPARATOR ',')
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Admin_Action_Executions'
                       AND INDEX_NAME='idx_red_addon_admin_action_execution_package'),
                    (SELECT COUNT(*)=1
                       AND SUM(CONSTRAINT_NAME=
                         'fk_red_addon_admin_action_execution_installation'
                         AND TABLE_NAME='RED_Addon_Admin_Action_Executions'
                         AND REFERENCED_TABLE_NAME='RED_Addon_Installations'
                         AND DELETE_RULE='RESTRICT'
                         AND UPDATE_RULE='RESTRICT')=1
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND CONSTRAINT_NAME=
                         'fk_red_addon_admin_action_execution_installation')
                 ) AS StorageState"
            );
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            return (string) ($row['StorageState'] ?? '')
                === '1:1:PackageID,ActionID,TargetRecordID:PackageID,CompletedAt,TargetRecordID:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_tables')) {
    function red_addon_admin_tool_action_execution_tables($actionId)
    {
        $metadata = red_addon_runtime_metadata('adminToolActions', $actionId);
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

if (!function_exists('red_addon_admin_tool_action_execution_binding')) {
    function red_addon_admin_tool_action_execution_binding($toolId, $actionId)
    {
        $binding = red_addon_admin_tool_action_preflight_binding(
            $toolId,
            $actionId
        );
        if (!is_array($binding)) {
            return null;
        }
        $packageId = $binding['package'] ?? null;
        $actionOwner = red_addon_runtime_owner('adminToolActions', $actionId);
        $actionHandler = red_addon_runtime_handler('adminToolActions', $actionId);
        $loaderOwner = red_addon_runtime_owner(
            'adminToolActionStateLoaders',
            $actionId
        );
        $stateLoader = red_addon_runtime_handler(
            'adminToolActionStateLoaders',
            $actionId
        );
        $tables = red_addon_admin_tool_action_execution_tables($actionId);
        if (!is_string($packageId)
            || !red_addon_valid_package_id($packageId)
            || !is_string($actionOwner)
            || !hash_equals($packageId, $actionOwner)
            || !is_callable($actionHandler)
            || !is_string($loaderOwner)
            || !hash_equals($packageId, $loaderOwner)
            || !is_callable($stateLoader)
            || !is_array($tables)
            || ($binding['idempotency'] ?? null) !== 'once-per-target'
        ) {
            return null;
        }
        $binding['handler'] = $actionHandler;
        $binding['stateLoader'] = $stateLoader;
        $binding['tables'] = $tables;
        return $binding;
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_package_version')) {
    function red_addon_admin_tool_action_execution_package_version(
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
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
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

if (!function_exists('red_addon_admin_tool_action_execution_completed')) {
    function red_addon_admin_tool_action_execution_completed(
        $connection,
        $packageId,
        $actionId,
        $targetRecordId,
        $forUpdate = false
    ) {
        if (!$connection
            || !red_addon_valid_package_id($packageId)
            || !red_addon_valid_capability($actionId)
            || red_addon_admin_tool_action_target_record_id($targetRecordId) < 1
        ) {
            return null;
        }
        $sql = 'SELECT StateSHA256 FROM RED_Addon_Admin_Action_Executions
                WHERE PackageID=? AND ActionID=? AND TargetRecordID=? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        try {
            $statement = mysqli_prepare($connection, $sql);
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param(
                $statement,
                'ssi',
                $packageId,
                $actionId,
                $targetRecordId
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return null;
            }
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($statement);
            return $row !== null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_state_sha256')) {
    function red_addon_admin_tool_action_execution_state_sha256(
        $packageId,
        $actionId,
        $targetRecordId,
        RED_Addon_Admin_Tool_Action_Target_State $state
    ) {
        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_capability($actionId)
            || red_addon_admin_tool_action_target_record_id($targetRecordId) < 1
            || $state->targetRecordId() !== $targetRecordId
        ) {
            return '';
        }
        $json = json_encode(
            [
                'schema' => 1,
                'package' => $packageId,
                'action' => $actionId,
                'targetRecordId' => (string) $targetRecordId,
                'state' => $state->state(),
            ],
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
        return is_string($json) && $json !== ''
            ? hash('sha256', $json)
            : '';
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_plan_sha256')) {
    function red_addon_admin_tool_action_execution_plan_sha256(array $plan)
    {
        $json = json_encode(
            [
                'version' => 1,
                'package' => $plan['package'] ?? null,
                'packageVersion' => $plan['packageVersion'] ?? null,
                'tool' => $plan['tool'] ?? null,
                'action' => $plan['action'] ?? null,
                'actorRecordId' => $plan['actorRecordId'] ?? null,
                'targetRecordId' => $plan['targetRecordId'] ?? null,
                'permission' => $plan['permission'] ?? null,
                'method' => $plan['method'] ?? null,
                'csrf' => $plan['csrf'] ?? null,
                'idempotency' => $plan['idempotency'] ?? null,
                'contractSha256' => $plan['contractSha256'] ?? null,
                'metadataPlanSha256' => $plan['metadataPlanSha256'] ?? null,
                'previousStateSha256' => $plan['previousStateSha256'] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($json) && $json !== '' ? hash('sha256', $json) : '';
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_invoke_state_loader')) {
    function red_addon_admin_tool_action_execution_invoke_state_loader(
        $stateLoader,
        $connection,
        RED_Addon_Admin_Tool_Action_Target_Request $request
    ) {
        if (!is_callable($stateLoader)) {
            return null;
        }
        $bufferLevel = ob_get_level();
        $state = null;
        $emitted = '';
        try {
            ob_start();
            $state = $stateLoader($connection, $request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on administrator action state loader altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log('RED-CMS add-on administrator action state loader failed.');
            return null;
        }
        return $emitted === ''
            && $state instanceof RED_Addon_Admin_Tool_Action_Target_State
                ? $state
                : null;
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_invoke_handler')) {
    function red_addon_admin_tool_action_execution_invoke_handler(
        $handler,
        $connection,
        RED_Addon_Admin_Tool_Action_Execution_Request $request
    ) {
        if (!is_callable($handler)) {
            return null;
        }
        $bufferLevel = ob_get_level();
        $result = null;
        $emitted = '';
        try {
            ob_start();
            $result = $handler($connection, $request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on administrator action handler altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log('RED-CMS add-on administrator action handler failed.');
            return null;
        }
        return $emitted === ''
            && $result instanceof RED_Addon_Admin_Tool_Action_Execution_Result
                ? $result
                : null;
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_copy_metadata')) {
    function red_addon_admin_tool_action_execution_copy_metadata(
        array &$result,
        array $metadata
    ) {
        foreach (
            [
                'authorized',
                'tool',
                'action',
                'package',
                'actorRecordId',
                'targetRecordId',
                'permission',
                'method',
                'csrf',
                'idempotency',
                'contractSha256',
            ] as $key
        ) {
            if (array_key_exists($key, $metadata)) {
                $result[$key] = $metadata[$key];
            }
        }
        $result['metadataPlanSha256'] = is_string(
            $metadata['planSha256'] ?? null
        ) ? $metadata['planSha256'] : '';
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_plan')) {
    function red_addon_admin_tool_action_execution_plan(
        $connection,
        $toolId,
        $actionId,
        $actorRecordId,
        $targetRecordId,
        $lockCompleted = false
    ) {
        $result = red_addon_admin_tool_action_execution_result(
            $toolId,
            $actionId,
            $actorRecordId,
            $targetRecordId
        );
        if ($result['tool'] === ''
            || $result['action'] === ''
            || $result['actorRecordId'] < 1
            || $result['targetRecordId'] < 1
        ) {
            return $result;
        }
        if (!red_addon_admin_tool_action_execution_transaction_active(
            $connection
        )) {
            $result['reason'] = 'transaction_required';
            return $result;
        }
        if (!red_addon_admin_tool_action_execution_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }

        $metadata = red_addon_admin_tool_action_preflight(
            $connection,
            $result['tool'],
            $result['action'],
            $result['actorRecordId'],
            $result['targetRecordId']
        );
        red_addon_admin_tool_action_execution_copy_metadata($result, $metadata);
        if (empty($metadata['ready'])) {
            $result['reason'] = is_string($metadata['reason'] ?? null)
                ? $metadata['reason']
                : 'metadata_preflight_failed';
            return $result;
        }

        $binding = red_addon_admin_tool_action_execution_binding(
            $result['tool'],
            $result['action']
        );
        if (!is_array($binding)
            || !hash_equals(
                $result['package'],
                (string) ($binding['package'] ?? '')
            )
        ) {
            $result['authorized'] = false;
            $result['reason'] = 'action_unavailable';
            return $result;
        }
        if (!red_admin_transaction_tables_supported(
            $connection,
            array_merge(
                [
                    'RED_Addon_Installations',
                    'RED_Addon_Activity_Log',
                    'RED_Addon_Admin_Action_Executions',
                ],
                $binding['tables']
            )
        )) {
            $result['authorized'] = false;
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        $packageVersion = red_addon_admin_tool_action_execution_package_version(
            $connection,
            $result['package'],
            false
        );
        if ($packageVersion === '') {
            $result['authorized'] = false;
            $result['reason'] = 'package_not_enabled';
            return $result;
        }
        $result['packageVersion'] = $packageVersion;
        $completed = red_addon_admin_tool_action_execution_completed(
            $connection,
            $result['package'],
            $result['action'],
            $result['targetRecordId'],
            (bool) $lockCompleted
        );
        if ($completed === null) {
            $result['authorized'] = false;
            $result['reason'] = 'execution_ledger_unavailable';
            return $result;
        }
        if ($completed) {
            $result['reason'] = 'already_executed';
            return $result;
        }

        try {
            $request = new RED_Addon_Admin_Tool_Action_Target_Request(
                $result['tool'],
                $result['action'],
                $result['package'],
                $result['actorRecordId'],
                $result['targetRecordId']
            );
        } catch (Throwable $throwable) {
            $result['authorized'] = false;
            $result['reason'] = 'request_invalid';
            return $result;
        }
        $result['stateLoaderInvoked'] = true;
        $state = red_addon_admin_tool_action_execution_invoke_state_loader(
            $binding['stateLoader'],
            $connection,
            $request
        );
        if (!$state instanceof RED_Addon_Admin_Tool_Action_Target_State
            || $state->targetRecordId() !== $result['targetRecordId']
            || !red_addon_admin_tool_action_execution_transaction_active(
                $connection
            )
        ) {
            $result['authorized'] = false;
            $result['reason'] = 'state_loader_failed';
            return $result;
        }
        $previousStateSha256 = red_addon_admin_tool_action_execution_state_sha256(
            $result['package'],
            $result['action'],
            $result['targetRecordId'],
            $state
        );
        if (!red_addon_valid_sha256($previousStateSha256)) {
            $result['authorized'] = false;
            $result['reason'] = 'state_invalid';
            return $result;
        }
        $result['previousStateSha256'] = $previousStateSha256;
        $result['planSha256'] = red_addon_admin_tool_action_execution_plan_sha256(
            $result
        );
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['authorized'] = false;
            $result['reason'] = 'plan_invalid';
            return $result;
        }
        $result['ready'] = true;
        $result['reason'] = 'preflight_ready';
        return $result;
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_preflight')) {
    function red_addon_admin_tool_action_execution_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorRecordId,
        $targetRecordId
    ) {
        $result = red_addon_admin_tool_action_execution_result(
            $toolId,
            $actionId,
            $actorRecordId,
            $targetRecordId
        );
        if ($result['tool'] === ''
            || $result['action'] === ''
            || $result['actorRecordId'] < 1
            || $result['targetRecordId'] < 1
        ) {
            return $result;
        }
        if (red_addon_admin_tool_action_execution_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        if (!mysqli_begin_transaction($connection)) {
            $result['reason'] = 'transaction_failed';
            return $result;
        }
        try {
            $result = red_addon_admin_tool_action_execution_plan(
                $connection,
                $toolId,
                $actionId,
                $actorRecordId,
                $targetRecordId
            );
            if (!red_addon_admin_tool_action_execution_transaction_active(
                $connection
            )) {
                $result['authorized'] = false;
                $result['ready'] = false;
                $result['reason'] = 'transaction_lost';
                return $result;
            }
            if (!mysqli_rollback($connection)) {
                $result['authorized'] = false;
                $result['ready'] = false;
                $result['reason'] = 'transaction_failed';
            }
            return $result;
        } catch (Throwable $throwable) {
            try {
                mysqli_rollback($connection);
            } catch (Throwable $rollbackFailure) {
                error_log('RED-CMS administrator action preflight rollback failed.');
            }
            $result['reason'] = 'preflight_failed';
            return $result;
        }
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_reserve')) {
    function red_addon_admin_tool_action_execution_reserve(
        $connection,
        array $plan
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Addon_Admin_Action_Executions (
                    PackageID, ActionID, TargetRecordID, PlanSHA256,
                    ContractSHA256, PreviousStateSHA256, StateSHA256,
                    ActorAdminRecordID
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$statement) {
                return 'failed';
            }
            mysqli_stmt_bind_param(
                $statement,
                'ssissssi',
                $plan['package'],
                $plan['action'],
                $plan['targetRecordId'],
                $plan['planSha256'],
                $plan['contractSha256'],
                $plan['previousStateSha256'],
                $plan['previousStateSha256'],
                $plan['actorRecordId']
            );
            $executed = mysqli_stmt_execute($statement);
            $errno = mysqli_stmt_errno($statement);
            mysqli_stmt_close($statement);
            if ($executed) {
                return 'reserved';
            }
            return $errno === 1062 ? 'duplicate' : 'failed';
        } catch (Throwable $throwable) {
            return 'failed';
        }
    }
}

if (!function_exists('red_addon_admin_tool_action_execution_update_state')) {
    function red_addon_admin_tool_action_execution_update_state(
        $connection,
        array $plan,
        $stateSha256
    ) {
        if (!red_addon_valid_sha256($stateSha256)) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'UPDATE RED_Addon_Admin_Action_Executions
                 SET StateSHA256=?
                 WHERE PackageID=? AND ActionID=? AND TargetRecordID=?
                   AND StateSHA256=?'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'sssis',
                $stateSha256,
                $plan['package'],
                $plan['action'],
                $plan['targetRecordId'],
                $plan['previousStateSha256']
            );
            $executed = mysqli_stmt_execute($statement);
            $updated = mysqli_stmt_affected_rows($statement);
            mysqli_stmt_close($statement);
            return $executed && $updated === 1;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_admin_tool_action_execute')) {
    function red_addon_admin_tool_action_execute(
        $connection,
        $toolId,
        $actionId,
        $actorRecordId,
        $targetRecordId,
        $expectedPlanSha256
    ) {
        $result = red_addon_admin_tool_action_execution_result(
            $toolId,
            $actionId,
            $actorRecordId,
            $targetRecordId
        );
        if ($result['tool'] === ''
            || $result['action'] === ''
            || $result['actorRecordId'] < 1
            || $result['targetRecordId'] < 1
        ) {
            return $result;
        }
        if (!red_addon_valid_sha256($expectedPlanSha256)) {
            $result['reason'] = 'invalid_plan_hash';
            return $result;
        }
        if (red_addon_admin_tool_action_execution_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }

        $initial = red_addon_admin_tool_action_execution_preflight(
            $connection,
            $toolId,
            $actionId,
            $actorRecordId,
            $targetRecordId
        );
        $result = $initial;
        if (empty($initial['ready'])) {
            return $result;
        }
        if (!hash_equals($initial['planSha256'], $expectedPlanSha256)) {
            $result['ready'] = false;
            $result['reason'] = 'plan_mismatch';
            return $result;
        }

        $lifecycleLocked = false;
        $packageLocked = false;
        $transactionStarted = false;
        $transactionReason = 'transaction_failed';
        try {
            if (!red_addon_lifecycle_lock($connection)) {
                $result['ready'] = false;
                $result['reason'] = 'lifecycle_lock_failed';
                return $result;
            }
            $lifecycleLocked = true;
            if (!red_addon_install_lock($connection, $initial['package'])) {
                $result['ready'] = false;
                $result['reason'] = 'package_lock_failed';
                return $result;
            }
            $packageLocked = true;
            if (red_addon_admin_tool_action_execution_transaction_active(
                $connection
            ) || !mysqli_begin_transaction($connection)) {
                $result['ready'] = false;
                $result['reason'] = 'transaction_failed';
                return $result;
            }
            $transactionStarted = true;
            $lockedVersion = red_addon_admin_tool_action_execution_package_version(
                $connection,
                $initial['package'],
                true
            );
            if ($lockedVersion === '') {
                $transactionReason = 'package_not_enabled';
                throw new RuntimeException($transactionReason);
            }
            $locked = red_addon_admin_tool_action_execution_plan(
                $connection,
                $toolId,
                $actionId,
                $actorRecordId,
                $targetRecordId,
                true
            );
            $result = $locked;
            if (empty($locked['ready'])) {
                $transactionReason = is_string($locked['reason'] ?? null)
                    ? $locked['reason']
                    : 'preflight_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!hash_equals($locked['planSha256'], $expectedPlanSha256)
                || !hash_equals($lockedVersion, $locked['packageVersion'])
            ) {
                $transactionReason = 'plan_mismatch';
                throw new RuntimeException($transactionReason);
            }
            $reservation = red_addon_admin_tool_action_execution_reserve(
                $connection,
                $locked
            );
            if ($reservation !== 'reserved') {
                $transactionReason = $reservation === 'duplicate'
                    ? 'already_executed'
                    : 'execution_reservation_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_admin_tool_action_execution_transaction_active(
                $connection
            )) {
                $transactionReason = 'transaction_lost';
                throw new RuntimeException($transactionReason);
            }
            $target = new RED_Addon_Admin_Tool_Action_Target_Request(
                $locked['tool'],
                $locked['action'],
                $locked['package'],
                $locked['actorRecordId'],
                $locked['targetRecordId']
            );
            $request = new RED_Addon_Admin_Tool_Action_Execution_Request(
                $target,
                $locked['previousStateSha256'],
                $locked['planSha256']
            );
            $binding = red_addon_admin_tool_action_execution_binding(
                $locked['tool'],
                $locked['action']
            );
            $actionResult = is_array($binding)
                ? red_addon_admin_tool_action_execution_invoke_handler(
                    $binding['handler'],
                    $connection,
                    $request
                )
                : null;
            if (!$actionResult instanceof RED_Addon_Admin_Tool_Action_Execution_Result
                || !red_addon_admin_tool_action_execution_transaction_active(
                    $connection
                )
            ) {
                $transactionReason = 'action_failed';
                throw new RuntimeException($transactionReason);
            }
            $expectedState = $actionResult->state();
            if ($expectedState->targetRecordId() !== $locked['targetRecordId']) {
                $transactionReason = 'action_result_invalid';
                throw new RuntimeException($transactionReason);
            }
            $expectedStateSha256 = red_addon_admin_tool_action_execution_state_sha256(
                $locked['package'],
                $locked['action'],
                $locked['targetRecordId'],
                $expectedState
            );
            if (!red_addon_valid_sha256($expectedStateSha256)) {
                $transactionReason = 'action_result_invalid';
                throw new RuntimeException($transactionReason);
            }
            $currentState = red_addon_admin_tool_action_execution_invoke_state_loader(
                $binding['stateLoader'] ?? null,
                $connection,
                $target
            );
            if (!$currentState instanceof RED_Addon_Admin_Tool_Action_Target_State
                || $currentState->targetRecordId() !== $locked['targetRecordId']
                || !red_addon_admin_tool_action_execution_transaction_active(
                    $connection
                )
            ) {
                $transactionReason = 'postcondition_failed';
                throw new RuntimeException($transactionReason);
            }
            $currentStateSha256 = red_addon_admin_tool_action_execution_state_sha256(
                $locked['package'],
                $locked['action'],
                $locked['targetRecordId'],
                $currentState
            );
            if (!hash_equals($expectedStateSha256, $currentStateSha256)) {
                $transactionReason = 'postcondition_failed';
                throw new RuntimeException($transactionReason);
            }
            if ($actionResult->hasChanged()) {
                if (hash_equals(
                    $locked['previousStateSha256'],
                    $currentStateSha256
                )) {
                    $transactionReason = 'action_result_invalid';
                    throw new RuntimeException($transactionReason);
                }
            } elseif (!hash_equals(
                $locked['previousStateSha256'],
                $currentStateSha256
            )) {
                $transactionReason = 'action_result_invalid';
                throw new RuntimeException($transactionReason);
            } else {
                if (!mysqli_rollback($connection)) {
                    $transactionReason = 'transaction_failed';
                    throw new RuntimeException($transactionReason);
                }
                $transactionStarted = false;
                $result['unchanged'] = true;
                $result['stateSha256'] = $currentStateSha256;
                $result['reason'] = 'unchanged';
                return $result;
            }
            if (!red_addon_admin_tool_action_execution_update_state(
                $connection,
                $locked,
                $currentStateSha256
            )) {
                $transactionReason = 'execution_ledger_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_install_audit_record(
                $connection,
                'addon.action.completed',
                $locked['package'],
                $locked['packageVersion'],
                $locked['actorRecordId'],
                'succeeded',
                'action_completed'
            )) {
                $transactionReason = 'audit_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_admin_tool_action_execution_transaction_active(
                $connection
            ) || !mysqli_commit($connection)) {
                $transactionReason = 'transaction_failed';
                throw new RuntimeException($transactionReason);
            }
            $transactionStarted = false;
            $result['executed'] = true;
            $result['stateSha256'] = $currentStateSha256;
            $result['reason'] = 'executed';
            return $result;
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                try {
                    mysqli_rollback($connection);
                } catch (Throwable $rollbackFailure) {
                    error_log('RED-CMS administrator action rollback failed.');
                }
            }
            $result['ready'] = false;
            $result['reason'] = $transactionReason;
            return $result;
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock($connection, $initial['package']);
            }
            if ($lifecycleLocked) {
                red_addon_lifecycle_unlock($connection);
            }
        }
    }
}

?>
