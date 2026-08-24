<?php
/** Creates disposable C4B4D durable evidence and confirmations. */

if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    fwrite(STDERR, "Usage: fixture.php EVIDENCE_JSON CONFIRMATIONS_JSON\n");
    exit(64);
}

define('RED_WOMPI_C4B4B_FIXTURE_ONLY', true);
require_once __DIR__ . '/wompi-payment-adapter-c4b4b-self-test.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_wompi_transport_double_helpers.php';

$evidencePath = $argv[1];
$confirmationsPath = $argv[2];
$now = time();

function red_wompi_c4b4d_fixture_require($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $password = password_hash(
        'WompiC4B4D-Disposable-2026!',
        PASSWORD_DEFAULT
    );
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_wompi_c4b4d', ?, 'Admin', 'WompiC4B4D',
                   'webmaster', '', '', 'wompi-c4b4d@example.test',
                   'N', 'to', 'N', 'to')"
    );
    red_wompi_c4b4d_fixture_require(
        $statement instanceof mysqli_stmt,
        'Could not prepare disposable Owner.'
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_wompi_c4b4d_fixture_require(
        $inserted,
        'Could not record disposable Owner.'
    );
    red_wompi_c4b4d_fixture_require(
        (bool) mysqli_query(
            $connection,
            "INSERT INTO RED_Admin_Roles
             (AdminRecordID, RoleName, AssignedByAdminRecordID)
             VALUES ($actorId, 'owner', $actorId)"
        ),
        'Could not record disposable Owner role.'
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
        red_wompi_c4b4d_fixture_require(
            $statement instanceof mysqli_stmt,
            'Could not prepare disposable capability.'
        );
        mysqli_stmt_bind_param(
            $statement,
            'isi',
            $actorId,
            $capability,
            $actorId
        );
        $granted = mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        red_wompi_c4b4d_fixture_require(
            $granted,
            'Could not record disposable capability.'
        );
    }

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $wompiPackage = $catalog['packages'][$wompiPackageId] ?? [];
    red_wompi_c4b4d_fixture_require(
        !empty($catalog['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.35'
            && ($wompiPackage['manifest']['version'] ?? null) === '0.1.4',
        'Exact packages did not discover.'
    );
    red_wompi_c4b4d_fixture_require(
        red_wompi_c3b_record_enabled_store(
            $connection,
            $storePackage,
            $actorId
        ),
        'Could not record Store Lite dependency.'
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
    red_wompi_c4b4d_fixture_require(
        !empty($installPlan['valid'])
            && ($installed['status'] ?? null) === 'installed_disabled',
        'Could not install Wompi disabled.'
    );
    $references = [
        'wompi.private-key' => 'config:c4b4d-wompi-private',
        'wompi.integrity-key' => 'config:c4b4d-wompi-integrity',
        'wompi.event-secret' => 'config:c4b4d-wompi-event',
    ];
    red_wompi_c4b4d_fixture_require(
        red_wompi_c3c1_store_settings(
            $connection,
            $wompiPackageId,
            $actorId,
            'sandbox-public-reference-c4b4d',
            $references
        ),
        'Could not record value-free setting references.'
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
    red_wompi_c4b4d_fixture_require(
        !empty($enablePlan['valid'])
            && ($enabled['status'] ?? null) === 'enabled',
        'Could not enable Wompi with value-free declarations.'
    );

    require_once $fixtureProject
        . '/addons/redcms/store-lite-wompi/addon.php';
    [$authorization, $claim] = red_wompi_c4b4b_evidence(
        $connection,
        $actorId,
        $now,
        '8',
        '9'
    );
    $claimPlan = red_addon_wompi_claim_plan(
        $connection,
        $wompiPackage,
        $catalog,
        $actorId,
        $authorization,
        $claim,
        $now
    );
    $claimed = red_addon_wompi_claim_record(
        $connection,
        $fixtureProject,
        $actorId,
        $authorization,
        $claim,
        $claimPlan['authorizationStateSha256'] ?? '',
        $claimPlan['claimStateSha256'] ?? '',
        $now
    );
    $plan = red_addon_wompi_transport_plan(
        $connection,
        $wompiPackage,
        $catalog,
        $actorId,
        $authorization,
        $claim,
        $now
    );
    red_wompi_c4b4d_fixture_require(
        !empty($claimPlan['ready'])
            && ($claimed['status'] ?? null) === 'claimed'
            && !empty($plan['ready']),
        'Could not prepare exact durable operator evidence.'
    );

    $evidence = [
        'authorization' => $authorization,
        'claim' => $claim,
    ];
    $confirmations = [
        'actorAdmin' => $actorId,
        'database' => red_addon_install_database_name($connection),
        'package' => $plan['packageId'],
        'version' => $plan['packageVersion'],
        'state' => $plan['lifecycleState'],
        'clientScopeSha256' => $plan['clientScopeSha256'],
        'databaseSha256' => $plan['databaseSha256'],
        'actorSubjectSha256' => $plan['actorSubjectSha256'],
        'orderSha256' => hash('sha256', $plan['orderId']),
        'planSha256' => $plan['planSha256'],
        'wireRequestSha256' => $plan['wireRequestSha256'],
        'authorizationSha256' => $plan['authorizationSha256'],
        'authorizationStateSha256' =>
            $plan['authorizationStateSha256'],
        'claimSha256' => $plan['claimSha256'],
        'claimStateSha256' => $plan['claimStateSha256'],
        'requestSha256' => $plan['requestSha256'],
        'executionStartSha256' => $plan['executionStartStateSha256'],
    ];
    $jsonFlags = JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR;
    red_wompi_c4b4d_fixture_require(
        file_put_contents(
            $evidencePath,
            json_encode($evidence, $jsonFlags) . "\n"
        ) !== false
            && file_put_contents(
                $confirmationsPath,
                json_encode($confirmations, $jsonFlags) . "\n"
            ) !== false,
        'Could not write bounded rehearsal evidence.'
    );
    $db->close();
    echo "Wompi C4B4D disposable evidence fixture prepared.\n";
} catch (Throwable $throwable) {
    $db->close();
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
