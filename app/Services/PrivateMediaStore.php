<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class PrivateMediaStore
{
    public function root(): string
    {
        $path = storage_path('app/private/media');
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('Media storage unavailable.');
        }
        $real = realpath($path);
        $public = realpath(public_path());
        if ($real === false || is_link($path) || $public === false || $real === $public || str_starts_with($real, $public.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Unsafe media storage root.');
        }
        if (! chmod($real, 0700)) {
            throw new RuntimeException('Media permissions unavailable.');
        }

        return $real;
    }

    public function directory(string $id, bool $create = false): string
    {
        if (! Str::isUuid($id)) {
            throw new RuntimeException('Invalid media reference.');
        }
        $root = $this->root();
        $directory = $root.'/'.$id;
        if ($create && ! file_exists($directory) && ! mkdir($directory, 0700)) {
            throw new RuntimeException('Media directory unavailable.');
        }
        if (is_link($directory) || (file_exists($directory) && realpath($directory) !== $directory)) {
            throw new RuntimeException('Unsafe media directory.');
        }

        return $directory;
    }

    public function path(string $id, string $file): string
    {
        if (! in_array($file, ['source.bin', 'content.jpg', 'content.mp4', 'thumbnail.jpg'], true)) {
            throw new RuntimeException('Invalid media variant.');
        }
        $path = $this->directory($id).'/'.$file;
        if (is_link($path)) {
            throw new RuntimeException('Unsafe media file.');
        }

        return $path;
    }

    public function receive(string $id, UploadedFile $file): void
    {
        $directory = $this->directory($id, true);
        $file->move($directory, 'source.bin');
        $this->protect($this->path($id, 'source.bin'));
    }

    public function protect(string $path): void
    {
        clearstatcache(true, $path);
        if (is_link($path) || ! is_file($path) || ! chmod($path, 0600)) {
            throw new RuntimeException('Media file unavailable.');
        }
    }

    public function removeSource(string $id): void
    {
        $source = $this->path($id, 'source.bin');
        if (is_file($source) && ! unlink($source)) {
            throw new RuntimeException('Source cleanup failed.');
        }
    }

    public function remove(string $id): void
    {
        $directory = $this->directory($id);
        if (! is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $file) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }
            // Never recurse into an unexpected directory or follow a link.
            $path = $directory.'/'.$file;
            if ((! is_file($path) && ! is_link($path)) || ! unlink($path)) {
                throw new RuntimeException('Media cleanup incomplete.');
            }
        }
        if (! rmdir($directory)) {
            throw new RuntimeException('Media cleanup incomplete.');
        }
    }
}
