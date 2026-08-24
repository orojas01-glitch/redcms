<?php
/** CLI-only C4C2 dry-run-first Wompi merchant-read sealed-double command. */

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
    . '/includes/addon_payment_adapter_wompi_merchant_read_preflight_helpers.php';

function red_wompi_merchant_read_double_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-wompi-merchant-read-double-execute.php " .
        "--actor-admin=ID\n" .
        "  php scripts/admin-wompi-merchant-read-double-execute.php " .
        "--actor-admin=ID \\\n" .
        "    --confirm-database=NAME " .
        "--confirm-package=redcms.store-lite-wompi \\\n" .
        "    --confirm-version=0.1.5 --confirm-state=enabled \\\n" .
        "    --confirm-client-scope-sha256=SHA256 \\\n" .
        "    --confirm-database-sha256=SHA256 \\\n" .
        "    --confirm-actor-subject-sha256=SHA256 \\\n" .
        "    --confirm-public-key-sha256=SHA256 \\\n" .
        "    --confirm-setting-state-sha256=SHA256 \\\n" .
        "    --confirm-reference-state-sha256=SHA256 \\\n" .
        "    --confirm-merchant-plan-sha256=SHA256 \\\n" .
        "    --confirm-preflight-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 \\\n" .
        "    --confirm-operation=merchant.acceptance-contracts.retrieve-sandbox-double \\\n" .
        "    --confirm-target=core-sealed-no-network-double \\\n" .
        "    --confirm-maximum-attempts=1 --confirm-retry-authorized=no \\\n" .
        "    --confirm-network-disabled=yes \\\n" .
        "    --confirm-real-provider-contact-authorized=no \\\n" .
        "    --confirm-provider-mutation-authorized=no \\\n" .
        "    --confirm-transaction-creation-authorized=no \\\n" .
        "    --confirm-payment-authorized=no \\\n" .
        "    --confirm-order-mutation-authorized=no --apply\n"
    );
}

$options = [
    'actorAdmin' => 0,
    'confirmDatabase' => '',
    'confirmPackage' => '',
    'confirmVersion' => '',
    'confirmState' => '',
    'confirmClientScopeSha256' => '',
    'confirmDatabaseSha256' => '',
    'confirmActorSubjectSha256' => '',
    'confirmPublicKeySha256' => '',
    'confirmSettingStateSha256' => '',
    'confirmReferenceStateSha256' => '',
    'confirmMerchantPlanSha256' => '',
    'confirmPreflightSha256' => '',
    'confirmBackupSha256' => '',
    'confirmOperation' => '',
    'confirmTarget' => '',
    'confirmMaximumAttempts' => '',
    'confirmRetryAuthorized' => '',
    'confirmNetworkDisabled' => '',
    'confirmRealProviderContactAuthorized' => '',
    'confirmProviderMutationAuthorized' => '',
    'confirmTransactionCreationAuthorized' => '',
    'confirmPaymentAuthorized' => '',
    'confirmOrderMutationAuthorized' => '',
    'apply' => false,
];
$argumentMap = [
    '--actor-admin=' => 'actorAdmin',
    '--confirm-database=' => 'confirmDatabase',
    '--confirm-package=' => 'confirmPackage',
    '--confirm-version=' => 'confirmVersion',
    '--confirm-state=' => 'confirmState',
    '--confirm-client-scope-sha256=' => 'confirmClientScopeSha256',
    '--confirm-database-sha256=' => 'confirmDatabaseSha256',
    '--confirm-actor-subject-sha256=' => 'confirmActorSubjectSha256',
    '--confirm-public-key-sha256=' => 'confirmPublicKeySha256',
    '--confirm-setting-state-sha256=' => 'confirmSettingStateSha256',
    '--confirm-reference-state-sha256=' => 'confirmReferenceStateSha256',
    '--confirm-merchant-plan-sha256=' => 'confirmMerchantPlanSha256',
    '--confirm-preflight-sha256=' => 'confirmPreflightSha256',
    '--confirm-backup-sha256=' => 'confirmBackupSha256',
    '--confirm-operation=' => 'confirmOperation',
    '--confirm-target=' => 'confirmTarget',
    '--confirm-maximum-attempts=' => 'confirmMaximumAttempts',
    '--confirm-retry-authorized=' => 'confirmRetryAuthorized',
    '--confirm-network-disabled=' => 'confirmNetworkDisabled',
    '--confirm-real-provider-contact-authorized=' =>
        'confirmRealProviderContactAuthorized',
    '--confirm-provider-mutation-authorized=' =>
        'confirmProviderMutationAuthorized',
    '--confirm-transaction-creation-authorized=' =>
        'confirmTransactionCreationAuthorized',
    '--confirm-payment-authorized=' => 'confirmPaymentAuthorized',
    '--confirm-order-mutation-authorized=' =>
        'confirmOrderMutationAuthorized',
];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $options['apply'] = true;
        continue;
    }
    $matched = false;
    foreach ($argumentMap as $prefix => $key) {
        if (str_starts_with($argument, $prefix)) {
            $value = substr($argument, strlen($prefix));
            $options[$key] = $key === 'actorAdmin' ? (int) $value : $value;
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        red_wompi_merchant_read_double_usage();
        exit(64);
    }
}
if ($options['actorAdmin'] <= 0) {
    red_wompi_merchant_read_double_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$plan = red_addon_wompi_merchant_read_preflight(
    $connection,
    $projectRoot,
    $options['actorAdmin']
);
if (empty($plan['ready'])) {
    fwrite(
        STDERR,
        'Wompi merchant-read double refused: '
            . ($plan['status'] ?? 'invalid')
            . (($plan['errors'] ?? []) === []
                ? ''
                : ' (' . implode(', ', $plan['errors']) . ')')
            . PHP_EOL
    );
    $db->close();
    exit(65);
}

$database = red_addon_install_database_name($connection);
printf("Database: %s\n", $database);
printf("Package: %s %s\n", $plan['packageId'], $plan['packageVersion']);
printf("Store package: %s %s\n", $plan['storePackageId'], $plan['storePackageVersion']);
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf("Current state: %s\n", $plan['lifecycleState']);
printf("Client scope SHA-256: %s\n", $plan['clientScopeSha256']);
printf("Database SHA-256: %s\n", $plan['databaseSha256']);
printf("Actor subject SHA-256: %s\n", $plan['actorSubjectSha256']);
printf("Public key SHA-256: %s\n", $plan['publicKeySha256']);
printf("Setting state SHA-256: %s\n", $plan['settingStateSha256']);
printf("Reference state SHA-256: %s\n", $plan['referenceStateSha256']);
printf("Merchant plan SHA-256: %s\n", $plan['merchantPlanSha256']);
printf("Preflight SHA-256: %s\n", $plan['preflightSha256']);
echo "Operation: merchant.acceptance-contracts.retrieve-sandbox-double\n";
echo "Target: core-sealed-no-network-double\n";
echo "Maximum attempts: 1\n";
echo "Retry authorized: no\n";
echo "Network disabled: yes\n";
echo "Real provider contact authorized: no\n";
echo "Provider mutation authorized: no\n";
echo "Transaction creation authorized: no\n";
echo "Payment authorized: no\n";
echo "Order mutation authorized: no\n";

if (!$options['apply']) {
    echo "DRY RUN: current client, Owner, packages, public-key hash, and opaque-reference state were revalidated.\n";
    echo "No package file or handler was loaded, no key value was printed, no double ran, and no network or Wompi contact occurred.\n";
    echo "Supply every printed confirmation with --apply to invoke only the sealed no-network double.\n";
    $db->close();
    exit(0);
}

$confirmationsValid =
    $options['confirmDatabase'] === $database
    && $options['confirmPackage'] === 'redcms.store-lite-wompi'
    && $options['confirmVersion'] === '0.1.5'
    && $options['confirmState'] === 'enabled'
    && hash_equals(
        $plan['clientScopeSha256'],
        $options['confirmClientScopeSha256']
    )
    && hash_equals(
        $plan['databaseSha256'],
        $options['confirmDatabaseSha256']
    )
    && hash_equals(
        $plan['actorSubjectSha256'],
        $options['confirmActorSubjectSha256']
    )
    && hash_equals(
        $plan['publicKeySha256'],
        $options['confirmPublicKeySha256']
    )
    && hash_equals(
        $plan['settingStateSha256'],
        $options['confirmSettingStateSha256']
    )
    && hash_equals(
        $plan['referenceStateSha256'],
        $options['confirmReferenceStateSha256']
    )
    && hash_equals(
        $plan['merchantPlanSha256'],
        $options['confirmMerchantPlanSha256']
    )
    && hash_equals(
        $plan['preflightSha256'],
        $options['confirmPreflightSha256']
    )
    && red_addon_wompi_claim_sha256($options['confirmBackupSha256'])
    && !hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    && $options['confirmOperation']
        === 'merchant.acceptance-contracts.retrieve-sandbox-double'
    && $options['confirmTarget'] === 'core-sealed-no-network-double'
    && $options['confirmMaximumAttempts'] === '1'
    && $options['confirmRetryAuthorized'] === 'no'
    && $options['confirmNetworkDisabled'] === 'yes'
    && $options['confirmRealProviderContactAuthorized'] === 'no'
    && $options['confirmProviderMutationAuthorized'] === 'no'
    && $options['confirmTransactionCreationAuthorized'] === 'no'
    && $options['confirmPaymentAuthorized'] === 'no'
    && $options['confirmOrderMutationAuthorized'] === 'no';
if (!$confirmationsValid) {
    fwrite(
        STDERR,
        "Apply requires every exact printed confirmation and one nonzero backup SHA-256.\n"
    );
    $db->close();
    exit(64);
}

$settingState = red_addon_wompi_merchant_read_setting_state($connection);
$publicKey = !empty($settingState['valid'])
    ? $settingState['publicKey']
    : '';
$packageRoot = $projectRoot . '/addons/redcms/store-lite-wompi';
foreach ([
    'WompiMerchantContractRequestPlanner.php',
    'WompiMerchantContractResponseGate.php',
    'WompiMerchantContractTransport.php',
    'WompiMerchantContractTransportDouble.php',
    'WompiMerchantContractRetrieval.php',
] as $file) {
    require_once $packageRoot . '/' . $file;
}
$merchantPlan =
    RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::plan([
        'publicKeySettingPresent' => true,
        'publicKeySha256' => $plan['publicKeySha256'],
    ]);
if (($merchantPlan['planSha256'] ?? '') !== $plan['merchantPlanSha256']) {
    $publicKey = '';
    $db->close();
    fwrite(STDERR, "Package merchant plan changed after confirmation.\n");
    exit(65);
}
$double =
    new RED_CMS_Store_Lite_Wompi_Merchant_Contract_Transport_Double();
$result = RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::execute(
    $merchantPlan,
    $publicKey,
    $double
);
$publicKey = '';
$db->close();

printf("Outcome: %s\n", $result['status'] ?? 'invalid');
echo "Sealed double calls: " . $double->callCount() . "\n";
echo "Durable attempt consumed: no\n";
echo "Replay protection active: no\n";
echo "Real provider contact authorized: no\n";
echo "Retry authorized: no\n";
echo "Network disabled: yes\n";

$expected =
    RED_CMS_Store_Lite_Wompi_Merchant_Contract_Retrieval::valid($result)
    && $double->callCount() === 1
    && ($result['networkAccess'] ?? null) === false
    && ($result['providerContact'] ?? null) === false
    && ($result['providerMutation'] ?? null) === false
    && ($result['transactionCreation'] ?? null) === false
    && ($result['payment'] ?? null) === false
    && ($result['eventRegistration'] ?? null) === false
    && ($result['orderMutation'] ?? null) === false
    && ($result['retryAuthorized'] ?? null) === false
    && ($result['responseBodyIncluded'] ?? null) === false
    && ($result['responseHeadersIncluded'] ?? null) === false
    && ($result['publicKeyIncluded'] ?? null) === false
    && ($result['rawTokensReturned'] ?? null) === false;
if (!$expected) {
    fwrite(STDERR, "Sealed merchant-read double did not produce the bounded result.\n");
    exit(1);
}
printf("Contracts SHA-256: %s\n", $result['contractsSha256']);
printf("Transport evidence SHA-256: %s\n", $result['transportEvidenceSha256']);
echo "Observed the exact bounded Wompi merchant-read sealed-double result.\n";
echo "No real key value, network request, Wompi contact, transaction, payment, event, order mutation, retry, or client action occurred.\n";
exit(0);

?>
