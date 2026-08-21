<?php
/** CLI-only P3E-9C3B1 one-shot transport-double operator command. */

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
    . '/includes/addon_sandbox_checkout_mutation_transport_double_helpers.php';

function red_addon_checkout_transport_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-sandbox-checkout-transport-double-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/path.json\n" .
        "  php scripts/admin-sandbox-checkout-transport-double-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/path.json \\\n" .
        "    --confirm-database=NAME " .
        "--confirm-package=redcms.store-lite-stripe-checkout \\\n" .
        "    --confirm-version=0.1.5 --confirm-state=enabled \\\n" .
        "    --confirm-plan-sha256=SHA256 --confirm-input-sha256=SHA256 \\\n" .
        "    --confirm-authorization-sha256=SHA256 \\\n" .
        "    --confirm-authorization-state-sha256=SHA256 \\\n" .
        "    --confirm-claim-state-sha256=SHA256 \\\n" .
        "    --confirm-execution-start-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 \\\n" .
        "    --confirm-operation=checkout.create-sandbox-transport-double \\\n" .
        "    --confirm-target=core-in-memory-transport-double \\\n" .
        "    --confirm-maximum-attempts=1 --confirm-retry-authorized=no \\\n" .
        "    --confirm-network-authorized=no \\\n" .
        "    --confirm-provider-mutation-authorized=no \\\n" .
        "    --confirm-checkout-creation-authorized=no --apply\n"
    );
}

$options = [
    'actorAdmin' => 0,
    'evidenceFile' => '',
    'confirmDatabase' => '',
    'confirmPackage' => '',
    'confirmVersion' => '',
    'confirmState' => '',
    'confirmPlanSha256' => '',
    'confirmInputSha256' => '',
    'confirmAuthorizationSha256' => '',
    'confirmAuthorizationStateSha256' => '',
    'confirmClaimStateSha256' => '',
    'confirmExecutionStartSha256' => '',
    'confirmBackupSha256' => '',
    'confirmOperation' => '',
    'confirmTarget' => '',
    'confirmMaximumAttempts' => '',
    'confirmRetryAuthorized' => '',
    'confirmNetworkAuthorized' => '',
    'confirmProviderMutationAuthorized' => '',
    'confirmCheckoutCreationAuthorized' => '',
    'apply' => false,
];
$argumentMap = [
    '--actor-admin=' => 'actorAdmin',
    '--evidence-file=' => 'evidenceFile',
    '--confirm-database=' => 'confirmDatabase',
    '--confirm-package=' => 'confirmPackage',
    '--confirm-version=' => 'confirmVersion',
    '--confirm-state=' => 'confirmState',
    '--confirm-plan-sha256=' => 'confirmPlanSha256',
    '--confirm-input-sha256=' => 'confirmInputSha256',
    '--confirm-authorization-sha256=' => 'confirmAuthorizationSha256',
    '--confirm-authorization-state-sha256=' =>
        'confirmAuthorizationStateSha256',
    '--confirm-claim-state-sha256=' => 'confirmClaimStateSha256',
    '--confirm-execution-start-sha256=' =>
        'confirmExecutionStartSha256',
    '--confirm-backup-sha256=' => 'confirmBackupSha256',
    '--confirm-operation=' => 'confirmOperation',
    '--confirm-target=' => 'confirmTarget',
    '--confirm-maximum-attempts=' => 'confirmMaximumAttempts',
    '--confirm-retry-authorized=' => 'confirmRetryAuthorized',
    '--confirm-network-authorized=' => 'confirmNetworkAuthorized',
    '--confirm-provider-mutation-authorized=' =>
        'confirmProviderMutationAuthorized',
    '--confirm-checkout-creation-authorized=' =>
        'confirmCheckoutCreationAuthorized',
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
        red_addon_checkout_transport_cli_usage();
        exit(64);
    }
}
if ($options['actorAdmin'] <= 0 || $options['evidenceFile'] === '') {
    red_addon_checkout_transport_cli_usage();
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
    fwrite(STDERR, "Evidence must be one absolute regular JSON file no larger than 64 KiB.\n");
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
    || !red_addon_checkout_synthetic_exact_keys(
        $evidence,
        ['input', 'prepared']
    )
    || !is_array($evidence['input'] ?? null)
    || !is_array($evidence['prepared'] ?? null)
) {
    fwrite(STDERR, "Evidence must contain only exact input and prepared objects.\n");
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$packageId = 'redcms.store-lite-stripe-checkout';
$package = $catalog['packages'][$packageId] ?? null;
if (empty($catalog['valid']) || !is_array($package)) {
    fwrite(STDERR, "Exact adapter discovery or trust validation failed.\n");
    $db->close();
    exit(65);
}
$evaluatedAtUtc = gmdate('Y-m-d\TH:i:s\Z');
$plan = red_addon_checkout_mutation_transport_plan(
    $connection,
    $package,
    $catalog,
    $options['actorAdmin'],
    $evidence['input'],
    $evidence['prepared'],
    $evaluatedAtUtc
);
if (empty($plan['ready'])) {
    fwrite(
        STDERR,
        'Checkout transport-double execution refused: '
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
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf("Current state: %s\n", $plan['lifecycleState']);
printf("Expires at UTC: %s\n", $plan['expiresAtUtc']);
printf("Plan SHA-256: %s\n", $plan['syntheticPlanSha256']);
printf("Input SHA-256: %s\n", $plan['inputSha256']);
printf("Authorization SHA-256: %s\n", $plan['authorizationSha256']);
printf("Authorization state SHA-256: %s\n", $plan['authorizationStateSha256']);
printf("Claim state SHA-256: %s\n", $plan['claimStateSha256']);
printf("Execution start SHA-256: %s\n", $plan['executionStartStateSha256']);
echo "Operation: checkout.create-sandbox-transport-double\n";
echo "Target: core-in-memory-transport-double\n";
echo "Maximum attempts: 1\n";
echo "Retry authorized: no\n";
echo "Network authorized: no\n";
echo "Provider mutation authorized: no\n";
echo "Checkout creation authorized: no\n";

if (!$options['apply']) {
    echo "DRY RUN: authorization, claim, package, and start evidence were revalidated.\n";
    echo "No credential was resolved, no package handler was invoked, no double ran, and no network or provider contact occurred.\n";
    echo "Retain a verified backup and supply every printed confirmation with --apply before expiry.\n";
    $db->close();
    exit(0);
}

$confirmationsValid =
    $options['confirmDatabase'] === $database
    && $options['confirmPackage'] === $plan['packageId']
    && $options['confirmVersion'] === '0.1.5'
    && $options['confirmVersion'] === $plan['packageVersion']
    && $options['confirmState'] === 'enabled'
    && hash_equals($plan['syntheticPlanSha256'], $options['confirmPlanSha256'])
    && hash_equals($plan['inputSha256'], $options['confirmInputSha256'])
    && hash_equals(
        $plan['authorizationSha256'],
        $options['confirmAuthorizationSha256']
    )
    && hash_equals(
        $plan['authorizationStateSha256'],
        $options['confirmAuthorizationStateSha256']
    )
    && hash_equals($plan['claimStateSha256'], $options['confirmClaimStateSha256'])
    && hash_equals(
        $plan['executionStartStateSha256'],
        $options['confirmExecutionStartSha256']
    )
    && red_addon_provider_contact_sha256($options['confirmBackupSha256'])
    && !hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    && $options['confirmOperation']
        === 'checkout.create-sandbox-transport-double'
    && $options['confirmTarget'] === 'core-in-memory-transport-double'
    && $options['confirmMaximumAttempts'] === '1'
    && $options['confirmRetryAuthorized'] === 'no'
    && $options['confirmNetworkAuthorized'] === 'no'
    && $options['confirmProviderMutationAuthorized'] === 'no'
    && $options['confirmCheckoutCreationAuthorized'] === 'no';
if (!$confirmationsValid) {
    fwrite(STDERR, "Apply requires every exact printed confirmation and one nonzero backup SHA-256.\n");
    $db->close();
    exit(64);
}

$double = new RED_Addon_Checkout_Mutation_Transport_Double('completed');
$result = red_addon_checkout_mutation_execute_transport_double(
    $connection,
    $projectRoot,
    $options['actorAdmin'],
    $evidence['input'],
    $evidence['prepared'],
    $options['confirmAuthorizationSha256'],
    $options['confirmAuthorizationStateSha256'],
    $options['confirmClaimStateSha256'],
    $options['confirmExecutionStartSha256'],
    $double,
    $evaluatedAtUtc
);
$db->close();

$outcome = $result['boundedOutcome'] ?? null;
printf("Outcome: %s\n", $result['status'] ?? 'invalid');
echo 'Attempt consumed: ' . (!empty($result['executionStarted']) ? 'yes' : 'no') . "\n";
echo "Retry authorized: no\n";
echo "Network authorized: no\n";
echo "Provider mutation authorized: no\n";
echo "Checkout creation authorized: no\n";

$expected = ($result['status'] ?? '') === 'transport_double_completed'
    && $double->callCount() === 1
    && !empty($result['executionStarted'])
    && !empty($result['outcomeRecorded'])
    && !empty($result['outcomeAuditRecorded'])
    && is_array($outcome)
    && ($outcome['simulationObserved'] ?? null) === true
    && ($outcome['networkAccess'] ?? null) === false
    && ($outcome['providerContact'] ?? null) === false
    && ($outcome['providerMutation'] ?? null) === false
    && ($outcome['checkoutCreation'] ?? null) === false
    && ($outcome['payment'] ?? null) === false
    && ($outcome['webhook'] ?? null) === false
    && ($outcome['storeLiteMutation'] ?? null) === false
    && ($outcome['retryAuthorized'] ?? null) === false;
if (!$expected) {
    fwrite(STDERR, "Transport-double rehearsal did not produce the exact bounded result. The attempt remains consumed and no retry is authorized.\n");
    exit(1);
}
echo "Observed the exact bounded core-owned transport-double result.\n";
echo "No credential, network request, provider mutation, Checkout Session, payment, webhook, Store Lite mutation, or client action occurred.\n";
exit(0);

?>
