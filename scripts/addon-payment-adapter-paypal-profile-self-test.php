<?php
/**
 * Dependency-free checks for the exact Store Lite PayPal adapter profile.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_payment_adapter_preflight_helpers.php';

$assertions = 0;
$externalPackageRoot = null;
foreach (array_slice($argv, 1) as $argument) {
    if (!str_starts_with($argument, '--package-root=')) {
        fwrite(STDERR, "Unknown argument.\n");
        exit(64);
    }
    $externalPackageRoot = substr($argument, strlen('--package-root='));
}

function red_paypal_profile_assert($condition, $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_paypal_profile_manifest(): array
{
    return [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.store-lite-paypal',
        'name' => 'RED-CMS Store Lite PayPal',
        'description' => 'Closed PayPal payment-adapter fixture.',
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
            'adapters' => ['redcms.store-lite-paypal/checkout'],
        ],
        'dependencies' => [
            'required' => [[
                'id' => 'redcms.store-lite',
                'version' => '>=0.1.50 <1.0',
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
            'key' => 'paypal.webhook-id',
            'label' => 'PayPal webhook ID',
            'type' => 'text',
            'secret' => false,
            'default' => null,
        ], [
            'key' => 'paypal.client-id',
            'label' => 'PayPal client ID reference',
            'type' => 'secret-reference',
            'secret' => true,
        ], [
            'key' => 'paypal.client-secret',
            'label' => 'PayPal client secret reference',
            'type' => 'secret-reference',
            'secret' => true,
        ]],
        'migrations' => [[
            'id' => '2026-09-01-paypal-order-attempts',
            'path' => 'migrations/2026-09-01-create-order-attempts.sql',
            'sha256' => str_repeat('1', 64),
        ], [
            'id' => '2026-09-01-paypal-event-receipts',
            'path' => 'migrations/2026-09-01-create-event-receipts.sql',
            'sha256' => str_repeat('2', 64),
        ]],
        'routes' => [[
            'id' => 'redcms.store-lite-paypal/provider-events',
            'scope' => 'public',
            'path' => '/addons/redcms/store-lite-paypal/provider-events',
            'methods' => ['POST'],
            'authentication' => 'server-signature',
            'csrf' => 'not-applicable',
        ]],
        'publicMutationContracts' => [],
        'jobs' => [],
        'outboundHosts' => ['api-m.sandbox.paypal.com'],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => str_repeat('3', 64),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
}

try {
    $manifest = red_paypal_profile_manifest();
    $profile = red_addon_payment_adapter_profile($manifest);
    red_paypal_profile_assert(
        red_addon_payment_adapter_profile_is_valid($profile)
            && $profile['valid']
            && $profile['contractReady'],
        'exact PayPal manifest produces one valid closed profile'
    );
    red_paypal_profile_assert(
        $profile['profileId'] === 'store_lite_paypal_adapter_v1'
            && $profile['packageId'] === 'redcms.store-lite-paypal'
            && $profile['adapter'] === 'redcms.store-lite-paypal/checkout'
            && $profile['dependencyPackageId'] === 'redcms.store-lite',
        'profile binds exact identity, adapter, and Store Lite dependency'
    );
    red_paypal_profile_assert(
        $profile['serverEventRoute']
            === 'redcms.store-lite-paypal/provider-events'
            && $profile['serverEventPath']
                === '/addons/redcms/store-lite-paypal/provider-events'
            && $profile['outboundHost'] === 'api-m.sandbox.paypal.com',
        'profile binds exact route and Sandbox-only API host'
    );
    red_paypal_profile_assert(
        $profile['migrationCount'] === 2
            && $profile['ordinarySettingCount'] === 2
            && $profile['secretSettingCount'] === 2
            && $profile['ordinarySettingKeys'] === [
                'checkout.return-origin',
                'paypal.webhook-id',
            ]
            && $profile['secretSettingKeys'] === [
                'paypal.client-id',
                'paypal.client-secret',
            ],
        'profile signs exact migration and setting-key evidence'
    );
    red_paypal_profile_assert(
        !$profile['activationSupported']
            && !$profile['stateMutation']
            && !$profile['runtimeLoad']
            && !$profile['packageExecution']
            && !$profile['secretResolution']
            && !$profile['networkAccess']
            && !$profile['routeExposure'],
        'profile remains data-only and non-activating'
    );
    red_paypal_profile_assert(
        array_column($profile['blockers'], 'code') === [
            'atomic_payment_adapter_enablement_required',
            'database_bound_adapter_preflight_required',
            'registrar_validation_required',
            'server_event_ingress_required',
        ],
        'every later database, registrar, ingress, and enablement gate remains'
    );
    $repeat = red_addon_payment_adapter_profile($manifest);
    red_paypal_profile_assert(
        hash_equals($profile['contractSha256'], $repeat['contractSha256']),
        'unchanged PayPal profile fingerprint is deterministic'
    );
    red_paypal_profile_assert(
        red_addon_payment_adapter_profile_result('redcms.store-lite-paypal')
            ['profileId'] === 'store_lite_paypal_adapter_v1'
            && red_addon_payment_adapter_profile_result(
                'redcms.store-lite-wompi'
            )['profileId'] === 'store_lite_wompi_adapter_v1'
            && red_addon_payment_adapter_profile_result(
                'redcms.stripe-fixture'
            )['profileId'] === 'store_lite_stripe_checkout_adapter_v1',
        'PayPal selection preserves Wompi and Stripe profile families'
    );
    $tamperedProfile = $profile;
    $tamperedProfile['ordinarySettingKeys'][0] = 'checkout.other-origin';
    red_paypal_profile_assert(
        !red_addon_payment_adapter_profile_is_valid($tamperedProfile),
        'changed normalized setting evidence fails profile verification'
    );
    red_paypal_profile_assert(
        !red_addon_payment_adapter_profile_is_valid(null),
        'non-array profile input fails closed'
    );

    $mutations = [];
    $mutations['package id'] = static function (array $value): array {
        $value['id'] = 'redcms.store-lite-paypal-other';
        $value['provides']['adapters'] = [$value['id'] . '/checkout'];
        $value['routes'][0]['id'] = $value['id'] . '/provider-events';
        $value['routes'][0]['path']
            = '/addons/redcms/store-lite-paypal-other/provider-events';
        return $value;
    };
    $mutations['adapter id'] = static function (array $value): array {
        $value['provides']['adapters'][0] = $value['id'] . '/other';
        return $value;
    };
    $mutations['dependency version'] = static function (array $value): array {
        $value['dependencies']['required'][0]['version'] = '>=0.1.35 <1.0';
        return $value;
    };
    $mutations['ordinary setting'] = static function (array $value): array {
        $value['settings'][0]['key'] = 'checkout.other-origin';
        return $value;
    };
    $mutations['secret removed'] = static function (array $value): array {
        array_pop($value['settings']);
        return $value;
    };
    $mutations['secret renamed'] = static function (array $value): array {
        $value['settings'][2]['key'] = 'paypal.other-secret';
        return $value;
    };
    $mutations['secret default'] = static function (array $value): array {
        $value['settings'][2]['default'] = null;
        return $value;
    };
    $mutations['migration id'] = static function (array $value): array {
        $value['migrations'][0]['id'] = '2026-09-01-other-attempts';
        return $value;
    };
    $mutations['migration path'] = static function (array $value): array {
        $value['migrations'][1]['path'] = 'migrations/other.sql';
        return $value;
    };
    $mutations['migration order'] = static function (array $value): array {
        $value['migrations'] = array_reverse($value['migrations']);
        return $value;
    };
    $mutations['route id'] = static function (array $value): array {
        $value['routes'][0]['id'] = $value['id'] . '/other';
        return $value;
    };
    $mutations['route path'] = static function (array $value): array {
        $value['routes'][0]['path']
            = '/addons/redcms/store-lite-paypal/other';
        return $value;
    };
    $mutations['route method'] = static function (array $value): array {
        $value['routes'][0]['methods'] = ['GET'];
        return $value;
    };
    $mutations['production host'] = static function (array $value): array {
        $value['outboundHosts'] = ['api-m.paypal.com'];
        return $value;
    };
    $mutations['permission surface'] = static function (array $value): array {
        $value['permissions'] = ['payments.manage'];
        return $value;
    };
    $mutations['public mutation'] = static function (array $value): array {
        $value['publicMutationContracts'] = [['forged' => true]];
        return $value;
    };
    foreach ($mutations as $name => $mutate) {
        red_paypal_profile_assert(
            !red_addon_payment_adapter_profile($mutate($manifest))['valid'],
            $name . ' remains outside the exact PayPal profile'
        );
    }

    $source = (string) file_get_contents(
        $projectRoot . '/includes/addon_payment_adapter_preflight_helpers.php'
    );
    red_paypal_profile_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\bgetenv\s*\(|\bmysqli_|\bcurl_|'
                . '\bfsockopen\s*\(|\bstream_socket_client\s*\()/i',
            $source
        ) !== 1,
        'profile helper has no request, environment, database, or network path'
    );

    if ($externalPackageRoot !== null) {
        $resolved = realpath($externalPackageRoot);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException('External package root is invalid.');
        }
        $externalManifest = json_decode(
            (string) file_get_contents($resolved . '/addon.json'),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
        red_paypal_profile_assert(
            is_array($externalManifest)
                && ($externalManifest['id'] ?? null)
                    === 'redcms.store-lite-paypal'
                && ($externalManifest['version'] ?? null) === '0.2.0',
            'external package identity and version are exact'
        );
        red_paypal_profile_assert(
            red_addon_payment_adapter_profile_is_valid(
                red_addon_payment_adapter_profile($externalManifest)
            ),
            'external package manifest passes the exact PayPal profile'
        );
        foreach ($externalManifest['integrity']['files'] as $file) {
            red_paypal_profile_assert(
                is_file($resolved . '/' . $file['path'])
                    && hash_equals(
                        $file['sha256'],
                        hash_file('sha256', $resolved . '/' . $file['path'])
                    ),
                'external ' . $file['path'] . ' matches declared integrity'
            );
        }
    }

    echo 'PayPal payment-adapter profile self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
