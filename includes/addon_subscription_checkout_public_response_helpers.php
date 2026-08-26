<?php
/**
 * Closed AJAX handoff from a completed Store Lite subscription intent to a
 * validated Checkout coordinator result.
 *
 * This helper does not dispatch a public mutation, contact a provider, resolve
 * a secret, read request globals, emit a response, or navigate a browser. It
 * only authorizes one transient browser handoff after exact internal evidence
 * has already completed.
 */

require_once __DIR__ . '/addon_subscription_checkout_coordinator_helpers.php';

if (!function_exists('red_addon_subscription_checkout_public_response_result')) {
    function red_addon_subscription_checkout_public_response_result(
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
            'authorizationSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_subscription_checkout_execution_valid')) {
    function red_addon_subscription_checkout_execution_valid($execution)
    {
        if (!is_array($execution)
            || array_keys($execution) !== [
                'completed', 'replayed', 'outcome', 'route', 'mutation',
                'reason',
            ]
            || !is_bool($execution['completed'])
            || !is_bool($execution['replayed'])
            || !is_string($execution['outcome'])
            || !is_string($execution['route'])
            || !is_string($execution['mutation'])
            || !is_string($execution['reason'])
            || $execution['route']
                !== 'redcms.store-lite/subscription-intent'
            || $execution['mutation']
                !== 'redcms.store-lite/create-subscription-intent'
            || !in_array($execution['outcome'], ['accepted', 'unchanged'], true)
        ) {
            return false;
        }
        return ($execution['completed'] === true
                && $execution['replayed'] === false
                && $execution['reason'] === 'completed')
            || ($execution['completed'] === false
                && $execution['replayed'] === true
                && $execution['reason'] === 'replayed');
    }
}

if (!function_exists('red_addon_subscription_checkout_public_url_valid')) {
    function red_addon_subscription_checkout_public_url_valid($url)
    {
        if (!is_string($url)) {
            return false;
        }
        $path = parse_url($url, PHP_URL_PATH);
        $prefix = '/c/pay/';
        if (!is_string($path) || !str_starts_with($path, $prefix)) {
            return false;
        }
        return red_addon_subscription_checkout_url_valid(
            $url,
            substr($path, strlen($prefix))
        );
    }
}

if (!function_exists('red_addon_subscription_checkout_coordinator_result_valid')) {
    function red_addon_subscription_checkout_coordinator_result_valid(
        $coordinated,
        $subjectRecordId,
        $offerId
    ) {
        if (!is_array($coordinated)
            || ($coordinated['valid'] ?? null) !== true
            || ($coordinated['ready'] ?? null) !== true
            || !in_array(
                $coordinated['status'] ?? '',
                ['synthetic_redirect_ready', 'real_redirect_ready'],
                true
            )
            || ($coordinated['reason'] ?? '')
                !== ($coordinated['status'] ?? '')
            || ($coordinated['subjectRecordId'] ?? null) !== $subjectRecordId
            || ($coordinated['offerId'] ?? null) !== $offerId
            || !red_addon_subscription_checkout_public_url_valid(
                $coordinated['checkoutUrl'] ?? null
            )
            || ($coordinated['httpStatus'] ?? null) !== 303
            || ($coordinated['cacheControl'] ?? '') !== 'no-store'
            || ($coordinated['navigationMode'] ?? '') !== 'location.assign'
            || ($coordinated['transientOnly'] ?? null) !== true
            || ($coordinated['responseEmission'] ?? null) !== false
            || ($coordinated['browserNavigation'] ?? null) !== false
            || !red_addon_valid_sha256(
                $coordinated['contractSha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $coordinated['requestSha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $coordinated['responseEvidenceSha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $coordinated['resultSha256'] ?? null
            )
            || !red_addon_valid_sha256(
                $coordinated['checkoutSessionRefSha256'] ?? null
            )
        ) {
            return false;
        }
        if ($coordinated['status'] === 'synthetic_redirect_ready') {
            return empty($coordinated['networkAccess'])
                && empty($coordinated['providerContact'])
                && empty($coordinated['providerMutation'])
                && empty($coordinated['checkoutCreation'])
                && empty($coordinated['subscriptionCreation']);
        }
        return ($coordinated['journalStarted'] ?? null) === true
            && ($coordinated['journalCompleted'] ?? null) === true
            && ($coordinated['networkAccess'] ?? null) === true
            && ($coordinated['providerContact'] ?? null) === true
            && ($coordinated['providerMutation'] ?? null) === true
            && ($coordinated['checkoutCreation'] ?? null) === true
            && ($coordinated['subscriptionCreation'] ?? null) === true
            && ($coordinated['retryAuthorized'] ?? null) === false;
    }
}

if (!function_exists('red_addon_subscription_checkout_public_response')) {
    function red_addon_subscription_checkout_public_response(
        $execution,
        $subjectRecordId,
        $offerId,
        $coordinated
    ) {
        $result = red_addon_subscription_checkout_public_response_result();
        if (!red_addon_subscription_checkout_execution_valid($execution)
            || !is_int($subjectRecordId)
            || $subjectRecordId < 1
            || !is_string($offerId)
            || preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/D', $offerId) !== 1
            || !red_addon_subscription_checkout_coordinator_result_valid(
                $coordinated,
                $subjectRecordId,
                $offerId
            )
        ) {
            return $result;
        }
        $checkoutUrl = $coordinated['checkoutUrl'];
        $authorization = json_encode([
            'schema' => 1,
            'purpose' => 'subscription-checkout-browser-handoff',
            'subjectRecordId' => $subjectRecordId,
            'offerId' => $offerId,
            'intentReference' => $coordinated['intentReference'],
            'contractSha256' => $coordinated['contractSha256'],
            'requestSha256' => $coordinated['requestSha256'],
            'responseEvidenceSha256' =>
                $coordinated['responseEvidenceSha256'],
            'resultSha256' => $coordinated['resultSha256'],
            'checkoutSessionRefSha256' =>
                $coordinated['checkoutSessionRefSha256'],
            'executionOutcome' => $execution['outcome'],
            'executionReason' => $execution['reason'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($authorization)) {
            return $result;
        }
        $body = json_encode([
            'ok' => true,
            'outcome' => 'subscription_checkout_ready',
            'checkoutUrl' => $checkoutUrl,
            'navigationMode' => 'location.assign',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($body) || strlen($body) > 4608) {
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
            'outcome' => 'subscription_checkout_ready',
            'checkoutUrl' => $checkoutUrl,
            'navigationMode' => 'location.assign',
            'transientOnly' => true,
            'navigationAuthorized' => true,
            'authorizationSha256' => hash('sha256', $authorization),
            'reason' => 'handoff_ready',
        ];
    }
}

if (!function_exists('red_addon_subscription_checkout_public_response_valid')) {
    function red_addon_subscription_checkout_public_response_valid($response)
    {
        if (!is_array($response)
            || array_keys($response) !== array_keys(
                red_addon_subscription_checkout_public_response_result()
            )
            || ($response['valid'] ?? null) !== true
            || ($response['httpStatus'] ?? null) !== 200
            || ($response['ok'] ?? null) !== true
            || ($response['outcome'] ?? '')
                !== 'subscription_checkout_ready'
            || ($response['navigationMode'] ?? '') !== 'location.assign'
            || ($response['transientOnly'] ?? null) !== true
            || ($response['navigationAuthorized'] ?? null) !== true
            || ($response['reason'] ?? '') !== 'handoff_ready'
            || !red_addon_subscription_checkout_public_url_valid(
                $response['checkoutUrl'] ?? null
            )
            || !red_addon_valid_sha256(
                $response['authorizationSha256'] ?? null
            )
        ) {
            return false;
        }
        $body = json_encode([
            'ok' => true,
            'outcome' => 'subscription_checkout_ready',
            'checkoutUrl' => $response['checkoutUrl'],
            'navigationMode' => 'location.assign',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($body)
            && $response['body'] === $body
            && $response['headers'] === [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Length' => (string) strlen($body),
            ];
    }
}

if (!function_exists('red_addon_subscription_checkout_public_response_emit')) {
    function red_addon_subscription_checkout_public_response_emit($response)
    {
        if (!red_addon_subscription_checkout_public_response_valid($response)) {
            throw new InvalidArgumentException(
                'Subscription Checkout public response is invalid.'
            );
        }
        if (headers_sent()) {
            throw new RuntimeException(
                'Subscription Checkout headers were sent prematurely.'
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

?>
