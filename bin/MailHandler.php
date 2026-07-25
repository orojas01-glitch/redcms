<?php
require_once __DIR__ . '/../includes/runtime_config_helpers.php';

function red_legacy_mail_post($key)
{
    return isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

function red_legacy_mail_header_value($value)
{
    return trim(str_replace(["\r", "\n"], '', (string) $value));
}

function red_legacy_mail_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function red_legacy_mail_fail()
{
    echo "mail failed\n";
    exit;
}

$owner_email = red_legacy_mail_header_value(
    red_config_value('LEGACY_MAIL_OWNER', ['RED_LEGACY_MAIL_OWNER', 'LEGACY_MAIL_OWNER'], '')
);
$fromEmail = red_legacy_mail_header_value(red_legacy_mail_post('email'));
$visitorName = red_legacy_mail_header_value(red_legacy_mail_post('name'));

if (!filter_var($owner_email, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    red_legacy_mail_fail();
}

$headers = 'From: ' . $owner_email . "\r\nReply-To: " . $fromEmail;
$subject = 'A message from your site visitor ' . $visitorName;
$messageBody = "";

foreach ($_POST as $key => $value) {
    if (!is_scalar($value)) {
        continue;
    }

    switch ($key) {
        case "owner_email":
        case "stripHTML":
            break;

        default:
            $messageBody .= '<p>' . red_legacy_mail_html($key) . ': ' . red_legacy_mail_html($value) . '</p>' . "\n";
            $messageBody .= '<br>' . "\n";
            break;
    }
}

if (red_legacy_mail_post("stripHTML") === 'true') {
    $messageBody = strip_tags($messageBody);
}

try {
    if (!mail($owner_email, $subject, $messageBody, $headers)) {
        throw new Exception('mail failed');
    } else {
        echo 'mail sent';
    }
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
?>
