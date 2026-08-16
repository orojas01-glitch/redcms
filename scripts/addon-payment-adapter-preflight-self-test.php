<?php
/**
 * Dependency-free checks for the P3A-1 payment-adapter manifest profile.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_payment_adapter_preflight_helpers.php';
require_once $projectRoot . '/includes/addon_enable_preflight_helpers.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir() . '/redcms-payment-adapter-profile-' .
    bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$packageId = 'redcms.stripe-fixture';
$packageDirectory = $fixtureProject . '/addons/redcms/stripe-fixture';
$executionMarker = $temporaryRoot . '/addon-executed';

function red_addon_payment_adapter_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_payment_adapter_test_remove_tree($path)
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

function red_addon_payment_adapter_test_write_manifest($directory, array $manifest)
{
    file_put_contents(
        $directory . '/addon.json',
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n"
    );
}

function red_addon_payment_adapter_test_fixture(
    $packageId,
    $entrypoint,
    $migration
) {
    return [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Stripe adapter profile fixture',
        'description' => 'Non-executing payment-adapter profile fixture.',
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
            'id' => '2026-08-15-stripe-fixture',
            'path' => 'migrations/2026-08-15-stripe-fixture.sql',
            'sha256' => hash('sha256', $migration),
        ]],
        'routes' => [[
            'id' => $packageId . '/provider-events',
            'scope' => 'public',
            'path' => '/addons/redcms/stripe-fixture/provider-events',
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
                'path' => 'migrations/2026-08-15-stripe-fixture.sql',
                'sha256' => hash('sha256', $migration),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
}

try {
    if (!mkdir($packageDirectory . '/migrations', 0700, true)
        && !is_dir($packageDirectory . '/migrations')
    ) {
        throw new RuntimeException('Could not create payment-adapter fixture.');
    }
    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) .
        ", 'executed');\nreturn static function (): void {};\n";
    $migration = "CREATE TABLE RED_Addon_Stripe_Fixture_Attempts (\n" .
        "  RecordID bigint unsigned NOT NULL AUTO_INCREMENT,\n" .
        "  PRIMARY KEY (RecordID)\n" .
        ") ENGINE=InnoDB;\n";
    file_put_contents($packageDirectory . '/addon.php', $entrypoint);
    file_put_contents(
        $packageDirectory . '/migrations/2026-08-15-stripe-fixture.sql',
        $migration
    );
    $manifest = red_addon_payment_adapter_test_fixture(
        $packageId,
        $entrypoint,
        $migration
    );
    red_addon_payment_adapter_test_write_manifest($packageDirectory, $manifest);

    $validated = red_addon_validate_manifest(
        $packageId,
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    red_addon_payment_adapter_test_assert(
        !empty($validated['valid']) && !file_exists($executionMarker),
        'server-signature manifest validation remains non-executing'
    );

    $profile = red_addon_payment_adapter_profile($validated['manifest']);
    red_addon_payment_adapter_test_assert(
        red_addon_payment_adapter_profile_is_valid($profile)
            && !empty($profile['contractReady'])
            && !$profile['activationSupported']
            && !$profile['stateMutation']
            && !$profile['runtimeLoad']
            && !$profile['packageExecution']
            && !$profile['secretResolution']
            && !$profile['networkAccess']
            && !$profile['routeExposure'],
        'closed adapter surface produces value-free non-activating evidence'
    );
    red_addon_payment_adapter_test_assert(
        $profile['packageId'] === $packageId
            && $profile['adapter'] === $packageId . '/checkout'
            && $profile['dependencyPackageId'] === 'redcms.store-lite'
            && $profile['serverEventRoute']
                === $packageId . '/provider-events'
            && $profile['serverEventPath']
                === '/addons/redcms/stripe-fixture/provider-events'
            && $profile['migrationCount'] === 1
            && $profile['ordinarySettingCount'] === 1
            && $profile['secretSettingCount'] === 2
            && $profile['outboundHost'] === 'api.stripe.com',
        'profile evidence binds the exact adapter, dependency, route, settings, migration, and host'
    );
    red_addon_payment_adapter_test_assert(
        array_column($profile['blockers'], 'code') === [
            'atomic_payment_adapter_enablement_required',
            'database_bound_adapter_preflight_required',
            'registrar_validation_required',
            'server_event_ingress_required',
        ],
        'contract evidence retains every later execution and lifecycle blocker'
    );

    $repeat = red_addon_payment_adapter_profile($validated['manifest']);
    red_addon_payment_adapter_test_assert(
        hash_equals($profile['contractSha256'], $repeat['contractSha256'])
            && !file_exists($executionMarker),
        'unchanged manifest evidence is deterministic and never includes package PHP'
    );
    $tampered = $profile;
    $tampered['serverEventPath'] .= '-changed';
    red_addon_payment_adapter_test_assert(
        !red_addon_payment_adapter_profile_is_valid($tampered),
        'changed profile evidence fails its deterministic fingerprint'
    );

    red_addon_payment_adapter_test_assert(
        red_addon_public_mutation_static_route(
            $manifest['routes'][0],
            $packageId
        ) === null,
        'server-signature route cannot become a browser public-mutation route'
    );
    $baseProfile = red_addon_enable_preflight_activation_profile($manifest);
    red_addon_payment_adapter_test_assert(
        empty($baseProfile['eligible'])
            && ($baseProfile['id'] ?? '') === 'expanded_contract_required'
            && in_array(
                'supported_activation_profile_required',
                array_column($baseProfile['blockers'], 'code'),
                true
            ),
        'existing activation profiles still reject the payment adapter'
    );

    $mutations = [];
    $mutations['package type'] = static function (array $value): array {
        $value['type'] = 'service';
        return $value;
    };
    $mutations['capability surface'] = static function (array $value): array {
        $value['provides']['services'][] = 'commerce.payment';
        return $value;
    };
    $mutations['adapter multiplicity'] = static function (array $value): array {
        $value['provides']['adapters'][] = $value['id'] . '/other';
        return $value;
    };
    $mutations['dependency identity'] = static function (array $value): array {
        $value['dependencies']['required'][0]['id'] = 'redcms.other-store';
        return $value;
    };
    $mutations['optional dependency'] = static function (array $value): array {
        $value['dependencies']['optional'][] = [
            'id' => 'redcms.other-adapter',
            'version' => '>=0.1 <1.0',
        ];
        return $value;
    };
    $mutations['secret count'] = static function (array $value): array {
        array_pop($value['settings']);
        return $value;
    };
    $mutations['ordinary setting default'] = static function (array $value): array {
        $value['settings'][0]['default'] = 'https://example.test';
        return $value;
    };
    $mutations['migration absence'] = static function (array $value): array {
        $value['migrations'] = [];
        return $value;
    };
    $mutations['browser route'] = static function (array $value): array {
        $value['routes'][0]['authentication'] = 'public';
        $value['routes'][0]['csrf'] = 'required';
        return $value;
    };
    $mutations['multi-method route'] = static function (array $value): array {
        $value['routes'][0]['methods'] = ['GET', 'POST'];
        return $value;
    };
    $mutations['public mutation'] = static function (array $value): array {
        $value['publicMutationContracts'] = [['forged' => true]];
        return $value;
    };
    $mutations['wrong outbound host'] = static function (array $value): array {
        $value['outboundHosts'] = ['api.example.test'];
        return $value;
    };
    $mutations['permission surface'] = static function (array $value): array {
        $value['permissions'] = ['payments.manage'];
        return $value;
    };
    $mutations['asset surface'] = static function (array $value): array {
        $value['assets']['public'] = [[
            'path' => 'asset.css',
            'sha256' => str_repeat('a', 64),
        ]];
        return $value;
    };
    foreach ($mutations as $label => $mutate) {
        red_addon_payment_adapter_test_assert(
            empty(red_addon_payment_adapter_profile($mutate($manifest))['valid']),
            $label . ' remains outside the closed payment-adapter profile'
        );
    }

    $invalidServerRoute = $manifest;
    $invalidServerRoute['routes'][0]['csrf'] = 'required';
    red_addon_payment_adapter_test_write_manifest(
        $packageDirectory,
        $invalidServerRoute
    );
    $invalidServerRouteResult = red_addon_validate_manifest(
        $packageId,
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    red_addon_payment_adapter_test_assert(
        empty($invalidServerRouteResult['valid'])
            && str_contains(
                implode("\n", $invalidServerRouteResult['errors'] ?? []),
                'server-signature routes require one public POST with CSRF not-applicable'
            ),
        'server-signature route refuses CSRF-required browser semantics'
    );

    $invalidBrowserRoute = $manifest;
    $invalidBrowserRoute['routes'][0]['authentication'] = 'public';
    red_addon_payment_adapter_test_write_manifest(
        $packageDirectory,
        $invalidBrowserRoute
    );
    $invalidBrowserRouteResult = red_addon_validate_manifest(
        $packageId,
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    red_addon_payment_adapter_test_assert(
        empty($invalidBrowserRouteResult['valid']),
        'ordinary public POST still requires CSRF and cannot imitate server-signature authentication'
    );

    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_payment_adapter_preflight_helpers.php'
    );
    red_addon_payment_adapter_test_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\bgetenv\s*\(|\bmysqli_|\bcurl_|\bfsockopen\s*\(|\bstream_socket_client\s*\()/i',
            $helperSource
        ) !== 1,
        'profile helper has no request, environment, database, secret-value, or network path'
    );
    red_addon_payment_adapter_test_assert(
        !file_exists($executionMarker),
        'all refusal and schema checks leave package PHP unexecuted'
    );

    red_addon_payment_adapter_test_remove_tree($temporaryRoot);
    echo 'Payment adapter P3A-1 profile self-test passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_payment_adapter_test_remove_tree($temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
