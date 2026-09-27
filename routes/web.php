<?php

use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionController;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show'])->whereNumber('post');
Route::view('/report', 'report');
Route::post('/report', [ReportController::class, 'store'])->middleware('strict:reports');
Route::view('/forgot-password', 'password-forgot')->name('password.request');
Route::post('/forgot-password', [AccountSecurityController::class, 'forgot'])->middleware('strict:recovery')->name('password.email');
Route::get('/reset-password', [AccountSecurityController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [AccountSecurityController::class, 'reset'])->middleware('strict:reset')->name('password.update');
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth', ['mode' => 'login'])->name('login');
    Route::view('/register', 'auth', ['mode' => 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('strict:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('strict:signup');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/mfa', [MfaController::class, 'settings']);
    Route::get('/account/mfa/challenge', [MfaController::class, 'form'])->name('mfa.challenge');
    Route::post('/account/mfa/challenge', [MfaController::class, 'challenge'])->middleware('strict:mfa')->name('mfa.verify');
    Route::post('/account/mfa/enroll', [MfaController::class, 'begin'])->middleware('strict:mfa');
    Route::post('/account/mfa/replace', [MfaController::class, 'replace'])->middleware('strict:mfa');
    Route::post('/account/mfa/confirm', [MfaController::class, 'confirm'])->middleware('strict:mfa');
    Route::post('/account/mfa/recovery-codes', [MfaController::class, 'regenerate'])->middleware('strict:mfa');
    Route::get('/account/sessions', [SessionController::class, 'index']);
    Route::post('/account/sessions/revoke-all', [SessionController::class, 'revokeAll'])->middleware('strict:mfa');
    Route::post('/account/sessions/{id}/revoke', [SessionController::class, 'revoke'])->whereUuid('id')->middleware('strict:mfa');
    Route::get('/account/security', [AccountSecurityController::class, 'settings'])->name('verification.notice');
    Route::post('/email/verification-notification', [AccountSecurityController::class, 'resend'])->middleware('strict:verification');
    Route::get('/email/verify/{id}/{hash}/{generation}', [AccountSecurityController::class, 'verify'])->whereNumber(['id', 'generation'])->middleware(['signed:relative', 'strict:verify-link'])->name('verification.verify');
    Route::get('/account', function (Request $request) {
        return view('account', ['invoices' => Invoice::query()->where('user_id', $request->user()->getAuthIdentifier())->latest()->paginate(15)]);
    });
    Route::post('/posts/{post}/invoices', [InvoiceController::class, 'store'])->whereNumber('post')->middleware('strict:invoices');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->whereUuid('invoice');
    Route::post('/checkout', fn () => abort(503, 'Checkout is not implemented. No funds have been accepted.'));
});
Route::middleware(['auth', 'can:manage-content'])->prefix('studio')->group(function (): void {
    Route::get('/', fn () => view('studio', ['posts' => Post::query()->latest('id')->paginate(20), 'reportCount' => Report::query()->count()]));
    Route::get('/posts/new', fn () => view('editor', ['post' => new Post]));
    Route::get('/posts/{post}/edit', fn (Post $post) => view('editor', compact('post')))->whereNumber('post');
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{post}', [PostController::class, 'update'])->whereNumber('post');
    Route::get('/reports', fn () => view('reports', ['reports' => Report::query()->latest('id')->paginate(20)]));
});
