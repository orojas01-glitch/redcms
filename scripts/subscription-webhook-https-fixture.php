<?php

declare(strict_types=1);

$projectRoot = realpath((string) getenv('RED_SUBSCRIPTION_WEBHOOK_CORE_ROOT'));
$adapterRoot = realpath((string) getenv(
    'RED_SUBSCRIPTION_WEBHOOK_ADAPTER_ROOT'
));
if (!is_string($projectRoot)
    || !is_string($adapterRoot)
    || !is_dir($projectRoot)
    || !is_dir($adapterRoot)
) {
    http_response_code(404);
    exit;
}
require_once $projectRoot
    . '/includes/addon_subscription_webhook_endpoint_helpers.php';
require_once $adapterRoot . '/StripeBoundedJsonDecoder.php';
require_once $adapterRoot . '/StripeSandboxWebhookSignatureEnvelope.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$target = $_SERVER['REQUEST_URI'] ?? '';
if (!red_addon_subscription_webhook_candidate($target)) {
    http_response_code(404);
    exit;
}
if ($method !== 'POST') {
    red_addon_subscription_webhook_emit(
        red_addon_subscription_webhook_response(
            405,
            ['ok' => false, 'error' => 'method_not_allowed'],
            'method_invalid'
        )
    );
    exit;
}
if (red_addon_subscription_webhook_preflight($_SERVER) === null) {
    error_log(json_encode([
        'subscriptionWebhookPreflight' => 'refused',
        'method' => $method,
        'target' => $target,
        'https' => $_SERVER['HTTPS'] ?? null,
        'contentType' => $_SERVER['CONTENT_TYPE'] ?? null,
        'contentLength' => $_SERVER['CONTENT_LENGTH'] ?? null,
        'httpContentTypePresent' =>
            isset($_SERVER['HTTP_CONTENT_TYPE']),
        'httpContentLengthPresent' =>
            isset($_SERVER['HTTP_CONTENT_LENGTH']),
        'signaturePresent' => isset($_SERVER['HTTP_STRIPE_SIGNATURE']),
        'transferEncodingPresent' =>
            isset($_SERVER['HTTP_TRANSFER_ENCODING'])
            || isset($_SERVER['TRANSFER_ENCODING']),
        'contentEncodingPresent' =>
            isset($_SERVER['HTTP_CONTENT_ENCODING'])
            || isset($_SERVER['CONTENT_ENCODING']),
    ], JSON_UNESCAPED_SLASHES));
}
$capture = red_addon_subscription_webhook_capture_current();
$result = red_addon_subscription_webhook_dispatch(
    $method,
    $target,
    $capture,
    true,
    static function (array $request): array {
        $secret = (string) getenv(
            'RED_SUBSCRIPTION_WEBHOOK_PROOF_SECRET'
        );
        $verified =
            RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
                verify(
                    $request['rawBody'],
                    $request['signatureHeader'],
                    $secret,
                    $request['receivedAt']
                );
        $secret = '';
        $success = ($verified['valid'] ?? false) === true;
        return [
            'invoked' => true,
            'success' => $success,
            'route' =>
                'redcms.store-lite-stripe-checkout/provider-events',
            'package' => 'redcms.store-lite-stripe-checkout',
            'statusCode' => $success ? 200 : 400,
            'data' => $success ? ['acknowledged' => true] : [],
            'error' => $success
                ? '' : 'subscription_webhook_signature_refused',
            'reason' => $success ? 'completed' : 'handler_refused',
        ];
    }
);
red_addon_subscription_webhook_emit($result);
exit;
