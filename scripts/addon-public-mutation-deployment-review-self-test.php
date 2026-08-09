<?php
/**
 * Dependency-free checks for the non-executing deployment review packet.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_deployment_review_helpers.php';

$assertions = 0;

function red_addon_public_mutation_deployment_review_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_deployment_review_test_profile()
{
    return [
        'clientId' => 'demo-red-sphere',
        'databaseName' => 'redcms_demo_red_sphere',
        'trustedOrigin' => 'https://demo.example.test',
        'server' => [
            'runtime' => 'frankenphp',
            'frankenphpVersion' => '1.12.4',
            'caddyVersion' => '2.11.4',
            'tlsMode' => 'https',
            'proxyMode' => 'none',
        ],
        'ingress' => [
            'captureVersion' => 'v1',
            'hmacKeyEnvironment' =>
                'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY',
            'hmacKeySource' => 'process_environment',
            'trustedOriginSource' => 'process_environment',
            'routeOrder' => [
                'red_public_mutation_attestation',
                'php_server',
            ],
            'keyRotation' => 'operator_owned',
        ],
        'response' => [
            'owner' => 'core',
            'emitter' => 'core_public_mutation_response_emitter',
            'browserCookieOwner' => 'core',
            'packageMayEmitHeaders' => false,
            'frontControllerLinked' => false,
        ],
        'subjectCookie' => [
            'name' => 'redcms_public_mutation_subject',
            'domain' => '',
            'path' => '/',
            'secure' => true,
            'httpOnly' => true,
            'sameSite' => 'Strict',
            'maxAgeSeconds' => 1800,
        ],
        'isolation' => [
            'databaseScoped' => true,
            'configurationOutsideStarter' => true,
            'binaryOutsideStarter' => true,
            'secretsOutsideStarter' => true,
            'mediaOutsideStarter' => true,
        ],
        'activation' => [
            'dispatcherLinked' => false,
            'dispatcherEnabled' => false,
            'packageEnabled' => false,
            'storeLiteEnabled' => false,
        ],
    ];
}

function red_addon_public_mutation_deployment_review_test_case(
    $width,
    $height,
    $evidenceHash
) {
    return [
        'viewportWidth' => $width,
        'viewportHeight' => $height,
        'httpsLoaded' => true,
        'statusCode' => 200,
        'consoleErrors' => 0,
        'networkErrors' => 0,
        'responseHeadersMatched' => true,
        'cookiePolicyMatched' => true,
        'tokenAbsentFromBody' => true,
        'evidenceSHA256' => $evidenceHash,
    ];
}

function red_addon_public_mutation_deployment_review_test_packet()
{
    return [
        'profileHash' => '',
        'server' => [
            'runtime' => 'frankenphp',
            'frankenphpVersion' => '1.12.4',
            'caddyVersion' => '2.11.4',
            'tlsMode' => 'https',
            'proxyMode' => 'none',
            'siteOrigin' => 'https://demo.example.test',
            'routeOrder' => [
                'red_public_mutation_attestation',
                'php_server',
            ],
            'dispatcherLinked' => false,
            'deploymentRootOutsideStarter' => true,
            'binaryOutsideStarter' => true,
            'caddyfileOutsideStarter' => true,
            'certificatesOutsideStarter' => true,
            'caddyfileSHA256' => str_repeat('1', 64),
            'binarySHA256' => str_repeat('2', 64),
            'certificateChainSHA256' => str_repeat('3', 64),
        ],
        'trust' => [
            'hmacKeyEnvironment' =>
                'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY',
            'hmacKeySource' => 'process_environment',
            'hmacKeyShapeVerified' => true,
            'trustedOriginEnvironment' =>
                'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN',
            'trustedOriginSource' => 'process_environment',
            'trustedOriginMatchesProfile' => true,
            'rotationOwner' => 'operator_owned',
            'rotationProcedureVersion' => 'v1',
            'activeKeyPresent' => true,
            'previousKeyRevoked' => true,
            'rotationVerified' => true,
            'secretValuesRecorded' => false,
        ],
        'browser' => [
            'reviewVersion' => 'v1',
            'origin' => 'https://demo.example.test',
            'desktop' => red_addon_public_mutation_deployment_review_test_case(
                1440,
                1000,
                str_repeat('4', 64)
            ),
            'mobile' => red_addon_public_mutation_deployment_review_test_case(
                390,
                844,
                str_repeat('5', 64)
            ),
            'evidenceOutsideStarter' => true,
            'dispatcherLinked' => false,
            'publicMutationEndpointExercised' => false,
            'clientStateChanged' => false,
        ],
    ];
}

function red_addon_public_mutation_deployment_review_test_invalid(
    $profile,
    $review,
    $reason,
    $message
) {
    $result = red_addon_public_mutation_deployment_review($profile, $review);
    red_addon_public_mutation_deployment_review_test_assert(
        !$result['valid']
            && $result['reason'] === $reason
            && red_addon_public_mutation_deployment_review_valid($result),
        $message
    );
}

$serverBefore = $_SERVER;
$cookieBefore = $_COOKIE;
$requestBefore = $_REQUEST;
$headersBefore = headers_list();
$sessionBefore = session_status();

try {
    $profile = red_addon_public_mutation_deployment_profile(
        red_addon_public_mutation_deployment_review_test_profile()
    );
    $review = red_addon_public_mutation_deployment_review_test_packet();
    $review['profileHash'] = $profile['profileHash'];
    $valid = red_addon_public_mutation_deployment_review($profile, $review);

    red_addon_public_mutation_deployment_review_test_assert(
        $profile['valid']
            && $valid['valid']
            && $valid['reason'] === 'deployment_review_valid'
            && red_addon_public_mutation_deployment_review_valid($valid),
        'the closed deployment review packet validates against its profile'
    );
    red_addon_public_mutation_deployment_review_test_assert(
        $valid['profileHash'] === $profile['profileHash']
            && $valid['review']['server']['siteOrigin']
                === 'https://demo.example.test'
            && $valid['review']['server']['routeOrder'] === [
                'red_public_mutation_attestation',
                'php_server',
            ],
        'the review binds the canonical origin and attestation-before-PHP order'
    );
    red_addon_public_mutation_deployment_review_test_assert(
        $valid['review']['trust']['secretValuesRecorded'] === false
            && !str_contains(json_encode($valid), 'super-secret')
            && !array_key_exists('hmacKeyValue', $valid['review']['trust']),
        'the review contains only non-secret provisioning and rotation evidence'
    );
    red_addon_public_mutation_deployment_review_test_assert(
        $valid['review']['browser']['desktop']['viewportWidth'] === 1440
            && $valid['review']['browser']['mobile']['viewportWidth'] === 390
            && $valid['review']['browser']['clientStateChanged'] === false,
        'desktop/mobile browser evidence is bounded and read-only'
    );
    $repeat = red_addon_public_mutation_deployment_review($profile, $review);
    red_addon_public_mutation_deployment_review_test_assert(
        $valid['reviewHash'] === $repeat['reviewHash'],
        'the non-secret review hash is deterministic'
    );

    $profileMismatch = $review;
    $profileMismatch['profileHash'] = str_repeat('a', 64);
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $profileMismatch,
        'profile_hash_invalid',
        'review evidence cannot bind to another profile'
    );

    $unknownKey = $review;
    $unknownKey['secretValue'] = 'super-secret';
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $unknownKey,
        'review_shape_invalid',
        'secret-shaped or unknown review fields fail closed'
    );

    $invalidServer = $review;
    $invalidServer['server']['tlsMode'] = 'http';
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $invalidServer,
        'server_evidence_invalid',
        'TLS downgrade fails closed'
    );

    $invalidArtifacts = $review;
    $invalidArtifacts['server']['caddyfileOutsideStarter'] = false;
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $invalidArtifacts,
        'server_evidence_invalid',
        'deployment artifacts cannot reside in the clean starter'
    );

    $invalidProxy = $review;
    $invalidProxy['server']['proxyMode'] = 'request_headers';
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $invalidProxy,
        'server_evidence_invalid',
        'unreviewed proxy modes fail closed'
    );

    $invalidTrustSource = $review;
    $invalidTrustSource['trust']['hmacKeySource'] = 'request_header';
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $invalidTrustSource,
        'trust_evidence_invalid',
        'request headers cannot become the HMAC source'
    );

    $invalidRotation = $review;
    $invalidRotation['trust']['previousKeyRevoked'] = false;
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $invalidRotation,
        'trust_evidence_invalid',
        'unverified key rotation fails closed'
    );

    $invalidBrowser = $review;
    $invalidBrowser['browser']['mobile']['viewportWidth'] = 414;
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $invalidBrowser,
        'browser_evidence_invalid',
        'unreviewed mobile viewport evidence fails closed'
    );

    $invalidBrowserState = $review;
    $invalidBrowserState['browser']['clientStateChanged'] = true;
    red_addon_public_mutation_deployment_review_test_invalid(
        $profile,
        $invalidBrowserState,
        'browser_evidence_invalid',
        'browser evidence cannot claim client-state mutation'
    );

    $forgedResult = $valid;
    $forgedResult['reviewHash'] = str_repeat('f', 64);
    red_addon_public_mutation_deployment_review_test_assert(
        !red_addon_public_mutation_deployment_review_valid($forgedResult),
        'a forged review hash fails result validation'
    );

    $source = file_get_contents(
        $projectRoot
            . '/includes/addon_public_mutation_deployment_review_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_deployment_review_test_assert(
        is_string($source)
            && is_string($frontController)
            && preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|setcookie|session_start|session_id|getenv|'
                    . 'file_get_contents|file_put_contents|fopen|ob_start|'
                    . 'ob_end_clean|ob_get_clean|error_log|header|echo)\s*\(/',
                $source
            ) !== 1
            && strpos(
                $frontController,
                'addon_public_mutation_deployment_review_helpers.php'
            ) === false,
        'the deployment review is pure, non-emitting, and unlinked'
    );

    red_addon_public_mutation_deployment_review_test_assert(
        $_SERVER === $serverBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && session_status() === $sessionBefore,
        'review validation changes no request, cookie, header, or session state'
    );

    echo 'Public-mutation deployment review self-test passed (' .
        $assertions . " assertions).\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
