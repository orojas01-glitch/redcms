#!/usr/bin/env python3
"""Actual PHP response headers; only synthetic fixtures, no local config/DB."""
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
count = 0
with tempfile.TemporaryDirectory(prefix='redcms-csp-') as temp:
    folder = pathlib.Path(temp)
    # Shadow only private configuration loading; use real bootstrap and emitter.
    (folder / 'index.php').write_text("<?php\nfunction red_local_config_values() { return []; }\n"
        + "$_SERVER['RED_CSP_MODE'] = 'off'; $_ENV['RED_CSP_MODE'] = 'off';\n"
        + 'require_once ' + json.dumps(str(root / 'includes/bootstrap.php')) + ';\n'
        + "if ($_SERVER['REQUEST_URI'] === '/denied') { red_require_admin(); }\n"
        + "header('Content-Type: text/html'); echo '<!doctype html><title>Synthetic public page</title>';\n")
    cases = [
        ('off', '{}', '0', 200, False, False),
        ('report-only', '{}', '0', 200, False, True),
        ('enforce', '{}', '0', 200, True, False),
        ('enforce', '{}', '1', 200, True, True),
        ('enforce', '{"connect-src":["https://api.example.test"]}', '0', 200, True, False),
        ('enforce', '{"script-src":["https://evil.test; report-uri https://evil.test"]}', '0', 503, False, False),
        ('typo', '{}', '0', 503, False, False),
        ('enforce', 'not-json', '0', 503, False, False),
    ]
    for mode, sources, strict, status, enforced, reported in cases:
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        env = dict(os.environ, RED_CSP_MODE=mode, RED_CSP_SOURCES=sources, RED_CSP_STRICT_REPORT=strict)
        with (folder / 'server.log').open('w') as log:
            server = subprocess.Popen([php, '-d', 'session.save_path=' + temp, '-S', f'127.0.0.1:{port}', str(folder / 'index.php')], cwd=temp, env=env, stdout=log, stderr=log)
            try:
                for attempt in range(60):
                    try:
                        conn = http.client.HTTPConnection('127.0.0.1', port, timeout=3)
                        conn.request('GET', '/', headers={'X-RED-CSP-MODE': 'off'})
                        response = conn.getresponse()
                        break
                    except ConnectionRefusedError:
                        time.sleep(.05)
                else:
                    raise RuntimeError('CSP fixture unavailable')
                body = response.read()
                assert response.status == status
                assert bool(response.getheader('Content-Security-Policy')) == enforced
                assert bool(response.getheader('Content-Security-Policy-Report-Only')) == reported
                assert response.getheader('Set-Cookie') is None
                if status == 503:
                    assert body == b'Security configuration is temporarily unavailable.'
                    assert response.getheader('Cache-Control') == 'no-store'
                if enforced:
                    conn.request('GET', '/denied')
                    denied = conn.getresponse()
                    assert denied.status == 403 and denied.getheader('Content-Security-Policy')
                    assert denied.read() == b'no'
                conn.close()
                count += 1
            finally:
                server.terminate()
                server.wait(timeout=5)
print(f'CSP HTTP passed: {count} mode/configuration cases, public and denied admin responses.')
