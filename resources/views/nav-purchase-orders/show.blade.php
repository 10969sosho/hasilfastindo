@extends('layouts.app')

@section('title', 'Detail PO NAV')
@section('subtitle', $po->po_no.' · '.$po->supplier_name)

@section('content')
@php
    $poStatusLabels = [
        'open' => 'Menunggu Penerimaan',
        'released' => 'Menunggu Penerimaan',
        'partially_received' => 'Diterima Sebagian',
        'completed' => 'Selesai',
    ];
@endphp
<div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="kpi">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Tanggal</p>
        <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $po->order_date?->format('d/m/Y') ?: '-' }}</p>
    </div>
    <div class="kpi">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Qty NAV</p>
        <p class="mt-1 text-lg font-extrabold text-slate-900">{{ number_format($po->totalNavQtyPcs()) }} PCS</p>
    </div>
    <div class="kpi">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Sudah Diterima</p>
        <p class="mt-1 text-lg font-extrabold text-emerald-600">{{ number_format($po->totalReceivedQtyPcs()) }} PCS</p>
    </div>
    <div class="kpi">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Status</p>
        <p class="mt-1"><x-badge :status="$po->status" :label="$poStatusLabels[$po->status] ?? null" /></p>
    </div>
</div>

<div class="card card-pad mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-sm font-extrabold text-slate-900">{{ $po->supplier_name }}</p>
        @if ($po->notes)
            <p class="hint mt-0.5">{{ $po->notes }}</p>
        @endif
    </div>
    <div class="flex gap-2">
        <a href="{{ route('nav-po.index') }}" class="btn btn-ghost">Kembali</a>
        @if ($po->isReceivable())
            <a href="{{ route('inbound.create', ['nav_po_id' => $po->id]) }}" class="btn btn-success"><x-icon name="inbound" :size="16" /> Proses ke Inbound</a>
        @endif
    </div>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr><th>SKU</th><th>Item</th><th>Satuan NAV</th><th class="text-right">Qty NAV (PCS)</th><th class="text-right">Diterima (PCS)</th><th class="text-right">Sisa (PCS)</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse ($po->items as $line)
                <tr>
                    <td class="font-mono text-xs font-bold text-indigo-700">{{ $line->sku }}</td>
                    <td class="text-slate-700">{{ $line->item_name }}</td>
                    <td class="font-mono text-xs text-slate-600">{{ $line->uom_nav }}</td>
                    <td class="text-right font-bold text-slate-700">{{ number_format($line->nav_qty_pcs) }}</td>
                    <td class="text-right font-bold text-emerald-700">{{ number_format($line->received_qty_pcs) }}</td>
                    <td class="text-right font-bold text-slate-600">{{ number_format($line->remainingQtyPcs()) }}</td>
                    <td><x-badge :status="$line->status" /></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-slate-400">PO ini tidak memiliki item.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
