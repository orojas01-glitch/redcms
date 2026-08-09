<?php
/**
 * Disposable checks for non-executing administrator tool form planning.
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
require_once $projectRoot .
    '/includes/addon_admin_tool_form_preflight_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_admin_tool_form|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on administrator tool form preflight refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$calls = 0;
$actorId = 2147000971;
$toolId = 'redcms.tool-form-fixture/products';
$formId = 'redcms.tool-form-fixture/product-editor';
$permission = 'fixture.products.manage';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_admin_tool_form_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_tool_form_test_execute(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare administrator form SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException(
            'Administrator form fixture SQL failed: ' . $error
        );
    }
    mysqli_stmt_close($statement);
}

function red_addon_admin_tool_form_test_counts($connection, $actorId)
{
    $result = mysqli_query(
        $connection,
        'SELECT CONCAT_WS(\':\',
            (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=' . (int) $actorId . '),
            (SELECT COUNT(*) FROM RED_Admin_Roles WHERE AdminRecordID=' . (int) $actorId . '),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities WHERE AdminRecordID=' . (int) $actorId . ')
        ) AS Counts'
    );
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return (string) ($row['Counts'] ?? '');
}

function red_addon_admin_tool_form_test_cleanup($connection, $actorId)
{
    try {
        red_addon_admin_tool_form_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_tool_form_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_tool_form_test_execute(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=?',
            'i',
            [$actorId]
        );
    } catch (Throwable $throwable) {
        error_log('Administrator form fixture cleanup failed.');
    }
}

function red_addon_admin_tool_form_test_manifest(
    $toolId,
    $formId,
    $permission,
    $description = 'Prepare one bounded product editor.'
) {
    return [
        'id' => 'redcms.tool-form-fixture',
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
            'description' => 'Review product status.',
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
            'maxBodyBytes' => 262144,
        ]],
        'routes' => [],
    ];
}

function red_addon_admin_tool_form_test_context(
    $toolId,
    $formId,
    $permission,
    callable $handler,
    $description = 'Prepare one bounded product editor.'
) {
    $manifest = red_addon_admin_tool_form_test_manifest(
        $toolId,
        $formId,
        $permission,
        $description
    );
    $registry = new RED_Addon_Runtime_Registry(
        'redcms.tool-form-fixture',
        $manifest
    );
    $registry->registerAdminTool($toolId, $handler);
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        ['redcms.tool-form-fixture'],
        ['redcms.tool-form-fixture' => $registry]
    );
}

try {
    red_addon_admin_tool_form_test_cleanup($connection, $actorId);
    red_addon_admin_tool_form_test_assert(
        red_addon_component_editor_permission_storage_available($connection),
        'fresh package-permission storage is available in the disposable database'
    );

    $password = password_hash('AddonToolForm-2026!', PASSWORD_DEFAULT);
    red_addon_admin_tool_form_test_execute(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_tool_form', ?, 'Admin',
            'ToolForm', 'webmaster', '', '', 'addon-tool-form@example.test',
            'N', 'to', 'N', 'to')",
        'is',
        [$actorId, $password]
    );

    $manifest = red_addon_admin_tool_form_test_manifest(
        $toolId,
        $formId,
        $permission
    );
    red_addon_admin_tool_form_test_assert(
        red_addon_admin_tool_form_contract(
            $manifest,
            $toolId,
            $formId
        ) === $manifest['adminToolFormContracts'][0],
        'closed form metadata resolves without executing package code'
    );

    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $missing = red_addon_admin_tool_form_preflight(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    red_addon_admin_tool_form_test_assert(
        empty($missing['authorized'])
            && empty($missing['ready'])
            && empty($missing['invoked'])
            && $missing['reason'] === 'form_unavailable',
        'a form cannot plan without exact request-local tool ownership'
    );

    $handler = static function () use (&$calls) {
        $calls++;
        throw new RuntimeException('tool callback must remain uninvoked');
    };
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_tool_form_test_context(
            $toolId,
            $formId,
            $permission,
            $handler
        );
    $denied = red_addon_admin_tool_form_preflight(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    red_addon_admin_tool_form_test_assert(
        empty($denied['authorized'])
            && empty($denied['ready'])
            && $denied['reason'] === 'permission_denied'
            && $calls === 0,
        'missing exact form permission refuses without invoking the tool'
    );

    red_addon_admin_tool_form_test_execute(
        $connection,
        "INSERT INTO RED_Admin_Roles (
            AdminRecordID, RoleName, AssignedByAdminRecordID
         ) VALUES (?, 'owner', ?)",
        'ii',
        [$actorId, $actorId]
    );
    red_addon_admin_tool_form_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, 'addons.enable', $actorId]
    );
    red_addon_admin_tool_form_test_assert(
        empty(red_addon_admin_tool_form_preflight(
            $connection,
            $toolId,
            $formId,
            $actorId
        )['authorized']) && $calls === 0,
        'Owner and lifecycle authority do not imply operational form access'
    );

    red_addon_admin_tool_form_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    red_addon_admin_tool_form_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, 'Fixture.products.manage', $actorId]
    );
    red_addon_admin_tool_form_test_assert(
        empty(red_addon_admin_tool_form_preflight(
            $connection,
            $toolId,
            $formId,
            $actorId
        )['authorized']) && $calls === 0,
        'case-drifted package permission cannot authorize a form plan'
    );

    red_addon_admin_tool_form_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    red_addon_admin_tool_form_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    $countsBefore = red_addon_admin_tool_form_test_counts(
        $connection,
        $actorId
    );
    $ready = red_addon_admin_tool_form_preflight(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    $repeat = red_addon_admin_tool_form_preflight(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    $countsAfter = red_addon_admin_tool_form_test_counts(
        $connection,
        $actorId
    );
    red_addon_admin_tool_form_test_assert(
        $ready['authorized'] === true
            && $ready['ready'] === true
            && $ready['invoked'] === false
            && $ready['tool'] === $toolId
            && $ready['form'] === $formId
            && $ready['package'] === 'redcms.tool-form-fixture'
            && $ready['actorRecordId'] === $actorId
            && $ready['permission'] === $permission
            && $ready['method'] === 'POST'
            && $ready['csrf'] === 'required'
            && $ready['encoding'] === 'application/json'
            && $ready['maxBodyBytes'] === 262144
            && red_addon_valid_sha256($ready['contractSha256'])
            && red_addon_valid_sha256($ready['planSha256'])
            && $ready['planSha256'] === $repeat['planSha256']
            && $ready['reason'] === 'preflight_ready'
            && $countsBefore === '1:1:1'
            && $countsAfter === $countsBefore
            && $calls === 0,
        'exact grant produces deterministic value-free form evidence without callback or database mutation'
    );

    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_tool_form_test_context(
            $toolId,
            $formId,
            $permission,
            $handler,
            'Prepare one revised bounded product editor.'
        );
    $drift = red_addon_admin_tool_form_preflight(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    red_addon_admin_tool_form_test_assert(
        $drift['ready'] === true
            && $drift['contractSha256'] !== $ready['contractSha256']
            && $drift['planSha256'] !== $ready['planSha256']
            && $calls === 0,
        'current declarative form drift changes both deterministic hashes'
    );

    foreach (
        [
            [$toolId, $formId, 0],
            [$toolId, $formId, '1'],
            ['../tool', $formId, $actorId],
            [$toolId, '../form', $actorId],
        ] as [$candidateTool, $candidateForm, $candidateActor]
    ) {
        $invalid = red_addon_admin_tool_form_preflight(
            $connection,
            $candidateTool,
            $candidateForm,
            $candidateActor
        );
        red_addon_admin_tool_form_test_assert(
            empty($invalid['authorized'])
                && empty($invalid['ready'])
                && empty($invalid['invoked'])
                && $invalid['reason'] === 'invalid_request'
                && $calls === 0,
            'invalid form identities and actor input fail before callback invocation'
        );
    }

    $badManifest = red_addon_admin_tool_form_test_manifest(
        $toolId,
        $formId,
        $permission
    );
    $badManifest['adminToolFormContracts'][0]['encoding'] =
        'multipart/form-data';
    red_addon_admin_tool_form_test_assert(
        red_addon_admin_tool_form_contract(
            $badManifest,
            $toolId,
            $formId
        ) === null,
        'forged non-JSON form metadata cannot enter preflight binding'
    );

    red_addon_admin_tool_form_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $revoked = red_addon_admin_tool_form_preflight(
        $connection,
        $toolId,
        $formId,
        $actorId
    );
    red_addon_admin_tool_form_test_assert(
        empty($revoked['authorized'])
            && empty($revoked['ready'])
            && $revoked['reason'] === 'permission_denied'
            && $calls === 0,
        'permission revocation applies on the next form preflight'
    );

    $source = (string) file_get_contents(
        $projectRoot .
            '/includes/addon_admin_tool_form_preflight_helpers.php'
    );
    red_addon_admin_tool_form_test_assert(
        !str_contains($source, '$_POST')
            && !str_contains($source, '$_SESSION')
            && !str_contains($source, 'red_csrf_token(')
            && !str_contains($source, 'red_verify_csrf(')
            && !str_contains($source, 'red_addon_runtime_request_bootstrap(')
            && preg_match(
                '/\b(?:START TRANSACTION|COMMIT|ROLLBACK|INSERT|UPDATE|DELETE)\b/i',
                $source
            ) !== 1
            && !is_file(
                $projectRoot . '/admin/bin/run_addon_tool_form.php'
            ),
        'form preflight has no request, CSRF, transaction, callback, or endpoint surface'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_admin_tool_form_test_cleanup($connection, $actorId);
    red_addon_admin_tool_form_test_assert(
        red_addon_admin_tool_form_test_counts($connection, $actorId)
            === '0:0:0',
        'administrator, role, and grant fixtures are removed exactly'
    );
    $db->close();
}

fwrite(
    STDOUT,
    'Add-on administrator tool form preflight self-test passed (' .
        $assertions . " assertions).\n"
);

?>
