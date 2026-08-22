<?php
/** Dependency-free P3E-9D2 core containment and identity checks. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_sandbox_checkout_real_operation_helpers.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-p3e9d2-' . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$packageDirectory = $fixtureProject
    . '/addons/redcms/store-lite-stripe-checkout';
$GLOBALS['RED_P3E9D2_REGISTRAR_CALLS'] = 0;
$GLOBALS['RED_P3E9D2_HANDLER_CALLS'] = 0;
$GLOBALS['RED_P3E9D2_SECRET_CALLS'] = 0;
$GLOBALS['RED_P3E9D2_LAST_OPERATION'] = '';
$GLOBALS['RED_P3E9D2_MODE'] = 'success';

function red_checkout_p3e9d2_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_checkout_p3e9d2_delete($path)
{
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            red_checkout_p3e9d2_delete($path . '/' . $entry);
        }
    }
    rmdir($path);
}

function red_checkout_p3e9d2_input()
{
    return [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => [
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
        ],
        'policy' => [
            'apiVersion' => '2024-09-30.acacia',
            'successUrl' =>
                'https://shop.example.test/checkout/stripe-complete',
            'cancelUrl' => 'https://shop.example.test/checkout',
            'createdAtEpoch' => 1787025600,
            'expiresAtEpoch' => 1787027400,
        ],
        'profile' => [
            'packageId' => 'redcms.store-lite-stripe-checkout',
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
        ],
        'contractSha256' => str_repeat('c', 64),
    ];
}

function red_checkout_p3e9d2_synthetic_plan(array $input)
{
    return [
        'valid' => true,
        'ready' => true,
        'status' => 'ready',
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'packageVersion' => '0.1.5',
        'adapterId' => 'redcms.store-lite-stripe-checkout/checkout',
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
}

function red_checkout_p3e9d2_write_package($packageDirectory)
{
    if (!mkdir($packageDirectory . '/migrations', 0700, true)
        && !is_dir($packageDirectory . '/migrations')
    ) {
        throw new RuntimeException('Could not create P3E-9D2 fixture.');
    }
    $entrypoint = <<<'PHP'
<?php
$GLOBALS['RED_P3E9D2_REGISTRAR_CALLS'] =
    (int) ($GLOBALS['RED_P3E9D2_REGISTRAR_CALLS'] ?? 0) + 1;
return static function ($registry): void {
    $registry->registerAdapter(
        'redcms.store-lite-stripe-checkout/checkout',
        static function (
            RED_Addon_Adapter_Request $request
        ): RED_Addon_Adapter_Result {
            $GLOBALS['RED_P3E9D2_HANDLER_CALLS'] =
                (int) ($GLOBALS['RED_P3E9D2_HANDLER_CALLS'] ?? 0) + 1;
            $GLOBALS['RED_P3E9D2_LAST_OPERATION'] = $request->operation();
            if ($request->operation()
                    !== 'checkout.create-sandbox-real-post-preflight'
            ) {
                return RED_Addon_Adapter_Result::failure(
                    'unsupported_operation'
                );
            }
            $mode = $GLOBALS['RED_P3E9D2_MODE'] ?? 'success';
            if ($mode === 'throw') {
                throw new RuntimeException('p3e9d2_handler_failed');
            }
            if ($mode === 'output') {
                echo 'forbidden';
            }
            if ($mode === 'malformed') {
                return RED_Addon_Adapter_Result::success([
                    'valid' => true,
                    'unexpected' => true,
                ]);
            }
            $input = $request->input();
            $preflight = $input['realPostPreflight'];
            $fields = [
                'mode' => 'payment',
                'success_url' => $input['policy']['successUrl'],
                'cancel_url' => $input['policy']['cancelUrl'],
                'expires_at' => $input['policy']['expiresAtEpoch'],
                'client_reference_id' => $input['checkout']['orderId'],
                'metadata[order_snapshot_sha256]' =>
                    $input['checkout']['orderSnapshotSha256'],
                'metadata[input_sha256]' => $preflight['inputSha256'],
            ];
            foreach ($input['checkout']['lineItems'] as $index => $line) {
                $prefix = 'line_items[' . $index . ']';
                $fields[$prefix . '[price_data][currency]'] = 'usd';
                $fields[$prefix . '[price_data][product_data][name]'] =
                    $line['name'];
                $fields[$prefix . '[price_data][unit_amount]'] =
                    $line['unitAmountMinor'];
                $fields[$prefix . '[quantity]'] = $line['quantity'];
            }
            $typedFields = [];
            foreach ($fields as $name => $value) {
                $typedFields[] = ['name' => $name, 'value' => $value];
            }
            $data = [
                'valid' => true,
                'adopted' => true,
                'status' => 'request_contract_adopted',
                'packageId' => 'redcms.store-lite-stripe-checkout',
                'packageVersion' => '0.1.7',
                'sourcePackageVersion' => '0.1.5',
                'operation' =>
                    'checkout.create-sandbox-real-post-preflight',
                'providerOperation' => 'checkout.create-sandbox-real-post',
                'request' => [
                    'method' => $preflight['method'],
                    'host' => $preflight['host'],
                    'path' => $preflight['path'],
                    'apiVersion' => $preflight['apiVersion'],
                    'contentType' => $preflight['contentType'],
                    'idempotencyKey' => $preflight['idempotencyKey'],
                    'formFields' => $typedFields,
                ],
                'inputSha256' => $preflight['inputSha256'],
                'syntheticPlanSha256' =>
                    $preflight['syntheticPlanSha256'],
                'contractSha256' => $input['contractSha256'],
                'requestSha256' => $preflight['requestSha256'],
                'restrictedTestWriteKeyRequired' => true,
                'credentialValueIncluded' => false,
                'authorizationHeaderIncluded' => false,
                'executionReady' => false,
                'networkAccess' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'checkoutCreation' => false,
                'payment' => false,
                'webhook' => false,
                'browserNavigation' => false,
                'storeLiteMutation' => false,
                'retryAuthorized' => false,
                'liveMode' => false,
                'clientDeployment' => false,
                'executionPerformed' => false,
                'errors' => [],
            ];
            if ($mode === 'changed-provider-operation') {
                $data['providerOperation'] = 'checkout.create-sandbox';
            }
            return RED_Addon_Adapter_Result::success($data);
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
    $migration = "CREATE TABLE RED_P3E9D2_Unused (\n"
        . "  RecordID bigint unsigned NOT NULL\n"
        . ") ENGINE=InnoDB;\n";
    file_put_contents($packageDirectory . '/addon.php', $entrypoint);
    file_put_contents(
        $packageDirectory . '/migrations/2026-08-21-p3e9d2-unused.sql',
        $migration
    );
    $packageId = 'redcms.store-lite-stripe-checkout';
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'P3E-9D2 real operation preflight fixture',
        'description' => 'Integrity-checked no-contact adapter fixture.',
        'version' => '0.1.7',
        'type' => 'adapter',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => [$packageId . '/checkout'],
        ],
        'dependencies' => [
            'required' => [[
                'id' => 'redcms.store-lite',
                'version' => '>=0.1.35 <1.0',
            ]],
            'optional' => [],
        ],
        'permissions' => [],
        'settings' => [[
            'key' => 'checkout.return-origin',
            'label' => 'Checkout return origin',
            'type' => 'url',
            'secret' => false,
            'default' => null,
        ], [
            'key' => 'stripe.secret-key',
            'label' => 'Stripe secret key reference',
            'type' => 'secret-reference',
            'secret' => true,
        ], [
            'key' => 'stripe.webhook-secret',
            'label' => 'Stripe webhook secret reference',
            'type' => 'secret-reference',
            'secret' => true,
        ]],
        'migrations' => [[
            'id' => '2026-08-21-p3e9d2-unused',
            'path' => 'migrations/2026-08-21-p3e9d2-unused.sql',
            'sha256' => hash('sha256', $migration),
        ]],
        'routes' => [[
            'id' => $packageId . '/provider-events',
            'scope' => 'public',
            'path' =>
                '/addons/redcms/store-lite-stripe-checkout/provider-events',
            'methods' => ['POST'],
            'authentication' => 'server-signature',
            'csrf' => 'not-applicable',
        ]],
        'publicMutationContracts' => [],
        'jobs' => [],
        'outboundHosts' => ['api.stripe.com'],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => hash('sha256', $entrypoint),
            ], [
                'path' => 'migrations/2026-08-21-p3e9d2-unused.sql',
                'sha256' => hash('sha256', $migration),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
    file_put_contents(
        $packageDirectory . '/addon.json',
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
        ) . "\n"
    );

    $storeDirectory = dirname($packageDirectory) . '/store-lite';
    mkdir($storeDirectory, 0700, true);
    $storeEntrypoint = "<?php\nreturn static function (\$registry): void {};\n";
    file_put_contents($storeDirectory . '/addon.php', $storeEntrypoint);
    $storeManifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.store-lite',
        'name' => 'Store Lite dependency fixture',
        'description' => 'P3E-9D2 dependency-only fixture.',
        'version' => '0.1.35',
        'type' => 'content-package',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'publicMutationContracts' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => hash('sha256', $storeEntrypoint),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
    file_put_contents(
        $storeDirectory . '/addon.json',
        json_encode(
            $storeManifest,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
        ) . "\n"
    );
}

red_checkout_p3e9d2_delete($temporaryRoot);

try {
    red_checkout_p3e9d2_write_package($packageDirectory);
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][
        'redcms.store-lite-stripe-checkout'
    ] ?? [];
    red_checkout_p3e9d2_assert(
        !empty($catalog['valid'])
            && !empty($package['valid'])
            && $GLOBALS['RED_P3E9D2_REGISTRAR_CALLS'] === 0,
        'adapter 0.1.7 discovers without package execution'
    );

    $input = red_checkout_p3e9d2_input();
    $syntheticPlan = red_checkout_p3e9d2_synthetic_plan($input);
    $preflight = red_addon_checkout_real_post_preflight(
        $syntheticPlan,
        $input
    );
    red_checkout_p3e9d2_assert(
        !empty($preflight['ready'])
            && ($preflight['packageVersion'] ?? null) === '0.1.5'
            && red_addon_checkout_synthetic_sha256(
                $preflight['requestSha256'] ?? null
            )
            && is_array($preflight['formFields'] ?? null),
        'real D0 preflight supplies canonical source evidence and form fields'
    );

    $plan = red_addon_checkout_real_operation_plan(
        $package,
        $syntheticPlan,
        $input,
        $preflight
    );
    red_checkout_p3e9d2_assert(
        !empty($plan['valid'])
            && !empty($plan['ready'])
            && ($plan['packageVersion'] ?? null) === '0.1.7'
            && ($plan['sourcePackageVersion'] ?? null) === '0.1.5'
            && ($plan['operation'] ?? null)
                === 'checkout.create-sandbox-real-post-preflight'
            && ($plan['providerOperation'] ?? null)
                === 'checkout.create-sandbox-real-post'
            && red_addon_checkout_synthetic_sha256(
                $plan['planSha256'] ?? null
            )
            && red_addon_checkout_synthetic_sha256(
                $plan['executionStartIdentitySha256'] ?? null
            ),
        'D2 plan binds exact package, operations, request, and start identity'
    );
    red_checkout_p3e9d2_assert(
        !empty($plan['startIdentityPrepared'])
            && empty($plan['resultIdentityPrepared'])
            && empty($plan['adapterInvoked'])
            && empty($plan['credentialAccessProvided'])
            && empty($plan['executionReady'])
            && empty($plan['executionStarted'])
            && empty($plan['resultRecorded'])
            && empty($plan['executionPerformed'])
            && empty($plan['networkAccess'])
            && empty($plan['providerContact'])
            && empty($plan['checkoutCreation'])
            && $GLOBALS['RED_P3E9D2_REGISTRAR_CALLS'] === 0
            && $GLOBALS['RED_P3E9D2_HANDLER_CALLS'] === 0,
        'dry plan prepares identity only and performs no package or real effect'
    );
    red_checkout_p3e9d2_assert(
        $plan === red_addon_checkout_real_operation_plan(
            $package,
            $syntheticPlan,
            $input,
            $preflight
        ),
        'identical evidence produces identical plan and identity hashes'
    );

    $changedPreflight = $preflight;
    $changedPreflight['requestSha256'] = str_repeat('0', 64);
    red_checkout_p3e9d2_assert(
        empty(red_addon_checkout_real_operation_plan(
            $package,
            $syntheticPlan,
            $input,
            $changedPreflight
        )['ready']),
        'changed D0 request hash is refused'
    );
    $changedInput = $input;
    $changedInput['checkout']['amountMinor']++;
    red_checkout_p3e9d2_assert(
        empty(red_addon_checkout_real_operation_plan(
            $package,
            $syntheticPlan,
            $changedInput,
            $preflight
        )['ready']),
        'changed Checkout input cannot borrow D0 evidence'
    );
    $wrongVersion = $package;
    $wrongVersion['manifest']['version'] = '0.1.6';
    red_checkout_p3e9d2_assert(
        empty(red_addon_checkout_real_operation_plan(
            $wrongVersion,
            $syntheticPlan,
            $input,
            $preflight
        )['ready']),
        'adapter 0.1.6 is refused after the canonical-hash repair'
    );

    $executed = red_addon_checkout_real_operation_execute(
        $package,
        $syntheticPlan,
        $input,
        $preflight,
        $plan['planSha256'],
        $plan['executionStartIdentitySha256']
    );
    red_checkout_p3e9d2_assert(
        !empty($executed['valid'])
            && empty($executed['ready'])
            && ($executed['status'] ?? null)
                === 'request_contract_adopted'
            && !empty($executed['adapterInvoked'])
            && !empty($executed['startIdentityPrepared'])
            && !empty($executed['resultIdentityPrepared'])
            && red_addon_checkout_synthetic_sha256(
                $executed['resultIdentitySha256'] ?? null
            ),
        'core contains one preflight invocation and derives result identity'
    );
    red_checkout_p3e9d2_assert(
        empty($executed['credentialAccessProvided'])
            && empty($executed['credentialValueIncluded'])
            && empty($executed['authorizationHeaderIncluded'])
            && empty($executed['executionReady'])
            && empty($executed['executionStarted'])
            && empty($executed['resultRecorded'])
            && empty($executed['executionPerformed'])
            && empty($executed['networkAccess'])
            && empty($executed['providerContact'])
            && empty($executed['providerMutation'])
            && empty($executed['checkoutCreation'])
            && empty($executed['payment'])
            && empty($executed['retryAuthorized']),
        'contained result keeps every credential, provider, and business effect false'
    );
    red_checkout_p3e9d2_assert(
        $GLOBALS['RED_P3E9D2_REGISTRAR_CALLS'] === 1
            && $GLOBALS['RED_P3E9D2_HANDLER_CALLS'] === 1
            && $GLOBALS['RED_P3E9D2_SECRET_CALLS'] === 0
            && $GLOBALS['RED_P3E9D2_LAST_OPERATION']
                === 'checkout.create-sandbox-real-post-preflight'
            && ($executed['boundedOutcome']['providerOperation'] ?? null)
                === 'checkout.create-sandbox-real-post'
            && ($executed['boundedOutcome']['executionPerformed'] ?? null)
                === false,
        'only the preflight operation runs and no secret boundary is called'
    );
    red_checkout_p3e9d2_assert(
        is_array($executed['boundedOutcome']['request']['formFields'] ?? null)
            && array_is_list(
                $executed['boundedOutcome']['request']['formFields']
            )
            && !array_key_exists('realPostPreflight', $executed['boundedOutcome']),
        'provider form map leaves the typed input and returns only as a list'
    );

    $before = $GLOBALS['RED_P3E9D2_HANDLER_CALLS'];
    foreach ([
        [$plan['planSha256'], str_repeat('1', 64)],
        [str_repeat('2', 64), $plan['executionStartIdentitySha256']],
    ] as [$expectedPlan, $expectedStart]) {
        $refused = red_addon_checkout_real_operation_execute(
            $package,
            $syntheticPlan,
            $input,
            $preflight,
            $expectedPlan,
            $expectedStart
        );
        red_checkout_p3e9d2_assert(
            ($refused['status'] ?? null) === 'execution_identity_changed'
                && $GLOBALS['RED_P3E9D2_HANDLER_CALLS'] === $before,
            'changed expected identity is refused before package invocation'
        );
    }

    foreach (['malformed', 'throw', 'output', 'changed-provider-operation']
        as $mode
    ) {
        $GLOBALS['RED_P3E9D2_MODE'] = $mode;
        $refused = red_addon_checkout_real_operation_execute(
            $package,
            $syntheticPlan,
            $input,
            $preflight,
            $plan['planSha256'],
            $plan['executionStartIdentitySha256']
        );
        red_checkout_p3e9d2_assert(
            in_array($refused['status'] ?? null, [
                'package_execution_refused', 'package_outcome_refused',
            ], true)
                && empty($refused['resultIdentityPrepared'])
                && empty($refused['executionStarted'])
                && empty($refused['executionPerformed'])
                && empty($refused['networkAccess'])
                && empty($refused['providerContact'])
                && empty($refused['checkoutCreation']),
            'handler ' . $mode . ' failure is contained without partial identity'
        );
    }

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_sandbox_checkout_real_operation_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'Authorization:', 'php://input', '$_POST', '$_SERVER', 'PDO',
        'mysqli', 'getenv(', 'putenv(', 'shell_exec(', 'exec(', 'sleep(',
        'usleep(', 'RED_Addon_Runtime_Secret_Access',
        'red_addon_runtime_secret', '->secret(',
    ] as $forbidden) {
        red_checkout_p3e9d2_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from P3E-9D2 core source'
        );
    }
    red_checkout_p3e9d2_assert(
        str_contains(
            $source,
            "'checkout.create-sandbox-real-post-preflight'"
        )
            && str_contains($source, "'checkout.create-sandbox-real-post'")
            && substr_count(
                $source,
                "\n                null\n            );"
            ) === 1,
        'core names both identities but invokes the adapter without secret access'
    );

    red_checkout_p3e9d2_delete($temporaryRoot);
    red_checkout_p3e9d2_assert(
        !file_exists($temporaryRoot),
        'fixture cleanup removes the exact temporary package project'
    );

    echo 'Sandbox Checkout P3E-9D2 core operation self-test passed: '
        . $assertions . " assertions.\n";
    echo "No credential, database, DNS, TLS, HTTP, Stripe, Checkout Session, payment, or deployment effect occurred.\n";
} catch (Throwable $throwable) {
    red_checkout_p3e9d2_delete($temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
