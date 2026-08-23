<?php
/**
 * Dependency-free checks for P3A-3 registration-only adapter validation.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_payment_adapter_registrar_helpers.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir() . '/redcms-payment-adapter-registrar-' .
    bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$packageId = 'redcms.stripe-registrar-fixture';
$packageDirectory =
    $fixtureProject . '/addons/redcms/stripe-registrar-fixture';
$adapterHandlerMarker = $temporaryRoot . '/adapter-handler-invoked';
$routeHandlerMarker = $temporaryRoot . '/route-handler-invoked';
$invalidPlanMarker = $temporaryRoot . '/invalid-plan-package-loaded';
$wompiPackageId = 'redcms.store-lite-wompi';
$wompiPackageDirectory =
    $fixtureProject . '/addons/redcms/store-lite-wompi';
$wompiAdapterMarker = $temporaryRoot . '/wompi-adapter-invoked';
$wompiRouteMarker = $temporaryRoot . '/wompi-route-invoked';

function red_addon_payment_adapter_registrar_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_payment_adapter_registrar_test_remove_tree($path)
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

function red_addon_payment_adapter_registrar_test_manifest(
    $packageId,
    $entrypoint,
    $migration
) {
    return [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Stripe registrar fixture',
        'description' => 'Registration-only P3A-3 fixture.',
        'version' => '0.1.0',
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
                'version' => '>=0.1 <1.0',
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
            'id' => '2026-08-15-stripe-registrar-fixture',
            'path' => 'migrations/2026-08-15-stripe-registrar-fixture.sql',
            'sha256' => hash('sha256', $migration),
        ]],
        'routes' => [[
            'id' => $packageId . '/provider-events',
            'scope' => 'public',
            'path' =>
                '/addons/redcms/stripe-registrar-fixture/provider-events',
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
                'path' =>
                    'migrations/2026-08-15-stripe-registrar-fixture.sql',
                'sha256' => hash('sha256', $migration),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
}

function red_addon_payment_adapter_registrar_test_write_package(
    $directory,
    $packageId,
    $entrypoint,
    $migration
) {
    if (!is_dir($directory . '/migrations')
        && !mkdir($directory . '/migrations', 0700, true)
        && !is_dir($directory . '/migrations')
    ) {
        throw new RuntimeException('Could not create registrar fixture.');
    }
    file_put_contents($directory . '/addon.php', $entrypoint);
    file_put_contents(
        $directory .
            '/migrations/2026-08-15-stripe-registrar-fixture.sql',
        $migration
    );
    $manifest = red_addon_payment_adapter_registrar_test_manifest(
        $packageId,
        $entrypoint,
        $migration
    );
    file_put_contents(
        $directory . '/addon.json',
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n"
    );
}

function red_addon_payment_adapter_registrar_test_package(
    $packageId,
    $fixtureProject
) {
    return red_addon_validate_manifest(
        $packageId,
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
}

function red_addon_payment_adapter_registrar_test_write_wompi_package(
    $directory,
    $adapterMarker,
    $routeMarker
) {
    if (!is_dir($directory . '/migrations')
        && !mkdir($directory . '/migrations', 0700, true)
        && !is_dir($directory . '/migrations')
    ) {
        throw new RuntimeException('Could not create Wompi registrar fixture.');
    }
    $entrypoint = "<?php\nreturn static function (\$registry): void {\n" .
        "    \$registry->registerAdapter(" .
        var_export('redcms.store-lite-wompi/checkout', true) .
        ", static function (): void { file_put_contents(" .
        var_export($adapterMarker, true) . ", 'invoked'); });\n" .
        "    \$registry->registerRoute(" .
        var_export('redcms.store-lite-wompi/provider-events', true) .
        ", static function (): void { file_put_contents(" .
        var_export($routeMarker, true) . ", 'invoked'); });\n" .
        "};\n";
    $attemptsPath = 'migrations/2026-08-23-create-payment-attempts.sql';
    $eventsPath = 'migrations/2026-08-23-create-event-receipts.sql';
    $attemptsSql = "CREATE TABLE RED_Addon_Wompi_Registrar_Attempts (\n" .
        "  RecordID bigint unsigned NOT NULL AUTO_INCREMENT,\n" .
        "  PRIMARY KEY (RecordID)\n" .
        ") ENGINE=InnoDB;\n";
    $eventsSql = "CREATE TABLE RED_Addon_Wompi_Registrar_Events (\n" .
        "  RecordID bigint unsigned NOT NULL AUTO_INCREMENT,\n" .
        "  PRIMARY KEY (RecordID)\n" .
        ") ENGINE=InnoDB;\n";
    $files = [
        'addon.php' => $entrypoint,
        $attemptsPath => $attemptsSql,
        $eventsPath => $eventsSql,
    ];
    foreach ($files as $path => $contents) {
        file_put_contents($directory . '/' . $path, $contents);
    }
    $integrityFiles = [];
    foreach ($files as $path => $contents) {
        $integrityFiles[] = [
            'path' => $path,
            'sha256' => hash('sha256', $contents),
        ];
    }
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.store-lite-wompi',
        'name' => 'Wompi registrar fixture',
        'description' => 'Registration-only C3B Wompi fixture.',
        'version' => '0.1.0',
        'type' => 'adapter',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => ['redcms.store-lite-wompi/checkout'],
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
            'key' => 'wompi.public-key',
            'label' => 'Wompi public key',
            'type' => 'text',
            'secret' => false,
            'default' => null,
        ], [
            'key' => 'wompi.private-key',
            'label' => 'Wompi private key reference',
            'type' => 'secret-reference',
            'secret' => true,
        ], [
            'key' => 'wompi.integrity-key',
            'label' => 'Wompi integrity key reference',
            'type' => 'secret-reference',
            'secret' => true,
        ], [
            'key' => 'wompi.event-secret',
            'label' => 'Wompi event secret reference',
            'type' => 'secret-reference',
            'secret' => true,
        ]],
        'migrations' => [[
            'id' => '2026-08-23-wompi-payment-attempts',
            'path' => $attemptsPath,
            'sha256' => hash('sha256', $attemptsSql),
        ], [
            'id' => '2026-08-23-wompi-event-receipts',
            'path' => $eventsPath,
            'sha256' => hash('sha256', $eventsSql),
        ]],
        'routes' => [[
            'id' => 'redcms.store-lite-wompi/provider-events',
            'scope' => 'public',
            'path' => '/addons/redcms/store-lite-wompi/provider-events',
            'methods' => ['POST'],
            'authentication' => 'server-signature',
            'csrf' => 'not-applicable',
        ]],
        'publicMutationContracts' => [],
        'jobs' => [],
        'outboundHosts' => ['sandbox.wompi.co'],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => $integrityFiles,
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
    file_put_contents(
        $directory . '/addon.json',
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n"
    );
}

function red_addon_payment_adapter_registrar_test_database_plan(array $package)
{
    $profile = red_addon_payment_adapter_profile($package['manifest']);
    $plan = red_addon_payment_adapter_database_result($package['id']);
    $plan['valid'] = true;
    $plan['databaseEvidenceReady'] = true;
    $plan['version'] = $package['manifest']['version'];
    $plan['currentState'] = 'installed_disabled';
    $plan['databaseSha256'] = hash('sha256', 'disposable-database');
    $plan['contractSha256'] = $profile['contractSha256'];
    $plan['baseEnablementSha256'] = hash('sha256', 'base-enablement');
    $plan['dependencyEvidenceSha256'] = hash('sha256', 'dependency');
    $plan['migrationEvidenceSha256'] = hash('sha256', 'migration');
    $plan['tableEvidenceSha256'] = hash('sha256', 'table');
    $plan['dependencyCount'] = 1;
    $plan['migrationCount'] = $profile['migrationCount'];
    $plan['tableCount'] = $profile['migrationCount'];
    $plan['innoDbTableCount'] = $profile['migrationCount'];
    foreach ([
        'adapterContract', 'authorization', 'trust', 'registry',
        'dependencies', 'capabilityNamespace', 'routeNamespace',
        'migrations', 'packageTables',
    ] as $gate) {
        $plan['gates'][$gate] = 'passed';
    }
    $plan['blockers'] = [
        ['code' => 'atomic_payment_adapter_enablement_required'],
        ['code' => 'registrar_validation_required'],
        ['code' => 'server_event_ingress_required'],
    ];
    $plan['planSha256'] =
        red_addon_payment_adapter_database_fingerprint($plan);
    return $plan;
}

// P3A-4 reuses only the package/database fixture builders above.
if (!defined('RED_ADDON_PAYMENT_ADAPTER_REGISTRAR_FIXTURE_ONLY')) {
try {
    $migration =
        "CREATE TABLE RED_Addon_Stripe_Registrar_Fixture_Attempts (\n" .
        "  RecordID bigint unsigned NOT NULL AUTO_INCREMENT,\n" .
        "  PRIMARY KEY (RecordID)\n" .
        ") ENGINE=InnoDB;\n";
    $entrypoint = "<?php\nreturn static function (\$registry): void {\n" .
        "    \$registry->registerAdapter(" .
        var_export($packageId . '/checkout', true) .
        ", static function (): void { file_put_contents(" .
        var_export($adapterHandlerMarker, true) . ", 'invoked'); });\n" .
        "    \$registry->registerRoute(" .
        var_export($packageId . '/provider-events', true) .
        ", static function (): void { file_put_contents(" .
        var_export($routeHandlerMarker, true) . ", 'invoked'); });\n" .
        "};\n";
    red_addon_payment_adapter_registrar_test_write_package(
        $packageDirectory,
        $packageId,
        $entrypoint,
        $migration
    );
    $package = red_addon_payment_adapter_registrar_test_package(
        $packageId,
        $fixtureProject
    );
    red_addon_payment_adapter_registrar_test_assert(
        !empty($package['valid'])
            && !file_exists($adapterHandlerMarker)
            && !file_exists($routeHandlerMarker),
        'trusted package validation remains non-executing'
    );

    $databasePlan =
        red_addon_payment_adapter_registrar_test_database_plan($package);
    red_addon_payment_adapter_registrar_test_assert(
        red_addon_payment_adapter_database_preflight_is_valid($databasePlan)
            && !empty($databasePlan['databaseEvidenceReady']),
        'fixture supplies exact ready P3A-2 evidence'
    );

    $result = red_addon_payment_adapter_validate_registrar(
        $package,
        $databasePlan
    );
    red_addon_payment_adapter_registrar_test_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid($result)
            && !empty($result['registrarEvidenceReady'])
            && !$result['enableReady']
            && !$result['activationSupported']
            && !$result['stateMutation']
            && !$result['runtimePublication']
            && $result['packageExecutionAttempted']
            && $result['registrarExecutionCompleted']
            && !$result['handlerInvocation']
            && !$result['secretResolution']
            && !$result['networkAccess']
            && !$result['routeExposure'],
        'registration-only evidence executes the registrar but publishes and invokes nothing'
    );
    red_addon_payment_adapter_registrar_test_assert(
        $result['packageId'] === $packageId
            && $result['version'] === '0.1.0'
            && $result['adapter'] === $packageId . '/checkout'
            && $result['serverEventRoute']
                === $packageId . '/provider-events'
            && $result['registrationCount'] === 2
            && red_addon_valid_sha256($result['registrationSha256'])
            && red_addon_valid_sha256($result['manifestSha256'])
            && red_addon_valid_sha256($result['inventorySha256']),
        'evidence contains only exact identities, counts, and hashes'
    );
    red_addon_payment_adapter_registrar_test_assert(
        !file_exists($adapterHandlerMarker)
            && !file_exists($routeHandlerMarker)
            && array_column($result['blockers'], 'code') === [
                'atomic_payment_adapter_enablement_required',
                'server_event_ingress_required',
            ],
        'adapter and route callbacks remain uninvoked and later gates remain blocked'
    );

    $repeat = red_addon_payment_adapter_validate_registrar(
        $package,
        $databasePlan
    );
    red_addon_payment_adapter_registrar_test_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid($repeat)
            && hash_equals($result['planSha256'], $repeat['planSha256'])
            && !file_exists($adapterHandlerMarker)
            && !file_exists($routeHandlerMarker),
        'unchanged registrar evidence is deterministic without handler invocation'
    );

    $tamperedPlan = $databasePlan;
    $tamperedPlan['databaseSha256'] = hash('sha256', 'forged-database');
    $guardedEntrypoint = "<?php\nfile_put_contents(" .
        var_export($invalidPlanMarker, true) .
        ", 'loaded');\nreturn static function (): void {};\n";
    red_addon_payment_adapter_registrar_test_write_package(
        $packageDirectory,
        $packageId,
        $guardedEntrypoint,
        $migration
    );
    $guardedPackage = red_addon_payment_adapter_registrar_test_package(
        $packageId,
        $fixtureProject
    );
    $refused = red_addon_payment_adapter_validate_registrar(
        $guardedPackage,
        $tamperedPlan
    );
    red_addon_payment_adapter_registrar_test_assert(
        empty($refused['valid'])
            && !$refused['packageExecutionAttempted']
            && in_array(
                'database_payment_adapter_evidence_invalid',
                $refused['errors'],
                true
            )
            && !file_exists($invalidPlanMarker),
        'invalid database evidence fails before package PHP is loaded'
    );

    $failureEntrypoints = [
        'entrypoint output' => "<?php\necho 'forbidden';\n" .
            "return static function (): void {};\n",
        'registrar output' => "<?php\nreturn static function (\$registry): void {\n" .
            "    echo 'forbidden';\n" .
            "    \$registry->registerAdapter(" .
            var_export($packageId . '/checkout', true) .
            ", static function (): void {});\n" .
            "    \$registry->registerRoute(" .
            var_export($packageId . '/provider-events', true) .
            ", static function (): void {});\n};\n",
        'missing route registration' =>
            "<?php\nreturn static function (\$registry): void {\n" .
            "    \$registry->registerAdapter(" .
            var_export($packageId . '/checkout', true) .
            ", static function (): void {});\n};\n",
        'duplicate adapter registration' =>
            "<?php\nreturn static function (\$registry): void {\n" .
            "    \$registry->registerAdapter(" .
            var_export($packageId . '/checkout', true) .
            ", static function (): void {});\n" .
            "    \$registry->registerAdapter(" .
            var_export($packageId . '/checkout', true) .
            ", static function (): void {});\n};\n",
    ];
    foreach ($failureEntrypoints as $label => $failureEntrypoint) {
        red_addon_payment_adapter_registrar_test_write_package(
            $packageDirectory,
            $packageId,
            $failureEntrypoint,
            $migration
        );
        $failurePackage = red_addon_payment_adapter_registrar_test_package(
            $packageId,
            $fixtureProject
        );
        $failurePlan =
            red_addon_payment_adapter_registrar_test_database_plan(
                $failurePackage
            );
        ob_start();
        $failure = red_addon_payment_adapter_validate_registrar(
            $failurePackage,
            $failurePlan
        );
        $leakedOutput = ob_get_clean();
        red_addon_payment_adapter_registrar_test_assert(
            empty($failure['valid'])
                && $failure['packageExecutionAttempted']
                && !$failure['registrarExecutionCompleted']
                && in_array(
                    'payment_adapter_registrar_execution_failed',
                    $failure['errors'],
                    true
                )
                && $leakedOutput === '',
            $label . ' fails closed without leaking output'
        );
    }

    $tampered = $result;
    $tampered['registrationCount'] = 3;
    red_addon_payment_adapter_registrar_test_assert(
        !red_addon_payment_adapter_registrar_preflight_is_valid($tampered),
        'tampered registration evidence fails its deterministic contract'
    );

    red_addon_payment_adapter_registrar_test_write_wompi_package(
        $wompiPackageDirectory,
        $wompiAdapterMarker,
        $wompiRouteMarker
    );
    $wompiPackage = red_addon_payment_adapter_registrar_test_package(
        $wompiPackageId,
        $fixtureProject
    );
    red_addon_payment_adapter_registrar_test_assert(
        !empty($wompiPackage['valid']),
        'exact Wompi registrar fixture passes generic package trust'
    );
    $wompiDatabasePlan =
        red_addon_payment_adapter_registrar_test_database_plan(
            $wompiPackage
        );
    red_addon_payment_adapter_registrar_test_assert(
        red_addon_payment_adapter_database_preflight_is_valid(
            $wompiDatabasePlan
        )
            && $wompiDatabasePlan['migrationCount'] === 2,
        'Wompi fixture supplies exact two-migration database evidence'
    );
    $wompiResult = red_addon_payment_adapter_validate_registrar(
        $wompiPackage,
        $wompiDatabasePlan
    );
    red_addon_payment_adapter_registrar_test_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid($wompiResult)
            && $wompiResult['profileId']
                === 'store_lite_wompi_adapter_v1'
            && $wompiResult['adapter']
                === 'redcms.store-lite-wompi/checkout'
            && $wompiResult['serverEventRoute']
                === 'redcms.store-lite-wompi/provider-events'
            && $wompiResult['registrationCount'] === 2,
        'Wompi registrar evidence binds the exact closed profile and ids'
    );
    red_addon_payment_adapter_registrar_test_assert(
        !file_exists($wompiAdapterMarker)
            && !file_exists($wompiRouteMarker)
            && !$wompiResult['handlerInvocation']
            && !$wompiResult['runtimePublication']
            && !$wompiResult['networkAccess']
            && !$wompiResult['routeExposure'],
        'Wompi handlers remain uninvoked, unpublished, and offline'
    );
    $wrongWompiProfile = $wompiResult;
    $wrongWompiProfile['profileId'] =
        'store_lite_stripe_checkout_adapter_v1';
    $wrongWompiProfile['planSha256'] =
        red_addon_payment_adapter_registrar_fingerprint(
            $wrongWompiProfile
        );
    red_addon_payment_adapter_registrar_test_assert(
        !red_addon_payment_adapter_registrar_preflight_is_valid(
            $wrongWompiProfile
        ),
        'Wompi package evidence cannot be relabeled as the Stripe profile'
    );
    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_payment_adapter_registrar_helpers.php'
    );
    red_addon_payment_adapter_registrar_test_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\bgetenv\s*\(|\bcurl_|\bfsockopen\s*\(|\bstream_socket_client\s*\(|->handler\s*\(|RED_Addon_Settings|red_addon_(?:runtime_)?secret)/i',
            $helperSource
        ) !== 1,
        'registrar validator has no request, secret, network, setting, or handler-invocation path'
    );

    red_addon_payment_adapter_registrar_test_remove_tree($temporaryRoot);
    echo 'Payment adapter P3A-3 registrar self-test passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_payment_adapter_registrar_test_remove_tree($temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
}

?>
