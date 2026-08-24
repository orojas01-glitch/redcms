<?php
/**
 * Disposable checks for destination checkpoints, creation, placement, and deletion.
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
    . '/includes/addon_component_editor_publish_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_destination_preflight_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_destination_execution_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_destination_route_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_destination_component_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_destination_publish_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_destination_completion_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_destination_coordinator_helpers.php';
require_once $projectRoot
    . '/includes/addon_component_editor_delete_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_editor_create|addon_destination_completion|rev_base|store_lite_browser)_[A-Za-z0-9_]+\z/',
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
$targetPageRecordId = 2147000977;
$duplicateTargetPageRecordId = 2147000978;
$destinationRouteRecordId = 2147000979;
$destinationComponentRecordId = 2147000980;
$coordinatorRouteRecordId = 2147000981;
$coordinatorComponentRecordId = 2147000982;
$packageId = 'redcms.editor-create-fixture';
$componentId = 'redcms.editor-create-fixture/item';
$previewService = 'content.destination-preview.fixture';
$createPermission = 'fixture.editor-create.create';
$viewPermission = 'fixture.editor-create.view';
$editPermission = 'fixture.editor-create.edit';
$deletePermission = 'fixture.editor-create.delete';
$publishPermission = 'fixture.editor-create.publish';
$packageTable = 'RED_Addon_Component_Editor_Create_Fixture';
$creatorCalls = 0;
$loaderCalls = 0;
$destinationCreatorCalls = 0;
$destinationLoaderCalls = 0;
$deleterCalls = 0;
$creatorMode = 'valid';
$deleterMode = 'valid';
$previewCalls = 0;
$previewMode = 'valid';
$indexSyncCalls = 0;
$indexSyncMode = 'valid';
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
    $targetPageRecordId,
    $duplicateTargetPageRecordId,
    $destinationRouteRecordId,
    $destinationComponentRecordId,
    $coordinatorRouteRecordId,
    $coordinatorComponentRecordId,
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
                    'RED_Content_Revisions',
                    'redcms_component_publish_revision_fail',
                ],
                [
                    'RED_Admin_Activity_Log',
                    'redcms_component_publish_audit_fail',
                ],
                [
                    'RED_Admin_Activity_Log',
                    'redcms_destination_route_audit_fail',
                ],
                [
                    'RED_Addon_Component_Destination_Executions',
                    'redcms_destination_component_checkpoint_fail',
                ],
                [
                    'RED_Addon_Component_Destination_Executions',
                    'redcms_destination_publish_checkpoint_fail',
                ],
                [
                    'RED_Addon_Component_Destination_Executions',
                    'redcms_destination_completion_checkpoint_fail',
                ],
                [
                    'RED_Addon_Component_Destination_Executions',
                    'redcms_destination_coordinator_completion_fail',
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
            "DELETE FROM RED_Addon_Component_Destination_Executions
             WHERE PackageID='" . mysqli_real_escape_string(
                 $connection,
                 $packageId
             ) . "'"
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Page_SEO WHERE OwnerRecordID=' . (int) $targetPageRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Page_SEO WHERE OwnerRecordID='
                . (int) $destinationRouteRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Page_SEO WHERE OwnerRecordID='
                . (int) $coordinatorRouteRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Component_Revisions WHERE ContentRecordID='
                . (int) $contentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Component_Revisions WHERE ContentRecordID='
                . (int) $destinationComponentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Addon_Component_Revisions WHERE ContentRecordID='
                . (int) $coordinatorComponentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Content_Revisions WHERE ContentRecordID='
                . (int) $contentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Content_Revisions WHERE ContentRecordID='
                . (int) $targetPageRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Content_Revisions WHERE ContentRecordID='
                . (int) $destinationRouteRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Content_Revisions WHERE ContentRecordID='
                . (int) $destinationComponentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Content_Revisions WHERE ContentRecordID IN ('
                . (int) $coordinatorRouteRecordId . ','
                . (int) $coordinatorComponentRecordId . ')'
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Admin_Activity_Log
             WHERE TargetType='component' AND TargetRecordID="
                . (int) $contentRecordId
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Admin_Activity_Log
             WHERE TargetType='component' AND TargetRecordID="
                . (int) $destinationComponentRecordId
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Admin_Activity_Log
             WHERE TargetType='article' AND TargetRecordID="
                . (int) $destinationRouteRecordId
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Admin_Activity_Log
             WHERE (TargetType='component' AND TargetRecordID="
                . (int) $coordinatorComponentRecordId . ")
                OR (TargetType='article' AND TargetRecordID="
                . (int) $coordinatorRouteRecordId . ')'
        );
        mysqli_query(
            $connection,
            'DROP TABLE IF EXISTS `' . $packageTable . '`'
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID=' . (int) $contentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID=' . (int) $targetPageRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID='
                . (int) $duplicateTargetPageRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID='
                . (int) $destinationRouteRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID='
                . (int) $destinationComponentRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID IN ('
                . (int) $coordinatorRouteRecordId . ','
                . (int) $coordinatorComponentRecordId . ')'
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
    $deletePermission,
    $publishPermission,
    $previewService
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
            'services' => [$previewService, 'content.index-sync'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [
            $createPermission,
            $viewPermission,
            $editPermission,
            $deletePermission,
            $publishPermission,
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
                'publish' => $publishPermission,
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
            global $destinationComponentRecordId,
                $coordinatorComponentRecordId, $destinationLoaderCalls;
            if (in_array(
                (int) $context['contentRecordId'],
                [
                    $destinationComponentRecordId,
                    $coordinatorComponentRecordId,
                ],
                true
            )) {
                $destinationLoaderCalls++;
            } else {
                $loaderCalls++;
            }
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
                global $destinationComponentRecordId,
                    $coordinatorComponentRecordId,
                    $destinationCreatorCalls;
                if (in_array(
                    (int) $context['contentRecordId'],
                    [
                        $destinationComponentRecordId,
                        $coordinatorComponentRecordId,
                    ],
                    true
                )) {
                    $destinationCreatorCalls++;
                } else {
                    $creatorCalls++;
                }
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
    $serviceId = $manifest['provides']['services'][0] ?? '';
    $registry->registerService(
        $serviceId,
        static function (
            RED_Addon_Service_Request $request
        ): RED_Addon_Service_Result {
            global $previewCalls, $previewMode;
            $previewCalls++;
            if ($previewMode === 'emit') {
                echo 'unsafe-preview-output';
            }
            if ($previewMode === 'throw') {
                throw new RuntimeException('private preview failure');
            }
            $input = $request->input();
            if ($request->operation() !== 'destination.preview'
                || array_keys($input) !== ['alias']
                || !is_string($input['alias'])
                || preg_match(
                    '/\A[a-z][a-z0-9._-]{0,63}\z/D',
                    $input['alias']
                ) !== 1
            ) {
                return RED_Addon_Service_Result::failure(
                    'destination_preview_request_invalid'
                );
            }
            return RED_Addon_Service_Result::success([
                'schema' => 1,
                'planSha256' => $previewMode === 'stale'
                    ? str_repeat('e', 64)
                    : str_repeat('d', 64),
                'intent' => 'provision',
                'ready' => true,
                'requiresConfirmation' => true,
                'writesEnabled' => false,
                'path' => '/' . rawurlencode($input['alias']),
            ]);
        }
    );
    $registry->registerService(
        'content.index-sync',
        static function (
            RED_Addon_Service_Request $request
        ): RED_Addon_Service_Result {
            global $indexSyncCalls, $indexSyncMode,
                $destinationRouteRecordId, $destinationComponentRecordId,
                $coordinatorRouteRecordId, $coordinatorComponentRecordId;
            $indexSyncCalls++;
            if ($indexSyncMode === 'emit') {
                echo 'unsafe-index-sync-output';
            }
            if ($indexSyncMode === 'throw') {
                throw new RuntimeException('private index sync failure');
            }
            $input = $request->input();
            $acceptedIds = [
                [$destinationRouteRecordId, $destinationComponentRecordId],
                [$coordinatorRouteRecordId, $coordinatorComponentRecordId],
            ];
            if ($request->operation() !== 'refresh'
                || array_keys($input) !== ['event', 'recordIds']
                || $input['event'] !== 'component.published'
                || !in_array($input['recordIds'], $acceptedIds, true)
            ) {
                return RED_Addon_Service_Result::failure(
                    'index_sync_request_invalid'
                );
            }
            return $indexSyncMode === 'fail'
                ? RED_Addon_Service_Result::failure('index_sync_failed')
                : RED_Addon_Service_Result::success(['refreshed' => true]);
        }
    );
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );
}

try {
    red_addon_editor_create_test_assert(
        red_addon_component_destination_completion_search([
            'attempted' => true,
            'completed' => false,
            'event' => 'component.published',
            'recordCount' => 2,
            'reason' => 'service_failed',
        ]) === [
            'attempted' => true,
            'succeeded' => false,
            'event' => 'component.published',
            'recordCount' => 2,
            'checkpoint' => 'failed',
        ],
        'contained search failure maps only to terminal failed evidence'
    );
    red_addon_editor_create_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $targetPageRecordId,
        $duplicateTargetPageRecordId,
        $destinationRouteRecordId,
        $destinationComponentRecordId,
        $coordinatorRouteRecordId,
        $coordinatorComponentRecordId,
        $packageId,
        $packageTable
    );
    $manifest = red_addon_editor_create_test_manifest(
        $packageId,
        $componentId,
        $createPermission,
        $viewPermission,
        $editPermission,
        $deletePermission,
        $publishPermission,
        $previewService
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
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $publishPermission
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

    $positionOptions = red_admin_article_layout_position_options(
        $connection,
        $layout,
        false
    );
    $destinationPosition = (int) array_key_first($positionOptions);
    $destinationRequest = [
        'packagePreview' => [
            'schema' => 1,
            'planSha256' => str_repeat('d', 64),
            'intent' => 'provision',
            'ready' => true,
            'requiresConfirmation' => true,
            'writesEnabled' => false,
            'path' => '/product-fixture',
        ],
        'routeRecordId' => $targetPageRecordId,
        'componentRecordId' => $contentRecordId,
        'title' => 'Product fixture',
        'alias' => 'product-fixture',
        'language' => 'sp',
        'layout' => $layout,
        'routePagePosition' => $destinationPosition,
        'routePagePositionOrder' => 0,
        'componentPagePosition' => $destinationPosition,
        'componentPagePositionOrder' => 1,
        'componentValues' => $submittedValues,
    ];
    $destinationBefore = red_addon_editor_create_test_record_fingerprint(
        $connection,
        $contentRecordId,
        $packageTable
    );
    $destination = red_addon_component_destination_preflight(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $destinationRequest
    );
    $destinationRepeated = red_addon_component_destination_preflight(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $destinationRequest
    );
    $destinationAfter = red_addon_editor_create_test_record_fingerprint(
        $connection,
        $contentRecordId,
        $packageTable
    );
    red_addon_editor_create_test_assert(
        !empty($destination['ready'])
            && $destination['reason'] === 'ready'
            && $destination['package'] === $packageId
            && $destination['component'] === $componentId
            && $destination['createPermission'] === $createPermission
            && $destination['publishPermission'] === $publishPermission
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $destination['componentCreatePlanHash']
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $destination['planHash']
            ) === 1,
        'destination preflight composes exact create and publish authority'
    );
    red_addon_editor_create_test_assert(
        $destination['routeValues'] === [
            'RecordID' => $targetPageRecordId,
            'Title' => 'Product fixture',
            'Component' => 'Article',
            'Alias' => 'product-fixture',
            'Sections' => 'home',
            'Categories' => '',
            'SubCategories' => '',
            'Layout' => $layout,
            'PagePosition' => $destinationPosition,
            'PagePositionOrder' => 0,
            'Active' => 'Y',
            'Language' => 'sp',
        ]
            && $destination['placementValues']['Article'] ===
                'product-fixture'
            && $destination['placementValues']['PagePosition'] ===
                $destinationPosition
            && $destination['operations'] === [
                'core.article-route.create',
                'core.addon-component.create',
                'core.addon-component.publish',
                'content.search.refresh',
            ],
        'destination plan fixes the root route, placement, and operation order'
    );
    red_addon_editor_create_test_assert(
        $destinationRepeated === $destination
            && $destinationBefore === $destinationAfter
            && $creatorCalls === 0
            && $loaderCalls === 0,
        'destination preflight is deterministic and read-only without callbacks'
    );

    $routeExecutionRequest = [
        'previewService' => $previewService,
        'previewOperation' => 'destination.preview',
        'previewInput' => ['alias' => 'coordinator-route'],
        'routeRecordId' => $destinationRouteRecordId,
        'componentRecordId' => $destinationComponentRecordId,
        'title' => 'Coordinator route fixture',
        'alias' => 'coordinator-route',
        'language' => 'sp',
        'layout' => $layout,
        'routePagePosition' => $destinationPosition,
        'routePagePositionOrder' => 0,
        'componentPagePosition' => $destinationPosition,
        'componentPagePositionOrder' => 1,
        'componentValues' => $submittedValues,
    ];
    $routePreview = red_addon_component_destination_route_preview(
        $manifest,
        $routeExecutionRequest
    );
    $routePlanRequest =
        red_addon_component_destination_route_preflight_request(
            $routeExecutionRequest,
            $routePreview
        );
    $routePlan = red_addon_component_destination_preflight(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routePlanRequest
    );
    red_addon_editor_create_test_assert(
        is_array($routePreview)
            && !empty($routePlan['ready'])
            && red_addon_valid_sha256($routePlan['planHash']),
        'typed package preview composes one exact route-stage plan'
    );

    mysqli_query(
        $connection,
        "ALTER TABLE RED_Admin_Activity_Log
         ADD CONSTRAINT redcms_destination_route_audit_fail
         CHECK (`EventName` <> 'article.created')"
    );
    $previewCallsBeforeRouteFailure = $previewCalls;
    $failedRoute = red_addon_component_destination_route_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($failedRoute['created'])
            && $failedRoute['reason'] === 'audit_failed'
            && $failedRoute['stage'] === 'planned'
            && $previewCalls === $previewCallsBeforeRouteFailure + 2
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Articles
                     WHERE RecordID=$destinationRouteRecordId),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE ContentRecordID=$destinationRouteRecordId),
                    (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                     WHERE TargetType='article'
                       AND TargetRecordID=$destinationRouteRecordId),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Component_Destination_Executions
                     WHERE PackageID='$packageId'
                       AND PlanSHA256='" . $routePlan['planHash'] . "'
                       AND Stage='planned'))"
            ) === '0:0:0:1',
        'late route audit failure rolls back content while retaining planned retry evidence'
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Admin_Activity_Log
         DROP CHECK redcms_destination_route_audit_fail'
    );

    $previewCallsBeforeRoute = $previewCalls;
    $createdRoute = red_addon_component_destination_route_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($createdRoute['created'])
            && empty($createdRoute['resumed'])
            && $createdRoute['reason'] === 'route_created'
            && $createdRoute['stage'] === 'route_created'
            && red_addon_valid_sha256($createdRoute['routeStateSha256'])
            && $createdRoute['revisionId'] > 0
            && $previewCalls === $previewCallsBeforeRoute + 2,
        'route stage rederives preview and commits route revision audit and checkpoint'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID=$destinationRouteRecordId
                   AND Component='Article' AND Alias='coordinator-route'
                   AND Active='Y' AND Language='sp'),
                (SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID=$destinationRouteRecordId
                   AND RevisionNumber=1 AND Operation='create'
                   AND SnapshotHash='" . $createdRoute['routeStateSha256'] . "'),
                (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE EventName='article.created' AND TargetType='article'
                   AND TargetRecordID=$destinationRouteRecordId
                   AND ActorAdminRecordID=$adminRecordId),
                (SELECT COUNT(*)
                 FROM RED_Addon_Component_Destination_Executions
                 WHERE PackageID='$packageId'
                   AND PlanSHA256='" . $routePlan['planHash'] . "'
                   AND Stage='route_created'
                   AND RouteStateSHA256='"
                    . $createdRoute['routeStateSha256'] . "'))"
        ) === '1:1:1:1',
        'route stage stores one exact public route and bounded evidence set'
    );
    $previewCallsBeforeRouteReplay = $previewCalls;
    $replayedRoute = red_addon_component_destination_route_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($replayedRoute['created'])
            && !empty($replayedRoute['resumed'])
            && $replayedRoute['reason'] === 'resumed'
            && $replayedRoute['revisionId'] === $createdRoute['revisionId']
            && hash_equals(
                $createdRoute['routeStateSha256'],
                $replayedRoute['routeStateSha256']
            )
            && $previewCalls === $previewCallsBeforeRouteReplay + 2,
        'exact route-stage replay rederives preview and resumes without duplicate writes'
    );
    $changedRouteRequest = $routeExecutionRequest;
    $changedRouteRequest['componentValues']['quantity'] = '6';
    $changedRoute = red_addon_component_destination_route_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $changedRouteRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($changedRoute['created'])
            && $changedRoute['reason'] === 'stale_plan'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Articles
                     WHERE RecordID=$destinationRouteRecordId),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE ContentRecordID=$destinationRouteRecordId),
                    (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                     WHERE TargetType='article'
                       AND TargetRecordID=$destinationRouteRecordId))"
            ) === '1:1:1',
        'changed package component values fail exact route-stage replay'
    );
    $previewMode = 'stale';
    $staleRoute = red_addon_component_destination_route_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    $previewMode = 'valid';
    red_addon_editor_create_test_assert(
        empty($staleRoute['created'])
            && $staleRoute['reason'] === 'stale_plan'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Articles
                     WHERE RecordID=$destinationRouteRecordId),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE ContentRecordID=$destinationRouteRecordId),
                    (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                     WHERE TargetType='article'
                       AND TargetRecordID=$destinationRouteRecordId))"
            ) === '1:1:1',
        'changed package preview fails before route-stage replay or mutation'
    );

    mysqli_query(
        $connection,
        "ALTER TABLE RED_Addon_Component_Destination_Executions
         ADD CONSTRAINT redcms_destination_component_checkpoint_fail
         CHECK (`Stage` <> 'component_created')"
    );
    $previewCallsBeforeComponentFailure = $previewCalls;
    $failedComponent = red_addon_component_destination_component_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    $failedComponentFingerprint = red_addon_editor_create_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':',
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=$destinationComponentRecordId
               AND Component='$componentId' AND Active='N'
               AND Alias='' AND PagePosition=0),
            (SELECT COUNT(*) FROM `$packageTable`
             WHERE ContentRecordID=$destinationComponentRecordId),
            (SELECT COUNT(*) FROM RED_Content_Revisions
             WHERE ContentRecordID=$destinationComponentRecordId
               AND RevisionNumber=1 AND Operation='create'),
            (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
             WHERE ContentRecordID=$destinationComponentRecordId
               AND RevisionNumber=1 AND Operation='baseline'),
            (SELECT COUNT(*)
             FROM RED_Addon_Component_Destination_Executions
             WHERE PackageID='$packageId'
               AND PlanSHA256='" . $routePlan['planHash'] . "'
               AND Stage='route_created'
               AND ComponentStateSHA256 IS NULL))"
    );
    red_addon_editor_create_test_assert(
        empty($failedComponent['created'])
            && $failedComponent['reason'] === 'checkpoint_failed'
            && $failedComponent['stage'] === 'route_created'
            && $destinationCreatorCalls === 1
            && $previewCalls === $previewCallsBeforeComponentFailure + 2
            && $failedComponentFingerprint === '1:1:1:1:1',
        'checkpoint failure retains one committed inactive component and the route checkpoint: '
            . json_encode([
                'result' => $failedComponent,
                'creatorCalls' => $destinationCreatorCalls,
                'previewDelta' =>
                    $previewCalls - $previewCallsBeforeComponentFailure,
                'fingerprint' => $failedComponentFingerprint,
            ])
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Component_Destination_Executions
         DROP CHECK redcms_destination_component_checkpoint_fail'
    );

    $previewCallsBeforeComponentRetry = $previewCalls;
    $createdComponent = red_addon_component_destination_component_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($createdComponent['created'])
            && !empty($createdComponent['resumed'])
            && $createdComponent['reason'] === 'resumed'
            && $createdComponent['stage'] === 'component_created'
            && red_addon_valid_sha256(
                $createdComponent['componentStateSha256']
            )
            && $createdComponent['parentRevisionId'] > 0
            && $createdComponent['packageRevisionId'] > 0
            && $destinationCreatorCalls === 1
            && $previewCalls === $previewCallsBeforeComponentRetry + 2,
        'retry reconciles the committed component and advances the retained route checkpoint'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID=$destinationRouteRecordId),
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID=$destinationComponentRecordId),
                (SELECT COUNT(*) FROM `$packageTable`
                 WHERE ContentRecordID=$destinationComponentRecordId),
                (SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID=$destinationComponentRecordId),
                (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
                 WHERE ContentRecordID=$destinationComponentRecordId),
                (SELECT COUNT(*)
                 FROM RED_Addon_Component_Destination_Executions
                 WHERE PackageID='$packageId'
                   AND PlanSHA256='" . $routePlan['planHash'] . "'
                   AND Stage='component_created'
                   AND ComponentStateSHA256='"
                    . $createdComponent['componentStateSha256'] . "'))"
        ) === '1:1:1:1:1:1',
        'component checkpoint binds one route, component, package row, and revision pair'
    );

    $previewCallsBeforeComponentReplay = $previewCalls;
    $replayedComponent = red_addon_component_destination_component_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($replayedComponent['created'])
            && !empty($replayedComponent['resumed'])
            && $replayedComponent['reason'] === 'resumed'
            && $replayedComponent['parentRevisionId'] ===
                $createdComponent['parentRevisionId']
            && $replayedComponent['packageRevisionId'] ===
                $createdComponent['packageRevisionId']
            && hash_equals(
                $createdComponent['componentStateSha256'],
                $replayedComponent['componentStateSha256']
            )
            && $destinationCreatorCalls === 1
            && $previewCalls === $previewCallsBeforeComponentReplay + 2,
        'exact component-stage replay rederives preview without duplicate package writes'
    );

    $changedComponentRequest = $routeExecutionRequest;
    $changedComponentRequest['componentValues']['quantity'] = '6';
    $changedComponent = red_addon_component_destination_component_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $changedComponentRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($changedComponent['created'])
            && $changedComponent['reason'] === 'component_drift'
            && $destinationCreatorCalls === 1,
        'changed component values cannot resume the immutable destination plan'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Title='Drifted component shell'
         WHERE RecordID=$destinationComponentRecordId"
    );
    $driftedComponent = red_addon_component_destination_component_create(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($driftedComponent['created'])
            && $driftedComponent['reason'] === 'component_drift'
            && $destinationCreatorCalls === 1,
        'parent or revision drift fails closed without a second creator call'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Title='Coordinator route fixture'
         WHERE RecordID=$destinationComponentRecordId"
    );

    mysqli_query(
        $connection,
        "ALTER TABLE RED_Addon_Component_Destination_Executions
         ADD CONSTRAINT redcms_destination_publish_checkpoint_fail
         CHECK (`Stage` <> 'component_published')"
    );
    $previewCallsBeforePublishFailure = $previewCalls;
    $failedPublish = red_addon_component_destination_component_publish(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($failedPublish['published'])
            && $failedPublish['reason'] === 'checkpoint_failed'
            && $failedPublish['stage'] === 'component_created'
            && $previewCalls === $previewCallsBeforePublishFailure + 2
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Articles
                     WHERE RecordID=$destinationComponentRecordId
                       AND Component='$componentId' AND Active='Y'
                       AND Article='coordinator-route'
                       AND PagePosition=$destinationPosition),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE ContentRecordID=$destinationComponentRecordId
                       AND RevisionNumber=2 AND Operation='move'),
                    (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                     WHERE EventName='component.public_placed'
                       AND TargetType='component'
                       AND TargetRecordID=$destinationComponentRecordId
                       AND ActorAdminRecordID=$adminRecordId),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Component_Destination_Executions
                     WHERE PackageID='$packageId'
                       AND PlanSHA256='" . $routePlan['planHash'] . "'
                       AND Stage='component_created'
                       AND PlacementStateSHA256 IS NULL))"
            ) === '1:1:1:1',
        'publish checkpoint failure retains one committed public placement and component checkpoint'
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Component_Destination_Executions
         DROP CHECK redcms_destination_publish_checkpoint_fail'
    );

    $previewCallsBeforePublishRetry = $previewCalls;
    $publishedComponent = red_addon_component_destination_component_publish(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($publishedComponent['published'])
            && !empty($publishedComponent['resumed'])
            && $publishedComponent['reason'] === 'resumed'
            && $publishedComponent['stage'] === 'component_published'
            && red_addon_valid_sha256(
                $publishedComponent['placementStateSha256']
            )
            && $publishedComponent['revisionId'] > 0
            && $publishedComponent['revisionNumber'] === 2
            && $previewCalls === $previewCallsBeforePublishRetry + 2,
        'retry reconciles the committed placement and advances the component checkpoint'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID=$destinationComponentRecordId
                   AND Active='Y' AND Article='coordinator-route'),
                (SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID=$destinationComponentRecordId),
                (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
                 WHERE ContentRecordID=$destinationComponentRecordId),
                (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE EventName='component.public_placed'
                   AND TargetRecordID=$destinationComponentRecordId),
                (SELECT COUNT(*)
                 FROM RED_Addon_Component_Destination_Executions
                 WHERE PackageID='$packageId'
                   AND PlanSHA256='" . $routePlan['planHash'] . "'
                   AND Stage='component_published'
                   AND PlacementStateSHA256='"
                    . $publishedComponent['placementStateSha256'] . "'))"
        ) === '1:2:1:1:1',
        'published checkpoint binds one placement, move revision, baseline, and audit fact'
    );

    $previewCallsBeforePublishReplay = $previewCalls;
    $replayedPublish = red_addon_component_destination_component_publish(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($replayedPublish['published'])
            && !empty($replayedPublish['resumed'])
            && $replayedPublish['reason'] === 'resumed'
            && $replayedPublish['revisionId'] ===
                $publishedComponent['revisionId']
            && hash_equals(
                $publishedComponent['placementStateSha256'],
                $replayedPublish['placementStateSha256']
            )
            && $previewCalls === $previewCallsBeforePublishReplay + 2,
        'exact publish-stage replay rederives preview without another move or audit'
    );

    $changedPublishRequest = $routeExecutionRequest;
    $changedPublishRequest['componentValues']['quantity'] = '6';
    $changedPublish = red_addon_component_destination_component_publish(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $changedPublishRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($changedPublish['published'])
            && $changedPublish['reason'] === 'placement_drift',
        'changed package values cannot resume the immutable published plan'
    );

    mysqli_query(
        $connection,
        'UPDATE RED_Articles SET PagePositionOrder=2 WHERE RecordID='
            . $destinationComponentRecordId
    );
    $driftedPublish = red_addon_component_destination_component_publish(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($driftedPublish['published'])
            && $driftedPublish['reason'] === 'placement_drift',
        'published parent or revision drift fails closed without another placement'
    );
    mysqli_query(
        $connection,
        'UPDATE RED_Articles SET PagePositionOrder=1 WHERE RecordID='
            . $destinationComponentRecordId
    );

    mysqli_query(
        $connection,
        "ALTER TABLE RED_Addon_Component_Destination_Executions
         ADD CONSTRAINT redcms_destination_completion_checkpoint_fail
         CHECK (`Stage` <> 'completed')"
    );
    $previewCallsBeforeCompletionFailure = $previewCalls;
    $indexSyncCallsBeforeCompletionFailure = $indexSyncCalls;
    $failedCompletion = red_addon_component_destination_complete(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        empty($failedCompletion['completed'])
            && $failedCompletion['reason'] === 'checkpoint_failed'
            && !empty($failedCompletion['notificationAttempted'])
            && !empty($failedCompletion['notificationSucceeded'])
            && $failedCompletion['notificationEvent'] === 'component.published'
            && $failedCompletion['notificationRecordCount'] === 2
            && $previewCalls === $previewCallsBeforeCompletionFailure + 2
            && $indexSyncCalls === $indexSyncCallsBeforeCompletionFailure + 1
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT COUNT(*)
                 FROM RED_Addon_Component_Destination_Executions
                 WHERE PackageID='$packageId'
                   AND PlanSHA256='" . $routePlan['planHash'] . "'
                   AND Stage='component_published'
                   AND SearchNotification='pending'"
            ) === '1',
        'notification success cannot hide a failed terminal checkpoint'
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Component_Destination_Executions
         DROP CHECK redcms_destination_completion_checkpoint_fail'
    );

    $previewCallsBeforeCompletionRetry = $previewCalls;
    $completedDestination = red_addon_component_destination_complete(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        !empty($completedDestination['completed'])
            && empty($completedDestination['resumed'])
            && $completedDestination['reason'] === 'completed'
            && $completedDestination['stage'] === 'completed'
            && $completedDestination['searchNotification'] === 'succeeded'
            && !empty($completedDestination['notificationAttempted'])
            && !empty($completedDestination['notificationSucceeded'])
            && $previewCalls === $previewCallsBeforeCompletionRetry + 2
            && $indexSyncCalls === $indexSyncCallsBeforeCompletionFailure + 2,
        'retry repeats only the repairable refresh and durably completes success'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID IN (
                    $destinationRouteRecordId,
                    $destinationComponentRecordId
                 )),
                (SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID IN (
                    $destinationRouteRecordId,
                    $destinationComponentRecordId
                 )),
                (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
                 WHERE ContentRecordID=$destinationComponentRecordId),
                (SELECT COUNT(*) FROM RED_Addon_Component_Destination_Executions
                 WHERE PackageID='$packageId'
                   AND PlanSHA256='" . $routePlan['planHash'] . "'
                   AND Stage='completed'
                   AND SearchNotification='succeeded'))"
        ) === '2:3:1:1',
        'completion changes only terminal notification evidence'
    );

    $previewCallsBeforeCompletionReplay = $previewCalls;
    $replayedCompletion = red_addon_component_destination_complete(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        !empty($replayedCompletion['completed'])
            && !empty($replayedCompletion['resumed'])
            && $replayedCompletion['reason'] === 'resumed'
            && $replayedCompletion['searchNotification'] === 'succeeded'
            && empty($replayedCompletion['notificationAttempted'])
            && !empty($replayedCompletion['notificationSucceeded'])
            && $previewCalls === $previewCallsBeforeCompletionReplay + 2
            && $indexSyncCalls === $indexSyncCallsBeforeCompletionFailure + 2,
        'completed replay revalidates content without another notification'
    );

    $changedCompletionRequest = $routeExecutionRequest;
    $changedCompletionRequest['componentValues']['quantity'] = '6';
    $changedCompletion = red_addon_component_destination_complete(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $changedCompletionRequest,
        $routePlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        empty($changedCompletion['completed'])
            && $changedCompletion['reason'] === 'placement_drift'
            && $indexSyncCalls === $indexSyncCallsBeforeCompletionFailure + 2,
        'changed package values cannot replay terminal completion'
    );

    mysqli_query(
        $connection,
        'UPDATE RED_Articles SET PagePositionOrder=2 WHERE RecordID='
            . $destinationComponentRecordId
    );
    $driftedCompletion = red_addon_component_destination_complete(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $routeExecutionRequest,
        $routePlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        empty($driftedCompletion['completed'])
            && $driftedCompletion['reason'] === 'placement_drift'
            && $indexSyncCalls === $indexSyncCallsBeforeCompletionFailure + 2,
        'published placement drift fails closed without another notification'
    );
    mysqli_query(
        $connection,
        'UPDATE RED_Articles SET PagePositionOrder=1 WHERE RecordID='
            . $destinationComponentRecordId
    );

    $coordinatorRequest = $routeExecutionRequest;
    $coordinatorRequest['previewInput'] = ['alias' => 'coordinator-full'];
    $coordinatorRequest['routeRecordId'] = $coordinatorRouteRecordId;
    $coordinatorRequest['componentRecordId'] = $coordinatorComponentRecordId;
    $coordinatorRequest['title'] = 'Full coordinator fixture';
    $coordinatorRequest['alias'] = 'coordinator-full';
    $coordinatorRequest['componentPagePositionOrder'] = 2;
    $coordinatorPreview = red_addon_component_destination_route_preview(
        $manifest,
        $coordinatorRequest
    );
    $coordinatorPlan = red_addon_component_destination_preflight(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        red_addon_component_destination_route_preflight_request(
            $coordinatorRequest,
            $coordinatorPreview
        )
    );
    red_addon_editor_create_test_assert(
        is_array($coordinatorPreview)
            && !empty($coordinatorPlan['ready'])
            && red_addon_valid_sha256($coordinatorPlan['planHash']),
        'full coordinator starts from one exact immutable destination plan'
    );

    mysqli_query(
        $connection,
        "ALTER TABLE RED_Addon_Component_Destination_Executions
         ADD CONSTRAINT redcms_destination_coordinator_completion_fail
         CHECK (PlanSHA256 <> '" . $coordinatorPlan['planHash'] . "'
                OR Stage <> 'completed')"
    );
    $creatorCallsBeforeCoordinator = $destinationCreatorCalls;
    $indexCallsBeforeCoordinator = $indexSyncCalls;
    $failedCoordinator = red_addon_component_destination_coordinate(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $coordinatorRequest,
        $coordinatorPlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        empty($failedCoordinator['completed'])
            && !empty($failedCoordinator['routeCreated'])
            && !empty($failedCoordinator['componentCreated'])
            && !empty($failedCoordinator['componentPublished'])
            && empty($failedCoordinator['completionRecorded'])
            && $failedCoordinator['stageAttempts'] === 4
            && $failedCoordinator['completedStages'] === 3
            && $failedCoordinator['failedStage'] === 'completion'
            && $failedCoordinator['reason'] === 'checkpoint_failed'
            && $failedCoordinator['stage'] === 'component_published'
            && $destinationCreatorCalls ===
                $creatorCallsBeforeCoordinator + 1
            && $indexSyncCalls === $indexCallsBeforeCoordinator + 1
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Articles
                     WHERE RecordID IN (
                        $coordinatorRouteRecordId,
                        $coordinatorComponentRecordId
                     )),
                    (SELECT COUNT(*) FROM `$packageTable`
                     WHERE ContentRecordID=$coordinatorComponentRecordId),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Component_Destination_Executions
                     WHERE PackageID='$packageId'
                       AND PlanSHA256='" . $coordinatorPlan['planHash'] . "'
                       AND Stage='component_published'
                       AND SearchNotification='pending'))"
            ) === '2:1:1',
        'coordinator stops after a contained terminal-checkpoint failure with published content retained'
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Addon_Component_Destination_Executions
         DROP CHECK redcms_destination_coordinator_completion_fail'
    );

    $resumedCoordinator = red_addon_component_destination_coordinate(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $coordinatorRequest,
        $coordinatorPlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        !empty($resumedCoordinator['completed'])
            && !empty($resumedCoordinator['resumed'])
            && !empty($resumedCoordinator['routeCreated'])
            && !empty($resumedCoordinator['componentCreated'])
            && !empty($resumedCoordinator['componentPublished'])
            && !empty($resumedCoordinator['completionRecorded'])
            && !empty($resumedCoordinator['notificationSucceeded'])
            && $resumedCoordinator['stageAttempts'] === 1
            && $resumedCoordinator['completedStages'] === 4
            && $resumedCoordinator['failedStage'] === ''
            && $resumedCoordinator['stage'] === 'completed'
            && $resumedCoordinator['searchNotification'] === 'succeeded'
            && $resumedCoordinator['reason'] === 'resumed'
            && $destinationCreatorCalls ===
                $creatorCallsBeforeCoordinator + 1
            && $indexSyncCalls === $indexCallsBeforeCoordinator + 2,
        'one retry resumes all retained stage evidence and repeats only repairable search completion: '
            . json_encode([
                'result' => $resumedCoordinator,
                'creatorCalls' => $destinationCreatorCalls,
                'expectedCreatorCalls' =>
                    $creatorCallsBeforeCoordinator + 1,
                'indexCalls' => $indexSyncCalls,
                'expectedIndexCalls' => $indexCallsBeforeCoordinator + 2,
            ])
    );

    $indexCallsBeforeCoordinatorReplay = $indexSyncCalls;
    $replayedCoordinator = red_addon_component_destination_coordinate(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $coordinatorRequest,
        $coordinatorPlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        !empty($replayedCoordinator['completed'])
            && !empty($replayedCoordinator['resumed'])
            && $replayedCoordinator['stageAttempts'] === 1
            && $replayedCoordinator['completedStages'] === 4
            && $replayedCoordinator['searchNotification'] === 'succeeded'
            && $destinationCreatorCalls ===
                $creatorCallsBeforeCoordinator + 1
            && $indexSyncCalls === $indexCallsBeforeCoordinatorReplay,
        'terminal coordinator replay revalidates every stage without duplicate package or search writes'
    );

    $changedCoordinatorRequest = $coordinatorRequest;
    $changedCoordinatorRequest['componentValues']['quantity'] = '6';
    $refusedCoordinator = red_addon_component_destination_coordinate(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $changedCoordinatorRequest,
        $coordinatorPlan['planHash'],
        $projectRoot
    );
    red_addon_editor_create_test_assert(
        empty($refusedCoordinator['completed'])
            && $refusedCoordinator['stageAttempts'] === 1
            && $refusedCoordinator['completedStages'] === 3
            && $refusedCoordinator['failedStage'] === 'completion'
            && $refusedCoordinator['reason'] === 'placement_drift'
            && $destinationCreatorCalls ===
                $creatorCallsBeforeCoordinator + 1
            && $indexSyncCalls === $indexCallsBeforeCoordinatorReplay,
        'coordinator resumes from durable progress and refuses changed immutable input without downstream work'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Addon_Component_Destination_Executions
         WHERE PackageID='$packageId'
           AND PlanSHA256='" . $coordinatorPlan['planHash'] . "'"
    );
    mysqli_query(
        $connection,
        'DELETE FROM RED_Addon_Component_Revisions WHERE ContentRecordID='
            . $coordinatorComponentRecordId
    );
    mysqli_query(
        $connection,
        'DELETE FROM RED_Content_Revisions WHERE ContentRecordID IN ('
            . $coordinatorRouteRecordId . ','
            . $coordinatorComponentRecordId . ')'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Activity_Log
         WHERE (TargetType='article' AND TargetRecordID="
            . $coordinatorRouteRecordId . ")
            OR (TargetType='component' AND TargetRecordID="
            . $coordinatorComponentRecordId . ')'
    );
    mysqli_query(
        $connection,
        'DELETE FROM `' . $packageTable . '` WHERE ContentRecordID='
            . $coordinatorComponentRecordId
    );
    mysqli_query(
        $connection,
        'DELETE FROM RED_Articles WHERE RecordID IN ('
            . $coordinatorRouteRecordId . ','
            . $coordinatorComponentRecordId . ')'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Articles WHERE RecordID IN (
                    $coordinatorRouteRecordId,
                    $coordinatorComponentRecordId
                )),
                (SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID IN (
                    $coordinatorRouteRecordId,
                    $coordinatorComponentRecordId
                 )),
                (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
                 WHERE ContentRecordID=$coordinatorComponentRecordId),
                (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE TargetRecordID IN (
                    $coordinatorRouteRecordId,
                    $coordinatorComponentRecordId
                 )),
                (SELECT COUNT(*)
                 FROM RED_Addon_Component_Destination_Executions
                 WHERE PackageID='$packageId'
                   AND PlanSHA256='" . $coordinatorPlan['planHash'] . "'))"
        ) === '0:0:0:0:0',
        'full coordinator fixture cleans its route, component, revisions, audits, and checkpoint exactly'
    );

    red_addon_editor_create_test_assert(
        red_addon_component_destination_execution_storage_available(
            $connection
        ),
        'destination execution checkpoint storage matches the exact schema'
    );
    $reservation = red_addon_component_destination_execution_reserve(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $destinationRequest,
        $destination['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($reservation['reserved'])
            && empty($reservation['resumed'])
            && $reservation['stage'] === 'planned'
            && $reservation['searchNotification'] === 'pending'
            && $reservation['routeStateSha256'] === ''
            && $reservation['componentStateSha256'] === ''
            && $reservation['placementStateSha256'] === ''
            && $destinationBefore ===
                red_addon_editor_create_test_record_fingerprint(
                    $connection,
                    $contentRecordId,
                    $packageTable
                ),
        'exact provisioning plan reserves bounded evidence without content writes'
    );
    $reservationRepeated = red_addon_component_destination_execution_reserve(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $destinationRequest,
        $destination['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($reservationRepeated['reserved'])
            && !empty($reservationRepeated['resumed'])
            && $reservationRepeated['stage'] === 'planned'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT COUNT(*)
                 FROM RED_Addon_Component_Destination_Executions
                 WHERE PackageID='$packageId'
                   AND PlanSHA256='" . $destination['planHash'] . "'"
            ) === '1',
        'exact reservation replay resumes one immutable execution row'
    );
    $staleReservation = red_addon_component_destination_execution_reserve(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $destinationRequest,
        str_repeat('e', 64)
    );
    red_addon_editor_create_test_assert(
        empty($staleReservation['reserved'])
            && $staleReservation['reason'] === 'stale_plan',
        'changed coordinator plan hash is refused before ledger mutation'
    );

    $routeStateSha256 = str_repeat('1', 64);
    $componentStateSha256 = str_repeat('2', 64);
    $placementStateSha256 = str_repeat('3', 64);
    $routeCheckpoint = red_addon_component_destination_execution_checkpoint(
        $connection,
        $packageId,
        $destination['planHash'],
        $adminRecordId,
        'planned',
        'route_created',
        $routeStateSha256
    );
    $routeCheckpointRepeated =
        red_addon_component_destination_execution_checkpoint(
            $connection,
            $packageId,
            $destination['planHash'],
            $adminRecordId,
            'planned',
            'route_created',
            $routeStateSha256
        );
    red_addon_editor_create_test_assert(
        !empty($routeCheckpoint['checkpointed'])
            && empty($routeCheckpoint['resumed'])
            && $routeCheckpoint['stage'] === 'route_created'
            && $routeCheckpoint['routeStateSha256'] === $routeStateSha256
            && !empty($routeCheckpointRepeated['checkpointed'])
            && !empty($routeCheckpointRepeated['resumed']),
        'route checkpoint is compare-and-swap guarded and replayable'
    );
    $wrongActorCheckpoint =
        red_addon_component_destination_execution_checkpoint(
            $connection,
            $packageId,
            $destination['planHash'],
            $adminRecordId + 1,
            'route_created',
            'component_created',
            $componentStateSha256
        );
    red_addon_editor_create_test_assert(
        empty($wrongActorCheckpoint['checkpointed'])
            && $wrongActorCheckpoint['reason'] === 'actor_mismatch',
        'checkpoint continuation remains bound to the reserving actor'
    );
    $componentCheckpoint =
        red_addon_component_destination_execution_checkpoint(
            $connection,
            $packageId,
            $destination['planHash'],
            $adminRecordId,
            'route_created',
            'component_created',
            $componentStateSha256
        );
    $placementCheckpoint =
        red_addon_component_destination_execution_checkpoint(
            $connection,
            $packageId,
            $destination['planHash'],
            $adminRecordId,
            'component_created',
            'component_published',
            $placementStateSha256
        );
    red_addon_editor_create_test_assert(
        $componentCheckpoint['stage'] === 'component_created'
            && $componentCheckpoint['componentStateSha256'] ===
                $componentStateSha256
            && $placementCheckpoint['stage'] === 'component_published'
            && $placementCheckpoint['placementStateSha256'] ===
                $placementStateSha256,
        'component and placement checkpoints retain only state hashes'
    );
    $completedCheckpoint =
        red_addon_component_destination_execution_checkpoint(
            $connection,
            $packageId,
            $destination['planHash'],
            $adminRecordId,
            'component_published',
            'completed',
            '',
            'failed'
        );
    red_addon_editor_create_test_assert(
        !empty($completedCheckpoint['checkpointed'])
            && $completedCheckpoint['stage'] === 'completed'
            && $completedCheckpoint['searchNotification'] === 'failed'
            && $completedCheckpoint['routeStateSha256'] === $routeStateSha256
            && $completedCheckpoint['componentStateSha256'] ===
                $componentStateSha256
            && $completedCheckpoint['placementStateSha256'] ===
                $placementStateSha256,
        'best-effort search failure can close content completion durably'
    );
    $terminalCheckpoint =
        red_addon_component_destination_execution_checkpoint(
            $connection,
            $packageId,
            $destination['planHash'],
            $adminRecordId,
            'planned',
            'route_created',
            str_repeat('4', 64)
        );
    red_addon_editor_create_test_assert(
        empty($terminalCheckpoint['checkpointed'])
            && $terminalCheckpoint['reason'] === 'stale_stage',
        'completed execution cannot regress or overwrite checkpoint evidence'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Component_Destination_Executions
         SET SearchNotification='pending'
         WHERE PackageID='$packageId'
           AND PlanSHA256='" . $destination['planHash'] . "'"
    );
    $malformedCheckpoint = red_addon_component_destination_execution_load(
        $connection,
        $packageId,
        $destination['planHash']
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Component_Destination_Executions
         SET SearchNotification='failed'
         WHERE PackageID='$packageId'
           AND PlanSHA256='" . $destination['planHash'] . "'"
    );
    red_addon_editor_create_test_assert(
        $malformedCheckpoint === null,
        'stage and hash shape drift fails closed during checkpoint loading'
    );

    $unsafePreview = $destinationRequest;
    $unsafePreview['packagePreview']['writesEnabled'] = true;
    $refusedDestination = red_addon_component_destination_preflight(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $unsafePreview
    );
    red_addon_editor_create_test_assert(
        empty($refusedDestination['ready'])
            && $refusedDestination['reason'] === 'preview_invalid',
        'write-enabled or malformed package preview evidence fails closed'
    );

    $duplicateIds = $destinationRequest;
    $duplicateIds['routeRecordId'] = $contentRecordId;
    $refusedDestination = red_addon_component_destination_preflight(
        $connection,
        $manifest,
        $componentId,
        $adminRecordId,
        $duplicateIds
    );
    red_addon_editor_create_test_assert(
        empty($refusedDestination['ready'])
            && $refusedDestination['reason'] === 'invalid_request',
        'route and component identifiers must be distinct server-derived values'
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

    $targetInserted = red_admin_article_insert(
        $connection,
        $targetPageRecordId,
        [
            'Title' => 'Disposable placement target',
            'Component' => 'Article',
            'Alias' => 'addon-placement-target',
            'Sections' => '',
            'Categories' => '',
            'SubCategories' => '',
            'Layout' => $layout,
            'Article' => '',
            'Active' => 'Y',
            'Language' => 'en',
            'LongDesc' => '<p>Disposable placement target.</p>',
        ]
    );
    $target = red_addon_component_editor_publish_target(
        $connection,
        $targetPageRecordId
    );
    red_addon_editor_create_test_assert(
        $targetInserted
            && is_array($target)
            && $target['recordId'] === $targetPageRecordId
            && $target['alias'] === 'addon-placement-target'
            && strlen($target['stateHash']) === 64,
        'one exact active Article route is an eligible placement target'
    );

    $loaderCallsBeforeInvalidPublish = $loaderCalls;
    $refused = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        0,
        0,
        $updated['stateHash'],
        $updated['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready'])
            && $refused['reason'] === 'invalid_request'
            && $loaderCalls === $loaderCallsBeforeInvalidPublish,
        'invalid placement input is refused before package loading'
    );

    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId . " AND Capability='"
            . mysqli_real_escape_string($connection, $publishPermission) . "'"
    );
    $loaderCallsBeforePublishRefusal = $loaderCalls;
    $refused = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready'])
            && $refused['reason'] === 'permission_denied'
            && $refused['publishPermission'] === $publishPermission
            && $loaderCalls === $loaderCallsBeforePublishRefusal,
        'the fresh exact publish grant is required before package loading'
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $publishPermission
    );

    $publishFingerprint = red_addon_editor_create_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':',
            (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                SubCategories, Article, PagePosition, PagePositionOrder)
             FROM RED_Articles WHERE RecordID=$contentRecordId),
            (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                SubCategories, Layout, Language, PagePosition)
             FROM RED_Articles WHERE RecordID=$targetPageRecordId),
            (SELECT COUNT(*) FROM `$packageTable`
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Content_Revisions
             WHERE ContentRecordID IN ($contentRecordId,$targetPageRecordId)),
            (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
             WHERE ContentRecordID=$contentRecordId))"
    );
    $publishPlan = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash']
    );
    $repeatPublishPlan = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        !empty($publishPlan['ready'])
            && $publishPlan['reason'] === 'ready'
            && $publishPlan['package'] === $packageId
            && $publishPlan['viewPermission'] === $viewPermission
            && $publishPlan['publishPermission'] === $publishPermission
            && $publishPlan['parentStateHash'] === $updated['stateHash']
            && $publishPlan['packageStateHash'] === $updated['packageStateHash']
            && $publishPlan['targetStateHash'] === $target['stateHash']
            && $publishPlan['placementValues'] === [
                'Sections' => '',
                'Categories' => '',
                'SubCategories' => '',
                'Article' => 'addon-placement-target',
                'PagePosition' => 1,
                'PagePositionOrder' => 3,
                'Active' => 'Y',
            ]
            && strlen($publishPlan['planHash']) === 64,
        'publish preflight derives one closed public-placement plan from the exact route owner'
    );
    red_addon_editor_create_test_assert(
        !empty($repeatPublishPlan['ready'])
            && $repeatPublishPlan['planHash'] === $publishPlan['planHash']
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                        SubCategories, Article, PagePosition, PagePositionOrder)
                     FROM RED_Articles WHERE RecordID=$contentRecordId),
                    (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                        SubCategories, Layout, Language, PagePosition)
                     FROM RED_Articles WHERE RecordID=$targetPageRecordId),
                    (SELECT COUNT(*) FROM `$packageTable`
                     WHERE ContentRecordID=$contentRecordId),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE ContentRecordID IN ($contentRecordId,$targetPageRecordId)),
                    (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
                     WHERE ContentRecordID=$contentRecordId))"
            ) === $publishFingerprint,
        'identical publish evidence is deterministic and writes no core or package state'
    );
    $controlContext = red_addon_component_editor_publish_control_context(
        $connection,
        $contentRecordId,
        $adminRecordId
    );
    $controlHtml = red_addon_component_editor_publish_control_render(
        $controlContext,
        str_repeat('b', 64)
    );
    red_addon_editor_create_test_assert(
        !empty($controlContext['ready'])
            && $controlContext['component'] === $componentId
            && $controlContext['package'] === $packageId
            && $controlContext['parentStateHash'] === $updated['stateHash']
            && $controlContext['packageStateHash']
                === $updated['packageStateHash']
            && $controlContext['targets'] === [[
                'recordId' => $targetPageRecordId,
                'title' => 'Disposable placement target',
                'alias' => 'addon-placement-target',
            ]]
            && $controlContext['positions'] !== []
            && str_contains($controlHtml, 'data-red-addon-placement-form')
            && str_contains(
                $controlHtml,
                'action="/admin/bin/place_addon_component.php"'
            )
            && str_contains(
                $controlHtml,
                'name="ContentRecordID" value="' . $contentRecordId . '"'
            )
            && str_contains(
                $controlHtml,
                'name="TargetPageRecordID"'
            )
            && str_contains($controlHtml, 'name="PagePosition"')
            && str_contains($controlHtml, 'name="PagePositionOrder"')
            && str_contains($controlHtml, 'name="ParentStateHash"')
            && str_contains($controlHtml, 'name="PackageStateHash"')
            && str_contains(
                $controlHtml,
                'name="csrf_token" value="' . str_repeat('b', 64) . '"'
            )
            && !str_contains($controlHtml, 'name="package"')
            && !str_contains($controlHtml, 'name="component"')
            && !str_contains($controlHtml, 'name="planHash"')
            && red_addon_component_editor_publish_control_render(
                array_replace($controlContext, [
                    'targets' => [[
                        'recordId' => (string) $targetPageRecordId,
                        'title' => 'Forged target',
                        'alias' => 'forged-target',
                    ]],
                ]),
                str_repeat('b', 64)
            ) === '',
        'the core placement form exposes only numeric choices, stale-state evidence, and CSRF while deriving ownership server-side'
    );

    $refused = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        hash('sha256', 'stale-parent'),
        $updated['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready']) && $refused['reason'] === 'stale_state',
        'stale parent evidence cannot produce a placement plan'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Language='sp'
         WHERE RecordID=$targetPageRecordId"
    );
    $refused = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash']
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Language='en'
         WHERE RecordID=$targetPageRecordId"
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready']) && $refused['reason'] === 'language_mismatch',
        'a component cannot be planned onto a route in another language'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Active='N'
         WHERE RecordID=$targetPageRecordId"
    );
    $refused = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash']
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Active='Y'
         WHERE RecordID=$targetPageRecordId"
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready'])
            && $refused['reason'] === 'target_page_unavailable',
        'inactive destinations are not eligible public route owners'
    );

    $duplicateInserted = red_admin_article_insert(
        $connection,
        $duplicateTargetPageRecordId,
        [
            'Title' => 'Duplicate disposable placement target',
            'Component' => 'Article',
            'Alias' => 'addon-placement-target',
            'Sections' => '',
            'Categories' => '',
            'SubCategories' => '',
            'Layout' => $layout,
            'Article' => '',
            'Active' => 'Y',
            'Language' => 'en',
        ]
    );
    $refused = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash']
    );
    mysqli_query(
        $connection,
        'DELETE FROM RED_Articles WHERE RecordID='
            . $duplicateTargetPageRecordId
    );
    red_addon_editor_create_test_assert(
        $duplicateInserted
            && empty($refused['ready'])
            && $refused['reason'] === 'target_page_unavailable',
        'ambiguous route ownership cannot produce a placement plan'
    );

    $refused = red_addon_component_editor_publish_preflight(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        99,
        3,
        $updated['stateHash'],
        $updated['packageStateHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['ready'])
            && $refused['reason'] === 'placement_unsupported',
        'the active theme must support the requested destination position'
    );

    mysqli_begin_transaction($connection);
    $refused = red_addon_component_editor_publish_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash'],
        $publishPlan['planHash']
    );
    mysqli_rollback($connection);
    red_addon_editor_create_test_assert(
        empty($refused['placed'])
            && $refused['reason'] === 'transaction_already_active',
        'atomic placement refuses a caller-owned transaction'
    );

    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId . " AND Capability='"
            . mysqli_real_escape_string($connection, $publishPermission) . "'"
    );
    $loaderCallsBeforePublishRunnerRefusal = $loaderCalls;
    $refused = red_addon_component_editor_publish_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash'],
        $publishPlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['placed'])
            && $refused['reason'] === 'permission_denied'
            && $loaderCalls === $loaderCallsBeforePublishRunnerRefusal,
        'atomic placement rechecks the exact publish grant before package loading'
    );
    red_addon_editor_create_test_grant(
        $connection,
        $adminRecordId,
        $publishPermission
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Alias='addon-placement-target-drift'
         WHERE RecordID=$targetPageRecordId"
    );
    $refused = red_addon_component_editor_publish_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash'],
        $publishPlan['planHash']
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Alias='addon-placement-target'
         WHERE RecordID=$targetPageRecordId"
    );
    red_addon_editor_create_test_assert(
        empty($refused['placed']) && $refused['reason'] === 'stale_plan',
        'destination drift invalidates the caller plan before placement'
    );

    $placementFingerprint = red_addon_editor_create_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':',
            (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                SubCategories, Article, PagePosition, PagePositionOrder)
             FROM RED_Articles WHERE RecordID=$contentRecordId),
            (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                SubCategories, Layout, Language, PagePosition)
             FROM RED_Articles WHERE RecordID=$targetPageRecordId),
            (SELECT CONCAT_WS('|', Title, Quantity) FROM `$packageTable`
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Content_Revisions
             WHERE ContentRecordID=$contentRecordId),
            (SELECT COUNT(*) FROM RED_Admin_Activity_Log
             WHERE TargetType='component'
               AND TargetRecordID=$contentRecordId))"
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Content_Revisions ADD CONSTRAINT '
            . '`redcms_component_publish_revision_fail` CHECK '
            . "(`Operation` <> 'move' OR `ContentRecordID` <> "
            . $contentRecordId . ')'
    );
    $refused = red_addon_component_editor_publish_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash'],
        $publishPlan['planHash']
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Content_Revisions DROP CHECK '
            . '`redcms_component_publish_revision_fail`'
    );
    red_addon_editor_create_test_assert(
        empty($refused['placed'])
            && $refused['reason'] === 'revision_failed'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                        SubCategories, Article, PagePosition, PagePositionOrder)
                     FROM RED_Articles WHERE RecordID=$contentRecordId),
                    (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                        SubCategories, Layout, Language, PagePosition)
                     FROM RED_Articles WHERE RecordID=$targetPageRecordId),
                    (SELECT CONCAT_WS('|', Title, Quantity) FROM `$packageTable`
                     WHERE ContentRecordID=$contentRecordId),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE ContentRecordID=$contentRecordId),
                    (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                     WHERE TargetType='component'
                       AND TargetRecordID=$contentRecordId))"
            ) === $placementFingerprint,
        'a forced placement revision failure rolls back source, target, and package state'
    );

    mysqli_query(
        $connection,
        'ALTER TABLE RED_Admin_Activity_Log ADD CONSTRAINT '
            . '`redcms_component_publish_audit_fail` CHECK '
            . "(`EventName` <> 'component.public_placed' "
            . "OR `TargetRecordID` <> $contentRecordId)"
    );
    $refused = red_addon_component_editor_publish_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash'],
        $publishPlan['planHash']
    );
    mysqli_query(
        $connection,
        'ALTER TABLE RED_Admin_Activity_Log DROP CHECK '
            . '`redcms_component_publish_audit_fail`'
    );
    red_addon_editor_create_test_assert(
        empty($refused['placed'])
            && $refused['reason'] === 'audit_failed'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                        SubCategories, Article, PagePosition, PagePositionOrder)
                     FROM RED_Articles WHERE RecordID=$contentRecordId),
                    (SELECT CONCAT_WS('|', Active, Alias, Sections, Categories,
                        SubCategories, Layout, Language, PagePosition)
                     FROM RED_Articles WHERE RecordID=$targetPageRecordId),
                    (SELECT CONCAT_WS('|', Title, Quantity) FROM `$packageTable`
                     WHERE ContentRecordID=$contentRecordId),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE ContentRecordID=$contentRecordId),
                    (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                     WHERE TargetType='component'
                       AND TargetRecordID=$contentRecordId))"
            ) === $placementFingerprint,
        'a forced placement audit failure rolls back parent, revision, package, and destination state'
    );

    $placed = red_addon_component_editor_publish_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash'],
        $publishPlan['planHash']
    );
    red_addon_editor_create_test_assert(
        !empty($placed['placed'])
            && $placed['reason'] === 'placed'
            && $placed['package'] === $packageId
            && $placed['viewPermission'] === $viewPermission
            && $placed['publishPermission'] === $publishPermission
            && $placed['previousParentStateHash'] === $updated['stateHash']
            && $placed['stateHash'] !== $updated['stateHash']
            && $placed['packageStateHash'] === $updated['packageStateHash']
            && $placed['targetStateHash'] === $publishPlan['targetStateHash']
            && $placed['placementValues'] === $publishPlan['placementValues']
            && $placed['revisionId'] > 0
            && $placed['revisionNumber'] === 3
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', EventName, ActorAdminRecordID,
                    TargetType, TargetRecordID)
                 FROM RED_Admin_Activity_Log
                 WHERE TargetType='component'
                   AND TargetRecordID=$contentRecordId"
            ) === 'component.public_placed:' . $adminRecordId
                . ':component:' . $contentRecordId,
        'the exact plan atomically places and activates the component with one move revision and one bounded audit fact'
    );
    red_addon_editor_create_test_assert(
        red_addon_editor_create_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':', a.Active, IF(a.Alias='', 'empty', a.Alias),
                IF(a.Sections='', 'empty', a.Sections),
                IF(a.Categories='', 'empty', a.Categories),
                IF(a.SubCategories='', 'empty', a.SubCategories),
                a.Article, a.PagePosition, a.PagePositionOrder,
                cr.RevisionNumber, cr.Operation, cr.ActorAdminRecordID,
                p.Title, p.Quantity,
                (SELECT Alias FROM RED_Articles
                 WHERE RecordID=$targetPageRecordId))
             FROM RED_Articles a
             INNER JOIN RED_Content_Revisions cr
               ON cr.ContentRecordID=a.RecordID AND cr.RevisionNumber=3
             INNER JOIN `$packageTable` p ON p.ContentRecordID=a.RecordID
             WHERE a.RecordID=$contentRecordId"
        ) === 'Y:empty:empty:empty:empty:addon-placement-target:1:3:3:move:'
            . $adminRecordId . ':Package row:5:addon-placement-target',
        'placement changes only the derived parent fields and preserves package and target data'
    );

    $revisionCountAfterPlacement = red_addon_editor_create_test_scalar(
        $connection,
        "SELECT COUNT(*) FROM RED_Content_Revisions
         WHERE ContentRecordID=$contentRecordId"
    );
    $refused = red_addon_component_editor_publish_values(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $targetPageRecordId,
        1,
        3,
        $updated['stateHash'],
        $updated['packageStateHash'],
        $publishPlan['planHash']
    );
    red_addon_editor_create_test_assert(
        empty($refused['placed'])
            && $refused['reason'] === 'parent_state_unsupported'
            && red_addon_editor_create_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID=$contentRecordId"
            ) === $revisionCountAfterPlacement,
        'a committed public-placement plan is single-use'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Articles
         SET Sections='', Categories='', SubCategories='', Article='',
             PagePosition=0, PagePositionOrder=0, Active='N'
         WHERE RecordID=$contentRecordId"
    );
    $fixtureRestored = red_addon_component_editor_parent_revision(
        $connection,
        $contentRecordId,
        $adminRecordId,
        'save'
    );
    $restoredParentState = red_addon_component_editor_parent_state(
        $connection,
        $manifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_create_test_assert(
        is_array($fixtureRestored)
            && $fixtureRestored['revisionNumber'] === 4
            && !empty($restoredParentState['loaded'])
            && $restoredParentState['packageStateHash']
                === $updated['packageStateHash'],
        'the disposable fixture returns to a revision-backed inactive shell for delete testing'
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
            ) === '1:1:4:1:0',
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
            ) === '1:1:5:1:1',
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
            && $deleteFingerprint === '1:1:5:1:1',
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
            ) === '0:0:6:2:0',
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
            . ':6:delete:' . $deleteParentState['stateHash'],
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
            ) === '0:0:6:2:0',
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
        $targetPageRecordId,
        $duplicateTargetPageRecordId,
        $destinationRouteRecordId,
        $destinationComponentRecordId,
        $coordinatorRouteRecordId,
        $coordinatorComponentRecordId,
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
                (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE TargetType='component'
                   AND TargetRecordID=$contentRecordId),
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID=$destinationRouteRecordId),
                (SELECT COUNT(*) FROM RED_Content_Revisions
                 WHERE ContentRecordID=$destinationRouteRecordId),
                (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE TargetType='article'
                   AND TargetRecordID=$destinationRouteRecordId),
                (SELECT COUNT(*)
                 FROM RED_Addon_Component_Destination_Executions
                 WHERE PackageID='"
                    . mysqli_real_escape_string($connection, $packageId) . "'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"
                    . $packageTable . "'))"
        ) === '0:0:0:0:0:0:0:0:0',
        'the disposable creation fixture cleans all database state'
    );
} catch (Throwable $throwable) {
    red_addon_editor_create_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $targetPageRecordId,
        $duplicateTargetPageRecordId,
        $destinationRouteRecordId,
        $destinationComponentRecordId,
        $coordinatorRouteRecordId,
        $coordinatorComponentRecordId,
        $packageId,
        $packageTable
    );
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

fwrite(
    STDOUT,
    'Add-on destination-checkpoint/component-creation/public-placement/search-completion/atomic-delete self-test passed ('
        . $assertions . " assertions).\n"
);

?>
