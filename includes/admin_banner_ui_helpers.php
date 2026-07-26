<?php
/**
 * Shared presentation helpers for the modern Banner create/edit workspace.
 *
 * Banner remains a Gallery subtype: the parent record lives in RED_Articles and
 * the image/link record lives in RED_C_Gallery. These helpers only prepare and
 * render the administrator UI; the existing write endpoints remain authoritative.
 */

require_once __DIR__.'/admin_article_helpers.php';
require_once __DIR__.'/admin_content_revision_ui_helpers.php';
require_once __DIR__.'/admin_seo_helpers.php';

if (!function_exists('red_admin_banner_date_meta')) {
    function red_admin_banner_date_meta($value, $sentinel)
    {
        $raw = red_admin_text($value ?? '');
        $date = substr($raw, 0, 10);
        $legacyUnset = $date === '0000-00-00';
        $display = (!$legacyUnset && $date !== $sentinel && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
            ? $date
            : '';

        return [
            'display' => $display,
            'legacyUnset' => $legacyUnset,
        ];
    }
}

if (!function_exists('red_admin_banner_preserve_option')) {
    function red_admin_banner_preserve_option($options, $selected)
    {
        $selected = red_admin_text($selected);
        if ($selected === '' || strpos((string) $options, ' selected="selected"') !== false) {
            return (string) $options;
        }

        return '<option value="'.red_admin_area_html($selected).'" selected="selected">'
            .red_admin_area_html($selected).' — unavailable; preserved</option>'.(string) $options;
    }
}

if (!function_exists('red_admin_banner_upload_url')) {
    function red_admin_banner_upload_url(array $parameters)
    {
        return '/admin/bin/post_file.php?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('red_admin_banner_photo_names')) {
    function red_admin_banner_photo_names($value)
    {
        $photos = [];
        foreach (explode(',', red_admin_text($value)) as $photo) {
            $photo = red_admin_text($photo);
            if ($photo !== '') {
                $photos[] = $photo;
            }
        }

        return $photos;
    }
}

if (!function_exists('red_admin_render_banner_form')) {
    function red_admin_render_banner_form(array $context)
    {
        $defaults = [
            'mode' => 'create',
            'returnTarget' => 'add_content_grid',
            'formId' => 'insert_gallery',
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
            'link' => '',
            'newWindow' => '',
            'linkNavigatorOptions' => '',
            'sectionOptions' => '',
            'categoryOptions' => '',
            'subCategoryOptions' => '',
            'articleOptions' => '',
            'startDateMeta' => ['display' => '', 'legacyUnset' => false],
            'expirationDateMeta' => ['display' => '', 'legacyUnset' => false],
            'photos' => [],
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
            'seoValues' => red_seo_empty_values(),
        ];
        $context = array_merge($defaults, $context);
        $isEdit = $context['mode'] === 'edit';
        $mode = $isEdit ? 'edit' : 'create';
        $formId = $isEdit ? 'update_gallery' : 'insert_gallery';
        $messageId = $isEdit ? 'msggbox_update_gallery' : 'msggbox_insert_gallery';
        $returnLabel = $isEdit ? 'Show content' : 'All content types';
        $pageLabel = $isEdit ? 'Edit Banner' : 'New Banner';
        $heading = $isEdit ? 'Edit banner' : 'Create a new banner';
        $saveLabel = $isEdit ? 'Save changes' : 'Save banner';
        $photos = is_array($context['photos']) ? array_values($context['photos']) : [];
        $primaryPhoto = $photos[0] ?? '';
        $bannerScript = '/admin/assets/js/banner-form.js';
        $bannerScriptVersion = is_file($_SERVER['DOCUMENT_ROOT'].$bannerScript)
            ? filemtime($_SERVER['DOCUMENT_ROOT'].$bannerScript)
            : '1';
        $queueScript = '/admin/assets/js/gallery-create-uploads.js';
        $queueScriptVersion = is_file($_SERVER['DOCUMENT_ROOT'].$queueScript)
            ? filemtime($_SERVER['DOCUMENT_ROOT'].$queueScript)
            : '1';
        $supportingImages = [
            [
                'field' => 'BigPict',
                'dropbox' => 'dropbox2',
                'inputId' => 'banner-feature-image',
                'title' => 'Feature image',
                'description' => 'Optional image for feature and hero placements',
                'current' => red_admin_text($context['bigPict']),
                'uploadUrl' => red_admin_text($context['uploadUrls']['BigPict'] ?? ''),
            ],
            [
                'field' => 'SmallPict',
                'dropbox' => 'dropbox3',
                'inputId' => 'banner-summary-image',
                'title' => 'Summary image',
                'description' => 'Optional image for lists and compact cards',
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

<?php if (!$isEdit) { ?>
<script>
window.RED_GALLERY_CREATE_QUEUE_UPLOADS = true;
window.RED_GALLERY_CREATE_CONFIG = {
    recordId: <?php echo json_encode((int) $context['recordId']); ?>,
    articleRecordId: <?php echo json_encode((int) $context['artRecordId']); ?>,
    galleryType: 'Banner',
    language: <?php echo json_encode(red_admin_text($context['language'])); ?>,
    csrfToken: <?php echo json_encode(red_admin_text($context['csrfToken'])); ?>,
    maxImageBytes: 2 * 1024 * 1024,
    allowedExtensions: ['jpg', 'jpeg', 'png', 'gif']
};
</script>
<?php } ?>

<form
    id="<?php echo red_admin_area_html($formId); ?>"
    name="<?php echo red_admin_area_html($formId); ?>"
    class="cp red-admin-article-form red-admin-banner-form"
    method="post"
    data-red-banner-form
    data-banner-mode="<?php echo red_admin_area_html($mode); ?>"
    data-submit-url="<?php echo red_admin_area_html($context['submitUrl']); ?>"
    data-delete-url="<?php echo red_admin_area_html($context['deleteUrl']); ?>"
    onsubmit="return <?php echo $isEdit ? 'run_update_gallery' : 'run_insert_gallery'; ?>(this);"
>
    <fieldset>
        <legend class="red-admin-visually-hidden"><?php echo red_admin_area_html($heading); ?></legend>

        <div class="red-admin-article-shell">
            <header class="red-admin-article-header red-admin-banner-header">
                <span class="red-admin-article-header__icon red-admin-banner-header__icon<?php echo $isEdit ? ' red-admin-article-header__icon--edit' : ''; ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <rect x="3.5" y="5" width="17" height="14" rx="2"></rect>
                        <circle cx="9" cy="10" r="1.5"></circle>
                        <path d="M5.5 17l4.5-4 3 2.5 2.5-2 3 3.5"></path>
                    </svg>
                </span>
                <div class="red-admin-article-header__copy">
                    <span class="red-admin-article-header__eyebrow"><?php echo $isEdit ? 'Edit Content' : 'Add Content'; ?></span>
                    <h2><?php echo red_admin_area_html($heading); ?></h2>
                    <p>Set the essentials, choose the banner image and destination, then open optional settings only when needed.</p>
                </div>
                <span class="red-admin-article-header__badge red-admin-banner-header__badge">Banner</span>
            </header>

            <section class="red-admin-article-panel" aria-labelledby="banner-basics-title">
                <div class="red-admin-article-panel__heading">
                    <div>
                        <span class="red-admin-article-panel__step">01</span>
                        <h3 id="banner-basics-title">Banner basics</h3>
                    </div>
                    <p>Title, visibility, and placement</p>
                </div>

                <div class="red-admin-field-grid red-admin-field-grid--basics">
                    <div class="red-admin-field red-admin-field--title">
                        <label for="banner-title">Title <span aria-hidden="true">*</span></label>
                        <input name="Title" type="text" id="banner-title" value="<?php echo red_admin_area_html($context['title']); ?>" autocomplete="off" required aria-describedby="banner-title-help banner-title-error" />
                        <span class="red-admin-field__help" id="banner-title-help">The administrator label used to identify this banner.</span>
                        <span class="red-admin-field__error" id="banner-title-error" data-title-error hidden>Add a title before saving.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="banner-status">Status</label>
                        <select name="Active" id="banner-status">
                            <option value="Y"<?php echo $context['active'] === 'Y' ? ' selected="selected"' : ''; ?>>Published</option>
                            <option value="N"<?php echo $context['active'] !== 'Y' ? ' selected="selected"' : ''; ?>>Inactive</option>
                        </select>
                        <span class="red-admin-field__help">Inactive banners stay out of public view.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="banner-layout-position">Layout position</label>
                        <select name="<?php echo red_admin_area_html($context['varPosition']); ?>" id="banner-layout-position">
                            <?php foreach ($context['positionOptions'] as $positionValue => $positionLabel) { ?>
                                <option value="<?php echo (int) $positionValue; ?>"<?php echo (int) $context['position'] === (int) $positionValue ? ' selected="selected"' : ''; ?>><?php echo red_admin_area_html($positionLabel); ?> (<?php echo (int) $positionValue; ?>)</option>
                            <?php } ?>
                        </select>
                        <span class="red-admin-field__help">Where this banner appears in the selected layout.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="banner-order">Order</label>
                        <input name="<?php echo red_admin_area_html($context['varPosition'].'Order'); ?>" type="number" id="banner-order" value="<?php echo red_admin_area_html($context['positionOrder']); ?>" min="0" step="1" inputmode="numeric" placeholder="Auto" />
                        <span class="red-admin-field__help">Lower numbers appear first.</span>
                    </div>

                    <label class="red-admin-choice-card" for="banner-home-feature">
                        <input name="HomeFeature" type="checkbox" id="banner-home-feature" value="Y"<?php echo $context['homeFeature'] === 'Y' ? ' checked="checked"' : ''; ?> />
                        <span class="red-admin-choice-card__control" aria-hidden="true"></span>
                        <span><strong>Feature on Home</strong><small>Include this banner in the Home feature area.</small></span>
                    </label>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-banner-media-panel" aria-labelledby="banner-media-title">
                <div class="red-admin-article-panel__heading">
                    <div>
                        <span class="red-admin-article-panel__step">02</span>
                        <h3 id="banner-media-title">Banner image &amp; destination</h3>
                    </div>
                    <p>The visual and where it leads</p>
                </div>

                <div class="red-admin-banner-media-grid">
                    <article
                        class="red-admin-upload-card red-admin-banner-primary-upload"
                        id="dropbox"
                        <?php if ($isEdit) { ?>data-banner-upload data-upload-field="Photo0" data-upload-url="<?php echo red_admin_area_html($context['uploadUrls']['Gallery'] ?? ''); ?>"<?php } else { ?>data-red-banner-queue<?php } ?>
                        aria-busy="false"
                    >
                        <div class="red-admin-upload-card__heading">
                            <strong><?php echo $isEdit && $primaryPhoto !== '' ? 'Current banner & replacement' : 'Banner image'; ?></strong>
                            <span>Use a wide image that remains clear across screen sizes.</span>
                        </div>

                        <?php if ($isEdit) { ?>
                            <?php if (empty($photos)) { ?>
                                <input type="hidden" name="Photo0" value="" data-upload-value data-banner-photo-value />
                            <?php } ?>
                            <div class="red-admin-banner-current-images" data-banner-current-images>
                                <?php foreach ($photos as $photoIndex => $photoName) { ?>
                                    <input type="hidden" name="Photo<?php echo (int) $photoIndex; ?>" value="<?php echo red_admin_area_html($photoName); ?>"<?php echo $photoIndex === 0 ? ' data-upload-value' : ''; ?> data-banner-photo-value />
                                    <div class="red-admin-upload-current red-admin-banner-upload-current" data-current-media data-banner-photo-index="<?php echo (int) $photoIndex; ?>">
                                        <img src="/images/resize.php?w=520&amp;h=180&amp;img=/images/gallery/<?php echo rawurlencode($photoName); ?>" alt="Current banner image"<?php echo $photoIndex === 0 ? ' data-current-image' : ''; ?> />
                                        <div class="red-admin-upload-current__meta">
                                            <span>Current banner</span>
                                            <strong<?php echo $photoIndex === 0 ? ' data-current-name' : ''; ?>><?php echo red_admin_area_html($photoName); ?></strong>
                                        </div>
                                        <label class="red-admin-upload-remove">
                                            <input name="Delete<?php echo (int) $photoIndex; ?>" type="checkbox" value="Y" data-remove-image data-banner-photo-delete />
                                            <span>Remove when saved</span>
                                        </label>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <div class="red-admin-upload-dropzone" data-upload-dropzone>
                            <?php if ($isEdit) { ?>
                                <input class="red-admin-visually-hidden" type="file" id="banner-main-image" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" tabindex="-1" aria-hidden="true" data-upload-input />
                            <?php } ?>
                            <svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path></svg>
                            <strong><?php echo $isEdit ? 'Drop replacement here' : 'Drop banner image here'; ?></strong>
                            <span>or choose one from your computer</span>
                            <button type="button" class="red-admin-upload-browse" data-upload-browse>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>
                                Browse computer
                            </button>
                            <small>JPG, PNG or GIF · maximum 2 MB</small>
                            <span class="message red-admin-banner-queue-message" role="status" aria-live="polite">No image selected yet.</span>
                        </div>
                        <div class="red-admin-upload-preview" data-upload-preview hidden>
                            <img src="" alt="" data-upload-preview-image />
                            <div><strong data-upload-file-name></strong><span data-upload-status role="status" aria-live="polite">Ready to upload</span></div>
                        </div>
                        <div class="red-admin-upload-progress" aria-hidden="true"><span data-upload-progress></span></div>
                    </article>

                    <div class="red-admin-banner-link-card">
                        <div class="red-admin-banner-link-card__heading">
                            <span class="red-admin-optional-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 007.1 0l2-2a5 5 0 00-7.1-7.1l-1.1 1.1"></path><path d="M14 11a5 5 0 00-7.1 0l-2 2A5 5 0 0012 20.1l1.1-1.1"></path></svg>
                            </span>
                            <div><h4>Link destination</h4><p>Choose a site page or enter a complete URL.</p></div>
                        </div>

                        <div class="red-admin-field">
                            <label for="LinkNavigator">Choose an internal page</label>
                            <select name="LinkNavigator" id="LinkNavigator"><?php echo $context['linkNavigatorOptions']; ?></select>
                            <span class="red-admin-field__help">Selecting a page fills the destination below.</span>
                        </div>
                        <div class="red-admin-field red-admin-banner-link-field">
                            <label for="Link">Link destination</label>
                            <input name="Link" type="text" id="Link" value="<?php echo red_admin_area_html($context['link']); ?>" inputmode="url" placeholder="/page/ or https://example.com" />
                            <span class="red-admin-field__help">Leave blank for a banner without a link.</span>
                        </div>
                        <label class="red-admin-inline-choice" for="banner-new-window">
                            <input name="NewWindow" type="checkbox" id="banner-new-window" value="Y"<?php echo $context['newWindow'] === 'Y' ? ' checked="checked"' : ''; ?> />
                            <span class="red-admin-inline-choice__control" aria-hidden="true"></span>
                            <span>Open this link in a new tab</span>
                        </label>
                    </div>
                </div>
            </section>

            <details class="red-admin-article-advanced" data-banner-advanced>
                <summary>
                    <span class="red-admin-article-advanced__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"></path></svg></span>
                    <span class="red-admin-article-advanced__copy"><strong>Optional settings</strong><small>Publishing schedule, location, metadata, and supporting images</small></span>
                    <span class="red-admin-article-advanced__badge">Advanced</span>
                    <svg class="red-admin-article-advanced__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5"></path></svg>
                </summary>

                <div class="red-admin-article-advanced__body">
                    <section class="red-admin-optional-card" aria-labelledby="banner-publishing-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect><path d="M7.5 3.5v4M16.5 3.5v4M3.5 9.5h17"></path></svg></span>
                            <div><h4 id="banner-publishing-title">Publishing &amp; metadata</h4><p>Schedule availability and manage administrator-friendly identity values.</p></div>
                        </div>

                        <?php if ($isEdit) { ?>
                            <div class="red-admin-field-grid red-admin-banner-identity-grid">
                                <div class="red-admin-field"><label for="banner-alias">URL alias</label><input name="Alias" type="text" id="banner-alias" value="<?php echo red_admin_area_html($context['alias']); ?>" autocomplete="off" /><span class="red-admin-field__help">Keep this stable when the banner is already in use.</span></div>
                                <div class="red-admin-field"><label for="banner-tags">SEO tags</label><input name="Tags" type="text" id="banner-tags" value="<?php echo red_admin_area_html($context['tags']); ?>" autocomplete="off" /><span class="red-admin-field__help">Optional internal search and SEO keywords.</span></div>
                            </div>
                        <?php } else { ?>
                            <p class="red-admin-banner-auto-note">The alias and tags will be generated automatically from the title.</p>
                        <?php } ?>

                        <div class="red-admin-field-grid red-admin-field-grid--dates">
                            <div class="red-admin-field">
                                <label for="banner-start-date">Start date</label>
                                <?php if ($isEdit) { ?>
                                    <input type="date" id="banner-start-date" value="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" autocomplete="off" data-banner-date="start" data-original-date="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" />
                                    <input name="StartDate" type="hidden" value="" data-date-payload disabled />
                                <?php } else { ?>
                                    <input name="StartDate" type="date" id="banner-start-date" value="" autocomplete="off" data-banner-date="start" />
                                <?php } ?>
                                <span class="red-admin-field__help">Leave blank to publish immediately.</span>
                            </div>
                            <div class="red-admin-field">
                                <label for="banner-expiration-date">Expiration date</label>
                                <?php if ($isEdit) { ?>
                                    <input type="date" id="banner-expiration-date" value="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" autocomplete="off" data-banner-date="expiration" data-original-date="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" />
                                    <input name="ExpDate" type="hidden" value="" data-date-payload disabled />
                                <?php } else { ?>
                                    <input name="ExpDate" type="date" id="banner-expiration-date" value="" autocomplete="off" data-banner-date="expiration" />
                                <?php } ?>
                                <span class="red-admin-field__help">Leave blank to keep the banner available.</span>
                            </div>
                        </div>
                    </section>

                    <?php echo red_admin_seo_fields_html($context['seoValues'], 'banner-seo'); ?>

                    <section class="red-admin-optional-card" aria-labelledby="banner-location-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon red-admin-optional-card__icon--location" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"></path><circle cx="12" cy="10" r="2"></circle></svg></span>
                            <div><h4 id="banner-location-title">Content location</h4><p>Connect the banner to the relevant hierarchy.</p></div>
                        </div>
                        <div class="red-admin-field-grid red-admin-field-grid--location">
                            <div class="red-admin-field"><label for="banner-section">Section</label><select name="Sections" id="banner-section"><option value="">No section</option><?php echo $context['sectionOptions']; ?></select></div>
                            <div class="red-admin-field"><label for="banner-category">Category</label><select name="Categories" id="banner-category"><option value="">No category</option><?php echo $context['categoryOptions']; ?></select></div>
                            <div class="red-admin-field"><label for="banner-subcategory">Subcategory</label><select name="SubCategories" id="banner-subcategory"><option value="">No subcategory</option><?php echo $context['subCategoryOptions']; ?></select></div>
                            <div class="red-admin-field"><label for="banner-parent">Parent article</label><select name="Article" id="banner-parent"><option value="">No parent article</option><?php echo $context['articleOptions']; ?></select></div>
                        </div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="banner-supporting-images-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon red-admin-optional-card__icon--media" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect><circle cx="9" cy="9" r="1.5"></circle><path d="M5.5 17l4.5-4 3 2.5 2.5-2 3 3.5"></path></svg></span>
                            <div><h4 id="banner-supporting-images-title">Supporting images</h4><p>Optional alternatives used by feature and summary placements.</p></div>
                        </div>

                        <div class="red-admin-upload-grid red-admin-banner-supporting-grid">
                            <?php foreach ($supportingImages as $supportingImage) { ?>
                                <article
                                    class="red-admin-upload-card"
                                    id="<?php echo red_admin_area_html($supportingImage['dropbox']); ?>"
                                    <?php if ($isEdit) { ?>data-banner-upload data-upload-field="<?php echo red_admin_area_html($supportingImage['field']); ?>" data-upload-url="<?php echo red_admin_area_html($supportingImage['uploadUrl']); ?>"<?php } else { ?>data-red-banner-queue<?php } ?>
                                    aria-busy="false"
                                >
                                    <div class="red-admin-upload-card__heading"><strong><?php echo red_admin_area_html($supportingImage['title']); ?></strong><span><?php echo red_admin_area_html($supportingImage['description']); ?></span></div>
                                    <?php if ($isEdit) { ?>
                                        <input type="hidden" name="<?php echo red_admin_area_html($supportingImage['field']); ?>" value="<?php echo red_admin_area_html($supportingImage['current']); ?>" data-upload-value />
                                        <div class="red-admin-upload-current" data-current-media<?php echo $supportingImage['current'] === '' ? ' hidden' : ''; ?>>
                                            <img src="<?php echo $supportingImage['current'] === '' ? '' : '/images/resize.php?w=180&amp;h=110&amp;img=/images/articles/'.rawurlencode($supportingImage['current']); ?>" alt="Current <?php echo red_admin_area_html(strtolower($supportingImage['title'])); ?>" data-current-image />
                                            <div class="red-admin-upload-current__meta"><span>Current image</span><strong data-current-name><?php echo red_admin_area_html($supportingImage['current']); ?></strong></div>
                                            <label class="red-admin-upload-remove"><input name="Delete_<?php echo red_admin_area_html($supportingImage['field']); ?>" type="checkbox" value="Y" data-remove-image /><span>Remove when saved</span></label>
                                        </div>
                                        <input class="red-admin-visually-hidden" type="file" id="<?php echo red_admin_area_html($supportingImage['inputId']); ?>" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" tabindex="-1" aria-hidden="true" data-upload-input />
                                    <?php } ?>
                                    <div class="red-admin-upload-dropzone" data-upload-dropzone>
                                        <svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path></svg>
                                        <strong><?php echo $isEdit ? 'Drop replacement here' : 'Drop image here'; ?></strong>
                                        <span>or choose one from your computer</span>
                                        <button type="button" class="red-admin-upload-browse" data-upload-browse><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>Browse computer</button>
                                        <small>JPG, PNG or GIF · maximum 2 MB</small>
                                        <span class="<?php echo $supportingImage['field'] === 'BigPict' ? 'message2' : 'message3'; ?> red-admin-banner-queue-message" role="status" aria-live="polite">No image selected yet.</span>
                                    </div>
                                    <div class="red-admin-upload-preview" data-upload-preview hidden><img src="" alt="" data-upload-preview-image /><div><strong data-upload-file-name></strong><span data-upload-status role="status" aria-live="polite">Ready to upload</span></div></div>
                                    <div class="red-admin-upload-progress" aria-hidden="true"><span data-upload-progress></span></div>
                                    <?php if ($supportingImage['field'] === 'SmallPict') { ?>
                                        <div class="red-admin-field red-admin-upload-card__alignment"><label for="banner-summary-alignment">Image alignment</label><select name="SmallPictAlign" id="banner-summary-alignment"><option value=""<?php echo $context['smallPictAlign'] === '' ? ' selected="selected"' : ''; ?>>Theme default</option><option value="Top"<?php echo $context['smallPictAlign'] === 'Top' ? ' selected="selected"' : ''; ?>>Top</option><option value="Left"<?php echo $context['smallPictAlign'] === 'Left' ? ' selected="selected"' : ''; ?>>Left</option><option value="Right"<?php echo $context['smallPictAlign'] === 'Right' ? ' selected="selected"' : ''; ?>>Right</option></select></div>
                                    <?php } ?>
                                </article>
                            <?php } ?>
                        </div>
                    </section>
                </div>
            </details>

            <input type="hidden" name="GalleryType" value="Banner" />
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
                <span id="<?php echo red_admin_area_html($messageId); ?>" class="red-admin-article-message" data-banner-message role="status" aria-live="polite" hidden></span>
                <?php if ($isEdit) { ?>
                    <button type="button" class="red-admin-article-delete" data-banner-delete>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"></path></svg>
                        Delete banner
                    </button>
                <?php } ?>
                <button type="submit" name="submit" value="Save" id="save" class="red-admin-article-save" data-banner-save data-default-label="<?php echo red_admin_area_html($saveLabel); ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"></path><path d="M8 4v6h8V4M8 20v-6h8v6"></path></svg>
                    <span data-save-label><?php echo red_admin_area_html($saveLabel); ?></span>
                </button>
            </div>
        </div>
    </fieldset>
</form>

<?php if (!$isEdit) { ?>
    <script src="<?php echo red_admin_area_html($queueScript); ?>?v=<?php echo rawurlencode((string) $queueScriptVersion); ?>"></script>
<?php } ?>
<script src="<?php echo red_admin_area_html($bannerScript); ?>?v=<?php echo rawurlencode((string) $bannerScriptVersion); ?>"></script>
        <?php
    }
}
