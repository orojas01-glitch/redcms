<?php
/**
 * Dependency-free checks for data-only component mutation presentations.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_component_render_helpers.php';

$assertions = 0;

function red_addon_component_mutation_presentation_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_component_mutation_presentation_test_options(
    int $count
): array {
    $options = [];
    for ($index = 1; $index <= $count; $index++) {
        $options[] = [
            'value' => sprintf('shirt-%03d', $index),
            'label' => 'Size ' . $index,
        ];
    }
    return $options;
}

function red_addon_component_mutation_presentation_test_form(
    int $optionCount = 2
): array {
    $options = red_addon_component_mutation_presentation_test_options(
        $optionCount
    );
    return [
        'route' => 'redcms.store-lite/cart-intent',
        'mutation' => 'redcms.store-lite/add-to-cart',
        'submitLabel' => 'Add to cart',
        'fields' => [[
            'key' => 'product',
            'control' => 'hidden',
            'value' => 'studio-shirt',
        ], [
            'key' => 'quantity',
            'control' => 'number',
            'label' => 'Quantity',
            'value' => 1,
        ], [
            'key' => 'variant',
            'control' => 'select',
            'label' => 'Size',
            'value' => $options[0]['value'],
            'options' => $options,
        ]],
    ];
}

function red_addon_component_mutation_presentation_test_row_forms(): array
{
    $handle = 'line-' . str_repeat('a', 64);
    return [[
        'route' => 'redcms.store-lite/cart-line-quantity',
        'mutation' => 'redcms.store-lite/set-cart-line-quantity',
        'submitLabel' => 'Update quantity',
        'fields' => [[
            'key' => 'line',
            'control' => 'hidden',
            'value' => $handle,
        ], [
            'key' => 'quantity',
            'control' => 'number',
            'label' => 'Quantity',
            'value' => 2,
        ]],
    ], [
        'route' => 'redcms.store-lite/cart-line-remove',
        'mutation' => 'redcms.store-lite/remove-cart-line',
        'submitLabel' => 'Remove item',
        'fields' => [[
            'key' => 'line',
            'control' => 'hidden',
            'value' => $handle,
        ]],
    ]];
}

try {
    $form = red_addon_component_mutation_presentation_test_form();
    $viewModel = [
        'title' => 'Studio shirt',
        'summary' => 'A variable product.',
        'facts' => [[
            'label' => 'Availability',
            'value' => 'In stock',
        ]],
        'mutationForm' => $form,
    ];
    $normalized = red_addon_public_component_view_model($viewModel);
    red_addon_component_mutation_presentation_test_assert(
        is_array($normalized)
            && ($normalized['mutationForm'] ?? null) === $form
            && count($normalized['mutationForm']['fields']) === 3,
        'the exact bounded variable-product presentation is retained'
    );

    $simple = $form;
    $simple['fields'] = array_slice($simple['fields'], 0, 2);
    red_addon_component_mutation_presentation_test_assert(
        red_addon_public_component_view_model([
            'title' => 'Bananas',
            'summary' => '',
            'mutationForm' => $simple,
        ])['mutationForm'] === $simple,
        'a simple product presentation does not require a variant field'
    );

    $collection = [
        'label' => 'Cart items',
        'items' => [[
            'title' => 'Studio shirt',
            'facts' => [[
                'label' => 'Quantity',
                'value' => '2',
            ], [
                'label' => 'Total',
                'value' => 'USD 48.00',
            ]],
        ]],
    ];
    red_addon_component_mutation_presentation_test_assert(
        red_addon_public_component_collection_presentation($collection)
            === $collection
            && red_addon_public_component_collection_presentation([
                'label' => 'Cart items', 'items' => [],
            ]) === null,
        'the bounded generic collection presentation retains rows and refuses empty lists'
    );

    $rowForms =
        red_addon_component_mutation_presentation_test_row_forms();
    $collectionWithForms = $collection;
    $collectionWithForms['items'][0]['mutationForms'] = $rowForms;
    red_addon_component_mutation_presentation_test_assert(
        red_addon_public_component_collection_presentation(
            $collectionWithForms
        ) === $collectionWithForms
            && red_addon_public_component_collection_mutation_forms(
                [$rowForms[0]]
            ) === [$rowForms[0]],
        'one collection row retains one or two closed mutation presentations'
    );

    $duplicateForms = $rowForms;
    $duplicateForms[1] = $duplicateForms[0];
    $malformedForms = $rowForms;
    $malformedForms[1]['csrfToken'] = str_repeat('b', 64);
    red_addon_component_mutation_presentation_test_assert(
        red_addon_public_component_collection_mutation_forms([]) === null
            && red_addon_public_component_collection_mutation_forms([
                $rowForms[0], $rowForms[1], $rowForms[0],
            ]) === null
            && red_addon_public_component_collection_mutation_forms([
                'quantity' => $rowForms[0],
            ]) === null
            && red_addon_public_component_collection_mutation_forms(
                $duplicateForms
            ) === null
            && red_addon_public_component_collection_mutation_forms(
                $malformedForms
            ) === null,
        'empty, overflowing, associative, duplicate, or authority-bearing row forms fail closed'
    );

    $expandedRow = $collectionWithForms;
    $expandedRow['items'][0]['controlHtml'] = '<button>Remove</button>';
    red_addon_component_mutation_presentation_test_assert(
        red_addon_public_component_collection_presentation($expandedRow)
            === null,
        'collection rows cannot add package HTML or unknown control fields'
    );

    $maximum = red_addon_component_mutation_presentation_test_form(128);
    $overflow = red_addon_component_mutation_presentation_test_form(129);
    red_addon_component_mutation_presentation_test_assert(
        red_addon_public_component_mutation_form_presentation($maximum)
            === $maximum
            && red_addon_public_component_mutation_form_presentation(
                $overflow
            ) === null,
        'the component boundary accepts 128 variants and refuses 129'
    );

    $commercial = $form;
    $commercial['price'] = '24.99';
    $reserved = $form;
    $reserved['fields'][0]['key'] = 'price';
    $authority = $form;
    $authority['csrfToken'] = str_repeat('a', 64);
    red_addon_component_mutation_presentation_test_assert(
        red_addon_public_component_mutation_form_presentation($commercial)
            === null
            && red_addon_public_component_mutation_form_presentation(
                $reserved
            ) === null
            && red_addon_public_component_mutation_form_presentation(
                $authority
            ) === null,
        'commercial state and raw browser authority fail the exact shape'
    );

    $duplicate = $form;
    $duplicate['fields'][1]['key'] = 'product';
    $unsafeLabel = $form;
    $unsafeLabel['fields'][1]['label'] = "Quantity\nfor cart";
    $missingSelection = $form;
    $missingSelection['fields'][2]['value'] = 'shirt-missing';
    red_addon_component_mutation_presentation_test_assert(
        red_addon_public_component_mutation_form_presentation($duplicate)
            === null
            && red_addon_public_component_mutation_form_presentation(
                $unsafeLabel
            ) === null
            && red_addon_public_component_mutation_form_presentation(
                $missingSelection
            ) === null,
        'duplicate fields, control characters, and forged selections fail closed'
    );

    $packageId = 'redcms.component-mutation-fixture';
    $componentId = $packageId . '/product';
    $manifest = [
        'id' => $packageId,
        'provides' => [
            'components' => [$componentId],
            'services' => [],
            'adminTools' => [],
            'adapters' => [],
        ],
    ];
    $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
    $fixtureViewModel = $viewModel;
    $registry->registerComponent(
        $componentId,
        static function () use (&$fixtureViewModel) {
            return $fixtureViewModel;
        }
    );
    red_addon_runtime_set_request_context(
        new RED_Addon_Runtime_Context(
            [$packageId],
            [$packageId => $registry]
        )
    );
    $context = [
        'component' => $componentId,
        'active' => true,
        'inputs' => [
            'recordId' => 42,
            'layout' => 'homepage',
            'article' => 'store',
            'position' => 1,
        ],
    ];
    $fixtureViewModel = [
        'title' => $viewModel['title'],
        'summary' => $viewModel['summary'],
        'facts' => $viewModel['facts'],
        'collection' => $collectionWithForms,
        'mutationForm' => $viewModel['mutationForm'],
    ];
    ob_start();
    $rendered = red_addon_public_component_render($context);
    $output = (string) ob_get_clean();
    red_addon_component_mutation_presentation_test_assert(
        $rendered === true
            && str_contains($output, '<h2>Studio shirt</h2>')
            && str_contains($output, '<dt>Availability</dt>')
            && str_contains($output, 'aria-label="Cart items"')
            && str_contains($output, '<h4>Studio shirt</h4>')
            && str_contains($output, '<dt>Quantity</dt><dd>2</dd>')
            && !str_contains($output, '<form')
            && !str_contains($output, 'Add to cart')
            && !str_contains($output, 'Update quantity')
            && !str_contains($output, 'Remove item')
            && !str_contains($output, 'line-' . str_repeat('a', 64))
            && !str_contains($output, 'cart-intent')
            && !str_contains($output, 'studio-shirt'),
        'the existing renderer emits collection facts but no retained form or authority'
    );

    $fixtureViewModel['mutationForm']['csrfToken'] = str_repeat('b', 64);
    ob_start();
    $invalidRendered = red_addon_public_component_render($context);
    $invalidOutput = (string) ob_get_clean();
    red_addon_component_mutation_presentation_test_assert(
        $invalidRendered === true
            && str_contains(
                $invalidOutput,
                'Content is temporarily unavailable.'
            )
            && !str_contains($invalidOutput, 'Studio shirt')
            && !str_contains($invalidOutput, str_repeat('b', 64)),
        'an invalid combined view model is replaced by the static fallback'
    );

    fwrite(
        STDOUT,
        'Add-on public component mutation presentation assertions passed: ' .
        $assertions . "\n"
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
