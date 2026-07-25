<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/bootstrap.php";
red_start_session();
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/public_paypal_helpers.php';

$ppHostname = red_config_value('PAYPAL_PDT_HOSTNAME', ['RED_PAYPAL_PDT_HOSTNAME', 'PAYPAL_PDT_HOSTNAME'], 'www.paypal.com');
$authToken = red_config_value('PAYPAL_PDT_AUTH_TOKEN', ['RED_PAYPAL_PDT_AUTH_TOKEN', 'PAYPAL_PDT_AUTH_TOKEN'], '');
$confirmationFromEmail = red_config_value(
    'PAYPAL_CONFIRMATION_FROM_EMAIL',
    ['RED_PAYPAL_CONFIRMATION_FROM_EMAIL', 'PAYPAL_CONFIRMATION_FROM_EMAIL'],
    ''
);
$confirmationFromName = red_config_value(
    'PAYPAL_CONFIRMATION_FROM_NAME',
    ['RED_PAYPAL_CONFIRMATION_FROM_NAME', 'PAYPAL_CONFIRMATION_FROM_NAME'],
    'RED-CMS'
);
$txToken = red_public_paypal_scalar($_GET['tx'] ?? '');

if (
    $authToken === ''
    || $txToken === ''
    || !filter_var($confirmationFromEmail, FILTER_VALIDATE_EMAIL)
) {
    exit;
}

$requestBody = http_build_query(
    [
        'cmd' => '_notify-synch',
        'tx' => $txToken,
        'at' => $authToken,
    ],
    '',
    '&'
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://' . $ppHostname . '/cgi-bin/webscr');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: ' . $ppHostname]);
$response = curl_exec($ch);
curl_close($ch);

$payment = red_public_paypal_parse_pdt($response);
if ($payment === null) {
    exit;
}

$itemName = $payment['item_name'] ?? '';
$amount = $payment['payment_gross'] ?? '';
$txnId = $payment['txn_id'] ?? '';
$payerEmail = $payment['payer_email'] ?? '';
$firstName = $payment['first_name'] ?? '';
$lastName = $payment['last_name'] ?? '';
$emailBody = red_public_paypal_confirmation_body($itemName, $amount, $txnId);
red_public_paypal_send_confirmation(
    $payerEmail,
    $firstName,
    $lastName,
    $emailBody,
    $confirmationFromEmail,
    $confirmationFromName
);

header('Location: http://' . BASE_URL . '/');
exit;
?>
