<?php
/**
 * Dependency-free checks for core-owned add-on component editor markup.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_component_editor_ui_helpers.php';
require_once dirname(__DIR__) . '/includes/addon_component_editor_revision_ui_helpers.php';

$assertions = 0;

function red_addon_editor_renderer_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_editor_renderer_test_manifest()
{
    $component = 'redcms.editor-renderer/item';
    $permission = 'editor-renderer.items.manage';
    return [
        'provides' => ['components' => [$component]],
        'permissions' => [$permission],
        'componentEditors' => [[
            'component' => $component,
            'label' => 'Fixture <item>',
            'description' => 'Core-owned & package-independent editor.',
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
                    'label' => 'Title <required>',
                    'type' => 'text',
                    'required' => true,
                    'help' => 'Visible & public.',
                    'minLength' => 2,
                    'maxLength' => 120,
                ],
                [
                    'key' => 'summary',
                    'label' => 'Summary',
                    'type' => 'textarea',
                    'required' => false,
                    'maxLength' => 500,
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
                        ['value' => 'active', 'label' => 'Active <now>'],
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

function red_addon_editor_renderer_test_values()
{
    return [
        'title' => '"><script>alert(1)</script>',
        'summary' => "First <line>\nSecond & line",
        'quantity' => '12',
        'featured' => '0',
        'status' => 'active',
        'website' => 'https://example.com/item?ref=fixture&mode=view',
        'contact-email' => 'editor@example.com',
        'release-date' => '2028-02-29',
        'starts-at' => '2028-02-29T14:30:00Z',
        'media' => 'media:fixture-item-01',
    ];
}

try {
    $manifest = red_addon_editor_renderer_test_manifest();
    $component = 'redcms.editor-renderer/item';
    $values = red_addon_editor_renderer_test_values();
    $inputFingerprint = hash('sha256', serialize([$manifest, $values]));
    $validated = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $values
    );
    red_addon_editor_renderer_test_assert(
        !empty($validated['valid']) && $validated['errors'] === [],
        'the fixture values pass the separate closed-schema value validator'
    );

    $html = red_addon_component_editor_ui_render(
        $manifest,
        $component,
        $validated,
        'fixture-editor'
    );
    $token = substr(hash('sha256', $component), 0, 12);
    $prefix = 'fixture-editor-' . $token;
    red_addon_editor_renderer_test_assert(
        str_starts_with(
            $html,
            '<fieldset class="red-admin-addon-editor"'
        )
            && str_contains(
                $html,
                'data-red-addon-component-editor="redcms.editor-renderer/item"'
            )
            && str_contains($html, 'Fixture &lt;item&gt;')
            && str_contains(
                $html,
                'Core-owned &amp; package-independent editor.'
            ),
        'the renderer emits one namespaced fieldset with escaped schema copy'
    );
    red_addon_editor_renderer_test_assert(
        !str_contains($html, '<form')
            && !str_contains($html, '<button')
            && !str_contains($html, '<script')
            && !str_contains($html, '<style')
            && !str_contains($html, ' action=')
            && !str_contains($html, ' method='),
        'display-only markup cannot submit, execute, or inject package markup'
    );
    red_addon_editor_renderer_test_assert(
        str_contains(
            $html,
            '<label for="' . $prefix . '-title">Title &lt;required&gt;'
        )
            && str_contains(
                $html,
                'name="componentValues[title]" id="' . $prefix . '-title"'
            )
            && str_contains($html, 'minlength="2" maxlength="120"')
            && str_contains(
                $html,
                'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"'
            )
            && str_contains($html, 'Visible &amp; public.'),
        'text controls use stable names, declared bounds, descriptions, and escaped values'
    );
    red_addon_editor_renderer_test_assert(
        str_contains($html, '<textarea name="componentValues[summary]"')
            && str_contains($html, 'rows="5" maxlength="500"')
            && str_contains(
                $html,
                'First &lt;line&gt;' . "\n" . 'Second &amp; line'
            ),
        'textarea content is escaped and retains normalized line breaks'
    );
    red_addon_editor_renderer_test_assert(
        str_contains(
            $html,
            '<input type="number" step="1" inputmode="numeric" min="0" max="100"'
        )
            && str_contains($html, 'name="componentValues[quantity]"')
            && str_contains($html, 'value="12"'),
        'integer controls use fixed whole-number input attributes and schema bounds'
    );
    red_addon_editor_renderer_test_assert(
        str_contains($html, 'name="componentValues[featured]"')
            && str_contains($html, '<option value="0" selected>No</option>')
            && str_contains($html, 'name="componentValues[status]"')
            && str_contains(
                $html,
                '<option value="active" selected>Active &lt;now&gt;</option>'
            ),
        'boolean and select fields render only fixed or allowlisted choices'
    );
    red_addon_editor_renderer_test_assert(
        str_contains(
            $html,
            '<input type="url" inputmode="url" name="componentValues[website]"'
        )
            && str_contains(
                $html,
                '<input type="email" inputmode="email" name="componentValues[contact-email]"'
            )
            && str_contains(
                $html,
                '<input type="date" name="componentValues[release-date]"'
            )
            && str_contains(
                $html,
                'placeholder="YYYY-MM-DDTHH:MM:SS+00:00"'
            )
            && str_contains(
                $html,
                'value="2028-02-29T14:30:00+00:00"'
            )
            && str_contains(
                $html,
                'name="componentValues[media]"'
            ),
        'locator, temporal, and media-reference types use fixed core controls'
    );
    red_addon_editor_renderer_test_assert(
        substr_count($html, ' required') === 4
            && substr_count($html, 'aria-required="true"') === 4
            && str_contains(
                $html,
                'aria-describedby="' . $prefix . '-title-help"'
            ),
        'required and described-by relationships are emitted deterministically'
    );
    red_addon_editor_renderer_test_assert(
        hash('sha256', serialize([$manifest, $values])) === $inputFingerprint
            && red_addon_component_editor_ui_render(
                $manifest,
                $component,
                $validated,
                'fixture-editor'
            ) === $html,
        'rendering is deterministic and does not mutate its inputs'
    );

    $invalidValues = $values;
    $invalidValues['title'] = '';
    $invalidValues['quantity'] = '101';
    $invalid = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $invalidValues
    );
    $invalidHtml = red_addon_component_editor_ui_render(
        $manifest,
        $component,
        $invalid,
        'fixture-errors'
    );
    red_addon_editor_renderer_test_assert(
        empty($invalid['valid'])
            && str_contains($invalidHtml, 'aria-invalid="true"')
            && str_contains($invalidHtml, 'This field is required.')
            && str_contains(
                $invalidHtml,
                'Enter a value at or below the maximum.'
            )
            && !str_contains($invalidHtml, '101'),
        'failed validation renders only core-owned field errors and no rejected values'
    );

    $unknownValues = $values;
    $unknownValues['callback'] = '<img src=x onerror=alert(1)>';
    $unknown = red_addon_component_editor_validate_values(
        $manifest,
        $component,
        $unknownValues
    );
    $unknownHtml = red_addon_component_editor_ui_render(
        $manifest,
        $component,
        $unknown,
        'fixture-global'
    );
    red_addon_editor_renderer_test_assert(
        str_contains(
            $unknownHtml,
            '<div class="red-admin-addon-editor__summary" role="alert">'
        )
            && str_contains(
                $unknownHtml,
                'The submitted fields did not match this editor.'
            )
            && !str_contains($unknownHtml, 'callback')
            && !str_contains($unknownHtml, 'onerror'),
        'unknown submitted fields become a fixed global error without reflection'
    );

    $emptyHtml = red_addon_component_editor_ui_render(
        $manifest,
        $component,
        null,
        'fixture-empty'
    );
    red_addon_editor_renderer_test_assert(
        str_contains($emptyHtml, 'value=""')
            && str_contains($emptyHtml, '<option value="" selected disabled>')
            && !str_contains($emptyHtml, 'aria-invalid="true"'),
        'a safe empty display state needs no package data loader'
    );

    $forgedValid = $validated;
    $forgedValid['values']['status'] = 'not-declared';
    $forgedError = [
        'valid' => false,
        'component' => $component,
        'values' => [],
        'errors' => [[
            'field' => 'title',
            'code' => '<script>alert(1)</script>',
        ]],
    ];
    $missingSchema = $manifest;
    unset($missingSchema['componentEditors']);
    red_addon_editor_renderer_test_assert(
        red_addon_component_editor_ui_render(
            $manifest,
            $component,
            $forgedValid
        ) === red_addon_component_editor_ui_unavailable()
            && red_addon_component_editor_ui_render(
                $manifest,
                $component,
                $forgedError
            ) === red_addon_component_editor_ui_unavailable()
            && red_addon_component_editor_ui_render(
                $manifest,
                $component,
                null,
                'INVALID_PREFIX'
            ) === red_addon_component_editor_ui_unavailable()
            && red_addon_component_editor_ui_render(
                $missingSchema,
                $component
            ) === red_addon_component_editor_ui_unavailable(),
        'forged state, unknown errors, unsafe ids, and unavailable schemas fail closed'
    );

    $css = file_get_contents(
        dirname(__DIR__) . '/admin/assets/css/cp.css'
    );
    red_addon_editor_renderer_test_assert(
        is_string($css)
            && str_contains($css, '#advanced .red-admin-addon-editor {')
            && str_contains($css, 'box-sizing: border-box;')
            && str_contains(
                $css,
                '#advanced .red-admin-addon-editor .red-admin-field textarea'
            )
            && str_contains(
                $css,
                'input[type="email"][aria-invalid="true"]'
            ),
        'administrator styles are core-owned, scoped, and contain control width'
    );

    $currentHash = hash('sha256', 'current-state');
    $history = [
        [
            'revisionId' => 42,
            'revisionNumber' => 3,
            'operation' => 'restore',
            'actorRecordId' => 7,
            'actorAlias' => 'Owner <one>',
            'stateHash' => $currentHash,
            'restoredFromRevisionId' => 20,
            'createdAt' => '2026-08-01 15:30:00',
        ],
        [
            'revisionId' => 21,
            'revisionNumber' => 2,
            'operation' => 'save',
            'actorRecordId' => 7,
            'actorAlias' => '',
            'stateHash' => hash('sha256', 'other-state'),
            'restoredFromRevisionId' => 0,
            'createdAt' => '2026-08-01 15:20:00',
        ],
        [
            'revisionId' => 20,
            'revisionNumber' => 1,
            'operation' => 'baseline',
            'actorRecordId' => 7,
            'actorAlias' => 'Owner <one>',
            'stateHash' => $currentHash,
            'restoredFromRevisionId' => 0,
            'createdAt' => '2026-08-01 15:10:00',
        ],
    ];
    $historyHtml = red_addon_component_revision_ui_render(
        $history,
        $currentHash,
        'Fixture <item>',
        'fixture-history'
    );
    red_addon_editor_renderer_test_assert(
        str_contains($historyHtml, 'aria-labelledby="fixture-history-heading"')
            && str_contains($historyHtml, 'Fixture &lt;item&gt;')
            && str_contains($historyHtml, 'Owner &lt;one&gt;')
            && str_contains($historyHtml, 'Administrator #7')
            && str_contains($historyHtml, 'Revision record #20'),
        'history markup is accessible and escapes bounded revision metadata'
    );
    red_addon_editor_renderer_test_assert(
        str_contains($historyHtml, '>Current</span>')
            && str_contains($historyHtml, '>Restore check required</span>')
            && str_contains($historyHtml, '>Matches current</span>')
            && substr_count($historyHtml, '<li ') === 3,
        'history distinguishes current, matching, and preflight-required states'
    );
    red_addon_editor_renderer_test_assert(
        !str_contains($historyHtml, '<form')
            && !str_contains($historyHtml, '<button')
            && !str_contains($historyHtml, '<a ')
            && !str_contains($historyHtml, '<script')
            && !str_contains($historyHtml, '<style')
            && !str_contains($historyHtml, $currentHash)
            && !str_contains($historyHtml, 'componentValues'),
        'history discloses no values, hashes, action, link, or executable markup'
    );
    $forgedHistory = $history;
    $forgedHistory[1]['values'] = ['secret' => 'must-not-render'];
    $wrongOrder = [$history[1], $history[0], $history[2]];
    red_addon_editor_renderer_test_assert(
        red_addon_component_revision_ui_render(
            [],
            $currentHash
        ) === red_addon_component_revision_ui_unavailable()
            && red_addon_component_revision_ui_render(
                $forgedHistory,
                $currentHash
            ) === red_addon_component_revision_ui_unavailable()
            && red_addon_component_revision_ui_render(
                $wrongOrder,
                $currentHash
            ) === red_addon_component_revision_ui_unavailable()
            && red_addon_component_revision_ui_render(
                $history,
                hash('sha256', 'stale-state')
            ) === red_addon_component_revision_ui_unavailable(),
        'empty, value-bearing, reordered, and stale histories fail closed'
    );
    red_addon_editor_renderer_test_assert(
        str_contains($css, '#advanced .red-admin-addon-history {')
            && str_contains($css, '.red-admin-addon-history__list {')
            && str_contains($css, '@media (max-width: 700px)')
            && str_contains($css, '.red-admin-addon-history__meta {'),
        'revision-history styles are core-owned, scoped, and responsive'
    );

    printf(
        "Add-on component editor renderer self-test passed: %d assertions.\n",
        $assertions
    );
} catch (Throwable $throwable) {
    fwrite(
        STDERR,
        'Add-on component editor renderer self-test failed: '
            . $throwable->getMessage()
            . ' (after ' . $assertions . " assertions)\n"
    );
    exit(1);
}
