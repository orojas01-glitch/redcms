<?php
/**
 * Disposable Store Lite Release C3 two-client isolation proof.
 *
 * The shell wrapper creates two fresh databases and stages clean core plus the
 * external package. This command installs and enables the same package in both
 * databases, but gives each installation distinct settings and business data.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath((string) getenv('RED_STORE_LITE_PROJECT_ROOT'));
$clientADatabase = (string) getenv('RED_DB_NAME');
$clientBDatabase = (string) getenv('RED_STORE_LITE_CLIENT_B_DATABASE');
if (!is_string($projectRoot)
    || !is_dir($projectRoot)
    || preg_match(
        '/\Aredcms_sl_iso_a_[A-Za-z0-9_]+\z/D',
        $clientADatabase
    ) !== 1
    || preg_match(
        '/\Aredcms_sl_iso_b_[A-Za-z0-9_]+\z/D',
        $clientBDatabase
    ) !== 1
    || hash_equals($clientADatabase, $clientBDatabase)
) {
    fwrite(STDERR, "Store Lite two-client rehearsal refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_install_helpers.php';
require_once $projectRoot . '/includes/addon_enable_helpers.php';
require_once $projectRoot . '/includes/addon_disable_helpers.php';
require_once $projectRoot . '/addons/redcms/store-lite/src/CatalogPersistence.php';

$clientA = new connection(DBHOST, DBUSER, DBPASS, $clientADatabase);
$clientB = new connection(DBHOST, DBUSER, DBPASS, $clientBDatabase);
$connections = [
    'client-a' => $clientA->connection,
    'client-b' => $clientB->connection,
];
$packageId = 'redcms.store-lite';
$actorId = 1;
$assertions = 0;

function red_store_lite_c3_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_store_lite_c3_scalar(mysqli $connection, string $sql): string
{
    $query = mysqli_query($connection, $sql);
    if (!$query) {
        throw new RuntimeException(
            'Store Lite C3 query failed: ' . mysqli_error($connection)
        );
    }
    $row = mysqli_fetch_row($query);
    mysqli_free_result($query);
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_store_lite_c3_rows(mysqli $connection, string $sql): array
{
    $query = mysqli_query($connection, $sql);
    if (!$query) {
        throw new RuntimeException(
            'Store Lite C3 query failed: ' . mysqli_error($connection)
        );
    }
    $rows = [];
    while ($row = mysqli_fetch_row($query)) {
        $rows[] = array_map(
            static fn ($value): string => $value === null ? '' : (string) $value,
            $row
        );
    }
    mysqli_free_result($query);
    return $rows;
}

function red_store_lite_c3_data_fingerprint(
    mysqli $connection,
    string $packageId
): string {
    $escaped = mysqli_real_escape_string($connection, $packageId);
    $material = [
        'settings' => red_store_lite_c3_rows(
            $connection,
            "SELECT SettingKey, ValueType, ValueJSON,
                    COALESCE(SecretReference, '')
             FROM RED_Addon_Settings
             WHERE PackageID='$escaped'
             ORDER BY SettingKey"
        ),
        'products' => red_store_lite_c3_rows(
            $connection,
            "SELECT ProductID, ProductType, Title, COALESCE(Summary, ''),
                    Currency, State, Availability,
                    COALESCE(ImageReference, ''), COALESCE(SKU, ''),
                    COALESCE(PriceMinor, -1), COALESCE(Stock, -1)
             FROM RED_Addon_StoreLite_Products
             ORDER BY ProductID"
        ),
    ];
    return hash(
        'sha256',
        json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        )
    );
}

function red_store_lite_c3_prepare_owner(
    mysqli $connection,
    int $actorId
): void {
    mysqli_query(
        $connection,
        "INSERT IGNORE INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    foreach (['addons.install', 'addons.enable', 'addons.disable'] as $capability) {
        $escaped = mysqli_real_escape_string($connection, $capability);
        mysqli_query(
            $connection,
            "INSERT IGNORE INTO RED_Admin_Capabilities
             (AdminRecordID, Capability, GrantedByAdminRecordID)
             VALUES ($actorId, '$escaped', $actorId)"
        );
    }
}

function red_store_lite_c3_store_settings(
    mysqli $connection,
    string $packageId,
    int $actorId,
    array $settings
): void {
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings
            (PackageID, SettingKey, ValueType, ValueJSON,
             SecretReference, UpdatedByAdminRecordID)
         VALUES (?, ?, ?, ?, NULL, ?)'
    );
    if (!$statement) {
        throw new RuntimeException('Could not prepare Store Lite settings.');
    }
    foreach ($settings as [$key, $type, $value]) {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        mysqli_stmt_bind_param(
            $statement,
            'ssssi',
            $packageId,
            $key,
            $type,
            $encoded,
            $actorId
        );
        if (!mysqli_stmt_execute($statement)) {
            mysqli_stmt_close($statement);
            throw new RuntimeException('Could not store Store Lite settings.');
        }
    }
    mysqli_stmt_close($statement);
}

function red_store_lite_c3_product(
    string $id,
    string $title,
    string $currency,
    string $sku,
    int $priceMinor,
    int $stock
): array {
    return [
        'id' => $id,
        'type' => 'simple',
        'title' => $title,
        'summary' => 'Release C3 isolated product.',
        'currency' => $currency,
        'state' => 'published',
        'availability' => 'available',
        'imageRef' => null,
        'sku' => $sku,
        'priceMinor' => $priceMinor,
        'stock' => $stock,
        'options' => [],
        'variants' => [],
    ];
}

try {
    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? null;
    $snapshot = is_array($package)
        ? red_addon_registry_snapshot($package)
        : null;
    red_store_lite_c3_assert(
        !empty($catalog['valid'])
            && is_array($package)
            && !empty($package['valid'])
            && is_array($snapshot)
            && $snapshot['version'] === '0.1.29'
            && count($snapshot['migrations']) === 10,
        'trusted Store Lite 0.1.29 has ten manifest-ordered migrations'
    );
    red_store_lite_c3_assert(
        red_store_lite_c3_scalar($connections['client-a'], 'SELECT DATABASE()')
                === $clientADatabase
            && red_store_lite_c3_scalar(
                $connections['client-b'],
                'SELECT DATABASE()'
            ) === $clientBDatabase,
        'both connections are bound to distinct approved client databases'
    );

    $installPlans = [];
    $installResults = [];
    foreach ($connections as $client => $connection) {
        red_store_lite_c3_prepare_owner($connection, $actorId);
        $installPlans[$client] = red_addon_install_plan(
            $connection,
            $package,
            $actorId,
            false,
            $catalog
        );
        $installResults[$client] = red_addon_install_package(
            $connection,
            $packageId,
            $projectRoot,
            $actorId,
            $installPlans[$client]['planSha256'] ?? ''
        );
    }
    red_store_lite_c3_assert(
        !empty($installPlans['client-a']['valid'])
            && !empty($installPlans['client-b']['valid'])
            && $installPlans['client-a']['database'] === $clientADatabase
            && $installPlans['client-b']['database'] === $clientBDatabase
            && count($installPlans['client-a']['pendingMigrations']) === 10
            && count($installPlans['client-b']['pendingMigrations']) === 10,
        'each installation receives its own database-bound ten-migration plan'
    );
    red_store_lite_c3_assert(
        ($installResults['client-a']['status'] ?? '') === 'installed_disabled'
            && ($installResults['client-b']['status'] ?? '') === 'installed_disabled'
            && count($installResults['client-a']['appliedMigrations'] ?? []) === 10
            && count($installResults['client-b']['appliedMigrations'] ?? []) === 10
            && red_store_lite_c3_scalar(
                $connections['client-a'],
                "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Migrations
                     WHERE PackageID='$packageId'),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME LIKE 'RED_Addon_StoreLite\\_%'))
                 FROM RED_Addon_Installations WHERE PackageID='$packageId'"
            ) === '0.1.29:installed_disabled:10:15'
            && red_store_lite_c3_scalar(
                $connections['client-b'],
                "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Migrations
                     WHERE PackageID='$packageId'),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME LIKE 'RED_Addon_StoreLite\\_%'))
                 FROM RED_Addon_Installations WHERE PackageID='$packageId'"
            ) === '0.1.29:installed_disabled:10:15',
        'both isolated clients install the exact current package and schema'
    );

    red_store_lite_c3_store_settings(
        $connections['client-a'],
        $packageId,
        $actorId,
        [
            ['catalog.currency', 'text', 'USD'],
            ['checkout.delivery-enabled', 'boolean', false],
            ['checkout.delivery-fee-minor', 'integer', 0],
            ['checkout.pay-on-receipt-enabled', 'boolean', true],
            ['checkout.pickup-enabled', 'boolean', true],
        ]
    );
    red_store_lite_c3_store_settings(
        $connections['client-b'],
        $packageId,
        $actorId,
        [
            ['catalog.currency', 'text', 'COP'],
            ['checkout.delivery-enabled', 'boolean', true],
            ['checkout.delivery-fee-minor', 'integer', 12000],
            ['checkout.pay-on-receipt-enabled', 'boolean', true],
            ['checkout.pickup-enabled', 'boolean', false],
        ]
    );
    red_store_lite_c3_assert(
        red_store_lite_c3_scalar(
            $connections['client-a'],
            "SELECT GROUP_CONCAT(CONCAT(SettingKey, '=', ValueJSON)
                    ORDER BY SettingKey SEPARATOR '|')
             FROM RED_Addon_Settings WHERE PackageID='$packageId'"
        ) === 'catalog.currency="USD"|checkout.delivery-enabled=false|checkout.delivery-fee-minor=0|checkout.pay-on-receipt-enabled=true|checkout.pickup-enabled=true'
            && red_store_lite_c3_scalar(
                $connections['client-b'],
                "SELECT GROUP_CONCAT(CONCAT(SettingKey, '=', ValueJSON)
                        ORDER BY SettingKey SEPARATOR '|')
                 FROM RED_Addon_Settings WHERE PackageID='$packageId'"
            ) === 'catalog.currency="COP"|checkout.delivery-enabled=true|checkout.delivery-fee-minor=12000|checkout.pay-on-receipt-enabled=true|checkout.pickup-enabled=false',
        'client settings remain distinct for currency and fulfillment'
    );

    $productA = red_store_lite_c3_product(
        'client-a-shirt',
        'Client A shirt',
        'USD',
        'CLIENT-A-SHIRT',
        2500,
        12
    );
    $productB = red_store_lite_c3_product(
        'client-b-coffee',
        'Client B coffee',
        'COP',
        'CLIENT-B-COFFEE',
        3200000,
        40
    );
    $createdA = RED_CMS_Store_Lite_Catalog_Persistence::create(
        $connections['client-a'],
        $productA,
        'USD'
    );
    $createdB = RED_CMS_Store_Lite_Catalog_Persistence::create(
        $connections['client-b'],
        $productB,
        'COP'
    );
    red_store_lite_c3_assert(
        ($createdA['status'] ?? '') === 'created'
            && ($createdB['status'] ?? '') === 'created',
        'each client creates one package-owned product in its own database'
    );
    red_store_lite_c3_assert(
        (RED_CMS_Store_Lite_Catalog_Persistence::read(
            $connections['client-a'],
            'client-a-shirt',
            'USD'
        )['status'] ?? '') === 'found'
            && (RED_CMS_Store_Lite_Catalog_Persistence::read(
                $connections['client-b'],
                'client-b-coffee',
                'COP'
            )['status'] ?? '') === 'found'
            && (RED_CMS_Store_Lite_Catalog_Persistence::read(
                $connections['client-a'],
                'client-b-coffee',
                'USD'
            )['status'] ?? '') === 'not_found'
            && (RED_CMS_Store_Lite_Catalog_Persistence::read(
                $connections['client-b'],
                'client-a-shirt',
                'COP'
            )['status'] ?? '') === 'not_found',
        'neither client can read the other client product identifier'
    );

    $enablePlans = [];
    $enableResults = [];
    foreach ($connections as $client => $connection) {
        $enablePlans[$client] = red_addon_enable_transition_plan(
            $connection,
            $package,
            $actorId,
            $catalog
        );
        $enableResults[$client] = red_addon_enable_package(
            $connection,
            $packageId,
            $projectRoot,
            $actorId,
            $enablePlans[$client]['planSha256'] ?? ''
        );
    }
    red_store_lite_c3_assert(
        !empty($enablePlans['client-a']['transitionReady'])
            && !empty($enablePlans['client-b']['transitionReady'])
            && $enablePlans['client-a']['database'] === $clientADatabase
            && $enablePlans['client-b']['database'] === $clientBDatabase
            && !hash_equals(
                $enablePlans['client-a']['planSha256'],
                $enablePlans['client-b']['planSha256']
            ),
        'enablement plans bind the same package to different client databases'
    );
    $registrarEvidence = (string) (
        $enableResults['client-a']['registrarEvidenceSha256'] ?? ''
    );
    red_store_lite_c3_assert(
        ($enableResults['client-a']['status'] ?? '') === 'enabled'
            && ($enableResults['client-b']['status'] ?? '') === 'enabled'
            && red_addon_valid_sha256($registrarEvidence)
            && hash_equals(
                $registrarEvidence,
                (string) ($enableResults['client-b']['registrarEvidenceSha256'] ?? '')
            ),
        'both clients enable independently with identical package evidence'
    );
    $runtimeA = red_addon_runtime_bootstrap(
        $connections['client-a'],
        $projectRoot
    );
    $runtimeB = red_addon_runtime_bootstrap(
        $connections['client-b'],
        $projectRoot
    );
    red_store_lite_c3_assert(
        $runtimeA['context']->handler(
            'components',
            'redcms.store-lite/product'
        ) !== null
            && $runtimeA['context']->handler(
                'publicMutationHandlers',
                'redcms.store-lite/create-guest-order'
            ) !== null
            && $runtimeB['context']->handler(
                'components',
                'redcms.store-lite/product'
            ) !== null
            && $runtimeB['context']->handler(
                'publicMutationHandlers',
                'redcms.store-lite/create-guest-order'
            ) !== null,
        'each enabled client builds its own complete runtime context'
    );

    $clientBBefore = red_store_lite_c3_data_fingerprint(
        $connections['client-b'],
        $packageId
    );
    $readA = RED_CMS_Store_Lite_Catalog_Persistence::read(
        $connections['client-a'],
        'client-a-shirt',
        'USD'
    );
    $changedAProduct = $readA['product'];
    $changedAProduct['title'] = 'Client A shirt updated';
    $changedA = RED_CMS_Store_Lite_Catalog_Persistence::replace(
        $connections['client-a'],
        $changedAProduct,
        'USD',
        (string) $readA['stateSha256']
    );
    red_store_lite_c3_assert(
        ($changedA['status'] ?? '') === 'updated'
            && hash_equals(
                $clientBBefore,
                red_store_lite_c3_data_fingerprint(
                    $connections['client-b'],
                    $packageId
                )
            )
            && (RED_CMS_Store_Lite_Catalog_Persistence::read(
                $connections['client-b'],
                'client-a-shirt',
                'COP'
            )['status'] ?? '') === 'not_found',
        'mutating client A leaves client B settings and catalog unchanged'
    );

    $clientABefore = red_store_lite_c3_data_fingerprint(
        $connections['client-a'],
        $packageId
    );
    $readB = RED_CMS_Store_Lite_Catalog_Persistence::read(
        $connections['client-b'],
        'client-b-coffee',
        'COP'
    );
    $changedBProduct = $readB['product'];
    $changedBProduct['stock'] = 39;
    $changedB = RED_CMS_Store_Lite_Catalog_Persistence::replace(
        $connections['client-b'],
        $changedBProduct,
        'COP',
        (string) $readB['stateSha256']
    );
    red_store_lite_c3_assert(
        ($changedB['status'] ?? '') === 'updated'
            && hash_equals(
                $clientABefore,
                red_store_lite_c3_data_fingerprint(
                    $connections['client-a'],
                    $packageId
                )
            )
            && (RED_CMS_Store_Lite_Catalog_Persistence::read(
                $connections['client-a'],
                'client-b-coffee',
                'USD'
            )['status'] ?? '') === 'not_found',
        'mutating client B leaves client A settings and catalog unchanged'
    );

    $disablePlanA = red_addon_disable_transition_plan(
        $connections['client-a'],
        $package,
        $actorId,
        $catalog
    );
    $disabledA = red_addon_disable_package(
        $connections['client-a'],
        $packageId,
        $projectRoot,
        $actorId,
        $disablePlanA['planSha256'] ?? ''
    );
    $disabledRuntimeA = red_addon_runtime_bootstrap(
        $connections['client-a'],
        $projectRoot
    );
    $stillEnabledRuntimeB = red_addon_runtime_bootstrap(
        $connections['client-b'],
        $projectRoot
    );
    red_store_lite_c3_assert(
        ($disabledA['status'] ?? '') === 'installed_disabled'
            && $disabledRuntimeA['context']->handler(
                'components',
                'redcms.store-lite/product'
            ) === null
            && $stillEnabledRuntimeB['context']->handler(
                'components',
                'redcms.store-lite/product'
            ) !== null
            && red_store_lite_c3_scalar(
                $connections['client-b'],
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'"
            ) === 'enabled',
        'disabling client A does not disable or unload client B'
    );

    $reEnablePlanA = red_addon_enable_transition_plan(
        $connections['client-a'],
        $package,
        $actorId,
        $catalog
    );
    $reEnabledA = red_addon_enable_package(
        $connections['client-a'],
        $packageId,
        $projectRoot,
        $actorId,
        $reEnablePlanA['planSha256'] ?? ''
    );
    red_store_lite_c3_assert(
        ($reEnabledA['status'] ?? '') === 'enabled'
            && hash_equals(
                $registrarEvidence,
                (string) ($reEnabledA['registrarEvidenceSha256'] ?? '')
            )
            && hash_equals(
                $clientABefore,
                red_store_lite_c3_data_fingerprint(
                    $connections['client-a'],
                    $packageId
                )
            )
            && red_store_lite_c3_scalar(
                $connections['client-a'],
                "SELECT CONCAT_WS(':', LifecycleState,
                    SUM(EventName='addon.enable.completed'),
                    SUM(EventName='addon.disable.completed'))
                 FROM RED_Addon_Installations installation
                 INNER JOIN RED_Addon_Activity_Log activity
                   ON activity.PackageID=installation.PackageID
                 WHERE installation.PackageID='$packageId'"
            ) === 'enabled:2:1'
            && red_store_lite_c3_scalar(
                $connections['client-b'],
                "SELECT CONCAT_WS(':', LifecycleState,
                    SUM(EventName='addon.enable.completed'),
                    SUM(EventName='addon.disable.completed'))
                 FROM RED_Addon_Installations installation
                 INNER JOIN RED_Addon_Activity_Log activity
                   ON activity.PackageID=installation.PackageID
                 WHERE installation.PackageID='$packageId'"
            ) === 'enabled:1:0',
        'client A re-enables with preserved data while client B remains unchanged'
    );

    echo json_encode(
        [
            'ok' => true,
            'packageVersion' => $snapshot['version'],
            'clientADatabase' => $clientADatabase,
            'clientBDatabase' => $clientBDatabase,
            'clientAProduct' => 'client-a-shirt',
            'clientBProduct' => 'client-b-coffee',
            'registrarEvidenceSha256' => $registrarEvidence,
            'assertions' => $assertions,
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    $clientA->close();
    $clientB->close();
    exit(1);
}

$clientA->close();
$clientB->close();
exit(0);

?>
