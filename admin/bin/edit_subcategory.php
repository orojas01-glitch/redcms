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
$row = red_admin_area_record($db->connection, 'RED_SubCategories', $recordId);
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
$parentOptions = red_admin_area_parent_options($db->connection, 'RED_SubCategories', $row['Language']);
$currentParentId = (int) ($row['CategoryRecordID'] ?? 0);
$routeContext = red_admin_area_record_route_context($db->connection, 'RED_SubCategories', $RecordID);
$currentPath = red_admin_text($routeContext['path'] ?? '');
$relatedCount = red_admin_area_related_article_count($db->connection, 'SubCategories', $row['SubCategories']);
$storedAccessLevel = red_admin_text($row['AccessLevel'] ?? 'Public');
$csrfToken = red_csrf_token();
$deletePrompt = $relatedCount > 0
    ? 'Delete this Subcategory? ' . $relatedCount . ' related Article' . ($relatedCount === 1 ? '' : 's') . ' will keep their content and subcategory text, but this Subcategory record cannot be recovered.'
    : 'Delete this Subcategory? It has no related Articles and cannot be recovered.';
?>
<script type="text/javascript">
<!--
function run_update_subcategory (update_subcategory)
{
    $.ajax({
        type: "POST",
        url: "/admin/bin/update_subcategory.php",
        data: $("#update_subcategory").serialize(),
        success: function(data, textStatus, jqXHR) {
            var status = $.trim(data);
            if (status === 'yes' || status === 'updateupdateyes' || status === 'updateupdateupdateyes' || status === 'updateyes') {
                var canonicalAlias = jqXHR.getResponseHeader('X-RED-Canonical-Alias');
                var canonicalPath = jqXHR.getResponseHeader('X-RED-Canonical-Path');
                if (canonicalAlias || canonicalPath) {
                    alert('Your Subcategory route was updated.\n NOTE: owned \'Articles\' and \'Menu Links\' were moved to the new route.');
                }
                $('#msggbox_update_subcategory').removeClass('has-error').addClass('is-success').html("Subcategory updated.")
                    .hide()
                    .fadeIn(200, function() {
                        if (canonicalPath && RED_ADMIN_AREA_RENAME.redirectPath(canonicalPath)) {
                            return;
                        }
                        if (canonicalAlias && RED_ADMIN_AREA_RENAME.redirect(canonicalAlias, 3)) {
                            return;
                        }
                        window.location.reload();
                    });
            } else if (status === 'error') {
                alert('There is a Section using the same name. Please enter a different Subcategory name.');
            } else if (status === 'error2') {
                alert('There is a Category using the same name. Please enter a different Subcategory name.');
            } else if (status === 'error3') {
                alert('There is a Subcategory using the same name. Please enter a different Subcategory name.');
            } else {
                $('#msggbox_update_subcategory').removeClass('is-success').addClass('has-error').html("The subcategory could not be updated. Please review the fields and try again.")
                    .hide()
                    .fadeIn(200);
            }
        }
    });
    return false;
}

function run_delete_subcategory_record (RecordID)
{
    if (!confirm(<?php echo json_encode($deletePrompt); ?>)) {
        return false;
    }

    $.ajax({
        type: "POST",
        url: "/admin/bin/delete_label.php",
        data: {RecordID: RecordID, T: "subcategories", csrf_token: <?php echo json_encode($csrfToken); ?>},
        success: function(data) {
            if ($.trim(data) === 'yes') {
                $('#msggbox_deleterecord').removeClass('has-error').addClass('is-success').html("Subcategory deleted.")
                    .hide()
                    .fadeIn(200, function() {
                        window.location.reload();
                    });
            } else {
                $('#msggbox_deleterecord').removeClass('is-success').addClass('has-error').html("The subcategory could not be deleted. Please try again.")
                    .hide()
                    .fadeIn(200);
            }
        }
    });
    return false;
}

function redAdminEditSubcategoryPreview()
{
    var alias = document.getElementById('edit-subcategory-alias');
    var parent = document.getElementById('edit-subcategory-parent-category');
    var preview = document.querySelector('[data-red-edit-subcategory-url-preview]');
    var selected = parent && parent.options[parent.selectedIndex];
    var section = selected && selected.dataset ? selected.dataset.sectionAlias : '';
    var category = selected && selected.dataset ? selected.dataset.categoryAlias : '';
    var subcategory = alias && alias.value ? alias.value.toLowerCase().replace(/[^a-z0-9_-]+/g, '-') : '';
    subcategory = subcategory.replace(/^-+|-+$/g, '');
    if (preview) {
        preview.textContent = section && category && subcategory
            ? '/' + encodeURIComponent(section) + '/' + encodeURIComponent(category) + '/' + encodeURIComponent(subcategory) + '/'
            : 'Choose a parent Category and enter an alias';
    }
}
//-->
</script>
<div class="red-admin-section-return">
    <button type="button" class="red-admin-section-return__button" onclick="showdiv('edit_subcategory_grid'); return false;">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Back to subcategories
    </button>
    <span>Editing an existing nested topic</span>
</div>
<form id="update_subcategory" name="update_subcategory" class="cp red-admin-section-form red-admin-section-form--edit red-admin-area-form--subcategory" method="post" onSubmit="return run_update_subcategory(this);" data-red-area-form="subcategory-edit">
<fieldset>
<?php echo red_csrf_input(); ?>
<div class="red-admin-section-shell">
    <header class="red-admin-section-header">
        <span class="red-admin-section-header__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M6 4v12a3 3 0 0 0 3 3h9"/><path d="m14 15 4 4-4 4M6 9h8"/><path d="m11 6 3 3-3 3"/></svg>
        </span>
        <div class="red-admin-section-header__copy">
            <span class="red-admin-section-header__eyebrow">Nested organization</span>
            <h2>Edit subcategory</h2>
            <p>Refine its public name, layout, presentation, and publishing status.</p>
        </div>
        <span class="red-admin-section-header__badge"><?php echo red_admin_area_html($row['SubCategories']); ?></span>
    </header>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-subcategory-basics-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">1</span><h3 id="red-edit-subcategory-basics-title">Subcategory basics</h3></div>
            <p>Public label, URL alias, layout, and visibility</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--identity">
            <div class="red-admin-section-field">
                <label for="edit-subcategory-title">Display title <span aria-hidden="true">*</span></label>
                <input name="Title" type="text" id="edit-subcategory-title" value="<?php echo red_admin_area_html($row['Title']); ?>" maxlength="120" required />
                <small>The visitor-facing title shown by compatible templates.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-subcategory-alias">URL alias <span aria-hidden="true">*</span></label>
                <input name="SubCategories" type="text" id="edit-subcategory-alias" value="<?php echo red_admin_area_html($row['SubCategories']); ?>" maxlength="120" required autocomplete="off" oninput="redAdminEditSubcategoryPreview()" />
                <small>Complete route: <strong data-red-edit-subcategory-url-preview><?php echo $currentPath !== '' ? red_admin_area_html($currentPath) : 'Choose a parent Category'; ?></strong></small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-subcategory-parent-category">Parent Category <span aria-hidden="true">*</span></label>
                <select name="CategoryRecordID" id="edit-subcategory-parent-category" required onchange="redAdminEditSubcategoryPreview()">
                    <option value=""><?php echo empty($parentOptions) ? 'Create and assign a Category first' : 'Choose a parent Category…'; ?></option>
                    <?php foreach ($parentOptions as $parentOption) {
                        $parentRecordId = (int) ($parentOption['ParentRecordID'] ?? 0);
                        $parentAlias = red_admin_text($parentOption['ParentAlias'] ?? '');
                        $parentTitle = red_admin_text($parentOption['ParentTitle'] ?? $parentAlias);
                        $sectionAlias = red_admin_text($parentOption['SectionAlias'] ?? '');
                        $sectionTitle = red_admin_text($parentOption['SectionTitle'] ?? $sectionAlias);
                    ?>
                        <option value="<?php echo $parentRecordId; ?>" data-section-alias="<?php echo red_admin_area_html($sectionAlias); ?>" data-category-alias="<?php echo red_admin_area_html($parentAlias); ?>" <?php if ($parentRecordId === $currentParentId) echo 'selected="selected"'; ?>><?php echo red_admin_area_html($sectionTitle); ?> › <?php echo red_admin_area_html($parentTitle); ?></option>
                    <?php } ?>
                </select>
                <small>Moving this Subcategory also updates its owned Article routes.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-subcategory-layout">Layout <span aria-hidden="true">*</span></label>
                <select name="Layout" id="edit-subcategory-layout" required>
                    <?php foreach ($layouts as $thislayout => $layoutLabel) { ?>
                        <option value="<?php echo red_admin_area_html($thislayout); ?>" <?php if ($thislayout === $layout) echo 'selected="selected"'; ?>><?php echo red_admin_area_html($layoutLabel); ?> (<?php echo red_admin_area_html($thislayout); ?>)</option>
                    <?php } ?>
                </select>
                <small>Choose a structure compatible with the content positions already assigned here.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-subcategory-active">Publishing status</label>
                <select name="Active" id="edit-subcategory-active">
                    <option value="Y" <?php if ($row['Active'] === 'Y') echo 'selected="selected"'; ?>>Active — visible on the site</option>
                    <option value="N" <?php if ($row['Active'] === 'N') echo 'selected="selected"'; ?>>Draft — hidden from visitors</option>
                </select>
                <small>Draft subcategories remain available in the administrator workspace.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-subcategory-presentation-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">2</span><h3 id="red-edit-subcategory-presentation-title">Presentation</h3></div>
            <p>Optional discovery and feature settings</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--content">
            <div class="red-admin-section-field">
                <span class="red-admin-section-field__label">Features</span>
                <?php if (empty($features)) { ?>
                    <div class="red-admin-section-empty">No optional subcategory features are installed.</div>
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
                <small>Features enhance the subcategory but are not required.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-subcategory-tags">Search tags</label>
                <input name="Tags" type="text" id="edit-subcategory-tags" value="<?php echo red_admin_area_html($row['Tags']); ?>" placeholder="music, lessons, events" />
                <small>Use a few comma-separated phrases that describe this subcategory.</small>
            </div>
            <div class="red-admin-section-field red-admin-section-field--wide">
                <label for="edit-subcategory-description">Subcategory description</label>
                <textarea name="Description" id="edit-subcategory-description" rows="4" placeholder="A concise summary for visitors and search previews."><?php echo red_admin_area_html($row['Description']); ?></textarea>
                <small>Keep this clear and specific; the public template decides where it appears.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel red-admin-section-panel--access" aria-labelledby="red-edit-subcategory-access-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">3</span><h3 id="red-edit-subcategory-access-title">Visitor access</h3></div>
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
                    ? 'Anyone can open this subcategory.'
                    : 'This flag is not enforced by public rendering. Treat this subcategory as public until member access is implemented.'; ?></small>
            </span>
        </div>
        <div class="red-admin-section-notice">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg>
            <p>Access is read-only here so saving other edits cannot accidentally change legacy metadata or promise protection the public runtime does not provide.</p>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-subcategory-management-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">4</span><h3 id="red-edit-subcategory-management-title">Content relationship</h3></div>
            <p>Review impact before removal</p>
        </div>
        <div class="red-admin-section-management">
            <div class="red-admin-section-stat">
                <span class="red-admin-section-stat__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6M9 16h6"/></svg></span>
                <span><strong><?php echo $relatedCount; ?></strong><small>Related Article<?php echo $relatedCount === 1 ? '' : 's'; ?></small></span>
            </div>
            <div class="red-admin-section-danger">
                <div>
                    <strong>Delete subcategory</strong>
                    <small><?php echo $relatedCount > 0
                        ? 'Related Articles keep their content and subcategory text, but the Subcategory record will be removed.'
                        : 'This subcategory has no related Articles. Deletion cannot be undone.'; ?></small>
                </div>
                <button type="button" id="deleterecord_<?php echo $RecordID; ?>" class="red-admin-section-delete" onclick="return run_delete_subcategory_record(<?php echo $RecordID; ?>);">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                    Delete subcategory
                </button>
            </div>
        </div>
        <span id="msggbox_deleterecord" class="red-admin-section-status red-admin-section-status--management" role="status" aria-live="polite" style="display:none"></span>
    </section>

    <input type="hidden" name="RecordID" id="subcategory-record-id" value="<?php echo $RecordID; ?>" />
    <input type="hidden" name="CurrentSubCategory" id="current-subcategory" value="<?php echo red_admin_area_html($row['SubCategories']); ?>" />
    <div class="red-admin-section-actions">
        <span id="msggbox_update_subcategory" class="red-admin-section-status" role="status" aria-live="polite" style="display:none"></span>
        <button type="submit" name="submit" value="Save" id="save-subcategory-changes" class="red-admin-section-submit">
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
