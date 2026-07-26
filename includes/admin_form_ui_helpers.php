<?php
/**
 * Shared presentation helpers for the modern Form create/edit workspace.
 *
 * RED_C_Form.LongDesc remains the canonical legacy field definition. The
 * visual builder stages changes into that exact textarea so existing forms can
 * be opened and saved without silently normalizing their stored source.
 */

require_once __DIR__.'/admin_form_helpers.php';
require_once __DIR__.'/admin_content_revision_ui_helpers.php';
require_once __DIR__.'/admin_seo_helpers.php';

if (!function_exists('red_admin_form_ui_date_meta')) {
    function red_admin_form_ui_date_meta($value, $sentinel)
    {
        $date = substr(red_admin_text($value ?? ''), 0, 10);
        $display = $date !== '0000-00-00'
            && $date !== $sentinel
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
            ? $date
            : '';

        return ['display' => $display];
    }
}

if (!function_exists('red_admin_form_ui_preserve_option')) {
    function red_admin_form_ui_preserve_option($options, $selected)
    {
        $selected = red_admin_text($selected);
        if ($selected === '' || strpos((string) $options, ' selected="selected"') !== false) {
            return (string) $options;
        }

        return '<option value="'.red_admin_area_html($selected).'" selected="selected">'
            .red_admin_area_html($selected).' — unavailable; preserved</option>'.(string) $options;
    }
}

if (!function_exists('red_admin_form_ui_upload_url')) {
    function red_admin_form_ui_upload_url(array $parameters)
    {
        unset($parameters['csrf_token']);
        return '/admin/bin/post_file.php?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('red_admin_form_ui_type_options')) {
    function red_admin_form_ui_type_options()
    {
        return [
            'Contact' => 'Contact / inquiry',
            'Response' => 'Email + response',
            'Register' => 'Registration & storage',
            'Other' => 'Display-only / legacy',
            'Login' => 'Administrator login',
        ];
    }
}

if (!function_exists('red_admin_form_ui_default_definition')) {
    function red_admin_form_ui_default_definition($formType)
    {
        $definitions = [
            'Contact' => [
                '#|question=|name=name|type=textfield|required=true|displayname=Full name|initialvalue=|autocomplete=name|placeholder=Your name',
                '#|question=|name=email|type=textfield|required=true|displayname=Email address|initialvalue=|inputtype=email|autocomplete=email|placeholder=you@example.com',
                '#|question=|name=message|type=textarea|required=true|displayname=Message|readonly=false|initialvalue=|cols=45|rows=6',
                '#|question=|name=Submit|type=button|displayname=Send message',
            ],
            'Response' => [
                '#|question=|name=full_name|type=textfield|required=true|displayname=Full name|initialvalue=|autocomplete=name|placeholder=Your name',
                '#|question=|name=email|type=textfield|required=true|displayname=Email address|initialvalue=|inputtype=email|autocomplete=email|placeholder=you@example.com',
                '#|question=|name=topic|type=select|required=true|displayname=Topic|value=Choose a topic^selected,General question,Support,Other',
                '#|question=|name=reply_method|type=radio|required=true|displayname=Preferred reply|value=Email^email,Phone^phone',
                '#|question=|name=interests|type=checkbox|required=false|displayname=Interests|value=Updates^updates,Events^events',
                '#|question=|name=message|type=textarea|required=false|displayname=Message|readonly=false|initialvalue=|cols=45|rows=6',
                '#|question=|name=Submit|type=button|displayname=Continue',
            ],
            'Register' => [
                '#|question=|name=full_name|type=textfield|required=true|displayname=Full name|initialvalue=|autocomplete=name|placeholder=Your name',
                '#|question=|name=email|type=textfield|required=true|displayname=Email address|initialvalue=|inputtype=email|autocomplete=email|placeholder=you@example.com',
                '#|question=|name=registration_type|type=select|required=true|displayname=Registration type|value=Choose a type^selected,General registration,Event registration,Membership',
                '#|question=|name=consent|type=checkbox|required=true|displayname=Consent|value=I agree^yes',
                '#|question=|name=notes|type=textarea|required=false|displayname=Notes|readonly=false|initialvalue=|cols=45|rows=5',
                '#|question=|name=Submit|type=button|displayname=Register',
            ],
        ];

        $rows = $definitions[red_admin_form_clean_type($formType)] ?? [];
        return $rows === [] ? '' : implode(";\r\n", $rows).';';
    }
}

if (!function_exists('red_admin_form_ui_creation_definition')) {
    function red_admin_form_ui_creation_definition($formType, $candidate)
    {
        $formType = red_admin_form_clean_type($formType);
        $candidate = red_admin_form_scalar($candidate);
        if (!in_array($formType, ['Contact', 'Response', 'Register'], true)) {
            return $candidate;
        }

        try {
            red_public_contact_compile_fields($candidate);
            return $candidate;
        } catch (Throwable $exception) {
            return red_admin_form_ui_default_definition($formType);
        }
    }
}

if (!function_exists('red_admin_form_ui_type_meta')) {
    function red_admin_form_ui_type_meta($formType)
    {
        $types = [
            'Contact' => [
                'label' => 'Contact form',
                'summary' => 'Collect an inquiry and deliver it to the configured recipients.',
                'outcome' => 'Email delivery',
            ],
            'Other' => [
                'label' => 'Display-only form',
                'summary' => 'Render a legacy field layout without attaching a submission handler.',
                'outcome' => 'Display-only / no submission',
            ],
            'Response' => [
                'label' => 'Response form',
                'summary' => 'Collect fields and return a custom confirmation after submission.',
                'outcome' => 'Custom response',
            ],
            'Register' => [
                'label' => 'Registration form',
                'summary' => 'Collect a registration in a system-managed storage table.',
                'outcome' => 'Managed storage',
            ],
            'Login' => [
                'label' => 'Administrator login',
                'summary' => 'Use the protected username and password contract for administrator access.',
                'outcome' => 'Secure sign-in',
            ],
        ];

        return $types[$formType] ?? [
            'label' => $formType === '' ? 'Form' : $formType,
            'summary' => 'Build and arrange the fields used by this form.',
            'outcome' => 'Form submission',
        ];
    }
}

if (!function_exists('red_admin_render_form_workspace')) {
    function red_admin_render_form_workspace(array $context)
    {
        $defaults = [
            'mode' => 'create',
            'returnTarget' => 'add_content_grid',
            'submitUrl' => '/admin/bin/insert_form.php',
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
            'formType' => 'Other',
            'typeOptions' => [],
            'definition' => '',
            'shortDesc' => '',
            'subject' => '',
            'submitter' => '',
            'destinatary' => '',
            'cc' => '',
            'bcc' => '',
            'response' => '',
            'tableName' => '',
            'schemaLocked' => false,
            'typePresets' => [],
            'sectionOptions' => '',
            'categoryOptions' => '',
            'subCategoryOptions' => '',
            'articleOptions' => '',
            'startDateMeta' => ['display' => ''],
            'expirationDateMeta' => ['display' => ''],
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
        $formType = red_admin_form_clean_type($context['formType']);
        $isLogin = $formType === 'Login';
        $purposeLocked = $isEdit || $isLogin;
        $schemaLocked = (bool) $context['schemaLocked'] || $isLogin;
        $usesDelivery = in_array($formType, ['Contact', 'Response', 'Register'], true);
        $usesResponse = in_array($formType, ['Response', 'Register'], true);
        $usesRegistration = $formType === 'Register';
        $typeMeta = red_admin_form_ui_type_meta($formType);
        $formId = $isEdit ? 'update_form' : 'insert_form';
        $messageId = $isEdit ? 'msggbox_update_form' : 'msggbox_insert_form';
        $returnLabel = $isEdit ? 'Show content' : 'All content types';
        $pageLabel = $isEdit ? 'Edit Form' : 'New Form';
        $heading = $isEdit ? 'Edit form' : 'Create a new form';
        $saveLabel = $isEdit ? 'Save changes' : 'Save form';
        $definitionCount = count(red_admin_form_parse_definition($context['definition']));
        $formScript = '/admin/assets/js/form-builder.js';
        $formScriptVersion = is_file($_SERVER['DOCUMENT_ROOT'].$formScript)
            ? filemtime($_SERVER['DOCUMENT_ROOT'].$formScript)
            : '1';
        $sourceReadOnly = $isLogin || $schemaLocked;
        $typeOptions = is_array($context['typeOptions']) && $context['typeOptions'] !== []
            ? $context['typeOptions']
            : [$formType => $typeMeta['label']];
        if (!isset($typeOptions[$formType])) {
            $typeOptions = [$formType => $typeMeta['label']] + $typeOptions;
        }
        $supportingImages = [
            [
                'field' => 'BigPict',
                'inputId' => 'form-feature-image',
                'title' => 'Feature image',
                'description' => 'Optional image used by feature and hero placements',
                'current' => red_admin_text($context['bigPict']),
                'uploadUrl' => red_admin_text($context['uploadUrls']['BigPict'] ?? ''),
            ],
            [
                'field' => 'SmallPict',
                'inputId' => 'form-summary-image',
                'title' => 'Summary image',
                'description' => 'Optional image used by lists and compact cards',
                'current' => red_admin_text($context['smallPict']),
                'uploadUrl' => red_admin_text($context['uploadUrls']['SmallPict'] ?? ''),
            ],
        ];
        $presetJson = json_encode(
            $context['typePresets'],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($presetJson)) {
            $presetJson = '{}';
        }
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
    class="cp red-admin-article-form red-admin-form-workspace"
    method="post"
    data-red-form-builder
    data-form-mode="<?php echo red_admin_area_html($mode); ?>"
    data-form-type="<?php echo red_admin_area_html($formType); ?>"
    data-form-subtype="<?php echo red_admin_area_html($formType); ?>"
    data-form-schema-locked="<?php echo $schemaLocked ? 'true' : 'false'; ?>"
    data-register-schema-locked="<?php echo ($formType === 'Register' && $schemaLocked) ? 'true' : 'false'; ?>"
    data-submit-url="<?php echo red_admin_area_html($context['submitUrl']); ?>"
    data-delete-url="<?php echo red_admin_area_html($context['deleteUrl']); ?>"
    data-max-image-bytes="2097152"
    onsubmit="return <?php echo $isEdit ? 'run_update_form' : 'run_insert_form'; ?>(this);"
>
    <fieldset>
        <legend class="red-admin-visually-hidden"><?php echo red_admin_area_html($heading); ?></legend>

        <div class="red-admin-article-shell">
            <header class="red-admin-article-header red-admin-form-header">
                <span class="red-admin-article-header__icon red-admin-form-header__icon<?php echo $isEdit ? ' red-admin-article-header__icon--edit' : ''; ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M5 4h14v16H5z"></path><path d="M8 8h8M8 12h3M13 12h3M8 16h8"></path></svg>
                </span>
                <div class="red-admin-article-header__copy">
                    <span class="red-admin-article-header__eyebrow"><?php echo $isEdit ? 'Edit Content' : 'Add Content'; ?></span>
                    <h2><?php echo red_admin_area_html($heading); ?></h2>
                    <p>Choose the form purpose, arrange fields visually, then configure delivery and optional settings.</p>
                </div>
                <span class="red-admin-article-header__badge red-admin-form-header__badge"><?php echo red_admin_area_html($typeMeta['label']); ?></span>
            </header>

            <span class="red-admin-visually-hidden" data-form-announcer role="status" aria-live="polite"></span>
            <script type="application/json" data-form-type-presets><?php echo $presetJson; ?></script>

            <section class="red-admin-article-panel" aria-labelledby="form-basics-title">
                <div class="red-admin-article-panel__heading">
                    <div><span class="red-admin-article-panel__step">01</span><h3 id="form-basics-title">Form basics</h3></div>
                    <p>Identity, visibility, and placement</p>
                </div>

                <div class="red-admin-field-grid red-admin-field-grid--basics">
                    <div class="red-admin-field red-admin-field--title">
                        <label for="form-title">Title <span aria-hidden="true">*</span></label>
                        <input name="Title" type="text" id="form-title" value="<?php echo red_admin_area_html($context['title']); ?>" autocomplete="off" required aria-describedby="form-title-help form-title-error" />
                        <span class="red-admin-field__help" id="form-title-help">The administrator label used to identify this form.</span>
                        <span class="red-admin-field__error" id="form-title-error" data-form-title-error hidden>Add a title before saving.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="form-status">Status</label>
                        <select name="Active" id="form-status"><option value="Y"<?php echo $context['active'] === 'Y' ? ' selected="selected"' : ''; ?>>Published</option><option value="N"<?php echo $context['active'] !== 'Y' ? ' selected="selected"' : ''; ?>>Inactive</option></select>
                        <span class="red-admin-field__help">Inactive forms stay out of public view.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="form-layout-position">Layout position</label>
                        <select name="<?php echo red_admin_area_html($context['varPosition']); ?>" id="form-layout-position">
                            <?php foreach ($context['positionOptions'] as $positionValue => $positionLabel) { ?>
                                <option value="<?php echo (int) $positionValue; ?>"<?php echo (int) $context['position'] === (int) $positionValue ? ' selected="selected"' : ''; ?>><?php echo red_admin_area_html($positionLabel); ?> (<?php echo (int) $positionValue; ?>)</option>
                            <?php } ?>
                        </select>
                        <span class="red-admin-field__help">Where this form appears in the selected layout.</span>
                    </div>
                    <div class="red-admin-field">
                        <label for="form-order">Order</label>
                        <input name="<?php echo red_admin_area_html($context['varPosition'].'Order'); ?>" type="number" id="form-order" value="<?php echo red_admin_area_html($context['positionOrder']); ?>" min="0" step="1" inputmode="numeric" placeholder="Auto" />
                        <span class="red-admin-field__help">Lower numbers appear first.</span>
                    </div>
                    <label class="red-admin-choice-card" for="form-home-feature">
                        <input name="HomeFeature" type="checkbox" id="form-home-feature" value="Y"<?php echo $context['homeFeature'] === 'Y' ? ' checked="checked"' : ''; ?> />
                        <span class="red-admin-choice-card__control" aria-hidden="true"></span>
                        <span><strong>Feature on Home</strong><small>Include this form in the Home feature area.</small></span>
                    </label>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-form-purpose-panel" aria-labelledby="form-purpose-title">
                <div class="red-admin-article-panel__heading">
                    <div><span class="red-admin-article-panel__step">02</span><h3 id="form-purpose-title">Purpose &amp; outcome</h3></div>
                    <span class="red-admin-form-outcome" data-form-outcome><?php echo red_admin_area_html($typeMeta['outcome']); ?></span>
                </div>
                <div class="red-admin-form-purpose-grid">
                    <div class="red-admin-field">
                        <label for="form-purpose">Form purpose</label>
                        <select id="form-purpose" data-form-type-select<?php echo $purposeLocked ? ' disabled aria-disabled="true"' : ' name="FormType"'; ?>>
                            <?php foreach ($typeOptions as $typeValue => $typeLabel) { ?>
                                <option value="<?php echo red_admin_area_html($typeValue); ?>"<?php echo $typeValue === $formType ? ' selected="selected"' : ''; ?>><?php echo red_admin_area_html($typeLabel); ?></option>
                            <?php } ?>
                        </select>
                        <?php if ($purposeLocked) { ?><input type="hidden" name="FormType" value="<?php echo red_admin_area_html($formType); ?>" /><?php } ?>
                        <span class="red-admin-field__help"><?php echo $isEdit ? 'The purpose is fixed after a form is created so its public endpoint remains stable.' : ($isLogin ? 'Administrator sign-in uses a protected system purpose.' : 'Choose what should happen when a visitor submits the form.'); ?></span>
                    </div>
                    <div class="red-admin-form-purpose-card" data-form-purpose-card>
                        <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 4.7-3.1 7.7-8 9-4.9-1.3-8-4.3-8-9V7z"></path><path d="M8.5 12l2.3 2.3 4.7-5"></path></svg></span>
                        <div><strong data-form-purpose-label><?php echo red_admin_area_html($typeMeta['label']); ?></strong><p data-form-purpose-copy><?php echo red_admin_area_html($typeMeta['summary']); ?></p></div>
                    </div>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-form-builder-panel" aria-labelledby="form-fields-title">
                <div class="red-admin-article-panel__heading red-admin-form-builder-heading">
                    <div><span class="red-admin-article-panel__step">03</span><h3 id="form-fields-title">Form fields</h3></div>
                    <span class="red-admin-form-field-count" data-form-field-count role="status" aria-live="polite"><?php echo (int) $definitionCount; ?> fields</span>
                </div>

                <div class="red-admin-form-builder-alert" data-form-builder-alert<?php echo (!$isLogin && !$schemaLocked) ? ' hidden' : ''; ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v5c0 5-3.4 8.2-8 10-4.6-1.8-8-5-8-10V6z"></path><path d="M9 12l2 2 4-5"></path></svg>
                    <?php if ($isLogin) { ?>
                        <p><strong>Protected login contract.</strong> Username, password, and submit controls stay locked so administrator sign-in cannot be broken accidentally.</p>
                    <?php } elseif ($schemaLocked) { ?>
                        <p><strong>Stored registration schema.</strong> Fields, labels, validation, choices, and order are read-only here to protect the saved submission schema.</p>
                    <?php } ?>
                </div>

                <div class="red-admin-form-workspace-tabs" role="tablist" aria-label="Form editing mode">
                    <button type="button" role="tab" id="form-builder-tab" aria-controls="form-builder-panel" aria-selected="true" tabindex="0" data-form-workspace-tab="builder">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v4H4zM4 12h16v7H4z"></path><path d="M8 14v3M12 14v3"></path></svg>
                        <span><strong>Builder</strong><small>Arrange fields visually</small></span>
                    </button>
                    <button type="button" role="tab" id="form-source-tab" aria-controls="form-source-panel" aria-selected="false" tabindex="-1" data-form-workspace-tab="source"<?php echo $isLogin ? ' disabled aria-disabled="true" title="The login definition is protected"' : ''; ?>>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7l-5 5 5 5M16 7l5 5-5 5M14 4l-4 16"></path></svg>
                        <span><strong>Definition source</strong><small>Legacy field syntax</small></span>
                    </button>
                </div>

                <div id="form-builder-panel" class="red-admin-form-workspace-panel" role="tabpanel" aria-labelledby="form-builder-tab" data-form-workspace-panel="builder">
                    <div class="red-admin-form-builder-layout red-admin-form-builder-grid">
                        <aside class="red-admin-form-palette" data-form-palette aria-labelledby="form-palette-title">
                            <div class="red-admin-form-palette__heading"><strong id="form-palette-title">Add a field</strong><span>Drag or click</span></div>
                            <div class="red-admin-form-palette__grid">
                                <button type="button" draggable="true" data-form-add-field="textfield"<?php echo $isLogin ? ' disabled' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14v10H5zM8 11h8"></path></svg><span>Text field</span></button>
                                <button type="button" draggable="true" data-form-add-field="textarea"<?php echo $isLogin ? ' disabled' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v14H5zM8 9h8M8 13h6"></path></svg><span>Long text</span></button>
                                <button type="button" draggable="true" data-form-add-field="select"<?php echo $isLogin ? ' disabled' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14v10H5zM15 11l2 2 2-2"></path></svg><span>Dropdown</span></button>
                                <button type="button" draggable="true" data-form-add-field="radio"<?php echo $isLogin ? ' disabled' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="7" cy="8" r="2"></circle><circle cx="7" cy="16" r="2"></circle><path d="M11 8h8M11 16h8"></path></svg><span>Radio group</span></button>
                                <button type="button" draggable="true" data-form-add-field="checkbox"<?php echo $isLogin ? ' disabled' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="5" height="5" rx="1"></rect><path d="M6.5 7.5l1 1 2-2M13 7.5h6"></path><rect x="5" y="14" width="5" height="5" rx="1"></rect><path d="M13 16.5h6"></path></svg><span>Checkboxes</span></button>
                                <button type="button" draggable="true" data-form-add-field="paragraph"<?php echo $isLogin ? ' disabled' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M5 11h14M5 15h9M5 19h7"></path></svg><span>Help text</span></button>
                                <button type="button" draggable="true" data-form-add-field="hidden"<?php echo $isLogin ? ' disabled' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12s3.5-5 9-5 9 5 9 5-3.5 5-9 5-9-5-9-5z"></path><path d="M4 4l16 16"></path></svg><span>Hidden value</span></button>
                                <?php if ($isLogin) { ?><button type="button" draggable="false" data-form-add-field="password" disabled aria-disabled="true" title="Protected login field"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 018 0v3M12 14v2"></path></svg><span>Password · protected</span></button><?php } ?>
                            </div>
                        </aside>

                        <div class="red-admin-form-canvas" data-form-drop-zone>
                            <div class="red-admin-form-canvas__heading"><div><strong>Form canvas</strong><span>Fields appear publicly in this order.</span></div><span>Drop fields here</span></div>
                            <div class="red-admin-form-empty" data-form-empty<?php echo $definitionCount > 0 ? ' hidden' : ''; ?>>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h5"></path><path d="M16 15v5M13.5 17.5h5"></path></svg>
                                <strong>Start building your form</strong>
                                <span>Drag a field from the palette or select one to add it.</span>
                            </div>
                            <ol class="red-admin-form-field-list" data-form-field-list aria-label="Form fields"></ol>
                        </div>

                        <aside class="red-admin-form-inspector" data-form-field-inspector hidden aria-live="polite"></aside>
                    </div>
                </div>

                <div id="form-source-panel" class="red-admin-form-workspace-panel red-admin-form-source-panel" role="tabpanel" aria-labelledby="form-source-tab" data-form-workspace-panel="source" hidden>
                    <div class="red-admin-form-source-toolbar">
                        <span><strong>Field definition</strong> Exact stored source</span>
                        <button type="button" data-form-copy-source><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V5H5v11h3"></path></svg><span data-copy-label>Copy source</span></button>
                    </div>
                    <label class="red-admin-visually-hidden" for="form-definition-source">Form field definition source</label>
                    <textarea name="LongDesc" id="form-definition-source" rows="18" spellcheck="false" autocapitalize="off" autocomplete="off" data-form-definition-source<?php echo $sourceReadOnly ? ' readonly aria-readonly="true"' : ''; ?>><?php echo red_admin_area_html($context['definition']); ?></textarea>
                    <div class="red-admin-form-source-footer"><span><?php echo $sourceReadOnly ? 'View-only protected definition' : 'Advanced: changes here update the visual builder'; ?></span><span data-form-source-stats><?php echo strlen((string) $context['definition']); ?> characters</span></div>
                    <div class="red-admin-form-source-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v5c0 5-3.4 8.2-8 10-4.6-1.8-8-5-8-10V6z"></path><path d="M12 8v5M12 16h.01"></path></svg><p><strong>Compatibility source.</strong> Keep field names unique and avoid changing a stored registration field key. The visual builder is recommended for everyday editing.</p></div>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-form-delivery-panel" data-form-types="Contact Response Register"<?php echo in_array($formType, ['Contact', 'Response', 'Register'], true) ? '' : ' hidden'; ?> aria-labelledby="form-delivery-title">
                <div class="red-admin-article-panel__heading"><div><span class="red-admin-article-panel__step">04</span><h3 id="form-delivery-title">Delivery settings</h3></div><p>Email routing</p></div>
                <div class="red-admin-field-grid red-admin-form-delivery-grid">
                    <div class="red-admin-field red-admin-field--wide"><label for="form-email-subject">Email subject</label><input name="Subject" type="text" id="form-email-subject" value="<?php echo red_admin_area_html($context['subject']); ?>" autocomplete="off" data-form-conditional-control<?php echo $usesDelivery ? '' : ' disabled'; ?> /><span class="red-admin-field__help">A clear subject helps recipients recognize submissions.</span></div>
                    <div class="red-admin-field"><label for="form-email-from">From <span aria-hidden="true">*</span></label><input name="Submitter" type="text" id="form-email-from" value="<?php echo red_admin_area_html($context['submitter']); ?>" inputmode="email" autocomplete="off" placeholder="sender@example.com, Site name" required aria-required="true" data-form-conditional-control<?php echo $usesDelivery ? '' : ' disabled'; ?> /><span class="red-admin-field__help">Required sender address, optionally followed by a display name.</span></div>
                    <div class="red-admin-field"><label for="form-email-to">To <span aria-hidden="true">*</span></label><input name="Destinatary" type="text" id="form-email-to" value="<?php echo red_admin_area_html($context['destinatary']); ?>" inputmode="email" autocomplete="off" placeholder="inbox@example.com, Team" required aria-required="true" data-form-conditional-control<?php echo $usesDelivery ? '' : ' disabled'; ?> /><span class="red-admin-field__help">At least one valid recipient is required.</span></div>
                    <div class="red-admin-field"><label for="form-email-cc">CC</label><input name="CC" type="text" id="form-email-cc" value="<?php echo red_admin_area_html($context['cc']); ?>" inputmode="email" autocomplete="off" data-form-conditional-control<?php echo $usesDelivery ? '' : ' disabled'; ?> /></div>
                    <div class="red-admin-field"><label for="form-email-bcc">BCC</label><input name="BCC" type="text" id="form-email-bcc" value="<?php echo red_admin_area_html($context['bcc']); ?>" inputmode="email" autocomplete="off" data-form-conditional-control<?php echo $usesDelivery ? '' : ' disabled'; ?> /></div>
                </div>
            </section>

            <section class="red-admin-article-panel red-admin-form-response-panel" data-form-types="Response Register"<?php echo in_array($formType, ['Response', 'Register'], true) ? '' : ' hidden'; ?> aria-labelledby="form-response-title">
                <div class="red-admin-article-panel__heading"><div><span class="red-admin-article-panel__step">05</span><h3 id="form-response-title">Submission response</h3></div><p>Advanced trusted HTML</p></div>
                <div class="red-admin-form-response-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v5c0 5-3.4 8.2-8 10-4.6-1.8-8-5-8-10V6z"></path><path d="M9 12l2 2 4-5"></path></svg><p>This source is returned after a successful submission. Keep existing integrations intact and only paste markup from a source you trust.</p></div>
                <label class="red-admin-visually-hidden" for="form-response-source">Submission response HTML</label>
                <textarea name="Response" id="form-response-source" rows="12" spellcheck="false" autocapitalize="off" autocomplete="off" data-form-response-source data-form-conditional-control<?php echo $usesResponse ? '' : ' disabled'; ?>><?php echo red_admin_area_html($context['response']); ?></textarea>
            </section>

            <section class="red-admin-article-panel red-admin-form-storage-panel" data-form-types="Register"<?php echo $formType === 'Register' ? '' : ' hidden'; ?> aria-labelledby="form-storage-title">
                <div class="red-admin-article-panel__heading"><div><span class="red-admin-article-panel__step">06</span><h3 id="form-storage-title">Managed registration storage</h3></div><span class="red-admin-form-outcome">System managed</span></div>
                <div class="red-admin-form-storage-card"><svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="6" rx="7" ry="3"></ellipse><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"></path></svg><div><strong>Storage identifier</strong><code data-form-table-name><?php echo red_admin_area_html($context['tableName']); ?></code><p>The system owns this identifier. Administrators cannot rename it, which protects stored submissions and database safety.</p></div></div>
                <input type="hidden" name="TableName" value="<?php echo red_admin_area_html($context['tableName']); ?>" data-form-conditional-control<?php echo $usesRegistration ? '' : ' disabled'; ?> />
            </section>

            <section class="red-admin-article-panel red-admin-form-login-panel" data-form-types="Login"<?php echo $formType === 'Login' ? '' : ' hidden'; ?> aria-labelledby="form-login-title">
                <div class="red-admin-article-panel__heading"><div><span class="red-admin-article-panel__step">04</span><h3 id="form-login-title">Administrator sign-in contract</h3></div><span class="red-admin-form-outcome">Protected</span></div>
                <div class="red-admin-form-login-card"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 018 0v3M12 14v2"></path></svg><div><strong>Endpoint and credentials stay system-owned</strong><p>You can update the form title, visibility, placement, and optional presentation settings. The username, password, and submit field contract remains locked.</p></div></div>
            </section>

            <details class="red-admin-article-advanced" data-form-advanced>
                <summary>
                    <span class="red-admin-article-advanced__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"></path></svg></span>
                    <span class="red-admin-article-advanced__copy"><strong>Optional settings</strong><small>Publishing schedule, location, metadata, and supporting images</small></span>
                    <span class="red-admin-article-advanced__badge">Advanced</span>
                    <svg class="red-admin-article-advanced__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5"></path></svg>
                </summary>
                <div class="red-admin-article-advanced__body">
                    <section class="red-admin-optional-card" aria-labelledby="form-publishing-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect><path d="M7.5 3.5v4M16.5 3.5v4M3.5 9.5h17"></path></svg></span><div><h4 id="form-publishing-title">Publishing &amp; metadata</h4><p>Schedule availability and manage administrator-friendly identity values.</p></div></div>
                        <?php if ($isEdit) { ?>
                            <div class="red-admin-field-grid red-admin-form-identity-grid"><div class="red-admin-field"><label for="form-alias">URL alias</label><input name="Alias" type="text" id="form-alias" value="<?php echo red_admin_area_html($context['alias']); ?>" autocomplete="off" /><span class="red-admin-field__help">Keep this stable when the form is already in use.</span></div><div class="red-admin-field"><label for="form-tags">SEO tags</label><input name="Tags" type="text" id="form-tags" value="<?php echo red_admin_area_html($context['tags']); ?>" autocomplete="off" /></div></div>
                        <?php } else { ?>
                            <p class="red-admin-form-auto-note">The alias will be generated automatically from the title. SEO tags can be refined after the form is created.</p>
                        <?php } ?>
                        <div class="red-admin-field-grid red-admin-field-grid--dates">
                            <div class="red-admin-field"><label for="form-start-date">Start date</label><?php if ($isEdit) { ?><input type="date" id="form-start-date" value="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" data-form-date="start" data-original-date="<?php echo red_admin_area_html($context['startDateMeta']['display']); ?>" /><input name="StartDate" type="hidden" value="" data-date-payload disabled /><?php } else { ?><input name="StartDate" type="date" id="form-start-date" data-form-date="start" /><?php } ?><span class="red-admin-field__help">Leave blank to publish immediately.</span></div>
                            <div class="red-admin-field"><label for="form-expiration-date">Expiration date</label><?php if ($isEdit) { ?><input type="date" id="form-expiration-date" value="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" data-form-date="expiration" data-original-date="<?php echo red_admin_area_html($context['expirationDateMeta']['display']); ?>" /><input name="ExpDate" type="hidden" value="" data-date-payload disabled /><?php } else { ?><input name="ExpDate" type="date" id="form-expiration-date" data-form-date="expiration" /><?php } ?><span class="red-admin-field__help">Leave blank to keep the form available.</span></div>
                        </div>
                    </section>

                    <?php echo red_admin_seo_fields_html($context['seoValues'], 'form-seo'); ?>

                    <section class="red-admin-optional-card" aria-labelledby="form-location-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon red-admin-optional-card__icon--location" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"></path><circle cx="12" cy="10" r="2"></circle></svg></span><div><h4 id="form-location-title">Content location</h4><p>Connect the form to the relevant site hierarchy.</p></div></div>
                        <div class="red-admin-field-grid red-admin-field-grid--location"><div class="red-admin-field"><label for="form-section">Section</label><select name="Sections" id="form-section"><option value="">No section</option><?php echo $context['sectionOptions']; ?></select></div><div class="red-admin-field"><label for="form-category">Category</label><select name="Categories" id="form-category"><option value="">No category</option><?php echo $context['categoryOptions']; ?></select></div><div class="red-admin-field"><label for="form-subcategory">Subcategory</label><select name="SubCategories" id="form-subcategory"><option value="">No subcategory</option><?php echo $context['subCategoryOptions']; ?></select></div><div class="red-admin-field"><label for="form-parent">Parent article</label><select name="Article" id="form-parent"><option value="">No parent article</option><?php echo $context['articleOptions']; ?></select></div></div>
                    </section>

                    <section class="red-admin-optional-card" aria-labelledby="form-images-title">
                        <div class="red-admin-optional-card__heading"><span class="red-admin-optional-card__icon red-admin-optional-card__icon--media" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect><circle cx="9" cy="9" r="1.5"></circle><path d="M5.5 17l4.5-4 3 2.5 2.5-2 3 3.5"></path></svg></span><div><h4 id="form-images-title">Supporting images</h4><p>Optional alternatives used by feature areas and content lists.</p></div></div>
                        <div class="red-admin-upload-grid red-admin-form-supporting-grid">
                            <?php foreach ($supportingImages as $supportingImage) { ?>
                                <article class="red-admin-upload-card" data-form-upload data-upload-field="<?php echo red_admin_area_html($supportingImage['field']); ?>" data-upload-url="<?php echo red_admin_area_html($supportingImage['uploadUrl']); ?>" aria-busy="false">
                                    <div class="red-admin-upload-card__heading"><strong><?php echo red_admin_area_html($supportingImage['title']); ?></strong><span><?php echo red_admin_area_html($supportingImage['description']); ?></span></div>
                                    <input type="hidden" name="<?php echo red_admin_area_html($supportingImage['field']); ?>" value="<?php echo red_admin_area_html($supportingImage['current']); ?>" data-upload-value />
                                    <div class="red-admin-upload-current" data-current-media<?php echo $supportingImage['current'] === '' ? ' hidden' : ''; ?>><img<?php echo $supportingImage['current'] === '' ? '' : ' src="/images/resize.php?w=180&amp;h=110&amp;img=/images/articles/'.rawurlencode($supportingImage['current']).'"'; ?> alt="Current <?php echo red_admin_area_html(strtolower($supportingImage['title'])); ?>" data-current-image /><div class="red-admin-upload-current__meta"><span>Current image</span><strong data-current-name><?php echo red_admin_area_html($supportingImage['current']); ?></strong></div><label class="red-admin-upload-remove"><input name="Delete_<?php echo red_admin_area_html($supportingImage['field']); ?>" type="checkbox" value="Y" data-form-remove-image /><span>Remove when saved</span></label></div>
                                    <input class="red-admin-visually-hidden" type="file" id="<?php echo red_admin_area_html($supportingImage['inputId']); ?>" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" tabindex="-1" aria-hidden="true" data-upload-input />
                                    <div class="red-admin-upload-dropzone" data-upload-dropzone><svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path></svg><strong><?php echo $isEdit ? 'Drop replacement here' : 'Drop image here'; ?></strong><span>or choose one from your computer</span><button type="button" class="red-admin-upload-browse" data-upload-browse><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>Browse computer</button><small>JPG, PNG or GIF · maximum 2 MB</small></div>
                                    <div class="red-admin-upload-preview" data-upload-preview hidden><img src="" alt="" data-upload-preview-image /><div><strong data-upload-file-name></strong><span data-upload-status role="status" aria-live="polite">Ready to upload</span></div></div>
                                    <div class="red-admin-upload-progress" aria-hidden="true"><span data-upload-progress></span></div>
                                    <?php if ($supportingImage['field'] === 'SmallPict') { ?><div class="red-admin-field red-admin-upload-card__alignment"><label for="form-summary-alignment">Image alignment</label><select name="SmallPictAlign" id="form-summary-alignment"><option value=""<?php echo $context['smallPictAlign'] === '' ? ' selected="selected"' : ''; ?>>Theme default</option><option value="Top"<?php echo $context['smallPictAlign'] === 'Top' ? ' selected="selected"' : ''; ?>>Top</option><option value="Left"<?php echo $context['smallPictAlign'] === 'Left' ? ' selected="selected"' : ''; ?>>Left</option><option value="Right"<?php echo $context['smallPictAlign'] === 'Right' ? ' selected="selected"' : ''; ?>>Right</option></select></div><?php } ?>
                                </article>
                            <?php } ?>
                        </div>
                    </section>
                </div>
            </details>

            <input type="hidden" name="ShortDesc" value="<?php echo red_admin_area_html($context['shortDesc']); ?>" />
            <input type="hidden" name="ArtRecordID" id="ArtRecordID" value="<?php echo (int) $context['artRecordId']; ?>" />
            <input type="hidden" name="RecordID" id="RecordID" value="<?php echo (int) $context['recordId']; ?>" />
            <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_area_html($context['editedBy']); ?>" />
            <?php if (!$isEdit) { ?>
                <?php if ((string) $context['alias'] !== '') { ?><input type="hidden" name="Alias" value="<?php echo red_admin_area_html($context['alias']); ?>" /><?php } ?>
                <input type="hidden" name="Language" id="Language" value="<?php echo red_admin_area_html($context['language']); ?>" />
                <input type="hidden" name="Component" id="Component" value="Form" />
                <input type="hidden" name="Layout" id="Layout" value="<?php echo red_admin_area_html($context['layout']); ?>" />
            <?php } ?>
            <input type="hidden" name="csrf_token" value="<?php echo red_admin_area_html($context['csrfToken']); ?>" />

            <?php if ($isEdit) { red_admin_content_revision_panel((int) $context['artRecordId']); } ?>

            <div class="red-admin-article-actions<?php echo $isEdit ? ' red-admin-article-actions--edit' : ''; ?>">
                <span id="<?php echo red_admin_area_html($messageId); ?>" class="red-admin-article-message" data-form-message role="status" aria-live="polite" hidden></span>
                <?php if ($isEdit) { ?><button type="button" class="red-admin-article-delete" data-form-delete><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"></path></svg>Delete form</button><?php } ?>
                <button type="submit" name="submit" value="Save" id="save" class="red-admin-article-save" data-form-save data-default-label="<?php echo red_admin_area_html($saveLabel); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5z"></path><path d="M8 4v6h8V4M8 20v-6h8v6"></path></svg><span data-save-label><?php echo red_admin_area_html($saveLabel); ?></span></button>
            </div>
        </div>
    </fieldset>
</form>
<script src="<?php echo red_admin_area_html($formScript); ?>?v=<?php echo rawurlencode((string) $formScriptVersion); ?>"></script>
        <?php
    }
}
