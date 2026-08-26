<?php
/**
 * Dedicated internal boundary for bounded raw webhook requests.
 *
 * This helper is not linked to the front controller. It preserves raw bytes
 * without widening the normal 16 KB service/adapter payload contract.
 */

require_once __DIR__ . '/addon_adapter_helpers.php';

if (!class_exists('RED_Addon_Webhook_Request', false)) {
    final class RED_Addon_Webhook_Request
    {
        public function __construct(
            private string $route,
            private string $rawBody,
            private string $signatureHeader,
            private int $receivedAt,
            private RED_Addon_Runtime_Secret_Access $secretAccess
        ) {
            if (!red_addon_valid_capability($route)
                || strlen($rawBody) < 2
                || strlen($rawBody) > 262144
                || preg_match('//u', $rawBody) !== 1
                || strlen($signatureHeader) < 1
                || strlen($signatureHeader) > 4096
                || preg_match('/[^\x21-\x7E]/', $signatureHeader) === 1
                || $receivedAt < 1
                || $receivedAt > 4102444800
            ) {
                throw new InvalidArgumentException(
                    'Add-on webhook request is invalid.'
                );
            }
        }

        public function route(): string
        {
            return $this->route;
        }

        public function rawBody(): string
        {
            return $this->rawBody;
        }

        public function signatureHeader(): string
        {
            return $this->signatureHeader;
        }

        public function receivedAt(): int
        {
            return $this->receivedAt;
        }

        public function secret(
            string $settingKey,
            &$resolvedValue = null
        ): array {
            return $this->secretAccess->resolve(
                $settingKey,
                $resolvedValue
            );
        }
    }
}

if (!class_exists('RED_Addon_Webhook_Result', false)) {
    final class RED_Addon_Webhook_Result
    {
        private function __construct(
            private bool $success,
            private int $statusCode,
            private array $data,
            private string $error
        ) {}

        public static function accepted(array $data): self
        {
            $normalized = red_addon_service_payload($data);
            if (!is_array($normalized)) {
                throw new InvalidArgumentException(
                    'Add-on webhook result data is invalid.'
                );
            }
            return new self(true, 200, $normalized, '');
        }

        public static function refused(
            string $error,
            int $statusCode = 400
        ): self {
            if (!in_array($statusCode, [400, 404, 409, 422, 500], true)
                || preg_match(
                    '/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D',
                    $error
                ) !== 1
                || strlen($error) > 80
            ) {
                throw new InvalidArgumentException(
                    'Add-on webhook refusal is invalid.'
                );
            }
            return new self(false, $statusCode, [], $error);
        }

        public function successState(): bool
        {
            return $this->success;
        }

        public function statusCode(): int
        {
            return $this->statusCode;
        }

        public function data(): array
        {
            return $this->data;
        }

        public function error(): string
        {
            return $this->error;
        }
    }
}

if (!function_exists('red_addon_webhook_data_safe')) {
    function red_addon_webhook_data_safe(
        $value,
        $rawBody,
        $signatureHeader,
        $secretAccess,
        $depth = 0,
        &$nodes = 0
    ) {
        if ($depth > 4 || ++$nodes > 128) {
            return false;
        }
        if ($value === null || is_bool($value) || is_int($value)) {
            return true;
        }
        if (is_string($value)) {
            return !$secretAccess->containsValue($value)
                && ($rawBody === '' || !str_contains($value, $rawBody))
                && ($signatureHeader === ''
                    || !str_contains($value, $signatureHeader));
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!red_addon_webhook_data_safe(
                $item,
                $rawBody,
                $signatureHeader,
                $secretAccess,
                $depth + 1,
                $nodes
            )) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('red_addon_webhook_route_manifest')) {
    function red_addon_webhook_route_manifest($manifest, $route)
    {
        if (!is_array($manifest)) {
            return null;
        }
        foreach ($manifest['routes'] ?? [] as $candidate) {
            if (is_array($candidate)
                && ($candidate['id'] ?? null) === $route
            ) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('red_addon_webhook_invoke_registered')) {
    function red_addon_webhook_invoke_registered(
        $route,
        $rawBody,
        $signatureHeader,
        $receivedAt,
        $owner,
        $handler,
        $manifest,
        $secretAccess
    ) {
        $result = [
            'invoked' => false,
            'success' => false,
            'route' => '',
            'package' => '',
            'statusCode' => 0,
            'data' => [],
            'error' => '',
            'reason' => 'invalid_request',
        ];
        $declaration = red_addon_webhook_route_manifest(
            $manifest,
            $route
        );
        if (!is_string($route)
            || !red_addon_valid_capability($route)
            || !is_string($owner)
            || !red_addon_valid_package_id($owner)
            || !is_callable($handler)
            || !is_array($manifest)
            || ($manifest['id'] ?? null) !== $owner
            || !is_array($declaration)
            || ($declaration['scope'] ?? null) !== 'public'
            || ($declaration['methods'] ?? null) !== ['POST']
            || ($declaration['authentication'] ?? null)
                !== 'server-signature'
            || ($declaration['csrf'] ?? null) !== 'not-applicable'
            || !$secretAccess instanceof RED_Addon_Runtime_Secret_Access
            || $secretAccess->packageId() !== $owner
            || $secretAccess->settingCount() !== 1
        ) {
            return $result;
        }
        $result['route'] = $route;
        $result['package'] = $owner;
        try {
            $request = new RED_Addon_Webhook_Request(
                $route,
                $rawBody,
                $signatureHeader,
                $receivedAt,
                $secretAccess
            );
        } catch (Throwable $throwable) {
            return $result;
        }

        $bufferLevel = ob_get_level();
        try {
            ob_start();
            $result['invoked'] = true;
            $webhookResult = $handler($request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on webhook altered output buffering.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '') {
                $result['reason'] = 'handler_output';
                return $result;
            }
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $result['reason'] = 'handler_failed';
            return $result;
        }
        if (!$webhookResult instanceof RED_Addon_Webhook_Result) {
            $result['reason'] = 'invalid_result';
            return $result;
        }
        $nodes = 0;
        if (!red_addon_webhook_data_safe(
            $webhookResult->data(),
            $rawBody,
            $signatureHeader,
            $secretAccess,
            0,
            $nodes
        ) || $secretAccess->containsValue($webhookResult->error())) {
            $result['reason'] = 'sensitive_disclosure';
            return $result;
        }
        $result['success'] = $webhookResult->successState();
        $result['statusCode'] = $webhookResult->statusCode();
        $result['data'] = $webhookResult->data();
        $result['error'] = $webhookResult->error();
        $result['reason'] = $result['success']
            ? 'completed' : 'handler_refused';
        return $result;
    }
}

?>
