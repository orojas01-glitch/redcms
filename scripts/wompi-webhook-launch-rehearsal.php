<?php

declare(strict_types=1);

/**
 * Disposable Wompi webhook rehearsal. Run only through
 * scripts/wompi-webhook-launch-rehearsal.sh, which stages a project copy,
 * creates and later removes the database and grant, and proves the primary
 * database is unchanged. Wompi is never contacted: the confirmation GET is a
 * local double that returns the same synthetic transaction.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath((string) getenv('RED_WOMPI_LAUNCH_PROJECT_ROOT'));
$databaseName = (string) getenv('RED_DB_NAME');
if (!is_string($projectRoot)
    || !is_dir($projectRoot)
    || preg_match('/\Aredcms_wompi_launch_[A-Za-z0-9_]+\z/D', $databaseName)
        !== 1
) {
    fwrite(STDERR, "Wompi launch rehearsal refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_install_helpers.php';
require_once $projectRoot . '/includes/addon_enable_helpers.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_enable_helpers.php';
require_once $projectRoot
    . '/includes/addon_wompi_webhook_runtime_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$storePackageId = 'redcms.store-lite';
$adapterPackageId = 'redcms.store-lite-wompi';
$actorId = 1;
$assertions = 0;

function red_wompi_launch_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_launch_scalar(mysqli $connection, string $sql): string
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_wompi_launch_setting(
    mysqli $connection,
    string $packageId,
    int $actorId,
    string $key,
    string $type,
    ?string $valueJson,
    ?string $reference
): void {
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings
            (PackageID, SettingKey, ValueType, ValueJSON,
             SecretReference, UpdatedByAdminRecordID)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_execute($statement, [
        $packageId, $key, $type, $valueJson, $reference, $actorId,
    ]);
    mysqli_stmt_close($statement);
}

// Synthetic values only. None of these is a real Wompi key.
$eventsSecret = 'test_events_' . str_repeat('R', 24);
$eventReference = 'config:wompi-launch-event-secret';
$receivedAt = time();
$orderId = 'ord_' . bin2hex(random_bytes(16));
$snapshotSha256 = hash('sha256', 'wompi-launch-order-' . $orderId);
$transaction = [
    'id' => '1234-' . $receivedAt . '-49201',
    'amount_in_cents' => 2490000,
    'reference' => $orderId,
    'customer_email' => 'wompi-launch@example.test',
    'currency' => 'COP',
    'payment_method_type' => 'PSE',
    'status' => 'APPROVED',
];

$signedBody = static function (array $tx, int $timestamp) use (
    $eventsSecret
): array {
    $checksum = hash(
        'sha256',
        $tx['id'] . $tx['status'] . $tx['amount_in_cents']
            . $timestamp . $eventsSecret
    );
    $body = json_encode([
        'event' => 'transaction.updated',
        'data' => ['transaction' => $tx],
        'environment' => 'test',
        'signature' => [
            'properties' => [
                'transaction.id',
                'transaction.status',
                'transaction.amount_in_cents',
            ],
            'checksum' => $checksum,
        ],
        'timestamp' => $timestamp,
        'sent_at' => gmdate('Y-m-d\TH:i:s.000\Z', $timestamp),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return [$body, strtoupper($checksum)];
};

try {
    red_wompi_launch_assert(
        red_wompi_launch_scalar($connection, 'SELECT DATABASE()')
            === $databaseName,
        'connection uses only the disposable database'
    );
    mysqli_query(
        $connection,
        "INSERT IGNORE INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    foreach (['addons.install', 'addons.enable'] as $capability) {
        mysqli_query(
            $connection,
            "INSERT IGNORE INTO RED_Admin_Capabilities
             (AdminRecordID, Capability, GrantedByAdminRecordID)
             VALUES ($actorId, '$capability', $actorId)"
        );
    }

    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? null;
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? null;
    red_wompi_launch_assert(
        !empty($catalog['valid'])
            && ($storePackage['manifest']['version'] ?? '') === '0.1.53'
            && ($adapterPackage['manifest']['version'] ?? '') === '0.4.0',
        'exact Store Lite 0.1.53 and Wompi 0.4.0 packages are trusted ('
            . json_encode($catalog['errors'] ?? []) . ')'
    );

    $storeInstallPlan = red_addon_install_plan(
        $connection, $storePackage, $actorId, false, $catalog
    );
    $storeInstalled = red_addon_install_package(
        $connection, $storePackageId, $projectRoot, $actorId,
        $storeInstallPlan['planSha256'] ?? ''
    );
    foreach ([
        ['catalog.currency', 'text', 'COP'],
        ['checkout.delivery-enabled', 'boolean', false],
        ['checkout.delivery-fee-minor', 'integer', 0],
        ['checkout.pay-on-receipt-enabled', 'boolean', true],
        ['checkout.pickup-enabled', 'boolean', true],
    ] as [$key, $type, $value]) {
        red_wompi_launch_setting(
            $connection, $storePackageId, $actorId, $key, $type,
            json_encode($value, JSON_THROW_ON_ERROR), null
        );
    }
    $storeEnablePlan = red_addon_enable_transition_plan(
        $connection, $storePackage, $actorId, $catalog
    );
    $storeEnabled = red_addon_enable_package(
        $connection, $storePackageId, $projectRoot, $actorId,
        $storeEnablePlan['planSha256'] ?? ''
    );
    red_wompi_launch_assert(
        ($storeInstalled['status'] ?? '') === 'installed_disabled'
            && ($storeEnabled['status'] ?? '') === 'enabled',
        'Store Lite installs and enables ('
            . json_encode([
                $storeInstalled['errors'] ?? null,
                $storeEnablePlan['errors'] ?? null,
                $storeEnabled['errors'] ?? null,
            ]) . ')'
    );

    $adapterInstallPlan = red_addon_install_plan(
        $connection, $adapterPackage, $actorId, false, $catalog
    );
    $adapterInstalled = red_addon_install_package(
        $connection, $adapterPackageId, $projectRoot, $actorId,
        $adapterInstallPlan['planSha256'] ?? ''
    );
    $references = [
        'wompi.event-secret' => $eventReference,
        'wompi.integrity-key' => 'config:wompi-launch-integrity-key',
        'wompi.private-key' => 'config:wompi-launch-private-key',
    ];
    red_wompi_launch_setting(
        $connection, $adapterPackageId, $actorId, 'wompi.public-key',
        'text', json_encode('pub_test_' . str_repeat('R', 24)), null
    );
    foreach ($references as $key => $reference) {
        red_wompi_launch_setting(
            $connection, $adapterPackageId, $actorId, $key,
            'secret-reference', null, $reference
        );
    }
    $declarations = red_addon_secret_reference_declarations(
        array_values($references),
        ''
    );
    $adapterEnablePlan = red_addon_payment_adapter_enablement_plan(
        $connection, $adapterPackage, $actorId, $catalog, $declarations
    );
    $adapterEnabled = red_addon_payment_adapter_enable_package(
        $connection, $adapterPackageId, $projectRoot, $actorId,
        $adapterEnablePlan['planSha256'] ?? '', $declarations
    );
    red_wompi_launch_assert(
        ($adapterInstalled['status'] ?? '') === 'installed_disabled'
            && !empty($adapterEnablePlan['enableReady'])
            && ($adapterEnabled['status'] ?? '') === 'enabled',
        'Wompi adapter installs and enables with opaque references only ('
            . json_encode([
                $adapterInstalled['errors'] ?? null,
                $adapterEnablePlan['errors'] ?? null,
                $adapterEnablePlan['blockers'] ?? null,
                $adapterEnabled['errors'] ?? null,
            ]) . ')'
    );

    // One pending Wompi Web Checkout order, as checkout would store it.
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_StoreLite_Orders
         (OrderID, SourceCartRecordID, SubjectRecordID,
          IdempotencyKeySHA256, SourceCartStateSHA256, SnapshotVersion,
          SnapshotSHA256, Currency, CustomerName, CustomerEmail,
          FulfillmentMethod, FulfillmentFeeMinor, PaymentMethod, PaymentKind,
          OrderStatus, PaymentStatus, FulfillmentStatus, QuantityTotal,
          SubtotalMinor, TotalMinor)
         VALUES (?, 1, 1, UNHEX(?), UNHEX(?), 1, UNHEX(?), \'COP\',
                 \'Wompi Launch\', \'wompi-launch@example.test\',
                 \'pickup\', 0, \'wompi_checkout\', \'hosted\', \'pending\',
                 \'pending\', \'unfulfilled\', 1, 2490000, 2490000)'
    );
    mysqli_stmt_execute($statement, [
        $orderId,
        hash('sha256', 'wompi-launch-idempotency'),
        hash('sha256', 'wompi-launch-cart'),
        $snapshotSha256,
    ]);
    $orderRecordId = (int) mysqli_insert_id($connection);
    mysqli_stmt_close($statement);
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_StoreLite_Order_Status_History
         (OrderRecordID, EventName, OrderStatus, PaymentStatus,
          FulfillmentStatus, ActorType, ActorRecordID, SnapshotSHA256)
         VALUES (?, \'order.created\', \'pending\', \'pending\',
                 \'unfulfilled\', \'anonymous\', 1, UNHEX(?))'
    );
    mysqli_stmt_execute($statement, [$orderRecordId, $snapshotSha256]);
    mysqli_stmt_close($statement);

    $orderState = static fn (): string => red_wompi_launch_scalar(
        $connection,
        "SELECT CONCAT_WS(':', OrderStatus, PaymentStatus, FulfillmentStatus,
            (SELECT COUNT(*) FROM RED_Addon_StoreLite_Order_Status_History
             WHERE OrderRecordID=$orderRecordId))
         FROM RED_Addon_StoreLite_Orders WHERE RecordID=$orderRecordId"
    );
    red_wompi_launch_assert(
        $orderState() === 'pending:pending:unfulfilled:1',
        'the synthetic order starts pending'
    );

    $lookups = [];
    $lookupTx = $transaction;
    $lookup = static function (string $url) use (&$lookups, &$lookupTx): string {
        $lookups[] = $url;
        return json_encode(['data' => $lookupTx], JSON_THROW_ON_ERROR);
    };
    $deliver = static function (
        string $body,
        string $checksum,
        array $secretValues
    ) use ($connection, $projectRoot, $receivedAt, $lookup): array {
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => red_addon_wompi_webhook_path(),
            'HTTPS' => 'on',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => (string) strlen($body),
            'HTTP_X_EVENT_CHECKSUM' => $checksum,
        ];
        $capture = red_addon_wompi_webhook_capture(
            $server,
            $body,
            $receivedAt
        );
        $previousReferences = getenv('RED_ADDON_SECRET_REFERENCES');
        $previousValues = getenv('RED_ADDON_SECRET_VALUES_JSON');
        putenv('RED_ADDON_SECRET_REFERENCES='
            . implode(',', array_keys($secretValues)));
        putenv('RED_ADDON_SECRET_VALUES_JSON='
            . json_encode($secretValues, JSON_UNESCAPED_SLASHES));
        $evidence = [];
        try {
            $response = red_addon_wompi_webhook_dispatch(
                'POST',
                red_addon_wompi_webhook_path(),
                $capture,
                true,
                static function (array $request) use (
                    $connection,
                    $projectRoot,
                    $lookup,
                    &$evidence
                ): array {
                    $result = red_addon_wompi_webhook_runtime_run(
                        $connection,
                        $projectRoot,
                        $request,
                        $lookup
                    );
                    $evidence[] = $result;
                    return $result;
                }
            );
        } finally {
            unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
            $previousReferences === false
                ? putenv('RED_ADDON_SECRET_REFERENCES')
                : putenv('RED_ADDON_SECRET_REFERENCES=' . $previousReferences);
            $previousValues === false
                ? putenv('RED_ADDON_SECRET_VALUES_JSON')
                : putenv('RED_ADDON_SECRET_VALUES_JSON=' . $previousValues);
        }
        return [$response, $evidence];
    };
    $secrets = [$eventReference => $eventsSecret];

    // Forged delivery: refused, nothing looked up or written.
    [$forgedBody, $forgedChecksum] = (static function () use ($transaction, $receivedAt): array {
        $checksum = hash('sha256', 'forged');
        return [json_encode([
            'event' => 'transaction.updated',
            'data' => ['transaction' => $transaction],
            'environment' => 'test',
            'signature' => [
                'properties' => ['transaction.id'],
                'checksum' => $checksum,
            ],
            'timestamp' => $receivedAt - 30,
            'sent_at' => '2026-11-08T00:00:00.000Z',
        ], JSON_THROW_ON_ERROR), strtoupper($checksum)];
    })();
    [$forged, $forgedEvidence] = $deliver($forgedBody, $forgedChecksum, $secrets);
    red_wompi_launch_assert(
        ($forged['status'] ?? 0) === 400
            && $lookups === []
            && $orderState() === 'pending:pending:unfulfilled:1',
        'a forged delivery is refused with 400 before lookup or write ('
            . json_encode($forgedEvidence) . ')'
    );

    // Missing events secret: retry, nothing written.
    [$body, $checksum] = $signedBody($transaction, $receivedAt - 30);
    [$noSecret] = $deliver($body, $checksum, [
        'config:wompi-launch-other' => 'unused-value-' . str_repeat('x', 8),
    ]);
    red_wompi_launch_assert(
        ($noSecret['status'] ?? 0) === 500
            && $orderState() === 'pending:pending:unfulfilled:1',
        'an unresolved events secret asks Wompi to retry and writes nothing'
    );

    // Lookup disagrees (still PENDING at Wompi): retry, nothing written.
    $lookupTx = ['status' => 'PENDING'] + $transaction;
    [$disagree] = $deliver($body, $checksum, $secrets);
    red_wompi_launch_assert(
        ($disagree['status'] ?? 0) === 500
            && count($lookups) === 1
            && $orderState() === 'pending:pending:unfulfilled:1',
        'a lookup that disagrees with the event asks Wompi to retry'
    );

    // Real delivery: applied as paid.
    $lookupTx = $transaction;
    [$applied, $appliedEvidence] = $deliver($body, $checksum, $secrets);
    red_wompi_launch_assert(
        ($applied['status'] ?? 0) === 200
            && ($applied['body'] ?? '') === '{"ok":true}'
            && ($appliedEvidence[0]['data'] ?? null)
                === ['disposition' => 'applied', 'outcome' => 'paid']
            && $orderState() === 'paid:paid:unfulfilled:2'
            && end($lookups)
                === 'https://sandbox.wompi.co/v1/transactions/'
                    . $transaction['id'],
        'a signed APPROVED event is confirmed and applied as paid once ('
            . $orderState() . ' ' . json_encode($appliedEvidence) . ')'
    );

    // Wompi redelivery: acknowledged, no second write. Store Lite matches a
    // replay only against the original pre-payment order, and the endpoint
    // reads the current (already paid) order, so Store Lite reports
    // payment_event_replay_conflict and the coordinator acknowledges it as
    // apply_refused. The outcome is the safe one: 200 and no second write.
    [$replay, $replayEvidence] = $deliver($body, $checksum, $secrets);
    red_wompi_launch_assert(
        ($replay['status'] ?? 0) === 200
            && ($replayEvidence[0]['data']['disposition'] ?? '')
                === 'apply_refused'
            && $orderState() === 'paid:paid:unfulfilled:2',
        'a redelivery is acknowledged without a second write ('
            . $orderState() . ' ' . json_encode([$replay['status'] ?? 0, $replayEvidence]) . ')'
    );

    red_wompi_launch_assert(
        !str_contains(
            json_encode([$forged, $noSecret, $disagree, $applied, $replay]),
            $eventsSecret
        )
            && !str_contains(json_encode($applied), 'wompi-launch@example.test')
            && getenv('RED_ADDON_SECRET_VALUES_JSON') === false,
        'no secret or customer data reaches a response, and the environment is restored'
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

echo 'Wompi webhook launch rehearsal passed ' . $assertions
    . " assertions.\n";
echo "No Wompi contact, real secret, payment, or deployment occurred.\n";
