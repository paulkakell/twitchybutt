<?php

use App\Models\User;
use App\Rules\PasswordBytes;
use App\Services\SecurityMaintenance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('cms:admin {--email= : Administrator email address}', function (): int {
    $email = strtolower((string) ($this->option('email') ?: $this->ask('Email')));
    $password = (string) $this->secret('Password');
    $confirmation = (string) $this->secret('Confirm password');
    $validator = Validator::make(['email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
        'email' => ['required', 'email', 'max:254', 'unique:users,email'],
        'password' => ['bail', 'required', new PasswordBytes, 'confirmed', Password::min(12)->letters()->numbers()],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }
    $user = new User(['name' => 'Creator', 'email' => $email, 'password' => $password]);
    $user->is_admin = true;
    $user->save();
    $this->info('Administrator created. No credentials were written to logs.');

    return 0;
})->purpose('Provision an administrator locally; passwords are prompted and never command arguments.');

Artisan::command('cms:doctor', function (): int {
    $checks = [
        'application_key' => is_string(config('app.key')) && strlen(config('app.key')) >= 32,
        'payments_disabled' => config('cms.payments_enabled') === false,
        'restricted_publication_disabled' => config('cms.restricted_publishing_enabled') === false,
        'version' => preg_match('/\A\d{2}\.\d{2}\.\d{2}\z/', (string) config('cms.version')) === 1,
        '64_bit_php' => PHP_INT_SIZE === 8,
        'storage_writable' => is_writable(storage_path('framework/sessions')),
    ];
    try {
        DB::select('select 1');
        $checks['database'] = true;
    } catch (Throwable) {
        $checks['database'] = false;
    }
    foreach ($checks as $name => $passed) {
        $this->line($name.': '.($passed ? 'PASS' : 'FAIL'));
    }

    return in_array(false, $checks, true) ? 1 : 0;
})->purpose('Check configuration without printing secrets.');

Artisan::command('cms:security-prune', function (): void {
    $counts = app(SecurityMaintenance::class)->prune();
    $this->info('Expired session records removed: '.$counts['sessions']);
    $this->info('Expired pending factors cleared: '.$counts['pending_factors']);
    $this->info('Expired attempt budgets removed: '.$counts['budgets']);
})->purpose('Remove expired local security metadata without exposing identities or keys');

Artisan::command('cms:media-status', function (): int {
    $this->line('media_enabled: '.(config('media.enabled') ? 'yes' : 'no'));
    $this->line('video_enabled: '.(config('media.video_enabled') ? 'yes' : 'no'));
    try {
        $reserved = (int) DB::table('media_storage')->where('id', 1)->value('reserved_bytes');
        $this->line('reserved_bytes: '.$reserved);
        $this->line('quota_bytes: '.(config('media.quota_mb') * 1048576));
        foreach (['uploading', 'queued', 'processing', 'ready', 'failed', 'deleting'] as $state) {
            $this->line($state.': '.DB::table('media_assets')->where('state', $state)->count());
        }
        $stalled = DB::table('media_assets')->whereIn('state', ['uploading', 'processing'])->where('updated_at', '<', now()->subMinutes(15))->count();
        $this->line('stalled_operations: '.$stalled);

        return $stalled > 0 ? 1 : 0;
    } catch (Throwable) {
        $this->error('Media status unavailable. Check migrations and database connectivity.');

        return 1;
    }
})->purpose('Report local media state and quota without filenames, content or credentials.');
