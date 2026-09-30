"""Disposable CI-only private media workflow, real HTTP and media queue execution."""
from __future__ import annotations

import base64
import hashlib
import hmac
import html
import json
import os
import re
import signal
import struct
import subprocess
import time
import urllib.error
import urllib.request
import uuid
import zlib
from pathlib import Path

import account_http_smoke as http

http.BASE = "http://127.0.0.1:8768"


def png() -> bytes:
    def chunk(kind: bytes, value: bytes) -> bytes:
        return struct.pack(">I", len(value)) + kind + value + struct.pack(">I", zlib.crc32(kind + value) & 0xffffffff)
    data = (b"\x00" + b"\x90\x90\x90" * 64) * 48
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", 64, 48, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(data)) + chunk(b"IEND", b"")


def code(secret: str) -> str:
    mac = hmac.new(base64.b32decode(secret), struct.pack(">Q", int(time.time()) // 30), hashlib.sha1).digest()
    offset = mac[-1] & 15
    return str((struct.unpack(">I", mac[offset:offset + 4])[0] & 0x7fffffff) % 1000000).zfill(6)


def fixture(env: dict, action: str, identifier: str, post_id: int = 0) -> dict:
    php = r'''
require 'vendor/autoload.php';
$a = require 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('CI') !== 'true' || !$a->environment('local')) { exit(9); }
$d = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/\Amedia-http-[a-f0-9]{32}\z/', $d['identifier'])) { exit(9); }
$email = $d['identifier'].'@example.test';
if ($d['action'] === 'create') {
 $owner = new App\Models\User(['name' => 'Media owner fixture', 'email' => $email, 'password' => 'ExamplePassword123']);
 $owner->is_admin = true; $owner->save();
 App\Models\User::query()->create(['name' => 'Media reader fixture', 'email' => 'reader-'.$email, 'password' => 'ExamplePassword123']);
 $post = App\Models\Post::query()->create(['title' => 'HTTP media fixture', 'body' => 'HTTP media test body', 'status' => 'published', 'classification' => 'general', 'price_units' => 20000000]);
 echo json_encode(['post_id' => $post->id]);
} elseif ($d['action'] === 'grant' || $d['action'] === 'revoke') {
 $reader = App\Models\User::query()->where('email', 'reader-'.$email)->firstOrFail();
 if ($d['action'] === 'grant') { (new App\Models\Entitlement)->forceFill(['user_id' => $reader->id, 'post_id' => $d['post_id']])->save(); }
 else { App\Models\Entitlement::query()->where('user_id', $reader->id)->where('post_id', $d['post_id'])->update(['revoked_at' => now()]); }
 echo '{}';
} else {
 $asset = App\Models\MediaAsset::query()->where('post_id', $d['post_id'])->where('state', '!=', 'deleted')->firstOrFail();
 echo json_encode(['asset_id' => $asset->id, 'state' => $asset->state, 'kind' => $asset->kind]);
}
'''
    result = subprocess.run(["php", "-r", php], input=json.dumps({"action": action, "identifier": identifier, "post_id": post_id}), env=env, text=True, capture_output=True, timeout=15)
    assert result.returncode == 0, "Media fixture setup failed"
    return json.loads(result.stdout)


def upload(opener, path: str, data: bytes, filename: str, mime: str, token: str | None):
    boundary = "tb-media-" + uuid.uuid4().hex
    fields = {"alt_text": "PRIVATE_ALT_SENTINEL"}
    if token is not None:
        fields["_token"] = token
    body = b""
    for name, value in fields.items():
        body += (f"--{boundary}\r\nContent-Disposition: form-data; name=\"{name}\"\r\n\r\n{value}\r\n").encode()
    body += (f"--{boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{filename}\"\r\nContent-Type: {mime}\r\n\r\n").encode() + data + f"\r\n--{boundary}--\r\n".encode()
    request = urllib.request.Request(http.BASE + path, data=body, headers={"Content-Type": "multipart/form-data; boundary=" + boundary})
    try:
        result = opener.open(request, timeout=15)
    except urllib.error.HTTPError as error:
        result = error
    with result:
        return result.status, result.read().decode()


def binary(opener, path: str, headers: dict | None = None):
    assert path.startswith("/media/"), "Media URL escaped expected route"
    try:
        result = opener.open(urllib.request.Request(http.BASE + path, headers=headers or {}), timeout=15)
    except urllib.error.HTTPError as error:
        result = error
    with result:
        return result.status, result.read(), result.headers


def media_url(opener, post_id: int) -> str:
    page = http.request(opener, f"/posts/{post_id}")
    assert page[0] == 200
    match = re.search(r'src="(/media/[^\"]+/content\?[^\"]+)"', page[1])
    assert match, "Authorized media link was not rendered"
    return html.unescape(match[1])


def worker(env: dict):
    result = subprocess.run(["php", "artisan", "queue:work", "media", "--queue=media", "--stop-when-empty", "--max-jobs=5", "--tries=1", "--timeout=240", "--quiet"], env=env, capture_output=True, timeout=260)
    assert result.returncode == 0, "Media queue worker failed"


def main() -> None:
    assert os.environ.get("CI") == "true", "Disposable CI only"
    env = {**os.environ, "APP_ENV": "local", "APP_DEBUG": "false", "APP_URL": http.BASE,
           "CMS_MEDIA_ENABLED": "true", "CMS_MEDIA_VIDEO_ENABLED": "true", "CMS_ACCOUNT_MAIL_ENABLED": "false",
           "SESSION_DRIVER": "file", "SESSION_SECURE_COOKIE": "false"}
    Path("build").mkdir(exist_ok=True)
    started = time.monotonic()
    identifier = "media-http-" + uuid.uuid4().hex
    post_id = fixture(env, "create", identifier)["post_id"]
    owner, reader, guest = http.client(), http.client(), http.client()
    with open("build/media-server.log", "w") as output:
        server = subprocess.Popen(["php", "artisan", "serve", "--no-reload", "--host=127.0.0.1", "--port=8768"], env=env, stdout=output, stderr=output, start_new_session=True)
        try:
            for _ in range(50):
                try:
                    if http.request(owner, "/up")[0] == 200:
                        break
                except urllib.error.URLError:
                    pass
                time.sleep(0.1)
            else:
                raise RuntimeError("Media test HTTP server unavailable")
            email = identifier + "@example.test"
            assert http.submit(owner, "/login", {"email": email, "password": "ExamplePassword123"})[0] == 200
            page = http.submit(owner, "/account/mfa/enroll", {"password": "ExamplePassword123"}, form="/account/mfa")
            key = re.search(r'<p class="mono">([A-Z2-7]{32})</p>', page[1]).group(1)
            assert http.submit(owner, "/account/mfa/confirm", {"password": "ExamplePassword123", "code": code(key)}, form="/account/mfa")[0] == 200
            management = f"/studio/posts/{post_id}/media"
            assert upload(owner, management, png(), "fixture.png", "image/png", None)[0] == 419, "Upload bypassed CSRF"
            assert upload(owner, management, png(), "fixture.png", "image/png", http.csrf(owner, management))[0] == 200
            asset = fixture(env, "inspect", identifier, post_id)
            assert asset["state"] == "queued"
            assert "/media/" + asset["asset_id"] not in http.request(owner, f"/posts/{post_id}")[1], "Quarantined file leaked"
            worker(env)
            assert fixture(env, "inspect", identifier, post_id)["state"] == "ready", "Image was not processed"
            owner_url = media_url(owner, post_id)
            assert binary(guest, owner_url)[0] == 404, "Private media readable by guest"
            assert http.request(guest, f"/storage/media/{asset['asset_id']}/source.bin")[0] == 404
            assert http.submit(reader, "/login", {"email": "reader-" + email, "password": "ExamplePassword123"})[0] == 200
            assert "PRIVATE_ALT_SENTINEL" not in http.request(reader, f"/posts/{post_id}")[1], "Paywall leaked description"
            fixture(env, "grant", identifier, post_id)
            reader_url = media_url(reader, post_id)
            status, body, headers = binary(reader, reader_url, {"Range": "bytes=0-9"})
            assert status == 206 and len(body) == 10 and headers["Content-Type"] == "image/jpeg"
            assert "no-store" in headers["Cache-Control"]
            fixture(env, "revoke", identifier, post_id)
            assert binary(reader, reader_url)[0] == 404, "Revoked entitlement survived signed URL"
            assert http.submit(owner, management + "/" + asset["asset_id"], {"_method": "DELETE"}, form=management)[0] == 200
            assert binary(owner, owner_url)[0] == 404, "Deleted media remained readable"
            assert not Path("storage/app/private/media", asset["asset_id"]).exists(), "Deleted files remained on disk"
            video = Path("build/media-http-fixture.mp4")
            result = subprocess.run(["/usr/bin/ffmpeg", "-nostdin", "-hide_banner", "-loglevel", "error", "-y", "-f", "lavfi", "-i", "color=c=white:s=64x48:r=1", "-t", "1", "-c:v", "libx264", "-threads", "1", "-pix_fmt", "yuv420p", str(video)], capture_output=True, timeout=20)
            assert result.returncode == 0, "Video test fixture unavailable"
            assert upload(owner, management, video.read_bytes(), "fixture.mp4", "video/mp4", http.csrf(owner, management))[0] == 200
            video.unlink()
            worker(env)
            asset = fixture(env, "inspect", identifier, post_id)
            assert asset["state"] == "ready" and asset["kind"] == "video", "Video conversion did not finish"
            video_url = media_url(owner, post_id)
            status, body, headers = binary(owner, video_url, {"Range": "bytes=0-15"})
            assert status == 206 and len(body) == 16 and headers["Content-Type"] == "video/mp4"
            assert http.submit(owner, management + "/" + asset["asset_id"], {"_method": "DELETE"}, form=management)[0] == 200
            summary = {"status": "passed", "checks": ["mfa_admin_upload", "upload_csrf", "quarantine", "image_queue_conversion", "no_generic_storage_route", "paywall_omission", "authorized_range", "revoked_entitlement", "deletion", "mp4_queue_conversion"], "duration_seconds": round(time.monotonic() - started, 3)}
            Path("build/media-http.json").write_text(json.dumps(summary, indent=2) + "\n")
            print("PASS: private image/video uploads, worker conversion, range authorization, revocation and deletion")
        finally:
            if server.poll() is None:
                os.killpg(server.pid, signal.SIGTERM)
                server.wait(timeout=10)


if __name__ == "__main__":
    main()
