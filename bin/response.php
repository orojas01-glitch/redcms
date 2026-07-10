<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/bootstrap.php";
red_start_session();
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/public_form_helpers.php';

if (empty($_SESSION['contact'])) {
    red_public_form_redirect_home();
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$recordId = red_public_form_record_id($_POST['RecordID'] ?? 0);
$formRecord = red_public_form_fetch_record($db->connection, $recordId);
if ($formRecord === null) {
    red_public_form_redirect_home();
}

$send = red_public_form_post_value($_POST, 'MySpamTrap') === '';
$fromEmail = (string) $formRecord['Submitter'];
$toEmail = (string) $formRecord['Destinatary'];
$ccEmail = (string) $formRecord['CC'];
$bccEmail = (string) $formRecord['BCC'];
$subjectEmail = (string) $formRecord['Subject'];
$response = (string) $formRecord['Response'];

$emailhtml = '<HTML><HEAD><TITLE>' . red_public_form_html(BASE_URL) . '</TITLE></HEAD>';
$emailhtml .= '<style type="text/css">';
$emailhtml .= 'table.standard {font-family:Verdana, Geneva, sans-serif; font-size:14px; border-width: 0px;	border-spacing:0px;	border-style: solid; border-color:#cccccc;	border-collapse: collapse;	background-color: white;}';
$emailhtml .= 'table.standard th {	border-width: 0px;	padding:4px; border-style: inset; border-color: #cccccc; background-color: #F5F5F5;}';
$emailhtml .= 'table.standard td {	border-width: 0px;	padding: 4px;	border-style: inset; border-color: #cccccc;	background-color: white;}';
$emailhtml .= '</style>';
$emailhtml .= '<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">';

foreach (red_public_form_parse_definition($formRecord['LongDesc']) as $field) {
    $fieldName = red_public_form_identifier($field['name'] ?? '');
    if ($fieldName === null) {
        continue;
    }

    $value = red_public_form_post_value($_POST, $fieldName);
    if ($fieldName === 'MySpamTrap') {
        if ($value !== '') {
            $send = false;
        }
        continue;
    }

    if (!red_public_form_is_input_type($field['type'] ?? '')) {
        continue;
    }

    $required = ($field['required'] ?? '') !== 'false';
    $emailhtml .= red_public_form_email_row($fieldName, $value, $required);

    if ($required || $value !== '') {
        $response = red_public_form_replace_response_token($response, $fieldName, $value);
    }
    $subjectEmail = red_public_form_replace_mail_token($subjectEmail, $fieldName, $value);
    if ($required) {
        $toEmail = red_public_form_replace_mail_token($toEmail, $fieldName, $value);
        $fromEmail = red_public_form_replace_mail_token($fromEmail, $fieldName, $value);
    }
}

$emailhtml .= '<tr><th>IP</th><td>' . red_public_form_html(getRealIpAddr()) . '</td></tr>';
$emailhtml .= '<tr><th>Country</th><td>' . red_public_form_html(getlocation(getRealIpAddr())) . '</td></tr>';
$emailhtml .= '</table></html>';

unset($_SESSION['contact']);
if ($send) {
    require 'Exception.php';
    require 'PHPMailer.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer();

    $fromEmailName = explode(',', $fromEmail, 2);
    $thisFrom = trim($fromEmailName[0] ?? '');
    $thisName = trim($fromEmailName[1] ?? '');
    $mail->From = $thisFrom;
    $mail->FromName = $thisName;

    red_public_form_add_recipients($mail, 'AddAddress', $toEmail);
    red_public_form_add_recipients($mail, 'AddCC', $ccEmail);
    red_public_form_add_recipients($mail, 'AddBCC', $bccEmail);

    $mail->WordWrap = 50;
    $mail->IsHTML(true);
    $mail->Subject = red_public_form_header_value($subjectEmail);
    $mail->Body = $emailhtml;
    $mail->AltBody = "This is the text-only body";

    if (!$mail->Send()) {
        $recipient = 'redspheredevelopment@gmail.com';
        $subject = red_public_form_header_value($subjectEmail) . ' failed';
        mail($recipient, $subject, $emailhtml, "From: mail@red-sphere.com\r\nReply-To: $thisFrom\r\nX-Mailer: DT_formmail");
        exit;
    }
}

echo $response;
$db->close();
?>
