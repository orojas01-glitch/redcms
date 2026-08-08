<?php
/**
 * Dependency-free checks for bounded administrator form schemas and previews.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_admin_tool_form_ui_helpers.php';
require_once $projectRoot
    . '/includes/addon_admin_tool_form_preflight_helpers.php';

$assertions = 0;
$toolId = 'redcms.form-renderer/products';
$formId = 'redcms.form-renderer/product-editor';
$permission = 'fixture.products.manage';

function red_addon_admin_form_renderer_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_form_renderer_manifest()
{
    global $toolId, $formId, $permission;
    return [
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [$toolId],
            'adapters' => [],
        ],
        'permissions' => [$permission],
        'adminToolFormContracts' => [[
            'tool' => $toolId,
            'form' => $formId,
            'label' => 'Product <editor>',
            'description' => 'Simple & variable product preview.',
            'permission' => $permission,
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/json',
            'maxBodyBytes' => 262144,
            'fields' => [
                [
                    'key' => 'id',
                    'label' => 'Product identifier',
                    'type' => 'text',
                    'required' => true,
                    'help' => 'Stable package-owned identifier.',
                    'minLength' => 1,
                    'maxLength' => 64,
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
                    'label' => 'Price in minor units',
                    'type' => 'integer',
                    'required' => false,
                    'minimum' => 0,
                    'maximum' => 999999999,
                ],
                [
                    'key' => 'options',
                    'label' => 'Option groups',
                    'type' => 'collection',
                    'required' => false,
                    'itemLabel' => 'Option group',
                    'help' => 'Size, color, or another bounded choice.',
                    'minItems' => 0,
                    'maxItems' => 3,
                    'fields' => [
                        [
                            'key' => 'key',
                            'label' => 'Option key',
                            'type' => 'text',
                            'required' => true,
                            'minLength' => 1,
                            'maxLength' => 32,
                        ],
                        [
                            'key' => 'label',
                            'label' => 'Option label',
                            'type' => 'text',
                            'required' => true,
                            'minLength' => 1,
                            'maxLength' => 80,
                        ],
                        [
                            'key' => 'values',
                            'label' => 'Option values',
                            'type' => 'collection',
                            'required' => true,
                            'itemLabel' => 'Option value',
                            'minItems' => 1,
                            'maxItems' => 16,
                            'fields' => [
                                [
                                    'key' => 'id',
                                    'label' => 'Value identifier',
                                    'type' => 'text',
                                    'required' => true,
                                    'minLength' => 1,
                                    'maxLength' => 32,
                                ],
                                [
                                    'key' => 'label',
                                    'label' => 'Value label',
                                    'type' => 'text',
                                    'required' => true,
                                    'minLength' => 1,
                                    'maxLength' => 80,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'variants',
                    'label' => 'Variants',
                    'type' => 'collection',
                    'required' => false,
                    'itemLabel' => 'Variant',
                    'minItems' => 0,
                    'maxItems' => 128,
                    'fields' => [
                        [
                            'key' => 'sku',
                            'label' => 'SKU',
                            'type' => 'text',
                            'required' => true,
                            'minLength' => 1,
                            'maxLength' => 64,
                        ],
                        [
                            'key' => 'selections',
                            'label' => 'Option selections',
                            'type' => 'collection',
                            'required' => true,
                            'itemLabel' => 'Selection',
                            'minItems' => 1,
                            'maxItems' => 3,
                            'fields' => [
                                [
                                    'key' => 'option',
                                    'label' => 'Option key',
                                    'type' => 'text',
                                    'required' => true,
                                    'minLength' => 1,
                                    'maxLength' => 32,
                                ],
                                [
                                    'key' => 'value',
                                    'label' => 'Value identifier',
                                    'type' => 'text',
                                    'required' => true,
                                    'minLength' => 1,
                                    'maxLength' => 32,
                                ],
                            ],
                        ],
                        [
                            'key' => 'price-minor',
                            'label' => 'Variant price',
                            'type' => 'integer',
                            'required' => true,
                            'minimum' => 0,
                            'maximum' => 999999999,
                        ],
                    ],
                ],
            ],
        ]],
    ];
}

function red_addon_admin_form_renderer_error_contains(
    array $result,
    $fragment
) {
    foreach ($result['errors'] ?? [] as $error) {
        if (is_string($error) && str_contains($error, $fragment)) {
            return true;
        }
    }
    return false;
}

try {
    $manifest = red_addon_admin_form_renderer_manifest();
    $inputHash = hash('sha256', serialize($manifest));
    $contract = red_addon_admin_tool_form_contract(
        $manifest,
        $toolId,
        $formId
    );
    red_addon_admin_form_renderer_assert(
        is_array($contract)
            && count($contract['fields']) === 5
            && $contract['fields'][3]['maxItems'] === 3
            && $contract['fields'][3]['fields'][2]['maxItems'] === 16
            && $contract['fields'][4]['maxItems'] === 128,
        'closed schema resolves the simple and variable product bounds'
    );

    $html = red_addon_admin_tool_form_ui_render(
        $manifest,
        $toolId,
        $formId,
        'fixture-product'
    );
    red_addon_admin_form_renderer_assert(
        str_starts_with($html, '<section class="red-admin-addon-tool-form"')
            && str_contains(
                $html,
                'data-red-addon-admin-tool-form="' . $formId . '"'
            )
            && str_contains($html, 'Product &lt;editor&gt;')
            && str_contains(
                $html,
                'Simple &amp; variable product preview.'
            ),
        'renderer emits one namespaced preview with escaped contract copy'
    );
    red_addon_admin_form_renderer_assert(
        !str_contains($html, '<form')
            && !str_contains($html, '<button')
            && !str_contains($html, '<script')
            && !str_contains($html, '<style')
            && !str_contains($html, ' name=')
            && !str_contains($html, ' action=')
            && !str_contains($html, ' method='),
        'preview contains no operational or package-controlled markup'
    );
    red_addon_admin_form_renderer_assert(
        substr_count($html, ' disabled') === 11
            && substr_count($html, 'aria-disabled="true"') === 11
            && str_contains(
                $html,
                'Preview only. Editing and saving are not available.'
            ),
        'every scalar template control is disabled and the preview state is explicit'
    );
    red_addon_admin_form_renderer_assert(
        str_contains($html, 'type="text"')
            && str_contains($html, 'minlength="1" maxlength="64"')
            && str_contains($html, 'type="number" step="1"')
            && str_contains($html, 'max="999999999"'),
        'text and integer controls expose only their declared browser bounds'
    );
    red_addon_admin_form_renderer_assert(
        str_contains($html, '<option value="simple">Simple item</option>')
            && str_contains(
                $html,
                '<option value="variable">Variable item</option>'
            ),
        'product mode uses only allowlisted core-rendered choices'
    );
    red_addon_admin_form_renderer_assert(
        str_contains(
            $html,
            'data-red-addon-admin-form-collection="options" data-min-items="0" data-max-items="3"'
        )
            && str_contains($html, 'Option group template')
            && str_contains(
                $html,
                'data-red-addon-admin-form-collection="values" data-min-items="1" data-max-items="16"'
            )
            && str_contains($html, 'Option value template'),
        'option groups and nested values remain separately bounded'
    );
    red_addon_admin_form_renderer_assert(
        str_contains(
            $html,
            'data-red-addon-admin-form-collection="variants" data-min-items="0" data-max-items="128"'
        )
            && str_contains($html, 'Variant template')
            && str_contains(
                $html,
                'data-red-addon-admin-form-collection="selections" data-min-items="1" data-max-items="3"'
            )
            && str_contains($html, 'Selection template'),
        'variant rows and their exact option selections fit the same closed schema'
    );
    red_addon_admin_form_renderer_assert(
        hash('sha256', serialize($manifest)) === $inputHash
            && red_addon_admin_tool_form_ui_render(
                $manifest,
                $toolId,
                $formId,
                'fixture-product'
            ) === $html,
        'schema rendering is deterministic and does not mutate manifest data'
    );

    $withoutFields = $manifest;
    unset($withoutFields['adminToolFormContracts'][0]['fields']);
    red_addon_admin_form_renderer_assert(
        str_contains(
            red_addon_admin_tool_form_ui_render(
                $withoutFields,
                $toolId,
                $formId
            ),
            'data-red-addon-admin-tool-form-unavailable'
        ),
        'a declaration without a field schema cannot render a preview'
    );
    red_addon_admin_form_renderer_assert(
        str_contains(
            red_addon_admin_tool_form_ui_render(
                $manifest,
                $toolId,
                $formId,
                '../unsafe'
            ),
            'data-red-addon-admin-tool-form-unavailable'
        ),
        'unsafe renderer instance identities fail closed'
    );

    $executable = $manifest;
    $executable['adminToolFormContracts'][0]['fields'][0]['renderer'] =
        'package_callback';
    $result = ['errors' => [], 'warnings' => []];
    $fieldCount = 0;
    red_addon_validate_admin_tool_form_fields(
        $executable['adminToolFormContracts'][0]['fields'],
        'Fixture fields',
        0,
        $result,
        $fieldCount
    );
    red_addon_admin_form_renderer_assert(
        red_addon_admin_form_renderer_error_contains(
            $result,
            'contains unsupported field "renderer"'
        ),
        'executable field metadata is rejected before rendering'
    );

    $tooDeep = $manifest['adminToolFormContracts'][0]['fields'];
    $tooDeep[3]['fields'][2]['fields'][] = [
        'key' => 'aliases',
        'label' => 'Aliases',
        'type' => 'collection',
        'required' => false,
        'itemLabel' => 'Alias',
        'minItems' => 0,
        'maxItems' => 2,
        'fields' => [[
            'key' => 'value',
            'label' => 'Alias value',
            'type' => 'text',
            'required' => true,
            'minLength' => 1,
            'maxLength' => 32,
        ]],
    ];
    $result = ['errors' => [], 'warnings' => []];
    $fieldCount = 0;
    red_addon_validate_admin_tool_form_fields(
        $tooDeep,
        'Fixture fields',
        0,
        $result,
        $fieldCount
    );
    red_addon_admin_form_renderer_assert(
        red_addon_admin_form_renderer_error_contains(
            $result,
            'exceeds the two-level collection depth'
        ),
        'a third collection level is rejected explicitly'
    );

    $duplicate = $manifest['adminToolFormContracts'][0]['fields'];
    $duplicate[] = $duplicate[0];
    $result = ['errors' => [], 'warnings' => []];
    $fieldCount = 0;
    red_addon_validate_admin_tool_form_fields(
        $duplicate,
        'Fixture fields',
        0,
        $result,
        $fieldCount
    );
    red_addon_admin_form_renderer_assert(
        red_addon_admin_form_renderer_error_contains(
            $result,
            'repeats field key "id"'
        ),
        'field identities remain unique within each object or collection row'
    );

    $badBounds = $manifest['adminToolFormContracts'][0]['fields'];
    $badBounds[4]['maxItems'] = 129;
    $result = ['errors' => [], 'warnings' => []];
    $fieldCount = 0;
    red_addon_validate_admin_tool_form_fields(
        $badBounds,
        'Fixture fields',
        0,
        $result,
        $fieldCount
    );
    red_addon_admin_form_renderer_assert(
        red_addon_admin_form_renderer_error_contains(
            $result,
            'collection bounds are invalid'
        ),
        'collections cannot exceed the 128-row product-variant ceiling'
    );

    $tooManyFields = [];
    for ($groupIndex = 0; $groupIndex < 7; $groupIndex++) {
        $childFields = [];
        for ($fieldIndex = 0; $fieldIndex < 32; $fieldIndex++) {
            $childFields[] = [
                'key' => 'field-' . $fieldIndex,
                'label' => 'Field ' . $fieldIndex,
                'type' => 'boolean',
                'required' => false,
            ];
        }
        $tooManyFields[] = [
            'key' => 'group-' . $groupIndex,
            'label' => 'Group ' . $groupIndex,
            'type' => 'collection',
            'required' => false,
            'itemLabel' => 'Group item',
            'minItems' => 0,
            'maxItems' => 1,
            'fields' => $childFields,
        ];
    }
    $result = ['errors' => [], 'warnings' => []];
    $fieldCount = 0;
    red_addon_validate_admin_tool_form_fields(
        $tooManyFields,
        'Fixture fields',
        0,
        $result,
        $fieldCount
    );
    red_addon_admin_form_renderer_assert(
        $fieldCount === 231
            && red_addon_admin_form_renderer_error_contains(
                $result,
                'exceeds 200 total fields'
            ),
        'complete schemas cannot exceed 200 fields across collection rows'
    );

    $drift = $manifest;
    $drift['adminToolFormContracts'][0]['fields'][4]['maxItems'] = 64;
    $driftContract = red_addon_admin_tool_form_contract(
        $drift,
        $toolId,
        $formId
    );
    red_addon_admin_form_renderer_assert(
        red_addon_admin_tool_form_contract_fingerprint($contract)
            !== red_addon_admin_tool_form_contract_fingerprint(
                $driftContract
            ),
        'schema drift changes the form contract fingerprint'
    );

    $schema = json_decode(
        (string) file_get_contents(
            $projectRoot . '/docs/addon-manifest.schema.json'
        ),
        true
    );
    red_addon_admin_form_renderer_assert(
        is_array($schema)
            && ($schema['$defs']['adminToolFormField']['oneOf'][1]['$ref'] ?? '')
                === '#/$defs/adminToolFormCollectionField'
            && ($schema['$defs']['adminToolFormCollectionField']['properties']['maxItems']['maximum'] ?? null)
                === 128
            && ($schema['$defs']['adminToolFormCollectionField']['properties']['fields']['items']['$ref'] ?? '')
                === '#/$defs/adminToolFormField',
        'published schema closes scalar fields and recursive collection rows'
    );

    $source = (string) file_get_contents(
        $projectRoot . '/includes/addon_admin_tool_form_ui_helpers.php'
    );
    red_addon_admin_form_renderer_assert(
        !str_contains($source, '$_POST')
            && !str_contains($source, '$_GET')
            && !str_contains($source, '$_SESSION')
            && !str_contains($source, 'mysqli')
            && !str_contains($source, 'red_csrf_token(')
            && !str_contains($source, 'red_verify_csrf(')
            && !str_contains($source, 'red_addon_runtime_handler('),
        'the display-only preview helper keeps no request, database, CSRF, or callback path'
    );
} finally {
    fwrite(
        STDOUT,
        'Add-on administrator tool form renderer self-test passed ('
            . $assertions . " assertions).\n"
    );
}

?>
