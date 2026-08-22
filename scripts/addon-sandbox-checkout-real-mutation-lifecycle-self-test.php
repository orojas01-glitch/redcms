<?php
/** Disposable-database P3E-9D4B fresh authorization/claim acceptance. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_CHECKOUT_MUTATION_AUTHORIZATION_FIXTURE_ONLY', true);
require_once __DIR__
    . '/addon-sandbox-checkout-mutation-authorization-self-test.php';
define('RED_ADDON_CHECKOUT_REAL_MUTATION_EVIDENCE_FIXTURE_ONLY', true);
require_once __DIR__
    . '/addon-sandbox-checkout-real-mutation-evidence-self-test.php';

$assertions = 0;
$actorId = 2147000992;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Checkout_Real_Mutation_Fixture';
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-checkout-real-mutation-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$packageIds = [$adapterPackageId, $storePackageId];

function red_checkout_p3e9d4b_lifecycle_settings(
    $connection,
    $packageId,
    $actorId,
    $apiReference,
    $webhookReference
) {
    $rows = [
        [
            'checkout.return-origin',
            'url',
            json_encode('https://checkout.example.test'),
            null,
        ],
        ['stripe.secret-key', 'secret-reference', null, $apiReference],
        [
            'stripe.webhook-secret',
            'secret-reference',
            null,
            $webhookReference,
        ],
    ];
    foreach ($rows as [$key, $type, $valueJson, $secretReference]) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Settings (
                PackageID, SettingKey, ValueType, ValueJSON,
                SecretReference, UpdatedByAdminRecordID
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $statement,
            'sssssi',
            $packageId,
            $key,
            $type,
            $valueJson,
            $secretReference,
            $actorId
        );
        if (!mysqli_stmt_execute($statement)) {
            mysqli_stmt_close($statement);
            return false;
        }
        mysqli_stmt_close($statement);
    }
    return true;
}

function red_checkout_p3e9d4b_lifecycle_cleanup(
    $connection,
    array $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
) {
    foreach ($packageIds as $packageId) {
        $escaped = mysqli_real_escape_string($connection, $packageId);
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Settings WHERE PackageID='$escaped'"
        );
    }
    red_addon_checkout_mutation_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
}

function red_checkout_p3e9d4b_lifecycle_context(
    $connection,
    $fixtureProject,
    $actorId,
    $nonceCharacter,
    array $declarations
) {
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][
        'redcms.store-lite-stripe-checkout'
    ] ?? [];
    $input = red_checkout_p3e9d4b_evidence_input();
    $syntheticPlan = red_checkout_p3e9d4b_evidence_synthetic_plan($input);
    $preflight = red_addon_checkout_real_post_preflight(
        $syntheticPlan,
        $input
    );
    $outcome = red_checkout_p3e9d4b_evidence_outcome($input, $preflight);
    $evidence = red_addon_checkout_real_mutation_preflight_evidence(
        $package,
        $syntheticPlan,
        $input,
        $preflight,
        $outcome
    );
    $secretEvidence = red_addon_checkout_real_mutation_secret_evidence(
        $connection,
        $package,
        $declarations
    );
    $ownerSubject = red_addon_provider_contact_owner_subject_sha256(
        $connection,
        $actorId
    );
    $prepared = red_addon_checkout_real_mutation_prepare(
        $evidence,
        $ownerSubject,
        red_addon_checkout_real_mutation_database_sha256($connection),
        $input['checkout']['orderSnapshotSha256'],
        $secretEvidence['evidenceSha256'] ?? '',
        str_repeat($nonceCharacter, 64),
        '2026-08-22T12:00:00Z',
        '2026-08-22T12:15:00Z'
    );
    return compact(
        'catalog',
        'package',
        'input',
        'syntheticPlan',
        'preflight',
        'outcome',
        'evidence',
        'secretEvidence',
        'ownerSubject',
        'prepared'
    );
}

function red_checkout_p3e9d4b_lifecycle_plan(
    $connection,
    $actorId,
    array $context,
    $stage,
    array $declarations
) {
    return red_addon_checkout_real_mutation_plan(
        $connection,
        $context['package'],
        $context['catalog'],
        $actorId,
        $context['syntheticPlan'],
        $context['input'],
        $context['preflight'],
        $context['outcome'],
        $context['prepared'],
        '2026-08-22T12:05:00Z',
        $stage,
        false,
        $declarations
    );
}

function red_checkout_p3e9d4b_lifecycle_record(
    $connection,
    $fixtureProject,
    $actorId,
    array $context,
    array $plan,
    $stage,
    array $declarations,
    $auditRecorder = null
) {
    return red_addon_checkout_real_mutation_record_stage(
        $connection,
        $fixtureProject,
        $actorId,
        $context['syntheticPlan'],
        $context['input'],
        $context['preflight'],
        $context['outcome'],
        $context['prepared'],
        $stage,
        $plan['authorizationSha256'] ?? '',
        $plan['authorizationStateSha256'] ?? '',
        $stage === 'claim' ? ($plan['claimStateSha256'] ?? '') : '',
        '2026-08-22T12:05:00Z',
        $auditRecorder,
        $declarations
    );
}

if (defined('RED_ADDON_CHECKOUT_REAL_MUTATION_LIFECYCLE_FIXTURE_ONLY')
    && RED_ADDON_CHECKOUT_REAL_MUTATION_LIFECYCLE_FIXTURE_ONLY
) {
    return;
}

red_checkout_p3e9d4b_lifecycle_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash('CheckoutRealMutation-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_checkout_real_mutation', ?, 'Admin',
                   'D4BLifecycle', 'webmaster', '', '',
                   'checkout-real@example.test', 'N', 'to', 'N', 'to')"
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

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $storePackageId,
        'content-package',
        '0.1.35',
        $executionMarker
    );
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $adapterPackageId,
        'adapter',
        '0.1.8',
        $executionMarker,
        $tableName
    );
    $storeManifestPath = $fixtureProject
        . '/addons/redcms/store-lite/addon.json';
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
    $adapterManifestPath = $fixtureProject
        . '/addons/redcms/store-lite-stripe-checkout/addon.json';
    $adapterManifest = json_decode(
        (string) file_get_contents($adapterManifestPath),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $adapterManifest['dependencies']['required'][0]['version'] =
        '>=0.1.35 <1.0';
    $adapterManifest['routes'][0]['path'] =
        '/addons/redcms/store-lite-stripe-checkout/provider-events';
    file_put_contents(
        $adapterManifestPath,
        json_encode(
            $adapterManifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n"
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? [];
    red_addon_checkout_mutation_test_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($adapterPackage['valid'])
            && !file_exists($executionMarker),
        'exact packages discover without executing package PHP'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_payment_adapter_db_test_record_installation(
            $connection,
            $storePackage,
            $actorId,
            'enabled'
        ) && red_addon_payment_adapter_db_test_record_installation(
            $connection,
            $adapterPackage,
            $actorId,
            'enabled'
        ),
        'adapter 0.1.8 and Store Lite 0.1.35 are current in one database'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    $apiReference = 'config:p3e9d4b-stripe-secret-key';
    $webhookReference = 'config:p3e9d4b-stripe-webhook-secret';
    red_addon_checkout_mutation_test_assert(
        red_checkout_p3e9d4b_lifecycle_settings(
            $connection,
            $adapterPackageId,
            $actorId,
            $apiReference,
            $webhookReference
        ),
        'only opaque package setting references are stored'
    );
    $declarations = red_addon_secret_reference_declarations(
        [$apiReference],
        ''
    );
    $context = red_checkout_p3e9d4b_lifecycle_context(
        $connection,
        $fixtureProject,
        $actorId,
        'a',
        $declarations
    );
    $contextChecks = [
        'evidence' => !empty($context['evidence']['valid']),
        'secret' => !empty($context['secretEvidence']['valid'])
            && !empty($context['secretEvidence']['available']),
        'prepared' => !empty($context['prepared']['prepared']),
        'owner' => red_addon_provider_contact_sha256(
            $context['ownerSubject'] ?? ''
        ),
        'database' => red_addon_provider_contact_sha256(
            red_addon_checkout_real_mutation_database_sha256($connection)
        ),
        'order' => red_addon_provider_contact_sha256(
            $context['input']['checkout']['orderSnapshotSha256'] ?? ''
        ),
        'tables' => red_admin_transaction_tables_supported($connection, [
            'RED_Addon_Admin_Action_Executions',
            'RED_Addon_Activity_Log',
        ]),
    ];
    red_addon_checkout_mutation_test_assert(
        !in_array(false, $contextChecks, true),
        'D4 lifecycle context is complete: ' . json_encode($contextChecks)
    );
    $authorizationPlan = red_checkout_p3e9d4b_lifecycle_plan(
        $connection,
        $actorId,
        $context,
        'authorization',
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        !empty($authorizationPlan['ready'])
            && ($authorizationPlan['packageVersion'] ?? '') === '0.1.8'
            && ($authorizationPlan['stage'] ?? '') === 'authorization'
            && empty($authorizationPlan['authorizationRecorded'])
            && empty($authorizationPlan['claimRecorded']),
        'fresh D4 authorization plan is zero-write and version-bound: '
            . ($authorizationPlan['status'] ?? '') . ' '
            . implode(',', $authorizationPlan['errors'] ?? [])
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'))"
        ) === '0:0',
        'planning writes no authorization, claim, start, result, or audit'
    );
    $authorized = red_checkout_p3e9d4b_lifecycle_record(
        $connection,
        $fixtureProject,
        $actorId,
        $context,
        $authorizationPlan,
        'authorization',
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($authorized['status'] ?? '') === 'authorized'
            && !empty($authorized['authorizationRecorded'])
            && empty($authorized['claimRecorded']),
        'one fresh D4 authorization and value-free audit commit atomically'
    );
    $claimPlan = red_checkout_p3e9d4b_lifecycle_plan(
        $connection,
        $actorId,
        $context,
        'claim',
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        !empty($claimPlan['ready'])
            && !empty($claimPlan['authorizationRecorded'])
            && empty($claimPlan['claimRecorded']),
        'claim requires the exact fresh D4 authorization row'
    );
    $claimed = red_checkout_p3e9d4b_lifecycle_record(
        $connection,
        $fixtureProject,
        $actorId,
        $context,
        $claimPlan,
        'claim',
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($claimed['status'] ?? '') === 'claimed'
            && !empty($claimed['authorizationRecorded'])
            && !empty($claimed['claimRecorded']),
        'one fresh D4 claim and value-free audit commit atomically'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'))"
        ) === '2:2',
        'exactly authorization, claim, and their two audits exist'
    );
    $executionPlan = red_checkout_p3e9d4b_lifecycle_plan(
        $connection,
        $actorId,
        $context,
        'execution',
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        !empty($executionPlan['ready'])
            && !empty($executionPlan['executionStartAvailable'])
            && red_addon_provider_contact_sha256(
                $executionPlan['executionStartStateSha256'] ?? ''
            ),
        'exact fresh authorization and claim expose one durable start identity'
    );
    $replay = red_checkout_p3e9d4b_lifecycle_plan(
        $connection,
        $actorId,
        $context,
        'claim',
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($replay['status'] ?? '') === 'attempt_already_claimed',
        'claim replay is refused'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId
           AND Capability='store.orders.manage'"
    );
    red_addon_checkout_mutation_test_assert(
        ($refused = red_checkout_p3e9d4b_lifecycle_plan(
            $connection,
            $actorId,
            $context,
            'execution',
            $declarations
        )) && ($refused['errors'][0] ?? '') === 'order_authority_refused',
        'revoked Store Lite order authority blocks execution planning'
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($actorId, 'store.orders.manage', $actorId)"
    );

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_sandbox_checkout_real_mutation_helpers.php'
    );
    $executionBoundary = strpos(
        $source,
        "if (!function_exists('red_addon_checkout_real_mutation_typed_input'))"
    );
    $authoritySource = $executionBoundary === false
        ? ''
        : substr($source, 0, $executionBoundary);
    foreach (['curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'Authorization:', 'php://input', '$_POST', '$_SERVER', 'getenv(',
        'putenv(', 'shell_exec(', 'sleep(', 'usleep(',
        'red_addon_secret_resolve(']
        as $forbidden
    ) {
        red_addon_checkout_mutation_test_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from the complete D4B helper'
        );
    }
    foreach (['red_addon_runtime_register_package(',
        'red_addon_adapter_invoke_registered('] as $executionPrimitive
    ) {
        red_addon_checkout_mutation_test_assert(
            $authoritySource !== ''
                && !str_contains($authoritySource, $executionPrimitive),
            $executionPrimitive . ' is absent from D4B1 authority and claim'
        );
    }

    red_checkout_p3e9d4b_lifecycle_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID IN ('$adapterPackageId','$storePackageId')),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tableName'))"
        ) === '0:0:0:0:0'
            && !file_exists($temporaryRoot),
        'cleanup removes every D4 lifecycle fixture exactly'
    );
    echo 'Sandbox Checkout P3E-9D4B lifecycle self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_checkout_p3e9d4b_lifecycle_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
