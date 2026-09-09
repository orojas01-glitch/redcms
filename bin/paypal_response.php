<?php
/** Retired legacy PDT URL: never bootstrap credentials, sessions or payment code. */
http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo 'This payment endpoint has been retired.';
exit;
