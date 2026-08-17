<?php
/**
 * Server-local P3E-8A claim for one previously authorized contact attempt.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot .
    '/includes/addon_provider_contact_claim_helpers.php';

function red_addon_provider_contact_claim_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-provider-contact-claim.php --actor-admin=ID --evidence-file=/absolute/path.json\n" .
        "  php scripts/admin-provider-contact-claim.php --actor-admin=ID --evidence-file=/absolute/path.json \\\n" .
        "    --confirm-database=NAME --confirm-package=redcms.store-lite-stripe-checkout \\\n" .
        "    --confirm-version=0.1.1 --confirm-authorization-sha256=SHA256 \\\n" .
        "    --confirm-authorization-state-sha256=SHA256 --confirm-claim-state-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 --confirm-state=enabled --apply\n"
    );
}

$options = [
    'actorAdmin' => 0,
    'evidenceFile' => '',
    'confirmDatabase' => '',
    'confirmPackage' => '',
    'confirmVersion' => '',
    'confirmAuthorizationSha256' => '',
    'confirmAuthorizationStateSha256' => '',
    'confirmClaimStateSha256' => '',
    'confirmBackupSha256' => '',
    'confirmState' => '',
    'apply' => false,
];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $options['apply'] = true;
    } elseif (str_starts_with($argument, '--actor-admin=')) {
        $options['actorAdmin'] = (int) substr(
            $argument,
            strlen('--actor-admin=')
        );
    } elseif (str_starts_with($argument, '--evidence-file=')) {
        $options['evidenceFile'] = substr(
            $argument,
            strlen('--evidence-file=')
        );
    } elseif (str_starts_with($argument, '--confirm-database=')) {
        $options['confirmDatabase'] = substr(
            $argument,
            strlen('--confirm-database=')
        );
    } elseif (str_starts_with($argument, '--confirm-package=')) {
        $options['confirmPackage'] = substr(
            $argument,
            strlen('--confirm-package=')
        );
    } elseif (str_starts_with($argument, '--confirm-version=')) {
        $options['confirmVersion'] = substr(
            $argument,
            strlen('--confirm-version=')
        );
    } elseif (str_starts_with(
        $argument,
        '--confirm-authorization-sha256='
    )) {
        $options['confirmAuthorizationSha256'] = substr(
            $argument,
            strlen('--confirm-authorization-sha256=')
        );
    } elseif (str_starts_with(
        $argument,
        '--confirm-authorization-state-sha256='
    )) {
        $options['confirmAuthorizationStateSha256'] = substr(
            $argument,
            strlen('--confirm-authorization-state-sha256=')
        );
    } elseif (str_starts_with(
        $argument,
        '--confirm-claim-state-sha256='
    )) {
        $options['confirmClaimStateSha256'] = substr(
            $argument,
            strlen('--confirm-claim-state-sha256=')
        );
    } elseif (str_starts_with($argument, '--confirm-backup-sha256=')) {
        $options['confirmBackupSha256'] = substr(
            $argument,
            strlen('--confirm-backup-sha256=')
        );
    } elseif (str_starts_with($argument, '--confirm-state=')) {
        $options['confirmState'] = substr(
            $argument,
            strlen('--confirm-state=')
        );
    } else {
        red_addon_provider_contact_claim_cli_usage();
        exit(64);
    }
}
if ($options['actorAdmin'] <= 0) {
    red_addon_provider_contact_claim_cli_usage();
    exit(64);
}

$evidencePath = $options['evidenceFile'];
$evidenceRealPath = is_string($evidencePath)
    && str_starts_with($evidencePath, DIRECTORY_SEPARATOR)
    ? realpath($evidencePath)
    : false;
if (!is_string($evidenceRealPath)
    || !str_starts_with($evidenceRealPath, DIRECTORY_SEPARATOR)
    || !is_file($evidenceRealPath)
    || is_link($evidencePath)
    || filesize($evidenceRealPath) < 2
    || filesize($evidenceRealPath) > 65536
) {
    fwrite(
        STDERR,
        "Evidence must be one absolute regular JSON file no larger than 64 KiB.\n"
    );
    exit(64);
}
try {
    $evidence = json_decode(
        (string) file_get_contents($evidenceRealPath),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
} catch (Throwable $throwable) {
    $evidence = null;
}
if (!is_array($evidence)
    || !red_addon_provider_contact_exact_keys(
        $evidence,
        ['readiness', 'prepared']
    )
    || !is_array($evidence['readiness'] ?? null)
    || !is_array($evidence['prepared'] ?? null)
) {
    fwrite(
        STDERR,
        "Evidence must contain only exact readiness and prepared objects.\n"
    );
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$packageId = 'redcms.store-lite-stripe-checkout';
$package = $catalog['packages'][$packageId] ?? null;
if (empty($catalog['valid']) || !is_array($package)) {
    fwrite(STDERR, "Exact adapter discovery or trust validation failed.\n");
    $db->close();
    exit(65);
}
$plan = red_addon_provider_contact_claim_plan(
    $connection,
    $package,
    $catalog,
    $options['actorAdmin'],
    $evidence['readiness'],
    $evidence['prepared'],
    gmdate('Y-m-d\TH:i:s\Z')
);
if (empty($plan['ready'])) {
    fwrite(
        STDERR,
        'Provider-contact attempt claim refused: ' .
            $plan['status'] .
            (($plan['errors'] ?? []) === []
                ? ''
                : ' (' . implode(', ', $plan['errors']) . ')') .
            PHP_EOL
    );
    $db->close();
    exit(65);
}

$database = red_addon_install_database_name($connection);
printf("Database: %s\n", $database);
printf("Package: %s %s\n", $plan['packageId'], $plan['packageVersion']);
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf("Current state: %s\n", $plan['lifecycleState']);
printf("Expires at UTC: %s\n", $plan['expiresAtUtc']);
printf("Authorization SHA-256: %s\n", $plan['authorizationSha256']);
printf(
    "Authorization state SHA-256: %s\n",
    $plan['authorizationStateSha256']
);
printf("Claim state SHA-256: %s\n", $plan['claimStateSha256']);

if (!$options['apply']) {
    echo "DRY RUN: the exact P3E-7 authorization exists and remains unclaimed.\n";
    echo "No claim, credential resolution, network, or provider operation occurred.\n";
    echo "Retain a verified backup and provide exact confirmations with --apply before expiry.\n";
    $db->close();
    exit(0);
}

if ($options['confirmDatabase'] !== $database
    || $options['confirmPackage'] !== $plan['packageId']
    || $options['confirmVersion'] !== $plan['packageVersion']
    || !hash_equals(
        $plan['authorizationSha256'],
        $options['confirmAuthorizationSha256']
    )
    || !hash_equals(
        $plan['authorizationStateSha256'],
        $options['confirmAuthorizationStateSha256']
    )
    || !hash_equals(
        $plan['claimStateSha256'],
        $options['confirmClaimStateSha256']
    )
    || !red_addon_provider_contact_sha256(
        $options['confirmBackupSha256']
    )
    || hash_equals(
        str_repeat('0', 64),
        $options['confirmBackupSha256']
    )
    || $options['confirmState'] !== 'enabled'
) {
    fwrite(
        STDERR,
        "Apply requires exact database, package, version, authorization, " .
            "authorization-state, claim-state, nonzero backup checksum, " .
            "and enabled-state confirmations.\n"
    );
    $db->close();
    exit(64);
}

$result = red_addon_provider_contact_claim(
    $connection,
    $projectRoot,
    $options['actorAdmin'],
    $evidence['readiness'],
    $evidence['prepared'],
    $options['confirmAuthorizationSha256'],
    $options['confirmAuthorizationStateSha256'],
    $options['confirmClaimStateSha256']
);
$db->close();
if (($result['status'] ?? '') !== 'claimed') {
    fwrite(
        STDERR,
        'Provider-contact attempt claim failed closed: ' .
            ($result['status'] ?? 'invalid') . PHP_EOL
    );
    exit(1);
}
echo "Claimed the one authorized future contact attempt atomically.\n";
echo "No credential was resolved and no network, provider, Checkout, payment, " .
    "webhook, Store Lite, or client action occurred.\n";
exit(0);

?>
