<?php
/** C4B4D runtime probe: succeeds only when common network paths are disabled. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

foreach ([
    'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen',
    'stream_socket_client', 'socket_create', 'socket_connect',
] as $function) {
    if (function_exists($function)) {
        fwrite(STDERR, "Network function remains available: $function\n");
        exit(1);
    }
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
    fwrite(STDERR, "URL streams remain available.\n");
    exit(1);
}
echo "wompi-c4b4d-no-contact-runtime:ready\n";
