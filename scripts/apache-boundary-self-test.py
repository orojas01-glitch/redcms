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
compiled = subprocess.check_output([httpd, '-l'], text=True)
module_lines = []
for name in ['mpm_prefork', 'unixd', 'authz_core', 'authz_host', 'access_compat', 'rewrite', 'headers']:
    builtin_names = [f'mod_{name}.c']
    if name == 'mpm_prefork':
        builtin_names.append('prefork.c')
    if any(builtin in compiled.split() for builtin in builtin_names):
        continue
    library = modules / f'mod_{name}.so'
    if not library.is_file():
        raise RuntimeError(f'Required Apache module is neither built-in nor available: {name}')
    module_lines.append(f'LoadModule {name}_module "{library}"')
with tempfile.TemporaryDirectory(prefix='redcms-apache-boundary-') as temp:
    folder = pathlib.Path(temp)
    web = folder / 'www'
    web.mkdir()
    (web / '.htaccess').write_bytes((root / '.htaccess').read_bytes())
    (web / 'ok.txt').write_text('public fixture')
    (web / 'php.ini').write_text('; synthetic per-directory PHP settings')
    (web / 'images/articles').mkdir(parents=True)
    (web / 'images/gallery').mkdir()
    (web / 'images/.htaccess').write_bytes((root / 'images/.htaccess').read_bytes())
    # No PHP interpreter is attached: these are harmless access-control sentinels.
    for relative in ['resize.php', 'probe.php', 'articles/resize.php', 'gallery/probe.phtml', 'articles/probe.php.jpg', 'articles/probe.php83', 'articles/photo.png']:
        (web / 'images' / relative).write_text('synthetic image boundary fixture')
    challenge = web / '.well-known/acme-challenge/test'
    challenge.parent.mkdir(parents=True)
    challenge.write_text('synthetic validation')
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    config = '\n'.join(module_lines)
    config += f'''
ServerRoot "{folder}"
DefaultRuntimeDir "{folder}"
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
    validation = subprocess.run([httpd, '-f', str(conf), '-t'], capture_output=True, text=True)
    if validation.returncode:
        raise RuntimeError('Synthetic Apache configuration rejected: ' + validation.stderr.strip())
    startup = (folder / 'startup.log').open('w')
    server = subprocess.Popen([httpd, '-f', str(conf), '-DFOREGROUND'], stdout=subprocess.DEVNULL, stderr=startup, start_new_session=True)
    def startup_failure():
        # The server serves only synthetic files; these diagnostics contain no
        # repository credentials, user sessions or workstation configuration.
        messages = []
        for name in ['startup.log', 'error.log']:
            path = folder / name
            if path.is_file():
                messages.append(path.read_text()[-2000:])
        return RuntimeError('Synthetic Apache fixture failed to start: ' + '\n'.join(messages))
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
                if server.poll() is not None:
                    raise startup_failure()
                time.sleep(.05)
        else:
            raise startup_failure()
        assert status == 200, 'public fixture must remain available'
        assert headers.get('X-Content-Type-Options') == 'nosniff'
        for path in ['/.git/config', '/.env', '/php.ini', '/database/test.sql', '/scripts/test.php', '/deployments/test.php', '/includes/config.local.php', '/synthetic.xlsx', '/synthetic.pem']:
            assert get(path)[0] == 403, 'protected path not denied: ' + path
        assert get('/.well-known/acme-challenge/test')[0] == 200
        for path in ['/images/probe.php', '/images/articles/resize.php', '/images/gallery/probe.phtml', '/images/articles/probe.php.jpg', '/images/articles/probe.php83']:
            assert get(path)[0] == 403, 'uploaded executable path not denied: ' + path
        assert get('/images/resize.php')[0] == 200, 'shipped thumbnail endpoint must remain reachable'
        assert get('/images/articles/photo.png')[0] == 200, 'ordinary uploaded image must remain reachable'
        print('Apache boundary passed: 14 protected paths denied; public, ACME, thumbnail and image paths served; nosniff present.')
    finally:
        if server.poll() is None:
            server.terminate()
        server.wait(timeout=5)
        startup.close()
