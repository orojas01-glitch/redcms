<?php
/**
 * Gated live composition from one completed Store Lite subscription intent
 * to the existing one-attempt Stripe Sandbox provider operation.
 */

require_once __DIR__ . '/addon_subscription_checkout_provider_journal_helpers.php';
require_once __DIR__ . '/addon_public_mutation_dispatch_helpers.php';

if (!function_exists('red_addon_subscription_checkout_public_runtime_enabled')) {
    function red_addon_subscription_checkout_public_runtime_enabled()
    {
        $enabled = red_server_config_value(
            'SUBSCRIPTION_CHECKOUT_SANDBOX_ENABLED',
            ['RED_SUBSCRIPTION_CHECKOUT_SANDBOX_ENABLED'],
            false
        );
        $authorization = red_server_config_value(
            'SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256',
            ['RED_SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256'],
            ''
        );
        return ($enabled === true || $enabled === '1')
            && red_addon_valid_sha256($authorization);
    }
}

if (!function_exists('red_addon_subscription_checkout_public_runtime_target')) {
    function red_addon_subscription_checkout_public_runtime_target($target)
    {
        if (!is_string($target)) {
            return false;
        }
        $queryAt = strpos($target, '?');
        $path = $queryAt === false ? $target : substr($target, 0, $queryAt);
        return $path === '/addons/redcms/store-lite/subscription-intent';
    }
}

if (!function_exists('red_addon_subscription_checkout_public_runtime_origin')) {
    function red_addon_subscription_checkout_public_runtime_origin($connection)
    {
        if (!($connection instanceof mysqli)) {
            return '';
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT ValueJSON
                 FROM RED_Addon_Settings
                 WHERE PackageID='redcms.store-lite-stripe-checkout'
                   AND SettingKey='checkout.return-origin'
                   AND ValueType='url'
                   AND SecretReference IS NULL
                 LIMIT 1"
            );
            if (!$statement || !mysqli_stmt_execute($statement)) {
                if ($statement) {
                    mysqli_stmt_close($statement);
                }
                return '';
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            $encoded = is_array($row) ? ($row['ValueJSON'] ?? null) : null;
            if (!is_string($encoded)) {
                return '';
            }
            $origin = json_decode($encoded, true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return '';
        }
        $trusted = red_addon_public_mutation_server_trusted_origin();
        if (!is_string($origin)
            || $origin !== $trusted
            || preg_match(
                '/\Ahttps:\/\/[A-Za-z0-9.-]+\z/D',
                $origin
            ) !== 1
        ) {
            return '';
        }
        return $origin;
    }
}

if (!function_exists('red_addon_subscription_checkout_public_runtime_execution')) {
    function red_addon_subscription_checkout_public_runtime_execution(
        $dispatch,
        $selection
    ) {
        $response = is_array($dispatch) ? ($dispatch['response'] ?? null) : null;
        $reason = is_array($dispatch) ? ($dispatch['reason'] ?? null) : null;
        if (!is_array($selection)
            || !red_addon_public_mutation_response_emitter_valid($response)
            || ($response['httpStatus'] ?? null) !== 200
            || !in_array($response['outcome'] ?? '', ['accepted', 'unchanged'], true)
            || !in_array($reason, ['completed', 'replayed'], true)
        ) {
            return null;
        }
        return [
            'completed' => $reason === 'completed',
            'replayed' => $reason === 'replayed',
            'outcome' => $response['outcome'],
            'route' => $selection['route'] ?? '',
            'mutation' => $selection['mutation'] ?? '',
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_subscription_checkout_public_runtime_refusal')) {
    function red_addon_subscription_checkout_public_runtime_refusal()
    {
        return red_addon_public_mutation_response_refusal(
            'runtime_unavailable'
        );
    }
}

if (!function_exists('red_addon_subscription_checkout_public_runtime_complete')) {
    function red_addon_subscription_checkout_public_runtime_complete(
        $connection,
        $projectRoot,
        $requestTarget,
        $capture,
        $dispatch
    ) {
        $response = is_array($dispatch) ? ($dispatch['response'] ?? null) : null;
        if (!red_addon_subscription_checkout_public_runtime_target(
            $requestTarget
        )) {
            return $response;
        }
        $refused = red_addon_subscription_checkout_public_runtime_refusal();
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || !is_dir($projectRoot)
            || !red_addon_public_mutation_dispatch_capture_valid($capture)
            || !red_addon_subscription_checkout_public_runtime_enabled()
        ) {
            return $refused;
        }

        try {
            $selection = red_addon_public_mutation_route_select(
                $requestTarget
            );
            if (!red_addon_public_mutation_route_selection_valid($selection)
                || empty($selection['ready'])
                || ($selection['packageId'] ?? '') !== 'redcms.store-lite'
                || ($selection['route'] ?? '')
                    !== 'redcms.store-lite/subscription-intent'
                || ($selection['mutation'] ?? '')
                    !== 'redcms.store-lite/create-subscription-intent'
            ) {
                return $refused;
            }
            $execution =
                red_addon_subscription_checkout_public_runtime_execution(
                    $dispatch,
                    $selection
                );
            $manifest = red_addon_runtime_manifest('redcms.store-lite');
            if (!is_array($execution) || !is_array($manifest)) {
                return $refused;
            }
            $request = red_addon_public_mutation_http_request_normalize(
                $manifest,
                $selection['route'],
                $selection['mutation'],
                $capture['trustedOrigin'],
                $capture['method'],
                $capture['requestTarget'],
                $capture['headers'],
                $capture['body']
            );
            $subject = is_array($request) && !empty($request['valid'])
                ? red_addon_public_mutation_subject_resolve(
                    $connection,
                    $request['subjectToken']
                )
                : null;
            $fields = red_addon_public_mutation_form_decode(
                $manifest,
                $selection['route'],
                $selection['mutation'],
                $capture['body']
            );
            $subjectRecordId = is_array($subject)
                ? (int) ($subject['subjectRecordId'] ?? 0) : 0;
            $offerId = is_array($fields)
                ? ($fields['fields']['offer'] ?? null) : null;
            if (empty($subject['valid'])
                || empty($fields['valid'])
                || $subjectRecordId < 1
                || !is_string($offerId)
                || preg_match(
                    '/\A[a-z0-9][a-z0-9._-]{0,63}\z/D',
                    $offerId
                ) !== 1
            ) {
                return $refused;
            }

            $catalog = red_addon_discover($projectRoot, [
                'cmsVersion' => '5.1.0',
                'phpVersion' => PHP_VERSION,
            ]);
            $adapterPackage = $catalog['packages']
                ['redcms.store-lite-stripe-checkout'] ?? null;
            if (($catalog['valid'] ?? false) !== true
                || !is_array($adapterPackage)
                || ($adapterPackage['manifest']['version'] ?? '') !== '0.1.15'
            ) {
                return $refused;
            }
            $secret = red_addon_runtime_secret_access_for_package(
                $connection,
                $adapterPackage,
                true,
                ['stripe.secret-key']
            );
            if (($secret['valid'] ?? false) !== true
                || ($secret['settingCount'] ?? 0) !== 1
                || ($secret['resolvedCount'] ?? 0) !== 1
                || !red_addon_valid_sha256(
                    $secret['stateSha256'] ?? null
                )
                || !$secret['access'] instanceof
                    RED_Addon_Runtime_Secret_Access
            ) {
                return $refused;
            }

            $adapterId = 'redcms.store-lite-stripe-checkout/checkout';
            $adapterOwner = red_addon_runtime_owner('adapters', $adapterId);
            $adapterHandler = red_addon_runtime_handler(
                'adapters',
                $adapterId
            );
            $adapterManifest = red_addon_runtime_manifest(
                'redcms.store-lite-stripe-checkout'
            );
            if ($adapterOwner !== 'redcms.store-lite-stripe-checkout'
                || !is_callable($adapterHandler)
                || !is_array($adapterManifest)
                || red_addon_runtime_owner(
                    'services',
                    'commerce.subscriptions'
                ) !== 'redcms.store-lite'
            ) {
                return $refused;
            }

            $origin =
                red_addon_subscription_checkout_public_runtime_origin(
                    $connection
                );
            if ($origin === '') {
                return $refused;
            }
            $now = time();
            $authorizationSha256 = red_server_config_value(
                'SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256',
                ['RED_SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256'],
                ''
            );
            $availabilitySha256 = hash('sha256', json_encode([
                'schema' => 1,
                'purpose' => 'subscription-sandbox-secret-availability',
                'packageId' => 'redcms.store-lite-stripe-checkout',
                'packageVersion' => '0.1.15',
                'settingKey' => 'stripe.secret-key',
                'settingsStateSha256' => $secret['stateSha256'],
                'resolvedCount' => 1,
                'valueIncluded' => false,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $coordinated = red_addon_subscription_provider_operation(
                [
                    'storePackageId' => 'redcms.store-lite',
                    'storePackageVersion' => '0.1.50',
                    'storeService' => 'commerce.subscriptions',
                    'stripePackageId' =>
                        'redcms.store-lite-stripe-checkout',
                    'stripePackageVersion' => '0.1.15',
                    'stripeAdapter' => $adapterId,
                ],
                $subjectRecordId,
                $offerId,
                [
                    'apiVersion' => '2024-09-30.acacia',
                    'successUrl' => $origin . '/subscription/complete',
                    'cancelUrl' => $origin . '/subscription/cancel',
                    'createdAtEpoch' => $now,
                    'expiresAtEpoch' => $now + 1800,
                ],
                [
                    'authorized' => true,
                    'authorizationSha256' => $authorizationSha256,
                    'secretAvailabilitySha256' => $availabilitySha256,
                    'issuedAtEpoch' => $now,
                    'expiresAtEpoch' => $now + 900,
                    'maximumAttempts' => 1,
                    'retryAuthorized' => false,
                ],
                static fn ($service, $operation, $input) =>
                    red_addon_service_invoke($service, $operation, $input),
                static fn ($adapter, $operation, $input) =>
                    red_addon_adapter_invoke_registered(
                        $adapter,
                        $operation,
                        $input,
                        'redcms.store-lite-stripe-checkout',
                        $adapterHandler,
                        $adapterManifest,
                        $secret['access']
                    ),
                red_addon_subscription_provider_database_journal(
                    $connection
                )
            );
            $public = red_addon_subscription_checkout_public_response(
                $execution,
                $subjectRecordId,
                $offerId,
                $coordinated
            );
            return red_addon_subscription_checkout_public_response_valid(
                $public
            ) ? $public : $refused;
        } catch (Throwable $throwable) {
            return $refused;
        }
    }
}

?>
