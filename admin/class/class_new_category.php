<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_area_helpers.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_seo_helpers.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class newcategory
{
    public function category_form($language)
    {
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $layoutOptions = red_admin_area_layout_options($db->connection);
        $featureOptions = red_admin_area_features($db->connection);
        $parentOptions = red_admin_area_parent_options($db->connection, 'RED_Categories', $language);
        $seoValues = red_seo_empty_values();
        ?>
<script type="text/javascript">
<!--
function run_insert_category (insert_category)
{
    $.ajax({
        type: "POST",
        url: "/admin/bin/insert_category.php",
        data: $("#insert_category").serialize(),
        success: function(data) {
            var status = $.trim(data);
            if (status === 'yes') {
                $('#msggbox_insert_category').removeClass('has-error').addClass('is-success').html("Category added.")
                    .hide()
                    .fadeIn(200, function() {
                        window.location.reload();
                    });
            } else if (status === 'error') {
                alert('There is a Section using the same name. Please enter a different Category name.');
            } else if (status === 'error2') {
                alert('There is a Category using the same name. Please enter a different Category name.');
            } else if (status === 'error3') {
                alert('There is a Subcategory using the same name. Please enter a different Category name.');
            } else {
                $('#msggbox_insert_category').removeClass('is-success').addClass('has-error').html("The category could not be saved. Please review the fields and try again.")
                    .hide()
                    .fadeIn(200);
            }
        }
    });
    return false;
}

function redAdminCategoryPreview()
{
    var input = document.getElementById('category-name');
    var parent = document.getElementById('category-parent-section');
    var value = input && input.value ? input.value : '';
    var slug = value;
    if (slug.normalize) {
        slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    slug = slug.toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    var preview = document.querySelector('[data-red-category-url-preview]');
    if (preview) {
        var selected = parent && parent.options[parent.selectedIndex];
        var section = selected && selected.dataset ? selected.dataset.sectionAlias : '';
        preview.textContent = slug && section
            ? '/' + encodeURIComponent(section) + '/' + encodeURIComponent(slug) + '/'
            : 'Choose a parent Section and enter a name';
    }
}
//-->
</script>
<form id="insert_category" name="insert_category" class="cp red-admin-section-form red-admin-area-form--category" method="post" onSubmit="return run_insert_category(this);" data-red-area-form="category-create">
<fieldset>
<?php echo red_csrf_input(); ?>
<div class="red-admin-section-shell">
    <header class="red-admin-section-header">
        <span class="red-admin-section-header__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/></svg>
        </span>
        <div class="red-admin-section-header__copy">
            <span class="red-admin-section-header__eyebrow">Content organization</span>
            <h2>Create a category</h2>
            <p>Add a reusable topic destination and choose how its articles will be arranged.</p>
        </div>
        <span class="red-admin-section-header__badge">New category</span>
    </header>

    <section class="red-admin-section-panel" aria-labelledby="red-category-basics-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">1</span><h3 id="red-category-basics-title">Category basics</h3></div>
            <p>Name, visibility, and page structure</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--basics">
            <div class="red-admin-section-field red-admin-section-field--name">
                <label for="category-name">Category name <span aria-hidden="true">*</span></label>
                <input name="Categories" type="text" id="category-name" value="" maxlength="120" required autocomplete="off" oninput="redAdminCategoryPreview()" />
                <small>Its complete route will be <strong data-red-category-url-preview>Choose a parent Section and enter a name</strong>.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="category-parent-section">Parent Section <span aria-hidden="true">*</span></label>
                <select name="SectionRecordID" id="category-parent-section" required onchange="redAdminCategoryPreview()">
                    <option value=""><?php echo empty($parentOptions) ? 'Create a Section first' : 'Choose a parent Section…'; ?></option>
                    <?php foreach ($parentOptions as $parentOption) {
                        $parentRecordId = (int) ($parentOption['ParentRecordID'] ?? 0);
                        $parentAlias = red_admin_text($parentOption['ParentAlias'] ?? '');
                        $parentTitle = red_admin_text($parentOption['ParentTitle'] ?? $parentAlias);
                    ?>
                        <option value="<?php echo $parentRecordId; ?>" data-section-alias="<?php echo red_admin_area_html($parentAlias); ?>"><?php echo red_admin_area_html($parentTitle); ?> — <?php echo red_admin_area_html($parentAlias); ?></option>
                    <?php } ?>
                </select>
                <small>Every Category belongs to one Section. Changing it later also updates its owned Article paths.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="category-layout">Layout <span aria-hidden="true">*</span></label>
                <select name="Layout" id="category-layout" required>
                    <?php foreach ($layoutOptions as $layoutOption => $layoutLabel) { ?>
                        <option value="<?php echo red_admin_area_html($layoutOption); ?>"><?php echo red_admin_area_html($layoutLabel); ?> (<?php echo red_admin_area_html($layoutOption); ?>)</option>
                    <?php } ?>
                </select>
                <small>You can change the layout later when its assigned positions are compatible.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="category-active">Publishing status</label>
                <select name="Active" id="category-active">
                    <option value="Y">Active — visible on the site</option>
                    <option value="N">Draft — hidden from visitors</option>
                </select>
                <small>Draft categories remain available in the administrator workspace.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-category-presentation-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">2</span><h3 id="red-category-presentation-title">Presentation</h3></div>
            <p>Optional discovery and feature settings</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--content">
            <div class="red-admin-section-field">
                <span class="red-admin-section-field__label">Features</span>
                <?php if (empty($featureOptions)) { ?>
                    <div class="red-admin-section-empty">No optional category features are installed.</div>
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
                <small>Features enhance the category but are not required.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="category-tags">Search tags</label>
                <input name="Tags" type="text" id="category-tags" value="" placeholder="music, lessons, events" />
                <small>Use a few comma-separated phrases that describe this category.</small>
            </div>
            <div class="red-admin-section-field red-admin-section-field--wide">
                <label for="category-description">Category description</label>
                <textarea name="Description" id="category-description" rows="4" placeholder="A concise summary for visitors and search previews."></textarea>
                <small>Keep this clear and specific; the public template decides where it appears.</small>
            </div>
        </div>
    </section>

    <?php echo red_admin_seo_fields_html($seoValues, 'category-seo'); ?>

    <section class="red-admin-section-panel red-admin-section-panel--access" aria-labelledby="red-category-access-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">3</span><h3 id="red-category-access-title">Visitor access</h3></div>
            <p>Public now; member access is a protected follow-up</p>
        </div>
        <div class="red-admin-section-access-grid">
            <label class="red-admin-section-access is-selected">
                <input type="radio" name="AccessLevel" value="Public" checked />
                <span class="red-admin-section-access__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.5 12h17M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg></span>
                <span><strong>Public</strong><small>Anyone can open this category.</small></span>
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
        <span id="msggbox_insert_category" class="red-admin-section-status" role="status" aria-live="polite" style="display:none"></span>
        <button type="submit" name="submit" value="Save" id="save-category" class="red-admin-section-submit" <?php if (empty($parentOptions)) echo 'disabled'; ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
            Create category
        </button>
    </div>
</div>
<input type="hidden" name="Language" id="category-language" value="<?php echo red_admin_area_html($language); ?>" />
</fieldset>
</form>
<?php
        $db->close();
    }
}
?>
