@extends('layout')
@section('title', 'Test invoice')
@section('content')
<section class="narrow"><p class="eyebrow">Payment foundation</p><h1>Test invoice</h1><div class="notice"><strong>Not payable.</strong> Do not send funds. No wallet, token contract, or blockchain settlement is connected.</div>
<div class="panel"><p class="mono">{{ $invoice->id }}</p><dl class="invoice-lines"><dt>Purchase amount</dt><dd>{{ App\Services\TokenAmount::format($invoice->gross_units) }} TEST</dd><dt>Licensing fee (2%)</dt><dd>{{ App\Services\TokenAmount::format($invoice->fee_units) }} TEST</dd><dt>Creator allocation</dt><dd>{{ App\Services\TokenAmount::format($invoice->creator_units) }} TEST</dd><dt>Quote expiry (UTC)</dt><dd>{{ $invoice->expires_at->toDateTimeString() }}</dd><dt>Status</dt><dd>{{ $invoice->expires_at->isPast() ? 'Expired quote' : 'Quote only' }}</dd></dl></div>
<p class="hint">Tax, network fees, and refund funding are not implemented. This snapshot is not proof of payment or permission to access a post.</p><a href="/account">Back to account</a></section>
@endsection
