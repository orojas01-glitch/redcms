<?php
/**
 * Helpers for public PayPal PDT/store callbacks.
 */

if (!function_exists('red_public_paypal_scalar')) {
    function red_public_paypal_scalar($value)
    {
        return is_array($value) ? '' : trim((string) $value);
    }
}

if (!function_exists('red_public_paypal_html')) {
    function red_public_paypal_html($value)
    {
        return htmlspecialchars(red_public_paypal_scalar($value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('red_public_paypal_header_value')) {
    function red_public_paypal_header_value($value)
    {
        return str_replace(["\r", "\n"], ' ', red_public_paypal_scalar($value));
    }
}

if (!function_exists('red_public_paypal_parse_pdt')) {
    function red_public_paypal_parse_pdt($response)
    {
        $lines = explode("\n", (string) $response);
        if (!isset($lines[0]) || trim($lines[0]) !== 'SUCCESS') {
            return null;
        }

        $data = [];
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $data[urldecode($key)] = urldecode($value);
        }

        return $data;
    }
}

if (!function_exists('red_public_paypal_confirmation_body')) {
    function red_public_paypal_confirmation_body($itemName, $amount, $txnId)
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>Payment confirmation</title></head><body>' .
            '<style type="text/css">' .
            'table.standard {font-family:Verdana, Geneva, sans-serif; font-size:14px; border-width: 0px;	border-spacing:0px;	border-style: solid; border-color:#cccccc;	border-collapse: collapse;	background-color: white;}' .
            'table.standard th {	border-width: 0px;	padding:4px; border-style: inset; border-color: #cccccc; background-color: #F5F5F5;}' .
            'table.standard td {	border-width: 0px;	padding: 4px;	border-style: inset; border-color: #cccccc;	background-color: white;}' .
            '</style>' .
            '<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">' .
            '<tr><th>Item:</th><td>' . red_public_paypal_html($itemName) . '</td></tr>' .
            '<tr><th>Amount:</th><td>' . red_public_paypal_html($amount) . '</td></tr>' .
            '<tr><th>Confirmation #:</th><td>' . red_public_paypal_html($txnId) . '</td></tr>' .
            '</table></body></html>';
    }
}

if (!function_exists('red_public_paypal_send_confirmation')) {
    function red_public_paypal_send_confirmation(
        $payerEmail,
        $firstName,
        $lastName,
        $body,
        $fromEmail = '',
        $fromName = 'RED-CMS'
    )
    {
        $payerEmail = red_public_paypal_header_value($payerEmail);
        $fromEmail = red_public_paypal_header_value($fromEmail);
        $fromName = red_public_paypal_header_value($fromName);
        if (
            !filter_var($payerEmail, FILTER_VALIDATE_EMAIL)
            || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
        ) {
            return false;
        }
        if ($fromName === '') {
            $fromName = 'RED-CMS';
        }

        require_once __DIR__ . '/../bin/Exception.php';
        require_once __DIR__ . '/../bin/phpmailer.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->From = $fromEmail;
        $mail->FromName = $fromName;
        $mail->AddAddress($payerEmail, trim(red_public_paypal_header_value($firstName . ' ' . $lastName)));
        $mail->WordWrap = 50;
        $mail->IsHTML(true);

        switch (language) {
            case 'en':
                $mail->Subject = $fromName . ' - Payment Confirmation';
                break;
            case 'sp':
            default:
                $mail->Subject = $fromName . ' - Confirmacion de pago';
                break;
        }

        $mail->Body = $body;
        $mail->AltBody = "This is the text-only body";

        return $mail->Send();
    }
}

?>
