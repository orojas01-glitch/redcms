<?php
/**
 * Server-rendered shell for Advanced → Layout Builder.
 */

require_once __DIR__ . '/admin_custom_layout_helpers.php';
require_once __DIR__ . '/admin_area_helpers.php';

if (!function_exists('red_admin_custom_layout_html')) {
    function red_admin_custom_layout_html($value)
    {
        return htmlspecialchars(red_custom_layout_scalar($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('red_admin_custom_layout_bootstrap_json')) {
    function red_admin_custom_layout_bootstrap_json(array $value)
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE |
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return is_string($json) ? $json : '{}';
    }
}

if (!function_exists('red_admin_custom_layout_library_rows')) {
    function red_admin_custom_layout_library_rows($connection)
    {
        $rows = [];
        foreach (red_custom_layout_list($connection, true) as $row) {
            $published = trim((string) ($row['PublishedDefinition'] ?? '')) !== '';
            $hasChanges = !$published
                || !hash_equals(
                    (string) ($row['PublishedHash'] ?? ''),
                    (string) ($row['DraftHash'] ?? '')
                )
                || (string) ($row['PublishedLabel'] ?? '') !== (string) ($row['DraftLabel'] ?? '');
            $rows[] = [
                'layoutId' => (string) $row['LayoutID'],
                'label' => (string) ($row['DraftLabel'] ?? $row['LayoutID']),
                'publishedLabel' => (string) ($row['PublishedLabel'] ?? ''),
                'published' => $published,
                'hasUnpublishedChanges' => $hasChanges,
                'archived' => (string) ($row['Archived'] ?? 'N') === 'Y',
                'revisionNumber' => (int) ($row['RevisionNumber'] ?? 0),
                'stateHash' => (string) $row['StateHash'],
            ];
        }

        return $rows;
    }
}

if (!function_exists('red_admin_render_custom_layout_builder')) {
    function red_admin_render_custom_layout_builder($connection, $selectedLayoutId = '')
    {
        $selectedLayoutId = red_custom_layout_scalar($selectedLayoutId);
        $tablesAvailable = red_custom_layout_tables_available($connection, true);
        $contract = red_theme_active_layout_contract($connection);
        $standardThemeActive = ($contract['themeType'] ?? '') === 'standard';
        $templateLayouts = [];
        foreach (red_theme_layout_manifest_catalog($contract['manifest']) as $layoutId => $definition) {
            try {
                $templateLayouts[] = [
                    'layoutId' => (string) $layoutId,
                    'label' => (string) $definition['label'],
                    'definition' => red_custom_layout_definition_from_catalog($definition),
                    'positionCount' => count($definition['positions']),
                ];
            } catch (Throwable $exception) {
                continue;
            }
        }

        $customLayouts = $tablesAvailable
            ? red_admin_custom_layout_library_rows($connection)
            : [];
        $selectedRow = $tablesAvailable && red_custom_layout_valid_id($selectedLayoutId)
            ? red_custom_layout_fetch($connection, $selectedLayoutId)
            : null;
        $selected = $selectedRow
            ? red_admin_custom_layout_client_row($connection, $selectedRow, true)
            : null;
        $initialDefinition = $selected
            ? $selected['draftDefinition']
            : red_custom_layout_default_definition();
        $bootstrap = [
            'csrfToken' => red_csrf_token(),
            'actionEndpoint' => '/admin/bin/layout_builder_action.php',
            'refreshEndpoint' => '/admin/bin/edit_layout_builder.php',
            'standardThemeActive' => $standardThemeActive,
            'themeId' => (string) ($contract['themeId'] ?? ''),
            'templateLayouts' => $templateLayouts,
            'customLayouts' => $customLayouts,
            'selected' => $selected,
            'initialDefinition' => $initialDefinition,
            'presets' => [
                ['id' => 'full', 'label' => 'Full width', 'spans' => [12]],
                ['id' => 'halves', 'label' => 'Two equal', 'spans' => [6, 6]],
                ['id' => 'thirds', 'label' => 'Three equal', 'spans' => [4, 4, 4]],
                ['id' => 'quarters', 'label' => 'Four equal', 'spans' => [3, 3, 3, 3]],
                ['id' => 'sidebar-right', 'label' => 'Main + right', 'spans' => [8, 4]],
                ['id' => 'sidebar-left', 'label' => 'Left + main', 'spans' => [4, 8]],
                ['id' => 'narrow-left', 'label' => 'Narrow + wide', 'spans' => [3, 9]],
                ['id' => 'narrow-right', 'label' => 'Wide + narrow', 'spans' => [9, 3]],
                ['id' => 'six', 'label' => 'Six columns', 'spans' => [2, 2, 2, 2, 2, 2]],
                ['id' => 'twelve', 'label' => 'Twelve columns', 'spans' => array_fill(0, 12, 1)],
            ],
        ];

        $selectedStatus = 'New draft';
        if ($selected) {
            if ($selected['archived']) {
                $selectedStatus = 'Archived';
            } elseif ($selected['published'] && $selected['hasUnpublishedChanges']) {
                $selectedStatus = 'Published + draft changes';
            } elseif ($selected['published']) {
                $selectedStatus = 'Published';
            } else {
                $selectedStatus = 'Draft';
            }
        }
        ?>
        <section class="red-layout-builder" data-red-layout-builder>
            <script type="application/json" data-red-layout-builder-bootstrap><?= red_admin_custom_layout_bootstrap_json($bootstrap) ?></script>

            <header class="red-layout-builder__header">
                <button type="button" class="red-layout-builder__back" data-red-layout-builder-close aria-label="Return to Advanced settings">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                    <span>Advanced</span>
                </button>
                <div class="red-layout-builder__heading">
                    <span class="red-layout-builder__eyebrow">Visual structure tool</span>
                    <h2>Layout Builder</h2>
                    <p>Build reusable page layouts with rows and a responsive 12-unit grid.</p>
                </div>
                <div class="red-layout-builder__theme">
                    <span>Active template</span>
                    <strong><?= red_admin_custom_layout_html($contract['themeId'] ?? '') ?></strong>
                </div>
            </header>

            <?php if (!$tablesAvailable) : ?>
                <div class="red-layout-builder__notice red-layout-builder__notice--error" role="alert">
                    <strong>Database update required</strong>
                    <span>Apply the Layout Builder migration before creating layouts.</span>
                </div>
            <?php else : ?>
                <?php if (!$standardThemeActive) : ?>
                    <div class="red-layout-builder__notice" role="status">
                        <strong>Draft mode only</strong>
                        <span>You can design and save layouts, but publishing requires an active standard template.</span>
                    </div>
                <?php endif; ?>

                <div class="red-layout-builder__shell">
                    <aside class="red-layout-builder__library" aria-label="Layout library">
                        <div class="red-layout-builder__library-header">
                            <div>
                                <span>Library</span>
                                <strong><?= count($templateLayouts) + count($customLayouts) ?> layouts</strong>
                            </div>
                            <button type="button" class="red-layout-builder__new" data-red-layout-new>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
                                New
                            </button>
                        </div>

                        <section class="red-layout-builder__library-group">
                            <h3>My layouts</h3>
                            <?php if ($customLayouts === []) : ?>
                                <p class="red-layout-builder__library-empty">No custom layouts yet. Start blank or copy a template layout.</p>
                            <?php else : ?>
                                <div class="red-layout-builder__library-list">
                                    <?php foreach ($customLayouts as $layout) : ?>
                                        <?php
                                        $classes = ['red-layout-builder__library-item'];
                                        if ($selected && $selected['layoutId'] === $layout['layoutId']) {
                                            $classes[] = 'is-selected';
                                        }
                                        if ($layout['archived']) {
                                            $classes[] = 'is-archived';
                                        }
                                        $stateLabel = $layout['archived']
                                            ? 'Archived'
                                            : ($layout['published']
                                                ? ($layout['hasUnpublishedChanges'] ? 'Published · changes' : 'Published')
                                                : 'Draft');
                                        ?>
                                        <button type="button" class="<?= implode(' ', $classes) ?>"
                                            data-red-layout-open="<?= red_admin_custom_layout_html($layout['layoutId']) ?>">
                                            <span class="red-layout-builder__library-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="6" rx="1.5"></rect><rect x="3" y="14" width="8" height="6" rx="1.5"></rect><rect x="13" y="14" width="8" height="6" rx="1.5"></rect></svg>
                                            </span>
                                            <span class="red-layout-builder__library-copy">
                                                <strong><?= red_admin_custom_layout_html($layout['label']) ?></strong>
                                                <small><?= red_admin_custom_layout_html($layout['layoutId']) ?></small>
                                            </span>
                                            <span class="red-layout-builder__library-state"><?= red_admin_custom_layout_html($stateLabel) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="red-layout-builder__library-group">
                            <h3>Template layouts</h3>
                            <div class="red-layout-builder__library-list">
                                <?php foreach ($templateLayouts as $layout) : ?>
                                    <button type="button" class="red-layout-builder__library-item red-layout-builder__library-item--template"
                                        data-red-layout-copy="<?= red_admin_custom_layout_html($layout['layoutId']) ?>">
                                        <span class="red-layout-builder__library-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="14" rx="2"></rect><path d="M9 21h8a2 2 0 0 0 2-2v-8"></path></svg>
                                        </span>
                                        <span class="red-layout-builder__library-copy">
                                            <strong><?= red_admin_custom_layout_html($layout['label']) ?></strong>
                                            <small><?= (int) $layout['positionCount'] ?> positions</small>
                                        </span>
                                        <span class="red-layout-builder__library-state">Copy</span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </aside>

                    <div class="red-layout-builder__workspace">
                        <div class="red-layout-builder__workspace-bar">
                            <div>
                                <span class="red-layout-builder__workspace-label">Working layout</span>
                                <strong data-red-layout-working-title><?= red_admin_custom_layout_html($selected['draftLabel'] ?? 'Untitled layout') ?></strong>
                            </div>
                            <span class="red-layout-builder__status" data-red-layout-state-label><?= red_admin_custom_layout_html($selectedStatus) ?></span>
                        </div>

                        <div class="red-layout-builder__identity">
                            <label>
                                <span>Layout name</span>
                                <input type="text" maxlength="120" value="<?= red_admin_custom_layout_html($selected['draftLabel'] ?? '') ?>"
                                    placeholder="Example: Services with sidebar" data-red-layout-label>
                            </label>
                            <label>
                                <span>Stable layout ID</span>
                                <div class="red-layout-builder__id-field">
                                    <span>custom-</span>
                                    <input type="text" maxlength="56"
                                        value="<?= red_admin_custom_layout_html($selected ? substr($selected['layoutId'], 7) : '') ?>"
                                        placeholder="services-sidebar" data-red-layout-id <?= $selected ? 'readonly' : '' ?>>
                                </div>
                                <small><?= $selected ? 'The ID stays fixed after the first save.' : 'Generated from the name; lowercase letters, numbers, and hyphens.' ?></small>
                            </label>
                        </div>

                        <div class="red-layout-builder__authoring">
                            <section class="red-layout-builder__palette" aria-labelledby="red-layout-builder-palette-title">
                                <div class="red-layout-builder__section-heading">
                                    <div>
                                        <span>Step 1</span>
                                        <h3 id="red-layout-builder-palette-title">Add a row</h3>
                                    </div>
                                    <p>Click a pattern or drag it into the canvas.</p>
                                </div>
                                <div class="red-layout-builder__presets" data-red-layout-presets></div>
                            </section>

                            <section class="red-layout-builder__canvas-section" aria-labelledby="red-layout-builder-canvas-title">
                                <div class="red-layout-builder__section-heading">
                                    <div>
                                        <span>Step 2</span>
                                        <h3 id="red-layout-builder-canvas-title">Arrange the grid</h3>
                                    </div>
                                    <p>Drag rows and columns, adjust spans, and name every content position.</p>
                                </div>
                                <div class="red-layout-builder__canvas" data-red-layout-canvas
                                    aria-label="Layout rows" aria-describedby="red-layout-builder-validation"></div>
                                <div class="red-layout-builder__validation" id="red-layout-builder-validation"
                                    data-red-layout-validation role="status" aria-live="polite"></div>
                            </section>

                            <section class="red-layout-builder__preview-section" aria-labelledby="red-layout-builder-preview-title">
                                <div class="red-layout-builder__section-heading red-layout-builder__section-heading--preview">
                                    <div>
                                        <span>Step 3</span>
                                        <h3 id="red-layout-builder-preview-title">Preview map</h3>
                                    </div>
                                    <div class="red-layout-builder__preview-switch" role="group" aria-label="Preview size">
                                        <button type="button" class="is-active" data-red-layout-preview-mode="desktop">Desktop</button>
                                        <button type="button" data-red-layout-preview-mode="mobile">Mobile</button>
                                    </div>
                                </div>
                                <div class="red-layout-builder__preview" data-red-layout-preview data-preview-mode="desktop">
                                    <div class="red-layout-builder__preview-browser">
                                        <span></span><span></span><span></span>
                                    </div>
                                    <div class="red-layout-builder__preview-map" data-red-layout-preview-map></div>
                                </div>
                                <p class="red-layout-builder__preview-note">Desktop follows the 12-unit grid. Columns stack in position order on smaller screens.</p>
                            </section>
                        </div>

                        <?php if ($selected) : ?>
                            <details class="red-layout-builder__history">
                                <summary>
                                    <span>
                                        <strong>Version history</strong>
                                        <small><?= count($selected['history']) ?> recent versions</small>
                                    </span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"></path></svg>
                                </summary>
                                <div class="red-layout-builder__history-list">
                                    <?php foreach ($selected['history'] as $index => $revision) : ?>
                                        <article class="red-layout-builder__history-item">
                                            <span class="red-layout-builder__history-number">v<?= (int) $revision['revisionNumber'] ?></span>
                                            <div>
                                                <strong><?= red_admin_custom_layout_html(ucfirst($revision['operation'])) ?></strong>
                                                <small><?= red_admin_custom_layout_html($revision['actorAlias']) ?> · <?= red_admin_custom_layout_html($revision['createdAt']) ?></small>
                                            </div>
                                            <?php if ($index > 0 || $selected['archived']) : ?>
                                                <button type="button" data-red-layout-restore-version="<?= (int) $revision['revisionId'] ?>">Restore as draft</button>
                                            <?php else : ?>
                                                <span class="red-layout-builder__history-current">Current</span>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>

                        <div class="red-layout-builder__actions">
                            <div class="red-layout-builder__actions-secondary">
                                <?php if ($selected) : ?>
                                    <button type="button" class="red-layout-builder__archive"
                                        data-red-layout-archive="<?= $selected['archived'] ? '0' : '1' ?>">
                                        <?= $selected['archived'] ? 'Restore layout' : 'Archive layout' ?>
                                    </button>
                                <?php endif; ?>
                                <span class="red-layout-builder__save-message" data-red-layout-message role="status" aria-live="polite"></span>
                            </div>
                            <div class="red-layout-builder__actions-primary">
                                <button type="button" class="red-layout-builder__save" data-red-layout-save>Save draft</button>
                                <button type="button" class="red-layout-builder__publish" data-red-layout-publish
                                    <?= (!$selected || $selected['archived'] || !$standardThemeActive) ? 'disabled' : '' ?>>
                                    Publish layout
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }
}

?>
