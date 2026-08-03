<?php
/**
 * Dependency-free checks for data-only add-on setting normalization.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_setting_helpers.php';

$assertions = 0;

function red_addon_setting_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_setting_test_has_error(array $result, $setting, $code)
{
    return in_array(
        ['setting' => $setting, 'code' => $code],
        $result['errors'] ?? [],
        true
    );
}

function red_addon_setting_test_manifest()
{
    return [
        'settings' => [
            [
                'key' => 'store.name',
                'label' => 'Store name',
                'type' => 'text',
                'secret' => false,
                'default' => 'Fixture Store',
            ],
            [
                'key' => 'checkout.enabled',
                'label' => 'Checkout enabled',
                'type' => 'boolean',
                'secret' => false,
                'default' => false,
            ],
            [
                'key' => 'inventory.limit',
                'label' => 'Inventory limit',
                'type' => 'integer',
                'secret' => false,
                'default' => 25,
            ],
            [
                'key' => 'store.currency',
                'label' => 'Currency',
                'type' => 'select',
                'secret' => false,
                'default' => 'USD',
                'options' => ['USD', 'COP'],
            ],
            [
                'key' => 'store.terms-url',
                'label' => 'Terms URL',
                'type' => 'url',
                'secret' => false,
            ],
            [
                'key' => 'store.contact-email',
                'label' => 'Contact email',
                'type' => 'email',
                'secret' => false,
            ],
            [
                'key' => 'payment.api-key',
                'label' => 'Payment API key',
                'type' => 'secret-reference',
                'secret' => true,
            ],
        ],
    ];
}

function red_addon_setting_test_required_values()
{
    return [
        'store.terms-url' => 'https://example.test/terms',
        'store.contact-email' => 'orders@example.test',
        'payment.api-key' => 'config:redcms.store-lite.payment-api-key',
    ];
}

try {
    $manifest = red_addon_setting_test_manifest();
    $values = red_addon_setting_test_required_values();
    $schema = red_addon_settings_schema($manifest);
    red_addon_setting_test_assert(
        is_array($schema)
            && count($schema) === 7
            && $schema[0]['default'] === 'Fixture Store'
            && $schema[1]['default'] === false
            && $schema[2]['default'] === 25
            && $schema[3]['options'] === ['USD', 'COP'],
        'manifest settings normalize in declaration order with typed defaults'
    );

    $fingerprint = hash('sha256', serialize([$manifest, $values]));
    $valid = red_addon_settings_validate_values($manifest, $values);
    red_addon_setting_test_assert(
        $valid === [
            'valid' => true,
            'values' => [
                'store.name' => 'Fixture Store',
                'checkout.enabled' => false,
                'inventory.limit' => 25,
                'store.currency' => 'USD',
                'store.terms-url' => 'https://example.test/terms',
                'store.contact-email' => 'orders@example.test',
            ],
            'secretReferences' => [
                'payment.api-key' =>
                    'config:redcms.store-lite.payment-api-key',
            ],
            'missing' => [],
            'errors' => [],
        ],
        'valid configuration applies defaults and separates opaque secret references'
    );
    red_addon_setting_test_assert(
        hash('sha256', serialize([$manifest, $values])) === $fingerprint,
        'setting validation does not mutate manifest or configured input'
    );

    $overrides = $values + [
        'store.name' => 'Override Store',
        'checkout.enabled' => true,
        'inventory.limit' => 0,
        'store.currency' => 'COP',
    ];
    $overrideResult = red_addon_settings_validate_values(
        $manifest,
        $overrides
    );
    red_addon_setting_test_assert(
        !empty($overrideResult['valid'])
            && $overrideResult['values']['store.name'] === 'Override Store'
            && $overrideResult['values']['checkout.enabled'] === true
            && $overrideResult['values']['inventory.limit'] === 0
            && $overrideResult['values']['store.currency'] === 'COP',
        'explicit values replace defaults without loose scalar coercion'
    );

    $missing = red_addon_settings_validate_values($manifest, []);
    red_addon_setting_test_assert(
        empty($missing['valid'])
            && $missing['values'] === []
            && $missing['secretReferences'] === []
            && $missing['missing'] === [
                'payment.api-key',
                'store.contact-email',
                'store.terms-url',
            ]
            && red_addon_setting_test_has_error(
                $missing,
                'payment.api-key',
                'required'
            ),
        'missing non-default and secret-reference settings fail closed'
    );

    $unknownValues = $values;
    $unknownValues['callback'] = 'dangerous';
    $unknown = red_addon_settings_validate_values(
        $manifest,
        $unknownValues
    );
    red_addon_setting_test_assert(
        empty($unknown['valid'])
            && $unknown['values'] === []
            && red_addon_setting_test_has_error(
                $unknown,
                'callback',
                'unknown_setting'
            ),
        'unknown setting keys return no normalized configuration'
    );

    $invalidPayload = red_addon_settings_validate_values(
        $manifest,
        ['not-an-object']
    );
    red_addon_setting_test_assert(
        red_addon_setting_test_has_error(
            $invalidPayload,
            null,
            'invalid_payload'
        ),
        'list and non-object payload shapes are rejected'
    );

    $invalidTextValues = $values + ['store.name' => ['nested']];
    $invalidText = red_addon_settings_validate_values(
        $manifest,
        $invalidTextValues
    );
    red_addon_setting_test_assert(
        red_addon_setting_test_has_error(
            $invalidText,
            'store.name',
            'invalid_text'
        ),
        'nested and non-string text values are rejected'
    );

    $controlValues = $values + ['store.name' => "Unsafe\nName"];
    $control = red_addon_settings_validate_values($manifest, $controlValues);
    red_addon_setting_test_assert(
        red_addon_setting_test_has_error(
            $control,
            'store.name',
            'invalid_text'
        ),
        'control characters are rejected from bounded text settings'
    );

    foreach (['12', 2147483648, 1.5] as $invalidInteger) {
        $integerValues = $values + ['inventory.limit' => $invalidInteger];
        $integer = red_addon_settings_validate_values(
            $manifest,
            $integerValues
        );
        red_addon_setting_test_assert(
            red_addon_setting_test_has_error(
                $integer,
                'inventory.limit',
                'invalid_integer'
            ),
            'integer settings reject string, overflow, and floating values'
        );
    }

    $booleanValues = $values + ['checkout.enabled' => '1'];
    $boolean = red_addon_settings_validate_values($manifest, $booleanValues);
    red_addon_setting_test_assert(
        red_addon_setting_test_has_error(
            $boolean,
            'checkout.enabled',
            'invalid_boolean'
        ),
        'boolean settings reject string coercion'
    );

    $selectValues = $values + ['store.currency' => 'EUR'];
    $select = red_addon_settings_validate_values($manifest, $selectValues);
    red_addon_setting_test_assert(
        red_addon_setting_test_has_error(
            $select,
            'store.currency',
            'invalid_option'
        ),
        'select settings accept only exact declared choices'
    );

    foreach (
        [
            ' file:///tmp/secret',
            'https://user:pass@example.test/private',
        ] as $invalidUrl
    ) {
        $urlValues = $values;
        $urlValues['store.terms-url'] = $invalidUrl;
        $url = red_addon_settings_validate_values($manifest, $urlValues);
        red_addon_setting_test_assert(
            red_addon_setting_test_has_error(
                $url,
                'store.terms-url',
                'invalid_url'
            ),
            'URL settings require bounded credential-free HTTP or HTTPS URLs'
        );
    }

    $emailValues = $values;
    $emailValues['store.contact-email'] = 'not-an-email';
    $email = red_addon_settings_validate_values($manifest, $emailValues);
    red_addon_setting_test_assert(
        red_addon_setting_test_has_error(
            $email,
            'store.contact-email',
            'invalid_email'
        ),
        'email settings require a bounded validated address'
    );

    foreach (['actual-secret-value', 'CONFIG:UPPER', '../secret'] as $raw) {
        $secretValues = $values;
        $secretValues['payment.api-key'] = $raw;
        $secret = red_addon_settings_validate_values(
            $manifest,
            $secretValues
        );
        red_addon_setting_test_assert(
            red_addon_setting_test_has_error(
                $secret,
                'payment.api-key',
                'invalid_secret_reference'
            ),
            'secret settings accept only opaque config reference identifiers'
        );
    }

    $invalidManifest = $manifest;
    $invalidManifest['settings'][1]['default'] = 'false';
    $invalidManifest['settings'][3]['default'] = 'EUR';
    $invalidManifest['settings'][6]['default'] =
        'config:redcms.store-lite.forbidden';
    $invalidSchema = red_addon_settings_validate_values(
        $invalidManifest,
        $values
    );
    red_addon_setting_test_assert(
        red_addon_setting_test_has_error(
            $invalidSchema,
            null,
            'schema_unavailable'
        ),
        'type-drifted and secret defaults make the complete schema unavailable'
    );

    $empty = red_addon_settings_validate_values(['settings' => []], []);
    red_addon_setting_test_assert(
        $empty === [
            'valid' => true,
            'values' => [],
            'secretReferences' => [],
            'missing' => [],
            'errors' => [],
        ],
        'a package with no settings has one exact valid empty configuration'
    );
} finally {
    // Dependency-free fixture creates no filesystem or database state.
}

fwrite(
    STDOUT,
    'Add-on setting values self-test passed (' . $assertions .
        " assertions).\n"
);

?>
