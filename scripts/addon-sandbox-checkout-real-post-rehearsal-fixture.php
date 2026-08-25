<?php
/** Prepare disposable D4C2 dry-run evidence from exact staged packages. */

if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(
        STDERR,
        "Usage: fixture.php STAGED_PROJECT EVIDENCE_JSON CONFIRMATIONS_JSON\n"
    );
    exit(64);
}

define('RED_ADDON_CHECKOUT_REAL_MUTATION_LIFECYCLE_FIXTURE_ONLY', true);
require_once __DIR__
    . '/addon-sandbox-checkout-real-mutation-lifecycle-self-test.php';

$stagedProject = realpath($argv[1]);
$evidencePath = $argv[2];
$confirmationsPath = $argv[3];
$actorId = 2147000994;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$apiReference = 'config:p3e9d4c2-stripe-secret-key';
$webhookReference = 'config:p3e9d4c2-stripe-webhook-secret';
if (!is_string($stagedProject)
    || !is_dir($stagedProject)
    || !str_starts_with($evidencePath, DIRECTORY_SEPARATOR)
    || !str_starts_with($confirmationsPath, DIRECTORY_SEPARATOR)
    || file_exists($evidencePath)
    || file_exists($confirmationsPath)
) {
    throw new RuntimeException('D4C2 fixture paths are invalid.');
}

$password = password_hash('CheckoutRealPostRehearsal-2026!', PASSWORD_DEFAULT);
$statement = mysqli_prepare(
    $connection,
    "INSERT INTO RED_Admin (
        RecordID, Username, Password, Administrator, Alias, AdminType,
        AdminComponents, AdminTools, Email, Contact_Form,
        Contact_Form_Pref, Donation_Form, Donation_Form_Pref
     ) VALUES (?, 'codex_checkout_real_post_rehearsal', ?, 'Admin',
               'D4C2Rehearse', 'webmaster', '', '',
               'd4c2-rehearsal@example.test', 'N', 'to', 'N', 'to')"
);
mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
mysqli_stmt_execute($statement);
mysqli_stmt_close($statement);
mysqli_query(
    $connection,
    "INSERT INTO RED_Admin_Roles
     (AdminRecordID, RoleName, AssignedByAdminRecordID)
     VALUES ($actorId, 'owner', $actorId)"
);
foreach (['addons.enable', 'store.orders.manage'] as $capability) {
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

$catalog = red_addon_discover($stagedProject, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$adapterPackage = $catalog['packages'][$adapterPackageId] ?? null;
$storePackage = $catalog['packages'][$storePackageId] ?? null;
if (empty($catalog['valid'])
    || !is_array($adapterPackage)
    || !is_array($storePackage)
    || ($adapterPackage['manifest']['version'] ?? null) !== '0.1.8'
    || ($storePackage['manifest']['version'] ?? null) !== '0.1.35'
) {
    throw new RuntimeException(
        'Exact staged package discovery refused: ' . json_encode([
            'catalogValid' => $catalog['valid'] ?? null,
            'catalogErrors' => $catalog['errors'] ?? null,
            'adapterValid' => is_array($adapterPackage)
                ? ($adapterPackage['valid'] ?? null)
                : null,
            'adapterErrors' => is_array($adapterPackage)
                ? ($adapterPackage['errors'] ?? null)
                : null,
            'adapterVersion' => is_array($adapterPackage)
                ? ($adapterPackage['manifest']['version'] ?? null)
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
$adapterDirectory = realpath((string) ($adapterPackage['path'] ?? ''));
$expectedAdapterDirectory = realpath(
    $stagedProject . '/addons/redcms/store-lite-stripe-checkout'
);
if (!is_string($adapterDirectory)
    || !is_string($expectedAdapterDirectory)
    || $adapterDirectory !== $expectedAdapterDirectory
) {
    throw new RuntimeException('Exact staged adapter path refused.');
}
foreach ([
    'StripeCheckoutResponseNormalizer.php',
    'StripeSandboxCheckoutTransportPlanner.php',
    'StripeSandboxCheckoutTransportResponseGate.php',
    'StripeBoundedJsonDecoder.php',
    'StripeSandboxCheckoutWireCodec.php',
    'StripeSandboxCheckoutCreationContract.php',
] as $contractFile) {
    $contractPath = $adapterDirectory . '/' . $contractFile;
    if (!is_file($contractPath)) {
        throw new RuntimeException('Exact staged contract source refused.');
    }
    require_once $contractPath;
}
$storeRecorded = red_addon_payment_adapter_db_test_record_installation(
    $connection,
    $storePackage,
    $actorId,
    'enabled'
);
$adapterRecorded = $storeRecorded
    && red_addon_payment_adapter_db_test_record_installation(
        $connection,
        $adapterPackage,
        $actorId,
        'enabled'
    );
if (!$storeRecorded || !$adapterRecorded) {
    throw new RuntimeException(
        'Exact staged package registry refused: ' . json_encode([
            'storeRecorded' => $storeRecorded,
            'adapterRecorded' => $adapterRecorded,
            'storeMigrations' => count(
                red_addon_registry_snapshot($storePackage)['migrations'] ?? []
            ),
            'adapterMigrations' => count(
                red_addon_registry_snapshot($adapterPackage)['migrations'] ?? []
            ),
        ], JSON_UNESCAPED_SLASHES)
    );
}
if (!red_checkout_p3e9d4b_lifecycle_settings(
    $connection,
    $adapterPackageId,
    $actorId,
    $apiReference,
    $webhookReference
)) {
    throw new RuntimeException('Could not record opaque D4C2 settings.');
}
$declarations = red_addon_secret_reference_declarations(
    [$apiReference],
    ''
);
$createdAtEpoch = time();
$input = red_checkout_p3e9d4b_evidence_input();
$input['policy']['createdAtEpoch'] = $createdAtEpoch;
$input['policy']['expiresAtEpoch'] = $createdAtEpoch + 3600;
$checkoutContract =
    RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
        $input['checkout'],
        $input['policy'],
        $input['profile']
    );
if (($checkoutContract['valid'] ?? null) !== true
    || !red_addon_checkout_synthetic_sha256(
        $checkoutContract['contractSha256'] ?? null
    )
    || ($checkoutContract['errors'] ?? null) !== []
) {
    throw new RuntimeException('Fresh staged Checkout contract refused.');
}
$input['contractSha256'] = $checkoutContract['contractSha256'];
$checkoutContract = null;
$syntheticPlan = red_checkout_p3e9d4b_evidence_synthetic_plan($input);
$preflight = red_addon_checkout_real_post_preflight($syntheticPlan, $input);
$preflightOutcome = red_checkout_p3e9d4b_evidence_outcome(
    $input,
    $preflight
);
$preflightEvidence = red_addon_checkout_real_mutation_preflight_evidence(
    $adapterPackage,
    $syntheticPlan,
    $input,
    $preflight,
    $preflightOutcome
);
$secretEvidence = red_addon_checkout_real_mutation_secret_evidence(
    $connection,
    $adapterPackage,
    $declarations
);
$ownerSubject = red_addon_provider_contact_owner_subject_sha256(
    $connection,
    $actorId
);
$issued = time() - 30;
$evaluatedAtUtc = gmdate('Y-m-d\TH:i:s\Z');
$prepared = red_addon_checkout_real_mutation_prepare(
    $preflightEvidence,
    $ownerSubject,
    red_addon_checkout_real_mutation_database_sha256($connection),
    $input['checkout']['orderSnapshotSha256'],
    $secretEvidence['evidenceSha256'] ?? '',
    hash('sha256', random_bytes(32)),
    gmdate('Y-m-d\TH:i:s\Z', $issued),
    gmdate('Y-m-d\TH:i:s\Z', $issued + 900)
);
$authorizationPlan = red_addon_checkout_real_mutation_plan(
    $connection,
    $adapterPackage,
    $catalog,
    $actorId,
    $syntheticPlan,
    $input,
    $preflight,
    $preflightOutcome,
    $prepared,
    $evaluatedAtUtc,
    'authorization',
    false,
    $declarations
);
$authorized = red_addon_checkout_real_mutation_record_stage(
    $connection,
    $stagedProject,
    $actorId,
    $syntheticPlan,
    $input,
    $preflight,
    $preflightOutcome,
    $prepared,
    'authorization',
    $authorizationPlan['authorizationSha256'] ?? '',
    $authorizationPlan['authorizationStateSha256'] ?? '',
    '',
    $evaluatedAtUtc,
    null,
    $declarations
);
$claimPlan = red_addon_checkout_real_mutation_plan(
    $connection,
    $adapterPackage,
    $catalog,
    $actorId,
    $syntheticPlan,
    $input,
    $preflight,
    $preflightOutcome,
    $prepared,
    $evaluatedAtUtc,
    'claim',
    false,
    $declarations
);
$claimed = red_addon_checkout_real_mutation_record_stage(
    $connection,
    $stagedProject,
    $actorId,
    $syntheticPlan,
    $input,
    $preflight,
    $preflightOutcome,
    $prepared,
    'claim',
    $claimPlan['authorizationSha256'] ?? '',
    $claimPlan['authorizationStateSha256'] ?? '',
    $claimPlan['claimStateSha256'] ?? '',
    $evaluatedAtUtc,
    null,
    $declarations
);
$executionPlan = red_addon_checkout_real_mutation_plan(
    $connection,
    $adapterPackage,
    $catalog,
    $actorId,
    $syntheticPlan,
    $input,
    $preflight,
    $preflightOutcome,
    $prepared,
    $evaluatedAtUtc,
    'execution',
    false,
    $declarations
);
if (empty($preflightEvidence['valid'])
    || empty($secretEvidence['valid'])
    || empty($prepared['prepared'])
    || ($authorized['status'] ?? null) !== 'authorized'
    || ($claimed['status'] ?? null) !== 'claimed'
    || empty($executionPlan['ready'])
) {
    throw new RuntimeException('Could not prepare exact D4C2 evidence.');
}

$evidence = [
    'input' => $input,
    'preflight' => $preflight,
    'preflightOutcome' => $preflightOutcome,
    'prepared' => $prepared,
    'syntheticPlan' => $syntheticPlan,
];
$confirmations = [
    'actorAdmin' => $actorId,
    'databaseSha256' => $executionPlan['databaseSha256'],
    'package' => $executionPlan['packageId'],
    'version' => $executionPlan['packageVersion'],
    'storePackage' => $executionPlan['storePackageId'],
    'storeVersion' => $executionPlan['storePackageVersion'],
    'preflightPlanSha256' => $executionPlan['preflightPlanSha256'],
    'preflightStartIdentitySha256' =>
        $executionPlan['preflightStartIdentitySha256'],
    'preflightResultIdentitySha256' =>
        $executionPlan['preflightResultIdentitySha256'],
    'inputSha256' => $executionPlan['inputSha256'],
    'syntheticPlanSha256' => $executionPlan['syntheticPlanSha256'],
    'contractSha256' => $executionPlan['contractSha256'],
    'requestSha256' => $executionPlan['requestSha256'],
    'orderSnapshotSha256' => $executionPlan['orderSnapshotSha256'],
    'authorizationSha256' => $executionPlan['authorizationSha256'],
    'authorizationStateSha256' =>
        $executionPlan['authorizationStateSha256'],
    'claimStateSha256' => $executionPlan['claimStateSha256'],
    'executionStartSha256' => $executionPlan['executionStartStateSha256'],
    'secretAvailabilitySha256' =>
        $executionPlan['secretAvailabilitySha256'],
];
foreach ([$evidencePath => $evidence, $confirmationsPath => $confirmations]
    as $outputPath => $value
) {
    $encoded = json_encode(
        $value,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
    ) . "\n";
    if (file_put_contents($outputPath, $encoded, LOCK_EX) !== strlen($encoded)) {
        throw new RuntimeException('Could not write exact D4C2 evidence.');
    }
    chmod($outputPath, 0600);
}

echo "P3E-9D4C2 non-secret dry-run evidence prepared.\n";

?>
