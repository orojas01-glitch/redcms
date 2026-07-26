<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_area_helpers.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_seo_helpers.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class newsection
{
	public function section_form($Language)
	{
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	        $layoutOptions = red_admin_area_layout_options($db->connection);
        $featureOptions = red_admin_area_features($db->connection);
        $seoValues = red_seo_empty_values();
		?>
        <!-- The main script file -->
<script type="text/javascript">
<!--
function run_insert_section (insert_section)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/insert_section.php", 
	data: $("#insert_section").serialize(),
	success: function(data) {
	var status = $.trim(data);
	if (status=='yes')
	{
	$('#msggbox_insert_section').removeClass('has-error').addClass('is-success').html("Section added.")
	.hide()
	.fadeIn(200, function() {
	$('#msggbox_insert_section');
	window.location.reload();
	});
	}
	else if(status=='error')
	{
	alert ('There is a Section using the same name.  Please enter a different Section Name.');	
	}
	else if(status=='error2')
	{
	alert ('There is a Category using the same name.  Please enter a different Section Name.');	
	}
	else if(status=='error3')
	{
	alert ('There is a SubCategory using the same name.  Please enter a different Section Name.');	
	}	
	else
	{
	$('#msggbox_insert_section').removeClass('is-success').addClass('has-error').html("The section could not be saved. Please review the fields and try again.")
	.hide()
	.fadeIn(200, function() {
	$('#msggbox_insert_section');
	});
	}
	}
	});
	return false;
}

function redAdminSectionPreview(input)
{
	var value = input && input.value ? input.value : '';
	var slug = value;
	if (slug.normalize) {
		slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	}
	slug = slug.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
	var preview = document.querySelector('[data-red-section-url-preview]');
	if (preview) {
		preview.textContent = slug ? '/' + slug + '/' : 'URL created automatically';
	}
}
//-->
</script>
<form id="insert_section" name="insert_section" class="cp red-admin-section-form" method="post" onSubmit="return run_insert_section(this);" data-red-section-form data-red-area-form="section-create">
<fieldset>
<?php echo red_csrf_input(); ?>
<div class="red-admin-section-shell">
    <header class="red-admin-section-header">
        <span class="red-admin-section-header__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 5.5h6l1.6 2H20v11H4z"/><path d="M8 13h8M12 9v8"/></svg>
        </span>
        <div class="red-admin-section-header__copy">
            <span class="red-admin-section-header__eyebrow">Site structure</span>
            <h2>Create a section</h2>
            <p>Add a top-level destination and choose how its content will be arranged.</p>
        </div>
        <span class="red-admin-section-header__badge">New section</span>
    </header>

    <section class="red-admin-section-panel" aria-labelledby="red-section-basics-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">1</span><h3 id="red-section-basics-title">Section basics</h3></div>
            <p>Name, visibility, and page structure</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--basics">
            <div class="red-admin-section-field red-admin-section-field--name">
                <label for="section-name">Section name <span aria-hidden="true">*</span></label>
                <input name="Sections" type="text" id="section-name" value="" maxlength="120" required autocomplete="off" oninput="redAdminSectionPreview(this)" />
                <small>The public URL will be <strong data-red-section-url-preview>URL created automatically</strong>.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="section-layout">Layout <span aria-hidden="true">*</span></label>
                <select name="Layout" id="section-layout" required>
                    <?php foreach ($layoutOptions as $layoutOption => $layoutLabel) { ?>
                        <option value="<?php echo red_admin_area_html($layoutOption); ?>"><?php echo red_admin_area_html($layoutLabel); ?> (<?php echo red_admin_area_html($layoutOption); ?>)</option>
                    <?php } ?>
                </select>
                <small>You can change the layout later when its assigned positions are compatible.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="section-active">Publishing status</label>
                <select name="Active" id="section-active">
                    <option value="Y">Active — visible on the site</option>
                    <option value="N">Draft — hidden from visitors</option>
                </select>
                <small>Draft sections remain available in the administrator workspace.</small>
            </div>
        </div>
    </section>

    <section class="red-admin-section-panel" aria-labelledby="red-section-presentation-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">2</span><h3 id="red-section-presentation-title">Presentation</h3></div>
            <p>Optional discovery and feature settings</p>
        </div>
        <div class="red-admin-section-grid red-admin-section-grid--content">
            <div class="red-admin-section-field">
                <span class="red-admin-section-field__label">Features</span>
                <?php if (empty($featureOptions)) { ?>
                    <div class="red-admin-section-empty">No optional section features are installed.</div>
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
                <small>Features enhance the section but are not required.</small>
            </div>
            <div class="red-admin-section-field">
                <label for="section-tags">Search tags</label>
                <input name="Tags" type="text" id="section-tags" value="" placeholder="music, lessons, events" />
                <small>Use a few comma-separated phrases that describe this section.</small>
            </div>
            <div class="red-admin-section-field red-admin-section-field--wide">
                <label for="section-description">Section description</label>
                <textarea name="Description" id="section-description" rows="4" placeholder="A concise summary for visitors and search previews."></textarea>
                <small>Keep this clear and specific; the public template decides where it appears.</small>
            </div>
        </div>
    </section>

    <?php echo red_admin_seo_fields_html($seoValues, 'section-seo'); ?>

    <section class="red-admin-section-panel red-admin-section-panel--access" aria-labelledby="red-section-access-title">
        <div class="red-admin-section-panel__heading">
            <div><span class="red-admin-section-panel__step">3</span><h3 id="red-section-access-title">Visitor access</h3></div>
            <p>Public now; member access is a protected follow-up</p>
        </div>
        <div class="red-admin-section-access-grid">
            <label class="red-admin-section-access is-selected">
                <input type="radio" name="AccessLevel" value="Public" checked />
                <span class="red-admin-section-access__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.5 12h17M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg></span>
                <span><strong>Public</strong><small>Anyone can open this section.</small></span>
            </label>
            <div class="red-admin-section-access is-planned" aria-disabled="true">
                <span class="red-admin-section-access__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                <span><strong>Members only <em>Planned</em></strong><small>Requires a separate member identity and entitlement system before it can safely protect content.</small></span>
            </div>
        </div>
        <div class="red-admin-section-notice">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/></svg>
            <p>The legacy Private value was never enforced by public rendering. It is intentionally unavailable here until route protection, member sessions, and access checks are implemented together.</p>
        </div>
    </section>

    <div class="red-admin-section-actions">
        <span id="msggbox_insert_section" class="red-admin-section-status" role="status" aria-live="polite" style="display:none"></span>
        <button type="submit" name="submit" value="Save" id="save-section" class="red-admin-section-submit">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/></svg>
            Create section
        </button>
    </div>
</div>
<input type="hidden" name="Language" id="Language" value="<?php echo red_admin_area_html($Language)?>" />
</fieldset>
</form>
<?php
        $db->close();
	}
}
?>
