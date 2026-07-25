<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/bootstrap.php";
red_start_session();
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/public_form_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/public_form_operation_helpers.php';

$contactPayload = $_POST;

$db = null;
$dependencies = [
    'fetchForm' => static function ($recordId) use (&$db) {
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        return red_public_contact_fetch_record($db->connection, $recordId);
    },
    'buildMessage' => static function ($form, $fields, $values) {
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
            $value = $values[$field['name']] ?? '';
            $suffix = $field['required'] ? '*' : '';
            echo '<tr><th>' . red_public_form_html($field['label']) . $suffix . '</th><td>' .
                nl2br(red_public_form_html($value)) . '</td></tr>';
        }
?>
</table>
</body>
</html>
<?php
        $message = ob_get_clean();
        if (!is_string($message)) {
            throw new RuntimeException('Public Contact message buffer failed.');
        }

        return $message;
    },
    'consumeContactSession' => static function () {
        unset($_SESSION['contact']);
        return true;
    },
    'sendMail' => static function ($form, $values, $body) {
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
            $mail->Subject = red_public_form_header_value($form['subject']);
            $mail->Body = $body;
            $mail->AltBody = 'Contact form submission';

            return (bool) $mail->send();
        } catch (Throwable $exception) {
            error_log('Public Contact PHPMailer delivery failed: ' . $exception->getMessage());
            return false;
        }
    },
    'fallbackMail' => static function ($form, $values, $body) {
        $recipient = $form['recipientMailboxes'][0]['email'] ?? '';
        $thisFrom = $form['fromMailbox']['email'] ?? '';
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
            || filter_var($thisFrom, FILTER_VALIDATE_EMAIL) === false
        ) {
            return false;
        }

        $subject = red_public_form_header_value($form['subject']) . ' failed';
        return mail(
            $recipient,
            $subject,
            $body,
            "From: $thisFrom\r\nReply-To: $thisFrom\r\nX-Mailer: DT_formmail"
        );
    },
];

try {
    $result = red_public_form_operation_execute(
        'contact',
        [
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'endpoint' => '/bin/contact.php',
            'payload' => $contactPayload,
        ],
        [
            'contactSession' => !empty($_SESSION['contact']),
            'baseUrl' => (string) BASE_URL,
        ],
        $dependencies
    );
} catch (InvalidArgumentException $exception) {
    if ($db instanceof connection) {
        $db->close();
    }
    red_public_form_redirect_home();
}

if ($db instanceof connection) {
    $db->close();
}

http_response_code($result['httpStatus']);
foreach ($result['headers'] as $name => $value) {
    header($name . ': ' . $value);
}
echo $result['body'];
?>
