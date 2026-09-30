<?php

namespace App\Jobs;

use App\Services\MediaProcessor;
use App\Services\MediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Throwable;

final class ProcessMedia implements ShouldQueue
{
    use Queueable;

    public string $claimToken;

    public int $tries = 1;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public string $assetId)
    {
        $this->claimToken = (string) Str::uuid();
        $this->onConnection('media');
        $this->onQueue('media');
    }

    public function handle(MediaService $service, MediaProcessor $processor): void
    {
        $service->process($this->assetId, $processor, $this->claimToken);
    }

    public function failed(?Throwable $exception): void
    {
        app(MediaService::class)->fail($this->assetId, $this->claimToken);
    }
}
