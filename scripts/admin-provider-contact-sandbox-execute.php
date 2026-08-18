<?php
/**
 * Server-local P3E-8B3C3A one-shot Stripe Sandbox operator command.
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
    '/includes/addon_provider_contact_provider_execution_helpers.php';

function red_addon_provider_contact_sandbox_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-provider-contact-sandbox-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/path.json\n" .
        "  php scripts/admin-provider-contact-sandbox-execute.php " .
        "--actor-admin=ID --evidence-file=/absolute/path.json \\\n" .
        "    --confirm-database=NAME --confirm-package=redcms.store-lite-stripe-checkout \\\n" .
        "    --confirm-version=0.1.4 --confirm-state=enabled \\\n" .
        "    --confirm-plan-sha256=SHA256 --confirm-authorization-sha256=SHA256 \\\n" .
        "    --confirm-claim-state-sha256=SHA256 --confirm-execution-start-sha256=SHA256 \\\n" .
        "    --confirm-secret-availability-sha256=SHA256 --confirm-backup-sha256=SHA256 \\\n" .
        "    --confirm-operation=provider-contact.read-only-probe-sandbox \\\n" .
        "    --confirm-target=stripe-sandbox --confirm-credential-mode=restricted_test \\\n" .
        "    --confirm-maximum-attempts=1 --confirm-retry-authorized=no \\\n" .
        "    --confirm-mutation-authorized=no --apply\n"
    );
}

$options = [
    'actorAdmin' => 0,
    'evidenceFile' => '',
    'confirmDatabase' => '',
    'confirmPackage' => '',
    'confirmVersion' => '',
    'confirmState' => '',
    'confirmPlanSha256' => '',
    'confirmAuthorizationSha256' => '',
    'confirmClaimStateSha256' => '',
    'confirmExecutionStartSha256' => '',
    'confirmSecretAvailabilitySha256' => '',
    'confirmBackupSha256' => '',
    'confirmOperation' => '',
    'confirmTarget' => '',
    'confirmCredentialMode' => '',
    'confirmMaximumAttempts' => '',
    'confirmRetryAuthorized' => '',
    'confirmMutationAuthorized' => '',
    'apply' => false,
];
$argumentMap = [
    '--actor-admin=' => 'actorAdmin',
    '--evidence-file=' => 'evidenceFile',
    '--confirm-database=' => 'confirmDatabase',
    '--confirm-package=' => 'confirmPackage',
    '--confirm-version=' => 'confirmVersion',
    '--confirm-state=' => 'confirmState',
    '--confirm-plan-sha256=' => 'confirmPlanSha256',
    '--confirm-authorization-sha256=' =>
        'confirmAuthorizationSha256',
    '--confirm-claim-state-sha256=' => 'confirmClaimStateSha256',
    '--confirm-execution-start-sha256=' =>
        'confirmExecutionStartSha256',
    '--confirm-secret-availability-sha256=' =>
        'confirmSecretAvailabilitySha256',
    '--confirm-backup-sha256=' => 'confirmBackupSha256',
    '--confirm-operation=' => 'confirmOperation',
    '--confirm-target=' => 'confirmTarget',
    '--confirm-credential-mode=' => 'confirmCredentialMode',
    '--confirm-maximum-attempts=' => 'confirmMaximumAttempts',
    '--confirm-retry-authorized=' => 'confirmRetryAuthorized',
    '--confirm-mutation-authorized=' => 'confirmMutationAuthorized',
];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $options['apply'] = true;
        continue;
    }
    $matched = false;
    foreach ($argumentMap as $prefix => $key) {
        if (str_starts_with($argument, $prefix)) {
            $value = substr($argument, strlen($prefix));
            $options[$key] = $key === 'actorAdmin' ? (int) $value : $value;
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        red_addon_provider_contact_sandbox_cli_usage();
        exit(64);
    }
}
if ($options['actorAdmin'] <= 0 || $options['evidenceFile'] === '') {
    red_addon_provider_contact_sandbox_cli_usage();
    exit(64);
}

$evidencePath = $options['evidenceFile'];
$evidenceRealPath = str_starts_with($evidencePath, DIRECTORY_SEPARATOR)
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
$evaluatedAtUtc = gmdate('Y-m-d\TH:i:s\Z');
$plan = red_addon_provider_contact_sandbox_execution_plan(
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
        'Provider-contact sandbox execution refused: ' .
            ($plan['status'] ?? 'invalid') .
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
printf("Plan SHA-256: %s\n", $plan['planSha256']);
printf("Authorization SHA-256: %s\n", $plan['authorizationSha256']);
printf("Claim state SHA-256: %s\n", $plan['claimStateSha256']);
printf(
    "Execution start SHA-256: %s\n",
    $plan['executionStartStateSha256']
);
printf(
    "Secret availability SHA-256: %s\n",
    $plan['secretAvailabilitySha256']
);
echo "Operation: provider-contact.read-only-probe-sandbox\n";
echo "Target: stripe-sandbox\n";
echo "Credential mode: restricted_test\n";
echo "Maximum attempts: 1\n";
echo "Retry authorized: no\n";
echo "Mutation authorized: no\n";

if (!$options['apply']) {
    echo "DRY RUN: authorization, claim, package, and value-free secret " .
        "availability were revalidated.\n";
    echo "No credential value was resolved, no package handler was invoked, " .
        "and no network or provider contact occurred.\n";
    echo "Retain a verified backup and supply every printed confirmation with " .
        "--apply before expiry.\n";
    $db->close();
    exit(0);
}

$confirmationsValid =
    $options['confirmDatabase'] === $database
    && $options['confirmPackage'] === $plan['packageId']
    && $options['confirmVersion'] === '0.1.4'
    && $options['confirmVersion'] === $plan['packageVersion']
    && $options['confirmState'] === 'enabled'
    && hash_equals($plan['planSha256'], $options['confirmPlanSha256'])
    && hash_equals(
        $plan['authorizationSha256'],
        $options['confirmAuthorizationSha256']
    )
    && hash_equals(
        $plan['claimStateSha256'],
        $options['confirmClaimStateSha256']
    )
    && hash_equals(
        $plan['executionStartStateSha256'],
        $options['confirmExecutionStartSha256']
    )
    && hash_equals(
        $plan['secretAvailabilitySha256'],
        $options['confirmSecretAvailabilitySha256']
    )
    && red_addon_provider_contact_sha256(
        $options['confirmBackupSha256']
    )
    && !hash_equals(
        str_repeat('0', 64),
        $options['confirmBackupSha256']
    )
    && $options['confirmOperation']
        === 'provider-contact.read-only-probe-sandbox'
    && $options['confirmTarget'] === 'stripe-sandbox'
    && $options['confirmCredentialMode'] === 'restricted_test'
    && $options['confirmMaximumAttempts'] === '1'
    && $options['confirmRetryAuthorized'] === 'no'
    && $options['confirmMutationAuthorized'] === 'no';
if (!$confirmationsValid) {
    fwrite(
        STDERR,
        "Apply requires exact database, package, version, state, plan, " .
            "authorization, claim, execution-start, secret-availability, " .
            "nonzero-backup, operation, target, restricted-test-key, " .
            "one-attempt, no-retry, and no-mutation confirmations.\n"
    );
    $db->close();
    exit(64);
}

$result = red_addon_provider_contact_execute_sandbox(
    $connection,
    $projectRoot,
    $options['actorAdmin'],
    $evidence['readiness'],
    $evidence['prepared'],
    $options['confirmAuthorizationSha256'],
    $options['confirmClaimStateSha256'],
    $options['confirmExecutionStartSha256'],
    $evaluatedAtUtc
);
$db->close();

$outcome = $result['boundedOutcome'] ?? null;
printf("Outcome: %s\n", $result['status'] ?? 'invalid');
echo "Attempt consumed: " .
    (!empty($result['executionStarted']) ? 'yes' : 'no') . "\n";
echo "Retry authorized: no\n";
echo "Mutation authorized: no\n";
if (is_array($outcome)) {
    printf(
        "HTTP status: %s\n",
        is_int($outcome['statusCode'] ?? null)
            ? (string) $outcome['statusCode']
            : 'unavailable'
    );
    printf(
        "Response bytes: %s\n",
        is_int($outcome['responseBytes'] ?? null)
            ? (string) $outcome['responseBytes']
            : 'unavailable'
    );
    printf(
        "Transport evidence SHA-256: %s\n",
        red_addon_provider_contact_sha256(
            $outcome['transportEvidenceSha256'] ?? null
        )
            ? $outcome['transportEvidenceSha256']
            : 'unavailable'
    );
}

$expected = ($result['status'] ?? '') === 'resource_miss_observed'
    && !empty($result['executionStarted'])
    && !empty($result['outcomeRecorded'])
    && !empty($result['outcomeAuditRecorded'])
    && !empty($result['executionPerformed'])
    && !empty($result['networkAccess'])
    && !empty($result['providerContact'])
    && is_array($outcome)
    && ($outcome['statusCode'] ?? null) === 404
    && ($outcome['expectedEffectObserved'] ?? null) === true
    && ($outcome['responseBodyIncluded'] ?? null) === false
    && ($outcome['responseHeadersIncluded'] ?? null) === false
    && ($outcome['credentialIncluded'] ?? null) === false
    && ($outcome['retryAuthorized'] ?? null) === false
    && ($outcome['mutationAuthorized'] ?? null) === false;
if (!$expected) {
    fwrite(
        STDERR,
        "Provider rehearsal did not observe the exact bounded resource miss. " .
            "The attempt remains consumed and no retry is authorized.\n"
    );
    exit(1);
}
echo "Observed the exact bounded read-only Stripe Sandbox resource miss.\n";
echo "No response body, response header, credential, Checkout, payment, " .
    "webhook, Store Lite mutation, or client action was retained.\n";
exit(0);

?>
