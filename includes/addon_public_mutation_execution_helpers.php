<?php
/**
 * Atomic internal execution for one declared public add-on mutation.
 *
 * This is deliberately not an HTTP endpoint or browser bridge. A later
 * core-owned dispatcher must establish same-origin request handling, parse one
 * declared form body, resolve the opaque subject, and supply only typed command
 * values plus CSRF/idempotency evidence. This helper never reads request,
 * cookie, session, header, or package-file globals; enables a package; serves
 * a route; or constructs a public response.
 */

require_once __DIR__ . '/addon_public_mutation_idempotency_helpers.php';
require_once __DIR__ . '/addon_install_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_service_helpers.php';

if (!function_exists('red_addon_public_mutation_execution_transaction_active')) {
    function red_addon_public_mutation_execution_transaction_active($connection)
    {
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_public_mutation_execution_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_public_mutation_execution_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_execution_outcome_valid')) {
    function red_addon_public_mutation_execution_outcome_valid($outcome)
    {
        return is_string($outcome)
            && in_array($outcome, ['accepted', 'unchanged'], true);
    }
}

if (!function_exists('red_addon_public_mutation_execution_result')) {
    function red_addon_public_mutation_execution_result(
        $routeId,
        $mutationId,
        $reason = 'invalid_request'
    ) {
        return [
            'completed' => false,
            'replayed' => false,
            'outcome' => '',
            'route' => is_string($routeId)
                && red_addon_valid_capability($routeId)
                    ? $routeId
                    : '',
            'mutation' => is_string($mutationId)
                && red_addon_valid_capability($mutationId)
                    ? $mutationId
                    : '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_execution_storage_available')) {
    function red_addon_public_mutation_execution_storage_available($connection)
    {
        if (!red_addon_public_mutation_idempotency_storage_available(
            $connection
        )) {
            return false;
        }
        try {
            $query = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(
                    ':',
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME IN (
                         'RED_Addon_Public_Mutation_Subjects',
                         'RED_Addon_Public_Mutation_CSRF_Tokens',
                         'RED_Addon_Public_Mutation_Rate_Limits',
                         'RED_Addon_Public_Mutation_Idempotency_Keys',
                         'RED_Addon_Public_Mutation_Executions'
                       )
                       AND ENGINE='InnoDB'),
                    (SELECT COUNT(*)=7
                       AND SUM(COLUMN_NAME='RecordID'
                         AND COLUMN_TYPE='bigint unsigned'
                         AND IS_NULLABLE='NO'
                         AND EXTRA='auto_increment')=1
                       AND SUM(COLUMN_NAME='IdempotencyRecordID'
                         AND COLUMN_TYPE='int unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='CommandSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='Outcome'
                         AND COLUMN_TYPE='varchar(16)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='PreviousStateSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='StateSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='CompletedAt'
                         AND DATA_TYPE='datetime'
                         AND IS_NULLABLE='NO')=1
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
                        AND INDEX_NAME='PRIMARY')='RecordID'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
                        AND INDEX_NAME=
                          'uq_red_addon_public_mutation_execution_idempotency')
                       ='IdempotencyRecordID'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Executions'
                        AND INDEX_NAME=
                          'idx_red_addon_public_mutation_execution_completed')
                       ='CompletedAt,RecordID'),
                    (SELECT COUNT(*)=1
                       AND SUM(CONSTRAINT_NAME=
                             'fk_red_addon_public_mutation_execution_idempotency'
                         AND TABLE_NAME=
                           'RED_Addon_Public_Mutation_Executions'
                         AND REFERENCED_TABLE_NAME=
                           'RED_Addon_Public_Mutation_Idempotency_Keys'
                         AND DELETE_RULE='CASCADE'
                         AND UPDATE_RULE='RESTRICT')=1
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND CONSTRAINT_NAME=
                         'fk_red_addon_public_mutation_execution_idempotency')
                 ) AS StorageState"
            );
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            return (string) ($row['StorageState'] ?? '')
                === '5:1:1:1:1:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_execution_identifier_valid')) {
    function red_addon_public_mutation_execution_identifier_valid(
        $value,
        $minLength,
        $maxLength
    ) {
        return is_string($value)
            && is_int($minLength)
            && is_int($maxLength)
            && strlen($value) >= $minLength
            && strlen($value) <= $maxLength
            && preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9._~-]*\z/D',
                $value
            ) === 1;
    }
}

if (!function_exists('red_addon_public_mutation_execution_fields')) {
    function red_addon_public_mutation_execution_fields(
        array $contract,
        $fields
    ) {
        if (!is_array($fields) || array_is_list($fields)) {
            return null;
        }
        $declared = [];
        foreach ($contract['requestFields'] ?? [] as $field) {
            $key = is_array($field) && is_string($field['key'] ?? null)
                ? $field['key']
                : '';
            if ($key === '' || isset($declared[$key])) {
                return null;
            }
            $declared[$key] = $field;
        }
        if ($declared === [] || count($declared) > 8) {
            return null;
        }
        foreach ($fields as $key => $_value) {
            if (!is_string($key) || !isset($declared[$key])) {
                return null;
            }
        }
        $normalized = [];
        foreach ($declared as $key => $field) {
            $required = $field['required'] ?? null;
            $present = array_key_exists($key, $fields);
            if (!$present) {
                if ($required !== false) {
                    return null;
                }
                continue;
            }
            $value = $fields[$key];
            if (($field['type'] ?? null) === 'identifier') {
                if (!red_addon_public_mutation_execution_identifier_valid(
                    $value,
                    $field['minLength'] ?? null,
                    $field['maxLength'] ?? null
                )) {
                    return null;
                }
            } elseif (($field['type'] ?? null) === 'positive-integer') {
                if (!is_int($value)
                    || !is_int($field['minimum'] ?? null)
                    || !is_int($field['maximum'] ?? null)
                    || $value < $field['minimum']
                    || $value > $field['maximum']
                ) {
                    return null;
                }
            } else {
                return null;
            }
            $normalized[$key] = $value;
        }
        ksort($normalized, SORT_STRING);
        return $normalized;
    }
}

if (!class_exists('RED_Addon_Public_Mutation_Command', false)) {
    final class RED_Addon_Public_Mutation_Command
    {
        private string $packageId;
        private string $routeId;
        private string $mutationId;
        private int $subjectRecordId;
        private array $fields;

        public function __construct(
            string $packageId,
            string $routeId,
            string $mutationId,
            int $subjectRecordId,
            array $fields
        ) {
            $normalized = red_addon_service_payload($fields);
            if (!red_addon_valid_package_id($packageId)
                || !red_addon_valid_capability($routeId)
                || !red_addon_valid_capability($mutationId)
                || $subjectRecordId < 1
                || $subjectRecordId > 2147483647
                || !is_array($normalized)
            ) {
                throw new InvalidArgumentException(
                    'Public mutation command is invalid.'
                );
            }
            ksort($normalized, SORT_STRING);
            $this->packageId = $packageId;
            $this->routeId = $routeId;
            $this->mutationId = $mutationId;
            $this->subjectRecordId = $subjectRecordId;
            $this->fields = $normalized;
        }

        public function packageId(): string
        {
            return $this->packageId;
        }

        public function routeId(): string
        {
            return $this->routeId;
        }

        public function mutationId(): string
        {
            return $this->mutationId;
        }

        public function subjectRecordId(): int
        {
            return $this->subjectRecordId;
        }

        public function fields(): array
        {
            return $this->fields;
        }

        public function field(string $key)
        {
            return $this->fields[$key] ?? null;
        }
    }
}

if (!function_exists('red_addon_public_mutation_execution_canonical_state')) {
    function red_addon_public_mutation_execution_canonical_state(array $state)
    {
        $normalized = red_addon_service_payload($state);
        if (!is_array($normalized)) {
            return null;
        }
        $canonicalize = static function (array $value) use (&$canonicalize) {
            if (array_is_list($value)) {
                $result = [];
                foreach ($value as $item) {
                    $result[] = is_array($item)
                        ? $canonicalize($item)
                        : $item;
                }
                return $result;
            }
            ksort($value, SORT_STRING);
            foreach ($value as $key => $item) {
                $value[$key] = is_array($item)
                    ? $canonicalize($item)
                    : $item;
            }
            return $value;
        };
        return $canonicalize($normalized);
    }
}

if (!class_exists('RED_Addon_Public_Mutation_State', false)) {
    final class RED_Addon_Public_Mutation_State
    {
        private int $subjectRecordId;
        private array $state;

        public function __construct(int $subjectRecordId, array $state)
        {
            $normalized = red_addon_public_mutation_execution_canonical_state(
                $state
            );
            if ($subjectRecordId < 1
                || $subjectRecordId > 2147483647
                || !is_array($normalized)
            ) {
                throw new InvalidArgumentException(
                    'Public mutation state is invalid.'
                );
            }
            $this->subjectRecordId = $subjectRecordId;
            $this->state = $normalized;
        }

        public function subjectRecordId(): int
        {
            return $this->subjectRecordId;
        }

        public function state(): array
        {
            return $this->state;
        }
    }
}

if (!class_exists('RED_Addon_Public_Mutation_Execution_Request', false)) {
    final class RED_Addon_Public_Mutation_Execution_Request
    {
        private RED_Addon_Public_Mutation_Command $command;
        private string $previousStateSha256;
        private string $planSha256;

        public function __construct(
            RED_Addon_Public_Mutation_Command $command,
            string $previousStateSha256,
            string $planSha256
        ) {
            if (!red_addon_valid_sha256($previousStateSha256)
                || !red_addon_valid_sha256($planSha256)
            ) {
                throw new InvalidArgumentException(
                    'Public mutation execution evidence is invalid.'
                );
            }
            $this->command = $command;
            $this->previousStateSha256 = $previousStateSha256;
            $this->planSha256 = $planSha256;
        }

        public function packageId(): string
        {
            return $this->command->packageId();
        }

        public function routeId(): string
        {
            return $this->command->routeId();
        }

        public function mutationId(): string
        {
            return $this->command->mutationId();
        }

        public function subjectRecordId(): int
        {
            return $this->command->subjectRecordId();
        }

        public function fields(): array
        {
            return $this->command->fields();
        }

        public function field(string $key)
        {
            return $this->command->field($key);
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

if (!class_exists('RED_Addon_Public_Mutation_Execution_Result', false)) {
    final class RED_Addon_Public_Mutation_Execution_Result
    {
        private string $outcome;
        private RED_Addon_Public_Mutation_State $state;

        private function __construct(
            string $outcome,
            RED_Addon_Public_Mutation_State $state
        ) {
            $this->outcome = $outcome;
            $this->state = $state;
        }

        public static function accepted(
            RED_Addon_Public_Mutation_State $state
        ): self {
            return new self('accepted', $state);
        }

        public static function unchanged(
            RED_Addon_Public_Mutation_State $state
        ): self {
            return new self('unchanged', $state);
        }

        public function outcome(): string
        {
            return $this->outcome;
        }

        public function state(): RED_Addon_Public_Mutation_State
        {
            return $this->state;
        }
    }
}

if (!function_exists('red_addon_public_mutation_execution_command_from_contract')) {
    function red_addon_public_mutation_execution_command_from_contract(
        array $declarationPlan,
        array $contract,
        $subjectRecordId,
        $fields
    ) {
        if (!red_addon_public_mutation_declaration_preflight_is_valid(
            $declarationPlan
        ) || !is_int($subjectRecordId)
            || $subjectRecordId < 1
            || !hash_equals(
                $declarationPlan['contractSha256'],
                red_addon_public_mutation_contract_fingerprint($contract)
            )
        ) {
            return null;
        }
        $normalized = red_addon_public_mutation_execution_fields(
            $contract,
            $fields
        );
        if (!is_array($normalized)) {
            return null;
        }
        try {
            return new RED_Addon_Public_Mutation_Command(
                $declarationPlan['packageId'],
                $declarationPlan['route'],
                $declarationPlan['mutation'],
                $subjectRecordId,
                $normalized
            );
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_public_mutation_execution_command_sha256')) {
    function red_addon_public_mutation_execution_command_sha256(
        RED_Addon_Public_Mutation_Command $command,
        array $declarationPlan,
        $idempotencyKey
    ) {
        if (!red_addon_public_mutation_declaration_preflight_is_valid(
            $declarationPlan
        ) || !red_addon_public_mutation_valid_opaque_token($idempotencyKey)) {
            return '';
        }
        $encoded = json_encode(
            [
                'schema' => 1,
                'purpose' => 'public-mutation-command',
                'packageId' => $command->packageId(),
                'route' => $command->routeId(),
                'mutation' => $command->mutationId(),
                'subjectRecordId' => (string) $command->subjectRecordId(),
                'contractSha256' => $declarationPlan['contractSha256'],
                'fields' => $command->fields(),
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded)
            ? hash_hmac('sha256', $encoded, $idempotencyKey)
            : '';
    }
}

if (!function_exists('red_addon_public_mutation_execution_state_sha256')) {
    function red_addon_public_mutation_execution_state_sha256(
        RED_Addon_Public_Mutation_Command $command,
        $commandSha256,
        RED_Addon_Public_Mutation_State $state,
        $idempotencyKey
    ) {
        if (!red_addon_valid_sha256($commandSha256)
            || !red_addon_public_mutation_valid_opaque_token($idempotencyKey)
            || $state->subjectRecordId() !== $command->subjectRecordId()
        ) {
            return '';
        }
        $encoded = json_encode(
            [
                'schema' => 1,
                'purpose' => 'public-mutation-state',
                'commandSha256' => $commandSha256,
                'packageId' => $command->packageId(),
                'route' => $command->routeId(),
                'mutation' => $command->mutationId(),
                'subjectRecordId' => (string) $command->subjectRecordId(),
                'state' => $state->state(),
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded)
            ? hash_hmac('sha256', $encoded, $idempotencyKey)
            : '';
    }
}

if (!function_exists('red_addon_public_mutation_execution_tables')) {
    function red_addon_public_mutation_execution_tables($tables)
    {
        if (!is_array($tables) || !array_is_list($tables)) {
            return null;
        }
        $normalized = [];
        foreach ($tables as $table) {
            if (!red_addon_valid_public_mutation_table($table)
                || isset($normalized[$table])
            ) {
                return null;
            }
            $normalized[$table] = true;
        }
        if ($normalized === [] || count($normalized) > 8) {
            return null;
        }
        $tables = array_keys($normalized);
        sort($tables, SORT_STRING);
        return $tables;
    }
}

if (!function_exists('red_addon_public_mutation_execution_binding')) {
    function red_addon_public_mutation_execution_binding(
        array $manifest,
        array $declarationPlan
    ) {
        if (!red_addon_public_mutation_declaration_preflight_is_valid(
            $declarationPlan
        ) || red_addon_runtime_manifest($declarationPlan['packageId']) !== $manifest
        ) {
            return null;
        }
        $contract = red_addon_public_mutation_contract(
            $manifest,
            $declarationPlan['route'],
            $declarationPlan['mutation']
        );
        if (!is_array($contract)
            || !hash_equals(
                $declarationPlan['contractSha256'],
                red_addon_public_mutation_contract_fingerprint($contract)
            )
        ) {
            return null;
        }
        $mutationId = $declarationPlan['mutation'];
        $handlerOwner = red_addon_runtime_owner(
            'publicMutationHandlers',
            $mutationId
        );
        $stateLoaderOwner = red_addon_runtime_owner(
            'publicMutationStateLoaders',
            $mutationId
        );
        $handler = red_addon_runtime_handler('publicMutationHandlers', $mutationId);
        $stateLoader = red_addon_runtime_handler(
            'publicMutationStateLoaders',
            $mutationId
        );
        $tables = red_addon_public_mutation_execution_tables(
            red_addon_runtime_metadata('publicMutationHandlers', $mutationId)['tables']
                ?? null
        );
        $contractTables = red_addon_public_mutation_execution_tables(
            $contract['tables'] ?? null
        );
        if (!is_string($handlerOwner)
            || !is_string($stateLoaderOwner)
            || !hash_equals($declarationPlan['packageId'], $handlerOwner)
            || !hash_equals($declarationPlan['packageId'], $stateLoaderOwner)
            || !is_callable($handler)
            || !is_callable($stateLoader)
            || !is_array($tables)
            || !is_array($contractTables)
            || $tables !== $contractTables
        ) {
            return null;
        }
        return [
            'contract' => $contract,
            'handler' => $handler,
            'stateLoader' => $stateLoader,
            'tables' => $tables,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_execution_package_version')) {
    function red_addon_public_mutation_execution_package_version(
        $connection,
        $packageId,
        $lock = false
    ) {
        if (!red_addon_valid_package_id($packageId)) {
            return '';
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT PackageVersion
                 FROM RED_Addon_Installations
                 WHERE PackageID=? AND LifecycleState='enabled'
                 LIMIT 1" . ($lock ? ' FOR UPDATE' : '')
            );
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
            $version = is_array($row) && is_string($row['PackageVersion'] ?? null)
                ? $row['PackageVersion']
                : '';
            return red_addon_valid_semantic_version($version) ? $version : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_public_mutation_execution_csrf_locked')) {
    function red_addon_public_mutation_execution_csrf_locked(
        $connection,
        $subjectRecordId,
        array $declarationPlan,
        $token
    ) {
        $scopeSha256 = red_addon_public_mutation_csrf_scope_sha256(
            $connection,
            $declarationPlan
        );
        $tokenSha256 = red_addon_public_mutation_opaque_token_sha256($token);
        if (!is_int($subjectRecordId)
            || $subjectRecordId < 1
            || !red_addon_valid_sha256($scopeSha256)
            || !red_addon_valid_sha256($tokenSha256)
        ) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT t.RecordID
                 FROM RED_Addon_Public_Mutation_CSRF_Tokens t
                 INNER JOIN RED_Addon_Public_Mutation_Subjects s
                   ON s.RecordID=t.SubjectRecordID
                 WHERE t.SubjectRecordID=?
                   AND BINARY t.ScopeSHA256=BINARY ?
                   AND BINARY t.TokenSHA256=BINARY ?
                   AND t.ExpiresAt > UTC_TIMESTAMP()
                   AND s.ExpiresAt > UTC_TIMESTAMP()
                 LIMIT 1 FOR UPDATE'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'iss',
                $subjectRecordId,
                $scopeSha256,
                $tokenSha256
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return false;
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            return (int) ($row['RecordID'] ?? 0) > 0;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_execution_idempotency_locked')) {
    function red_addon_public_mutation_execution_idempotency_locked(
        $connection,
        $subjectRecordId,
        array $declarationPlan,
        $key
    ) {
        $result = [
            'status' => 'invalid',
            'idempotencyRecordId' => 0,
            'commandSha256' => '',
            'outcome' => '',
            'previousStateSha256' => '',
            'stateSha256' => '',
        ];
        $scopeSha256 = red_addon_public_mutation_idempotency_scope_sha256(
            $connection,
            $declarationPlan
        );
        $keySha256 = red_addon_public_mutation_opaque_token_sha256($key);
        if (!is_int($subjectRecordId)
            || $subjectRecordId < 1
            || !red_addon_valid_sha256($scopeSha256)
            || !red_addon_valid_sha256($keySha256)
        ) {
            return $result;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT k.RecordID,
                    e.RecordID AS ExecutionRecordID,
                    e.CommandSHA256,
                    e.Outcome,
                    e.PreviousStateSHA256,
                    e.StateSHA256
                 FROM RED_Addon_Public_Mutation_Idempotency_Keys k
                 INNER JOIN RED_Addon_Public_Mutation_Subjects s
                   ON s.RecordID=k.SubjectRecordID
                 LEFT JOIN RED_Addon_Public_Mutation_Executions e
                   ON e.IdempotencyRecordID=k.RecordID
                 WHERE k.SubjectRecordID=?
                   AND BINARY k.ScopeSHA256=BINARY ?
                   AND BINARY k.KeySHA256=BINARY ?
                   AND k.ExpiresAt > UTC_TIMESTAMP()
                   AND s.ExpiresAt > UTC_TIMESTAMP()
                 LIMIT 1 FOR UPDATE'
            );
            if (!$statement) {
                $result['status'] = 'failed';
                return $result;
            }
            mysqli_stmt_bind_param(
                $statement,
                'iss',
                $subjectRecordId,
                $scopeSha256,
                $keySha256
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $result['status'] = 'failed';
                return $result;
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $result['status'] = 'failed';
            return $result;
        }
        $recordId = is_array($row) ? (int) ($row['RecordID'] ?? 0) : 0;
        if ($recordId < 1) {
            return $result;
        }
        $result['idempotencyRecordId'] = $recordId;
        $executionRecordId = (int) ($row['ExecutionRecordID'] ?? 0);
        if ($executionRecordId < 1) {
            $result['status'] = 'available';
            return $result;
        }
        $commandSha256 = is_string($row['CommandSHA256'] ?? null)
            ? $row['CommandSHA256']
            : '';
        $outcome = is_string($row['Outcome'] ?? null) ? $row['Outcome'] : '';
        $previousStateSha256 = is_string($row['PreviousStateSHA256'] ?? null)
            ? $row['PreviousStateSHA256']
            : '';
        $stateSha256 = is_string($row['StateSHA256'] ?? null)
            ? $row['StateSHA256']
            : '';
        if (!red_addon_valid_sha256($commandSha256)
            || !red_addon_public_mutation_execution_outcome_valid($outcome)
            || !red_addon_valid_sha256($previousStateSha256)
            || !red_addon_valid_sha256($stateSha256)
        ) {
            $result['status'] = 'failed';
            return $result;
        }
        $result['status'] = 'completed';
        $result['commandSha256'] = $commandSha256;
        $result['outcome'] = $outcome;
        $result['previousStateSha256'] = $previousStateSha256;
        $result['stateSha256'] = $stateSha256;
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_execution_invoke_state_loader')) {
    function red_addon_public_mutation_execution_invoke_state_loader(
        $stateLoader,
        $connection,
        RED_Addon_Public_Mutation_Command $command
    ) {
        if (!is_callable($stateLoader)) {
            return null;
        }
        $bufferLevel = ob_get_level();
        $state = null;
        $emitted = '';
        try {
            ob_start();
            $state = $stateLoader($connection, $command);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Public mutation state loader altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log('RED-CMS public-mutation state loader failed.');
            return null;
        }
        return $emitted === ''
            && $state instanceof RED_Addon_Public_Mutation_State
                ? $state
                : null;
    }
}

if (!function_exists('red_addon_public_mutation_execution_invoke_handler')) {
    function red_addon_public_mutation_execution_invoke_handler(
        $handler,
        $connection,
        RED_Addon_Public_Mutation_Execution_Request $request
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
                    'Public mutation handler altered output buffers.'
                );
            }
            $emitted = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log('RED-CMS public-mutation handler failed.');
            return null;
        }
        return $emitted === ''
            && $result instanceof RED_Addon_Public_Mutation_Execution_Result
                ? $result
                : null;
    }
}

if (!function_exists('red_addon_public_mutation_execution_reserve')) {
    function red_addon_public_mutation_execution_reserve(
        $connection,
        $idempotencyRecordId,
        $commandSha256,
        $previousStateSha256
    ) {
        if (!is_int($idempotencyRecordId)
            || $idempotencyRecordId < 1
            || !red_addon_valid_sha256($commandSha256)
            || !red_addon_valid_sha256($previousStateSha256)
        ) {
            return 'failed';
        }
        $outcome = 'accepted';
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Addon_Public_Mutation_Executions (
                    IdempotencyRecordID, CommandSHA256, Outcome,
                    PreviousStateSHA256, StateSHA256, CompletedAt
                 ) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            );
            if (!$statement) {
                return 'failed';
            }
            mysqli_stmt_bind_param(
                $statement,
                'issss',
                $idempotencyRecordId,
                $commandSha256,
                $outcome,
                $previousStateSha256,
                $previousStateSha256
            );
            $executed = mysqli_stmt_execute($statement);
            $errorCode = mysqli_stmt_errno($statement);
            mysqli_stmt_close($statement);
            if ($executed) {
                return 'reserved';
            }
            return $errorCode === 1062 ? 'duplicate' : 'failed';
        } catch (Throwable $throwable) {
            return 'failed';
        }
    }
}

if (!function_exists('red_addon_public_mutation_execution_finalize')) {
    function red_addon_public_mutation_execution_finalize(
        $connection,
        $idempotencyRecordId,
        $commandSha256,
        $previousStateSha256,
        $stateSha256,
        $outcome
    ) {
        if (!is_int($idempotencyRecordId)
            || $idempotencyRecordId < 1
            || !red_addon_valid_sha256($commandSha256)
            || !red_addon_valid_sha256($previousStateSha256)
            || !red_addon_valid_sha256($stateSha256)
            || !red_addon_public_mutation_execution_outcome_valid($outcome)
        ) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'UPDATE RED_Addon_Public_Mutation_Executions
                 SET Outcome=?, StateSHA256=?, CompletedAt=UTC_TIMESTAMP()
                 WHERE IdempotencyRecordID=?
                   AND BINARY CommandSHA256=BINARY ?
                   AND BINARY PreviousStateSHA256=BINARY ?
                   AND BINARY StateSHA256=BINARY ?'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param(
                $statement,
                'ssisss',
                $outcome,
                $stateSha256,
                $idempotencyRecordId,
                $commandSha256,
                $previousStateSha256,
                $previousStateSha256
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

if (!function_exists('red_addon_public_mutation_execution_audit_record')) {
    function red_addon_public_mutation_execution_audit_record(
        $connection,
        $packageId,
        $packageVersion,
        $outcome
    ) {
        if (!red_addon_public_mutation_execution_outcome_valid($outcome)) {
            return false;
        }
        return red_addon_install_audit_record(
            $connection,
            'addon.public-mutation.completed',
            $packageId,
            $packageVersion,
            0,
            'succeeded',
            'public_mutation_' . $outcome
        );
    }
}

if (!function_exists('red_addon_public_mutation_execute')) {
    function red_addon_public_mutation_execute(
        $connection,
        array $manifest,
        $routeId,
        $mutationId,
        array $subject,
        $csrfToken,
        $idempotencyKey,
        $fields
    ) {
        $result = red_addon_public_mutation_execution_result(
            $routeId,
            $mutationId
        );
        $declarationPlan = red_addon_public_mutation_declaration_preflight(
            $manifest,
            $routeId,
            $mutationId
        );
        if (!red_addon_public_mutation_declaration_preflight_is_valid(
            $declarationPlan
        )) {
            $result['reason'] = 'declaration_invalid';
            return $result;
        }
        $subjectRecordId = red_addon_public_mutation_subject_record_id($subject);
        if ($subjectRecordId < 1) {
            $result['reason'] = 'subject_invalid';
            return $result;
        }
        $contract = red_addon_public_mutation_contract(
            $manifest,
            $declarationPlan['route'],
            $declarationPlan['mutation']
        );
        if (!is_array($contract)) {
            $result['reason'] = 'declaration_invalid';
            return $result;
        }
        $command = red_addon_public_mutation_execution_command_from_contract(
            $declarationPlan,
            $contract,
            $subjectRecordId,
            $fields
        );
        if (!$command instanceof RED_Addon_Public_Mutation_Command) {
            $result['reason'] = 'command_invalid';
            return $result;
        }
        if (!red_addon_public_mutation_valid_opaque_token($idempotencyKey)) {
            $result['reason'] = 'idempotency_invalid';
            return $result;
        }
        $commandSha256 = red_addon_public_mutation_execution_command_sha256(
            $command,
            $declarationPlan,
            $idempotencyKey
        );
        if (!red_addon_valid_sha256($commandSha256)) {
            $result['reason'] = 'idempotency_invalid';
            return $result;
        }
        $binding = red_addon_public_mutation_execution_binding(
            $manifest,
            $declarationPlan
        );
        if (!is_array($binding)) {
            $result['reason'] = 'runtime_unavailable';
            return $result;
        }
        if (!red_addon_public_mutation_execution_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        $transactionTables = array_merge(
            [
                'RED_Addon_Installations',
                'RED_Addon_Activity_Log',
                'RED_Addon_Public_Mutation_Subjects',
                'RED_Addon_Public_Mutation_CSRF_Tokens',
                'RED_Addon_Public_Mutation_Rate_Limits',
                'RED_Addon_Public_Mutation_Idempotency_Keys',
                'RED_Addon_Public_Mutation_Executions',
            ],
            $binding['tables']
        );
        if (!red_admin_transaction_tables_supported(
            $connection,
            $transactionTables
        )) {
            $result['reason'] = 'transaction_unsupported';
            return $result;
        }
        if (red_addon_public_mutation_execution_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }

        $lifecycleLocked = false;
        $packageLocked = false;
        $transactionStarted = false;
        $transactionReason = 'transaction_failed';
        $packageId = $declarationPlan['packageId'];
        try {
            if (!red_addon_lifecycle_lock($connection)) {
                $result['reason'] = 'lifecycle_lock_failed';
                return $result;
            }
            $lifecycleLocked = true;
            if (!red_addon_install_lock($connection, $packageId)) {
                $result['reason'] = 'package_lock_failed';
                return $result;
            }
            $packageLocked = true;
            if (red_addon_public_mutation_execution_transaction_active(
                $connection
            ) || !mysqli_begin_transaction($connection)) {
                $result['reason'] = 'transaction_failed';
                return $result;
            }
            $transactionStarted = true;
            $packageVersion = red_addon_public_mutation_execution_package_version(
                $connection,
                $packageId,
                true
            );
            if ($packageVersion === '') {
                $transactionReason = 'package_not_enabled';
                throw new RuntimeException($transactionReason);
            }
            $lockedPlan = red_addon_public_mutation_declaration_preflight(
                $manifest,
                $routeId,
                $mutationId
            );
            $lockedBinding = red_addon_public_mutation_execution_binding(
                $manifest,
                $lockedPlan
            );
            if (!red_addon_public_mutation_declaration_preflight_is_valid(
                $lockedPlan
            ) || !is_array($lockedBinding)
                || !hash_equals(
                    $declarationPlan['planSha256'],
                    $lockedPlan['planSha256']
                )
                || $lockedBinding['tables'] !== $binding['tables']
            ) {
                $transactionReason = 'runtime_unavailable';
                throw new RuntimeException($transactionReason);
            }
            if (red_addon_public_mutation_rate_limit_locked_subject(
                $connection,
                $subjectRecordId
            ) < 1) {
                $transactionReason = 'subject_invalid';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_public_mutation_execution_csrf_locked(
                $connection,
                $subjectRecordId,
                $lockedPlan,
                $csrfToken
            )) {
                $transactionReason = 'csrf_invalid';
                throw new RuntimeException($transactionReason);
            }
            $idempotency = red_addon_public_mutation_execution_idempotency_locked(
                $connection,
                $subjectRecordId,
                $lockedPlan,
                $idempotencyKey
            );
            if (($idempotency['status'] ?? '') === 'failed') {
                $transactionReason = 'idempotency_unavailable';
                throw new RuntimeException($transactionReason);
            }
            if (($idempotency['status'] ?? '') === 'invalid') {
                $transactionReason = 'idempotency_invalid';
                throw new RuntimeException($transactionReason);
            }
            if (($idempotency['status'] ?? '') === 'completed') {
                if (!hash_equals(
                    $idempotency['commandSha256'],
                    $commandSha256
                )) {
                    $transactionReason = 'idempotency_conflict';
                    throw new RuntimeException($transactionReason);
                }
                if (!red_addon_public_mutation_execution_transaction_active(
                    $connection
                ) || !mysqli_commit($connection)) {
                    $transactionReason = 'transaction_failed';
                    throw new RuntimeException($transactionReason);
                }
                $transactionStarted = false;
                $result['replayed'] = true;
                $result['outcome'] = $idempotency['outcome'];
                $result['reason'] = 'replayed';
                return $result;
            }
            if (($idempotency['status'] ?? '') !== 'available') {
                $transactionReason = 'idempotency_unavailable';
                throw new RuntimeException($transactionReason);
            }
            $rateLimit = red_addon_public_mutation_rate_limit_claim_in_transaction(
                $connection,
                $subject,
                $lockedPlan
            );
            if (($rateLimit['reason'] ?? '') === 'rate_limited') {
                if (!red_addon_public_mutation_execution_transaction_active(
                    $connection
                ) || !mysqli_commit($connection)) {
                    $transactionReason = 'transaction_failed';
                    throw new RuntimeException($transactionReason);
                }
                $transactionStarted = false;
                $result['reason'] = 'rate_limited';
                return $result;
            }
            if (empty($rateLimit['valid']) || empty($rateLimit['allowed'])) {
                $transactionReason = 'rate_limit_failed';
                throw new RuntimeException($transactionReason);
            }
            $previousState = red_addon_public_mutation_execution_invoke_state_loader(
                $lockedBinding['stateLoader'],
                $connection,
                $command
            );
            if (!$previousState instanceof RED_Addon_Public_Mutation_State
                || $previousState->subjectRecordId() !== $subjectRecordId
                || !red_addon_public_mutation_execution_transaction_active(
                    $connection
                )
            ) {
                $transactionReason = 'state_loader_failed';
                throw new RuntimeException($transactionReason);
            }
            $previousStateSha256 = red_addon_public_mutation_execution_state_sha256(
                $command,
                $commandSha256,
                $previousState,
                $idempotencyKey
            );
            if (!red_addon_valid_sha256($previousStateSha256)) {
                $transactionReason = 'state_invalid';
                throw new RuntimeException($transactionReason);
            }
            $reservation = red_addon_public_mutation_execution_reserve(
                $connection,
                $idempotency['idempotencyRecordId'],
                $commandSha256,
                $previousStateSha256
            );
            if ($reservation !== 'reserved') {
                $transactionReason = $reservation === 'duplicate'
                    ? 'idempotency_conflict'
                    : 'execution_reservation_failed';
                throw new RuntimeException($transactionReason);
            }
            $request = new RED_Addon_Public_Mutation_Execution_Request(
                $command,
                $previousStateSha256,
                $lockedPlan['planSha256']
            );
            $execution = red_addon_public_mutation_execution_invoke_handler(
                $lockedBinding['handler'],
                $connection,
                $request
            );
            if (!$execution instanceof RED_Addon_Public_Mutation_Execution_Result
                || !red_addon_public_mutation_execution_transaction_active(
                    $connection
                )
            ) {
                $transactionReason = 'handler_failed';
                throw new RuntimeException($transactionReason);
            }
            $expectedState = $execution->state();
            if ($expectedState->subjectRecordId() !== $subjectRecordId) {
                $transactionReason = 'execution_result_invalid';
                throw new RuntimeException($transactionReason);
            }
            $expectedStateSha256 = red_addon_public_mutation_execution_state_sha256(
                $command,
                $commandSha256,
                $expectedState,
                $idempotencyKey
            );
            if (!red_addon_valid_sha256($expectedStateSha256)) {
                $transactionReason = 'execution_result_invalid';
                throw new RuntimeException($transactionReason);
            }
            $currentState = red_addon_public_mutation_execution_invoke_state_loader(
                $lockedBinding['stateLoader'],
                $connection,
                $command
            );
            if (!$currentState instanceof RED_Addon_Public_Mutation_State
                || $currentState->subjectRecordId() !== $subjectRecordId
                || !red_addon_public_mutation_execution_transaction_active(
                    $connection
                )
            ) {
                $transactionReason = 'postcondition_failed';
                throw new RuntimeException($transactionReason);
            }
            $currentStateSha256 = red_addon_public_mutation_execution_state_sha256(
                $command,
                $commandSha256,
                $currentState,
                $idempotencyKey
            );
            if (!hash_equals($expectedStateSha256, $currentStateSha256)) {
                $transactionReason = 'postcondition_failed';
                throw new RuntimeException($transactionReason);
            }
            if ($execution->outcome() === 'accepted') {
                if (hash_equals($previousStateSha256, $currentStateSha256)) {
                    $transactionReason = 'execution_result_invalid';
                    throw new RuntimeException($transactionReason);
                }
            } elseif ($execution->outcome() === 'unchanged') {
                if (!hash_equals($previousStateSha256, $currentStateSha256)) {
                    $transactionReason = 'execution_result_invalid';
                    throw new RuntimeException($transactionReason);
                }
            } else {
                $transactionReason = 'execution_result_invalid';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_public_mutation_execution_finalize(
                $connection,
                $idempotency['idempotencyRecordId'],
                $commandSha256,
                $previousStateSha256,
                $currentStateSha256,
                $execution->outcome()
            )) {
                $transactionReason = 'execution_ledger_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_public_mutation_execution_audit_record(
                $connection,
                $packageId,
                $packageVersion,
                $execution->outcome()
            )) {
                $transactionReason = 'audit_failed';
                throw new RuntimeException($transactionReason);
            }
            if (!red_addon_public_mutation_execution_transaction_active(
                $connection
            ) || !mysqli_commit($connection)) {
                $transactionReason = 'transaction_failed';
                throw new RuntimeException($transactionReason);
            }
            $transactionStarted = false;
            $result['completed'] = true;
            $result['outcome'] = $execution->outcome();
            $result['reason'] = 'completed';
            return $result;
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                try {
                    mysqli_rollback($connection);
                } catch (Throwable $rollbackFailure) {
                    error_log('RED-CMS public-mutation rollback failed.');
                }
            }
            $result['reason'] = $transactionReason;
            return $result;
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock($connection, $packageId);
            }
            if ($lifecycleLocked) {
                red_addon_lifecycle_unlock($connection);
            }
        }
    }
}

?>
