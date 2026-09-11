<?php
/** Server-owned, opt-in SMTP settings shared by core mail endpoints. */
require_once __DIR__ . '/runtime_config_helpers.php';

function red_mail_transport_mode()
{
    return red_server_config_value('MAIL_TRANSPORT', ['RED_MAIL_TRANSPORT'], 'mail');
}

function red_mail_legacy_fallback_allowed()
{
    // Invalid or incomplete SMTP configuration must never downgrade to mail().
    return red_mail_transport_mode() === 'mail';
}

function red_mail_configure_transport($mail)
{
    $mode = red_mail_transport_mode();
    if ($mode === 'mail') {
        $mail->isMail();
        return;
    }
    if ($mode !== 'smtp') {
        throw new RuntimeException('Invalid mail transport configuration.');
    }
    $read = static function ($key) {
        return red_server_config_value($key, ['RED_' . $key], '');
    };
    $host = $read('SMTP_HOST');
    $port = $read('SMTP_PORT');
    $tls = $read('SMTP_SECURITY');
    $user = $read('SMTP_USERNAME');
    $password = $read('SMTP_PASSWORD');
    $from = $read('SMTP_FROM_EMAIL');
    $name = $read('SMTP_FROM_NAME');
    if (!is_string($host) || strlen($host) > 253
        || !preg_match('/\A[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?\z/', $host)
        || !in_array($tls, ['starttls', 'smtps'], true)
        || !in_array($port, [465, 587, '465', '587'], true)
        || ($tls === 'starttls' && (int) $port !== 587)
        || ($tls === 'smtps' && (int) $port !== 465)
        || !is_string($user) || $user === '' || preg_match('/[\x00-\x1f\x7f]/', $user)
        || !is_string($password) || $password === '' || str_contains($password, "\0")
        || !is_string($from) || !filter_var($from, FILTER_VALIDATE_EMAIL)
        || !is_string($name) || preg_match('/[\x00-\x1f\x7f]/', $name)
    ) {
        throw new RuntimeException('Invalid SMTP configuration.');
    }
    require_once dirname(__DIR__) . '/bin/SMTP.php';
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = (int) $port;
    $mail->SMTPSecure = $tls === 'starttls' ? 'tls' : 'ssl';
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $password;
    $mail->SMTPDebug = 0;
    $mail->Timeout = 15;
    $mail->getSMTPInstance()->Timelimit = 30;
    $mail->SMTPKeepAlive = false;
    $mail->SMTPOptions = ['ssl' => [
        'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
    ]];
    // Preserve the managed form's reply address while using an approved sender.
    if ($mail->getReplyToAddresses() === [] && $mail->From !== $from
        && filter_var($mail->From, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($mail->From, $mail->FromName);
    }
    $mail->setFrom($from, $name);
    $mail->Sender = $from;
}
