<?php
/**
 * Dependency-free checks for the non-executing per-client deployment profile.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_public_mutation_deployment_profile_helpers.php';

$assertions = 0;

function red_addon_public_mutation_deployment_profile_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_deployment_profile_test_fixture(
    $proxyMode = 'none'
) {
    return [
        'clientId' => 'demo-red-sphere',
        'databaseName' => 'redcms_demo_red_sphere',
        'trustedOrigin' => 'https://demo.example.test',
        'server' => [
            'runtime' => 'frankenphp',
            'frankenphpVersion' => '1.12.4',
            'caddyVersion' => '2.11.4',
            'tlsMode' => 'https',
            'proxyMode' => $proxyMode,
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

function red_addon_public_mutation_deployment_profile_test_direct_fixture()
{
    $profile = red_addon_public_mutation_deployment_profile_test_fixture();
    $profile['server'] = [
        'runtime' => 'apache_php',
        'apacheVersion' => '2.4.65',
        'phpVersion' => '8.4.13',
        'sapi' => 'apache2handler',
        'tlsMode' => 'https',
        'proxyMode' => 'none',
    ];
    $profile['ingress'] = [
        'profile' => 'direct_php',
        'projectionVersion' => 'v1',
        'trustedOriginSource' => 'process_environment',
        'routeOrder' => [
            'apache_https',
            'php_server_projection',
            'red_direct_php_ingress',
        ],
        'directHttpsRequired' => true,
        'hostIgnored' => true,
        'forwardedHeadersIgnored' => true,
        'hmacRequired' => false,
    ];
    return $profile;
}

function red_addon_public_mutation_deployment_profile_test_invalid(
    array $profile,
    $reason,
    $message
) {
    $result = red_addon_public_mutation_deployment_profile($profile);
    red_addon_public_mutation_deployment_profile_test_assert(
        !$result['valid']
            && $result['reason'] === $reason
            && red_addon_public_mutation_deployment_profile_valid($result),
        $message
    );
}

$serverBefore = $_SERVER;
$cookieBefore = $_COOKIE;
$headersBefore = headers_list();
$sessionBefore = session_status();

try {
    $profile = red_addon_public_mutation_deployment_profile_test_fixture();
    $valid = red_addon_public_mutation_deployment_profile($profile);
    red_addon_public_mutation_deployment_profile_test_assert(
        $valid['valid']
            && $valid['reason'] === 'profile_valid'
            && red_addon_public_mutation_deployment_profile_valid($valid),
        'the closed generic client deployment profile validates'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $valid['profile']['trustedOrigin'] === 'https://demo.example.test'
            && $valid['profile']['databaseName']
                === 'redcms_demo_red_sphere',
        'the normalized profile preserves the canonical origin and client database scope'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $valid['profile']['server'] === $profile['server']
            && $valid['profile']['ingress'] === $profile['ingress'],
        'pinned server and ingress review facts remain deterministic'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $valid['profile']['response'] === $profile['response']
            && $valid['profile']['activation'] === $profile['activation'],
        'core response ownership and all activation flags remain disabled'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $valid['profile']['subjectCookie'] === $profile['subjectCookie']
            && $valid['profile']['subjectCookie']['domain'] === '',
        'the fixed host-only subject-cookie policy is preserved'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $valid['profile']['isolation'] === $profile['isolation'],
        'all client configuration, binary, secret, media, and database isolation flags are required'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $valid['profileHash'] ===
            red_addon_public_mutation_deployment_profile($profile)['profileHash'],
        'the non-secret deployment profile hash is deterministic'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        !array_key_exists('secretValue', $valid['profile'])
            && !str_contains(json_encode($valid), 'super-secret'),
        'the profile result contains no secret value or secret-shaped field'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $_SERVER === $serverBefore
            && $_COOKIE === $cookieBefore
            && headers_list() === $headersBefore
            && session_status() === $sessionBefore,
        'profile validation changes no request, cookie, header, or session state'
    );

    $unknownKey = $profile;
    $unknownKey['secretValue'] = 'super-secret';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $unknownKey,
        'profile_shape_invalid',
        'unknown or secret-shaped profile fields fail closed'
    );

    $invalidClient = $profile;
    $invalidClient['clientId'] = 'Demo Client';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidClient,
        'client_id_invalid',
        'client identifiers must be lowercase deployment slugs'
    );

    $invalidDatabase = $profile;
    $invalidDatabase['databaseName'] = 'redcms_v51_starter';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidDatabase,
        'database_invalid',
        'the clean starter database cannot be selected as a client database'
    );

    $invalidOrigin = $profile;
    $invalidOrigin['trustedOrigin'] = 'http://demo.example.test';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidOrigin,
        'origin_invalid',
        'the trusted origin requires canonical HTTPS'
    );

    $originPath = $profile;
    $originPath['trustedOrigin'] = 'https://demo.example.test/site';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $originPath,
        'origin_invalid',
        'the trusted origin cannot contain a path'
    );

    $invalidServer = $profile;
    $invalidServer['server']['caddyVersion'] = '2.10.0';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidServer,
        'server_invalid',
        'unreviewed server version drift fails closed'
    );

    $invalidProxy = $profile;
    $invalidProxy['server']['proxyMode'] = 'request_headers';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidProxy,
        'server_invalid',
        'an unreviewed proxy mode fails closed'
    );

    $invalidKeyEnvironment = $profile;
    $invalidKeyEnvironment['ingress']['hmacKeyEnvironment'] =
        'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY_VALUE';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidKeyEnvironment,
        'ingress_invalid',
        'the profile accepts only the fixed HMAC environment-name boundary'
    );

    $invalidKeySource = $profile;
    $invalidKeySource['ingress']['hmacKeySource'] = 'request_header';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidKeySource,
        'ingress_invalid',
        'a request value cannot become the HMAC key source'
    );

    $invalidTrustedSource = $profile;
    $invalidTrustedSource['ingress']['trustedOriginSource'] = 'Host';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidTrustedSource,
        'ingress_invalid',
        'Host cannot become the trusted-origin source'
    );

    $invalidRouteOrder = $profile;
    $invalidRouteOrder['ingress']['routeOrder'] = [
        'php_server',
        'red_public_mutation_attestation',
    ];
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidRouteOrder,
        'ingress_invalid',
        'attestation must precede PHP in the reviewed route order'
    );

    $invalidResponseOwner = $profile;
    $invalidResponseOwner['response']['owner'] = 'package';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidResponseOwner,
        'response_owner_invalid',
        'packages cannot own the public response'
    );

    $invalidEmitter = $profile;
    $invalidEmitter['response']['emitter'] = 'theme';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidEmitter,
        'response_owner_invalid',
        'themes cannot replace the core response emitter'
    );

    $invalidCookieDomain = $profile;
    $invalidCookieDomain['subjectCookie']['domain'] = '.example.test';
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidCookieDomain,
        'cookie_policy_invalid',
        'cross-subdomain cookie scope fails closed'
    );

    $invalidCookieLifetime = $profile;
    $invalidCookieLifetime['subjectCookie']['maxAgeSeconds'] = 3600;
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidCookieLifetime,
        'cookie_policy_invalid',
        'cookie lifetime drift fails closed'
    );

    $invalidIsolation = $profile;
    $invalidIsolation['isolation']['mediaOutsideStarter'] = false;
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidIsolation,
        'isolation_invalid',
        'client media must remain outside the clean starter'
    );

    $invalidActivation = $profile;
    $invalidActivation['activation']['dispatcherLinked'] = true;
    red_addon_public_mutation_deployment_profile_test_invalid(
        $invalidActivation,
        'activation_invalid',
        'a review profile cannot link the dispatcher'
    );

    $proxyProfile = red_addon_public_mutation_deployment_profile(
        red_addon_public_mutation_deployment_profile_test_fixture(
            'operator_trusted'
        )
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $proxyProfile['valid']
            && $proxyProfile['profile']['server']['proxyMode']
                === 'operator_trusted',
        'an explicitly reviewed operator-trusted proxy mode remains representable'
    );

    $directProfile =
        red_addon_public_mutation_deployment_profile_test_direct_fixture();
    $directProfile['databaseName'] = 'orojas_demo_redsphere';
    $direct = red_addon_public_mutation_deployment_profile($directProfile);
    red_addon_public_mutation_deployment_profile_test_assert(
        $direct['valid']
            && red_addon_public_mutation_deployment_profile_valid($direct),
        'the explicit direct-PHP deployment profile validates independently'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $direct['profile']['databaseName'] === 'orojas_demo_redsphere',
        'a safe host-prefixed per-client database name remains representable'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        $direct['profile']['server'] === $directProfile['server']
            && $direct['profile']['ingress'] === $directProfile['ingress']
            && $direct['profileHash'] !== $valid['profileHash'],
        'the pinned Apache PHP projection and profile hash are deterministic'
    );
    red_addon_public_mutation_deployment_profile_test_assert(
        !str_contains(json_encode($direct), 'HMAC')
            && $direct['profile']['ingress']['hmacRequired'] === false
            && $direct['profile']['activation'] === $profile['activation'],
        'direct PHP invents no HMAC secret and keeps every activation disabled'
    );

    foreach ([
        ['apacheVersion', '2.2.34'],
        ['phpVersion', '8.1.31'],
        ['sapi', 'cli'],
        ['proxyMode', 'operator_trusted'],
    ] as [$field, $value]) {
        $candidate = $directProfile;
        $candidate['server'][$field] = $value;
        red_addon_public_mutation_deployment_profile_test_invalid(
            $candidate,
            'server_invalid',
            'unsupported direct-PHP server drift fails closed: ' . $field
        );
    }

    $directRouteDrift = $directProfile;
    $directRouteDrift['ingress']['routeOrder'] = [
        'php_server_projection',
        'apache_https',
        'red_direct_php_ingress',
    ];
    red_addon_public_mutation_deployment_profile_test_invalid(
        $directRouteDrift,
        'ingress_invalid',
        'direct-PHP projection cannot precede the Apache HTTPS boundary'
    );

    $cgiProfile = $directProfile;
    $cgiProfile['server']['sapi'] = 'cgi-fcgi';
    $cgi = red_addon_public_mutation_deployment_profile($cgiProfile);
    red_addon_public_mutation_deployment_profile_test_assert(
        $cgi['valid'] && $cgi['profile']['server']['sapi'] === 'cgi-fcgi',
        'an explicitly pinned Apache CGI/FastCGI projection is representable'
    );

    $directForwardingDrift = $directProfile;
    $directForwardingDrift['ingress']['forwardedHeadersIgnored'] = false;
    red_addon_public_mutation_deployment_profile_test_invalid(
        $directForwardingDrift,
        'ingress_invalid',
        'forwarded headers cannot become direct-PHP transport trust'
    );

    $directHmacDrift = $directProfile;
    $directHmacDrift['ingress']['hmacRequired'] = true;
    red_addon_public_mutation_deployment_profile_test_invalid(
        $directHmacDrift,
        'ingress_invalid',
        'the direct profile cannot claim an unused HMAC trust boundary'
    );

    foreach (['mysql', 'redcms_acceptance_123_456'] as $reservedDatabase) {
        $candidate = $directProfile;
        $candidate['databaseName'] = $reservedDatabase;
        red_addon_public_mutation_deployment_profile_test_invalid(
            $candidate,
            'database_invalid',
            'system and disposable database names remain unavailable'
        );
    }

    echo 'Public-mutation deployment profile self-test passed (' .
        $assertions . " assertions).\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
