<?php
/**
 * Gated handoff from a placed Store Lite guest order paid with Wompi Web
 * Checkout to Wompi's hosted payment page.
 *
 * The flow is split around the existing guest-checkout dispatch:
 *
 * - prepare() runs before dispatch, only for a guest-checkout POST whose
 *   payment-method field is wompi_checkout. It checks the server-local
 *   Sandbox gate, the exact package pair, the public key, the integrity key
 *   reference and the return origin. If any is missing the request is refused
 *   before an order exists, so a customer can never place a Wompi order that
 *   cannot be paid.
 * - complete() runs after a successful dispatch. It reads the visitor's
 *   newest pending Wompi order through commerce.orders payment.order.handoff,
 *   signs the Web Checkout fields with the W1 planner and returns one closed
 *   JSON response telling the browser to navigate to checkout.wompi.co.
 *
 * Building the URL contacts nobody and never marks an order paid. Only the
 * verified webhook (addon_wompi_webhook_*) can do that.
 */

require_once __DIR__ . '/runtime_config_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_service_helpers.php';
require_once __DIR__ . '/addon_public_mutation_dispatch_helpers.php';
require_once __DIR__ . '/addon_public_mutation_response_emitter_helpers.php';
require_once __DIR__ . '/addon_public_mutation_server_request_helpers.php';

if (!function_exists('red_addon_wompi_checkout_public_runtime_versions')) {
    /** Exact package pair this handoff was verified against. */
    function red_addon_wompi_checkout_public_runtime_versions()
    {
        return [
            'redcms.store-lite' => '0.1.54',
            'redcms.store-lite-wompi' => '0.4.0',
        ];
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_enabled')) {
    function red_addon_wompi_checkout_public_runtime_enabled()
    {
        $enabled = red_server_config_value(
            'WOMPI_CHECKOUT_SANDBOX_ENABLED',
            ['RED_WOMPI_CHECKOUT_SANDBOX_ENABLED'],
            false
        );
        return $enabled === true || $enabled === '1';
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_target')) {
    function red_addon_wompi_checkout_public_runtime_target($target)
    {
        if (!is_string($target)) {
            return false;
        }
        $queryAt = strpos($target, '?');
        $path = $queryAt === false ? $target : substr($target, 0, $queryAt);
        return $path === '/addons/redcms/store-lite/guest-checkout';
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_return_path')) {
    function red_addon_wompi_checkout_public_runtime_return_path()
    {
        return '/checkout/wompi-return';
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_refusal')) {
    function red_addon_wompi_checkout_public_runtime_refusal(
        $diagnosticStage = ''
    ) {
        if (is_string($diagnosticStage)
            && preg_match('/\A[a-z][a-z0-9_]{0,79}\z/D', $diagnosticStage)
                === 1
        ) {
            error_log(
                'RED-CMS Wompi Checkout handoff refused: ' . $diagnosticStage
            );
        }
        return red_addon_public_mutation_response_refusal(
            'runtime_unavailable'
        );
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_text_setting')) {
    /** Reads one non-secret text setting of the Wompi package. */
    function red_addon_wompi_checkout_public_runtime_text_setting(
        $connection,
        $settingKey,
        $valueType
    ) {
        if (!($connection instanceof mysqli)
            || !in_array(
                [$settingKey, $valueType],
                [['wompi.public-key', 'text']],
                true
            )
        ) {
            return '';
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                "SELECT ValueJSON
                 FROM RED_Addon_Settings
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND SettingKey=?
                   AND ValueType=?
                   AND SecretReference IS NULL
                 LIMIT 1"
            );
            if (!$statement
                || !mysqli_stmt_execute($statement, [$settingKey, $valueType])
            ) {
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
            $value = json_decode($encoded, true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return '';
        }
        return is_string($value) ? $value : '';
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_origin')) {
    /** The return origin is the operator's canonical HTTPS origin. */
    function red_addon_wompi_checkout_public_runtime_origin()
    {
        $origin = red_addon_public_mutation_server_trusted_origin();
        return is_string($origin)
            && preg_match(
                '/\Ahttps:\/\/(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}'
                    . '[a-z0-9])?\.)+[a-z]{2,63}\z/D',
                $origin
            ) === 1
                ? $origin
                : '';
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_payment_method')) {
    /**
     * The submitted payment-method field, decoded with the exact guest-checkout
     * form contract. Anything undecodable is '' and left to the dispatcher.
     */
    function red_addon_wompi_checkout_public_runtime_payment_method(
        $storeManifest,
        $capture
    ) {
        if (!is_array($storeManifest)
            || !is_array($capture)
            || !is_string($capture['body'] ?? null)
        ) {
            return '';
        }
        try {
            $fields = red_addon_public_mutation_form_decode(
                $storeManifest,
                'redcms.store-lite/guest-checkout',
                'redcms.store-lite/create-guest-order',
                $capture['body']
            );
        } catch (Throwable $throwable) {
            return '';
        }
        $method = is_array($fields) && !empty($fields['valid'])
            ? ($fields['fields']['payment-method'] ?? null)
            : null;
        return is_string($method) ? $method : '';
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_prepare_result')) {
    function red_addon_wompi_checkout_public_runtime_prepare_result(
        $applies,
        $reason
    ) {
        return [
            'applies' => (bool) $applies,
            'ready' => false,
            'publicKey' => '',
            'origin' => '',
            'access' => null,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_prepare')) {
    /**
     * Pre-dispatch check. Returns applies=false for every request that is not
     * a Wompi guest checkout, so other orders keep their current path.
     */
    function red_addon_wompi_checkout_public_runtime_prepare(
        $connection,
        $projectRoot,
        $requestTarget,
        $capture
    ) {
        if (!red_addon_wompi_checkout_public_runtime_target($requestTarget)
            || !($connection instanceof mysqli)
            || !is_string($projectRoot)
            || !is_dir($projectRoot)
            || !red_addon_public_mutation_dispatch_capture_valid($capture)
        ) {
            return red_addon_wompi_checkout_public_runtime_prepare_result(
                false,
                'not_applicable'
            );
        }
        $storeId = 'redcms.store-lite';
        $adapterId = 'redcms.store-lite-wompi';
        try {
            $catalog = red_addon_discover($projectRoot, [
                'cmsVersion' => '5.1.0',
                'phpVersion' => PHP_VERSION,
            ]);
        } catch (Throwable $throwable) {
            $catalog = null;
        }
        $store = is_array($catalog)
            ? ($catalog['packages'][$storeId] ?? null) : null;
        $method = red_addon_wompi_checkout_public_runtime_payment_method(
            is_array($store) ? ($store['manifest'] ?? null) : null,
            $capture
        );
        if ($method !== 'wompi_checkout') {
            return red_addon_wompi_checkout_public_runtime_prepare_result(
                false,
                'not_applicable'
            );
        }
        if (!red_addon_wompi_checkout_public_runtime_enabled()) {
            return red_addon_wompi_checkout_public_runtime_prepare_result(
                true,
                'gate_disabled'
            );
        }
        $versions = red_addon_wompi_checkout_public_runtime_versions();
        $adapter = $catalog['packages'][$adapterId] ?? null;
        if (($catalog['valid'] ?? false) !== true
            || ($store['manifest']['version'] ?? '') !== $versions[$storeId]
            || !is_array($adapter)
            || ($adapter['manifest']['version'] ?? '')
                !== $versions[$adapterId]
        ) {
            return red_addon_wompi_checkout_public_runtime_prepare_result(
                true,
                'package_unavailable'
            );
        }
        try {
            foreach ([$store, $adapter] as $package) {
                $report = red_addon_registry_package_report(
                    $connection,
                    $package
                );
                if (($report['status'] ?? '') !== 'enabled_current'
                    || ($report['errors'] ?? null) !== []
                ) {
                    return red_addon_wompi_checkout_public_runtime_prepare_result(
                        true,
                        'package_unavailable'
                    );
                }
            }
            // Only the integrity key enters this request. The private and
            // events keys stay outside the checkout context.
            $secret = red_addon_runtime_secret_access_for_package(
                $connection,
                $adapter,
                true,
                ['wompi.integrity-key']
            );
        } catch (Throwable $throwable) {
            $secret = null;
        }
        if (!is_array($secret)
            || ($secret['valid'] ?? false) !== true
            || ($secret['settingCount'] ?? 0) !== 1
            || ($secret['resolvedCount'] ?? 0) !== 1
            || !$secret['access'] instanceof RED_Addon_Runtime_Secret_Access
            || $secret['access']->packageId() !== $adapterId
        ) {
            return red_addon_wompi_checkout_public_runtime_prepare_result(
                true,
                'integrity_key_unavailable'
            );
        }
        $publicKey = red_addon_wompi_checkout_public_runtime_text_setting(
            $connection,
            'wompi.public-key',
            'text'
        );
        if (preg_match('/\Apub_test_[A-Za-z0-9]{8,128}\z/D', $publicKey)
            !== 1
        ) {
            return red_addon_wompi_checkout_public_runtime_prepare_result(
                true,
                'public_key_unavailable'
            );
        }
        $origin = red_addon_wompi_checkout_public_runtime_origin();
        if ($origin === '') {
            return red_addon_wompi_checkout_public_runtime_prepare_result(
                true,
                'return_origin_unavailable'
            );
        }
        $result = red_addon_wompi_checkout_public_runtime_prepare_result(
            true,
            'ready'
        );
        $result['ready'] = true;
        $result['publicKey'] = $publicKey;
        $result['origin'] = $origin;
        $result['access'] = $secret['access'];
        return $result;
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_prepared_valid')) {
    function red_addon_wompi_checkout_public_runtime_prepared_valid($prepared)
    {
        return is_array($prepared)
            && array_keys($prepared) === [
                'applies', 'ready', 'publicKey', 'origin', 'access', 'reason',
            ]
            && $prepared['applies'] === true
            && $prepared['ready'] === true
            && $prepared['reason'] === 'ready'
            && is_string($prepared['publicKey'])
            && is_string($prepared['origin'])
            && $prepared['access'] instanceof RED_Addon_Runtime_Secret_Access;
    }
}

if (!function_exists('red_addon_wompi_checkout_public_url')) {
    /**
     * Builds the GET URL for a valid W1 plan. Returns '' unless the plan is a
     * Sandbox plan for checkout.wompi.co with the exact field order.
     */
    function red_addon_wompi_checkout_public_url($plan)
    {
        if (!is_array($plan)
            || ($plan['valid'] ?? null) !== true
            || ($plan['environment'] ?? '') !== 'sandbox'
            || ($plan['action'] ?? '') !== 'https://checkout.wompi.co/p/'
            || !is_array($plan['fields'] ?? null)
        ) {
            return '';
        }
        $query = [];
        foreach ($plan['fields'] as $field) {
            if (!is_array($field)
                || count($field) !== 2
                || !is_string($field[0] ?? null)
                || !is_string($field[1] ?? null)
            ) {
                return '';
            }
            $query[] = rawurlencode($field[0]) . '=' . rawurlencode($field[1]);
        }
        $url = $plan['action'] . '?' . implode('&', $query);
        return red_addon_wompi_checkout_public_url_valid($url) ? $url : '';
    }
}

if (!function_exists('red_addon_wompi_checkout_public_url_valid')) {
    function red_addon_wompi_checkout_public_url_valid($url)
    {
        if (!is_string($url)
            || strlen($url) > 4096
            || !str_starts_with($url, 'https://checkout.wompi.co/p/?')
        ) {
            return false;
        }
        $pairs = explode('&', substr($url, strlen('https://checkout.wompi.co/p/?')));
        $names = [
            'public-key', 'currency', 'amount-in-cents', 'reference',
            'signature:integrity', 'redirect-url', 'expiration-time',
        ];
        if (count($pairs) !== count($names)) {
            return false;
        }
        $values = [];
        foreach ($pairs as $index => $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) !== 2
                || rawurldecode($parts[0]) !== $names[$index]
                || rawurlencode(rawurldecode($parts[0])) !== $parts[0]
                || rawurlencode(rawurldecode($parts[1])) !== $parts[1]
            ) {
                return false;
            }
            $values[$names[$index]] = rawurldecode($parts[1]);
        }
        $redirect = $values['redirect-url'];
        return preg_match(
            '/\Apub_test_[A-Za-z0-9]{8,128}\z/D',
            $values['public-key']
        ) === 1
            && $values['currency'] === 'COP'
            && preg_match('/\A[1-9][0-9]{2,11}\z/D', $values['amount-in-cents'])
                === 1
            && preg_match('/\Aord_[a-f0-9]{32}\z/D', $values['reference'])
                === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $values['signature:integrity']
            ) === 1
            && preg_match(
                '/\Ahttps:\/\/[a-z0-9.-]{4,253}\/checkout\/wompi-return\z/D',
                $redirect
            ) === 1
            && preg_match(
                '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z\z/D',
                $values['expiration-time']
            ) === 1;
    }
}

if (!function_exists('red_addon_wompi_checkout_public_response_result')) {
    function red_addon_wompi_checkout_public_response_result(
        $reason = 'handoff_invalid'
    ) {
        return [
            'valid' => false,
            'httpStatus' => 0,
            'headers' => [],
            'body' => '',
            'ok' => false,
            'outcome' => '',
            'checkoutUrl' => '',
            'navigationMode' => '',
            'transientOnly' => false,
            'navigationAuthorized' => false,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_wompi_checkout_public_response_body')) {
    function red_addon_wompi_checkout_public_response_body($checkoutUrl)
    {
        $body = json_encode([
            'ok' => true,
            'outcome' => 'wompi_checkout_ready',
            'checkoutUrl' => $checkoutUrl,
            'navigationMode' => 'location.assign',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($body) && strlen($body) <= 4608 ? $body : '';
    }
}

if (!function_exists('red_addon_wompi_checkout_public_response')) {
    function red_addon_wompi_checkout_public_response($checkoutUrl)
    {
        $result = red_addon_wompi_checkout_public_response_result();
        if (!red_addon_wompi_checkout_public_url_valid($checkoutUrl)) {
            return $result;
        }
        $body = red_addon_wompi_checkout_public_response_body($checkoutUrl);
        if ($body === '') {
            return $result;
        }
        return [
            'valid' => true,
            'httpStatus' => 200,
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Length' => (string) strlen($body),
            ],
            'body' => $body,
            'ok' => true,
            'outcome' => 'wompi_checkout_ready',
            'checkoutUrl' => $checkoutUrl,
            'navigationMode' => 'location.assign',
            'transientOnly' => true,
            'navigationAuthorized' => true,
            'reason' => 'handoff_ready',
        ];
    }
}

if (!function_exists('red_addon_wompi_checkout_public_response_valid')) {
    function red_addon_wompi_checkout_public_response_valid($response)
    {
        if (!is_array($response)
            || array_keys($response) !== array_keys(
                red_addon_wompi_checkout_public_response_result()
            )
            || !red_addon_wompi_checkout_public_url_valid(
                $response['checkoutUrl'] ?? null
            )
        ) {
            return false;
        }
        return $response === red_addon_wompi_checkout_public_response(
            $response['checkoutUrl']
        );
    }
}

if (!function_exists('red_addon_wompi_checkout_public_response_emit')) {
    function red_addon_wompi_checkout_public_response_emit($response)
    {
        if (!red_addon_wompi_checkout_public_response_valid($response)) {
            throw new InvalidArgumentException(
                'Wompi Checkout public response is invalid.'
            );
        }
        if (headers_sent()) {
            throw new RuntimeException(
                'Wompi Checkout headers were sent prematurely.'
            );
        }
        header_remove();
        http_response_code(200);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $response['body'];
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_handoff')) {
    /**
     * Pure composition after the order exists: handoff order -> W1 plan ->
     * public response. $serviceInvoker and $planner are injected so the
     * composition is testable without a database or the package.
     */
    function red_addon_wompi_checkout_public_runtime_handoff(
        $subjectRecordId,
        $publicKey,
        RED_Addon_Runtime_Secret_Access $access,
        $origin,
        callable $serviceInvoker,
        callable $planner,
        $now
    ) {
        if (!is_int($subjectRecordId)
            || $subjectRecordId < 1
            || !is_string($publicKey)
            || !is_string($origin)
            || !is_int($now)
        ) {
            return ['response' => null, 'reason' => 'input_unavailable'];
        }
        $handoff = $serviceInvoker(
            'commerce.orders',
            'payment.order.handoff',
            ['subjectRecordId' => $subjectRecordId]
        );
        $order = is_array($handoff)
            && ($handoff['invoked'] ?? false) === true
            && ($handoff['success'] ?? false) === true
            && is_array($handoff['data'] ?? null)
            && ($handoff['data']['found'] ?? null) === true
                ? ($handoff['data']['order'] ?? null)
                : null;
        if (!is_array($order)
            || ($order['paymentMethod'] ?? null) !== 'wompi_checkout'
            || ($order['paymentKind'] ?? null) !== 'hosted'
            || ($order['currency'] ?? null) !== 'COP'
            || !is_int($order['totalMinor'] ?? null)
            || [
                $order['orderStatus'] ?? null,
                $order['paymentStatus'] ?? null,
                $order['fulfillmentStatus'] ?? null,
            ] !== ['pending', 'pending', 'unfulfilled']
            || !is_string($order['orderId'] ?? null)
        ) {
            return ['response' => null, 'reason' => 'order_unavailable'];
        }
        $integritySecret = null;
        $resolved = $access->resolve('wompi.integrity-key', $integritySecret);
        if (($resolved['resolved'] ?? false) !== true
            || !is_string($integritySecret)
        ) {
            return ['response' => null, 'reason' => 'integrity_key_unavailable'];
        }
        try {
            $plan = $planner(
                'sandbox',
                $publicKey,
                $integritySecret,
                [
                    'orderId' => $order['orderId'],
                    'amountMinor' => $order['totalMinor'],
                    'currency' => 'COP',
                ],
                $origin
                    . red_addon_wompi_checkout_public_runtime_return_path(),
                gmdate('Y-m-d\TH:i:s', $now + 1800) . '.000Z',
                $now
            );
        } catch (Throwable $throwable) {
            $plan = null;
        } finally {
            $integritySecret = null;
        }
        if (!is_array($plan) || ($plan['valid'] ?? null) !== true) {
            $errors = is_array($plan) && is_array($plan['errors'] ?? null)
                ? $plan['errors'] : [];
            $first = is_string($errors[0] ?? null) ? $errors[0] : 'invalid';
            return ['response' => null, 'reason' => 'plan_' . $first];
        }
        $url = red_addon_wompi_checkout_public_url($plan);
        $response = red_addon_wompi_checkout_public_response($url);
        if (!red_addon_wompi_checkout_public_response_valid($response)
            || $access->containsValue($response['body'])
        ) {
            return ['response' => null, 'reason' => 'response_unavailable'];
        }
        return ['response' => $response, 'reason' => 'handoff_ready'];
    }
}

if (!function_exists('red_addon_wompi_checkout_public_runtime_complete')) {
    function red_addon_wompi_checkout_public_runtime_complete(
        $connection,
        $prepared,
        $capture,
        $dispatchResponse
    ) {
        // A refused checkout (bad input, stale cart) keeps its own response:
        // no order was placed, so there is nothing to pay.
        if (red_addon_public_mutation_response_emitter_valid($dispatchResponse)
            && (($dispatchResponse['httpStatus'] ?? null) !== 200
                || !in_array(
                    $dispatchResponse['outcome'] ?? '',
                    ['accepted', 'unchanged'],
                    true
                ))
        ) {
            return $dispatchResponse;
        }
        if (!red_addon_wompi_checkout_public_runtime_prepared_valid($prepared)
            || !($connection instanceof mysqli)
            || !red_addon_public_mutation_dispatch_capture_valid($capture)
            || !red_addon_public_mutation_response_emitter_valid(
                $dispatchResponse
            )
        ) {
            return red_addon_wompi_checkout_public_runtime_refusal(
                'execution_unavailable'
            );
        }
        try {
            $manifest = red_addon_runtime_manifest('redcms.store-lite');
            $request = is_array($manifest)
                ? red_addon_public_mutation_http_request_normalize(
                    $manifest,
                    'redcms.store-lite/guest-checkout',
                    'redcms.store-lite/create-guest-order',
                    $capture['trustedOrigin'],
                    $capture['method'],
                    $capture['requestTarget'],
                    $capture['headers'],
                    $capture['body']
                )
                : null;
            $subject = is_array($request) && !empty($request['valid'])
                ? red_addon_public_mutation_subject_resolve(
                    $connection,
                    $request['subjectToken']
                )
                : null;
            $subjectRecordId = is_array($subject) && !empty($subject['valid'])
                ? (int) ($subject['subjectRecordId'] ?? 0) : 0;
            if ($subjectRecordId < 1
                || red_addon_runtime_owner('services', 'commerce.orders')
                    !== 'redcms.store-lite'
                || red_addon_runtime_owner(
                    'adapters',
                    'redcms.store-lite-wompi/checkout'
                ) !== 'redcms.store-lite-wompi'
                || !class_exists(
                    'RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner',
                    false
                )
            ) {
                return red_addon_wompi_checkout_public_runtime_refusal(
                    'runtime_binding_unavailable'
                );
            }
            $handoff = red_addon_wompi_checkout_public_runtime_handoff(
                $subjectRecordId,
                $prepared['publicKey'],
                $prepared['access'],
                $prepared['origin'],
                static fn ($service, $operation, $input) =>
                    red_addon_service_invoke($service, $operation, $input),
                ['RED_CMS_Store_Lite_Wompi_Web_Checkout_Planner', 'plan'],
                time()
            );
        } catch (Throwable $throwable) {
            return red_addon_wompi_checkout_public_runtime_refusal(
                'runtime_exception'
            );
        }
        if (is_array($handoff['response'] ?? null)) {
            return $handoff['response'];
        }
        return red_addon_wompi_checkout_public_runtime_refusal(
            'handoff_' . (is_string($handoff['reason'] ?? null)
                ? $handoff['reason'] : 'unavailable')
        );
    }
}

?>
