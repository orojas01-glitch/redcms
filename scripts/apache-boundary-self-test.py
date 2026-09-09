#!/usr/bin/env python3
"""Exercise shipped Apache rules on synthetic files, never operator files."""
import http.client
import os
import pathlib
import socket
import subprocess
import tempfile
import time

root = pathlib.Path(__file__).resolve().parents[1]
httpd = os.environ.get('RED_HTTPD_BIN', '/usr/sbin/httpd')
modules = pathlib.Path(os.environ.get('RED_APACHE_MODULES', '/usr/libexec/apache2'))
with tempfile.TemporaryDirectory(prefix='redcms-apache-boundary-') as temp:
    folder = pathlib.Path(temp)
    web = folder / 'www'
    web.mkdir()
    (web / '.htaccess').write_bytes((root / '.htaccess').read_bytes())
    (web / 'ok.txt').write_text('public fixture')
    challenge = web / '.well-known/acme-challenge/test'
    challenge.parent.mkdir(parents=True)
    challenge.write_text('synthetic validation')
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    config = '\n'.join(f'LoadModule {name}_module "{modules}/mod_{name}.so"' for name in ['mpm_prefork', 'unixd', 'authz_core', 'authz_host', 'access_compat', 'rewrite', 'headers'])
    config += f'''
ServerRoot "{folder}"
PidFile "{folder}/httpd.pid"
Listen 127.0.0.1:{port}
ServerName localhost
ErrorLog "{folder}/error.log"
DocumentRoot "{web}"
<Directory "{web}">
    AllowOverride All
    Require all granted
</Directory>
'''
    conf = folder / 'httpd.conf'
    conf.write_text(config)
    server = subprocess.Popen([httpd, '-f', str(conf), '-DFOREGROUND'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    def get(path):
        c = http.client.HTTPConnection('127.0.0.1', port, timeout=3)
        c.request('GET', path)
        r = c.getresponse()
        status, headers = r.status, dict(r.getheaders())
        r.read(); c.close()
        return status, headers
    try:
        for _ in range(60):
            try:
                status, headers = get('/ok.txt')
                break
            except ConnectionRefusedError:
                time.sleep(.05)
        else:
            raise RuntimeError('Apache fixture failed to start')
        assert status == 200, 'public fixture must remain available'
        assert headers.get('X-Content-Type-Options') == 'nosniff'
        for path in ['/.git/config', '/.env', '/database/test.sql', '/scripts/test.php', '/deployments/test.php', '/includes/config.local.php', '/synthetic.xlsx', '/synthetic.pem']:
            assert get(path)[0] == 403, 'protected path not denied: ' + path
        assert get('/.well-known/acme-challenge/test')[0] == 200
        print('Apache boundary passed: 8 protected paths denied; public and ACME files served; nosniff present.')
    finally:
        server.terminate(); server.wait(timeout=5)
