#!/usr/bin/env python3
"""HTTP cookie/expiry regression; fixture contains no database or real identity."""
import http.client
import json
import os
import pathlib
import socket
import subprocess
import tempfile
import time

root = pathlib.Path(__file__).resolve().parents[1]
php = os.environ.get('RED_PHP_BIN', 'php')
with tempfile.TemporaryDirectory(prefix='redcms-session-test-') as temp:
    folder = pathlib.Path(temp)
    # Only this synthetic router is served, never the repository or local config.
    (folder / 'index.php').write_text("<?php\nrequire_once " + json.dumps(str(root / 'includes/bootstrap.php')) + ";\n" + r'''
if ($_SERVER['REQUEST_URI'] === '/secure') { $_SERVER['HTTPS'] = 'on'; }
red_start_session();
if ($_SERVER['REQUEST_URI'] === '/denied') { red_require_admin(); }
if ($_SERVER['REQUEST_URI'] === '/login') {
    red_admin_session_establish(); $_SESSION['AdminRecordID'] = 123;
}
if ($_SERVER['REQUEST_URI'] === '/expire') { $_SESSION['AdminSessionLastSeen'] = time() - 1800; }
if ($_SERVER['REQUEST_URI'] === '/logout') { red_destroy_session(); }
header('Content-Type: application/json');
echo json_encode(['authenticated' => !empty($_SESSION['AdminRecordID'])]);
''')
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    with (folder / 'server.log').open('w') as log:
        server = subprocess.Popen([php, '-d', 'session.save_path=' + temp, '-S', f'127.0.0.1:{port}', str(folder / 'index.php')], cwd=temp, stdout=log, stderr=log)
        def request(path, cookie=''):
            c = http.client.HTTPConnection('127.0.0.1', port, timeout=3)
            c.request('GET', path, headers={'Cookie': cookie, 'X-Forwarded-Proto': 'https'})
            r = c.getresponse()
            headers, body = r.getheaders(), json.loads(r.read())
            c.close()
            return [v for k, v in headers if k.lower() == 'set-cookie'], body
        try:
            for _ in range(50):
                try:
                    cookies, body = request('/start')
                    break
                except ConnectionRefusedError:
                    time.sleep(.05)
            else:
                raise RuntimeError('fixture did not start')
            cookie = cookies[-1].split(';')[0]
            assert 'httponly' in cookies[-1].lower() and 'samesite=lax' in cookies[-1].lower()
            assert 'secure' not in cookies[-1].lower()  # forwarding header ignored
            renewed, body = request('/login', cookie)
            assert body['authenticated'] and renewed[-1].split(';')[0] != cookie
            cookie = renewed[-1].split(';')[0]
            request('/expire', cookie)
            _, body = request('/state', cookie)
            assert not body['authenticated']
            renewed, _ = request('/login')
            expired, body = request('/logout', renewed[-1].split(';')[0])
            assert not body['authenticated'] and any('expires=' in c.lower() for c in expired)
            cookies, _ = request('/secure')
            assert 'secure' in cookies[-1].lower()  # synthetic direct-TLS SAPI projection
            c = http.client.HTTPConnection('127.0.0.1', port, timeout=3)
            c.request('GET', '/denied')
            denied = c.getresponse()
            assert denied.status == 403 and denied.getheader('Cache-Control') == 'no-store'
            assert denied.getheader('X-Frame-Options') == 'SAMEORIGIN' and denied.read() == b'no'
            c.close()
            print('Session HTTP checks passed: flags, forwarding refusal, renewal, expiry, logout.')
        finally:
            server.terminate()
            server.wait(timeout=5)
