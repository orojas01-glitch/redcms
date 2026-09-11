<?php
declare(strict_types=1);
// Exercise real config parsing and PHPMailer without private config or network.
function red_local_config_values() { return []; }
require_once dirname(__DIR__) . '/includes/mail_transport_helpers.php';
require_once dirname(__DIR__) . '/bin/Exception.php';
require_once dirname(__DIR__) . '/bin/phpmailer.php';

$keys = ['MAIL_TRANSPORT', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURITY',
    'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME'];
$original = [];
foreach ($keys as $key) {
    $original[$key] = getenv('RED_' . $key);
    putenv('RED_' . $key);
}
$checks = 0;
$assert = static function ($ok, $label) use (&$checks) {
    if (!$ok) { throw new RuntimeException($label); }
    $checks++;
};
$configure = static function ($overrides = []) {
    $fixture = array_merge([
        'MAIL_TRANSPORT' => 'smtp', 'SMTP_HOST' => 'smtp.example.test',
        'SMTP_PORT' => '587', 'SMTP_SECURITY' => 'starttls',
        'SMTP_USERNAME' => 'sender@example.test', 'SMTP_PASSWORD' => 'synthetic-test-only',
        'SMTP_FROM_EMAIL' => 'sender@example.test', 'SMTP_FROM_NAME' => 'Synthetic sender',
    ], $overrides);
    foreach ($fixture as $key => $value) { putenv('RED_' . $key . '=' . $value); }
};
try {
    $_SERVER['RED_MAIL_TRANSPORT'] = 'smtp';
    $_ENV['RED_MAIL_TRANSPORT'] = 'smtp';
    $assert(red_mail_transport_mode() === 'mail', 'Request-projected config ignored');
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->setFrom('form@example.test', 'Form');
    red_mail_configure_transport($mail);
    $assert($mail->Mailer === 'mail' && $mail->From === 'form@example.test', 'Legacy transport preserved');
    $assert(red_mail_legacy_fallback_allowed(), 'Legacy fallback preserved only in mail mode');
    $configure();
    red_mail_configure_transport($mail);
    $assert($mail->Mailer === 'smtp' && $mail->SMTPAuth === true, 'Authentication required');
    $assert($mail->SMTPSecure === 'tls' && $mail->Port === 587, 'Explicit STARTTLS');
    $assert($mail->SMTPOptions['ssl'] === ['verify_peer' => true, 'verify_peer_name' => true,
        'allow_self_signed' => false], 'Certificate verification required');
    $assert($mail->From === 'sender@example.test' && $mail->Sender === $mail->From, 'Fixed sender and envelope');
    $assert(isset($mail->getReplyToAddresses()['form@example.test']), 'Managed form reply address preserved');
    $assert($mail->SMTPDebug === 0 && $mail->Timeout === 15
        && $mail->getSMTPInstance()->Timelimit === 30, 'Debug disabled and waits bounded');
    $assert(!red_mail_legacy_fallback_allowed(), 'SMTP cannot downgrade');
    $configure(['SMTP_PORT' => '465', 'SMTP_SECURITY' => 'smtps']);
    red_mail_configure_transport($mail);
    $assert($mail->SMTPSecure === 'ssl' && $mail->Port === 465, 'Implicit TLS supported');
    foreach ([['MAIL_TRANSPORT' => 'invalid'], ['SMTP_HOST' => 'smtp.example.test;evil.test'],
        ['SMTP_HOST' => 'ssl://smtp.example.test'], ['SMTP_PORT' => '25'],
        ['SMTP_PORT' => '465'], ['SMTP_SECURITY' => 'none'], ['SMTP_PASSWORD' => ''],
        ['SMTP_USERNAME' => "sender\r\ninjection"], ['SMTP_FROM_EMAIL' => "a@example.test\r\nBcc:x@example.test"],
        ['SMTP_FROM_NAME' => "Name\nInjected"], ['SMTP_FROM_EMAIL' => '']] as $bad) {
        $configure($bad);
        $rejected = false;
        try { red_mail_configure_transport(new PHPMailer\PHPMailer\PHPMailer()); }
        catch (RuntimeException $e) {
            $rejected = true;
            $assert(!str_contains($e->getMessage(), 'synthetic-test-only'), 'Configuration error redacted');
        }
        $assert($rejected && !red_mail_legacy_fallback_allowed(), 'Unsafe configuration fails closed');
    }
    $configure();
    require_once dirname(__DIR__) . '/bin/SMTP.php';
    $smtp = new class extends PHPMailer\PHPMailer\SMTP {
        public $attempts = 0;
        public function connect($host, $port = null, $timeout = 30, $options = []) {
            $this->attempts++;
            return false; // Deterministic transport failure; no socket opened.
        }
    };
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->setSMTPInstance($smtp);
    $mail->setFrom('form@example.test');
    $mail->addAddress('recipient@example.test');
    $mail->Subject = 'Synthetic failure test';
    $mail->Body = 'No real message is sent.';
    red_mail_configure_transport($mail);
    $assert(!$mail->send() && $smtp->attempts === 1 && !red_mail_legacy_fallback_allowed(),
        'Real mailer failure does not select local fallback');
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->setFrom('owner@example.test');
    $mail->addReplyTo('visitor@example.test');
    red_mail_configure_transport($mail);
    $assert(count($mail->getReplyToAddresses()) === 1
        && isset($mail->getReplyToAddresses()['visitor@example.test']), 'Existing visitor reply address preserved');
    echo "Mail transport: $checks assertions passed; no network or mail effects.\n";
} finally {
    foreach ($original as $key => $value) {
        putenv('RED_' . $key . ($value === false ? '' : '=' . $value));
    }
    unset($_SERVER['RED_MAIL_TRANSPORT'], $_ENV['RED_MAIL_TRANSPORT']);
}
