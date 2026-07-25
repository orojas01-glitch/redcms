<?php
/**
 * Shared presentation helpers for the modern Other create/edit workspace.
 *
 * Other.ShortDesc remains the authoritative trusted HTML source. The visual
 * editor is an unnamed staging surface so opening or saving an existing block
 * cannot silently normalize its stored markup.
 */

require_once __DIR__.'/admin_article_helpers.php';
require_once __DIR__.'/admin_content_revision_ui_helpers.php';

if (!function_exists('red_admin_other_date_meta')) {
    function red_admin_other_date_meta($value, $sentinel)
    {
        $date = substr(red_admin_text($value ?? ''), 0, 10);
        $display = $date !== '0000-00-00'
            && $date !== $sentinel
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
            ? $date
            : '';

        return ['display' => $display];
    }
}

if (!function_exists('red_admin_other_preserve_option')) {
    function red_admin_other_preserve_option($options, $selected)
    {
        $selected = red_admin_text($selected);
        if ($selected === '' || strpos((string) $options, ' selected="selected"') !== false) {
            return (string) $options;
        }

        return '<option value="'.red_admin_area_html($selected).'" selected="selected">'
            .red_admin_area_html($selected).' — unavailable; preserved</option>'.(string) $options;
    }
}

if (!function_exists('red_admin_other_upload_url')) {
    function red_admin_other_upload_url(array $parameters)
    {
        unset($parameters['csrf_token']);
        return '/admin/bin/post_file.php?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('red_admin_other_preferred_editor_mode')) {
    function red_admin_other_preferred_editor_mode($html, $mode = 'edit')
    {
        if ($mode !== 'edit') {
            return 'visual';
        }

        $html = red_admin_article_scalar($html);
        if ($html === '') {
            return 'visual';
        }

        $advancedPatterns = [
            '/<\s*(?:div|section|article|main|header|footer|nav|aside|form|input|button|select|option|textarea|iframe|frame|frameset|script|noscript|style|svg|math|object|embed|applet|template|canvas|video|audio|source|picture|meta|base|link)\b/i',
            '/\s(?:class|id|style|srcdoc|srcset|data-[a-z0-9_:-]+|aria-[a-z0-9_:-]+|on[a-z0-9_:-]+)\s*=/i',
            '/<\s*[a-z][a-z0-9]*-[a-z0-9-]+\b/i',
            '/(?:<!--|<!doctype|<\?)/i',
        ];

        foreach ($advancedPatterns as $pattern) {
            if (preg_match($pattern, $html) === 1) {
                return 'html';
            }
        }

        return 'visual';
    }
}

if (!function_exists('red_admin_render_other_form')) {
    function red_admin_render_other_form(array $context)
    {
        $defaults = [
            'mode' => 'create',
            'returnTarget' => 'add_content_grid',
            'submitUrl' => '/admin/bin/insert_content.php',
            'deleteUrl' => '/admin/bin/delete_label.php',
            'title' => '',
            'alias' => '',
            'tags' => '',
            'active' => 'Y',
            'homeFeature' => '',
            'position' => 0,
            'positionOrder' => '',
            'positionOptions' => [],
            'varPosition' => 'PagePosition',
            'html' => '',
            'preferredEditorMode' => 'visual',
            'sectionOptions' => '',
            'categoryOptions' => '',
            'subCategoryOptions' => '',
            'articleOptions' => '',
            'startDateMeta' => ['display' => ''],
            'expirationDateMeta' => ['display' => ''],
            'bigPict' => '',
            'smallPict' => '',
            'smallPictAlign' => '',
            'uploadUrls' => [],
            'publicUrl' => '',
            'recordId' => 0,
            'language' => '',
            'layout' => '',
            'editedBy' => '',
            'csrfToken' => '',
        ];
        $context = array_merge($defaults, $context);
        $isEdit = $context['mode'] === 'edit';
        $mode = $isEdit ? 'edit' : 'create';
        $preferredMode = $context['preferredEditorMode'] === 'html' ? 'html' : 'visual';
        $advancedMarkup = $preferredMode === 'html';
        $formId = $isEdit ? 'update_content' : 'insert_content';
        $messageId = $isEdit ? 'msggbox_update_content' : 'msggbox_insert_content';
        $returnLabel = $isEdit ? 'Show content' : 'All content types';
        $pageLabel = $isEdit ? 'Edit Other' : 'New Other';
        $heading = $isEdit ? 'Edit HTML block' : 'Create an HTML block';
        $saveLabel = $isEdit ? 'Save changes' : 'Save HTML block';
        $otherScript = '/admin/assets/js/other-form.js';
        $otherScriptVersion = is_file($_SERVER['DOCUMENT_ROOT'].$otherScript)
            ? filemtime($_SERVER['DOCUMENT_ROOT'].$otherScript)
            : '1';
        $supportingImages = [
            [
                'field' => 'BigPict',
                'inputId' => 'other-feature-image',
                'title' => 'Feature image',
                'description' => 'Optional image used by feature and hero placements',
                'current' => red_admin_text($context['bigPict']),
                'uploadUrl' => red_admin_text($context['uploadUrls']['BigPict'] ?? ''),
            ],
            [
                'field' => 'SmallPict',
                'inputId' => 'other-summary-image',
                'title' => 'Summary image',
                'description' => 'Optional image used by lists and compact cards',
                'current' => red_admin_text($context['smallPict']),
                'uploadUrl' => red_admin_text($context['uploadUrls']['SmallPict'] ?? ''),
            ],
        ];
        ?>

<div class="cp_viewall red-admin-article-return">
    <button type="button" class="red-admin-article-return__button" onclick="showdiv('<?php echo red_admin_area_html($context['returnTarget']); ?>'); return false;">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
        <span><?php echo red_admin_area_html($returnLabel); ?></span>
    </button>
    <span class="red-admin-article-return__divider" aria-hidden="true">/</span>
    <span aria-current="page"><?php echo red_admin_area_html($pageLabel); ?></span>
</div>

<form
    id="<?php echo red_admin_area_html($formId); ?>"
    name="<?php echo red_admin_area_html($formId); ?>"
    class="cp red-admin-article-form red-admin-other-form"
    method="post"
    data-red-other-form
    data-other-mode="<?php echo red_admin_area_html($mode); ?>"
    data-preferred-editor-mode="<?php echo red_admin_area_html($preferredMode); ?>"
    data-advanced-markup="<?php echo $advancedMarkup ? 'true' : 'false'; ?>"
    data-submit-url="<?php echo red_admin_area_html($context['submitUrl']); ?>"
    data-delete-url="<?php echo red_admin_area_html($context['deleteUrl']); ?>"
    onsubmit="return <?php echo $isEdit ? 'run_update_content' : 'run_insert_content'; ?>(this);"
>
    <fieldset>
        <legend class="red-admin-visually-hidden"><?php echo red_admin_area_html($heading); ?></legend>

        <div class="red-admin-article-shell">
            <header class="red-admin-article-header red-admin-other-header">
                <span class="red-admin-article-header__icon red-admin-other-header__icon<?php echo $isEdit ? ' red-admin-article-header__icon--edit' : ''; ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 7l-4 5 4 5M16 7l4 5-4 5M14 4l-4 16"></path></svg>
                </span>
                <div class="red-admin-article-header__copy">
                    <span class="red-admin-article-header__eyebrow"><?php echo $isEdit ? 'Edit Content' : 'Add Content'; ?></span>
                    <h2><?php echo red_admin_area_html($heading); ?></h2>
                    <p>Write visually for everyday content, or switch to HTML whenever you need complete source control.</p>
                </div>
                <div class="red-admin-article-header__actions">
                    <?php if ($isEdit && red_admin_text($context['publicUrl']) !== '') { ?>
                        <button type="button" class="red-admin-article-view-link" data-other-copy-page data-copy-value="<?php echo red_admin_area_html($context['publicUrl']); ?>" aria-label="Copy current page address to the clipboard">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"></path></svg>
                            <span data-copy-label>Copy page link</span>
                        </button>
                    <?php } ?>
                    <span class="red-admin-article-header__badge red-admin-other-header__badge">HTML block</span>
                </div>
            </header>

            <span class="red-admin-visually-hidden" role="status" aria-live="polite" data-other-copy-status></span>

            <section class="red-admin-article-panel" aria-labelledby="other-basics-title">
                <div class="red-admin-article-panel__heading">
                    <div><span class="red-admin-article-panel__step">01</span><h3 id="other-basics-title">Block basics</h3></div>
                    <p>Identity, visibility, and placement</p>
                </div>

                <div class="red-admin-field-grid red-admin-field-grid--basics">
                    <div class="red-admin-field red-admin-field--title">
                        <label for="other-title">Title <span aria-hidden="true">*</span></label>
                        <input name="Title" type="text" id="other-title" value="<?php echo red_admin_area_html($context['title']); ?>" autocomplete="off" required aria-describedby="other-title-help other-title-error" />
                        <span class="red-admin-field__help" id="other-title-help">The administrator label and optional public heading for this block.</span>
                        <span class="red-admin-field__error" id="other-title-error" data-other-title-error hidden>Add a title before saving.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="other-status">Status</label>
                        <select name="Active" id="other-status"><option value="Y"<?php echo $context['active'] === 'Y' ? ' selected="selected"' : ''; ?>>Published</option><option value="N"<?php echo $context['active'] !== 'Y' ? ' selected="selected"' : ''; ?>>Inactive</option></select>
                        <span class="red-admin-field__help">Inactive blocks stay out of public view.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="other-layout-position">Layout position</label>
                        <select name="<?php echo red_admin_area_html($context['varPosition']); ?>" id="other-layout-position">
                            <?php foreach ($context['positionOptions'] as $positionValue => $positionLabel) { ?>
                                <option value="<?php echo (int) $positionValue; ?>"<?php echo (int) $context['position'] === (int) $positionValue ? ' selected="selected"' : ''; ?>><?php echo red_admin_area_html($positionLabel); ?> (<?php echo (int) $positionValue; ?>)</option>
                            <?php } ?>
                        </select>
                        <span class="red-admin-field__help">Where this block appears in the selected layout.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="other-order">Order</label>
                        <input name="<?php echo red_admin_area_html($context['varPosition'].'Order'); ?>" type="number" id="other-order" value="<?php echo red_admin_area_html($context['positionOrder']); ?>" min="0" step="1" inputmode="numeric" placeholder="Auto" />
                        <span class="red-admin-field__help">Lower numbers appear first.</span>
                    </div>
                    <label class="red-admin-choice-card" for="other-home-feature">
                        <input name="HomeFeature" type="checkbox" id="other-home-feature" value="Y"<?php echo $context['homeFeature'] === 'Y' ? ' checked="checked"' : ''; ?> />
                        <span class="red-admin-choice-card__control" aria-hidden="true"></span>
                        <span><strong>Feature on Home</strong><small>Include this block in the Home feature area.</small></span>
                    </label>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-other-editor-panel" aria-labelledby="other-content-title">
                <div class="red-admin-article-panel__heading red-admin-other-editor-heading">
                    <div><span class="red-admin-article-panel__step">02</span><h3 id="other-content-title">Content editor</h3></div>
                    <span class="red-admin-editor-status" data-other-editor-status data-state="loading" role="status" aria-live="polite"><?php echo $preferredMode === 'html' ? 'HTML source ready' : 'Loading visual tools…'; ?></span>
                </div>

                <?php if ($advancedMarkup) { ?>
                    <div class="red-admin-other-structure-note" data-other-structure-note>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l9 17H3z"></path><path d="M12 9v5M12 17h.01"></path></svg>
                        <p><strong>Advanced layout markup detected.</strong> This block stays in HTML mode so template structure, embedded content, and attributes cannot be deleted by a visual editor.</p>
                    </div>
                <?php } ?>

                <div class="red-admin-other-modebar">
                    <div class="red-admin-other-mode-tabs" role="tablist" aria-label="Content editing mode">
                        <button type="button" role="tab" id="other-visual-tab" aria-controls="other-visual-panel" aria-selected="<?php echo $preferredMode === 'visual' ? 'true' : 'false'; ?>" tabindex="<?php echo $preferredMode === 'visual' ? '0' : '-1'; ?>" data-other-editor-mode="visual"<?php echo $advancedMarkup ? ' disabled aria-disabled="true" title="Visual editing is unavailable for structured template HTML"' : ''; ?>>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h8M8 13h5"></path></svg>
                            <span><strong>Visual</strong><small>Easy editing</small></span>
                            <em><?php echo $advancedMarkup ? 'Simple blocks only' : 'Recommended'; ?></em>
                        </button>
                        <button type="button" role="tab" id="other-html-tab" aria-controls="other-html-panel" aria-selected="<?php echo $preferredMode === 'html' ? 'true' : 'false'; ?>" tabindex="<?php echo $preferredMode === 'html' ? '0' : '-1'; ?>" data-other-editor-mode="html">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7l-5 5 5 5M16 7l5 5-5 5M14 4l-4 16"></path></svg>
                            <span><strong>HTML</strong><small>Full source control</small></span>
                        </button>
                    </div>
                    <p data-other-mode-description><?php echo $preferredMode === 'html' ? 'Edit the exact stored HTML. Only paste code you trust.' : 'Format text, headings, lists, links, and tables without writing HTML.'; ?></p>
                </div>

                <div class="red-admin-other-editor-workspace" data-other-editor-workspace data-editor-mode="<?php echo red_admin_area_html($preferredMode); ?>">
                    <div id="other-visual-panel" class="red-admin-other-editor-pane red-admin-other-editor-pane--visual" role="tabpanel" aria-labelledby="other-visual-tab"<?php echo $preferredMode === 'visual' ? '' : ' hidden'; ?> data-other-visual-panel>
                        <label class="red-admin-visually-hidden" for="other-visual-editor">Visual content editor</label>
                        <textarea id="other-visual-editor" rows="14" data-other-visual-editor aria-describedby="other-visual-help"></textarea>
                        <p id="other-visual-help" class="red-admin-other-editor-help"><strong>Tip:</strong> Use the Formats menu for headings and paragraphs. This editor is for content—not a theme preview.</p>
                    </div>

                    <div id="other-html-panel" class="red-admin-other-editor-pane red-admin-other-editor-pane--html" role="tabpanel" aria-labelledby="other-html-tab"<?php echo $preferredMode === 'html' ? '' : ' hidden'; ?> data-other-html-panel>
                        <div class="red-admin-other-source-toolbar">
                            <span><strong>HTML</strong> Exact stored source</span>
                            <button type="button" data-other-copy-html><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V5H5v11h3"></path></svg><span data-copy-label>Copy HTML</span></button>
                        </div>
                        <label class="red-admin-visually-hidden" for="other-html-source">HTML source</label>
                        <textarea name="ShortDesc" id="other-html-source" rows="18" spellcheck="false" autocapitalize="off" autocomplete="off" data-other-html-source><?php echo red_admin_area_html($context['html']); ?></textarea>
                        <div class="red-admin-other-source-footer"><span>Tab moves to the next control</span><span data-other-source-stats>1 line · 0 characters</span></div>
                        <div class="red-admin-other-trust-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v5c0 5-3.4 8.2-8 10-4.6-1.8-8-5-8-10V6z"></path><path d="M9 12l2 2 4-5"></path></svg><p><strong>Trusted administrator HTML.</strong> This code is rendered on the public page as entered. Do not paste scripts or markup from an unknown source.</p></div>
                    </div>
                </div>
            </section>

            <details class="red-admin-article-advanced" data-other-advanced>
                <summary>
                    <span class="red-admin-article-advanced__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"></path></svg></span>
                    <span class="red-admin-article-advanced__copy"><strong>Optional settings</strong><small>Publishing schedule, location, metadata, and supporting images</small></span>
                    <span class="red-admin-article-advanced__badge">Advanced</span>
                    <svg class="red-admin-article-advanced__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5"></path></svg>
                </summary>
                <div class="red-admin-article-advanced__body">
                    <section class="red-admin-optional-card" aria-labelledby="other-publishing-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect><path d="M7.5 3.5v4M16.5 3.5v4M3.5 9.5h17"></path></svg></span><div><h4 id="other-publishing-title">Publishing &amp; metadata</h4><p>Schedule availability and manage administrator-friendly identity values.</p></div></div>
                        <?php if ($isEdit) { ?>
                            <div class="red-admin-field-grid red-admin-other-identity-grid"><div class="red-admin-field"><label for="other-alias">URL alias</label><input name="Alias" type="text" id="other-alias" value="<?php echo red_admin_area_html($context['alias']); ?>" autocomplete="off" /></div><div class="red-admin-field"><label for="other-tags">SEO tags</label><input name="Tags" type="text" id="other-tags" value="<?php echo red_admin_area_html($context['tags']); ?>" autocomplete="off" /></div></div>
                        <?php } else { ?>
                            <p class="red-admin-other-auto-note">The alias will be generated automatically from the title. SEO tags can be refined after the block is created.</p>
                        <?php } ?>
                        <div class="red-admin-field-grid red-admin-field-grid--dates">
                            <div class="red-admin-field"><label for="other-start-date">Start date</label><?php if ($isEdit) { ?><input type="date" id="other-start-date" value="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" data-other-date="start" data-original-date="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" /><input name="StartDate" type="hidden" value="" data-date-payload disabled /><?php } else { ?><input name="StartDate" type="date" id="other-start-date" data-other-date="start" /><?php } ?><span class="red-admin-field__help">Leave blank to publish immediately.</span></div>
                            <div class="red-admin-field"><label for="other-expiration-date">Expiration date</label><?php if ($isEdit) { ?><input type="date" id="other-expiration-date" value="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" data-other-date="expiration" data-original-date="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" /><input name="ExpDate" type="hidden" value="" data-date-payload disabled /><?php } else { ?><input name="ExpDate" type="date" id="other-expiration-date" data-other-date="expiration" /><?php } ?><span class="red-admin-field__help">Leave blank to keep the block available.</span></div>
                        </div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="other-location-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon red-admin-optional-card__icon--location" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"></path><circle cx="12" cy="10" r="2"></circle></svg></span><div><h4 id="other-location-title">Content location</h4><p>Connect the block to the relevant site hierarchy.</p></div></div>
                        <div class="red-admin-field-grid red-admin-field-grid--location"><div class="red-admin-field"><label for="other-section">Section</label><select name="Sections" id="other-section"><option value="">No section</option><?php echo $context['sectionOptions']; ?></select></div><div class="red-admin-field"><label for="other-category">Category</label><select name="Categories" id="other-category"><option value="">No category</option><?php echo $context['categoryOptions']; ?></select></div><div class="red-admin-field"><label for="other-subcategory">Subcategory</label><select name="SubCategories" id="other-subcategory"><option value="">No subcategory</option><?php echo $context['subCategoryOptions']; ?></select></div><div class="red-admin-field"><label for="other-parent">Parent article</label><select name="Article" id="other-parent"><option value="">No parent article</option><?php echo $context['articleOptions']; ?></select></div></div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="other-images-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon red-admin-optional-card__icon--media" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect><circle cx="9" cy="9" r="1.5"></circle><path d="M5.5 17l4.5-4 3 2.5 2.5-2 3 3.5"></path></svg></span><div><h4 id="other-images-title">Supporting images</h4><p>Optional alternatives used by feature areas and content lists.</p></div></div>
                        <div class="red-admin-upload-grid red-admin-other-supporting-grid">
                            <?php foreach ($supportingImages as $supportingImage) { ?>
                                <article class="red-admin-upload-card" data-other-upload data-upload-field="<?php echo red_admin_area_html($supportingImage['field']); ?>" data-upload-url="<?php echo red_admin_area_html($supportingImage['uploadUrl']); ?>" aria-busy="false">
                                    <div class="red-admin-upload-card__heading"><strong><?php echo red_admin_area_html($supportingImage['title']); ?></strong><span><?php echo red_admin_area_html($supportingImage['description']); ?></span></div>
                                    <input type="hidden" name="<?php echo red_admin_area_html($supportingImage['field']); ?>" value="<?php echo red_admin_area_html($supportingImage['current']); ?>" data-upload-value />
                                    <div class="red-admin-upload-current" data-current-media<?php echo $supportingImage['current'] === '' ? ' hidden' : ''; ?>><img<?php echo $supportingImage['current'] === '' ? '' : ' src="/images/resize.php?w=180&amp;h=110&amp;img=/images/articles/'.rawurlencode($supportingImage['current']).'"'; ?> alt="Current <?php echo red_admin_area_html(strtolower($supportingImage['title'])); ?>" data-current-image /><div class="red-admin-upload-current__meta"><span>Current image</span><strong data-current-name><?php echo red_admin_area_html($supportingImage['current']); ?></strong></div><label class="red-admin-upload-remove"><input name="Delete_<?php echo red_admin_area_html($supportingImage['field']); ?>" type="checkbox" value="Y" data-other-remove-image /><span>Remove when saved</span></label></div>
                                    <input class="red-admin-visually-hidden" type="file" id="<?php echo red_admin_area_html($supportingImage['inputId']); ?>" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" tabindex="-1" aria-hidden="true" data-upload-input />
                                    <div class="red-admin-upload-dropzone" data-upload-dropzone><svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path></svg><strong><?php echo $isEdit ? 'Drop replacement here' : 'Drop image here'; ?></strong><span>or choose one from your computer</span><button type="button" class="red-admin-upload-browse" data-upload-browse><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>Browse computer</button><small>JPG, PNG or GIF · maximum 2 MB</small></div>
                                    <div class="red-admin-upload-preview" data-upload-preview hidden><img src="" alt="" data-upload-preview-image /><div><strong data-upload-file-name></strong><span data-upload-status role="status" aria-live="polite">Ready to upload</span></div></div>
                                    <div class="red-admin-upload-progress" aria-hidden="true"><span data-upload-progress></span></div>
                                    <?php if ($supportingImage['field'] === 'SmallPict') { ?><div class="red-admin-field red-admin-upload-card__alignment"><label for="other-summary-alignment">Image alignment</label><select name="SmallPictAlign" id="other-summary-alignment"><option value=""<?php echo $context['smallPictAlign'] === '' ? ' selected="selected"' : ''; ?>>Theme default</option><option value="Top"<?php echo $context['smallPictAlign'] === 'Top' ? ' selected="selected"' : ''; ?>>Top</option><option value="Left"<?php echo $context['smallPictAlign'] === 'Left' ? ' selected="selected"' : ''; ?>>Left</option><option value="Right"<?php echo $context['smallPictAlign'] === 'Right' ? ' selected="selected"' : ''; ?>>Right</option></select></div><?php } ?>
                                </article>
                            <?php } ?>
                        </div>
                    </section>
                </div>
            </details>

            <input type="hidden" name="RecordID" id="RecordID" value="<?php echo (int) $context['recordId']; ?>" />
            <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_area_html($context['editedBy']); ?>" />
            <?php if (!$isEdit) { ?>
                <input type="hidden" name="Language" id="Language" value="<?php echo red_admin_area_html($context['language']); ?>" />
                <input type="hidden" name="Layout" id="Layout" value="<?php echo red_admin_area_html($context['layout']); ?>" />
                <input type="hidden" name="Component" id="Component" value="Other" />
            <?php } ?>
            <input type="hidden" name="csrf_token" value="<?php echo red_admin_area_html($context['csrfToken']); ?>" />

            <?php if ($isEdit) { red_admin_content_revision_panel((int) $context['recordId']); } ?>

            <div class="red-admin-article-actions<?php echo $isEdit ? ' red-admin-article-actions--edit' : ''; ?>">
                <span id="<?php echo red_admin_area_html($messageId); ?>" class="red-admin-article-message" data-other-message role="status" aria-live="polite" hidden></span>
                <?php if ($isEdit) { ?><button type="button" class="red-admin-article-delete" data-other-delete><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"></path></svg>Delete HTML block</button><?php } ?>
                <button type="submit" name="submit" value="Save" id="save" class="red-admin-article-save" data-other-save data-default-label="<?php echo red_admin_area_html($saveLabel); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"></path><path d="M8 4v6h8V4M8 20v-6h8v6"></path></svg><span data-save-label><?php echo red_admin_area_html($saveLabel); ?></span></button>
            </div>
        </div>
    </fieldset>
</form>
<script src="<?php echo red_admin_area_html($otherScript); ?>?v=<?php echo rawurlencode((string) $otherScriptVersion); ?>"></script>
        <?php
    }
}
