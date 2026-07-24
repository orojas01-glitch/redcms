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
    $resolved = red_public_operational_form_fetch_record($db->connection, $recordId, 'Response');
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
    ], 'Response');
    $fields = red_public_contact_compile_fields($form['definition']);
    $payload = red_public_form_operation_contact_payload($_POST);
    $honeypot = $payload['MySpamTrap'] !== '';
    $values = red_public_contact_validate_submission($form, $fields, $payload, !$honeypot);
} catch (InvalidArgumentException $exception) {
    $db->close();
    red_public_form_redirect_home();
}

$response = (string) $resolved['response'];
$subject = (string) $form['subject'];
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
    $emailhtml .= red_public_form_email_row($fieldName, $value, $field['required']);
    if ($field['required'] || $value !== '') {
        $response = red_public_form_replace_response_token($response, $fieldName, $value);
    }
    $subject = red_public_form_replace_mail_token($subject, $fieldName, $value);
}

$emailhtml .= '</table></body></html>';
unset($_SESSION['contact']);
$db->close();

if (!$honeypot) {
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
        $mail->WordWrap = 50;
        $mail->isHTML(true);
        $mail->Subject = red_public_form_header_value($subject);
        $mail->Body = $emailhtml;
        $mail->AltBody = 'Form response submission';
        $sent = (bool) $mail->send();
    } catch (Throwable $exception) {
        error_log('Public Response PHPMailer delivery failed: ' . $exception->getMessage());
    }

    if (!$sent) {
        $recipient = $form['recipientMailboxes'][0]['email'] ?? '';
        $from = $form['fromMailbox']['email'] ?? '';
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false
            && filter_var($from, FILTER_VALIDATE_EMAIL) !== false
        ) {
            mail(
                $recipient,
                red_public_form_header_value($subject) . ' failed',
                $emailhtml,
                "From: $from\r\nReply-To: $from\r\nX-Mailer: DT_formmail"
            );
        }
    }
}

echo $response;
?>
