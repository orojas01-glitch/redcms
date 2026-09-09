<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$count = 0;
function check_session(bool $ok, string $label): void {
    global $count;
    if (!$ok) { throw new RuntimeException($label); }
    $count++;
}
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
check_session(!red_session_https(), 'forwarding header cannot grant TLS trust');
$_SERVER['HTTPS'] = 'on';
check_session(red_session_https(), 'direct TLS accepted');
session_id('unissued-session-fixation-attempt');
red_start_session();
check_session(session_id() !== 'unissued-session-fixation-attempt', 'strict mode rejects unissued ID');
$p = session_get_cookie_params();
check_session($p['secure'] && $p['httponly'] && $p['samesite'] === 'Lax' && $p['domain'] === '' && $p['lifetime'] === 0, 'cookie policy');
$old = session_id();
$csrf = red_csrf_token();
red_admin_session_establish();
check_session(session_id() !== $old && red_csrf_token() !== $csrf, 'login renews ID and CSRF');
$s = ['AdminSessionIssuedAt' => 1000, 'AdminSessionLastSeen' => 2000];
check_session(red_admin_session_current($s, 3799), 'active session before boundary');
check_session(!red_admin_session_current($s, 3800), 'inactivity boundary');
check_session(!red_admin_session_current(['AdminSessionIssuedAt'=>1000,'AdminSessionLastSeen'=>29799], 29800), 'absolute boundary');
check_session(!red_admin_session_current([], 2000), 'old sessions require login');
check_session(!red_admin_session_current($s, 1999), 'future timestamp refused');
check_session(!red_admin_session_current(['AdminSessionIssuedAt'=>'1000','AdminSessionLastSeen'=>2000], 2000), 'malformed timestamp refused');
$_SESSION['AdminRecordID'] = 123;
red_destroy_session();
check_session($_SESSION === [] && session_status() !== PHP_SESSION_ACTIVE, 'logout clears state and server session');
echo "Admin session: $count assertions passed.\n";
