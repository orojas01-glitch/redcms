<?php
/**
 * Dependency-free source contract for the unlinked browser controller.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$controllerPath = $projectRoot . '/js/public-addon-mutation.js';
$browserTestPath = $projectRoot .
    '/scripts/addon-public-mutation-browser-controller-self-test.mjs';
$controller = is_file($controllerPath)
    ? file_get_contents($controllerPath)
    : false;
$browserTest = is_file($browserTestPath)
    ? file_get_contents($browserTestPath)
    : false;
$assertions = 0;

function red_addon_browser_controller_contract_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

red_addon_browser_controller_contract_assert(
    is_string($controller)
        && strlen($controller) >= 4000
        && strlen($controller) <= 20000
        && str_starts_with($controller, "(function () {\n    'use strict';"),
    'controller is one bounded strict core-owned script'
);
red_addon_browser_controller_contract_assert(
    substr_count($controller, '[data-red-addon-public-mutation-form]') === 1
        && substr_count(
            $controller,
            '[data-red-addon-public-mutation-status]'
        ) === 1
        && str_contains($controller, "addEventListener('submit'")
        && str_contains($controller, 'event.preventDefault()'),
    'controller attaches only to the core form and status contract'
);
red_addon_browser_controller_contract_assert(
    str_contains($controller, "'X-RED-CMS-CSRF': state.csrfToken")
        && str_contains(
            $controller,
            "'Idempotency-Key': state.idempotencyKey"
        )
        && str_contains(
            $controller,
            "'Content-Type': 'application/x-www-form-urlencoded'"
        )
        && str_contains($controller, "Accept: 'application/json'"),
    'controller owns the exact fixed request headers'
);
red_addon_browser_controller_contract_assert(
    str_contains($controller, "method: 'POST'")
        && str_contains($controller, "mode: 'same-origin'")
        && str_contains($controller, "credentials: 'same-origin'")
        && str_contains($controller, "cache: 'no-store'")
        && str_contains($controller, "redirect: 'error'")
        && str_contains($controller, "referrerPolicy: 'same-origin'"),
    'fetch is same-origin, no-store, and redirect closed'
);
red_addon_browser_controller_contract_assert(
    str_contains($controller, 'var states = new WeakMap();')
        && str_contains($controller, 'state.body = formBody(form);')
        && str_contains($controller, 'freezeCommand(state);')
        && str_contains(
            $controller,
            "data-red-addon-public-mutation-frozen', 'true'"
        ),
    'one captured command body is frozen for exact retry only'
);
red_addon_browser_controller_contract_assert(
    str_contains(
        $controller,
        "form.removeAttribute('data-red-csrf-token')"
    )
        && str_contains(
            $controller,
            "form.removeAttribute('data-red-idempotency-key')"
        )
        && !str_contains($controller, 'document.cookie')
        && !str_contains($controller, 'localStorage')
        && !str_contains($controller, 'sessionStorage'),
    'opaque evidence is removed from DOM and never persisted by JavaScript'
);
red_addon_browser_controller_contract_assert(
    !preg_match(
        '/\b(?:eval|Function|WebSocket|EventSource|sendBeacon)\s*\(/',
        $controller
    )
        && !str_contains($controller, 'innerHTML')
        && !str_contains($controller, 'outerHTML')
        && !str_contains($controller, 'insertAdjacentHTML')
        && !str_contains($controller, 'console.')
        && !preg_match('/https?:\/\//', $controller),
    'controller has no dynamic-code, HTML sink, logging, or external URL path'
);
red_addon_browser_controller_contract_assert(
    is_string($browserTest)
        && str_contains($browserTest, '{width: 1440, height: 1000}')
        && str_contains($browserTest, '{width: 390, height: 844}')
        && str_contains($browserTest, "route.abort('failed')")
        && str_contains($browserTest, 'request_conflict')
        && str_contains($browserTest, 'https://foreign.example/add-to-cart')
        && str_contains($browserTest, 'pageErrors.length === 0'),
    'browser proof covers desktop, mobile, retry, refusal, and invalid setup'
);
$linkedSources = [
    $projectRoot . '/index.php',
    $projectRoot . '/includes/theme_standard_adapter.php',
    $projectRoot . '/themes/legacy-bootstrap/document.php',
];
$linked = false;
foreach ($linkedSources as $path) {
    $source = is_file($path) ? file_get_contents($path) : false;
    $linked = $linked || (is_string($source)
        && str_contains($source, 'public-addon-mutation.js'));
}
red_addon_browser_controller_contract_assert(
    !$linked,
    'controller remains unlinked until response and endpoint ownership land'
);

echo 'Public mutation browser controller contract passed (' .
    $assertions . " assertions).\n";

?>
