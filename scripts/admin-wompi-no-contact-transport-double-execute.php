<?php
/** CLI-only C4B4D dry-run-first Wompi sealed-double operator command. */

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
    . '/includes/addon_payment_adapter_wompi_transport_double_helpers.php';

function red_wompi_no_contact_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-wompi-no-contact-transport-double-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/path.json\n" .
        "  php scripts/admin-wompi-no-contact-transport-double-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/path.json \\\n" .
        "    --confirm-database=NAME " .
        "--confirm-package=redcms.store-lite-wompi \\\n" .
        "    --confirm-version=0.1.4 --confirm-state=enabled \\\n" .
        "    --confirm-client-scope-sha256=SHA256 \\\n" .
        "    --confirm-database-sha256=SHA256 \\\n" .
        "    --confirm-actor-subject-sha256=SHA256 \\\n" .
        "    --confirm-order-sha256=SHA256 --confirm-plan-sha256=SHA256 \\\n" .
        "    --confirm-wire-request-sha256=SHA256 \\\n" .
        "    --confirm-authorization-sha256=SHA256 \\\n" .
        "    --confirm-authorization-state-sha256=SHA256 \\\n" .
        "    --confirm-claim-sha256=SHA256 \\\n" .
        "    --confirm-claim-state-sha256=SHA256 \\\n" .
        "    --confirm-request-sha256=SHA256 \\\n" .
        "    --confirm-execution-start-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 \\\n" .
        "    --confirm-operation=checkout.create-sandbox-no-contact-double \\\n" .
        "    --confirm-target=core-sealed-in-memory-double \\\n" .
        "    --confirm-maximum-attempts=1 --confirm-retry-authorized=no \\\n" .
        "    --confirm-network-disabled=yes \\\n" .
        "    --confirm-provider-contact-denied=yes \\\n" .
        "    --confirm-provider-mutation-denied=yes \\\n" .
        "    --confirm-transaction-creation-denied=yes \\\n" .
        "    --confirm-payment-denied=yes \\\n" .
        "    --confirm-order-mutation-denied=yes --apply\n"
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
    'confirmOrderSha256' => '',
    'confirmPlanSha256' => '',
    'confirmWireRequestSha256' => '',
    'confirmAuthorizationSha256' => '',
    'confirmAuthorizationStateSha256' => '',
    'confirmClaimSha256' => '',
    'confirmClaimStateSha256' => '',
    'confirmRequestSha256' => '',
    'confirmExecutionStartSha256' => '',
    'confirmBackupSha256' => '',
    'confirmOperation' => '',
    'confirmTarget' => '',
    'confirmMaximumAttempts' => '',
    'confirmRetryAuthorized' => '',
    'confirmNetworkDisabled' => '',
    'confirmProviderContactDenied' => '',
    'confirmProviderMutationDenied' => '',
    'confirmTransactionCreationDenied' => '',
    'confirmPaymentDenied' => '',
    'confirmOrderMutationDenied' => '',
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
    '--confirm-order-sha256=' => 'confirmOrderSha256',
    '--confirm-plan-sha256=' => 'confirmPlanSha256',
    '--confirm-wire-request-sha256=' => 'confirmWireRequestSha256',
    '--confirm-authorization-sha256=' => 'confirmAuthorizationSha256',
    '--confirm-authorization-state-sha256=' =>
        'confirmAuthorizationStateSha256',
    '--confirm-claim-sha256=' => 'confirmClaimSha256',
    '--confirm-claim-state-sha256=' => 'confirmClaimStateSha256',
    '--confirm-request-sha256=' => 'confirmRequestSha256',
    '--confirm-execution-start-sha256=' =>
        'confirmExecutionStartSha256',
    '--confirm-backup-sha256=' => 'confirmBackupSha256',
    '--confirm-operation=' => 'confirmOperation',
    '--confirm-target=' => 'confirmTarget',
    '--confirm-maximum-attempts=' => 'confirmMaximumAttempts',
    '--confirm-retry-authorized=' => 'confirmRetryAuthorized',
    '--confirm-network-disabled=' => 'confirmNetworkDisabled',
    '--confirm-provider-contact-denied=' => 'confirmProviderContactDenied',
    '--confirm-provider-mutation-denied=' => 'confirmProviderMutationDenied',
    '--confirm-transaction-creation-denied=' =>
        'confirmTransactionCreationDenied',
    '--confirm-payment-denied=' => 'confirmPaymentDenied',
    '--confirm-order-mutation-denied=' => 'confirmOrderMutationDenied',
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
        red_wompi_no_contact_cli_usage();
        exit(64);
    }
}
if ($options['actorAdmin'] <= 0 || $options['evidenceFile'] === '') {
    red_wompi_no_contact_cli_usage();
    exit(64);
}

$evidencePath = $options['evidenceFile'];
$evidenceRealPath = str_starts_with($evidencePath, DIRECTORY_SEPARATOR)
    ? realpath($evidencePath)
    : false;
if (!is_string($evidenceRealPath)
    || !str_starts_with($evidenceRealPath, DIRECTORY_SEPARATOR)
    || !is_file($evidenceRealPath)
    || is_link($evidencePath)
    || filesize($evidenceRealPath) < 2
    || filesize($evidenceRealPath) > 65536
) {
    fwrite(
        STDERR,
        "Evidence must be one absolute regular JSON file no larger than 64 KiB.\n"
    );
    exit(64);
}
try {
    $evidence = json_decode(
        (string) file_get_contents($evidenceRealPath),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
} catch (Throwable $throwable) {
    $evidence = null;
}
if (!is_array($evidence)
    || !red_addon_wompi_claim_exact_keys(
        $evidence,
        ['authorization', 'claim']
    )
    || !is_array($evidence['authorization'] ?? null)
    || !is_array($evidence['claim'] ?? null)
) {
    fwrite(
        STDERR,
        "Evidence must contain only exact authorization and claim objects.\n"
    );
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$packageId = 'redcms.store-lite-wompi';
$package = $catalog['packages'][$packageId] ?? null;
$evaluatedAtEpoch = time();
if (empty($catalog['valid']) || !is_array($package)) {
    fwrite(STDERR, "Exact Wompi discovery or trust validation failed.\n");
    $db->close();
    exit(65);
}
$plan = red_addon_wompi_transport_plan(
    $connection,
    $package,
    $catalog,
    $options['actorAdmin'],
    $evidence['authorization'],
    $evidence['claim'],
    $evaluatedAtEpoch
);
if (empty($plan['ready'])) {
    fwrite(
        STDERR,
        'Wompi no-contact sealed-double execution refused: '
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
$orderSha256 = hash('sha256', $plan['orderId']);
printf("Database: %s\n", $database);
printf("Package: %s %s\n", $plan['packageId'], $plan['packageVersion']);
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf("Current state: %s\n", $plan['lifecycleState']);
printf("Expires at epoch: %d\n", $plan['expiresAtEpoch']);
printf("Client scope SHA-256: %s\n", $plan['clientScopeSha256']);
printf("Database SHA-256: %s\n", $plan['databaseSha256']);
printf("Actor subject SHA-256: %s\n", $plan['actorSubjectSha256']);
printf("Order SHA-256: %s\n", $orderSha256);
printf("Plan SHA-256: %s\n", $plan['planSha256']);
printf("Wire request SHA-256: %s\n", $plan['wireRequestSha256']);
printf("Authorization SHA-256: %s\n", $plan['authorizationSha256']);
printf(
    "Authorization state SHA-256: %s\n",
    $plan['authorizationStateSha256']
);
printf("Claim SHA-256: %s\n", $plan['claimSha256']);
printf("Claim state SHA-256: %s\n", $plan['claimStateSha256']);
printf("Request SHA-256: %s\n", $plan['requestSha256']);
printf(
    "Execution start SHA-256: %s\n",
    $plan['executionStartStateSha256']
);
echo "Operation: checkout.create-sandbox-no-contact-double\n";
echo "Target: core-sealed-in-memory-double\n";
echo "Maximum attempts: 1\n";
echo "Retry authorized: no\n";
echo "Network disabled: yes\n";
echo "Provider contact denied: yes\n";
echo "Provider mutation denied: yes\n";
echo "Transaction creation denied: yes\n";
echo "Payment denied: yes\n";
echo "Order mutation denied: yes\n";

if (!$options['apply']) {
    echo "DRY RUN: durable authorization, claim, package, and start evidence were revalidated.\n";
    echo "No double ran; no credential was resolved; no network, Wompi, transaction, payment, or order contact occurred.\n";
    echo "Retain a verified backup and supply every printed confirmation with --apply before expiry.\n";
    $db->close();
    exit(0);
}

$confirmationsValid =
    $options['confirmDatabase'] === $database
    && $options['confirmPackage'] === $plan['packageId']
    && $options['confirmVersion'] === '0.1.4'
    && $options['confirmVersion'] === $plan['packageVersion']
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
    && hash_equals($orderSha256, $options['confirmOrderSha256'])
    && hash_equals($plan['planSha256'], $options['confirmPlanSha256'])
    && hash_equals(
        $plan['wireRequestSha256'],
        $options['confirmWireRequestSha256']
    )
    && hash_equals(
        $plan['authorizationSha256'],
        $options['confirmAuthorizationSha256']
    )
    && hash_equals(
        $plan['authorizationStateSha256'],
        $options['confirmAuthorizationStateSha256']
    )
    && hash_equals($plan['claimSha256'], $options['confirmClaimSha256'])
    && hash_equals(
        $plan['claimStateSha256'],
        $options['confirmClaimStateSha256']
    )
    && hash_equals($plan['requestSha256'], $options['confirmRequestSha256'])
    && hash_equals(
        $plan['executionStartStateSha256'],
        $options['confirmExecutionStartSha256']
    )
    && red_addon_wompi_claim_sha256($options['confirmBackupSha256'])
    && !hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    && $options['confirmOperation']
        === 'checkout.create-sandbox-no-contact-double'
    && $options['confirmTarget'] === 'core-sealed-in-memory-double'
    && $options['confirmMaximumAttempts'] === '1'
    && $options['confirmRetryAuthorized'] === 'no'
    && $options['confirmNetworkDisabled'] === 'yes'
    && $options['confirmProviderContactDenied'] === 'yes'
    && $options['confirmProviderMutationDenied'] === 'yes'
    && $options['confirmTransactionCreationDenied'] === 'yes'
    && $options['confirmPaymentDenied'] === 'yes'
    && $options['confirmOrderMutationDenied'] === 'yes';
if (!$confirmationsValid) {
    fwrite(
        STDERR,
        "Apply requires every exact printed confirmation and one nonzero backup SHA-256.\n"
    );
    $db->close();
    exit(64);
}

$double = new RED_Addon_Wompi_No_Contact_Transport_Double('completed');
$result = red_addon_wompi_transport_execute(
    $connection,
    $projectRoot,
    $options['actorAdmin'],
    $evidence['authorization'],
    $evidence['claim'],
    $options['confirmExecutionStartSha256'],
    $double,
    $evaluatedAtEpoch
);
$db->close();

$outcome = $result['boundedOutcome'] ?? null;
printf("Outcome: %s\n", $result['status'] ?? 'invalid');
echo 'Attempt consumed: ' . (!empty($result['executionStarted']) ? 'yes' : 'no') . "\n";
echo "Retry authorized: no\n";
echo "Network disabled: yes\n";
echo "Provider contact denied: yes\n";
echo "Provider mutation denied: yes\n";
echo "Transaction creation denied: yes\n";
echo "Payment denied: yes\n";
echo "Order mutation denied: yes\n";

$expected = ($result['status'] ?? '') === 'sealed_double_completed'
    && $double->callCount() === 1
    && !empty($result['executionStarted'])
    && !empty($result['outcomeRecorded'])
    && !empty($result['outcomeAuditRecorded'])
    && is_array($outcome)
    && ($outcome['simulationObserved'] ?? null) === true
    && ($outcome['networkAccess'] ?? null) === false
    && ($outcome['providerContact'] ?? null) === false
    && ($outcome['providerMutation'] ?? null) === false
    && ($outcome['transactionCreation'] ?? null) === false
    && ($outcome['paymentVerified'] ?? null) === false
    && ($outcome['paymentApplied'] ?? null) === false
    && ($outcome['orderMutation'] ?? null) === false
    && ($outcome['retryAuthorized'] ?? null) === false;
if (!$expected) {
    fwrite(
        STDERR,
        "Sealed-double rehearsal did not produce the exact bounded result. The attempt remains consumed and no retry is authorized.\n"
    );
    exit(1);
}
echo "Observed the exact bounded core-owned Wompi sealed-double result.\n";
echo "No credential, network request, Wompi contact/mutation, transaction, payment, event, order mutation, retry, or client action occurred.\n";
exit(0);
