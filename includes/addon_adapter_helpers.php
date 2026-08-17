<?php
/**
 * Bounded internal invocation for enabled, registrar-owned add-on adapters.
 *
 * This is not an HTTP, route, browser, database, or provider transport API.
 * Core supplies one immutable typed request and accepts only one typed result.
 * Package code remains operator-reviewed trusted PHP, but output, exceptions,
 * malformed values, output-buffer changes, and secret disclosure are
 * contained at this boundary.
 */

require_once __DIR__ . '/addon_service_helpers.php';

if (!class_exists('RED_Addon_Adapter_Request', false)) {
    final class RED_Addon_Adapter_Request
    {
        private string $adapter;
        private string $operation;
        private array $input;
        private ?RED_Addon_Runtime_Secret_Access $secretAccess;

        public function __construct(
            string $adapter,
            string $operation,
            array $input,
            ?RED_Addon_Runtime_Secret_Access $secretAccess = null
        ) {
            if (!red_addon_valid_capability($adapter)
                || preg_match(
                    '/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D',
                    $operation
                ) !== 1
                || strlen($operation) > 80
            ) {
                throw new InvalidArgumentException(
                    'Add-on adapter request identity is invalid.'
                );
            }
            $normalized = red_addon_service_payload($input);
            if (!is_array($normalized)) {
                throw new InvalidArgumentException(
                    'Add-on adapter request input is invalid.'
                );
            }
            $this->adapter = $adapter;
            $this->operation = $operation;
            $this->input = $normalized;
            $this->secretAccess = $secretAccess;
        }

        public function adapter(): string
        {
            return $this->adapter;
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

if (!class_exists('RED_Addon_Adapter_Result', false)) {
    final class RED_Addon_Adapter_Result
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
                    'Add-on adapter result data is invalid.'
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
                    'Add-on adapter error code is invalid.'
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

if (!function_exists('red_addon_adapter_invocation_result')) {
    function red_addon_adapter_invocation_result(
        $adapter,
        $operation,
        $reason
    ) {
        return [
            'invoked' => false,
            'success' => false,
            'adapter' => is_string($adapter)
                && red_addon_valid_capability($adapter)
                    ? $adapter
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

if (!function_exists('red_addon_adapter_invoke_registered')) {
    function red_addon_adapter_invoke_registered(
        $adapter,
        $operation,
        $input,
        $owner,
        $handler,
        $manifest,
        $secretAccess = null
    )
    {
        $result = red_addon_adapter_invocation_result(
            $adapter,
            $operation,
            'invalid_request'
        );
        if (!is_string($adapter)
            || !red_addon_valid_capability($adapter)
            || !is_string($operation)
            || !is_array($input)
            || !is_string($owner)
            || !red_addon_valid_package_id($owner)
            || !is_callable($handler)
            || !is_array($manifest)
            || ($manifest['id'] ?? null) !== $owner
            || !in_array(
                $adapter,
                $manifest['provides']['adapters'] ?? [],
                true
            )
            || ($secretAccess !== null
                && (!$secretAccess instanceof RED_Addon_Runtime_Secret_Access
                    || $secretAccess->packageId() !== $owner))
        ) {
            return $result;
        }
        $result['package'] = $owner;
        try {
            $request = new RED_Addon_Adapter_Request(
                $adapter,
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
            $adapterResult = $handler($request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on adapter altered the output buffer stack.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '') {
                $result['reason'] = 'adapter_output';
                return $result;
            }
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $result['reason'] = 'adapter_failed';
            return $result;
        }
        if (!$adapterResult instanceof RED_Addon_Adapter_Result) {
            $result['reason'] = 'invalid_result';
            return $result;
        }
        $nodes = 0;
        if (!red_addon_runtime_secret_data_is_safe(
            $adapterResult->data(),
            $secretAccess,
            0,
            $nodes
        ) || ($secretAccess !== null
            && $secretAccess->containsValue($adapterResult->error())
        )) {
            $result['reason'] = 'secret_disclosure';
            return $result;
        }
        $result['success'] = $adapterResult->successState();
        $result['data'] = $adapterResult->data();
        $result['error'] = $adapterResult->error();
        $result['reason'] = $result['success']
            ? 'completed'
            : 'adapter_error';
        return $result;
    }
}

if (!function_exists('red_addon_adapter_invoke')) {
    function red_addon_adapter_invoke($adapter, $operation, $input)
    {
        $result = red_addon_adapter_invocation_result(
            $adapter,
            $operation,
            'invalid_request'
        );
        if (!is_string($adapter)
            || !red_addon_valid_capability($adapter)
            || !is_string($operation)
            || !is_array($input)
        ) {
            return $result;
        }
        try {
            new RED_Addon_Adapter_Request($adapter, $operation, $input);
        } catch (Throwable $throwable) {
            return $result;
        }

        $owner = red_addon_runtime_owner('adapters', $adapter);
        $handler = red_addon_runtime_handler('adapters', $adapter);
        $manifest = is_string($owner)
            ? red_addon_runtime_manifest($owner)
            : null;
        if (!is_string($owner)
            || !red_addon_valid_package_id($owner)
            || !is_callable($handler)
            || !is_array($manifest)
            || !in_array(
                $adapter,
                $manifest['provides']['adapters'] ?? [],
                true
            )
        ) {
            $result['reason'] = 'adapter_unavailable';
            return $result;
        }
        return red_addon_adapter_invoke_registered(
            $adapter,
            $operation,
            $input,
            $owner,
            $handler,
            $manifest,
            red_addon_runtime_secret_access($owner)
        );
    }
}

?>
