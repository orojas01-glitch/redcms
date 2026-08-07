<?php
/**
 * Installation-owned orchestration for one non-secret deployment review.
 *
 * Unlike the core review helper, this CLI is allowed to read explicit
 * evidence paths supplied by the disposable rehearsal. It hashes only the
 * public Caddyfile, built binary, certificate chain, and browser report; it
 * never reads or records an HMAC/private-key value.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_deployment_review_helpers.php';

function red_addon_public_mutation_deployment_review_build_fail($message)
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function red_addon_public_mutation_deployment_review_build_json($path)
{
    if (!is_string($path) || !is_file($path) || is_link($path)) {
        red_addon_public_mutation_deployment_review_build_fail(
            'Evidence file is missing or symbolic: ' . (string) $path
        );
    }
    $encoded = file_get_contents($path);
    $decoded = is_string($encoded)
        ? json_decode($encoded, true)
        : null;
    if (!is_array($decoded)) {
        red_addon_public_mutation_deployment_review_build_fail(
            'Evidence JSON is invalid: ' . $path
        );
    }
    return $decoded;
}

function red_addon_public_mutation_deployment_review_build_hash($path)
{
    if (!is_string($path) || !is_file($path) || is_link($path)) {
        red_addon_public_mutation_deployment_review_build_fail(
            'Artifact is missing or symbolic: ' . (string) $path
        );
    }
    $hash = hash_file('sha256', $path);
    if (!is_string($hash) || !red_addon_valid_sha256($hash)) {
        red_addon_public_mutation_deployment_review_build_fail(
            'Artifact hash could not be computed: ' . $path
        );
    }
    return $hash;
}

function red_addon_public_mutation_deployment_review_build_outside_starter(
    $path,
    $projectRoot
) {
    if (!is_string($path) || !is_string($projectRoot)) {
        return false;
    }
    $starter = realpath($projectRoot);
    $candidate = is_file($path) || is_link($path)
        ? realpath($path)
        : realpath(dirname($path));
    if ($starter === false || $candidate === false) {
        return false;
    }
    $prefix = rtrim($starter, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return $candidate !== $starter
        && !str_starts_with($candidate, $prefix);
}

function red_addon_public_mutation_deployment_review_build_args($argv)
{
    $values = [];
    for ($index = 1; $index < count($argv); $index++) {
        $argument = $argv[$index];
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            red_addon_public_mutation_deployment_review_build_fail(
                'Unknown argument: ' . (string) $argument
            );
        }
        $key = substr($argument, 2);
        if ($key === '' || !isset($argv[$index + 1])) {
            red_addon_public_mutation_deployment_review_build_fail(
                'Argument requires a value: ' . $argument
            );
        }
        $values[$key] = $argv[++$index];
    }
    $required = [
        'profile',
        'browser-report',
        'rotation-evidence',
        'caddyfile',
        'binary',
        'certificate',
        'output',
    ];
    foreach ($required as $key) {
        if (!isset($values[$key]) || !is_string($values[$key])
            || $values[$key] === ''
        ) {
            red_addon_public_mutation_deployment_review_build_fail(
                'Missing argument: --' . $key
            );
        }
    }
    return $values;
}

$args = red_addon_public_mutation_deployment_review_build_args($argv);
foreach ($args as $path) {
    if (!red_addon_public_mutation_deployment_review_build_outside_starter(
        $path,
        $projectRoot
    )) {
        red_addon_public_mutation_deployment_review_build_fail(
            'All deployment review evidence paths must remain outside the starter.'
        );
    }
}
$profile = red_addon_public_mutation_deployment_review_build_json(
    $args['profile']
);
$browser = red_addon_public_mutation_deployment_review_build_json(
    $args['browser-report']
);
$rotation = red_addon_public_mutation_deployment_review_build_json(
    $args['rotation-evidence']
);

$profileResult = red_addon_public_mutation_deployment_profile($profile);
if (!red_addon_public_mutation_deployment_profile_valid($profileResult)) {
    red_addon_public_mutation_deployment_review_build_fail(
        'Deployment profile did not validate: ' . ($profileResult['reason'] ?? '')
    );
}

if (array_keys($rotation) !== [
    'schemaVersion',
    'initialKeyProvisioned',
    'rotatedKeyProvisioned',
    'previousKeyAbsentAfterRestart',
    'rotationVerified',
]) {
    red_addon_public_mutation_deployment_review_build_fail(
        'Rotation evidence shape is invalid.'
    );
}
foreach (array_slice($rotation, 1) as $value) {
    if ($value !== true) {
        red_addon_public_mutation_deployment_review_build_fail(
            'Rotation evidence is not verified.'
        );
    }
}
if ($rotation['schemaVersion'] !== 1) {
    red_addon_public_mutation_deployment_review_build_fail(
        'Rotation evidence version is unsupported.'
    );
}

if (array_keys($browser) !== [
    'schemaVersion',
    'baseUrl',
    'origin',
    'desktop',
    'mobile',
    'evidenceOutsideStarter',
    'dispatcherLinked',
    'publicMutationEndpointExercised',
    'clientStateChanged',
    'passed',
]) {
    red_addon_public_mutation_deployment_review_build_fail(
        'Browser evidence shape is invalid.'
    );
}
if ($browser['schemaVersion'] !== 1
    || $browser['origin'] !== $profileResult['profile']['trustedOrigin']
    || $browser['evidenceOutsideStarter'] !== true
    || $browser['dispatcherLinked'] !== false
    || $browser['publicMutationEndpointExercised'] !== false
    || $browser['clientStateChanged'] !== false
    || $browser['passed'] !== true
) {
    red_addon_public_mutation_deployment_review_build_fail(
        'Browser evidence did not satisfy the deployment boundary.'
    );
}

$browserCase = static function ($case, $width, $height) {
    if (!is_array($case)) {
        red_addon_public_mutation_deployment_review_build_fail(
            'Browser viewport evidence is not an object.'
        );
    }
    $selected = [];
    foreach ([
        'viewportWidth',
        'viewportHeight',
        'httpsLoaded',
        'statusCode',
        'consoleErrors',
        'networkErrors',
        'responseHeadersMatched',
        'cookiePolicyMatched',
        'tokenAbsentFromBody',
        'evidenceSHA256',
    ] as $key) {
        if (!array_key_exists($key, $case)) {
            red_addon_public_mutation_deployment_review_build_fail(
                'Browser viewport evidence is incomplete.'
            );
        }
        $selected[$key] = $case[$key];
    }
    $normalized =
        red_addon_public_mutation_deployment_review_browser_case(
            $selected,
            $width,
            $height
        );
    if ($normalized === null) {
        red_addon_public_mutation_deployment_review_build_fail(
            'Browser viewport evidence did not validate.'
        );
    }
    return $normalized;
};
$desktop = $browserCase($browser['desktop'], 1440, 1000);
$mobile = $browserCase($browser['mobile'], 390, 844);

$reviewPacket = [
    'profileHash' => $profileResult['profileHash'],
    'server' => [
        'runtime' => $profileResult['profile']['server']['runtime'],
        'frankenphpVersion' =>
            $profileResult['profile']['server']['frankenphpVersion'],
        'caddyVersion' => $profileResult['profile']['server']['caddyVersion'],
        'tlsMode' => 'https',
        'proxyMode' => $profileResult['profile']['server']['proxyMode'],
        'siteOrigin' => $profileResult['profile']['trustedOrigin'],
        'routeOrder' => $profileResult['profile']['ingress']['routeOrder'],
        'dispatcherLinked' => false,
        'deploymentRootOutsideStarter' => true,
        'binaryOutsideStarter' => true,
        'caddyfileOutsideStarter' => true,
        'certificatesOutsideStarter' => true,
        'caddyfileSHA256' => red_addon_public_mutation_deployment_review_build_hash(
            $args['caddyfile']
        ),
        'binarySHA256' => red_addon_public_mutation_deployment_review_build_hash(
            $args['binary']
        ),
        'certificateChainSHA256' => red_addon_public_mutation_deployment_review_build_hash(
            $args['certificate']
        ),
    ],
    'trust' => [
        'hmacKeyEnvironment' => 'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY',
        'hmacKeySource' => 'process_environment',
        'hmacKeyShapeVerified' => true,
        'trustedOriginEnvironment' => 'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN',
        'trustedOriginSource' => 'process_environment',
        'trustedOriginMatchesProfile' => true,
        'rotationOwner' => 'operator_owned',
        'rotationProcedureVersion' => 'v1',
        'activeKeyPresent' => $rotation['rotatedKeyProvisioned'],
        'previousKeyRevoked' => $rotation['previousKeyAbsentAfterRestart'],
        'rotationVerified' => $rotation['rotationVerified'],
        'secretValuesRecorded' => false,
    ],
    'browser' => [
        'reviewVersion' => 'v1',
        'origin' => $browser['origin'],
        'desktop' => $desktop,
        'mobile' => $mobile,
        'evidenceOutsideStarter' => $browser['evidenceOutsideStarter'],
        'dispatcherLinked' => $browser['dispatcherLinked'],
        'publicMutationEndpointExercised' =>
            $browser['publicMutationEndpointExercised'],
        'clientStateChanged' => $browser['clientStateChanged'],
    ],
];

$reviewResult = red_addon_public_mutation_deployment_review(
    $profileResult,
    $reviewPacket
);
if (!red_addon_public_mutation_deployment_review_valid($reviewResult)) {
    red_addon_public_mutation_deployment_review_build_fail(
        'Deployment review did not validate: ' . ($reviewResult['reason'] ?? '')
    );
}

$output = $args['output'];
$outputParent = realpath(dirname($output));
$projectPrefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if ($outputParent === false
    || str_starts_with($outputParent . DIRECTORY_SEPARATOR, $projectPrefix)
) {
    red_addon_public_mutation_deployment_review_build_fail(
        'Deployment review output must remain outside the starter.'
    );
}

$document = [
    'schemaVersion' => 1,
    'profileHash' => $profileResult['profileHash'],
    'reviewHash' => $reviewResult['reviewHash'],
    'profile' => $profileResult['profile'],
    'review' => $reviewResult['review'],
    'rotationEvidence' => $rotation,
];
$encoded = json_encode(
    $document,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
if (!is_string($encoded)
    || file_put_contents($output, $encoded . "\n") === false
) {
    red_addon_public_mutation_deployment_review_build_fail(
        'Deployment review output could not be written.'
    );
}
chmod($output, 0600);
echo "Deployment review packet passed: $output\n";
?>
