"""Disposable CI: real MFA/session HTTP and competing one-use proof checks."""
from __future__ import annotations

import base64
import concurrent.futures
import hashlib
import hmac
import json
import os
import re
import signal
import struct
import subprocess
import time
import urllib.error
import uuid
from pathlib import Path

import account_http_smoke as http

http.BASE = "http://127.0.0.1:8767"


def totp(secret: str, counter: int) -> str:
    mac = hmac.new(base64.b32decode(secret), struct.pack(">Q", counter), hashlib.sha1).digest()
    offset = mac[-1] & 15
    number = struct.unpack(">I", mac[offset:offset + 4])[0] & 0x7fffffff
    return str(number % 1000000).zfill(6)


def concurrent_proof(address: str, code: str, recovery: bool, env: dict) -> None:
    source = r'''
require 'vendor/autoload.php';
$a = require 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('CI') !== 'true' || !$a->environment('local')) { exit(4); }
echo "READY\n"; flush();
$d = json_decode(fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!str_starts_with($d['email'] ?? '', 'mfa-http-') || !str_ends_with($d['email'], '@example.test')) { exit(5); }
$u = App\Models\User::query()->where('email', $d['email'])->firstOrFail();
try { $a->make(App\Services\MfaService::class)->challenge($u, $d['code'], $d['recovery']); echo 'true'; }
catch (Illuminate\Validation\ValidationException $e) { echo 'false'; }
'''
    workers = [subprocess.Popen(["php", "-r", source], env=env, stdin=subprocess.PIPE,
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True) for _ in range(2)]
    try:
        for worker in workers:
            assert worker.stdout.readline().strip() == "READY", "MFA worker boot failed"
        for worker in workers:
            worker.stdin.write(json.dumps({"email": address, "code": code, "recovery": recovery}) + "\n")
            worker.stdin.flush()
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            results = list(pool.map(lambda worker: worker.communicate(timeout=20), workers))
        assert all(worker.returncode == 0 for worker in workers), "MFA concurrency worker failed"
        assert sorted(json.loads(result[0]) for result in results) == [False, True], "Proof was not single-use"
    finally:
        for worker in workers:
            if worker.poll() is None:
                worker.kill()
                worker.wait()


def main() -> None:
    assert os.environ.get("CI") == "true", "Disposable CI only"
    env = {**os.environ, "APP_ENV": "local", "APP_DEBUG": "false", "APP_URL": http.BASE,
           "CMS_ACCOUNT_MAIL_ENABLED": "false", "CACHE_STORE": "file", "SESSION_DRIVER": "file",
           "SESSION_SECURE_COOKIE": "false"}
    Path("build").mkdir(exist_ok=True)
    started = time.monotonic()
    with open("build/mfa-server.log", "w") as output:
        server = subprocess.Popen(["php", "artisan", "serve", "--host=127.0.0.1", "--port=8767"],
                                  env=env, stdout=output, stderr=output, start_new_session=True)
        try:
            first, old, next_client = http.client(), http.client(), http.client()
            for _ in range(50):
                try:
                    if http.request(first, "/up")[0] == 200:
                        break
                except urllib.error.URLError:
                    pass
                time.sleep(0.1)
            else:
                raise RuntimeError("MFA HTTP server failed")
            address = f"mfa-http-{uuid.uuid4().hex}@example.test"
            password = "ExamplePassword123"
            assert http.submit(first, "/register", {"name": "MFA HTTP fixture", "email": address,
                "password": password, "password_confirmation": password}, form="/register")[0] == 200
            assert http.submit(old, "/login", {"email": address, "password": password})[0] == 200
            assert http.request(first, "/account/mfa/enroll", {"password": password})[0] == 419
            response = http.submit(first, "/account/mfa/enroll", {"password": password}, form="/account/mfa")
            assert response[0] == 200, "MFA enrollment failed"
            secret = re.search(r'<p class="mono">([A-Z2-7]{32})</p>', response[1]).group(1)
            response = http.submit(first, "/account/mfa/confirm", {"password": password,
                "code": totp(secret, int(time.time()) // 30)}, form="/account/mfa")
            assert response[0] == 200, "MFA confirmation failed"
            codes = re.findall(r"[0-9a-f]{8}(?:-[0-9a-f]{8}){3}", response[1])
            assert len(set(codes)) == 10, "Recovery code display invalid"
            assert all(code not in http.request(first, "/account/mfa")[1] for code in codes), "Recovery codes redisplayed"
            assert http.request(old, "/account", headers={"Accept": "application/json"})[0] == 401, "Pre-enrollment session survived"
            signed_in = http.submit(next_client, "/login", {"email": address, "password": password})
            assert signed_in[3].endswith("/account/mfa/challenge"), "Password bypassed MFA"
            assert http.request(next_client, "/account", headers={"Accept": "application/json"})[0] == 403
            assert http.request(next_client, "/account/mfa/challenge", {"code": codes[0], "recovery": 1})[0] == 419
            assert http.submit(next_client, "/account/mfa/challenge", {"code": codes[0], "recovery": 1},
                form="/account/mfa/challenge")[3].endswith("/account")
            sessions = http.request(next_client, "/account/sessions")[1]
            current = re.search(r"This session</td>.*?action=\"/account/sessions/([a-f0-9-]+)/revoke\"", sessions, re.S).group(1)
            assert http.request(first, "/account/sessions/" + current + "/revoke", {"password": password})[0] == 419
            assert http.submit(first, "/account/sessions/" + current + "/revoke", {"password": password}, form="/account/sessions")[0] == 200
            assert http.request(next_client, "/account", headers={"Accept": "application/json"})[0] == 401
            assert http.request(first, "/account")[0] == 200
            assert http.submit(next_client, "/login", {"email": address, "password": password})[3].endswith("/account/mfa/challenge")
            assert http.submit(next_client, "/account/mfa/challenge", {"code": codes[0], "recovery": 1},
                form="/account/mfa/challenge", Accept="application/json")[0] == 422
            concurrent_proof(address, codes[1], True, env)
            concurrent_proof(address, totp(secret, int(time.time()) // 30 + 1), False, env)
            Path("build/mfa-http.json").write_text(json.dumps({"status": "passed", "checks": [
                "http_enrollment", "enrollment_csrf", "one_time_recovery_display", "password_mfa_boundary",
                "challenge_csrf", "session_revoke_csrf", "cross_session_revocation", "recovery_replay",
                "concurrent_recovery", "concurrent_totp"], "duration_seconds": round(time.monotonic() - started, 3)}) + "\n")
            print("PASS: MFA HTTP/CSRF, separate sessions, replay and concurrent TOTP/recovery consumption")
        finally:
            if server.poll() is None:
                os.killpg(server.pid, signal.SIGTERM)
                server.wait(timeout=10)


if __name__ == "__main__":
    main()
