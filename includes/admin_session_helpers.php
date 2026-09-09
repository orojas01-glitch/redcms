<?php
/** Core session policy. Trust direct server TLS only, never forwarding headers. */
function red_session_https(): bool
{
    return isset($_SERVER['HTTPS']) && in_array(strtolower((string) $_SERVER['HTTPS']), ['on', '1'], true);
}

function red_admin_session_current(array $session, int $now): bool
{
    $issued = $session['AdminSessionIssuedAt'] ?? null;
    $last = $session['AdminSessionLastSeen'] ?? null;
    return is_int($issued) && is_int($last) && $issued <= $last && $last <= $now
        && $now - $issued < 28800 && $now - $last < 1800;
}

function red_destroy_session(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'], 'domain' => $params['domain'],
                'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }
}

function red_admin_session_establish(): void
{
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Session renewal failed');
    }
    $_SESSION['AdminSessionIssuedAt'] = time();
    $_SESSION['AdminSessionLastSeen'] = $_SESSION['AdminSessionIssuedAt'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
