<?php
/**
 * Shared presentation helpers for the modern Video create/edit workspace.
 * Video remains an exact RED_C_Gallery subtype and keeps the legacy paired
 * RED_Articles/RED_C_Gallery write contract.
 */

require_once __DIR__.'/admin_article_helpers.php';
require_once __DIR__.'/video_url_helpers.php';
require_once __DIR__.'/admin_content_revision_ui_helpers.php';

if (!function_exists('red_admin_video_date_meta')) {
    function red_admin_video_date_meta($value, $sentinel)
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

if (!function_exists('red_admin_video_preserve_option')) {
    function red_admin_video_preserve_option($options, $selected)
    {
        $selected = red_admin_text($selected);
        if ($selected === '' || strpos((string) $options, ' selected="selected"') !== false) {
            return (string) $options;
        }

        return '<option value="'.red_admin_area_html($selected).'" selected="selected">'.
            red_admin_area_html($selected).' — unavailable; preserved</option>'.(string) $options;
    }
}

if (!function_exists('red_admin_video_upload_url')) {
    function red_admin_video_upload_url(array $parameters)
    {
        unset($parameters['csrf_token']);
        return '/admin/bin/post_file.php?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('red_admin_render_video_form')) {
    function red_admin_render_video_form(array $context)
    {
        $defaults = [
            'mode' => 'create',
            'returnTarget' => 'add_content_grid',
            'submitUrl' => '/admin/bin/insert_gallery.php',
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
            'videoUrl' => '',
            'description' => '',
            'link' => '',
            'newWindow' => '',
            'linkNavigatorOptions' => '',
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
            'recordId' => 0,
            'artRecordId' => 0,
            'language' => '',
            'layout' => '',
            'editedBy' => '',
            'csrfToken' => '',
        ];
        $context = array_merge($defaults, $context);
        $isEdit = $context['mode'] === 'edit';
        $mode = $isEdit ? 'edit' : 'create';
        $formId = $isEdit ? 'update_gallery' : 'insert_gallery';
        $messageId = $isEdit ? 'msggbox_update_gallery' : 'msggbox_insert_gallery';
        $returnLabel = $isEdit ? 'Show content' : 'All content types';
        $pageLabel = $isEdit ? 'Edit Video' : 'New Video';
        $heading = $isEdit ? 'Edit video' : 'Add a video';
        $saveLabel = $isEdit ? 'Save changes' : 'Save video';
        $videoScript = '/admin/assets/js/video-form.js';
        $videoScriptVersion = is_file($_SERVER['DOCUMENT_ROOT'].$videoScript)
            ? filemtime($_SERVER['DOCUMENT_ROOT'].$videoScript)
            : '1';
        $supportingImages = [
            [
                'field' => 'BigPict',
                'inputId' => 'video-feature-image',
                'title' => 'Feature image',
                'description' => 'Optional image used by feature and hero placements',
                'current' => red_admin_text($context['bigPict']),
                'uploadUrl' => red_admin_text($context['uploadUrls']['BigPict'] ?? ''),
            ],
            [
                'field' => 'SmallPict',
                'inputId' => 'video-summary-image',
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
    class="cp red-admin-article-form red-admin-video-form"
    method="post"
    data-red-video-form
    data-video-mode="<?php echo red_admin_area_html($mode); ?>"
    data-submit-url="<?php echo red_admin_area_html($context['submitUrl']); ?>"
    data-delete-url="<?php echo red_admin_area_html($context['deleteUrl']); ?>"
    onsubmit="return <?php echo $isEdit ? 'run_update_gallery' : 'run_insert_gallery'; ?>(this);"
>
    <fieldset>
        <legend class="red-admin-visually-hidden"><?php echo red_admin_area_html($heading); ?></legend>

        <div class="red-admin-article-shell">
            <header class="red-admin-article-header red-admin-video-header">
                <span class="red-admin-article-header__icon red-admin-video-header__icon<?php echo $isEdit ? ' red-admin-article-header__icon--edit' : ''; ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="M10 9l5 3-5 3z"></path></svg>
                </span>
                <div class="red-admin-article-header__copy">
                    <span class="red-admin-article-header__eyebrow"><?php echo $isEdit ? 'Edit Content' : 'Add Content'; ?></span>
                    <h2><?php echo red_admin_area_html($heading); ?></h2>
                    <p>Paste one link. RED-CMS recognizes the provider, checks the video address, and prepares the public player automatically.</p>
                </div>
                <span class="red-admin-article-header__badge red-admin-video-header__badge">Video</span>
            </header>

            <section class="red-admin-article-panel" aria-labelledby="video-basics-title">
                <div class="red-admin-article-panel__heading">
                    <div><span class="red-admin-article-panel__step">01</span><h3 id="video-basics-title">Video basics</h3></div>
                    <p>Identity, visibility, and placement</p>
                </div>

                <div class="red-admin-field-grid red-admin-field-grid--basics">
                    <div class="red-admin-field red-admin-field--title">
                        <label for="video-title">Title <span aria-hidden="true">*</span></label>
                        <input name="Title" type="text" id="video-title" value="<?php echo red_admin_area_html($context['title']); ?>" autocomplete="off" required aria-describedby="video-title-help video-title-error" />
                        <span class="red-admin-field__help" id="video-title-help">The administrator label and public heading for this video.</span>
                        <span class="red-admin-field__error" id="video-title-error" data-video-title-error hidden>Add a title before saving.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="video-status">Status</label>
                        <select name="Active" id="video-status"><option value="Y"<?php echo $context['active'] === 'Y' ? ' selected="selected"' : ''; ?>>Published</option><option value="N"<?php echo $context['active'] !== 'Y' ? ' selected="selected"' : ''; ?>>Inactive</option></select>
                        <span class="red-admin-field__help">Inactive videos stay out of public view.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="video-layout-position">Layout position</label>
                        <select name="<?php echo red_admin_area_html($context['varPosition']); ?>" id="video-layout-position">
                            <?php foreach ($context['positionOptions'] as $positionValue => $positionLabel) { ?>
                                <option value="<?php echo (int) $positionValue; ?>"<?php echo (int) $context['position'] === (int) $positionValue ? ' selected="selected"' : ''; ?>><?php echo red_admin_area_html($positionLabel); ?> (<?php echo (int) $positionValue; ?>)</option>
                            <?php } ?>
                        </select>
                        <span class="red-admin-field__help">Where the video appears in the selected layout.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="video-order">Order</label>
                        <input name="<?php echo red_admin_area_html($context['varPosition'].'Order'); ?>" type="number" id="video-order" value="<?php echo red_admin_area_html($context['positionOrder']); ?>" min="0" step="1" inputmode="numeric" placeholder="Auto" />
                        <span class="red-admin-field__help">Lower numbers appear first.</span>
                    </div>
                    <label class="red-admin-choice-card" for="video-home-feature">
                        <input name="HomeFeature" type="checkbox" id="video-home-feature" value="Y"<?php echo $context['homeFeature'] === 'Y' ? ' checked="checked"' : ''; ?> />
                        <span class="red-admin-choice-card__control" aria-hidden="true"></span>
                        <span><strong>Feature on Home</strong><small>Include this video in the Home feature area.</small></span>
                    </label>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-video-source-panel" aria-labelledby="video-source-title">
                <div class="red-admin-article-panel__heading">
                    <div><span class="red-admin-article-panel__step">02</span><h3 id="video-source-title">Video source</h3></div>
                    <p>Automatic provider detection and preview</p>
                </div>

                <div class="red-admin-video-source-grid">
                    <div class="red-admin-video-source-card">
                        <div class="red-admin-video-source-card__heading">
                            <span class="red-admin-video-source-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 007.1 0l2-2a5 5 0 00-7.1-7.1l-1.1 1.1"></path><path d="M14 11a5 5 0 00-7.1 0l-2 2A5 5 0 0012 20.1l1.1-1.1"></path></svg></span>
                            <div><h4>Paste a video link</h4><p>YouTube and Vimeo play on the page. Other secure video links open on their provider.</p></div>
                        </div>
                        <div class="red-admin-field red-admin-video-url-field">
                            <label for="gal_video">Video URL <span aria-hidden="true">*</span></label>
                            <input name="LongDesc" type="url" id="gal_video" value="<?php echo red_admin_area_html($context['videoUrl']); ?>" inputmode="url" autocomplete="url" placeholder="https://www.youtube.com/watch?v=…" required aria-describedby="video-url-help video-url-error" data-video-url />
                            <span class="red-admin-field__help" id="video-url-help">Accepts YouTube watch, share, Shorts, Live and embed links; Vimeo page and player links; or another HTTPS video page.</span>
                            <span class="red-admin-field__error" id="video-url-error" data-video-url-error hidden>Enter a supported secure video link.</span>
                        </div>
                        <div class="red-admin-video-provider-note">
                            <span aria-hidden="true">✓</span>
                            <p><strong>No embed code needed.</strong> Paste the normal address from your browser or the platform Share button.</p>
                        </div>
                    </div>

                    <article class="red-admin-video-preview" data-video-preview data-state="empty" aria-live="polite">
                        <div class="red-admin-video-preview__media" data-video-preview-media>
                            <img src="" alt="" data-video-thumbnail hidden />
                            <span class="red-admin-video-preview__placeholder" data-video-placeholder aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="M10 9l5 3-5 3z"></path></svg></span>
                            <span class="red-admin-video-preview__play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 7l8 5-8 5z"></path></svg></span>
                            <div class="red-admin-video-preview__player" data-video-player hidden></div>
                        </div>
                        <div class="red-admin-video-preview__body">
                            <div class="red-admin-video-preview__identity"><span data-video-provider>Waiting for a link</span><strong data-video-identifier>Preview will appear here</strong></div>
                            <p data-video-preview-status>Paste a video URL to check it before saving.</p>
                            <div class="red-admin-video-preview__actions">
                                <button type="button" data-video-load disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7l8 5-8 5z"></path></svg><span data-video-load-label>Load player</span></button>
                                <button type="button" data-video-copy disabled><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V5H5v11h3"></path></svg><span data-video-copy-label>Copy link</span></button>
                                <a href="#" target="_blank" rel="noopener noreferrer" data-video-open aria-disabled="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8"></path><path d="M12 7H5v12h12v-7"></path></svg>Open video</a>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="red-admin-article-panel" aria-labelledby="video-content-title">
                <div class="red-admin-article-panel__heading">
                    <div><span class="red-admin-article-panel__step">03</span><h3 id="video-content-title">Description &amp; follow-up</h3></div>
                    <p>Optional supporting copy and destination</p>
                </div>
                <div class="red-admin-video-content-grid">
                    <div class="red-admin-video-description-card">
                        <div class="red-admin-video-card-heading"><div><h4>Video description</h4><p>Add useful context below the public player.</p></div><span data-video-editor-status>Loading rich text tools…</span></div>
                        <label class="red-admin-visually-hidden" for="video-description">Video description</label>
                        <textarea name="ShortDesc" id="video-description" rows="8" data-video-editor><?php echo red_admin_area_html($context['description']); ?></textarea>
                    </div>
                    <div class="red-admin-video-link-card">
                        <div class="red-admin-video-card-heading"><div><h4>Optional follow-up link</h4><p>Add a “Read More” destination below the video.</p></div></div>
                        <div class="red-admin-field"><label for="video-link-navigator">Choose an internal page</label><select name="LinkNavigator" id="video-link-navigator" data-video-link-navigator><?php echo $context['linkNavigatorOptions']; ?></select><span class="red-admin-field__help">Selecting a page fills the destination below.</span></div>
                        <div class="red-admin-field"><label for="video-link">Link destination</label><input name="Link" type="text" id="video-link" value="<?php echo red_admin_area_html($context['link']); ?>" inputmode="url" placeholder="/page/ or https://example.com" data-video-link /><span class="red-admin-field__help">Leave blank when no follow-up link is needed.</span></div>
                        <label class="red-admin-inline-choice" for="video-new-window"><input name="NewWindow" type="checkbox" id="video-new-window" value="Y"<?php echo $context['newWindow'] === 'Y' ? ' checked="checked"' : ''; ?> /><span class="red-admin-inline-choice__control" aria-hidden="true"></span><span>Open the follow-up link in a new tab</span></label>
                    </div>
                </div>
            </section>

            <details class="red-admin-article-advanced" data-video-advanced>
                <summary>
                    <span class="red-admin-article-advanced__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"></path></svg></span>
                    <span class="red-admin-article-advanced__copy"><strong>Optional settings</strong><small>Publishing schedule, location, metadata, and supporting images</small></span>
                    <span class="red-admin-article-advanced__badge">Advanced</span>
                    <svg class="red-admin-article-advanced__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5"></path></svg>
                </summary>
                <div class="red-admin-article-advanced__body">
                    <section class="red-admin-optional-card" aria-labelledby="video-publishing-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect><path d="M7.5 3.5v4M16.5 3.5v4M3.5 9.5h17"></path></svg></span><div><h4 id="video-publishing-title">Publishing &amp; metadata</h4><p>Schedule availability and manage administrator-friendly identity values.</p></div></div>
                        <?php if ($isEdit) { ?>
                            <div class="red-admin-field-grid red-admin-video-identity-grid"><div class="red-admin-field"><label for="video-alias">URL alias</label><input name="Alias" type="text" id="video-alias" value="<?php echo red_admin_area_html($context['alias']); ?>" autocomplete="off" /></div><div class="red-admin-field"><label for="video-tags">SEO tags</label><input name="Tags" type="text" id="video-tags" value="<?php echo red_admin_area_html($context['tags']); ?>" autocomplete="off" /></div></div>
                        <?php } else { ?>
                            <p class="red-admin-video-auto-note">The alias will be generated automatically from the title. SEO tags can be refined after the video is created.</p>
                        <?php } ?>
                        <div class="red-admin-field-grid red-admin-field-grid--dates">
                            <div class="red-admin-field"><label for="video-start-date">Start date</label><?php if ($isEdit) { ?><input type="date" id="video-start-date" value="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" data-video-date="start" data-original-date="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" /><input name="StartDate" type="hidden" value="" data-date-payload disabled /><?php } else { ?><input name="StartDate" type="date" id="video-start-date" data-video-date="start" /><?php } ?><span class="red-admin-field__help">Leave blank to publish immediately.</span></div>
                            <div class="red-admin-field"><label for="video-expiration-date">Expiration date</label><?php if ($isEdit) { ?><input type="date" id="video-expiration-date" value="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" data-video-date="expiration" data-original-date="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" /><input name="ExpDate" type="hidden" value="" data-date-payload disabled /><?php } else { ?><input name="ExpDate" type="date" id="video-expiration-date" data-video-date="expiration" /><?php } ?><span class="red-admin-field__help">Leave blank to keep the video available.</span></div>
                        </div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="video-location-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon red-admin-optional-card__icon--location" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"></path><circle cx="12" cy="10" r="2"></circle></svg></span><div><h4 id="video-location-title">Content location</h4><p>Connect the video to the relevant site hierarchy.</p></div></div>
                        <div class="red-admin-field-grid red-admin-field-grid--location"><div class="red-admin-field"><label for="video-section">Section</label><select name="Sections" id="video-section"><option value="">No section</option><?php echo $context['sectionOptions']; ?></select></div><div class="red-admin-field"><label for="video-category">Category</label><select name="Categories" id="video-category"><option value="">No category</option><?php echo $context['categoryOptions']; ?></select></div><div class="red-admin-field"><label for="video-subcategory">Subcategory</label><select name="SubCategories" id="video-subcategory"><option value="">No subcategory</option><?php echo $context['subCategoryOptions']; ?></select></div><div class="red-admin-field"><label for="video-parent">Parent article</label><select name="Article" id="video-parent"><option value="">No parent article</option><?php echo $context['articleOptions']; ?></select></div></div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="video-supporting-images-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon red-admin-optional-card__icon--media" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect><circle cx="9" cy="9" r="1.5"></circle><path d="M5.5 17l4.5-4 3 2.5 2.5-2 3 3.5"></path></svg></span><div><h4 id="video-supporting-images-title">Supporting images</h4><p>Optional alternatives used by feature areas and content lists.</p></div></div>
                        <div class="red-admin-upload-grid red-admin-video-supporting-grid">
                            <?php foreach ($supportingImages as $supportingImage) { ?>
                                <article class="red-admin-upload-card" data-video-upload data-upload-field="<?php echo red_admin_area_html($supportingImage['field']); ?>" data-upload-url="<?php echo red_admin_area_html($supportingImage['uploadUrl']); ?>" aria-busy="false">
                                    <div class="red-admin-upload-card__heading"><strong><?php echo red_admin_area_html($supportingImage['title']); ?></strong><span><?php echo red_admin_area_html($supportingImage['description']); ?></span></div>
                                    <input type="hidden" name="<?php echo red_admin_area_html($supportingImage['field']); ?>" value="<?php echo red_admin_area_html($supportingImage['current']); ?>" data-upload-value />
                                    <div class="red-admin-upload-current" data-current-media<?php echo $supportingImage['current'] === '' ? ' hidden' : ''; ?>><img<?php echo $supportingImage['current'] === '' ? '' : ' src="/images/resize.php?w=180&amp;h=110&amp;img=/images/articles/'.rawurlencode($supportingImage['current']).'"'; ?> alt="Current <?php echo red_admin_area_html(strtolower($supportingImage['title'])); ?>" data-current-image /><div class="red-admin-upload-current__meta"><span>Current image</span><strong data-current-name><?php echo red_admin_area_html($supportingImage['current']); ?></strong></div><label class="red-admin-upload-remove"><input name="Delete_<?php echo red_admin_area_html($supportingImage['field']); ?>" type="checkbox" value="Y" data-video-remove-image /><span>Remove when saved</span></label></div>
                                    <input class="red-admin-visually-hidden" type="file" id="<?php echo red_admin_area_html($supportingImage['inputId']); ?>" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" tabindex="-1" aria-hidden="true" data-upload-input />
                                    <div class="red-admin-upload-dropzone" data-upload-dropzone><svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path></svg><strong><?php echo $isEdit ? 'Drop replacement here' : 'Drop image here'; ?></strong><span>or choose one from your computer</span><button type="button" class="red-admin-upload-browse" data-upload-browse><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>Browse computer</button><small>JPG, PNG or GIF · maximum 2 MB</small></div>
                                    <div class="red-admin-upload-preview" data-upload-preview hidden><img src="" alt="" data-upload-preview-image /><div><strong data-upload-file-name></strong><span data-upload-status role="status" aria-live="polite"><?php echo $isEdit ? 'Ready to upload' : 'Ready after the video is saved'; ?></span></div></div>
                                    <div class="red-admin-upload-progress" aria-hidden="true"><span data-upload-progress></span></div>
                                    <?php if ($supportingImage['field'] === 'SmallPict') { ?><div class="red-admin-field red-admin-upload-card__alignment"><label for="video-summary-alignment">Image alignment</label><select name="SmallPictAlign" id="video-summary-alignment"><option value=""<?php echo $context['smallPictAlign'] === '' ? ' selected="selected"' : ''; ?>>Theme default</option><option value="Top"<?php echo $context['smallPictAlign'] === 'Top' ? ' selected="selected"' : ''; ?>>Top</option><option value="Left"<?php echo $context['smallPictAlign'] === 'Left' ? ' selected="selected"' : ''; ?>>Left</option><option value="Right"<?php echo $context['smallPictAlign'] === 'Right' ? ' selected="selected"' : ''; ?>>Right</option></select></div><?php } ?>
                                </article>
                            <?php } ?>
                        </div>
                    </section>
                </div>
            </details>

            <input type="hidden" name="GalleryType" value="Video" />
            <input type="hidden" name="ArtRecordID" id="ArtRecordID" value="<?php echo (int) $context['artRecordId']; ?>" />
            <input type="hidden" name="RecordID" id="RecordID" value="<?php echo (int) $context['recordId']; ?>" />
            <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_area_html($context['editedBy']); ?>" />
            <?php if (!$isEdit) { ?>
                <input type="hidden" name="Language" id="Language" value="<?php echo red_admin_area_html($context['language']); ?>" />
                <input type="hidden" name="Component" id="Component" value="Gallery" />
                <input type="hidden" name="Layout" id="Layout" value="<?php echo red_admin_area_html($context['layout']); ?>" />
            <?php } ?>
            <input type="hidden" name="csrf_token" value="<?php echo red_admin_area_html($context['csrfToken']); ?>" />

            <?php if ($isEdit) { red_admin_content_revision_panel((int) $context['artRecordId']); } ?>

            <div class="red-admin-article-actions<?php echo $isEdit ? ' red-admin-article-actions--edit' : ''; ?>">
                <span id="<?php echo red_admin_area_html($messageId); ?>" class="red-admin-article-message" data-video-message role="status" aria-live="polite" hidden></span>
                <?php if ($isEdit) { ?><button type="button" class="red-admin-article-delete" data-video-delete><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"></path></svg>Delete video</button><?php } ?>
                <button type="submit" name="submit" value="Save" id="save" class="red-admin-article-save" data-video-save data-default-label="<?php echo red_admin_area_html($saveLabel); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"></path><path d="M8 4v6h8V4M8 20v-6h8v6"></path></svg><span data-save-label><?php echo red_admin_area_html($saveLabel); ?></span></button>
            </div>
        </div>
    </fieldset>
</form>
<script src="<?php echo red_admin_area_html($videoScript); ?>?v=<?php echo rawurlencode((string) $videoScriptVersion); ?>"></script>
        <?php
    }
}
