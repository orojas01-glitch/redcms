<?php
/** CLI-only, draft-first synthetic delivery proof. Never expose as a web endpoint. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/mail_transport_helpers.php';
require_once dirname(__DIR__) . '/bin/Exception.php';
require_once dirname(__DIR__) . '/bin/phpmailer.php';

$options = getopt('', ['to:', 'send', 'confirm-to:']);
$to = $options['to'] ?? '';
if (!is_string($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/mail-delivery-proof.php --to=OWNER_APPROVED_ADDRESS [--send --confirm-to=SAME_ADDRESS]\n");
    exit(64);
}
if (isset($options['send']) && ($options['confirm-to'] ?? null) !== $to) {
    fwrite(STDERR, "Send refused: confirm the exact recipient.\n");
    exit(64);
}
try {
    if (red_mail_transport_mode() !== 'smtp') {
        throw new RuntimeException('SMTP required');
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    // Do not turn PHPMailer's placeholder From into a reply address.
    $mail->From = '';
    red_mail_configure_transport($mail);
    $mail->addAddress($to);
    $id = bin2hex(random_bytes(12));
    $domain = substr(strrchr($mail->From, '@'), 1);
    $mail->MessageID = '<redcms-proof-' . $id . '@' . $domain . '>';
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'RED-CMS demo delivery verification ' . $id;
    $mail->Body = "Owner-authorized synthetic RED-CMS delivery check.\nReference: $id\nNo client data is included.\n";
    if (!isset($options['send'])) {
        echo "SMTP configuration valid. Draft only; no connection or message sent.\n";
        echo "Proposed message: one plain-text RED-CMS delivery verification with a random reference.\n";
        exit;
    }
    if (!$mail->send()) {
        throw new RuntimeException('Transport did not confirm acceptance');
    }
    $receipt = $mail->getSMTPInstance()->getLastTransactionID();
    $safeReceipt = is_string($receipt) && preg_match('/\A[A-Za-z0-9._-]{1,150}\z/', $receipt)
        ? $receipt : 'unavailable';
    echo "SMTP server accepted the message; inbox receipt is still unverified.\n";
    echo "Reference: $id\nSMTP receipt: $safeReceipt\n";
} catch (Throwable $e) {
    // Provider exceptions may include addresses or credentials. Never print them.
    fwrite(STDERR, "Delivery/configuration failed or acceptance is uncertain. Inspect privately; do not retry blindly.\n");
    exit(1);
}
