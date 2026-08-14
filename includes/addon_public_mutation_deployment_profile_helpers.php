<?php
/**
 * Pure, non-executing per-client deployment profile validation for the future
 * public-mutation dispatcher.
 *
 * The profile is an operator-owned review packet, not runtime configuration.
 * It contains no secret value and is never loaded by index.php. This helper
 * reads no request/global state, filesystem, database, package, lifecycle, or
 * response state; it only validates a closed deployment shape and returns a
 * deterministic non-secret profile hash.
 */

require_once __DIR__ . '/addon_public_mutation_http_request_helpers.php';
require_once __DIR__ . '/addon_public_mutation_subject_cookie_helpers.php';

if (!function_exists('red_addon_public_mutation_deployment_profile_result')) {
    function red_addon_public_mutation_deployment_profile_result(
        $reason = 'deployment_profile_invalid'
    ) {
        $allowedReasons = [
            'deployment_profile_invalid',
            'profile_shape_invalid',
            'client_id_invalid',
            'database_invalid',
            'origin_invalid',
            'server_invalid',
            'ingress_invalid',
            'response_owner_invalid',
            'cookie_policy_invalid',
            'isolation_invalid',
            'activation_invalid',
            'profile_valid',
        ];
        $reason = is_string($reason) && in_array(
            $reason,
            $allowedReasons,
            true
        ) ? $reason : 'deployment_profile_invalid';
        return [
            'valid' => false,
            'profileHash' => '',
            'profile' => [],
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_deployment_profile_keys_valid')) {
    function red_addon_public_mutation_deployment_profile_keys_valid(
        $value,
        array $keys
    ) {
        return is_array($value) && array_keys($value) === $keys;
    }
}

if (!function_exists('red_addon_public_mutation_deployment_profile_apache_version_valid')) {
    function red_addon_public_mutation_deployment_profile_apache_version_valid(
        $version
    ) {
        return is_string($version)
            && preg_match('/\A2\.4\.[0-9]{1,3}\z/D', $version) === 1;
    }
}

if (!function_exists('red_addon_public_mutation_deployment_profile_php_version_valid')) {
    function red_addon_public_mutation_deployment_profile_php_version_valid(
        $version
    ) {
        return is_string($version)
            && preg_match('/\A8\.[2-5]\.[0-9]{1,3}\z/D', $version) === 1;
    }
}

if (!function_exists('red_addon_public_mutation_deployment_profile_normalize')) {
    /**
     * Validates one explicit, non-secret deployment review packet.
     */
    function red_addon_public_mutation_deployment_profile_normalize($profile)
    {
        $invalid = static function ($reason) {
            return red_addon_public_mutation_deployment_profile_result(
                $reason
            );
        };

        if (!red_addon_public_mutation_deployment_profile_keys_valid(
            $profile,
            [
                'clientId',
                'databaseName',
                'trustedOrigin',
                'server',
                'ingress',
                'response',
                'subjectCookie',
                'isolation',
                'activation',
            ]
        )) {
            return $invalid('profile_shape_invalid');
        }

        $clientId = $profile['clientId'];
        if (!is_string($clientId)
            || preg_match(
                '/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D',
                $clientId
            ) !== 1
        ) {
            return $invalid('client_id_invalid');
        }

        $databaseName = $profile['databaseName'];
        if (!is_string($databaseName)
            || preg_match('/\A[a-z0-9][a-z0-9_]{2,63}\z/D', $databaseName)
                !== 1
            || in_array(
                $databaseName,
                [
                    'information_schema',
                    'mysql',
                    'performance_schema',
                    'redcms_v51_starter',
                    'redcms_dev',
                    'sys',
                ],
                true
            )
            || str_starts_with($databaseName, 'redcms_acceptance_')
        ) {
            return $invalid('database_invalid');
        }

        $trustedOrigin =
            red_addon_public_mutation_http_request_trusted_origin(
                $profile['trustedOrigin']
            );
        if ($trustedOrigin === '') {
            return $invalid('origin_invalid');
        }

        $server = $profile['server'];
        $ingress = $profile['ingress'];
        $normalizedServer = null;
        $normalizedIngress = null;

        if (red_addon_public_mutation_deployment_profile_keys_valid(
            $server,
            [
                'runtime',
                'frankenphpVersion',
                'caddyVersion',
                'tlsMode',
                'proxyMode',
            ]
        )) {
            if ($server['runtime'] !== 'frankenphp'
                || $server['frankenphpVersion'] !== '1.12.4'
                || $server['caddyVersion'] !== '2.11.4'
                || $server['tlsMode'] !== 'https'
                || !in_array(
                    $server['proxyMode'],
                    ['none', 'operator_trusted'],
                    true
                )
            ) {
                return $invalid('server_invalid');
            }
            $normalizedServer = [
                'runtime' => 'frankenphp',
                'frankenphpVersion' => '1.12.4',
                'caddyVersion' => '2.11.4',
                'tlsMode' => 'https',
                'proxyMode' => $server['proxyMode'],
            ];
        } elseif (red_addon_public_mutation_deployment_profile_keys_valid(
            $server,
            [
                'runtime',
                'apacheVersion',
                'phpVersion',
                'sapi',
                'tlsMode',
                'proxyMode',
            ]
        )) {
            if ($server['runtime'] !== 'apache_php'
                || !red_addon_public_mutation_deployment_profile_apache_version_valid(
                    $server['apacheVersion']
                )
                || !red_addon_public_mutation_deployment_profile_php_version_valid(
                    $server['phpVersion']
                )
                || !in_array(
                    $server['sapi'],
                    ['apache2handler', 'cgi-fcgi', 'fpm-fcgi'],
                    true
                )
                || $server['tlsMode'] !== 'https'
                || $server['proxyMode'] !== 'none'
            ) {
                return $invalid('server_invalid');
            }
            $normalizedServer = [
                'runtime' => 'apache_php',
                'apacheVersion' => $server['apacheVersion'],
                'phpVersion' => $server['phpVersion'],
                'sapi' => $server['sapi'],
                'tlsMode' => 'https',
                'proxyMode' => 'none',
            ];
        } else {
            return $invalid('server_invalid');
        }

        if ($normalizedServer['runtime'] === 'frankenphp') {
            if (!red_addon_public_mutation_deployment_profile_keys_valid(
                $ingress,
                [
                    'captureVersion',
                    'hmacKeyEnvironment',
                    'hmacKeySource',
                    'trustedOriginSource',
                    'routeOrder',
                    'keyRotation',
                ]
            )
                || $ingress['captureVersion'] !== 'v1'
                || $ingress['hmacKeyEnvironment']
                    !== 'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY'
                || $ingress['hmacKeySource'] !== 'process_environment'
                || !in_array(
                    $ingress['trustedOriginSource'],
                    ['process_environment', 'ignored_local_config'],
                    true
                )
                || $ingress['routeOrder'] !== [
                    'red_public_mutation_attestation',
                    'php_server',
                ]
                || $ingress['keyRotation'] !== 'operator_owned'
            ) {
                return $invalid('ingress_invalid');
            }
            $normalizedIngress = [
                'captureVersion' => 'v1',
                'hmacKeyEnvironment' =>
                    'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY',
                'hmacKeySource' => 'process_environment',
                'trustedOriginSource' => $ingress['trustedOriginSource'],
                'routeOrder' => [
                    'red_public_mutation_attestation',
                    'php_server',
                ],
                'keyRotation' => 'operator_owned',
            ];
        } else {
            if (!red_addon_public_mutation_deployment_profile_keys_valid(
                $ingress,
                [
                    'profile',
                    'projectionVersion',
                    'trustedOriginSource',
                    'routeOrder',
                    'directHttpsRequired',
                    'hostIgnored',
                    'forwardedHeadersIgnored',
                    'hmacRequired',
                ]
            )
                || $ingress['profile'] !== 'direct_php'
                || $ingress['projectionVersion'] !== 'v1'
                || !in_array(
                    $ingress['trustedOriginSource'],
                    ['process_environment', 'ignored_local_config'],
                    true
                )
                || $ingress['routeOrder'] !== [
                    'apache_https',
                    'php_server_projection',
                    'red_direct_php_ingress',
                ]
                || $ingress['directHttpsRequired'] !== true
                || $ingress['hostIgnored'] !== true
                || $ingress['forwardedHeadersIgnored'] !== true
                || $ingress['hmacRequired'] !== false
            ) {
                return $invalid('ingress_invalid');
            }
            $normalizedIngress = [
                'profile' => 'direct_php',
                'projectionVersion' => 'v1',
                'trustedOriginSource' => $ingress['trustedOriginSource'],
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
        }

        $response = $profile['response'];
        if (!red_addon_public_mutation_deployment_profile_keys_valid(
            $response,
            [
                'owner',
                'emitter',
                'browserCookieOwner',
                'packageMayEmitHeaders',
                'frontControllerLinked',
            ]
        )
            || $response['owner'] !== 'core'
            || $response['emitter'] !== 'core_public_mutation_response_emitter'
            || $response['browserCookieOwner'] !== 'core'
            || $response['packageMayEmitHeaders'] !== false
            || $response['frontControllerLinked'] !== false
        ) {
            return $invalid('response_owner_invalid');
        }

        $subjectCookie = $profile['subjectCookie'];
        if (!red_addon_public_mutation_deployment_profile_keys_valid(
            $subjectCookie,
            [
                'name',
                'domain',
                'path',
                'secure',
                'httpOnly',
                'sameSite',
                'maxAgeSeconds',
            ]
        )
            || $subjectCookie['name']
                !== red_addon_public_mutation_subject_cookie_name()
            || $subjectCookie['domain'] !== ''
            || $subjectCookie['path'] !== '/'
            || $subjectCookie['secure'] !== true
            || $subjectCookie['httpOnly'] !== true
            || $subjectCookie['sameSite'] !== 'Strict'
            || $subjectCookie['maxAgeSeconds']
                !== red_addon_public_mutation_subject_lifetime_seconds()
        ) {
            return $invalid('cookie_policy_invalid');
        }

        $isolation = $profile['isolation'];
        if (!red_addon_public_mutation_deployment_profile_keys_valid(
            $isolation,
            [
                'databaseScoped',
                'configurationOutsideStarter',
                'binaryOutsideStarter',
                'secretsOutsideStarter',
                'mediaOutsideStarter',
            ]
        )
            || $isolation !== [
                'databaseScoped' => true,
                'configurationOutsideStarter' => true,
                'binaryOutsideStarter' => true,
                'secretsOutsideStarter' => true,
                'mediaOutsideStarter' => true,
            ]
        ) {
            return $invalid('isolation_invalid');
        }

        $activation = $profile['activation'];
        if (!red_addon_public_mutation_deployment_profile_keys_valid(
            $activation,
            [
                'dispatcherLinked',
                'dispatcherEnabled',
                'packageEnabled',
                'storeLiteEnabled',
            ]
        )
            || $activation !== [
                'dispatcherLinked' => false,
                'dispatcherEnabled' => false,
                'packageEnabled' => false,
                'storeLiteEnabled' => false,
            ]
        ) {
            return $invalid('activation_invalid');
        }

        $normalized = [
            'clientId' => $clientId,
            'databaseName' => $databaseName,
            'trustedOrigin' => $trustedOrigin,
            'server' => $normalizedServer,
            'ingress' => $normalizedIngress,
            'response' => [
                'owner' => 'core',
                'emitter' => 'core_public_mutation_response_emitter',
                'browserCookieOwner' => 'core',
                'packageMayEmitHeaders' => false,
                'frontControllerLinked' => false,
            ],
            'subjectCookie' => [
                'name' => red_addon_public_mutation_subject_cookie_name(),
                'domain' => '',
                'path' => '/',
                'secure' => true,
                'httpOnly' => true,
                'sameSite' => 'Strict',
                'maxAgeSeconds' =>
                    red_addon_public_mutation_subject_lifetime_seconds(),
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
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return $invalid('deployment_profile_invalid');
        }
        return [
            'valid' => true,
            'profileHash' => hash('sha256', $encoded),
            'profile' => $normalized,
            'reason' => 'profile_valid',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_deployment_profile')) {
    function red_addon_public_mutation_deployment_profile($profile)
    {
        return red_addon_public_mutation_deployment_profile_normalize(
            $profile
        );
    }
}

if (!function_exists('red_addon_public_mutation_deployment_profile_valid')) {
    function red_addon_public_mutation_deployment_profile_valid($result)
    {
        if (!is_array($result)
            || array_keys($result) !== [
                'valid',
                'profileHash',
                'profile',
                'reason',
            ]
            || !is_bool($result['valid'])
            || !is_string($result['profileHash'])
            || !is_array($result['profile'])
            || !is_string($result['reason'])
        ) {
            return false;
        }
        if (!$result['valid']) {
            return $result ===
                red_addon_public_mutation_deployment_profile_result(
                    $result['reason']
                );
        }
        $normalized = red_addon_public_mutation_deployment_profile_normalize(
            $result['profile']
        );
        return $normalized['valid'] === true
            && $result['reason'] === 'profile_valid'
            && $result['profileHash'] === $normalized['profileHash']
            && $result['profile'] === $normalized['profile'];
    }
}

?>
