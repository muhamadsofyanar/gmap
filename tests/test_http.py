"""Real MariaDB + HTTP integration. Uses a disposable *_test DB; see README."""
import http.cookiejar
import os
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')
CHECKS = 0


def check(condition, message):
    global CHECKS
    CHECKS += 1
    if not condition:
        raise AssertionError(message)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

    def request(self, path, data=None):
        if data is not None:
            data = urllib.parse.urlencode(data, doseq=True).encode()
        req = urllib.request.Request(self.base + path, data=data)
        try:
            response = self.opener.open(req, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode('utf-8-sig', errors='replace'), response.headers

    def token(self, path='/team'):
        status, body, _ = self.request(path)
        check(status == 200, 'token page: ' + path + ' ' + str(status))
        match = re.search(r'name="_csrf_token" value="([a-f0-9]+)"', body)
        check(match is not None, 'CSRF token exists')
        return match.group(1)

    def login(self, name, password='LongPassword123!'):
        token = self.token('/login')
        status, _, headers = self.request('/login', {
            '_csrf_token': token, 'identifier': name, 'password': password})
        check(status == 302, 'login redirects')
        return headers.get('Location')


def main():
    if os.environ.get('ALLOW_TEST_RESET') != '1' or not os.environ.get('DB_NAME', '').endswith('_test'):
        raise RuntimeError('Requires ALLOW_TEST_RESET=1 and disposable *_test database')
    subprocess.run([PHP, str(ROOT / 'tests/seed.php')], check=True)
    # A free loopback port for the test HTTP server; no public listener.
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    env = dict(os.environ, APP_ENV='development', APP_URL=base)
    with tempfile.TemporaryFile() as log:
        server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public'), str(ROOT / 'tests/router.php')], env=env, stdout=log, stderr=log)
        try:
            for _ in range(100):
                if server.poll() is not None:
                    raise RuntimeError('PHP HTTP server exited')
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.1):
                        break
                except OSError:
                    time.sleep(.05)
            guest = Client(base)
            check(guest.request('/team')[0] == 302, 'guest redirected')
            alice, bob, admin = Client(base), Client(base), Client(base)
            check(alice.login('alice').endswith('/team'), 'member login goes to team')
            bob.login('bob')
            check(admin.login('owner').endswith('/dashboard'), 'admin login goes to dashboard')
            a_token, b_token, root_token = alice.token(), bob.token(), admin.token()
            status, body, _ = alice.request('/team')
            check(status == 200 and 'Alice assigned business' in body and 'Bob secret business' not in body and 'Unassigned business' not in body, 'member list isolated')
            for path in ['/crm', '/projects', '/prospect/export', '/extras/directory', '/reports', '/sales', '/settings', '/templates', '/intake', '/reminders/counts', '/export', '/project/view/1']:
                check(alice.request(path)[0] == 403, 'legacy route blocked: ' + path)
            check(alice.request('/team/detail/11')[0] == 404, 'foreign direct lead denied')
            check(alice.request('/team/download/21')[0] == 404, 'foreign direct file denied')
            check('Bob secret business' not in alice.request('/team?q=Bob')[1], 'foreign search isolated')
            check('Bob secret business' not in alice.request('/team/export')[1], 'CSV isolated')
            check('SECRET-BOB' in bob.request('/team/download/21')[1], 'own attachment downloads')
            check(alice.request('/team/save', {'_csrf_token': a_token, 'lead_id': 11, 'status': 'closing', 'notes': 'attack', 'deal_value': '10'})[0] == 403, 'foreign mutation denied')
            check(alice.request('/team/completeTask', {'_csrf_token': a_token, 'lead_id': 10, 'task_id': 31})[0] == 403, 'mixed task ownership denied')
            check(alice.request('/team/deleteAttachment', {'_csrf_token': a_token, 'attachment_id': 21})[0] == 403, 'foreign file delete denied')
            check(alice.request('/team/assign', {'_csrf_token': a_token, 'lead_ids[]': [10], 'member_id': 3})[0] == 403, 'member assignment denied')
            check(alice.request('/team/createMember', {'_csrf_token': a_token, 'username': 'attacker', 'email': 'attack@example.test', 'name': 'Attack', 'password': 'StrongPassword123!'})[0] == 403, 'member account creation denied')
            check(alice.request('/team/save')[0] == 405, 'mutation GET rejected')
            check(alice.request('/team/save', {'lead_id': 10, 'status': 'lost', 'notes': 'no csrf', 'deal_value': '0'})[0] in (302,403), 'missing CSRF rejected')
            check(alice.request('/team/save', {'_csrf_token': a_token, 'lead_id': 10, 'status': 'contacted', 'notes': 'Alice updated safely', 'deal_value': '125000'})[0] == 302, 'own mutation succeeds')
            check('Alice updated safely' in alice.request('/team/detail/10')[1], 'own detail updated')
            check(alice.request('/profile')[0] == 200, 'member own profile loads')
            check(admin.request('/dashboard')[0] == 200, 'legacy admin dashboard works')
            check(admin.request('/team/assign', {'_csrf_token': root_token, 'lead_ids[]': [10], 'member_id': 3})[0] == 302, 'admin reassignment succeeds')
            check(alice.request('/team/detail/10')[0] == 404, 'old owner loses detail')
            check(alice.request('/team/download/20')[0] == 404, 'old owner loses download')
            check('Alice assigned business' not in alice.request('/team/export')[1], 'old owner loses export')
            check(bob.request('/team/detail/10')[0] == 200, 'new owner gains access')
            check(admin.request('/team/resetPassword', {'_csrf_token': root_token, 'member_id': 3, 'password': 'Replacement123!'})[0] == 302, 'admin password reset succeeds')
            check(bob.request('/team')[0] == 302, 'password reset revokes existing session')
            bob.login('bob', 'Replacement123!')
            check(admin.request('/team/disableMember', {'_csrf_token': root_token, 'member_id': 3})[0] == 302, 'admin deactivation succeeds')
            check(bob.request('/team')[0] == 302, 'deactivation revokes existing session')
            bob.login('bob', 'Replacement123!')
            check(bob.request('/team')[0] == 302, 'disabled member cannot login')
            # Change only the DB role, keeping the existing session cookie and version.
            code = "require " + repr(str(ROOT / 'scripts/bootstrap.php')) + r'''; \App\Core\Database::getInstance()->exec("UPDATE users SET role='user' WHERE id=1");'''
            subprocess.run([PHP, '-r', code], env=env, check=True)
            check(admin.request('/crm')[0] == 403, 'DB role overrides cached admin session')
            print(f'PASS: {CHECKS} HTTP checks')
        except Exception:
            log.seek(0)
            print(log.read().decode(errors='replace')[-8000:])
            raise
        finally:
            server.terminate()
            server.wait(timeout=10)


if __name__ == '__main__':
    main()
