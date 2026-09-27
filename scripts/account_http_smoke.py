"""CI-only account checks using a loopback HTTP server and in-memory SMTP sink."""
from __future__ import annotations

import concurrent.futures
import email.policy
import html
import http.cookiejar
import json
import os
import queue
import re
import signal
import socketserver
import subprocess
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from email.parser import BytesParser
from pathlib import Path

BASE = "http://127.0.0.1:8766"
MESSAGES: queue.Queue[bytes] = queue.Queue()


class SMTPHandler(socketserver.StreamRequestHandler):
    def handle(self) -> None:
        self.connection.settimeout(15)
        self.wfile.write(b"220 localhost test SMTP\r\n")
        while True:
            line = self.rfile.readline(8192)
            if not line:
                return
            command = line.split(b" ", 1)[0].strip().upper()
            if command in (b"EHLO", b"HELO"):
                self.wfile.write(b"250 localhost\r\n")
            elif command == b"DATA":
                self.wfile.write(b"354 End with a dot\r\n")
                parts = []
                total = 0
                while True:
                    part = self.rfile.readline(8192)
                    if part in (b".\r\n", b".\n"):
                        break
                    if not part:
                        return
                    total += len(part)
                    if total > 262144:
                        raise RuntimeError("Test message exceeds limit")
                    parts.append(part[1:] if part.startswith(b"..") else part)
                MESSAGES.put(b"".join(parts))
                self.wfile.write(b"250 Accepted\r\n")
            elif command == b"QUIT":
                self.wfile.write(b"221 Bye\r\n")
                return
            else:
                self.wfile.write(b"250 OK\r\n")


class SMTPServer(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


def client() -> urllib.request.OpenerDirector:
    return urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(opener, path: str, data: dict | None = None, headers: dict | None = None):
    if not path.startswith("/") or path.startswith("//"):
        raise RuntimeError("Test requests must stay on the fixed loopback server")
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, headers=headers or {})
    try:
        response = opener.open(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read().decode(), response.headers, response.geturl()


def csrf(opener, path="/login") -> str:
    status, body, _, _ = request(opener, path)
    assert status == 200, "CSRF form unavailable"
    match = re.search(r'name="_token"[^>]*value="([^"]+)"', body)
    assert match, "CSRF field missing"
    return html.unescape(match.group(1))


def submit(opener, path, data, form="/login", **headers):
    return request(opener, path, {**data, "_token": csrf(opener, form)}, headers)


def delivered_path(purpose: str, env: dict) -> str:
    worker = subprocess.run(["php", "artisan", "queue:work", "database", "--queue=account-mail", "--stop-when-empty", "--max-jobs=10", "--tries=1", "--timeout=20", "--quiet"], env=env, capture_output=True, timeout=45)
    assert worker.returncode == 0, "Account mail worker failed"
    message = BytesParser(policy=email.policy.default).parsebytes(MESSAGES.get(timeout=5))
    part = message.get_body(preferencelist=("plain", "html"))
    assert part, "No readable mail body"
    text = html.unescape(part.get_content())
    prefix = "/email/verify/" if purpose == "verify" else "/reset-password?token="
    match = re.search(re.escape(BASE + prefix) + r'[^\s<>"\)]+', text)
    assert match, "Canonical account link missing from delivered mail"
    return match.group(0)[len(BASE):]


def concurrent_redemption(credentials: dict, env: dict) -> None:
    code = r'''
require 'vendor/autoload.php';
$a = require 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('CI') !== 'true' || !$a->environment('local')) { exit(4); }
echo "READY\n"; flush();
$d = json_decode(fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!str_starts_with($d['email'] ?? '', 'account-http-') || !str_ends_with($d['email'], '@example.test')) { exit(5); }
echo json_encode($a->make(App\Services\AccountSecurityService::class)->reset($d));
'''
    workers = [subprocess.Popen(["php", "-r", code], env=env, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True) for _ in range(2)]
    try:
        for worker in workers:
            assert worker.stdout.readline().strip() == "READY", "Reset test worker could not boot"
        # Both applications are booted before either receives the same synthetic token.
        for worker in workers:
            worker.stdin.write(json.dumps(credentials) + "\n")
            worker.stdin.flush()
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            outputs = list(pool.map(lambda worker: worker.communicate(timeout=20), workers))
        assert all(worker.returncode == 0 for worker in workers), "Concurrent reset raised an unexpected error"
        assert sorted(json.loads(output[0]) for output in outputs) == [False, True], "Reset token was not consumed exactly once"
    finally:
        for worker in workers:
            if worker.poll() is None:
                worker.kill()
                worker.wait()


def main() -> None:
    assert os.environ.get("CI") == "true", "Run only in disposable CI infrastructure"
    env = {**os.environ, "APP_ENV": "local", "APP_DEBUG": "false", "APP_URL": BASE, "CMS_ACCOUNT_MAIL_ENABLED": "true", "MAIL_MAILER": "smtp", "MAIL_SCHEME": "smtp", "MAIL_HOST": "127.0.0.1", "MAIL_PORT": "1025", "MAIL_USERNAME": "", "MAIL_PASSWORD": "", "MAIL_FROM_ADDRESS": "noreply@example.test", "CACHE_STORE": "file", "SESSION_DRIVER": "file", "SESSION_SECURE_COOKIE": "false"}
    smtp = SMTPServer(("127.0.0.1", 1025), SMTPHandler)
    threading.Thread(target=smtp.serve_forever, daemon=True).start()
    Path("build").mkdir(exist_ok=True)
    started = time.monotonic()
    with open("build/account-server.log", "w") as server_log:
        server = subprocess.Popen(["php", "artisan", "serve", "--no-reload", "--host=127.0.0.1", "--port=8766"], env=env, stdout=server_log, stderr=server_log, start_new_session=True)
        try:
            first, second, recovery = client(), client(), client()
            for _ in range(50):
                try:
                    if request(first, "/up")[0] == 200:
                        break
                except urllib.error.URLError:
                    pass
                time.sleep(0.1)
            else:
                raise RuntimeError("Local account test server did not start")
            email_address = f"account-http-{uuid.uuid4().hex}@example.test"
            original = "ExamplePassword123"
            signup = submit(first, "/register", {"name": "Account HTTP fixture", "email": email_address, "password": original, "password_confirmation": original}, form="/register")
            assert signup[0] == 200 and signup[3].endswith("/account"), "Registration or session stamp failed"
            signed_in = submit(second, "/login", {"email": email_address, "password": original})
            assert signed_in[0] == 200 and signed_in[3].endswith("/account"), "Second session login failed"
            assert request(first, "/email/verification-notification", {})[0] == 419, "Verification write accepted missing CSRF"
            assert submit(first, "/email/verification-notification", {}, form="/account/security")[0] == 200
            verify_path = delivered_path("verify", env)
            verified = request(first, verify_path)
            assert verified[0] == 200 and "Email address verified." in verified[1], "Delivered verification link failed"
            assert request(first, verify_path + "x")[0] == 403, "Tampered signature accepted"
            assert request(recovery, "/forgot-password", {"email": email_address})[0] == 419, "Recovery write accepted missing CSRF"
            unknown = submit(recovery, "/forgot-password", {"email": "absent-http@example.test"}, form="/forgot-password", Accept="application/json")
            known = submit(recovery, "/forgot-password", {"email": email_address}, form="/forgot-password", Accept="application/json")
            assert unknown[0] == known[0] == 202 and unknown[1] == known[1], "Recovery request reveals account existence"
            reset_path = delivered_path("reset", env)
            reset_token = urllib.parse.parse_qs(urllib.parse.urlsplit(reset_path).query)["token"][0]
            data = {"email": email_address, "token": reset_token, "password": "NewExamplePassword456", "password_confirmation": "NewExamplePassword456"}
            assert request(recovery, "/reset-password", data)[0] == 419, "Reset write accepted missing CSRF"
            reset = submit(recovery, "/reset-password", data, form=reset_path)
            assert reset[0] == 200 and reset[3].endswith("/login"), "Reset did not require fresh authentication"
            assert request(first, "/account", headers={"Accept": "application/json"})[0] == 401, "First old session survived reset"
            assert request(second, "/account", headers={"Accept": "application/json"})[0] == 401, "Second old session survived reset"
            assert submit(recovery, "/reset-password", data, form=reset_path, Accept="application/json")[0] == 422, "Reset token replay succeeded"
            assert submit(second, "/login", {"email": email_address, "password": original}, Accept="application/json")[0] == 422, "Old password still works"
            login = submit(second, "/login", {"email": email_address, "password": data["password"]})
            assert login[0] == 200 and login[3].endswith("/account"), "New password login failed"
            assert request(second, "/studio")[0] == 403, "Recovery changed member privileges"
            assert request(recovery, "/forgot-password", headers={"Host": "untrusted.example"})[0] == 400, "Untrusted Host accepted"
            assert request(second, "/account/security")[2].get("Referrer-Policy") == "no-referrer"
            print("PASS: loopback SMTP delivery, signed verification, reset CSRF/replay, two-session revocation and Host rejection")
            # A separate local fixture creates a second token without printing it to CI logs.
            code = r"require 'vendor/autoload.php'; $a=require 'bootstrap/app.php'; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if(getenv('CI')!=='true'||!$a->environment('local')){exit(4);} $e=trim(fgets(STDIN)); if(!str_starts_with($e,'account-http-')||!str_ends_with($e,'@example.test')){exit(5);} $u=App\Models\User::query()->where('email',$e)->firstOrFail(); echo Illuminate\Support\Facades\Password::broker()->createToken($u);"
            token_process = subprocess.run(["php", "-r", code], input=email_address + "\n", env=env, capture_output=True, text=True, timeout=10)
            assert token_process.returncode == 0, "Concurrent reset fixture failed"
            concurrent_redemption({**data, "token": token_process.stdout.strip()}, env)
            result = {"status": "passed", "checks": ["local_smtp", "verification", "csrf", "token_replay", "two_session_revocation", "host_rejection", "concurrent_single_use"], "duration_seconds": round(time.monotonic() - started, 3)}
            Path("build/account-http.json").write_text(json.dumps(result) + "\n")
            print("PASS: two independent processes consumed one reset token exactly once")
        finally:
            if server.poll() is None:
                os.killpg(server.pid, signal.SIGTERM)
                server.wait(timeout=10)
            smtp.shutdown()
            smtp.server_close()


if __name__ == "__main__":
    main()
