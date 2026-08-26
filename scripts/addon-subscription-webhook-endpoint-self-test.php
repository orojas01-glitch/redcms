<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/addon_subscription_webhook_endpoint_helpers.php';

$assertions = 0;
$assert = static function (
    bool $condition,
    string $message
) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$path = red_addon_subscription_webhook_path();
$receivedAt = 1787630600;
$body = json_encode([
    'id' => 'evt_EndpointSelfTest123456',
    'object' => 'event',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$signature = 't=' . $receivedAt . ',v1=' . str_repeat('a', 64);
$server = [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => $path,
    'HTTPS' => 'on',
    'CONTENT_TYPE' => 'application/json',
    'CONTENT_LENGTH' => (string) strlen($body),
    'HTTP_STRIPE_SIGNATURE' => $signature,
];

$invocation = static function (
    bool $success,
    int $statusCode,
    string $error = ''
): array {
    return [
        'invoked' => true,
        'success' => $success,
        'route' => 'redcms.store-lite-stripe-checkout/provider-events',
        'package' => 'redcms.store-lite-stripe-checkout',
        'statusCode' => $statusCode,
        'data' => $success ? ['acknowledged' => true] : [],
        'error' => $error,
        'reason' => $success ? 'completed' : 'handler_refused',
    ];
};

try {
    $assert(
        red_addon_subscription_webhook_candidate($path)
            && !red_addon_subscription_webhook_candidate($path . '?x=1')
            && !red_addon_subscription_webhook_candidate(
                '/addons/redcms/store-lite-stripe-checkout/other'
            ),
        'only the exact query-free provider-events target is a candidate'
    );
    $preflight = red_addon_subscription_webhook_preflight($server);
    $assert(
        $preflight === [
            'bodyBytes' => strlen($body),
            'signatureHeader' => $signature,
        ],
        'direct HTTPS preflight preserves only length and signature material'
    );
    $capture = red_addon_subscription_webhook_capture(
        $server,
        $body,
        $receivedAt
    );
    $assert(
        ($capture['valid'] ?? false)
            && ($capture['reason'] ?? '') === 'captured'
            && ($capture['request'] ?? null) instanceof
                RED_Addon_Subscription_Webhook_Ingress_Request,
        'exact body and server projection create one opaque ingress request'
    );
    $request = $capture['request'];
    $debug = $request->__debugInfo();
    $assert(
        $debug === [
            'receivedAt' => $receivedAt,
            'bodyBytes' => strlen($body),
            'bodySha256' => hash('sha256', $body),
        ]
            && !str_contains(
                json_encode($debug, JSON_THROW_ON_ERROR),
                $body
            )
            && !str_contains(
                json_encode($debug, JSON_THROW_ON_ERROR),
                $signature
            ),
        'debug projection contains no raw body or signature'
    );
    $serializationRefused = false;
    try {
        serialize($request);
    } catch (LogicException $exception) {
        $serializationRefused = true;
    }
    $cloneRefused = false;
    try {
        clone $request;
    } catch (LogicException $exception) {
        $cloneRefused = true;
    }
    $assert(
        $serializationRefused && $cloneRefused,
        'opaque ingress request cannot be serialized or cloned'
    );

    foreach ([
        'http' => array_replace($server, ['HTTPS' => 'off']),
        'query' => array_replace($server, [
            'REQUEST_URI' => $path . '?attempt=1',
        ]),
        'type' => array_replace($server, [
            'CONTENT_TYPE' => 'application/json; charset=UTF-8',
        ]),
        'transfer' => array_replace($server, [
            'HTTP_TRANSFER_ENCODING' => 'chunked',
        ]),
        'alias-drift' => array_replace($server, [
            'HTTP_CONTENT_TYPE' => 'text/plain',
            'HTTP_CONTENT_LENGTH' => (string) (strlen($body) + 1),
        ]),
        'signature' => array_replace($server, [
            'HTTP_STRIPE_SIGNATURE' => "bad\nvalue",
        ]),
    ] as $name => $changedServer) {
        $assert(
            red_addon_subscription_webhook_preflight($changedServer)
                === null,
            $name . ' transport projection is refused before body access'
        );
    }
    $matchingAliases = array_replace($server, [
        'HTTP_CONTENT_TYPE' => $server['CONTENT_TYPE'],
        'HTTP_CONTENT_LENGTH' => $server['CONTENT_LENGTH'],
    ]);
    $assert(
        red_addon_subscription_webhook_preflight($matchingAliases)
            === $preflight,
        'supported-server content aliases must match canonical values exactly'
    );
    $lengthDrift = red_addon_subscription_webhook_capture(
        array_replace($server, [
            'CONTENT_LENGTH' => (string) (strlen($body) + 1),
        ]),
        $body,
        $receivedAt
    );
    $assert(
        !($lengthDrift['valid'] ?? true)
            && ($lengthDrift['reason'] ?? '') === 'transport_invalid',
        'body length drift is refused'
    );

    $runnerCalls = 0;
    $runner = static function (array $requestData) use (
        &$runnerCalls,
        $body,
        $signature,
        $receivedAt,
        $invocation
    ): array {
        $runnerCalls++;
        if ($requestData !== [
            'rawBody' => $body,
            'signatureHeader' => $signature,
            'receivedAt' => $receivedAt,
        ]) {
            return [];
        }
        return $invocation(true, 200);
    };
    $disabled = red_addon_subscription_webhook_dispatch(
        'POST',
        $path,
        $capture,
        false,
        $runner
    );
    $assert(
        $disabled === red_addon_subscription_webhook_endpoint_result()
            && $runnerCalls === 0,
        'default-disabled endpoint does not claim or invoke the route'
    );
    $darkResponse = red_addon_subscription_webhook_response(
        404,
        ['ok' => false, 'error' => 'not_found'],
        'endpoint_disabled'
    );
    $assert(
        red_addon_subscription_webhook_response_valid($darkResponse)
            && $darkResponse['body']
                === '{"ok":false,"error":"not_found"}',
        'front-controller dark state has one generic no-store 404 response'
    );
    $method = red_addon_subscription_webhook_dispatch(
        'GET',
        $path,
        red_addon_subscription_webhook_capture_result(),
        true,
        $runner
    );
    $assert(
        $method['status'] === 405
            && ($method['headers']['Allow'] ?? '') === 'POST'
            && $runnerCalls === 0,
        'enabled exact route refuses non-POST with the closed Allow header'
    );
    $accepted = red_addon_subscription_webhook_dispatch(
        'POST',
        $path,
        $capture,
        true,
        $runner
    );
    $assert(
        $accepted['status'] === 200
            && $accepted['body'] === '{"ok":true}'
            && $accepted['reason'] === 'acknowledged'
            && $runnerCalls === 1,
        'successful internal delivery emits one minimal acknowledgement'
    );
    $assert(
        red_addon_subscription_webhook_response_valid($accepted)
            && !str_contains($accepted['body'], $body)
            && !str_contains($accepted['body'], $signature),
        'acknowledgement is no-store, bounded, and contains no request material'
    );
    $signatureRefused = red_addon_subscription_webhook_dispatch(
        'POST',
        $path,
        $capture,
        true,
        static fn (array $requestData): array =>
            $invocation(
                false,
                400,
                'subscription_webhook_signature_refused'
            )
    );
    $assert(
        $signatureRefused['status'] === 400
            && $signatureRefused['body']
                === '{"ok":false,"error":"invalid_signature"}',
        'signature refusal maps to a stable public 400 response'
    );
    $retry = red_addon_subscription_webhook_dispatch(
        'POST',
        $path,
        $capture,
        true,
        static fn (array $requestData): array =>
            $invocation(
                false,
                500,
                'subscription_webhook_retry_required'
            )
    );
    $assert(
        $retry['status'] === 500
            && $retry['body']
                === '{"ok":false,"error":"temporarily_unavailable"}',
        'restartable failure maps to a stable public retry response'
    );
    $malformedRunner = red_addon_subscription_webhook_dispatch(
        'POST',
        $path,
        $capture,
        true,
        static fn (array $requestData): array => ['unexpected' => true]
    );
    $assert(
        $malformedRunner['status'] === 500
            && $malformedRunner['reason'] === 'runner_unavailable',
        'malformed runner output fails closed'
    );
    $tampered = $accepted;
    $tampered['headers']['Cache-Control'] = 'public';
    $assert(
        !red_addon_subscription_webhook_response_valid($tampered),
        'response validator refuses cache-policy drift'
    );

    $bufferLevel = ob_get_level();
    ob_start();
    red_addon_subscription_webhook_emit($accepted);
    $emitted = (string) ob_get_clean();
    $assert(
        ob_get_level() === $bufferLevel
            && http_response_code() === 200
            && $emitted === '{"ok":true}',
        'strict emitter writes only the validated response body'
    );

    $endpointSource = (string) file_get_contents(
        dirname(__DIR__)
            . '/includes/addon_subscription_webhook_endpoint_helpers.php'
    );
    $inputAt = strpos($endpointSource, "file_get_contents('php://input')");
    $preflightAt = strpos(
        $endpointSource,
        'red_addon_subscription_webhook_preflight($_SERVER)'
    );
    $indexSource = (string) file_get_contents(dirname(__DIR__) . '/index.php');
    $webhookBlockAt = strpos(
        $indexSource,
        '$redSubscriptionWebhookTarget'
    );
    $indexPreflightAt = is_int($webhookBlockAt)
        ? strpos(
            $indexSource,
            'red_addon_subscription_webhook_preflight($_SERVER)',
            $webhookBlockAt
        ) : false;
    $indexDatabaseAt = is_int($webhookBlockAt)
        ? strpos($indexSource, '@mysqli_connect', $webhookBlockAt)
        : false;
    $assert(
        substr_count($endpointSource, 'php://input') === 1
            && is_int($inputAt)
            && is_int($preflightAt)
            && $preflightAt < $inputAt
            && !str_contains($indexSource, 'php://input')
            && str_contains(
                $indexSource,
                'red_addon_subscription_webhook_endpoint_enabled()'
            )
            && is_int($webhookBlockAt)
            && is_int($indexPreflightAt)
            && is_int($indexDatabaseAt)
            && $webhookBlockAt < $indexPreflightAt
            && $indexPreflightAt < $indexDatabaseAt,
        'body and database I/O remain after exact config-gated preflight'
    );

    echo 'Subscription webhook endpoint passed '
        . $assertions . " assertions.\n";
    echo "No configured secret, network, Stripe, payment, browser, webhook activation, live mode, or deployment action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

exit(0);
