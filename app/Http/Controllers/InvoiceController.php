<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Post;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController
{
    public function store(Request $request, Post $post, InvoiceService $service): RedirectResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'uuid']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $invoice = $service->create($user, $post, strtolower($data['idempotency_key']));
        return redirect('/invoices/'.$invoice->id);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->user_id === $request->user()?->getAuthIdentifier(), 404);
        return view('invoice', ['invoice' => $invoice]);
    }
}
