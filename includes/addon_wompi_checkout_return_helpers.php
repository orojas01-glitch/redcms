<?php
/**
 * Fixed no-store browser return page for Wompi Web Checkout.
 *
 * Wompi appends ?id=<transaction>&env=<test|prod> to the redirect URL. The
 * page ignores the query entirely: it neither echoes nor trusts it, because
 * the order changes only through the verified webhook.
 */

require_once __DIR__ . '/addon_wompi_checkout_public_runtime_helpers.php';
require_once __DIR__ . '/addon_subscription_checkout_return_helpers.php';

if (!function_exists('red_addon_wompi_checkout_return_candidate')) {
    function red_addon_wompi_checkout_return_candidate($target)
    {
        if (!is_string($target) || strlen($target) > 2048) {
            return false;
        }
        $queryAt = strpos($target, '?');
        $path = $queryAt === false ? $target : substr($target, 0, $queryAt);
        return $path === red_addon_wompi_checkout_public_runtime_return_path();
    }
}

if (!function_exists('red_addon_wompi_checkout_return')) {
    function red_addon_wompi_checkout_return($method, $target)
    {
        $result = red_addon_subscription_checkout_return_result();
        if ($method !== 'GET'
            || !red_addon_wompi_checkout_return_candidate($target)
            || !red_addon_wompi_checkout_public_runtime_enabled()
        ) {
            return $result;
        }
        $title = 'Payment in review';
        $message = 'Wompi has returned you to the store. Your order is '
            . 'confirmed as paid only after Wompi notifies the store, which '
            . 'usually takes a few seconds. If the payment did not go '
            . 'through, the order stays unpaid and nothing is charged.';
        $body = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . $title . ' | RED-CMS Store Lite</title>'
            . '<style>html{color-scheme:light}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f1eb;color:#171717;font:16px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:min(620px,100%);padding:clamp(28px,6vw,56px);border:1px solid #d7d0c5;border-radius:24px;background:#fff;box-shadow:0 24px 70px rgba(37,28,18,.12)}.eyebrow{margin:0 0 12px;color:#9b321e;font-size:.78rem;font-weight:750;letter-spacing:.12em;text-transform:uppercase}h1{margin:0;font-size:clamp(2rem,7vw,3.5rem);line-height:1.02;letter-spacing:-.045em}p{margin:22px 0 0;color:#5c554d;max-width:48ch}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}a{display:inline-flex;min-height:46px;align-items:center;justify-content:center;padding:0 20px;border-radius:999px;background:#171717;color:#fff;font-weight:700;text-decoration:none}a:focus-visible{outline:3px solid #e26140;outline-offset:3px}</style>'
            . '</head><body><main class="card"><p class="eyebrow">RED-CMS Store Lite</p>'
            . '<h1>' . $title . '</h1><p>' . $message . '</p>'
            . '<div class="actions"><a href="/">Return to the store</a></div>'
            . '</main></body></html>';
        $result['claimed'] = true;
        $result['status'] = 200;
        $result['headers'] = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Content-Length' => (string) strlen($body),
        ];
        $result['body'] = $body;
        $result['outcome'] = 'complete';
        return $result;
    }
}

?>
