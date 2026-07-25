<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_area_helpers.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class newsubcategory
{
    public function subcategory_form($language)
    {
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $layoutOptions = red_admin_area_layout_options($db->connection);
        $featureOptions = red_admin_area_features($db->connection);
        $parentOptions = red_admin_area_parent_options($db->connection, 'RED_SubCategories', $language);
        ?>
<script type="text/javascript">
<!--
function run_insert_subcategory (insert_subcategory)
{
    $.ajax({
        type: "POST",
        url: "/admin/bin/insert_subcategory.php",
        data: $("#insert_subcategory").serialize(),
        success: function(data) {
            var status = $.trim(data);
            if (status === 'yes') {
                $('#msggbox_insert_subcategory').removeClass('has-error').addClass('is-success').html("Subcategory added.")
                    .hide()
                    .fadeIn(200, function() {
                        window.location.reload();
                    });
            } else if (status === 'error') {
                alert('There is a Section using the same name. Please enter a different Subcategory name.');
            } else if (status === 'error2') {
                alert('There is a Category using the same name. Please enter a different Subcategory name.');
            } else if (status === 'error3') {
                alert('There is a Subcategory using the same name. Please enter a different Subcategory name.');
            } else {
                $('#msggbox_insert_subcategory').removeClass('is-success').addClass('has-error').html("The subcategory could not be saved. Please review the fields and try again.")
                    .hide()
                    .fadeIn(200);
            }
        }
    });
    return false;
}

function redAdminSubcategoryPreview()
{
    var input = document.getElementById('subcategory-name');
    var parent = document.getElementById('subcategory-parent-category');
    var value = input && input.value ? input.value : '';
    var slug = value;
    if (slug.normalize) {
        slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    slug = slug.toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    var preview = document.querySelector('[data-red-subcategory-url-preview]');
    if (preview) {
        var selected = parent && parent.options[parent.selectedIndex];
        var section = selected && selected.dataset ? selected.dataset.sectionAlias : '';
        var category = selected && selected.dataset ? selected.dataset.categoryAlias : '';
        preview.textContent = slug && section && category
            ? '/' + encodeURIComponent(section) + '/' + encodeURIComponent(category) + '/' + encodeURIComponent(slug) + '/'
            : 'Choose a parent Category and enter a name';
    }
}
//-->
</script>
<form id="insert_subcategory" name="insert_subcategory" class="cp red-admin-section-form red-admin-area-form--subcategory" method="post" onSubmit="return run_insert_subcategory(this);" data-red-area-form="subcategory-create">
<fieldset>
<?php echo red_csrf_input(); ?>
<div class="red-admin-section-shell">
    <header class="red-admin-section-header">
        <span class="red-admin-section-header__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M6 4v12a3 3 0 0 0 3 3h9"/><path d="m14 15 4 4-4 4M6 9h8M11 6l3 3-3 3"/></svg>
        </span>
        <div class="red-admin-section-header__copy">
            <span class="red-admin-section-header__eyebrow">Nested organization</span>
            <h2>Create a subcategory</h2>
            <p>Add a focused topic level beneath the site’s broader destinations.</p>
        </div>
        <span class="red-admin-section-header__badge">New subcategory</span>
    </header>

    <section class="red-admin-section-panel" aria-labelledby="red-subcategory-basics-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">1</span><h3 id="red-subcategory-basics-title">Subcategory basics</h3></div>
            <p>Name, visibility, and page structure</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--basics">
            <div class="red-admin-section-field red-admin-section-field--name">
                <label for="subcategory-name">Subcategory name <span aria-hidden="true">*</span></label>
                <input name="SubCategories" type="text" id="subcategory-name" value="" maxlength="120" required autocomplete="off" oninput="redAdminSubcategoryPreview()" />
                <small>Its complete route will be <strong data-red-subcategory-url-preview>Choose a parent Category and enter a name</strong>.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="subcategory-parent-category">Parent Category <span aria-hidden="true">*</span></label>
                <select name="CategoryRecordID" id="subcategory-parent-category" required onchange="redAdminSubcategoryPreview()">
                    <option value=""><?php echo empty($parentOptions) ? 'Create and assign a Category first' : 'Choose a parent Category…'; ?></option>
                    <?php foreach ($parentOptions as $parentOption) {
                        $parentRecordId = (int) ($parentOption['ParentRecordID'] ?? 0);
                        $parentAlias = red_admin_text($parentOption['ParentAlias'] ?? '');
                        $parentTitle = red_admin_text($parentOption['ParentTitle'] ?? $parentAlias);
                        $sectionAlias = red_admin_text($parentOption['SectionAlias'] ?? '');
                        $sectionTitle = red_admin_text($parentOption['SectionTitle'] ?? $sectionAlias);
                    ?>
                        <option value="<?php echo $parentRecordId; ?>" data-section-alias="<?php echo red_admin_area_html($sectionAlias); ?>" data-category-alias="<?php echo red_admin_area_html($parentAlias); ?>"><?php echo red_admin_area_html($sectionTitle); ?> › <?php echo red_admin_area_html($parentTitle); ?></option>
                    <?php } ?>
                </select>
                <small>Every Subcategory belongs to one Category and inherits that Category’s Section.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="subcategory-layout">Layout <span aria-hidden="true">*</span></label>
                <select name="Layout" id="subcategory-layout" required>
                    <?php foreach ($layoutOptions as $layoutOption => $layoutLabel) { ?>
                        <option value="<?php echo red_admin_area_html($layoutOption); ?>"><?php echo red_admin_area_html($layoutLabel); ?> (<?php echo red_admin_area_html($layoutOption); ?>)</option>
                    <?php } ?>
                </select>
                <small>You can change the layout later when its assigned positions are compatible.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="subcategory-active">Publishing status</label>
                <select name="Active" id="subcategory-active">
                    <option value="Y">Active — visible on the site</option>
                    <option value="N">Draft — hidden from visitors</option>
                </select>
                <small>Draft subcategories remain available in the administrator workspace.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-subcategory-presentation-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">2</span><h3 id="red-subcategory-presentation-title">Presentation</h3></div>
            <p>Optional discovery and feature settings</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--content">
            <div class="red-admin-section-field">
                <span class="red-admin-section-field__label">Features</span>
                <?php if (empty($featureOptions)) { ?>
                    <div class="red-admin-section-empty">No optional subcategory features are installed.</div>
                <?php } else { ?>
                    <div class="red-admin-section-feature-list">
                    <?php foreach ($featureOptions as $featureOption) {
                        $featureValue = red_admin_area_html($featureOption);
                        $featureLabel = red_admin_area_html(red_admin_area_feature_label($featureOption));
                        $featureDescription = red_admin_area_html(red_admin_area_feature_description($featureOption));
                    ?>
                        <label class="red-admin-section-feature">
                            <input type="checkbox" name="Features[]" value="<?php echo $featureValue; ?>" />
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
                <label for="subcategory-tags">Search tags</label>
                <input name="Tags" type="text" id="subcategory-tags" value="" placeholder="music, lessons, events" />
                <small>Use a few comma-separated phrases that describe this subcategory.</small>
            </div>
            <div class="red-admin-section-field red-admin-section-field--wide">
                <label for="subcategory-description">Subcategory description</label>
                <textarea name="Description" id="subcategory-description" rows="4" placeholder="A concise summary for visitors and search previews."></textarea>
                <small>Keep this clear and specific; the public template decides where it appears.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel red-admin-section-panel--access" aria-labelledby="red-subcategory-access-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">3</span><h3 id="red-subcategory-access-title">Visitor access</h3></div>
            <p>Public now; member access is a protected follow-up</p>
        </div>
        <div class="red-admin-section-access-grid">
            <label class="red-admin-section-access is-selected">
                <input type="radio" name="AccessLevel" value="Public" checked />
                <span class="red-admin-section-access__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.5 12h17M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg></span>
                <span><strong>Public</strong><small>Anyone can open this subcategory.</small></span>
            </label>
            <div class="red-admin-section-access is-planned" aria-disabled="true">
                <span class="red-admin-section-access__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                <span><strong>Members only <em>Planned</em></strong><small>Requires member identity, entitlements, and route checks before it can safely protect content.</small></span>
            </div>
        </div>
        <div class="red-admin-section-notice">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg>
            <p>The legacy Private value was never enforced by public rendering. It is intentionally unavailable until access protection is implemented end to end.</p>
        </div>
    </section>

    <div class="red-admin-section-actions">
        <span id="msggbox_insert_subcategory" class="red-admin-section-status" role="status" aria-live="polite" style="display:none"></span>
        <button type="submit" name="submit" value="Save" id="save-subcategory" class="red-admin-section-submit" <?php if (empty($parentOptions)) echo 'disabled'; ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
            Create subcategory
        </button>
    </div>
</div>
<input type="hidden" name="Language" id="subcategory-language" value="<?php echo red_admin_area_html($language); ?>" />
</fieldset>
</form>
<?php
        $db->close();
    }
}
?>
