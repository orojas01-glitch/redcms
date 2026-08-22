<?php
/** CLI-only P3E-9D3A real-operation preflight command. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_sandbox_checkout_real_operation_helpers.php';

function red_addon_checkout_real_operation_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-sandbox-checkout-real-operation-preflight.php " .
        "--evidence-file=/absolute/path.json\n" .
        "  php scripts/admin-sandbox-checkout-real-operation-preflight.php " .
        "--evidence-file=/absolute/path.json \\\n" .
        "    --confirm-package=redcms.store-lite-stripe-checkout \\\n" .
        "    --confirm-version=0.1.7 --confirm-source-version=0.1.5 \\\n" .
        "    --confirm-manifest-sha256=SHA256 \\\n" .
        "    --confirm-inventory-sha256=SHA256 \\\n" .
        "    --confirm-plan-sha256=SHA256 --confirm-input-sha256=SHA256 \\\n" .
        "    --confirm-synthetic-plan-sha256=SHA256 \\\n" .
        "    --confirm-contract-sha256=SHA256 --confirm-request-sha256=SHA256 \\\n" .
        "    --confirm-start-identity-sha256=SHA256 \\\n" .
        "    --confirm-operation=checkout.create-sandbox-real-post-preflight \\\n" .
        "    --confirm-provider-operation=checkout.create-sandbox-real-post \\\n" .
        "    --confirm-maximum-attempts=1 \\\n" .
        "    --confirm-credential-access-provided=no \\\n" .
        "    --confirm-execution-ready=no --confirm-execution-started=no \\\n" .
        "    --confirm-result-recorded=no --confirm-network-authorized=no \\\n" .
        "    --confirm-provider-contact-authorized=no \\\n" .
        "    --confirm-provider-mutation-authorized=no \\\n" .
        "    --confirm-checkout-creation-authorized=no \\\n" .
        "    --confirm-retry-authorized=no --apply\n"
    );
}

$options = [
    'evidenceFile' => '',
    'confirmPackage' => '',
    'confirmVersion' => '',
    'confirmSourceVersion' => '',
    'confirmManifestSha256' => '',
    'confirmInventorySha256' => '',
    'confirmPlanSha256' => '',
    'confirmInputSha256' => '',
    'confirmSyntheticPlanSha256' => '',
    'confirmContractSha256' => '',
    'confirmRequestSha256' => '',
    'confirmStartIdentitySha256' => '',
    'confirmOperation' => '',
    'confirmProviderOperation' => '',
    'confirmMaximumAttempts' => '',
    'confirmCredentialAccessProvided' => '',
    'confirmExecutionReady' => '',
    'confirmExecutionStarted' => '',
    'confirmResultRecorded' => '',
    'confirmNetworkAuthorized' => '',
    'confirmProviderContactAuthorized' => '',
    'confirmProviderMutationAuthorized' => '',
    'confirmCheckoutCreationAuthorized' => '',
    'confirmRetryAuthorized' => '',
    'apply' => false,
];
$argumentMap = [
    '--evidence-file=' => 'evidenceFile',
    '--confirm-package=' => 'confirmPackage',
    '--confirm-version=' => 'confirmVersion',
    '--confirm-source-version=' => 'confirmSourceVersion',
    '--confirm-manifest-sha256=' => 'confirmManifestSha256',
    '--confirm-inventory-sha256=' => 'confirmInventorySha256',
    '--confirm-plan-sha256=' => 'confirmPlanSha256',
    '--confirm-input-sha256=' => 'confirmInputSha256',
    '--confirm-synthetic-plan-sha256=' =>
        'confirmSyntheticPlanSha256',
    '--confirm-contract-sha256=' => 'confirmContractSha256',
    '--confirm-request-sha256=' => 'confirmRequestSha256',
    '--confirm-start-identity-sha256=' =>
        'confirmStartIdentitySha256',
    '--confirm-operation=' => 'confirmOperation',
    '--confirm-provider-operation=' => 'confirmProviderOperation',
    '--confirm-maximum-attempts=' => 'confirmMaximumAttempts',
    '--confirm-credential-access-provided=' =>
        'confirmCredentialAccessProvided',
    '--confirm-execution-ready=' => 'confirmExecutionReady',
    '--confirm-execution-started=' => 'confirmExecutionStarted',
    '--confirm-result-recorded=' => 'confirmResultRecorded',
    '--confirm-network-authorized=' => 'confirmNetworkAuthorized',
    '--confirm-provider-contact-authorized=' =>
        'confirmProviderContactAuthorized',
    '--confirm-provider-mutation-authorized=' =>
        'confirmProviderMutationAuthorized',
    '--confirm-checkout-creation-authorized=' =>
        'confirmCheckoutCreationAuthorized',
    '--confirm-retry-authorized=' => 'confirmRetryAuthorized',
];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $options['apply'] = true;
        continue;
    }
    $matched = false;
    foreach ($argumentMap as $prefix => $key) {
        if (str_starts_with($argument, $prefix)) {
            $options[$key] = substr($argument, strlen($prefix));
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        red_addon_checkout_real_operation_cli_usage();
        exit(64);
    }
}
if ($options['evidenceFile'] === '') {
    red_addon_checkout_real_operation_cli_usage();
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
    || !red_addon_checkout_synthetic_exact_keys($evidence, [
        'input', 'preflight', 'syntheticPlan',
    ])
    || !is_array($evidence['input'] ?? null)
    || !is_array($evidence['preflight'] ?? null)
    || !is_array($evidence['syntheticPlan'] ?? null)
) {
    fwrite(
        STDERR,
        "Evidence must contain only exact input, preflight, and syntheticPlan objects.\n"
    );
    exit(64);
}

$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$packageId = 'redcms.store-lite-stripe-checkout';
$package = $catalog['packages'][$packageId] ?? null;
if (empty($catalog['valid']) || !is_array($package)) {
    fwrite(STDERR, "Exact adapter discovery or trust validation failed.\n");
    exit(65);
}
$plan = red_addon_checkout_real_operation_plan(
    $package,
    $evidence['syntheticPlan'],
    $evidence['input'],
    $evidence['preflight']
);
if (empty($plan['ready'])) {
    fwrite(
        STDERR,
        'Real-operation preflight refused: '
            . ($plan['status'] ?? 'invalid')
            . (($plan['errors'] ?? []) === []
                ? ''
                : ' (' . implode(', ', $plan['errors']) . ')')
            . PHP_EOL
    );
    exit(65);
}

printf("Package: %s %s\n", $plan['packageId'], $plan['packageVersion']);
printf("Source package version: %s\n", $plan['sourcePackageVersion']);
printf("Manifest SHA-256: %s\n", $plan['manifestSha256']);
printf("Inventory SHA-256: %s\n", $plan['inventorySha256']);
printf("Plan SHA-256: %s\n", $plan['planSha256']);
printf("Input SHA-256: %s\n", $plan['inputSha256']);
printf("Synthetic plan SHA-256: %s\n", $plan['syntheticPlanSha256']);
printf("Contract SHA-256: %s\n", $plan['contractSha256']);
printf("Request SHA-256: %s\n", $plan['requestSha256']);
printf(
    "Start identity SHA-256: %s\n",
    $plan['executionStartIdentitySha256']
);
printf("Operation: %s\n", $plan['operation']);
printf("Provider operation: %s\n", $plan['providerOperation']);
echo "Maximum attempts: 1\n";
echo "Credential access provided: no\n";
echo "Execution ready: no\n";
echo "Execution started: no\n";
echo "Result recorded: no\n";
echo "Network authorized: no\n";
echo "Provider contact authorized: no\n";
echo "Provider mutation authorized: no\n";
echo "Checkout creation authorized: no\n";
echo "Retry authorized: no\n";

if (!$options['apply']) {
    echo "DRY RUN: package, D0 request, and D2 identity evidence were revalidated.\n";
    echo "No registrar or adapter handler ran, no credential was available, and no execution or provider attempt started.\n";
    echo "Supply every printed confirmation with --apply for the one contained preflight invocation.\n";
    exit(0);
}

$confirmationsValid =
    $options['confirmPackage'] === $plan['packageId']
    && $options['confirmVersion'] === '0.1.7'
    && $options['confirmVersion'] === $plan['packageVersion']
    && $options['confirmSourceVersion'] === '0.1.5'
    && $options['confirmSourceVersion'] === $plan['sourcePackageVersion']
    && hash_equals(
        $plan['manifestSha256'],
        $options['confirmManifestSha256']
    )
    && hash_equals(
        $plan['inventorySha256'],
        $options['confirmInventorySha256']
    )
    && hash_equals($plan['planSha256'], $options['confirmPlanSha256'])
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
        $plan['executionStartIdentitySha256'],
        $options['confirmStartIdentitySha256']
    )
    && $options['confirmOperation']
        === 'checkout.create-sandbox-real-post-preflight'
    && $options['confirmOperation'] === $plan['operation']
    && $options['confirmProviderOperation']
        === 'checkout.create-sandbox-real-post'
    && $options['confirmProviderOperation'] === $plan['providerOperation']
    && $options['confirmMaximumAttempts'] === '1'
    && $options['confirmCredentialAccessProvided'] === 'no'
    && $options['confirmExecutionReady'] === 'no'
    && $options['confirmExecutionStarted'] === 'no'
    && $options['confirmResultRecorded'] === 'no'
    && $options['confirmNetworkAuthorized'] === 'no'
    && $options['confirmProviderContactAuthorized'] === 'no'
    && $options['confirmProviderMutationAuthorized'] === 'no'
    && $options['confirmCheckoutCreationAuthorized'] === 'no'
    && $options['confirmRetryAuthorized'] === 'no';
if (!$confirmationsValid) {
    fwrite(
        STDERR,
        "Apply requires every exact printed identity and no-effect confirmation.\n"
    );
    exit(64);
}

$result = red_addon_checkout_real_operation_execute(
    $package,
    $evidence['syntheticPlan'],
    $evidence['input'],
    $evidence['preflight'],
    $options['confirmPlanSha256'],
    $options['confirmStartIdentitySha256']
);
$outcome = $result['boundedOutcome'] ?? null;
printf("Outcome: %s\n", $result['status'] ?? 'invalid');
printf(
    "Result identity SHA-256: %s\n",
    $result['resultIdentitySha256'] ?? ''
);
echo "Adapter preflight invoked: "
    . (!empty($result['adapterInvoked']) ? 'yes' : 'no') . "\n";
echo "Credential access provided: no\n";
echo "Execution started: no\n";
echo "Result recorded: no\n";
echo "Network authorized: no\n";
echo "Provider contact authorized: no\n";
echo "Provider mutation authorized: no\n";
echo "Checkout creation authorized: no\n";
echo "Retry authorized: no\n";

$expected = ($result['status'] ?? '') === 'request_contract_adopted'
    && !empty($result['adapterInvoked'])
    && !empty($result['startIdentityPrepared'])
    && !empty($result['resultIdentityPrepared'])
    && red_addon_checkout_synthetic_sha256(
        $result['resultIdentitySha256'] ?? null
    )
    && empty($result['credentialAccessProvided'])
    && empty($result['executionReady'])
    && empty($result['executionStarted'])
    && empty($result['resultRecorded'])
    && empty($result['executionPerformed'])
    && empty($result['networkAccess'])
    && empty($result['providerContact'])
    && empty($result['providerMutation'])
    && empty($result['checkoutCreation'])
    && empty($result['payment'])
    && empty($result['webhook'])
    && empty($result['storeLiteMutation'])
    && empty($result['retryAuthorized'])
    && is_array($outcome)
    && ($outcome['operation'] ?? null)
        === 'checkout.create-sandbox-real-post-preflight'
    && ($outcome['providerOperation'] ?? null)
        === 'checkout.create-sandbox-real-post';
if (!$expected) {
    fwrite(
        STDERR,
        "Contained preflight did not produce the exact bounded identity result. No execution or provider attempt started.\n"
    );
    exit(1);
}
echo "Observed the exact bounded adapter preflight and non-persistent result identity.\n";
echo "No credential, database, network request, provider mutation, Checkout Session, payment, webhook, Store Lite mutation, or client action occurred.\n";
exit(0);

?>
