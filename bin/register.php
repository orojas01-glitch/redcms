<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/public_form_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/public_form_operation_helpers.php';

if (empty($_SESSION['contact']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    red_public_form_redirect_home();
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    $recordId = red_public_form_record_id($_POST['RecordID'] ?? 0);
    $resolved = red_public_operational_form_fetch_record($db->connection, $recordId, 'Register');
    if ($resolved === null) {
        red_public_form_redirect_home();
    }

    $form = red_public_contact_form_config([
        'recordId' => $resolved['recordId'],
        'articleRecordId' => $resolved['articleRecordId'],
        'articleComponent' => $resolved['articleComponent'],
        'alias' => $resolved['alias'],
        'formType' => $resolved['formType'],
        'definition' => $resolved['definition'],
        'subject' => $resolved['subject'],
        'submitter' => $resolved['submitter'],
        'destinatary' => $resolved['destinatary'],
        'cc' => $resolved['cc'],
        'bcc' => $resolved['bcc'],
    ], 'Register');
    $fields = red_public_contact_compile_fields($form['definition']);
    $payload = red_public_form_operation_contact_payload($_POST);
    $honeypot = $payload['MySpamTrap'] !== '';
    $values = red_public_contact_validate_submission($form, $fields, $payload, !$honeypot);
} catch (InvalidArgumentException $exception) {
    $db->close();
    red_public_form_redirect_home();
}

$managedTableName = (string) $resolved['tableName'];
$expectedTableName = 'RED_Register_' . $form['articleRecordId'];
if (!hash_equals($expectedTableName, $managedTableName)) {
    $db->close();
    red_public_form_redirect_home();
}

$response = (string) $resolved['response'];
$subject = (string) $form['subject'];
$storageValues = [];
$emailhtml = '<html><head><title>' . red_public_form_html(BASE_URL) . '</title>';
$emailhtml .= '<style type="text/css">';
$emailhtml .= 'table.standard {font-family:Verdana,Geneva,sans-serif;font-size:14px;border-spacing:0;border-collapse:collapse;background:#fff;}';
$emailhtml .= 'table.standard th {padding:4px;background:#f5f5f5;}';
$emailhtml .= 'table.standard td {padding:4px;background:#fff;}';
$emailhtml .= '</style></head><body>';
$emailhtml .= '<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">';

foreach ($fields as $field) {
    $fieldName = $field['name'];
    $value = red_public_form_submission_text($values[$fieldName] ?? '');
    $storageValues[$fieldName] = $value;
    $emailhtml .= red_public_form_email_row($fieldName, $value, $field['required']);
    if ($field['required'] || $value !== '') {
        $response = red_public_form_replace_response_token($response, $fieldName, $value);
    }
    $subject = red_public_form_replace_mail_token($subject, $fieldName, $value);
}

$emailhtml .= '</table></body></html>';
unset($_SESSION['contact']);

if ($honeypot) {
    $db->close();
    echo $response;
    exit;
}

$stored = red_public_form_insert_submission($db->connection, $managedTableName, $storageValues);
$db->close();
if (!$stored) {
    http_response_code(500);
    echo 'We could not save this registration. Please try again.';
    exit;
}

$sent = false;
try {
    require_once __DIR__ . '/Exception.php';
    require_once __DIR__ . '/phpmailer.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->setFrom($form['fromMailbox']['email'], $form['fromMailbox']['name']);
    foreach ($form['recipientMailboxes'] as $mailbox) {
        $mail->addAddress($mailbox['email'], $mailbox['name']);
    }
    foreach ($form['ccMailboxes'] as $mailbox) {
        $mail->addCC($mailbox['email'], $mailbox['name']);
    }
    foreach ($form['bccMailboxes'] as $mailbox) {
        $mail->addBCC($mailbox['email'], $mailbox['name']);
    }

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->Subject = red_public_form_header_value($subject);
    $mail->Body = $emailhtml;
    $mail->AltBody = 'Registration form submission';
    $sent = (bool) $mail->send();
} catch (Throwable $exception) {
    error_log('Public Register PHPMailer delivery failed: ' . $exception->getMessage());
}

if (!$sent) {
    echo 'Tuvimos problemas al enviar. Por favor contáctame via WhatsApp.';
    exit;
}

echo $response;
?>
