<?php
/**
 * Pure, non-executing evidence validation for one client deployment review.
 *
 * This helper binds an operator-owned deployment/evidence packet to the
 * existing non-executing profile hash. It accepts no secret value, file body,
 * request/global state, database connection, package code, browser session,
 * or front-controller link. It returns only normalized non-secret evidence
 * and a deterministic review hash.
 */

require_once __DIR__ . '/addon_public_mutation_deployment_profile_helpers.php';

if (!function_exists('red_addon_public_mutation_deployment_review_result')) {
    function red_addon_public_mutation_deployment_review_result(
        $reason = 'deployment_review_invalid'
    ) {
        $allowedReasons = [
            'deployment_review_invalid',
            'profile_invalid',
            'review_shape_invalid',
            'profile_hash_invalid',
            'server_evidence_invalid',
            'trust_evidence_invalid',
            'browser_evidence_invalid',
        ];
        $reason = is_string($reason) && in_array(
            $reason,
            $allowedReasons,
            true
        ) ? $reason : 'deployment_review_invalid';
        return [
            'valid' => false,
            'profileHash' => '',
            'reviewHash' => '',
            'review' => [],
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_keys_valid')) {
    function red_addon_public_mutation_deployment_review_keys_valid(
        $value,
        array $keys
    ) {
        return is_array($value) && array_keys($value) === $keys;
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_sha256_valid')) {
    function red_addon_public_mutation_deployment_review_sha256_valid($value)
    {
        return is_string($value) && red_addon_valid_sha256($value);
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_browser_case')) {
    function red_addon_public_mutation_deployment_review_browser_case(
        $case,
        $expectedWidth,
        $expectedHeight
    ) {
        if (!red_addon_public_mutation_deployment_review_keys_valid(
            $case,
            [
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
            ]
        )
            || $case['viewportWidth'] !== $expectedWidth
            || $case['viewportHeight'] !== $expectedHeight
            || $case['httpsLoaded'] !== true
            || $case['statusCode'] !== 200
            || $case['consoleErrors'] !== 0
            || $case['networkErrors'] !== 0
            || $case['responseHeadersMatched'] !== true
            || $case['cookiePolicyMatched'] !== true
            || $case['tokenAbsentFromBody'] !== true
            || !red_addon_public_mutation_deployment_review_sha256_valid(
                $case['evidenceSHA256']
            )
        ) {
            return null;
        }
        return [
            'viewportWidth' => $expectedWidth,
            'viewportHeight' => $expectedHeight,
            'httpsLoaded' => true,
            'statusCode' => 200,
            'consoleErrors' => 0,
            'networkErrors' => 0,
            'responseHeadersMatched' => true,
            'cookiePolicyMatched' => true,
            'tokenAbsentFromBody' => true,
            'evidenceSHA256' => $case['evidenceSHA256'],
        ];
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_server')) {
    function red_addon_public_mutation_deployment_review_server(
        $profile,
        $server
    ) {
        if (!is_array($profile) || !is_array($server)
            || !isset($profile['server']['runtime'])
        ) {
            return null;
        }

        if ($profile['server']['runtime'] === 'frankenphp') {
            if (!red_addon_public_mutation_deployment_review_keys_valid(
                $server,
                [
                    'runtime',
                    'frankenphpVersion',
                    'caddyVersion',
                    'tlsMode',
                    'proxyMode',
                    'siteOrigin',
                    'routeOrder',
                    'dispatcherLinked',
                    'deploymentRootOutsideStarter',
                    'binaryOutsideStarter',
                    'caddyfileOutsideStarter',
                    'certificatesOutsideStarter',
                    'caddyfileSHA256',
                    'binarySHA256',
                    'certificateChainSHA256',
                ]
            )
                || $server['runtime'] !== $profile['server']['runtime']
                || $server['frankenphpVersion']
                    !== $profile['server']['frankenphpVersion']
                || $server['caddyVersion']
                    !== $profile['server']['caddyVersion']
                || $server['tlsMode'] !== 'https'
                || $server['proxyMode'] !== $profile['server']['proxyMode']
                || $server['siteOrigin'] !== $profile['trustedOrigin']
                || $server['routeOrder'] !== $profile['ingress']['routeOrder']
                || $server['dispatcherLinked'] !== false
                || $server['deploymentRootOutsideStarter'] !== true
                || $server['binaryOutsideStarter'] !== true
                || $server['caddyfileOutsideStarter'] !== true
                || $server['certificatesOutsideStarter'] !== true
                || !red_addon_public_mutation_deployment_review_sha256_valid(
                    $server['caddyfileSHA256']
                )
                || !red_addon_public_mutation_deployment_review_sha256_valid(
                    $server['binarySHA256']
                )
                || !red_addon_public_mutation_deployment_review_sha256_valid(
                    $server['certificateChainSHA256']
                )
            ) {
                return null;
            }
            return $server;
        }

        if ($profile['server']['runtime'] !== 'apache_php'
            || !red_addon_public_mutation_deployment_review_keys_valid(
                $server,
                [
                    'runtime',
                    'apacheVersion',
                    'phpVersion',
                    'sapi',
                    'tlsMode',
                    'proxyMode',
                    'siteOrigin',
                    'routeOrder',
                    'dispatcherLinked',
                    'deploymentRootOutsideStarter',
                    'configurationOutsideStarter',
                    'certificatesOutsideStarter',
                    'apacheConfigSHA256',
                    'runtimeEvidenceSHA256',
                    'certificateChainSHA256',
                    'projectionEvidenceSHA256',
                    'projectionVerified',
                ]
            )
            || $server['runtime'] !== 'apache_php'
            || $server['apacheVersion']
                !== $profile['server']['apacheVersion']
            || $server['phpVersion'] !== $profile['server']['phpVersion']
            || $server['sapi'] !== $profile['server']['sapi']
            || $server['tlsMode'] !== 'https'
            || $server['proxyMode'] !== 'none'
            || $server['siteOrigin'] !== $profile['trustedOrigin']
            || $server['routeOrder'] !== $profile['ingress']['routeOrder']
            || $server['dispatcherLinked'] !== false
            || $server['deploymentRootOutsideStarter'] !== true
            || $server['configurationOutsideStarter'] !== true
            || $server['certificatesOutsideStarter'] !== true
            || $server['projectionVerified'] !== true
            || !red_addon_public_mutation_deployment_review_sha256_valid(
                $server['apacheConfigSHA256']
            )
            || !red_addon_public_mutation_deployment_review_sha256_valid(
                $server['runtimeEvidenceSHA256']
            )
            || !red_addon_public_mutation_deployment_review_sha256_valid(
                $server['certificateChainSHA256']
            )
            || !red_addon_public_mutation_deployment_review_sha256_valid(
                $server['projectionEvidenceSHA256']
            )
        ) {
            return null;
        }
        return $server;
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_trust')) {
    function red_addon_public_mutation_deployment_review_trust(
        $profile,
        $trust
    ) {
        if (!is_array($profile) || !is_array($trust)
            || !isset($profile['server']['runtime'])
        ) {
            return null;
        }

        if ($profile['server']['runtime'] === 'frankenphp') {
            $expected = [
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
            ];
            return $trust === $expected ? $expected : null;
        }

        $expected = [
            'profile' => 'direct_php',
            'trustedOriginEnvironment' =>
                'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN',
            'trustedOriginSource' =>
                $profile['ingress']['trustedOriginSource'],
            'trustedOriginMatchesProfile' => true,
            'httpsSource' => 'apache_server',
            'httpsVerified' => true,
            'hostIgnored' => true,
            'forwardedHeadersIgnored' => true,
            'hmacRequired' => false,
            'secretValuesRecorded' => false,
        ];
        return $profile['server']['runtime'] === 'apache_php'
            && in_array(
                $profile['ingress']['trustedOriginSource'],
                ['process_environment', 'ignored_local_config'],
                true
            )
            && $trust === $expected
                ? $expected
                : null;
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_profile_from_server')) {
    function red_addon_public_mutation_deployment_review_profile_from_server(
        $server,
        $trust
    ) {
        if (!is_array($server) || !is_array($trust)) {
            return null;
        }
        if (($server['runtime'] ?? null) === 'frankenphp') {
            if (!red_addon_public_mutation_deployment_review_keys_valid(
                $server,
                [
                    'runtime',
                    'frankenphpVersion',
                    'caddyVersion',
                    'tlsMode',
                    'proxyMode',
                    'siteOrigin',
                    'routeOrder',
                    'dispatcherLinked',
                    'deploymentRootOutsideStarter',
                    'binaryOutsideStarter',
                    'caddyfileOutsideStarter',
                    'certificatesOutsideStarter',
                    'caddyfileSHA256',
                    'binarySHA256',
                    'certificateChainSHA256',
                ]
            )) {
                return null;
            }
            return [
                'trustedOrigin' => $server['siteOrigin'],
                'server' => [
                    'runtime' => 'frankenphp',
                    'frankenphpVersion' => $server['frankenphpVersion'],
                    'caddyVersion' => $server['caddyVersion'],
                    'tlsMode' => $server['tlsMode'],
                    'proxyMode' => $server['proxyMode'],
                ],
                'ingress' => ['routeOrder' => $server['routeOrder']],
            ];
        }
        if (($server['runtime'] ?? null) !== 'apache_php'
            || !red_addon_public_mutation_deployment_review_keys_valid(
                $server,
                [
                    'runtime',
                    'apacheVersion',
                    'phpVersion',
                    'sapi',
                    'tlsMode',
                    'proxyMode',
                    'siteOrigin',
                    'routeOrder',
                    'dispatcherLinked',
                    'deploymentRootOutsideStarter',
                    'configurationOutsideStarter',
                    'certificatesOutsideStarter',
                    'apacheConfigSHA256',
                    'runtimeEvidenceSHA256',
                    'certificateChainSHA256',
                    'projectionEvidenceSHA256',
                    'projectionVerified',
                ]
            )
            || !is_string($trust['trustedOriginSource'] ?? null)
        ) {
            return null;
        }
        return [
            'trustedOrigin' => $server['siteOrigin'],
            'server' => [
                'runtime' => 'apache_php',
                'apacheVersion' => $server['apacheVersion'],
                'phpVersion' => $server['phpVersion'],
                'sapi' => $server['sapi'],
                'tlsMode' => $server['tlsMode'],
                'proxyMode' => $server['proxyMode'],
            ],
            'ingress' => [
                'trustedOriginSource' => $trust['trustedOriginSource'],
                'routeOrder' => $server['routeOrder'],
            ],
        ];
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_normalize')) {
    /**
     * Validates one explicit deployment/evidence packet without applying it.
     */
    function red_addon_public_mutation_deployment_review_normalize(
        $deploymentProfileResult,
        $review
    ) {
        $invalid = static function ($reason) {
            return red_addon_public_mutation_deployment_review_result(
                $reason
            );
        };

        if (!red_addon_public_mutation_deployment_profile_valid(
            $deploymentProfileResult
        )) {
            return $invalid('profile_invalid');
        }

        if (!red_addon_public_mutation_deployment_review_keys_valid(
            $review,
            ['profileHash', 'server', 'trust', 'browser']
        )) {
            return $invalid('review_shape_invalid');
        }

        $profileHash = $review['profileHash'];
        if (!red_addon_public_mutation_deployment_review_sha256_valid(
            $profileHash
        )
            || $profileHash !== $deploymentProfileResult['profileHash']
        ) {
            return $invalid('profile_hash_invalid');
        }

        $profile = $deploymentProfileResult['profile'];
        $server = $review['server'];
        $normalizedServer =
            red_addon_public_mutation_deployment_review_server(
                $profile,
                $server
            );
        if ($normalizedServer === null) {
            return $invalid('server_evidence_invalid');
        }

        $trust = $review['trust'];
        $normalizedTrust =
            red_addon_public_mutation_deployment_review_trust(
                $profile,
                $trust
            );
        if ($normalizedTrust === null) {
            return $invalid('trust_evidence_invalid');
        }

        $browser = $review['browser'];
        if (!red_addon_public_mutation_deployment_review_keys_valid(
            $browser,
            [
                'reviewVersion',
                'origin',
                'desktop',
                'mobile',
                'evidenceOutsideStarter',
                'dispatcherLinked',
                'publicMutationEndpointExercised',
                'clientStateChanged',
            ]
        )
            || $browser['reviewVersion'] !== 'v1'
            || $browser['origin'] !== $profile['trustedOrigin']
            || $browser['evidenceOutsideStarter'] !== true
            || $browser['dispatcherLinked'] !== false
            || $browser['publicMutationEndpointExercised'] !== false
            || $browser['clientStateChanged'] !== false
        ) {
            return $invalid('browser_evidence_invalid');
        }

        $desktop =
            red_addon_public_mutation_deployment_review_browser_case(
                $browser['desktop'],
                1440,
                1000
            );
        $mobile =
            red_addon_public_mutation_deployment_review_browser_case(
                $browser['mobile'],
                390,
                844
            );
        if ($desktop === null || $mobile === null) {
            return $invalid('browser_evidence_invalid');
        }

        $normalized = [
            'profileHash' => $profileHash,
            'server' => $normalizedServer,
            'trust' => $normalizedTrust,
            'browser' => [
                'reviewVersion' => 'v1',
                'origin' => $profile['trustedOrigin'],
                'desktop' => $desktop,
                'mobile' => $mobile,
                'evidenceOutsideStarter' => true,
                'dispatcherLinked' => false,
                'publicMutationEndpointExercised' => false,
                'clientStateChanged' => false,
            ],
        ];
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return $invalid('deployment_review_invalid');
        }
        return [
            'valid' => true,
            'profileHash' => $profileHash,
            'reviewHash' => hash('sha256', $encoded),
            'review' => $normalized,
            'reason' => 'deployment_review_valid',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review')) {
    function red_addon_public_mutation_deployment_review(
        $deploymentProfileResult,
        $review
    ) {
        return red_addon_public_mutation_deployment_review_normalize(
            $deploymentProfileResult,
            $review
        );
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_normalized_valid')) {
    function red_addon_public_mutation_deployment_review_normalized_valid(
        $review,
        $profileHash
    ) {
        if (!red_addon_public_mutation_deployment_review_keys_valid(
            $review,
            ['profileHash', 'server', 'trust', 'browser']
        )
            || $review['profileHash'] !== $profileHash
            || !red_addon_public_mutation_deployment_review_sha256_valid(
                $profileHash
            )
        ) {
            return false;
        }

        $server = $review['server'];
        $trust = $review['trust'];
        $profile =
            red_addon_public_mutation_deployment_review_profile_from_server(
                $server,
                $trust
            );
        if ($profile === null
            || red_addon_public_mutation_http_request_trusted_origin(
                $server['siteOrigin'] ?? null
            ) !== ($server['siteOrigin'] ?? null)
            || red_addon_public_mutation_deployment_review_server(
                $profile,
                $server
            ) !== $server
            || red_addon_public_mutation_deployment_review_trust(
                $profile,
                $trust
            ) !== $trust
        ) {
            return false;
        }

        $browser = $review['browser'];
        if (!red_addon_public_mutation_deployment_review_keys_valid(
            $browser,
            [
                'reviewVersion',
                'origin',
                'desktop',
                'mobile',
                'evidenceOutsideStarter',
                'dispatcherLinked',
                'publicMutationEndpointExercised',
                'clientStateChanged',
            ]
        )
            || $browser['reviewVersion'] !== 'v1'
            || $browser['origin'] !== $server['siteOrigin']
            || $browser['evidenceOutsideStarter'] !== true
            || $browser['dispatcherLinked'] !== false
            || $browser['publicMutationEndpointExercised'] !== false
            || $browser['clientStateChanged'] !== false
            || red_addon_public_mutation_deployment_review_browser_case(
                $browser['desktop'],
                1440,
                1000
            ) === null
            || red_addon_public_mutation_deployment_review_browser_case(
                $browser['mobile'],
                390,
                844
            ) === null
        ) {
            return false;
        }
        return true;
    }
}

if (!function_exists('red_addon_public_mutation_deployment_review_valid')) {
    function red_addon_public_mutation_deployment_review_valid($result)
    {
        if (!is_array($result)
            || array_keys($result) !== [
                'valid',
                'profileHash',
                'reviewHash',
                'review',
                'reason',
            ]
            || !is_bool($result['valid'])
            || !is_string($result['profileHash'])
            || !is_string($result['reviewHash'])
            || !is_array($result['review'])
            || !is_string($result['reason'])
        ) {
            return false;
        }
        if (!$result['valid']) {
            return $result === red_addon_public_mutation_deployment_review_result(
                $result['reason']
            );
        }
        if ($result['reason'] !== 'deployment_review_valid'
            || !red_addon_public_mutation_deployment_review_sha256_valid(
                $result['profileHash']
            )
            || !red_addon_public_mutation_deployment_review_sha256_valid(
                $result['reviewHash']
            )
            || !red_addon_public_mutation_deployment_review_normalized_valid(
                $result['review'],
                $result['profileHash']
            )
        ) {
            return false;
        }
        $encoded = json_encode(
            $result['review'],
            JSON_UNESCAPED_SLASHES
        );
        return is_string($encoded)
            && hash('sha256', $encoded) === $result['reviewHash'];
    }
}

?>
