<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReportController;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show'])->whereNumber('post');
Route::view('/report', 'report');
Route::post('/report', [ReportController::class, 'store'])->middleware('throttle:reports');
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth', ['mode' => 'login'])->name('login');
    Route::view('/register', 'auth', ['mode' => 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:signup');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/account', function (Request $request) {
        return view('account', ['invoices' => Invoice::query()->where('user_id', $request->user()->getAuthIdentifier())->latest()->paginate(15)]);
    });
    Route::post('/posts/{post}/invoices', [InvoiceController::class, 'store'])->whereNumber('post')->middleware('throttle:invoices');
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
