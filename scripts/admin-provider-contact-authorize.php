<?php
/**
 * Server-local P3E-7 Owner authorization for one future provider contact.
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
    '/includes/addon_provider_contact_authorization_helpers.php';

function red_addon_provider_contact_authorize_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-provider-contact-authorize.php --actor-admin=ID --show-owner-subject\n" .
        "  php scripts/admin-provider-contact-authorize.php --actor-admin=ID --evidence-file=/absolute/path.json\n" .
        "  php scripts/admin-provider-contact-authorize.php --actor-admin=ID --evidence-file=/absolute/path.json \\\n" .
        "    --confirm-database=NAME --confirm-package=redcms.store-lite-stripe-checkout \\\n" .
        "    --confirm-version=PRINTED_VERSION --confirm-authorization-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 --confirm-state=enabled --apply\n"
    );
}

$options = [
    'actorAdmin' => 0,
    'evidenceFile' => '',
    'showOwnerSubject' => false,
    'confirmDatabase' => '',
    'confirmPackage' => '',
    'confirmVersion' => '',
    'confirmAuthorizationSha256' => '',
    'confirmBackupSha256' => '',
    'confirmState' => '',
    'apply' => false,
];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $options['apply'] = true;
    } elseif ($argument === '--show-owner-subject') {
        $options['showOwnerSubject'] = true;
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
        red_addon_provider_contact_authorize_cli_usage();
        exit(64);
    }
}
if ($options['actorAdmin'] <= 0
    || ($options['showOwnerSubject'] && $options['apply'])
) {
    red_addon_provider_contact_authorize_cli_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$actor = red_admin_addon_database_actor(
    $connection,
    $options['actorAdmin']
);
if (!red_admin_addon_actor_can($actor, 'addons.enable')) {
    fwrite(STDERR, "Exact database-backed Owner enable authority is required.\n");
    $db->close();
    exit(65);
}
$ownerSubject = red_addon_provider_contact_owner_subject_sha256(
    $connection,
    $options['actorAdmin']
);
if (!red_addon_provider_contact_sha256($ownerSubject)) {
    fwrite(STDERR, "Could not derive the client-bound Owner subject.\n");
    $db->close();
    exit(65);
}
if ($options['showOwnerSubject']) {
    printf("Database: %s\n", red_addon_install_database_name($connection));
    printf("Actor administrator: %d\n", $options['actorAdmin']);
    printf("Owner subject SHA-256: %s\n", $ownerSubject);
    echo "No authorization, nonce, package, credential, or network state changed.\n";
    $db->close();
    exit(0);
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
    fwrite(STDERR, "Evidence must be one absolute regular JSON file no larger than 64 KiB.\n");
    $db->close();
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
    fwrite(STDERR, "Evidence must contain only exact readiness and prepared objects.\n");
    $db->close();
    exit(64);
}

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
$evaluatedAtUtc = gmdate('Y-m-d\TH:i:s\Z');
$plan = red_addon_provider_contact_authorization_plan(
    $connection,
    $package,
    $catalog,
    $options['actorAdmin'],
    $evidence['readiness'],
    $evidence['prepared'],
    $evaluatedAtUtc
);
if (empty($plan['ready'])) {
    fwrite(
        STDERR,
        'Provider-contact authorization refused: ' .
            implode(', ', $plan['errors'] ?? ['invalid']) . PHP_EOL
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
printf("Plan SHA-256: %s\n", $plan['planSha256']);
printf("Authorization SHA-256: %s\n", $plan['authorizationSha256']);

if (!$options['apply']) {
    echo "DRY RUN: Owner and package were revalidated; the nonce remains unconsumed.\n";
    echo "No credential was resolved and no network or provider operation occurred.\n";
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
            "nonzero backup checksum, and enabled-state confirmations.\n"
    );
    $db->close();
    exit(64);
}

$result = red_addon_provider_contact_authorize(
    $connection,
    $projectRoot,
    $options['actorAdmin'],
    $evidence['readiness'],
    $evidence['prepared'],
    $options['confirmAuthorizationSha256']
);
$db->close();
if ($result['status'] !== 'authorized') {
    fwrite(
        STDERR,
        'Provider-contact authorization failed closed: ' .
            $result['status'] . PHP_EOL
    );
    exit(1);
}
echo "Authorized one future read-only Stripe Sandbox contact. The nonce and " .
    "audit fact were committed atomically.\n";
echo "No credential was resolved and no network, provider, Checkout, payment, " .
    "webhook, Store Lite, or client action occurred.\n";
exit(0);

?>
