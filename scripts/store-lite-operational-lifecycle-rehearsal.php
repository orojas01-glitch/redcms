<?php
/**
 * Disposable real-package Release B proof for Store Lite.
 *
 * The surrounding rehearsal owns database/package staging and cleanup. This
 * command accepts only the dedicated disposable database name, then proves an
 * installed-disabled Store Lite package can enable, disable, and re-enable
 * without changing package tables, settings, migrations, or inventory.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath((string) getenv('RED_STORE_LITE_PROJECT_ROOT'));
$databaseName = (string) getenv('RED_DB_NAME');
if (!is_string($projectRoot)
    || !is_dir($projectRoot)
    || preg_match(
        '/\Aredcms_store_lite_lifecycle_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(STDERR, "Store Lite lifecycle rehearsal refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_enable_helpers.php';
require_once $projectRoot . '/includes/addon_disable_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$packageId = 'redcms.store-lite';
$actorId = 1;
$assertions = 0;

function red_store_lite_lifecycle_assert(
    bool $condition,
    string $message
): void {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_store_lite_lifecycle_scalar(mysqli $connection, string $sql): string
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_store_lite_lifecycle_data_fingerprint(
    mysqli $connection,
    string $packageId
): string {
    $tables = [];
    $query = mysqli_query(
        $connection,
        "SELECT TABLE_NAME, ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME LIKE 'RED_Addon_StoreLite\\_%'
         ORDER BY TABLE_NAME"
    );
    while ($query && ($row = mysqli_fetch_assoc($query))) {
        $table = (string) $row['TABLE_NAME'];
        $checksumQuery = mysqli_query(
            $connection,
            'CHECKSUM TABLE `' . $table . '`'
        );
        $checksumRow = $checksumQuery
            ? mysqli_fetch_assoc($checksumQuery)
            : null;
        if ($checksumQuery) {
            mysqli_free_result($checksumQuery);
        }
        $tables[] = [
            'table' => $table,
            'engine' => (string) $row['ENGINE'],
            'checksum' => is_array($checksumRow)
                ? (string) ($checksumRow['Checksum'] ?? '')
                : '',
        ];
    }
    if ($query) {
        mysqli_free_result($query);
    }
    $escaped = mysqli_real_escape_string($connection, $packageId);
    $settings = [];
    $query = mysqli_query(
        $connection,
        "SELECT SettingKey, ValueType, ValueJSON, COALESCE(SecretReference, '')
         FROM RED_Addon_Settings
         WHERE PackageID='$escaped'
         ORDER BY SettingKey"
    );
    while ($query && ($row = mysqli_fetch_row($query))) {
        $settings[] = array_map('strval', $row);
    }
    if ($query) {
        mysqli_free_result($query);
    }
    $migrations = [];
    $query = mysqli_query(
        $connection,
        "SELECT MigrationID, MigrationPath, Checksum
         FROM RED_Addon_Migrations
         WHERE PackageID='$escaped'
         ORDER BY MigrationID"
    );
    while ($query && ($row = mysqli_fetch_row($query))) {
        $migrations[] = array_map('strval', $row);
    }
    if ($query) {
        mysqli_free_result($query);
    }
    $encoded = json_encode(
        ['tables' => $tables, 'settings' => $settings, 'migrations' => $migrations],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    return is_string($encoded) ? hash('sha256', $encoded) : '';
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
    red_store_lite_lifecycle_assert(
        !empty($catalog['valid'])
            && is_array($package)
            && !empty($package['valid'])
            && is_array($snapshot)
            && $snapshot['version'] === '0.1.28',
        'staged Store Lite 0.1.28 package is trusted and current'
    );
    red_store_lite_lifecycle_assert(
        red_store_lite_lifecycle_scalar(
            $connection,
            "SELECT CONCAT_WS(':', LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='$packageId'))
             FROM RED_Addon_Installations WHERE PackageID='$packageId'"
        ) === 'installed_disabled:8:5',
        'Store Lite begins installed-disabled with eight migrations and five settings'
    );
    $dataFingerprint = red_store_lite_lifecycle_data_fingerprint(
        $connection,
        $packageId
    );
    red_store_lite_lifecycle_assert(
        red_addon_valid_sha256($dataFingerprint)
            && red_store_lite_lifecycle_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME LIKE 'RED_Addon_StoreLite\\_%'),
                    (SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME LIKE 'RED_Addon_StoreLite\\_%'
                       AND ENGINE='InnoDB'),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Products),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Carts),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Cart_Lines))"
            ) === '15:15:2:2:2',
        'all fifteen Store Lite tables and seeded product/cart rows are fingerprinted'
    );

    $plan = red_addon_enable_transition_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    red_store_lite_lifecycle_assert(
        !empty($plan['valid'])
            && !empty($plan['transitionReady'])
            && ($plan['activationProfile']['id'] ?? '')
                === 'operational_content_package',
        'Store Lite receives the generic operational transition profile'
    );
    $enabled = red_addon_enable_package(
        $connection,
        $packageId,
        $projectRoot,
        $actorId,
        $plan['planSha256']
    );
    red_store_lite_lifecycle_assert(
        $enabled['status'] === 'enabled'
            && red_addon_valid_sha256($enabled['registrarEvidenceSha256']),
        'real Store Lite registrar validates before atomic enable'
    );
    $registrarEvidence = $enabled['registrarEvidenceSha256'];
    $runtime = red_addon_runtime_bootstrap($connection, $projectRoot);
    red_store_lite_lifecycle_assert(
        $runtime['context']->handler(
            'components',
            'redcms.store-lite/product'
        ) !== null
            && $runtime['context']->handler(
                'components',
                'redcms.store-lite/cart'
            ) !== null
            && $runtime['context']->handler(
                'publicMutationHandlers',
                'redcms.store-lite/create-guest-order'
            ) !== null,
        'enabled request bootstrap exposes product, cart, and checkout registrations'
    );

    $disablePlan = red_addon_disable_transition_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    $disabled = red_addon_disable_package(
        $connection,
        $packageId,
        $projectRoot,
        $actorId,
        $disablePlan['planSha256']
    );
    red_store_lite_lifecycle_assert(
        !empty($disablePlan['transitionReady'])
            && $disabled['status'] === 'installed_disabled',
        'Store Lite disables through the non-executing atomic transition'
    );
    $disabledRuntime = red_addon_runtime_bootstrap($connection, $projectRoot);
    red_store_lite_lifecycle_assert(
        $disabledRuntime['context']->handler(
            'components',
            'redcms.store-lite/product'
        ) === null
            && hash_equals(
                $dataFingerprint,
                red_store_lite_lifecycle_data_fingerprint(
                    $connection,
                    $packageId
                )
            ),
        'disabled Store Lite is excluded while all package data stays exact'
    );

    $reEnablePlan = red_addon_enable_transition_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    $reEnabled = red_addon_enable_package(
        $connection,
        $packageId,
        $projectRoot,
        $actorId,
        $reEnablePlan['planSha256']
    );
    red_store_lite_lifecycle_assert(
        $reEnabled['status'] === 'enabled'
            && hash_equals(
                $registrarEvidence,
                $reEnabled['registrarEvidenceSha256']
            )
            && hash_equals(
                $dataFingerprint,
                red_store_lite_lifecycle_data_fingerprint(
                    $connection,
                    $packageId
                )
            ),
        're-enable repeats identical registrar evidence and preserves all data'
    );
    red_store_lite_lifecycle_assert(
        red_store_lite_lifecycle_scalar(
            $connection,
            "SELECT CONCAT_WS(':', LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$packageId'
                   AND EventName='addon.enable.completed'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$packageId'
                   AND EventName='addon.disable.completed'))
             FROM RED_Addon_Installations WHERE PackageID='$packageId'"
        ) === 'enabled:2:1',
        'real package finishes enabled with two enable and one disable facts'
    );

    echo json_encode(
        [
            'ok' => true,
            'packageVersion' => $snapshot['version'],
            'activationProfile' => $plan['activationProfile']['id'],
            'registrarEvidenceSha256' => $registrarEvidence,
            'dataFingerprintSha256' => $dataFingerprint,
            'assertions' => $assertions,
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
} finally {
    $db->close();
}
