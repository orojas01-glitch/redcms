<?php
/** CLI-only C4C3A dry-run-first durable Wompi provider-double command. */

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
    . '/includes/addon_payment_adapter_wompi_merchant_read_durable_helpers.php';

function red_wompi_merchant_provider_double_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-wompi-merchant-read-provider-double-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/authorization.json\n" .
        "  Repeat the dry-run output confirmations and add --apply.\n"
    );
}

$options = [
    'actorAdmin' => 0,
    'evidenceFile' => '',
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
    'confirmAuthorizationSha256' => '',
    'confirmRequestSha256' => '',
    'confirmStartStateSha256' => '',
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
    'confirmEventRegistrationAuthorized' => '',
    'confirmOrderMutationAuthorized' => '',
    'apply' => false,
];
$argumentMap = [
    '--actor-admin=' => 'actorAdmin',
    '--evidence-file=' => 'evidenceFile',
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
    '--confirm-authorization-sha256=' => 'confirmAuthorizationSha256',
    '--confirm-request-sha256=' => 'confirmRequestSha256',
    '--confirm-start-state-sha256=' => 'confirmStartStateSha256',
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
    '--confirm-event-registration-authorized=' =>
        'confirmEventRegistrationAuthorized',
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
        red_wompi_merchant_provider_double_usage();
        exit(64);
    }
}
if ($options['actorAdmin'] <= 0
    || $options['evidenceFile'] === ''
    || $options['evidenceFile'][0] !== '/'
    || !is_file($options['evidenceFile'])
) {
    red_wompi_merchant_provider_double_usage();
    exit(64);
}
try {
    $authorization = json_decode(
        (string) file_get_contents($options['evidenceFile']),
        true,
        16,
        JSON_THROW_ON_ERROR
    );
} catch (Throwable $throwable) {
    $authorization = null;
}
$evaluatedAtEpoch = time();
if (!is_array($authorization)
    || !red_addon_wompi_merchant_durable_authorization_valid(
        $authorization,
        $evaluatedAtEpoch
    )
) {
    fwrite(STDERR, "C4C3A authorization evidence refused.\n");
    exit(65);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$plan = red_addon_wompi_merchant_durable_plan(
    $connection,
    $projectRoot,
    $options['actorAdmin'],
    $authorization,
    $evaluatedAtEpoch
);
if (empty($plan['ready'])) {
    fwrite(
        STDERR,
        'C4C3A provider-double refused: ' . ($plan['status'] ?? 'invalid')
            . (($plan['errors'] ?? []) === []
                ? '' : ' (' . implode(', ', $plan['errors']) . ')')
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
printf("Authorization SHA-256: %s\n", $plan['authorizationSha256']);
printf("Request SHA-256: %s\n", $plan['requestSha256']);
printf("Start state SHA-256: %s\n", $plan['executionStartStateSha256']);
printf("Authorization expires at epoch: %d\n", $plan['expiresAtEpoch']);
echo "Operation: merchant.acceptance-contracts.provider-double\n";
echo "Target: core-durable-provider-double\n";
echo "Maximum attempts: 1\n";
echo "Retry authorized: no\n";
echo "Network disabled: yes\n";
echo "Real provider contact authorized: no\n";
echo "Provider mutation authorized: no\n";
echo "Transaction creation authorized: no\n";
echo "Payment authorized: no\n";
echo "Event registration authorized: no\n";
echo "Order mutation authorized: no\n";

if (!$options['apply']) {
    echo "DRY RUN: current client, Owner, packages, authorization, hashes, and unused durable start were revalidated.\n";
    echo "No provider double ran, no durable row was written, no secret was resolved, and no network or Wompi contact occurred.\n";
    echo "Supply every printed confirmation with --apply to consume this authorization exactly once.\n";
    $db->close();
    exit(0);
}

$confirmationsValid =
    $options['confirmDatabase'] === $database
    && $options['confirmPackage'] === 'redcms.store-lite-wompi'
    && $options['confirmVersion'] === '0.1.5'
    && $options['confirmState'] === 'enabled'
    && hash_equals($plan['clientScopeSha256'], $options['confirmClientScopeSha256'])
    && hash_equals($plan['databaseSha256'], $options['confirmDatabaseSha256'])
    && hash_equals($plan['actorSubjectSha256'], $options['confirmActorSubjectSha256'])
    && hash_equals($plan['publicKeySha256'], $options['confirmPublicKeySha256'])
    && hash_equals($plan['settingStateSha256'], $options['confirmSettingStateSha256'])
    && hash_equals($plan['referenceStateSha256'], $options['confirmReferenceStateSha256'])
    && hash_equals($plan['merchantPlanSha256'], $options['confirmMerchantPlanSha256'])
    && hash_equals($plan['preflightSha256'], $options['confirmPreflightSha256'])
    && hash_equals($plan['authorizationSha256'], $options['confirmAuthorizationSha256'])
    && hash_equals($plan['requestSha256'], $options['confirmRequestSha256'])
    && hash_equals($plan['executionStartStateSha256'], $options['confirmStartStateSha256'])
    && red_addon_wompi_claim_sha256($options['confirmBackupSha256'])
    && !hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    && $options['confirmOperation']
        === 'merchant.acceptance-contracts.provider-double'
    && $options['confirmTarget'] === 'core-durable-provider-double'
    && $options['confirmMaximumAttempts'] === '1'
    && $options['confirmRetryAuthorized'] === 'no'
    && $options['confirmNetworkDisabled'] === 'yes'
    && $options['confirmRealProviderContactAuthorized'] === 'no'
    && $options['confirmProviderMutationAuthorized'] === 'no'
    && $options['confirmTransactionCreationAuthorized'] === 'no'
    && $options['confirmPaymentAuthorized'] === 'no'
    && $options['confirmEventRegistrationAuthorized'] === 'no'
    && $options['confirmOrderMutationAuthorized'] === 'no';
if (!$confirmationsValid) {
    fwrite(
        STDERR,
        "Apply requires every exact printed confirmation and one nonzero backup SHA-256.\n"
    );
    $db->close();
    exit(64);
}

$double = new RED_Addon_Wompi_Merchant_Read_Provider_Double();
$result = red_addon_wompi_merchant_durable_execute(
    $connection,
    $projectRoot,
    $options['actorAdmin'],
    $authorization,
    $plan['executionStartStateSha256'],
    $double,
    $evaluatedAtEpoch
);
$db->close();

printf("Outcome: %s\n", $result['status'] ?? 'invalid');
echo "Provider-double calls: " . $double->callCount() . "\n";
echo "Durable attempt consumed: "
    . (!empty($result['executionStarted']) ? 'yes' : 'no') . "\n";
echo "Replay protection active: "
    . (!empty($result['replayProtectionActive']) ? 'yes' : 'no') . "\n";
echo "Real provider contact authorized: no\n";
echo "Retry authorized: no\n";
echo "Network disabled: yes\n";

$outcome = $result['boundedOutcome'] ?? [];
$expected =
    ($result['status'] ?? null) === 'merchant_provider_double_completed'
    && $double->callCount() === 1
    && !empty($result['executionStarted'])
    && !empty($result['outcomeRecorded'])
    && !empty($result['replayProtectionActive'])
    && ($outcome['networkAccess'] ?? null) === false
    && ($outcome['providerContact'] ?? null) === false
    && ($outcome['providerMutation'] ?? null) === false
    && ($outcome['transactionCreation'] ?? null) === false
    && ($outcome['payment'] ?? null) === false
    && ($outcome['eventRegistration'] ?? null) === false
    && ($outcome['orderMutation'] ?? null) === false
    && ($outcome['retryAuthorized'] ?? null) === false;
if (!$expected) {
    fwrite(STDERR, "Durable provider double did not produce the bounded result.\n");
    exit(1);
}
printf("Outcome evidence SHA-256: %s\n", $result['outcomeEvidenceSha256']);
printf("Outcome state SHA-256: %s\n", $result['outcomeStateSha256']);
echo "Observed one durable bounded Wompi merchant-read provider-double result.\n";
echo "No real key value, network request, Wompi contact, provider mutation, transaction, payment, event, order mutation, retry, or client action occurred.\n";
exit(0);

?>
