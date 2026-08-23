<?php
/**
 * Core-owned public GET dispatch for enabled, registrar-owned add-on routes.
 *
 * This first route slice accepts exact static manifest paths only. It supplies
 * one immutable route request containing bounded query data and accepts only a
 * typed JSON result. It does not expose PHP request globals, sessions, database
 * connections, HTML responses, member authentication, unsafe methods,
 * placeholder routes, or administrator routes.
 */

require_once __DIR__ . '/addon_service_helpers.php';

if (!class_exists('RED_Addon_Public_Route_Request', false)) {
    final class RED_Addon_Public_Route_Request
    {
        private string $route;
        private string $method;
        private string $path;
        private array $query;

        public function __construct(
            string $route,
            string $method,
            string $path,
            array $query
        ) {
            $normalized = red_addon_service_payload($query);
            if (!red_addon_valid_capability($route)
                || $method !== 'GET'
                || !red_addon_valid_route_path($path)
                || str_contains($path, '{')
                || str_contains($path, '}')
                || !is_array($normalized)
            ) {
                throw new InvalidArgumentException(
                    'Add-on public route request is invalid.'
                );
            }
            $this->route = $route;
            $this->method = $method;
            $this->path = $path;
            $this->query = $normalized;
        }

        public function route(): string
        {
            return $this->route;
        }

        public function method(): string
        {
            return $this->method;
        }

        public function path(): string
        {
            return $this->path;
        }

        public function query(): array
        {
            return $this->query;
        }
    }
}

if (!class_exists('RED_Addon_Public_Route_Result', false)) {
    final class RED_Addon_Public_Route_Result
    {
        private bool $success;
        private int $status;
        private array $data;
        private string $error;

        private function __construct(
            bool $success,
            int $status,
            array $data,
            string $error
        ) {
            $this->success = $success;
            $this->status = $status;
            $this->data = $data;
            $this->error = $error;
        }

        public static function success(array $data = []): self
        {
            $normalized = red_addon_service_payload($data);
            if (!is_array($normalized)) {
                throw new InvalidArgumentException(
                    'Add-on public route result data is invalid.'
                );
            }
            return new self(true, 200, $normalized, '');
        }

        public static function failure(string $error, int $status = 400): self
        {
            if (!in_array($status, [400, 404, 409, 422, 429], true)
                || preg_match(
                    '/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D',
                    $error
                ) !== 1
                || strlen($error) > 80
            ) {
                throw new InvalidArgumentException(
                    'Add-on public route failure is invalid.'
                );
            }
            return new self(false, $status, [], $error);
        }

        public function successState(): bool
        {
            return $this->success;
        }

        public function status(): int
        {
            return $this->status;
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

if (!function_exists('red_addon_public_route_result')) {
    function red_addon_public_route_result($reason = 'not_matched')
    {
        return [
            'claimed' => false,
            'invoked' => false,
            'success' => false,
            'route' => '',
            'package' => '',
            'status' => 0,
            'headers' => [],
            'body' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_route_response')) {
    function red_addon_public_route_response(
        array $result,
        $status,
        array $payload,
        $reason
    ) {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($body) || strlen($body) > 32768) {
            $status = 503;
            $reason = 'response_unavailable';
            $body = '{"ok":false,"error":"temporarily_unavailable"}';
        }
        $result['status'] = (int) $status;
        $result['headers'] = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $result['body'] = $body;
        $result['reason'] = (string) $reason;
        return $result;
    }
}

if (!function_exists('red_addon_public_route_path')) {
    function red_addon_public_route_path($requestUri)
    {
        if (!is_string($requestUri)
            || $requestUri === ''
            || strlen($requestUri) > 2048
            || preg_match('/[\x00-\x20\x7F]/', $requestUri) === 1
        ) {
            return null;
        }
        $path = parse_url($requestUri, PHP_URL_PATH);
        return is_string($path)
            && red_addon_valid_route_path($path)
            && !str_contains($path, '%')
                ? $path
                : null;
    }
}

if (!function_exists('red_addon_public_route_query')) {
    /**
     * Reads only the caller-supplied query string from the request target.
     *
     * Apache front-controller rewrites may add an internal key to $_GET, so
     * public add-on routes must not receive the rewritten PHP query globals.
     */
    function red_addon_public_route_query($requestUri)
    {
        if (red_addon_public_route_path($requestUri) === null) {
            return null;
        }
        $rawQuery = parse_url($requestUri, PHP_URL_QUERY);
        if ($rawQuery === null) {
            return [];
        }
        if (!is_string($rawQuery)) {
            return null;
        }
        $query = [];
        parse_str($rawQuery, $query);
        return is_array($query) ? $query : null;
    }
}

if (!function_exists('red_addon_public_route_declaration')) {
    function red_addon_public_route_declaration($path)
    {
        $context = red_addon_runtime_current_context();
        if (!$context instanceof RED_Addon_Runtime_Context) {
            return null;
        }
        $snapshot = $context->snapshot();
        $owners = $snapshot['registrations']['routes'] ?? [];
        if (!is_array($owners)) {
            return null;
        }
        foreach ($owners as $routeId => $owner) {
            if (!is_string($routeId)
                || !is_string($owner)
                || !red_addon_valid_package_id($owner)
            ) {
                continue;
            }
            $manifest = red_addon_runtime_manifest($owner);
            if (!is_array($manifest)) {
                continue;
            }
            foreach ($manifest['routes'] ?? [] as $route) {
                if (is_array($route)
                    && ($route['id'] ?? null) === $routeId
                    && ($route['path'] ?? null) === $path
                ) {
                    return [
                        'id' => $routeId,
                        'owner' => $owner,
                        'route' => $route,
                    ];
                }
            }
        }
        return null;
    }
}

if (!function_exists('red_addon_public_route_restore_http_state')) {
    function red_addon_public_route_restore_http_state(
        array $headers,
        $status
    ) {
        if (headers_sent()) {
            return;
        }
        header_remove();
        foreach ($headers as $header) {
            if (is_string($header) && $header !== '') {
                header($header, false);
            }
        }
        http_response_code(is_int($status) && $status > 0 ? $status : 200);
    }
}

if (!function_exists('red_addon_public_route_dispatch')) {
    function red_addon_public_route_dispatch($method, $requestUri, $query)
    {
        $result = red_addon_public_route_result();
        $path = red_addon_public_route_path($requestUri);
        if ($path === null) {
            return $result;
        }
        $declaration = red_addon_public_route_declaration($path);
        if (!is_array($declaration)) {
            return $result;
        }

        $result['claimed'] = true;
        $result['route'] = $declaration['id'];
        $result['package'] = $declaration['owner'];
        $route = $declaration['route'];
        if (($route['scope'] ?? null) !== 'public'
            || ($route['authentication'] ?? null) !== 'public'
            || ($route['csrf'] ?? null) !== 'not-applicable'
            || str_contains($path, '{')
            || str_contains($path, '}')
        ) {
            return red_addon_public_route_response(
                $result,
                503,
                ['ok' => false, 'error' => 'temporarily_unavailable'],
                'route_not_dispatchable'
            );
        }
        if (!is_string($method)
            || $method !== 'GET'
            || !in_array('GET', $route['methods'] ?? [], true)
            || !in_array($method, $route['methods'] ?? [], true)
        ) {
            $result = red_addon_public_route_response(
                $result,
                405,
                ['ok' => false, 'error' => 'method_not_allowed'],
                'method_not_allowed'
            );
            $result['headers']['Allow'] = 'GET';
            return $result;
        }
        if (!is_array($query)) {
            return red_addon_public_route_response(
                $result,
                400,
                ['ok' => false, 'error' => 'invalid_request'],
                'invalid_request'
            );
        }
        try {
            $request = new RED_Addon_Public_Route_Request(
                $declaration['id'],
                $method,
                $path,
                $query
            );
        } catch (Throwable $throwable) {
            return red_addon_public_route_response(
                $result,
                400,
                ['ok' => false, 'error' => 'invalid_request'],
                'invalid_request'
            );
        }

        $owner = red_addon_runtime_owner('routes', $declaration['id']);
        $handler = red_addon_runtime_handler('routes', $declaration['id']);
        if ($owner !== $declaration['owner'] || !is_callable($handler)) {
            return red_addon_public_route_response(
                $result,
                503,
                ['ok' => false, 'error' => 'temporarily_unavailable'],
                'route_unavailable'
            );
        }

        $bufferLevel = ob_get_level();
        $headersBefore = headers_list();
        $statusBefore = http_response_code();
        try {
            ob_start();
            $result['invoked'] = true;
            $routeResult = $handler($request);
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new RuntimeException(
                    'Add-on public route altered the output buffer stack.'
                );
            }
            if (headers_list() !== $headersBefore
                || http_response_code() !== $statusBefore
            ) {
                throw new RuntimeException(
                    'Add-on public route altered HTTP response state.'
                );
            }
            $output = (string) ob_get_clean();
            if ($output !== '') {
                return red_addon_public_route_response(
                    $result,
                    503,
                    ['ok' => false, 'error' => 'temporarily_unavailable'],
                    'route_output'
                );
            }
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            red_addon_public_route_restore_http_state(
                $headersBefore,
                $statusBefore
            );
            return red_addon_public_route_response(
                $result,
                503,
                ['ok' => false, 'error' => 'temporarily_unavailable'],
                'route_failed'
            );
        }
        if (!$routeResult instanceof RED_Addon_Public_Route_Result) {
            return red_addon_public_route_response(
                $result,
                503,
                ['ok' => false, 'error' => 'temporarily_unavailable'],
                'invalid_result'
            );
        }

        $result['success'] = $routeResult->successState();
        return red_addon_public_route_response(
            $result,
            $routeResult->status(),
            $result['success']
                ? ['ok' => true, 'data' => $routeResult->data()]
                : ['ok' => false, 'error' => $routeResult->error()],
            $result['success'] ? 'completed' : 'route_error'
        );
    }
}

if (!function_exists('red_addon_public_route_emit')) {
    function red_addon_public_route_emit(array $result)
    {
        if (empty($result['claimed'])
            || !is_int($result['status'])
            || $result['status'] < 100
            || $result['status'] > 599
            || !is_array($result['headers'])
            || !is_string($result['body'])
        ) {
            throw new InvalidArgumentException(
                'Add-on public route response is invalid.'
            );
        }
        http_response_code($result['status']);
        foreach ($result['headers'] as $name => $value) {
            if (is_string($name) && is_string($value)) {
                header($name . ': ' . $value);
            }
        }
        echo $result['body'];
    }
}

?>
