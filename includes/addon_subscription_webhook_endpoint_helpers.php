<?php
/** Default-disabled direct-HTTPS ingress for Stripe subscription webhooks. */

require_once __DIR__ . '/runtime_config_helpers.php';
require_once __DIR__ . '/addon_subscription_event_webhook_handler_helpers.php';

if (!class_exists('RED_Addon_Subscription_Webhook_Ingress_Request', false)) {
    final class RED_Addon_Subscription_Webhook_Ingress_Request
    {
        private static ?WeakMap $material = null;

        public function __construct(
            private int $receivedAt,
            private int $bodyBytes,
            private string $bodySha256,
            string $rawBody,
            string $signatureHeader
        ) {
            if ($receivedAt < 1
                || $receivedAt > 4102444800
                || $bodyBytes !== strlen($rawBody)
                || $bodyBytes < 2
                || $bodyBytes > 262144
                || !red_addon_valid_sha256($bodySha256)
                || $bodySha256 !== hash('sha256', $rawBody)
                || preg_match('//u', $rawBody) !== 1
                || strlen($signatureHeader) < 8
                || strlen($signatureHeader) > 4096
                || trim($signatureHeader) !== $signatureHeader
                || preg_match('/[^\x21-\x7E]/', $signatureHeader) === 1
            ) {
                throw new InvalidArgumentException(
                    'Subscription webhook ingress material is invalid.'
                );
            }
            if (!(self::$material instanceof WeakMap)) {
                self::$material = new WeakMap();
            }
            self::$material[$this] = [
                'rawBody' => $rawBody,
                'signatureHeader' => $signatureHeader,
            ];
        }

        public function receivedAt(): int
        {
            return $this->receivedAt;
        }

        public function bodyBytes(): int
        {
            return $this->bodyBytes;
        }

        public function bodySha256(): string
        {
            return $this->bodySha256;
        }

        public function material(
            &$rawBody = null,
            &$signatureHeader = null
        ): bool {
            $material = self::$material[$this] ?? null;
            if (!is_array($material)) {
                $rawBody = null;
                $signatureHeader = null;
                return false;
            }
            $rawBody = $material['rawBody'];
            $signatureHeader = $material['signatureHeader'];
            return true;
        }

        public function __serialize(): array
        {
            throw new LogicException(
                'Subscription webhook ingress cannot be serialized.'
            );
        }

        public function __clone(): void
        {
            throw new LogicException(
                'Subscription webhook ingress cannot be cloned.'
            );
        }

        public function __debugInfo(): array
        {
            return [
                'receivedAt' => $this->receivedAt,
                'bodyBytes' => $this->bodyBytes,
                'bodySha256' => $this->bodySha256,
            ];
        }
    }
}

if (!function_exists('red_addon_subscription_webhook_path')) {
    function red_addon_subscription_webhook_path()
    {
        return '/addons/redcms/store-lite-stripe-checkout/provider-events';
    }
}

if (!function_exists('red_addon_subscription_webhook_endpoint_enabled')) {
    function red_addon_subscription_webhook_endpoint_enabled()
    {
        $enabled = red_server_config_value(
            'SUBSCRIPTION_WEBHOOK_ENDPOINT_ENABLED',
            ['RED_SUBSCRIPTION_WEBHOOK_ENDPOINT_ENABLED'],
            false
        );
        $mode = red_server_config_value(
            'SUBSCRIPTION_WEBHOOK_MODE',
            ['RED_SUBSCRIPTION_WEBHOOK_MODE'],
            ''
        );
        return ($enabled === true || $enabled === '1')
            && $mode === 'sandbox';
    }
}

if (!function_exists('red_addon_subscription_webhook_candidate')) {
    function red_addon_subscription_webhook_candidate($requestTarget)
    {
        return is_string($requestTarget)
            && $requestTarget === red_addon_subscription_webhook_path();
    }
}

if (!function_exists('red_addon_subscription_webhook_content_type_valid')) {
    function red_addon_subscription_webhook_content_type_valid($value)
    {
        if (!is_string($value)
            || strlen($value) < 16
            || strlen($value) > 128
            || trim($value) !== $value
            || preg_match('/[^\x20-\x7E]/', $value) === 1
        ) {
            return false;
        }
        $parts = array_map('trim', explode(';', $value));
        if (strtolower(array_shift($parts)) !== 'application/json') {
            return false;
        }
        if ($parts === []) {
            return true;
        }
        return count($parts) === 1
            && preg_match(
                '/\Acharset\s*=\s*(?:utf-8|"utf-8")\z/Di',
                $parts[0]
            ) === 1;
    }
}

if (!function_exists('red_addon_subscription_webhook_preflight_reason')) {
    function red_addon_subscription_webhook_preflight_reason($server)
    {
        if (!is_array($server)) {
            return 'server_invalid';
        }
        if (($server['REQUEST_METHOD'] ?? null) !== 'POST') {
            return 'method_invalid';
        }
        if (!red_addon_subscription_webhook_candidate(
            $server['REQUEST_URI'] ?? null
        )) {
            return 'target_invalid';
        }
        if (!in_array($server['HTTPS'] ?? null, ['on', '1'], true)) {
            return 'https_invalid';
        }
        if (array_key_exists('HTTP_CONTENT_TYPE', $server)
            && ($server['HTTP_CONTENT_TYPE'] ?? null)
                !== ($server['CONTENT_TYPE'] ?? null)
        ) {
            return 'content_type_alias_invalid';
        }
        if (array_key_exists('HTTP_CONTENT_LENGTH', $server)
            && ($server['HTTP_CONTENT_LENGTH'] ?? null)
                !== ($server['CONTENT_LENGTH'] ?? null)
        ) {
            return 'content_length_alias_invalid';
        }
        if (array_key_exists('HTTP_CONTENT_ENCODING', $server)
            || array_key_exists('CONTENT_ENCODING', $server)
        ) {
            return 'content_encoding_invalid';
        }
        if (!red_addon_subscription_webhook_content_type_valid(
            $server['CONTENT_TYPE'] ?? null
        )) {
            return 'content_type_invalid';
        }

        $httpTransfer = $server['HTTP_TRANSFER_ENCODING'] ?? null;
        $transfer = $server['TRANSFER_ENCODING'] ?? null;
        if ($httpTransfer !== null
            && $transfer !== null
            && $httpTransfer !== $transfer
        ) {
            return 'transfer_alias_invalid';
        }
        $transferValue = $httpTransfer ?? $transfer;
        $hasLength = array_key_exists('CONTENT_LENGTH', $server);
        if ($hasLength) {
            $length = $server['CONTENT_LENGTH'] ?? null;
            if (!is_string($length)
                || preg_match('/\A[1-9][0-9]{0,5}\z/D', $length) !== 1
                || (int) $length > 262144
            ) {
                return 'content_length_invalid';
            }
            if ($transferValue !== null) {
                return 'transfer_conflict';
            }
        } elseif ($transferValue !== null
            && (!is_string($transferValue)
                || strtolower(trim($transferValue)) !== 'chunked')
        ) {
            return 'transfer_invalid';
        }

        $signature = $server['HTTP_STRIPE_SIGNATURE'] ?? null;
        if (!is_string($signature)
            || strlen($signature) < 8
            || strlen($signature) > 4096
            || trim($signature) !== $signature
            || preg_match('/[^\x21-\x7E]/', $signature) === 1
        ) {
            return 'signature_header_invalid';
        }
        return '';
    }
}

if (!function_exists('red_addon_subscription_webhook_capture_result')) {
    function red_addon_subscription_webhook_capture_result(
        $reason = 'transport_unavailable'
    ) {
        return [
            'valid' => false,
            'request' => null,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_subscription_webhook_preflight')) {
    function red_addon_subscription_webhook_preflight($server)
    {
        $reason = red_addon_subscription_webhook_preflight_reason($server);
        if ($reason !== '') {
            if (PHP_SAPI !== 'cli') {
                error_log(
                    'RED-CMS subscription webhook transport refused: '
                    . $reason
                );
            }
            return null;
        }
        return [
            'bodyBytes' => array_key_exists('CONTENT_LENGTH', $server)
                ? (int) $server['CONTENT_LENGTH'] : 0,
            'signatureHeader' => $server['HTTP_STRIPE_SIGNATURE'],
        ];
    }
}

if (!function_exists('red_addon_subscription_webhook_capture')) {
    function red_addon_subscription_webhook_capture(
        $server,
        $rawBody,
        $receivedAt
    ) {
        $preflight = red_addon_subscription_webhook_preflight($server);
        $bodyBytes = is_string($rawBody) ? strlen($rawBody) : 0;
        if (!is_array($preflight)
            || !is_string($rawBody)
            || $bodyBytes < 2
            || $bodyBytes > 262144
            || ($preflight['bodyBytes'] !== 0
                && $bodyBytes !== $preflight['bodyBytes'])
            || preg_match('//u', $rawBody) !== 1
            || !is_int($receivedAt)
            || $receivedAt < 1
            || $receivedAt > 4102444800
        ) {
            return red_addon_subscription_webhook_capture_result(
                'transport_invalid'
            );
        }
        try {
            $request = new RED_Addon_Subscription_Webhook_Ingress_Request(
                $receivedAt,
                $bodyBytes,
                hash('sha256', $rawBody),
                $rawBody,
                $preflight['signatureHeader']
            );
        } catch (Throwable $throwable) {
            return red_addon_subscription_webhook_capture_result(
                'transport_invalid'
            );
        }
        return [
            'valid' => true,
            'request' => $request,
            'reason' => 'captured',
        ];
    }
}

if (!function_exists('red_addon_subscription_webhook_capture_current')) {
    function red_addon_subscription_webhook_capture_current()
    {
        $preflight = red_addon_subscription_webhook_preflight($_SERVER);
        if (!is_array($preflight)) {
            return red_addon_subscription_webhook_capture_result();
        }
        $rawBody = file_get_contents(
            'php://input',
            false,
            null,
            0,
            262145
        );
        if (!is_string($rawBody)) {
            return red_addon_subscription_webhook_capture_result();
        }
        return red_addon_subscription_webhook_capture(
            $_SERVER,
            $rawBody,
            time()
        );
    }
}

if (!function_exists('red_addon_subscription_webhook_response')) {
    function red_addon_subscription_webhook_response(
        $status,
        $payload,
        $reason
    ) {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_int($status)
            || !in_array($status, [200, 400, 404, 405, 500, 503], true)
            || !is_array($payload)
            || !is_string($body)
            || strlen($body) > 512
        ) {
            $status = 503;
            $reason = 'endpoint_unavailable';
            $body = '{"ok":false,"error":"temporarily_unavailable"}';
        }
        $headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($body),
        ];
        if ($status === 405) {
            $headers['Allow'] = 'POST';
        }
        return [
            'claimed' => true,
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_subscription_webhook_endpoint_result')) {
    function red_addon_subscription_webhook_endpoint_result()
    {
        return [
            'claimed' => false,
            'status' => 0,
            'headers' => [],
            'body' => '',
            'reason' => 'not_claimed',
        ];
    }
}

if (!function_exists('red_addon_subscription_webhook_dispatch')) {
    function red_addon_subscription_webhook_dispatch(
        $method,
        $requestTarget,
        $capture,
        $enabled,
        $runner
    ) {
        if (!red_addon_subscription_webhook_candidate($requestTarget)
            || $enabled !== true
        ) {
            return red_addon_subscription_webhook_endpoint_result();
        }
        if ($method !== 'POST') {
            return red_addon_subscription_webhook_response(
                405,
                ['ok' => false, 'error' => 'method_not_allowed'],
                'method_invalid'
            );
        }
        $request = is_array($capture)
            ? ($capture['request'] ?? null) : null;
        if (($capture['valid'] ?? false) !== true
            || !$request instanceof
                RED_Addon_Subscription_Webhook_Ingress_Request
            || !is_callable($runner)
        ) {
            return red_addon_subscription_webhook_response(
                400,
                ['ok' => false, 'error' => 'invalid_request'],
                'transport_invalid'
            );
        }
        $rawBody = null;
        $signatureHeader = null;
        try {
            if (!$request->material($rawBody, $signatureHeader)
                || !is_string($rawBody)
                || !is_string($signatureHeader)
            ) {
                throw new RuntimeException(
                    'Subscription webhook material is unavailable.'
                );
            }
            $invocation = $runner([
                'rawBody' => $rawBody,
                'signatureHeader' => $signatureHeader,
                'receivedAt' => $request->receivedAt(),
            ]);
        } catch (Throwable $throwable) {
            $invocation = null;
        } finally {
            $rawBody = null;
            $signatureHeader = null;
        }
        if (!is_array($invocation)
            || ($invocation['invoked'] ?? false) !== true
            || !is_int($invocation['statusCode'] ?? null)
        ) {
            return red_addon_subscription_webhook_response(
                500,
                ['ok' => false, 'error' => 'temporarily_unavailable'],
                'runner_unavailable'
            );
        }
        if (($invocation['success'] ?? false) === true
            && $invocation['statusCode'] === 200
        ) {
            return red_addon_subscription_webhook_response(
                200,
                ['ok' => true],
                'acknowledged'
            );
        }
        if ($invocation['statusCode'] === 400) {
            return red_addon_subscription_webhook_response(
                400,
                ['ok' => false, 'error' => 'invalid_signature'],
                'signature_refused'
            );
        }
        return red_addon_subscription_webhook_response(
            500,
            ['ok' => false, 'error' => 'temporarily_unavailable'],
            'delivery_retry_required'
        );
    }
}

if (!function_exists('red_addon_subscription_webhook_response_valid')) {
    function red_addon_subscription_webhook_response_valid($result)
    {
        if (!is_array($result)
            || array_keys($result) !== [
                'claimed', 'status', 'headers', 'body', 'reason',
            ]
            || ($result['claimed'] ?? null) !== true
            || !in_array(
                $result['status'] ?? 0,
                [200,400,404,405,500,503],
                true
            )
            || !is_string($result['body'] ?? null)
            || strlen($result['body']) > 512
        ) {
            return false;
        }
        $headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($result['body']),
        ];
        if ($result['status'] === 405) {
            $headers['Allow'] = 'POST';
        }
        return $result['headers'] === $headers
            && json_decode($result['body'], true) !== null;
    }
}

if (!function_exists('red_addon_subscription_webhook_emit')) {
    function red_addon_subscription_webhook_emit($result)
    {
        if (!red_addon_subscription_webhook_response_valid($result)
            || headers_sent()
        ) {
            throw new RuntimeException(
                'Subscription webhook response is unavailable.'
            );
        }
        header_remove();
        http_response_code($result['status']);
        foreach ($result['headers'] as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $result['body'];
    }
}

?>
