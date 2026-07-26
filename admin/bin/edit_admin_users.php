<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin_user_manager(); ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/config.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_user_helpers.php'; ?>
<?php
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$view = red_admin_user_text($_POST['view'] ?? '');
if (!in_array($view, ['add', 'list', 'user'], true)) {
    $view = '';
}

$recordId = (int) red_admin_user_scalar($_POST['RecordID'] ?? 0);
$currentRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
$csrfToken = red_csrf_token();
$users = [];
$user = null;
$components = [];
$tools = [];

if ($view === 'list') {
    $users = red_admin_user_list($db->connection);
} elseif ($view === 'user') {
    $user = red_admin_user_lookup($db->connection, $recordId);
    if (!$user) {
        $view = 'list';
        $users = red_admin_user_list($db->connection);
    } else {
        $components = red_admin_user_component_permissions(red_admin_user_components($db->connection));
        $tools = red_admin_user_tools($db->connection);
    }
} elseif ($view === 'add') {
    $components = red_admin_user_component_permissions(red_admin_user_components($db->connection));
    $tools = red_admin_user_tools($db->connection);
}

function red_admin_user_permission_options($name, $rows, $selectedIds, $kind)
{
    $selectedIds = red_admin_user_ids($selectedIds);
    foreach ($rows as $row) {
        $permissionId = (int) ($row['RecordID'] ?? 0);
        if ($permissionId <= 0) {
            continue;
        }
        $permissionIds = red_admin_user_ids($row['PermissionIDs'] ?? [$permissionId]);
        if (empty($permissionIds)) {
            $permissionIds = [$permissionId];
        }
        $isChecked = count(array_intersect($permissionIds, $selectedIds)) > 0;
        $checked = $isChecked ? ' checked="checked"' : '';
        if ($kind === 'tool') {
            $label = red_admin_user_text($row['AltContent'] ?? $row['ButtonTag'] ?? $row['UniqueName'] ?? '');
        } else {
            $buttonTag = red_admin_user_text($row['ButtonTag'] ?? '');
            $uniqueName = red_admin_user_text($row['UniqueName'] ?? '');
            $label = $buttonTag !== '' ? $buttonTag : $uniqueName;
        }
        $permissionKey = red_admin_user_text($row['PermissionKey'] ?? $kind . '-' . $permissionId);
        $description = red_admin_user_text($row['PermissionDescription'] ?? '');
        $isGrouped = count($permissionIds) > 1;
        echo '<label class="red-admin-permission' . ($isChecked ? ' is-selected' : '') . '">';
        echo '<span class="red-admin-permission__heading">';
        echo '<input class="red-admin-permission__control" type="checkbox" name="' . red_admin_user_html($name) . '[]" value="' . $permissionIds[0] . '"' . $checked;
        if ($isGrouped) {
            echo ' onchange="redAdminSyncPermissionGroup(this)"';
        }
        echo ' /> ';
        echo '<span class="red-admin-permission__label">' . red_admin_user_html($label) . '</span>';
        echo '</span>';
        if ($description !== '') {
            echo '<span class="red-admin-permission__description">' . red_admin_user_html($description) . '</span>';
        }
        if ($isGrouped) {
            echo '<span class="red-admin-permission__scope">' . count($permissionIds) . ' linked content types</span>';
            foreach (array_slice($permissionIds, 1) as $groupedId) {
                echo '<input type="checkbox" hidden="hidden" tabindex="-1" aria-hidden="true" data-red-permission-member="' . red_admin_user_html($permissionKey) . '" name="' . red_admin_user_html($name) . '[]" value="' . (int) $groupedId . '"' . $checked . ' />';
            }
        } else {
            echo '<small>#' . $permissionId . '</small>';
        }
        echo '</label>';
    }
}
?>
<script type="text/javascript">
function redAdminSyncPermissionGroup(control) {
    var permission = control.closest ? control.closest('.red-admin-permission') : null;
    if (!permission) {
        return;
    }
    var members = permission.querySelectorAll('[data-red-permission-member]');
    for (var index = 0; index < members.length; index += 1) {
        members[index].checked = control.checked;
    }
    if (permission.classList) {
        permission.classList.toggle('is-selected', control.checked);
    }
}

function redAdminUserStatus(message, isError) {
    $('#red-admin-user-status')
        .removeClass('red-admin-user-error red-admin-user-success')
        .addClass(isError ? 'red-admin-user-error' : 'red-admin-user-success')
        .text(message)
        .show();
}

function redAdminUserResultMessage(status) {
    var messages = {
        duplicate: 'That username already exists.',
        duplicate_email: 'That email address is already assigned to another user.',
        email_required: 'A valid email address is required.',
        weak_password: 'Use a password with at least 12 characters.',
        invalid: 'Please check the required fields and try again.',
        self: 'You cannot delete the account currently in use.',
        self_role: 'Use another Webmaster account to change the current account type.',
        last_manager: 'The final site manager cannot be deleted or changed to Guest.',
        owner_protected: 'The protected Owner cannot be deleted or changed to Guest.',
        csrf: 'Your security token expired. Reload the page and try again.'
    };
    return messages[status] || 'The administrator account could not be saved.';
}

function redLoadAdministratorUserView(view, recordId, message) {
    $.ajax({
        type: 'POST',
        url: '/admin/bin/edit_admin_users.php',
        data: {view: view || '', RecordID: recordId || 0},
        success: function(data) {
            $('#msggbox_edit_advanced').html(data).show();
            if (message) {
                redAdminUserStatus(message, false);
            }
        }
    });
    return false;
}

function redSubmitAdministratorUser(form, successMessage, successView) {
    $.ajax({
        type: 'POST',
        url: '/admin/bin/update_admin_users.php',
        data: $(form).serialize(),
        success: function(data) {
            var status = $.trim(data);
            if (status === 'yes') {
                redLoadAdministratorUserView(successView || 'list', 0, successMessage);
            } else if (status === 'reauth') {
                window.alert('Password updated. Please sign in again.');
                window.location.reload();
            } else {
                redAdminUserStatus(redAdminUserResultMessage(status), true);
            }
        }
    });
    return false;
}

function redDeleteAdministratorUser(recordId, username) {
    if (!window.confirm('Delete administrator "' + username + '"? This cannot be undone.')) {
        return false;
    }
    $.ajax({
        type: 'POST',
        url: '/admin/bin/update_admin_users.php',
        data: {
            action: 'delete',
            RecordID: recordId,
            csrf_token: <?php echo json_encode($csrfToken); ?>
        },
        success: function(data) {
            var status = $.trim(data);
            if (status === 'yes') {
                redLoadAdministratorUserView('list', 0, 'Administrator deleted.');
            } else {
                redAdminUserStatus(redAdminUserResultMessage(status), true);
            }
        }
    });
    return false;
}
</script>
<style>
#advanced .red-admin-users-return {
    box-sizing: border-box;
    width: 100%;
    height: auto;
    margin: 0;
    padding: 8px 10px;
    background: #edf2f5;
}
#advanced .red-admin-users-return .viewall {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 28px;
    padding: 0 9px;
    border: 1px solid #ccd6df;
    border-radius: 7px;
    background: #fff;
    color: #4e6273;
    font: 700 10px/1 Arial, Helvetica, sans-serif;
    text-decoration: none;
}
#advanced .red-admin-users-return .viewall:hover {
    border-color: #9eb0c0;
    color: #263746;
}
#advanced .red-admin-users,
#advanced .red-admin-users * {
    box-sizing: border-box;
}
#advanced .red-admin-users {
    width: 100%;
    margin: 0;
    padding: 10px;
    background: #eaf0f4;
    color: #1d2935;
    font-family: Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users .wrapper,
#advanced .red-admin-users article,
#advanced .red-admin-users__surface {
    width: 100%;
    margin: 0;
}
#advanced .red-admin-users article.cp_admin {
    border: 0;
    border-radius: 11px;
    background: transparent;
}
#advanced .red-admin-users__surface {
    padding: 0;
}
#advanced .red-admin-users__header {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    margin: 0 0 10px;
    padding: 14px 16px;
    border: 1px solid #384958;
    border-radius: 11px;
    background: linear-gradient(135deg, #202d39 0%, #2a3c4d 100%);
    box-shadow: 0 5px 16px rgba(16, 30, 43, .13);
    color: #f8fafc;
}
#advanced .red-admin-users__header-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border: 1px solid rgba(111, 215, 200, .32);
    border-radius: 10px;
    background: rgba(36, 135, 126, .22);
    color: #92e0d5;
}
#advanced .red-admin-users svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}
#advanced .red-admin-users__eyebrow {
    display: block;
    margin: 0 0 2px;
    color: #9eddd5;
    font: 800 9px/1.2 Arial, Helvetica, sans-serif;
    letter-spacing: .11em;
    text-transform: uppercase;
}
#advanced .red-admin-users h3 {
    margin: 0;
    color: #fff;
    font: 700 18px/1.25 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users__header p {
    margin: 3px 0 0;
    color: #c2ced8;
    font: 400 11px/1.4 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users__badge,
#advanced .red-admin-users__mini-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 5px 9px;
    border-radius: 999px;
    font: 800 9px/1 Arial, Helvetica, sans-serif;
    letter-spacing: .06em;
    text-transform: uppercase;
    white-space: nowrap;
}
#advanced .red-admin-users__badge {
    border: 1px solid rgba(111, 215, 200, .28);
    background: rgba(17, 31, 43, .36);
    color: #cbf4ee;
}
#advanced .red-admin-users__mini-badge {
    border: 1px solid #d2dce5;
    background: #f3f6f8;
    color: #657482;
}
#advanced .red-admin-users .red-admin-note {
    margin: 0;
    color: #657482;
    font: 400 10px/1.45 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users .red-admin-user-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
    margin: 0 0 10px;
}
#advanced .red-admin-users .red-admin-user-option {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 68px;
    margin: 0;
    padding: 10px 12px;
    border: 1px solid #d3dde5;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(26, 44, 61, .045);
    color: #263746;
    text-align: left;
    cursor: pointer;
    transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
}
#advanced .red-admin-users .red-admin-user-option:hover {
    border-color: #96a9b9;
    box-shadow: 0 4px 12px rgba(24, 35, 47, .08);
}
#advanced .red-admin-users .red-admin-user-option.is-active {
    border-color: #24877e;
    background: #f1faf8;
    box-shadow: 0 0 0 1px rgba(36, 135, 126, .12);
}
#advanced .red-admin-users .red-admin-user-option__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: #e8f1f4;
    color: #426275;
}
#advanced .red-admin-users .red-admin-user-option.is-active .red-admin-user-option__icon {
    background: #d8efeb;
    color: #20766e;
}
#advanced .red-admin-users .red-admin-user-option__copy,
#advanced .red-admin-users .red-admin-user-option strong,
#advanced .red-admin-users .red-admin-user-option span {
    display: block;
    min-width: 0;
}
#advanced .red-admin-users .red-admin-user-option strong {
    margin: 0 0 3px;
    color: #263746;
    font: 800 13px/1.2 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users .red-admin-user-option__copy > span {
    color: #687783;
    font: 400 10px/1.35 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users .red-admin-card {
    margin: 0;
    padding: 14px;
    border: 1px solid #d3dde5;
    border-radius: 11px;
    background: #fff;
    box-shadow: 0 2px 9px rgba(26, 44, 61, .055);
}
#advanced .red-admin-users .red-admin-card fieldset {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}
#advanced .red-admin-users .red-admin-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 13px;
    padding: 0 0 11px;
    border-bottom: 1px solid #e3e9ee;
}
#advanced .red-admin-users .red-admin-card__header h4 {
    margin: 2px 0 0;
    color: #1e2c39;
    font: 700 15px/1.25 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users .red-admin-card__eyebrow {
    color: #24877e;
    font: 800 9px/1.2 Arial, Helvetica, sans-serif;
    letter-spacing: .09em;
    text-transform: uppercase;
}
#advanced .red-admin-users h4.red-admin-users__section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 16px 0 9px;
    color: #324353;
    font: 800 11px/1.2 Arial, Helvetica, sans-serif;
    letter-spacing: .025em;
}
#advanced .red-admin-users h4.red-admin-users__section-title::after {
    flex: 1 1 auto;
    height: 1px;
    background: #e5eaee;
    content: "";
}
#advanced .red-admin-users .red-admin-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 11px 12px;
}
#advanced .red-admin-users .red-admin-field {
    display: block;
    min-width: 0;
    margin: 0;
}
#advanced .red-admin-users .red-admin-field > span {
    display: block;
    margin: 0 0 5px;
    color: #344454;
    font: 700 10px/1.25 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users input[type="text"],
#advanced .red-admin-users input[type="password"],
#advanced .red-admin-users input[type="email"],
#advanced .red-admin-users select {
    display: block;
    width: 100%;
    height: 40px;
    min-width: 0;
    margin: 0;
    padding: 8px 10px;
    border: 1px solid #bdc9d3;
    border-radius: 7px;
    background: #fbfcfd;
    box-shadow: inset 0 1px 1px rgba(19, 37, 53, .035);
    color: #202d38;
    font: 400 12px/1.2 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users input:hover,
#advanced .red-admin-users select:hover {
    border-color: #93a7b8;
    background: #fff;
}
#advanced .red-admin-users input:focus,
#advanced .red-admin-users select:focus {
    border-color: #24877e;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(36, 135, 126, .15);
    outline: 0;
}
#advanced .red-admin-users .red-admin-permissions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 9px;
}
#advanced .red-admin-users .red-admin-permission {
    position: relative;
    display: flex;
    min-height: 64px;
    flex-direction: column;
    justify-content: center;
    padding: 11px 12px 10px 42px;
    border: 1px solid #d6dce3;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(24, 35, 47, .05);
    color: #26313d;
    line-height: 1.3;
    cursor: pointer;
    transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
}
#advanced .red-admin-users .red-admin-permission:hover {
    border-color: #91a2b4;
    box-shadow: 0 4px 12px rgba(24, 35, 47, .08);
}
#advanced .red-admin-users .red-admin-permission.is-selected,
#advanced .red-admin-users .red-admin-permission:has(.red-admin-permission__control:checked) {
    border-color: #24877e;
    background: #f2fbf9;
    box-shadow: 0 0 0 1px rgba(36, 135, 126, .12);
}
#advanced .red-admin-users .red-admin-permission__heading {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
}
#advanced .red-admin-users .red-admin-permission__control {
    position: absolute;
    top: 16px;
    left: 14px;
    width: 18px;
    height: 18px;
    margin: 0;
    accent-color: #24877e;
}
#advanced .red-admin-users .red-admin-permission__label {
    color: #26313d;
    font-size: 12px;
}
#advanced .red-admin-users .red-admin-permission__description {
    display: block;
    margin-top: 3px;
    color: #5b6875;
    font-size: 10px;
    line-height: 1.4;
}
#advanced .red-admin-users .red-admin-permission__scope {
    display: inline-flex;
    align-self: flex-start;
    margin-top: 7px;
    padding: 3px 7px;
    border-radius: 999px;
    background: #dcefeb;
    color: #256f69;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}
#advanced .red-admin-users .red-admin-permission small {
    margin-top: 4px;
    color: #7c8792;
    font-size: 8px;
}
#advanced .red-admin-users .red-admin-email-list {
    display: grid;
    gap: 8px;
    margin-top: 11px;
}
#advanced .red-admin-users .red-admin-email-row {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    width: 100%;
    margin: 0;
    padding: 10px;
    border: 1px solid #d6dee5;
    border-radius: 9px;
    background: #fbfcfd;
    color: #263746;
    text-align: left;
    cursor: pointer;
}
#advanced .red-admin-users .red-admin-email-row:hover {
    border-color: #99aaba;
    background: #fff;
    box-shadow: 0 3px 10px rgba(26, 44, 61, .07);
}
#advanced .red-admin-users .red-admin-email-row__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: #e8f1f4;
    color: #426275;
}
#advanced .red-admin-users .red-admin-email-row strong,
#advanced .red-admin-users .red-admin-email-row span {
    min-width: 0;
    color: #263746;
    overflow-wrap: anywhere;
}
#advanced .red-admin-users .red-admin-email-row strong {
    font: 700 11px/1.3 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-users .red-admin-email-row__meta {
    color: #687783;
    font: 400 9px/1.35 Arial, Helvetica, sans-serif;
    text-align: right;
}
#advanced .red-admin-users .red-admin-email-row.is-missing strong {
    color: #a33b34;
}
#advanced .red-admin-users .red-admin-actions {
    position: sticky;
    bottom: 0;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    margin: 16px -14px -14px;
    padding: 10px 14px;
    border-top: 1px solid #d9e1e7;
    border-radius: 0 0 11px 11px;
    background: rgba(247, 250, 252, .96);
    box-shadow: 0 -4px 12px rgba(26, 44, 61, .045);
}
#advanced .red-admin-users .red-admin-actions input,
#advanced .red-admin-users .red-admin-actions button {
    min-height: 36px;
    margin: 0;
    padding: 7px 13px;
    border: 1px solid #1f746c;
    border-radius: 8px;
    background: #24877e;
    color: #fff;
    font: 800 10px/1 Arial, Helvetica, sans-serif;
    letter-spacing: .035em;
    text-transform: uppercase;
    cursor: pointer;
}
#advanced .red-admin-users .red-admin-actions input:hover {
    border-color: #195f59;
    background: #1d756d;
}
#advanced .red-admin-users .red-admin-actions .red-admin-delete {
    border-color: #c7d0d8;
    background: #fff;
    color: #a33b34;
}
#advanced .red-admin-users .red-admin-actions .red-admin-delete:hover {
    border-color: #c9645c;
    background: #fff5f4;
}
#advanced .red-admin-users button:focus-visible,
#advanced .red-admin-users input:focus-visible,
#advanced .red-admin-users select:focus-visible,
#advanced .red-admin-users-return .viewall:focus-visible {
    outline: 3px solid rgba(36, 135, 126, .24);
    outline-offset: 2px;
}
#advanced .red-admin-user-success,
#advanced .red-admin-user-error {
    display: none;
    margin: 0 0 10px;
    padding: 9px 10px;
    border-radius: 8px;
    font: 700 10px/1.35 Arial, Helvetica, sans-serif;
}
#advanced .red-admin-user-success {
    border: 1px solid #b9ddd0;
    background: #eef9f5;
    color: #246d56;
}
#advanced .red-admin-user-error {
    border: 1px solid #ecc7c4;
    background: #fff0f0;
    color: #8a1515;
}
@media (max-width: 700px) {
    #advanced .red-admin-users {
        padding: 8px;
    }
    #advanced .red-admin-users__header {
        grid-template-columns: 38px minmax(0, 1fr);
        padding: 12px;
    }
    #advanced .red-admin-users__header-icon {
        width: 38px;
        height: 38px;
    }
    #advanced .red-admin-users__badge {
        display: none;
    }
    #advanced .red-admin-users__mini-badge {
        display: none;
    }
    #advanced .red-admin-users .red-admin-user-options,
    #advanced .red-admin-users .red-admin-fields,
    #advanced .red-admin-users .red-admin-permissions {
        grid-template-columns: 1fr;
    }
    #advanced .red-admin-users .red-admin-card {
        padding: 12px;
    }
    #advanced .red-admin-users input[type="text"],
    #advanced .red-admin-users input[type="password"],
    #advanced .red-admin-users input[type="email"],
    #advanced .red-admin-users select {
        height: 44px;
    }
    #advanced .red-admin-users .red-admin-email-row {
        grid-template-columns: 34px minmax(0, 1fr);
    }
    #advanced .red-admin-users .red-admin-email-row__meta {
        grid-column: 2;
        text-align: left;
    }
    #advanced .red-admin-users .red-admin-actions {
        margin-right: -12px;
        margin-bottom: -12px;
        margin-left: -12px;
        padding: 9px 12px;
    }
    #advanced .red-admin-users .red-admin-actions input,
    #advanced .red-admin-users .red-admin-actions button {
        min-height: 44px;
    }
}
</style>
<div class="cp_viewall red-admin-users-return"><a href="javascript:;" class="viewall" onclick="showdiv('edit_advanced_grid');"><span aria-hidden="true">←</span> Advanced items</a></div>
<div class="container_12 cp_padtop red-admin-users">
    <div class="wrapper">
        <article class="grid_12 cp_admin red-admin-users__workspace">
            <div class="red-admin-users__surface">
                <header class="red-admin-users__header">
                    <span class="red-admin-users__header-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 20v-1.6a4.4 4.4 0 0 0-4.4-4.4H6.4A4.4 4.4 0 0 0 2 18.4V20"/><circle cx="9" cy="7" r="4"/><path d="M18 8v6M15 11h6"/></svg></span>
                    <div>
                        <span class="red-admin-users__eyebrow">Account access</span>
                        <h3>Administrator Users</h3>
                        <p>Create accounts, assign content permissions, and manage site access.</p>
                    </div>
                    <span class="red-admin-users__badge">Secure roles</span>
                </header>
                <div id="red-admin-user-status" style="display:none"></div>

                <div class="red-admin-user-options" aria-label="Administrator user actions">
                    <button type="button" class="red-admin-user-option<?php echo $view === 'add' ? ' is-active' : ''; ?>" onclick="return redLoadAdministratorUserView('add', 0, '');">
                        <span class="red-admin-user-option__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-5A4.5 4.5 0 0 0 2 18.5V20"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M16 11h6"/></svg></span>
                        <span class="red-admin-user-option__copy"><strong>Add User</strong><span>Create a new administrator account.</span></span>
                    </button>
                    <button type="button" class="red-admin-user-option<?php echo in_array($view, ['list', 'user'], true) ? ' is-active' : ''; ?>" onclick="return redLoadAdministratorUserView('list', 0, '');">
                        <span class="red-admin-user-option__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="4"/><path d="M2 20v-1.5A4.5 4.5 0 0 1 6.5 14H11M15 19l5-5 2 2-5 5-3 .8z"/></svg></span>
                        <span class="red-admin-user-option__copy"><strong>Edit User</strong><span>Find an account and update access.</span></span>
                    </button>
                </div>

                <?php if ($view === 'add'): ?>
                <form id="red-admin-user-create" class="cp red-admin-card" method="post" autocomplete="off" onsubmit="return redSubmitAdministratorUser(this, 'Administrator added.', 'list');">
                    <fieldset>
                        <div class="red-admin-card__header"><div><span class="red-admin-card__eyebrow">New account</span><h4>Add User</h4></div><span class="red-admin-users__mini-badge">Required fields</span></div>
                        <?php echo red_csrf_input(); ?>
                        <input type="hidden" name="action" value="create" />
                        <div class="red-admin-fields">
                            <label class="red-admin-field"><span>Username</span><input type="text" name="Username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" required="required" autocomplete="off" /></label>
                            <label class="red-admin-field"><span>Display name</span><input type="text" name="Alias" maxlength="14" required="required" /></label>
                            <label class="red-admin-field"><span>Password</span><input type="password" name="Password" minlength="12" maxlength="255" required="required" autocomplete="new-password" /></label>
                            <label class="red-admin-field"><span>Email</span><input type="email" name="Email" maxlength="254" required="required" autocomplete="email" /></label>
                            <label class="red-admin-field"><span>Account type</span><select name="AdminType" required="required"><option value="guest">Guest — assigned content and tools</option><option value="webmaster">Webmaster — full site manager</option></select></label>
                        </div>
                        <h4 class="red-admin-users__section-title">Content access</h4>
                        <div class="red-admin-permissions"><?php red_admin_user_permission_options('components', $components, [], 'component'); ?></div>
                        <h4 class="red-admin-users__section-title">Utility tools</h4>
                        <div class="red-admin-permissions"><?php red_admin_user_permission_options('tools', $tools, [], 'tool'); ?></div>
                        <div class="red-admin-actions"><input type="submit" name="submit" value="Add Administrator" id="save" /></div>
                    </fieldset>
                </form>

                <?php elseif ($view === 'list'): ?>
                <div class="red-admin-card">
                    <div class="red-admin-card__header"><div><span class="red-admin-card__eyebrow">Account directory</span><h4>Edit User</h4></div><span class="red-admin-users__mini-badge"><?php echo count($users); ?> accounts</span></div>
                    <p class="red-admin-note">Select an email address to open that account. Legacy accounts without email are marked for repair.</p>
                    <div class="red-admin-email-list">
                    <?php foreach ($users as $listUser):
                        $listRecordId = (int) ($listUser['RecordID'] ?? 0);
                        $email = red_admin_user_text($listUser['Email'] ?? '');
                        $missingClass = $email === '' ? ' is-missing' : '';
                        $emailLabel = $email !== '' ? $email : 'Email required';
                        $identity = red_admin_user_text($listUser['Alias'] ?? '');
                        $username = red_admin_user_text($listUser['Username'] ?? '');
                    ?>
                        <button type="button" class="red-admin-email-row<?php echo $missingClass; ?>" onclick="return redLoadAdministratorUserView('user', <?php echo $listRecordId; ?>, '');">
                            <span class="red-admin-email-row__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0 1 7-7h2a7 7 0 0 1 7 7v1"/></svg></span>
                            <strong><?php echo red_admin_user_html($emailLabel); ?></strong>
                            <span class="red-admin-email-row__meta"><?php echo red_admin_user_html($identity); ?> (<?php echo red_admin_user_html($username); ?>)</span>
                        </button>
                    <?php endforeach; ?>
                    </div>
                </div>

                <?php elseif ($view === 'user' && $user):
                    $userRecordId = (int) ($user['RecordID'] ?? 0);
                    $isCurrent = $userRecordId === $currentRecordId;
                ?>
                <form id="red-admin-user-<?php echo $userRecordId; ?>" class="cp red-admin-card" method="post" autocomplete="off" onsubmit="return redSubmitAdministratorUser(this, 'Administrator updated.', 'list');">
                    <fieldset>
                        <?php echo red_csrf_input(); ?>
                        <input type="hidden" name="action" value="update" />
                        <input type="hidden" name="RecordID" value="<?php echo $userRecordId; ?>" />
                        <div class="red-admin-card__header"><div><span class="red-admin-card__eyebrow">Account profile</span><h4>Edit User: <?php echo red_admin_user_html($user['Username'] ?? ''); ?></h4></div><span class="red-admin-users__mini-badge"><?php echo $isCurrent ? 'Current account' : red_admin_user_html(red_admin_user_type($user['AdminType'] ?? 'guest', 'guest')); ?></span></div>
                        <div class="red-admin-fields">
                            <label class="red-admin-field"><span>Username</span><input type="text" value="<?php echo red_admin_user_html($user['Username'] ?? ''); ?>" disabled="disabled" /></label>
                            <label class="red-admin-field"><span>Display name</span><input type="text" name="Alias" maxlength="14" required="required" value="<?php echo red_admin_user_html($user['Alias'] ?? ''); ?>" /></label>
                            <label class="red-admin-field"><span>Email</span><input type="email" name="Email" maxlength="254" required="required" autocomplete="email" value="<?php echo red_admin_user_html($user['Email'] ?? ''); ?>" /></label>
                            <label class="red-admin-field"><span>New password (leave blank to keep)</span><input type="password" name="Password" minlength="12" maxlength="255" autocomplete="new-password" /></label>
                            <label class="red-admin-field"><span>Account type</span><?php $storedType = red_admin_user_type($user['AdminType'] ?? 'guest', 'guest'); $selectedType = $storedType === 'superadmin' ? 'webmaster' : $storedType; ?><select name="AdminType" required="required"<?php echo $isCurrent ? ' disabled="disabled"' : ''; ?>><option value="guest"<?php echo $selectedType === 'guest' ? ' selected="selected"' : ''; ?>>Guest — assigned content and tools</option><option value="webmaster"<?php echo $selectedType === 'webmaster' ? ' selected="selected"' : ''; ?>>Webmaster — full site manager</option></select><?php if ($isCurrent): ?><input type="hidden" name="AdminType" value="<?php echo red_admin_user_html($storedType); ?>" /><?php endif; ?></label>
                        </div>
                        <h4 class="red-admin-users__section-title">Content access</h4>
                        <div class="red-admin-permissions"><?php red_admin_user_permission_options('components', $components, $user['AdminComponents'] ?? '', 'component'); ?></div>
                        <h4 class="red-admin-users__section-title">Utility tools</h4>
                        <div class="red-admin-permissions"><?php red_admin_user_permission_options('tools', $tools, $user['AdminTools'] ?? '', 'tool'); ?></div>
                        <div class="red-admin-actions">
                            <input type="submit" name="submit" value="Save User" id="save" />
                            <?php if (!$isCurrent): ?>
                            <button type="button" class="red-admin-delete" onclick="return redDeleteAdministratorUser(<?php echo $userRecordId; ?>, <?php echo red_admin_user_html(json_encode((string) ($user['Username'] ?? ''))); ?>);">Delete User</button>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                </form>
                <?php endif; ?>
            </div>
        </article>
    </div>
</div>
<?php $db->close(); ?>
