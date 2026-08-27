<?php
/** Fixed no-store browser returns for Stripe hosted subscription Checkout. */

require_once __DIR__ . '/addon_subscription_checkout_public_runtime_helpers.php';

if (!function_exists('red_addon_subscription_checkout_return_result')) {
    function red_addon_subscription_checkout_return_result()
    {
        return [
            'claimed' => false,
            'status' => 0,
            'headers' => [],
            'body' => '',
            'outcome' => '',
        ];
    }
}

if (!function_exists('red_addon_subscription_checkout_return')) {
    function red_addon_subscription_checkout_return($method, $target)
    {
        $result = red_addon_subscription_checkout_return_result();
        if ($method !== 'GET'
            || !is_string($target)
            || !in_array(
                $target,
                ['/subscription/complete', '/subscription/cancel'],
                true
            )
            || !red_addon_subscription_checkout_public_runtime_enabled()
        ) {
            return $result;
        }
        $completed = $target === '/subscription/complete';
        $outcome = $completed ? 'complete' : 'canceled';
        $title = $completed
            ? 'Subscription confirmation in progress'
            : 'Checkout canceled';
        $message = $completed
            ? 'Stripe has returned you safely. Access is activated only after the signed subscription confirmation is received.'
            : 'No subscription was completed. You can return to the store whenever you are ready.';
        $body = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . $title . ' | RED-CMS Store Lite</title>'
            . '<style>html{color-scheme:light}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f1eb;color:#171717;font:16px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:min(620px,100%);padding:clamp(28px,6vw,56px);border:1px solid #d7d0c5;border-radius:24px;background:#fff;box-shadow:0 24px 70px rgba(37,28,18,.12)}.eyebrow{margin:0 0 12px;color:#9b321e;font-size:.78rem;font-weight:750;letter-spacing:.12em;text-transform:uppercase}h1{margin:0;font-size:clamp(2rem,7vw,3.5rem);line-height:1.02;letter-spacing:-.045em}p{margin:22px 0 0;color:#5c554d;max-width:48ch}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}a{display:inline-flex;min-height:46px;align-items:center;justify-content:center;padding:0 20px;border-radius:999px;background:#171717;color:#fff;font-weight:700;text-decoration:none}a:focus-visible{outline:3px solid #e26140;outline-offset:3px}@media(prefers-reduced-motion:no-preference){.card{animation:enter .45s cubic-bezier(.22,1,.36,1) both}@keyframes enter{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}}</style>'
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
        $result['outcome'] = $outcome;
        return $result;
    }
}

if (!function_exists('red_addon_subscription_checkout_return_valid')) {
    function red_addon_subscription_checkout_return_valid($result)
    {
        return is_array($result)
            && array_keys($result) === [
                'claimed', 'status', 'headers', 'body', 'outcome',
            ]
            && ($result['claimed'] ?? null) === true
            && ($result['status'] ?? null) === 200
            && in_array(
                $result['outcome'] ?? '',
                ['complete', 'canceled'],
                true
            )
            && is_string($result['body'] ?? null)
            && strlen($result['body']) >= 256
            && ($result['headers']['Content-Type'] ?? '')
                === 'text/html; charset=UTF-8'
            && ($result['headers']['Cache-Control'] ?? '') === 'no-store'
            && ($result['headers']['X-Content-Type-Options'] ?? '')
                === 'nosniff'
            && ($result['headers']['Referrer-Policy'] ?? '') === 'same-origin'
            && ($result['headers']['Content-Length'] ?? '')
                === (string) strlen($result['body']);
    }
}

if (!function_exists('red_addon_subscription_checkout_return_emit')) {
    function red_addon_subscription_checkout_return_emit($result)
    {
        if (!red_addon_subscription_checkout_return_valid($result)
            || headers_sent()
        ) {
            throw new RuntimeException(
                'Subscription Checkout return is unavailable.'
            );
        }
        header_remove();
        http_response_code(200);
        foreach ($result['headers'] as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $result['body'];
    }
}

?>
