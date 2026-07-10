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
$fields = red_public_form_parse_definition($formRecord['LongDesc']);

ob_start();
?>
<html>
<head>
<style type="text/css">
table.standard {font-family:Verdana, Geneva, sans-serif; font-size:14px; border-width: 0px;	border-spacing:0px;	border-style: solid; border-color:#cccccc;	border-collapse: collapse;	background-color: white;}
table.standard th {	border-width: 0px;	padding:4px; border-style: inset; border-color: #cccccc; background-color: #F5F5F5;}
table.standard td {	border-width: 0px;	padding: 4px;	border-style: inset; border-color: #cccccc;	background-color: white;}
</style>
</head>
<body>
<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">
<?php
foreach ($fields as $field) {
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
    echo red_public_form_email_row($fieldName, $value, $required);
}

echo '<tr><th>IP</th><td>' . red_public_form_html(getRealIpAddr()) . '</td></tr>';
echo '<tr><th>Country</th><td>' . red_public_form_html(getlocation(getRealIpAddr())) . '</td></tr>';
?>
</table>
</body>
</html>
<?php
unset($_SESSION['contact']);
if ($send) {
    $body = ob_get_contents();
    require 'Exception.php';
    require 'PHPMailer.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer();

    $fromEmailName = explode(',', (string) $formRecord['Submitter'], 2);
    $thisFrom = trim($fromEmailName[0] ?? '');
    $thisName = trim($fromEmailName[1] ?? '');
    $mail->From = $thisFrom;
    $mail->FromName = $thisName;

    red_public_form_add_recipients($mail, 'AddAddress', $formRecord['Destinatary']);
    red_public_form_add_recipients($mail, 'AddCC', $formRecord['CC']);
    red_public_form_add_recipients($mail, 'AddBCC', $formRecord['BCC']);

    $mail->WordWrap = 50;
    $mail->IsHTML(true);
    $mail->Subject = red_public_form_header_value($formRecord['Subject']);
    $mail->Body = $body;
    $mail->AltBody = "This is the text-only body";

    if (!$mail->Send()) {
        $recipient = 'orojas01@gmail.com';
        $subject = red_public_form_header_value($formRecord['Subject']) . ' failed';
        $content = $body;
        mail($recipient, $subject, $content, "From: mail@redsphere.tv\r\nReply-To: $thisFrom\r\nX-Mailer: DT_formmail");
        exit;
    }
}

$db->close();
?>
