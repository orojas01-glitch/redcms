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
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_seo_helpers.php';

$recordId = (int) red_admin_post_text('RecordID');
if ($recordId <= 0) {
    echo 'no';
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$row = red_admin_area_record($db->connection, 'RED_Sections', $recordId);
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
$relatedCount = red_admin_area_related_article_count(
    $db->connection,
    'Sections',
    $row['Sections'],
    $row['Language']
);
$childCategoryCount = red_admin_area_child_count($db->connection, 'RED_Sections', $RecordID);
$storedAccessLevel = red_admin_text($row['AccessLevel'] ?? 'Public');
$seoValues = red_admin_seo_values($db->connection, 'section', $RecordID);
$csrfToken = red_csrf_token();
$deletePrompt = $childCategoryCount > 0
    ? 'This Section cannot be deleted until its ' . $childCategoryCount . ' child Categor' . ($childCategoryCount === 1 ? 'y is' : 'ies are') . ' reassigned or deleted.'
    : ($relatedCount > 0
    ? 'Delete this Section and move its ' . $relatedCount . ' related Article' . ($relatedCount === 1 ? '' : 's') . ' to Inactive Articles?'
    : 'Delete this Section? It has no related Articles.');
?>
<script type="text/javascript">
<!--
function run_update_section (update_section)
{
    $.ajax({
        type: "POST",
        url: "/admin/bin/update_section.php",
        data: $("#update_section").serialize(),
        success: function(data, textStatus, jqXHR) {
            var status = $.trim(data);
            if (status === 'yes' || status === 'updateupdateyes' || status === 'updateupdateupdateyes' || status === 'updateyes') {
                var canonicalAlias = jqXHR.getResponseHeader('X-RED-Canonical-Alias');
                if (canonicalAlias) {
                    alert('Your Section name was updated.\n NOTE: \'Articles\' and \'Menu Links\' owned by the previous Section were updated.');
                }
                $('#msggbox_update_section').removeClass('has-error').addClass('is-success').html("Section updated.")
                    .hide()
                    .fadeIn(200, function() {
                        if (!canonicalAlias || !RED_ADMIN_AREA_RENAME.redirect(canonicalAlias, 1)) {
                            window.location.reload();
                        }
                    });
            } else if (status === 'error') {
                alert('There is a Section using the same name. Please enter a different Section name.');
            } else if (status === 'error2') {
                alert('There is a Category using the same name. Please enter a different Section name.');
            } else if (status === 'error3') {
                alert('There is a Subcategory using the same name. Please enter a different Section name.');
            } else {
                $('#msggbox_update_section').removeClass('is-success').addClass('has-error').html("The section could not be updated. Please review the fields and try again.")
                    .hide()
                    .fadeIn(200);
            }
        }
    });
    return false;
}

function run_delete_section_record (RecordID)
{
    if (<?php echo $childCategoryCount; ?> > 0) {
        $('#msggbox_deleterecord').removeClass('is-success').addClass('has-error').html(<?php echo json_encode($deletePrompt); ?>).show();
        return false;
    }
    if (!confirm(<?php echo json_encode($deletePrompt); ?>)) {
        return false;
    }

    $.ajax({
        type: "POST",
        url: "/admin/bin/delete_label.php",
        data: {RecordID: RecordID, T: "sections", csrf_token: <?php echo json_encode($csrfToken); ?>},
        success: function(data, textStatus, jqXHR) {
            if ($.trim(data) === 'yes') {
                var archivedCount = parseInt(jqXHR.getResponseHeader('X-RED-Archived-Articles') || '0', 10);
                var deleteMessage = archivedCount > 0
                    ? "Section deleted. " + archivedCount + " Article" + (archivedCount === 1 ? " was" : "s were") + " moved to Inactive Articles."
                    : "Section deleted. No related Articles required archiving.";
                $('#msggbox_deleterecord').removeClass('has-error').addClass('is-success').html(deleteMessage)
                    .hide()
                    .fadeIn(200, function() {
                        window.location.assign('/#cp_inactive');
                    });
            } else {
                $('#msggbox_deleterecord').removeClass('is-success').addClass('has-error').html("The section could not be deleted. Please try again.")
                    .hide()
                    .fadeIn(200);
            }
        }
    });
    return false;
}
//-->
</script>
<div class="red-admin-section-return">
    <button type="button" class="red-admin-section-return__button" onclick="showdiv('edit_section_grid'); return false;">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Back to sections
    </button>
    <span>Editing an existing site destination</span>
</div>
<form id="update_section" name="update_section" class="cp red-admin-section-form red-admin-section-form--edit" method="post" onSubmit="return run_update_section(this);" data-red-area-form="section-edit">
<fieldset>
<?php echo red_csrf_input(); ?>
<div class="red-admin-section-shell">
    <header class="red-admin-section-header">
        <span class="red-admin-section-header__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 5.5h6l1.6 2H20v11H4z"/><path d="m9 15 6-6 2 2-6 6-3 1z"/></svg>
        </span>
        <div class="red-admin-section-header__copy">
            <span class="red-admin-section-header__eyebrow">Site structure</span>
            <h2>Edit section</h2>
            <p>Refine its public name, layout, presentation, and publishing status.</p>
        </div>
        <span class="red-admin-section-header__badge"><?php echo red_admin_area_html($row['Sections']); ?></span>
    </header>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-section-basics-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">1</span><h3 id="red-edit-section-basics-title">Section basics</h3></div>
            <p>Public label, URL alias, layout, and visibility</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--identity">
            <div class="red-admin-section-field">
                <label for="edit-section-title">Display title <span aria-hidden="true">*</span></label>
                <input name="Title" type="text" id="edit-section-title" value="<?php echo red_admin_area_html($row['Title']); ?>" maxlength="120" required />
                <small>The visitor-facing title shown by compatible templates.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-section-alias">URL alias <span aria-hidden="true">*</span></label>
                <input name="Sections" type="text" id="edit-section-alias" value="<?php echo red_admin_area_html($row['Sections']); ?>" maxlength="120" required autocomplete="off" />
                <small>Changing this also updates owned Articles and Menu Links, then opens the renamed route.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-section-layout">Layout <span aria-hidden="true">*</span></label>
                <select name="Layout" id="edit-section-layout" required>
                    <?php foreach ($layouts as $thislayout => $layoutLabel) { ?>
                        <option value="<?php echo red_admin_area_html($thislayout); ?>" <?php if ($thislayout === $layout) echo 'selected="selected"'; ?>><?php echo red_admin_area_html($layoutLabel); ?> (<?php echo red_admin_area_html($thislayout); ?>)</option>
                    <?php } ?>
                </select>
                <small>Choose a structure compatible with the content positions already assigned here.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-section-active">Publishing status</label>
                <select name="Active" id="edit-section-active">
                    <option value="Y" <?php if ($row['Active'] === 'Y') echo 'selected="selected"'; ?>>Active — visible on the site</option>
                    <option value="N" <?php if ($row['Active'] === 'N') echo 'selected="selected"'; ?>>Draft — hidden from visitors</option>
                </select>
                <small>Draft sections remain available in the administrator workspace.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-section-presentation-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">2</span><h3 id="red-edit-section-presentation-title">Presentation</h3></div>
            <p>Optional discovery and feature settings</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--content">
            <div class="red-admin-section-field">
                <span class="red-admin-section-field__label">Features</span>
                <?php if (empty($features)) { ?>
                    <div class="red-admin-section-empty">No optional section features are installed.</div>
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
                <small>Features enhance the section but are not required.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="edit-section-tags">Search tags</label>
                <input name="Tags" type="text" id="edit-section-tags" value="<?php echo red_admin_area_html($row['Tags']); ?>" placeholder="music, lessons, events" />
                <small>Use a few comma-separated phrases that describe this section.</small>
            </div>
            <div class="red-admin-section-field red-admin-section-field--wide">
                <label for="edit-section-description">Section description</label>
                <textarea name="Description" id="edit-section-description" rows="4" placeholder="A concise summary for visitors and search previews."><?php echo red_admin_area_html($row['Description']); ?></textarea>
                <small>Keep this clear and specific; the public template decides where it appears.</small>
            </div>
        </div>
    </section>

    <?php echo red_admin_seo_fields_html($seoValues, 'section-seo'); ?>

    <section class="red-admin-section-panel red-admin-section-panel--access" aria-labelledby="red-edit-section-access-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">3</span><h3 id="red-edit-section-access-title">Visitor access</h3></div>
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
                    ? 'Anyone can open this section.'
                    : 'This flag is not enforced by public rendering. Treat this section as public until member access is implemented.'; ?></small>
            </span>
        </div>
        <div class="red-admin-section-notice">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg>
            <p>Access is read-only here so saving other edits cannot accidentally change legacy metadata or promise protection the public runtime does not provide.</p>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-edit-section-management-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">4</span><h3 id="red-edit-section-management-title">Content relationship</h3></div>
            <p>Review impact before removal</p>
        </div>
        <div class="red-admin-section-management">
            <div class="red-admin-section-stat">
                <span class="red-admin-section-stat__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6M9 16h6"/></svg></span>
                <span><strong><?php echo $relatedCount; ?></strong><small>Related Article<?php echo $relatedCount === 1 ? '' : 's'; ?></small></span>
            </div>
            <div class="red-admin-section-stat">
                <span class="red-admin-section-stat__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5"/></svg></span>
                <span><strong><?php echo $childCategoryCount; ?></strong><small>Child Categor<?php echo $childCategoryCount === 1 ? 'y' : 'ies'; ?></small></span>
            </div>
            <div class="red-admin-section-danger">
                <div>
                    <strong>Delete section</strong>
                    <small><?php echo $childCategoryCount > 0
                        ? 'Reassign or delete its child Categories first. Their hierarchy is protected.'
                        : ($relatedCount > 0
                        ? 'Its related Articles will move to Inactive Articles so their content is preserved.'
                        : 'This section has no related Articles. Deletion cannot be undone.'); ?></small>
                </div>
                <button type="button" id="deleterecord_<?php echo $RecordID; ?>" class="red-admin-section-delete" onclick="return run_delete_section_record(<?php echo $RecordID; ?>);" <?php if ($childCategoryCount > 0) echo 'disabled aria-disabled="true"'; ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                    <?php echo $childCategoryCount > 0 ? 'Child Categories exist' : 'Delete section'; ?>
                </button>
            </div>
        </div>
        <span id="msggbox_deleterecord" class="red-admin-section-status red-admin-section-status--management" role="status" aria-live="polite" style="display:none"></span>
    </section>

    <input type="hidden" name="RecordID" id="section-record-id" value="<?php echo $RecordID; ?>" />
    <input type="hidden" name="CurrentSection" id="current-section" value="<?php echo red_admin_area_html($row['Sections']); ?>" />
    <div class="red-admin-section-actions">
        <span id="msggbox_update_section" class="red-admin-section-status" role="status" aria-live="polite" style="display:none"></span>
        <button type="submit" name="submit" value="Save" id="save-section-changes" class="red-admin-section-submit">
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
