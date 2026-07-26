<?php
/**
 * Shared presentation helpers for the modern Gallery create/edit workspace.
 *
 * Gallery remains a paired component: the parent placement lives in
 * RED_Articles while ordered filenames and their index-matched captions live
 * in RED_C_Gallery. This renderer keeps those legacy write contracts intact.
 */

require_once __DIR__.'/admin_article_helpers.php';
require_once __DIR__.'/admin_content_revision_ui_helpers.php';
require_once __DIR__.'/admin_seo_helpers.php';

if (!function_exists('red_admin_gallery_ui_date_meta')) {
    function red_admin_gallery_ui_date_meta($value, $sentinel)
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

if (!function_exists('red_admin_gallery_ui_preserve_option')) {
    function red_admin_gallery_ui_preserve_option($options, $selected)
    {
        $selected = red_admin_text($selected);
        if ($selected === '' || strpos((string) $options, ' selected="selected"') !== false) {
            return (string) $options;
        }

        return '<option value="'.red_admin_area_html($selected).'" selected="selected">'
            .red_admin_area_html($selected).' — unavailable; preserved</option>'.(string) $options;
    }
}

if (!function_exists('red_admin_gallery_ui_upload_url')) {
    function red_admin_gallery_ui_upload_url(array $parameters)
    {
        unset($parameters['csrf_token']);
        return '/admin/bin/post_file.php?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('red_admin_gallery_ui_photo_entries')) {
    function red_admin_gallery_ui_photo_entries($photoValue, $descriptionValue)
    {
        $entries = [];
        $photos = explode(',', red_admin_text($photoValue));
        $descriptions = explode(',', red_admin_text($descriptionValue));

        foreach ($photos as $index => $photoName) {
            $photoName = red_admin_text($photoName);
            if ($photoName === '') {
                continue;
            }

            $descriptionParts = explode(';', $descriptions[$index] ?? '', 3);
            $entries[] = [
                'name' => $photoName,
                'caption' => red_admin_text($descriptionParts[0] ?? ''),
                'link' => red_admin_text($descriptionParts[1] ?? ''),
            ];
        }

        return $entries;
    }
}

if (!function_exists('red_admin_gallery_ui_presentation')) {
    function red_admin_gallery_ui_presentation($newWindowValue)
    {
        return red_admin_text($newWindowValue) === 'Y' ? 'carousel' : 'stack';
    }
}

if (!function_exists('red_admin_render_gallery_form')) {
    function red_admin_render_gallery_form(array $context)
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
            'presentation' => 'stack',
            'photos' => [],
            'sectionOptions' => '',
            'categoryOptions' => '',
            'subCategoryOptions' => '',
            'articleOptions' => '',
            'startDateMeta' => ['display' => '', 'legacyUnset' => false],
            'expirationDateMeta' => ['display' => '', 'legacyUnset' => false],
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
        $pageLabel = $isEdit ? 'Edit Gallery' : 'New Gallery';
        $heading = $isEdit ? 'Edit gallery' : 'Create a new gallery';
        $saveLabel = $isEdit ? 'Save changes' : 'Save gallery';
        $presentation = $context['presentation'] === 'carousel' ? 'carousel' : 'stack';
        $photos = is_array($context['photos']) ? array_values($context['photos']) : [];
        $galleryScript = '/admin/assets/js/gallery-form.js';
        $galleryScriptVersion = is_file($_SERVER['DOCUMENT_ROOT'].$galleryScript)
            ? filemtime($_SERVER['DOCUMENT_ROOT'].$galleryScript)
            : '1';
        $supportingImages = [
            [
                'field' => 'BigPict',
                'inputId' => 'gallery-feature-image-input',
                'title' => 'Feature image',
                'description' => 'Optional image used by feature and hero placements',
                'current' => red_admin_text($context['bigPict']),
                'uploadUrl' => red_admin_text($context['uploadUrls']['BigPict'] ?? ''),
            ],
            [
                'field' => 'SmallPict',
                'inputId' => 'gallery-summary-image-input',
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
    class="cp red-admin-article-form red-admin-gallery-form"
    method="post"
    data-red-gallery-form
    data-gallery-mode="<?php echo red_admin_area_html($mode); ?>"
    data-submit-url="<?php echo red_admin_area_html($context['submitUrl']); ?>"
    data-delete-url="<?php echo red_admin_area_html($context['deleteUrl']); ?>"
    data-max-image-bytes="2097152"
    data-max-gallery-batch="10"
    onsubmit="return <?php echo $isEdit ? 'run_update_gallery' : 'run_insert_gallery'; ?>(this);"
>
    <fieldset>
        <legend class="red-admin-visually-hidden"><?php echo red_admin_area_html($heading); ?></legend>

        <div class="red-admin-article-shell">
            <header class="red-admin-article-header red-admin-gallery-header">
                <span class="red-admin-article-header__icon red-admin-gallery-header__icon<?php echo $isEdit ? ' red-admin-article-header__icon--edit' : ''; ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="4" y="5" width="14" height="12" rx="2"></rect><path d="M7 14l3-3 2.5 2 2-1.5L18 15"></path><path d="M8 20h11a2 2 0 002-2V9"></path><circle cx="9" cy="9" r="1.25"></circle></svg>
                </span>
                <div class="red-admin-article-header__copy">
                    <span class="red-admin-article-header__eyebrow"><?php echo $isEdit ? 'Edit Content' : 'Add Content'; ?></span>
                    <h2><?php echo red_admin_area_html($heading); ?></h2>
                    <p>Choose the presentation, arrange the images and captions, then open optional settings only when needed.</p>
                </div>
                <span class="red-admin-article-header__badge red-admin-gallery-header__badge">Gallery</span>
            </header>

            <section class="red-admin-article-panel" aria-labelledby="gallery-basics-title">
                <div class="red-admin-article-panel__heading">
                    <div><span class="red-admin-article-panel__step">01</span><h3 id="gallery-basics-title">Gallery basics</h3></div>
                    <p>Title, visibility, and placement</p>
                </div>

                <div class="red-admin-field-grid red-admin-field-grid--basics">
                    <div class="red-admin-field red-admin-field--title">
                        <label for="gallery-title">Title <span aria-hidden="true">*</span></label>
                        <input name="Title" type="text" id="gallery-title" value="<?php echo red_admin_area_html($context['title']); ?>" autocomplete="off" required aria-describedby="gallery-title-help gallery-title-error" />
                        <span class="red-admin-field__help" id="gallery-title-help">The administrator label used to identify this gallery.</span>
                        <span class="red-admin-field__error" id="gallery-title-error" data-title-error hidden>Add a title before saving.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="gallery-status">Status</label>
                        <select name="Active" id="gallery-status">
                            <option value="Y"<?php echo $context['active'] === 'Y' ? ' selected="selected"' : ''; ?>>Published</option>
                            <option value="N"<?php echo $context['active'] !== 'Y' ? ' selected="selected"' : ''; ?>>Inactive</option>
                        </select>
                        <span class="red-admin-field__help">Inactive galleries stay out of public view.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="gallery-layout-position">Layout position</label>
                        <select name="<?php echo red_admin_area_html($context['varPosition']); ?>" id="gallery-layout-position">
                            <?php foreach ($context['positionOptions'] as $positionValue => $positionLabel) { ?>
                                <option value="<?php echo (int) $positionValue; ?>"<?php echo (int) $context['position'] === (int) $positionValue ? ' selected="selected"' : ''; ?>><?php echo red_admin_area_html($positionLabel); ?> (<?php echo (int) $positionValue; ?>)</option>
                            <?php } ?>
                        </select>
                        <span class="red-admin-field__help">Where this gallery appears in the selected layout.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="gallery-order">Order</label>
                        <input name="<?php echo red_admin_area_html($context['varPosition'].'Order'); ?>" type="number" id="gallery-order" value="<?php echo red_admin_area_html($context['positionOrder']); ?>" min="0" step="1" inputmode="numeric" placeholder="Auto" />
                        <span class="red-admin-field__help">Lower numbers appear first.</span>
                    </div>

                    <label class="red-admin-choice-card" for="gallery-home-feature">
                        <input name="HomeFeature" type="checkbox" id="gallery-home-feature" value="Y"<?php echo $context['homeFeature'] === 'Y' ? ' checked="checked"' : ''; ?> />
                        <span class="red-admin-choice-card__control" aria-hidden="true"></span>
                        <span><strong>Feature on Home</strong><small>Include this gallery in the Home feature area.</small></span>
                    </label>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-gallery-media-panel" aria-labelledby="gallery-images-title">
                <div class="red-admin-article-panel__heading">
                    <div><span class="red-admin-article-panel__step">02</span><h3 id="gallery-images-title">Gallery images</h3></div>
                    <span class="red-admin-gallery-count" data-gallery-count role="status" aria-live="polite"><?php echo count($photos); ?> <?php echo count($photos) === 1 ? 'image' : 'images'; ?></span>
                </div>

                <fieldset class="red-admin-gallery-presentation">
                    <legend>Gallery presentation</legend>
                    <div class="red-admin-gallery-presentation__grid">
                        <label class="red-admin-gallery-presentation-card">
                            <input name="GalleryPresentation" type="radio" value="stack"<?php echo $presentation === 'stack' ? ' checked="checked"' : ''; ?> />
                            <span class="red-admin-gallery-presentation-card__visual red-admin-gallery-presentation-card__visual--stack" aria-hidden="true"><i></i><i></i><i></i></span>
                            <span><strong>Photo stack</strong><small>Show every image together in a responsive photo collection.</small></span>
                        </label>
                        <label class="red-admin-gallery-presentation-card">
                            <input name="GalleryPresentation" type="radio" value="carousel"<?php echo $presentation === 'carousel' ? ' checked="checked"' : ''; ?> />
                            <span class="red-admin-gallery-presentation-card__visual red-admin-gallery-presentation-card__visual--carousel" aria-hidden="true"><i></i><i></i><i></i></span>
                            <span><strong>Carousel</strong><small>Show one image at a time with accessible controls.</small></span>
                        </label>
                    </div>
                </fieldset>

                <article class="red-admin-upload-card red-admin-gallery-uploader" id="dropbox" data-gallery-primary-upload data-upload-url="<?php echo red_admin_area_html($context['uploadUrls']['Gallery'] ?? ''); ?>" aria-busy="false">
                    <div class="red-admin-upload-card__heading">
                        <strong><?php echo $isEdit ? 'Add more images' : 'Choose gallery images'; ?></strong>
                        <span><?php echo $isEdit ? 'New images upload immediately and join the ordered collection below.' : 'Images stay safely queued until the Gallery and Article records are saved.'; ?></span>
                    </div>
                    <div class="red-admin-upload-dropzone" data-upload-dropzone>
                        <input class="red-admin-visually-hidden red-admin-gallery-file-input" type="file" id="gallery-images-input" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" multiple tabindex="-1" aria-hidden="true" data-upload-input />
                        <svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path></svg>
                        <strong>Drop images here</strong>
                        <span>or choose a batch from your computer</span>
                        <button type="button" class="red-admin-upload-browse" data-upload-browse><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>Browse images</button>
                        <small>JPG, PNG or GIF · maximum 2 MB each · up to 10 images per batch</small>
                        <span class="red-admin-gallery-upload-status" data-gallery-upload-status role="status" aria-live="polite"><?php echo $isEdit ? 'No new images selected.' : 'No images queued yet.'; ?></span>
                    </div>
                    <div class="red-admin-upload-progress" aria-hidden="true"><span data-upload-progress></span></div>
                </article>

                <div class="red-admin-gallery-empty" data-gallery-empty<?php echo empty($photos) ? '' : ' hidden'; ?>>
                    <span aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="14" height="12" rx="2"></rect><path d="M7 14l3-3 2.5 2 2-1.5L18 15"></path><path d="M8 20h11a2 2 0 002-2V9"></path></svg></span>
                    <div><strong>No gallery images yet</strong><p>Browse or drop images above. Captions and order can be adjusted before saving.</p></div>
                </div>

                <div class="red-admin-gallery-collection" data-gallery-collection>
                    <?php foreach ($photos as $photoIndex => $photo) {
                        $photoName = red_admin_text($photo['name'] ?? '');
                        $photoCaption = red_admin_text($photo['caption'] ?? '');
                        $photoLink = red_admin_text($photo['link'] ?? '');
                        if ($photoName === '') {
                            continue;
                        }
                        ?>
                        <article class="red-admin-gallery-photo-card" data-gallery-photo-card data-gallery-photo-state="stored">
                            <div class="red-admin-gallery-photo-card__media">
                                <img src="/images/resize.php?w=440&amp;h=280&amp;img=/images/gallery/<?php echo rawurlencode($photoName); ?>" alt="Current gallery image: <?php echo red_admin_area_html($photoCaption !== '' ? $photoCaption : $photoName); ?>" data-gallery-photo-preview />
                                <span class="red-admin-gallery-photo-card__order" data-gallery-order><?php echo (int) $photoIndex + 1; ?></span>
                                <span class="red-admin-gallery-photo-card__state" data-gallery-state-label>Saved</span>
                            </div>
                            <div class="red-admin-gallery-photo-card__body">
                                <strong class="red-admin-gallery-photo-card__name" title="<?php echo red_admin_area_html($photoName); ?>" data-gallery-file-name><?php echo red_admin_area_html($photoName); ?></strong>
                                <input type="hidden" name="Photo<?php echo (int) $photoIndex; ?>" value="<?php echo red_admin_area_html($photoName); ?>" data-gallery-photo-value />
                                <label class="red-admin-gallery-photo-card__field"><span>Caption</span><input type="text" value="<?php echo red_admin_area_html($photoCaption); ?>" maxlength="240" autocomplete="off" data-gallery-caption /><small>No commas or semicolons.</small></label>
                                <label class="red-admin-gallery-photo-card__field"><span>Caption link <em>optional</em></span><input type="text" value="<?php echo red_admin_area_html($photoLink); ?>" inputmode="url" autocomplete="off" placeholder="/page/ or https://example.com" data-gallery-caption-link /><small>Use /page/ or HTTPS. No commas or semicolons.</small></label>
                                <div class="red-admin-gallery-photo-card__controls">
                                    <div class="red-admin-gallery-order-controls" role="group" aria-label="Change image order">
                                        <button type="button" data-gallery-move="earlier" aria-label="Move image earlier"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"></path></svg></button>
                                        <button type="button" data-gallery-move="later" aria-label="Move image later"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M18 13l-6 6-6-6"></path></svg></button>
                                    </div>
                                    <label class="red-admin-gallery-remove-choice" data-gallery-remove-choice><input name="Delete<?php echo (int) $photoIndex; ?>" type="checkbox" value="Y" data-gallery-remove /><span>Remove from gallery when saved</span></label>
                                    <button type="button" class="red-admin-gallery-discard" data-gallery-discard hidden>Remove queued image</button>
                                </div>
                                <span class="red-admin-gallery-photo-card__status" data-gallery-card-status role="status" aria-live="polite"></span>
                            </div>
                        </article>
                    <?php } ?>
                </div>

                <input type="hidden" name="ShortDesc" value="" data-gallery-short-desc />

                <template data-gallery-photo-template>
                    <article class="red-admin-gallery-photo-card" data-gallery-photo-card data-gallery-photo-state="queued">
                        <div class="red-admin-gallery-photo-card__media"><img alt="" data-gallery-photo-preview /><span class="red-admin-gallery-photo-card__order" data-gallery-order></span><span class="red-admin-gallery-photo-card__state" data-gallery-state-label>Queued</span></div>
                        <div class="red-admin-gallery-photo-card__body">
                            <strong class="red-admin-gallery-photo-card__name" data-gallery-file-name></strong>
                            <input type="hidden" value="" data-gallery-photo-value disabled />
                            <label class="red-admin-gallery-photo-card__field"><span>Caption</span><input type="text" value="" maxlength="240" autocomplete="off" data-gallery-caption /><small>No commas or semicolons.</small></label>
                            <label class="red-admin-gallery-photo-card__field"><span>Caption link <em>optional</em></span><input type="text" value="" inputmode="url" autocomplete="off" placeholder="/page/ or https://example.com" data-gallery-caption-link /><small>Use /page/ or HTTPS. No commas or semicolons.</small></label>
                            <div class="red-admin-gallery-photo-card__controls">
                                <div class="red-admin-gallery-order-controls" role="group" aria-label="Change image order"><button type="button" data-gallery-move="earlier" aria-label="Move image earlier"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"></path></svg></button><button type="button" data-gallery-move="later" aria-label="Move image later"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M18 13l-6 6-6-6"></path></svg></button></div>
                                <label class="red-admin-gallery-remove-choice" data-gallery-remove-choice hidden><input type="checkbox" value="Y" data-gallery-remove disabled /><span>Remove from gallery when saved</span></label>
                                <button type="button" class="red-admin-gallery-discard" data-gallery-discard>Remove queued image</button>
                            </div>
                            <span class="red-admin-gallery-photo-card__status" data-gallery-card-status role="status" aria-live="polite">Ready to upload after the gallery is saved.</span>
                        </div>
                    </article>
                </template>
            </section>

            <details class="red-admin-article-advanced" data-gallery-advanced>
                <summary>
                    <span class="red-admin-article-advanced__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"></path></svg></span>
                    <span class="red-admin-article-advanced__copy"><strong>Optional settings</strong><small>Publishing schedule, location, metadata, and supporting images</small></span>
                    <span class="red-admin-article-advanced__badge">Advanced</span>
                    <svg class="red-admin-article-advanced__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5"></path></svg>
                </summary>

                <div class="red-admin-article-advanced__body">
                    <section class="red-admin-optional-card" aria-labelledby="gallery-publishing-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect><path d="M7.5 3.5v4M16.5 3.5v4M3.5 9.5h17"></path></svg></span><div><h4 id="gallery-publishing-title">Publishing &amp; metadata</h4><p>Schedule availability and manage administrator-friendly identity values.</p></div></div>
                        <?php if ($isEdit) { ?>
                            <div class="red-admin-field-grid red-admin-gallery-identity-grid">
                                <div class="red-admin-field"><label for="gallery-alias">URL alias</label><input name="Alias" type="text" id="gallery-alias" value="<?php echo red_admin_area_html($context['alias']); ?>" autocomplete="off" /><span class="red-admin-field__help">Keep this stable when the gallery is already in use.</span></div>
                                <div class="red-admin-field"><label for="gallery-tags">SEO tags</label><input name="Tags" type="text" id="gallery-tags" value="<?php echo red_admin_area_html($context['tags']); ?>" autocomplete="off" /><span class="red-admin-field__help">Optional internal search and SEO keywords.</span></div>
                            </div>
                        <?php } else { ?>
                            <p class="red-admin-gallery-auto-note">The alias and tags will be generated automatically from the title.</p>
                        <?php } ?>
                        <div class="red-admin-field-grid red-admin-field-grid--dates">
                            <div class="red-admin-field"><label for="gallery-start-date">Start date</label><?php if ($isEdit) { ?><input type="date" id="gallery-start-date" value="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" autocomplete="off" data-gallery-date="start" data-original-date="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" /><input name="StartDate" type="hidden" value="" data-date-payload disabled /><?php } else { ?><input name="StartDate" type="date" id="gallery-start-date" value="" autocomplete="off" data-gallery-date="start" /><?php } ?><span class="red-admin-field__help">Leave blank to publish immediately.</span></div>
                            <div class="red-admin-field"><label for="gallery-expiration-date">Expiration date</label><?php if ($isEdit) { ?><input type="date" id="gallery-expiration-date" value="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" autocomplete="off" data-gallery-date="expiration" data-original-date="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" /><input name="ExpDate" type="hidden" value="" data-date-payload disabled /><?php } else { ?><input name="ExpDate" type="date" id="gallery-expiration-date" value="" autocomplete="off" data-gallery-date="expiration" /><?php } ?><span class="red-admin-field__help">Leave blank to keep the gallery available.</span></div>
                        </div>
                    </section>

                    <?php echo red_admin_seo_fields_html($context['seoValues'], 'gallery-seo'); ?>

                    <section class="red-admin-optional-card" aria-labelledby="gallery-location-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon red-admin-optional-card__icon--location" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"></path><circle cx="12" cy="10" r="2"></circle></svg></span><div><h4 id="gallery-location-title">Content location</h4><p>Connect the gallery to the relevant hierarchy.</p></div></div>
                        <div class="red-admin-field-grid red-admin-field-grid--location">
                            <div class="red-admin-field"><label for="gallery-section">Section</label><select name="Sections" id="gallery-section"><option value="">No section</option><?php echo $context['sectionOptions']; ?></select></div>
                            <div class="red-admin-field"><label for="gallery-category">Category</label><select name="Categories" id="gallery-category"><option value="">No category</option><?php echo $context['categoryOptions']; ?></select></div>
                            <div class="red-admin-field"><label for="gallery-subcategory">Subcategory</label><select name="SubCategories" id="gallery-subcategory"><option value="">No subcategory</option><?php echo $context['subCategoryOptions']; ?></select></div>
                            <div class="red-admin-field"><label for="gallery-parent">Parent article</label><select name="Article" id="gallery-parent"><option value="">No parent article</option><?php echo $context['articleOptions']; ?></select></div>
                        </div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="gallery-supporting-images-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon red-admin-optional-card__icon--media" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect><circle cx="9" cy="9" r="1.5"></circle><path d="M5.5 17l4.5-4 3 2.5 2.5-2 3 3.5"></path></svg></span><div><h4 id="gallery-supporting-images-title">Supporting images</h4><p>Optional alternatives used outside the gallery image collection.</p></div></div>
                        <div class="red-admin-upload-grid red-admin-gallery-supporting-grid">
                            <?php foreach ($supportingImages as $supportingImage) { ?>
                                <article class="red-admin-upload-card" data-gallery-support-upload data-upload-field="<?php echo red_admin_area_html($supportingImage['field']); ?>" data-upload-url="<?php echo red_admin_area_html($supportingImage['uploadUrl']); ?>" aria-busy="false">
                                    <div class="red-admin-upload-card__heading"><strong><?php echo red_admin_area_html($supportingImage['title']); ?></strong><span><?php echo red_admin_area_html($supportingImage['description']); ?></span></div>
                                    <input type="hidden" name="<?php echo red_admin_area_html($supportingImage['field']); ?>" value="<?php echo red_admin_area_html($supportingImage['current']); ?>" data-upload-value />
                                    <div class="red-admin-upload-current" data-current-media<?php echo $supportingImage['current'] === '' ? ' hidden' : ''; ?>>
                                        <img<?php echo $supportingImage['current'] === '' ? '' : ' src="/images/resize.php?w=180&amp;h=110&amp;img=/images/articles/'.rawurlencode($supportingImage['current']).'"'; ?> alt="Current <?php echo red_admin_area_html(strtolower($supportingImage['title'])); ?>" data-current-image />
                                        <div class="red-admin-upload-current__meta"><span>Current image</span><strong data-current-name><?php echo red_admin_area_html($supportingImage['current']); ?></strong></div>
                                        <label class="red-admin-upload-remove"><input name="Delete_<?php echo red_admin_area_html($supportingImage['field']); ?>" type="checkbox" value="Y" data-gallery-remove-image /><span>Remove when saved</span></label>
                                    </div>
                                    <input class="red-admin-visually-hidden red-admin-gallery-file-input" type="file" id="<?php echo red_admin_area_html($supportingImage['inputId']); ?>" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" tabindex="-1" aria-hidden="true" data-upload-input />
                                    <div class="red-admin-upload-dropzone" data-upload-dropzone><svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path></svg><strong><?php echo $isEdit ? 'Drop replacement here' : 'Drop image here'; ?></strong><span>or choose one from your computer</span><button type="button" class="red-admin-upload-browse" data-upload-browse><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>Browse computer</button><small>JPG, PNG or GIF · maximum 2 MB</small></div>
                                    <div class="red-admin-upload-preview" data-upload-preview hidden><img src="" alt="" data-upload-preview-image /><div><strong data-upload-file-name></strong><span data-upload-status role="status" aria-live="polite"><?php echo $isEdit ? 'Ready to upload' : 'Ready after the gallery is saved'; ?></span></div></div>
                                    <div class="red-admin-upload-progress" aria-hidden="true"><span data-upload-progress></span></div>
                                    <?php if ($supportingImage['field'] === 'SmallPict') { ?><div class="red-admin-field red-admin-upload-card__alignment"><label for="gallery-summary-alignment">Image alignment</label><select name="SmallPictAlign" id="gallery-summary-alignment"><option value=""<?php echo $context['smallPictAlign'] === '' ? ' selected="selected"' : ''; ?>>Theme default</option><option value="Top"<?php echo $context['smallPictAlign'] === 'Top' ? ' selected="selected"' : ''; ?>>Top</option><option value="Left"<?php echo $context['smallPictAlign'] === 'Left' ? ' selected="selected"' : ''; ?>>Left</option><option value="Right"<?php echo $context['smallPictAlign'] === 'Right' ? ' selected="selected"' : ''; ?>>Right</option></select></div><?php } ?>
                                </article>
                            <?php } ?>
                        </div>
                    </section>
                </div>
            </details>

            <input type="hidden" name="GalleryType" value="Gallery" />
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
                <span id="<?php echo red_admin_area_html($messageId); ?>" class="red-admin-article-message" data-gallery-message role="status" aria-live="polite" hidden></span>
                <?php if ($isEdit) { ?>
                    <button type="button" class="red-admin-article-delete" data-gallery-delete><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"></path></svg>Delete gallery</button>
                <?php } ?>
                <button type="submit" name="submit" value="Save" id="save" class="red-admin-article-save" data-gallery-save data-default-label="<?php echo red_admin_area_html($saveLabel); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"></path><path d="M8 4v6h8V4M8 20v-6h8v6"></path></svg><span data-save-label><?php echo red_admin_area_html($saveLabel); ?></span></button>
            </div>
        </div>
    </fieldset>
</form>
<script src="<?php echo red_admin_area_html($galleryScript); ?>?v=<?php echo rawurlencode((string) $galleryScriptVersion); ?>"></script>
        <?php
    }
}
