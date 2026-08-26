<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/addon_webhook_request_helpers.php';
$adapterRoot = realpath(
    dirname(__DIR__) . '/../redcms-store-lite-stripe-checkout/package'
);
if (!is_string($adapterRoot)) {
    fwrite(STDERR, "Stripe adapter package is unavailable.\n");
    exit(1);
}
require_once $adapterRoot . '/StripeBoundedJsonDecoder.php';
require_once $adapterRoot . '/StripeSandboxWebhookSignatureEnvelope.php';

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

$route = 'redcms.store-lite-stripe-checkout/provider-events';
$package = 'redcms.store-lite-stripe-checkout';
$secret = 'whsec_synthetic_core_boundary_123456789';
$receivedAt = 1787630550;
$event = [
    'id' => 'evt_CoreWebhookBoundary123456',
    'object' => 'event',
    'api_version' => '2024-09-30.acacia',
    'created' => 1787630400,
    'data' => ['object' => [
        'id' => 'cs_test_CoreWebhookBoundary123456',
        'object' => 'checkout.session',
        'padding' => str_repeat('p', 20000),
    ]],
    'livemode' => false,
    'type' => 'checkout.session.completed',
];
$rawBody = json_encode(
    $event,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);
$signature = hash_hmac(
    'sha256',
    $receivedAt . '.' . $rawBody,
    $secret
);
$header = 't=' . $receivedAt . ',v1=' . $signature;
$manifest = [
    'id' => $package,
    'routes' => [[
        'id' => $route,
        'scope' => 'public',
        'path' => '/addons/redcms/store-lite-stripe-checkout/provider-events',
        'methods' => ['POST'],
        'authentication' => 'server-signature',
        'csrf' => 'not-applicable',
    ]],
];
$access = new RED_Addon_Runtime_Secret_Access(
    $package,
    ['stripe.webhook-secret' => $secret]
);
$handler = static function (
    RED_Addon_Webhook_Request $request
): RED_Addon_Webhook_Result {
    $apiKey = null;
    $apiResolution = $request->secret('stripe.secret-key', $apiKey);
    $webhookSecret = null;
    $webhookResolution = $request->secret(
        'stripe.webhook-secret',
        $webhookSecret
    );
    if (($apiResolution['resolved'] ?? true) !== false
        || $apiKey !== null
        || ($webhookResolution['resolved'] ?? false) !== true
        || !is_string($webhookSecret)
    ) {
        return RED_Addon_Webhook_Result::refused(
            'webhook_secret_scope_refused'
        );
    }
    $verified =
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $request->rawBody(),
                $request->signatureHeader(),
                $webhookSecret,
                $request->receivedAt()
            );
    $webhookSecret = null;
    return ($verified['valid'] ?? false) === true
        ? RED_Addon_Webhook_Result::accepted($verified)
        : RED_Addon_Webhook_Result::refused(
            'webhook_signature_refused'
        );
};

try {
    $source = (string) file_get_contents(
        dirname(__DIR__) . '/includes/addon_webhook_request_helpers.php'
    );
    $assert(
        !preg_match(
            '/\$_(?:GET|POST|COOKIE|SERVER|SESSION|ENV)'
                . '|\b(?:mysqli|PDO|curl|fsockopen|getenv|setcookie)\s*\('
                . '|\bheader\s*\(/',
            $source
        ),
        'core webhook boundary has no request-global, database, network, or response primitive'
    );

    $accepted = red_addon_webhook_invoke_registered(
        $route,
        $rawBody,
        $header,
        $receivedAt,
        $package,
        $handler,
        $manifest,
        $access
    );
    $assert(
        $accepted['invoked']
            && $accepted['success']
            && $accepted['statusCode'] === 200
            && $accepted['reason'] === 'completed'
            && ($accepted['data']['verification'] ?? '') === 'verified',
        'dedicated boundary carries a verified twenty-kilobyte raw event'
    );
    $assert(
        strlen($rawBody) > 16384
            && ($accepted['data']['bodyBytes'] ?? 0) === strlen($rawBody),
        'webhook capacity expands without using the ordinary typed payload'
    );
    $encoded = json_encode(
        $accepted,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $assert(
        !str_contains($encoded, $rawBody)
            && !str_contains($encoded, $header)
            && !str_contains($encoded, $secret),
        'invocation result exposes no raw body, signature, or secret'
    );

    $adapterPayloadRefused = false;
    try {
        new RED_Addon_Adapter_Request(
            'redcms.store-lite-stripe-checkout/checkout',
            'contract.probe',
            ['rawBody' => str_repeat('x', 20000)]
        );
    } catch (InvalidArgumentException $exception) {
        $adapterPayloadRefused = true;
    }
    $assert(
        $adapterPayloadRefused,
        'ordinary adapter payload remains capped below the webhook body size'
    );

    $badHeader = red_addon_webhook_invoke_registered(
        $route,
        $rawBody,
        't=' . $receivedAt . ',v1=' . str_repeat('0', 64),
        $receivedAt,
        $package,
        $handler,
        $manifest,
        $access
    );
    $assert(
        $badHeader['invoked']
            && !$badHeader['success']
            && $badHeader['statusCode'] === 400
            && $badHeader['error'] === 'webhook_signature_refused',
        'signature refusal stays a bounded handler result'
    );

    $oversized = red_addon_webhook_invoke_registered(
        $route,
        str_repeat('x', 262145),
        $header,
        $receivedAt,
        $package,
        $handler,
        $manifest,
        $access
    );
    $assert(
        !$oversized['invoked']
            && $oversized['reason'] === 'invalid_request',
        'oversized body is refused before package invocation'
    );

    $twoSecretAccess = new RED_Addon_Runtime_Secret_Access(
        $package,
        [
            'stripe.secret-key' => 'rk_test_synthetic_scope_123456789',
            'stripe.webhook-secret' => $secret,
        ]
    );
    $assert(
        red_addon_webhook_invoke_registered(
            $route,
            $rawBody,
            $header,
            $receivedAt,
            $package,
            $handler,
            $manifest,
            $twoSecretAccess
        )['invoked'] === false,
        'boundary requires exactly one scoped secret setting'
    );

    $wrongManifest = $manifest;
    $wrongManifest['routes'][0]['authentication'] = 'public';
    $assert(
        red_addon_webhook_invoke_registered(
            $route,
            $rawBody,
            $header,
            $receivedAt,
            $package,
            $handler,
            $wrongManifest,
            $access
        )['invoked'] === false,
        'non-signature route declaration is refused'
    );

    foreach ([
        static fn (RED_Addon_Webhook_Request $request) =>
            RED_Addon_Webhook_Result::accepted([
                'leak' => $request->rawBody(),
            ]),
        static fn (RED_Addon_Webhook_Request $request) =>
            RED_Addon_Webhook_Result::accepted([
                'leak' => $request->signatureHeader(),
            ]),
        static fn (RED_Addon_Webhook_Request $request) =>
            RED_Addon_Webhook_Result::accepted([
                'leak' => (function () use ($request): string {
                    $value = null;
                    $request->secret('stripe.webhook-secret', $value);
                    return (string) $value;
                })(),
            ]),
    ] as $leakingHandler) {
        $leaked = red_addon_webhook_invoke_registered(
            $route,
            $rawBody,
            $header,
            $receivedAt,
            $package,
            $leakingHandler,
            $manifest,
            $access
        );
        $assert(
            !$leaked['success']
                && in_array(
                    $leaked['reason'],
                    ['sensitive_disclosure', 'handler_failed'],
                    true
                )
                && $leaked['data'] === [],
            'raw body, signature, and secret disclosure are contained'
        );
    }

    $throwing = red_addon_webhook_invoke_registered(
        $route,
        $rawBody,
        $header,
        $receivedAt,
        $package,
        static function (): never {
            throw new RuntimeException('synthetic_handler_failure');
        },
        $manifest,
        $access
    );
    $assert(
        $throwing['invoked']
            && !$throwing['success']
            && $throwing['reason'] === 'handler_failed',
        'handler exception is contained'
    );

    echo 'Core webhook request boundary passed '
        . $assertions . " assertions.\n";
    echo "No request-global read, real secret, route exposure, network, Stripe, database, payment, browser, or deployment action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

exit(0);
