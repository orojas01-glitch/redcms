<?php
/**
 * Dependency-free checks for bounded generic rich public-mutation fields.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_component_render_helpers.php';
require_once $projectRoot . '/includes/addon_public_mutation_execution_helpers.php';

$assertions = 0;

function red_addon_rich_field_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_rich_field_test_declarations(): array
{
    return [[
        'key' => 'confirmation-method',
        'type' => 'identifier',
        'required' => true,
        'minLength' => 1,
        'maxLength' => 32,
    ], [
        'key' => 'contact-email',
        'type' => 'string',
        'format' => 'email',
        'required' => true,
        'minLength' => 3,
        'maxLength' => 254,
    ], [
        'key' => 'contact-name',
        'type' => 'string',
        'format' => 'plain-text',
        'required' => true,
        'minLength' => 1,
        'maxLength' => 120,
    ], [
        'key' => 'contact-phone',
        'type' => 'string',
        'format' => 'telephone',
        'required' => false,
        'minLength' => 7,
        'maxLength' => 32,
    ], [
        'key' => 'location-city',
        'type' => 'string',
        'format' => 'plain-text',
        'required' => false,
        'minLength' => 1,
        'maxLength' => 160,
    ], [
        'key' => 'location-country-code',
        'type' => 'string',
        'format' => 'iso-3166-1-alpha-2-uppercase',
        'required' => false,
        'minLength' => 2,
        'maxLength' => 2,
    ], [
        'key' => 'location-instructions',
        'type' => 'string',
        'format' => 'plain-text',
        'required' => false,
        'minLength' => 1,
        'maxLength' => 500,
    ], [
        'key' => 'location-line1',
        'type' => 'string',
        'format' => 'plain-text',
        'required' => false,
        'minLength' => 1,
        'maxLength' => 160,
    ], [
        'key' => 'location-line2',
        'type' => 'string',
        'format' => 'plain-text',
        'required' => false,
        'minLength' => 1,
        'maxLength' => 160,
    ], [
        'key' => 'location-postal-code',
        'type' => 'string',
        'format' => 'plain-text',
        'required' => false,
        'minLength' => 1,
        'maxLength' => 32,
    ], [
        'key' => 'location-region',
        'type' => 'string',
        'format' => 'plain-text',
        'required' => false,
        'minLength' => 1,
        'maxLength' => 160,
    ], [
        'key' => 'response-method',
        'type' => 'identifier',
        'required' => true,
        'minLength' => 1,
        'maxLength' => 32,
    ]];
}

function red_addon_rich_field_test_manifest(): array
{
    return [
        'id' => 'redcms.rich-form-fixture',
        'routes' => [[
            'id' => 'redcms.rich-form-fixture/submit',
            'scope' => 'public',
            'path' => '/addons/redcms/rich-form-fixture/submit',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.rich-form-fixture/submit',
            'mutation' => 'redcms.rich-form-fixture/record-response',
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 4096,
            'requestFields' => red_addon_rich_field_test_declarations(),
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Rich_Form_Fixture_Responses'],
            'postcondition' => 'server-derived-state',
            'audit' => 'forms.response.recorded',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_rich_field_test_condition(): array
{
    return ['field' => 'response-method', 'equals' => 'onsite'];
}

function red_addon_rich_field_test_presented_fields(): array
{
    $condition = red_addon_rich_field_test_condition();
    return [[
        'key' => 'contact-name',
        'control' => 'text',
        'label' => 'Name',
        'required' => true,
        'maxLength' => 120,
    ], [
        'key' => 'contact-email',
        'control' => 'email',
        'label' => 'Email',
        'required' => true,
        'minLength' => 3,
        'maxLength' => 254,
    ], [
        'key' => 'contact-phone',
        'control' => 'tel',
        'label' => 'Phone',
        'required' => false,
        'requiredWhen' => $condition,
        'minLength' => 7,
        'maxLength' => 32,
    ], [
        'key' => 'response-method',
        'control' => 'select',
        'label' => 'Response method',
        'value' => 'remote',
        'options' => [[
            'value' => 'remote',
            'label' => 'Remote',
        ], [
            'value' => 'onsite',
            'label' => 'On site',
        ]],
    ], [
        'key' => 'location-line1',
        'control' => 'text',
        'label' => 'Address',
        'required' => false,
        'requiredWhen' => $condition,
        'maxLength' => 160,
    ], [
        'key' => 'location-line2',
        'control' => 'text',
        'label' => 'Address line 2',
        'required' => false,
        'visibleWhen' => $condition,
        'maxLength' => 160,
    ], [
        'key' => 'location-city',
        'control' => 'text',
        'label' => 'City',
        'required' => false,
        'requiredWhen' => $condition,
        'maxLength' => 160,
    ], [
        'key' => 'location-region',
        'control' => 'text',
        'label' => 'Region',
        'required' => false,
        'requiredWhen' => $condition,
        'maxLength' => 160,
    ], [
        'key' => 'location-postal-code',
        'control' => 'text',
        'label' => 'Postal code',
        'required' => false,
        'visibleWhen' => $condition,
        'maxLength' => 32,
    ], [
        'key' => 'location-country-code',
        'control' => 'text',
        'label' => 'Country code',
        'required' => false,
        'requiredWhen' => $condition,
        'minLength' => 2,
        'maxLength' => 2,
        'format' => 'iso-3166-1-alpha-2-uppercase',
    ], [
        'key' => 'location-instructions',
        'control' => 'textarea',
        'label' => 'Instructions',
        'required' => false,
        'visibleWhen' => $condition,
        'maxLength' => 500,
    ], [
        'key' => 'confirmation-method',
        'control' => 'select',
        'label' => 'Confirmation',
        'value' => 'email',
        'options' => [[
            'value' => 'email',
            'label' => 'Email',
        ], [
            'value' => 'phone',
            'label' => 'Phone',
        ]],
    ]];
}

function red_addon_rich_field_test_evidence(): array
{
    return [[
        'valid' => true,
        'issued' => true,
        'subjectRecordId' => 42,
        'scopeSha256' => str_repeat('a', 64),
        'token' => str_repeat('b', 64),
        'maxAgeSeconds' => 600,
        'reason' => 'csrf_issued',
    ], [
        'valid' => true,
        'issued' => true,
        'idempotencyRecordId' => 84,
        'subjectRecordId' => 42,
        'scopeSha256' => str_repeat('c', 64),
        'key' => str_repeat('d', 64),
        'maxAgeSeconds' => 600,
        'reason' => 'idempotency_issued',
    ]];
}

function red_addon_rich_field_test_body(array $fields): string
{
    $pairs = [];
    foreach ($fields as $key => $value) {
        $pairs[] = $key . '=' . red_addon_public_mutation_form_value_encode(
            $value
        );
    }
    return implode('&', $pairs);
}

try {
    $declarationResult = ['errors' => []];
    $declared = red_addon_validate_public_mutation_request_fields(
        red_addon_rich_field_test_declarations(),
        'Rich form fixture',
        $declarationResult
    );
    red_addon_rich_field_test_assert(
        $declarationResult['errors'] === []
            && count($declared) === 12
            && array_column($declared, 'key')
                === array_column(
                    red_addon_rich_field_test_declarations(),
                    'key'
                ),
        'twelve sorted identifier and formatted-string fields validate'
    );

    $overflow = red_addon_rich_field_test_declarations();
    for ($index = 1; $index <= 5; $index++) {
        $overflow[] = [
            'key' => 'overflow-' . $index,
            'type' => 'identifier',
            'required' => false,
            'minLength' => 1,
            'maxLength' => 8,
        ];
    }
    $overflowResult = ['errors' => []];
    red_addon_validate_public_mutation_request_fields(
        $overflow,
        'Overflow fixture',
        $overflowResult
    );
    red_addon_rich_field_test_assert(
        in_array(
            'Overflow fixture requestFields must contain one to sixteen fields.',
            $overflowResult['errors'],
            true
        ),
        'a seventeenth field is refused by the manifest boundary'
    );

    $presentation = [
        'route' => 'redcms.rich-form-fixture/submit',
        'mutation' => 'redcms.rich-form-fixture/record-response',
        'submitLabel' => 'Submit response',
        'fields' => red_addon_rich_field_test_presented_fields(),
    ];
    $normalizedPresentation =
        red_addon_public_component_mutation_form_presentation($presentation);
    red_addon_rich_field_test_assert(
        is_array($normalizedPresentation)
            && count($normalizedPresentation['fields']) === 12
            && $normalizedPresentation['fields'][0]['format']
                === 'plain-text'
            && $normalizedPresentation['fields'][2]['requiredWhen']
                === red_addon_rich_field_test_condition(),
        'the component boundary strips rich fields to one closed data model'
    );

    [$csrf, $idempotency] = red_addon_rich_field_test_evidence();
    $model = red_addon_public_mutation_form_ui_compose(
        red_addon_rich_field_test_manifest(),
        'redcms.rich-form-fixture/submit',
        'redcms.rich-form-fixture/record-response',
        'response-form',
        'Submit response',
        $normalizedPresentation['fields'],
        $csrf,
        $idempotency
    );
    red_addon_rich_field_test_assert(
        $model['valid'] === true
            && count($model['fields']) === 12
            && array_column($model['fields'], 'key')
                === array_column(
                    red_addon_rich_field_test_declarations(),
                    'key'
                ),
        'core composes all rich controls in declaration order'
    );

    $html = red_addon_public_mutation_form_ui_render($model);
    red_addon_rich_field_test_assert(
        str_contains($html, 'type="email" name="contact-email"')
            && str_contains($html, 'type="tel" name="contact-phone"')
            && str_contains($html, '<textarea id="red-public-mutation-response-form-location-instructions"')
            && str_contains($html, 'pattern="[A-Z]{2}"')
            && str_contains($html, 'data-red-required-when-field="response-method"')
            && str_contains($html, 'data-red-visible-when-equals="onsite"')
            && !str_contains($html, '<script'),
        'escaped semantic markup exposes bounded rich and conditional controls'
    );

    $input = [
        'confirmation-method' => 'email',
        'contact-email' => 'ana@example.com',
        'contact-name' => 'Ana María',
        'contact-phone' => '+1 202-555-0199',
        'location-city' => 'Arlington',
        'location-country-code' => 'US',
        'location-instructions' => 'Use the side entrance',
        'location-line1' => '100 Main St.',
        'location-line2' => '',
        'location-postal-code' => '22201',
        'location-region' => 'Virginia',
        'response-method' => 'onsite',
    ];
    $decoded = red_addon_public_mutation_form_decode(
        red_addon_rich_field_test_manifest(),
        'redcms.rich-form-fixture/submit',
        'redcms.rich-form-fixture/record-response',
        red_addon_rich_field_test_body($input)
    );
    red_addon_rich_field_test_assert(
        $decoded === [
            'valid' => true,
            'fields' => $input,
            'reason' => 'parsed',
        ],
        'canonical URLSearchParams bytes preserve bounded Unicode and empty strings'
    );
    red_addon_rich_field_test_assert(
        red_addon_public_mutation_execution_fields(
            red_addon_rich_field_test_manifest()['publicMutationContracts'][0],
            $decoded['fields']
        ) === $input,
        'the execution boundary independently revalidates the rich command'
    );
    $typedCommand = new RED_Addon_Public_Mutation_Command(
        'redcms.rich-form-fixture',
        'redcms.rich-form-fixture/submit',
        'redcms.rich-form-fixture/record-response',
        101,
        $decoded['fields']
    );
    red_addon_rich_field_test_assert(
        $typedCommand->fields() === $input
            && $typedCommand->field('contact-name') === 'Ana María'
            && $typedCommand->field('location-line2') === '',
        'typed command retains kebab-case rich fields and explicit empty strings'
    );

    $optional = $input;
    foreach ([
        'contact-phone', 'location-city', 'location-country-code',
        'location-instructions', 'location-line1', 'location-line2',
        'location-postal-code', 'location-region',
    ] as $key) {
        $optional[$key] = '';
    }
    $optional['response-method'] = 'remote';
    red_addon_rich_field_test_assert(
        red_addon_public_mutation_form_decode(
            red_addon_rich_field_test_manifest(),
            'redcms.rich-form-fixture/submit',
            'redcms.rich-form-fixture/record-response',
            red_addon_rich_field_test_body($optional)
        )['valid'] === true,
        'optional empty strings remain explicit for later package semantics'
    );
    $optionalCommand = new RED_Addon_Public_Mutation_Command(
        'redcms.rich-form-fixture',
        'redcms.rich-form-fixture/submit',
        'redcms.rich-form-fixture/record-response',
        101,
        $optional
    );
    red_addon_rich_field_test_assert(
        $optionalCommand->fields() === $optional,
        'typed command preserves every optional empty control for package rules'
    );
    red_addon_rich_field_test_assert(
        red_addon_public_mutation_command_fields([]) === null
            && red_addon_public_mutation_command_fields(
                ['bad_key' => 'value']
            ) === null
            && red_addon_public_mutation_command_fields(
                ['field' => ['nested']]
            ) === null
            && red_addon_public_mutation_command_fields(
                ['field' => true]
            ) === null
            && red_addon_public_mutation_command_fields(
                ['field' => 0]
            ) === null
            && red_addon_public_mutation_command_fields(
                ['field' => ' padded ']
            ) === null,
        'typed command still refuses empty maps, underscore keys, nested or boolean values, non-positive integers, and non-canonical strings'
    );

    $invalidBodies = [
        'malformed email' => str_replace(
            'contact-email=ana%40example.com',
            'contact-email=not-an-email',
            red_addon_rich_field_test_body($input)
        ),
        'lowercase percent escape' => str_replace(
            'Ana+Mar%C3%ADa',
            'Ana+Mar%c3%ADa',
            red_addon_rich_field_test_body($input)
        ),
        'noncanonical encoded letter' => str_replace(
            'contact-name=Ana+Mar%C3%ADa',
            'contact-name=%41na+Mar%C3%ADa',
            red_addon_rich_field_test_body($input)
        ),
        'lowercase country code' => str_replace(
            'location-country-code=US',
            'location-country-code=us',
            red_addon_rich_field_test_body($input)
        ),
        'leading whitespace' => str_replace(
            'contact-name=Ana+Mar%C3%ADa',
            'contact-name=+Ana+Mar%C3%ADa',
            red_addon_rich_field_test_body($input)
        ),
    ];
    foreach ($invalidBodies as $name => $body) {
        red_addon_rich_field_test_assert(
            red_addon_public_mutation_form_decode(
                red_addon_rich_field_test_manifest(),
                'redcms.rich-form-fixture/submit',
                'redcms.rich-form-fixture/record-response',
                $body
            ) === red_addon_public_mutation_form_result(),
            $name . ' fails uniformly with no partial fields'
        );
    }

    $badPresentation = $presentation;
    $badPresentation['fields'][2]['requiredWhen']['equals'] = 'forged/value';
    $unknownCondition = $normalizedPresentation['fields'];
    $unknownCondition[2]['requiredWhen']['field'] = 'unknown-controller';
    red_addon_rich_field_test_assert(
        red_addon_public_component_mutation_form_presentation(
            $badPresentation
        ) === null
            && red_addon_public_mutation_form_ui_compose(
                red_addon_rich_field_test_manifest(),
                'redcms.rich-form-fixture/submit',
                'redcms.rich-form-fixture/record-response',
                'response-form',
                'Submit response',
                $unknownCondition,
                $csrf,
                $idempotency
            ) === red_addon_public_mutation_form_ui_result('fields_invalid'),
        'malformed or undeclared conditional authority fails closed'
    );

    echo 'Public mutation rich-field self-test passed ('
        . $assertions . " assertions).\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
