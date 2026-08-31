<?php

declare(strict_types=1);

$helper = dirname(__DIR__)
    . '/includes/addon_subscription_catalog_binding_helpers.php';
if (!is_file($helper)) {
    throw new RuntimeException('Subscription catalog binding helper missing.');
}
require_once $helper;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$binding = [
    'offerId' => 'ai-assistant-foundation-monthly',
    'stripeProductId' => 'prod_VAdppdm2hxfXT7',
    'stripePriceId' => 'price_1UAIXQPzjg2rInjnX5CypQNL',
    'currency' => 'USD',
    'priceMinor' => 5900,
    'billingPeriod' => 'monthly',
    'active' => true,
    'livemode' => false,
];
$offer = [
    'id' => 'ai-assistant-foundation-monthly',
    'productId' => 'ai-assistant-foundation',
    'variantId' => null,
    'title' => 'AI Assistance Foundation',
    'summary' => 'Configured AI messaging assistant.',
    'currency' => 'USD',
    'priceMinor' => 5900,
    'billingPeriod' => 'monthly',
    'state' => 'published',
    'availability' => 'available',
    'buttonLabel' => 'Start sandbox subscription',
];

$normalized = red_addon_subscription_catalog_binding_normalize($binding);
$assert($normalized === $binding, 'exact array binding accepted');
$assert(
    red_addon_subscription_catalog_binding_normalize(json_encode(
        $binding,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    )) === $binding,
    'server environment JSON binding accepted'
);
$assert(
    red_addon_subscription_catalog_binding_matches_offer($binding, $offer),
    'catalog binding matches the provider-neutral Store Lite offer'
);

foreach ([
    ['stripeProductId', 'prod_invalid space'],
    ['stripePriceId', 'price_live_invalid'],
    ['active', false],
    ['livemode', true],
] as [$key, $value]) {
    $changed = $binding;
    $changed[$key] = $value;
    $assert(
        red_addon_subscription_catalog_binding_normalize($changed) === null,
        'invalid catalog binding refused: ' . $key
    );
}

foreach ([
    ['currency', 'EUR'],
    ['priceMinor', 5800],
    ['billingPeriod', 'yearly'],
] as [$key, $value]) {
    $changed = $binding;
    $changed[$key] = $value;
    $assert(
        !red_addon_subscription_catalog_binding_matches_offer(
            $changed,
            $offer
        ),
        'commercial catalog drift refused: ' . $key
    );
}

$foreign = $binding;
$foreign['offerId'] = 'foreign-offer';
$assert(
    !red_addon_subscription_catalog_binding_matches_offer($foreign, $offer),
    'foreign offer mapping refused'
);
$assert(
    red_addon_subscription_catalog_binding_normalize(
        json_encode($binding) . ' trailing'
    ) === null,
    'malformed environment JSON refused'
);

echo 'Subscription catalog binding helper passed '
    . $assertions . " assertions.\n";
