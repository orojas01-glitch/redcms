<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 4.0 - (2025/03/06)
 * @requires linux v1.2.2 or later
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
 **/
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_menu_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_content_revision_ui_helpers.php';

$RecordID = (int) ($_POST['RecordID'] ?? 0);
$VarPosition = red_admin_article_position_column($_POST['VarPosition'] ?? '');
if ($RecordID <= 0 || $VarPosition === null) {
    echo 'no';
    exit;
}

$ArticleSelected = red_admin_text($_POST['Article'] ?? '');
$Layout = red_admin_text($_POST['Layout'] ?? '');

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_article_access($db->connection, $RecordID);
$row = red_admin_article_full_record($db->connection, $RecordID);
if (!$row) {
    $db->close();
    echo 'no';
    exit;
}

if ($Layout === '') {
    $Layout = red_admin_text($row['Layout'] ?? '');
}

$Language = substr(red_admin_text($row['Language'] ?? ''), 0, 2);
$Section = red_admin_text($row['Sections'] ?? '');
$Category = red_admin_text($row['Categories'] ?? '');
$SubCategory = red_admin_text($row['SubCategories'] ?? '');
$Article = red_admin_text($row['Article'] ?? $ArticleSelected);
$HomeFeature = red_admin_text($row['HomeFeature'] ?? '');
$positionOrder = (int) ($row[$VarPosition.'Order'] ?? 0);
$currentPosition = (int) ($row[$VarPosition] ?? 0);

$positionOptions = red_admin_article_layout_position_options($db->connection, $Layout);
if (!array_key_exists($currentPosition, $positionOptions)) {
    $positionOptions = [$currentPosition => 'Unavailable; preserved'] + $positionOptions;
}

$linkNavigatorOptions = red_admin_main_menu_link_options($db->connection);
$sectionOptions = red_admin_article_area_options($db->connection, 'RED_Sections', 'Sections', $Section);
$categoryOptions = red_admin_article_area_options($db->connection, 'RED_Categories', 'Categories', $Category);
$subCategoryOptions = red_admin_article_area_options($db->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
$articleOptions = red_admin_article_page_options($db->connection, $Article);

$preserveUnavailableOption = static function ($options, $selected) {
    $selected = red_admin_text($selected);
    if ($selected === '' || strpos($options, ' selected="selected"') !== false) {
        return $options;
    }

    return '<option value="'.red_admin_area_html($selected).'" data-red-hierarchy-unavailable="true" selected="selected">'
        .red_admin_area_html($selected).' — unavailable; preserved</option>'.$options;
};
$sectionOptions = $preserveUnavailableOption($sectionOptions, $Section);
$categoryOptions = $preserveUnavailableOption($categoryOptions, $Category);
$subCategoryOptions = $preserveUnavailableOption($subCategoryOptions, $SubCategory);
$articleOptions = $preserveUnavailableOption($articleOptions, $Article);
$db->close();

$csrfToken = red_csrf_token();
$uploadUrls = [];
foreach (['BigPict', 'SmallPict', 'SmallPict2'] as $uploadCase) {
    $uploadUrls[$uploadCase] = '/admin/bin/post_file.php?'.http_build_query(
        [
            'RecordID' => $RecordID,
            'UC' => $uploadCase,
            'Language' => $Language,
            'csrf_token' => $csrfToken,
        ],
        '',
        '&',
        PHP_QUERY_RFC3986
    );
}

$forwardedProtocol = strtolower(red_admin_text($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$requestUsesHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || $forwardedProtocol === 'https';
$publicUrl = ($requestUsesHttps ? 'https' : 'http').'://'.BASE_URL;
if ($Section !== '') {
    $publicUrl .= '/'.$Section.'/';
}
if ($Category !== '') {
    $publicUrl .= $Category.'/';
}
if ($SubCategory !== '') {
    $publicUrl .= $SubCategory.'/';
}
$publicUrl = trim($publicUrl.red_admin_text($row['Alias'] ?? ''));

$dateMeta = static function ($value, $sentinel) {
    $raw = red_admin_text($value ?? '');
    $date = substr($raw, 0, 10);
    $legacyUnset = $date === '0000-00-00';
    $display = (!$legacyUnset && $date !== $sentinel && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : '';

    return [
        'display' => $display,
        'legacyUnset' => $legacyUnset,
    ];
};

$startDateMeta = $dateMeta($row['StartDate'] ?? '', '1970-01-01');
$expirationDateMeta = $dateMeta($row['ExpDate'] ?? '', '9999-12-31');

$uploadCards = [
    [
        'field' => 'BigPict',
        'id' => 'article-feature-image',
        'title' => 'Feature image',
        'description' => 'Large feature and hero placements',
        'current' => red_admin_text($row['BigPict'] ?? ''),
        'alignmentName' => '',
        'alignmentId' => '',
        'alignmentValue' => '',
    ],
    [
        'field' => 'SmallPict',
        'id' => 'article-summary-image',
        'title' => 'Summary image',
        'description' => 'Article descriptions and list cards',
        'current' => red_admin_text($row['SmallPict'] ?? ''),
        'alignmentName' => 'SmallPictAlign',
        'alignmentId' => 'article-summary-alignment',
        'alignmentValue' => red_admin_text($row['SmallPictAlign'] ?? ''),
    ],
    [
        'field' => 'SmallPict2',
        'id' => 'article-content-image',
        'title' => 'Article image',
        'description' => 'Landing-page and article placements',
        'current' => red_admin_text($row['SmallPict2'] ?? ''),
        'alignmentName' => 'SmallPictAlign2',
        'alignmentId' => 'article-content-alignment',
        'alignmentValue' => red_admin_text($row['SmallPictAlign2'] ?? ''),
    ],
];

$articleFormScript = '/admin/assets/js/new-article-form.js';
$articleFormScriptVersion = filemtime($_SERVER['DOCUMENT_ROOT'].$articleFormScript);
?>

<div class="cp_viewall red-admin-article-return">
    <button type="button" class="red-admin-article-return__button" onclick="showdiv('edit_content_grid'); return false;">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15 18l-6-6 6-6"></path>
        </svg>
        <span>Show content</span>
    </button>
    <span class="red-admin-article-return__divider" aria-hidden="true">/</span>
    <span aria-current="page">Edit Article</span>
</div>

<form
    id="update_content"
    name="update_content"
    class="cp red-admin-article-form"
    method="post"
    data-red-article-form
    data-article-mode="edit"
    data-submit-url="/admin/bin/update_content.php"
    data-delete-url="/admin/bin/delete_label.php"
    onsubmit="return run_update_content(this);"
>
    <fieldset>
        <legend class="red-admin-visually-hidden">Edit article</legend>

        <div class="red-admin-article-shell">
            <header class="red-admin-article-header">
                <span class="red-admin-article-header__icon red-admin-article-header__icon--edit" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M5 19l3.6-.8L18 8.8 14.2 5 4.8 14.4z"></path>
                        <path d="M12.8 6.4l3.8 3.8M5 19h14"></path>
                    </svg>
                </span>
                <div class="red-admin-article-header__copy">
                    <span class="red-admin-article-header__eyebrow">Edit Content</span>
                    <h2>Edit article</h2>
                    <p>Update the essential content first, then open optional settings for publishing, location, and media.</p>
                </div>
                <div class="red-admin-article-header__actions">
                    <button
                        type="button"
                        class="red-admin-article-view-link"
                        data-copy-page-link
                        data-copy-value="<?php echo red_admin_area_html($publicUrl); ?>"
                        data-copy-default-label="Copy page link"
                        aria-label="Copy current page address to the clipboard"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="8" y="8" width="11" height="11" rx="2"></rect>
                            <path d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"></path>
                        </svg>
                        <span data-copy-label>Copy page link</span>
                    </button>
                    <span class="red-admin-article-header__badge">Article</span>
                </div>
            </header>

            <span class="red-admin-visually-hidden" role="status" aria-live="polite" data-copy-status></span>

            <section class="red-admin-article-panel" aria-labelledby="article-basics-title">
                <div class="red-admin-article-panel__heading">
                    <div>
                        <span class="red-admin-article-panel__step">01</span>
                        <h3 id="article-basics-title">Article basics</h3>
                    </div>
                    <p>Title, visibility, and placement</p>
                </div>

                <div class="red-admin-field-grid red-admin-field-grid--basics">
                    <div class="red-admin-field red-admin-field--title">
                        <label for="article-title">Title <span aria-hidden="true">*</span></label>
                        <input name="Title" type="text" id="article-title" value="<?php echo red_admin_area_html($row['Title'] ?? ''); ?>" autocomplete="off" required aria-describedby="article-title-help article-title-error" />
                        <span class="red-admin-field__help" id="article-title-help">The public heading and primary editor label.</span>
                        <span class="red-admin-field__error" id="article-title-error" data-title-error hidden>Add a title before saving.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="article-status">Status</label>
                        <select name="Active" id="article-status">
                            <option value="Y"<?php echo ($row['Active'] ?? '') === 'Y' ? ' selected="selected"' : ''; ?>>Published</option>
                            <option value="N"<?php echo ($row['Active'] ?? '') !== 'Y' ? ' selected="selected"' : ''; ?>>Draft</option>
                        </select>
                        <span class="red-admin-field__help">Draft articles stay out of public view.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="article-layout-position">Layout position</label>
                        <select name="<?php echo red_admin_area_html($VarPosition); ?>" id="article-layout-position">
                            <?php foreach ($positionOptions as $positionValue => $positionLabel) { ?>
                                <option value="<?php echo (int) $positionValue; ?>"<?php echo (int) $positionValue === $currentPosition ? ' selected="selected"' : ''; ?>><?php echo red_admin_area_html($positionLabel); ?> (<?php echo (int) $positionValue; ?>)</option>
                            <?php } ?>
                        </select>
                        <span class="red-admin-field__help">Where this article appears in the selected layout.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="article-order">Order</label>
                        <input name="<?php echo red_admin_area_html($VarPosition.'Order'); ?>" type="number" id="article-order" value="<?php echo $positionOrder; ?>" min="0" step="1" inputmode="numeric" />
                        <span class="red-admin-field__help">Lower numbers appear first.</span>
                    </div>

                    <label class="red-admin-choice-card" for="article-home-feature">
                        <input name="HomeFeature" type="checkbox" id="article-home-feature" value="Y"<?php echo $HomeFeature === 'Y' ? ' checked="checked"' : ''; ?> />
                        <span class="red-admin-choice-card__control" aria-hidden="true"></span>
                        <span>
                            <strong>Feature on Home</strong>
                            <small>Include this article in the Home feature area.</small>
                        </span>
                    </label>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-article-panel--content" aria-labelledby="article-content-title">
                <div class="red-admin-article-panel__heading">
                    <div>
                        <span class="red-admin-article-panel__step">02</span>
                        <h3 id="article-content-title">Edit the content</h3>
                    </div>
                    <span class="red-admin-editor-status" data-editor-status data-state="loading" role="status" aria-live="polite">Loading rich text tools…</span>
                </div>

                <div class="red-admin-editor-field red-admin-editor-field--compact">
                    <div class="red-admin-editor-field__label">
                        <label for="SliderDesc">Slider summary</label>
                        <span>Optional short line for feature sliders</span>
                    </div>
                    <textarea name="SliderDesc" id="SliderDesc" rows="3"><?php echo red_admin_area_html($row['SliderDesc'] ?? ''); ?></textarea>
                </div>

                <div class="red-admin-editor-field">
                    <div class="red-admin-editor-field__label">
                        <label for="ShortDesc">Summary</label>
                        <span>Optional introduction or card copy</span>
                    </div>
                    <textarea name="ShortDesc" id="ShortDesc" rows="5" data-article-editor data-editor-height="180"><?php echo red_admin_area_html($row['ShortDesc'] ?? ''); ?></textarea>
                </div>

                <div class="red-admin-editor-field">
                    <div class="red-admin-editor-field__label">
                        <label for="LongDesc">Main article</label>
                        <span>The complete body content</span>
                    </div>
                    <textarea name="LongDesc" id="LongDesc" rows="12" data-article-editor data-editor-height="320"><?php echo red_admin_area_html($row['LongDesc'] ?? ''); ?></textarea>
                </div>
            </section>

            <details class="red-admin-article-advanced" data-article-advanced>
                <summary>
                    <span class="red-admin-article-advanced__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"></path></svg>
                    </span>
                    <span class="red-admin-article-advanced__copy">
                        <strong>Optional settings</strong>
                        <small>URL, SEO, publishing dates, links, location, and article images</small>
                    </span>
                    <span class="red-admin-article-advanced__badge">Advanced</span>
                    <svg class="red-admin-article-advanced__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5"></path></svg>
                </summary>

                <div class="red-admin-article-advanced__body">
                    <section class="red-admin-optional-card" aria-labelledby="article-publishing-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect><path d="M7.5 3.5v4M16.5 3.5v4M3.5 9.5h17"></path></svg>
                            </span>
                            <div>
                                <h4 id="article-publishing-title">Publishing, URL &amp; SEO</h4>
                                <p>Control the address, search labels, availability, and optional destination.</p>
                            </div>
                        </div>

                        <div class="red-admin-field-grid red-admin-field-grid--identity">
                            <div class="red-admin-field">
                                <label for="article-alias">URL alias</label>
                                <input name="Alias" type="text" id="article-alias" value="<?php echo red_admin_area_html($row['Alias'] ?? ''); ?>" autocomplete="off" />
                                <span class="red-admin-field__help">The final page-address segment.</span>
                            </div>
                            <div class="red-admin-field red-admin-field--tags">
                                <label for="article-tags">SEO tags</label>
                                <input name="Tags" type="text" id="article-tags" value="<?php echo red_admin_area_html($row['Tags'] ?? ''); ?>" placeholder="music, events, interviews" />
                                <span class="red-admin-field__help">Use a short comma-separated list.</span>
                            </div>
                            <div class="red-admin-field red-admin-field--permalink">
                                <span class="red-admin-field__label">Current page</span>
                                <button
                                    type="button"
                                    class="red-admin-article-permalink-copy"
                                    data-copy-page-link
                                    data-copy-value="<?php echo red_admin_area_html($publicUrl); ?>"
                                    data-copy-default-label="Copy"
                                    aria-label="Copy current page address to the clipboard"
                                >
                                    <span class="red-admin-article-permalink-copy__url"><?php echo red_admin_area_html($publicUrl); ?></span>
                                    <span class="red-admin-article-permalink-copy__action">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="8" y="8" width="11" height="11" rx="2"></rect>
                                            <path d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"></path>
                                        </svg>
                                        <span data-copy-label>Copy</span>
                                    </span>
                                </button>
                                <span class="red-admin-field__help">Click to copy. The address updates after you save an alias or location change.</span>
                            </div>
                        </div>

                        <div class="red-admin-field-grid red-admin-field-grid--dates">
                            <div class="red-admin-field">
                                <label for="article-start-date">Start date</label>
                                <input type="date" id="article-start-date" value="<?php echo red_admin_area_html($startDateMeta['display']); ?>" autocomplete="off" data-article-date="start" data-original-date="<?php echo red_admin_area_html($startDateMeta['display']); ?>" />
                                <input type="hidden" name="StartDate" value="" disabled data-date-payload />
                                <span class="red-admin-field__help"><?php echo $startDateMeta['legacyUnset'] ? 'Legacy unset value is preserved until you choose a date.' : 'Blank means publish immediately.'; ?></span>
                            </div>
                            <div class="red-admin-field">
                                <label for="article-expiration-date">Expiration date</label>
                                <input type="date" id="article-expiration-date" value="<?php echo red_admin_area_html($expirationDateMeta['display']); ?>" autocomplete="off" data-article-date="expiration" data-original-date="<?php echo red_admin_area_html($expirationDateMeta['display']); ?>" />
                                <input type="hidden" name="ExpDate" value="" disabled data-date-payload />
                                <span class="red-admin-field__help"><?php echo $expirationDateMeta['legacyUnset'] ? 'Legacy unset value is preserved until you choose a date.' : 'Blank keeps the article available.'; ?></span>
                            </div>
                        </div>

                        <div class="red-admin-field-grid red-admin-field-grid--link">
                            <div class="red-admin-field">
                                <label for="LinkNavigator">Choose an internal page</label>
                                <select name="LinkNavigator" id="LinkNavigator">
                                    <?php echo $linkNavigatorOptions; ?>
                                </select>
                                <span class="red-admin-field__help">Selecting a page fills the destination below.</span>
                            </div>
                            <div class="red-admin-field">
                                <label for="Link">Link destination</label>
                                <input name="Link" type="text" id="Link" value="<?php echo red_admin_area_html($row['Link'] ?? ''); ?>" inputmode="url" placeholder="/page/ or https://example.com" />
                                <span class="red-admin-field__help">Use a site path or a complete external URL.</span>
                            </div>
                        </div>

                        <label class="red-admin-inline-choice" for="article-new-window">
                            <input name="NewWindow" type="checkbox" id="article-new-window" value="Y"<?php echo ($row['NewWindow'] ?? '') === 'Y' ? ' checked="checked"' : ''; ?> />
                            <span class="red-admin-inline-choice__control" aria-hidden="true"></span>
                            <span>Open this link in a new tab</span>
                        </label>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="article-location-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon red-admin-optional-card__icon--location" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"></path><circle cx="12" cy="10" r="2"></circle></svg>
                            </span>
                            <div>
                                <h4 id="article-location-title">Content location</h4>
                                <p>Connect the article to the relevant hierarchy.</p>
                            </div>
                        </div>

                        <div class="red-admin-field-grid red-admin-field-grid--location">
                            <div class="red-admin-field">
                                <label for="article-section">Section</label>
                                <select name="Sections" id="article-section"><option value="">No section</option><?php echo $sectionOptions; ?></select>
                            </div>
                            <div class="red-admin-field">
                                <label for="article-category">Category</label>
                                <select name="Categories" id="article-category"><option value="">No category</option><?php echo $categoryOptions; ?></select>
                            </div>
                            <div class="red-admin-field">
                                <label for="article-subcategory">Subcategory</label>
                                <select name="SubCategories" id="article-subcategory"><option value="">No subcategory</option><?php echo $subCategoryOptions; ?></select>
                            </div>
                            <div class="red-admin-field">
                                <label for="article-parent">Parent article</label>
                                <select name="Article" id="article-parent"><option value="">No parent article</option><?php echo $articleOptions; ?></select>
                            </div>
                        </div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="article-images-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon red-admin-optional-card__icon--media" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect><circle cx="9" cy="9" r="1.5"></circle><path d="M5.5 17l4.5-4 3 2.5 2.5-2 3 3.5"></path></svg>
                            </span>
                            <div>
                                <h4 id="article-images-title">Article images</h4>
                                <p>Review the current image, replace it by dropping or browsing, or mark it for removal when saved.</p>
                            </div>
                        </div>

                        <div class="red-admin-upload-grid">
                            <?php foreach ($uploadCards as $uploadCard) { ?>
                                <article
                                    class="red-admin-upload-card"
                                    id="upload-<?php echo red_admin_area_html($uploadCard['field']); ?>"
                                    data-article-upload
                                    data-upload-field="<?php echo red_admin_area_html($uploadCard['field']); ?>"
                                    data-upload-url="<?php echo red_admin_area_html($uploadUrls[$uploadCard['field']]); ?>"
                                    aria-busy="false"
                                >
                                    <div class="red-admin-upload-card__heading">
                                        <strong><?php echo red_admin_area_html($uploadCard['title']); ?></strong>
                                        <span><?php echo red_admin_area_html($uploadCard['description']); ?></span>
                                    </div>

                                    <input type="hidden" name="<?php echo red_admin_area_html($uploadCard['field']); ?>" value="<?php echo red_admin_area_html($uploadCard['current']); ?>" data-upload-value />
                                    <div class="red-admin-upload-current" data-current-media<?php echo $uploadCard['current'] === '' ? ' hidden' : ''; ?>>
                                        <img
                                            src="<?php echo $uploadCard['current'] === '' ? '' : '/images/resize.php?w=180&amp;h=110&amp;img=/images/articles/'.rawurlencode($uploadCard['current']); ?>"
                                            alt="Current <?php echo red_admin_area_html(strtolower($uploadCard['title'])); ?>"
                                            data-current-image
                                        />
                                        <div class="red-admin-upload-current__meta">
                                            <span>Current image</span>
                                            <strong data-current-name><?php echo red_admin_area_html($uploadCard['current']); ?></strong>
                                        </div>
                                        <label class="red-admin-upload-remove">
                                            <input name="Delete_<?php echo red_admin_area_html($uploadCard['field']); ?>" type="checkbox" value="Y" data-remove-image />
                                            <span>Remove when saved</span>
                                        </label>
                                    </div>

                                    <div class="red-admin-upload-dropzone" data-upload-dropzone>
                                        <input class="red-admin-visually-hidden" type="file" id="<?php echo red_admin_area_html($uploadCard['id']); ?>" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" data-upload-input />
                                        <svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path></svg>
                                        <strong>Drop replacement here</strong>
                                        <span>or choose one from your computer</span>
                                        <button type="button" class="red-admin-upload-browse" data-upload-browse>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>
                                            Browse computer
                                        </button>
                                        <small>JPG, PNG or GIF · maximum 2 MB</small>
                                    </div>

                                    <div class="red-admin-upload-preview" data-upload-preview hidden>
                                        <img src="" alt="" data-upload-preview-image />
                                        <div><strong data-upload-file-name></strong><span data-upload-status role="status" aria-live="polite">Ready to upload</span></div>
                                    </div>
                                    <div class="red-admin-upload-progress" aria-hidden="true"><span data-upload-progress></span></div>

                                    <?php if ($uploadCard['alignmentName'] !== '') { ?>
                                        <div class="red-admin-field red-admin-upload-card__alignment">
                                            <label for="<?php echo red_admin_area_html($uploadCard['alignmentId']); ?>">Image alignment</label>
                                            <select name="<?php echo red_admin_area_html($uploadCard['alignmentName']); ?>" id="<?php echo red_admin_area_html($uploadCard['alignmentId']); ?>">
                                                <option value=""<?php echo $uploadCard['alignmentValue'] === '' ? ' selected="selected"' : ''; ?>>Theme default</option>
                                                <option value="Top"<?php echo $uploadCard['alignmentValue'] === 'Top' ? ' selected="selected"' : ''; ?>>Top</option>
                                                <option value="Left"<?php echo $uploadCard['alignmentValue'] === 'Left' ? ' selected="selected"' : ''; ?>>Left</option>
                                                <option value="Right"<?php echo $uploadCard['alignmentValue'] === 'Right' ? ' selected="selected"' : ''; ?>>Right</option>
                                            </select>
                                        </div>
                                    <?php } ?>
                                </article>
                            <?php } ?>
                        </div>
                    </section>
                </div>
            </details>

            <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $RecordID; ?>" />
            <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_area_html($_SESSION['alias'] ?? ''); ?>" />
            <?php echo red_csrf_input(); ?>

            <?php red_admin_content_revision_panel($RecordID); ?>

            <div class="red-admin-article-actions red-admin-article-actions--edit">
                <span id="msggbox_update_content" class="red-admin-article-message" data-article-message role="status" aria-live="polite" hidden></span>
                <button type="button" class="red-admin-article-delete" data-article-delete>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"></path></svg>
                    Delete article
                </button>
                <button type="submit" name="submit" value="Save" id="save" class="red-admin-article-save" data-article-save data-default-label="Save changes">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"></path><path d="M8 4v6h8V4M8 20v-6h8v6"></path></svg>
                    <span data-save-label>Save changes</span>
                </button>
            </div>
        </div>
    </fieldset>
</form>

<script src="<?php echo red_admin_area_html($articleFormScript); ?>?v=<?php echo rawurlencode((string) $articleFormScriptVersion); ?>"></script>
