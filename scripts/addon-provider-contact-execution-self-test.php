<?php
/**
 * Disposable-database P3E-8B2 loopback execution acceptance fixture.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_PROVIDER_CONTACT_AUTHORIZATION_FIXTURE_ONLY', true);
require_once __DIR__ . '/addon-provider-contact-authorization-self-test.php';
require_once $projectRoot .
    '/includes/addon_provider_contact_execution_helpers.php';

$assertions = 0;
$actorId = 2147000991;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Contact_Execution_Fixture';
$temporaryRoot = sys_get_temp_dir() . '/redcms-provider-contact-execution-' .
    bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$packageIds = [$adapterPackageId, $storePackageId];
$apiReference = 'config:p3e8b2-stripe-secret-key';
$webhookReference = 'config:p3e8b2-stripe-webhook-secret';
$GLOBALS['RED_P3E8B2_REGISTRAR_CALLS'] = 0;
$GLOBALS['RED_P3E8B2_LOOPBACK_DOUBLE'] = null;

function red_addon_provider_contact_execution_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_provider_contact_execution_test_clear_environment()
{
    putenv('RED_ADDON_SECRET_REFERENCES');
    putenv('RED_ADDON_SECRET_VALUES_JSON');
}

function red_addon_provider_contact_execution_test_cleanup(
    $connection,
    array $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
) {
    red_addon_provider_contact_execution_test_clear_environment();
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $GLOBALS['RED_P3E8B2_LOOPBACK_DOUBLE'] = null;
    foreach ($packageIds as $packageId) {
        $escaped = mysqli_real_escape_string($connection, $packageId);
        foreach ([
            'RED_Addon_Admin_Action_Executions',
            'RED_Addon_Settings',
            'RED_Addon_Activity_Log',
        ] as $table) {
            mysqli_query(
                $connection,
                "DELETE FROM $table WHERE PackageID='$escaped'"
            );
        }
    }
    red_addon_payment_adapter_db_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
}

function red_addon_provider_contact_execution_test_scalar(
    $connection,
    $sql
) {
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_addon_provider_contact_execution_test_write_adapter(
    $fixtureProject,
    $adapterPackageId,
    $executionMarker,
    $tableName
) {
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $adapterPackageId,
        'adapter',
        '0.1.1',
        $executionMarker,
        $tableName
    );
    $directory = $fixtureProject .
        '/addons/redcms/store-lite-stripe-checkout';
    $entrypoint = <<<'PHP'
<?php
$GLOBALS['RED_P3E8B2_REGISTRAR_CALLS'] =
    (int) ($GLOBALS['RED_P3E8B2_REGISTRAR_CALLS'] ?? 0) + 1;
return static function ($registry): void {
    $registry->registerAdapter(
        'redcms.store-lite-stripe-checkout/checkout',
        static function (
            RED_Addon_Adapter_Request $request
        ): RED_Addon_Adapter_Result {
            if ($request->operation()
                    !== 'provider-contact.read-only-probe-loopback'
                || ($request->input()['contactTarget'] ?? null)
                    !== 'loopback'
                || !is_array($request->input()['contactPlan'] ?? null)
            ) {
                return RED_Addon_Adapter_Result::failure(
                    'unsupported_operation'
                );
            }
            $apiKey = null;
            $api = $request->secret('stripe.secret-key', $apiKey);
            $webhook = null;
            $webhookResult = $request->secret(
                'stripe.webhook-secret',
                $webhook
            );
            if (($api['resolved'] ?? false) !== true
                || !is_string($apiKey)
                || $apiKey === ''
                || ($webhookResult['resolved'] ?? false) !== false
                || $webhook !== null
            ) {
                $apiKey = null;
                return RED_Addon_Adapter_Result::failure(
                    'scoped_secret_unavailable'
                );
            }
            $double = $GLOBALS['RED_P3E8B2_LOOPBACK_DOUBLE'] ?? null;
            if (!is_callable($double)) {
                $apiKey = null;
                return RED_Addon_Adapter_Result::failure(
                    'loopback_double_unavailable'
                );
            }
            try {
                $outcome = $double(
                    $request->input()['contactPlan'],
                    $apiKey
                );
            } catch (Throwable $throwable) {
                $outcome = null;
            } finally {
                $apiKey = null;
            }
            return is_array($outcome)
                ? RED_Addon_Adapter_Result::success($outcome)
                : RED_Addon_Adapter_Result::failure(
                    'loopback_execution_failed'
                );
        }
    );
    $registry->registerRoute(
        'redcms.store-lite-stripe-checkout/provider-events',
        static function (): never {
            throw new LogicException('provider_event_route_inert');
        }
    );
};
PHP;
    file_put_contents($directory . '/addon.php', $entrypoint);
    $manifestPath = $directory . '/addon.json';
    $manifest = json_decode(
        (string) file_get_contents($manifestPath),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $manifest['routes'][0]['path'] =
        '/addons/redcms/store-lite-stripe-checkout/provider-events';
    foreach ($manifest['integrity']['files'] as &$file) {
        if (($file['path'] ?? null) === 'addon.php') {
            $file['sha256'] = hash('sha256', $entrypoint);
        }
    }
    unset($file);
    file_put_contents(
        $manifestPath,
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
        ) . "\n"
    );
}

function red_addon_provider_contact_execution_test_settings(
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

function red_addon_provider_contact_execution_test_claim(
    $connection,
    $fixtureProject,
    $actorId,
    array $readiness,
    $ownerSubject,
    $nonceSha256
) {
    $prepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        $nonceSha256
    );
    $authorized = red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $prepared,
        $prepared['authorizationSha256'],
        '2026-08-17T12:05:00Z'
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][
        'redcms.store-lite-stripe-checkout'
    ] ?? [];
    $claimPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $package,
        $catalog,
        $actorId,
        $readiness,
        $prepared,
        '2026-08-17T12:06:00Z'
    );
    $claimed = red_addon_provider_contact_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $prepared,
        $claimPlan['authorizationSha256'] ?? '',
        $claimPlan['authorizationStateSha256'] ?? '',
        $claimPlan['claimStateSha256'] ?? '',
        '2026-08-17T12:06:00Z'
    );
    return [
        'prepared' => $prepared,
        'authorized' => $authorized,
        'claimPlan' => $claimPlan,
        'claimed' => $claimed,
    ];
}

red_addon_provider_contact_execution_test_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash(
        'ProviderContactExecution-2026!',
        PASSWORD_DEFAULT
    );
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_provider_contact_execution', ?, 'Admin',
                   'P3E8B2Exec', 'webmaster', '', '',
                   'provider-execution@example.test', 'N', 'to', 'N', 'to')"
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
    $capability = 'addons.enable';
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param($statement, 'isi', $actorId, $capability, $actorId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $storePackageId,
        'content-package',
        '0.1.35',
        $executionMarker
    );
    red_addon_provider_contact_execution_test_write_adapter(
        $fixtureProject,
        $adapterPackageId,
        $executionMarker,
        $tableName
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? [];
    red_addon_provider_contact_execution_test_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($adapterPackage['valid'])
            && $GLOBALS['RED_P3E8B2_REGISTRAR_CALLS'] === 0,
        'exact fixture packages discover without executing the registrar'
    );
    red_addon_provider_contact_execution_test_assert(
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
        'same disposable client records enabled Store Lite and adapter'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    red_addon_provider_contact_execution_test_assert(
        mysqli_errno($connection) === 0
            && red_addon_provider_contact_execution_test_settings(
                $connection,
                $adapterPackageId,
                $actorId,
                $apiReference,
                $webhookReference
            ),
        'fixture records only isolated InnoDB and package-setting state'
    );

    $readiness = red_addon_provider_contact_test_readiness();
    $ownerSubject = red_addon_provider_contact_owner_subject_sha256(
        $connection,
        $actorId
    );
    $first = red_addon_provider_contact_execution_test_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $ownerSubject,
        str_repeat('1', 64)
    );
    red_addon_provider_contact_execution_test_assert(
        ($first['authorized']['status'] ?? '') === 'authorized'
            && ($first['claimed']['status'] ?? '') === 'claimed',
        'exact P3E-7 authorization and P3E-8A claim precede execution'
    );

    red_addon_provider_contact_execution_test_clear_environment();
    putenv('RED_ADDON_SECRET_REFERENCES=' . $apiReference);
    $plan = red_addon_provider_contact_execution_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $first['prepared'],
        '2026-08-17T12:07:00Z'
    );
    red_addon_provider_contact_execution_test_assert(
        !empty($plan['valid'])
            && !empty($plan['ready'])
            && ($plan['status'] ?? '') === 'ready'
            && !empty($plan['claimRecorded'])
            && !empty($plan['executionStartAvailable'])
            && red_addon_provider_contact_sha256(
                $plan['secretAvailabilitySha256'] ?? ''
            )
            && red_addon_provider_contact_sha256(
                $plan['executionStartStateSha256'] ?? ''
            ),
        'value-free dry run revalidates claim and secret declaration'
    );
    red_addon_provider_contact_execution_test_assert(
        getenv('RED_ADDON_SECRET_VALUES_JSON') === false
            && $GLOBALS['RED_P3E8B2_REGISTRAR_CALLS'] === 0
            && $GLOBALS['RED_P3E8B2_LOOPBACK_DOUBLE'] === null
            && red_addon_provider_contact_execution_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'"
            ) === '2',
        'dry run resolves no value, executes no package, and writes nothing'
    );

    $syntheticKey = 'rk_' . 'test_' . str_repeat('a', 32);
    $loopbackCalls = 0;
    $keyMatched = false;
    $planMatched = false;
    $GLOBALS['RED_P3E8B2_LOOPBACK_DOUBLE'] = static function (
        array $contactPlan,
        string $apiKey
    ) use (
        &$loopbackCalls,
        &$keyMatched,
        &$planMatched,
        $syntheticKey,
        $readiness
    ): array {
        $loopbackCalls++;
        $keyMatched = hash_equals($syntheticKey, $apiKey);
        $planMatched = $contactPlan === $readiness['contactPlan'];
        $evidence = [
            'contactTarget' => 'loopback',
            'statusCode' => 404,
            'responseBytes' => 227,
            'bodyDiscarded' => true,
        ];
        return [
            'valid' => true,
            'contactTarget' => 'loopback',
            'outcome' => 'resource_miss_observed',
            'statusCode' => 404,
            'expectedEffectObserved' => true,
            'responseBytes' => 227,
            'transportEvidenceSha256' => hash(
                'sha256',
                json_encode($evidence, JSON_UNESCAPED_SLASHES)
            ),
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'executionPerformed' => true,
            'errors' => [],
        ];
    };
    putenv('RED_ADDON_SECRET_VALUES_JSON=' . json_encode([
        $apiReference => $syntheticKey,
    ], JSON_UNESCAPED_SLASHES));
    $executed = red_addon_provider_contact_execute_loopback(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $first['prepared'],
        $plan['authorizationSha256'],
        $plan['claimStateSha256'],
        $plan['executionStartStateSha256'],
        '2026-08-17T12:07:00Z'
    );
    red_addon_provider_contact_execution_test_assert(
        ($executed['status'] ?? '') === 'resource_miss_observed'
            && !empty($executed['executionStarted'])
            && !empty($executed['startAuditRecorded'])
            && !empty($executed['registrarValidated'])
            && !empty($executed['secretResolution'])
            && !empty($executed['adapterInvoked'])
            && !empty($executed['executionPerformed'])
            && !empty($executed['outcomeRecorded'])
            && !empty($executed['outcomeAuditRecorded'])
            && empty($executed['networkAccess'])
            && empty($executed['providerContact'])
            && empty($executed['retryAuthorized']),
        'one claimed execution reaches only the contained loopback adapter'
    );
    red_addon_provider_contact_execution_test_assert(
        $loopbackCalls === 1
            && $keyMatched
            && $planMatched
            && $GLOBALS['RED_P3E8B2_REGISTRAR_CALLS'] === 1
            && !str_contains(json_encode($executed), $syntheticKey)
            && ($executed['boundedOutcome']['responseBodyIncluded'] ?? null)
                === false,
        'loopback receives exact plan and scoped key without disclosure'
    );
    $startAction = mysqli_real_escape_string(
        $connection,
        $plan['executionStartActionId']
    );
    $outcomeAction = mysqli_real_escape_string(
        $connection,
        $plan['outcomeActionId']
    );
    red_addon_provider_contact_execution_test_assert(
        red_addon_provider_contact_execution_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'
                   AND ActionID='$startAction'
                   AND PreviousStateSHA256='{$plan['claimStateSha256']}'
                   AND StateSHA256='{$plan['executionStartStateSha256']}'),
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'
                   AND ActionID='$outcomeAction'
                   AND PreviousStateSHA256='{$plan['executionStartStateSha256']}'
                   AND StateSHA256='{$executed['outcomeStateSha256']}'))"
        ) === '1:1',
        'start and outcome ledgers bind only the exact state hashes'
    );
    red_addon_provider_contact_execution_test_assert(
        red_addon_provider_contact_execution_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'
                   AND DetailCode='provider_contact_execution_started'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'
                   AND DetailCode='provider_contact_loopback_resource_miss'))"
        ) === '1:1',
        'bounded start and loopback outcome audits are committed'
    );
    $replay = red_addon_provider_contact_execute_loopback(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $first['prepared'],
        $plan['authorizationSha256'],
        $plan['claimStateSha256'],
        $plan['executionStartStateSha256'],
        '2026-08-17T12:08:00Z'
    );
    red_addon_provider_contact_execution_test_assert(
        ($replay['status'] ?? '') === 'execution_already_started'
            && $loopbackCalls === 1
            && $GLOBALS['RED_P3E8B2_REGISTRAR_CALLS'] === 1,
        'committed execution start permanently blocks replay'
    );

    $second = red_addon_provider_contact_execution_test_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $ownerSubject,
        str_repeat('2', 64)
    );
    $secondPlan = red_addon_provider_contact_execution_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $second['prepared'],
        '2026-08-17T12:07:00Z'
    );
    putenv('RED_ADDON_SECRET_VALUES_JSON');
    $missingSecret = red_addon_provider_contact_execute_loopback(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $second['prepared'],
        $secondPlan['authorizationSha256'],
        $secondPlan['claimStateSha256'],
        $secondPlan['executionStartStateSha256'],
        '2026-08-17T12:07:00Z'
    );
    red_addon_provider_contact_execution_test_assert(
        ($missingSecret['status'] ?? '') === 'indeterminate'
            && !empty($missingSecret['executionStarted'])
            && empty($missingSecret['secretResolution'])
            && empty($missingSecret['adapterInvoked'])
            && empty($missingSecret['executionPerformed'])
            && !empty($missingSecret['outcomeRecorded'])
            && $loopbackCalls === 1,
        'missing value after start records indeterminate without invocation'
    );
    $missingReplay = red_addon_provider_contact_execute_loopback(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $second['prepared'],
        $secondPlan['authorizationSha256'],
        $secondPlan['claimStateSha256'],
        $secondPlan['executionStartStateSha256'],
        '2026-08-17T12:08:00Z'
    );
    red_addon_provider_contact_execution_test_assert(
        ($missingReplay['status'] ?? '') === 'execution_already_started'
            && $loopbackCalls === 1,
        'indeterminate post-start failure cannot be retried'
    );

    $third = red_addon_provider_contact_execution_test_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $ownerSubject,
        str_repeat('3', 64)
    );
    $thirdPlan = red_addon_provider_contact_execution_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $third['prepared'],
        '2026-08-17T12:07:00Z'
    );
    putenv('RED_ADDON_SECRET_VALUES_JSON=' . json_encode([
        $apiReference => $syntheticKey,
    ], JSON_UNESCAPED_SLASHES));
    $startAuditFailure = red_addon_provider_contact_execute_loopback(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $third['prepared'],
        $thirdPlan['authorizationSha256'],
        $thirdPlan['claimStateSha256'],
        $thirdPlan['executionStartStateSha256'],
        '2026-08-17T12:07:00Z',
        static fn () => false
    );
    red_addon_provider_contact_execution_test_assert(
        ($startAuditFailure['status'] ?? '')
            === 'execution_start_audit_failed'
            && empty($startAuditFailure['executionStarted'])
            && $loopbackCalls === 1
            && $GLOBALS['RED_P3E8B2_REGISTRAR_CALLS'] === 2,
        'failed start audit rolls back before registrar or loopback execution'
    );
    $afterStartRollback = red_addon_provider_contact_execute_loopback(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $third['prepared'],
        $thirdPlan['authorizationSha256'],
        $thirdPlan['claimStateSha256'],
        $thirdPlan['executionStartStateSha256'],
        '2026-08-17T12:07:00Z'
    );
    red_addon_provider_contact_execution_test_assert(
        ($afterStartRollback['status'] ?? '') === 'resource_miss_observed'
            && $loopbackCalls === 2,
        'rolled-back start remains available before any attempt began'
    );

    $fourth = red_addon_provider_contact_execution_test_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $ownerSubject,
        str_repeat('4', 64)
    );
    $fourthPlan = red_addon_provider_contact_execution_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $fourth['prepared'],
        '2026-08-17T12:07:00Z'
    );
    $outcomeAuditFailure = red_addon_provider_contact_execute_loopback(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $fourth['prepared'],
        $fourthPlan['authorizationSha256'],
        $fourthPlan['claimStateSha256'],
        $fourthPlan['executionStartStateSha256'],
        '2026-08-17T12:07:00Z',
        null,
        static fn () => false
    );
    $failedOutcomeAction = mysqli_real_escape_string(
        $connection,
        $fourthPlan['outcomeActionId']
    );
    red_addon_provider_contact_execution_test_assert(
        ($outcomeAuditFailure['status'] ?? '') === 'outcome_audit_failed'
            && !empty($outcomeAuditFailure['executionStarted'])
            && empty($outcomeAuditFailure['outcomeRecorded'])
            && $loopbackCalls === 3
            && red_addon_provider_contact_execution_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'
                   AND ActionID='$failedOutcomeAction'"
            ) === '0',
        'outcome audit failure rolls back result but not committed start'
    );
    $afterOutcomeFailure = red_addon_provider_contact_execute_loopback(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $fourth['prepared'],
        $fourthPlan['authorizationSha256'],
        $fourthPlan['claimStateSha256'],
        $fourthPlan['executionStartStateSha256'],
        '2026-08-17T12:08:00Z'
    );
    red_addon_provider_contact_execution_test_assert(
        ($afterOutcomeFailure['status'] ?? '')
            === 'execution_already_started'
            && $loopbackCalls === 3,
        'result-record failure cannot authorize a second execution'
    );

    $missingClaimPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('5', 64)
    );
    $missingClaimAuthorization = red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $missingClaimPrepared,
        $missingClaimPrepared['authorizationSha256'],
        '2026-08-17T12:05:00Z'
    );
    $missingClaimPlan = red_addon_provider_contact_execution_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $missingClaimPrepared,
        '2026-08-17T12:07:00Z'
    );
    red_addon_provider_contact_execution_test_assert(
        ($missingClaimAuthorization['status'] ?? '') === 'authorized'
            && ($missingClaimPlan['status'] ?? '') === 'claim_record_refused'
            && $loopbackCalls === 3,
        'authorization without the exact P3E-8A claim cannot execute'
    );

    $source = (string) file_get_contents(
        $projectRoot .
            '/includes/addon_provider_contact_execution_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST',
        '$_SERVER', 'shell_exec(', 'sleep(', 'usleep(',
    ] as $forbidden) {
        red_addon_provider_contact_execution_test_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from core loopback execution source'
        );
    }
    red_addon_provider_contact_execution_test_assert(
        red_addon_provider_contact_execution_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM `$tableName`),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions),
                (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Idempotency_Keys))"
        ) === '0:0:0',
        'loopback orchestration creates no package or public-mutation row'
    );

    $syntheticKey = null;
    $GLOBALS['RED_P3E8B2_LOOPBACK_DOUBLE'] = null;
    red_addon_provider_contact_execution_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    red_addon_provider_contact_execution_test_assert(
        red_addon_provider_contact_execution_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID IN ('$adapterPackageId','$storePackageId')),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tableName'))"
        ) === '0:0:0:0:0:0'
            && getenv('RED_ADDON_SECRET_REFERENCES') === false
            && getenv('RED_ADDON_SECRET_VALUES_JSON') === false,
        'cleanup removes every row, table, secret, and package artifact'
    );

    echo 'Provider contact P3E-8B2 loopback execution self-test passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    $GLOBALS['RED_P3E8B2_LOOPBACK_DOUBLE'] = null;
    red_addon_provider_contact_execution_test_cleanup(
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
