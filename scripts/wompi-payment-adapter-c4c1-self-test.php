<?php
/** Disposable C4C1 exact-package adoption and sealed merchant-read proof. */

define('RED_WOMPI_C3C1_FIXTURE_ONLY', true);
require_once __DIR__ . '/wompi-payment-adapter-c3c1-self-test.php';
require_once $projectRoot . '/includes/addon_adapter_helpers.php';

$assertions = 0;

function red_wompi_c4c1_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    $password = password_hash('WompiC4C1-Disposable-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_wompi_c4c1', ?, 'Admin', 'WompiC4C1',
                   'webmaster', '', '', 'wompi-c4c1@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_wompi_c4c1_assert($inserted, 'disposable Owner fixture is recorded');
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    foreach (['addons.install', 'addons.enable', 'store.orders.manage']
        as $capability
    ) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Admin_Capabilities
             (AdminRecordID, Capability, GrantedByAdminRecordID)
             VALUES (?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $statement,
            'isi',
            $actorId,
            $capability,
            $actorId
        );
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }
    red_wompi_c4c1_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT RoleName FROM RED_Admin_Roles
                 WHERE AdminRecordID=$actorId),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$actorId
                   AND Capability IN (
                     'addons.install','addons.enable','store.orders.manage'
                   )))"
        ) === 'owner:3',
        'Owner has exact lifecycle and order authority'
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $wompiPackage = $catalog['packages'][$wompiPackageId] ?? [];
    red_wompi_c4c1_assert(
        !empty($catalog['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.35'
            && ($wompiPackage['manifest']['version'] ?? null) === '0.1.5'
            && ($wompiPackage['integrity']['declaredFiles'] ?? null) === 19
            && ($wompiPackage['integrity']['verifiedFiles'] ?? null) === 19,
        'exact Store Lite and published Wompi 0.1.5 discover without execution'
    );
    red_wompi_c4c1_assert(
        red_wompi_c3b_record_enabled_store(
            $connection,
            $storePackage,
            $actorId
        ),
        'Store Lite enabled dependency baseline is recorded'
    );
    $installPlan = red_addon_install_plan(
        $connection,
        $wompiPackage,
        $actorId,
        false,
        $catalog
    );
    $installed = red_addon_install_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $installPlan['planSha256'] ?? ''
    );
    red_wompi_c4c1_assert(
        !empty($installPlan['valid'])
            && ($installed['status'] ?? null) === 'installed_disabled'
            && ($installed['version'] ?? null) === '0.1.5',
        'Wompi 0.1.5 installs disabled before separate enablement'
    );
    $publicKey = 'pub_' . 'test_' . str_repeat('a', 32);
    $references = [
        'wompi.private-key' => 'config:c4c1-wompi-private',
        'wompi.integrity-key' => 'config:c4c1-wompi-integrity',
        'wompi.event-secret' => 'config:c4c1-wompi-event',
    ];
    red_wompi_c4c1_assert(
        red_wompi_c3c1_store_settings(
            $connection,
            $wompiPackageId,
            $actorId,
            $publicKey,
            $references
        ),
        'client-local synthetic public key and three opaque references store'
    );
    $declarations = red_addon_secret_reference_declarations(
        array_values($references),
        ''
    );
    $enablePlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $wompiPackage,
        $actorId,
        $catalog,
        $declarations
    );
    $enabled = red_addon_payment_adapter_enable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $enablePlan['planSha256'] ?? '',
        $declarations
    );
    red_wompi_c4c1_assert(
        !empty($enablePlan['valid'])
            && ($enabled['status'] ?? null) === 'enabled'
            && ($enabled['version'] ?? null) === '0.1.5',
        'Wompi 0.1.5 enables with value-free secret declarations'
    );

    $registry = red_addon_runtime_register_package($wompiPackage);
    $snapshot = $registry->snapshot();
    $handler = $registry->handler(
        'adapters',
        'redcms.store-lite-wompi/checkout'
    );
    red_wompi_c4c1_assert(
        ($snapshot['packageId'] ?? null) === $wompiPackageId
            && ($snapshot['registrations']['adapters'] ?? null) === [
                'redcms.store-lite-wompi/checkout',
            ]
            && is_callable($handler),
        'contained registrar exposes only the declared adapter and route'
    );
    $probe = red_addon_adapter_invoke_registered(
        'redcms.store-lite-wompi/checkout',
        'contract.probe',
        [],
        $wompiPackageId,
        $handler,
        $wompiPackage['manifest'],
        null
    );
    red_wompi_c4c1_assert(
        !empty($probe['success'])
            && ($probe['data']['packageVersion'] ?? null) === '0.1.5'
            && ($probe['data']['merchantContractReadOnlyReady'] ?? null)
                === true
            && ($probe['data']['transactionTransportReady'] ?? null) === false
            && ($probe['data']['networkAccess'] ?? null) === false
            && ($probe['data']['providerContact'] ?? null) === false,
        'non-contact probe reports only merchant-read transport readiness'
    );

    $merchantPlan =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::plan([
            'publicKeySettingPresent' => true,
            'publicKeySha256' => hash('sha256', $publicKey),
        ]);
    $double =
        new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport_Double();
    $retrieved =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::execute(
            $merchantPlan,
            $publicKey,
            $double
        );
    red_wompi_c4c1_assert(
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::valid(
            $retrieved
        )
            && $double->callCount() === 1
            && !$retrieved['networkAccess']
            && !$retrieved['providerContact']
            && !$retrieved['providerMutation']
            && !$retrieved['transactionCreation']
            && !$retrieved['payment']
            && !$retrieved['eventRegistration']
            && !$retrieved['orderMutation']
            && !$retrieved['retryAuthorized'],
        'one sealed double produces bounded contracts with all real effects false'
    );
    $encoded = json_encode(
        $retrieved,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_wompi_c4c1_assert(
        !str_contains($encoded, $publicKey)
            && !str_contains($encoded, 'synthetic.end.user.')
            && !str_contains($encoded, 'synthetic.personal.auth.')
            && !$retrieved['responseBodyIncluded']
            && !$retrieved['responseHeadersIncluded']
            && !$retrieved['publicKeyIncluded']
            && !$retrieved['rawTokensReturned'],
        'bounded core-visible result retains no key, token, body, or header'
    );
    red_wompi_c4c1_assert(
        red_wompi_c3b_scalar(
            $connection,
            'SELECT CONCAT_WS(\':\',
                (SELECT COUNT(*)
                 FROM RED_Addon_StoreLite_Wompi_Payment_Attempts),
                (SELECT COUNT(*)
                 FROM RED_Addon_StoreLite_Wompi_Event_Receipts))'
        ) === '0:0',
        'merchant-read double creates no Wompi attempt or event business rows'
    );
    $adapterSource = (string) file_get_contents(
        $fixtureProject
            . '/addons/redcms/store-lite-wompi/WompiNequiOfflineAdapter.php'
    );
    red_wompi_c4c1_assert(
        substr_count(
            $adapterSource,
            "'merchant.acceptance-contracts.retrieve-sandbox'"
        ) === 1
            && substr_count(
                $adapterSource,
                'new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Curl_Transport()'
            ) === 1
            && !str_contains($adapterSource, '->secret(')
            && !str_contains($adapterSource, 'checkout.create')
            && !str_contains($adapterSource, 'transaction.create'),
        'adapter exposes one public-key-only read path and no transaction path'
    );

    $db->close();
    echo 'Wompi C4C1 core adoption self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    $db->close();
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
