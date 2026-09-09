#!/usr/bin/env python3
"""Exercise the retired callback with synthetic settings, no client or provider access."""
import http.client
import os
import pathlib
import socket
import subprocess
import tempfile
import time

root = pathlib.Path(__file__).resolve().parents[1]
php = os.environ.get('RED_PHP_BIN', 'php')
assert not (root / 'includes/public_paypal_helpers.php').exists()
example = (root / 'includes/config.local.example.php').read_text()
assert 'PAYPAL_PDT_' not in example and 'PAYPAL_CONFIRMATION_' not in example
with tempfile.TemporaryDirectory(prefix='redcms-pdt-retired-') as temp:
    folder = pathlib.Path(temp)
    (folder / 'bin').mkdir()
    (folder / 'bin/paypal_response.php').write_bytes((root / 'bin/paypal_response.php').read_bytes())
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = dict(os.environ, RED_PAYPAL_PDT_AUTH_TOKEN='synthetic-retired-fixture', RED_PAYPAL_PDT_HOSTNAME='example.invalid', RED_PAYPAL_CONFIRMATION_FROM_EMAIL='fixture@example.test')
    with (folder / 'server.log').open('w') as log:
        server = subprocess.Popen([php, '-d', 'display_errors=0', '-d', 'disable_functions=curl_init,curl_exec,mail,mysqli_connect', '-S', f'127.0.0.1:{port}', '-t', temp], env=env, stdout=log, stderr=log)
        try:
            for _ in range(60):
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=1):
                        break
                except ConnectionRefusedError:
                    time.sleep(.05)
            else:
                raise RuntimeError('Fixture server did not start')
            for method in ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT']:
                connection = http.client.HTTPConnection('127.0.0.1', port, timeout=3)
                connection.request(method, '/bin/paypal_response.php?tx=synthetic&custom=store,1,2', body='tx=synthetic')
                response = connection.getresponse()
                body = response.read()
                assert response.status == 410, method
                assert response.getheader('Cache-Control') == 'no-store'
                assert response.getheader('X-Content-Type-Options') == 'nosniff'
                assert response.getheader('Set-Cookie') is None
                assert response.getheader('Location') is None
                assert body == (b'' if method == 'HEAD' else b'This payment endpoint has been retired.')
                connection.close()
            print('Retired PDT boundary passed: five HTTP methods remain inert with synthetic configuration present.')
        finally:
            server.terminate()
            server.wait(timeout=5)
