<?php
/**
 * Disposable checks for the protected administrator-form JSON adapter.
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
require_once $projectRoot
    . '/includes/addon_admin_tool_form_submission_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_admin_tool_form_json|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on administrator form JSON adapter refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000963;
$packageId = 'redcms.tool-form-json-fixture';
$toolId = $packageId . '/products';
$formId = $packageId . '/product-editor';
$permission = 'fixture.products.manage';
$calls = ['tool' => 0, 'loader' => 0];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_admin_form_json_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_form_json_test_execute(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare form JSON fixture SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Form JSON fixture SQL failed: ' . $error);
    }
    mysqli_stmt_close($statement);
}

function red_addon_admin_form_json_test_scalar(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare form JSON scalar SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Form JSON scalar SQL failed: ' . $error);
    }
    $result = mysqli_stmt_get_result($statement);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    mysqli_stmt_close($statement);
    return $row[0] ?? null;
}

function red_addon_admin_form_json_test_manifest(
    $packageId,
    $toolId,
    $formId,
    $permission,
    $maxBodyBytes = 4096,
    $description = 'Validate one bounded product submission.'
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
            'description' => 'Review products.',
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
            'maxBodyBytes' => $maxBodyBytes,
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
                    'label' => 'Type',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'simple', 'label' => 'Simple'],
                        ['value' => 'variable', 'label' => 'Variable'],
                    ],
                ],
                [
                    'key' => 'price-minor',
                    'label' => 'Price',
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 0,
                    'maximum' => 999999999,
                ],
                [
                    'key' => 'options',
                    'label' => 'Options',
                    'type' => 'collection',
                    'required' => false,
                    'itemLabel' => 'Option',
                    'minItems' => 0,
                    'maxItems' => 3,
                    'fields' => [
                        [
                            'key' => 'key',
                            'label' => 'Key',
                            'type' => 'text',
                            'required' => true,
                            'minLength' => 1,
                            'maxLength' => 32,
                        ],
                        [
                            'key' => 'values',
                            'label' => 'Values',
                            'type' => 'collection',
                            'required' => true,
                            'itemLabel' => 'Value',
                            'minItems' => 1,
                            'maxItems' => 16,
                            'fields' => [[
                                'key' => 'label',
                                'label' => 'Label',
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

function red_addon_admin_form_json_test_values($connection, $recordId)
{
    $statement = mysqli_prepare(
        $connection,
        'SELECT ProductKey, ProductType, PriceMinor
         FROM RED_Addon_Admin_Form_JSON_Fixture WHERE RecordID=? LIMIT 1'
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
        throw new RuntimeException('JSON adapter product is unavailable.');
    }
    return [
        'id' => $row['ProductKey'],
        'type' => $row['ProductType'],
        'price-minor' => (int) $row['PriceMinor'],
        'options' => [[
            'key' => 'size',
            'values' => [['label' => 'Small'], ['label' => 'Large']],
        ]],
    ];
}

function red_addon_admin_form_json_test_context(
    array $manifest,
    $packageId,
    $toolId,
    $formId
) {
    global $calls;
    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerAdminTool(
        $toolId,
        static function () use (&$calls) {
            $calls['tool']++;
            throw new RuntimeException('Display tool must remain uninvoked.');
        }
    );
    $registry->registerAdminToolFormValueLoader(
        $formId,
        static function ($connection, $request) use (&$calls) {
            $calls['loader']++;
            return RED_Addon_Admin_Tool_Form_Values::current(
                red_addon_admin_form_json_test_values(
                    $connection,
                    $request->targetRecordId()
                )
            );
        }
    );
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );
}

function red_addon_admin_form_json_test_encode(array $payload)
{
    return json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
}

function red_addon_admin_form_json_test_cleanup($connection, $actorId)
{
    try {
        red_addon_admin_form_json_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_form_json_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_form_json_test_execute(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=?',
            'i',
            [$actorId]
        );
        mysqli_query(
            $connection,
            'DROP TABLE IF EXISTS RED_Addon_Admin_Form_JSON_Fixture'
        );
    } catch (Throwable $throwable) {
        error_log('Administrator form JSON fixture cleanup failed.');
    }
}

try {
    $transportBody = '{"fixture":true}';
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $transportBody);
    rewind($stream);
    $transport = red_addon_admin_tool_form_submission_read_body(
        'application/json',
        (string) strlen($transportBody),
        $stream
    );
    fclose($stream);
    red_addon_admin_form_json_test_assert(
        $transport['valid'] === true
            && $transport['rawBody'] === $transportBody
            && $transport['reason'] === 'valid',
        'exact JSON content type, canonical length, and bounded stream are accepted'
    );

    foreach (
        [
            ['application/json; charset=UTF-8', (string) strlen($transportBody)],
            ['application/json', '0'],
            ['application/json', '01'],
            ['application/json', '262145'],
            ['application/json', (string) (strlen($transportBody) - 1)],
        ]
        as [$candidateType, $candidateLength]
    ) {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $transportBody);
        rewind($stream);
        $refused = red_addon_admin_tool_form_submission_read_body(
            $candidateType,
            $candidateLength,
            $stream
        );
        fclose($stream);
        red_addon_admin_form_json_test_assert(
            empty($refused['valid']) && $refused['rawBody'] === '',
            'alternate content type, length, size, and extra bytes fail closed'
        );
    }

    $decodeFixture = [
        'tool' => 'redcms.fixture/tool',
        'form' => 'redcms.fixture/form',
        'targetRecordId' => 1,
        'currentStateSha256' => str_repeat('a', 64),
        'values' => ['id' => 'item'],
    ];
    $decoded = red_addon_admin_tool_form_submission_decode(
        red_addon_admin_form_json_test_encode($decodeFixture)
    );
    red_addon_admin_form_json_test_assert(
        is_array($decoded)
            && $decoded['targetRecordId'] === 1
            && $decoded['values'] === ['id' => 'item'],
        'canonical closed JSON root decodes into exact internal request fields'
    );
    foreach (
        [
            ' ' . red_addon_admin_form_json_test_encode($decodeFixture),
            str_replace(
                '"tool":"redcms.fixture/tool"',
                '"tool":"redcms.fixture/tool","tool":"redcms.fixture/tool"',
                red_addon_admin_form_json_test_encode($decodeFixture)
            ),
            red_addon_admin_form_json_test_encode(
                $decodeFixture + ['extra' => true]
            ),
            str_replace('"targetRecordId":1', '"targetRecordId":"1"',
                red_addon_admin_form_json_test_encode($decodeFixture)),
            '[]',
        ]
        as $invalidJSON
    ) {
        red_addon_admin_form_json_test_assert(
            red_addon_admin_tool_form_submission_decode($invalidJSON) === null,
            'noncanonical, duplicate, unknown, mistyped, and non-object JSON fails closed'
        );
    }

    red_addon_admin_form_json_test_cleanup($connection, $actorId);
    red_addon_admin_form_json_test_execute(
        $connection,
        'CREATE TABLE RED_Addon_Admin_Form_JSON_Fixture (
            RecordID INT NOT NULL PRIMARY KEY,
            ProductKey VARCHAR(64) NOT NULL,
            ProductType VARCHAR(16) NOT NULL,
            PriceMinor INT NOT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    red_addon_admin_form_json_test_execute(
        $connection,
        "INSERT INTO RED_Addon_Admin_Form_JSON_Fixture
            (RecordID, ProductKey, ProductType, PriceMinor)
         VALUES (51, 'shirt', 'variable', 2400)"
    );
    $password = password_hash('AddonFormJSON-2026!', PASSWORD_DEFAULT);
    red_addon_admin_form_json_test_execute(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_form_json', ?, 'Admin',
            'FormJSON', 'webmaster', '', '', 'form-json@example.test',
            'N', 'to', 'N', 'to')",
        'is',
        [$actorId, $password]
    );

    $manifest = red_addon_admin_form_json_test_manifest(
        $packageId,
        $toolId,
        $formId,
        $permission
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_json_test_context(
            $manifest,
            $packageId,
            $toolId,
            $formId
        );
    $placeholder = [
        'tool' => $toolId,
        'form' => $formId,
        'targetRecordId' => 51,
        'currentStateSha256' => str_repeat('b', 64),
        'values' => red_addon_admin_form_json_test_values($connection, 51),
    ];
    $denied = red_addon_admin_tool_form_submission_prepare(
        $connection,
        red_addon_admin_form_json_test_encode($placeholder),
        $actorId
    );
    red_addon_admin_form_json_test_assert(
        empty($denied['authorized'])
            && empty($denied['invoked'])
            && empty($denied['prepared'])
            && $denied['reason'] === 'permission_denied'
            && $calls === ['tool' => 0, 'loader' => 0],
        'missing exact package permission refuses before value loading'
    );

    red_addon_admin_form_json_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Roles
            (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES (?, \'owner\', ?)',
        'ii',
        [$actorId, $actorId]
    );
    red_addon_admin_form_json_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
            (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    $loaded = red_addon_admin_tool_form_load_values(
        $connection,
        $toolId,
        $formId,
        51,
        $actorId
    );
    red_addon_admin_form_json_test_assert(
        $loaded['loaded'] === true
            && red_addon_valid_sha256($loaded['stateSha256']),
        'current state is loaded through the existing permission-scoped provider'
    );

    $submittedValues = $loaded['values'];
    $submittedValues['price-minor'] = 2600;
    $payload = [
        'tool' => $toolId,
        'form' => $formId,
        'targetRecordId' => 51,
        'currentStateSha256' => $loaded['stateSha256'],
        'values' => $submittedValues,
    ];
    $rawPayload = red_addon_admin_form_json_test_encode($payload);
    $fixtureBefore = (string) red_addon_admin_form_json_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':', RecordID, ProductKey, ProductType, PriceMinor)
         FROM RED_Addon_Admin_Form_JSON_Fixture WHERE RecordID=51"
    );
    $callsBeforePrepare = $calls;
    $prepared = red_addon_admin_tool_form_submission_prepare(
        $connection,
        $rawPayload,
        $actorId
    );
    $repeat = red_addon_admin_tool_form_submission_prepare(
        $connection,
        $rawPayload,
        $actorId
    );
    red_addon_admin_form_json_test_assert(
        $prepared['authorized'] === true
            && $prepared['invoked'] === true
            && $prepared['prepared'] === true
            && $prepared['package'] === $packageId
            && $prepared['targetRecordId'] === 51
            && $prepared['actorRecordId'] === $actorId
            && $prepared['permission'] === $permission
            && $prepared['currentStateSha256'] === $loaded['stateSha256']
            && $prepared['values']['price-minor'] === 2600
            && red_addon_valid_sha256($prepared['contractSha256'])
            && red_addon_valid_sha256($prepared['submittedValuesSha256'])
            && red_addon_valid_sha256($prepared['planSha256'])
            && $prepared['reason'] === 'prepared'
            && $calls['tool'] === 0
            && $calls['loader'] === $callsBeforePrepare['loader'] + 2,
        'fresh grant and current state prepare normalized nested values without a writer'
    );
    red_addon_admin_form_json_test_assert(
        $repeat['values'] === $prepared['values']
            && $repeat['planSha256'] === $prepared['planSha256'],
        'identical actor, contract, target, state, and values produce deterministic evidence'
    );

    $public = red_addon_admin_tool_form_submission_public_result($prepared);
    red_addon_admin_form_json_test_assert(
        $public === [
            'httpStatus' => 200,
            'body' => ['ok' => true, 'status' => 'validated'],
        ]
            && !str_contains(serialize($public), 'shirt')
            && !str_contains(serialize($public), $prepared['planSha256']),
        'public success contains no values, identity, state, or plan evidence'
    );

    $stalePayload = $payload;
    $stalePayload['currentStateSha256'] = str_repeat('c', 64);
    $stale = red_addon_admin_tool_form_submission_prepare(
        $connection,
        red_addon_admin_form_json_test_encode($stalePayload),
        $actorId
    );
    red_addon_admin_form_json_test_assert(
        empty($stale['prepared'])
            && $stale['reason'] === 'state_conflict'
            && $stale['values'] === []
            && red_addon_admin_tool_form_submission_public_result($stale)
                === [
                    'httpStatus' => 409,
                    'body' => ['ok' => false, 'reason' => 'state_conflict'],
                ],
        'stale current-state evidence refuses without reflecting submitted values'
    );

    foreach (['invalid', 'extra'] as $mode) {
        $invalidPayload = $payload;
        if ($mode === 'invalid') {
            $invalidPayload['values']['price-minor'] = 26.5;
        } else {
            $invalidPayload['values']['undeclared'] = true;
        }
        $invalid = red_addon_admin_tool_form_submission_prepare(
            $connection,
            red_addon_admin_form_json_test_encode($invalidPayload),
            $actorId
        );
        red_addon_admin_form_json_test_assert(
            empty($invalid['prepared'])
                && $invalid['reason'] === 'invalid_values'
                && $invalid['values'] === [],
            'mistyped and undeclared submitted fields fail closed'
        );
    }

    $callsBeforeLimit = $calls['loader'];
    $smallManifest = red_addon_admin_form_json_test_manifest(
        $packageId,
        $toolId,
        $formId,
        $permission,
        128
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_json_test_context(
            $smallManifest,
            $packageId,
            $toolId,
            $formId
        );
    $oversized = red_addon_admin_tool_form_submission_prepare(
        $connection,
        $rawPayload,
        $actorId
    );
    red_addon_admin_form_json_test_assert(
        empty($oversized['prepared'])
            && $oversized['reason'] === 'body_too_large'
            && $calls['loader'] === $callsBeforeLimit,
        'manifest body limit is enforced before current-value provider invocation'
    );

    $driftManifest = red_addon_admin_form_json_test_manifest(
        $packageId,
        $toolId,
        $formId,
        $permission,
        4096,
        'Validate one revised product submission.'
    );
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_json_test_context(
            $driftManifest,
            $packageId,
            $toolId,
            $formId
        );
    $drifted = red_addon_admin_tool_form_submission_prepare(
        $connection,
        $rawPayload,
        $actorId
    );
    red_addon_admin_form_json_test_assert(
        empty($drifted['prepared'])
            && $drifted['reason'] === 'state_conflict'
            && $drifted['contractSha256'] !== $prepared['contractSha256'],
        'current contract drift invalidates previously loaded state evidence'
    );

    red_addon_admin_form_json_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $callsBeforeRevoke = $calls['loader'];
    $revoked = red_addon_admin_tool_form_submission_prepare(
        $connection,
        $rawPayload,
        $actorId
    );
    red_addon_admin_form_json_test_assert(
        empty($revoked['authorized'])
            && empty($revoked['invoked'])
            && empty($revoked['prepared'])
            && $revoked['reason'] === 'permission_denied'
            && $calls['loader'] === $callsBeforeRevoke,
        'grant revocation applies before the next body preparation provider call'
    );

    red_addon_admin_form_json_test_assert(
        (string) red_addon_admin_form_json_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':', RecordID, ProductKey, ProductType, PriceMinor)
             FROM RED_Addon_Admin_Form_JSON_Fixture WHERE RecordID=51"
        ) === $fixtureBefore,
        'validation, conflict, drift, and refusal paths preserve package data exactly'
    );

    $helperSource = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_admin_tool_form_submission_helpers.php'
    );
    $endpointSource = (string) file_get_contents(
        $projectRoot . '/admin/bin/validate_addon_tool_form.php'
    );
    $authOffset = strpos($endpointSource, 'red_require_admin(true);');
    $bodyOffset = strpos($endpointSource, "fopen('php://input'");
    red_addon_admin_form_json_test_assert(
        !str_contains($helperSource, '$_POST')
            && !str_contains($helperSource, '$_GET')
            && !str_contains($helperSource, '$_SESSION')
            && !str_contains($helperSource, 'php://input')
            && !str_contains($helperSource, 'registerAdminToolFormWriter')
            && !str_contains($endpointSource, '$_POST')
            && is_int($authOffset)
            && is_int($bodyOffset)
            && $authOffset < $bodyOffset,
        'core endpoint authenticates and verifies CSRF before body I/O while helper stays transport-explicit and writer-free'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_admin_form_json_test_cleanup($connection, $actorId);
    red_addon_admin_form_json_test_assert(
        (int) red_addon_admin_form_json_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME='RED_Addon_Admin_Form_JSON_Fixture'"
        ) === 0
            && (int) red_addon_admin_form_json_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Admin WHERE RecordID=' . (int) $actorId
            ) === 0,
        'administrator and package-table JSON fixtures are removed exactly'
    );
    $db->close();
}

fwrite(
    STDOUT,
    'Add-on administrator tool form JSON adapter self-test passed ('
        . $assertions . " assertions).\n"
);

?>
