<?php
/** Build exact non-secret P3E-9D3B evidence for a staged project. */

if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(
        STDERR,
        "Usage: fixture.php STAGED_PROJECT EVIDENCE_JSON CONFIRMATIONS_JSON\n"
    );
    exit(64);
}

$stagedProject = realpath($argv[1]);
$evidencePath = $argv[2];
$confirmationsPath = $argv[3];
if (!is_string($stagedProject)
    || !is_dir($stagedProject)
    || !str_starts_with($evidencePath, DIRECTORY_SEPARATOR)
    || !str_starts_with($confirmationsPath, DIRECTORY_SEPARATOR)
    || file_exists($evidencePath)
    || file_exists($confirmationsPath)
) {
    throw new RuntimeException('D3B fixture paths are invalid.');
}

require_once $stagedProject
    . '/includes/addon_sandbox_checkout_real_operation_helpers.php';
$packageDirectory = $stagedProject
    . '/addons/redcms/store-lite-stripe-checkout';
foreach ([
    'StripeCheckoutResponseNormalizer.php',
    'StripeSandboxCheckoutTransportPlanner.php',
    'StripeSandboxCheckoutTransportResponseGate.php',
    'StripeSandboxCheckoutWireCodec.php',
    'StripeSandboxCheckoutCreationContract.php',
] as $dependency) {
    require_once $packageDirectory . '/' . $dependency;
}

$catalog = red_addon_discover($stagedProject, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$packageId = 'redcms.store-lite-stripe-checkout';
$package = $catalog['packages'][$packageId] ?? null;
$storePackage = $catalog['packages']['redcms.store-lite'] ?? null;
if (empty($catalog['valid'])
    || !is_array($package)
    || ($package['manifest']['version'] ?? null) !== '0.1.7'
) {
    throw new RuntimeException(
        'Exact merged adapter is unavailable: ' . json_encode([
            'catalogValid' => $catalog['valid'] ?? null,
            'catalogErrors' => $catalog['errors'] ?? null,
            'packageKeys' => is_array($package) ? array_keys($package) : null,
            'packageValid' => is_array($package)
                ? ($package['valid'] ?? null)
                : null,
            'packageErrors' => is_array($package)
                ? ($package['errors'] ?? null)
                : null,
            'packageVersion' => is_array($package)
                ? ($package['manifest']['version'] ?? null)
                : null,
            'storeValid' => is_array($storePackage)
                ? ($storePackage['valid'] ?? null)
                : null,
            'storeErrors' => is_array($storePackage)
                ? ($storePackage['errors'] ?? null)
                : null,
            'storeVersion' => is_array($storePackage)
                ? ($storePackage['manifest']['version'] ?? null)
                : null,
        ], JSON_UNESCAPED_SLASHES)
    );
}

$checkout = [
    'orderId' => 'ord_0123456789abcdef0123456789abcdef',
    'orderSnapshotSha256' => str_repeat('a', 64),
    'paymentMethod' => 'stripe_checkout',
    'amountMinor' => 5897,
    'currency' => 'USD',
    'idempotencySha256' => str_repeat('b', 64),
    'lineItems' => [[
        'name' => 'Dog scarf - Small / Red',
        'quantity' => 2,
        'unitAmountMinor' => 1999,
        'lineTotalMinor' => 3998,
    ], [
        'name' => 'Delivery fee',
        'quantity' => 1,
        'unitAmountMinor' => 1899,
        'lineTotalMinor' => 1899,
    ]],
];
$policy = [
    'apiVersion' => '2024-09-30.acacia',
    'successUrl' => 'https://shop.example.test/checkout/stripe-complete',
    'cancelUrl' => 'https://shop.example.test/checkout',
    'createdAtEpoch' => 1787025600,
    'expiresAtEpoch' => 1787027400,
];
$profile = [
    'packageId' => $packageId,
    'contractVersion' => 'p3e9a-v1',
    'operation' => 'checkout.create-sandbox',
    'contactTarget' => 'stripe-sandbox',
    'credentialMode' => 'restricted_test_write',
    'providerContact' => true,
    'providerMutation' => true,
    'checkoutCreation' => true,
    'payment' => false,
    'webhook' => false,
    'browserNavigation' => false,
    'orderMutation' => false,
    'clientDeployment' => false,
    'oneAttempt' => true,
    'automaticRetry' => false,
];
$contract =
    RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
        $checkout,
        $policy,
        $profile
    );
if (($contract['valid'] ?? null) !== true
    || !red_addon_checkout_synthetic_sha256(
        $contract['contractSha256'] ?? null
    )
) {
    throw new RuntimeException('Exact P3E-9A contract could not be prepared.');
}
$input = [
    'contactTarget' => 'synthetic-checkout-package',
    'checkout' => $checkout,
    'policy' => $policy,
    'profile' => $profile,
    'contractSha256' => $contract['contractSha256'],
];
$syntheticPlan = [
    'valid' => true,
    'ready' => true,
    'status' => 'ready',
    'packageId' => $packageId,
    'packageVersion' => '0.1.5',
    'adapterId' => $packageId . '/checkout',
    'operation' => 'checkout.create-sandbox-synthetic',
    'manifestSha256' => str_repeat('d', 64),
    'inventorySha256' => str_repeat('e', 64),
    'inputSha256' => red_addon_checkout_synthetic_hash($input),
    'planSha256' => str_repeat('f', 64),
    'adapterInvoked' => false,
    'boundedOutcome' => null,
    'outcomeSha256' => '',
    'executionPerformed' => false,
    'networkAccess' => false,
    'providerContact' => false,
    'providerMutation' => false,
    'checkoutCreation' => false,
    'payment' => false,
    'webhook' => false,
    'browserNavigation' => false,
    'orderMutation' => false,
    'retryAuthorized' => false,
    'clientDeployment' => false,
    'errors' => [],
];
$preflight = red_addon_checkout_real_post_preflight(
    $syntheticPlan,
    $input
);
$plan = red_addon_checkout_real_operation_plan(
    $package,
    $syntheticPlan,
    $input,
    $preflight
);
if (empty($preflight['ready'])
    || empty($plan['ready'])
    || !empty($plan['adapterInvoked'])
    || !empty($plan['executionStarted'])
    || !empty($plan['networkAccess'])
    || !empty($plan['providerContact'])
) {
    throw new RuntimeException('Exact no-contact D3B evidence was refused.');
}

$evidence = [
    'input' => $input,
    'preflight' => $preflight,
    'syntheticPlan' => $syntheticPlan,
];
$confirmations = [
    'package' => $plan['packageId'],
    'version' => $plan['packageVersion'],
    'sourceVersion' => $plan['sourcePackageVersion'],
    'manifestSha256' => $plan['manifestSha256'],
    'inventorySha256' => $plan['inventorySha256'],
    'planSha256' => $plan['planSha256'],
    'inputSha256' => $plan['inputSha256'],
    'syntheticPlanSha256' => $plan['syntheticPlanSha256'],
    'contractSha256' => $plan['contractSha256'],
    'requestSha256' => $plan['requestSha256'],
    'startIdentitySha256' => $plan['executionStartIdentitySha256'],
    'operation' => $plan['operation'],
    'providerOperation' => $plan['providerOperation'],
];
foreach ([$evidencePath => $evidence, $confirmationsPath => $confirmations]
    as $path => $value
) {
    $encoded = json_encode(
        $value,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
    ) . "\n";
    if (file_put_contents($path, $encoded, LOCK_EX) !== strlen($encoded)) {
        throw new RuntimeException('Could not write exact D3B evidence.');
    }
    chmod($path, 0600);
}

echo "P3E-9D3B non-secret evidence prepared.\n";

?>
