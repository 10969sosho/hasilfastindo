@extends('layouts.app')

@section('title', 'Daftar PO NAV')

@section('content')
<div class="card p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('nav-po.index') }}" class="flex flex-wrap items-center gap-2.5">
        <div class="w-72">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Cari nomor PO atau supplier...">
        </div>
        <div class="w-48">
            <select class="input searchable" name="status" placeholder="Semua Status">
                <option value="">Semua Status</option>
                @php
                    $statusLabels = [
                        'open' => 'Menunggu Penerimaan',
                        'released' => 'Menunggu Penerimaan',
                        'partially_received' => 'Diterima Sebagian',
                        'completed' => 'Selesai',
                    ];
                @endphp
                @foreach ($statusLabels as $value => $text)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('nav-po.sync') }}">
            @csrf
            <button class="btn btn-ghost" title="Tarik Purchase Order terbaru dari Microsoft Dynamics NAV"><x-icon name="sync" :size="15" /> Sinkronisasi dari NAV</button>
        </form>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Nomor PO</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th>Ringkasan Item &amp; Qty NAV (PCS)</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($pos as $po)
                <tr class="hover:bg-slate-50/70">
                    <td>
                        <a href="{{ route('nav-po.show', $po) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $po->po_no }}</a>
                        @if ($po->notes)
                            <p class="max-w-[16rem] truncate text-[11px] text-slate-400">{{ $po->notes }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-slate-600">{{ $po->order_date?->format('d/m/Y') ?: '-' }}</td>
                    <td class="font-medium text-slate-800">{{ $po->supplier_name }}</td>
                    <td>
                        @php $extraItems = $po->items_count - 3; @endphp
                        <p class="font-bold text-slate-700">{{ $po->items_count }} item · {{ number_format($po->items_sum_nav_qty_pcs ?? 0) }} PCS</p>
                        <p class="max-w-[22rem] truncate text-[11px] text-slate-400">{{ $po->items->take(3)->pluck('sku')->join(', ') }}{{ $extraItems > 0 ? ', +'.$extraItems : '' }}</p>
                    </td>
                    <td><x-badge :status="$po->status" :label="$statusLabels[$po->status] ?? null" /></td>
                    <td>
                        <div class="flex justify-end gap-1.5">
                            <a href="{{ route('nav-po.show', $po) }}" class="btn btn-ghost btn-sm" title="Detail item"><x-icon name="eye" :size="14" /></a>
                            @if ($po->isReceivable())
                                <a href="{{ route('inbound.create', ['nav_po_id' => $po->id]) }}" class="btn btn-success btn-sm">Proses ke Inbound</a>
                            @else
                                <span class="btn btn-ghost btn-sm opacity-60" title="PO sudah lengkap diterima">Selesai</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-400">Belum ada dokumen Purchase Order NAV.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-slate-100">{{ $pos->links() }}</div>
</div>
@endsection
