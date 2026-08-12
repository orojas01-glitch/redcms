<?php
/**
 * Disposable checks for the atomic core-only public-mutation transaction
 * runner. This creates no package filesystem fixture, public endpoint, or
 * Store Lite state.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot .
    '/includes/addon_public_mutation_execution_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_public_mutation_execution)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Public-mutation execution self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$subjectIds = [];
$packageId = 'redcms.public-execution-fixture';
$routeId = 'redcms.public-execution-fixture/cart-intent';
$mutationId = 'redcms.public-execution-fixture/add-to-cart';
$fixtureTable = 'RED_Addon_Public_Execution_Fixture_Carts';
$auditConstraint = 'redcms_public_mutation_execution_audit_failure';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_public_mutation_execution_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_execution_test_bind(
    $statement,
    $types,
    array $values
) {
    if ($types === '') {
        return true;
    }
    $arguments = [$statement, $types];
    foreach ($values as $index => $value) {
        $arguments[] = &$values[$index];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $arguments);
}

function red_addon_public_mutation_execution_test_execute(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement
        || !red_addon_public_mutation_execution_test_bind(
            $statement,
            $types,
            $values
        )
        || !mysqli_stmt_execute($statement)
    ) {
        $error = $statement ? mysqli_stmt_error($statement) : mysqli_error($connection);
        if ($statement) {
            mysqli_stmt_close($statement);
        }
        throw new RuntimeException('Fixture SQL failed: ' . $error);
    }
    $affected = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);
    return $affected;
}

function red_addon_public_mutation_execution_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_public_mutation_execution_test_manifest(
    $packageId,
    $routeId,
    $mutationId,
    $fixtureTable
) {
    return [
        'id' => $packageId,
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => [],
        ],
        'permissions' => ['fixture.settings.manage'],
        'settings' => [[
            'key' => 'fixture.currency',
            'label' => 'Fixture currency',
            'type' => 'text',
            'secret' => false,
            'permission' => 'fixture.settings.manage',
            'default' => null,
        ], [
            'key' => 'fixture.provider-token',
            'label' => 'Fixture provider token',
            'type' => 'secret-reference',
            'secret' => true,
            'permission' => 'fixture.settings.manage',
        ]],
        'routes' => [[
            'id' => $routeId,
            'scope' => 'public',
            'path' => '/addons/redcms/public-execution-fixture/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => $routeId,
            'mutation' => $mutationId,
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 1024,
            'requestFields' => [
                [
                    'key' => 'product',
                    'type' => 'identifier',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 120,
                ],
                [
                    'key' => 'quantity',
                    'type' => 'positive-integer',
                    'required' => true,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => [$fixtureTable],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
            'runtimeSettings' => ['fixture.currency'],
        ]],
    ];
}

function red_addon_public_mutation_execution_test_state($connection, $subjectRecordId)
{
    $statement = mysqli_prepare(
        $connection,
        'SELECT ProductReference, Quantity
         FROM RED_Addon_Public_Execution_Fixture_Carts
         WHERE SubjectRecordID=? LIMIT 1'
    );
    if (!$statement) {
        throw new RuntimeException('Fixture state statement is unavailable.');
    }
    mysqli_stmt_bind_param($statement, 'i', $subjectRecordId);
    if (!mysqli_stmt_execute($statement)) {
        mysqli_stmt_close($statement);
        throw new RuntimeException('Fixture state query failed.');
    }
    $query = mysqli_stmt_get_result($statement);
    $row = $query ? mysqli_fetch_assoc($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    mysqli_stmt_close($statement);
    if (!is_array($row)) {
        return ['present' => false];
    }
    return [
        'present' => true,
        'product' => (string) $row['ProductReference'],
        'quantity' => (int) $row['Quantity'],
    ];
}

function red_addon_public_mutation_execution_test_write_state(
    $connection,
    $subjectRecordId,
    $product,
    $quantity
) {
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'INSERT INTO RED_Addon_Public_Execution_Fixture_Carts (
            SubjectRecordID, ProductReference, Quantity
         ) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            ProductReference=VALUES(ProductReference),
            Quantity=VALUES(Quantity)',
        'isi',
        [$subjectRecordId, $product, $quantity]
    );
}

function red_addon_public_mutation_execution_test_evidence(
    $connection,
    array $plan,
    array &$subjectIds
) {
    $subject = red_addon_public_mutation_subject_issue($connection);
    if (!empty($subject['subjectRecordId'])) {
        $subjectIds[] = $subject['subjectRecordId'];
    }
    $csrf = red_addon_public_mutation_csrf_issue($connection, $subject, $plan);
    $key = red_addon_public_mutation_idempotency_issue(
        $connection,
        $subject,
        $plan
    );
    if (empty($subject['valid']) || empty($csrf['valid']) || empty($key['valid'])) {
        throw new RuntimeException('Fixture core evidence could not be issued.');
    }
    return [$subject, $csrf, $key];
}

function red_addon_public_mutation_execution_test_runtime(
    array $manifest,
    $routeId,
    $mutationId,
    $fixtureTable,
    $routeHandler,
    $handler,
    $stateLoader
) {
    $packageId = $manifest['id'];
    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerRoute($routeId, $routeHandler);
    $registry->registerPublicMutation($mutationId, $handler, [$fixtureTable]);
    $registry->registerPublicMutationStateLoader($mutationId, $stateLoader);
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );
}

function red_addon_public_mutation_execution_test_cleanup(
    $connection,
    $packageId,
    $fixtureTable,
    $auditConstraint,
    array $subjectIds
) {
    $ids = array_values(array_filter(
        array_unique(array_map('intval', $subjectIds)),
        static function ($recordId) {
            return $recordId > 0;
        }
    ));
    try {
        if (red_addon_public_mutation_execution_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA=DATABASE()
               AND TABLE_NAME='RED_Addon_Activity_Log'
               AND CONSTRAINT_NAME='$auditConstraint'"
        ) === '1') {
            mysqli_query(
                $connection,
                'ALTER TABLE RED_Addon_Activity_Log DROP CHECK `'
                    . $auditConstraint . '`'
            );
        }
        red_addon_public_mutation_execution_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Activity_Log WHERE PackageID=?',
            's',
            [$packageId]
        );
        red_addon_public_mutation_execution_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Settings WHERE PackageID=?',
            's',
            [$packageId]
        );
        if ($ids !== []) {
            mysqli_query(
                $connection,
                'DELETE FROM RED_Addon_Public_Mutation_Subjects WHERE RecordID IN (' .
                    implode(',', $ids) . ')'
            );
        }
        red_addon_public_mutation_execution_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Installations WHERE PackageID=?',
            's',
            [$packageId]
        );
        mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $fixtureTable . '`');
    } catch (Throwable $throwable) {
        error_log('Public-mutation execution cleanup failed: ' . $throwable->getMessage());
    }
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
}

try {
    red_addon_public_mutation_execution_test_cleanup(
        $connection,
        $packageId,
        $fixtureTable,
        $auditConstraint,
        []
    );
    red_addon_public_mutation_execution_test_assert(
        red_addon_public_mutation_execution_storage_available($connection)
            && red_addon_valid_public_mutation_table($fixtureTable)
            && !red_addon_valid_public_mutation_table(
                'RED_Addon_Public_Mutation_Executions'
            ),
        'the disposable client exposes exact hash-only execution storage and reserves it from package declarations'
    );
    if (!mysqli_query(
        $connection,
        'CREATE TABLE RED_Addon_Public_Execution_Fixture_Carts (
            SubjectRecordID int unsigned NOT NULL,
            ProductReference varchar(120) NOT NULL,
            Quantity int unsigned NOT NULL,
            PRIMARY KEY (SubjectRecordID)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    )) {
        throw new RuntimeException('Could not create public-mutation fixture table.');
    }
    red_addon_public_mutation_execution_test_execute(
        $connection,
        "INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (?, '0.1.0', 'content-package', ?, ?, 'enabled', 1, 1)",
        'sss',
        [$packageId, str_repeat('a', 64), str_repeat('b', 64)]
    );
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, NULL, ?)',
        'ssssi',
        [
            $packageId,
            'fixture.currency',
            'text',
            '"USD"',
            1,
        ]
    );
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, NULL, ?, ?)',
        'ssssi',
        [
            $packageId,
            'fixture.provider-token',
            'secret-reference',
            'config:fixture-provider-token',
            1,
        ]
    );

    $manifest = red_addon_public_mutation_execution_test_manifest(
        $packageId,
        $routeId,
        $mutationId,
        $fixtureTable
    );
    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_execution_test_assert(
        red_addon_public_mutation_declaration_preflight_is_valid($plan),
        'the runner fixture uses one already-valid closed declaration plan'
    );

    $calls = ['route' => 0, 'handler' => 0, 'loader' => 0];
    $runtimeSettingsObserved = [];
    $handlerMode = 'accepted';
    $loaderMode = 'normal';
    $routeHandler = static function () use (&$calls) {
        $calls['route']++;
        throw new RuntimeException('public route callback must remain uninvoked');
    };
    $stateLoader = static function ($connection, $command) use (
        &$calls,
        &$loaderMode,
        &$runtimeSettingsObserved
    ) {
        $calls['loader']++;
        $settings = $command->runtimeSettings();
        $runtimeSettingsObserved[] = [
            'role' => 'loader',
            'declared' => $settings->declared(),
            'values' => $settings->values(),
            'stateSha256' => $settings->stateSha256(),
            'commandSettingsSha256' => $command->runtimeSettingsSha256(),
        ];
        if ($loaderMode === 'output') {
            echo 'state-loader-output';
        }
        return new RED_Addon_Public_Mutation_State(
            $command->subjectRecordId(),
            red_addon_public_mutation_execution_test_state(
                $connection,
                $command->subjectRecordId()
            )
        );
    };
    $handler = static function ($connection, $request) use (
        &$calls,
        &$handlerMode,
        &$runtimeSettingsObserved
    ) {
        $calls['handler']++;
        $settings = $request->runtimeSettings();
        $runtimeSettingsObserved[] = [
            'role' => 'handler',
            'declared' => $settings->declared(),
            'values' => $settings->values(),
            'stateSha256' => $settings->stateSha256(),
        ];
        $subjectRecordId = $request->subjectRecordId();
        $product = (string) $request->field('product');
        $quantity = (int) $request->field('quantity');
        $current = red_addon_public_mutation_execution_test_state(
            $connection,
            $subjectRecordId
        );
        if ($handlerMode === 'throw') {
            red_addon_public_mutation_execution_test_write_state(
                $connection,
                $subjectRecordId,
                $product,
                $quantity
            );
            throw new RuntimeException('fixture handler failure');
        }
        if ($handlerMode === 'output') {
            red_addon_public_mutation_execution_test_write_state(
                $connection,
                $subjectRecordId,
                $product,
                $quantity
            );
            echo 'handler-output';
            return RED_Addon_Public_Mutation_Execution_Result::accepted(
                new RED_Addon_Public_Mutation_State(
                    $subjectRecordId,
                    ['present' => true, 'product' => $product, 'quantity' => $quantity]
                )
            );
        }
        if ($handlerMode === 'bad-result') {
            red_addon_public_mutation_execution_test_write_state(
                $connection,
                $subjectRecordId,
                $product,
                $quantity
            );
            return true;
        }
        if ($handlerMode === 'wrong-state') {
            red_addon_public_mutation_execution_test_write_state(
                $connection,
                $subjectRecordId,
                $product,
                $quantity
            );
            return RED_Addon_Public_Mutation_Execution_Result::accepted(
                new RED_Addon_Public_Mutation_State(
                    $subjectRecordId,
                    ['present' => true, 'product' => 'wrong-product', 'quantity' => $quantity]
                )
            );
        }
        if ($handlerMode === 'accepted-unchanged') {
            return RED_Addon_Public_Mutation_Execution_Result::accepted(
                new RED_Addon_Public_Mutation_State($subjectRecordId, $current)
            );
        }
        if ($handlerMode === 'unchanged-changed') {
            red_addon_public_mutation_execution_test_write_state(
                $connection,
                $subjectRecordId,
                $product,
                $quantity
            );
            return RED_Addon_Public_Mutation_Execution_Result::unchanged(
                new RED_Addon_Public_Mutation_State(
                    $subjectRecordId,
                    ['present' => true, 'product' => $product, 'quantity' => $quantity]
                )
            );
        }
        if ($handlerMode === 'rollback') {
            red_addon_public_mutation_execution_test_write_state(
                $connection,
                $subjectRecordId,
                $product,
                $quantity
            );
            mysqli_rollback($connection);
            return RED_Addon_Public_Mutation_Execution_Result::accepted(
                new RED_Addon_Public_Mutation_State(
                    $subjectRecordId,
                    ['present' => true, 'product' => $product, 'quantity' => $quantity]
                )
            );
        }
        if ($handlerMode === 'unchanged') {
            return RED_Addon_Public_Mutation_Execution_Result::unchanged(
                new RED_Addon_Public_Mutation_State($subjectRecordId, $current)
            );
        }
        red_addon_public_mutation_execution_test_write_state(
            $connection,
            $subjectRecordId,
            $product,
            $quantity
        );
        return RED_Addon_Public_Mutation_Execution_Result::accepted(
            new RED_Addon_Public_Mutation_State(
                $subjectRecordId,
                ['present' => true, 'product' => $product, 'quantity' => $quantity]
            )
        );
    };
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_public_mutation_execution_test_runtime(
            $manifest,
            $routeId,
            $mutationId,
            $fixtureTable,
            $routeHandler,
            $handler,
            $stateLoader
        );
    red_addon_public_mutation_execution_test_assert(
        ($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']->snapshot()['registrations']
            ['publicMutationHandlers'][$mutationId] ?? '') === $packageId
            && ($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']->snapshot()['registrations']
                ['publicMutationStateLoaders'][$mutationId] ?? '') === $packageId,
        'one in-memory trusted runtime can bind exactly one declared handler and state loader without invoking a route'
    );

    [$subject, $csrf, $key] = red_addon_public_mutation_execution_test_evidence(
        $connection,
        $plan,
        $subjectIds
    );
    $cookieSnapshot = $_COOKIE;
    $sessionStatus = session_status();
    $headerSnapshot = headers_list();
    $accepted = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $subject,
        $csrf['token'],
        $key['key'],
        ['product' => 'percussion-kit', 'quantity' => 2]
    );
    red_addon_public_mutation_execution_test_assert(
        $accepted === [
            'completed' => true,
            'replayed' => false,
            'outcome' => 'accepted',
            'route' => $routeId,
            'mutation' => $mutationId,
            'reason' => 'completed',
        ]
            && $calls === ['route' => 0, 'handler' => 1, 'loader' => 2]
            && count($runtimeSettingsObserved) === 3
            && count(array_filter(
                $runtimeSettingsObserved,
                static function (array $observed): bool {
                    return $observed['declared'] === true
                        && $observed['values'] === ['fixture.currency' => 'USD']
                        && red_addon_valid_sha256($observed['stateSha256']);
                }
            )) === 3
            && count(array_unique(array_column(
                $runtimeSettingsObserved,
                'stateSha256'
            ))) === 1
            && red_addon_public_mutation_execution_test_state(
                $connection,
                $subject['subjectRecordId']
            ) === [
                'present' => true,
                'product' => 'percussion-kit',
                'quantity' => 2,
            ]
            && $_COOKIE === $cookieSnapshot
            && session_status() === $sessionStatus
            && headers_list() === $headerSnapshot,
        'one valid internal command exposes only its declared non-secret setting despite a coexisting secret row, then commits the exact package postcondition with bounded non-browser evidence'
    );
    [$invalidSettingsSubject, $invalidSettingsCsrf, $invalidSettingsKey] =
        red_addon_public_mutation_execution_test_evidence(
            $connection,
            $plan,
            $subjectIds
        );
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'UPDATE RED_Addon_Settings SET ValueJSON=?
         WHERE PackageID=? AND SettingKey=?',
        'sss',
        ['[]', $packageId, 'fixture.currency']
    );
    $callsBeforeRuntimeSettingsFailure = $calls;
    $runtimeSettingsBeforeFailure = count($runtimeSettingsObserved);
    $invalidSettings = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $invalidSettingsSubject,
        $invalidSettingsCsrf['token'],
        $invalidSettingsKey['key'],
        ['product' => 'runtime-settings-fixture', 'quantity' => 1]
    );
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'UPDATE RED_Addon_Settings SET ValueJSON=?
         WHERE PackageID=? AND SettingKey=?',
        'sss',
        ['"USD"', $packageId, 'fixture.currency']
    );
    red_addon_public_mutation_execution_test_assert(
        ($invalidSettings['reason'] ?? '') === 'runtime_settings_unavailable'
            && $calls === $callsBeforeRuntimeSettingsFailure
            && count($runtimeSettingsObserved) === $runtimeSettingsBeforeFailure
            && red_addon_public_mutation_execution_test_state(
                $connection,
                $invalidSettingsSubject['subjectRecordId']
            ) === ['present' => false]
            && red_addon_public_mutation_execution_test_scalar(
                $connection,
                'SELECT CONCAT_WS(\':\',
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions
                     WHERE IdempotencyRecordID=' .
                        (int) $invalidSettingsKey['idempotencyRecordId'] . '),
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=' .
                        (int) $invalidSettingsSubject['subjectRecordId'] . ')
                )'
            ) === '0:0',
        'a missing or malformed declared runtime setting fails before package callbacks, rate use, replay evidence, or package writes'
    );
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'UPDATE RED_Addon_Settings SET ValueJSON=?
         WHERE PackageID=? AND SettingKey=?',
        'sss',
        ['"EUR"', $packageId, 'fixture.currency']
    );
    $callsBeforeRuntimeSettingsConflict = $calls;
    $runtimeSettingsBeforeConflict = count($runtimeSettingsObserved);
    $runtimeSettingsConflict = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $subject,
        $csrf['token'],
        $key['key'],
        ['product' => 'percussion-kit', 'quantity' => 2]
    );
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'UPDATE RED_Addon_Settings SET ValueJSON=?
         WHERE PackageID=? AND SettingKey=?',
        'sss',
        ['"USD"', $packageId, 'fixture.currency']
    );
    red_addon_public_mutation_execution_test_assert(
        ($runtimeSettingsConflict['reason'] ?? '') === 'idempotency_conflict'
            && $calls === $callsBeforeRuntimeSettingsConflict
            && count($runtimeSettingsObserved) === $runtimeSettingsBeforeConflict,
        'a changed runtime configuration invalidates prior idempotency command evidence without invoking package code'
    );
    red_addon_public_mutation_execution_test_assert(
        red_addon_public_mutation_execution_test_scalar(
            $connection,
            'SELECT CONCAT_WS(\':\',
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions),
                (SELECT IdempotencyRecordID=' .
                    (int) $key['idempotencyRecordId'] . '
                 FROM RED_Addon_Public_Mutation_Executions LIMIT 1),
                (SELECT Outcome FROM RED_Addon_Public_Mutation_Executions LIMIT 1),
                (SELECT CHAR_LENGTH(CommandSHA256)
                 FROM RED_Addon_Public_Mutation_Executions LIMIT 1),
                (SELECT PreviousStateSHA256<>StateSHA256
                 FROM RED_Addon_Public_Mutation_Executions LIMIT 1),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID=\'' . $packageId . '\'
                   AND EventName=\'addon.public-mutation.completed\'
                   AND ActorAdminRecordID=0
                   AND Result=\'succeeded\'
                   AND DetailCode=\'public_mutation_accepted\')
            )'
        ) === '1:1:accepted:64:1:1',
        'the committed replay ledger and value-free audit store only one key relation, keyed hashes, and a bounded outcome'
    );

    $callsBeforeReplay = $calls;
    $replay = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $subject,
        $csrf['token'],
        $key['key'],
        ['product' => 'percussion-kit', 'quantity' => 2]
    );
    red_addon_public_mutation_execution_test_assert(
        $replay === [
            'completed' => false,
            'replayed' => true,
            'outcome' => 'accepted',
            'route' => $routeId,
            'mutation' => $mutationId,
            'reason' => 'replayed',
        ]
            && $calls === $callsBeforeReplay
            && red_addon_public_mutation_execution_test_scalar(
                $connection,
                'SELECT CONCAT_WS(\':\',
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions),
                    (SELECT RequestCount
                     FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=' . (int) $subject['subjectRecordId'] . '
                     LIMIT 1),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID=\'' . $packageId . '\')
                )'
            ) === '1:1:1',
        'an exact duplicate returns its committed bounded outcome before rate, loader, handler, or audit work repeats'
    );

    $callsBeforeConflict = $calls;
    $conflict = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $subject,
        $csrf['token'],
        $key['key'],
        ['product' => 'percussion-kit', 'quantity' => 3]
    );
    red_addon_public_mutation_execution_test_assert(
        empty($conflict['completed'])
            && empty($conflict['replayed'])
            && $conflict['reason'] === 'idempotency_conflict'
            && $calls === $callsBeforeConflict
            && red_addon_public_mutation_execution_test_state(
                $connection,
                $subject['subjectRecordId']
            )['quantity'] === 2,
        'one consumed key cannot be rebound to a different typed command'
    );

    $callsBeforeInvalid = $calls;
    $invalidCommand = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $subject,
        $csrf['token'],
        $key['key'],
        ['product' => 'percussion-kit', 'quantity' => '2']
    );
    red_addon_public_mutation_execution_test_assert(
        $invalidCommand['reason'] === 'command_invalid'
            && $calls === $callsBeforeInvalid,
        'noncanonical typed input fails before runtime package execution'
    );

    [$invalidSubject, $invalidCsrf, $invalidKey] =
        red_addon_public_mutation_execution_test_evidence(
            $connection,
            $plan,
            $subjectIds
        );
    $callsBeforeCsrf = $calls;
    $csrfFailure = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $invalidSubject,
        str_repeat('0', 64),
        $invalidKey['key'],
        ['product' => 'drum-pad', 'quantity' => 1]
    );
    red_addon_public_mutation_execution_test_assert(
        $csrfFailure['reason'] === 'csrf_invalid'
            && $calls === $callsBeforeCsrf
            && red_addon_public_mutation_execution_test_scalar(
                $connection,
                'SELECT CONCAT_WS(\':\',
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions
                     WHERE IdempotencyRecordID=' .
                        (int) $invalidKey['idempotencyRecordId'] . '),
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=' .
                        (int) $invalidSubject['subjectRecordId'] . ')
                )'
            ) === '0:0',
        'invalid CSRF evidence refuses before a rate claim, reservation, or package callback'
    );

    foreach ([
        ['output', 'handler_failed'],
        ['throw', 'handler_failed'],
        ['bad-result', 'handler_failed'],
        ['wrong-state', 'postcondition_failed'],
        ['accepted-unchanged', 'execution_result_invalid'],
        ['unchanged-changed', 'execution_result_invalid'],
        ['rollback', 'handler_failed'],
    ] as [$mode, $expectedReason]) {
        [$failureSubject, $failureCsrf, $failureKey] =
            red_addon_public_mutation_execution_test_evidence(
                $connection,
                $plan,
                $subjectIds
            );
        $handlerMode = $mode;
        $failure = red_addon_public_mutation_execute(
            $connection,
            $manifest,
            $routeId,
            $mutationId,
            $failureSubject,
            $failureCsrf['token'],
            $failureKey['key'],
            ['product' => 'fixture-' . str_replace('-', '', $mode), 'quantity' => 1]
        );
        red_addon_public_mutation_execution_test_assert(
            empty($failure['completed'])
                && empty($failure['replayed'])
                && $failure['reason'] === $expectedReason
                && red_addon_public_mutation_execution_test_state(
                    $connection,
                    $failureSubject['subjectRecordId']
                ) === ['present' => false]
                && red_addon_public_mutation_execution_test_scalar(
                    $connection,
                    'SELECT CONCAT_WS(\':\',
                        (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions
                         WHERE IdempotencyRecordID=' .
                            (int) $failureKey['idempotencyRecordId'] . '),
                        (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits
                         WHERE SubjectRecordID=' .
                            (int) $failureSubject['subjectRecordId'] . '),
                        (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                         WHERE PackageID=\'' . $packageId . '\'
                           AND DetailCode=\'public_mutation_accepted\')
                    )'
                ) === '0:0:1',
            'contained handler failures and invalid postconditions roll back package, rate, replay, and audit state: ' . $mode
        );
    }
    $handlerMode = 'accepted';

    [$loaderSubject, $loaderCsrf, $loaderKey] =
        red_addon_public_mutation_execution_test_evidence(
            $connection,
            $plan,
            $subjectIds
        );
    $loaderMode = 'output';
    $callsBeforeLoaderFailure = $calls;
    $loaderFailure = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $loaderSubject,
        $loaderCsrf['token'],
        $loaderKey['key'],
        ['product' => 'loader-fixture', 'quantity' => 1]
    );
    $loaderMode = 'normal';
    red_addon_public_mutation_execution_test_assert(
        $loaderFailure['reason'] === 'state_loader_failed'
            && $calls['handler'] === $callsBeforeLoaderFailure['handler']
            && red_addon_public_mutation_execution_test_state(
                $connection,
                $loaderSubject['subjectRecordId']
            ) === ['present' => false]
            && red_addon_public_mutation_execution_test_scalar(
                $connection,
                'SELECT CONCAT_WS(\':\',
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions
                     WHERE IdempotencyRecordID=' .
                        (int) $loaderKey['idempotencyRecordId'] . '),
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=' .
                        (int) $loaderSubject['subjectRecordId'] . ')
                )'
            ) === '0:0',
        'state-loader output is contained and rolls back before a handler can run'
    );

    [$auditSubject, $auditCsrf, $auditKey] =
        red_addon_public_mutation_execution_test_evidence(
            $connection,
            $plan,
            $subjectIds
        );
    $handlerMode = 'unchanged';
    if (!mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Activity_Log ADD CONSTRAINT `'
            . $auditConstraint . '` CHECK '
            . "(`DetailCode` <> 'public_mutation_unchanged')"
    )) {
        throw new RuntimeException('Could not add disposable audit-failure constraint.');
    }
    $auditFailure = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $auditSubject,
        $auditCsrf['token'],
        $auditKey['key'],
        ['product' => 'audit-fixture', 'quantity' => 1]
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Activity_Log DROP CHECK `'
            . $auditConstraint . '`'
    );
    $handlerMode = 'accepted';
    red_addon_public_mutation_execution_test_assert(
        $auditFailure['reason'] === 'audit_failed'
            && red_addon_public_mutation_execution_test_state(
                $connection,
                $auditSubject['subjectRecordId']
            ) === ['present' => false]
            && red_addon_public_mutation_execution_test_scalar(
                $connection,
                'SELECT CONCAT_WS(\':\',
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions
                     WHERE IdempotencyRecordID=' .
                        (int) $auditKey['idempotencyRecordId'] . '),
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=' .
                        (int) $auditSubject['subjectRecordId'] . '),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID=\'' . $packageId . '\'
                       AND DetailCode=\'public_mutation_accepted\')
                )'
            ) === '0:0:1',
        'an audit write failure rolls the package mutation, rate claim, and replay reservation back together'
    );

    [$expiredSubject, $expiredCsrf, $expiredKey] =
        red_addon_public_mutation_execution_test_evidence(
            $connection,
            $plan,
            $subjectIds
        );
    $expiredScope = red_addon_public_mutation_rate_limit_scope_sha256(
        $connection,
        $plan
    );
    if (!red_addon_valid_sha256($expiredScope)) {
        throw new RuntimeException('Could not derive expired rate fixture scope.');
    }
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'INSERT INTO RED_Addon_Public_Mutation_Rate_Limits (
            SubjectRecordID, ScopeSHA256, WindowStartedAt,
            RequestCount, ExpiresAt
         ) VALUES (
            ?, ?, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE),
            1, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)
         )',
        'is',
        [$expiredSubject['subjectRecordId'], $expiredScope]
    );
    $expiredCleanup = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $expiredSubject,
        $expiredCsrf['token'],
        $expiredKey['key'],
        ['product' => 'expired-rate-fixture', 'quantity' => 1]
    );
    red_addon_public_mutation_execution_test_assert(
        !empty($expiredCleanup['completed'])
            && ($expiredCleanup['outcome'] ?? '') === 'accepted'
            && red_addon_public_mutation_execution_test_scalar(
                $connection,
                'SELECT CONCAT_WS(\':\',
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=' .
                        (int) $expiredSubject['subjectRecordId'] . '),
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=' .
                        (int) $expiredSubject['subjectRecordId'] . '
                       AND ExpiresAt > UTC_TIMESTAMP())
                )'
            ) === '1:1',
        'the atomic runner purges expired opaque rate evidence inside its transaction before claiming the current window'
    );

    [$rateSubject, $rateCsrf] = array_slice(
        red_addon_public_mutation_execution_test_evidence(
            $connection,
            $plan,
            $subjectIds
        ),
        0,
        2
    );
    $handlerMode = 'unchanged';
    $rateResults = [];
    for ($attempt = 0; $attempt < 13; $attempt++) {
        $rateKey = red_addon_public_mutation_idempotency_issue(
            $connection,
            $rateSubject,
            $plan
        );
        if (empty($rateKey['valid'])) {
            throw new RuntimeException('Could not issue rate-limit fixture key.');
        }
        $rateResults[] = red_addon_public_mutation_execute(
            $connection,
            $manifest,
            $routeId,
            $mutationId,
            $rateSubject,
            $rateCsrf['token'],
            $rateKey['key'],
            ['product' => 'rate-fixture', 'quantity' => 1]
        );
    }
    $handlerMode = 'accepted';
    red_addon_public_mutation_execution_test_assert(
        count(array_filter(
            array_slice($rateResults, 0, 12),
            static function ($item) {
                return !empty($item['completed'])
                    && ($item['outcome'] ?? '') === 'unchanged';
            }
        )) === 12
            && ($rateResults[12]['reason'] ?? '') === 'rate_limited'
            && red_addon_public_mutation_execution_test_scalar(
                $connection,
                'SELECT CONCAT_WS(\':\',
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions e
                     INNER JOIN RED_Addon_Public_Mutation_Idempotency_Keys k
                       ON k.RecordID=e.IdempotencyRecordID
                     WHERE k.SubjectRecordID=' .
                        (int) $rateSubject['subjectRecordId'] . '),
                    (SELECT RequestCount FROM RED_Addon_Public_Mutation_Rate_Limits
                     WHERE SubjectRecordID=' .
                        (int) $rateSubject['subjectRecordId'] . ' LIMIT 1),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID=\'' . $packageId . '\'
                       AND DetailCode=\'public_mutation_unchanged\')
                )'
            ) === '12:12:12',
        'the atomic runner applies the fixed twelve-request budget before a thirteenth key can reserve or invoke a package mutation'
    );

    $noRuntimeManifest = $manifest;
    unset($noRuntimeManifest['publicMutationContracts'][0]['runtimeSettings']);
    $noRuntimeManifest['permissions'] = [];
    $noRuntimeManifest['settings'] = [];
    $noRuntimePlan = red_addon_public_mutation_declaration_preflight(
        $noRuntimeManifest,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_execution_test_assert(
        red_addon_public_mutation_declaration_preflight_is_valid(
            $noRuntimePlan
        ),
        'a public mutation remains valid when it declares no runtime settings'
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_public_mutation_execution_test_runtime(
            $noRuntimeManifest,
            $routeId,
            $mutationId,
            $fixtureTable,
            $routeHandler,
            $handler,
            $stateLoader
        );
    [$noRuntimeSubject, $noRuntimeCsrf, $noRuntimeKey] =
        red_addon_public_mutation_execution_test_evidence(
            $connection,
            $noRuntimePlan,
            $subjectIds
        );
    red_addon_public_mutation_execution_test_execute(
        $connection,
        'DELETE FROM RED_Addon_Settings WHERE PackageID=?',
        's',
        [$packageId]
    );
    $callsBeforeNoRuntime = $calls;
    $runtimeSettingsBeforeNoRuntime = count($runtimeSettingsObserved);
    $noRuntime = red_addon_public_mutation_execute(
        $connection,
        $noRuntimeManifest,
        $routeId,
        $mutationId,
        $noRuntimeSubject,
        $noRuntimeCsrf['token'],
        $noRuntimeKey['key'],
        ['product' => 'no-runtime-settings', 'quantity' => 1]
    );
    $noRuntimeObserved = array_slice(
        $runtimeSettingsObserved,
        $runtimeSettingsBeforeNoRuntime
    );
    red_addon_public_mutation_execution_test_assert(
        $noRuntime === [
            'completed' => true,
            'replayed' => false,
            'outcome' => 'accepted',
            'route' => $routeId,
            'mutation' => $mutationId,
            'reason' => 'completed',
        ]
            && $calls === [
                'route' => $callsBeforeNoRuntime['route'],
                'handler' => $callsBeforeNoRuntime['handler'] + 1,
                'loader' => $callsBeforeNoRuntime['loader'] + 2,
            ]
            && count($noRuntimeObserved) === 3
            && count(array_filter(
                $noRuntimeObserved,
                static function (array $observed): bool {
                    return $observed['declared'] === false
                        && $observed['values'] === []
                        && red_addon_valid_sha256($observed['stateSha256']);
                }
            )) === 3
            && count(array_filter(
                $noRuntimeObserved,
                static function (array $observed): bool {
                    return ($observed['role'] ?? '') === 'loader'
                        && ($observed['commandSettingsSha256'] ?? null) === '';
                }
            )) === 2,
        'an undeclared mutation gets an empty typed settings object and remains executable after all package settings are absent'
    );

    red_addon_public_mutation_execution_test_cleanup(
        $connection,
        $packageId,
        $fixtureTable,
        $auditConstraint,
        $subjectIds
    );
    red_addon_public_mutation_execution_test_assert(
        red_addon_public_mutation_execution_test_scalar(
            $connection,
            'SELECT CONCAT_WS(\':\',
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Subjects),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_CSRF_Tokens),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Rate_Limits),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions),
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID=\'' . $packageId . '\'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID=\'' . $packageId . '\'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME=\'' . $fixtureTable . '\'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA=DATABASE()
                   AND TABLE_NAME=\'RED_Addon_Activity_Log\'
                   AND CONSTRAINT_NAME=\'' . $auditConstraint . '\')
            )'
        ) === '0:0:0:0:0:0:0:0:0',
        'the disposable public-mutation runner fixture leaves no core evidence, package state, table, constraint, runtime context, or client artifact'
    );
    echo "Public-mutation execution self-test passed ($assertions assertions).\n";
} catch (Throwable $throwable) {
    red_addon_public_mutation_execution_test_cleanup(
        $connection,
        $packageId,
        $fixtureTable,
        $auditConstraint,
        $subjectIds
    );
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
