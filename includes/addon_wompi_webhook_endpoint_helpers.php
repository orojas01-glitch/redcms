<?php
/**
 * Default-disabled direct-HTTPS ingress for Wompi Web Checkout events.
 *
 * Mirrors the Stripe subscription webhook endpoint: one exact query-free
 * POST target, server-local enable + sandbox gates, transport preflight before
 * any database access or body read, a bounded raw body, and a closed JSON
 * response set. The provider decision itself (signature, confirmation GET,
 * order reconciliation, Store Lite apply) is made by the Wompi package's W3
 * coordinator inside the registered webhook boundary.
 */

require_once __DIR__ . '/runtime_config_helpers.php';
require_once __DIR__ . '/addon_subscription_webhook_endpoint_helpers.php';
require_once __DIR__ . '/addon_webhook_request_helpers.php';

if (!class_exists('RED_Addon_Wompi_Webhook_Ingress_Request', false)) {
    final class RED_Addon_Wompi_Webhook_Ingress_Request
    {
        private static ?WeakMap $material = null;

        public function __construct(
            private int $receivedAt,
            private int $bodyBytes,
            string $rawBody,
            string $checksumHeader
        ) {
            if ($receivedAt < 1
                || $receivedAt > 4102444800
                || $bodyBytes !== strlen($rawBody)
                || $bodyBytes < 2
                || $bodyBytes > red_addon_wompi_webhook_max_body_bytes()
                || preg_match('//u', $rawBody) !== 1
                || !red_addon_wompi_webhook_checksum_header_valid(
                    $checksumHeader
                )
            ) {
                throw new InvalidArgumentException(
                    'Wompi webhook ingress material is invalid.'
                );
            }
            if (!(self::$material instanceof WeakMap)) {
                self::$material = new WeakMap();
            }
            self::$material[$this] = [
                'rawBody' => $rawBody,
                'checksumHeader' => $checksumHeader,
            ];
        }

        public function receivedAt(): int
        {
            return $this->receivedAt;
        }

        public function material(
            &$rawBody = null,
            &$checksumHeader = null
        ): bool {
            $material = self::$material[$this] ?? null;
            if (!is_array($material)) {
                $rawBody = null;
                $checksumHeader = null;
                return false;
            }
            $rawBody = $material['rawBody'];
            $checksumHeader = $material['checksumHeader'];
            return true;
        }

        public function __serialize(): array
        {
            throw new LogicException(
                'Wompi webhook ingress cannot be serialized.'
            );
        }

        public function __clone(): void
        {
            throw new LogicException(
                'Wompi webhook ingress cannot be cloned.'
            );
        }

        public function __debugInfo(): array
        {
            return [
                'receivedAt' => $this->receivedAt,
                'bodyBytes' => $this->bodyBytes,
            ];
        }
    }
}

if (!function_exists('red_addon_wompi_webhook_path')) {
    function red_addon_wompi_webhook_path()
    {
        return '/addons/redcms/store-lite-wompi/provider-events';
    }
}

if (!function_exists('red_addon_wompi_webhook_max_body_bytes')) {
    function red_addon_wompi_webhook_max_body_bytes()
    {
        return 65536;
    }
}

if (!function_exists('red_addon_wompi_webhook_endpoint_enabled')) {
    /**
     * Both server-local values must be explicit. Only `sandbox` exists;
     * production needs a separate, authorized change.
     */
    function red_addon_wompi_webhook_endpoint_enabled()
    {
        $enabled = red_server_config_value(
            'WOMPI_WEBHOOK_ENDPOINT_ENABLED',
            ['RED_WOMPI_WEBHOOK_ENDPOINT_ENABLED'],
            false
        );
        $mode = red_server_config_value(
            'WOMPI_WEBHOOK_MODE',
            ['RED_WOMPI_WEBHOOK_MODE'],
            ''
        );
        return ($enabled === true || $enabled === '1')
            && $mode === 'sandbox';
    }
}

if (!function_exists('red_addon_wompi_webhook_candidate')) {
    function red_addon_wompi_webhook_candidate($requestTarget)
    {
        return is_string($requestTarget)
            && $requestTarget === red_addon_wompi_webhook_path();
    }
}

if (!function_exists('red_addon_wompi_webhook_checksum_header_valid')) {
    /** X-Event-Checksum is a SHA-256 hex digest; Wompi documents it upper-case. */
    function red_addon_wompi_webhook_checksum_header_valid($value)
    {
        return is_string($value)
            && preg_match('/\A[A-Fa-f0-9]{64}\z/D', $value) === 1;
    }
}

if (!function_exists('red_addon_wompi_webhook_preflight_reason')) {
    function red_addon_wompi_webhook_preflight_reason($server)
    {
        if (!is_array($server)) {
            return 'server_invalid';
        }
        if (($server['REQUEST_METHOD'] ?? null) !== 'POST') {
            return 'method_invalid';
        }
        if (!red_addon_wompi_webhook_candidate(
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
        if (array_key_exists('CONTENT_LENGTH', $server)) {
            $length = $server['CONTENT_LENGTH'] ?? null;
            if (!is_string($length)
                || preg_match('/\A[1-9][0-9]{0,4}\z/D', $length) !== 1
                || (int) $length > red_addon_wompi_webhook_max_body_bytes()
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

        if (!red_addon_wompi_webhook_checksum_header_valid(
            $server['HTTP_X_EVENT_CHECKSUM'] ?? null
        )) {
            return 'checksum_header_invalid';
        }
        return '';
    }
}

if (!function_exists('red_addon_wompi_webhook_preflight')) {
    function red_addon_wompi_webhook_preflight($server)
    {
        $reason = red_addon_wompi_webhook_preflight_reason($server);
        if ($reason !== '') {
            if (PHP_SAPI !== 'cli') {
                error_log(
                    'RED-CMS Wompi webhook transport refused: ' . $reason
                );
            }
            return null;
        }
        return [
            'bodyBytes' => array_key_exists('CONTENT_LENGTH', $server)
                ? (int) $server['CONTENT_LENGTH'] : 0,
            'checksumHeader' => $server['HTTP_X_EVENT_CHECKSUM'],
        ];
    }
}

if (!function_exists('red_addon_wompi_webhook_capture_result')) {
    function red_addon_wompi_webhook_capture_result(
        $reason = 'transport_unavailable'
    ) {
        return [
            'valid' => false,
            'request' => null,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_wompi_webhook_capture')) {
    function red_addon_wompi_webhook_capture($server, $rawBody, $receivedAt)
    {
        $preflight = red_addon_wompi_webhook_preflight($server);
        $bodyBytes = is_string($rawBody) ? strlen($rawBody) : 0;
        if (!is_array($preflight)
            || !is_string($rawBody)
            || $bodyBytes < 2
            || $bodyBytes > red_addon_wompi_webhook_max_body_bytes()
            || ($preflight['bodyBytes'] !== 0
                && $bodyBytes !== $preflight['bodyBytes'])
            || !is_int($receivedAt)
        ) {
            return red_addon_wompi_webhook_capture_result(
                'transport_invalid'
            );
        }
        try {
            $request = new RED_Addon_Wompi_Webhook_Ingress_Request(
                $receivedAt,
                $bodyBytes,
                $rawBody,
                $preflight['checksumHeader']
            );
        } catch (Throwable $throwable) {
            return red_addon_wompi_webhook_capture_result(
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

if (!function_exists('red_addon_wompi_webhook_capture_current')) {
    function red_addon_wompi_webhook_capture_current()
    {
        $preflight = red_addon_wompi_webhook_preflight($_SERVER);
        if (!is_array($preflight)) {
            return red_addon_wompi_webhook_capture_result();
        }
        $rawBody = file_get_contents(
            'php://input',
            false,
            null,
            0,
            red_addon_wompi_webhook_max_body_bytes() + 1
        );
        if (!is_string($rawBody)) {
            return red_addon_wompi_webhook_capture_result();
        }
        return red_addon_wompi_webhook_capture($_SERVER, $rawBody, time());
    }
}

if (!function_exists('red_addon_wompi_webhook_dispatch')) {
    /**
     * Maps one runner invocation to the closed public response. Wompi retries
     * every non-200 delivery (about 30 min, 3 h and 24 h later), so only a
     * final decision is acknowledged with 200.
     */
    function red_addon_wompi_webhook_dispatch(
        $method,
        $requestTarget,
        $capture,
        $enabled,
        $runner
    ) {
        if (!red_addon_wompi_webhook_candidate($requestTarget)
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
            || !$request instanceof RED_Addon_Wompi_Webhook_Ingress_Request
            || !is_callable($runner)
        ) {
            return red_addon_subscription_webhook_response(
                400,
                ['ok' => false, 'error' => 'invalid_request'],
                'transport_invalid'
            );
        }
        $rawBody = null;
        $checksumHeader = null;
        try {
            if (!$request->material($rawBody, $checksumHeader)
                || !is_string($rawBody)
                || !is_string($checksumHeader)
            ) {
                throw new RuntimeException(
                    'Wompi webhook material is unavailable.'
                );
            }
            $invocation = $runner([
                'rawBody' => $rawBody,
                'signatureHeader' => $checksumHeader,
                'receivedAt' => $request->receivedAt(),
            ]);
        } catch (Throwable $throwable) {
            $invocation = null;
        } finally {
            $rawBody = null;
            $checksumHeader = null;
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

if (!function_exists('red_addon_wompi_webhook_handle')) {
    /**
     * Webhook-boundary handler. Resolves only `wompi.event-secret`, runs the
     * package's W3 coordinator with core-owned callables, clears the secret,
     * and converts the coordinator's HTTP decision to a typed result:
     * 200 → accepted, 401 → refused 400, anything else → refused 500 (retry).
     *
     * $coordinator mirrors
     *   RED_CMS_Store_Lite_Wompi_Event_Ingress_Coordinator::handle
     * $lookup(string $url): ?string is the bounded confirmation GET.
     * $serviceInvoker(string $service, string $operation, array $input): array
     *   mirrors red_addon_service_invoke().
     */
    function red_addon_wompi_webhook_handle(
        $request,
        $coordinator,
        $lookup,
        $serviceInvoker,
        $now
    ) {
        if (!$request instanceof RED_Addon_Webhook_Request
            || $request->route() !== 'redcms.store-lite-wompi/provider-events'
            || !is_callable($coordinator)
            || !is_callable($lookup)
            || !is_callable($serviceInvoker)
            || !is_int($now)
            || $now < $request->receivedAt()
            || $now > 4102444800
        ) {
            return RED_Addon_Webhook_Result::refused(
                'wompi_webhook_handler_refused',
                500
            );
        }
        $eventsSecret = null;
        $resolution = $request->secret('wompi.event-secret', $eventsSecret);
        if (($resolution['resolved'] ?? false) !== true
            || ($resolution['valid'] ?? false) !== true
            || !is_string($eventsSecret)
            || preg_match(
                '/\Atest_events_[A-Za-z0-9]{8,128}\z/D',
                $eventsSecret
            ) !== 1
        ) {
            $eventsSecret = null;
            return RED_Addon_Webhook_Result::refused(
                'wompi_webhook_secret_unavailable',
                500
            );
        }

        $loadOrder = static function (string $orderId) use (
            $serviceInvoker
        ): ?array {
            if (preg_match('/\Aord_[a-f0-9]{32}\z/D', $orderId) !== 1) {
                return null;
            }
            $read = $serviceInvoker(
                'commerce.orders',
                'payment.order.read',
                ['orderId' => $orderId]
            );
            $data = is_array($read) ? ($read['data'] ?? null) : null;
            if (!is_array($read)
                || ($read['success'] ?? false) !== true
                || !is_array($data)
                || !is_bool($data['found'] ?? null)
            ) {
                throw new RuntimeException('Store Lite order read failed.');
            }
            if ($data['found'] === false) {
                return null;
            }
            return is_array($data['order'] ?? null) ? $data['order'] : [];
        };
        $apply = static function (array $order, array $event) use (
            $serviceInvoker
        ): array {
            $applied = $serviceInvoker(
                'commerce.orders',
                'payment.event.apply',
                ['order' => $order, 'event' => $event]
            );
            if (is_array($applied)
                && ($applied['success'] ?? false) === true
            ) {
                return [
                    'ok' => true,
                    'status' => (string) ($applied['data']['status'] ?? ''),
                ];
            }
            $error = is_array($applied) ? ($applied['error'] ?? '') : '';
            return [
                'ok' => false,
                'error' => is_string($error) && $error !== ''
                    ? $error
                    : 'payment_event_storage_unavailable',
            ];
        };

        try {
            $decision = $coordinator(
                $request->rawBody(),
                $request->signatureHeader(),
                'sandbox',
                $eventsSecret,
                $now,
                [],
                $lookup,
                $loadOrder,
                $apply
            );
        } catch (Throwable $throwable) {
            $decision = null;
        } finally {
            $eventsSecret = null;
        }

        $status = is_array($decision) ? ($decision['httpStatus'] ?? 0) : 0;
        $disposition = is_array($decision)
            ? ($decision['disposition'] ?? '') : '';
        if (!is_string($disposition)
            || preg_match('/\A[a-z][a-z_]{0,39}\z/D', $disposition) !== 1
        ) {
            $status = 0;
        }
        if ($status === 200) {
            $outcome = (string) ($decision['outcome'] ?? '');
            return RED_Addon_Webhook_Result::accepted([
                'disposition' => $disposition,
                'outcome' => in_array(
                    $outcome,
                    ['', 'paid', 'failed', 'cancelled'],
                    true
                ) ? $outcome : '',
            ]);
        }
        if ($status === 401) {
            return RED_Addon_Webhook_Result::refused(
                'wompi_webhook_event_refused',
                400
            );
        }
        return RED_Addon_Webhook_Result::refused(
            'wompi_webhook_retry_required',
            500
        );
    }
}

?>
