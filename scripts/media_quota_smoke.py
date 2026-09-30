"""Disposable CI: concurrent upload reservations must not oversubscribe private storage."""
from __future__ import annotations

import base64
import json
import os
import subprocess
import tempfile
import time
import uuid
from pathlib import Path

from media_http_smoke import png

BOOT = r'''
require 'vendor/autoload.php';
$a = require 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('CI') !== 'true' || !$a->environment(['local','testing'])) { exit(9); }
'''
WORKER = BOOT + r'''
$d = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$user = App\Models\User::query()->findOrFail($d['user_id']);
$post = App\Models\Post::query()->findOrFail($d['post_id']);
file_put_contents($d['ready'], 'ready');
$deadline = microtime(true) + 15;
while (!is_file($d['go'])) { if(microtime(true)>$deadline) { exit(8); } usleep(1000); }
try {
 $file = new Illuminate\Http\UploadedFile($d['file'], 'fixture.png', 'image/png', null, true);
 app(App\Services\MediaService::class)->upload($post, $user, $file, 'Quota fixture');
 echo 'allow';
} catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
 if ($error->getStatusCode() !== 507) { exit(7); }
 echo 'deny';
}
'''


def main() -> None:
    if os.environ.get('CI') != 'true' or os.environ.get('APP_ENV') not in {'testing', 'local'}:
        raise SystemExit('Disposable CI only')
    env = {**os.environ, 'CMS_MEDIA_ENABLED': 'true', 'CMS_MEDIA_VIDEO_ENABLED': 'false', 'CMS_MEDIA_QUOTA_MB': '64'}
    address = 'quota-http-' + uuid.uuid4().hex + '@example.test'
    setup = BOOT + r'''
if ((int) Illuminate\Support\Facades\DB::table('media_storage')->value('reserved_bytes') !== 0) { exit(7); }
$email = trim(stream_get_contents(STDIN));
$user = new App\Models\User(['name'=>'Quota fixture','email'=>$email,'password'=>'ExamplePassword123']); $user->is_admin=true; $user->save();
$post = App\Models\Post::query()->create(['title'=>'Quota fixture','body'=>'Fixture','classification'=>'general','status'=>'draft','price_units'=>0]);
echo json_encode(['user_id'=>$user->id,'post_id'=>$post->id]);
'''
    result = subprocess.run(['php', '-r', setup], env=env, input=address, capture_output=True, text=True, timeout=15)
    assert result.returncode == 0, 'Quota fixture setup or empty-ledger precondition failed'
    fixture = json.loads(result.stdout)
    started = time.monotonic()
    with tempfile.TemporaryDirectory(prefix='tb-media-quota-') as temp:
        root = Path(temp)
        workers = []
        try:
            for number in range(10):
                image = root / f'input-{number}.png'
                image.write_bytes(png())
                process = subprocess.Popen(['php', '-r', WORKER], env=env, text=True, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                process.stdin.write(json.dumps({**fixture, 'ready': str(root / f'ready-{number}'), 'go': str(root / 'go'), 'file': str(image)}))
                process.stdin.close()
                process.stdin = None
                workers.append(process)
            deadline = time.monotonic() + 15
            while len(list(root.glob('ready-*'))) != 10:
                assert time.monotonic() < deadline and all(p.poll() is None for p in workers), 'Quota workers failed to synchronize'
                time.sleep(0.02)
            (root / 'go').write_text('go')
            outputs = [p.communicate(timeout=30)[0].strip() for p in workers]
            assert all(p.returncode == 0 for p in workers), 'Quota worker failed'
            assert outputs.count('allow') == 1 and outputs.count('deny') == 9, 'Media quota was oversubscribed'
        finally:
            for process in workers:
                if process.poll() is None:
                    process.kill()
                process.wait()
    cleanup = BOOT + r'''
$post = (int) trim(stream_get_contents(STDIN));
$assets = App\Models\MediaAsset::query()->where('post_id', $post)->get();
if ($assets->count() !== 1) { exit(6); }
$reserved = (int) Illuminate\Support\Facades\DB::table('media_storage')->value('reserved_bytes');
if ($reserved !== $assets[0]->reserved_bytes || $reserved > 67108864) { exit(5); }
app(App\Services\MediaService::class)->delete($assets[0]);
if ((int) Illuminate\Support\Facades\DB::table('media_storage')->value('reserved_bytes') !== 0) { exit(4); }
'''
    result = subprocess.run(['php', '-r', cleanup], env=env, input=str(fixture['post_id']), capture_output=True, text=True, timeout=15)
    assert result.returncode == 0, 'Media quota reconciliation or cleanup failed'
    summary = {'status': 'passed', 'competing_uploads': 10, 'accepted': 1, 'quota_rejected': 9, 'quota_reconciled': True, 'duration_seconds': round(time.monotonic() - started, 3)}
    Path('build/media-quota.json').write_text(json.dumps(summary, indent=2) + '\n')
    print('PASS: one of ten competing uploads reserves capacity; quota stays within its limit and returns to zero after removal')


if __name__ == '__main__':
    main()
