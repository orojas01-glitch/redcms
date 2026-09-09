<?php
require_once __DIR__ . '/../includes/csp_helpers.php';
$count = 0;
function csp_check($condition): void { global $count; if (!$condition) { throw new RuntimeException('CSP assertion failed: ' . ($count + 1)); } $count++; }
$policy = red_csp_policy();
foreach (["object-src 'none'", "base-uri 'self'", "frame-ancestors 'self'", "form-action 'self'", "'unsafe-inline'"] as $expected) { csp_check(str_contains($policy, $expected)); }
csp_check(!str_contains($policy, 'unsafe-eval'));
csp_check(red_csp_headers('off', [], false) === []);
csp_check(array_keys(red_csp_headers('report-only', [], false)) === ['Content-Security-Policy-Report-Only']);
$headers = red_csp_headers('enforce', ['connect-src' => ['https://api.example.test']], true);
csp_check(count($headers) === 2);
csp_check(str_contains($headers['Content-Security-Policy'], 'https://api.example.test'));
csp_check(!str_contains(explode('; style-src', $headers['Content-Security-Policy-Report-Only'])[0], 'unsafe-inline'));
csp_check(str_contains($headers['Content-Security-Policy-Report-Only'], "style-src 'self' 'unsafe-inline'"));
csp_check(red_csp_headers('enforce', '{}', false) === red_csp_headers('enforce', [], false));
foreach (['https://evil.test; script-src *', "https://example.test\r\nX-Test: yes", 'http://example.test', 'https://*.example.test', 'https://user@example.test', 'https://example.test/path', 'https://example.test?key=value', "'unsafe-eval'", 'https://example.test:99999'] as $origin) {
    try { red_csp_policy(['script-src' => [$origin]]); csp_check(false); } catch (InvalidArgumentException $e) { csp_check(true); }
}
foreach ([['bad', [], false], ['enforce', ['report-uri' => ['https://example.test']], false], ['enforce', ['object-src' => ['https://example.test']], false], ['enforce', 'null', false], ['enforce', [], 'false']] as $args) {
    try { red_csp_headers(...$args); csp_check(false); } catch (InvalidArgumentException $e) { csp_check(true); }
}
echo "CSP contracts passed: $count assertions.\n";
