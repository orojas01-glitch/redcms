<?php
/**
 * Builds one non-secret direct-PHP deployment review from explicit evidence.
 *
 * This CLI may read only paths supplied by the disposable Apache proof. Core
 * validation remains pure and non-executing. No secret, private key, database,
 * package, browser session, dispatcher link, or client state is accepted.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_deployment_review_helpers.php';

function red_addon_public_mutation_direct_review_build_fail($message)
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function red_addon_public_mutation_direct_review_build_json($path)
{
    if (!is_string($path) || !is_file($path) || is_link($path)) {
        red_addon_public_mutation_direct_review_build_fail(
            'Evidence file is missing or symbolic: ' . (string) $path
        );
    }
    $encoded = file_get_contents($path);
    $decoded = is_string($encoded) ? json_decode($encoded, true) : null;
    if (!is_array($decoded)) {
        red_addon_public_mutation_direct_review_build_fail(
            'Evidence JSON is invalid: ' . $path
        );
    }
    return $decoded;
}

function red_addon_public_mutation_direct_review_build_hash($path)
{
    if (!is_string($path) || !is_file($path) || is_link($path)) {
        red_addon_public_mutation_direct_review_build_fail(
            'Artifact is missing or symbolic: ' . (string) $path
        );
    }
    $hash = hash_file('sha256', $path);
    if (!is_string($hash) || !red_addon_valid_sha256($hash)) {
        red_addon_public_mutation_direct_review_build_fail(
            'Artifact hash could not be computed: ' . $path
        );
    }
    return $hash;
}

function red_addon_public_mutation_direct_review_build_outside_starter(
    $path,
    $projectRoot
) {
    $starter = realpath($projectRoot);
    $candidate = is_file($path) || is_link($path)
        ? realpath($path)
        : realpath(dirname($path));
    if ($starter === false || $candidate === false) {
        return false;
    }
    $prefix = rtrim($starter, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return $candidate !== $starter && !str_starts_with($candidate, $prefix);
}

function red_addon_public_mutation_direct_review_build_args($argv)
{
    $values = [];
    for ($index = 1; $index < count($argv); $index++) {
        $argument = $argv[$index];
        if (!is_string($argument) || !str_starts_with($argument, '--')
            || !isset($argv[$index + 1])
        ) {
            red_addon_public_mutation_direct_review_build_fail(
                'Unknown or incomplete argument: ' . (string) $argument
            );
        }
        $key = substr($argument, 2);
        if ($key === '' || array_key_exists($key, $values)) {
            red_addon_public_mutation_direct_review_build_fail(
                'Duplicate or empty argument: ' . $argument
            );
        }
        $values[$key] = $argv[++$index];
    }
    $required = [
        'profile',
        'browser-report',
        'projection-report',
        'apache-config',
        'runtime-evidence',
        'certificate',
        'output',
    ];
    if (array_keys($values) !== $required) {
        red_addon_public_mutation_direct_review_build_fail(
            'Direct deployment review arguments are incomplete or reordered.'
        );
    }
    return $values;
}

$args = red_addon_public_mutation_direct_review_build_args($argv);
foreach ($args as $path) {
    if (!red_addon_public_mutation_direct_review_build_outside_starter(
        $path,
        $projectRoot
    )) {
        red_addon_public_mutation_direct_review_build_fail(
            'All direct deployment evidence paths must remain outside the starter.'
        );
    }
}

$profile = red_addon_public_mutation_direct_review_build_json(
    $args['profile']
);
$browser = red_addon_public_mutation_direct_review_build_json(
    $args['browser-report']
);
$projection = red_addon_public_mutation_direct_review_build_json(
    $args['projection-report']
);
$runtime = red_addon_public_mutation_direct_review_build_json(
    $args['runtime-evidence']
);
$profileResult = red_addon_public_mutation_deployment_profile($profile);
if (!red_addon_public_mutation_deployment_profile_valid($profileResult)
    || ($profileResult['profile']['server']['runtime'] ?? '') !== 'apache_php'
    || ($profileResult['profile']['ingress']['profile'] ?? '') !== 'direct_php'
) {
    red_addon_public_mutation_direct_review_build_fail(
        'An exact validated direct-PHP deployment profile is required.'
    );
}

$expectedRuntimeKeys = [
    'schemaVersion',
    'apacheBinary',
    'phpCgiBinary',
    'apacheVersion',
    'phpVersion',
    'sapi',
    'databaseOpened',
    'packageLoaded',
    'dispatcherLinked',
];
$safeAbsolutePath = static function ($value) {
    return is_string($value)
        && preg_match('/\A\/[^\x00-\x1F\x7F]{1,1023}\z/D', $value) === 1;
};
if (array_keys($runtime) !== $expectedRuntimeKeys
    || $runtime['schemaVersion'] !== 1
    || !$safeAbsolutePath($runtime['apacheBinary'])
    || !$safeAbsolutePath($runtime['phpCgiBinary'])
    || $runtime['apacheVersion']
        !== $profileResult['profile']['server']['apacheVersion']
    || $runtime['phpVersion']
        !== $profileResult['profile']['server']['phpVersion']
    || $runtime['sapi'] !== $profileResult['profile']['server']['sapi']
    || $runtime['databaseOpened'] !== false
    || $runtime['packageLoaded'] !== false
    || $runtime['dispatcherLinked'] !== false
) {
    red_addon_public_mutation_direct_review_build_fail(
        'Apache PHP runtime evidence does not match the profile boundary.'
    );
}

$certificate = file_get_contents($args['certificate']);
if (!is_string($certificate)
    || !str_starts_with($certificate, "-----BEGIN CERTIFICATE-----\n")
    || str_contains($certificate, 'PRIVATE KEY')
) {
    red_addon_public_mutation_direct_review_build_fail(
        'Certificate evidence is invalid or contains private-key material.'
    );
}

$expectedProjectionKeys = [
    'schemaVersion',
    'apacheVersion',
    'phpVersion',
    'sapi',
    'httpsProjection',
    'canonicalRequestCaptured',
    'spoofedHostForwardingIgnored',
    'duplicateOriginRefused',
    'duplicateCsrfRefused',
    'duplicateCookieRefused',
    'contentEncodingRefused',
    'transferEncodingNormalized',
    'forwardedHttpsOverHttpRefused',
    'clientStateChanged',
    'passed',
];
if (array_keys($projection) !== $expectedProjectionKeys
    || $projection['schemaVersion'] !== 1
    || $projection['apacheVersion']
        !== $profileResult['profile']['server']['apacheVersion']
    || $projection['phpVersion']
        !== $profileResult['profile']['server']['phpVersion']
    || $projection['sapi'] !== $profileResult['profile']['server']['sapi']
    || $projection['clientStateChanged'] !== false
    || $projection['passed'] !== true
) {
    red_addon_public_mutation_direct_review_build_fail(
        'Apache PHP projection evidence does not match the profile.'
    );
}
foreach (array_slice($projection, 4, 9, true) as $value) {
    if ($value !== true) {
        red_addon_public_mutation_direct_review_build_fail(
            'Apache PHP projection evidence contains an unverified case.'
        );
    }
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
]
    || $browser['schemaVersion'] !== 1
    || $browser['origin'] !== $profileResult['profile']['trustedOrigin']
    || $browser['evidenceOutsideStarter'] !== true
    || $browser['dispatcherLinked'] !== false
    || $browser['publicMutationEndpointExercised'] !== false
    || $browser['clientStateChanged'] !== false
    || $browser['passed'] !== true
) {
    red_addon_public_mutation_direct_review_build_fail(
        'Browser evidence does not satisfy the direct deployment boundary.'
    );
}

$browserCase = static function ($case, $width, $height) {
    if (!is_array($case)) {
        red_addon_public_mutation_direct_review_build_fail(
            'Browser viewport evidence is invalid.'
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
            red_addon_public_mutation_direct_review_build_fail(
                'Browser viewport evidence is incomplete.'
            );
        }
        $selected[$key] = $case[$key];
    }
    $normalized = red_addon_public_mutation_deployment_review_browser_case(
        $selected,
        $width,
        $height
    );
    if ($normalized === null) {
        red_addon_public_mutation_direct_review_build_fail(
            'Browser viewport evidence did not validate.'
        );
    }
    return $normalized;
};

$profileNormalized = $profileResult['profile'];
$reviewPacket = [
    'profileHash' => $profileResult['profileHash'],
    'server' => [
        'runtime' => 'apache_php',
        'apacheVersion' => $profileNormalized['server']['apacheVersion'],
        'phpVersion' => $profileNormalized['server']['phpVersion'],
        'sapi' => $profileNormalized['server']['sapi'],
        'tlsMode' => 'https',
        'proxyMode' => 'none',
        'siteOrigin' => $profileNormalized['trustedOrigin'],
        'routeOrder' => $profileNormalized['ingress']['routeOrder'],
        'dispatcherLinked' => false,
        'deploymentRootOutsideStarter' => true,
        'configurationOutsideStarter' => true,
        'certificatesOutsideStarter' => true,
        'apacheConfigSHA256' =>
            red_addon_public_mutation_direct_review_build_hash(
                $args['apache-config']
            ),
        'runtimeEvidenceSHA256' =>
            red_addon_public_mutation_direct_review_build_hash(
                $args['runtime-evidence']
            ),
        'certificateChainSHA256' =>
            red_addon_public_mutation_direct_review_build_hash(
                $args['certificate']
            ),
        'projectionEvidenceSHA256' =>
            red_addon_public_mutation_direct_review_build_hash(
                $args['projection-report']
            ),
        'projectionVerified' => true,
    ],
    'trust' => [
        'profile' => 'direct_php',
        'trustedOriginEnvironment' =>
            'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN',
        'trustedOriginSource' =>
            $profileNormalized['ingress']['trustedOriginSource'],
        'trustedOriginMatchesProfile' => true,
        'httpsSource' => 'apache_server',
        'httpsVerified' => true,
        'hostIgnored' => true,
        'forwardedHeadersIgnored' => true,
        'hmacRequired' => false,
        'secretValuesRecorded' => false,
    ],
    'browser' => [
        'reviewVersion' => 'v1',
        'origin' => $browser['origin'],
        'desktop' => $browserCase($browser['desktop'], 1440, 1000),
        'mobile' => $browserCase($browser['mobile'], 390, 844),
        'evidenceOutsideStarter' => true,
        'dispatcherLinked' => false,
        'publicMutationEndpointExercised' => false,
        'clientStateChanged' => false,
    ],
];

$reviewResult = red_addon_public_mutation_deployment_review(
    $profileResult,
    $reviewPacket
);
if (!red_addon_public_mutation_deployment_review_valid($reviewResult)) {
    red_addon_public_mutation_direct_review_build_fail(
        'Direct deployment review did not validate: '
            . ($reviewResult['reason'] ?? '')
    );
}

$document = [
    'schemaVersion' => 1,
    'profileHash' => $profileResult['profileHash'],
    'reviewHash' => $reviewResult['reviewHash'],
    'profile' => $profileResult['profile'],
    'review' => $reviewResult['review'],
    'projectionEvidence' => $projection,
];
$encoded = json_encode(
    $document,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
if (!is_string($encoded)
    || file_put_contents($args['output'], $encoded . "\n") === false
) {
    red_addon_public_mutation_direct_review_build_fail(
        'Direct deployment review output could not be written.'
    );
}
chmod($args['output'], 0600);
echo 'Direct deployment review packet passed: ' . $args['output'] . "\n";

?>
