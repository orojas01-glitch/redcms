<?php
/** CLI-only P3E-9D4C1 one-shot real-POST operator command. */

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
    . '/includes/addon_sandbox_checkout_real_mutation_helpers.php';

function red_addon_checkout_real_post_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-sandbox-checkout-real-post-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/path.json\n" .
        "  php scripts/admin-sandbox-checkout-real-post-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/path.json \\\n" .
        "    --confirm-database=NAME --confirm-database-sha256=SHA256 \\\n" .
        "    --confirm-package=redcms.store-lite-stripe-checkout \\\n" .
        "    --confirm-version=0.1.8 --confirm-state=enabled \\\n" .
        "    --confirm-store-package=redcms.store-lite \\\n" .
        "    --confirm-store-version=0.1.35 \\\n" .
        "    --confirm-preflight-plan-sha256=SHA256 \\\n" .
        "    --confirm-preflight-start-identity-sha256=SHA256 \\\n" .
        "    --confirm-preflight-result-identity-sha256=SHA256 \\\n" .
        "    --confirm-input-sha256=SHA256 \\\n" .
        "    --confirm-synthetic-plan-sha256=SHA256 \\\n" .
        "    --confirm-contract-sha256=SHA256 \\\n" .
        "    --confirm-request-sha256=SHA256 \\\n" .
        "    --confirm-order-snapshot-sha256=SHA256 \\\n" .
        "    --confirm-authorization-sha256=SHA256 \\\n" .
        "    --confirm-authorization-state-sha256=SHA256 \\\n" .
        "    --confirm-claim-state-sha256=SHA256 \\\n" .
        "    --confirm-execution-start-sha256=SHA256 \\\n" .
        "    --confirm-secret-availability-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 \\\n" .
        "    --confirm-operation=checkout.create-sandbox-real-post \\\n" .
        "    --confirm-target=stripe-sandbox-real-post \\\n" .
        "    --confirm-maximum-attempts=1 \\\n" .
        "    --confirm-provider-contact-authorized=yes \\\n" .
        "    --confirm-provider-mutation-authorized=yes \\\n" .
        "    --confirm-checkout-creation-authorized=yes \\\n" .
        "    --confirm-payment-authorized=no \\\n" .
        "    --confirm-webhook-authorized=no \\\n" .
        "    --confirm-browser-navigation-authorized=no \\\n" .
        "    --confirm-store-lite-mutation-authorized=no \\\n" .
        "    --confirm-session-expiration-authorized=no \\\n" .
        "    --confirm-retry-authorized=no \\\n" .
        "    --confirm-live-mode-authorized=no \\\n" .
        "    --confirm-client-deployment-authorized=no --apply\n"
    );
}

$options = [
    'actorAdmin' => 0,
    'evidenceFile' => '',
    'confirmDatabase' => '',
    'confirmDatabaseSha256' => '',
    'confirmPackage' => '',
    'confirmVersion' => '',
    'confirmState' => '',
    'confirmStorePackage' => '',
    'confirmStoreVersion' => '',
    'confirmPreflightPlanSha256' => '',
    'confirmPreflightStartIdentitySha256' => '',
    'confirmPreflightResultIdentitySha256' => '',
    'confirmInputSha256' => '',
    'confirmSyntheticPlanSha256' => '',
    'confirmContractSha256' => '',
    'confirmRequestSha256' => '',
    'confirmOrderSnapshotSha256' => '',
    'confirmAuthorizationSha256' => '',
    'confirmAuthorizationStateSha256' => '',
    'confirmClaimStateSha256' => '',
    'confirmExecutionStartSha256' => '',
    'confirmSecretAvailabilitySha256' => '',
    'confirmBackupSha256' => '',
    'confirmOperation' => '',
    'confirmTarget' => '',
    'confirmMaximumAttempts' => '',
    'confirmProviderContactAuthorized' => '',
    'confirmProviderMutationAuthorized' => '',
    'confirmCheckoutCreationAuthorized' => '',
    'confirmPaymentAuthorized' => '',
    'confirmWebhookAuthorized' => '',
    'confirmBrowserNavigationAuthorized' => '',
    'confirmStoreLiteMutationAuthorized' => '',
    'confirmSessionExpirationAuthorized' => '',
    'confirmRetryAuthorized' => '',
    'confirmLiveModeAuthorized' => '',
    'confirmClientDeploymentAuthorized' => '',
    'apply' => false,
];
$argumentMap = [
    '--actor-admin=' => 'actorAdmin',
    '--evidence-file=' => 'evidenceFile',
    '--confirm-database=' => 'confirmDatabase',
    '--confirm-database-sha256=' => 'confirmDatabaseSha256',
    '--confirm-package=' => 'confirmPackage',
    '--confirm-version=' => 'confirmVersion',
    '--confirm-state=' => 'confirmState',
    '--confirm-store-package=' => 'confirmStorePackage',
    '--confirm-store-version=' => 'confirmStoreVersion',
    '--confirm-preflight-plan-sha256=' => 'confirmPreflightPlanSha256',
    '--confirm-preflight-start-identity-sha256=' =>
        'confirmPreflightStartIdentitySha256',
    '--confirm-preflight-result-identity-sha256=' =>
        'confirmPreflightResultIdentitySha256',
    '--confirm-input-sha256=' => 'confirmInputSha256',
    '--confirm-synthetic-plan-sha256=' => 'confirmSyntheticPlanSha256',
    '--confirm-contract-sha256=' => 'confirmContractSha256',
    '--confirm-request-sha256=' => 'confirmRequestSha256',
    '--confirm-order-snapshot-sha256=' => 'confirmOrderSnapshotSha256',
    '--confirm-authorization-sha256=' => 'confirmAuthorizationSha256',
    '--confirm-authorization-state-sha256=' =>
        'confirmAuthorizationStateSha256',
    '--confirm-claim-state-sha256=' => 'confirmClaimStateSha256',
    '--confirm-execution-start-sha256=' =>
        'confirmExecutionStartSha256',
    '--confirm-secret-availability-sha256=' =>
        'confirmSecretAvailabilitySha256',
    '--confirm-backup-sha256=' => 'confirmBackupSha256',
    '--confirm-operation=' => 'confirmOperation',
    '--confirm-target=' => 'confirmTarget',
    '--confirm-maximum-attempts=' => 'confirmMaximumAttempts',
    '--confirm-provider-contact-authorized=' =>
        'confirmProviderContactAuthorized',
    '--confirm-provider-mutation-authorized=' =>
        'confirmProviderMutationAuthorized',
    '--confirm-checkout-creation-authorized=' =>
        'confirmCheckoutCreationAuthorized',
    '--confirm-payment-authorized=' => 'confirmPaymentAuthorized',
    '--confirm-webhook-authorized=' => 'confirmWebhookAuthorized',
    '--confirm-browser-navigation-authorized=' =>
        'confirmBrowserNavigationAuthorized',
    '--confirm-store-lite-mutation-authorized=' =>
        'confirmStoreLiteMutationAuthorized',
    '--confirm-session-expiration-authorized=' =>
        'confirmSessionExpirationAuthorized',
    '--confirm-retry-authorized=' => 'confirmRetryAuthorized',
    '--confirm-live-mode-authorized=' => 'confirmLiveModeAuthorized',
    '--confirm-client-deployment-authorized=' =>
        'confirmClientDeploymentAuthorized',
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
        red_addon_checkout_real_post_cli_usage();
        exit(64);
    }
}
if ($options['actorAdmin'] <= 0 || $options['evidenceFile'] === '') {
    red_addon_checkout_real_post_cli_usage();
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
    || filesize($evidenceRealPath) > 131072
) {
    fwrite(
        STDERR,
        "Evidence must be one absolute regular JSON file no larger than 128 KiB.\n"
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
    || !red_addon_checkout_synthetic_exact_keys($evidence, [
        'input', 'preflight', 'preflightOutcome', 'prepared',
        'syntheticPlan',
    ])
    || !is_array($evidence['input'] ?? null)
    || !is_array($evidence['preflight'] ?? null)
    || !is_array($evidence['preflightOutcome'] ?? null)
    || !is_array($evidence['prepared'] ?? null)
    || !is_array($evidence['syntheticPlan'] ?? null)
) {
    fwrite(
        STDERR,
        "Evidence must contain only exact input, preflight, preflightOutcome, prepared, and syntheticPlan objects.\n"
    );
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
$plan = red_addon_checkout_real_mutation_plan(
    $connection,
    $package,
    $catalog,
    $options['actorAdmin'],
    $evidence['syntheticPlan'],
    $evidence['input'],
    $evidence['preflight'],
    $evidence['preflightOutcome'],
    $evidence['prepared'],
    $evaluatedAtUtc,
    'execution'
);
if (empty($plan['ready'])) {
    fwrite(
        STDERR,
        'Real-POST execution refused: '
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
printf("Database SHA-256: %s\n", $plan['databaseSha256']);
printf("Package: %s %s\n", $plan['packageId'], $plan['packageVersion']);
printf(
    "Store package: %s %s\n",
    $plan['storePackageId'],
    $plan['storePackageVersion']
);
printf("Actor administrator: %d\n", $options['actorAdmin']);
echo "Current state: enabled\n";
printf("Expires at UTC: %s\n", $plan['expiresAtUtc']);
printf("Preflight plan SHA-256: %s\n", $plan['preflightPlanSha256']);
printf(
    "Preflight start identity SHA-256: %s\n",
    $plan['preflightStartIdentitySha256']
);
printf(
    "Preflight result identity SHA-256: %s\n",
    $plan['preflightResultIdentitySha256']
);
printf("Input SHA-256: %s\n", $plan['inputSha256']);
printf("Synthetic plan SHA-256: %s\n", $plan['syntheticPlanSha256']);
printf("Contract SHA-256: %s\n", $plan['contractSha256']);
printf("Request SHA-256: %s\n", $plan['requestSha256']);
printf("Order snapshot SHA-256: %s\n", $plan['orderSnapshotSha256']);
printf("Authorization SHA-256: %s\n", $plan['authorizationSha256']);
printf(
    "Authorization state SHA-256: %s\n",
    $plan['authorizationStateSha256']
);
printf("Claim state SHA-256: %s\n", $plan['claimStateSha256']);
printf(
    "Execution start SHA-256: %s\n",
    $plan['executionStartStateSha256']
);
printf(
    "Secret availability SHA-256: %s\n",
    $plan['secretAvailabilitySha256']
);
echo "Operation: checkout.create-sandbox-real-post\n";
echo "Target: stripe-sandbox-real-post\n";
echo "Maximum attempts: 1\n";
echo "Provider contact authorized: yes\n";
echo "Provider mutation authorized: yes\n";
echo "Checkout creation authorized: yes\n";
echo "Payment authorized: no\n";
echo "Webhook authorized: no\n";
echo "Browser navigation authorized: no\n";
echo "Store Lite mutation authorized: no\n";
echo "Session expiration authorized: no\n";
echo "Retry authorized: no\n";
echo "Live mode authorized: no\n";
echo "Client deployment authorized: no\n";

if (!$options['apply']) {
    echo "DRY RUN: D4 authorization, claim, package, request, secret-availability, and start identities were revalidated.\n";
    echo "No secret value was resolved, no registrar or handler ran, no start or result was written, and no network or provider request occurred.\n";
    echo "Retain a verified backup and supply every printed confirmation with --apply before expiry.\n";
    $db->close();
    exit(0);
}

$confirmationsValid =
    $options['confirmDatabase'] === $database
    && hash_equals(
        $plan['databaseSha256'],
        $options['confirmDatabaseSha256']
    )
    && $options['confirmPackage'] === $plan['packageId']
    && $options['confirmVersion'] === '0.1.8'
    && $options['confirmVersion'] === $plan['packageVersion']
    && $options['confirmState'] === 'enabled'
    && $options['confirmStorePackage'] === 'redcms.store-lite'
    && $options['confirmStorePackage'] === $plan['storePackageId']
    && $options['confirmStoreVersion'] === '0.1.35'
    && $options['confirmStoreVersion'] === $plan['storePackageVersion']
    && hash_equals(
        $plan['preflightPlanSha256'],
        $options['confirmPreflightPlanSha256']
    )
    && hash_equals(
        $plan['preflightStartIdentitySha256'],
        $options['confirmPreflightStartIdentitySha256']
    )
    && hash_equals(
        $plan['preflightResultIdentitySha256'],
        $options['confirmPreflightResultIdentitySha256']
    )
    && hash_equals($plan['inputSha256'], $options['confirmInputSha256'])
    && hash_equals(
        $plan['syntheticPlanSha256'],
        $options['confirmSyntheticPlanSha256']
    )
    && hash_equals(
        $plan['contractSha256'],
        $options['confirmContractSha256']
    )
    && hash_equals($plan['requestSha256'], $options['confirmRequestSha256'])
    && hash_equals(
        $plan['orderSnapshotSha256'],
        $options['confirmOrderSnapshotSha256']
    )
    && hash_equals(
        $plan['authorizationSha256'],
        $options['confirmAuthorizationSha256']
    )
    && hash_equals(
        $plan['authorizationStateSha256'],
        $options['confirmAuthorizationStateSha256']
    )
    && hash_equals(
        $plan['claimStateSha256'],
        $options['confirmClaimStateSha256']
    )
    && hash_equals(
        $plan['executionStartStateSha256'],
        $options['confirmExecutionStartSha256']
    )
    && hash_equals(
        $plan['secretAvailabilitySha256'],
        $options['confirmSecretAvailabilitySha256']
    )
    && red_addon_provider_contact_sha256($options['confirmBackupSha256'])
    && !hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    && $options['confirmOperation']
        === 'checkout.create-sandbox-real-post'
    && $options['confirmTarget'] === 'stripe-sandbox-real-post'
    && $options['confirmMaximumAttempts'] === '1'
    && $options['confirmProviderContactAuthorized'] === 'yes'
    && $options['confirmProviderMutationAuthorized'] === 'yes'
    && $options['confirmCheckoutCreationAuthorized'] === 'yes'
    && $options['confirmPaymentAuthorized'] === 'no'
    && $options['confirmWebhookAuthorized'] === 'no'
    && $options['confirmBrowserNavigationAuthorized'] === 'no'
    && $options['confirmStoreLiteMutationAuthorized'] === 'no'
    && $options['confirmSessionExpirationAuthorized'] === 'no'
    && $options['confirmRetryAuthorized'] === 'no'
    && $options['confirmLiveModeAuthorized'] === 'no'
    && $options['confirmClientDeploymentAuthorized'] === 'no';
if (!$confirmationsValid) {
    fwrite(
        STDERR,
        "Apply requires every exact printed identity, effect confirmation, and one nonzero backup SHA-256.\n"
    );
    $db->close();
    exit(64);
}

$result = red_addon_checkout_real_mutation_execute(
    $connection,
    $projectRoot,
    $options['actorAdmin'],
    $evidence['syntheticPlan'],
    $evidence['input'],
    $evidence['preflight'],
    $evidence['preflightOutcome'],
    $evidence['prepared'],
    $options['confirmAuthorizationSha256'],
    $options['confirmAuthorizationStateSha256'],
    $options['confirmClaimStateSha256'],
    $options['confirmExecutionStartSha256'],
    $evaluatedAtUtc
);
$db->close();

$outcome = $result['boundedOutcome'] ?? null;
printf("Outcome: %s\n", $result['status'] ?? 'invalid');
printf(
    "Result state SHA-256: %s\n",
    $result['outcomeStateSha256'] ?? ''
);
echo 'Attempt consumed: '
    . (!empty($result['executionStarted']) ? 'yes' : 'no') . "\n";
echo "Retry authorized: no\n";
echo "Payment authorized: no\n";
echo "Webhook authorized: no\n";
echo "Browser navigation authorized: no\n";
echo "Store Lite mutation authorized: no\n";
echo "Session expiration authorized: no\n";

$created = ($result['status'] ?? '') === 'checkout_session_created'
    && !empty($result['executionStarted'])
    && !empty($result['outcomeRecorded'])
    && !empty($result['outcomeAuditRecorded'])
    && is_array($outcome)
    && is_array($outcome['checkout'] ?? null)
    && ($outcome['checkout']['status'] ?? null) === 'open'
    && ($outcome['checkout']['paymentStatus'] ?? null) === 'unpaid'
    && ($outcome['checkout']['livemode'] ?? null) === false
    && ($outcome['payment'] ?? null) === false
    && ($outcome['webhook'] ?? null) === false
    && ($outcome['browserNavigation'] ?? null) === false
    && ($outcome['storeLiteMutation'] ?? null) === false
    && ($outcome['retryAuthorized'] ?? null) === false;
if ($created) {
    printf(
        "Checkout Session reference: %s\n",
        $outcome['checkout']['checkoutSessionRef']
    );
    echo "Created one bounded open, unpaid, non-live Sandbox Checkout Session.\n";
    echo "The Checkout URL was discarded and is not authorized for opening.\n";
    exit(0);
}

fwrite(
    STDERR,
    "The attempt remains consumed and no retry is authorized. Review only the bounded result and Stripe Sandbox request log.\n"
);
exit(1);

?>
