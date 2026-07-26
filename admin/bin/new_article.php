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
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_seo_helpers.php';

if (empty($_SESSION['alias'])) {
    header('Location: http://'.BASE_URL.'');
    exit;
}

$Type = red_admin_post_text('Type');
$CountPage = red_admin_post_text('CountPage');
$Section = red_admin_post_text('Section');
$Category = red_admin_post_text('Category');
$SubCategory = red_admin_post_text('SubCategory');
$VarPosition = red_admin_article_position_column($_POST['VarPosition'] ?? '', 'PagePosition');
if ($VarPosition === null) {
    echo 'no';
    exit;
}

$Language = substr(red_admin_post_text('Language'), 0, 2);
$Layout = red_admin_post_text('Layout');
$Article = red_admin_post_text('Article');
$RecordID = mt_rand();
$csrfToken = red_csrf_token();

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_component_selection($db->connection, 'Article');
$positionOptions = red_admin_article_layout_position_options($db->connection, $Layout);
$linkNavigatorOptions = red_admin_main_menu_link_options($db->connection);
$sectionOptions = red_admin_article_area_options($db->connection, 'RED_Sections', 'Sections', $Section);
$categoryOptions = red_admin_article_area_options($db->connection, 'RED_Categories', 'Categories', $Category);
$subCategoryOptions = red_admin_article_area_options($db->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
$articleOptions = red_admin_article_page_options($db->connection, $Article);
$db->close();
$seoValues = red_seo_empty_values();

$uploadUrls = [];
foreach (['BigPict', 'SmallPict', 'SmallPict2'] as $uploadCase) {
    $uploadUrls[$uploadCase] = '/admin/bin/post_file.php?'.http_build_query(
        [
            'RecordID' => $RecordID,
            'UC' => $uploadCase,
            'Insert' => 'true',
            'AuthComponent' => 'Article',
            'Language' => $Language,
            'csrf_token' => $csrfToken,
        ],
        '',
        '&',
        PHP_QUERY_RFC3986
    );
}

$articleFormScript = '/admin/assets/js/new-article-form.js';
$articleFormScriptVersion = filemtime($_SERVER['DOCUMENT_ROOT'].$articleFormScript);
?>

<div class="cp_viewall red-admin-article-return">
    <button type="button" class="red-admin-article-return__button" onclick="showdiv('add_content_grid'); return false;">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15 18l-6-6 6-6"></path>
        </svg>
        <span>All content types</span>
    </button>
    <span class="red-admin-article-return__divider" aria-hidden="true">/</span>
    <span aria-current="page">New Article</span>
</div>

<form id="insert_content" name="insert_content" class="cp red-admin-article-form" method="post" data-red-article-form data-article-mode="create" data-submit-url="/admin/bin/insert_content.php" onsubmit="return run_insert_content(this);">
    <fieldset>
        <legend class="red-admin-visually-hidden">Create a new article</legend>

        <div class="red-admin-article-shell">
            <header class="red-admin-article-header">
                <span class="red-admin-article-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M6 3.75h9.25L19 7.5v12.75H6z"></path>
                        <path d="M15 3.75V7.5h4M9 11h7M9 14h7M9 17h4"></path>
                    </svg>
                </span>
                <div class="red-admin-article-header__copy">
                    <span class="red-admin-article-header__eyebrow">Add Content</span>
                    <h2>Create a new article</h2>
                    <p>Write the page content first, then open optional settings only when you need them.</p>
                </div>
                <span class="red-admin-article-header__badge">Article</span>
            </header>

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
                        <input name="Title" type="text" id="article-title" value="" autocomplete="off" required aria-describedby="article-title-help article-title-error" />
                        <span class="red-admin-field__help" id="article-title-help">The public heading and primary editor label.</span>
                        <span class="red-admin-field__error" id="article-title-error" data-title-error hidden>Add a title before saving.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="article-status">Status</label>
                        <select name="Active" id="article-status">
                            <option value="Y" selected="selected">Published</option>
                            <option value="">Draft</option>
                        </select>
                        <span class="red-admin-field__help">Draft articles stay out of public view.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="article-layout-position">Layout position</label>
                        <select name="<?php echo red_admin_area_html($VarPosition); ?>" id="article-layout-position">
                            <?php foreach ($positionOptions as $positionValue => $positionLabel) { ?>
                                <option value="<?php echo (int) $positionValue; ?>"><?php echo red_admin_area_html($positionLabel); ?> (<?php echo (int) $positionValue; ?>)</option>
                            <?php } ?>
                        </select>
                        <span class="red-admin-field__help">Where this article appears in the selected layout.</span>
                    </div>

                    <div class="red-admin-field">
                        <label for="article-order">Order</label>
                        <input name="<?php echo red_admin_area_html($VarPosition.'Order'); ?>" type="number" id="article-order" value="" min="0" step="1" inputmode="numeric" placeholder="Auto" />
                        <span class="red-admin-field__help">Lower numbers appear first.</span>
                    </div>

                    <label class="red-admin-choice-card" for="article-home-feature">
                        <input name="HomeFeature" type="checkbox" id="article-home-feature" value="Y" />
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
                        <h3 id="article-content-title">Write the content</h3>
                    </div>
                    <span class="red-admin-editor-status" data-editor-status data-state="loading" role="status" aria-live="polite">Loading rich text tools…</span>
                </div>

                <div class="red-admin-editor-field">
                    <div class="red-admin-editor-field__label">
                        <label for="ShortDesc">Summary</label>
                        <span>Optional introduction or card copy</span>
                    </div>
                    <textarea name="ShortDesc" id="ShortDesc" rows="5" data-article-editor data-editor-height="180"></textarea>
                </div>

                <div class="red-admin-editor-field">
                    <div class="red-admin-editor-field__label">
                        <label for="LongDesc">Main article</label>
                        <span>The complete body content</span>
                    </div>
                    <textarea name="LongDesc" id="LongDesc" rows="12" data-article-editor data-editor-height="320"></textarea>
                </div>
            </section>

            <details class="red-admin-article-advanced" data-article-advanced>
                <summary>
                    <span class="red-admin-article-advanced__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"></path>
                        </svg>
                    </span>
                    <span class="red-admin-article-advanced__copy">
                        <strong>Optional settings</strong>
                        <small>Publishing dates, links, location, and article images</small>
                    </span>
                    <span class="red-admin-article-advanced__badge">Advanced</span>
                    <svg class="red-admin-article-advanced__chevron" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 10l5 5 5-5"></path>
                    </svg>
                </summary>

                <div class="red-admin-article-advanced__body">
                    <section class="red-admin-optional-card" aria-labelledby="article-publishing-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect>
                                    <path d="M7.5 3.5v4M16.5 3.5v4M3.5 9.5h17"></path>
                                </svg>
                            </span>
                            <div>
                                <h4 id="article-publishing-title">Publishing &amp; link</h4>
                                <p>Schedule availability or send readers to another destination.</p>
                            </div>
                        </div>

                        <div class="red-admin-field-grid red-admin-field-grid--dates">
                            <div class="red-admin-field">
                                <label for="article-start-date">Start date</label>
                                <input name="StartDate" type="date" id="article-start-date" value="" autocomplete="off" data-article-date="start" />
                                <span class="red-admin-field__help">Leave blank to publish immediately.</span>
                            </div>
                            <div class="red-admin-field">
                                <label for="article-expiration-date">Expiration date</label>
                                <input name="ExpDate" type="date" id="article-expiration-date" value="" autocomplete="off" data-article-date="expiration" />
                                <span class="red-admin-field__help">Leave blank to keep the article available.</span>
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
                                <input name="Link" type="text" id="Link" value="" inputmode="url" placeholder="/page/ or https://example.com" />
                                <span class="red-admin-field__help">Use a site path or a complete external URL.</span>
                            </div>
                        </div>

                        <label class="red-admin-inline-choice" for="article-new-window">
                            <input name="NewWindow" type="checkbox" id="article-new-window" value="Y" />
                            <span class="red-admin-inline-choice__control" aria-hidden="true"></span>
                            <span>Open this link in a new tab</span>
                        </label>
                    </section>

                    <?php echo red_admin_seo_fields_html($seoValues, 'article-seo'); ?>

                    <section class="red-admin-optional-card" aria-labelledby="article-location-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon red-admin-optional-card__icon--location" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"></path>
                                    <circle cx="12" cy="10" r="2"></circle>
                                </svg>
                            </span>
                            <div>
                                <h4 id="article-location-title">Content location</h4>
                                <p>Connect the article to the relevant hierarchy.</p>
                            </div>
                        </div>

                        <div class="red-admin-field-grid red-admin-field-grid--location">
                            <div class="red-admin-field">
                                <label for="article-section">Section</label>
                                <select name="Sections" id="article-section">
                                    <option value="">No section</option>
                                    <?php echo $sectionOptions; ?>
                                </select>
                            </div>
                            <div class="red-admin-field">
                                <label for="article-category">Category</label>
                                <select name="Categories" id="article-category">
                                    <option value="">No category</option>
                                    <?php echo $categoryOptions; ?>
                                </select>
                            </div>
                            <div class="red-admin-field">
                                <label for="article-subcategory">Subcategory</label>
                                <select name="SubCategories" id="article-subcategory">
                                    <option value="">No subcategory</option>
                                    <?php echo $subCategoryOptions; ?>
                                </select>
                            </div>
                            <div class="red-admin-field">
                                <label for="article-parent">Parent article</label>
                                <select name="Article" id="article-parent">
                                    <option value="">No parent article</option>
                                    <?php echo $articleOptions; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="article-images-title">
                        <div class="red-admin-optional-card__heading">
                            <span class="red-admin-optional-card__icon red-admin-optional-card__icon--media" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect>
                                    <circle cx="9" cy="9" r="1.5"></circle>
                                    <path d="M5.5 17l4.5-4 3 2.5 2.5-2 3 3.5"></path>
                                </svg>
                            </span>
                            <div>
                                <h4 id="article-images-title">Article images</h4>
                                <p>Drag an image into a card or browse and search your computer.</p>
                            </div>
                        </div>

                        <div class="red-admin-upload-grid">
                            <article class="red-admin-upload-card" id="dropbox" data-article-upload data-upload-field="BigPict" data-upload-url="<?php echo red_admin_area_html($uploadUrls['BigPict']); ?>" aria-busy="false">
                                <div class="red-admin-upload-card__heading">
                                    <strong>Feature image</strong>
                                    <span>Large feature and hero placements</span>
                                </div>
                                <div class="red-admin-upload-dropzone" data-upload-dropzone>
                                    <input class="red-admin-visually-hidden" type="file" id="article-feature-image" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" data-upload-input />
                                    <svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path>
                                    </svg>
                                    <strong>Drop image here</strong>
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
                            </article>

                            <article class="red-admin-upload-card" id="dropbox2" data-article-upload data-upload-field="SmallPict" data-upload-url="<?php echo red_admin_area_html($uploadUrls['SmallPict']); ?>" aria-busy="false">
                                <div class="red-admin-upload-card__heading">
                                    <strong>Summary image</strong>
                                    <span>Article descriptions and list cards</span>
                                </div>
                                <div class="red-admin-upload-dropzone" data-upload-dropzone>
                                    <input class="red-admin-visually-hidden" type="file" id="article-summary-image" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" data-upload-input />
                                    <svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path>
                                    </svg>
                                    <strong>Drop image here</strong>
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
                                <div class="red-admin-field red-admin-upload-card__alignment">
                                    <label for="article-summary-alignment">Image alignment</label>
                                    <select name="SmallPictAlign" id="article-summary-alignment">
                                        <option value="">Theme default</option>
                                        <option value="Top">Top</option>
                                        <option value="Left">Left</option>
                                        <option value="Right">Right</option>
                                    </select>
                                </div>
                            </article>

                            <article class="red-admin-upload-card" id="dropbox3" data-article-upload data-upload-field="SmallPict2" data-upload-url="<?php echo red_admin_area_html($uploadUrls['SmallPict2']); ?>" aria-busy="false">
                                <div class="red-admin-upload-card__heading">
                                    <strong>Article image</strong>
                                    <span>Landing-page and article placements</span>
                                </div>
                                <div class="red-admin-upload-dropzone" data-upload-dropzone>
                                    <input class="red-admin-visually-hidden" type="file" id="article-content-image" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" data-upload-input />
                                    <svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path>
                                    </svg>
                                    <strong>Drop image here</strong>
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
                                <div class="red-admin-field red-admin-upload-card__alignment">
                                    <label for="article-content-alignment">Image alignment</label>
                                    <select name="SmallPictAlign2" id="article-content-alignment">
                                        <option value="">Theme default</option>
                                        <option value="Top">Top</option>
                                        <option value="Left">Left</option>
                                        <option value="Right">Right</option>
                                    </select>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </details>

            <input type="hidden" name="RecordID" id="RecordID" value="<?php echo (int) $RecordID; ?>" />
            <input type="hidden" name="Language" id="Language" value="<?php echo red_admin_area_html($Language); ?>" />
            <input type="hidden" name="Layout" id="Layout" value="<?php echo red_admin_area_html($Layout); ?>" />
            <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_area_html($_SESSION['alias']); ?>" />
            <input type="hidden" name="Component" id="Component" value="Article" />
            <?php echo red_csrf_input(); ?>

            <div class="red-admin-article-actions">
                <span id="msggbox_insert_content" class="red-admin-article-message" data-article-message role="status" aria-live="polite" hidden></span>
                <button type="submit" name="submit" value="Save" id="save" class="red-admin-article-save" data-article-save data-default-label="Save article">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 4h12l2 2v14H5z"></path>
                        <path d="M8 4v6h8V4M8 20v-6h8v6"></path>
                    </svg>
                    <span data-save-label>Save article</span>
                </button>
            </div>
        </div>
    </fieldset>
</form>

<script src="<?php echo red_admin_area_html($articleFormScript); ?>?v=<?php echo rawurlencode((string) $articleFormScriptVersion); ?>"></script>
