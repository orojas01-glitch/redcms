<?php
/**
 * Disposable Store Lite enabled-installation fixture for browser rehearsal.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$command = $argv[1] ?? '';
$projectRoot = realpath((string) getenv('RED_STORE_LITE_PROJECT_ROOT'));
$databaseName = (string) getenv('RED_DB_NAME');
$username = 'store_lite_browser';
$password = 'StoreLiteBrowser-2026!';
$actorRecordId = 1;
$packageId = 'redcms.store-lite';

if (!in_array($command, ['prepare', 'verify'], true)
    || !is_string($projectRoot)
    || !is_dir($projectRoot)
    || preg_match(
        '/\Aredcms_store_lite_browser_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(STDERR, "Store Lite browser fixture refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_registry_helpers.php';
require_once $projectRoot . '/includes/addon_public_mutation_execution_helpers.php';
require_once $projectRoot . '/includes/addon_public_mutation_subject_helpers.php';
require_once $projectRoot . '/addons/redcms/store-lite/src/CatalogPersistence.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$assertions = 0;

function red_store_lite_browser_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_store_lite_browser_scalar(mysqli $connection, string $sql): string
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_store_lite_browser_insert_registry(
    mysqli $connection,
    string $projectRoot,
    int $actorRecordId
): array {
    $catalog = red_addon_discover(
        $projectRoot,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $package = $catalog['packages']['redcms.store-lite'] ?? null;
    $snapshot = is_array($package)
        ? red_addon_registry_snapshot($package)
        : null;
    red_store_lite_browser_assert(
        !empty($catalog['valid']) && is_array($snapshot),
        'staged Store Lite package is validated before registry insertion'
    );

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations
            (PackageID, PackageVersion, PackageType, ManifestSHA256,
             InventorySHA256, LifecycleState,
             InstalledByAdminRecordID, UpdatedByAdminRecordID)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $state = 'enabled';
    mysqli_stmt_bind_param(
        $statement,
        'ssssssii',
        $snapshot['id'],
        $snapshot['version'],
        $snapshot['type'],
        $snapshot['manifestSha256'],
        $snapshot['inventorySha256'],
        $state,
        $actorRecordId,
        $actorRecordId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $migrationStatement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Migrations
            (PackageID, MigrationID, MigrationPath, Checksum,
             AppliedByAdminRecordID, ExecutionMs)
         VALUES (?, ?, ?, ?, ?, 0)'
    );
    foreach ($snapshot['migrations'] as $migration) {
        mysqli_stmt_bind_param(
            $migrationStatement,
            'ssssi',
            $snapshot['id'],
            $migration['id'],
            $migration['path'],
            $migration['sha256'],
            $actorRecordId
        );
        mysqli_stmt_execute($migrationStatement);
    }
    mysqli_stmt_close($migrationStatement);

    $settingKey = 'catalog.currency';
    $valueType = 'text';
    $valueJson = json_encode('USD', JSON_THROW_ON_ERROR);
    $settingStatement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings
            (PackageID, SettingKey, ValueType, ValueJSON,
             SecretReference, UpdatedByAdminRecordID)
         VALUES (?, ?, ?, ?, NULL, ?)'
    );
    mysqli_stmt_bind_param(
        $settingStatement,
        'ssssi',
        $snapshot['id'],
        $settingKey,
        $valueType,
        $valueJson,
        $actorRecordId
    );
    mysqli_stmt_execute($settingStatement);
    mysqli_stmt_close($settingStatement);
    return $snapshot;
}

function red_store_lite_browser_seed_products(mysqli $connection): array
{
    $banana = [
        'id' => 'banana-bunch',
        'type' => 'simple',
        'title' => 'Banana bunch',
        'summary' => 'Six ripe bananas.',
        'currency' => 'USD',
        'state' => 'published',
        'availability' => 'available',
        'imageRef' => 'media:banana-bunch.jpg',
        'sku' => 'BANANA-BUNCH',
        'priceMinor' => 599,
        'stock' => 40,
    ];
    $shirt = [
        'id' => 'classic-shirt',
        'type' => 'variable',
        'title' => 'Classic T-shirt',
        'summary' => 'A soft everyday shirt.',
        'currency' => 'USD',
        'state' => 'draft',
        'availability' => 'available',
        'imageRef' => 'media:classic-shirt.jpg',
        'options' => [[
            'key' => 'size',
            'label' => 'Size',
            'values' => [
                ['id' => 'small', 'label' => 'Small'],
                ['id' => 'large', 'label' => 'Large'],
            ],
        ], [
            'key' => 'color',
            'label' => 'Color',
            'values' => [
                ['id' => 'black', 'label' => 'Black'],
                ['id' => 'white', 'label' => 'White'],
            ],
        ]],
        'variants' => [[
            'id' => 'small-black',
            'sku' => 'SHIRT-S-BLACK',
            'options' => ['size' => 'small', 'color' => 'black'],
            'priceMinor' => 2499,
            'availability' => 'available',
            'stock' => 8,
            'imageRef' => 'media:shirt-black.jpg',
        ], [
            'id' => 'large-white',
            'sku' => 'SHIRT-L-WHITE',
            'options' => ['size' => 'large', 'color' => 'white'],
            'priceMinor' => 2699,
            'availability' => 'available',
            'stock' => 5,
            'imageRef' => null,
        ]],
    ];
    $bananaResult = RED_CMS_Store_Lite_Catalog_Persistence::create(
        $connection,
        $banana,
        'USD'
    );
    $shirtResult = RED_CMS_Store_Lite_Catalog_Persistence::create(
        $connection,
        $shirt,
        'USD'
    );
    red_store_lite_browser_assert(
        ($bananaResult['status'] ?? '') === 'created'
            && ($shirtResult['status'] ?? '') === 'created',
        'simple and variable fixture products are created atomically'
    );
    return [$bananaResult, $shirtResult];
}

function red_store_lite_browser_mutation_evidence(
    mysqli $connection,
    array $plan
): array {
    $subject = red_addon_public_mutation_subject_issue($connection);
    $csrf = red_addon_public_mutation_csrf_issue($connection, $subject, $plan);
    $key = red_addon_public_mutation_idempotency_issue(
        $connection,
        $subject,
        $plan
    );
    red_store_lite_browser_assert(
        !empty($subject['valid'])
            && !empty($csrf['valid'])
            && !empty($key['valid']),
        'core issues isolated subject, CSRF, and idempotency evidence'
    );
    return [$subject, $csrf, $key];
}

function red_store_lite_browser_prove_cart_mutation(
    mysqli $connection,
    string $projectRoot
): void {
    $packageId = 'redcms.store-lite';
    $routeId = 'redcms.store-lite/cart-intent';
    $mutationId = 'redcms.store-lite/add-to-cart';
    $catalog = red_addon_discover(
        $projectRoot,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $package = $catalog['packages'][$packageId] ?? null;
    red_store_lite_browser_assert(
        !empty($catalog['valid']) && is_array($package),
        'the installed package is rediscovered from the isolated project'
    );
    $registry = red_addon_runtime_register_package($package);
    red_addon_runtime_set_request_context(
        new RED_Addon_Runtime_Context(
            [$packageId],
            [$packageId => $registry]
        )
    );
    $manifest = $package['manifest'] ?? [];
    $plan = red_addon_public_mutation_declaration_preflight(
        $manifest,
        $routeId,
        $mutationId
    );
    red_store_lite_browser_assert(
        red_addon_public_mutation_declaration_preflight_is_valid($plan),
        'Store Lite exposes one closed Add-to-cart declaration plan'
    );

    [$bananaSubject, $bananaCsrf, $bananaKey] =
        red_store_lite_browser_mutation_evidence($connection, $plan);
    $banana = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $bananaSubject,
        $bananaCsrf['token'],
        $bananaKey['key'],
        ['product' => 'banana-bunch', 'quantity' => 2]
    );
    $bananaReplay = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $bananaSubject,
        $bananaCsrf['token'],
        $bananaKey['key'],
        ['product' => 'banana-bunch', 'quantity' => 2]
    );
    $bananaConflict = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $bananaSubject,
        $bananaCsrf['token'],
        $bananaKey['key'],
        ['product' => 'banana-bunch', 'quantity' => 3]
    );
    red_store_lite_browser_assert(
        !empty($banana['completed'])
            && ($banana['outcome'] ?? '') === 'accepted'
            && !empty($bananaReplay['replayed'])
            && ($bananaConflict['reason'] ?? '') === 'idempotency_conflict',
        'simple-product Add-to-cart accepts once, replays once, and refuses key reuse'
    );

    red_store_lite_browser_assert(
        mysqli_query(
            $connection,
            "UPDATE RED_Addon_StoreLite_Products
             SET State='published'
             WHERE ProductID='classic-shirt' AND State='draft'"
        ) === true
            && mysqli_affected_rows($connection) === 1,
        'the variable fixture is published only for the isolated mutation proof'
    );
    [$shirtSubject, $shirtCsrf, $shirtKey] =
        red_store_lite_browser_mutation_evidence($connection, $plan);
    $shirt = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $shirtSubject,
        $shirtCsrf['token'],
        $shirtKey['key'],
        [
            'product' => 'classic-shirt',
            'quantity' => 1,
            'variant' => 'small-black',
        ]
    );
    red_store_lite_browser_assert(
        !empty($shirt['completed']) && ($shirt['outcome'] ?? '') === 'accepted',
        'variable-product Add-to-cart accepts one exact server-resolved variant'
    );

    [$invalidSubject, $invalidCsrf, $invalidKey] =
        red_store_lite_browser_mutation_evidence($connection, $plan);
    $invalid = red_addon_public_mutation_execute(
        $connection,
        $manifest,
        $routeId,
        $mutationId,
        $invalidSubject,
        $invalidCsrf['token'],
        $invalidKey['key'],
        [
            'product' => 'classic-shirt',
            'quantity' => 1,
            'variant' => 'not-a-real-variant',
        ]
    );
    red_store_lite_browser_assert(
        empty($invalid['completed'])
            && ($invalid['reason'] ?? '') === 'handler_failed',
        'unresolved variant fails closed through the core runner'
    );
    red_store_lite_browser_assert(
        mysqli_query(
            $connection,
            "UPDATE RED_Addon_StoreLite_Products
             SET State='draft'
             WHERE ProductID='classic-shirt' AND State='published'"
        ) === true
            && mysqli_affected_rows($connection) === 1,
        'the variable fixture returns to its browser-facing draft state'
    );
    red_store_lite_browser_assert(
        red_store_lite_browser_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Carts),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Cart_Lines),
                (SELECT COALESCE(SUM(Quantity), 0)
                 FROM RED_Addon_StoreLite_Cart_Lines),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Cart_Activity),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite'
                   AND EventName='addon.public-mutation.completed'))"
        ) === '2:2:3:2:2:2',
        'package state, replay ledger, and value-free core audit commit atomically'
    );
}

try {
    if ($command === 'prepare') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        red_store_lite_browser_assert(
            is_string($hash) && strlen($hash) >= 60,
            'disposable administrator password is hashed'
        );
        $adminStatement = mysqli_prepare(
            $connection,
            'UPDATE RED_Admin
             SET Username=?, Password=?, Administrator=?, Alias=?,
                 AdminType=?, AdminComponents=?, AdminTools=?, Email=?
             WHERE RecordID=?'
        );
        $administrator = 'Store Lite Browser Rehearsal';
        $alias = 'Store QA';
        $adminType = 'webmaster';
        $components = '100,102,103,104,105,107,111,116,117';
        $tools = '1,2';
        $email = '';
        mysqli_stmt_bind_param(
            $adminStatement,
            'ssssssssi',
            $username,
            $hash,
            $administrator,
            $alias,
            $adminType,
            $components,
            $tools,
            $email,
            $actorRecordId
        );
        mysqli_stmt_execute($adminStatement);
        red_store_lite_browser_assert(
            mysqli_stmt_affected_rows($adminStatement) === 1,
            'seed administrator is replaced only inside the disposable database'
        );
        mysqli_stmt_close($adminStatement);

        $capability = 'store.products.manage';
        $capabilityStatement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Admin_Capabilities
                (AdminRecordID, Capability, GrantedByAdminRecordID)
             VALUES (?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $capabilityStatement,
            'isi',
            $actorRecordId,
            $capability,
            $actorRecordId
        );
        mysqli_stmt_execute($capabilityStatement);
        mysqli_stmt_close($capabilityStatement);

        $snapshot = red_store_lite_browser_insert_registry(
            $connection,
            $projectRoot,
            $actorRecordId
        );
        red_store_lite_browser_seed_products($connection);
        red_store_lite_browser_prove_cart_mutation($connection, $projectRoot);
        red_store_lite_browser_assert(
            red_store_lite_browser_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Products),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Product_Options),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Product_Variants),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Product_Placements),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Cart_Placements),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Product_Activity),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.store-lite'))"
            ) === '2:2:2:0:0:0:2',
            'fixture contains two products, no component placement, and only two core mutation audits'
        );
        echo json_encode([
            'ok' => true,
            'packageVersion' => $snapshot['version'],
            'username' => $username,
            'productCount' => 2,
            'assertions' => $assertions,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } else {
        $banana = RED_CMS_Store_Lite_Catalog_Persistence::read(
            $connection,
            'banana-bunch',
            'USD'
        );
        $shirt = RED_CMS_Store_Lite_Catalog_Persistence::read(
            $connection,
            'classic-shirt',
            'USD'
        );
        $created = RED_CMS_Store_Lite_Catalog_Persistence::read(
            $connection,
            'browser-created-shirt',
            'USD'
        );
        red_store_lite_browser_assert(
            ($banana['status'] ?? '') === 'found'
                && ($banana['product']['title'] ?? '')
                    === 'Banana bunch browser-verified'
                && ($banana['product']['priceMinor'] ?? null) === 649
                && ($banana['product']['stock'] ?? null) === 39,
            'browser Save persisted the exact simple-product changes'
        );
        red_store_lite_browser_assert(
            ($shirt['status'] ?? '') === 'found'
                && ($shirt['product']['title'] ?? '') === 'Classic T-shirt'
                && count($shirt['product']['options'] ?? []) === 2
                && count($shirt['product']['variants'] ?? []) === 2,
            'variable T-shirt remains unchanged with its bounded graph'
        );
        red_store_lite_browser_assert(
            ($created['status'] ?? '') === 'found'
                && ($created['product']['title'] ?? '')
                    === 'Browser-created shirt'
                && ($created['product']['state'] ?? '') === 'draft'
                && ($created['product']['availability'] ?? '')
                    === 'unavailable'
                && ($created['product']['sku'] ?? '') === 'BROWSER-SHIRT'
                && ($created['product']['priceMinor'] ?? null) === 3200
                && ($created['product']['stock'] ?? null) === 12,
            'browser Create persisted one unavailable simple draft product'
        );
        red_store_lite_browser_assert(
            red_store_lite_browser_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*)
                     FROM RED_Addon_StoreLite_Product_Activity
                     WHERE EventName='product.created'
                       AND ProductID='browser-created-shirt'
                       AND ActorAdminRecordID=1),
                    (SELECT COUNT(*)
                     FROM RED_Addon_StoreLite_Product_Activity
                     WHERE EventName='product.updated'
                       AND ProductID='banana-bunch'
                       AND ActorAdminRecordID=1),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Activity_Log
                     WHERE EventName='addon.form.saved'
                       AND PackageID='redcms.store-lite'
                       AND ActorAdminRecordID=1
                       AND Result='succeeded'
                       AND DetailCode='form_saved'),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Activity_Log
                     WHERE EventName='addon.form.created'
                       AND PackageID='redcms.store-lite'
                       AND ActorAdminRecordID=1
                       AND Result='succeeded'
                       AND DetailCode='form_created'))"
            ) === '1:1:1:1',
            'package and core recorded one browser create and one update'
        );
        red_store_lite_browser_assert(
            red_store_lite_browser_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*)
                     FROM RED_Addon_StoreLite_Product_Placements p
                     INNER JOIN RED_Articles a
                       ON a.RecordID=p.ContentRecordID
                     INNER JOIN RED_Addon_StoreLite_Products product
                       ON product.RecordID=p.ProductRecordID
                     WHERE product.ProductID='banana-bunch'
                       AND a.Component='redcms.store-lite/product'
                       AND LOWER(a.Sections)='home'
                       AND a.HomePosition=1
                       AND a.HomePositionOrder=90
                       AND a.PagePosition=0
                       AND a.Active='Y'),
                    (SELECT COUNT(*)
                     FROM RED_Addon_StoreLite_Cart_Placements p
                     INNER JOIN RED_Articles a
                       ON a.RecordID=p.ContentRecordID
                     WHERE p.Title='Shopping cart'
                       AND a.Component='redcms.store-lite/cart'
                       AND LOWER(a.Sections)='home'
                       AND a.HomePosition=1
                       AND a.HomePositionOrder=92
                       AND a.PagePosition=0
                       AND a.Active='Y'),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE Operation='create'
                       AND ContentType='redcms.store-lite/product'),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE Operation='create'
                       AND ContentType='redcms.store-lite/cart'),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE Operation='move'
                       AND ContentType='redcms.store-lite/product'),
                    (SELECT COUNT(*) FROM RED_Content_Revisions
                     WHERE Operation='move'
                       AND ContentType='redcms.store-lite/cart'),
                    (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
                     WHERE PackageID='redcms.store-lite'
                       AND ComponentID='redcms.store-lite/product'
                       AND Operation='baseline'),
                    (SELECT COUNT(*) FROM RED_Addon_Component_Revisions
                     WHERE PackageID='redcms.store-lite'
                       AND ComponentID='redcms.store-lite/cart'
                       AND Operation='baseline'),
                    (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                     WHERE EventName='component.public_placed'
                       AND TargetType='component'
                       AND ActorAdminRecordID=1))"
            ) === '1:1:1:1:1:1:1:1:2',
            'browser Product and Cart creation/placement retain exact revision and audit evidence'
        );
        red_store_lite_browser_assert(
            red_store_lite_browser_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Carts),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Cart_Lines),
                    (SELECT COALESCE(SUM(Quantity), 0)
                     FROM RED_Addon_StoreLite_Cart_Lines),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Cart_Activity),
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions))"
            ) === '2:2:3:2:2',
            'browser activity did not alter the isolated cart-runner proof'
        );
        echo json_encode([
            'ok' => true,
            'bananaTitle' => $banana['product']['title'],
            'bananaPriceMinor' => $banana['product']['priceMinor'],
            'bananaStock' => $banana['product']['stock'],
            'shirtVariantCount' => count($shirt['product']['variants']),
            'createdProductId' => $created['product']['id'],
            'assertions' => $assertions,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
} finally {
    $db->close();
}
