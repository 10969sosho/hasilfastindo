@extends('layouts.app')

@section('title', 'Packing '.$packing->no_packing)
@section('subtitle', $packing->salesOrder?->so_number.' · '.$packing->customer?->name.' · '.ucfirst($packing->status))

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $packing->status }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $packing->total_box }} dus</span>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $packing->branch?->code }}</span>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('packing.print', $packing) }}" class="btn btn-ghost"><x-icon name="printer" :size="16" /> Cetak Detail</a>
        <a href="{{ route('packing.print-boxes', $packing) }}" class="btn btn-ghost"><x-icon name="printer" :size="16" /> Cetak Label Dus</a>
        <a href="{{ route('delivery.create', ['packing_list_id' => $packing->id]) }}" class="btn btn-primary"><x-icon name="outbound" :size="16" /> Buat Surat Jalan</a>
        <a href="{{ route('packing.index') }}" class="btn btn-ghost">Kembali</a>
    </div>
</div>

<div class="card overflow-hidden mb-5">
    <div class="card-pad border-b border-slate-100">
        <h2 class="text-sm font-extrabold text-slate-900">Isi per Dus</h2>
        <p class="hint">Tiap dus punya barcode unik HF-BOX-... untuk scan out.</p>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>Dus</th><th>Barcode</th><th>Isi</th><th class="text-right">Total Qty</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($packing->boxes as $box)
                <tr>
                    <td class="font-bold text-slate-800">Box {{ $box->box_number }}</td>
                    <td class="font-mono text-xs font-bold text-indigo-700">{{ $box->box_barcode }}</td>
                    <td>
                        <ul class="space-y-0.5">
                            @foreach ($box->items as $bi)
                                <li class="text-xs text-slate-600">{{ $bi->item?->name }} — <b>{{ number_format($bi->qty) }} {{ $bi->uom?->code }}</b></li>
                            @endforeach
                        </ul>
                    </td>
                    <td class="text-right font-bold">{{ number_format($box->items->sum('qty')) }}</td>
                    <td><x-badge status="{{ $box->status }}" /></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="card-pad border-b border-slate-100">
        <h2 class="text-sm font-extrabold text-slate-900">Ringkasan per Item</h2>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>Item</th><th class="text-right">Qty</th><th>Satuan</th></tr></thead>
            <tbody>
            @foreach ($packing->items as $row)
                <tr>
                    <td>
                        <p class="font-bold text-slate-800">{{ $row->item?->name }}</p>
                        <p class="text-[11px] text-slate-400">{{ $row->item?->sku }}</p>
                    </td>
                    <td class="text-right font-bold">{{ number_format($row->qty) }}</td>
                    <td>{{ $row->uom?->code }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-pad flex flex-wrap items-center justify-between gap-3 border-t border-slate-100">
        <span class="hint">Catatan: {{ $packing->notes ?: '-' }}</span>
        <form method="POST" action="{{ route('packing.destroy', $packing) }}"
              onsubmit="return wmsConfirm('Hapus packing list ini? Qty packed akan dikembalikan.')">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm">Hapus</button>
        </form>
    </div>
</div>
@endsection
