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
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin_site_manager();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_area_helpers.php';

$recordId = (int) red_admin_post_text('RecordID');
if ($recordId <= 0) {
    echo 'no';
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$row = red_admin_area_record($db->connection, 'RED_Categories', $recordId);
if (!$row) {
    echo 'no';
    $db->close();
    exit;
}

$RecordID = (int) $row['RecordID'];
$layout = (string) $row['Layout'];
$selectedFeatures = array_flip(array_filter(array_map('trim', explode(',', (string) $row['Features']))));
$layouts = red_admin_area_layout_options($db->connection, $layout);
$features = red_admin_area_features($db->connection);
$parentOptions = red_admin_area_parent_options($db->connection, 'RED_Categories', $row['Language']);
$currentParentId = (int) ($row['SectionRecordID'] ?? 0);
$routeContext = red_admin_area_record_route_context($db->connection, 'RED_Categories', $RecordID);
$currentPath = red_admin_text($routeContext['path'] ?? '');
$relatedCount = red_admin_area_related_article_count($db->connection, 'Categories', $row['Categories']);
$childCount = red_admin_area_child_count($db->connection, 'RED_Categories', $RecordID);
$storedAccessLevel = red_admin_text($row['AccessLevel'] ?? 'Public');
$csrfToken = red_csrf_token();
$deletePrompt = $childCount > 0
    ? 'This Category cannot be deleted until its ' . $childCount . ' child Subcategor' . ($childCount === 1 ? 'y is' : 'ies are') . ' reassigned or deleted.'
    : ($relatedCount > 0
    ? 'Delete this Category? ' . $relatedCount . ' related Article' . ($relatedCount === 1 ? '' : 's') . ' will keep their content and category text, but this Category record cannot be recovered.'
    : 'Delete this Category? It has no related Articles and cannot be recovered.');
?>
<script type="text/javascript">
<!--
function run_update_category (update_category)
{
    $.ajax({
        type: "POST",
        url: "/admin/bin/update_category.php",
        data: $("#update_category").serialize(),
        success: function(data, textStatus, jqXHR) {
            var status = $.trim(data);
            if (status === 'yes' || status === 'updateupdateyes' || status === 'updateupdateupdateyes' || status === 'updateyes') {
                var canonicalAlias = jqXHR.getResponseHeader('X-RED-Canonical-Alias');
                var canonicalPath = jqXHR.getResponseHeader('X-RED-Canonical-Path');
                if (canonicalAlias || canonicalPath) {
                    alert('Your Category route was updated.\n NOTE: owned \'Articles\' and \'Menu Links\' were moved to the new route.');
                }
                $('#msggbox_update_category').removeClass('has-error').addClass('is-success').html("Category updated.")
                    .hide()
                    .fadeIn(200, function() {
                        if (canonicalPath && RED_ADMIN_AREA_RENAME.redirectPath(canonicalPath)) {
                            return;
                        }
                        if (canonicalAlias && RED_ADMIN_AREA_RENAME.redirect(canonicalAlias, 2)) {
                            return;
                        }
                        window.location.reload();
                    });
            } else if (status === 'error') {
                alert('There is a Section using the same name. Please enter a different Category name.');
            } else if (status === 'error2') {
                alert('There is a Category using the same name. Please enter a different Category name.');
            } else if (status === 'error3') {
                alert('There is a Subcategory using the same name. Please enter a different Category name.');
            } else {
                $('#msggbox_update_category').removeClass('is-success').addClass('has-error').html("The category could not be updated. Please review the fields and try again.")
                    .hide()
                    .fadeIn(200);
            }
        }
    });
    return false;
}

function run_delete_category_record (RecordID)
{
    if (<?php echo $childCount; ?> > 0) {
        $('#msggbox_deleterecord').removeClass('is-success').addClass('has-error').html(<?php echo json_encode($deletePrompt); ?>).show();
        return false;
    }
    if (!confirm(<?php echo json_encode($deletePrompt); ?>)) {
        return false;
    }

    $.ajax({
        type: "POST",
        url: "/admin/bin/delete_label.php",
        data: {RecordID: RecordID, T: "categories", csrf_token: <?php echo json_encode($csrfToken); ?>},
        success: function(data) {
            if ($.trim(data) === 'yes') {
                $('#msggbox_deleterecord').removeClass('has-error').addClass('is-success').html("Category deleted.")
                    .hide()
                    .fadeIn(200, function() {
                        window.location.reload();
                    });
            } else {
                $('#msggbox_deleterecord').removeClass('is-success').addClass('has-error').html("The category could not be deleted. Please try again.")
                    .hide()
                    .fadeIn(200);
            }
        }
    });
    return false;
}

function redAdminEditCategoryPreview()
{
    var alias = document.getElementById('edit-category-alias');
    var parent = document.getElementById('edit-category-parent-section');
    var preview = document.querySelector('[data-red-edit-category-url-preview]');
    var selected = parent && parent.options[parent.selectedIndex];
    var section = selected && selected.dataset ? selected.dataset.sectionAlias : '';
    var category = alias && alias.value ? alias.value.toLowerCase().replace(/[^a-z0-9_-]+/g, '-') : '';
    category = category.replace(/^-+|-+$/g, '');
    if (preview) {
        preview.textContent = section && category
            ? '/' + encodeURIComponent(section) + '/' + encodeURIComponent(category) + '/'
            : 'Choose a parent Section and enter an alias';
    }
}
//-->
</script>
<div class="red-admin-section-return">
    <button type="button" class="red-admin-section-return__button" onclick="showdiv('edit_category_grid'); return false;">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Back to categories
    </button>
    <span>Editing an existing content topic</span>
</div>
<form id="update_category" name="update_category" class="cp red-admin-section-form red-admin-section-form--edit red-admin-area-form--category" method="post" onSubmit="return run_update_category(this);" data-red-area-form="category-edit">
<fieldset>
<?php echo red_csrf_input(); ?>
<div class="red-admin-section-shell">
    <header class="red-admin-section-header">
        <span class="red-admin-section-header__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5"/><path d="m14.5 18.5 4-4 2 2-4 4-3 1z"/></svg>
        </span>
        <div class="red-admin-section-header__copy">
            <span class="red-admin-section-header__eyebrow">Content organization</span>
            <h2>Edit category</h2>
            <p>Refine its public name, layout, presentation, and publishing status.</p>
        </div>
        <span class="red-admin-section-header__badge"><?php echo red_admin_area_html($row['Categories']); ?></span>
    </header>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-category-basics-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">1</span><h3 id="red-edit-category-basics-title">Category basics</h3></div>
            <p>Public label, URL alias, layout, and visibility</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--identity">
            <div class="red-admin-section-field">
                <label for="edit-category-title">Display title <span aria-hidden="true">*</span></label>
                <input name="Title" type="text" id="edit-category-title" value="<?php echo red_admin_area_html($row['Title']); ?>" maxlength="120" required />
                <small>The visitor-facing title shown by compatible templates.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-category-alias">URL alias <span aria-hidden="true">*</span></label>
                <input name="Categories" type="text" id="edit-category-alias" value="<?php echo red_admin_area_html($row['Categories']); ?>" maxlength="120" required autocomplete="off" oninput="redAdminEditCategoryPreview()" />
                <small>Complete route: <strong data-red-edit-category-url-preview><?php echo $currentPath !== '' ? red_admin_area_html($currentPath) : 'Choose a parent Section'; ?></strong></small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-category-parent-section">Parent Section <span aria-hidden="true">*</span></label>
                <select name="SectionRecordID" id="edit-category-parent-section" required onchange="redAdminEditCategoryPreview()">
                    <option value=""><?php echo empty($parentOptions) ? 'Create a Section first' : 'Choose a parent Section…'; ?></option>
                    <?php foreach ($parentOptions as $parentOption) {
                        $parentRecordId = (int) ($parentOption['ParentRecordID'] ?? 0);
                        $parentAlias = red_admin_text($parentOption['ParentAlias'] ?? '');
                        $parentTitle = red_admin_text($parentOption['ParentTitle'] ?? $parentAlias);
                    ?>
                        <option value="<?php echo $parentRecordId; ?>" data-section-alias="<?php echo red_admin_area_html($parentAlias); ?>" <?php if ($parentRecordId === $currentParentId) echo 'selected="selected"'; ?>><?php echo red_admin_area_html($parentTitle); ?> — <?php echo red_admin_area_html($parentAlias); ?></option>
                    <?php } ?>
                </select>
                <small>Moving this Category also updates its owned Article routes, including child Subcategories.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-category-layout">Layout <span aria-hidden="true">*</span></label>
                <select name="Layout" id="edit-category-layout" required>
                    <?php foreach ($layouts as $thislayout => $layoutLabel) { ?>
                        <option value="<?php echo red_admin_area_html($thislayout); ?>" <?php if ($thislayout === $layout) echo 'selected="selected"'; ?>><?php echo red_admin_area_html($layoutLabel); ?> (<?php echo red_admin_area_html($thislayout); ?>)</option>
                    <?php } ?>
                </select>
                <small>Choose a structure compatible with the content positions already assigned here.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-category-active">Publishing status</label>
                <select name="Active" id="edit-category-active">
                    <option value="Y" <?php if ($row['Active'] === 'Y') echo 'selected="selected"'; ?>>Active — visible on the site</option>
                    <option value="N" <?php if ($row['Active'] === 'N') echo 'selected="selected"'; ?>>Draft — hidden from visitors</option>
                </select>
                <small>Draft categories remain available in the administrator workspace.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-category-presentation-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">2</span><h3 id="red-edit-category-presentation-title">Presentation</h3></div>
            <p>Optional discovery and feature settings</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--content">
            <div class="red-admin-section-field">
                <span class="red-admin-section-field__label">Features</span>
                <?php if (empty($features)) { ?>
                    <div class="red-admin-section-empty">No optional category features are installed.</div>
                <?php } else { ?>
                    <div class="red-admin-section-feature-list">
                    <?php foreach ($features as $featureName) {
                        $featureValue = red_admin_area_html($featureName);
                        $featureLabel = red_admin_area_html(red_admin_area_feature_label($featureName));
                        $featureDescription = red_admin_area_html(red_admin_area_feature_description($featureName));
                    ?>
                        <label class="red-admin-section-feature">
                            <input type="checkbox" name="Features[]" value="<?php echo $featureValue; ?>" <?php if (isset($selectedFeatures[$featureName])) echo 'checked'; ?> />
                            <span>
                                <strong><?php echo $featureLabel; ?></strong>
                                <?php if ($featureDescription !== '') { ?><small><?php echo $featureDescription; ?></small><?php } ?>
                            </span>
                        </label>
                    <?php } ?>
                    </div>
                <?php } ?>
                <small>Features enhance the category but are not required.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-category-tags">Search tags</label>
                <input name="Tags" type="text" id="edit-category-tags" value="<?php echo red_admin_area_html($row['Tags']); ?>" placeholder="music, lessons, events" />
                <small>Use a few comma-separated phrases that describe this category.</small>
            </div>
            <div class="red-admin-section-field red-admin-section-field--wide">
                <label for="edit-category-description">Category description</label>
                <textarea name="Description" id="edit-category-description" rows="4" placeholder="A concise summary for visitors and search previews."><?php echo red_admin_area_html($row['Description']); ?></textarea>
                <small>Keep this clear and specific; the public template decides where it appears.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel red-admin-section-panel--access" aria-labelledby="red-edit-category-access-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">3</span><h3 id="red-edit-category-access-title">Visitor access</h3></div>
            <p>Current runtime behavior</p>
        </div>
        <div class="red-admin-section-legacy-access<?php echo strcasecmp($storedAccessLevel, 'Public') === 0 ? '' : ' has-warning'; ?>">
            <span class="red-admin-section-access__icon" aria-hidden="true">
                <?php if (strcasecmp($storedAccessLevel, 'Public') === 0) { ?>
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.5 12h17M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                <?php } else { ?>
                    <svg viewBox="0 0 24 24"><path d="M5 5l14 14"/><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 6.7-3"/></svg>
                <?php } ?>
            </span>
            <span>
                <strong><?php echo strcasecmp($storedAccessLevel, 'Public') === 0 ? 'Public' : 'Legacy Private flag'; ?></strong>
                <small><?php echo strcasecmp($storedAccessLevel, 'Public') === 0
                    ? 'Anyone can open this category.'
                    : 'This flag is not enforced by public rendering. Treat this category as public until member access is implemented.'; ?></small>
            </span>
        </div>
        <div class="red-admin-section-notice">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg>
            <p>Access is read-only here so saving other edits cannot accidentally change legacy metadata or promise protection the public runtime does not provide.</p>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-category-management-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">4</span><h3 id="red-edit-category-management-title">Content relationship</h3></div>
            <p>Review impact before removal</p>
        </div>
        <div class="red-admin-section-management">
            <div class="red-admin-section-stat">
                <span class="red-admin-section-stat__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6M9 16h6"/></svg></span>
                <span><strong><?php echo $relatedCount; ?></strong><small>Related Article<?php echo $relatedCount === 1 ? '' : 's'; ?></small></span>
            </div>
            <div class="red-admin-section-stat">
                <span class="red-admin-section-stat__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 4v12a3 3 0 0 0 3 3h9"/><path d="m14 15 4 4-4 4M6 9h8"/></svg></span>
                <span><strong><?php echo $childCount; ?></strong><small>Child Subcategor<?php echo $childCount === 1 ? 'y' : 'ies'; ?></small></span>
            </div>
            <div class="red-admin-section-danger">
                <div>
                    <strong>Delete category</strong>
                    <small><?php echo $childCount > 0
                        ? 'Reassign or delete its child Subcategories first. Their hierarchy is protected.'
                        : ($relatedCount > 0
                        ? 'Related Articles keep their content and category text, but the Category record will be removed.'
                        : 'This category has no related Articles. Deletion cannot be undone.'); ?></small>
                </div>
                <button type="button" id="deleterecord_<?php echo $RecordID; ?>" class="red-admin-section-delete" onclick="return run_delete_category_record(<?php echo $RecordID; ?>);" <?php if ($childCount > 0) echo 'disabled aria-disabled="true"'; ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                    <?php echo $childCount > 0 ? 'Child Subcategories exist' : 'Delete category'; ?>
                </button>
            </div>
        </div>
        <span id="msggbox_deleterecord" class="red-admin-section-status red-admin-section-status--management" role="status" aria-live="polite" style="display:none"></span>
    </section>

    <input type="hidden" name="RecordID" id="category-record-id" value="<?php echo $RecordID; ?>" />
    <input type="hidden" name="CurrentCategory" id="current-category" value="<?php echo red_admin_area_html($row['Categories']); ?>" />
    <div class="red-admin-section-actions">
        <span id="msggbox_update_category" class="red-admin-section-status" role="status" aria-live="polite" style="display:none"></span>
        <button type="submit" name="submit" value="Save" id="save-category-changes" class="red-admin-section-submit">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
            Save changes
        </button>
    </div>
</div>
</fieldset>
</form>
<?php
$db->close();
?>
