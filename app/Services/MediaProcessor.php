<?php

namespace App\Services;

use App\Models\MediaAsset;
use RuntimeException;
use Symfony\Component\Process\Process;

final class MediaProcessor
{
    public const IMAGE_MAX = 8388608;

    public const VIDEO_MAX = 67108864;

    public const IMAGE_OUTPUT_MAX = 33554432;

    public const VIDEO_OUTPUT_MAX = 134217728;

    public const THUMBNAIL_MAX = 1048576;

    public function __construct(private readonly PrivateMediaStore $store) {}

    public static function validateConfiguration(): void
    {
        if (! config('media.enabled')) {
            return;
        }
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromjpeg') || ! function_exists('imagecreatefrompng') || ! function_exists('imagecreatefromwebp') || (app()->runningInConsole() && ! extension_loaded('pcntl')) || config('media.quota_mb') < 64 || config('media.quota_mb') > 1048576) {
            throw new RuntimeException('Media requires GD and a quota between 64 and 1048576 MiB.');
        }
        if (config('media.video_enabled')) {
            foreach (['ffmpeg', 'ffprobe'] as $binary) {
                $path = config('media.'.$binary);
                if (! is_string($path) || ! str_starts_with($path, '/') || ! is_file($path) || ! is_executable($path)) {
                    throw new RuntimeException('Video processing binaries are unavailable.');
                }
            }
        }
    }

    /** @return array{content_bytes: int, thumbnail_bytes: int, content_sha256: string, thumbnail_sha256: string} */
    public function process(MediaAsset $asset): array
    {
        self::validateConfiguration();
        $source = $this->store->path($asset->id, 'source.bin');
        clearstatcache(true, $source);
        if (! is_file($source) || filesize($source) !== $asset->source_bytes) {
            throw new RuntimeException('Quarantined source unavailable.');
        }
        $content = $this->store->path($asset->id, $asset->kind === 'image' ? 'content.jpg' : 'content.mp4');
        $thumbnail = $this->store->path($asset->id, 'thumbnail.jpg');
        if ($asset->kind === 'image') {
            $this->image($source, $content, $thumbnail);
        } elseif ($asset->kind === 'video' && config('media.video_enabled')) {
            $this->video($source, $content, $thumbnail);
        } else {
            throw new RuntimeException('Unsupported media processing.');
        }
        foreach ([$content, $thumbnail] as $file) {
            $this->store->protect($file);
        }
        $size = filesize($content);
        $thumbSize = filesize($thumbnail);
        $maximum = $asset->kind === 'image' ? self::IMAGE_OUTPUT_MAX : self::VIDEO_OUTPUT_MAX;
        $thumbInfo = @getimagesize($thumbnail);
        if (! is_int($size) || $size < 1 || $size > $maximum || ! is_int($thumbSize) || $thumbSize < 1 || $thumbSize > self::THUMBNAIL_MAX || (! is_array($thumbInfo) || $thumbInfo[2] !== IMAGETYPE_JPEG)) {
            throw new RuntimeException('Processed media outside limits.');
        }

        return ['content_bytes' => $size, 'thumbnail_bytes' => $thumbSize, 'content_sha256' => hash_file('sha256', $content), 'thumbnail_sha256' => hash_file('sha256', $thumbnail)];
    }

    private function image(string $source, string $content, string $thumbnail): void
    {
        // Decode in the worker only, not while handling the upload request.
        $info = @getimagesize($source);
        if (! is_array($info) || ! $this->dimensions($info[0], $info[1]) || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Unsupported image.');
        }
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
        };
        if ($image === false) {
            throw new RuntimeException('Image decoding failed.');
        }
        try {
            foreach ([[$content, 4096], [$thumbnail, 640]] as [$destination, $limit]) {
                $scale = min(1.0, $limit / max($info[0], $info[1]));
                $width = max(1, (int) floor($info[0] * $scale));
                $height = max(1, (int) floor($info[1] * $scale));
                $out = imagecreatetruecolor($width, $height);
                if ($out === false) {
                    throw new RuntimeException('Image allocation failed.');
                }
                try {
                    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
                    if (! imagecopyresampled($out, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]) || ! imagejpeg($out, $destination, 85)) {
                        throw new RuntimeException('Image conversion failed.');
                    }
                    // GD's success result alone does not guarantee an output file.
                    $outputInfo = @getimagesize($destination);
                    if (! is_file($destination) || ! is_array($outputInfo) || $outputInfo[2] !== IMAGETYPE_JPEG) {
                        throw new RuntimeException('Image output missing.');
                    }
                } finally {
                    imagedestroy($out);
                }
            }
        } finally {
            imagedestroy($image);
        }
    }

    private function dimensions(int $width, int $height): bool
    {
        return $width >= 1 && $height >= 1 && $width <= 8192 && $height <= 8192 && $width * $height <= 20000000;
    }

    /** @return array{duration: float, width: int, height: int} */
    private function probe(string $file): array
    {
        $process = $this->command([config('media.ffprobe'), '-v', 'error', '-protocol_whitelist', 'file,pipe', '-enable_drefs', '0', '-use_absolute_path', '0', '-f', 'mov', '-i', $file, '-show_entries', 'format=duration:stream=codec_type,width,height', '-of', 'json']);
        $process->setTimeout(10);
        $process->run();
        if (! $process->isSuccessful() || strlen($process->getOutput()) > 65536) {
            throw new RuntimeException('Video inspection failed.');
        }
        $data = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        $duration = (float) ($data['format']['duration'] ?? 0);
        $streams = $data['streams'] ?? [];
        $videos = array_values(array_filter($streams, fn ($stream) => ($stream['codec_type'] ?? '') === 'video'));
        if (count($streams) > 8 || count($videos) !== 1 || ! is_finite($duration) || $duration <= 0 || $duration > 600 || ! $this->dimensions((int) ($videos[0]['width'] ?? 0), (int) ($videos[0]['height'] ?? 0))) {
            throw new RuntimeException('Video outside limits.');
        }

        return ['duration' => $duration, 'width' => (int) $videos[0]['width'], 'height' => (int) $videos[0]['height']];
    }

    /** @param list<string> $arguments */
    private function command(array $arguments): Process
    {
        // Host-native media tools do not inherit application or portable-PHP secrets/libraries.
        $keys = array_unique(array_merge(array_keys(getenv()), array_keys($_ENV), array_keys($_SERVER)));
        $environment = [];
        foreach ($keys as $key) {
            if (is_string($key)) {
                $environment[$key] = false;
            }
        }
        $environment['PATH'] = '/usr/bin:/bin';
        $environment['LANG'] = 'C';
        $environment['LC_ALL'] = 'C';

        return new Process($arguments, null, $environment);
    }

    private function video(string $source, string $content, string $thumbnail): void
    {
        $before = $this->probe($source);
        $input = [config('media.ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y', '-protocol_whitelist', 'file,pipe', '-enable_drefs', '0', '-use_absolute_path', '0', '-threads', '2', '-f', 'mov', '-i', $source];
        $args = array_merge($input, ['-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-map_chapters', '-1', '-sn', '-dn', '-vf', "scale=w='min(1920,iw)':h='min(1080,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2", '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '24', '-threads', '2', '-filter_threads', '1', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-max_muxing_queue_size', '256', '-movflags', '+faststart', '-fs', (string) self::VIDEO_OUTPUT_MAX, '-t', '600', $content]);
        $process = $this->command($args);
        $process->setTimeout(120);
        $process->disableOutput();
        $process->mustRun();
        $after = $this->probe($content);
        if (abs($before['duration'] - $after['duration']) > 1.0) {
            throw new RuntimeException('Video output truncated.');
        }
        $process = $this->command(array_merge($input, ['-map', '0:v:0', '-map_metadata', '-1', '-frames:v', '1', '-vf', "scale=w='min(640,iw)':h='min(640,ih)':force_original_aspect_ratio=decrease", '-threads', '1', '-filter_threads', '1', '-fs', (string) self::THUMBNAIL_MAX, $thumbnail]));
        $process->setTimeout(20);
        $process->disableOutput();
        $process->mustRun();
    }
}
