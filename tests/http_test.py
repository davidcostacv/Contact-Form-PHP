"""HTTP regression tests using only Python's standard library and PHP CLI."""
import http.cookiejar
import os
from pathlib import Path
import re
import shlex
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


class ContactHTTP(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        if not (ROOT / 'public/index.php').is_file():
            raise AssertionError('Missing public contact form controller')
        cls.temp = tempfile.TemporaryDirectory()
        cls.fixture = Path(cls.temp.name)
        shutil.copytree(ROOT / 'public', cls.fixture / 'public')
        shutil.copytree(ROOT / 'src', cls.fixture / 'src')
        # Capture mail locally; tests never contact an SMTP service or real mailbox.
        (cls.fixture / 'capture_mail.php').write_text(
            "<?php $data=stream_get_contents(STDIN); "
            "if(is_file(__DIR__.'/fail-mail')){exit(1);} "
            "file_put_contents(__DIR__.'/mail.eml',$data);", encoding='utf-8')
        php = os.environ.get('PHP_BINARY', 'php')
        cls.php_version = int(subprocess.check_output([php, '-r', 'echo PHP_VERSION_ID;']))
        sendmail = f'"{php}" "{cls.fixture / "capture_mail.php"}"'
        if os.name == 'nt':
            sendmail = f'"{sendmail}"'  # cmd.exe preserves both quoted paths.
        else:
            # -d values use PHP's INI parser: quote the whole command so spaces survive.
            sendmail = '"' + shlex.join([php, str(cls.fixture / 'capture_mail.php')]) + '"'
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        cls.url = f'http://127.0.0.1:{port}/'
        cls.log = open(cls.fixture / 'server.log', 'w')
        cls.server = subprocess.Popen([
            php, '-d', f'sendmail_path={sendmail}', '-S', f'127.0.0.1:{port}',
            '-t', str(cls.fixture / 'public'),
        ], stdout=cls.log, stderr=cls.log)
        for _ in range(100):
            try:
                urllib.request.urlopen(cls.url, timeout=1).close()
                return
            except (OSError, urllib.error.URLError):
                time.sleep(.05)
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.log.close()
        cls.temp.cleanup()
        raise AssertionError('PHP server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.log.close()
        cls.temp.cleanup()

    def setUp(self):
        (self.fixture / 'config.php').write_text("<?php return ['mode' => 'demo'];", encoding='utf-8')
        (self.fixture / 'fail-mail').unlink(missing_ok=True)
        (self.fixture / 'mail.eml').unlink(missing_ok=True)
        self.client = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.status, self.page, _ = self.request()
        self.token = re.search(r'name="csrf" value="([a-f0-9]+)"', self.page).group(1)

    def request(self, fields=None, path='', method=None):
        data = urllib.parse.urlencode(fields).encode() if fields is not None else None
        request = urllib.request.Request(self.url + path, data=data, method=method)
        try:
            response = self.client.open(request, timeout=5)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.read().decode(), response.headers

    def fields(self, **changes):
        fields = dict(name='David Costa', email='david@example.org', subject='Consulta',
                      message='Hola, quiero conocer el proyecto.', csrf=self.token, website='')
        fields.update(changes)
        return fields

    def test_form_and_security_headers(self):
        self.assertEqual(self.status, 200)
        self.assertIn('Modo demostración', self.page)
        _, _, headers = self.request()
        self.assertIn("default-src 'self'", headers['Content-Security-Policy'])
        self.assertIn('no-store', headers['Cache-Control'])
        self.assertIn('HttpOnly', headers['Set-Cookie'] if headers.get('Set-Cookie') else self.request_with_new_client_cookie())
        self.assertEqual(self.request(path='style.css')[0], 200)
        self.assertEqual(self.request(path='../config.php')[0], 404)

    def request_with_new_client_cookie(self):
        with urllib.request.urlopen(self.url) as response:
            return response.headers.get('Set-Cookie', '')

    def test_required_email_and_escaping(self):
        status, page, _ = self.request(self.fields(email='bad', name='<script>alert(1)</script>'))
        self.assertEqual(status, 422)
        self.assertIn('correo electrónico válido', page)
        self.assertIn('&lt;script&gt;', page)
        self.assertNotIn('<script>alert(1)</script>', page)
        self.assertIn('aria-invalid="true"', page)

    def test_csrf_and_honeypot(self):
        self.assertEqual(self.request(self.fields(csrf='wrong'))[0], 403)
        self.assertEqual(self.request(self.fields(website='spam'))[0], 422)
        self.assertEqual(self.request(self.fields(**{'email[]': 'bad'}, email=''))[0], 422)

    def test_success_redirect_token_rotation_and_throttle(self):
        status, page, _ = self.request(self.fields())
        self.assertEqual(status, 200)  # urllib follows the 303 redirect.
        self.assertIn('no se ha enviado ningún correo', page)
        new_token = re.search(r'name="csrf" value="([a-f0-9]+)"', page).group(1)
        self.assertNotEqual(new_token, self.token)
        self.assertEqual(self.request(self.fields())[0], 403)
        self.assertEqual(self.request(self.fields(csrf=new_token))[0], 429)
        self.assertNotIn('no se ha enviado ningún correo', self.request()[1].split('role="status"')[-1])

    def test_mail_failure(self):
        if os.name == 'nt' and self.php_version < 80500:
            self.skipTest('Windows PHP <8.5 does not detect a failed sendmail exit; unit failure coverage remains active')
        (self.fixture / 'fail-mail').touch()
        (self.fixture / 'config.php').write_text(
            "<?php return ['mode'=>'mail','to'=>'owner@example.org','from'=>'website@example.org'];", encoding='utf-8')
        status, page, _ = self.request(self.fields())
        self.assertEqual(status, 503)
        self.assertIn('No pudimos enviar', page)
        self.assertNotIn('SMTP', page)

    def test_configuration_failure(self):
        (self.fixture / 'config.php').write_text("<?php throw new RuntimeException('secret');", encoding='utf-8')
        status, page, _ = self.request()
        self.assertEqual(status, 503)
        self.assertNotIn('secret', page)

    def test_real_mail_function_with_local_capture(self):
        (self.fixture / 'config.php').write_text(
            "<?php return ['mode'=>'mail','to'=>'owner@example.org','from'=>'website@example.org'];", encoding='utf-8')
        status, page, _ = self.request(self.fields())
        self.assertEqual(status, 200, (self.fixture / 'server.log').read_text())
        self.assertIn('aceptado para envío', page)
        captured = (self.fixture / 'mail.eml').read_text(encoding='utf-8')
        self.assertIn('Reply-To: david@example.org', captured)
        self.assertIn('From: website@example.org', captured)
        self.assertIn('David Costa', captured)

    def test_method_not_allowed(self):
        status, _, headers = self.request(method='PUT')
        self.assertEqual(status, 405)
        self.assertEqual(headers['Allow'], 'GET, POST')


if __name__ == '__main__':
    unittest.main(verbosity=2)
