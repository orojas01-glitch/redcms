<?php
/**
 * Dependency-free checks for the non-emitting core response owner.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_response_owner_helpers.php';

$assertions = 0;

function red_addon_public_mutation_response_owner_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_response_owner_test_fixture()
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

function red_addon_public_mutation_response_owner_test_cookie(
    $token
) {
    return 'redcms_public_mutation_subject=' . $token
        . '; Max-Age=1800; Path=/; Secure; HttpOnly; SameSite=Strict';
}

$serverBefore = $_SERVER;
$cookieBefore = $_COOKIE;
$requestBefore = $_REQUEST;
$headersBefore = headers_list();
$sessionBefore = session_status();

try {
    $profile = red_addon_public_mutation_deployment_profile(
        red_addon_public_mutation_response_owner_test_fixture()
    );
    $response = red_addon_public_mutation_response_success('accepted');
    $resolved = red_addon_public_mutation_subject_cookie_lifecycle_set_result(
        'resolved',
        9,
        9,
        '',
        '',
        'subject_cookie_resolved'
    );
    $issued = red_addon_public_mutation_subject_cookie_lifecycle_set_result(
        'issued',
        10,
        0,
        red_addon_public_mutation_response_owner_test_cookie(
            str_repeat('a', 64)
        ),
        '',
        'subject_cookie_issued'
    );
    $cleared = red_addon_public_mutation_subject_cookie_lifecycle_set_result(
        'cleared',
        0,
        10,
        '',
        red_addon_public_mutation_subject_cookie_clear_serialize(),
        'subject_cookie_cleared'
    );
    $rotated = red_addon_public_mutation_subject_cookie_lifecycle_set_result(
        'rotated',
        11,
        10,
        red_addon_public_mutation_response_owner_test_cookie(
            str_repeat('b', 64)
        ),
        red_addon_public_mutation_subject_cookie_clear_serialize(),
        'subject_cookie_rotated'
    );

    red_addon_public_mutation_response_owner_test_assert(
        $profile['valid']
            && red_addon_public_mutation_response_emitter_valid($response),
        'the response-owner fixture starts with a valid profile and envelope'
    );

    $resolvedResult =
        red_addon_public_mutation_response_owner_compose(
            $profile,
            $response,
            $resolved
        );
    red_addon_public_mutation_response_owner_test_assert(
        $resolvedResult['valid']
            && $resolvedResult['reason'] === 'response_owner_valid'
            && $resolvedResult['profileHash'] === $profile['profileHash']
            && $resolvedResult['response'] === $response
            && $resolvedResult['setCookieValues'] === []
            && red_addon_public_mutation_response_owner_result_valid(
                $resolvedResult
            ),
        'a resolved subject composes the fixed response without a cookie line'
    );

    $issuedResult = red_addon_public_mutation_response_owner_compose(
        $profile,
        $response,
        $issued
    );
    red_addon_public_mutation_response_owner_test_assert(
        $issuedResult['valid']
            && $issuedResult['setCookieValues'] === [
                $issued['setCookieValue'],
            ]
            && !str_contains(
                $issuedResult['response']['body'],
                str_repeat('a', 64)
            ),
        'issuance adds exactly one fixed host-only Set-Cookie descriptor'
    );

    $clearedResult = red_addon_public_mutation_response_owner_compose(
        $profile,
        $response,
        $cleared
    );
    red_addon_public_mutation_response_owner_test_assert(
        $clearedResult['valid']
            && $clearedResult['setCookieValues'] === [
                $cleared['clearCookieValue'],
            ],
        'clear adds exactly one fixed deletion descriptor'
    );

    $rotatedResult = red_addon_public_mutation_response_owner_compose(
        $profile,
        $response,
        $rotated
    );
    red_addon_public_mutation_response_owner_test_assert(
        $rotatedResult['valid']
            && $rotatedResult['setCookieValues'] === [
                $rotated['clearCookieValue'],
                $rotated['setCookieValue'],
            ]
            && red_addon_public_mutation_response_owner_result_valid(
                $rotatedResult
            ),
        'rotation orders one deletion before one replacement descriptor'
    );

    $forgedOwnerResult = $rotatedResult;
    $forgedOwnerResult['setCookieValues'] = [
        $rotated['setCookieValue'],
        $rotated['clearCookieValue'],
    ];
    red_addon_public_mutation_response_owner_test_assert(
        !red_addon_public_mutation_response_owner_result_valid(
            $forgedOwnerResult
        ),
        'a forged response result cannot reverse clear-before-set ordering'
    );

    $noLifecycleResult = red_addon_public_mutation_response_owner_compose(
        $profile,
        $response
    );
    red_addon_public_mutation_response_owner_test_assert(
        $noLifecycleResult['valid']
            && $noLifecycleResult['setCookieValues'] === [],
        'omitting lifecycle state is equivalent to no cookie mutation'
    );

    $invalidProfile = $profile;
    $invalidProfile['profile']['response']['owner'] = 'package';
    red_addon_public_mutation_response_owner_test_assert(
        !red_addon_public_mutation_response_owner_compose(
            $invalidProfile,
            $response,
            $resolved
        )['valid'],
        'package response ownership cannot reach the composer'
    );

    $linkedProfile = $profile;
    $linkedProfile['profile']['activation']['dispatcherLinked'] = true;
    red_addon_public_mutation_response_owner_test_assert(
        red_addon_public_mutation_response_owner_compose(
            $linkedProfile,
            $response,
            $resolved
        )['reason'] === 'deployment_profile_invalid',
        'a linked dispatcher profile remains outside this pre-link composer'
    );

    $forgedResponse = $response;
    $forgedResponse['headers']['Set-Cookie'] = 'forbidden=1';
    red_addon_public_mutation_response_owner_test_assert(
        red_addon_public_mutation_response_owner_compose(
            $profile,
            $forgedResponse,
            $resolved
        )['reason'] === 'response_invalid',
        'arbitrary response headers fail before composition'
    );

    $forgedLifecycle = $issued;
    $forgedLifecycle['setCookieValue'] .= '; Domain=.example.test';
    red_addon_public_mutation_response_owner_test_assert(
        red_addon_public_mutation_response_owner_compose(
            $profile,
            $response,
            $forgedLifecycle
        )['reason'] === 'lifecycle_invalid',
        'cookie attribute drift fails before response ownership'
    );

    $invalidLifecycle = $resolved;
    $invalidLifecycle['state'] = 'issued';
    red_addon_public_mutation_response_owner_test_assert(
        red_addon_public_mutation_response_owner_compose(
            $profile,
            $response,
            $invalidLifecycle
        )['reason'] === 'lifecycle_invalid',
        'state and descriptor disagreement fails closed'
    );

    $source = file_get_contents(
        $projectRoot
            . '/includes/addon_public_mutation_response_owner_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_response_owner_test_assert(
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
                'addon_public_mutation_response_owner_helpers.php'
            ) === false,
        'the composer is pure, non-emitting, and unlinked from the front controller'
    );

    red_addon_public_mutation_response_owner_test_assert(
        $_SERVER === $serverBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && session_status() === $sessionBefore,
        'composition changes no request, cookie, header, or session state'
    );

    echo 'Public-mutation response-owner self-test passed (' .
        $assertions . " assertions).\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
