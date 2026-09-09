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
$bootstrapPath = $root . '/includes/bootstrap.php';
$maintenanceNotesPath = $root . '/docs/RELEASE-NOTES-5.1.1.md';

$releaseBytes = file_get_contents($releasePath);
$profileBytes = file_get_contents($profilePath);
$builder = file_get_contents($builderPath);
$fullAcceptance = file_get_contents($fullAcceptancePath);
$bootstrap = file_get_contents($bootstrapPath);
$maintenanceNotes = file_get_contents($maintenanceNotesPath);

red_client_kit_test_assert(is_string($releaseBytes), 'release manifest is readable');
red_client_kit_test_assert(is_string($profileBytes), 'client profile is readable');
red_client_kit_test_assert(is_string($builder), 'builder is readable');
red_client_kit_test_assert(
    is_string($fullAcceptance),
    'full acceptance wrapper is readable'
);
red_client_kit_test_assert(is_string($bootstrap), 'core bootstrap is readable');
red_client_kit_test_assert(
    is_string($maintenanceNotes),
    '5.1.1 maintenance release notes are readable'
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
        && ($release['version'] ?? null) === '0.1.12',
    'release identity is exact'
);
red_client_kit_test_assert(
    ($release['core']['version'] ?? null) === '5.1.1'
        && ($release['core']['minimumCommit'] ?? null)
            === 'cee2062fe0d5d2b292169ea076c0e03bdf1173ae',
    'core 5.1.1 compatibility and reconciled minimum revision are pinned'
);
red_client_kit_test_assert(
    str_contains($bootstrap, "define('RED_CMS_VERSION', '5.1.1')")
        && str_contains($maintenanceNotes, '# RED-CMS 5.1.1 Release Notes'),
    'runtime identity and maintenance release documentation agree on 5.1.1'
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
    ($packages['store-lite']['version'] ?? null) === '0.1.51'
        && ($packages['store-lite']['commit'] ?? null)
            === '57a948929142efb417285d2dbd76b5b3478b7738'
        && ($packages['store-lite']['required'] ?? null) === true
        && ($packages['store-lite']['releaseState'] ?? null)
            === 'legacy_sandbox_evidence_offline_commerce_foundation'
        && ($packages['stripe']['version'] ?? null) === '0.1.21'
        && ($packages['stripe']['commit'] ?? null)
            === '35a7b4bcb1dba1cf94e3d51ea50658b57ef09874'
        && ($packages['stripe']['releaseState'] ?? null)
            === 'legacy_sandbox_evidence_offline_commerce_foundation'
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
        === 'Provider-only Sandbox order and subscription objects are verified; runtime transport, Store Lite mutation, signed webhook lifecycle, and client deployment are not complete.',
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
