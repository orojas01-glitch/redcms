<?php
/** Disposable P3E-9D4B2 durable start/result and in-memory execution proof. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_CHECKOUT_REAL_MUTATION_LIFECYCLE_FIXTURE_ONLY', true);
require_once __DIR__
    . '/addon-sandbox-checkout-real-mutation-lifecycle-self-test.php';

$assertions = 0;
$actorId = 2147000993;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Checkout_Real_Execution_Fixture';
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-checkout-real-execution-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$packageIds = [$adapterPackageId, $storePackageId];
$apiReference = 'config:p3e9d4b2-stripe-secret-key';
$webhookReference = 'config:p3e9d4b2-stripe-webhook-secret';
$fixtureSecret = 'd4b2-local-fixture-secret';
$GLOBALS['RED_P3E9D4B2_MODE'] = 'created';
$GLOBALS['RED_P3E9D4B2_REGISTRAR_CALLS'] = 0;
$GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'] = 0;
$GLOBALS['RED_P3E9D4B2_SECRET_CALLS'] = 0;
$GLOBALS['RED_P3E9D4B2_WEBHOOK_CALLS'] = 0;

function red_checkout_p3e9d4b2_environment_clear()
{
    putenv('RED_ADDON_SECRET_REFERENCES');
    putenv('RED_ADDON_SECRET_VALUES_JSON');
}

function red_checkout_p3e9d4b2_write_handler($fixtureProject)
{
    $path = $fixtureProject
        . '/addons/redcms/store-lite-stripe-checkout/addon.php';
    $entrypoint = <<<'PHP'
<?php
$GLOBALS['RED_P3E9D4B2_REGISTRAR_CALLS'] =
    (int) ($GLOBALS['RED_P3E9D4B2_REGISTRAR_CALLS'] ?? 0) + 1;
return static function ($registry): void {
    $registry->registerAdapter(
        'redcms.store-lite-stripe-checkout/checkout',
        static function (
            RED_Addon_Adapter_Request $request
        ): RED_Addon_Adapter_Result {
            $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'] =
                (int) ($GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'] ?? 0) + 1;
            if ($request->operation()
                    !== 'checkout.create-sandbox-real-post'
            ) {
                return RED_Addon_Adapter_Result::failure(
                    'unsupported_operation'
                );
            }
            $secret = null;
            $secretResult = $request->secret('stripe.secret-key', $secret);
            $GLOBALS['RED_P3E9D4B2_SECRET_CALLS'] =
                (int) ($GLOBALS['RED_P3E9D4B2_SECRET_CALLS'] ?? 0) + 1;
            $webhook = null;
            $webhookResult = $request->secret(
                'stripe.webhook-secret',
                $webhook
            );
            $GLOBALS['RED_P3E9D4B2_WEBHOOK_CALLS'] =
                (int) ($GLOBALS['RED_P3E9D4B2_WEBHOOK_CALLS'] ?? 0) + 1;
            if (($secretResult['resolved'] ?? null) !== true
                || !is_string($secret)
                || $secret === ''
                || ($webhookResult['resolved'] ?? null) !== false
                || $webhook !== null
            ) {
                $secret = null;
                return RED_Addon_Adapter_Result::failure('secret_refused');
            }
            $mode = $GLOBALS['RED_P3E9D4B2_MODE'] ?? 'created';
            if ($mode === 'throw') {
                $secret = null;
                throw new RuntimeException('sealed_handler_fault');
            }
            if ($mode === 'malformed') {
                $secret = null;
                return RED_Addon_Adapter_Result::success([
                    'valid' => true,
                    'unexpected' => true,
                ]);
            }
            $input = $request->input();
            $execution = $input['execution'] ?? null;
            $preflight = $input['realPostPreflight'] ?? null;
            if (($input['contactTarget'] ?? null)
                    !== 'stripe-sandbox-real-post'
                || !is_array($execution)
                || !is_array($preflight)
            ) {
                $secret = null;
                return RED_Addon_Adapter_Result::failure('input_refused');
            }
            $checkout = [
                'checkoutSessionRef' =>
                    'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
                'checkoutUrlValidated' => true,
                'mode' => 'payment',
                'status' => 'open',
                'paymentStatus' => 'unpaid',
                'amountMinor' => $input['checkout']['amountMinor'],
                'currency' => 'usd',
                'expiresAtEpoch' => $input['policy']['expiresAtEpoch'],
                'recoveryEnabled' => false,
                'livemode' => false,
            ];
            $responseEvidence = hash('sha256', 'd4b2-response-evidence');
            $resultSha256 = hash('sha256', json_encode([
                'execution' => $execution,
                'checkout' => $checkout,
                'requestSha256' => $preflight['requestSha256'],
                'responseEvidenceSha256' => $responseEvidence,
            ], JSON_UNESCAPED_SLASHES));
            $secret = null;
            return RED_Addon_Adapter_Result::success([
                'valid' => true,
                'status' => 'checkout_session_created',
                'packageId' => 'redcms.store-lite-stripe-checkout',
                'packageVersion' => '0.1.8',
                'sourcePackageVersion' => '0.1.7',
                'operation' => 'checkout.create-sandbox-real-post',
                'providerOperation' => 'checkout.create-sandbox-real-post',
                'execution' => $execution,
                'inputSha256' => $preflight['inputSha256'],
                'syntheticPlanSha256' =>
                    $preflight['syntheticPlanSha256'],
                'contractSha256' => $input['contractSha256'],
                'requestSha256' => $preflight['requestSha256'],
                'checkout' => $checkout,
                'responseEvidenceSha256' => $responseEvidence,
                'resultSha256' => $resultSha256,
                'restrictedTestWriteKeyRequired' => true,
                'credentialValueIncluded' => false,
                'authorizationHeaderIncluded' => false,
                'responseBodyIncluded' => false,
                'responseHeadersIncluded' => false,
                'checkoutUrlIncluded' => false,
                'networkAccess' => true,
                'providerContact' => true,
                'providerMutation' => true,
                'checkoutCreation' => true,
                'payment' => false,
                'webhook' => false,
                'browserNavigation' => false,
                'storeLiteMutation' => false,
                'retryAuthorized' => false,
                'liveMode' => false,
                'clientDeployment' => false,
                'executionPerformed' => true,
                'errors' => [],
            ]);
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
    file_put_contents($path, $entrypoint);
    $manifestPath = dirname($path) . '/addon.json';
    $manifest = json_decode(
        (string) file_get_contents($manifestPath),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    foreach ($manifest['integrity']['files'] as &$file) {
        if (($file['path'] ?? '') === 'addon.php') {
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

function red_checkout_p3e9d4b2_chain(
    $connection,
    $fixtureProject,
    $actorId,
    $nonceCharacter,
    array $declarations
) {
    $context = red_checkout_p3e9d4b_lifecycle_context(
        $connection,
        $fixtureProject,
        $actorId,
        $nonceCharacter,
        $declarations
    );
    $authorizationPlan = red_checkout_p3e9d4b_lifecycle_plan(
        $connection,
        $actorId,
        $context,
        'authorization',
        $declarations
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
    $claimPlan = red_checkout_p3e9d4b_lifecycle_plan(
        $connection,
        $actorId,
        $context,
        'claim',
        $declarations
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
    $executionPlan = red_checkout_p3e9d4b_lifecycle_plan(
        $connection,
        $actorId,
        $context,
        'execution',
        $declarations
    );
    return compact(
        'context',
        'authorized',
        'claimed',
        'executionPlan'
    );
}

function red_checkout_p3e9d4b2_execute(
    $connection,
    $fixtureProject,
    $actorId,
    array $chain,
    array $declarations,
    $startAudit = null,
    $outcomeAudit = null
) {
    $context = $chain['context'];
    $plan = $chain['executionPlan'];
    return red_addon_checkout_real_mutation_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $context['syntheticPlan'],
        $context['input'],
        $context['preflight'],
        $context['outcome'],
        $context['prepared'],
        $plan['authorizationSha256'] ?? '',
        $plan['authorizationStateSha256'] ?? '',
        $plan['claimStateSha256'] ?? '',
        $plan['executionStartStateSha256'] ?? '',
        '2026-08-22T12:07:00Z',
        $startAudit,
        $outcomeAudit,
        $declarations
    );
}

red_checkout_p3e9d4b2_environment_clear();
red_checkout_p3e9d4b_lifecycle_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash('CheckoutRealExecution-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_checkout_real_execution', ?, 'Admin',
                   'D4BExecution', 'webmaster', '', '',
                   'checkout-exec@example.test', 'N', 'to', 'N', 'to')"
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
    red_checkout_p3e9d4b2_write_handler($fixtureProject);
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
            && $GLOBALS['RED_P3E9D4B2_REGISTRAR_CALLS'] === 0,
        'exact packages discover without executing registrar or handler'
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
        'exact adapter and Store Lite packages are enabled'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    red_addon_checkout_mutation_test_assert(
        red_checkout_p3e9d4b_lifecycle_settings(
            $connection,
            $adapterPackageId,
            $actorId,
            $apiReference,
            $webhookReference
        ),
        'only opaque secret references are stored'
    );
    $declarations = red_addon_secret_reference_declarations(
        [$apiReference],
        ''
    );
    putenv('RED_ADDON_SECRET_REFERENCES=' . $apiReference);
    putenv('RED_ADDON_SECRET_VALUES_JSON=' . json_encode([
        $apiReference => $fixtureSecret,
    ]));

    $chain = red_checkout_p3e9d4b2_chain(
        $connection,
        $fixtureProject,
        $actorId,
        'a',
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($chain['authorized']['status'] ?? '') === 'authorized'
            && ($chain['claimed']['status'] ?? '') === 'claimed'
            && !empty($chain['executionPlan']['ready'])
            && $GLOBALS['RED_P3E9D4B2_REGISTRAR_CALLS'] === 0,
        'fresh D4 authorization and claim exist before any package access'
    );
    $executed = red_checkout_p3e9d4b2_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $chain,
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($executed['status'] ?? '') === 'checkout_session_created'
            && !empty($executed['executionStarted'])
            && !empty($executed['startAuditRecorded'])
            && !empty($executed['registrarValidated'])
            && !empty($executed['secretResolution'])
            && !empty($executed['adapterInvoked'])
            && !empty($executed['outcomeRecorded'])
            && !empty($executed['outcomeAuditRecorded'])
            && $GLOBALS['RED_P3E9D4B2_REGISTRAR_CALLS'] === 1
            && $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'] === 1
            && $GLOBALS['RED_P3E9D4B2_SECRET_CALLS'] === 1
            && $GLOBALS['RED_P3E9D4B2_WEBHOOK_CALLS'] === 1,
        'start commits before one scoped secret and in-memory handler invocation'
    );
    $outcome = $executed['boundedOutcome'] ?? [];
    red_addon_checkout_mutation_test_assert(
        ($outcome['checkout'] ?? null) === [
            'checkoutSessionRef' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
            'checkoutUrlValidated' => true,
            'mode' => 'payment',
            'status' => 'open',
            'paymentStatus' => 'unpaid',
            'amountMinor' => 5897,
            'currency' => 'usd',
            'expiresAtEpoch' => 1787027400,
            'recoveryEnabled' => false,
            'livemode' => false,
        ]
            && !empty($outcome['networkAccess'])
            && !empty($outcome['providerMutation'])
            && !empty($outcome['checkoutCreation'])
            && empty($outcome['payment'])
            && empty($outcome['webhook'])
            && empty($outcome['storeLiteMutation'])
            && empty($outcome['retryAuthorized'])
            && empty($outcome['clientDeployment']),
        'bounded result retains only conservative provider-effect facts'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'))"
        ) === '4:4',
        'authorization, claim, start, result, and four audits are exact'
    );

    $callsBeforeReplay = $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'];
    $replay = red_checkout_p3e9d4b2_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $chain,
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($replay['status'] ?? '') === 'execution_already_started'
            && $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'] === $callsBeforeReplay,
        'replay is refused before a second handler invocation'
    );

    $rollbackChain = red_checkout_p3e9d4b2_chain(
        $connection,
        $fixtureProject,
        $actorId,
        'b',
        $declarations
    );
    $callsBeforeRollback = $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'];
    $startFailure = red_checkout_p3e9d4b2_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $rollbackChain,
        $declarations,
        static fn () => false
    );
    red_addon_checkout_mutation_test_assert(
        ($startFailure['status'] ?? '') === 'execution_start_audit_failed'
            && $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'] === $callsBeforeRollback,
        'start-audit failure rolls back before registrar or handler access'
    );
    $rollbackRecovered = red_checkout_p3e9d4b2_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $rollbackChain,
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($rollbackRecovered['status'] ?? '') === 'checkout_session_created'
            && $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS']
                === $callsBeforeRollback + 1,
        'rolled-back start permits one clean recovery'
    );

    $outcomeChain = red_checkout_p3e9d4b2_chain(
        $connection,
        $fixtureProject,
        $actorId,
        'c',
        $declarations
    );
    $outcomeFailure = red_checkout_p3e9d4b2_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $outcomeChain,
        $declarations,
        null,
        static fn () => false
    );
    red_addon_checkout_mutation_test_assert(
        ($outcomeFailure['status'] ?? '') === 'outcome_audit_failed'
            && !empty($outcomeFailure['executionStarted'])
            && empty($outcomeFailure['outcomeRecorded']),
        'outcome-audit failure keeps the committed attempt spent'
    );
    $callsBeforeNoRetry = $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'];
    $noRetry = red_checkout_p3e9d4b2_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $outcomeChain,
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($noRetry['status'] ?? '') === 'execution_already_started'
            && $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'] === $callsBeforeNoRetry,
        'post-start result failure is permanently no-retry'
    );

    foreach ([['d', 'throw'], ['e', 'malformed']] as [$nonce, $mode]) {
        $GLOBALS['RED_P3E9D4B2_MODE'] = $mode;
        $faultChain = red_checkout_p3e9d4b2_chain(
            $connection,
            $fixtureProject,
            $actorId,
            $nonce,
            $declarations
        );
        $fault = red_checkout_p3e9d4b2_execute(
            $connection,
            $fixtureProject,
            $actorId,
            $faultChain,
            $declarations
        );
        red_addon_checkout_mutation_test_assert(
            ($fault['status'] ?? '') === 'indeterminate'
                && !empty($fault['outcomeRecorded'])
                && !empty($fault['boundedOutcome']['providerMutation'])
                && empty($fault['boundedOutcome']['retryAuthorized']),
            $mode . ' handler result records bounded consumed indeterminate'
        );
    }
    $GLOBALS['RED_P3E9D4B2_MODE'] = 'created';

    $missingChain = red_checkout_p3e9d4b2_chain(
        $connection,
        $fixtureProject,
        $actorId,
        'f',
        $declarations
    );
    putenv('RED_ADDON_SECRET_VALUES_JSON');
    $callsBeforeMissing = $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'];
    $missing = red_checkout_p3e9d4b2_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $missingChain,
        $declarations
    );
    red_addon_checkout_mutation_test_assert(
        ($missing['status'] ?? '') === 'indeterminate'
            && !empty($missing['executionStarted'])
            && !empty($missing['outcomeRecorded'])
            && $GLOBALS['RED_P3E9D4B2_HANDLER_CALLS'] === $callsBeforeMissing
            && empty($missing['boundedOutcome']['networkAccess'])
            && empty($missing['boundedOutcome']['providerMutation']),
        'missing secret after start records no-invocation indeterminate result'
    );
    putenv('RED_ADDON_SECRET_VALUES_JSON=' . json_encode([
        $apiReference => $fixtureSecret,
    ]));

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_sandbox_checkout_real_mutation_helpers.php'
    );
    $handlerSource = (string) file_get_contents(
        $fixtureProject
            . '/addons/redcms/store-lite-stripe-checkout/addon.php'
    );
    foreach (['curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST',
        '$_SERVER', 'shell_exec(', 'sleep(', 'usleep('] as $forbidden
    ) {
        red_addon_checkout_mutation_test_assert(
            !str_contains($source, $forbidden)
                && !str_contains($handlerSource, $forbidden),
            $forbidden . ' is absent from core runner and sealed handler'
        );
    }
    red_addon_checkout_mutation_test_assert(
        !str_contains($source, $fixtureSecret)
            && !str_contains($handlerSource, $fixtureSecret),
        'fixture secret bytes are absent from core and package source'
    );

    red_checkout_p3e9d4b2_environment_clear();
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
            && !file_exists($temporaryRoot)
            && getenv('RED_ADDON_SECRET_REFERENCES') === false
            && getenv('RED_ADDON_SECRET_VALUES_JSON') === false,
        'cleanup removes every row, package, table, file, and secret input'
    );
    echo 'Sandbox Checkout P3E-9D4B2 execution self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_checkout_p3e9d4b2_environment_clear();
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
