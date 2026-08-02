<?php
/**
 * Disposable database checks for permission-scoped administrator tools.
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
require_once $projectRoot . '/includes/addon_admin_tool_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_admin_tool|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on administrator tool self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$calls = 0;
$actorId = 2147000966;
$toolId = 'redcms.admin-tool-fixture/orders';
$permission = 'fixture.orders.view';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_admin_tool_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_tool_test_execute($connection, $sql, $types = '', array $values = [])
{
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare administrator tool fixture SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Administrator tool fixture SQL failed: ' . $error);
    }
    mysqli_stmt_close($statement);
}

function red_addon_admin_tool_test_context(
    $toolId,
    $permission,
    callable $handler
) {
    $manifest = [
        'id' => 'redcms.admin-tool-fixture',
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [$toolId],
            'adapters' => [],
        ],
        'permissions' => [$permission],
        'componentEditors' => [],
        'adminToolContracts' => [[
            'tool' => $toolId,
            'label' => 'Orders <unsafe>',
            'description' => 'Read-only order overview.',
            'icon' => 'orders',
            'permission' => $permission,
            'mode' => 'read-only',
        ]],
        'routes' => [],
    ];
    $registry = new RED_Addon_Runtime_Registry(
        'redcms.admin-tool-fixture',
        $manifest
    );
    $registry->registerAdminTool($toolId, $handler);
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        ['redcms.admin-tool-fixture'],
        ['redcms.admin-tool-fixture' => $registry]
    );
}

function red_addon_admin_tool_test_cleanup($connection, $actorId)
{
    try {
        red_addon_admin_tool_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_tool_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_tool_test_execute(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=?',
            'i',
            [$actorId]
        );
    } catch (Throwable $throwable) {
        error_log(
            'Add-on administrator tool cleanup failed: ' .
            $throwable->getMessage()
        );
    }
}

try {
    red_addon_admin_tool_test_cleanup($connection, $actorId);
    red_addon_admin_tool_test_assert(
        red_addon_component_editor_permission_storage_available($connection),
        'fresh package-permission storage is available in the disposable database'
    );

    $password = password_hash('AddonAdminTool-2026!', PASSWORD_DEFAULT);
    red_addon_admin_tool_test_execute(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_admin_tool', ?, 'Admin',
            'AddonTool', 'webmaster', '', '', 'addon-tool@example.test',
            'N', 'to', 'N', 'to')",
        'is',
        [$actorId, $password]
    );

    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $missing = red_addon_admin_tool_dispatch(
        $connection,
        $toolId,
        $actorId
    );
    red_addon_admin_tool_test_assert(
        empty($missing['authorized'])
            && empty($missing['invoked'])
            && $missing['reason'] === 'tool_unavailable',
        'a tool cannot dispatch without request-local enabled ownership'
    );

    $handler = static function (RED_Addon_Admin_Tool_Request $request) use (&$calls) {
        $calls++;
        return RED_Addon_Admin_Tool_Result::view(
            'Orders <script>alert(1)</script>',
            'Actor #' . $request->actorRecordId() . ' opened ' . $request->tool(),
            [
                ['value' => '3 & waiting', 'label' => 'Pending <b>'],
                ['label' => 'Mode', 'value' => 'Read only'],
            ]
        );
    };
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_tool_test_context($toolId, $permission, $handler);

    $denied = red_addon_admin_tool_dispatch(
        $connection,
        $toolId,
        $actorId
    );
    red_addon_admin_tool_test_assert(
        empty($denied['authorized'])
            && empty($denied['invoked'])
            && $denied['reason'] === 'permission_denied'
            && $calls === 0,
        'a missing exact package grant refuses dispatch before the handler'
    );

    red_addon_admin_tool_test_execute(
        $connection,
        "INSERT INTO RED_Admin_Roles (
            AdminRecordID, RoleName, AssignedByAdminRecordID
         ) VALUES (?, 'owner', ?)",
        'ii',
        [$actorId, $actorId]
    );
    red_addon_admin_tool_test_execute(
        $connection,
        "INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, 'addons.enable', ?)",
        'ii',
        [$actorId, $actorId]
    );
    $ownerDenied = red_addon_admin_tool_dispatch(
        $connection,
        $toolId,
        $actorId
    );
    red_addon_admin_tool_test_assert(
        empty($ownerDenied['authorized'])
            && empty($ownerDenied['invoked'])
            && $calls === 0,
        'Owner and lifecycle authority do not imply administrator tool access'
    );

    red_addon_admin_tool_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    red_addon_admin_tool_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, 'Fixture.orders.view', $actorId]
    );
    $caseDenied = red_addon_admin_tool_dispatch(
        $connection,
        $toolId,
        $actorId
    );
    red_addon_admin_tool_test_assert(
        empty($caseDenied['authorized'])
            && empty($caseDenied['invoked'])
            && $calls === 0,
        'case-drifted package permission does not authorize a tool'
    );

    red_addon_admin_tool_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    red_addon_admin_tool_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    $catalog = red_addon_admin_tool_catalog($connection, $actorId);
    red_addon_admin_tool_test_assert(
        $catalog === [[
            'tool' => $toolId,
            'package' => 'redcms.admin-tool-fixture',
            'label' => 'Orders <unsafe>',
            'description' => 'Read-only order overview.',
            'icon' => 'orders',
            'permission' => $permission,
            'mode' => 'read-only',
        ]],
        'the tool chooser exposes only exact freshly granted manifest contracts'
    );

    $completed = red_addon_admin_tool_dispatch(
        $connection,
        $toolId,
        $actorId
    );
    red_addon_admin_tool_test_assert(
        $completed['authorized'] === true
            && $completed['invoked'] === true
            && $completed['success'] === true
            && $completed['reason'] === 'completed'
            && $completed['package'] === 'redcms.admin-tool-fixture'
            && $completed['permission'] === $permission
            && $calls === 1,
        'the exact granted actor invokes only the request-local tool owner'
    );
    red_addon_admin_tool_test_assert(
        str_contains($completed['html'], 'Orders &lt;script&gt;alert(1)&lt;/script&gt;')
            && str_contains($completed['html'], 'Pending &lt;b&gt;')
            && str_contains($completed['html'], '3 &amp; waiting')
            && !str_contains($completed['html'], '<script')
            && !str_contains($completed['html'], '<form')
            && !str_contains($completed['html'], '<button')
            && !str_contains($completed['html'], '<a '),
        'core escapes the bounded text model and emits no package action surface'
    );

    red_addon_admin_tool_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $revoked = red_addon_admin_tool_dispatch(
        $connection,
        $toolId,
        $actorId
    );
    red_addon_admin_tool_test_assert(
        empty($revoked['authorized'])
            && empty($revoked['invoked'])
            && $calls === 1,
        'permission revocation applies on the next dispatch decision'
    );

    red_addon_admin_tool_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    $invalid = red_addon_admin_tool_dispatch($connection, '../tool', 0);
    red_addon_admin_tool_test_assert(
        empty($invalid['authorized'])
            && empty($invalid['invoked'])
            && $invalid['reason'] === 'invalid_request',
        'invalid tool and actor identities fail before runtime lookup'
    );

    $modes = [
        'output' => static function () {
            echo 'forbidden';
            return RED_Addon_Admin_Tool_Result::view('Title', 'Description');
        },
        'malformed' => static fn () => ['title' => 'Unsafe'],
        'exception' => static function () {
            throw new RuntimeException('contained');
        },
        'buffer' => static function () {
            ob_end_clean();
            return RED_Addon_Admin_Tool_Result::view('Title', 'Description');
        },
        'status' => static function () {
            http_response_code(201);
            return RED_Addon_Admin_Tool_Result::view('Title', 'Description');
        },
    ];
    foreach ($modes as $mode => $modeHandler) {
        $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
            red_addon_admin_tool_test_context(
                $toolId,
                $permission,
                $modeHandler
            );
        $contained = red_addon_admin_tool_dispatch(
            $connection,
            $toolId,
            $actorId
        );
        red_addon_admin_tool_test_assert(
            $contained['authorized'] === true
                && $contained['invoked'] === true
                && empty($contained['success'])
                && $contained['html'] === '',
            'administrator tool ' . $mode . ' behavior is contained'
        );
    }

    red_addon_admin_tool_test_assert(
        ob_get_level() === 0 && http_response_code() === 200,
        'all administrator tool paths restore buffer and HTTP status state'
    );

    $endpoint = file_get_contents(
        $projectRoot . '/admin/bin/view_addon_tool.php'
    );
    $chooser = file_get_contents(
        $projectRoot . '/admin/class/class_add_tools.php'
    );
    red_addon_admin_tool_test_assert(
        str_contains($endpoint, 'red_require_admin(true)')
            && str_contains($endpoint, 'red_addon_runtime_request_bootstrap(')
            && str_contains($endpoint, 'red_addon_admin_tool_dispatch(')
            && str_contains($chooser, 'red_addon_admin_tool_catalog(')
            && str_contains($chooser, '/admin/bin/view_addon_tool.php'),
        'the core chooser and POST/CSRF endpoint use only the scoped dispatcher'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_admin_tool_test_cleanup($connection, $actorId);
    $remaining = mysqli_query(
        $connection,
        'SELECT CONCAT_WS(\':\',
            (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=' . $actorId . '),
            (SELECT COUNT(*) FROM RED_Admin_Roles WHERE AdminRecordID=' . $actorId . '),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities WHERE AdminRecordID=' . $actorId . ')
        ) AS Remaining'
    );
    $row = $remaining ? mysqli_fetch_assoc($remaining) : null;
    if ($remaining) {
        mysqli_free_result($remaining);
    }
    red_addon_admin_tool_test_assert(
        ($row['Remaining'] ?? '') === '0:0:0',
        'administrator, role, and grant fixtures are removed exactly'
    );
    $db->close();
}

fwrite(
    STDOUT,
    'Add-on administrator tool dispatch self-test passed (' .
        $assertions . " assertions).\n"
);

?>
