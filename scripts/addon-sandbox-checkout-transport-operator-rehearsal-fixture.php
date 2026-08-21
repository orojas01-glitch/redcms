<?php
/** Creates disposable C3B2 operator evidence in a caller-owned database. */

if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(STDERR, "Usage: fixture.php STAGED_PROJECT EVIDENCE_JSON CONFIRMATIONS_JSON\n");
    exit(64);
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

define('RED_ADDON_CHECKOUT_TRANSPORT_DOUBLE_FIXTURE_ONLY', true);
require_once __DIR__
    . '/addon-sandbox-checkout-mutation-transport-double-self-test.php';

$stagedProject = realpath($argv[1]);
$evidencePath = $argv[2];
$confirmationsPath = $argv[3];
$actorId = 2147000993;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Checkout_Operator_Rehearsal_Fixture';
$executionMarker = dirname($evidencePath) . '/package-executed';
if (!is_string($stagedProject) || !is_dir($stagedProject)) {
    throw new RuntimeException('Staged project is invalid.');
}

$password = password_hash('CheckoutOperatorRehearsal-2026!', PASSWORD_DEFAULT);
$statement = mysqli_prepare(
    $connection,
    "INSERT INTO RED_Admin (
        RecordID, Username, Password, Administrator, Alias, AdminType,
        AdminComponents, AdminTools, Email, Contact_Form,
        Contact_Form_Pref, Donation_Form, Donation_Form_Pref
     ) VALUES (?, 'codex_checkout_operator_rehearsal', ?, 'Admin',
               'CheckoutOps', 'webmaster', '', '',
               'checkout-ops@example.test', 'N', 'to', 'N', 'to')"
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
    mysqli_stmt_bind_param($statement, 'isi', $actorId, $capability, $actorId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

red_addon_payment_adapter_db_test_write_package(
    $stagedProject,
    $storePackageId,
    'content-package',
    '0.1.35',
    $executionMarker
);
red_addon_payment_adapter_db_test_write_package(
    $stagedProject,
    $adapterPackageId,
    'adapter',
    '0.1.5',
    $executionMarker,
    $tableName
);
$storeManifestPath = $stagedProject . '/addons/redcms/store-lite/addon.json';
$storeManifest = json_decode(
    (string) file_get_contents($storeManifestPath),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$storeManifest['permissions'] = ['store.orders.manage'];
file_put_contents(
    $storeManifestPath,
    json_encode($storeManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        . "\n"
);
$adapterManifestPath = $stagedProject
    . '/addons/redcms/store-lite-stripe-checkout/addon.json';
$adapterManifest = json_decode(
    (string) file_get_contents($adapterManifestPath),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$adapterManifest['dependencies']['required'][0]['version'] = '>=0.1.35 <1.0';
$adapterManifest['routes'][0]['path'] =
    '/addons/redcms/store-lite-stripe-checkout/provider-events';
file_put_contents(
    $adapterManifestPath,
    json_encode($adapterManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        . "\n"
);

$catalog = red_addon_discover($stagedProject, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$storePackage = $catalog['packages'][$storePackageId] ?? [];
$adapterPackage = $catalog['packages'][$adapterPackageId] ?? [];
if (empty($catalog['valid'])
    || !red_addon_payment_adapter_db_test_record_installation(
        $connection,
        $storePackage,
        $actorId,
        'enabled'
    )
    || !red_addon_payment_adapter_db_test_record_installation(
        $connection,
        $adapterPackage,
        $actorId,
        'enabled'
    )
) {
    throw new RuntimeException('Could not stage exact enabled packages.');
}
mysqli_query(
    $connection,
    "CREATE TABLE `$tableName` (
        RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
        PRIMARY KEY (RecordID)
     ) ENGINE=InnoDB"
);

$input = red_addon_checkout_mutation_test_input();
$ownerSubject = red_addon_provider_contact_owner_subject_sha256(
    $connection,
    $actorId
);
$syntheticPlan = red_addon_checkout_synthetic_plan($adapterPackage, $input);
$issued = time() - 30;
$prepared = red_addon_checkout_mutation_prepare(
    $syntheticPlan,
    $ownerSubject,
    hash('sha256', random_bytes(32)),
    gmdate('Y-m-d\TH:i:s\Z', $issued),
    gmdate('Y-m-d\TH:i:s\Z', $issued + 900)
);
$authorizationPlan = red_addon_checkout_mutation_authorization_plan(
    $connection,
    $adapterPackage,
    $catalog,
    $actorId,
    $input,
    $prepared,
    gmdate('Y-m-d\TH:i:s\Z')
);
$authorized = red_addon_checkout_mutation_authorize(
    $connection,
    $stagedProject,
    $actorId,
    $input,
    $prepared,
    $authorizationPlan['authorizationSha256']
);
$claimPlan = red_addon_checkout_mutation_claim_plan(
    $connection,
    $adapterPackage,
    $catalog,
    $actorId,
    $input,
    $prepared,
    gmdate('Y-m-d\TH:i:s\Z')
);
$claimed = red_addon_checkout_mutation_claim(
    $connection,
    $stagedProject,
    $actorId,
    $input,
    $prepared,
    $claimPlan['authorizationSha256'],
    $claimPlan['authorizationStateSha256'],
    $claimPlan['claimStateSha256']
);
$plan = red_addon_checkout_mutation_transport_plan(
    $connection,
    $adapterPackage,
    $catalog,
    $actorId,
    $input,
    $prepared,
    gmdate('Y-m-d\TH:i:s\Z')
);
if (($authorized['status'] ?? '') !== 'authorized'
    || ($claimed['status'] ?? '') !== 'claimed'
    || empty($plan['ready'])
    || file_exists($executionMarker)
) {
    throw new RuntimeException('Could not prepare exact operator evidence.');
}

file_put_contents(
    $evidencePath,
    json_encode(
        ['input' => $input, 'prepared' => $prepared],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n"
);
file_put_contents(
    $confirmationsPath,
    json_encode([
        'actorAdmin' => $actorId,
        'package' => $plan['packageId'],
        'version' => $plan['packageVersion'],
        'state' => $plan['lifecycleState'],
        'planSha256' => $plan['syntheticPlanSha256'],
        'inputSha256' => $plan['inputSha256'],
        'authorizationSha256' => $plan['authorizationSha256'],
        'authorizationStateSha256' => $plan['authorizationStateSha256'],
        'claimStateSha256' => $plan['claimStateSha256'],
        'executionStartSha256' => $plan['executionStartStateSha256'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);
echo "Operator rehearsal evidence prepared.\n";

?>
