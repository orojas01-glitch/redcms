<?php
/** Dependency-free contract checks for the portable client release kit. */

$root = dirname(__DIR__);
$assertions = 0;

function red_client_kit_test_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$releasePath = $root . '/release/client-deployment-kit-v1.json';
$profilePath = $root . '/docs/examples/client-deployment-profile.v1.example.json';
$builderPath = $root . '/scripts/client-deployment-kit.php';
$fullAcceptancePath = $root . '/scripts/client-deployment-kit-full-acceptance.sh';

$releaseBytes = file_get_contents($releasePath);
$profileBytes = file_get_contents($profilePath);
$builder = file_get_contents($builderPath);
$fullAcceptance = file_get_contents($fullAcceptancePath);

red_client_kit_test_assert(is_string($releaseBytes), 'release manifest is readable');
red_client_kit_test_assert(is_string($profileBytes), 'client profile is readable');
red_client_kit_test_assert(is_string($builder), 'builder is readable');
red_client_kit_test_assert(
    is_string($fullAcceptance),
    'full acceptance wrapper is readable'
);

try {
    $release = json_decode($releaseBytes, true, 64, JSON_THROW_ON_ERROR);
    $profile = json_decode($profileBytes, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, 'FAIL: JSON fixture invalid: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

red_client_kit_test_assert(
    ($release['schemaVersion'] ?? null) === 1
        && ($release['id'] ?? null) === 'redcms.store-lite-client-kit'
        && ($release['version'] ?? null) === '0.1.10',
    'release identity is exact'
);
red_client_kit_test_assert(
    ($release['core']['version'] ?? null) === '5.1.0'
        && preg_match(
            '/\A[a-f0-9]{40}\z/',
            (string) ($release['core']['minimumCommit'] ?? '')
        ) === 1,
    'core compatibility and minimum revision are pinned'
);

$packages = [];
foreach ($release['packages'] ?? [] as $package) {
    if (is_array($package) && is_string($package['selection'] ?? null)) {
        $packages[$package['selection']] = $package;
    }
}
red_client_kit_test_assert(
    array_keys($packages) === ['store-lite', 'stripe', 'wompi'],
    'release contains only Store Lite and the two implemented adapter selections'
);
red_client_kit_test_assert(
    ($packages['store-lite']['version'] ?? null) === '0.1.50'
        && ($packages['store-lite']['required'] ?? null) === true
        && ($packages['stripe']['version'] ?? null) === '0.1.20'
        && ($packages['stripe']['releaseState'] ?? null)
            === 'sandbox_verified_catalog_lifecycle'
        && ($packages['wompi']['version'] ?? null) === '0.1.5',
    'package versions match the reviewed repository manifests'
);
foreach ($packages as $selection => $package) {
    red_client_kit_test_assert(
        preg_match('/\A[a-f0-9]{40}\z/', (string) ($package['commit'] ?? '')) === 1,
        $selection . ' commit is pinned'
    );
    red_client_kit_test_assert(
        str_starts_with((string) ($package['archivePath'] ?? ''), 'redcms/addons/redcms/')
            && ($package['sourcePath'] ?? null) === 'package',
        $selection . ' has a closed package source and client archive destination'
    );
}
red_client_kit_test_assert(
    ($release['unsupportedSelections']['paypal'] ?? null)
        === 'Offline package, core profile, enablement, and two-client isolation are verified; provider transports and Sandbox payment acceptance are not yet complete.',
    'PayPal is visible but cannot be misreported as release-ready'
);
red_client_kit_test_assert(
    ($release['secretPolicy']['includeValues'] ?? null) === false
        && ($release['secretPolicy']['ownerEnteredPerClient'] ?? null) === true,
    'release manifest forbids credential values and keeps owner entry per client'
);

red_client_kit_test_assert(
    ($profile['payment']['environment'] ?? null) === 'sandbox'
        && ($profile['deployment']['installState'] ?? null) === 'installed_disabled'
        && ($profile['deployment']['enableAfterOwnerReview'] ?? null) === false
        && ($profile['deployment']['liveModeAuthorized'] ?? null) === false,
    'example client profile remains sandbox-first and disabled by default'
);
red_client_kit_test_assert(
    !preg_match('/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{16,}\b/', $profileBytes)
        && !preg_match('/\bwhsec_[A-Za-z0-9]{16,}\b/', $profileBytes)
        && !preg_match('/\b(?:pub|prv)_(?:prod|test)_[A-Za-z0-9]{16,}\b/', $profileBytes),
    'example profile contains references but no provider credential values'
);

foreach ([
    "PHP_SAPI !== 'cli'",
    "--confirm-plan-sha256=",
    "--apply",
    "--untracked-files=no",
    "red_client_kit_php_command",
    "'php-cli'",
    "clean-starter-boundary-self-test.php",
    "output_must_be_outside_source_repositories",
    "package_integrity_mismatch:",
    "COPYFILE_DISABLE=1",
    "--no-xattrs",
    "secret_value_pattern_in_artifact",
    "containsSecrets' => false",
    "providerContact' => false",
    "clientStateChanged' => false",
] as $guard) {
    red_client_kit_test_assert(
        str_contains($builder, $guard),
        'builder retains guard: ' . $guard
    );
}
red_client_kit_test_assert(
    !preg_match('/\b(?:curl_|mysqli_|fsockopen|stream_socket_client)\b/', $builder),
    'builder has no database or provider-network primitive'
);
foreach ([
    'redcms_client_kit_anchor_',
    'dev-acceptance.sh',
    'DROP DATABASE IF EXISTS',
    'REVOKE ALL PRIVILEGES',
    'primary:unchanged',
] as $guard) {
    red_client_kit_test_assert(
        str_contains($fullAcceptance, $guard),
        'full acceptance wrapper retains guard: ' . $guard
    );
}

printf(
    "Client deployment kit self-test passed: %d assertions.\n",
    $assertions
);
