<?php
/**
 * Bounded internal invocation for enabled, registrar-owned add-on services.
 *
 * This is not an HTTP, administrator, session, or database API. Core supplies
 * one immutable typed request and accepts only one typed result. Package code
 * remains operator-reviewed trusted PHP, but output, exceptions, malformed
 * values, and output-buffer changes are contained at this boundary.
 */

require_once __DIR__ . '/addon_runtime_helpers.php';
require_once __DIR__ . '/addon_runtime_secret_helpers.php';

if (!function_exists('red_addon_service_value')) {
    function red_addon_service_value($value, $depth, &$nodes)
    {
        if ($depth > 4 || ++$nodes > 128) {
            return null;
        }
        if ($value === null || is_bool($value) || is_int($value)) {
            return ['valid' => true, 'value' => $value];
        }
        if (is_string($value)) {
            if (strlen($value) > 4096
                || preg_match('//u', $value) !== 1
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
                    === 1
            ) {
                return null;
            }
            return ['valid' => true, 'value' => $value];
        }
        if (!is_array($value)) {
            return null;
        }
        $normalized = [];
        $isList = array_is_list($value);
        foreach ($value as $key => $item) {
            if (!$isList
                && (!is_string($key)
                    || preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D', $key)
                        !== 1)
            ) {
                return null;
            }
            $child = red_addon_service_value($item, $depth + 1, $nodes);
            if (!is_array($child) || empty($child['valid'])) {
                return null;
            }
            $normalized[$key] = $child['value'];
        }
        return ['valid' => true, 'value' => $normalized];
    }
}

if (!function_exists('red_addon_service_payload')) {
    function red_addon_service_payload($payload)
    {
        if (!is_array($payload)) {
            return null;
        }
        $nodes = 0;
        $result = red_addon_service_value($payload, 0, $nodes);
        if (!is_array($result) || empty($result['valid'])) {
            return null;
        }
        $json = json_encode(
            $result['value'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($json) && strlen($json) <= 16384
            ? $result['value']
            : null;
    }
}

if (!class_exists('RED_Addon_Service_Request', false)) {
    final class RED_Addon_Service_Request
    {
        private string $service;
        private string $operation;
        private array $input;
        private ?RED_Addon_Runtime_Secret_Access $secretAccess;

        public function __construct(
            string $service,
            string $operation,
            array $input,
            ?RED_Addon_Runtime_Secret_Access $secretAccess = null
        ) {
            if (!red_addon_valid_capability($service)
                || preg_match(
                    '/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D',
                    $operation
                ) !== 1
                || strlen($operation) > 80
            ) {
                throw new InvalidArgumentException(
                    'Add-on service request identity is invalid.'
                );
            }
            $normalized = red_addon_service_payload($input);
            if (!is_array($normalized)) {
                throw new InvalidArgumentException(
                    'Add-on service request input is invalid.'
                );
            }
            $this->service = $service;
            $this->operation = $operation;
            $this->input = $normalized;
            $this->secretAccess = $secretAccess;
        }

        public function service(): string
        {
            return $this->service;
        }

        public function operation(): string
        {
            return $this->operation;
        }

        public function input(): array
        {
            return $this->input;
        }

        /**
         * Resolve only this package's declared setting reference. The value
         * is returned solely through the internal by-reference argument.
         */
        public function secret(string $settingKey, &$resolvedValue = null): array
        {
            if ($this->secretAccess === null) {
                $resolvedValue = null;
                return [
                    'valid' => false,
                    'resolved' => false,
                    'reason' => 'secret_unavailable',
                ];
            }
            return $this->secretAccess->resolve(
                $settingKey,
                $resolvedValue
            );
        }
    }
}

if (!class_exists('RED_Addon_Service_Result', false)) {
    final class RED_Addon_Service_Result
    {
        private bool $success;
        private array $data;
        private string $error;

        private function __construct(bool $success, array $data, string $error)
        {
            $this->success = $success;
            $this->data = $data;
            $this->error = $error;
        }

        public static function success(array $data = []): self
        {
            $normalized = red_addon_service_payload($data);
            if (!is_array($normalized)) {
                throw new InvalidArgumentException(
                    'Add-on service result data is invalid.'
                );
            }
            return new self(true, $normalized, '');
        }

        public static function failure(string $error): self
        {
            if (preg_match(
                '/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D',
                $error
            ) !== 1 || strlen($error) > 80) {
                throw new InvalidArgumentException(
                    'Add-on service error code is invalid.'
                );
            }
            return new self(false, [], $error);
        }

        public function successState(): bool
        {
            return $this->success;
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

if (!function_exists('red_addon_service_invocation_result')) {
    function red_addon_service_invocation_result(
        $service,
        $operation,
        $reason
    ) {
        return [
            'invoked' => false,
            'success' => false,
            'service' => is_string($service)
                && red_addon_valid_capability($service)
                    ? $service
                    : '',
            'package' => '',
            'operation' => is_string($operation)
                && strlen($operation) <= 80
                && preg_match(
                    '/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D',
                    $operation
                ) === 1
                    ? $operation
                    : '',
            'data' => [],
            'error' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_service_invoke')) {
    function red_addon_service_invoke($service, $operation, $input)
    {
        $result = red_addon_service_invocation_result(
            $service,
            $operation,
            'invalid_request'
        );
        if (!is_string($service)
            || !red_addon_valid_capability($service)
            || !is_string($operation)
            || !is_array($input)
        ) {
            return $result;
        }
        try {
            $request = new RED_Addon_Service_Request(
                $service,
                $operation,
                $input
            );
        } catch (Throwable $throwable) {
            return $result;
        }

        $owner = red_addon_runtime_owner('services', $service);
        $handler = red_addon_runtime_handler('services', $service);
        $manifest = is_string($owner)
            ? red_addon_runtime_manifest($owner)
            : null;
        if (!is_string($owner)
            || !red_addon_valid_package_id($owner)
            || !is_callable($handler)
            || !is_array($manifest)
            || !in_array(
                $service,
                $manifest['provides']['services'] ?? [],
                true
            )
        ) {
            $result['reason'] = 'service_unavailable';
            return $result;
        }
        $result['package'] = $owner;
        $secretAccess = red_addon_runtime_secret_access($owner);
        try {
            $request = new RED_Addon_Service_Request(
                $service,
                $operation,
                $input,
                $secretAccess
            );
        } catch (Throwable $throwable) {
            return $result;
        }

        $bufferLevel = ob_get_level();
        try {
            ob_start();
            $result['invoked'] = true;
            $serviceResult = $handler($request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on service altered the output buffer stack.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '') {
                $result['reason'] = 'service_output';
                return $result;
            }
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $result['reason'] = 'service_failed';
            return $result;
        }
        if (!$serviceResult instanceof RED_Addon_Service_Result) {
            $result['reason'] = 'invalid_result';
            return $result;
        }
        $nodes = 0;
        if (!red_addon_runtime_secret_data_is_safe(
            $serviceResult->data(),
            $secretAccess,
            0,
            $nodes
        ) || ($secretAccess !== null
            && $secretAccess->containsValue($serviceResult->error())
        )) {
            $result['reason'] = 'secret_disclosure';
            return $result;
        }
        $result['success'] = $serviceResult->successState();
        $result['data'] = $serviceResult->data();
        $result['error'] = $serviceResult->error();
        $result['reason'] = $result['success']
            ? 'completed'
            : 'service_error';
        return $result;
    }
}

?>
