<?php
/** Server-owned CSP. Never infer permissions from request data or stored HTML. */
require_once __DIR__ . '/runtime_config_helpers.php';

function red_csp_policy(array $extra = [], bool $strictReport = false): string
{
    // Compatibility profile: inline code remains necessary in legacy CMS views.
    // External integrations must be inventoried per installation before enforcing.
    $directives = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://connect.facebook.net'],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'blob:'],
        'font-src' => ["'self'", 'data:'],
        'connect-src' => ["'self'"],
        'media-src' => ["'self'", 'blob:'],
        'frame-src' => ["'self'", 'https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com', 'https://www.facebook.com'],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'frame-ancestors' => ["'self'"],
        'form-action' => ["'self'"],
    ];
    $extendable = ['script-src', 'style-src', 'img-src', 'font-src', 'connect-src', 'media-src', 'frame-src', 'form-action'];
    foreach ($extra as $directive => $origins) {
        if (!in_array($directive, $extendable, true) || !is_array($origins) || count($origins) > 32) {
            throw new InvalidArgumentException('Invalid CSP source configuration.');
        }
        foreach ($origins as $origin) {
            // Exact HTTPS origins only: no wildcards, userinfo, paths, queries,
            // control characters, unsafe keywords, or reporting destinations.
            if (!is_string($origin) || strlen($origin) > 260
                || !preg_match('~\Ahttps://[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?(?::[1-9][0-9]{0,4})?\z~D', $origin)
                || parse_url($origin) === false) {
                throw new InvalidArgumentException('Invalid CSP source configuration.');
            }
            $directives[$directive][] = $origin;
        }
    }
    if ($strictReport) {
        $directives['script-src'] = array_values(array_diff($directives['script-src'], ["'unsafe-inline'"]));
    }
    $parts = [];
    foreach ($directives as $name => $sources) {
        $parts[] = $name . ' ' . implode(' ', array_unique($sources));
    }
    return implode('; ', $parts);
}

function red_csp_headers($mode, $sources, $strictReport): array
{
    if ($mode === 'off') {
        return [];
    }
    if (!in_array($mode, ['report-only', 'enforce'], true)) {
        throw new InvalidArgumentException('Invalid CSP mode.');
    }
    if (is_string($sources)) {
        $sources = json_decode($sources, true, 16, JSON_THROW_ON_ERROR);
    }
    if (!is_array($sources) || !is_bool($strictReport)) {
        throw new InvalidArgumentException('Invalid CSP configuration.');
    }
    $name = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
    $headers = [$name => red_csp_policy($sources, $mode === 'report-only' && $strictReport)];
    if ($mode === 'enforce' && $strictReport) {
        $headers['Content-Security-Policy-Report-Only'] = red_csp_policy($sources, true);
    }
    return $headers;
}

function red_csp_emit(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    try {
        $mode = red_server_config_value('CSP_MODE', ['RED_CSP_MODE'], 'off');
        $sources = red_server_config_value('CSP_SOURCES', ['RED_CSP_SOURCES'], []);
        $strict = red_server_config_value('CSP_STRICT_REPORT', ['RED_CSP_STRICT_REPORT'], false);
        if (is_string($strict) && in_array($strict, ['0', '1'], true)) {
            $strict = $strict === '1';
        }
        $headers = red_csp_headers($mode, $sources, $strict);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
    } catch (Throwable $exception) {
        // A configured-but-invalid policy must not silently remove protection.
        // No configuration values or exception details enter responses or logs.
        error_log('RED-CMS CSP configuration invalid.');
        http_response_code(503);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Security configuration is temporarily unavailable.');
    }
}
