@extends('layouts.print')

@section('title', 'Packing List '.$packing->no_packing)

@section('content')
<div class="mb-4 flex items-start justify-between gap-4 border-b pb-3">
    <div>
        <h1 class="text-lg font-extrabold">PACKING LIST</h1>
        <p class="text-xs text-slate-500">{{ $packing->no_packing }} · {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <div class="text-right text-xs">
        <p class="font-extrabold">HASIL FASTINDO</p>
        <p>{{ $packing->branch?->name }}</p>
        <p>Gudang: {{ $packing->sourceBranch?->name ?? '-' }}</p>
    </div>
</div>

<table class="table mb-4">
    <tbody>
        <tr><th style="width:20%">SO</th><td>{{ $packing->salesOrder?->so_number }}</td><th style="width:18%">Customer</th><td>{{ $packing->customer?->name ?? $packing->salesOrder?->customer?->name }}</td></tr>
        <tr><th>Jumlah Dus</th><td>{{ $packing->total_box }}</td><th>Status</th><td>{{ ucfirst($packing->status) }}</td></tr>
        <tr><th>Alamat</th><td colspan="3">{{ $packing->customer?->address ?? $packing->salesOrder?->customer?->address ?? '-' }}</td></tr>
        <tr><th>Catatan</th><td colspan="3">{{ $packing->notes ?: '-' }}</td></tr>
    </tbody>
</table>

<h2 class="mb-1 text-sm font-extrabold">Rincian per Dus</h2>
<table class="table mb-4">
    <thead>
        <tr><th>Dus</th><th>Barcode</th><th>Item</th><th class="text-right">Qty</th><th>Satuan</th></tr>
    </thead>
    <tbody>
    @foreach ($packing->boxes as $box)
        @php $boxLines = $box->items; @endphp
        @foreach ($boxLines as $i => $bi)
            <tr>
                @if ($i === 0)
                    <td rowspan="{{ max(1, $boxLines->count()) }}"><b>Box {{ $box->box_number }}</b></td>
                    <td rowspan="{{ max(1, $boxLines->count()) }}" class="font-mono">{{ $box->box_barcode }}</td>
                @endif
                <td>{{ $bi->item?->name }} <span style="color:#64748b">({{ $bi->item?->sku }})</span></td>
                <td style="text-align:right"><b>{{ number_format($bi->qty) }}</b></td>
                <td>{{ $bi->uom?->code ?? ($bi->item?->uom?->code ?? '') }}</td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>

<h2 class="mb-1 text-sm font-extrabold">Total per Item</h2>
<table class="table">
    <thead><tr><th>Item</th><th class="text-right">Total Qty</th><th>Satuan</th></tr></thead>
    <tbody>
    @foreach ($packing->items as $row)
        <tr>
            <td>{{ $row->item?->name }} <span style="color:#64748b">({{ $row->item?->sku }})</span></td>
            <td style="text-align:right"><b>{{ number_format($row->qty) }}</b></td>
            <td>{{ $row->uom?->code }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="mt-8 grid grid-cols-3 gap-8 text-center text-xs">
    <div><p class="mb-10">Dibuat oleh,</p><p class="border-t border-slate-400 pt-1">({{ $packing->user?->name ?? '......................' }})</p></div>
    <div><p class="mb-10">Dicek oleh,</p><p class="border-t border-slate-400 pt-1">(..........................)</p></div>
    <div><p class="mb-10">Diterima oleh,</p><p class="border-t border-slate-400 pt-1">(..........................)</p></div>
</div>
@endsection
