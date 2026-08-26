<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath(
    (string) getenv('RED_SUBSCRIPTION_LAUNCH_PROJECT_ROOT')
);
$databaseName = (string) getenv('RED_DB_NAME');
if (!is_string($projectRoot)
    || !is_dir($projectRoot)
    || preg_match(
        '/\Aredcms_subscription_launch_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(STDERR, "Subscription launch rehearsal refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_install_helpers.php';
require_once $projectRoot . '/includes/addon_enable_helpers.php';
require_once $projectRoot . '/includes/addon_disable_helpers.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_enable_helpers.php';
require_once $projectRoot
    . '/includes/addon_subscription_checkout_public_response_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$storePackageId = 'redcms.store-lite';
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$adapterId = $adapterPackageId . '/checkout';
$actorId = 1;
$assertions = 0;

function red_subscription_launch_assert(
    bool $condition,
    string $message
): void {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_subscription_launch_scalar(
    mysqli $connection,
    string $sql
): string {
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_subscription_launch_prepare_owner(
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

function red_subscription_launch_store_settings(
    mysqli $connection,
    string $packageId,
    int $actorId
): void {
    $settings = [
        ['catalog.currency', 'text', 'USD'],
        ['checkout.delivery-enabled', 'boolean', false],
        ['checkout.delivery-fee-minor', 'integer', 0],
        ['checkout.pay-on-receipt-enabled', 'boolean', true],
        ['checkout.pickup-enabled', 'boolean', true],
    ];
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings
            (PackageID, SettingKey, ValueType, ValueJSON,
             SecretReference, UpdatedByAdminRecordID)
         VALUES (?, ?, ?, ?, NULL, ?)'
    );
    foreach ($settings as [$key, $type, $value]) {
        mysqli_stmt_execute($statement, [
            $packageId,
            $key,
            $type,
            json_encode($value, JSON_THROW_ON_ERROR),
            $actorId,
        ]);
    }
    mysqli_stmt_close($statement);
}

function red_subscription_launch_adapter_settings(
    mysqli $connection,
    string $packageId,
    int $actorId,
    string $apiReference,
    string $webhookReference
): void {
    $rows = [[
        'checkout.return-origin',
        'url',
        json_encode(
            'https://shop.subscription-launch.example.test',
            JSON_THROW_ON_ERROR
        ),
        null,
    ], [
        'stripe.secret-key',
        'secret-reference',
        null,
        $apiReference,
    ], [
        'stripe.webhook-secret',
        'secret-reference',
        null,
        $webhookReference,
    ]];
    foreach ($rows as [$key, $type, $value, $reference]) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Settings
                (PackageID, SettingKey, ValueType, ValueJSON,
                 SecretReference, UpdatedByAdminRecordID)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        mysqli_stmt_execute($statement, [
            $packageId,
            $key,
            $type,
            $value,
            $reference,
            $actorId,
        ]);
        mysqli_stmt_close($statement);
    }
}

try {
    red_subscription_launch_assert(
        red_subscription_launch_scalar($connection, 'SELECT DATABASE()')
            === $databaseName,
        'connection uses only the disposable database'
    );
    red_subscription_launch_prepare_owner($connection, $actorId);

    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? null;
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? null;
    red_subscription_launch_assert(
        !empty($catalog['valid'])
            && is_array($storePackage)
            && is_array($adapterPackage)
            && ($storePackage['manifest']['version'] ?? '') === '0.1.49'
            && ($adapterPackage['manifest']['version'] ?? '') === '0.1.9',
        'exact Store Lite and Stripe launch candidates are trusted'
    );

    $storeInstallPlan = red_addon_install_plan(
        $connection,
        $storePackage,
        $actorId,
        false,
        $catalog
    );
    $storeInstalled = red_addon_install_package(
        $connection,
        $storePackageId,
        $projectRoot,
        $actorId,
        $storeInstallPlan['planSha256'] ?? ''
    );
    red_subscription_launch_store_settings(
        $connection,
        $storePackageId,
        $actorId
    );
    $storeEnablePlan = red_addon_enable_transition_plan(
        $connection,
        $storePackage,
        $actorId,
        $catalog
    );
    $storeEnabled = red_addon_enable_package(
        $connection,
        $storePackageId,
        $projectRoot,
        $actorId,
        $storeEnablePlan['planSha256'] ?? ''
    );
    red_subscription_launch_assert(
        ($storeInstalled['status'] ?? '') === 'installed_disabled'
            && count($storeInstalled['appliedMigrations'] ?? []) === 15
            && ($storeEnabled['status'] ?? '') === 'enabled',
        'Store Lite installs and enables with all lifecycle migrations'
    );

    $adapterInstallPlan = red_addon_install_plan(
        $connection,
        $adapterPackage,
        $actorId,
        false,
        $catalog
    );
    $adapterInstalled = red_addon_install_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $adapterInstallPlan['planSha256'] ?? ''
    );
    $apiReference = 'config:subscription-launch-stripe-secret-key';
    $webhookReference = 'config:subscription-launch-stripe-webhook-secret';
    red_subscription_launch_adapter_settings(
        $connection,
        $adapterPackageId,
        $actorId,
        $apiReference,
        $webhookReference
    );
    $declarations = red_addon_secret_reference_declarations(
        [$apiReference, $webhookReference],
        ''
    );
    $adapterEnablePlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    $adapterEnabled = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $adapterEnablePlan['planSha256'] ?? '',
        $declarations
    );
    red_subscription_launch_assert(
        ($adapterInstalled['status'] ?? '') === 'installed_disabled'
            && count($adapterInstalled['appliedMigrations'] ?? []) === 2
            && !empty($adapterEnablePlan['enableReady'])
            && ($adapterEnabled['status'] ?? '') === 'enabled',
        'Stripe adapter installs and enables with opaque references only'
    );

    $loadErrors = [];
    $loadOrder = red_addon_runtime_load_order(
        $catalog,
        [$adapterPackageId, $storePackageId],
        $loadErrors
    );
    $storeRegistry = red_addon_runtime_register_package($storePackage);
    $adapterRegistry = red_addon_runtime_register_package($adapterPackage);
    red_subscription_launch_assert(
        $loadOrder === [$storePackageId, $adapterPackageId]
            && $loadErrors === [],
        'dependency order is exact before request-local registration'
    );
    red_addon_runtime_set_request_context(new RED_Addon_Runtime_Context(
        $loadOrder,
        [
            $storePackageId => $storeRegistry,
            $adapterPackageId => $adapterRegistry,
        ]
    ));
    red_subscription_launch_assert(
        red_addon_runtime_owner('services', 'commerce.subscriptions')
            === $storePackageId
            && red_addon_runtime_owner('adapters', $adapterId)
                === $adapterPackageId,
        'one request-local context owns both typed boundaries'
    );

    $product = [
        'id' => 'launch-membership',
        'type' => 'simple',
        'title' => 'Launch membership',
        'summary' => 'Disposable recurring subscription fixture.',
        'currency' => 'USD',
        'state' => 'published',
        'availability' => 'available',
        'imageRef' => null,
        'sku' => 'LAUNCH-MEMBERSHIP',
        'priceMinor' => 2900,
        'stock' => null,
        'options' => [],
        'variants' => [],
    ];
    $offer = [
        'id' => 'launch-membership-monthly',
        'productId' => 'launch-membership',
        'variantId' => null,
        'title' => 'Launch membership',
        'summary' => 'Disposable recurring subscription fixture.',
        'currency' => 'USD',
        'priceMinor' => 2900,
        'billingPeriod' => 'monthly',
        'state' => 'published',
        'availability' => 'available',
        'buttonLabel' => 'Subscribe monthly',
    ];
    $subjectInserted = mysqli_query(
        $connection,
        "INSERT INTO RED_Addon_Public_Mutation_Subjects
         (RecordID, SubjectTokenSHA256, CreatedAt, ExpiresAt)
         VALUES (9501, '"
            . hash('sha256', 'subscription-launch-subject')
            . "', '2026-08-25 12:00:00', '2026-08-26 12:00:00')"
    );
    mysqli_begin_transaction($connection);
    $productCreated = RED_CMS_Store_Lite_Catalog_Persistence::
        createWithinTransaction($connection, $product, 'USD');
    $offerCreated = RED_CMS_Store_Lite_Subscription_Offer_Persistence::
        createWithinTransaction($connection, $offer, 'USD');
    $intentCreated = RED_CMS_Store_Lite_Subscription_Intent_Persistence::
        requestWithinTransaction(
            $connection,
            9501,
            'launch-membership-monthly',
            'USD'
        );
    mysqli_commit($connection);
    red_subscription_launch_assert(
        $subjectInserted === true
            && ($productCreated['status'] ?? '') === 'created'
            && ($offerCreated['status'] ?? '') === 'created'
            && ($intentCreated['status'] ?? '') === 'created',
        'one provider-neutral recurring intent is created locally ('
            . implode(':', [
                $productCreated['status'] ?? 'missing',
                $offerCreated['status'] ?? 'missing',
                $intentCreated['status'] ?? 'missing',
            ]) . ')'
    );

    $intentReference = red_addon_subscription_intent_reference(
        9501,
        'launch-membership-monthly',
        $intentCreated['intentStateSha256'],
        $intentCreated['offerStateSha256']
    );
    $sessionRef = 'cs_test_SubscriptionLaunchCandidate1234';
    $checkoutUrl = 'https://checkout.stripe.com/c/pay/' . $sessionRef
        . '#synthetic-launch-fragment';
    $policy = [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.subscription-launch.example.test/complete',
        'cancelUrl' =>
            'https://shop.subscription-launch.example.test/subscription',
        'createdAtEpoch' => 1787630400,
        'expiresAtEpoch' => 1787632200,
    ];
    $synthetic = [
        'envelope' => [
            'statusCode' => 200,
            'contentType' => 'application/json',
            'bodyBytes' => 2048,
            'bodySha256' => hash(
                'sha256',
                'subscription-launch-synthetic-body'
            ),
            'requestId' => 'req_SubscriptionLaunchCandidate',
            'tlsVersion' => 'TLSv1.3',
            'redirectCount' => 0,
        ],
        'projection' => [
            'id' => $sessionRef,
            'object' => 'checkout.session',
            'url' => $checkoutUrl,
            'mode' => 'subscription',
            'status' => 'open',
            'payment_status' => 'unpaid',
            'amount_total' => 2900,
            'currency' => 'usd',
            'client_reference_id' => $intentReference,
            'metadata' => [
                'redcms_intent_state_sha256' =>
                    $intentCreated['intentStateSha256'],
                'redcms_offer_state_sha256' =>
                    $intentCreated['offerStateSha256'],
            ],
            'livemode' => false,
            'expires_at' => 1787632200,
            'after_expiration' => null,
        ],
    ];
    $coordinated = red_addon_subscription_checkout_coordinate_current(
        9501,
        'launch-membership-monthly',
        $policy,
        $synthetic
    );
    red_subscription_launch_assert(
        ($coordinated['valid'] ?? false) === true
            && ($coordinated['ready'] ?? false) === true
            && ($coordinated['status'] ?? '')
                === 'synthetic_redirect_ready'
            && ($coordinated['checkoutUrl'] ?? '') === $checkoutUrl
            && ($coordinated['httpStatus'] ?? 0) === 303
            && ($coordinated['cacheControl'] ?? '') === 'no-store'
            && ($coordinated['responseEmission'] ?? true) === false
            && ($coordinated['browserNavigation'] ?? true) === false,
        'real packages complete the four-stage synthetic coordinator'
    );
    $publicResponse = red_addon_subscription_checkout_public_response(
        [
            'completed' => true,
            'replayed' => false,
            'outcome' => 'accepted',
            'route' => 'redcms.store-lite/subscription-intent',
            'mutation' => 'redcms.store-lite/create-subscription-intent',
            'reason' => 'completed',
        ],
        9501,
        'launch-membership-monthly',
        $coordinated
    );
    red_subscription_launch_assert(
        red_addon_subscription_checkout_public_response_valid(
            $publicResponse
        )
            && ($publicResponse['checkoutUrl'] ?? '') === $checkoutUrl
            && ($publicResponse['navigationAuthorized'] ?? false) === true
            && !str_contains(
                $publicResponse['body'] ?? '',
                $intentReference
            ),
        'completed intent receives one transient redacted AJAX handoff'
    );
    red_subscription_launch_assert(
        red_subscription_launch_scalar(
            $connection,
            "SELECT CONCAT_WS(':', SubscriptionStatus, EntitlementStatus,
                LOWER(HEX(CheckoutSessionRefSHA256)),
                (SELECT COUNT(*) FROM
                    RED_Addon_StoreLite_Subscription_Status_History))
             FROM RED_Addon_StoreLite_Subscriptions
             WHERE IntentReference='"
                . mysqli_real_escape_string($connection, $intentReference)
                . "'"
        ) === 'pending:inactive:' . hash('sha256', $sessionRef) . ':1',
        'only hashed pending inactive lifecycle evidence persists'
    );
    $replayed = red_addon_subscription_checkout_coordinate_current(
        9501,
        'launch-membership-monthly',
        $policy,
        $synthetic
    );
    red_subscription_launch_assert(
        ($replayed['status'] ?? '') === 'synthetic_redirect_ready'
            && ($replayed['checkoutSessionRefSha256'] ?? '')
                === hash('sha256', $sessionRef)
            && red_subscription_launch_scalar(
                $connection,
                'SELECT COUNT(*) FROM '
                    . 'RED_Addon_StoreLite_Subscription_Status_History'
            ) === '1',
        'exact coordinator replay adds no lifecycle history row'
    );
    red_subscription_launch_assert(
        red_subscription_launch_scalar(
            $connection,
            'SELECT CONCAT_WS(\':\',
                (SELECT COUNT(*) FROM
                    RED_Addon_StoreLite_Stripe_Checkout_Attempts),
                (SELECT COUNT(*) FROM
                    RED_Addon_StoreLite_Stripe_Event_Receipts))'
        ) === '0:0'
            && !str_contains(
                json_encode(
                    $coordinated,
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
                'sk_test_'
            ),
        'adapter tables and result contain no provider attempt or secret'
    );

    $disablePlan = red_addon_disable_transition_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog
    );
    $disabled = red_addon_disable_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $disablePlan['planSha256'] ?? ''
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $disabledRuntime = red_addon_runtime_bootstrap($connection, $projectRoot);
    red_addon_runtime_set_request_context($disabledRuntime['context']);
    $blocked = red_addon_subscription_checkout_coordinate_current(
        9501,
        'launch-membership-monthly',
        $policy,
        $synthetic
    );
    red_subscription_launch_assert(
        ($disabled['status'] ?? '') === 'installed_disabled'
            && ($blocked['valid'] ?? true) === false
            && red_addon_runtime_owner('adapters', $adapterId) === null,
        'disabled adapter removes ownership and blocks coordination'
    );

    echo json_encode([
        'ok' => true,
        'coreVersion' => '5.1.0',
        'storeLiteVersion' => '0.1.49',
        'stripeAdapterVersion' => '0.1.9',
        'assertions' => $assertions,
        'networkAccess' => false,
        'providerContact' => false,
        'secretResolution' => false,
        'checkoutCreation' => false,
        'browserNavigation' => false,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $db->close();
    exit(1);
}

unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
$db->close();
exit(0);
