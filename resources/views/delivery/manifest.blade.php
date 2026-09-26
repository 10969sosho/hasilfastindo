@extends('layouts.print')

@section('title', 'Manifest '.$delivery->delivery_no)

@section('content')
<div class="mb-4 flex items-start justify-between gap-4 border-b pb-3">
    <div>
        <h1 class="text-lg font-extrabold">SURAT JALAN / MANIFEST PENGIRIMAN</h1>
        <p class="text-xs text-slate-500">{{ $delivery->delivery_no }} · {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <div class="text-right text-xs">
        <p class="font-extrabold">HASIL FASTINDO</p>
        <p>{{ $delivery->branch?->name }}</p>
    </div>
</div>

<table class="table mb-4">
    <tbody>
        <tr><th style="width:18%">No. SO</th><td>{{ $delivery->salesOrder?->so_number }}</td><th style="width:16%">Packing List</th><td>{{ $delivery->packingList?->no_packing }}</td></tr>
        <tr><th>Customer</th><td>{{ $delivery->salesOrder?->customer?->name ?? $delivery->packingList?->customer?->name ?? '-' }}</td>
            <th>Metode</th><td>{{ $delivery->method === 'pickup_sendiri' ? 'Pickup Sendiri' : 'Armada' }}</td></tr>
        <tr><th>Armada</th><td>{{ $delivery->vehicle?->plate_number ?? '-' }} ({{ $delivery->vehicle?->vehicle_name ?? '-' }})</td>
            <th>Sopir</th><td>{{ $delivery->driver_name ?? '-' }} / Helper: {{ $delivery->helper_name ?? '-' }}</td></tr>
        <tr><th>Tujuan</th><td colspan="3">{{ $delivery->destination_address ?? '-' }}</td></tr>
        <tr><th>Status</th><td>{{ $statuses[$delivery->status] ?? $delivery->status }}</td>
            <th>Catatan</th><td>{{ $delivery->notes ?: '-' }}</td></tr>
    </tbody>
</table>

<h2 class="mb-1 text-sm font-extrabold">Daftar Muatan</h2>
<table class="table">
    <thead><tr><th>No.</th><th>Barcode Dus</th><th>Item</th><th class="text-right">Qty</th><th>Satuan</th><th>Scan Out</th></tr></thead>
    <tbody>
    @php $no = 0; @endphp
    @foreach ($delivery->items->groupBy('barcode') as $barcode => $group)
        @foreach ($group as $i => $item)
            <tr>
                @if ($i === 0)
                    <td rowspan="{{ max(1, $group->count()) }}">{{ ++$no }}</td>
                    <td rowspan="{{ max(1, $group->count()) }}" class="font-mono">{{ $barcode }}</td>
                @endif
                <td>{{ $item->item?->name }} <span style="color:#64748b">({{ $item->item?->sku }})</span></td>
                <td style="text-align:right"><b>{{ number_format($item->qty) }}</b></td>
                <td>{{ $item->uom?->code }}</td>
                <td>{{ $item->scanned_out_at?->format('d/m H:i') ?? 'Belum' }}</td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>

<div class="mt-8 grid grid-cols-3 gap-8 text-center text-xs">
    <div><p class="mb-10">Pengirim,</p><p class="border-t border-slate-400 pt-1">({{ $delivery->user?->name ?? '......................' }})</p></div>
    <div><p class="mb-10">Sopir,</p><p class="border-t border-slate-400 pt-1">({{ $delivery->driver_name ?? '......................' }})</p></div>
    <div><p class="mb-10">Penerima,</p><p class="border-t border-slate-400 pt-1">(..........................)</p></div>
</div>
@endsection
