<?php
/**
 * Disposable real-package Store Lite 0.1.28 to 0.1.29 upgrade proof.
 *
 * The shell wrapper owns database/package staging and cleanup. This command
 * accepts only two separately staged trusted package projects and a dedicated
 * disposable database. It never enables Store Lite or loads addon.php.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$baselineProject = realpath(
    (string) getenv('RED_STORE_LITE_BASELINE_PROJECT_ROOT')
);
$targetProject = realpath(
    (string) getenv('RED_STORE_LITE_TARGET_PROJECT_ROOT')
);
$databaseName = (string) getenv('RED_DB_NAME');
if (!is_string($baselineProject)
    || !is_dir($baselineProject)
    || !is_string($targetProject)
    || !is_dir($targetProject)
    || preg_match(
        '/\Aredcms_sl_upg_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(STDERR, "Store Lite upgrade rehearsal refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_upgrade_helpers.php';
require_once $projectRoot . '/includes/addon_runtime_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$packageId = 'redcms.store-lite';
$actorId = 2147000971;
$assertions = 0;

function red_store_lite_upgrade_assert(
    bool $condition,
    string $message
): void {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_store_lite_upgrade_scalar(
    mysqli $connection,
    string $sql
): string {
    $query = mysqli_query($connection, $sql);
    if (!$query) {
        throw new RuntimeException(
            'Store Lite rehearsal query failed: ' . mysqli_error($connection)
        );
    }
    $row = mysqli_fetch_row($query);
    mysqli_free_result($query);
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_store_lite_upgrade_business_fingerprint(
    mysqli $connection,
    string $packageId
): string {
    $escaped = mysqli_real_escape_string($connection, $packageId);
    $order = red_store_lite_upgrade_scalar(
        $connection,
        "SELECT CONCAT_WS('|', OrderID, SourceCartRecordID, SubjectRecordID,
            HEX(IdempotencyKeySHA256), HEX(SourceCartStateSHA256),
            SnapshotVersion, HEX(SnapshotSHA256), Currency, CustomerName,
            CustomerEmail, COALESCE(CustomerPhone, ''), FulfillmentMethod,
            FulfillmentFeeMinor, PaymentMethod, PaymentKind, OrderStatus,
            PaymentStatus, FulfillmentStatus, QuantityTotal, SubtotalMinor,
            TotalMinor)
         FROM RED_Addon_StoreLite_Orders
         WHERE OrderID='ord_11111111111111111111111111111111'"
    );
    $settings = [];
    $query = mysqli_query(
        $connection,
        "SELECT SettingKey, ValueType, ValueJSON,
                COALESCE(SecretReference, '')
         FROM RED_Addon_Settings
         WHERE PackageID='$escaped'
         ORDER BY SettingKey"
    );
    if (!$query) {
        throw new RuntimeException('Store Lite settings could not be read.');
    }
    while ($row = mysqli_fetch_row($query)) {
        $settings[] = array_map('strval', $row);
    }
    mysqli_free_result($query);
    $encoded = json_encode(
        ['order' => $order, 'settings' => $settings],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    return is_string($encoded) ? hash('sha256', $encoded) : '';
}

try {
    $baselineCatalog = red_addon_discover($baselineProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $targetCatalog = red_addon_discover($targetProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $baselinePackage = $baselineCatalog['packages'][$packageId] ?? null;
    $targetPackage = $targetCatalog['packages'][$packageId] ?? null;
    $baselineSnapshot = is_array($baselinePackage)
        ? red_addon_registry_snapshot($baselinePackage)
        : null;
    $targetSnapshot = is_array($targetPackage)
        ? red_addon_registry_snapshot($targetPackage)
        : null;
    red_store_lite_upgrade_assert(
        !empty($baselineCatalog['valid'])
            && !empty($targetCatalog['valid'])
            && is_array($baselineSnapshot)
            && is_array($targetSnapshot)
            && $baselineSnapshot['version'] === '0.1.28'
            && count($baselineSnapshot['migrations']) === 8
            && $targetSnapshot['version'] === '0.1.29'
            && count($targetSnapshot['migrations']) === 10,
        'trusted historical 0.1.28 and target 0.1.29 payloads are exact'
    );
    red_store_lite_upgrade_assert(
        array_slice(array_keys($targetSnapshot['migrations']), 0, 8)
                === array_keys($baselineSnapshot['migrations'])
            && array_slice(
                array_map(
                    static fn (array $migration): string =>
                        $migration['path'] . ':' . $migration['sha256'],
                    array_values($targetSnapshot['migrations'])
                ),
                0,
                8
            ) === array_map(
                static fn (array $migration): string =>
                    $migration['path'] . ':' . $migration['sha256'],
                array_values($baselineSnapshot['migrations'])
            ),
        'all eight historical migration paths and checksums remain unchanged'
    );

    $passwordHash = password_hash(
        'StoreLiteUpgradeRehearsal-2026!',
        PASSWORD_DEFAULT
    );
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'store_lite_upgrader', ?, 'Admin', 'StoreUpgrade',
                   'webmaster', '100', '1', 'store-upgrade@example.test',
                   'N', 'to', 'N', 'to')"
    );
    if (!$statement) {
        throw new RuntimeException('Could not prepare the Owner fixture.');
    }
    mysqli_stmt_bind_param($statement, 'is', $actorId, $passwordHash);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES
         ($actorId, 'addons.install', $actorId),
         ($actorId, 'addons.upgrade', $actorId)"
    );

    $installPlan = red_addon_install_plan(
        $connection,
        $baselinePackage,
        $actorId,
        false,
        $baselineCatalog
    );
    red_store_lite_upgrade_assert(
        !empty($installPlan['valid'])
            && $installPlan['version'] === '0.1.28'
            && count($installPlan['pendingMigrations']) === 8,
        'real 0.1.28 installation plan is Owner-authorized and complete'
    );
    $installed = red_addon_install_package(
        $connection,
        $packageId,
        $baselineProject,
        $actorId,
        $installPlan['planSha256']
    );
    red_store_lite_upgrade_assert(
        $installed['status'] === 'installed_disabled'
            && count($installed['appliedMigrations']) === 8
            && red_store_lite_upgrade_scalar(
                $connection,
                "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Migrations
                     WHERE PackageID='$packageId'),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME LIKE 'RED_Addon_StoreLite\\_%'))
                 FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'"
            ) === '0.1.28:installed_disabled:8:15',
        'real 0.1.28 package installs disabled with eight migrations and fifteen tables'
    );

    mysqli_query(
        $connection,
        "INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES
         ('$packageId', 'catalog.currency', 'text', '\"USD\"', NULL, $actorId),
         ('$packageId', 'checkout.delivery-enabled', 'boolean', 'true', NULL, $actorId),
         ('$packageId', 'checkout.delivery-fee-minor', 'integer', '500', NULL, $actorId),
         ('$packageId', 'checkout.pay-on-receipt-enabled', 'boolean', 'true', NULL, $actorId),
         ('$packageId', 'checkout.pickup-enabled', 'boolean', 'true', NULL, $actorId)"
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Addon_StoreLite_Orders (
            OrderID, SourceCartRecordID, SubjectRecordID,
            IdempotencyKeySHA256, SourceCartStateSHA256,
            SnapshotVersion, SnapshotSHA256, Currency, CustomerName,
            CustomerEmail, CustomerPhone, FulfillmentMethod,
            FulfillmentFeeMinor, PaymentMethod, PaymentKind, OrderStatus,
            PaymentStatus, FulfillmentStatus, QuantityTotal, SubtotalMinor,
            TotalMinor
         ) VALUES (
            'ord_11111111111111111111111111111111', 1, 1,
            UNHEX(REPEAT('1', 64)), UNHEX(REPEAT('2', 64)),
            1, UNHEX(REPEAT('3', 64)), 'USD', 'Upgrade Customer',
            'upgrade@example.test', NULL, 'pickup', 0,
            'pay_on_receipt', 'deferred', 'pending', 'due_on_receipt',
            'unfulfilled', 1, 2500, 2500
         )"
    );
    $businessFingerprint = red_store_lite_upgrade_business_fingerprint(
        $connection,
        $packageId
    );
    red_store_lite_upgrade_assert(
        red_addon_valid_sha256($businessFingerprint)
            && red_store_lite_upgrade_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Addon_Settings
                     WHERE PackageID='$packageId'),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders))"
            ) === '5:1',
        'five configured settings and one real order form the preserved baseline'
    );

    $plan = red_addon_upgrade_plan(
        $connection,
        $targetPackage,
        $actorId,
        false,
        $targetCatalog
    );
    $fulfillmentMigration =
        '2026-08-13-add-order-fulfillment-status-index';
    $paymentMigration = '2026-08-13-add-order-payment-status-index';
    $expectedAppliedMigrations = array_keys($baselineSnapshot['migrations']);
    sort($expectedAppliedMigrations, SORT_STRING);
    red_store_lite_upgrade_assert(
        !empty($plan['valid'])
            && !$plan['resume']
            && $plan['currentVersion'] === '0.1.28'
            && $plan['targetVersion'] === '0.1.29'
            && $plan['appliedMigrations'] === $expectedAppliedMigrations
            && $plan['pendingMigrations']
                === [$fulfillmentMigration, $paymentMigration]
            && ($plan['settingEvidence']['storedCount'] ?? 0) === 5,
        'dry run binds exact versions, settings, history, and two append-only migrations; observed=' .
            json_encode([
                'valid' => $plan['valid'],
                'errors' => $plan['errors'],
                'currentVersion' => $plan['currentVersion'],
                'targetVersion' => $plan['targetVersion'],
                'appliedMigrations' => $plan['appliedMigrations'],
                'pendingMigrations' => $plan['pendingMigrations'],
                'settingEvidence' => $plan['settingEvidence'],
            ], JSON_UNESCAPED_SLASHES)
    );

    $executionCount = 0;
    $forcedFailure = static function (
        mysqli $dbConnection,
        string $sql
    ) use (&$executionCount): bool {
        $executionCount++;
        if ($executionCount === 2) {
            throw new RuntimeException('forced_store_lite_second_migration_failure');
        }
        return red_addon_install_execute_sql($dbConnection, $sql);
    };
    $failed = red_addon_upgrade_package(
        $connection,
        $packageId,
        $targetProject,
        $actorId,
        $plan['planSha256'],
        false,
        null,
        $forcedFailure
    );
    red_store_lite_upgrade_assert(
        $failed['status'] === 'migration_execution_failed'
            && $failed['failedMigration'] === $paymentMigration
            && $failed['appliedMigrations'] === [$fulfillmentMigration],
        'forced second migration failure reports the exact resumable boundary'
    );
    red_store_lite_upgrade_assert(
        red_store_lite_upgrade_scalar(
            $connection,
            "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_StoreLite_Orders'
                   AND INDEX_NAME='idx_storelite_order_fulfillment_status'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_StoreLite_Orders'
                   AND INDEX_NAME='idx_storelite_order_payment_status'))
             FROM RED_Addon_Installations WHERE PackageID='$packageId'"
        ) === '0.1.28:upgrade_failed:9:2:0'
            && hash_equals(
                $businessFingerprint,
                red_store_lite_upgrade_business_fingerprint(
                    $connection,
                    $packageId
                )
            ),
        'failed upgrade preserves old identity, order, settings, and first DDL evidence'
    );
    $failedReport = red_addon_registry_package_report(
        $connection,
        $targetPackage
    );
    $runtime = red_addon_runtime_bootstrap($connection, $targetProject);
    red_store_lite_upgrade_assert(
        ($failedReport['status'] ?? '') === 'upgrade_failed'
            && empty($failedReport['loadable'])
            && $runtime['context']->handler(
                'components',
                'redcms.store-lite/product'
            ) === null
            && $runtime['context']->handler(
                'publicMutationHandlers',
                'redcms.store-lite/create-guest-order'
            ) === null,
        'upgrade_failed Store Lite is reported non-loadable with no registrations'
    );

    $resumePlan = red_addon_upgrade_plan(
        $connection,
        $targetPackage,
        $actorId,
        true,
        $targetCatalog
    );
    red_store_lite_upgrade_assert(
        !empty($resumePlan['valid'])
            && $resumePlan['resume']
            && $resumePlan['currentState'] === 'upgrade_failed'
            && $resumePlan['pendingMigrations'] === [$paymentMigration],
        'explicit resume schedules only the unrecorded payment-status index'
    );
    $completed = red_addon_upgrade_package(
        $connection,
        $packageId,
        $targetProject,
        $actorId,
        $resumePlan['planSha256'],
        true
    );
    red_store_lite_upgrade_assert(
        $completed['status'] === 'installed_disabled'
            && $completed['currentVersion'] === '0.1.28'
            && $completed['targetVersion'] === '0.1.29'
            && $completed['appliedMigrations'] === [$paymentMigration],
        'recovery applies only the remaining migration and commits target identity disabled'
    );
    $finalReport = red_addon_registry_package_report(
        $connection,
        $targetPackage
    );
    red_store_lite_upgrade_assert(
        ($finalReport['status'] ?? '') === 'installed_disabled_current'
            && empty($finalReport['loadable'])
            && red_store_lite_upgrade_scalar(
                $connection,
                "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Migrations
                     WHERE PackageID='$packageId'),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_StoreLite_Orders'
                       AND INDEX_NAME IN (
                         'idx_storelite_order_fulfillment_status',
                         'idx_storelite_order_payment_status')),
                    (SELECT COUNT(*) FROM RED_Addon_Settings
                     WHERE PackageID='$packageId'),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders))
                 FROM RED_Addon_Installations WHERE PackageID='$packageId'"
            ) === '0.1.29:installed_disabled:10:4:5:1'
            && hash_equals(
                $businessFingerprint,
                red_store_lite_upgrade_business_fingerprint(
                    $connection,
                    $packageId
                )
            ),
        'final registry, ten-migration ledger, indexes, settings, and order are exact'
    );
    red_store_lite_upgrade_assert(
        red_store_lite_upgrade_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                SUM(EventName='addon.upgrade.started' AND Result='started'),
                SUM(EventName='addon.upgrade.failed' AND Result='failed'),
                SUM(EventName='addon.upgrade.completed' AND Result='succeeded'))
             FROM RED_Addon_Activity_Log WHERE PackageID='$packageId'"
        ) === '2:1:1',
        'audit history records one failed attempt and one exact recovery'
    );
    $repeat = red_addon_upgrade_plan(
        $connection,
        $targetPackage,
        $actorId,
        false,
        $targetCatalog
    );
    red_store_lite_upgrade_assert(
        empty($repeat['valid'])
            && $repeat['errors'] === ['target_version_not_newer'],
        'the same Store Lite target version cannot be applied twice'
    );

    echo json_encode(
        [
            'ok' => true,
            'currentVersion' => '0.1.28',
            'targetVersion' => '0.1.29',
            'forcedFailureMigration' => $paymentMigration,
            'resumedMigrations' => $completed['appliedMigrations'],
            'businessFingerprintSha256' => $businessFingerprint,
            'assertions' => $assertions,
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    $db->close();
    exit(1);
}

$db->close();
exit(0);

?>
