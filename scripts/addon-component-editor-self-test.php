<?php
/**
 * Dependency-free checks for add-on component editor value normalization.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_component_editor_helpers.php';

$assertions = 0;

function red_addon_editor_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_editor_test_has_error(array $result, $field, $code)
{
    return in_array(
        [
            'field' => $field,
            'code' => $code,
        ],
        $result['errors'] ?? [],
        true
    );
}

function red_addon_editor_test_manifest()
{
    $component = 'redcms.editor-fixture/item';
    $permission = 'editor-fixture.items.manage';
    return [
        'provides' => [
            'components' => [$component],
        ],
        'permissions' => [$permission],
        'componentEditors' => [[
            'component' => $component,
            'label' => 'Fixture item',
            'description' => 'Dependency-free editor value fixture.',
            'icon' => 'package',
            'permissions' => [
                'create' => $permission,
                'view' => $permission,
                'edit' => $permission,
                'delete' => $permission,
                'publish' => $permission,
                'restore' => $permission,
            ],
            'fields' => [
                [
                    'key' => 'title',
                    'label' => 'Title',
                    'type' => 'text',
                    'required' => true,
                    'minLength' => 2,
                    'maxLength' => 20,
                ],
                [
                    'key' => 'summary',
                    'label' => 'Summary',
                    'type' => 'textarea',
                    'required' => false,
                    'maxLength' => 100,
                ],
                [
                    'key' => 'quantity',
                    'label' => 'Quantity',
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                [
                    'key' => 'featured',
                    'label' => 'Featured',
                    'type' => 'boolean',
                    'required' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'active', 'label' => 'Active'],
                        ['value' => 'inactive', 'label' => 'Inactive'],
                    ],
                ],
                [
                    'key' => 'website',
                    'label' => 'Website',
                    'type' => 'url',
                    'required' => false,
                    'maxLength' => 2048,
                ],
                [
                    'key' => 'contact-email',
                    'label' => 'Contact email',
                    'type' => 'email',
                    'required' => false,
                    'maxLength' => 254,
                ],
                [
                    'key' => 'release-date',
                    'label' => 'Release date',
                    'type' => 'date',
                    'required' => false,
                ],
                [
                    'key' => 'starts-at',
                    'label' => 'Starts at',
                    'type' => 'datetime',
                    'required' => false,
                ],
                [
                    'key' => 'media',
                    'label' => 'Media',
                    'type' => 'media-reference',
                    'required' => false,
                    'maxLength' => 255,
                ],
            ],
        ]],
    ];
}

function red_addon_editor_test_values()
{
    return [
        'title' => 'Fixture item',
        'summary' => "First line\r\nSecond line",
        'quantity' => '12',
        'featured' => '0',
        'status' => 'active',
        'website' => 'https://example.com/item?ref=fixture',
        'contact-email' => 'editor@example.com',
        'release-date' => '2028-02-29',
        'starts-at' => '2028-02-29T14:30:00Z',
        'media' => 'media:fixture-item-01',
    ];
}

try {
    $manifest = red_addon_editor_test_manifest();
    $component = 'redcms.editor-fixture/item';
    $values = red_addon_editor_test_values();
    $inputFingerprint = hash(
        'sha256',
        serialize([$manifest, $component, $values])
    );
    $valid = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $values
    );
    red_addon_editor_test_assert(
        !empty($valid['valid'])
            && $valid['component'] === $component
            && $valid['errors'] === []
            && $valid['values'] === [
                'title' => 'Fixture item',
                'summary' => "First line\nSecond line",
                'quantity' => 12,
                'featured' => false,
                'status' => 'active',
                'website' => 'https://example.com/item?ref=fixture',
                'contact-email' => 'editor@example.com',
                'release-date' => '2028-02-29',
                'starts-at' => '2028-02-29T14:30:00+00:00',
                'media' => 'media:fixture-item-01',
            ],
        'valid scalar submissions normalize into the exact schema field order'
    );
    red_addon_editor_test_assert(
        hash('sha256', serialize([$manifest, $component, $values]))
            === $inputFingerprint,
        'normalization does not mutate the manifest, component id, or submitted values'
    );

    $requiredOnly = [
        'title' => 'Required',
        'quantity' => 0,
        'featured' => false,
        'status' => 'inactive',
    ];
    $optional = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $requiredOnly
    );
    red_addon_editor_test_assert(
        !empty($optional['valid'])
            && $optional['values']['summary'] === null
            && $optional['values']['website'] === null
            && $optional['values']['contact-email'] === null
            && $optional['values']['release-date'] === null
            && $optional['values']['starts-at'] === null
            && $optional['values']['media'] === null,
        'omitted optional values normalize to null without weakening required fields'
    );

    $blankOptionalValues = $requiredOnly + [
        'summary' => '',
        'website' => '',
        'contact-email' => '',
        'release-date' => '',
        'starts-at' => '',
        'media' => '',
    ];
    $blankOptional = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $blankOptionalValues
    );
    red_addon_editor_test_assert(
        !empty($blankOptional['valid'])
            && $blankOptional['values']['summary'] === ''
            && $blankOptional['values']['website'] === null
            && $blankOptional['values']['contact-email'] === null
            && $blankOptional['values']['release-date'] === null
            && $blankOptional['values']['starts-at'] === null
            && $blankOptional['values']['media'] === null,
        'blank optional locator values normalize to null while blank optional text remains explicit'
    );

    $unknownValues = $values;
    $unknownValues['callback'] = 'dangerous';
    $unknown = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $unknownValues
    );
    red_addon_editor_test_assert(
        empty($unknown['valid'])
            && $unknown['values'] === []
            && red_addon_editor_test_has_error(
                $unknown,
                'callback',
                'unknown_field'
            ),
        'unknown fields fail closed without returning partially normalized values'
    );

    $missingRequired = $requiredOnly;
    unset($missingRequired['title']);
    $missingRequiredResult = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $missingRequired
    );
    $emptyRequired = $requiredOnly;
    $emptyRequired['title'] = '';
    $emptyRequiredResult = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $emptyRequired
    );
    red_addon_editor_test_assert(
        red_addon_editor_test_has_error(
            $missingRequiredResult,
            'title',
            'required'
        )
            && red_addon_editor_test_has_error(
                $emptyRequiredResult,
                'title',
                'required'
            )
            && $missingRequiredResult['values'] === []
            && $emptyRequiredResult['values'] === [],
        'missing and empty required text both fail closed'
    );

    $unsafeTextValues = $values;
    $unsafeTextValues['title'] = 'x';
    $unsafeTextValues['summary'] = "unsafe\x00text";
    $unsafeText = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $unsafeTextValues
    );
    $invalidUtf8Values = $values;
    $invalidUtf8Values['title'] = "\xC3\x28";
    $invalidUtf8 = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $invalidUtf8Values
    );
    red_addon_editor_test_assert(
        red_addon_editor_test_has_error($unsafeText, 'title', 'too_short')
            && red_addon_editor_test_has_error(
                $unsafeText,
                'summary',
                'invalid_control_character'
            )
            && red_addon_editor_test_has_error(
                $invalidUtf8,
                'title',
                'invalid_utf8'
            ),
        'text bounds, unsafe controls, and invalid UTF-8 are rejected'
    );

    $nonCanonicalIntegerValues = $values;
    $nonCanonicalIntegerValues['quantity'] = '01';
    $nonCanonicalInteger = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $nonCanonicalIntegerValues
    );
    $outOfBoundsIntegerValues = $values;
    $outOfBoundsIntegerValues['quantity'] = 101;
    $outOfBoundsInteger = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $outOfBoundsIntegerValues
    );
    $negativeZeroValues = $values;
    $negativeZeroValues['quantity'] = '-0';
    $negativeZero = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $negativeZeroValues
    );
    red_addon_editor_test_assert(
        red_addon_editor_test_has_error(
            $nonCanonicalInteger,
            'quantity',
            'invalid_integer'
        )
            && red_addon_editor_test_has_error(
                $negativeZero,
                'quantity',
                'invalid_integer'
            )
            && red_addon_editor_test_has_error(
                $outOfBoundsInteger,
                'quantity',
                'above_maximum'
            ),
        'integers require canonical decimal input inside declared bounds'
    );

    $closedChoiceValues = $values;
    $closedChoiceValues['featured'] = 'true';
    $closedChoiceValues['status'] = 'archived';
    $closedChoice = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $closedChoiceValues
    );
    red_addon_editor_test_assert(
        red_addon_editor_test_has_error(
            $closedChoice,
            'featured',
            'invalid_boolean'
        )
            && red_addon_editor_test_has_error(
                $closedChoice,
                'status',
                'invalid_option'
            ),
        'boolean and select values use closed vocabularies'
    );

    $locatorValues = $values;
    $locatorValues['website'] = 'https://user@example.com/private';
    $locatorValues['contact-email'] = 'invalid-address';
    $locatorValues['media'] = '../fixture.png';
    $locators = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $locatorValues
    );
    red_addon_editor_test_assert(
        red_addon_editor_test_has_error(
            $locators,
            'website',
            'invalid_url'
        )
            && red_addon_editor_test_has_error(
                $locators,
                'contact-email',
                'invalid_email'
            )
            && red_addon_editor_test_has_error(
                $locators,
                'media',
                'invalid_media_reference'
            ),
        'URLs, email addresses, and opaque media references enforce narrow formats'
    );

    $temporalValues = $values;
    $temporalValues['release-date'] = '2027-02-29';
    $temporalValues['starts-at'] = '2028-02-29T14:30';
    $temporal = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $temporalValues
    );
    red_addon_editor_test_assert(
        red_addon_editor_test_has_error(
            $temporal,
            'release-date',
            'invalid_date'
        )
            && red_addon_editor_test_has_error(
                $temporal,
                'starts-at',
                'invalid_datetime'
            ),
        'dates must exist and datetimes require canonical seconds plus an offset'
    );

    $arrayValueValues = $values;
    $arrayValueValues['title'] = ['not', 'scalar'];
    $arrayValue = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $arrayValueValues
    );
    $invalidPayload = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        ['one', 'two']
    );
    red_addon_editor_test_assert(
        red_addon_editor_test_has_error(
            $arrayValue,
            'title',
            'invalid_type'
        )
            && red_addon_editor_test_has_error(
                $invalidPayload,
                null,
                'invalid_payload'
            ),
        'nested field values and list-shaped payloads are rejected'
    );

    $missingSchema = red_addon_component_editor_validate_values(
        [
            'provides' => ['components' => [$component]],
            'permissions' => [],
        ],
        $component,
        $values
    );
    $wrongComponent = red_addon_component_editor_validate_values(
        $manifest,
        'redcms.editor-fixture/other',
        $values
    );
    red_addon_editor_test_assert(
        red_addon_editor_test_has_error(
            $missingSchema,
            null,
            'schema_unavailable'
        )
            && red_addon_editor_test_has_error(
                $wrongComponent,
                null,
                'schema_unavailable'
            ),
        'missing or mismatched editor schemas fail closed before value handling'
    );

    printf(
        "Add-on component editor self-test passed: %d assertions.\n",
        $assertions
    );
} catch (Throwable $throwable) {
    fwrite(
        STDERR,
        'Add-on component editor self-test failed: ' .
        $throwable->getMessage() .
        ' (after ' . $assertions . " assertions)\n"
    );
    exit(1);
}
