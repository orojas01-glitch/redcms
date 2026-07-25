<?php
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

function red_legacy_mail_append_field(&$messageBody, $label, $value)
{
    if ($value === '' || $value === 'nope') {
        return;
    }

    $messageBody .= '<p>' . red_legacy_mail_html($label) . ': ' . red_legacy_mail_html($value) . '</p>' . "\n";
    $messageBody .= '<br>' . "\n";
}

function red_legacy_mail_fail()
{
    echo "mail failed\n";
    exit;
}

$owner_email = red_legacy_mail_header_value(red_legacy_mail_post("owner_email"));
$fromEmail = red_legacy_mail_header_value(red_legacy_mail_post("email"));
$visitorName = red_legacy_mail_header_value(red_legacy_mail_post("name"));

if (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
    red_legacy_mail_fail();
}

$headers = '';
if ($fromEmail !== 'nope' && $fromEmail !== '') {
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        red_legacy_mail_fail();
    }
    $headers = 'From:' . $fromEmail;
}

$subject = 'A message from your site visitor ' . $visitorName;
$messageBody = "";

red_legacy_mail_append_field($messageBody, 'Visitor', red_legacy_mail_post('name'));
red_legacy_mail_append_field($messageBody, 'Email Address', red_legacy_mail_post('email'));
red_legacy_mail_append_field($messageBody, 'State', red_legacy_mail_post('state'));
red_legacy_mail_append_field($messageBody, 'Phone Number', red_legacy_mail_post('phone'));
red_legacy_mail_append_field($messageBody, 'Fax Number', red_legacy_mail_post('fax'));

$message = red_legacy_mail_post('message');
if ($message !== '' && $message !== 'nope') {
    $messageBody .= '<p>Message: ' . red_legacy_mail_html($message) . '</p>' . "\n";
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
