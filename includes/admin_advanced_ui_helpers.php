<?php
/**
 * Shared presentation for identity, source-oriented, and managed-logo Advanced editors.
 *
 * The existing RED_Advanced records and Website CSS file target remain the
 * authoritative sources. This helper changes presentation only.
 */

require_once __DIR__ . '/admin_advanced_helpers.php';
require_once __DIR__ . '/site_logo_helpers.php';

if (!function_exists('red_admin_advanced_editor_definition')) {
    function red_admin_advanced_editor_definition($item)
    {
        $definitions = [
            'Website_CSS' => [
                'kind' => 'css',
                'eyebrow' => 'Design system',
                'title' => 'Website CSS',
                'description' => 'Adjust the active theme stylesheet with a focused, readable source workspace.',
                'badge' => 'CSS',
                'panelTitle' => 'Stylesheet source',
                'panelDescription' => 'Changes affect the active public theme after Save.',
                'fieldLabel' => 'CSS source',
                'fieldHelp' => 'Use two spaces for indentation. The Tab key inserts spaces inside this editor.',
                'textareaName' => 'CSS',
                'textareaId' => 'CSS',
                'saveLabel' => 'Save Website CSS',
                'placement' => 'Active public theme',
                'warningTitle' => 'Theme-level change',
                'warningCopy' => 'Invalid CSS can affect every public page. Review selectors and responsive rules before saving.',
                'icon' => '<path d="m8 7-4 5 4 5M16 7l4 5-4 5M14 4l-4 16"></path>',
            ],
            'Website_Header' => [
                'kind' => 'header',
                'eyebrow' => 'Global structure',
                'title' => 'Website Header',
                'description' => 'Manage the trusted HTML rendered inside the active theme header region.',
                'badge' => 'HTML',
                'panelTitle' => 'Header HTML',
                'panelDescription' => 'This source appears in the public header on every page.',
                'fieldLabel' => 'Header HTML source',
                'fieldHelp' => 'Use complete, valid HTML. Scripts and inline behavior are treated as trusted site code.',
                'textareaName' => 'Content',
                'textareaId' => 'Content',
                'saveLabel' => 'Save Website Header',
                'placement' => 'Public header region',
                'warningTitle' => 'Trusted HTML',
                'warningCopy' => 'Unclosed elements or global scripts can affect navigation and page layout. Save only reviewed markup.',
                'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 9h18M7 6.5h.01M10 6.5h.01"></path>',
            ],
            'Website_Footer' => [
                'kind' => 'footer',
                'eyebrow' => 'Global structure',
                'title' => 'Website Footer',
                'description' => 'Manage the trusted HTML rendered inside the active theme footer region.',
                'badge' => 'HTML',
                'panelTitle' => 'Footer HTML',
                'panelDescription' => 'This source appears near the end of every public page.',
                'fieldLabel' => 'Footer HTML source',
                'fieldHelp' => 'Use complete, valid HTML for contact details, legal links, credits, or supporting navigation.',
                'textareaName' => 'Content',
                'textareaId' => 'Content',
                'saveLabel' => 'Save Website Footer',
                'placement' => 'Public footer region',
                'warningTitle' => 'Trusted HTML',
                'warningCopy' => 'Unclosed elements or global scripts can affect the rest of the document. Save only reviewed markup.',
                'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 15h18M7 17.5h.01M10 17.5h.01"></path>',
            ],
        ];

        return $definitions[$item] ?? null;
    }
}

if (!function_exists('red_admin_advanced_identity_definition')) {
    function red_admin_advanced_identity_definition($item)
    {
        $definitions = [
            'Website_Title' => [
                'kind' => 'title',
                'eyebrow' => 'Brand identity',
                'title' => 'Website Title',
                'description' => 'Set the primary public name used in browser titles and compatible template headers.',
                'badge' => 'Site name',
                'fieldLabel' => 'Website title',
                'fieldHelp' => 'Use the official public name of the website. Keep it recognizable when space is limited.',
                'placeholder' => 'Your website name',
                'saveLabel' => 'Save Website Title',
                'placement' => 'Browser titles and site identity',
                'panelTitle' => 'Primary website name',
                'panelDescription' => 'A short, stable name is easiest to recognize across tabs, headers, and search results.',
                'previewTitle' => 'Browser title preview',
                'previewCopy' => 'This preview shows the name as a compact browser or template label.',
                'emptyPreview' => 'Untitled website',
                'emptyGuidance' => 'A template fallback will be used while this field is empty.',
                'idealMin' => 3,
                'idealMax' => 60,
                'multiline' => false,
                'warningTitle' => 'One shared identity',
                'warningCopy' => 'Changing this value can update browser titles and compatible public headers. Use the same official name across languages when appropriate.',
                'icon' => '<path d="M4 6h16M12 6v12M8 18h8"></path>',
            ],
            'Website_Slogan' => [
                'kind' => 'slogan',
                'eyebrow' => 'Brand voice',
                'title' => 'Website Slogan',
                'description' => 'Write the concise supporting message displayed with the website title where the template enables it.',
                'badge' => 'Tagline',
                'fieldLabel' => 'Website slogan',
                'fieldHelp' => 'Express the promise or purpose in one clear sentence. Plain text works best.',
                'placeholder' => 'A concise statement of purpose',
                'saveLabel' => 'Save Website Slogan',
                'placement' => 'Supporting brand message',
                'panelTitle' => 'Supporting statement',
                'panelDescription' => 'Keep the message specific, memorable, and short enough for compact layouts.',
                'previewTitle' => 'Slogan preview',
                'previewCopy' => 'This preview shows the line as a supporting brand statement.',
                'emptyPreview' => 'No slogan configured',
                'emptyGuidance' => 'The slogan is optional; templates can omit it while this field is empty.',
                'idealMin' => 10,
                'idealMax' => 100,
                'multiline' => true,
                'warningTitle' => 'Keep it focused',
                'warningCopy' => 'Use plain text and avoid repeating the website title. A specific promise is more useful than a broad marketing phrase.',
                'icon' => '<path d="M5 6h14M5 10h10M5 14h12M5 18h7"></path>',
            ],
        ];

        return $definitions[$item] ?? null;
    }
}

if (!function_exists('red_admin_render_advanced_source_editor')) {
    function red_admin_render_advanced_source_editor(array $context)
    {
        $item = red_admin_text($context['item'] ?? '');
        $definition = red_admin_advanced_editor_definition($item);
        if ($definition === null) {
            return false;
        }

        $content = red_admin_advanced_scalar($context['content'] ?? '');
        $recordId = (int) ($context['recordId'] ?? 0);
        $language = red_admin_advanced_language($context['language'] ?? '');
        $csrfToken = red_admin_advanced_scalar($context['csrfToken'] ?? '');
        $cssTarget = is_array($context['cssTarget'] ?? null) ? $context['cssTarget'] : null;
        $cssTargetToken = red_admin_advanced_scalar($context['cssTargetToken'] ?? '');
        $available = $item !== 'Website_CSS'
            || ($cssTarget !== null && $cssTargetToken !== '');
        $scriptPath = '/admin/assets/js/advanced-editor.js';
        $scriptVersion = is_file($_SERVER['DOCUMENT_ROOT'] . $scriptPath)
            ? filemtime($_SERVER['DOCUMENT_ROOT'] . $scriptPath)
            : '1';
        ?>
        <div class="red-admin-advanced-editor-shell red-admin-advanced-editor-shell--<?php echo red_admin_advanced_html($definition['kind']); ?>">
            <div class="cp_viewall red-admin-advanced-editor-return">
                <button type="button" class="red-admin-advanced-editor-return__button" onclick="showdiv('edit_advanced_grid'); return false;">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6"></path></svg>
                    <span>Advanced items</span>
                </button>
                <span class="red-admin-advanced-editor-return__divider" aria-hidden="true">/</span>
                <span aria-current="page"><?php echo red_admin_advanced_html($definition['title']); ?></span>
            </div>

            <form
                id="update_advanced"
                name="update_advanced"
                class="cp red-admin-advanced-editor"
                method="post"
                data-red-advanced-editor
                data-editor-kind="<?php echo red_admin_advanced_html($definition['kind']); ?>"
                data-item-label="<?php echo red_admin_advanced_html($definition['title']); ?>"
                data-submit-url="/admin/bin/update_advanced.php"
                onsubmit="return run_update_advanced(this);"
            >
                <fieldset>
                    <legend class="red-admin-visually-hidden">Edit <?php echo red_admin_advanced_html($definition['title']); ?></legend>

                    <header class="red-admin-advanced-editor__hero">
                        <span class="red-admin-advanced-editor__hero-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><?php echo $definition['icon']; ?></svg>
                        </span>
                        <div class="red-admin-advanced-editor__hero-copy">
                            <span class="red-admin-advanced-editor__eyebrow"><?php echo red_admin_advanced_html($definition['eyebrow']); ?></span>
                            <h2><?php echo red_admin_advanced_html($definition['title']); ?></h2>
                            <p><?php echo red_admin_advanced_html($definition['description']); ?></p>
                        </div>
                        <span class="red-admin-advanced-editor__badge"><?php echo red_admin_advanced_html($definition['badge']); ?></span>
                    </header>

                    <section class="red-admin-advanced-context" aria-label="Editor context">
                        <div class="red-admin-advanced-context__item">
                            <span>Placement</span>
                            <strong><?php echo red_admin_advanced_html($definition['placement']); ?></strong>
                        </div>
                        <div class="red-admin-advanced-context__item">
                            <span>Language</span>
                            <strong><?php echo red_admin_advanced_html(strtoupper($language)); ?></strong>
                        </div>
                        <?php if ($item === 'Website_CSS' && $cssTarget !== null) { ?>
                            <div class="red-admin-advanced-context__item red-admin-advanced-context__item--wide">
                                <span>Active theme</span>
                                <strong><?php echo red_admin_advanced_html($cssTarget['themeName'] ?? ''); ?></strong>
                                <code><?php echo red_admin_advanced_html($cssTarget['themeId'] ?? ''); ?></code>
                            </div>
                            <div class="red-admin-advanced-context__item red-admin-advanced-context__item--wide">
                                <span>Editing file</span>
                                <code><?php echo red_admin_advanced_html($cssTarget['displayPath'] ?? ''); ?></code>
                            </div>
                        <?php } else { ?>
                            <div class="red-admin-advanced-context__item red-admin-advanced-context__item--wide">
                                <span>Source record</span>
                                <code><?php echo red_admin_advanced_html($item); ?> · #<?php echo $recordId; ?></code>
                            </div>
                        <?php } ?>
                    </section>

                    <?php if ($item === 'Website_CSS' && !empty($cssTarget['usedFallback'])) { ?>
                        <div class="red-admin-advanced-notice red-admin-advanced-notice--warning" role="status">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 4.5 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0Z"></path></svg>
                            <div><strong>Fallback stylesheet</strong><p>The configured theme is unavailable, so this editor shows the public legacy fallback stylesheet.</p></div>
                        </div>
                    <?php } ?>

                    <?php if (!$available) { ?>
                        <div class="red-admin-advanced-empty" role="alert">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 4.5 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0Z"></path></svg>
                            <strong>No editable local stylesheet is available</strong>
                            <p>Activate a valid local theme with a declared stylesheet, then reopen Website CSS.</p>
                        </div>
                    <?php } else { ?>
                        <section class="red-admin-advanced-source-panel" aria-labelledby="red-admin-advanced-source-title">
                            <div class="red-admin-advanced-source-panel__heading">
                                <div>
                                    <span class="red-admin-advanced-source-panel__step">01</span>
                                    <div>
                                        <h3 id="red-admin-advanced-source-title"><?php echo red_admin_advanced_html($definition['panelTitle']); ?></h3>
                                        <p><?php echo red_admin_advanced_html($definition['panelDescription']); ?></p>
                                    </div>
                                </div>
                                <span class="red-admin-advanced-editor-status" data-advanced-editor-status data-state="ready" role="status" aria-live="polite">Saved source</span>
                            </div>

                            <div class="red-admin-advanced-source-toolbar">
                                <div class="red-admin-advanced-source-toolbar__counts" aria-live="polite">
                                    <span><strong data-advanced-line-count>0</strong> lines</span>
                                    <span><strong data-advanced-character-count>0</strong> characters</span>
                                </div>
                                <button type="button" class="red-admin-advanced-copy" data-advanced-copy>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>
                                    <span data-advanced-copy-label>Copy source</span>
                                </button>
                            </div>

                            <label class="red-admin-advanced-source-field" for="<?php echo red_admin_advanced_html($definition['textareaId']); ?>">
                                <span><?php echo red_admin_advanced_html($definition['fieldLabel']); ?></span>
                                <textarea
                                    name="<?php echo red_admin_advanced_html($definition['textareaName']); ?>"
                                    id="<?php echo red_admin_advanced_html($definition['textareaId']); ?>"
                                    rows="<?php echo $item === 'Website_CSS' ? '30' : '18'; ?>"
                                    spellcheck="false"
                                    autocapitalize="off"
                                    autocomplete="off"
                                    aria-describedby="red-admin-advanced-source-help"
                                    data-advanced-source
                                ><?php echo red_admin_advanced_html($content); ?></textarea>
                            </label>
                            <div class="red-admin-advanced-source-footer" id="red-admin-advanced-source-help">
                                <span><?php echo red_admin_advanced_html($definition['fieldHelp']); ?></span>
                                <code><?php echo red_admin_advanced_html($definition['badge']); ?></code>
                            </div>
                        </section>

                        <aside class="red-admin-advanced-notice" aria-label="<?php echo red_admin_advanced_html($definition['warningTitle']); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 4.5 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0Z"></path></svg>
                            <div><strong><?php echo red_admin_advanced_html($definition['warningTitle']); ?></strong><p><?php echo red_admin_advanced_html($definition['warningCopy']); ?></p></div>
                        </aside>

                        <?php echo red_csrf_input(); ?>
                        <?php if ($item === 'Website_CSS') { ?>
                            <input type="hidden" name="css_target_token" value="<?php echo red_admin_advanced_html($cssTargetToken); ?>">
                        <?php } ?>
                        <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $recordId; ?>">
                        <input type="hidden" name="Item" id="Item" value="<?php echo red_admin_advanced_html($item); ?>">

                        <div class="red-admin-advanced-actions">
                            <span id="msggbox_update_advanced" class="red-admin-advanced-message" role="status" aria-live="polite" hidden></span>
                            <button type="submit" name="submit" value="Save" id="save" class="red-admin-advanced-save">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5V4Zm3 0v6h8V4M8 20v-6h8v6"></path></svg>
                                <span><?php echo red_admin_advanced_html($definition['saveLabel']); ?></span>
                            </button>
                        </div>
                    <?php } ?>
                </fieldset>
            </form>
        </div>
        <script src="<?php echo red_admin_advanced_html($scriptPath); ?>?v=<?php echo rawurlencode((string) $scriptVersion); ?>"></script>
        <?php
        return true;
    }
}

if (!function_exists('red_admin_render_advanced_identity_editor')) {
    function red_admin_render_advanced_identity_editor(array $context)
    {
        $item = red_admin_text($context['item'] ?? '');
        $definition = red_admin_advanced_identity_definition($item);
        if ($definition === null) {
            return false;
        }

        $content = red_admin_advanced_scalar($context['content'] ?? '');
        $recordId = (int) ($context['recordId'] ?? 0);
        $language = red_admin_advanced_language($context['language'] ?? '');
        $csrfToken = red_admin_advanced_scalar($context['csrfToken'] ?? '');
        $scriptPath = '/admin/assets/js/advanced-identity.js';
        $scriptVersion = is_file($_SERVER['DOCUMENT_ROOT'] . $scriptPath)
            ? filemtime($_SERVER['DOCUMENT_ROOT'] . $scriptPath)
            : '1';
        ?>
        <div class="red-admin-advanced-editor-shell red-admin-advanced-identity-shell red-admin-advanced-identity-shell--<?php echo red_admin_advanced_html($definition['kind']); ?>">
            <div class="cp_viewall red-admin-advanced-editor-return">
                <button type="button" class="red-admin-advanced-editor-return__button" onclick="showdiv('edit_advanced_grid'); return false;">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6"></path></svg>
                    <span>Advanced items</span>
                </button>
                <span class="red-admin-advanced-editor-return__divider" aria-hidden="true">/</span>
                <span aria-current="page"><?php echo red_admin_advanced_html($definition['title']); ?></span>
            </div>

            <form
                id="update_advanced"
                name="update_advanced"
                class="cp red-admin-advanced-editor red-admin-advanced-identity"
                method="post"
                data-red-advanced-identity
                data-item-label="<?php echo red_admin_advanced_html($definition['title']); ?>"
                data-submit-url="/admin/bin/update_advanced.php"
                data-saved-value="<?php echo red_admin_advanced_html($content); ?>"
                data-ideal-min="<?php echo (int) $definition['idealMin']; ?>"
                data-ideal-max="<?php echo (int) $definition['idealMax']; ?>"
                data-empty-preview="<?php echo red_admin_advanced_html($definition['emptyPreview']); ?>"
                data-empty-guidance="<?php echo red_admin_advanced_html($definition['emptyGuidance']); ?>"
                onsubmit="return run_update_advanced(this);"
            >
                <fieldset>
                    <legend class="red-admin-visually-hidden">Edit <?php echo red_admin_advanced_html($definition['title']); ?></legend>

                    <header class="red-admin-advanced-editor__hero">
                        <span class="red-admin-advanced-editor__hero-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><?php echo $definition['icon']; ?></svg>
                        </span>
                        <div class="red-admin-advanced-editor__hero-copy">
                            <span class="red-admin-advanced-editor__eyebrow"><?php echo red_admin_advanced_html($definition['eyebrow']); ?></span>
                            <h2><?php echo red_admin_advanced_html($definition['title']); ?></h2>
                            <p><?php echo red_admin_advanced_html($definition['description']); ?></p>
                        </div>
                        <span class="red-admin-advanced-editor__badge"><?php echo red_admin_advanced_html($definition['badge']); ?></span>
                    </header>

                    <section class="red-admin-advanced-context" aria-label="Editor context">
                        <div class="red-admin-advanced-context__item">
                            <span>Placement</span>
                            <strong><?php echo red_admin_advanced_html($definition['placement']); ?></strong>
                        </div>
                        <div class="red-admin-advanced-context__item">
                            <span>Language</span>
                            <strong><?php echo red_admin_advanced_html(strtoupper($language)); ?></strong>
                        </div>
                        <div class="red-admin-advanced-context__item red-admin-advanced-context__item--wide">
                            <span>Source record</span>
                            <code><?php echo red_admin_advanced_html($item); ?> · #<?php echo $recordId; ?></code>
                        </div>
                    </section>

                    <div class="red-admin-advanced-identity-grid">
                        <section class="red-admin-advanced-identity-card" aria-labelledby="red-admin-advanced-identity-field-title">
                            <div class="red-admin-advanced-source-panel__heading">
                                <div>
                                    <span class="red-admin-advanced-source-panel__step">01</span>
                                    <div>
                                        <h3 id="red-admin-advanced-identity-field-title"><?php echo red_admin_advanced_html($definition['panelTitle']); ?></h3>
                                        <p><?php echo red_admin_advanced_html($definition['panelDescription']); ?></p>
                                    </div>
                                </div>
                                <span class="red-admin-advanced-editor-status" data-advanced-identity-status data-state="ready" role="status" aria-live="polite">Saved value</span>
                            </div>

                            <label class="red-admin-advanced-identity-field" for="ShortLine">
                                <span><?php echo red_admin_advanced_html($definition['fieldLabel']); ?></span>
                                <?php if (!empty($definition['multiline'])) { ?>
                                    <textarea
                                        name="ShortLine"
                                        id="ShortLine"
                                        rows="4"
                                        placeholder="<?php echo red_admin_advanced_html($definition['placeholder']); ?>"
                                        aria-describedby="red-admin-advanced-identity-help"
                                        data-advanced-identity-input
                                    ><?php echo red_admin_advanced_html($content); ?></textarea>
                                <?php } else { ?>
                                    <input
                                        type="text"
                                        name="ShortLine"
                                        id="ShortLine"
                                        value="<?php echo red_admin_advanced_html($content); ?>"
                                        placeholder="<?php echo red_admin_advanced_html($definition['placeholder']); ?>"
                                        aria-describedby="red-admin-advanced-identity-help"
                                        data-advanced-identity-input
                                    >
                                <?php } ?>
                            </label>

                            <div class="red-admin-advanced-identity-meta" id="red-admin-advanced-identity-help">
                                <span><?php echo red_admin_advanced_html($definition['fieldHelp']); ?></span>
                                <span class="red-admin-advanced-identity-count"><strong data-advanced-identity-count>0</strong> characters</span>
                            </div>
                            <p class="red-admin-advanced-identity-guidance" data-advanced-identity-guidance data-state="ready" aria-live="polite"></p>

                            <div class="red-admin-advanced-identity-tools">
                                <button type="button" class="red-admin-advanced-copy" data-advanced-identity-copy>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>
                                    <span data-advanced-identity-copy-label>Copy value</span>
                                </button>
                                <button type="button" class="red-admin-advanced-identity-restore" data-advanced-identity-restore disabled>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.3-5.7L4 8.6M4 4v4.6h4.6"></path></svg>
                                    <span>Restore saved</span>
                                </button>
                            </div>
                        </section>

                        <aside class="red-admin-advanced-identity-preview" aria-labelledby="red-admin-advanced-identity-preview-title">
                            <div class="red-admin-advanced-identity-preview__heading">
                                <span class="red-admin-advanced-source-panel__step">02</span>
                                <div>
                                    <h3 id="red-admin-advanced-identity-preview-title"><?php echo red_admin_advanced_html($definition['previewTitle']); ?></h3>
                                    <p><?php echo red_admin_advanced_html($definition['previewCopy']); ?></p>
                                </div>
                            </div>
                            <div class="red-admin-advanced-identity-preview__canvas" data-preview-kind="<?php echo red_admin_advanced_html($definition['kind']); ?>">
                                <?php if ($definition['kind'] === 'title') { ?>
                                    <div class="red-admin-advanced-identity-browser">
                                        <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
                                        <strong data-advanced-identity-preview><?php echo red_admin_advanced_html($content !== '' ? $content : $definition['emptyPreview']); ?></strong>
                                    </div>
                                    <div class="red-admin-advanced-identity-browser__page">
                                        <span>Website identity</span>
                                        <strong data-advanced-identity-preview><?php echo red_admin_advanced_html($content !== '' ? $content : $definition['emptyPreview']); ?></strong>
                                    </div>
                                <?php } else { ?>
                                    <div class="red-admin-advanced-identity-slogan">
                                        <span>Supporting message</span>
                                        <strong data-advanced-identity-preview><?php echo red_admin_advanced_html($content !== '' ? $content : $definition['emptyPreview']); ?></strong>
                                        <i aria-hidden="true"></i>
                                    </div>
                                <?php } ?>
                            </div>
                        </aside>
                    </div>

                    <aside class="red-admin-advanced-notice" aria-label="<?php echo red_admin_advanced_html($definition['warningTitle']); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5m0-8h.01"></path></svg>
                        <div><strong><?php echo red_admin_advanced_html($definition['warningTitle']); ?></strong><p><?php echo red_admin_advanced_html($definition['warningCopy']); ?></p></div>
                    </aside>

                    <?php echo red_csrf_input(); ?>
                    <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $recordId; ?>">
                    <input type="hidden" name="Item" id="Item" value="<?php echo red_admin_advanced_html($item); ?>">

                    <div class="red-admin-advanced-actions">
                        <span id="msggbox_update_advanced" class="red-admin-advanced-message" role="status" aria-live="polite" hidden></span>
                        <button type="submit" name="submit" value="Save" id="save" class="red-admin-advanced-save">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5V4Zm3 0v6h8V4M8 20v-6h8v6"></path></svg>
                            <span><?php echo red_admin_advanced_html($definition['saveLabel']); ?></span>
                        </button>
                    </div>
                </fieldset>
            </form>
        </div>
        <script src="<?php echo red_admin_advanced_html($scriptPath); ?>?v=<?php echo rawurlencode((string) $scriptVersion); ?>"></script>
        <?php
        return true;
    }
}

if (!function_exists('red_admin_render_advanced_credit_editor')) {
    function red_admin_render_advanced_credit_editor(array $context)
    {
        $content = strtoupper(red_admin_advanced_scalar($context['content'] ?? 'Y')) === 'N' ? 'N' : 'Y';
        $recordId = (int) ($context['recordId'] ?? 0);
        $language = red_admin_advanced_language($context['language'] ?? '');
        $scriptPath = '/admin/assets/js/advanced-credit.js';
        $scriptVersion = is_file($_SERVER['DOCUMENT_ROOT'] . $scriptPath)
            ? filemtime($_SERVER['DOCUMENT_ROOT'] . $scriptPath)
            : '1';
        ?>
        <div class="red-admin-advanced-editor-shell red-admin-advanced-credit-shell">
            <div class="cp_viewall red-admin-advanced-editor-return">
                <button type="button" class="red-admin-advanced-editor-return__button" onclick="showdiv('edit_advanced_grid'); return false;">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6"></path></svg>
                    <span>Advanced items</span>
                </button>
                <span class="red-admin-advanced-editor-return__divider" aria-hidden="true">/</span>
                <span aria-current="page">Red Sphere Website Credit</span>
            </div>

            <form
                id="update_advanced"
                name="update_advanced"
                class="cp red-admin-advanced-editor red-admin-advanced-credit"
                method="post"
                data-red-advanced-credit
                data-saved-value="<?php echo red_admin_advanced_html($content); ?>"
                data-item-label="Red Sphere website credit"
                data-submit-url="/admin/bin/update_advanced.php"
                onsubmit="return run_update_advanced(this);"
            >
                <fieldset>
                    <legend class="red-admin-visually-hidden">Edit Red Sphere website credit</legend>

                    <header class="red-admin-advanced-editor__hero">
                        <span class="red-admin-advanced-editor__hero-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.1 1.1"></path><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.1-1.1"></path></svg>
                        </span>
                        <div class="red-admin-advanced-editor__hero-copy">
                            <span class="red-admin-advanced-editor__eyebrow">Site attribution</span>
                            <h2>Red Sphere Website Credit</h2>
                            <p>Keep a quiet design signature at the bottom of every public page, or remove it for clients who request an unbranded footer.</p>
                        </div>
                        <span class="red-admin-advanced-editor__badge">Webmaster</span>
                    </header>

                    <section class="red-admin-advanced-context" aria-label="Website credit context">
                        <div class="red-admin-advanced-context__item">
                            <span>Placement</span>
                            <strong>Bottom of every public page</strong>
                        </div>
                        <div class="red-admin-advanced-context__item">
                            <span>Language</span>
                            <strong><?php echo red_admin_advanced_html(strtoupper($language)); ?></strong>
                        </div>
                        <div class="red-admin-advanced-context__item red-admin-advanced-context__item--wide">
                            <span>Destination</span>
                            <code>https://www.red-sphere.com</code>
                        </div>
                    </section>

                    <div class="red-admin-advanced-credit-grid">
                        <section class="red-admin-advanced-credit-card" aria-labelledby="red-admin-credit-choice-title">
                            <div class="red-admin-advanced-source-panel__heading">
                                <div>
                                    <span class="red-admin-advanced-source-panel__step">01</span>
                                    <div>
                                        <h3 id="red-admin-credit-choice-title">Choose footer attribution</h3>
                                        <p>The signature is visible by default and remains intentionally subtle.</p>
                                    </div>
                                </div>
                                <span class="red-admin-advanced-editor-status" data-advanced-credit-status data-state="ready" role="status" aria-live="polite">Saved value</span>
                            </div>

                            <div class="red-admin-advanced-credit-options">
                                <label class="red-admin-advanced-credit-option" data-credit-option>
                                    <input type="radio" name="ShortLine" value="Y" <?php echo $content === 'Y' ? 'checked' : ''; ?>>
                                    <span class="red-admin-advanced-credit-option__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M5 12.5 9.2 17 19 7"></path></svg>
                                    </span>
                                    <span><strong>Show signature</strong><small>Display “Web by Red Sphere” beneath the active template footer.</small></span>
                                </label>
                                <label class="red-admin-advanced-credit-option" data-credit-option>
                                    <input type="radio" name="ShortLine" value="N" <?php echo $content === 'N' ? 'checked' : ''; ?>>
                                    <span class="red-admin-advanced-credit-option__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"></path></svg>
                                    </span>
                                    <span><strong>Remove signature</strong><small>Keep the client footer completely free of Red Sphere attribution.</small></span>
                                </label>
                            </div>
                        </section>

                        <aside class="red-admin-advanced-credit-preview" aria-labelledby="red-admin-credit-preview-title">
                            <div class="red-admin-advanced-identity-preview__heading">
                                <span class="red-admin-advanced-source-panel__step">02</span>
                                <div>
                                    <h3 id="red-admin-credit-preview-title">Footer preview</h3>
                                    <p>The published mark stays intentionally low contrast so it does not compete with the client footer.</p>
                                </div>
                            </div>
                            <div class="red-admin-advanced-credit-preview__canvas">
                                <div class="red-admin-advanced-credit-preview__footer" aria-hidden="true">
                                    <span></span><span></span><span></span>
                                </div>
                                <div class="red-admin-advanced-credit-preview__signature" data-credit-preview>
                                    <small>WEB BY</small>
                                    <img src="/admin/images/red-tm.png" alt="" width="46" height="22">
                                    <span><strong>RED</strong> SPHERE</span>
                                </div>
                                <p data-credit-preview-message></p>
                            </div>
                        </aside>
                    </div>

                    <aside class="red-admin-advanced-notice" aria-label="Template independence">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 7v5c0 4.8 3.2 7.7 8 9 4.8-1.3 8-4.2 8-9V7l-8-4Z"></path><path d="m8.5 12 2.2 2.2 4.8-5"></path></svg>
                        <div><strong>Template-independent credit</strong><p>The CMS adds this signature after the selected template footer, so client theme styles do not need to include or maintain it.</p></div>
                    </aside>

                    <?php echo red_csrf_input(); ?>
                    <input type="hidden" name="RecordID" value="<?php echo $recordId; ?>">
                    <input type="hidden" name="Item" value="Website_Red_Sphere_Credit">

                    <div class="red-admin-advanced-actions">
                        <span id="msggbox_update_advanced" class="red-admin-advanced-message" role="status" aria-live="polite" hidden></span>
                        <button type="submit" name="submit" value="Save" id="save" class="red-admin-advanced-save">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5V4Zm3 0v6h8V4M8 20v-6h8v6"></path></svg>
                            <span>Save Website Credit</span>
                        </button>
                    </div>
                </fieldset>
            </form>
        </div>
        <script src="<?php echo red_admin_advanced_html($scriptPath); ?>?v=<?php echo rawurlencode((string) $scriptVersion); ?>"></script>
        <?php
        return true;
    }
}

if (!function_exists('red_admin_render_advanced_logo_editor')) {
    function red_admin_render_advanced_logo_editor(array $context)
    {
        $recordId = (int) ($context['recordId'] ?? 0);
        $language = red_admin_advanced_language($context['language'] ?? '');
        $csrfToken = red_admin_advanced_scalar($context['csrfToken'] ?? '');
        $fact = red_site_logo_fact(
            $_SERVER['DOCUMENT_ROOT'],
            red_admin_advanced_scalar($context['content'] ?? '')
        );
        $uploadUrl = '/admin/bin/post_file.php?' . http_build_query([
            'RecordID' => $recordId,
            'UC' => 'Webpage_Logo',
            'Language' => $language,
            'csrf_token' => $csrfToken,
        ]);
        $scriptPath = '/admin/assets/js/advanced-logo.js';
        $scriptVersion = is_file($_SERVER['DOCUMENT_ROOT'] . $scriptPath)
            ? filemtime($_SERVER['DOCUMENT_ROOT'] . $scriptPath)
            : '1';
        $statusLabel = !empty($fact['active']) ? 'Custom logo active' : 'Template logo active';
        $statusTone = !empty($fact['active']) ? 'active' : 'fallback';
        $kilobytes = !empty($fact['bytes']) ? number_format($fact['bytes'] / 1024, 1) . ' KB' : '—';
        ?>
        <div
            class="red-admin-advanced-logo-shell"
            data-red-advanced-logo
            data-record-id="<?php echo $recordId; ?>"
            data-upload-url="<?php echo red_admin_advanced_html($uploadUrl); ?>"
            data-max-bytes="<?php echo 2 * 1024 * 1024; ?>"
        >
            <div class="cp_viewall red-admin-advanced-editor-return">
                <button type="button" class="red-admin-advanced-editor-return__button" onclick="showdiv('edit_advanced_grid'); return false;">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6"></path></svg>
                    <span>Advanced items</span>
                </button>
                <span class="red-admin-advanced-editor-return__divider" aria-hidden="true">/</span>
                <span aria-current="page">Website Logo</span>
            </div>

            <section class="red-admin-advanced-logo" aria-labelledby="red-admin-logo-title">
                <header class="red-admin-advanced-editor__hero">
                    <span class="red-admin-advanced-editor__hero-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="3"></rect><circle cx="8.5" cy="9" r="1.5"></circle><path d="m5 17 4-4 3 3 2-2 5 5"></path></svg>
                    </span>
                    <div class="red-admin-advanced-editor__hero-copy">
                        <span class="red-admin-advanced-editor__eyebrow">Shared brand asset</span>
                        <h2 id="red-admin-logo-title">Website Logo</h2>
                        <p>Upload one trusted raster logo for every compatible template, while retaining each template’s built-in fallback.</p>
                    </div>
                    <span class="red-admin-advanced-editor__badge">PNG / JPG</span>
                </header>

                <section class="red-admin-advanced-context" aria-label="Logo context">
                    <div class="red-admin-advanced-context__item">
                        <span>Coverage</span>
                        <strong>Compatible public templates</strong>
                    </div>
                    <div class="red-admin-advanced-context__item">
                        <span>Language</span>
                        <strong><?php echo red_admin_advanced_html(strtoupper($language)); ?></strong>
                    </div>
                    <div class="red-admin-advanced-context__item red-admin-advanced-context__item--wide">
                        <span>Current behavior</span>
                        <strong><?php echo red_admin_advanced_html($statusLabel); ?></strong>
                        <code>Website_Logo · #<?php echo $recordId; ?></code>
                    </div>
                </section>

                <div class="red-admin-advanced-logo-grid">
                    <section class="red-admin-advanced-logo-current" aria-labelledby="red-admin-logo-current-title">
                        <div class="red-admin-advanced-logo-section-heading">
                            <span class="red-admin-advanced-source-panel__step">01</span>
                            <div>
                                <h3 id="red-admin-logo-current-title">Current logo source</h3>
                                <p>A valid custom PNG or JPG replaces the active template logo.</p>
                            </div>
                            <span class="red-admin-advanced-logo-status" data-tone="<?php echo red_admin_advanced_html($statusTone); ?>">
                                <?php echo red_admin_advanced_html($statusLabel); ?>
                            </span>
                        </div>

                        <div class="red-admin-advanced-logo-preview" data-logo-preview>
                            <?php if (!empty($fact['valid'])) { ?>
                                <img
                                    src="<?php echo red_admin_advanced_html($fact['url']); ?>"
                                    alt="Current Website Logo preview"
                                    width="<?php echo (int) $fact['width']; ?>"
                                    height="<?php echo (int) $fact['height']; ?>"
                                >
                            <?php } else { ?>
                                <div class="red-admin-advanced-logo-preview__empty">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"></rect><path d="m5 17 4-4 3 3 2-2 5 5"></path></svg>
                                    <span><?php echo !empty($fact['configured']) ? 'Configured logo unavailable' : 'No custom logo uploaded'; ?></span>
                                </div>
                            <?php } ?>
                        </div>

                        <dl class="red-admin-advanced-logo-facts">
                            <div><dt>File</dt><dd><?php echo red_admin_advanced_html($fact['filename'] !== '' ? $fact['filename'] : 'Not configured'); ?></dd></div>
                            <div><dt>Dimensions</dt><dd><?php echo !empty($fact['valid']) ? (int) $fact['width'] . ' × ' . (int) $fact['height'] . ' px' : '—'; ?></dd></div>
                            <div><dt>Format</dt><dd><?php echo !empty($fact['valid']) ? red_admin_advanced_html(strtoupper($fact['extension'])) : '—'; ?></dd></div>
                            <div><dt>File size</dt><dd><?php echo red_admin_advanced_html($kilobytes); ?></dd></div>
                        </dl>

                        <?php if (empty($fact['configured'])) { ?>
                            <div class="red-admin-advanced-notice red-admin-advanced-notice--logo" role="status">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5m0-8h.01"></path></svg>
                                <div><strong>Template fallback in use</strong><p>Each template keeps its own logo until you upload the shared replacement you want.</p></div>
                            </div>
                        <?php } ?>

                        <?php if (!empty($fact['valid'])) { ?>
                            <button type="button" class="red-admin-advanced-copy red-admin-advanced-logo-copy" data-logo-copy data-copy-value="<?php echo red_admin_advanced_html($fact['url']); ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>
                                <span data-logo-copy-label>Copy image URL</span>
                            </button>
                        <?php } ?>
                    </section>

                    <section class="red-admin-advanced-logo-upload" aria-labelledby="red-admin-logo-upload-title">
                        <div class="red-admin-advanced-logo-section-heading">
                            <span class="red-admin-advanced-source-panel__step">02</span>
                            <div>
                                <h3 id="red-admin-logo-upload-title">Upload replacement</h3>
                                <p>Choose from your computer or drag one image into the upload area.</p>
                            </div>
                        </div>

                        <div class="red-admin-advanced-logo-dropzone" data-logo-dropzone>
                            <input
                                type="file"
                                id="red-admin-logo-file"
                                accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                                data-logo-file
                            >
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5h14v-5"></path></svg>
                            <strong>Drop a PNG or JPG here</strong>
                            <span>Transparent PNG is recommended for logos. Maximum file size: 2 MB.</span>
                            <label for="red-admin-logo-file" class="red-admin-advanced-logo-browse">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h6l2 2h10v10H3V7Z"></path></svg>
                                <span>Browse computer</span>
                            </label>
                        </div>

                        <div class="red-admin-advanced-logo-progress" data-logo-progress hidden>
                            <span data-logo-progress-label>Preparing upload…</span>
                            <progress max="100" value="0" data-logo-progress-bar>0%</progress>
                        </div>
                        <p class="red-admin-advanced-logo-message" data-logo-message role="status" aria-live="polite"></p>

                        <aside class="red-admin-advanced-notice" aria-label="Logo guidance">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 4.5 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.5a2 2 0 0 0-3.4 0Z"></path></svg>
                            <div><strong>Template-safe replacement</strong><p>Templates control display size and spacing. Use a tightly cropped horizontal or compact logo with enough contrast for the site header.</p></div>
                        </aside>
                    </section>
                </div>
            </section>
        </div>
        <script src="<?php echo red_admin_advanced_html($scriptPath); ?>?v=<?php echo rawurlencode((string) $scriptVersion); ?>"></script>
        <?php
        return true;
    }
}
