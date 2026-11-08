<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__)
    . '/includes/addon_wompi_webhook_endpoint_helpers.php';
require_once dirname(__DIR__) . '/includes/addon_runtime_secret_helpers.php';

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

// Synthetic values only. None of these is a real Wompi key.
$path = red_addon_wompi_webhook_path();
$receivedAt = 1794000000;
$eventsSecret = 'test_events_' . str_repeat('S', 16);
$orderId = 'ord_' . str_repeat('ab', 16);
$body = json_encode(
    ['event' => 'transaction.updated', 'data' => ['transaction' => []]],
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);
$checksum = strtoupper(hash('sha256', 'synthetic-checksum'));
$server = [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => $path,
    'HTTPS' => 'on',
    'CONTENT_TYPE' => 'application/json',
    'CONTENT_LENGTH' => (string) strlen($body),
    'HTTP_X_EVENT_CHECKSUM' => $checksum,
];
$storeOrder = [
    'orderId' => $orderId,
    'snapshotSha256' => hash('sha256', 'synthetic-order'),
    'paymentMethod' => 'wompi_checkout',
    'paymentKind' => 'hosted',
    'currency' => 'COP',
    'totalMinor' => 2490000,
    'orderStatus' => 'pending',
    'paymentStatus' => 'pending',
    'fulfillmentStatus' => 'unfulfilled',
];

$invocation = static function (
    bool $success,
    int $statusCode
): array {
    return [
        'invoked' => true,
        'success' => $success,
        'route' => 'redcms.store-lite-wompi/provider-events',
        'package' => 'redcms.store-lite-wompi',
        'statusCode' => $statusCode,
        'data' => [],
        'error' => '',
        'reason' => $success ? 'completed' : 'handler_refused',
    ];
};

$webhookRequest = static function (
    array $secrets
) use ($body, $checksum, $receivedAt): RED_Addon_Webhook_Request {
    return new RED_Addon_Webhook_Request(
        'redcms.store-lite-wompi/provider-events',
        $body,
        $checksum,
        $receivedAt,
        new RED_Addon_Runtime_Secret_Access(
            'redcms.store-lite-wompi',
            $secrets
        )
    );
};

try {
    // Targeting and gates.
    $assert(
        red_addon_wompi_webhook_candidate($path)
            && !red_addon_wompi_webhook_candidate($path . '?x=1')
            && !red_addon_wompi_webhook_candidate($path . '/')
            && !red_addon_wompi_webhook_candidate(
                '/addons/redcms/store-lite-stripe-checkout/provider-events'
            ),
        'only the exact query-free Wompi provider-events target is a candidate'
    );
    putenv('RED_WOMPI_WEBHOOK_ENDPOINT_ENABLED');
    putenv('RED_WOMPI_WEBHOOK_MODE');
    $assert(
        red_addon_wompi_webhook_endpoint_enabled() === false,
        'the endpoint is disabled by default'
    );
    putenv('RED_WOMPI_WEBHOOK_ENDPOINT_ENABLED=1');
    $assert(
        red_addon_wompi_webhook_endpoint_enabled() === false,
        'enabling without a mode keeps the endpoint disabled'
    );
    foreach (['production', 'live', 'Sandbox'] as $mode) {
        putenv('RED_WOMPI_WEBHOOK_MODE=' . $mode);
        $assert(
            red_addon_wompi_webhook_endpoint_enabled() === false,
            'mode ' . $mode . ' is refused; only sandbox exists'
        );
    }
    putenv('RED_WOMPI_WEBHOOK_MODE=sandbox');
    $assert(
        red_addon_wompi_webhook_endpoint_enabled() === true,
        'explicit enable plus sandbox mode opens the endpoint'
    );
    putenv('RED_WOMPI_WEBHOOK_ENDPOINT_ENABLED');
    putenv('RED_WOMPI_WEBHOOK_MODE');

    // Transport preflight.
    $assert(
        red_addon_wompi_webhook_preflight($server) === [
            'bodyBytes' => strlen($body),
            'checksumHeader' => $checksum,
        ],
        'direct HTTPS preflight keeps only the length and checksum header'
    );
    $assert(
        red_addon_wompi_webhook_preflight(array_replace($server, [
            'HTTP_X_EVENT_CHECKSUM' => strtolower($checksum),
            'CONTENT_TYPE' => 'application/json; charset=utf-8',
        ])) !== null,
        'lower-case checksum and UTF-8 JSON content type are accepted'
    );
    $lengthless = $server;
    unset($lengthless['CONTENT_LENGTH']);
    $lengthless['HTTP_TRANSFER_ENCODING'] = 'chunked';
    $assert(
        red_addon_wompi_webhook_preflight($lengthless)['bodyBytes'] === 0,
        'a chunked delivery without a length is accepted for a bounded read'
    );
    $missingChecksum = $server;
    unset($missingChecksum['HTTP_X_EVENT_CHECKSUM']);
    foreach ([
        'checksum_header_invalid' => $missingChecksum,
        'checksum_header_invalid ' => array_replace($server, [
            'HTTP_X_EVENT_CHECKSUM' => str_repeat('g', 64),
        ]),
        'method_invalid' => array_replace($server, ['REQUEST_METHOD' => 'GET']),
        'target_invalid' => array_replace($server, ['REQUEST_URI' => $path . '?a=1']),
        'https_invalid' => array_replace($server, ['HTTPS' => 'off']),
        'content_type_invalid' => array_replace($server, ['CONTENT_TYPE' => 'text/plain']),
        'content_length_invalid' => array_replace($server, ['CONTENT_LENGTH' => '65537']),
        'content_encoding_invalid' => $server + ['HTTP_CONTENT_ENCODING' => 'gzip'],
        'transfer_conflict' => $server + ['HTTP_TRANSFER_ENCODING' => 'chunked'],
        'content_length_alias_invalid' => $server + ['HTTP_CONTENT_LENGTH' => '1'],
    ] as $reason => $candidate) {
        $assert(
            red_addon_wompi_webhook_preflight_reason($candidate)
                === trim($reason)
                && red_addon_wompi_webhook_preflight($candidate) === null,
            'preflight refuses with ' . trim($reason)
        );
    }

    // Capture.
    $capture = red_addon_wompi_webhook_capture($server, $body, $receivedAt);
    $assert(
        $capture['valid'] === true
            && $capture['request'] instanceof
                RED_Addon_Wompi_Webhook_Ingress_Request
            && !str_contains(print_r($capture['request'], true), $body),
        'capture keeps the raw body out of debug output'
    );
    $assert(
        red_addon_wompi_webhook_capture($server, $body . ' ', $receivedAt)['valid']
            === false
            && red_addon_wompi_webhook_capture(
                $server,
                "\xff\xfe",
                $receivedAt
            )['valid'] === false,
        'a body that differs from Content-Length or is not UTF-8 is refused'
    );

    // Dispatch and response mapping.
    $assert(
        red_addon_wompi_webhook_dispatch('POST', $path, $capture, false, null)
            === red_addon_subscription_webhook_endpoint_result(),
        'a disabled endpoint claims nothing'
    );
    $assert(
        red_addon_wompi_webhook_dispatch('GET', $path, $capture, true, null)['status']
            === 405,
        'a non-POST request receives 405'
    );
    $assert(
        red_addon_wompi_webhook_dispatch(
            'POST',
            $path,
            red_addon_wompi_webhook_capture_result(),
            true,
            static fn () => []
        )['status'] === 400,
        'an invalid capture receives 400 without running the handler'
    );
    $seen = null;
    $accepted = red_addon_wompi_webhook_dispatch(
        'POST',
        $path,
        $capture,
        true,
        static function (array $material) use (&$seen, $invocation): array {
            $seen = $material;
            return $invocation(true, 200);
        }
    );
    $assert(
        $seen === [
            'rawBody' => $body,
            'signatureHeader' => $checksum,
            'receivedAt' => $receivedAt,
        ]
            && $accepted['status'] === 200
            && $accepted['body'] === '{"ok":true}'
            && red_addon_subscription_webhook_response_valid($accepted),
        'an accepted delivery passes exact material and returns a bare 200'
    );
    foreach ([
        [$invocation(false, 400), 400],
        [$invocation(false, 500), 500],
        [['invoked' => false], 500],
    ] as [$result, $expected]) {
        $response = red_addon_wompi_webhook_dispatch(
            'POST',
            $path,
            $capture,
            true,
            static fn () => $result
        );
        $assert(
            $response['status'] === $expected
                && !str_contains($response['body'], $orderId)
                && !str_contains($response['body'], $checksum),
            'runner status ' . ($result['statusCode'] ?? 'none')
                . ' maps to ' . $expected . ' with a generic body'
        );
    }
    $assert(
        red_addon_wompi_webhook_dispatch(
            'POST',
            $path,
            $capture,
            true,
            static function (): array {
                throw new RuntimeException('runner failed');
            }
        )['status'] === 500,
        'a failing runner asks Wompi to retry'
    );

    // Handler: secret scope and coordinator wiring.
    $calls = [];
    $coordinatorResult = [
        'httpStatus' => 200,
        'disposition' => 'applied',
        'outcome' => 'paid',
        'eventEvidenceSha256' => str_repeat('e', 64),
        'transactionId' => '1234-1794000000-49201',
        'orderId' => $orderId,
        'error' => '',
    ];
    $coordinator = static function (...$args) use (
        &$calls,
        &$coordinatorResult
    ): array {
        $calls['coordinator'] = array_slice($args, 0, 6);
        $calls['callables'] = array_slice($args, 6);
        return $coordinatorResult;
    };
    $lookup = static fn (string $url): ?string => null;
    $services = [];
    $serviceInvoker = static function (
        string $service,
        string $operation,
        array $input
    ) use (&$services, $storeOrder): array {
        $services[] = [$service, $operation, $input];
        if ($operation === 'payment.order.read') {
            return [
                'success' => true,
                'data' => $input['orderId'] === $storeOrder['orderId']
                    ? ['found' => true, 'order' => $storeOrder]
                    : ['found' => false, 'order' => []],
            ];
        }
        return ['success' => true, 'data' => ['status' => 'applied']];
    };

    $result = red_addon_wompi_webhook_handle(
        $webhookRequest(['wompi.event-secret' => $eventsSecret]),
        $coordinator,
        $lookup,
        $serviceInvoker,
        $receivedAt + 1
    );
    $assert(
        $result->successState() === true
            && $result->statusCode() === 200
            && $result->data() === ['disposition' => 'applied', 'outcome' => 'paid'],
        'an applied coordinator decision is accepted with value-free data'
    );
    $assert(
        $calls['coordinator'] === [
            $body,
            $checksum,
            'sandbox',
            $eventsSecret,
            $receivedAt + 1,
            [],
        ]
            && $calls['callables'][0] === $lookup,
        'the coordinator receives the raw body, header, sandbox and only the events secret'
    );
    [, $loadOrder, $apply] = $calls['callables'];
    $assert(
        $loadOrder($orderId) === $storeOrder
            && $services[0] === [
                'commerce.orders',
                'payment.order.read',
                ['orderId' => $orderId],
            ],
        'loadOrder reads the Store Lite projection through commerce.orders'
    );
    $services = [];
    $assert(
        $loadOrder('ord_' . str_repeat('cd', 16)) === null
            && $loadOrder('not-an-order') === null
            && count($services) === 1,
        'an unknown order is null and a malformed reference never reaches Store Lite'
    );
    $assert(
        $apply($storeOrder, ['outcome' => 'paid'])
            === ['ok' => true, 'status' => 'applied']
            && end($services) === [
                'commerce.orders',
                'payment.event.apply',
                ['order' => $storeOrder, 'event' => ['outcome' => 'paid']],
            ],
        'apply forwards the order and event to payment.event.apply'
    );
    $failingInvoker = static fn () => [
        'success' => false,
        'error' => 'payment_order_state_conflict',
    ];
    $result = red_addon_wompi_webhook_handle(
        $webhookRequest(['wompi.event-secret' => $eventsSecret]),
        $coordinator,
        $lookup,
        $failingInvoker,
        $receivedAt
    );
    [, $failingLoad, $failingApply] = $calls['callables'];
    $loadThrew = false;
    try {
        $failingLoad($orderId);
    } catch (RuntimeException $exception) {
        $loadThrew = true;
    }
    $assert(
        $loadThrew
            && $failingApply($storeOrder, [])
                === ['ok' => false, 'error' => 'payment_order_state_conflict'],
        'a failed order read throws (retry) and an apply error keeps its code'
    );

    foreach ([
        401 => [false, 400],
        503 => [false, 500],
        0 => [false, 500],
    ] as $httpStatus => [$success, $status]) {
        $coordinatorResult['httpStatus'] = $httpStatus;
        $result = red_addon_wompi_webhook_handle(
            $webhookRequest(['wompi.event-secret' => $eventsSecret]),
            $coordinator,
            $lookup,
            $serviceInvoker,
            $receivedAt
        );
        $assert(
            $result->successState() === $success
                && $result->statusCode() === $status,
            'coordinator ' . $httpStatus . ' becomes webhook ' . $status
        );
    }
    $coordinatorResult['httpStatus'] = 200;

    $calls = [];
    foreach ([
        'missing secret' => ['wompi.integrity-key' => 'test_integrity_' . str_repeat('S', 16)],
        'production secret' => ['wompi.event-secret' => 'prod_events_' . str_repeat('S', 16)],
    ] as $label => $secrets) {
        $result = red_addon_wompi_webhook_handle(
            $webhookRequest($secrets),
            $coordinator,
            $lookup,
            $serviceInvoker,
            $receivedAt
        );
        $assert(
            $result->statusCode() === 500 && $calls === [],
            $label . ' is refused before the coordinator runs'
        );
    }
    $result = red_addon_wompi_webhook_handle(
        $webhookRequest(['wompi.event-secret' => $eventsSecret]),
        static function (): array {
            throw new RuntimeException('coordinator failed');
        },
        $lookup,
        $serviceInvoker,
        $receivedAt
    );
    $assert(
        $result->statusCode() === 500,
        'a failing coordinator asks Wompi to retry'
    );
    $assert(
        red_addon_wompi_webhook_handle(
            $webhookRequest(['wompi.event-secret' => $eventsSecret]),
            $coordinator,
            $lookup,
            $serviceInvoker,
            $receivedAt - 1
        )->statusCode() === 500,
        'a completion time before receipt is refused'
    );

    // Front controller ordering.
    $index = (string) file_get_contents(dirname(__DIR__) . '/index.php');
    $start = strpos($index, "=== '/addons/redcms/store-lite-wompi/provider-events'");
    $disabled = strpos($index, 'red_addon_wompi_webhook_endpoint_enabled()', (int) $start);
    $preflight = strpos($index, 'red_addon_wompi_webhook_preflight($_SERVER)', (int) $start);
    $database = strpos($index, 'mysqli_connect', (int) $start);
    $publicRoutes = strpos($index, '$redPublicReadMethod =');
    $assert(
        $start !== false
            && $disabled !== false
            && $preflight !== false
            && $database !== false
            && $disabled < $preflight
            && $preflight < $database
            && $database < $publicRoutes,
        'index.php checks the gate and transport before any database access'
    );

    // Optional: run the real W3 coordinator when the package is available.
    $packageRoot = getenv('RED_WOMPI_PACKAGE_ROOT');
    if (is_string($packageRoot) && $packageRoot !== '') {
        require_once $packageRoot . '/WompiEventIngressCoordinator.php';
        $tx = [
            'id' => '1234-1794000000-49201',
            'amount_in_cents' => 2490000,
            'reference' => $orderId,
            'customer_email' => 'synthetic@example.test',
            'currency' => 'COP',
            'payment_method_type' => 'PSE',
            'status' => 'APPROVED',
        ];
        $timestamp = $receivedAt - 30;
        $signedChecksum = hash(
            'sha256',
            $tx['id'] . $tx['status'] . $tx['amount_in_cents']
                . $timestamp . $eventsSecret
        );
        $signedBody = json_encode([
            'event' => 'transaction.updated',
            'data' => ['transaction' => $tx],
            'environment' => 'test',
            'signature' => [
                'properties' => [
                    'transaction.id',
                    'transaction.status',
                    'transaction.amount_in_cents',
                ],
                'checksum' => $signedChecksum,
            ],
            'timestamp' => $timestamp,
            'sent_at' => '2026-11-08T00:00:00.000Z',
        ], JSON_THROW_ON_ERROR);
        $lookedUp = [];
        $services = [];
        $real = red_addon_wompi_webhook_handle(
            new RED_Addon_Webhook_Request(
                'redcms.store-lite-wompi/provider-events',
                $signedBody,
                strtoupper($signedChecksum),
                $receivedAt,
                new RED_Addon_Runtime_Secret_Access(
                    'redcms.store-lite-wompi',
                    ['wompi.event-secret' => $eventsSecret]
                )
            ),
            ['RED_CMS_Store_Lite_Wompi_Event_Ingress_Coordinator', 'handle'],
            static function (string $url) use (&$lookedUp, $tx): string {
                $lookedUp[] = $url;
                return json_encode(['data' => $tx], JSON_THROW_ON_ERROR);
            },
            $serviceInvoker,
            $receivedAt
        );
        $assert(
            $real->successState() === true
                && $real->data() === ['disposition' => 'applied', 'outcome' => 'paid']
                && $lookedUp === [
                    'https://sandbox.wompi.co/v1/transactions/1234-1794000000-49201',
                ]
                && array_column($services, 1) === [
                    'payment.order.read',
                    'payment.event.apply',
                ],
            'the real W3 coordinator applies a signed APPROVED event as paid'
        );
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

echo 'Wompi webhook endpoint self-test passed: ' . $assertions
    . " assertions.\n";
