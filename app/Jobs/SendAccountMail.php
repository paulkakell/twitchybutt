<?php

namespace App\Jobs;

use App\Services\AccountSecurityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendAccountMail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public string $email, public string $purpose, public int $requestedAt, public ?int $generation = null)
    {
        $this->onConnection('database');
        $this->onQueue('account-mail');
    }

    public function handle(AccountSecurityService $service): void
    {
        if (! config('account_security.mail_enabled') || $this->requestedAt < now()->subMinutes(15)->getTimestamp()) {
            return;
        }
        $service->sendRequestedLink($this->email, $this->purpose, $this->requestedAt, $this->generation);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('cms.mail.failed');
    }
}
