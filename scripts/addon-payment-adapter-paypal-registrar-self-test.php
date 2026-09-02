<?php
/** Registration-only acceptance for the exact external PayPal package. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_payment_adapter_registrar_helpers.php';

$packageRoot = '/Users/oscarrojas/Documents/redcms-store-lite-paypal/package';
foreach (array_slice($argv, 1) as $argument) {
    if (!str_starts_with($argument, '--package-root=')) {
        fwrite(STDERR, "Unknown argument.\n");
        exit(64);
    }
    $packageRoot = substr($argument, strlen('--package-root='));
}

$assertions = 0;
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-paypal-registrar-' . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$fixturePackage = $fixtureProject . '/addons/redcms/store-lite-paypal';
$packageId = 'redcms.store-lite-paypal';

function red_paypal_registrar_assert($condition, $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_paypal_registrar_copy(string $source, string $target): void
{
    if (!mkdir($target, 0700, true) && !is_dir($target)) {
        throw new RuntimeException('Could not create registrar fixture.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($entry->isDir()) {
            if (!is_dir($destination)
                && !mkdir($destination, 0700, true)
                && !is_dir($destination)
            ) {
                throw new RuntimeException('Could not copy fixture directory.');
            }
        } elseif (!copy($entry->getPathname(), $destination)) {
            throw new RuntimeException('Could not copy fixture file.');
        }
    }
}

function red_paypal_registrar_remove(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entry->isDir() && !$entry->isLink()
            ? rmdir($entry->getPathname())
            : unlink($entry->getPathname());
    }
    rmdir($path);
}

function red_paypal_registrar_database_plan(array $package): array
{
    $profile = red_addon_payment_adapter_profile($package['manifest']);
    $plan = red_addon_payment_adapter_database_result($package['id']);
    $plan['valid'] = true;
    $plan['databaseEvidenceReady'] = true;
    $plan['version'] = $package['manifest']['version'];
    $plan['currentState'] = 'installed_disabled';
    $plan['databaseSha256'] = hash('sha256', 'disposable-paypal-database');
    $plan['contractSha256'] = $profile['contractSha256'];
    $plan['baseEnablementSha256'] = hash('sha256', 'base-enablement');
    $plan['dependencyEvidenceSha256'] = hash('sha256', 'dependency');
    $plan['migrationEvidenceSha256'] = hash('sha256', 'migration');
    $plan['tableEvidenceSha256'] = hash('sha256', 'table');
    $plan['dependencyCount'] = 1;
    $plan['migrationCount'] = 2;
    $plan['tableCount'] = 2;
    $plan['innoDbTableCount'] = 2;
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
    $plan['planSha256'] = red_addon_payment_adapter_database_fingerprint($plan);
    return $plan;
}

if (!defined('RED_PAYPAL_REGISTRAR_FIXTURE_ONLY')) {
try {
    $resolved = realpath($packageRoot);
    if ($resolved === false || !is_file($resolved . '/addon.json')) {
        throw new RuntimeException('PayPal package root is invalid.');
    }
    red_paypal_registrar_copy($resolved, $fixturePackage);
    $package = red_addon_validate_manifest(
        $packageId,
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    red_paypal_registrar_assert(
        !empty($package['valid'])
            && ($package['manifest']['version'] ?? null) === '0.2.0',
        'exact external PayPal package validates in isolation'
    );
    red_paypal_registrar_assert(
        !class_exists(RED_CMS_Store_Lite_PayPal_Offline_Adapter::class, false),
        'manifest discovery executes no PayPal package PHP'
    );

    $databasePlan = red_paypal_registrar_database_plan($package);
    red_paypal_registrar_assert(
        red_addon_payment_adapter_database_preflight_is_valid($databasePlan),
        'synthetic database evidence is valid for registrar isolation'
    );
    $registrarPlan = red_addon_payment_adapter_validate_registrar(
        $package,
        $databasePlan
    );
    red_paypal_registrar_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid($registrarPlan),
        'exact PayPal registrar produces valid registration-only evidence'
    );
    red_paypal_registrar_assert(
        $registrarPlan['profileId'] === 'store_lite_paypal_adapter_v1'
            && $registrarPlan['adapter']
                === 'redcms.store-lite-paypal/checkout'
            && $registrarPlan['serverEventRoute']
                === 'redcms.store-lite-paypal/provider-events'
            && $registrarPlan['registrationCount'] === 2,
        'registrar binds only the exact PayPal adapter and event route'
    );
    red_paypal_registrar_assert(
        $registrarPlan['packageExecutionAttempted']
            && $registrarPlan['registrarExecutionCompleted']
            && !$registrarPlan['handlerInvocation']
            && !$registrarPlan['runtimePublication']
            && !$registrarPlan['networkAccess']
            && !$registrarPlan['routeExposure'],
        'registrar executes package registration with no handler or runtime effect'
    );
    red_paypal_registrar_assert(
        array_column($registrarPlan['blockers'], 'code') === [
            'atomic_payment_adapter_enablement_required',
            'server_event_ingress_required',
        ],
        'atomic enablement and ingress remain blocked'
    );
    $changed = $registrarPlan;
    $changed['profileId'] = 'store_lite_stripe_checkout_adapter_v1';
    $changed['planSha256'] =
        red_addon_payment_adapter_registrar_fingerprint($changed);
    red_paypal_registrar_assert(
        !red_addon_payment_adapter_registrar_preflight_is_valid($changed),
        'PayPal registration evidence cannot be relabeled as Stripe'
    );
    red_paypal_registrar_assert(
        class_exists(RED_CMS_Store_Lite_PayPal_Offline_Adapter::class, false),
        'registration loads only reviewed PayPal package classes'
    );
    $source = (string) file_get_contents(
        $projectRoot . '/includes/addon_payment_adapter_registrar_helpers.php'
    );
    red_paypal_registrar_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\bgetenv\s*\(|\bmysqli_|\bcurl_|'
                . '\bfsockopen\s*\(|\bstream_socket_client\s*\()/i',
            $source
        ) !== 1,
        'registrar helper has no request, environment, database, or network path'
    );
} finally {
    red_paypal_registrar_remove($temporaryRoot);
}

echo 'PayPal payment-adapter registrar self-test passed: '
    . $assertions . " assertions.\n";
echo "No handler, secret, network, PayPal, payment, webhook, database, runtime, or Store Lite effect occurred.\n";
}

?>
