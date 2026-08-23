<?php
/**
 * Dependency-free C3A checks for the exact Store Lite Wompi adapter profile.
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

function red_wompi_c3a_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c3a_manifest(): array
{
    return [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.store-lite-wompi',
        'name' => 'RED-CMS Store Lite Wompi',
        'description' => 'Closed C3A Wompi payment-adapter fixture.',
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
            'path' => 'migrations/2026-08-23-create-payment-attempts.sql',
            'sha256' => str_repeat('1', 64),
        ], [
            'id' => '2026-08-23-wompi-event-receipts',
            'path' => 'migrations/2026-08-23-create-event-receipts.sql',
            'sha256' => str_repeat('2', 64),
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
    $manifest = red_wompi_c3a_manifest();
    $profile = red_addon_payment_adapter_profile($manifest);
    red_wompi_c3a_assert(
        red_addon_payment_adapter_profile_is_valid($profile)
            && $profile['valid']
            && $profile['contractReady'],
        'exact Wompi manifest produces one valid closed profile'
    );
    red_wompi_c3a_assert(
        $profile['profileId'] === 'store_lite_wompi_adapter_v1'
            && $profile['packageId'] === 'redcms.store-lite-wompi'
            && $profile['adapter'] === 'redcms.store-lite-wompi/checkout'
            && $profile['dependencyPackageId'] === 'redcms.store-lite',
        'profile binds exact identity, adapter, and Store Lite dependency'
    );
    red_wompi_c3a_assert(
        $profile['serverEventRoute']
            === 'redcms.store-lite-wompi/provider-events'
            && $profile['serverEventPath']
                === '/addons/redcms/store-lite-wompi/provider-events'
            && $profile['outboundHost'] === 'sandbox.wompi.co',
        'profile binds exact route and Sandbox-only host'
    );
    red_wompi_c3a_assert(
        $profile['migrationCount'] === 2
            && $profile['ordinarySettingCount'] === 1
            && $profile['secretSettingCount'] === 3
            && $profile['ordinarySettingKeys'] === ['wompi.public-key']
            && $profile['secretSettingKeys'] === [
                'wompi.event-secret',
                'wompi.integrity-key',
                'wompi.private-key',
            ],
        'profile signs exact migration and setting-key evidence'
    );
    red_wompi_c3a_assert(
        !$profile['activationSupported']
            && !$profile['stateMutation']
            && !$profile['runtimeLoad']
            && !$profile['packageExecution']
            && !$profile['secretResolution']
            && !$profile['networkAccess']
            && !$profile['routeExposure'],
        'profile remains data-only and non-activating'
    );
    red_wompi_c3a_assert(
        array_column($profile['blockers'], 'code') === [
            'atomic_payment_adapter_enablement_required',
            'database_bound_adapter_preflight_required',
            'registrar_validation_required',
            'server_event_ingress_required',
        ],
        'every later database, registrar, ingress, and enablement gate remains'
    );
    $repeat = red_addon_payment_adapter_profile($manifest);
    red_wompi_c3a_assert(
        hash_equals($profile['contractSha256'], $repeat['contractSha256']),
        'unchanged Wompi profile fingerprint is deterministic'
    );
    red_wompi_c3a_assert(
        red_addon_payment_adapter_profile_result('redcms.store-lite-wompi')
            ['profileId'] === 'store_lite_wompi_adapter_v1'
            && red_addon_payment_adapter_profile_result('redcms.stripe-fixture')
                ['profileId'] === 'store_lite_stripe_checkout_adapter_v1',
        'only the exact Wompi package selects the new profile family'
    );
    $tamperedProfile = $profile;
    $tamperedProfile['secretSettingKeys'][0] = 'wompi.changed-secret';
    red_wompi_c3a_assert(
        !red_addon_payment_adapter_profile_is_valid($tamperedProfile),
        'changed normalized setting evidence fails profile verification'
    );
    red_wompi_c3a_assert(
        !red_addon_payment_adapter_profile_is_valid(null),
        'non-array profile input fails closed without field access'
    );

    $mutations = [];
    $mutations['package id'] = static function (array $value): array {
        $value['id'] = 'redcms.store-lite-wompi-other';
        $value['provides']['adapters'] = [$value['id'] . '/checkout'];
        $value['routes'][0]['id'] = $value['id'] . '/provider-events';
        $value['routes'][0]['path']
            = '/addons/redcms/store-lite-wompi-other/provider-events';
        return $value;
    };
    $mutations['adapter id'] = static function (array $value): array {
        $value['provides']['adapters'][0] = $value['id'] . '/other';
        return $value;
    };
    $mutations['dependency id'] = static function (array $value): array {
        $value['dependencies']['required'][0]['id'] = 'redcms.other-store';
        return $value;
    };
    $mutations['dependency version'] = static function (array $value): array {
        $value['dependencies']['required'][0]['version'] = '>=0.1 <1.0';
        return $value;
    };
    $mutations['public setting name'] = static function (array $value): array {
        $value['settings'][0]['key'] = 'wompi.other-public-key';
        return $value;
    };
    $mutations['secret removed'] = static function (array $value): array {
        array_pop($value['settings']);
        return $value;
    };
    $mutations['secret renamed'] = static function (array $value): array {
        $value['settings'][1]['key'] = 'wompi.other-secret';
        return $value;
    };
    $mutations['secret default'] = static function (array $value): array {
        $value['settings'][1]['default'] = null;
        return $value;
    };
    $mutations['extra secret'] = static function (array $value): array {
        $value['settings'][] = [
            'key' => 'wompi.extra-secret',
            'label' => 'Extra secret',
            'type' => 'secret-reference',
            'secret' => true,
        ];
        return $value;
    };
    $mutations['migration id'] = static function (array $value): array {
        $value['migrations'][0]['id'] = '2026-08-23-other-attempts';
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
            = '/addons/redcms/store-lite-wompi/other';
        return $value;
    };
    $mutations['route method'] = static function (array $value): array {
        $value['routes'][0]['methods'] = ['GET'];
        return $value;
    };
    $mutations['outbound Stripe host'] = static function (array $value): array {
        $value['outboundHosts'] = ['api.stripe.com'];
        return $value;
    };
    $mutations['outbound production host'] = static function (
        array $value
    ): array {
        $value['outboundHosts'] = ['production.wompi.co'];
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
        red_wompi_c3a_assert(
            !red_addon_payment_adapter_profile($mutate($manifest))['valid'],
            $name . ' remains outside the exact Wompi profile'
        );
    }

    $source = (string) file_get_contents(
        $projectRoot . '/includes/addon_payment_adapter_preflight_helpers.php'
    );
    red_wompi_c3a_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\bgetenv\s*\(|\bmysqli_|\bcurl_|'
                . '\bfsockopen\s*\(|\bstream_socket_client\s*\()/i',
            $source
        ) !== 1,
        'profile helper still has no request, environment, database, or network path'
    );

    if ($externalPackageRoot !== null) {
        $resolved = realpath($externalPackageRoot);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException('External package root is invalid.');
        }
        $manifestPath = $resolved . '/addon.json';
        $externalManifest = json_decode(
            (string) file_get_contents($manifestPath),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
        red_wompi_c3a_assert(
            is_array($externalManifest)
                && ($externalManifest['id'] ?? null)
                    === 'redcms.store-lite-wompi'
                && ($externalManifest['version'] ?? null) === '0.1.0',
            'external package identity and version are exact'
        );
        $externalProfile = red_addon_payment_adapter_profile(
            $externalManifest
        );
        red_wompi_c3a_assert(
            red_addon_payment_adapter_profile_is_valid($externalProfile),
            'published external package manifest passes the exact Wompi profile'
        );
        foreach ($externalManifest['integrity']['files'] as $file) {
            red_wompi_c3a_assert(
                is_file($resolved . '/' . $file['path'])
                    && hash_equals(
                        $file['sha256'],
                        hash_file('sha256', $resolved . '/' . $file['path'])
                    ),
                'external ' . $file['path'] . ' matches declared integrity'
            );
        }
    }

    echo 'Wompi payment-adapter C3A profile self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
