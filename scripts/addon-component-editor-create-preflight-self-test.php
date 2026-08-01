<?php
/**
 * Disposable checks for read-only add-on component creation preflight.
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
require_once $projectRoot
    . '/includes/addon_component_editor_create_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_editor_create|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Component creation preflight self-test refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$adminRecordId = 2147000975;
$contentRecordId = 2147000976;
$packageId = 'redcms.editor-create-fixture';
$componentId = 'redcms.editor-create-fixture/item';
$createPermission = 'fixture.editor-create.create';
$viewPermission = 'fixture.editor-create.view';
$packageTable = 'RED_Addon_Component_Editor_Create_Fixture';
$creatorCalls = 0;
$loaderCalls = 0;
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_editor_create_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_editor_create_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_editor_create_test_cleanup(
    $connection,
    $adminRecordId,
    $contentRecordId,
    $packageId,
    $packageTable
) {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    try {
        mysqli_query(
            $connection,
            'DELETE FROM RED_Page_SEO WHERE OwnerRecordID=' . (int) $contentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Component_Revisions WHERE ContentRecordID='
                . (int) $contentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Content_Revisions WHERE ContentRecordID='
                . (int) $contentRecordId
        );
        mysqli_query(
            $connection,
            'DROP TABLE IF EXISTS `' . $packageTable . '`'
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID=' . (int) $contentRecordId
        );
        foreach (
            [
                'RED_Addon_Activity_Log',
                'RED_Addon_Migrations',
                'RED_Addon_Installations',
            ] as $table
        ) {
            $statement = mysqli_prepare(
                $connection,
                'DELETE FROM ' . $table . ' WHERE PackageID=?'
            );
            if ($statement) {
                mysqli_stmt_bind_param($statement, 's', $packageId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            }
        }
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
                . (int) $adminRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID='
                . (int) $adminRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=' . (int) $adminRecordId
        );
    } catch (Throwable $throwable) {
        error_log(
            'Component creation preflight cleanup failed: '
                . $throwable->getMessage()
        );
    }
}

function red_addon_editor_create_test_grant(
    $connection,
    $adminRecordId,
    $permission
) {
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'isi',
        $adminRecordId,
        $permission,
        $adminRecordId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

function red_addon_editor_create_test_manifest(
    $packageId,
    $componentId,
    $createPermission,
    $viewPermission
) {
    return [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Component Creation Preflight Fixture',
        'description' => 'Disposable read-only creation preflight fixture.',
        'version' => '1.0.0',
        'type' => 'component',
        'compatibility' => ['cms' => '>=5.1 <6.0', 'php' => '>=8.2 <9.0'],
        'provides' => [
            'components' => [$componentId],
            'services' => [],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$createPermission, $viewPermission],
        'componentEditors' => [[
            'component' => $componentId,
            'label' => 'Create fixture',
            'description' => 'Validate a package-owned creation plan.',
            'icon' => 'package',
            'permissions' => [
                'create' => $createPermission,
                'view' => $viewPermission,
                'edit' => $createPermission,
                'delete' => $createPermission,
                'publish' => $createPermission,
                'restore' => $createPermission,
            ],
            'fields' => [
                [
                    'key' => 'title',
                    'label' => 'Title',
                    'type' => 'text',
                    'required' => true,
                    'maxLength' => 120,
                ],
                [
                    'key' => 'quantity',
                    'label' => 'Quantity',
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 0,
                    'maximum' => 100,
                ],
            ],
        ]],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => ['entrypoint' => 'addon.php', 'files' => []],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => true,
        ],
    ];
}

function red_addon_editor_create_test_context(
    array $manifest,
    $packageId,
    $componentId,
    $packageTable,
    &$creatorCalls,
    &$loaderCalls,
    $withCreator = true
) {
    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerComponent(
        $componentId,
        static function (array $context): array {
            return ['title' => 'Fixture', 'summary' => 'Fixture'];
        }
    );
    $registry->registerComponentDataLoader(
        $componentId,
        static function () use (&$loaderCalls): array {
            $loaderCalls++;
            return ['title' => 'Unexpected', 'quantity' => 1];
        }
    );
    if ($withCreator) {
        $registry->registerComponentDataCreator(
            $componentId,
            static function () use (&$creatorCalls): bool {
                $creatorCalls++;
                return true;
            },
            [$packageTable]
        );
    }
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );
}

try {
    red_addon_editor_create_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        $packageTable
    );
    $manifest = red_addon_editor_create_test_manifest(
        $packageId,
        $componentId,
        $createPermission,
        $viewPermission
    );

    $undeclared = $manifest;
    unset($undeclared['componentEditors']);
    $registry = new RED_Addon_Runtime_Registry($packageId, $undeclared);
    try {
        $registry->registerComponentDataCreator(
            $componentId,
            static function (): bool {
                return true;
            },
            [$packageTable]
        );
        red_addon_editor_create_test_assert(false, 'undeclared creator must fail');
    } catch (LogicException $exception) {
        red_addon_editor_create_test_assert(
            str_contains($exception->getMessage(), 'undeclared'),
            'only a declared component editor may bind a creator'
        );
    }

    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    try {
        $registry->registerComponentDataCreator(
            $componentId,
            static function (): bool {
                return true;
            },
            ['RED_Addon_Component_Revisions']
        );
        red_addon_editor_create_test_assert(false, 'core creator table must fail');
    } catch (LogicException $exception) {
        red_addon_editor_create_test_assert(
            str_contains($exception->getMessage(), 'table'),
            'creator metadata accepts only package tables'
        );
    }

    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerComponentDataCreator(
        $componentId,
        static function (): bool {
            return true;
        },
        [$packageTable]
    );
    try {
        $registry->registerComponentDataCreator(
            $componentId,
            static function (): bool {
                return true;
            },
            [$packageTable]
        );
        red_addon_editor_create_test_assert(false, 'duplicate creator must fail');
    } catch (LogicException $exception) {
        red_addon_editor_create_test_assert(
            str_contains($exception->getMessage(), 'duplicated'),
            'one editor may bind at most one creator'
        );
    }

    mysqli_query(
        $connection,
        'CREATE TABLE `' . $packageTable . '` ('
            . '`ContentRecordID` int unsigned NOT NULL PRIMARY KEY'
            . ') ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci'
    );
    $passwordHash = password_hash('EditorCreate-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_editor_create', ?, 'Admin', 'CreatePlan', 'guest',
            '', '', 'editor-create@example.test', 'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $adminRecordId, $passwordHash);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $createPermission
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $viewPermission
    );
    $manifestHash = hash('sha256', json_encode($manifest));
    $inventoryHash = hash('sha256', 'create-preflight-fixture');
    $version = $manifest['version'];
    $type = $manifest['type'];
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, \'enabled\', ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'sssssii',
        $packageId,
        $version,
        $type,
        $manifestHash,
        $inventoryHash,
        $adminRecordId,
        $adminRecordId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $context = red_addon_editor_create_test_context(
        $manifest,
        $packageId,
        $componentId,
        $packageTable,
        $creatorCalls,
        $loaderCalls
    );
    red_addon_runtime_set_request_context($context);
    $contract = red_theme_active_layout_contract($connection, $projectRoot);
    $layout = (string) array_key_first($contract['catalog']);
    red_addon_editor_create_test_assert(
        $layout !== ''
            && red_admin_area_layout_definition($connection, $layout) !== null,
        'the fixture selects one active-theme layout'
    );
    $parentMetadata = [
        'title' => 'Inactive creation fixture',
        'layout' => $layout,
        'language' => 'sp',
    ];
    $submittedValues = ['title' => 'Package row', 'quantity' => '5'];

    $unsupported = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    );
    red_addon_editor_create_test_assert(
        empty($unsupported['ready'])
            && $unsupported['reason'] === 'transaction_unsupported'
            && $creatorCalls === 0
            && $loaderCalls === 0,
        'preflight refuses a non-transactional package table without callbacks'
    );
    mysqli_query(
        $connection,
        'ALTER TABLE `' . $packageTable . '` ENGINE=InnoDB'
    );

    $fingerprintBefore = red_addon_editor_create_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':',
            (SELECT COUNT(*) FROM RED_Articles WHERE RecordID=$contentRecordId),
            (SELECT COUNT(*) FROM `$packageTable`),
            (SELECT COUNT(*) FROM RED_Content_Revisions
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Page_SEO
             WHERE OwnerRecordID=$contentRecordId))"
    );
    $plan = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    );
    $repeated = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    );
    $fingerprintAfter = red_addon_editor_create_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':',
            (SELECT COUNT(*) FROM RED_Articles WHERE RecordID=$contentRecordId),
            (SELECT COUNT(*) FROM `$packageTable`),
            (SELECT COUNT(*) FROM RED_Content_Revisions
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Page_SEO
             WHERE OwnerRecordID=$contentRecordId))"
    );
    red_addon_editor_create_test_assert(
        !empty($plan['ready'])
            && $plan['reason'] === 'ready'
            && $plan['package'] === $packageId
            && $plan['permission'] === $createPermission,
        'an exact enabled owner and create grant produce a closed plan'
    );
    red_addon_editor_create_test_assert(
        $plan['parentValues']['RecordID'] === $contentRecordId
            && $plan['parentValues']['Component'] === $componentId
            && $plan['parentValues']['Active'] === 'N'
            && $plan['parentValues']['PagePosition'] === 0
            && $plan['parentValues']['HomePosition'] === 0
            && $plan['parentValues']['SectionPosition'] === 0
            && $plan['parentValues']['CategoryPosition'] === 0
            && $plan['parentValues']['SubCategoryPosition'] === 0
            && $plan['parentValues']['Alias'] === '',
        'the core parent is fixed inactive, hidden, and unrouted'
    );
    red_addon_editor_create_test_assert(
        $plan['values'] === ['title' => 'Package row', 'quantity' => 5]
            && $plan['transactionTables'] === [$packageTable],
        'package values and transaction tables are normalized exactly'
    );
    red_addon_editor_create_test_assert(
        preg_match('/\A[a-f0-9]{64}\z/', $plan['planHash']) === 1
            && $plan === $repeated,
        'the complete creation plan is deterministic'
    );
    red_addon_editor_create_test_assert(
        $fingerprintBefore === '0:0:0:0:0'
            && $fingerprintAfter === $fingerprintBefore
            && $creatorCalls === 0
            && $loaderCalls === 0,
        'preflight performs no writes and invokes no package callback'
    );

    $invalidParent = $parentMetadata;
    $invalidParent['active'] = 'Y';
    $refused = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $invalidParent,
        $submittedValues
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready'])
            && $refused['reason'] === 'invalid_parent_values',
        'unknown or caller-selected parent state is refused'
    );
    $refused = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        ['title' => 'Package row', 'quantity' => '101']
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready']) && $refused['reason'] === 'invalid_values',
        'schema-invalid package values are refused'
    );

    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId . " AND Capability='"
            . mysqli_real_escape_string($connection, $createPermission) . "'"
    );
    $refused = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready']) && $refused['reason'] === 'permission_denied',
        'a fresh exact create grant is mandatory'
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $createPermission
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='installed_disabled'
         WHERE PackageID='"
            . mysqli_real_escape_string($connection, $packageId) . "'"
    );
    $refused = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready']) && $refused['reason'] === 'package_not_enabled',
        'disabled packages cannot produce creation plans'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='"
            . mysqli_real_escape_string($connection, $packageId) . "'"
    );

    $withoutCreator = red_addon_editor_create_test_context(
        $manifest,
        $packageId,
        $componentId,
        $packageTable,
        $creatorCalls,
        $loaderCalls,
        false
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_runtime_set_request_context($withoutCreator);
    $refused = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready']) && $refused['reason'] === 'creator_unavailable',
        'an exact creator registration is mandatory'
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_runtime_set_request_context($context);

    $mismatched = $manifest;
    $mismatched['version'] = '1.0.1';
    $refused = red_addon_component_editor_create_preflight(
        $connection,
        $mismatched,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready']) && $refused['reason'] === 'manifest_mismatch',
        'caller and enabled runtime manifests must match exactly'
    );

    $parent = $plan['parentValues'];
    $columns = array_keys($parent);
    $values = [];
    foreach (array_values($parent) as $value) {
        $values[] = "'" . mysqli_real_escape_string(
            $connection,
            (string) $value
        ) . "'";
    }
    mysqli_query(
        $connection,
        'INSERT INTO RED_Articles (`' . implode('`,`', $columns) . '`) VALUES ('
            . implode(',', $values) . ')'
    );
    $refused = red_addon_component_editor_create_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready']) && $refused['reason'] === 'record_id_unavailable',
        'any existing core parent evidence refuses candidate reuse'
    );
    red_addon_editor_create_test_assert(
        $creatorCalls === 0 && $loaderCalls === 0,
        'every refusal path remains non-executing'
    );

    red_addon_editor_create_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        $packageTable
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Articles WHERE RecordID=$contentRecordId),
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='"
                    . mysqli_real_escape_string($connection, $packageId) . "'),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$adminRecordId),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"
                    . $packageTable . "'))"
        ) === '0:0:0:0',
        'the disposable creation fixture cleans all database state'
    );
} catch (Throwable $throwable) {
    red_addon_editor_create_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        $packageTable
    );
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

fwrite(
    STDOUT,
    'Add-on component creation preflight self-test passed ('
        . $assertions . " assertions).\n"
);

?>
