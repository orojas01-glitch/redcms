<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/addon_public_mutation_endpoint_helpers.php';
require_once dirname(__DIR__)
    . '/includes/addon_subscription_checkout_return_helpers.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (
    &$assertions
): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$previousEnabled = getenv('RED_SUBSCRIPTION_CHECKOUT_SANDBOX_ENABLED');
$previousAuthorization = getenv(
    'RED_SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256'
);

try {
    putenv('RED_SUBSCRIPTION_CHECKOUT_SANDBOX_ENABLED');
    putenv('RED_SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256');

    $assert(
        red_addon_subscription_checkout_public_runtime_target(
            '/addons/redcms/store-lite/subscription-intent'
        )
            && red_addon_subscription_checkout_public_runtime_target(
                '/addons/redcms/store-lite/subscription-intent?x=1'
            )
            && !red_addon_subscription_checkout_public_runtime_target(
                '/addons/redcms/store-lite/cart-intent'
            ),
        'only the exact subscription intent path selects the runtime'
    );
    $assert(
        !red_addon_subscription_checkout_public_runtime_enabled(),
        'provider runtime defaults disabled without server authorization'
    );

    $ordinary = red_addon_public_mutation_response_success('accepted');
    $ordinaryDispatch = [
        'claimed' => true,
        'response' => $ordinary,
        'reason' => 'completed',
    ];
    $passthrough =
        red_addon_subscription_checkout_public_runtime_complete(
            null,
            dirname(__DIR__),
            '/addons/redcms/store-lite/cart-intent',
            [],
            $ordinaryDispatch
        );
    $assert(
        $passthrough === $ordinary,
        'ordinary public mutations bypass the subscription provider runtime'
    );
    $closed = red_addon_subscription_checkout_public_runtime_complete(
        null,
        dirname(__DIR__),
        '/addons/redcms/store-lite/subscription-intent',
        [],
        $ordinaryDispatch
    );
    $assert(
        red_addon_public_mutation_response_emitter_valid($closed)
            && $closed['httpStatus'] === 503,
        'subscription provider runtime fails closed before database or provider access'
    );

    $session = 'cs_test_PublicRuntime1234567890';
    $url = 'https://checkout.stripe.com/c/pay/' . $session;
    $body = json_encode([
        'ok' => true,
        'outcome' => 'subscription_checkout_ready',
        'checkoutUrl' => $url,
        'navigationMode' => 'location.assign',
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $subscription = [
        'valid' => true,
        'httpStatus' => 200,
        'headers' => [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($body),
        ],
        'body' => $body,
        'ok' => true,
        'outcome' => 'subscription_checkout_ready',
        'checkoutUrl' => $url,
        'navigationMode' => 'location.assign',
        'transientOnly' => true,
        'navigationAuthorized' => true,
        'authorizationSha256' => str_repeat('a', 64),
        'reason' => 'handoff_ready',
    ];
    $assert(
        red_addon_subscription_checkout_public_response_valid(
            $subscription
        )
            && red_addon_public_mutation_endpoint_response_valid(
                $subscription
            ),
        'endpoint response union accepts the closed subscription handoff'
    );
    $assert(
        red_addon_public_mutation_endpoint_response_valid($ordinary),
        'endpoint response union preserves ordinary public mutations'
    );

    putenv('RED_SUBSCRIPTION_CHECKOUT_SANDBOX_ENABLED=1');
    putenv(
        'RED_SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256=' . str_repeat('b', 64)
    );
    $assert(
        red_addon_subscription_checkout_public_runtime_enabled(),
        'exact server authorization enables only the Sandbox runtime gate'
    );
    $complete = red_addon_subscription_checkout_return(
        'GET',
        '/subscription/complete'
    );
    $cancel = red_addon_subscription_checkout_return(
        'GET',
        '/subscription/cancel'
    );
    $assert(
        red_addon_subscription_checkout_return_valid($complete)
            && $complete['outcome'] === 'complete'
            && str_contains(
                $complete['body'],
                'activated only after the signed subscription confirmation'
            ),
        'success return stays pending until signed webhook confirmation'
    );
    $assert(
        red_addon_subscription_checkout_return_valid($cancel)
            && $cancel['outcome'] === 'canceled'
            && str_contains($cancel['body'], 'No subscription was completed'),
        'cancel return states that no subscription completed'
    );
    $assert(
        !red_addon_subscription_checkout_return(
            'GET',
            '/subscription/complete?forged=1'
        )['claimed']
            && !red_addon_subscription_checkout_return(
                'POST',
                '/subscription/complete'
            )['claimed'],
        'return boundary refuses queries and non-GET methods'
    );

    $runtimeSource = (string) file_get_contents(
        dirname(__DIR__)
            . '/includes/addon_subscription_checkout_public_runtime_helpers.php'
    );
    $returnSource = (string) file_get_contents(
        dirname(__DIR__)
            . '/includes/addon_subscription_checkout_return_helpers.php'
    );
    $indexSource = (string) file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(
        !preg_match(
            '/\$_(?:GET|POST|COOKIE|SERVER|SESSION|ENV)|\b(?:header|setcookie)\b/',
            $runtimeSource
        )
            && str_contains(
                $runtimeSource,
                "['stripe.secret-key']"
            )
            && !str_contains($runtimeSource, "['stripe.webhook-secret']"),
        'provider runtime reads no request globals and scopes access to the API key'
    );
    $assert(
        str_contains(
            $indexSource,
            'addon_subscription_checkout_return_helpers.php'
        )
            && str_contains(
                $returnSource,
                'red_addon_subscription_checkout_public_runtime_enabled'
            ),
        'front controller links only the gated fixed return boundary'
    );

    echo 'Subscription Checkout public runtime passed '
        . $assertions . " assertions.\n";
    echo "No database, secret resolution, network, Stripe, Checkout, payment, browser navigation, deployment, or live-mode action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
} finally {
    $previousEnabled === false
        ? putenv('RED_SUBSCRIPTION_CHECKOUT_SANDBOX_ENABLED')
        : putenv(
            'RED_SUBSCRIPTION_CHECKOUT_SANDBOX_ENABLED=' . $previousEnabled
        );
    $previousAuthorization === false
        ? putenv('RED_SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256')
        : putenv(
            'RED_SUBSCRIPTION_CHECKOUT_AUTHORIZATION_SHA256='
                . $previousAuthorization
        );
}

exit(0);
