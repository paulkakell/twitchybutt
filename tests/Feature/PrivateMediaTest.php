<?php

namespace Tests\Feature;

use App\Jobs\ProcessMedia;
use App\Models\Entitlement;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\User;
use App\Services\MediaLinks;
use App\Services\MediaProcessor;
use App\Services\MediaService;
use App\Services\PrivateMediaStore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class PrivateMediaTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = sys_get_temp_dir().'/tb-media-test-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config(['media.enabled' => true, 'media.video_enabled' => false, 'media.quota_mb' => 2048]);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporary);
        parent::tearDown();
    }

    private function member(bool $admin = false): User
    {
        $user = User::query()->create(['name' => 'Media fixture', 'email' => Str::uuid().'@example.test', 'password' => 'ExamplePassword123']);
        $user->forceFill(['is_admin' => $admin])->save();

        return $user;
    }

    private function postRecord(array $overrides = []): Post
    {
        return Post::query()->create(array_merge(['title' => 'Media fixture', 'body' => 'Fixture content', 'status' => 'published', 'classification' => 'general', 'price_units' => 0], $overrides));
    }

    private function upload(?Post $post = null, ?UploadedFile $file = null): MediaAsset
    {
        $post ??= $this->postRecord();
        $this->actingAsMfa($this->member(true))->post('/studio/posts/'.$post->id.'/media', ['file' => $file ?? UploadedFile::fake()->image('fixture.png', 64, 48), 'alt_text' => 'A safe test description'])->assertRedirect('/studio/posts/'.$post->id.'/media');

        return MediaAsset::query()->latest('created_at')->firstOrFail();
    }

    private function process(MediaAsset $asset): MediaAsset
    {
        app(MediaService::class)->process($asset->id, app(MediaProcessor::class));

        return $asset->fresh();
    }

    private function link(Post $post, string $variant = 'content'): string
    {
        $response = $this->get('/posts/'.$post->id)->assertOk();
        preg_match('#(?:src|poster)="([^\"]*/media/[^\"]*/'.$variant.'\?[^\"]+)"#', $response->getContent(), $match);
        self::assertNotEmpty($match, 'Expected media link absent.');

        return html_entity_decode($match[1], ENT_QUOTES);
    }

    public function test_media_is_disabled_without_explicit_configuration(): void
    {
        config(['media.enabled' => false]);
        $post = $this->postRecord();
        $this->actingAsMfa($this->member(true))->get('/studio/posts/'.$post->id.'/media')->assertOk()->assertSee('disabled');
        $this->post('/studio/posts/'.$post->id.'/media', ['file' => UploadedFile::fake()->image('photo.png'), 'alt_text' => 'Fixture'])->assertStatus(503);
        $this->assertDatabaseCount('media_assets', 0);
        Queue::assertNothingPushed();
    }

    public function test_members_and_password_only_administrators_cannot_upload(): void
    {
        $post = $this->postRecord();
        $this->actingAs($this->member())->post('/studio/posts/'.$post->id.'/media')->assertForbidden();
        $this->actingAs($this->member(true))->postJson('/studio/posts/'.$post->id.'/media')->assertForbidden();
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_upload_is_quarantined_private_and_does_not_trust_filename_or_state(): void
    {
        $post = $this->postRecord();
        $this->actingAsMfa($this->member(true))->post('/studio/posts/'.$post->id.'/media', ['file' => UploadedFile::fake()->image('../../untrusted.php.png'), 'alt_text' => 'Fixture', 'state' => 'ready', 'reserved_bytes' => 0, 'kind' => 'video', 'path' => '/etc/passwd'])->assertRedirect();
        $asset = MediaAsset::query()->firstOrFail();
        self::assertSame('queued', $asset->state);
        self::assertSame('image', $asset->kind);
        self::assertGreaterThan(0, $asset->reserved_bytes);
        $source = app(PrivateMediaStore::class)->path($asset->id, 'source.bin');
        self::assertFileExists($source);
        self::assertSame(0600, fileperms($source) & 0777);
        self::assertSame(0700, fileperms(dirname($source)) & 0777);
        self::assertStringNotContainsString('untrusted', $asset->toJson());
        Queue::assertPushed(ProcessMedia::class, fn ($job) => $job->assetId === $asset->id && $job->connection === 'media');
        $this->get('/posts/'.$post->id)->assertDontSee('/media/'.$asset->id);
    }

    public function test_original_source_has_no_delivery_route(): void
    {
        $asset = $this->process($this->upload());
        $this->get('/media/'.$asset->id.'/source')->assertNotFound();
        $this->get('/storage/media/'.$asset->id.'/source.bin')->assertNotFound();
        self::assertFileDoesNotExist(app(PrivateMediaStore::class)->path($asset->id, 'source.bin'));
    }

    public function test_malformed_active_and_archive_content_is_rejected(): void
    {
        $post = $this->postRecord();
        $this->actingAsMfa($this->member(true));
        foreach (['<svg xmlns="http://www.w3.org/2000/svg"><script>bad</script></svg>', '<!doctype html><script>bad</script>', '<?php echo "bad";', "PK\x03\x04malformed"] as $payload) {
            $this->post('/studio/posts/'.$post->id.'/media', ['file' => UploadedFile::fake()->createWithContent('claimed.jpg', $payload), 'alt_text' => 'Fixture'])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('media_assets', 0);
        self::assertSame(0, (int) DB::table('media_storage')->value('reserved_bytes'));
    }

    public function test_images_exceeding_byte_limit_are_rejected(): void
    {
        $post = $this->postRecord();
        $fixture = UploadedFile::fake()->image('large.png');
        $file = UploadedFile::fake()->createWithContent('large.png', file_get_contents($fixture->getPathname()).str_repeat('x', 8388609));
        $this->actingAsMfa($this->member(true))->post('/studio/posts/'.$post->id.'/media', ['file' => $file, 'alt_text' => 'Fixture'])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_alt_text_is_required_and_bounded(): void
    {
        $post = $this->postRecord();
        $this->actingAsMfa($this->member(true));
        foreach (['', str_repeat('a', 241)] as $alt) {
            $this->post('/studio/posts/'.$post->id.'/media', ['file' => UploadedFile::fake()->image('fixture.png'), 'alt_text' => $alt])->assertSessionHasErrors('alt_text');
        }
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_quota_reserves_output_capacity_and_rejects_before_writing_files(): void
    {
        config(['media.quota_mb' => 64]);
        $first = $this->upload();
        $before = $first->reserved_bytes;
        $post = $this->postRecord();
        $this->post('/studio/posts/'.$post->id.'/media', ['file' => UploadedFile::fake()->image('another.png'), 'alt_text' => 'Another'])->assertStatus(507);
        $this->assertDatabaseCount('media_assets', 1);
        self::assertSame($before, (int) DB::table('media_storage')->value('reserved_bytes'));
        self::assertCount(1, glob(app(PrivateMediaStore::class)->root().'/*'));
    }

    public function test_processing_reencodes_to_jpeg_and_reconciles_quota(): void
    {
        $asset = $this->upload();
        $source = app(PrivateMediaStore::class)->path($asset->id, 'source.bin');
        file_put_contents($source, 'PRIVATE_METADATA_SENTINEL', FILE_APPEND);
        clearstatcache(true, $source);
        $asset->source_bytes = filesize($source);
        $asset->save();
        $ready = $this->process($asset);
        self::assertSame('ready', $ready->state);
        $content = app(PrivateMediaStore::class)->path($asset->id, 'content.jpg');
        self::assertSame('image/jpeg', mime_content_type($content));
        self::assertStringNotContainsString('PRIVATE_METADATA_SENTINEL', file_get_contents($content));
        self::assertSame($ready->content_bytes + $ready->thumbnail_bytes, $ready->reserved_bytes);
        self::assertSame($ready->reserved_bytes, (int) DB::table('media_storage')->value('reserved_bytes'));
        self::assertFileDoesNotExist($source);
    }

    public function test_duplicate_jobs_do_not_change_ready_files_or_quota(): void
    {
        $asset = $this->process($this->upload());
        $before = $asset->toArray();
        $quota = DB::table('media_storage')->value('reserved_bytes');
        $this->process($asset);
        self::assertSame($before, $asset->fresh()->toArray());
        self::assertSame($quota, DB::table('media_storage')->value('reserved_bytes'));
    }

    public function test_missing_or_changed_source_fails_closed_and_keeps_reservation(): void
    {
        $asset = $this->upload();
        unlink(app(PrivateMediaStore::class)->path($asset->id, 'source.bin'));
        $failed = $this->process($asset);
        self::assertSame('failed', $failed->state);
        self::assertSame($asset->reserved_bytes, $failed->reserved_bytes);
        $this->get('/posts/'.$asset->post_id)->assertDontSee('/media/'.$asset->id);
    }

    public function test_wrong_dimensions_fail_in_worker_without_publication(): void
    {
        $asset = $this->upload(file: UploadedFile::fake()->image('wide.png', 8193, 1));
        self::assertSame('failed', $this->process($asset)->state);
        $this->get('/posts/'.$asset->post_id)->assertDontSee('/media/'.$asset->id);
    }

    public function test_queue_failure_never_marks_ready_or_releases_unremoved_files(): void
    {
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('PRIVATE_QUEUE_DETAILS'));
        $post = $this->postRecord();
        $this->actingAsMfa($this->member(true))->post('/studio/posts/'.$post->id.'/media', ['file' => UploadedFile::fake()->image('photo.png'), 'alt_text' => 'Fixture'])->assertStatus(503)->assertDontSee('PRIVATE_QUEUE_DETAILS');
        $asset = MediaAsset::query()->firstOrFail();
        self::assertSame('failed', $asset->state);
        self::assertGreaterThan(0, $asset->reserved_bytes);
    }

    public function test_signed_links_expire_and_tampering_is_rejected(): void
    {
        $asset = $this->process($this->upload());
        $post = Post::query()->findOrFail($asset->post_id);
        $url = $this->link($post);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(str_replace('content?', 'thumbnail?', $url))->assertForbidden();
        $this->get('/media/'.$asset->id.'/content')->assertForbidden();
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_paid_media_requires_current_entitlement_not_just_signed_url(): void
    {
        $post = $this->postRecord(['price_units' => 20000000]);
        $asset = $this->process($this->upload($post));
        $user = $this->member();
        $access = new Entitlement;
        $access->forceFill(['user_id' => $user->id, 'post_id' => $post->id, 'expires_at' => now()->addHour()])->save();
        $this->actingAs($user);
        $url = $this->link($post);
        $this->get($url)->assertOk();
        $access->revoked_at = now();
        $access->save();
        $this->get($url)->assertNotFound();
        $this->get('/posts/'.$post->id)->assertDontSee('/media/'.$asset->id)->assertDontSee('A safe test description');
    }

    public function test_paid_link_cannot_be_reused_by_another_entitled_account(): void
    {
        $post = $this->postRecord(['price_units' => 20000000]);
        $this->process($this->upload($post));
        $first = $this->member();
        $second = $this->member();
        foreach ([$first, $second] as $member) {
            (new Entitlement)->forceFill(['user_id' => $member->id, 'post_id' => $post->id])->save();
        }
        $this->actingAs($first);
        $url = $this->link($post);
        $this->actingAs($second)->get($url)->assertForbidden();
    }

    public function test_unpublishing_invalidates_previously_public_media_urls(): void
    {
        $post = $this->postRecord();
        $this->process($this->upload($post));
        $this->post('/logout');
        $url = $this->link($post);
        $post->update(['status' => 'draft']);
        $this->get($url)->assertNotFound();
    }

    public function test_restricted_media_never_leaks_to_member_or_public_catalog(): void
    {
        $post = $this->postRecord(['classification' => 'restricted', 'status' => 'draft']);
        $asset = $this->process($this->upload($post));
        $url = $this->link($post);
        $this->actingAs($this->member())->get($url)->assertNotFound();
        $this->get('/')->assertDontSee('/media/'.$asset->id)->assertDontSee($post->title);
        $this->get('/posts/'.$post->id)->assertNotFound();
    }

    public function test_delivery_has_no_store_and_ignores_sendfile_headers(): void
    {
        $post = $this->postRecord();
        $asset = $this->process($this->upload($post));
        $url = $this->link($post);
        $response = $this->get($url, ['X-Sendfile-Type' => 'X-Accel-Redirect']);
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeaderMissing('X-Accel-Redirect');
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get($url, ['Range' => 'bytes=0-9'])->assertStatus(206)->assertHeader('Content-Length', '10')->assertHeader('Content-Range', 'bytes 0-9/'.$asset->content_bytes);
        $this->get($url, ['Range' => 'bytes=999999999-'])->assertStatus(416);
    }

    public function test_delete_revokes_urls_removes_files_and_restores_quota(): void
    {
        $post = $this->postRecord();
        $asset = $this->process($this->upload($post));
        $url = $this->link($post);
        $directory = app(PrivateMediaStore::class)->directory($asset->id);
        $this->delete('/studio/posts/'.$post->id.'/media/'.$asset->id)->assertRedirect();
        $this->get($url)->assertNotFound();
        self::assertDirectoryDoesNotExist($directory);
        self::assertSame(0, (int) DB::table('media_storage')->value('reserved_bytes'));
        $this->delete('/studio/posts/'.$post->id.'/media/'.$asset->id)->assertRedirect();
        self::assertSame(0, (int) DB::table('media_storage')->value('reserved_bytes'));
    }

    public function test_delete_queued_upload_prevents_late_job_from_processing(): void
    {
        $asset = $this->upload();
        app(MediaService::class)->delete($asset);
        $this->process($asset);
        self::assertSame('deleted', $asset->fresh()->state);
        self::assertDirectoryDoesNotExist(app(PrivateMediaStore::class)->directory($asset->id));
    }

    public function test_delete_refuses_active_processing_to_prevent_resurrection_races(): void
    {
        $asset = $this->upload();
        $asset->state = 'processing';
        $asset->save();
        $this->delete('/studio/posts/'.$asset->post_id.'/media/'.$asset->id)->assertStatus(409);
        self::assertSame('processing', $asset->fresh()->state);
    }

    public function test_cleanup_failure_keeps_urls_revoked_and_quota_reserved(): void
    {
        $post = $this->postRecord();
        $asset = $this->process($this->upload($post));
        $url = $this->link($post);
        $directory = app(PrivateMediaStore::class)->directory($asset->id);
        mkdir($directory.'/unexpected', 0700);
        $this->delete('/studio/posts/'.$post->id.'/media/'.$asset->id)->assertStatus(500);
        self::assertSame('deleting', $asset->fresh()->state);
        self::assertGreaterThan(0, (int) DB::table('media_storage')->value('reserved_bytes'));
        $this->get($url)->assertNotFound();
        rmdir($directory.'/unexpected');
        $this->delete('/studio/posts/'.$post->id.'/media/'.$asset->id)->assertRedirect();
        self::assertSame(0, (int) DB::table('media_storage')->value('reserved_bytes'));
    }

    public function test_update_is_post_scoped_and_cannot_set_paths_or_state(): void
    {
        $asset = $this->upload();
        $other = $this->postRecord();
        $this->put('/studio/posts/'.$other->id.'/media/'.$asset->id, ['alt_text' => 'other', 'position' => 0])->assertNotFound();
        $this->put('/studio/posts/'.$asset->post_id.'/media/'.$asset->id, ['alt_text' => '<script>sentinel</script>', 'position' => 2, 'state' => 'ready', 'reserved_bytes' => 0])->assertRedirect();
        self::assertSame('queued', $asset->fresh()->state);
        self::assertGreaterThan(0, $asset->fresh()->reserved_bytes);
        $this->get('/studio/posts/'.$asset->post_id.'/media')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false);
    }

    public function test_private_path_rejects_traversal_and_symlinks(): void
    {
        $store = app(PrivateMediaStore::class);
        $asset = $this->process($this->upload());
        $path = $store->path($asset->id, 'content.jpg');
        unlink($path);
        symlink('/etc/passwd', $path);
        $this->expectException(RuntimeException::class);
        $store->path($asset->id, 'content.jpg');
    }

    public function test_path_cannot_choose_an_original_filename(): void
    {
        $asset = $this->upload();
        $this->expectException(RuntimeException::class);
        app(PrivateMediaStore::class)->path($asset->id, '../../private');
    }

    public function test_unsafe_public_storage_root_is_rejected(): void
    {
        mkdir($this->temporary.'/app/private', 0700, true);
        symlink(public_path(), $this->temporary.'/app/private/media');
        $this->expectException(RuntimeException::class);
        app(PrivateMediaStore::class)->root();
    }

    public function test_configuration_rejects_invalid_quota_and_missing_video_tools(): void
    {
        config(['media.video_enabled' => true, 'media.ffmpeg' => '/nonexistent/binary']);
        $this->expectException(RuntimeException::class);
        MediaProcessor::validateConfiguration();
    }

    public function test_new_schema_rollback_preserves_accounts_and_posts(): void
    {
        $post = $this->postRecord();
        $user = $this->member();
        $migration = require database_path('migrations/2026_09_27_000005_add_private_media.php');
        $migration->down();
        self::assertFalse(Schema::hasTable('media_assets'));
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $migration->up();
        self::assertSame(0, (int) DB::table('media_storage')->value('reserved_bytes'));
    }

    public function test_media_tools_do_not_inherit_application_secrets(): void
    {
        putenv('MEDIA_SYNTHETIC_SECRET=private-test-sentinel');
        try {
            $method = new \ReflectionMethod(MediaProcessor::class, 'command');
            $process = $method->invoke(app(MediaProcessor::class), ['/usr/bin/true']);
            self::assertFalse($process->getEnv()['MEDIA_SYNTHETIC_SECRET']);
            self::assertFalse($process->getEnv()['APP_KEY']);
            self::assertFalse($process->getEnv()['LD_LIBRARY_PATH']);
        } finally {
            putenv('MEDIA_SYNTHETIC_SECRET');
        }
    }

    public function test_late_duplicate_job_failure_cannot_fail_another_active_claim(): void
    {
        $asset = $this->upload();
        $job = new ProcessMedia($asset->id);
        $asset->forceFill(['state' => 'processing', 'processing_token' => $job->claimToken])->save();
        (new ProcessMedia($asset->id))->failed(new RuntimeException('Other job failed'));
        self::assertSame('processing', $asset->fresh()->state);
        $job->failed(new RuntimeException('Owning job failed'));
        self::assertSame('failed', $asset->fresh()->state);
    }

    public function test_media_job_timeout_is_shorter_than_reservation_retry(): void
    {
        $job = new ProcessMedia((string) Str::uuid());
        self::assertLessThan(config('queue.connections.media.retry_after'), $job->timeout);
        self::assertSame(1, $job->tries);
        self::assertTrue($job->failOnTimeout);
    }

    public function test_job_failure_marks_unfinished_asset_private_without_releasing_quota(): void
    {
        $asset = $this->upload();
        (new ProcessMedia($asset->id))->failed(new RuntimeException('PRIVATE_PROCESS_EXCEPTION'));
        self::assertSame('failed', $asset->fresh()->state);
        self::assertSame('processing_failed', $asset->fresh()->failure_code);
        self::assertSame($asset->reserved_bytes, $asset->fresh()->reserved_bytes);
        $this->get('/studio/posts/'.$asset->post_id.'/media')->assertDontSee('PRIVATE_PROCESS_EXCEPTION');
    }

    public function test_both_variants_are_hidden_when_media_is_disabled_after_processing(): void
    {
        $post = $this->postRecord();
        $this->process($this->upload($post));
        $url = $this->link($post);
        config(['media.enabled' => false]);
        $this->get($url)->assertNotFound();
        $this->get('/posts/'.$post->id)->assertDontSee('/media/');
    }

    public function test_post_media_limit_is_enforced_before_new_reservation(): void
    {
        $post = $this->postRecord();
        $asset = $this->upload($post);
        for ($i = 0; $i < 49; $i++) {
            $copy = $asset->replicate();
            $copy->id = (string) Str::uuid();
            $copy->save();
        }
        $before = DB::table('media_storage')->value('reserved_bytes');
        $this->post('/studio/posts/'.$post->id.'/media', ['file' => UploadedFile::fake()->image('another.png'), 'alt_text' => 'Fixture'])->assertStatus(422);
        self::assertSame($before, DB::table('media_storage')->value('reserved_bytes'));
        $this->assertDatabaseCount('media_assets', 50);
    }

    public function test_expired_entitlement_denies_thumbnail_and_content(): void
    {
        $post = $this->postRecord(['price_units' => 20000000]);
        $asset = $this->process($this->upload($post));
        $member = $this->member();
        (new Entitlement)->forceFill(['post_id' => $post->id, 'user_id' => $member->id, 'expires_at' => now()->addMinute()])->save();
        $this->actingAs($member)->get('/posts/'.$post->id);
        $links = app(MediaLinks::class);
        $content = $links->url($asset, $post, 'content');
        $thumb = $links->url($asset, $post, 'thumbnail');
        $this->travel(61)->seconds();
        $this->get($content)->assertNotFound();
        $this->get($thumb)->assertNotFound();
    }

    private function videoFixture(int $duration = 1): UploadedFile
    {
        $file = $this->temporary.'/fixture.mp4';
        $process = new Process(['/usr/bin/ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=white:s=64x48:r=1', '-t', (string) $duration, '-c:v', 'libx264', '-threads', '1', '-pix_fmt', 'yuv420p', '-metadata', 'comment=PRIVATE_VIDEO_SENTINEL', $file]);
        $process->setEnv(['LD_LIBRARY_PATH' => false]);
        $process->setTimeout(30)->mustRun();

        return new UploadedFile($file, 'fixture.mp4', 'video/mp4', null, true);
    }

    public function test_video_is_rejected_when_separate_video_switch_is_disabled(): void
    {
        $post = $this->postRecord();
        $this->actingAsMfa($this->member(true))->post('/studio/posts/'.$post->id.'/media', ['file' => $this->videoFixture(), 'alt_text' => 'Fixture'])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_mp4_is_reencoded_and_has_protected_thumbnail(): void
    {
        config(['media.video_enabled' => true]);
        $post = $this->postRecord();
        $asset = $this->process($this->upload($post, $this->videoFixture()));
        self::assertSame('ready', $asset->state);
        self::assertSame('video', $asset->kind);
        $url = $this->link($post);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get($this->link($post, 'thumbnail'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        self::assertStringNotContainsString('PRIVATE_VIDEO_SENTINEL', file_get_contents(app(PrivateMediaStore::class)->path($asset->id, 'content.mp4')));
    }

    public function test_video_longer_than_ten_minutes_fails_closed(): void
    {
        config(['media.video_enabled' => true]);
        $asset = $this->process($this->upload(file: $this->videoFixture(601)));
        self::assertSame('failed', $asset->state);
        $this->get('/posts/'.$asset->post_id)->assertDontSee('/media/'.$asset->id);
    }
}
