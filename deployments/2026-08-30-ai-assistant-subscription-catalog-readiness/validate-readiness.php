<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$coreRoot = dirname(__DIR__, 2);
$documentsRoot = dirname($coreRoot);
$storeRoot = $documentsRoot . '/redcms-store-lite';
$adapterRoot = $documentsRoot . '/redcms-store-lite-stripe-checkout';
foreach ([
    $storeRoot . '/package/src/ProductNormalizer.php',
    $storeRoot . '/package/src/SubscriptionOffer.php',
    $coreRoot . '/includes/addon_subscription_catalog_binding_helpers.php',
    $adapterRoot . '/package/addon.json',
] as $required) {
    if (!is_file($required)) {
        throw new RuntimeException('Required source is unavailable.');
    }
}
require_once $storeRoot . '/package/src/ProductNormalizer.php';
require_once $storeRoot . '/package/src/SubscriptionOffer.php';
require_once $coreRoot
    . '/includes/addon_subscription_catalog_binding_helpers.php';

$decode = static function (string $path): array {
    $raw = file_get_contents($path);
    if (!is_string($raw)) {
        throw new RuntimeException('Readiness file is unavailable.');
    }
    $value = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($value) || array_is_list($value)) {
        throw new RuntimeException('Readiness file is malformed.');
    }
    return $value;
};
$profile = $decode(__DIR__ . '/store-lite-offer.json');
$facts = $decode(__DIR__ . '/stripe-sandbox-facts.json');
$manifest = $decode($adapterRoot . '/package/addon.json');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$product = RED_CMS_Store_Lite_Product_Normalizer::normalize(
    $profile['product'] ?? [],
    'USD'
);
$offer = RED_CMS_Store_Lite_Subscription_Offer::normalize(
    $profile['offer'] ?? [],
    'USD'
);
$assert(($product['valid'] ?? null) === true, 'Store Lite Product is valid');
$assert(($offer['valid'] ?? null) === true, 'Store Lite offer is valid');

$binding = [
    'offerId' => $profile['offer']['id'] ?? null,
    'stripeProductId' => $facts['product']['id'] ?? null,
    'stripePriceId' => $facts['price']['id'] ?? null,
    'currency' => $facts['price']['currency'] ?? null,
    'priceMinor' => $facts['price']['unitAmountMinor'] ?? null,
    'billingPeriod' => ($facts['price']['interval'] ?? null) === 'month'
        ? 'monthly' : 'invalid',
    'active' => $facts['price']['active'] ?? null,
    'livemode' => ($facts['mode'] ?? null) !== 'sandbox',
];
$assert(
    red_addon_subscription_catalog_binding_normalize($binding) === $binding,
    'Stripe binding shape is valid'
);
$assert(
    red_addon_subscription_catalog_binding_matches_offer(
        $binding,
        $offer['offer'] ?? []
    ),
    'Stripe binding matches Store Lite offer'
);
$assert(
    ($facts['product']['active'] ?? null) === true
        && ($facts['price']['defaultForProduct'] ?? null) === true
        && ($facts['price']['type'] ?? null) === 'recurring'
        && ($facts['price']['intervalCount'] ?? null) === 1,
    'observed Stripe catalog facts are exact'
);
$assert(
    ($facts['eventDestination']['active'] ?? null) === true
        && ($facts['eventDestination']['events'] ?? null) === [
            'checkout.session.completed',
            'checkout.session.expired',
            'customer.subscription.deleted',
            'invoice.paid',
            'invoice.payment_failed',
        ],
    'webhook destination has the closed five-event set'
);
$assert(
    ($manifest['version'] ?? null) === '0.1.19',
    'prepared adapter version is 0.1.19'
);

foreach ($manifest['integrity']['files'] ?? [] as $file) {
    $path = $adapterRoot . '/package/' . ($file['path'] ?? '');
    $assert(
        is_file($path)
            && hash_equals(
                (string) ($file['sha256'] ?? ''),
                hash_file('sha256', $path)
            ),
        'adapter inventory hash matches: ' . ($file['path'] ?? '')
    );
}

$packet = implode('', [
    (string) file_get_contents(__DIR__ . '/stripe-sandbox-facts.json'),
    (string) file_get_contents(__DIR__ . '/store-lite-offer.json'),
    (string) file_get_contents(__DIR__ . '/config.local.catalog-binding.php'),
]);
$assert(
    preg_match('/\b(?:sk|rk)_(?:test|live)_[A-Za-z0-9_]+|\bwhsec_[A-Za-z0-9_]+/', $packet) !== 1,
    'readiness files contain no Stripe credential values'
);

echo 'AI Assistant subscription readiness passed '
    . $assertions . " assertions.\n";
echo "No database, secret, network, provider, payment, or deployment action occurred.\n";
