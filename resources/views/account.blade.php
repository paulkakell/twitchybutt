@extends('layout')
@section('title', 'My account')
@section('content')
<p class="eyebrow">Member account</p><h1>{{ auth()->user()->name }}</h1><p class="lede">Your invoice previews on this site. None represent a completed payment.</p>
<div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Total</th><th>Status</th></tr></thead><tbody>@forelse($invoices as $invoice)<tr><td><a href="/invoices/{{ $invoice->id }}">{{ $invoice->id }}</a></td><td>{{ App\Services\TokenAmount::format($invoice->gross_units) }} TEST</td><td>{{ $invoice->status }}</td></tr>@empty<tr><td colspan="3">No invoices yet.</td></tr>@endforelse</tbody></table></div>
@include('pagination', ['page' => $invoices])
@endsection
