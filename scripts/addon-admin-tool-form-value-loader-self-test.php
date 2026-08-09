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
$loaderMode = 'normal';
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
        'permissions' => [$permission],
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
            'maxBodyBytes' => 256,
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
    return [
        'id' => $row['ProductKey'],
        'type' => $row['ProductType'],
        'active' => ((int) $row['IsActive']) === 1,
        'description' => $row['Description'],
        'options' => [[
            'key' => 'size',
            'values' => [
                ['label' => 'Small'],
                ['label' => 'Large & Tall'],
            ],
        ]],
    ];
}

function red_addon_admin_form_value_test_context(
    array $manifest,
    $packageId,
    $toolId,
    $formId
) {
    global $calls, $loaderMode;
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
        static function ($connection, $request) use (&$calls, &$loaderMode) {
            $calls['loader']++;
            if (!$request instanceof RED_Addon_Admin_Tool_Form_Value_Request
                || !$connection
            ) {
                throw new RuntimeException('Loader arguments are invalid.');
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
                $values['description'] = str_repeat('x', 200);
            }
            return RED_Addon_Admin_Tool_Form_Values::current($values);
        }
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
            === $packageId,
        'schema-bearing form has one exact runtime value-loader owner'
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
            && red_addon_valid_sha256($loaded['stateSha256'])
            && $loaded['reason'] === 'loaded'
            && $calls === ['tool' => 0, 'loader' => 1],
        'exact grant loads one complete nested typed value graph without invoking the tool'
    );

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
    red_addon_admin_form_value_test_assert(
        !str_contains($source, '$_POST')
            && !str_contains($source, '$_GET')
            && !str_contains($source, '$_SESSION')
            && !str_contains($source, 'red_verify_csrf(')
            && !is_file($projectRoot . '/admin/bin/load_addon_tool_form.php'),
        'value loading adds no request, session, CSRF, or endpoint surface'
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
