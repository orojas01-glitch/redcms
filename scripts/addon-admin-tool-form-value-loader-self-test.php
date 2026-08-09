<?php
/**
 * Disposable checks for permission-scoped administrator form value loading.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_admin_tool_form_ui_helpers.php';
require_once $projectRoot .
    '/includes/addon_admin_tool_form_initial_value_helpers.php';
require_once $projectRoot .
    '/includes/addon_admin_tool_form_create_submission_helpers.php';
require_once $projectRoot .
    '/includes/addon_admin_tool_form_create_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_admin_tool_form_value|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on administrator form value loader refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000964;
$packageId = 'redcms.tool-form-value-fixture';
$toolId = $packageId . '/products';
$formId = $packageId . '/product-editor';
$permission = 'fixture.products.manage';
$calls = ['tool' => 0, 'loader' => 0];
$initialCalls = 0;
$initialMode = 'normal';
$creatorCalls = 0;
$creatorMode = 'normal';
$creatorRecordId = 43;
$loaderMode = 'normal';
$runtimeCurrency = 'USD';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_admin_form_value_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_form_value_test_execute(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare form value fixture SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Form value fixture SQL failed: ' . $error);
    }
    mysqli_stmt_close($statement);
}

function red_addon_admin_form_value_test_scalar(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare form value scalar SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Form value scalar SQL failed: ' . $error);
    }
    $result = mysqli_stmt_get_result($statement);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    mysqli_stmt_close($statement);
    return $row[0] ?? null;
}

function red_addon_admin_form_value_test_manifest(
    $packageId,
    $toolId,
    $formId,
    $permission,
    $description = 'Review one product and its bounded options.'
) {
    return [
        'id' => $packageId,
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [$toolId],
            'adapters' => [],
        ],
        'permissions' => [$permission, 'fixture.settings.manage'],
        'settings' => [[
            'key' => 'fixture.currency',
            'label' => 'Catalog currency',
            'type' => 'text',
            'secret' => false,
            'permission' => 'fixture.settings.manage',
            'default' => null,
        ]],
        'adminToolContracts' => [[
            'tool' => $toolId,
            'label' => 'Products',
            'description' => 'Review product state.',
            'icon' => 'products',
            'permission' => $permission,
            'mode' => 'read-only',
        ]],
        'adminToolFormContracts' => [[
            'tool' => $toolId,
            'form' => $formId,
            'label' => 'Product editor',
            'description' => $description,
            'permission' => $permission,
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/json',
            'maxBodyBytes' => 1024,
            'runtimeSettings' => ['fixture.currency'],
            'create' => [
                'label' => 'Add product',
                'description' => 'Create one bounded product.',
            ],
            'fields' => [
                [
                    'key' => 'id',
                    'label' => 'Identifier',
                    'type' => 'text',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 64,
                ],
                [
                    'key' => 'type',
                    'label' => 'Product type',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'simple', 'label' => 'Simple item'],
                        ['value' => 'variable', 'label' => 'Variable item'],
                    ],
                ],
                [
                    'key' => 'active',
                    'label' => 'Active',
                    'type' => 'boolean',
                    'required' => true,
                ],
                [
                    'key' => 'description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'required' => false,
                    'minLength' => 0,
                    'maxLength' => 300,
                ],
                [
                    'key' => 'options',
                    'label' => 'Option groups',
                    'type' => 'collection',
                    'required' => false,
                    'itemLabel' => 'Option group',
                    'minItems' => 0,
                    'maxItems' => 3,
                    'fields' => [
                        [
                            'key' => 'key',
                            'label' => 'Option key',
                            'type' => 'text',
                            'required' => true,
                            'minLength' => 1,
                            'maxLength' => 32,
                        ],
                        [
                            'key' => 'values',
                            'label' => 'Option values',
                            'type' => 'collection',
                            'required' => true,
                            'itemLabel' => 'Option value',
                            'minItems' => 1,
                            'maxItems' => 16,
                            'fields' => [[
                                'key' => 'label',
                                'label' => 'Value label',
                                'type' => 'text',
                                'required' => true,
                                'minLength' => 1,
                                'maxLength' => 80,
                            ]],
                        ],
                    ],
                ],
            ],
        ]],
        'routes' => [],
    ];
}

function red_addon_admin_form_value_test_values($recordId)
{
    global $connection;
    $statement = mysqli_prepare(
        $connection,
        'SELECT ProductKey, ProductType, IsActive, Description
         FROM RED_Addon_Admin_Form_Value_Fixture WHERE RecordID=? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $recordId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    mysqli_stmt_close($statement);
    if (!is_array($row)) {
        throw new RuntimeException('Product record is unavailable.');
    }
    $values = [
        'id' => $row['ProductKey'],
        'type' => $row['ProductType'],
        'active' => ((int) $row['IsActive']) === 1,
        'description' => $row['Description'],
        'options' => [],
    ];
    if ($row['ProductType'] === 'variable') {
        $values['options'] = [[
            'key' => 'size',
            'values' => [
                ['label' => 'Small'],
                ['label' => 'Large & Tall'],
            ],
        ]];
    }
    return $values;
}

function red_addon_admin_form_value_test_context(
    array $manifest,
    $packageId,
    $toolId,
    $formId
) {
    global $calls, $loaderMode, $runtimeCurrency, $initialCalls, $initialMode,
        $creatorCalls, $creatorMode, $creatorRecordId;
    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerAdminTool(
        $toolId,
        static function () use (&$calls) {
            $calls['tool']++;
            throw new RuntimeException('Tool callback must remain uninvoked.');
        }
    );
    $registry->registerAdminToolFormValueLoader(
        $formId,
        static function ($connection, $request) use (
            &$calls,
            &$loaderMode,
            &$runtimeCurrency
        ) {
            $calls['loader']++;
            if (!$request instanceof RED_Addon_Admin_Tool_Form_Value_Request
                || !$connection
            ) {
                throw new RuntimeException('Loader arguments are invalid.');
            }
            $runtimeSettings = $request->runtimeSettings();
            if (!$runtimeSettings->has('fixture.currency')
                || $runtimeSettings->value('fixture.currency')
                    !== $runtimeCurrency
                || $runtimeSettings->values() !== [
                    'fixture.currency' => $runtimeCurrency,
                ]
                || !red_addon_valid_sha256(
                    $runtimeSettings->stateSha256()
                )
            ) {
                throw new RuntimeException(
                    'Loader runtime settings are invalid.'
                );
            }
            if ($loaderMode === 'output') {
                echo 'unexpected output';
            } elseif ($loaderMode === 'buffer') {
                ob_start();
            } elseif ($loaderMode === 'throw') {
                throw new RuntimeException('Fixture loader failure.');
            }
            $values = red_addon_admin_form_value_test_values(
                $request->targetRecordId()
            );
            if ($loaderMode === 'invalid') {
                $values['type'] = 'forged';
            } elseif ($loaderMode === 'extra') {
                $values['undeclared'] = 'blocked';
            } elseif ($loaderMode === 'oversized') {
                $values['description'] = str_repeat('x', 301);
            }
            return RED_Addon_Admin_Tool_Form_Values::current($values);
        }
    );
    $registry->registerAdminToolFormInitialValueLoader(
        $formId,
        static function ($connection, $request) use (
            &$runtimeCurrency,
            &$initialCalls,
            &$initialMode
        ) {
            $initialCalls++;
            if (!$connection
                || !$request instanceof
                    RED_Addon_Admin_Tool_Form_Initial_Value_Request
                || $request->runtimeSettings()->value('fixture.currency')
                    !== $runtimeCurrency
            ) {
                throw new RuntimeException(
                    'Initial-value loader arguments are invalid.'
                );
            }
            if ($initialMode === 'output') {
                echo 'unexpected initial output';
            } elseif ($initialMode === 'throw') {
                throw new RuntimeException('Initial-value fixture failure.');
            }
            $values = [
                'id' => '',
                'type' => 'simple',
                'active' => false,
                'description' => null,
                'options' => [],
            ];
            if ($initialMode === 'invalid') {
                $values['type'] = 'forged';
            }
            return RED_Addon_Admin_Tool_Form_Initial_Values::draft($values);
        }
    );
    $registry->registerAdminToolFormCreator(
        $formId,
        static function ($connection, $request) use (
            &$runtimeCurrency,
            &$creatorCalls,
            &$creatorMode,
            &$creatorRecordId
        ) {
            $creatorCalls++;
            if (!$connection
                || !$request instanceof
                    RED_Addon_Admin_Tool_Form_Create_Request
                || $request->runtimeSettings()->value('fixture.currency')
                    !== $runtimeCurrency
            ) {
                throw new RuntimeException('Creator arguments are invalid.');
            }
            if ($creatorMode === 'output') {
                echo 'unexpected creator output';
            } elseif ($creatorMode === 'throw') {
                throw new RuntimeException('Fixture creator failure.');
            } elseif ($creatorMode === 'invalid-result') {
                return true;
            }
            $values = $request->values();
            $description = $values['description'];
            if ($creatorMode === 'wrong-postcondition') {
                $description = 'wrong saved value';
            }
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Addon_Admin_Form_Value_Fixture
                    (RecordID, ProductKey, ProductType, IsActive, Description)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if (!$statement) {
                throw new RuntimeException('Creator insert prepare failed.');
            }
            $active = $values['active'] ? 1 : 0;
            mysqli_stmt_bind_param(
                $statement,
                'issis',
                $creatorRecordId,
                $values['id'],
                $values['type'],
                $active,
                $description
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                throw new RuntimeException('Creator insert failed.');
            }
            mysqli_stmt_close($statement);
            if ($creatorMode === 'partial') {
                return true;
            }
            return RED_Addon_Admin_Tool_Form_Created_Record::created(
                $creatorRecordId
            );
        },
        ['RED_Addon_Admin_Form_Value_Fixture']
    );
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );
}

function red_addon_admin_form_value_test_cleanup($connection, $actorId)
{
    try {
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Activity_Log
             WHERE PackageID='redcms.tool-form-value-fixture'"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Settings
             WHERE PackageID='redcms.tool-form-value-fixture'"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Installations
             WHERE PackageID='redcms.tool-form-value-fixture'"
        );
        red_addon_admin_form_value_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_form_value_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_form_value_test_execute(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=?',
            'i',
            [$actorId]
        );
        mysqli_query(
            $connection,
            'DROP TABLE IF EXISTS RED_Addon_Admin_Form_Value_Fixture'
        );
    } catch (Throwable $throwable) {
        error_log('Administrator form value fixture cleanup failed.');
    }
}

try {
    red_addon_admin_form_value_test_cleanup($connection, $actorId);
    red_addon_admin_form_value_test_execute(
        $connection,
        'CREATE TABLE RED_Addon_Admin_Form_Value_Fixture (
            RecordID INT NOT NULL PRIMARY KEY,
            ProductKey VARCHAR(64) NOT NULL,
            ProductType VARCHAR(16) NOT NULL,
            IsActive TINYINT(1) NOT NULL,
            Description VARCHAR(300) NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        "INSERT INTO RED_Addon_Admin_Form_Value_Fixture
            (RecordID, ProductKey, ProductType, IsActive, Description)
         VALUES
            (41, 'shirt-large', 'variable', 1, 'Current <shirt> & stock.'),
            (42, 'shirt-large', 'variable', 1, 'Current <shirt> & stock.')"
    );
    $password = password_hash('AddonFormValues-2026!', PASSWORD_DEFAULT);
    red_addon_admin_form_value_test_execute(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_form_values', ?, 'Admin',
            'FormValues', 'webmaster', '', '', 'form-values@example.test',
            'N', 'to', 'N', 'to')",
        'is',
        [$actorId, $password]
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        "INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (
            'redcms.tool-form-value-fixture', '1.0.0', 'service',
            REPEAT('a', 64), REPEAT('b', 64), 'enabled', ?, ?
         )",
        'ii',
        [$actorId, $actorId]
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        "INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES (
            'redcms.tool-form-value-fixture', 'fixture.currency', 'text',
            '\"USD\"', NULL, ?
         )",
        'i',
        [$actorId]
    );

    $manifest = red_addon_admin_form_value_test_manifest(
        $packageId,
        $toolId,
        $formId,
        $permission
    );
    $incompleteRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $manifest
    );
    $incompleteRegistry->registerAdminTool($toolId, static function () {
        return null;
    });
    $missingLoaderRefused = false;
    try {
        $incompleteRegistry->assertComplete();
    } catch (LogicException $exception) {
        $missingLoaderRefused = str_contains(
            $exception->getMessage(),
            'adminToolFormValueLoaders: ' . $formId
        );
    }
    red_addon_admin_form_value_test_assert(
        $missingLoaderRefused,
        'schema-bearing form cannot complete runtime registration without its exact loader'
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_value_test_context(
            $manifest,
            $packageId,
            $toolId,
            $formId
        );
    red_addon_admin_form_value_test_assert(
        red_addon_runtime_owner('adminToolFormValueLoaders', $formId)
            === $packageId
            && red_addon_runtime_owner(
                'adminToolFormInitialValueLoaders',
                $formId
            ) === $packageId,
        'schema-bearing create form has exact current and initial value-loader owners'
    );

    $denied = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        41,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        empty($denied['authorized'])
            && empty($denied['invoked'])
            && empty($denied['loaded'])
            && $denied['reason'] === 'permission_denied'
            && $calls === ['tool' => 0, 'loader' => 0],
        'missing exact permission refuses before package loader invocation'
    );
    $deniedInitial = red_addon_admin_tool_form_load_initial_values(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        empty($deniedInitial['authorized'])
            && empty($deniedInitial['invoked'])
            && empty($deniedInitial['loaded'])
            && $deniedInitial['reason'] === 'permission_denied'
            && $initialCalls === 0,
        'missing exact permission refuses before initial-value loader invocation'
    );

    red_addon_admin_form_value_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Roles
            (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES (?, \'owner\', ?)',
        'ii',
        [$actorId, $actorId]
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
            (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    $fixtureBefore = (string) red_addon_admin_form_value_test_scalar(
        $connection,
        'SELECT GROUP_CONCAT(CONCAT_WS(\':\', RecordID, ProductKey,
            ProductType, IsActive, Description) ORDER BY RecordID SEPARATOR \'|\')
         FROM RED_Addon_Admin_Form_Value_Fixture'
    );

    $initial = red_addon_admin_tool_form_load_initial_values(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        $initial['authorized'] === true
            && $initial['invoked'] === true
            && $initial['loaded'] === true
            && $initial['package'] === $packageId
            && $initial['actorRecordId'] === $actorId
            && $initial['permission'] === $permission
            && $initial['values'] === [
                'id' => '',
                'type' => 'simple',
                'active' => false,
                'description' => null,
                'options' => [],
            ]
            && red_addon_valid_sha256($initial['contractSha256'])
            && red_addon_valid_sha256($initial['planSha256'])
            && red_addon_valid_sha256(
                $initial['runtimeSettingsSha256']
            )
            && red_addon_valid_sha256($initial['stateSha256'])
            && $initial['reason'] === 'loaded'
            && $initialCalls === 1,
        'exact grant loads one complete typed draft without a target record or write'
    );

    foreach (['invalid', 'output', 'throw'] as $failureMode) {
        $initialMode = $failureMode;
        $bufferBefore = ob_get_level();
        $failure = red_addon_admin_tool_form_load_initial_values(
            $connection,
            $toolId,
            $formId,
            $actorId
        );
        red_addon_admin_form_value_test_assert(
            $failure['authorized'] === true
                && $failure['invoked'] === true
                && empty($failure['loaded'])
                && $failure['values'] === []
                && $failure['stateSha256'] === ''
                && in_array(
                    $failure['reason'],
                    [
                        'invalid_initial_values',
                        'initial_loader_output',
                        'initial_loader_failed',
                    ],
                    true
                )
                && ob_get_level() === $bufferBefore,
            'invalid draft values, output, and exceptions remain contained'
        );
    }
    $initialMode = 'normal';

    $createValues = [
        'id' => 'new-shirt',
        'type' => 'simple',
        'active' => false,
        'description' => null,
        'options' => [],
    ];
    $createBody = json_encode(
        [
            'tool' => $toolId,
            'form' => $formId,
            'initialStateSha256' => $initial['stateSha256'],
            'values' => $createValues,
        ],
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
    $preparedCreate =
        red_addon_admin_tool_form_create_submission_prepare(
            $connection,
            $createBody,
            $actorId
        );
    red_addon_admin_form_value_test_assert(
        $preparedCreate['authorized'] === true
            && $preparedCreate['invoked'] === true
            && $preparedCreate['prepared'] === true
            && $preparedCreate['package'] === $packageId
            && $preparedCreate['actorRecordId'] === $actorId
            && $preparedCreate['permission'] === $permission
            && $preparedCreate['values'] === $createValues
            && $preparedCreate['initialStateSha256']
                === $initial['stateSha256']
            && $preparedCreate['runtimeSettingsSha256']
                === $initial['runtimeSettingsSha256']
            && red_addon_valid_sha256(
                $preparedCreate['submittedValuesSha256']
            )
            && red_addon_valid_sha256($preparedCreate['planSha256'])
            && $preparedCreate['reason'] === 'prepared',
        'canonical target-free creation values prepare opaque evidence without invoking the creator'
    );

    $staleCreate = json_decode(
        $createBody,
        true,
        12,
        JSON_THROW_ON_ERROR
    );
    $staleCreate['initialStateSha256'] = hash('sha256', 'stale-initial');
    $staleCreate = json_encode(
        $staleCreate,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
    $staleCreateResult =
        red_addon_admin_tool_form_create_submission_prepare(
            $connection,
            $staleCreate,
            $actorId
        );
    red_addon_admin_form_value_test_assert(
        $staleCreateResult['authorized'] === true
            && $staleCreateResult['invoked'] === true
            && empty($staleCreateResult['prepared'])
            && $staleCreateResult['reason'] === 'state_conflict'
            && $staleCreateResult['values'] === [],
        'stale draft or configuration evidence refuses creation preparation'
    );

    $invalidCreate = json_decode(
        $createBody,
        true,
        12,
        JSON_THROW_ON_ERROR
    );
    $invalidCreate['values']['id'] = '';
    $invalidCreate = json_encode(
        $invalidCreate,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
    $invalidCreateResult =
        red_addon_admin_tool_form_create_submission_prepare(
            $connection,
            $invalidCreate,
            $actorId
        );
    red_addon_admin_form_value_test_assert(
        $invalidCreateResult['authorized'] === true
            && $invalidCreateResult['invoked'] === true
            && empty($invalidCreateResult['prepared'])
            && $invalidCreateResult['reason'] === 'invalid_values'
            && $invalidCreateResult['values'] === [],
        'creation preparation restores strict required-field validation'
    );

    $loaded = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        41,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        $loaded['authorized'] === true
            && $loaded['invoked'] === true
            && $loaded['loaded'] === true
            && $loaded['package'] === $packageId
            && $loaded['targetRecordId'] === 41
            && $loaded['actorRecordId'] === $actorId
            && $loaded['permission'] === $permission
            && $loaded['values']['type'] === 'variable'
            && $loaded['values']['active'] === true
            && $loaded['values']['options'][0]['values'][1]['label']
                === 'Large & Tall'
            && red_addon_valid_sha256($loaded['contractSha256'])
            && red_addon_valid_sha256($loaded['planSha256'])
            && red_addon_valid_sha256(
                $loaded['runtimeSettingsSha256']
            )
            && red_addon_valid_sha256($loaded['stateSha256'])
            && $loaded['reason'] === 'loaded'
            && $calls === ['tool' => 0, 'loader' => 1],
        'exact grant loads one complete nested typed value graph without invoking the tool'
    );

    $createPreflight = red_addon_admin_tool_form_create_preflight(
        $connection,
        $createBody,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        $createPreflight['authorized'] === true
            && $createPreflight['prepared'] === true
            && empty($createPreflight['creatorInvoked'])
            && empty($createPreflight['executed'])
            && $createPreflight['packageVersion'] === '1.0.0'
            && $createPreflight['targetRecordId'] === 0
            && red_addon_valid_sha256($createPreflight['planSha256'])
            && $createPreflight['reason'] === 'preflight_ready'
            && $creatorCalls === 0,
        'atomic creation preflight binds package tables and version without invoking the creator or allocating an id'
    );

    mysqli_begin_transaction($connection);
    $nestedCreate = red_addon_admin_tool_form_create_preflight(
        $connection,
        $createBody,
        $actorId
    );
    mysqli_rollback($connection);
    red_addon_admin_form_value_test_assert(
        empty($nestedCreate['prepared'])
            && empty($nestedCreate['creatorInvoked'])
            && $nestedCreate['reason'] === 'transaction_already_active'
            && $creatorCalls === 0,
        'caller-owned transactions are refused before creation preparation or provider invocation'
    );

    $wrongPlan = red_addon_admin_tool_form_create(
        $connection,
        $createBody,
        $actorId,
        hash('sha256', 'wrong-create-plan')
    );
    red_addon_admin_form_value_test_assert(
        empty($wrongPlan['prepared'])
            && empty($wrongPlan['creatorInvoked'])
            && empty($wrongPlan['executed'])
            && $wrongPlan['reason'] === 'plan_mismatch'
            && $creatorCalls === 0,
        'caller plan mismatch refuses before locks, transaction, or creator invocation'
    );

    $created = red_addon_admin_tool_form_create(
        $connection,
        $createBody,
        $actorId,
        $createPreflight['planSha256']
    );
    red_addon_admin_form_value_test_assert(
        $created['authorized'] === true
            && $created['prepared'] === true
            && $created['creatorInvoked'] === true
            && $created['executed'] === true
            && $created['targetRecordId'] === 43
            && red_addon_valid_sha256($created['stateSha256'])
            && $created['reason'] === 'executed'
            && $creatorCalls === 1
            && (int) red_addon_admin_form_value_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Admin_Form_Value_Fixture
                 WHERE RecordID=43 AND ProductKey='new-shirt'
                   AND ProductType='simple' AND IsActive=0
                   AND Description IS NULL"
            ) === 1
            && (int) red_addon_admin_form_value_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.tool-form-value-fixture'
                   AND EventName='addon.form.created'
                   AND Result='succeeded'"
            ) === 1,
        'exact plan atomically creates one record, reloads exact values, and commits one value-free audit fact'
    );

    foreach (
        [
            'partial' => 'creator_failed',
            'output' => 'creator_failed',
            'invalid-result' => 'creator_failed',
            'wrong-postcondition' => 'postcondition_failed',
        ] as $failureMode => $failureReason
    ) {
        $creatorMode = $failureMode;
        $creatorRecordId = 44;
        $failureValues = $createValues;
        $failureValues['id'] = 'rollback-' . $failureMode;
        $failureBody = json_encode(
            [
                'tool' => $toolId,
                'form' => $formId,
                'initialStateSha256' => $initial['stateSha256'],
                'values' => $failureValues,
            ],
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
        );
        $failurePreflight = red_addon_admin_tool_form_create_preflight(
            $connection,
            $failureBody,
            $actorId
        );
        $failure = red_addon_admin_tool_form_create(
            $connection,
            $failureBody,
            $actorId,
            $failurePreflight['planSha256']
        );
        red_addon_admin_form_value_test_assert(
            empty($failure['prepared'])
                && $failure['creatorInvoked'] === true
                && empty($failure['executed'])
                && $failure['targetRecordId'] === 0
                && $failure['reason'] === $failureReason
                && (int) red_addon_admin_form_value_test_scalar(
                    $connection,
                    'SELECT COUNT(*)
                     FROM RED_Addon_Admin_Form_Value_Fixture
                     WHERE RecordID=44'
                ) === 0
                && (int) red_addon_admin_form_value_test_scalar(
                    $connection,
                    "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.tool-form-value-fixture'
                       AND EventName='addon.form.created'"
                ) === 1,
            'creator failure or mismatched reload rolls back package and audit state'
        );
    }
    $creatorMode = 'normal';
    $creatorRecordId = 44;
    $auditFailureValues = $createValues;
    $auditFailureValues['id'] = 'rollback-audit';
    $auditFailureBody = json_encode(
        [
            'tool' => $toolId,
            'form' => $formId,
            'initialStateSha256' => $initial['stateSha256'],
            'values' => $auditFailureValues,
        ],
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
    $auditFailurePlan = red_addon_admin_tool_form_create_preflight(
        $connection,
        $auditFailureBody,
        $actorId
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        "DELETE FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.tool-form-value-fixture'
           AND EventName='addon.form.created'"
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        "ALTER TABLE RED_Addon_Activity_Log
         ADD CONSTRAINT `red_addon_form_create_audit_fixture`
         CHECK (EventName <> 'addon.form.created')"
    );
    $auditFailure = red_addon_admin_tool_form_create(
        $connection,
        $auditFailureBody,
        $actorId,
        $auditFailurePlan['planSha256']
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        "ALTER TABLE RED_Addon_Activity_Log
         DROP CHECK `red_addon_form_create_audit_fixture`"
    );
    red_addon_admin_form_value_test_assert(
        empty($auditFailure['prepared'])
            && $auditFailure['creatorInvoked'] === true
            && empty($auditFailure['executed'])
            && $auditFailure['reason'] === 'audit_failed'
            && (int) red_addon_admin_form_value_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_Admin_Form_Value_Fixture
                 WHERE RecordID=44'
            ) === 0
            && (int) red_addon_admin_form_value_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.tool-form-value-fixture'
                   AND EventName='addon.form.created'"
            ) === 0,
        'forced core audit failure rolls the created record back atomically'
    );
    $creatorMode = 'normal';
    $creatorRecordId = 43;
    red_addon_admin_form_value_test_execute(
        $connection,
        'DELETE FROM RED_Addon_Admin_Form_Value_Fixture WHERE RecordID=43'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.tool-form-value-fixture'
           AND EventName='addon.form.created'"
    );

    $runtimeCurrency = 'EUR';
    red_addon_admin_form_value_test_execute(
        $connection,
        "UPDATE RED_Addon_Settings SET ValueJSON='\"EUR\"'
         WHERE PackageID=? AND SettingKey='fixture.currency'",
        's',
        [$packageId]
    );
    $changedSetting = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        41,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        $changedSetting['loaded'] === true
            && $changedSetting['values'] === $loaded['values']
            && $changedSetting['runtimeSettingsSha256']
                !== $loaded['runtimeSettingsSha256']
            && $changedSetting['stateSha256'] !== $loaded['stateSha256'],
        'a typed runtime-setting change reaches the loader and invalidates form state evidence'
    );
    $loaded = $changedSetting;

    $repeat = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        41,
        $actorId
    );
    $otherTarget = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        42,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        $repeat['values'] === $loaded['values']
            && $repeat['stateSha256'] === $loaded['stateSha256']
            && $otherTarget['values'] === $loaded['values']
            && $otherTarget['stateSha256'] !== $loaded['stateSha256'],
        'value evidence is deterministic and binds the numeric target identity'
    );

    red_addon_admin_form_value_test_execute(
        $connection,
        'DELETE FROM RED_Addon_Settings WHERE PackageID=? AND SettingKey=?',
        'ss',
        [$packageId, 'fixture.currency']
    );
    $callsBeforeMissingSetting = $calls['loader'];
    $missingSetting = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        41,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        $missingSetting['authorized'] === true
            && empty($missingSetting['invoked'])
            && empty($missingSetting['loaded'])
            && $missingSetting['reason'] === 'runtime_settings_unavailable'
            && $calls['loader'] === $callsBeforeMissingSetting,
        'an unconfigured declared runtime setting refuses before loader invocation'
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        "INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES (?, 'fixture.currency', 'text', '\"EUR\"', NULL, ?)",
        'si',
        [$packageId, $actorId]
    );

    red_addon_admin_form_value_test_execute(
        $connection,
        "UPDATE RED_Addon_Settings SET ValueJSON='[]'
         WHERE PackageID=? AND SettingKey='fixture.currency'",
        's',
        [$packageId]
    );
    $callsBeforeMalformedSetting = $calls['loader'];
    $malformedSetting = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        41,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        $malformedSetting['authorized'] === true
            && empty($malformedSetting['invoked'])
            && empty($malformedSetting['loaded'])
            && $malformedSetting['reason'] === 'runtime_settings_unavailable'
            && $calls['loader'] === $callsBeforeMalformedSetting,
        'a malformed stored runtime setting refuses before loader invocation'
    );
    red_addon_admin_form_value_test_execute(
        $connection,
        "UPDATE RED_Addon_Settings SET ValueJSON='\"EUR\"'
         WHERE PackageID=? AND SettingKey='fixture.currency'",
        's',
        [$packageId]
    );

    $html = red_addon_admin_tool_form_ui_render(
        $manifest,
        $toolId,
        $formId,
        'fixture-current-product',
        $loaded
    );
    red_addon_admin_form_value_test_assert(
        str_contains($html, 'Current values. Editing and saving are not available.')
            && str_contains($html, 'value="shirt-large"')
            && str_contains($html, 'value="variable" selected')
            && str_contains($html, 'value="1" selected')
            && str_contains($html, 'Current &lt;shirt&gt; &amp; stock.')
            && str_contains($html, 'Option group 1')
            && str_contains($html, 'Option value 2')
            && str_contains($html, 'value="Large &amp; Tall"'),
        'core renderer displays escaped current scalar and nested collection values'
    );
    red_addon_admin_form_value_test_assert(
        !str_contains($html, '<form')
            && !str_contains($html, '<button')
            && !str_contains($html, ' name=')
            && !str_contains($html, ' action=')
            && !str_contains($html, ' method=')
            && substr_count($html, ' disabled') === 7
            && substr_count($html, 'aria-disabled="true"') === 7,
        'loaded preview remains disabled and contains no submission surface'
    );

    $tampered = $loaded;
    $tampered['values']['type'] = 'forged';
    red_addon_admin_form_value_test_assert(
        str_contains(
            red_addon_admin_tool_form_ui_render(
                $manifest,
                $toolId,
                $formId,
                'fixture-tampered',
                $tampered
            ),
            'data-red-addon-admin-tool-form-unavailable'
        ),
        'renderer fails closed on value or state-evidence tampering'
    );

    foreach (['invalid', 'extra', 'oversized'] as $failureMode) {
        $loaderMode = $failureMode;
        $failure = red_addon_admin_tool_form_load_values(
            $connection,
            $toolId,
            $formId,
            41,
            $actorId
        );
        red_addon_admin_form_value_test_assert(
            $failure['authorized'] === true
                && $failure['invoked'] === true
                && empty($failure['loaded'])
                && $failure['values'] === []
                && $failure['stateSha256'] === ''
                && $failure['reason'] === 'invalid_values',
            'undeclared or invalid provider values fail closed'
        );
    }

    foreach (['output', 'buffer', 'throw'] as $failureMode) {
        $loaderMode = $failureMode;
        $bufferBefore = ob_get_level();
        $failure = red_addon_admin_tool_form_load_values(
            $connection,
            $toolId,
            $formId,
            41,
            $actorId
        );
        red_addon_admin_form_value_test_assert(
            $failure['authorized'] === true
                && $failure['invoked'] === true
                && empty($failure['loaded'])
                && in_array(
                    $failure['reason'],
                    ['loader_output', 'loader_failed'],
                    true
                )
                && ob_get_level() === $bufferBefore,
            'loader output, buffer drift, and exceptions remain contained'
        );
    }
    $loaderMode = 'normal';

    $driftManifest = red_addon_admin_form_value_test_manifest(
        $packageId,
        $toolId,
        $formId,
        $permission,
        'Review one revised product contract.'
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_value_test_context(
            $driftManifest,
            $packageId,
            $toolId,
            $formId
        );
    $drift = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        41,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        $drift['loaded'] === true
            && $drift['contractSha256'] !== $loaded['contractSha256']
            && $drift['stateSha256'] !== $loaded['stateSha256'],
        'current schema drift changes contract and value-state evidence'
    );

    red_addon_admin_form_value_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $callsBeforeRevoke = $calls['loader'];
    $revoked = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        41,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        empty($revoked['authorized'])
            && empty($revoked['invoked'])
            && empty($revoked['loaded'])
            && $revoked['reason'] === 'permission_denied'
            && $calls['loader'] === $callsBeforeRevoke,
        'permission revocation applies before the next loader call'
    );
    $initialCallsBeforeRevoke = $initialCalls;
    $revokedInitial = red_addon_admin_tool_form_load_initial_values(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    red_addon_admin_form_value_test_assert(
        empty($revokedInitial['authorized'])
            && empty($revokedInitial['invoked'])
            && empty($revokedInitial['loaded'])
            && $revokedInitial['reason'] === 'permission_denied'
            && $initialCalls === $initialCallsBeforeRevoke,
        'permission revocation applies before the next initial-value load'
    );

    foreach ([0, '41', 2147483648] as $invalidTarget) {
        $invalid = red_addon_admin_tool_form_load_values(
            $connection,
            $toolId,
            $formId,
            $invalidTarget,
            $actorId
        );
        red_addon_admin_form_value_test_assert(
            empty($invalid['authorized'])
                && empty($invalid['invoked'])
                && empty($invalid['loaded'])
                && $invalid['reason'] === 'invalid_request',
            'invalid target identity fails before authorization or invocation'
        );
    }

    red_addon_admin_form_value_test_assert(
        (string) red_addon_admin_form_value_test_scalar(
            $connection,
            'SELECT GROUP_CONCAT(CONCAT_WS(\':\', RecordID, ProductKey,
                ProductType, IsActive, Description) ORDER BY RecordID SEPARATOR \'|\')
             FROM RED_Addon_Admin_Form_Value_Fixture'
        ) === $fixtureBefore,
        'all value loads preserve package-owned fixture rows exactly'
    );

    $source = (string) file_get_contents(
        $projectRoot . '/includes/addon_admin_tool_form_value_helpers.php'
    );
    $initialSource = (string) file_get_contents(
        $projectRoot .
            '/includes/addon_admin_tool_form_initial_value_helpers.php'
    );
    red_addon_admin_form_value_test_assert(
        !str_contains($source, '$_POST')
            && !str_contains($source, '$_GET')
            && !str_contains($source, '$_SESSION')
            && !str_contains($source, 'red_verify_csrf(')
            && !str_contains($initialSource, '$_POST')
            && !str_contains($initialSource, '$_GET')
            && !str_contains($initialSource, '$_SESSION')
            && !str_contains($initialSource, 'red_verify_csrf(')
            && !is_file($projectRoot . '/admin/bin/load_addon_tool_form.php'),
        'current and initial value loading add no request, session, CSRF, or endpoint surface'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_admin_form_value_test_cleanup($connection, $actorId);
    red_addon_admin_form_value_test_assert(
        (int) red_addon_admin_form_value_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME='RED_Addon_Admin_Form_Value_Fixture'"
            ) === 0
            && (int) red_addon_admin_form_value_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='redcms.tool-form-value-fixture'"
            ) === 0
            && (int) red_addon_admin_form_value_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='redcms.tool-form-value-fixture'"
            ) === 0
            && (int) red_addon_admin_form_value_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Admin WHERE RecordID=' . (int) $actorId
            ) === 0,
        'administrator and package table fixtures are removed exactly'
    );
    $db->close();
}

fwrite(
    STDOUT,
    'Add-on administrator tool form value loader self-test passed ('
        . $assertions . " assertions).\n"
);

?>
