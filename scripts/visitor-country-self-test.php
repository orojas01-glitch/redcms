<?php
/**
 * Dependency-free checks for display-only visitor country detection.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/visitor_country_helpers.php';

$assertions = 0;

function red_visitor_country_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_visitor_country_with_env(array $env, callable $callback)
{
    $previous = [];
    foreach ($env as $key => $value) {
        $previous[$key] = getenv($key);
        putenv($value === null ? $key : $key . '=' . $value);
    }
    try {
        return $callback();
    } finally {
        foreach ($previous as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
    }
}

$headers = [
    'HTTP_CF_IPCOUNTRY' => 'co',
    'GEOIP_COUNTRY_CODE' => 'US',
    'REDIRECT_MM_COUNTRY_CODE' => 'MX',
];

red_visitor_country_assert(
    red_visitor_country_with_env(
        ['RED_VISITOR_COUNTRY_SOURCE' => null],
        fn () => red_visitor_country_code($headers)
    ) === null,
    'detection is off by default and ignores every header'
);
red_visitor_country_assert(
    red_visitor_country_with_env(
        ['RED_VISITOR_COUNTRY_SOURCE' => 'cloudflare'],
        fn () => red_visitor_country_code($headers)
    ) === 'CO',
    'cloudflare source reads only CF-IPCountry'
);
red_visitor_country_assert(
    red_visitor_country_with_env(
        ['RED_VISITOR_COUNTRY_SOURCE' => 'cloudflare'],
        fn () => [
            red_visitor_country_code(['HTTP_CF_IPCOUNTRY' => 'XX']),
            red_visitor_country_code(['HTTP_CF_IPCOUNTRY' => 'T1']),
            red_visitor_country_code(['HTTP_CF_IPCOUNTRY' => 'COL']),
            red_visitor_country_code([]),
        ]
    ) === [null, null, null, null],
    'unknown, Tor, malformed, and missing values are unknown'
);
red_visitor_country_assert(
    red_visitor_country_with_env(
        ['RED_VISITOR_COUNTRY_SOURCE' => 'server-variable'],
        fn () => [
            red_visitor_country_code($headers),
            red_visitor_country_code(['REDIRECT_MM_COUNTRY_CODE' => 'mx']),
            red_visitor_country_code(['HTTP_CF_IPCOUNTRY' => 'CO']),
        ]
    ) === ['US', 'MX', null],
    'server-variable source reads GeoIP module variables and never the header'
);
red_visitor_country_assert(
    red_visitor_country_with_env(
        [
            'RED_VISITOR_COUNTRY_SOURCE' => 'server-variable',
            'RED_VISITOR_COUNTRY_SERVER_VARIABLE' => 'HOST_GEO_COUNTRY',
        ],
        fn () => red_visitor_country_code(
            ['HOST_GEO_COUNTRY' => 'CO', 'GEOIP_COUNTRY_CODE' => 'US']
        )
    ) === 'CO'
        && red_visitor_country_with_env(
            [
                'RED_VISITOR_COUNTRY_SOURCE' => 'server-variable',
                'RED_VISITOR_COUNTRY_SERVER_VARIABLE' => 'HTTP_X_COUNTRY',
            ],
            fn () => red_visitor_country_code(['HTTP_X_COUNTRY' => 'CO'])
        ) === null,
    'a configured variable wins, but a request-header name is refused'
);
red_visitor_country_assert(
    red_visitor_country_with_env(
        ['RED_VISITOR_COUNTRY_SOURCE' => 'ipstack'],
        fn () => red_visitor_country_source()
    ) === 'off',
    'unknown sources fall back to off'
);
$source = (string) file_get_contents(
    dirname(__DIR__) . '/includes/visitor_country_helpers.php'
);
red_visitor_country_assert(
    !str_contains($source, 'curl_')
        && !str_contains($source, 'file_get_contents')
        && !str_contains($source, 'fsockopen'),
    'detection never contacts an outside service'
);

printf("Visitor country self-test passed (%d assertions).\n", $assertions);
