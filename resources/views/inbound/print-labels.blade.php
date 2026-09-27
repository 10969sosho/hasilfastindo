@extends('layouts.print')

@section('title', 'Label Penerimaan '.$gr->doc_number)

@section('content')
<div class="mb-4">
    <h1 class="text-lg font-extrabold">Label QR Code Penerimaan</h1>
    <p class="text-xs text-slate-500">{{ $gr->doc_number }} · {{ $gr->supplier?->name }} · {{ $gr->branch?->name }} · {{ now()->format('d/m/Y H:i') }}</p>
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($gr->items as $line)
        <div class="rounded-lg border border-slate-300 p-4 text-center">
            <p class="text-sm font-extrabold uppercase leading-tight">{{ $line->item?->name }}</p>
            <p class="mb-1 text-[11px] text-slate-500">{{ $line->item?->sku }} · Batch {{ $line->batch_no ?: '-' }}</p>
            <div class="mx-auto" data-qr="{{ $line->original_barcode }}" data-qr-size="130"></div>
            <p class="font-mono text-xs font-bold tracking-widest">{{ $line->original_barcode }}</p>
            <p class="mt-1 text-[11px] font-bold text-slate-600">
                {{ number_format($line->received_qty, 0) }} {{ $line->receivedUom?->code }}
                = {{ number_format($line->total_pcs) }} PCS
            </p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">HASIL FASTINDO · {{ $gr->doc_number }}</p>
        </div>
    @endforeach
</div>
@endsection
