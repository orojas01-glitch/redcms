<?php
/**
 * Disposable checks for the operational administrator-form Save bridge.
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
    . '/includes/addon_admin_tool_form_endpoint_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_form_bridge|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Administrator form Save bridge refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000963;
$targetId = 71;
$packageId = 'redcms.tool-form-bridge-fixture';
$toolId = $packageId . '/products';
$formId = $packageId . '/product-editor';
$permission = 'fixture.products.manage';
$table = 'RED_Addon_Admin_Form_Bridge_Fixture';
$calls = ['tool' => 0, 'loader' => 0, 'writer' => 0];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$browserMarkup = '';

function red_addon_admin_form_bridge_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_form_bridge_test_execute(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare Save-bridge fixture SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Save-bridge fixture SQL failed: ' . $error);
    }
    mysqli_stmt_close($statement);
}

function red_addon_admin_form_bridge_test_scalar(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare Save-bridge scalar SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    mysqli_stmt_execute($statement);
    $query = mysqli_stmt_get_result($statement);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    mysqli_stmt_close($statement);
    return $row[0] ?? null;
}

function red_addon_admin_form_bridge_test_manifest(
    $packageId,
    $toolId,
    $formId,
    $permission
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
            'label' => 'Product <editor>',
            'description' => 'Edit one bounded product & its options.',
            'permission' => $permission,
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/json',
            'maxBodyBytes' => 8192,
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
                    'key' => 'price-minor',
                    'label' => 'Price',
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 0,
                    'maximum' => 999999999,
                ],
                [
                    'key' => 'active',
                    'label' => 'Active',
                    'type' => 'boolean',
                    'required' => true,
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

function red_addon_admin_form_bridge_test_values($connection, $targetId)
{
    $statement = mysqli_prepare(
        $connection,
        'SELECT ProductKey, PriceMinor, Active, OptionsJSON
         FROM RED_Addon_Admin_Form_Bridge_Fixture
         WHERE RecordID=? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $targetId);
    mysqli_stmt_execute($statement);
    $query = mysqli_stmt_get_result($statement);
    $row = $query ? mysqli_fetch_assoc($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    mysqli_stmt_close($statement);
    $options = is_string($row['OptionsJSON'] ?? null)
        ? json_decode($row['OptionsJSON'], true)
        : null;
    if (!is_array($row) || !is_array($options)) {
        throw new RuntimeException('Save-bridge product is unavailable.');
    }
    return [
        'id' => $row['ProductKey'],
        'price-minor' => (int) $row['PriceMinor'],
        'active' => (int) $row['Active'] === 1,
        'options' => $options,
    ];
}

function red_addon_admin_form_bridge_test_context(
    array $manifest,
    $packageId,
    $toolId,
    $formId,
    $table,
    $withWriter = true
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
                red_addon_admin_form_bridge_test_values(
                    $connection,
                    $request->targetRecordId()
                )
            );
        }
    );
    if ($withWriter) {
        $registry->registerAdminToolFormWriter(
            $formId,
            static function ($connection, $request) use (&$calls) {
                $calls['writer']++;
                $values = $request->values();
                $options = json_encode(
                    $values['options'],
                    JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                        | JSON_THROW_ON_ERROR
                );
                red_addon_admin_form_bridge_test_execute(
                    $connection,
                    'UPDATE RED_Addon_Admin_Form_Bridge_Fixture
                     SET ProductKey=?, PriceMinor=?, Active=?, OptionsJSON=?
                     WHERE RecordID=?',
                    'siisi',
                    [
                        $values['id'],
                        $values['price-minor'],
                        $values['active'] ? 1 : 0,
                        $options,
                        $request->targetRecordId(),
                    ]
                );
                return true;
            },
            [$table]
        );
    }
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );
}

function red_addon_admin_form_bridge_test_encode(array $payload)
{
    return json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
}

function red_addon_admin_form_bridge_test_cleanup(
    $connection,
    $packageId,
    $actorId
) {
    try {
        red_addon_admin_form_bridge_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Activity_Log WHERE PackageID=?',
            's',
            [$packageId]
        );
        red_addon_admin_form_bridge_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Installations WHERE PackageID=?',
            's',
            [$packageId]
        );
        red_addon_admin_form_bridge_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_form_bridge_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_form_bridge_test_execute(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=?',
            'i',
            [$actorId]
        );
        mysqli_query(
            $connection,
            'DROP TABLE IF EXISTS RED_Addon_Admin_Form_Bridge_Fixture'
        );
    } catch (Throwable $throwable) {
        error_log('Administrator form Save-bridge cleanup failed.');
    }
}

try {
    red_addon_admin_form_bridge_test_cleanup(
        $connection,
        $packageId,
        $actorId
    );
    red_addon_admin_form_bridge_test_execute(
        $connection,
        'CREATE TABLE RED_Addon_Admin_Form_Bridge_Fixture (
            RecordID INT NOT NULL PRIMARY KEY,
            ProductKey VARCHAR(64) NOT NULL,
            PriceMinor INT NOT NULL,
            Active TINYINT(1) NOT NULL,
            OptionsJSON TEXT NOT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $options = json_encode(
        [[
            'key' => 'size',
            'values' => [['label' => 'Small'], ['label' => 'Large']],
        ]],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_addon_admin_form_bridge_test_execute(
        $connection,
        'INSERT INTO RED_Addon_Admin_Form_Bridge_Fixture
            (RecordID, ProductKey, PriceMinor, Active, OptionsJSON)
         VALUES (?, \'shirt<&\', 2400, 1, ?)',
        'is',
        [$targetId, $options]
    );
    $password = password_hash('AddonFormBridge-2026!', PASSWORD_DEFAULT);
    red_addon_admin_form_bridge_test_execute(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_form_bridge', ?, 'Admin',
            'FormBridge', 'webmaster', '', '', 'form-bridge@example.test',
            'N', 'to', 'N', 'to')",
        'is',
        [$actorId, $password]
    );
    red_addon_admin_form_bridge_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Roles
            (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES (?, \'owner\', ?)',
        'ii',
        [$actorId, $actorId]
    );
    red_addon_admin_form_bridge_test_execute(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType,
            ManifestSHA256, InventorySHA256, LifecycleState,
            InstalledByAdminRecordID, UpdatedByAdminRecordID
         ) VALUES (?, \'0.1.0\', \'component\', ?, ?, \'enabled\', ?, ?)',
        'sssii',
        [$packageId, str_repeat('c', 64), str_repeat('d', 64), $actorId, $actorId]
    );

    $manifest = red_addon_admin_form_bridge_test_manifest(
        $packageId,
        $toolId,
        $formId,
        $permission
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $missing = red_addon_admin_tool_form_endpoint_context(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId
    );
    red_addon_admin_form_bridge_test_assert(
        empty($missing['ready']) && $missing['reason'] === 'form_unavailable',
        'the editor requires one exact request-local enabled form binding'
    );

    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_bridge_test_context(
            $manifest,
            $packageId,
            $toolId,
            $formId,
            $table,
            false
        );
    $readOnly = red_addon_admin_tool_form_endpoint_context(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId
    );
    red_addon_admin_form_bridge_test_assert(
        empty($readOnly['ready'])
            && $readOnly['reason'] === 'form_unavailable'
            && $calls['loader'] === 0,
        'a schema without the exact optional writer remains non-editable'
    );

    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_form_bridge_test_context(
            $manifest,
            $packageId,
            $toolId,
            $formId,
            $table
        );
    $denied = red_addon_admin_tool_form_endpoint_context(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId
    );
    red_addon_admin_form_bridge_test_assert(
        empty($denied['ready'])
            && $denied['reason'] === 'permission_denied'
            && $calls['loader'] === 0
            && $calls['writer'] === 0,
        'Owner status does not imply the exact package form grant'
    );

    red_addon_admin_form_bridge_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
            (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    red_addon_admin_form_bridge_test_assert(
        red_addon_admin_tool_form_endpoint_request([
            'tool' => $toolId,
            'form' => $formId,
            'targetRecordId' => (string) $targetId,
        ]) === [
            'tool' => $toolId,
            'form' => $formId,
            'targetRecordId' => $targetId,
        ],
        'the edit endpoint accepts only canonical tool, form, and target identity'
    );
    foreach (
        [
            ['tool' => $toolId, 'form' => $formId, 'targetRecordId' => '071'],
            ['tool' => $toolId, 'form' => $formId, 'targetRecordId' => '0'],
            [
                'tool' => $toolId,
                'form' => $formId,
                'targetRecordId' => (string) $targetId,
                'package' => $packageId,
            ],
        ] as $invalidRequest
    ) {
        red_addon_admin_form_bridge_test_assert(
            red_addon_admin_tool_form_endpoint_request($invalidRequest) === null,
            'alternate, zero, and extra edit request fields fail closed'
        );
    }

    $context = red_addon_admin_tool_form_endpoint_context(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId
    );
    red_addon_admin_form_bridge_test_assert(
        $context['ready'] === true
            && $context['package'] === $packageId
            && $context['targetRecordId'] === $targetId
            && red_addon_valid_sha256($context['stateSha256'])
            && $calls['tool'] === 0
            && $calls['loader'] === 1
            && $calls['writer'] === 0,
        'the editor derives one freshly authorized complete current-value context'
    );
    $html = red_addon_admin_tool_form_endpoint_render($context);
    $browserMarkup = $html;
    red_addon_admin_form_bridge_test_assert(
        str_contains($html, 'data-red-addon-admin-form-edit')
            && str_contains($html, '/admin/bin/save_addon_tool_form.php')
            && str_contains($html, '/admin/bin/edit_addon_tool_form.php')
            && str_contains($html, 'data-current-state-sha256="')
            && str_contains($html, 'value="shirt&lt;&amp;"')
            && str_contains($html, 'value="2400"')
            && str_contains($html, 'value="true" selected')
            && !str_contains($html, '<script')
            && !str_contains($html, 'disabled aria-disabled'),
        'core renders escaped editable scalar controls without package markup'
    );
    red_addon_admin_form_bridge_test_assert(
        substr_count($html, 'data-red-addon-admin-form-collection') >= 3
            && str_contains($html, 'data-red-addon-admin-form-template')
            && str_contains($html, 'data-red-addon-admin-form-add')
            && str_contains($html, 'data-red-addon-admin-form-remove')
            && str_contains($html, 'data-min-items="1"')
            && str_contains($html, 'data-max-items="16"'),
        'nested option/value collections expose bounded core add/remove controls'
    );
    $invalidContext = $context;
    $invalidContext['stateSha256'] = str_repeat('e', 63);
    red_addon_admin_form_bridge_test_assert(
        red_addon_admin_tool_form_endpoint_render($invalidContext)
            === red_addon_admin_tool_form_ui_unavailable(),
        'forged render evidence fails to the static unavailable state'
    );

    $newValues = $context['values'];
    $newValues['price-minor'] = 2700;
    $newValues['active'] = false;
    $newValues['options'][] = [
        'key' => 'color',
        'values' => [['label' => 'Blue']],
    ];
    $rawBody = red_addon_admin_form_bridge_test_encode([
        'tool' => $toolId,
        'form' => $formId,
        'targetRecordId' => $targetId,
        'currentStateSha256' => $context['stateSha256'],
        'values' => $newValues,
    ]);
    $saved = red_addon_admin_tool_form_save_dispatch(
        $connection,
        $rawBody,
        $actorId
    );
    red_addon_admin_form_bridge_test_assert(
        $saved === [
            'httpStatus' => 200,
            'body' => ['ok' => true, 'status' => 'saved'],
        ]
            && $calls['writer'] === 1
            && red_addon_admin_form_bridge_test_values(
                $connection,
                $targetId
            ) === $newValues,
        'the bridge performs one exact atomic writer save and returns a bounded outcome'
    );
    red_addon_admin_form_bridge_test_assert(
        (int) red_addon_admin_form_bridge_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID=? AND EventName='addon.form.saved'",
            's',
            [$packageId]
        ) === 1
            && !str_contains(json_encode($saved), 'price-minor')
            && !str_contains(json_encode($saved), 'planSha256')
            && !str_contains(json_encode($saved), $context['stateSha256']),
        'the public Save response and atomic audit remain value and evidence free'
    );

    $stale = red_addon_admin_tool_form_save_dispatch(
        $connection,
        $rawBody,
        $actorId
    );
    red_addon_admin_form_bridge_test_assert(
        $stale === [
            'httpStatus' => 409,
            'body' => ['ok' => false, 'reason' => 'state_conflict'],
        ]
            && $calls['writer'] === 1,
        'replayed browser state is refused before a second writer invocation'
    );

    $reloaded = red_addon_admin_tool_form_endpoint_context(
        $connection,
        $toolId,
        $formId,
        $targetId,
        $actorId
    );
    $unchangedBody = red_addon_admin_form_bridge_test_encode([
        'tool' => $toolId,
        'form' => $formId,
        'targetRecordId' => $targetId,
        'currentStateSha256' => $reloaded['stateSha256'],
        'values' => $reloaded['values'],
    ]);
    $unchanged = red_addon_admin_tool_form_save_dispatch(
        $connection,
        $unchangedBody,
        $actorId
    );
    red_addon_admin_form_bridge_test_assert(
        $unchanged === [
            'httpStatus' => 200,
            'body' => ['ok' => true, 'status' => 'unchanged'],
        ]
            && $calls['writer'] === 1
            && (int) red_addon_admin_form_bridge_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID=? AND EventName='addon.form.saved'",
                's',
                [$packageId]
            ) === 1,
        'unchanged browser submissions invoke neither writer nor audit'
    );

    red_addon_admin_form_bridge_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $revoked = red_addon_admin_tool_form_save_dispatch(
        $connection,
        $unchangedBody,
        $actorId
    );
    red_addon_admin_form_bridge_test_assert(
        $revoked === [
            'httpStatus' => 403,
            'body' => ['ok' => false, 'reason' => 'permission_denied'],
        ]
            && $calls['writer'] === 1,
        'permission revocation applies on the next browser Save decision'
    );

    $editSource = file_get_contents(
        $projectRoot . '/admin/bin/edit_addon_tool_form.php'
    );
    $saveSource = file_get_contents(
        $projectRoot . '/admin/bin/save_addon_tool_form.php'
    );
    $validationSource = file_get_contents(
        $projectRoot . '/admin/bin/validate_addon_tool_form.php'
    );
    red_addon_admin_form_bridge_test_assert(
        is_string($editSource)
            && strpos($editSource, "red_require_admin(true);")
                < strpos($editSource, 'endpoint_request($_POST)')
            && strpos($editSource, "REQUEST_METHOD")
                < strpos($editSource, "red_require_admin(true);")
            && str_contains($editSource, 'runtime_request_bootstrap')
            && str_contains($editSource, 'endpoint_context')
            && str_contains($editSource, 'endpoint_render'),
        'the edit endpoint enforces method, session, and CSRF before exact input'
    );
    red_addon_admin_form_bridge_test_assert(
        is_string($saveSource)
            && strpos($saveSource, "red_require_admin(true);")
                < strpos($saveSource, "fopen('php://input'")
            && strpos($saveSource, "REQUEST_METHOD")
                < strpos($saveSource, "red_require_admin(true);")
            && str_contains($saveSource, 'submission_read_body')
            && str_contains($saveSource, 'form_save_dispatch')
            && is_string($validationSource)
            && !str_contains($validationSource, 'form_save_dispatch')
            && !str_contains($validationSource, 'form_write'),
        'the Save endpoint alone bridges authenticated canonical JSON to the writer'
    );

    $scriptSource = file_get_contents(
        $projectRoot . '/admin/assets/js/addon-admin-tool-form.js'
    );
    red_addon_admin_form_bridge_test_assert(
        is_string($scriptSource)
            && str_contains($scriptSource, "'Content-Type': 'application/json'")
            && str_contains($scriptSource, "'X-CSRF-Token': csrfToken()")
            && str_contains($scriptSource, 'JSON.stringify(payload)')
            && str_contains($scriptSource, 'objectValues(root)')
            && str_contains($scriptSource, 'Number.isSafeInteger')
            && str_contains($scriptSource, 'ensureMinimumCollections')
            && str_contains($scriptSource, 'data-red-addon-admin-form-add')
            && str_contains($scriptSource, 'data-red-addon-admin-form-remove')
            && !str_contains($scriptSource, 'eval('),
        'the core controller serializes typed nested values and enforces collection bounds'
    );
    $navigationSource = file_get_contents($projectRoot . '/admin/mainnav.php');
    $cssSource = file_get_contents($projectRoot . '/admin/assets/css/cp.css');
    red_addon_admin_form_bridge_test_assert(
        is_string($navigationSource)
            && str_contains($navigationSource, 'addon-admin-tool-form.js')
            && is_string($cssSource)
            && str_contains(
                $cssSource,
                '#advanced .red-admin-addon-tool-form--editable'
            )
            && str_contains($cssSource, '@media (max-width: 720px)'),
        'the authenticated shell loads scoped responsive core form assets'
    );

    red_addon_admin_form_bridge_test_cleanup(
        $connection,
        $packageId,
        $actorId
    );
    $residual = (int) red_addon_admin_form_bridge_test_scalar(
        $connection,
        'SELECT
            (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=?) +
            (SELECT COUNT(*) FROM RED_Admin_Roles WHERE AdminRecordID=?) +
            (SELECT COUNT(*) FROM RED_Admin_Capabilities WHERE AdminRecordID=?) +
            (SELECT COUNT(*) FROM RED_Addon_Installations WHERE PackageID=?) +
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log WHERE PackageID=?)',
        'iiiss',
        [$actorId, $actorId, $actorId, $packageId, $packageId]
    );
    $tableExists = (int) red_addon_admin_form_bridge_test_scalar(
        $connection,
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema=DATABASE()
           AND table_name=\'RED_Addon_Admin_Form_Bridge_Fixture\''
    );
    red_addon_admin_form_bridge_test_assert(
        $residual === 0 && $tableExists === 0,
        'the Save-bridge administrator, grant, package, audit, and table clean up exactly'
    );

    $fixturePath = getenv('RED_ADDON_FORM_BRIDGE_BROWSER_FIXTURE');
    if (is_string($fixturePath)
        && preg_match(
            '#\A/private/tmp/redcms-addon-form-bridge-[A-Za-z0-9_-]+\.html\z#D',
            $fixturePath
        ) === 1
        && $browserMarkup !== ''
    ) {
        file_put_contents(
            $fixturePath,
            '<!doctype html><html><head><meta charset="utf-8"><title>Form bridge fixture</title></head>'
                . '<body><main id="advanced">' . $browserMarkup
                . '</main></body></html>'
        );
    }

    echo 'Administrator form Save-bridge checks passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_admin_form_bridge_test_cleanup(
        $connection,
        $packageId,
        $actorId
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $db->close();
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
$db->close();

?>
