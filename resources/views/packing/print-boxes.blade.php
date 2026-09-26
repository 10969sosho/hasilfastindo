@extends('layouts.print')

@section('title', 'Label Dus '.$packing->no_packing)

@section('content')
<p class="mb-3 text-xs text-slate-500">
    {{ $packing->no_packing }} · SO {{ $packing->salesOrder?->so_number }} · {{ $packing->customer?->name ?? $packing->salesOrder?->customer?->name }}
</p>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($packing->boxes as $box)
        <div class="rounded-lg border border-slate-400 p-4 text-center" style="page-break-inside:avoid;">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">HASIL FASTINDO</p>
            <p class="text-base font-extrabold">BOX {{ $box->box_number }} / {{ $packing->total_box }}</p>
            <svg class="mx-auto" data-barcode="{{ $box->box_barcode }}"></svg>
            <p class="font-mono text-xs font-bold tracking-widest">{{ $box->box_barcode }}</p>
            <p class="mt-1 text-[11px] font-semibold text-slate-600">
                {{ $packing->salesOrder?->so_number }} · {{ Str::limit($packing->customer?->name ?? $packing->salesOrder?->customer?->name ?? '', 30) }}
            </p>
            @if ($box->weight_kg)<p class="text-[11px] text-slate-500">{{ number_format($box->weight_kg, 1) }} kg</p>@endif
        </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
    document.querySelectorAll('[data-barcode]').forEach(el => {
        try { JsBarcode(el, el.dataset.barcode, { format: 'CODE128', width: 2, height: 50, fontSize: 13, margin: 5 }); }
        catch (e) { el.textContent = el.dataset.barcode; }
    });
</script>
@endpush
