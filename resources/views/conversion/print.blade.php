@extends('layouts.print')

@section('title', 'Label Konversi '.$conversion->conversion_no)

@section('content')
<div class="mb-4">
    <h1 class="text-lg font-extrabold">Label Barcode Hasil Konversi</h1>
    <p class="text-xs text-slate-500">
        {{ $conversion->conversion_no }} · {{ $conversion->item?->sku }} ·
        {{ $conversion->fromUom?->code }} → {{ $conversion->toUom?->code }} ·
        {{ now()->format('d/m/Y H:i') }}
    </p>
</div>

@if ($serials->isEmpty())
    <p class="rounded-lg bg-slate-100 px-4 py-3 text-sm font-bold text-slate-600">Belum ada barcode yang diterbitkan.</p>
@else
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        @foreach ($serials as $s)
            <div class="rounded-lg border border-slate-300 p-3 text-center" style="page-break-inside:avoid;">
                <p class="font-mono text-[11px] font-bold tracking-tight break-all">{{ $s->kode_uom_serialno }}</p>
                <div class="mx-auto" data-qr="{{ $s->kode_uom_serialno }}" data-qr-size="130"></div>
                <p class="text-sm font-extrabold uppercase leading-tight">{{ $conversion->item?->name }}</p>
                <p class="text-[11px] text-slate-500">SKU: {{ $s->sku }} · UoM: {{ $s->uom }}</p>
                <p class="mt-1 text-[11px] font-bold text-slate-600">
                    Qty UoM {{ $s->qty_uom_in }} {{ $s->uom }}
                    = {{ rtrim(rtrim(number_format($s->qty_smallest_unit_in, 2, '.', ''), '0'), '.') }} PCS
                </p>
                <p class="mt-0.5 text-[11px] font-bold text-slate-700">BIN/Rak: {{ $s->bin_no ?: '-' }}</p>
                <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">CV: {{ $conversion->conversion_no }}</p>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ now()->format('d/m/Y H:i') }} · HASIL FASTINDO</p>
            </div>
        @endforeach
    </div>
@endif
@endsection
