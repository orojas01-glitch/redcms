<?php
/**
 * Dependency-free checks for administrator-form draft initial values.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_admin_tool_form_initial_value_helpers.php';

$assertions = 0;
$toolId = 'redcms.initial-fixture/products';
$formId = 'redcms.initial-fixture/product-editor';

function red_addon_initial_value_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_initial_value_contract()
{
    global $toolId, $formId;
    return [
        'tool' => $toolId,
        'form' => $formId,
        'label' => 'Edit product',
        'description' => 'Edit one bounded product.',
        'permission' => 'fixture.products.manage',
        'method' => 'POST',
        'csrf' => 'required',
        'encoding' => 'application/json',
        'maxBodyBytes' => 32768,
        'fields' => [
            [
                'key' => 'id',
                'label' => 'Identifier',
                'type' => 'text',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 64,
            ],
            [
                'key' => 'title',
                'label' => 'Title',
                'type' => 'text',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 200,
            ],
            [
                'key' => 'type',
                'label' => 'Product type',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'simple', 'label' => 'Simple item'],
                    ['value' => 'variable', 'label' => 'Variable item'],
                ],
            ],
            [
                'key' => 'price-minor',
                'label' => 'Price',
                'type' => 'integer',
                'required' => false,
                'minimum' => 0,
                'maximum' => 999999999,
            ],
            [
                'key' => 'options',
                'label' => 'Options',
                'type' => 'collection',
                'required' => false,
                'itemLabel' => 'Option',
                'minItems' => 0,
                'maxItems' => 3,
                'fields' => [[
                    'key' => 'label',
                    'label' => 'Label',
                    'type' => 'text',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 80,
                ]],
            ],
        ],
        'create' => [
            'label' => 'Add product',
            'description' => 'Create one bounded product.',
        ],
    ];
}

try {
    $contract = red_addon_initial_value_contract();
    $values = [
        'id' => '',
        'title' => '',
        'type' => 'simple',
        'price-minor' => null,
        'options' => [],
    ];
    $draft = RED_Addon_Admin_Tool_Form_Initial_Values::draft($values);
    $validated = red_addon_admin_tool_form_validate_initial_values(
        $contract,
        $draft->values()
    );
    red_addon_initial_value_assert(
        ($validated['valid'] ?? false) === true
            && ($validated['values'] ?? null) === $values,
        'draft values preserve the complete closed graph while required text may begin empty'
    );
    red_addon_initial_value_assert(
        (red_addon_admin_tool_form_validate_values(
            $contract,
            $values
        )['valid'] ?? true) === false,
        'the ordinary edit and submission validator remains strict'
    );

    $settings = new RED_Addon_Admin_Tool_Form_Runtime_Settings(
        ['store.currency' => 'USD'],
        hash('sha256', 'initial-runtime-settings')
    );
    $request = new RED_Addon_Admin_Tool_Form_Initial_Value_Request(
        $toolId,
        $formId,
        $settings
    );
    red_addon_initial_value_assert(
        $request->tool() === $toolId
            && $request->form() === $formId
            && $request->runtimeSettings() === $settings
            && $request->runtimeSettings()->value('store.currency') === 'USD',
        'the initial-value request exposes only exact form identity and immutable runtime settings'
    );

    $invalidRequest = false;
    try {
        new RED_Addon_Admin_Tool_Form_Initial_Value_Request(
            '../products',
            $formId
        );
    } catch (InvalidArgumentException $exception) {
        $invalidRequest = true;
    }
    red_addon_initial_value_assert(
        $invalidRequest,
        'invalid form identity is rejected before provider invocation'
    );

    $missing = $values;
    unset($missing['title']);
    $extra = $values;
    $extra['callback'] = 'dangerous';
    $invalidType = $values;
    $invalidType['price-minor'] = '0';
    $invalidSelect = $values;
    $invalidSelect['type'] = 'subscription';
    red_addon_initial_value_assert(
        (red_addon_admin_tool_form_validate_initial_values(
            $contract,
            $missing
        )['valid'] ?? true) === false
            && (red_addon_admin_tool_form_validate_initial_values(
                $contract,
                $extra
            )['valid'] ?? true) === false
            && (red_addon_admin_tool_form_validate_initial_values(
                $contract,
                $invalidType
            )['valid'] ?? true) === false
            && (red_addon_admin_tool_form_validate_initial_values(
                $contract,
                $invalidSelect
            )['valid'] ?? true) === false,
        'missing, extra, mistyped, and invalid-choice draft values fail closed'
    );

    $withoutCreate = $contract;
    unset($withoutCreate['create']);
    red_addon_initial_value_assert(
        (red_addon_admin_tool_form_validate_initial_values(
            $withoutCreate,
            $values
        )['reason'] ?? '') === 'create_unavailable',
        'a form without the create declaration cannot validate draft values'
    );

    $stateSha256 = red_addon_admin_tool_form_initial_value_state_hash(
        'redcms.initial-fixture',
        $toolId,
        $formId,
        hash('sha256', 'contract'),
        $settings->stateSha256(),
        $validated['values']
    );
    red_addon_initial_value_assert(
        red_addon_valid_sha256($stateSha256)
            && hash_equals(
                $stateSha256,
                red_addon_admin_tool_form_initial_value_state_hash(
                    'redcms.initial-fixture',
                    $toolId,
                    $formId,
                    hash('sha256', 'contract'),
                    $settings->stateSha256(),
                    $validated['values']
                )
            )
            && !hash_equals(
                $stateSha256,
                red_addon_admin_tool_form_initial_value_state_hash(
                    'redcms.initial-fixture',
                    $toolId,
                    $formId,
                    hash('sha256', 'contract-drift'),
                    $settings->stateSha256(),
                    $validated['values']
                )
            ),
        'draft-state evidence is deterministic and contract-bound without a synthetic record id'
    );

    printf(
        "Add-on administrator form initial-value self-test passed (%d assertions).\n",
        $assertions
    );
} catch (Throwable $throwable) {
    fwrite(
        STDERR,
        $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n"
    );
    exit(1);
}
