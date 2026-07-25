<?php
/**
 * Dependency-free contracts for the administrator Form Builder workspace.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $root;
require_once $root.'/includes/admin_form_ui_helpers.php';

$assertions = 0;
$assert = static function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: '.$message);
    }
};

try {
    $assert(
        red_admin_form_registration_table_name('RED_Admin', 2147000920) === 'RED_Register_2147000920',
        'registration table name is derived and cannot target a posted core table'
    );

    $registerInsert = ['FormType' => 'Register'];
    $assert(
        red_admin_form_apply_table_name(['TableName' => 'RED_Admin'], 2147000920, $registerInsert, 'insert')
            && ($registerInsert['TableName'] ?? '') === 'RED_Register_2147000920',
        'Register insert ignores the legacy TableName request value'
    );

    $registerUpdate = ['FormType' => 'Register'];
    $assert(
        red_admin_form_apply_table_name(['TableName' => 'RED_Admin'], 2147000920, $registerUpdate, 'update')
            && !array_key_exists('TableName', $registerUpdate),
        'Register update preserves the stored legacy table identifier'
    );

    $contactInsert = ['FormType' => 'Contact', 'TableName' => 'unexpected'];
    $assert(
        red_admin_form_apply_table_name(['TableName' => 'RED_Admin'], 2147000920, $contactInsert, 'insert')
            && $contactInsert['TableName'] === '',
        'non-registration forms cannot receive a storage table'
    );

    $safeDefinition = '#|question=|name=email|type=textfield|required=true|displayname=Email|inputtype=email|initialvalue=;'
        .'#|question=|name=Submit|type=button|displayname=Send';
    $passwordDefinition = '#|question=|name=password|type=password|required=true|displayname=Password|initialvalue=;'
        .'#|question=|name=Submit|type=button|displayname=Register';
    $assert(!red_admin_form_definition_has_password($safeDefinition), 'ordinary definitions remain allowed');
    $assert(red_admin_form_definition_has_password($passwordDefinition), 'registration password fields are detectable');
    $assert(red_admin_form_schema_is_locked('Register'), 'saved Register field schema is locked');
    $assert(red_admin_form_schema_is_locked('Login'), 'Login field contract is locked');
    $assert(!red_admin_form_schema_is_locked('Contact'), 'Contact fields remain editable');

    $invalidCreationDefinition = '#|name=secret|type=password|required=true|displayname=Secret';
    $safeResponsePreset = red_admin_form_ui_creation_definition('Response', $invalidCreationDefinition);
    $assert(
        $safeResponsePreset === red_admin_form_ui_default_definition('Response'),
        'invalid legacy Response templates fall back to the safe creation preset'
    );
    $assert(
        count(red_public_contact_compile_fields($safeResponsePreset)) === 6,
        'the safe Response creation preset compiles into six supported input fields'
    );
    $assert(
        red_admin_form_ui_creation_definition('Response', $safeDefinition) === $safeDefinition,
        'a valid authorized Response template remains byte-preserved on create'
    );

    $safeContactData = [
        'FormType' => 'Contact',
        'LongDesc' => $safeDefinition,
        'Subject' => 'Website inquiry',
        'Submitter' => 'sender@example.com, Website',
        'Destinatary' => 'team@example.com, Team',
        'CC' => '',
        'BCC' => '',
    ];
    $assert(red_admin_form_data_is_safe($safeContactData), 'valid operational Form data passes the write boundary');
    $unsafePasswordData = $safeContactData;
    $unsafePasswordData['FormType'] = 'Response';
    $unsafePasswordData['LongDesc'] = $passwordDefinition;
    $assert(!red_admin_form_data_is_safe($unsafePasswordData), 'password fields fail for every non-Login form');
    $missingRecipientData = $safeContactData;
    $missingRecipientData['Destinatary'] = '';
    $assert(!red_admin_form_data_is_safe($missingRecipientData), 'operational forms require a valid recipient');

    $rawDefinition = "#|question=How? |name=message|type=textarea|required=false|displayname=Message|initialvalue=<safe>;\r\n"
        .'#|question=|name=Submit|type=button|displayname=Send';
    $rawResponse = '<section data-result="ok"><strong>$message</strong></section>';
    $baseContext = [
        'mode' => 'create',
        'formType' => 'Contact',
        'typeOptions' => ['Contact' => 'Contact / inquiry', 'Response' => 'Email + response'],
        'typePresets' => [
            'Contact' => ['definition' => $rawDefinition, 'response' => ''],
            'Response' => ['definition' => $safeDefinition, 'response' => $rawResponse],
        ],
        'definition' => $rawDefinition,
        'response' => $rawResponse,
        'positionOptions' => [1 => 'Primary'],
        'varPosition' => 'PagePosition',
        'recordId' => 2147000921,
        'artRecordId' => 2147000920,
        'language' => 'en',
        'layout' => 'index',
        'csrfToken' => 'test-token',
    ];

    ob_start();
    red_admin_render_form_workspace($baseContext);
    $createMarkup = ob_get_clean();
    $assert(is_string($createMarkup) && $createMarkup !== '', 'create workspace renders');
    $assert(strpos($createMarkup, 'id="insert_form"') !== false, 'insert form id remains exact');
    $assert(strpos($createMarkup, 'name="FormType"') !== false, 'create purpose posts the legacy FormType');
    $assert(strpos($createMarkup, 'data-red-form-builder') !== false, 'builder enhancement hook renders');
    $assert(strpos($createMarkup, 'data-form-type-presets') !== false, 'authorized subtype presets are embedded');
    $assert(
        strpos($createMarkup, htmlspecialchars($rawDefinition, ENT_QUOTES, 'UTF-8')) !== false,
        'legacy definition bytes reach the canonical source textarea without normalization'
    );
    $assert(strpos($createMarkup, 'name="LongDesc"') !== false, 'LongDesc remains the authoritative wire field');
    $assert(
        preg_match('/id="form-email-from"[^>]*required/', $createMarkup) === 1
            && preg_match('/id="form-email-to"[^>]*required/', $createMarkup) === 1,
        'operational delivery sender and recipient are visibly required'
    );
    $assert(
        preg_match('/name="TableName"[^>]*disabled/', $createMarkup) === 1,
        'Contact UI disables the legacy storage-table wire field'
    );

    $registerContext = $baseContext;
    $registerContext['mode'] = 'edit';
    $registerContext['formType'] = 'Register';
    $registerContext['typeOptions'] = ['Register' => 'Registration & storage'];
    $registerContext['schemaLocked'] = true;
    $registerContext['tableName'] = 'RED_Register_2147000920';
    ob_start();
    red_admin_render_form_workspace($registerContext);
    $registerMarkup = ob_get_clean();
    $assert(strpos($registerMarkup, 'id="update_form"') !== false, 'update form id remains exact');
    $assert(strpos($registerMarkup, 'data-register-schema-locked="true"') !== false, 'Register schema lock is explicit');
    $assert(strpos($registerMarkup, 'readonly aria-readonly="true"') !== false, 'locked definition source is view-only');
    $assert(strpos($registerMarkup, '<code data-form-table-name>RED_Register_2147000920</code>') !== false, 'managed identifier is explanatory text');
    $assert(strpos($registerMarkup, 'type="text" name="TableName"') === false, 'no editable registration table-name control renders');

    $loginContext = $baseContext;
    $loginContext['mode'] = 'edit';
    $loginContext['formType'] = 'Login';
    $loginContext['typeOptions'] = ['Login' => 'Administrator login'];
    $loginContext['schemaLocked'] = true;
    ob_start();
    red_admin_render_form_workspace($loginContext);
    $loginMarkup = ob_get_clean();
    $assert(strpos($loginMarkup, 'Administrator sign-in contract') !== false, 'Login uses the protected workspace');
    $assert(strpos($loginMarkup, 'data-form-schema-locked="true"') !== false, 'Login definition is locked');

    $menuSource = file_get_contents($root.'/admin/class/class_add_menu.php');
    $builderSource = file_get_contents($root.'/admin/assets/js/form-builder.js');
    $cssSource = file_get_contents($root.'/admin/assets/css/cp.css');
    $insertSource = file_get_contents($root.'/admin/bin/insert_form.php');
    $updateSource = file_get_contents($root.'/admin/bin/update_form.php');
    $newFormSource = file_get_contents($root.'/admin/bin/new_form.php');
    $adminHelperSource = file_get_contents($root.'/includes/admin_form_helpers.php');
    foreach ([$menuSource, $builderSource, $cssSource, $insertSource, $updateSource, $newFormSource, $adminHelperSource] as $source) {
        $assert(is_string($source), 'Form Builder contract source is readable');
    }
    $assert(strpos($menuSource, "'Form Builder'") !== false, 'Add Content exposes the grouped Form Builder');
    $assert(strpos($menuSource, "'Admin Login'") !== false, 'Add Content keeps Admin Login separate');
    $assert(strpos($menuSource, "'form-builder'") !== false, 'grouped Form card has a stable key');
    $assert(strpos($builderSource, '[data-red-form-builder]') !== false, 'builder initializes from its scoped root');
    $assert(strpos($builderSource, 'dragstart') !== false && strpos($builderSource, 'drop') !== false, 'pointer drag and drop is implemented');
    $assert(strpos($builderSource, 'moveField') !== false, 'keyboard-visible field movement is implemented');
    $assert(strpos($builderSource, 'data-form-definition-source') !== false, 'builder synchronizes the canonical definition source');
    $assert(strpos($builderSource, 'PASSTHROUGH_ATTRIBUTES') !== false, 'visual edits preserve advanced legacy field attributes');
    $assert(strpos($builderSource, 'form.checkValidity') !== false, 'save reports required delivery fields before the request');
    $assert(
        strpos($builderSource, "data.type = cleanText(data.type).trim().toLowerCase()") !== false,
        'expert-source field types normalize to the canonical grammar'
    );
    $assert(
        strpos($builderSource, "label: 'Choose an option', value: 'Choose an option', selected: true") !== false,
        'new required dropdowns start with an explicit placeholder before usable choices'
    );
    $assert(
        strpos($builderSource, "option.selected && field.type !== 'checkbox'") !== false,
        'checkbox groups permit more than one default choice'
    );
    $assert(
        strpos($builderSource, 'function safeOptionValue') !== false
            && strpos($builderSource, ".replace(/,/g, '，')") !== false
            && strpos($builderSource, 'Choice punctuation was converted to full-width characters') !== false
            && strpos($builderSource, 'result = label || value') !== false
            && strpos($builderSource, "optionProperty === 'label' && field.type === 'select'") !== false,
        'visual choices visibly normalize delimiter punctuation and keep dropdown labels round-trippable'
    );
    $assert(
        strpos($builderSource, 'Expert source contains a field type this form cannot safely publish.') !== false,
        'operational expert source reports unsupported field types before save'
    );
    $assert(strpos($builderSource, 'eval(') === false, 'builder never evaluates source text');
    $assert(strpos($cssSource, '.red-admin-form-builder-layout') !== false, 'three-part builder workspace is styled');
    $assert(strpos($cssSource, '.red-admin-add-card--form-builder') !== false, 'grouped Add Content card is styled');
    $assert(strpos($insertSource, 'red_admin_form_data_is_safe') !== false, 'all public form definitions fail closed at the insert boundary');
    $assert(strpos($insertSource, 'red_admin_form_record_exists') !== false, 'creation refuses an existing Form record collision');
    $assert(
        strpos($insertSource, 'red_admin_form_registration_table_exists') !== false
            && strpos($insertSource, 'red_admin_form_drop_registration_table') !== false,
        'Register creation refuses table collisions and cleans up only its failed-request table'
    );
    $assert(
        strpos($adminHelperSource, 'CREATE TABLE IF NOT EXISTS') === false
            && strpos($adminHelperSource, "'textfield' => 'text'") !== false
            && strpos($adminHelperSource, "'textarea' => 'mediumtext'") !== false
            && strpos($adminHelperSource, 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci') !== false
            && strpos($adminHelperSource, "strtolower(trim(red_admin_text(\$field['type'] ?? '')))") !== false,
        'managed Register tables fail on collision, normalize field types, and use safe modern storage'
    );
    $assert(
        strpos($newFormSource, 'red_admin_form_ui_creation_definition') !== false
            && strpos($newFormSource, "'response' => (string) (\$presetTemplate['ResponseTemplate'] ?? '')") !== false,
        'new Form creation sanitizes field presets while retaining the authorized response template'
    );
    $assert(strpos($updateSource, 'red_admin_form_schema_is_locked') !== false, 'locked schemas are enforced at the write boundary');
    $assert(strpos($updateSource, 'red_admin_form_data_is_safe') !== false, 'effective public Form data is validated on update');

    echo 'Admin Form Builder self-test passed: '.$assertions." assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage()."\n");
    exit(1);
}
