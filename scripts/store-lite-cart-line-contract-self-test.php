<?php
/**
 * Dependency-free Store Lite server-authoritative cart-line contract fixture.
 *
 * This consumes only Gate 2A-normalized product-shaped arrays. It is not a
 * core commerce implementation and performs no database, filesystem, request,
 * package, lifecycle, runtime, cookie, session, or network work.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$assertions = 0;

function red_store_lite_cart_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_store_lite_cart_identifier($value)
{
    return is_string($value)
        && preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $value) === 1;
}

function red_store_lite_cart_refusal($reason)
{
    return ['resolved' => false, 'reason' => $reason, 'line' => null];
}

function red_store_lite_cart_product_shape(array $product, $currency)
{
    if (array_keys($product) !== [
            'id', 'type', 'title', 'summary', 'currency', 'state',
            'availability', 'imageRef', 'sku', 'priceMinor', 'stock',
            'options', 'variants',
        ]
        || !red_store_lite_cart_identifier($product['id'])
        || !in_array($product['type'], ['simple', 'variable'], true)
        || !is_string($product['title'])
        || $product['title'] === ''
        || !is_string($currency)
        || preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1
        || $product['currency'] !== $currency
        || !in_array($product['state'], ['draft', 'published', 'archived'], true)
        || !in_array($product['availability'], ['available', 'unavailable'], true)
        || !is_array($product['options'])
        || !array_is_list($product['options'])
        || !is_array($product['variants'])
        || !array_is_list($product['variants'])
    ) {
        return false;
    }
    if ($product['type'] === 'simple') {
        return is_string($product['sku'])
            && is_int($product['priceMinor'])
            && $product['priceMinor'] >= 0
            && $product['priceMinor'] <= 999999999
            && ($product['stock'] === null
                || (is_int($product['stock']) && $product['stock'] >= 0))
            && $product['options'] === []
            && $product['variants'] === [];
    }
    return $product['sku'] === null
        && $product['priceMinor'] === null
        && $product['stock'] === null
        && count($product['options']) >= 1
        && count($product['options']) <= 3
        && count($product['variants']) >= 1
        && count($product['variants']) <= 128;
}

function red_store_lite_cart_variant(array $variants, $variantId)
{
    foreach ($variants as $variant) {
        if (is_array($variant)
            && ($variant['id'] ?? null) === $variantId
        ) {
            return $variant;
        }
    }
    return null;
}

function red_store_lite_cart_option_labels(array $groups, array $selection)
{
    $labels = [];
    foreach ($groups as $group) {
        if (!is_array($group)
            || !is_string($group['key'] ?? null)
            || !is_string($group['label'] ?? null)
            || !is_array($group['values'] ?? null)
            || !array_key_exists($group['key'], $selection)
        ) {
            return null;
        }
        $valueId = $selection[$group['key']];
        $valueLabel = null;
        foreach ($group['values'] as $value) {
            if (is_array($value) && ($value['id'] ?? null) === $valueId) {
                $valueLabel = $value['label'] ?? null;
                break;
            }
        }
        if (!is_string($valueId) || !is_string($valueLabel)) {
            return null;
        }
        $labels[] = [
            'key' => $group['key'],
            'label' => $group['label'],
            'valueId' => $valueId,
            'valueLabel' => $valueLabel,
        ];
    }
    return $labels;
}

function red_store_lite_cart_resolve(
    array $product,
    $installationCurrency,
    array $intent
) {
    $keys = array_keys($intent);
    sort($keys, SORT_STRING);
    if (($keys !== ['product', 'quantity']
            && $keys !== ['product', 'quantity', 'variant'])
        || !red_store_lite_cart_identifier($intent['product'] ?? null)
        || !is_int($intent['quantity'] ?? null)
        || $intent['quantity'] < 1
        || $intent['quantity'] > 100
        || (array_key_exists('variant', $intent)
            && !red_store_lite_cart_identifier($intent['variant']))
    ) {
        return red_store_lite_cart_refusal('invalid_intent');
    }
    if (!red_store_lite_cart_product_shape($product, $installationCurrency)
        || $product['id'] !== $intent['product']
        || $product['state'] !== 'published'
        || $product['availability'] !== 'available'
    ) {
        return red_store_lite_cart_refusal('product_unavailable');
    }

    $variantId = null;
    $sku = $product['sku'];
    $price = $product['priceMinor'];
    $stock = $product['stock'];
    $labels = [];
    if ($product['type'] === 'simple') {
        if (array_key_exists('variant', $intent)) {
            return red_store_lite_cart_refusal('variant_unavailable');
        }
    } else {
        if (!array_key_exists('variant', $intent)) {
            return red_store_lite_cart_refusal('variant_required');
        }
        $variant = red_store_lite_cart_variant(
            $product['variants'],
            $intent['variant']
        );
        if (!is_array($variant)
            || array_keys($variant) !== [
                'id', 'sku', 'options', 'priceMinor', 'availability',
                'stock', 'imageRef',
            ]
            || !red_store_lite_cart_identifier($variant['id'])
            || !is_string($variant['sku'])
            || !is_array($variant['options'])
            || !is_int($variant['priceMinor'])
            || $variant['priceMinor'] < 0
            || $variant['priceMinor'] > 999999999
            || $variant['availability'] !== 'available'
            || ($variant['stock'] !== null
                && (!is_int($variant['stock']) || $variant['stock'] < 0))
        ) {
            return red_store_lite_cart_refusal('variant_unavailable');
        }
        $labels = red_store_lite_cart_option_labels(
            $product['options'],
            $variant['options']
        );
        if (!is_array($labels)) {
            return red_store_lite_cart_refusal('variant_unavailable');
        }
        $variantId = $variant['id'];
        $sku = $variant['sku'];
        $price = $variant['priceMinor'];
        $stock = $variant['stock'];
    }

    if (is_int($stock) && $intent['quantity'] > $stock) {
        return red_store_lite_cart_refusal('insufficient_stock');
    }
    if (!is_int($price)
        || $price > intdiv(PHP_INT_MAX, $intent['quantity'])
    ) {
        return red_store_lite_cart_refusal('product_unavailable');
    }
    $total = $price * $intent['quantity'];
    if ($total > 99999999900) {
        return red_store_lite_cart_refusal('product_unavailable');
    }
    $encoded = json_encode(
        $product,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if (!is_string($encoded)) {
        return red_store_lite_cart_refusal('product_unavailable');
    }
    return [
        'resolved' => true,
        'reason' => 'resolved',
        'line' => [
            'productId' => $product['id'],
            'variantId' => $variantId,
            'sku' => $sku,
            'title' => $product['title'],
            'optionLabels' => $labels,
            'quantity' => $intent['quantity'],
            'unitPriceMinor' => $price,
            'currency' => $product['currency'],
            'lineTotalMinor' => $total,
            'stockTracked' => is_int($stock),
            'stockAvailable' => is_int($stock) ? $stock : null,
            'productStateSha256' => hash('sha256', $encoded),
        ],
    ];
}

function red_store_lite_cart_simple_fixture()
{
    return [
        'id' => 'banana-pack',
        'type' => 'simple',
        'title' => 'Bananas, six-pack',
        'summary' => 'A simple product sold by pack.',
        'currency' => 'USD',
        'state' => 'published',
        'availability' => 'available',
        'imageRef' => 'media:banana-pack',
        'sku' => 'BANANA-6',
        'priceMinor' => 399,
        'stock' => 24,
        'options' => [],
        'variants' => [],
    ];
}

function red_store_lite_cart_variable_fixture()
{
    return [
        'id' => 'classic-tshirt',
        'type' => 'variable',
        'title' => 'Classic T-shirt',
        'summary' => 'A shirt with size and color choices.',
        'currency' => 'USD',
        'state' => 'published',
        'availability' => 'available',
        'imageRef' => 'media:classic-tshirt',
        'sku' => null,
        'priceMinor' => null,
        'stock' => null,
        'options' => [[
            'key' => 'size',
            'label' => 'Size',
            'values' => [
                ['id' => 's', 'label' => 'Small'],
                ['id' => 'm', 'label' => 'Medium'],
            ],
        ], [
            'key' => 'color',
            'label' => 'Color',
            'values' => [
                ['id' => 'red', 'label' => 'Red'],
                ['id' => 'blue', 'label' => 'Blue'],
            ],
        ]],
        'variants' => [[
            'id' => 'classic-tshirt-s-red',
            'sku' => 'TSHIRT-S-RED',
            'options' => ['size' => 's', 'color' => 'red'],
            'priceMinor' => 2499,
            'availability' => 'available',
            'stock' => 4,
            'imageRef' => null,
        ], [
            'id' => 'classic-tshirt-m-blue',
            'sku' => 'TSHIRT-M-BLUE',
            'options' => ['size' => 'm', 'color' => 'blue'],
            'priceMinor' => 2599,
            'availability' => 'available',
            'stock' => null,
            'imageRef' => null,
        ]],
    ];
}

try {
    red_store_lite_cart_assert(
        get_included_files() === [__FILE__],
        'fixture loads no package, core runtime, or external dependency'
    );

    $simple = red_store_lite_cart_resolve(
        red_store_lite_cart_simple_fixture(),
        'USD',
        ['product' => 'banana-pack', 'quantity' => 2]
    );
    red_store_lite_cart_assert(
        $simple['resolved']
            && $simple['line']['sku'] === 'BANANA-6'
            && $simple['line']['unitPriceMinor'] === 399
            && $simple['line']['currency'] === 'USD'
            && $simple['line']['lineTotalMinor'] === 798
            && $simple['line']['stockAvailable'] === 24,
        'simple line derives exact current server values'
    );
    red_store_lite_cart_assert(
        array_keys($simple['line']) === [
            'productId', 'variantId', 'sku', 'title', 'optionLabels',
            'quantity', 'unitPriceMinor', 'currency', 'lineTotalMinor',
            'stockTracked', 'stockAvailable', 'productStateSha256',
        ] && $simple['line']['variantId'] === null
            && $simple['line']['optionLabels'] === [],
        'simple result uses one closed line shape'
    );

    $variable = red_store_lite_cart_resolve(
        red_store_lite_cart_variable_fixture(),
        'USD',
        [
            'product' => 'classic-tshirt',
            'variant' => 'classic-tshirt-s-red',
            'quantity' => 3,
        ]
    );
    red_store_lite_cart_assert(
        $variable['resolved']
            && $variable['line']['sku'] === 'TSHIRT-S-RED'
            && $variable['line']['unitPriceMinor'] === 2499
            && $variable['line']['lineTotalMinor'] === 7497,
        'variable line resolves one exact current variant'
    );
    red_store_lite_cart_assert(
        $variable['line']['optionLabels'] === [[
            'key' => 'size',
            'label' => 'Size',
            'valueId' => 's',
            'valueLabel' => 'Small',
        ], [
            'key' => 'color',
            'label' => 'Color',
            'valueId' => 'red',
            'valueLabel' => 'Red',
        ]],
        'server derives labels in declared option order'
    );
    $untracked = red_store_lite_cart_resolve(
        red_store_lite_cart_variable_fixture(),
        'USD',
        [
            'product' => 'classic-tshirt',
            'variant' => 'classic-tshirt-m-blue',
            'quantity' => 100,
        ]
    );
    red_store_lite_cart_assert(
        $untracked['resolved']
            && !$untracked['line']['stockTracked']
            && $untracked['line']['stockAvailable'] === null,
        'untracked stock permits only the bounded quantity maximum'
    );

    $refusals = [];
    $draft = red_store_lite_cart_simple_fixture();
    $draft['state'] = 'draft';
    $refusals['draft'] = [$draft, ['product' => 'banana-pack', 'quantity' => 1], 'product_unavailable'];
    $closed = red_store_lite_cart_simple_fixture();
    $closed['availability'] = 'unavailable';
    $refusals['unavailable'] = [$closed, ['product' => 'banana-pack', 'quantity' => 1], 'product_unavailable'];
    $refusals['mismatch'] = [red_store_lite_cart_simple_fixture(), ['product' => 'other-product', 'quantity' => 1], 'product_unavailable'];
    $refusals['simple-variant'] = [red_store_lite_cart_simple_fixture(), ['product' => 'banana-pack', 'variant' => 'classic-tshirt-s-red', 'quantity' => 1], 'variant_unavailable'];
    $refusals['variant-required'] = [red_store_lite_cart_variable_fixture(), ['product' => 'classic-tshirt', 'quantity' => 1], 'variant_required'];
    $refusals['stale-variant'] = [red_store_lite_cart_variable_fixture(), ['product' => 'classic-tshirt', 'variant' => 'classic-tshirt-xl-green', 'quantity' => 1], 'variant_unavailable'];
    $refusals['simple-stock'] = [red_store_lite_cart_simple_fixture(), ['product' => 'banana-pack', 'quantity' => 25], 'insufficient_stock'];
    $refusals['variant-stock'] = [red_store_lite_cart_variable_fixture(), ['product' => 'classic-tshirt', 'variant' => 'classic-tshirt-s-red', 'quantity' => 5], 'insufficient_stock'];
    $currency = red_store_lite_cart_simple_fixture();
    $currency['currency'] = 'COP';
    $refusals['currency'] = [$currency, ['product' => 'banana-pack', 'quantity' => 1], 'product_unavailable'];
    $malformed = red_store_lite_cart_simple_fixture();
    $malformed['priceMinor'] = '399';
    $refusals['malformed'] = [$malformed, ['product' => 'banana-pack', 'quantity' => 1], 'product_unavailable'];
    foreach ($refusals as $name => $case) {
        $result = red_store_lite_cart_resolve($case[0], 'USD', $case[1]);
        red_store_lite_cart_assert(
            $result === red_store_lite_cart_refusal($case[2]),
            $name . ' refusal returns no partial line'
        );
    }

    $invalidIntents = [
        ['product' => 'banana-pack', 'quantity' => 0],
        ['product' => 'banana-pack', 'quantity' => 101],
        ['product' => 'banana-pack', 'quantity' => '2'],
        ['product' => 'banana-pack', 'quantity' => 1, 'price' => 399],
    ];
    foreach ($invalidIntents as $index => $intent) {
        red_store_lite_cart_assert(
            red_store_lite_cart_resolve(
                red_store_lite_cart_simple_fixture(),
                'USD',
                $intent
            ) === red_store_lite_cart_refusal('invalid_intent'),
            'invalid browser intent ' . ($index + 1) . ' fails closed'
        );
    }

    $edited = red_store_lite_cart_simple_fixture();
    $edited['priceMinor'] = 499;
    $editedLine = red_store_lite_cart_resolve(
        $edited,
        'USD',
        ['product' => 'banana-pack', 'quantity' => 2]
    );
    red_store_lite_cart_assert(
        $editedLine['resolved']
            && $editedLine['line']['unitPriceMinor'] === 499
            && $editedLine['line']['lineTotalMinor'] === 998
            && $editedLine['line']['productStateSha256']
                !== $simple['line']['productStateSha256'],
        'server-side product edits change derived money and state evidence'
    );

    echo 'Store Lite cart-line contract self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
