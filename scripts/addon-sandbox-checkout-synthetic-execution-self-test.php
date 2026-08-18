<?php
/**
 * Dependency-free P3E-9B2 synthetic Checkout core-runner acceptance.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_sandbox_checkout_synthetic_execution_helpers.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-p3e9b2-' . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$packageDirectory = $fixtureProject
    . '/addons/redcms/store-lite-stripe-checkout';
$GLOBALS['RED_P3E9B2_REGISTRAR_CALLS'] = 0;
$GLOBALS['RED_P3E9B2_HANDLER_CALLS'] = 0;
$GLOBALS['RED_P3E9B2_KEY_MATCHED'] = false;
$GLOBALS['RED_P3E9B2_MODE'] = 'success';

function red_addon_checkout_synthetic_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_checkout_synthetic_test_delete($path)
{
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        red_addon_checkout_synthetic_test_delete($path . '/' . $entry);
    }
    rmdir($path);
}

function red_addon_checkout_synthetic_test_checkout()
{
    return [
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
}

function red_addon_checkout_synthetic_test_policy()
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
        'createdAtEpoch' => 1787025600,
        'expiresAtEpoch' => 1787027400,
    ];
}

function red_addon_checkout_synthetic_test_profile()
{
    return [
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
    ];
}

function red_addon_checkout_synthetic_test_input()
{
    return [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => red_addon_checkout_synthetic_test_checkout(),
        'policy' => red_addon_checkout_synthetic_test_policy(),
        'profile' => red_addon_checkout_synthetic_test_profile(),
        'contractSha256' => str_repeat('c', 64),
    ];
}

function red_addon_checkout_synthetic_test_write_package(
    $packageDirectory
) {
    if (!mkdir($packageDirectory . '/migrations', 0700, true)
        && !is_dir($packageDirectory . '/migrations')
    ) {
        throw new RuntimeException('Could not create P3E-9B2 fixture.');
    }
    $entrypoint = <<<'PHP'
<?php
$GLOBALS['RED_P3E9B2_REGISTRAR_CALLS'] =
    (int) ($GLOBALS['RED_P3E9B2_REGISTRAR_CALLS'] ?? 0) + 1;
return static function ($registry): void {
    $registry->registerAdapter(
        'redcms.store-lite-stripe-checkout/checkout',
        static function (
            RED_Addon_Adapter_Request $request
        ): RED_Addon_Adapter_Result {
            $GLOBALS['RED_P3E9B2_HANDLER_CALLS'] =
                (int) ($GLOBALS['RED_P3E9B2_HANDLER_CALLS'] ?? 0) + 1;
            if ($request->operation()
                    !== 'checkout.create-sandbox-synthetic'
                || ($request->input()['contactTarget'] ?? null)
                    !== 'synthetic-checkout-package'
            ) {
                return RED_Addon_Adapter_Result::failure(
                    'unsupported_operation'
                );
            }
            $secret = null;
            $resolved = $request->secret('stripe.secret-key', $secret);
            $expected = 'rk_' . 'test_' . str_repeat('x', 32);
            $GLOBALS['RED_P3E9B2_KEY_MATCHED'] =
                ($resolved['resolved'] ?? false) === true
                && is_string($secret)
                && hash_equals($expected, $secret);
            $secret = null;
            if (!$GLOBALS['RED_P3E9B2_KEY_MATCHED']) {
                return RED_Addon_Adapter_Result::failure(
                    'synthetic_secret_refused'
                );
            }
            $mode = $GLOBALS['RED_P3E9B2_MODE'] ?? 'success';
            if ($mode === 'throw') {
                throw new RuntimeException('synthetic_handler_failed');
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
            return RED_Addon_Adapter_Result::success([
                'valid' => true,
                'contactTarget' => 'synthetic-checkout-package',
                'outcome' => 'checkout_contract_accepted',
                'checkoutSessionRef' =>
                    'cs_test_REDcmsP3E9BSyntheticSession01',
                'expiresAtEpoch' => $input['policy']['expiresAtEpoch'],
                'contractSha256' => $input['contractSha256'],
                'responseEvidenceSha256' => hash('sha256', 'response'),
                'resultSha256' => hash('sha256', 'result'),
                'responseBodyIncluded' => false,
                'responseHeadersIncluded' => false,
                'checkoutUrlIncluded' => false,
                'credentialIncluded' => false,
                'retryAuthorized' => false,
                'mutationAuthorized' => false,
                'networkAccess' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'checkoutCreation' => false,
                'payment' => false,
                'webhook' => false,
                'browserNavigation' => false,
                'orderMutation' => false,
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
    $migration = "CREATE TABLE RED_P3E9B2_Unused (\n"
        . "  RecordID bigint unsigned NOT NULL\n"
        . ") ENGINE=InnoDB;\n";
    file_put_contents($packageDirectory . '/addon.php', $entrypoint);
    file_put_contents(
        $packageDirectory . '/migrations/2026-08-18-p3e9b2-unused.sql',
        $migration
    );
    $packageId = 'redcms.store-lite-stripe-checkout';
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'P3E-9B2 synthetic Checkout fixture',
        'description' => 'Integrity-checked in-memory adapter fixture.',
        'version' => '0.1.5',
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
            'id' => '2026-08-18-p3e9b2-unused',
            'path' => 'migrations/2026-08-18-p3e9b2-unused.sql',
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
                'path' => 'migrations/2026-08-18-p3e9b2-unused.sql',
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
    if (!mkdir($storeDirectory, 0700, true)
        && !is_dir($storeDirectory)
    ) {
        throw new RuntimeException('Could not create Store Lite fixture.');
    }
    $storeEntrypoint = <<<'PHP'
<?php
return static function ($registry): void {
};
PHP;
    file_put_contents($storeDirectory . '/addon.php', $storeEntrypoint);
    $storeManifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.store-lite',
        'name' => 'Store Lite dependency fixture',
        'description' => 'P3E-9B2 dependency-only fixture.',
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

red_addon_checkout_synthetic_test_delete($temporaryRoot);

try {
    red_addon_checkout_synthetic_test_write_package($packageDirectory);
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][
        'redcms.store-lite-stripe-checkout'
    ] ?? [];
    red_addon_checkout_synthetic_test_assert(
        !empty($catalog['valid'])
            && !empty($package['valid'])
            && $GLOBALS['RED_P3E9B2_REGISTRAR_CALLS'] === 0,
        'exact adapter 0.1.5 fixture discovers without package execution: '
            . json_encode([
                'catalog' => $catalog['errors'] ?? [],
                'package' => $package['errors'] ?? [],
                'ids' => array_keys($catalog['packages'] ?? []),
                'catalogValid' => $catalog['valid'] ?? null,
                'packageValid' => $package['valid'] ?? null,
                'registrarCalls' => $GLOBALS['RED_P3E9B2_REGISTRAR_CALLS'],
            ])
    );

    $input = red_addon_checkout_synthetic_test_input();
    $plan = red_addon_checkout_synthetic_plan($package, $input);
    red_addon_checkout_synthetic_test_assert(
        !empty($plan['valid'])
            && !empty($plan['ready'])
            && ($plan['status'] ?? null) === 'ready'
            && ($plan['packageVersion'] ?? null) === '0.1.5'
            && ($plan['operation'] ?? null)
                === 'checkout.create-sandbox-synthetic'
            && red_addon_checkout_synthetic_sha256(
                $plan['manifestSha256'] ?? null
            )
            && red_addon_checkout_synthetic_sha256(
                $plan['inventorySha256'] ?? null
            )
            && red_addon_checkout_synthetic_sha256(
                $plan['inputSha256'] ?? null
            )
            && red_addon_checkout_synthetic_sha256(
                $plan['planSha256'] ?? null
            ),
        'dry plan binds exact package, operation, input, and integrity hashes'
    );
    red_addon_checkout_synthetic_test_assert(
        empty($plan['executionPerformed'])
            && empty($plan['adapterInvoked'])
            && empty($plan['networkAccess'])
            && empty($plan['providerContact'])
            && empty($plan['providerMutation'])
            && empty($plan['checkoutCreation'])
            && empty($plan['payment'])
            && empty($plan['retryAuthorized'])
            && $GLOBALS['RED_P3E9B2_REGISTRAR_CALLS'] === 0
            && $GLOBALS['RED_P3E9B2_HANDLER_CALLS'] === 0,
        'dry plan executes no registrar, handler, network, or mutation'
    );
    red_addon_checkout_synthetic_test_assert(
        $plan === red_addon_checkout_synthetic_plan($package, $input),
        'identical input produces an identical plan and hash'
    );

    foreach ([
        ['credentialMode', 'restricted_test_read'],
        ['providerMutation', false],
        ['checkoutCreation', false],
        ['automaticRetry', true],
    ] as [$field, $value]) {
        $changed = $input;
        $changed['profile'][$field] = $value;
        $refused = red_addon_checkout_synthetic_plan($package, $changed);
        red_addon_checkout_synthetic_test_assert(
            empty($refused['ready'])
                && ($refused['status'] ?? null) === 'contract_refused',
            'core refuses changed Checkout profile field ' . $field
        );
    }
    $changed = $input;
    $changed['checkout']['customerEmail'] = 'person@example.test';
    red_addon_checkout_synthetic_test_assert(
        empty(red_addon_checkout_synthetic_plan($package, $changed)['ready']),
        'customer identity is refused from the closed core input'
    );
    $changed = $input;
    $changed['policy']['expiresAtEpoch'] =
        $changed['policy']['createdAtEpoch'] + 1799;
    red_addon_checkout_synthetic_test_assert(
        empty(red_addon_checkout_synthetic_plan($package, $changed)['ready']),
        'expiry shorter than thirty minutes is refused'
    );
    $wrongVersion = $package;
    $wrongVersion['manifest']['version'] = '0.1.4';
    red_addon_checkout_synthetic_test_assert(
        empty(red_addon_checkout_synthetic_plan($wrongVersion, $input)['ready']),
        'historical adapter version cannot enter the new runner'
    );

    $legacySource = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_provider_contact_authorization_helpers.php'
    );
    red_addon_checkout_synthetic_test_assert(
        !str_contains($legacySource, "=== '0.1.5'")
            && !str_contains(
                $legacySource,
                "=== 'checkout.create-sandbox-synthetic'"
            ),
        'P3E-8 read-only authorization does not recognize the new profile'
    );

    $syntheticKey = 'rk_' . 'test_' . str_repeat('x', 32);
    $access = new RED_Addon_Runtime_Secret_Access(
        'redcms.store-lite-stripe-checkout',
        ['stripe.secret-key' => $syntheticKey]
    );
    $executed = red_addon_checkout_synthetic_execute(
        $package,
        $input,
        $access,
        $plan['planSha256']
    );
    red_addon_checkout_synthetic_test_assert(
        !empty($executed['valid'])
            && empty($executed['ready'])
            && ($executed['status'] ?? null)
                === 'checkout_contract_accepted'
            && !empty($executed['adapterInvoked'])
            && !empty($executed['executionPerformed'])
            && empty($executed['networkAccess'])
            && empty($executed['providerContact'])
            && empty($executed['providerMutation'])
            && empty($executed['checkoutCreation'])
            && empty($executed['payment'])
            && empty($executed['webhook'])
            && empty($executed['browserNavigation'])
            && empty($executed['orderMutation'])
            && empty($executed['retryAuthorized'])
            && empty($executed['clientDeployment'])
            && red_addon_checkout_synthetic_sha256(
                $executed['outcomeSha256'] ?? null
            ),
        'core invokes one contained synthetic operation and accepts bounded facts'
    );
    red_addon_checkout_synthetic_test_assert(
        $GLOBALS['RED_P3E9B2_REGISTRAR_CALLS'] === 1
            && $GLOBALS['RED_P3E9B2_HANDLER_CALLS'] === 1
            && $GLOBALS['RED_P3E9B2_KEY_MATCHED']
            && !str_contains(json_encode($executed), $syntheticKey)
            && !str_contains(
                json_encode($executed),
                'checkout.stripe.com'
            )
            && ($executed['boundedOutcome']['credentialIncluded'] ?? null)
                === false
            && ($executed['boundedOutcome']['checkoutUrlIncluded'] ?? null)
                === false,
        'exact scoped key reaches only the handler and never enters output'
    );

    $changedPlan = red_addon_checkout_synthetic_execute(
        $package,
        $input,
        $access,
        str_repeat('f', 64)
    );
    red_addon_checkout_synthetic_test_assert(
        ($changedPlan['status'] ?? null) === 'execution_changed'
            && $GLOBALS['RED_P3E9B2_HANDLER_CALLS'] === 1,
        'changed plan hash is refused before registrar and handler execution'
    );
    $unscoped = new RED_Addon_Runtime_Secret_Access(
        'redcms.store-lite-stripe-checkout',
        [
            'stripe.secret-key' => $syntheticKey,
            'stripe.webhook-secret' => 'whsec_' . str_repeat('y', 32),
        ]
    );
    $unscopedResult = red_addon_checkout_synthetic_execute(
        $package,
        $input,
        $unscoped,
        $plan['planSha256']
    );
    red_addon_checkout_synthetic_test_assert(
        ($unscopedResult['status'] ?? null) === 'execution_refused'
            && $GLOBALS['RED_P3E9B2_HANDLER_CALLS'] === 1,
        'secret access wider than one scoped key is refused before registration'
    );

    foreach (['malformed', 'throw', 'output'] as $mode) {
        $GLOBALS['RED_P3E9B2_MODE'] = $mode;
        $refused = red_addon_checkout_synthetic_execute(
            $package,
            $input,
            $access,
            $plan['planSha256']
        );
        red_addon_checkout_synthetic_test_assert(
            in_array($refused['status'] ?? null, [
                'package_execution_refused', 'package_outcome_refused',
            ], true)
                && empty($refused['executionPerformed'])
                && empty($refused['networkAccess'])
                && empty($refused['providerContact'])
                && empty($refused['checkoutCreation']),
            'handler ' . $mode . ' failure is contained without partial outcome'
        );
    }

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_sandbox_checkout_synthetic_execution_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST',
        '$_SERVER', 'PDO', 'mysqli', 'getenv(', 'putenv(', 'shell_exec(',
        'sleep(', 'usleep(',
    ] as $forbidden) {
        red_addon_checkout_synthetic_test_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from P3E-9B2 core source'
        );
    }
    red_addon_checkout_synthetic_test_assert(
        substr_count($source, "'checkout.create-sandbox-synthetic'") === 2
            && !str_contains($source, 'provider-contact.read-only-probe'),
        'core invokes only the synthetic operation and no read-only operation'
    );

    $syntheticKey = null;
    $access = null;
    $unscoped = null;
    red_addon_checkout_synthetic_test_delete($temporaryRoot);
    red_addon_checkout_synthetic_test_assert(
        !file_exists($temporaryRoot),
        'fixture cleanup removes the exact temporary package project'
    );

    echo 'Sandbox Checkout P3E-9B2 core self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_checkout_synthetic_test_delete($temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
