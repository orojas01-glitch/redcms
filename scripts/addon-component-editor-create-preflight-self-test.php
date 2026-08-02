<?php
/**
 * Disposable checks for add-on component creation and parent metadata.
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
require_once $projectRoot
    . '/includes/addon_component_editor_parent_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_editor_delete_helpers.php';

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
$editPermission = 'fixture.editor-create.edit';
$deletePermission = 'fixture.editor-create.delete';
$packageTable = 'RED_Addon_Component_Editor_Create_Fixture';
$creatorCalls = 0;
$loaderCalls = 0;
$deleterCalls = 0;
$creatorMode = 'valid';
$deleterMode = 'valid';
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

function red_addon_editor_create_test_record_fingerprint(
    $connection,
    $contentRecordId,
    $packageTable
) {
    return red_addon_editor_create_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':',
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=$contentRecordId),
            (SELECT COUNT(*) FROM `$packageTable`
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Content_Revisions
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Page_SEO
             WHERE OwnerRecordID=$contentRecordId))"
    );
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
        foreach (
            [
                [
                    'RED_Addon_Component_Revisions',
                    'redcms_addon_component_create_revision_fail',
                ],
                [
                    'RED_Content_Revisions',
                    'redcms_component_create_parent_revision_fail',
                ],
                [
                    'RED_Content_Revisions',
                    'redcms_component_parent_update_revision_fail',
                ],
                [
                    'RED_Addon_Component_Revisions',
                    'redcms_component_delete_package_revision_fail',
                ],
                [
                    'RED_Content_Revisions',
                    'redcms_component_delete_parent_revision_fail',
                ],
            ] as [$table, $constraint]
        ) {
            if (red_addon_editor_create_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA=DATABASE()
                   AND TABLE_NAME='$table'
                   AND CONSTRAINT_NAME='$constraint'"
            ) === '1') {
                mysqli_query(
                    $connection,
                    'ALTER TABLE `' . $table . '` DROP CHECK `'
                        . $constraint . '`'
                );
            }
        }
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
    $viewPermission,
    $editPermission,
    $deletePermission
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
        'permissions' => [
            $createPermission,
            $viewPermission,
            $editPermission,
            $deletePermission,
        ],
        'componentEditors' => [[
            'component' => $componentId,
            'label' => 'Create fixture',
            'description' => 'Validate a package-owned creation plan.',
            'icon' => 'package',
            'permissions' => [
                'create' => $createPermission,
                'view' => $viewPermission,
                'edit' => $editPermission,
                'delete' => $deletePermission,
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
    &$deleterCalls,
    &$creatorMode,
    &$deleterMode,
    $withCreator = true,
    $withDeleter = true
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
        static function ($connection, array $context) use (
            &$loaderCalls,
            $packageTable
        ): array {
            $loaderCalls++;
            $statement = mysqli_prepare(
                $connection,
                'SELECT Title, Quantity FROM `' . $packageTable
                    . '` WHERE ContentRecordID=? LIMIT 1'
            );
            mysqli_stmt_bind_param(
                $statement,
                'i',
                $context['contentRecordId']
            );
            mysqli_stmt_execute($statement);
            $queryResult = mysqli_stmt_get_result($statement);
            $row = $queryResult ? mysqli_fetch_assoc($queryResult) : null;
            mysqli_stmt_close($statement);
            if (!is_array($row)) {
                throw new RuntimeException('missing creation fixture data');
            }
            return [
                'title' => $row['Title'],
                'quantity' => (int) $row['Quantity'],
            ];
        }
    );
    if ($withCreator) {
        $registry->registerComponentDataCreator(
            $componentId,
            static function (
                $connection,
                array $context,
                array $values
            ) use (&$creatorCalls, &$creatorMode, $packageTable): bool {
                $creatorCalls++;
                if (array_keys($context) !== [
                        'component',
                        'contentRecordId',
                        'actorRecordId',
                        'planHash',
                    ]
                    || array_keys($values) !== ['title', 'quantity']
                ) {
                    throw new RuntimeException('unexpected creation context');
                }
                $quantity = $creatorMode === 'partial'
                    ? $values['quantity'] + 1
                    : $values['quantity'];
                $statement = mysqli_prepare(
                    $connection,
                    'INSERT INTO `' . $packageTable
                        . '` (ContentRecordID, Title, Quantity) VALUES (?, ?, ?)'
                );
                mysqli_stmt_bind_param(
                    $statement,
                    'isi',
                    $context['contentRecordId'],
                    $values['title'],
                    $quantity
                );
                $inserted = mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
                if (!$inserted) {
                    return false;
                }
                if ($creatorMode === 'emit') {
                    echo 'unsafe-creator-output';
                }
                if ($creatorMode === 'throw') {
                    throw new RuntimeException('private creator failure');
                }
                if ($creatorMode === 'nested') {
                    ob_start();
                }
                if ($creatorMode === 'false') {
                    return false;
                }
                return true;
            },
            [$packageTable]
        );
    }
    if ($withDeleter) {
        $registry->registerComponentDataDeleter(
            $componentId,
            static function ($connection, array $context) use (
                &$deleterCalls,
                &$deleterMode,
                $packageTable
            ): bool {
                $deleterCalls++;
                if (array_keys($context) !== [
                    'component',
                    'contentRecordId',
                    'actorRecordId',
                    'planHash',
                ]) {
                    throw new RuntimeException('unexpected deletion context');
                }
                if ($deleterMode !== 'partial') {
                    $statement = mysqli_prepare(
                        $connection,
                        'DELETE FROM `' . $packageTable
                            . '` WHERE ContentRecordID=?'
                    );
                    mysqli_stmt_bind_param(
                        $statement,
                        'i',
                        $context['contentRecordId']
                    );
                    $deleted = mysqli_stmt_execute($statement)
                        && mysqli_stmt_affected_rows($statement) === 1;
                    mysqli_stmt_close($statement);
                    if (!$deleted) {
                        return false;
                    }
                }
                if ($deleterMode === 'emit') {
                    echo 'unsafe-deleter-output';
                }
                if ($deleterMode === 'throw') {
                    throw new RuntimeException('private deleter failure');
                }
                if ($deleterMode === 'nested') {
                    ob_start();
                }
                if ($deleterMode === 'false') {
                    return false;
                }
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
        $viewPermission,
        $editPermission,
        $deletePermission
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

    $registry = new RED_Addon_Runtime_Registry($packageId, $undeclared);
    try {
        $registry->registerComponentDataDeleter(
            $componentId,
            static function (): bool {
                return true;
            },
            [$packageTable]
        );
        red_addon_editor_create_test_assert(false, 'undeclared deleter must fail');
    } catch (LogicException $exception) {
        red_addon_editor_create_test_assert(
            str_contains($exception->getMessage(), 'undeclared'),
            'only a declared component editor may bind a deleter'
        );
    }

    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    try {
        $registry->registerComponentDataDeleter(
            $componentId,
            static function (): bool {
                return true;
            },
            ['RED_Addon_Component_Revisions']
        );
        red_addon_editor_create_test_assert(false, 'core deleter table must fail');
    } catch (LogicException $exception) {
        red_addon_editor_create_test_assert(
            str_contains($exception->getMessage(), 'table'),
            'deleter metadata accepts only package tables'
        );
    }

    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $registry->registerComponentDataDeleter(
        $componentId,
        static function (): bool {
            return true;
        },
        [$packageTable]
    );
    try {
        $registry->registerComponentDataDeleter(
            $componentId,
            static function (): bool {
                return true;
            },
            [$packageTable]
        );
        red_addon_editor_create_test_assert(false, 'duplicate deleter must fail');
    } catch (LogicException $exception) {
        red_addon_editor_create_test_assert(
            str_contains($exception->getMessage(), 'duplicated'),
            'one editor may bind at most one deleter'
        );
    }

    mysqli_query(
        $connection,
        'CREATE TABLE `' . $packageTable . '` ('
            . '`ContentRecordID` int unsigned NOT NULL PRIMARY KEY,'
            . '`Title` varchar(120) NOT NULL,'
            . '`Quantity` int NOT NULL'
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
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $editPermission
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $deletePermission
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
        $loaderCalls,
        $deleterCalls,
        $creatorMode,
        $deleterMode
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
    mysqli_query(
        $connection,
        'ALTER TABLE `' . $packageTable . '` ADD CONSTRAINT '
            . '`fk_red_addon_editor_create_parent` FOREIGN KEY '
            . '(`ContentRecordID`) REFERENCES `RED_Articles` (`RecordID`) '
            . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
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
        $deleterCalls,
        $creatorMode,
        $deleterMode,
        false,
        true
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

    $refused = red_addon_component_editor_create_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues,
        'invalid'
    );
    red_addon_editor_create_test_assert(
        empty($refused['created'])
            && $refused['reason'] === 'invalid_plan_hash'
            && $creatorCalls === 0
            && $loaderCalls === 0,
        'the atomic runner requires one exact SHA-256 plan hash'
    );

    $refused = red_addon_component_editor_create_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues,
        str_repeat('0', 64)
    );
    red_addon_editor_create_test_assert(
        empty($refused['created'])
            && $refused['reason'] === 'stale_plan'
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === '0:0:0:0:0',
        'a substituted but well-formed plan is refused before execution'
    );

    mysqli_begin_transaction($connection);
    $refused = red_addon_component_editor_create_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues,
        $plan['planHash']
    );
    mysqli_rollback($connection);
    red_addon_editor_create_test_assert(
        empty($refused['created'])
            && $refused['reason'] === 'transaction_already_active',
        'the runner refuses a caller-owned transaction'
    );

    foreach (
        [
            'emit' => 'creator_failed',
            'throw' => 'creator_failed',
            'nested' => 'creator_failed',
            'false' => 'creator_failed',
            'partial' => 'package_postcondition_failed',
        ] as $mode => $expectedReason
    ) {
        $creatorMode = $mode;
        $refused = red_addon_component_editor_create_values(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            $parentMetadata,
            $submittedValues,
            $plan['planHash']
        );
        $modeFingerprint = red_addon_editor_create_test_record_fingerprint(
            $connection,
            $contentRecordId,
            $packageTable
        );
        red_addon_editor_create_test_assert(
            empty($refused['created'])
                && $refused['reason'] === $expectedReason
                && $modeFingerprint === '0:0:0:0:0',
            'creator mode ' . $mode
                . ' rolls back every parent/package/revision row; reason='
                . $refused['reason'] . '; fingerprint=' . $modeFingerprint
        );
    }

    $creatorMode = 'valid';
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Component_Revisions ADD CONSTRAINT '
            . '`redcms_addon_component_create_revision_fail` CHECK '
            . '(`ContentRecordID` <> ' . $contentRecordId . ')'
    );
    $refused = red_addon_component_editor_create_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues,
        $plan['planHash']
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Component_Revisions DROP CHECK '
            . '`redcms_addon_component_create_revision_fail`'
    );
    red_addon_editor_create_test_assert(
        empty($refused['created'])
            && $refused['reason'] === 'package_revision_failed'
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === '0:0:0:0:0',
        'a forced package-ledger failure rolls back parent and package creation'
    );

    mysqli_query(
        $connection,
        'ALTER TABLE RED_Content_Revisions ADD CONSTRAINT '
            . '`redcms_component_create_parent_revision_fail` CHECK '
            . '(`ContentRecordID` <> ' . $contentRecordId . ')'
    );
    $refused = red_addon_component_editor_create_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues,
        $plan['planHash']
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Content_Revisions DROP CHECK '
            . '`redcms_component_create_parent_revision_fail`'
    );
    red_addon_editor_create_test_assert(
        empty($refused['created'])
            && $refused['reason'] === 'parent_revision_failed'
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === '0:0:0:0:0',
        'a forced parent-ledger failure rolls back package revision and both rows'
    );

    $created = red_addon_component_editor_create_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues,
        $plan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($created['created'])
            && $created['reason'] === 'created'
            && $created['parentValues'] === $plan['parentValues']
            && $created['values'] === $plan['values']
            && $created['planHash'] === $plan['planHash']
            && preg_match('/\A[a-f0-9]{64}\z/', $created['stateHash']) === 1
            && $created['parentRevisionId'] > 0
            && $created['packageRevisionId'] > 0,
        'the exact plan creates one inactive parent, package row, and both revisions atomically'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_record_fingerprint(
            $connection,
            $contentRecordId,
            $packageTable
        ) === '1:1:1:1:0'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', a.Active, a.PagePosition,
                    IF(a.Alias='', 'empty', a.Alias), p.Title, p.Quantity,
                    cr.Operation, ar.Operation)
                 FROM RED_Articles a
                 INNER JOIN `$packageTable` p
                   ON p.ContentRecordID=a.RecordID
                 INNER JOIN RED_Content_Revisions cr
                   ON cr.ContentRecordID=a.RecordID
                 INNER JOIN RED_Addon_Component_Revisions ar
                   ON ar.ContentRecordID=a.RecordID
                 WHERE a.RecordID=$contentRecordId"
            ) === 'N:0:empty:Package row:5:create:baseline',
        'committed state remains hidden and has exact create/baseline evidence'
    );

    $parentState = red_addon_component_editor_parent_state(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_create_test_assert(
        !empty($parentState['loaded'])
            && $parentState['reason'] === 'loaded'
            && $parentState['viewPermission'] === $viewPermission
            && $parentState['parentValues'] === $parentMetadata
            && preg_match(
                '/\A[a-f0-9]{64}\z/',
                $parentState['stateHash']
            ) === 1
            && $parentState['stateHash'] ===
                red_addon_editor_create_test_scalar(
                    $connection,
                    "SELECT SnapshotHash FROM RED_Content_Revisions
                     WHERE ContentRecordID=$contentRecordId
                     ORDER BY RevisionNumber DESC LIMIT 1"
                )
            && $parentState['packageStateHash'] === $created['stateHash'],
        'read-only parent state requires the exact view grant, shell, package row, and current revision'
    );

    $deleteFingerprint = red_addon_editor_create_test_record_fingerprint(
        $connection,
        $contentRecordId,
        $packageTable
    );
    $deletePlan = red_addon_component_editor_delete_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        $parentState['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        !empty($deletePlan['ready'])
            && $deletePlan['reason'] === 'ready'
            && $deletePlan['viewPermission'] === $viewPermission
            && $deletePlan['deletePermission'] === $deletePermission
            && $deletePlan['parentStateHash'] === $parentState['stateHash']
            && $deletePlan['packageStateHash'] ===
                $parentState['packageStateHash']
            && $deletePlan['packageRevisionId'] ===
                $created['packageRevisionId']
            && $deletePlan['transactionTables'] === [$packageTable]
            && preg_match(
                '/\A[a-f0-9]{64}\z/',
                $deletePlan['planHash']
            ) === 1
            && $deleterCalls === 0
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === $deleteFingerprint,
        'delete preflight binds exact permission, shell, state, revision, and tables without executing or writing'
    );
    $repeatDeletePlan = red_addon_component_editor_delete_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        $parentState['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        !empty($repeatDeletePlan['ready'])
            && $repeatDeletePlan['planHash'] === $deletePlan['planHash']
            && $deleterCalls === 0
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === $deleteFingerprint,
        'identical delete evidence produces a deterministic read-only plan'
    );

    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId . " AND Capability='"
            . mysqli_real_escape_string($connection, $deletePermission) . "'"
    );
    $loaderCallsBeforeDeleteRefusal = $loaderCalls;
    $refused = red_addon_component_editor_delete_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        $parentState['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready'])
            && $refused['reason'] === 'permission_denied'
            && $loaderCalls === $loaderCallsBeforeDeleteRefusal
            && $deleterCalls === 0,
        'fresh delete permission is required before package loading or deletion'
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $deletePermission
    );

    $refusedParent = red_addon_component_editor_delete_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        hash('sha256', 'stale-parent'),
        $parentState['packageStateHash']
    );
    $refusedPackage = red_addon_component_editor_delete_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        hash('sha256', 'stale-package')
    );
    red_addon_editor_create_test_assert(
        empty($refusedParent['ready'])
            && $refusedParent['reason'] === 'stale_parent_state'
            && empty($refusedPackage['ready'])
            && $refusedPackage['reason'] === 'stale_package_state'
            && $deleterCalls === 0
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === $deleteFingerprint,
        'stale core or package state refuses delete planning without mutation'
    );

    $withoutDeleter = red_addon_editor_create_test_context(
        $manifest,
        $packageId,
        $componentId,
        $packageTable,
        $creatorCalls,
        $loaderCalls,
        $deleterCalls,
        $creatorMode,
        $deleterMode,
        true,
        false
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_runtime_set_request_context($withoutDeleter);
    $refused = red_addon_component_editor_delete_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        $parentState['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready'])
            && $refused['reason'] === 'deleter_unavailable'
            && $deleterCalls === 0,
        'an exact registrar-bound package deleter is mandatory but not invoked'
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_runtime_set_request_context($context);

    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId . " AND Capability='"
            . mysqli_real_escape_string($connection, $viewPermission) . "'"
    );
    $refused = red_addon_component_editor_parent_state(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_create_test_assert(
        empty($refused['loaded'])
            && $refused['reason'] === 'permission_denied',
        'parent state requires a fresh exact view grant before package loading'
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $viewPermission
    );

    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId . " AND Capability='"
            . mysqli_real_escape_string($connection, $editPermission) . "'"
    );
    $loaderCallsBeforeEditRefusal = $loaderCalls;
    $refused = red_addon_component_editor_parent_update(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        [
            'title' => 'Updated inactive parent',
            'layout' => $layout,
            'language' => 'en',
        ]
    );
    red_addon_editor_create_test_assert(
        empty($refused['updated'])
            && $refused['reason'] === 'permission_denied'
            && $loaderCalls === $loaderCallsBeforeEditRefusal,
        'the exact edit grant is required before any package callback'
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $editPermission
    );

    $loaderCallsBeforeInvalid = $loaderCalls;
    $refused = red_addon_component_editor_parent_update(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        [
            'title' => 'Updated inactive parent',
            'layout' => $layout,
            'language' => 'en',
            'active' => 'Y',
        ]
    );
    red_addon_editor_create_test_assert(
        empty($refused['updated'])
            && $refused['reason'] === 'invalid_parent_values'
            && $loaderCalls === $loaderCallsBeforeInvalid,
        'unknown or activation metadata is refused before package loading'
    );

    mysqli_begin_transaction($connection);
    $refused = red_addon_component_editor_parent_update(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        [
            'title' => 'Updated inactive parent',
            'layout' => $layout,
            'language' => 'en',
        ]
    );
    mysqli_rollback($connection);
    red_addon_editor_create_test_assert(
        empty($refused['updated'])
            && $refused['reason'] === 'transaction_already_active',
        'parent metadata refuses a caller-owned transaction'
    );

    $unchanged = red_addon_component_editor_parent_update(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        $parentMetadata
    );
    red_addon_editor_create_test_assert(
        !empty($unchanged['unchanged'])
            && empty($unchanged['updated'])
            && $unchanged['reason'] === 'unchanged'
            && $unchanged['stateHash'] === $parentState['stateHash']
            && $unchanged['revisionId'] === 0
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID=$contentRecordId"
            ) === '1',
        'identical parent metadata commits no change and adds no revision'
    );

    $updatedMetadata = [
        'title' => 'Updated inactive parent',
        'layout' => $layout,
        'language' => 'en',
    ];
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Content_Revisions ADD CONSTRAINT '
            . '`redcms_component_parent_update_revision_fail` CHECK '
            . "(`Operation` <> 'save' OR `ContentRecordID` <> "
            . $contentRecordId . ')'
    );
    $refused = red_addon_component_editor_parent_update(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        $updatedMetadata
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Content_Revisions DROP CHECK '
            . '`redcms_component_parent_update_revision_fail`'
    );
    red_addon_editor_create_test_assert(
        empty($refused['updated'])
            && $refused['reason'] === 'revision_failed'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', Title, Layout, Language, Active,
                    PagePosition, IF(Alias='', 'empty', Alias))
                 FROM RED_Articles WHERE RecordID=$contentRecordId"
            ) === 'Inactive creation fixture:' . $layout . ':sp:N:0:empty'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID=$contentRecordId"
            ) === '1',
        'a forced core revision failure rolls back every parent metadata change'
    );

    $updated = red_addon_component_editor_parent_update(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        $updatedMetadata
    );
    red_addon_editor_create_test_assert(
        !empty($updated['updated'])
            && empty($updated['unchanged'])
            && $updated['reason'] === 'updated'
            && $updated['editPermission'] === $editPermission
            && $updated['parentValues'] === $updatedMetadata
            && $updated['previousStateHash'] === $parentState['stateHash']
            && $updated['stateHash'] !== $parentState['stateHash']
            && $updated['packageStateHash'] === $created['stateHash']
            && $updated['revisionId'] > 0
            && $updated['revisionNumber'] === 2,
        'the exact metadata update commits with a new core state and revision'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':', a.Title, a.Layout, a.Language, a.Active,
                a.PagePosition, IF(a.Alias='', 'empty', a.Alias),
                cr.RevisionNumber, cr.Operation, cr.ActorAdminRecordID,
                (SELECT COUNT(*) FROM RED_Addon_Component_Revisions ar
                 WHERE ar.ContentRecordID=a.RecordID),
                p.Title, p.Quantity)
             FROM RED_Articles a
             INNER JOIN RED_Content_Revisions cr
               ON cr.ContentRecordID=a.RecordID
              AND cr.RevisionNumber=2
             INNER JOIN `$packageTable` p
               ON p.ContentRecordID=a.RecordID
             WHERE a.RecordID=$contentRecordId"
        ) === 'Updated inactive parent:' . $layout
            . ':en:N:0:empty:2:save:' . $adminRecordId
            . ':1:Package row:5',
        'only title, layout, and language change while placement and package data remain exact'
    );

    $refused = red_addon_component_editor_parent_update(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentState['stateHash'],
        $parentMetadata
    );
    red_addon_editor_create_test_assert(
        empty($refused['updated']) && $refused['reason'] === 'stale_state',
        'the pre-update parent state hash is single-use after a change'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Active='Y' WHERE RecordID=$contentRecordId"
    );
    $refused = red_addon_component_editor_parent_state(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Active='N' WHERE RecordID=$contentRecordId"
    );
    red_addon_editor_create_test_assert(
        empty($refused['loaded'])
            && $refused['reason'] === 'parent_state_unsupported',
        'public or non-shell parent state is outside the metadata writer'
    );

    $refused = red_addon_component_editor_create_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $parentMetadata,
        $submittedValues,
        $plan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['created'])
            && $refused['reason'] === 'record_id_unavailable'
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === '1:1:2:1:0',
        'the committed numeric id cannot be reused and remains unchanged'
    );

    $seoValues = array_merge(
        red_seo_empty_values(),
        ['SEO_Title' => 'Disposable component delete metadata']
    );
    $_SESSION['AdminRecordID'] = $adminRecordId;
    $_SESSION['alias'] = 'CreatePlan';
    $seoPrepared = red_seo_save_metadata(
        $connection,
        'article',
        $contentRecordId,
        $seoValues,
        $adminRecordId
    ) && red_admin_content_revision_record_current(
        $connection,
        $contentRecordId,
        'save'
    );
    unset($_SESSION['AdminRecordID'], $_SESSION['alias']);
    red_addon_editor_create_test_assert(
        $seoPrepared
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === '1:1:3:1:1',
        'the disposable delete fixture includes current revision-backed SEO metadata'
    );

    $deleteParentState = red_addon_component_editor_parent_state(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    $deletePlan = red_addon_component_editor_delete_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $deleteParentState['stateHash'],
        $deleteParentState['packageStateHash']
    );
    $deleteFingerprint = red_addon_editor_create_test_record_fingerprint(
        $connection,
        $contentRecordId,
        $packageTable
    );
    red_addon_editor_create_test_assert(
        !empty($deleteParentState['loaded'])
            && !empty($deletePlan['ready'])
            && $deleteFingerprint === '1:1:3:1:1',
        'the updated inactive record produces a fresh executable delete plan'
    );

    $deleterCallsBeforeRefusal = $deleterCalls;
    $refused = red_addon_component_editor_delete_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $deleteParentState['stateHash'],
        $deleteParentState['packageStateHash'],
        hash('sha256', 'stale-delete-plan')
    );
    red_addon_editor_create_test_assert(
        empty($refused['deleted'])
            && $refused['reason'] === 'stale_plan'
            && $deleterCalls === $deleterCallsBeforeRefusal
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === $deleteFingerprint,
        'a stale delete plan is refused without invoking package deletion'
    );

    mysqli_begin_transaction($connection);
    $refused = red_addon_component_editor_delete_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $deleteParentState['stateHash'],
        $deleteParentState['packageStateHash'],
        $deletePlan['planHash']
    );
    mysqli_rollback($connection);
    red_addon_editor_create_test_assert(
        empty($refused['deleted'])
            && $refused['reason'] === 'transaction_already_active'
            && $deleterCalls === $deleterCallsBeforeRefusal,
        'atomic deletion refuses a caller-owned transaction'
    );

    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId . " AND Capability='"
            . mysqli_real_escape_string($connection, $deletePermission) . "'"
    );
    $refused = red_addon_component_editor_delete_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $deleteParentState['stateHash'],
        $deleteParentState['packageStateHash'],
        $deletePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['deleted'])
            && $refused['reason'] === 'permission_denied'
            && $deleterCalls === $deleterCallsBeforeRefusal,
        'the runner rechecks the exact delete grant before package callbacks'
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $deletePermission
    );

    foreach (
        [
            'emit' => 'deleter_failed',
            'throw' => 'deleter_failed',
            'nested' => 'deleter_failed',
            'false' => 'deleter_failed',
            'partial' => 'package_postcondition_failed',
        ] as $mode => $reason
    ) {
        $deleterMode = $mode;
        $refused = red_addon_component_editor_delete_values(
            $connection,
            $manifest,
            $componentId,
            $contentRecordId,
            $adminRecordId,
            $deleteParentState['stateHash'],
            $deleteParentState['packageStateHash'],
            $deletePlan['planHash']
        );
        red_addon_editor_create_test_assert(
            empty($refused['deleted'])
                && $refused['reason'] === $reason
                && red_addon_editor_create_test_record_fingerprint(
                    $connection,
                    $contentRecordId,
                    $packageTable
                ) === $deleteFingerprint,
            "the $mode package deleter fails closed and rolls back all state"
        );
    }
    $deleterMode = 'valid';

    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Component_Revisions ADD CONSTRAINT '
            . '`redcms_component_delete_package_revision_fail` CHECK '
            . "(`Operation` <> 'delete' OR `ContentRecordID` <> "
            . $contentRecordId . ')'
    );
    $refused = red_addon_component_editor_delete_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $deleteParentState['stateHash'],
        $deleteParentState['packageStateHash'],
        $deletePlan['planHash']
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Component_Revisions DROP CHECK '
            . '`redcms_component_delete_package_revision_fail`'
    );
    red_addon_editor_create_test_assert(
        empty($refused['deleted'])
            && $refused['reason'] === 'package_revision_failed'
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === $deleteFingerprint,
        'a forced package delete-revision failure rolls back the operation'
    );

    mysqli_query(
        $connection,
        'ALTER TABLE RED_Content_Revisions ADD CONSTRAINT '
            . '`redcms_component_delete_parent_revision_fail` CHECK '
            . "(`Operation` <> 'delete' OR `ContentRecordID` <> "
            . $contentRecordId . ')'
    );
    $refused = red_addon_component_editor_delete_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $deleteParentState['stateHash'],
        $deleteParentState['packageStateHash'],
        $deletePlan['planHash']
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Content_Revisions DROP CHECK '
            . '`redcms_component_delete_parent_revision_fail`'
    );
    red_addon_editor_create_test_assert(
        empty($refused['deleted'])
            && $refused['reason'] === 'parent_revision_failed'
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === $deleteFingerprint,
        'a forced core delete-revision failure rolls back the package revision'
    );

    $deleted = red_addon_component_editor_delete_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $deleteParentState['stateHash'],
        $deleteParentState['packageStateHash'],
        $deletePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($deleted['deleted'])
            && $deleted['reason'] === 'deleted'
            && $deleted['package'] === $packageId
            && $deleted['viewPermission'] === $viewPermission
            && $deleted['deletePermission'] === $deletePermission
            && $deleted['parentRevisionId'] > 0
            && $deleted['packageRevisionId'] > 0
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === '0:0:4:2:0',
        'the exact plan deletes parent and package rows atomically while retaining both ledgers'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT CONCAT(RevisionNumber, ':', Operation, ':', StateHash)
                 FROM RED_Addon_Component_Revisions
                 WHERE RevisionID=" . (int) $deleted['packageRevisionId'] . "),
                (SELECT CONCAT(RevisionNumber, ':', Operation, ':', SnapshotHash)
                 FROM RED_Content_Revisions
                 WHERE RevisionID=" . (int) $deleted['parentRevisionId'] . '))'
        ) === '2:delete:' . $deleteParentState['packageStateHash']
            . ':4:delete:' . $deleteParentState['stateHash'],
        'the surviving final revisions are immutable delete snapshots of both states'
    );

    $reused = red_addon_component_editor_delete_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $deleteParentState['stateHash'],
        $deleteParentState['packageStateHash'],
        $deletePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($reused['deleted'])
            && $reused['reason'] === 'binding_unavailable'
            && red_addon_editor_create_test_record_fingerprint(
                $connection,
                $contentRecordId,
                $packageTable
            ) === '0:0:4:2:0',
        'a committed delete plan is single-use and cannot alter retained evidence'
    );
    red_addon_editor_create_test_assert(
        $creatorCalls === 8 && $loaderCalls > 18 && $deleterCalls === 6,
        'only authorized state and runner paths invoke package callbacks'
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
    'Add-on component creation/parent-metadata/atomic-delete self-test passed ('
        . $assertions . " assertions).\n"
);

?>
