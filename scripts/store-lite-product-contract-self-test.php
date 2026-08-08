<?php
/**
 * Dependency-free Store Lite product/variant contract fixture.
 *
 * This is a package acceptance fixture, not a core commerce implementation.
 * It deliberately performs no database, filesystem, request, package, or
 * lifecycle work. The separately distributed Store Lite package will own the
 * equivalent persistence and runtime normalizer.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$assertions = 0;

function red_store_lite_product_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_store_lite_product_test_bounds()
{
    return [
        'maxIdentifierLength' => 64,
        'maxSkuLength' => 64,
        'maxTitleLength' => 160,
        'maxSummaryLength' => 1000,
        'maxOptionGroups' => 3,
        'maxOptionValues' => 16,
        'maxVariants' => 128,
        'maxPriceMinor' => 999999999,
        'maxStock' => 1000000000,
    ];
}

function red_store_lite_product_test_text($value, $minimum, $maximum)
{
    if (!is_string($value)
        || $value === ''
        || preg_match('//u', $value) !== 1
        || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
    ) {
        return false;
    }
    $length = function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
    return $length >= $minimum && $length <= $maximum;
}

function red_store_lite_product_test_identifier($value, $maximum)
{
    $maximum = (int) $maximum;
    return is_string($value)
        && strlen($value) <= $maximum
        && $maximum >= 1
        && preg_match(
            '/\A[a-z][a-z0-9._-]{0,' . ($maximum - 1) . '}\z/D',
            $value
        ) === 1;
}

function red_store_lite_product_test_sku($value, $maximum)
{
    $maximum = (int) $maximum;
    return is_string($value)
        && strlen($value) <= $maximum
        && $maximum >= 1
        && preg_match(
            '/\A[A-Z0-9][A-Z0-9._-]{0,' . ($maximum - 1) . '}\z/D',
            $value
        ) === 1;
}

function red_store_lite_product_test_integer($value, $minimum, $maximum)
{
    return is_int($value) && $value >= $minimum && $value <= $maximum;
}

function red_store_lite_product_test_optional_integer(
    array $input,
    $key,
    $minimum,
    $maximum,
    array &$errors
) {
    if (!array_key_exists($key, $input) || $input[$key] === null) {
        return null;
    }
    if (!red_store_lite_product_test_integer($input[$key], $minimum, $maximum)) {
        $errors[] = $key . '_invalid';
        return null;
    }
    return $input[$key];
}

function red_store_lite_product_test_image($value)
{
    return is_string($value)
        && preg_match('/\Amedia:[a-z0-9._-]{1,120}\z/D', $value) === 1;
}

function red_store_lite_product_test_allowed_keys(array $input, array $allowed)
{
    $unknown = array_values(array_diff(array_keys($input), $allowed));
    sort($unknown, SORT_STRING);
    return $unknown;
}

function red_store_lite_product_test_normalize(array $input, $installationCurrency)
{
    $bounds = red_store_lite_product_test_bounds();
    $errors = red_store_lite_product_test_allowed_keys(
        $input,
        [
            'id',
            'type',
            'title',
            'summary',
            'currency',
            'state',
            'availability',
            'imageRef',
            'sku',
            'priceMinor',
            'stock',
            'options',
            'variants',
        ]
    );
    $required = [
        'id',
        'type',
        'title',
        'currency',
        'state',
        'availability',
    ];
    foreach ($required as $key) {
        if (!array_key_exists($key, $input)) {
            $errors[] = $key . '_missing';
        }
    }
    if ($errors !== []) {
        sort($errors, SORT_STRING);
        return ['valid' => false, 'product' => null, 'errors' => $errors];
    }

    if (!red_store_lite_product_test_identifier(
        $input['id'],
        $bounds['maxIdentifierLength']
    )) {
        $errors[] = 'id_invalid';
    }
    if (!in_array($input['type'], ['simple', 'variable'], true)) {
        $errors[] = 'type_invalid';
    }
    if (!red_store_lite_product_test_text(
        $input['title'],
        1,
        $bounds['maxTitleLength']
    )) {
        $errors[] = 'title_invalid';
    }
    if (array_key_exists('summary', $input)
        && $input['summary'] !== null
        && !red_store_lite_product_test_text(
            $input['summary'],
            1,
            $bounds['maxSummaryLength']
        )
    ) {
        $errors[] = 'summary_invalid';
    }
    if (!is_string($installationCurrency)
        || preg_match('/\A[A-Z]{3}\z/D', $installationCurrency) !== 1
        || $input['currency'] !== $installationCurrency
    ) {
        $errors[] = 'currency_invalid';
    }
    if (!in_array($input['state'], ['draft', 'published', 'archived'], true)) {
        $errors[] = 'state_invalid';
    }
    if (!in_array($input['availability'], ['available', 'unavailable'], true)) {
        $errors[] = 'availability_invalid';
    }
    if (array_key_exists('imageRef', $input)
        && $input['imageRef'] !== null
        && !red_store_lite_product_test_image($input['imageRef'])
    ) {
        $errors[] = 'image_ref_invalid';
    }

    $type = $input['type'];
    $normalized = [
        'id' => $input['id'],
        'type' => $type,
        'title' => $input['title'],
        'summary' => array_key_exists('summary', $input)
            ? $input['summary']
            : null,
        'currency' => $installationCurrency,
        'state' => $input['state'],
        'availability' => $input['availability'],
        'imageRef' => array_key_exists('imageRef', $input)
            ? $input['imageRef']
            : null,
        'sku' => null,
        'priceMinor' => null,
        'stock' => null,
        'options' => [],
        'variants' => [],
    ];

    if ($type === 'simple') {
        if (!array_key_exists('sku', $input)
            || !red_store_lite_product_test_sku(
                $input['sku'],
                $bounds['maxSkuLength']
            )
        ) {
            $errors[] = 'sku_invalid';
        } else {
            $normalized['sku'] = $input['sku'];
        }
        if (!array_key_exists('priceMinor', $input)
            || !red_store_lite_product_test_integer(
                $input['priceMinor'],
                0,
                $bounds['maxPriceMinor']
            )
        ) {
            $errors[] = 'price_minor_invalid';
        } else {
            $normalized['priceMinor'] = $input['priceMinor'];
        }
        $normalized['stock'] = red_store_lite_product_test_optional_integer(
            $input,
            'stock',
            0,
            $bounds['maxStock'],
            $errors
        );
        if (array_key_exists('options', $input)
            && $input['options'] !== []
        ) {
            $errors[] = 'simple_options_forbidden';
        }
        if (array_key_exists('variants', $input)
            && $input['variants'] !== []
        ) {
            $errors[] = 'simple_variants_forbidden';
        }
    } elseif ($type === 'variable') {
        foreach (['sku', 'priceMinor', 'stock'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null) {
                $errors[] = 'variable_' . $key . '_forbidden';
            }
        }
        $groups = $input['options'] ?? null;
        if (!is_array($groups)
            || count($groups) < 1
            || count($groups) > $bounds['maxOptionGroups']
        ) {
            $errors[] = 'option_group_count_invalid';
            $groups = [];
        }
        $groupValues = [];
        foreach ($groups as $groupIndex => $group) {
            if (!is_array($group)) {
                $errors[] = 'option_group_invalid';
                continue;
            }
            $groupErrors = red_store_lite_product_test_allowed_keys(
                $group,
                ['key', 'label', 'values']
            );
            if ($groupErrors !== []) {
                $errors = array_merge($errors, $groupErrors);
            }
            if (!red_store_lite_product_test_identifier(
                $group['key'] ?? null,
                32
            )) {
                $errors[] = 'option_group_key_invalid';
                continue;
            }
            if (isset($groupValues[$group['key']])) {
                $errors[] = 'option_group_key_duplicate';
                continue;
            }
            if (!red_store_lite_product_test_text(
                $group['label'] ?? null,
                1,
                80
            )) {
                $errors[] = 'option_group_label_invalid';
            }
            $values = $group['values'] ?? null;
            if (!is_array($values)
                || count($values) < 1
                || count($values) > $bounds['maxOptionValues']
            ) {
                $errors[] = 'option_value_count_invalid';
                continue;
            }
            $seenValues = [];
            $normalizedValues = [];
            foreach ($values as $value) {
                if (!is_array($value)
                    || red_store_lite_product_test_allowed_keys(
                        $value,
                        ['id', 'label']
                    ) !== []
                    || !red_store_lite_product_test_identifier(
                        $value['id'] ?? null,
                        32
                    )
                    || !red_store_lite_product_test_text(
                        $value['label'] ?? null,
                        1,
                        80
                    )
                ) {
                    $errors[] = 'option_value_invalid';
                    continue;
                }
                if (isset($seenValues[$value['id']])) {
                    $errors[] = 'option_value_duplicate';
                    continue;
                }
                $seenValues[$value['id']] = true;
                $normalizedValues[] = [
                    'id' => $value['id'],
                    'label' => $value['label'],
                ];
            }
            $groupValues[$group['key']] = $seenValues;
            $normalized['options'][] = [
                'key' => $group['key'],
                'label' => $group['label'] ?? null,
                'values' => $normalizedValues,
            ];
        }

        $variants = $input['variants'] ?? null;
        if (!is_array($variants)
            || count($variants) < 1
            || count($variants) > $bounds['maxVariants']
        ) {
            $errors[] = 'variant_count_invalid';
            $variants = [];
        }
        $seenVariantIds = [];
        $seenSkus = [];
        $seenTuples = [];
        foreach ($variants as $variant) {
            if (!is_array($variant)
                || red_store_lite_product_test_allowed_keys(
                    $variant,
                    [
                        'id',
                        'sku',
                        'options',
                        'priceMinor',
                        'availability',
                        'stock',
                        'imageRef',
                    ]
                ) !== []
            ) {
                $errors[] = 'variant_invalid';
                continue;
            }
            $variantId = $variant['id'] ?? null;
            $sku = $variant['sku'] ?? null;
            if (!red_store_lite_product_test_identifier(
                $variantId,
                $bounds['maxIdentifierLength']
            )) {
                $errors[] = 'variant_id_invalid';
            } elseif (isset($seenVariantIds[$variantId])) {
                $errors[] = 'variant_id_duplicate';
            } else {
                $seenVariantIds[$variantId] = true;
            }
            if (!red_store_lite_product_test_sku($sku, $bounds['maxSkuLength'])) {
                $errors[] = 'variant_sku_invalid';
            } elseif (isset($seenSkus[$sku])) {
                $errors[] = 'variant_sku_duplicate';
            } else {
                $seenSkus[$sku] = true;
            }
            $selected = $variant['options'] ?? null;
            $selectedKeys = is_array($selected)
                ? array_keys($selected)
                : [];
            $expectedKeys = array_keys($groupValues);
            sort($selectedKeys, SORT_STRING);
            sort($expectedKeys, SORT_STRING);
            if (!is_array($selected) || $selectedKeys !== $expectedKeys) {
                $errors[] = 'variant_options_invalid';
                $selected = [];
            }
            $tuple = [];
            $normalizedSelected = [];
            foreach ($groupValues as $key => $values) {
                $valueId = $selected[$key] ?? null;
                if (!is_string($valueId) || !isset($values[$valueId])) {
                    $errors[] = 'variant_option_value_invalid';
                    continue;
                }
                $tuple[$key] = $valueId;
                $normalizedSelected[$key] = $valueId;
            }
            ksort($tuple, SORT_STRING);
            $tupleKey = json_encode($tuple, JSON_UNESCAPED_SLASHES);
            if (is_string($tupleKey) && isset($seenTuples[$tupleKey])) {
                $errors[] = 'variant_option_tuple_duplicate';
            } elseif (is_string($tupleKey)) {
                $seenTuples[$tupleKey] = true;
            }
            if (!red_store_lite_product_test_integer(
                $variant['priceMinor'] ?? null,
                0,
                $bounds['maxPriceMinor']
            )) {
                $errors[] = 'variant_price_minor_invalid';
            }
            if (!in_array(
                $variant['availability'] ?? null,
                ['available', 'unavailable'],
                true
            )) {
                $errors[] = 'variant_availability_invalid';
            }
            $variantStock = red_store_lite_product_test_optional_integer(
                $variant,
                'stock',
                0,
                $bounds['maxStock'],
                $errors
            );
            if (array_key_exists('imageRef', $variant)
                && $variant['imageRef'] !== null
                && !red_store_lite_product_test_image($variant['imageRef'])
            ) {
                $errors[] = 'variant_image_ref_invalid';
            }
            $normalized['variants'][] = [
                'id' => $variantId,
                'sku' => $sku,
                'options' => $normalizedSelected,
                'priceMinor' => $variant['priceMinor'] ?? null,
                'availability' => $variant['availability'] ?? null,
                'stock' => $variantStock,
                'imageRef' => array_key_exists('imageRef', $variant)
                    ? $variant['imageRef']
                    : null,
            ];
        }
    }

    if ($errors !== []) {
        sort($errors, SORT_STRING);
        return ['valid' => false, 'product' => null, 'errors' => $errors];
    }
    return ['valid' => true, 'product' => $normalized, 'errors' => []];
}

function red_store_lite_product_test_simple_fixture()
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

function red_store_lite_product_test_variable_fixture()
{
    return [
        'id' => 'classic-tshirt',
        'type' => 'variable',
        'title' => 'Classic T-shirt',
        'summary' => 'A shirt with bounded size and color choices.',
        'currency' => 'USD',
        'state' => 'published',
        'availability' => 'available',
        'imageRef' => 'media:classic-tshirt',
        'options' => [
            [
                'key' => 'size',
                'label' => 'Size',
                'values' => [
                    ['id' => 's', 'label' => 'Small'],
                    ['id' => 'm', 'label' => 'Medium'],
                ],
            ],
            [
                'key' => 'color',
                'label' => 'Color',
                'values' => [
                    ['id' => 'red', 'label' => 'Red'],
                    ['id' => 'blue', 'label' => 'Blue'],
                ],
            ],
        ],
        'variants' => [
            [
                'id' => 'classic-tshirt-s-red',
                'sku' => 'TSHIRT-S-RED',
                'options' => ['size' => 's', 'color' => 'red'],
                'priceMinor' => 2499,
                'availability' => 'available',
                'stock' => 4,
                'imageRef' => 'media:classic-tshirt-red',
            ],
            [
                'id' => 'classic-tshirt-s-blue',
                'sku' => 'TSHIRT-S-BLUE',
                'options' => ['size' => 's', 'color' => 'blue'],
                'priceMinor' => 2499,
                'availability' => 'available',
                'stock' => 3,
            ],
            [
                'id' => 'classic-tshirt-m-red',
                'sku' => 'TSHIRT-M-RED',
                'options' => ['size' => 'm', 'color' => 'red'],
                'priceMinor' => 2599,
                'availability' => 'available',
                'stock' => 7,
            ],
            [
                'id' => 'classic-tshirt-m-blue',
                'sku' => 'TSHIRT-M-BLUE',
                'options' => ['size' => 'm', 'color' => 'blue'],
                'priceMinor' => 2599,
                'availability' => 'available',
                'stock' => 6,
            ],
        ],
    ];
}

try {
    $bounds = red_store_lite_product_test_bounds();
    red_store_lite_product_test_assert(
        $bounds === [
            'maxIdentifierLength' => 64,
            'maxSkuLength' => 64,
            'maxTitleLength' => 160,
            'maxSummaryLength' => 1000,
            'maxOptionGroups' => 3,
            'maxOptionValues' => 16,
            'maxVariants' => 128,
            'maxPriceMinor' => 999999999,
            'maxStock' => 1000000000,
        ],
        'the package contract exposes fixed bounded identifiers, text, money, stock, options, and variants'
    );

    $simple = red_store_lite_product_test_normalize(
        red_store_lite_product_test_simple_fixture(),
        'USD'
    );
    red_store_lite_product_test_assert(
        $simple['valid']
            && $simple['errors'] === []
            && $simple['product']['type'] === 'simple'
            && $simple['product']['sku'] === 'BANANA-6'
            && $simple['product']['priceMinor'] === 399
            && $simple['product']['options'] === []
            && $simple['product']['variants'] === [],
        'simple products normalize one sellable SKU and price without variant records'
    );

    $variable = red_store_lite_product_test_normalize(
        red_store_lite_product_test_variable_fixture(),
        'USD'
    );
    red_store_lite_product_test_assert(
        $variable['valid']
            && $variable['errors'] === []
            && $variable['product']['type'] === 'variable'
            && count($variable['product']['options']) === 2
            && count($variable['product']['variants']) === 4
            && $variable['product']['variants'][0]['options'] === [
                'size' => 's',
                'color' => 'red',
            ],
        'variable products normalize explicit size/color choices and sellable variants'
    );
    red_store_lite_product_test_assert(
        $variable['product']['sku'] === null
            && $variable['product']['priceMinor'] === null
            && $variable['product']['stock'] === null,
        'variable parent records do not duplicate variant SKU, price, or stock'
    );
    $reorderedVariant = red_store_lite_product_test_variable_fixture();
    $reorderedVariant['variants'][0]['options'] = [
        'color' => 'red',
        'size' => 's',
    ];
    $reorderedResult = red_store_lite_product_test_normalize(
        $reorderedVariant,
        'USD'
    );
    red_store_lite_product_test_assert(
        $reorderedResult['valid']
            && array_keys($reorderedResult['product']['variants'][0]['options'])
                === ['size', 'color'],
        'variant option object ordering is canonicalized without changing the selected tuple'
    );

    $invalidCases = [];
    $invalid = red_store_lite_product_test_simple_fixture();
    $invalid['currency'] = 'COP';
    $invalidCases['currency'] = $invalid;
    $invalid = red_store_lite_product_test_simple_fixture();
    $invalid['priceMinor'] = 3.99;
    $invalidCases['float-price'] = $invalid;
    $invalid = red_store_lite_product_test_simple_fixture();
    $invalid['variants'] = [['id' => 'not-allowed']];
    $invalidCases['simple-variants'] = $invalid;
    $invalid = red_store_lite_product_test_variable_fixture();
    $invalid['options'][] = [
        'key' => 'material',
        'label' => 'Material',
        'values' => [['id' => 'cotton', 'label' => 'Cotton']],
    ];
    $invalidCases['fourth-option-group'] = $invalid;
    $invalid = red_store_lite_product_test_variable_fixture();
    $invalid['options'][0]['values'] = [];
    $invalidCases['empty-option-values'] = $invalid;
    $invalid = red_store_lite_product_test_variable_fixture();
    $invalid['variants'][1]['options']['size'] = 'xl';
    $invalidCases['unknown-option-value'] = $invalid;
    $invalid = red_store_lite_product_test_variable_fixture();
    $invalid['variants'][1]['options'] = ['size' => 's'];
    $invalidCases['missing-option-group'] = $invalid;
    $invalid = red_store_lite_product_test_variable_fixture();
    $invalid['variants'][1]['id'] = $invalid['variants'][0]['id'];
    $invalidCases['duplicate-variant-id'] = $invalid;
    $invalid = red_store_lite_product_test_variable_fixture();
    $invalid['variants'][1]['sku'] = $invalid['variants'][0]['sku'];
    $invalidCases['duplicate-sku'] = $invalid;
    $invalid = red_store_lite_product_test_variable_fixture();
    $invalid['variants'][1]['options'] = $invalid['variants'][0]['options'];
    $invalidCases['duplicate-option-tuple'] = $invalid;
    $invalid = red_store_lite_product_test_variable_fixture();
    $invalid['unknown'] = true;
    $invalidCases['unknown-field'] = $invalid;

    foreach ($invalidCases as $name => $invalidProduct) {
        $result = red_store_lite_product_test_normalize($invalidProduct, 'USD');
        red_store_lite_product_test_assert(
            !$result['valid']
                && $result['product'] === null
                && $result['errors'] !== [],
            'invalid ' . $name . ' product data fails closed without a partial normalized record'
        );
    }

    $tooManyGroups = red_store_lite_product_test_variable_fixture();
    for ($index = 3; $index < 5; $index++) {
        $tooManyGroups['options'][] = [
            'key' => 'extra' . $index,
            'label' => 'Extra',
            'values' => [['id' => 'one', 'label' => 'One']],
        ];
    }
    $tooManyGroupsResult = red_store_lite_product_test_normalize(
        $tooManyGroups,
        'USD'
    );
    red_store_lite_product_test_assert(
        in_array('option_group_count_invalid', $tooManyGroupsResult['errors'], true),
        'option groups are bounded at three'
    );

    $tooManyValues = red_store_lite_product_test_variable_fixture();
    $tooManyValues['options'][0]['values'] = [];
    for ($index = 0; $index < 17; $index++) {
        $tooManyValues['options'][0]['values'][] = [
            'id' => 'value' . $index,
            'label' => 'Value ' . $index,
        ];
    }
    $tooManyValuesResult = red_store_lite_product_test_normalize(
        $tooManyValues,
        'USD'
    );
    red_store_lite_product_test_assert(
        in_array('option_value_count_invalid', $tooManyValuesResult['errors'], true),
        'option values are bounded at sixteen per group'
    );

    $tooManyVariants = red_store_lite_product_test_variable_fixture();
    for ($index = 4; $index < 129; $index++) {
        $tooManyVariants['variants'][] = [
            'id' => 'variant-' . $index,
            'sku' => 'TSHIRT-' . $index,
            'options' => ['size' => 's', 'color' => 'red'],
            'priceMinor' => 2499,
            'availability' => 'available',
            'stock' => 1,
        ];
    }
    $tooManyVariantsResult = red_store_lite_product_test_normalize(
        $tooManyVariants,
        'USD'
    );
    red_store_lite_product_test_assert(
        in_array('variant_count_invalid', $tooManyVariantsResult['errors'], true),
        'explicit variants are bounded at 128 per product parent'
    );

    red_store_lite_product_test_assert(
        count(get_included_files()) === 1,
        'the Store Lite product fixture has no database, request, package, or runtime dependency'
    );

    fwrite(
        STDOUT,
        'Store Lite product contract self-test passed (' . $assertions
            . " assertions).\n"
    );
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
