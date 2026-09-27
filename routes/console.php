<?php

use App\Models\User;
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
        'password' => ['required', 'confirmed', 'max:72', Password::min(12)->letters()->numbers()],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) { $this->error($error); }
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
    try { DB::select('select 1'); $checks['database'] = true; }
    catch (Throwable) { $checks['database'] = false; }
    foreach ($checks as $name => $passed) { $this->line($name.': '.($passed ? 'PASS' : 'FAIL')); }
    return in_array(false, $checks, true) ? 1 : 0;
})->purpose('Check configuration without printing secrets.');
