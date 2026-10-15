@extends('layouts.app')

@section('title', 'Daftar PO NAV')
@section('subtitle', 'Purchase Order dari NAV/BC — data staging mandiri, diproses ke Penerimaan Barang')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('nav-po.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="No. PO / Supplier">
        </div>
        <div class="min-w-[11rem]">
            <label class="label">Status</label>
            <select class="input" name="status">
                <option value="">Semua</option>
                @foreach (['open', 'released', 'partially_received', 'completed'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <a href="{{ route('inbound.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Penerimaan Baru</a>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr><th>Nomor PO</th><th>Tanggal</th><th>Supplier</th><th>Ringkasan Item &amp; Qty NAV (PCS)</th><th>Status</th><th class="text-right">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse ($pos as $po)
                <tr>
                    <td>
                        <a href="{{ route('nav-po.show', $po) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $po->po_no }}</a>
                        @if ($po->notes)
                            <p class="max-w-[16rem] truncate text-[11px] text-slate-400">{{ $po->notes }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-slate-600">{{ $po->order_date?->format('d/m/Y') ?: '-' }}</td>
                    <td class="text-slate-700">{{ $po->supplier_name }}</td>
                    <td>
                        @php $extraItems = $po->items_count - 3; @endphp
                        <p class="font-bold text-slate-700">{{ $po->items_count }} item · {{ number_format($po->items_sum_nav_qty_pcs ?? 0) }} PCS</p>
                        <p class="max-w-[22rem] truncate text-[11px] text-slate-400">{{ $po->items->take(3)->pluck('sku')->join(', ') }}{{ $extraItems > 0 ? ', +'.$extraItems : '' }}</p>
                    </td>
                    <td><x-badge :status="$po->status" /></td>
                    <td>
                        <div class="flex justify-end gap-1.5">
                            <a href="{{ route('nav-po.show', $po) }}" class="btn btn-ghost btn-sm" title="Detail item"><x-icon name="eye" :size="14" /></a>
                            @if ($po->isReceivable())
                                <a href="{{ route('inbound.create', ['nav_po_id' => $po->id]) }}" class="btn btn-success btn-sm">Proses Penerimaan</a>
                            @else
                                <span class="btn btn-ghost btn-sm opacity-60" title="PO sudah lengkap diterima">Selesai</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-400">Belum ada PO NAV.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $pos->links() }}</div>
</div>
@endsection
