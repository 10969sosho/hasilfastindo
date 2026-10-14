@extends('layouts.print')

@section('title', 'Label Penerimaan '.$gr->doc_number)

@section('content')
<div class="mb-4">
    <h1 class="text-lg font-extrabold">{{ $serials->isNotEmpty() ? 'Label Serial UOM' : 'Label QR Code Penerimaan' }}</h1>
    <p class="text-xs text-slate-500">{{ $gr->doc_number }} · PO {{ $gr->po_no ?: '-' }} · {{ $gr->supplier?->name }} · {{ $gr->branch?->name }} · {{ now()->format('d/m/Y H:i') }}</p>
</div>

@if ($serials->isNotEmpty())
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($serials as $s)
            <div class="rounded-lg border border-slate-300 p-3 text-center">
                <p class="font-mono text-[11px] font-bold tracking-tight break-all">{{ $s->kode_uom_serialno }}</p>
                <div class="mx-auto" data-qr="{{ $s->kode_uom_serialno }}" data-qr-size="130"></div>
                <p class="text-sm font-extrabold uppercase leading-tight">{{ $itemNames[$s->sku] ?? $s->sku }}</p>
                <p class="text-[11px] text-slate-500">SKU: {{ $s->sku }} · UoM: {{ $s->uom }}</p>
                <p class="mt-1 text-[11px] font-bold text-slate-600">
                    Qty UoM {{ $s->qty_uom_in }} {{ $s->uom }}
                    = {{ rtrim(rtrim(number_format($s->qty_smallest_unit_in, 2, '.', ''), '0'), '.') }} PCS
                </p>
                <p class="mt-0.5 text-[11px] font-bold text-slate-700">
                    Berat: {{ $s->weight !== null ? rtrim(rtrim(number_format($s->weight, 3, '.', ''), '0'), '.') : '-' }} kg
                    · BIN/Rak: {{ $s->bin_no ?: '-' }}
                </p>
                <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    RC: {{ $s->receive_no }} · PO: {{ $s->po_no ?: '-' }}
                </p>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    {{ $s->input_receive_date_time?->format('d/m/Y H:i') ?? '-' }} · HASIL FASTINDO
                </p>
            </div>
        @endforeach
    </div>
@else
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
@endif
@endsection
