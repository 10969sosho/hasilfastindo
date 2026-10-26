@extends('layouts.app')

@section('title', 'Detail Pick NAV')
@section('subtitle', $pick->pick_no . ($pick->source_so_no ? ' · SO: ' . $pick->source_so_no : ''))

@section('content')
@php
    $statusLabels = [
        'open' => 'Menunggu Diproses',
        'in_progress' => 'Sedang Picking',
        'completed' => 'Selesai Pick',
    ];
@endphp

<div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="kpi">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Tanggal Assignment</p>
        <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $pick->assignment_date?->format('d/m/Y') ?: '-' }}</p>
    </div>
    <div class="kpi">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Qty (Take)</p>
        <p class="mt-1 text-lg font-extrabold text-slate-900">{{ number_format($pick->totalTakeQty()) }} PCS</p>
    </div>
    <div class="kpi">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Lokasi NAV</p>
        <p class="mt-1 text-lg font-extrabold text-indigo-700">{{ $pick->location_code ?: '-' }}</p>
    </div>
    <div class="kpi">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Status</p>
        <p class="mt-1"><x-badge :status="$pick->status" :label="$statusLabels[$pick->status] ?? null" /></p>
    </div>
</div>

<div class="card card-pad mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="space-y-1">
        <div class="flex items-center gap-2">
            <span class="text-sm font-extrabold text-slate-900">Assigned User: {{ $pick->assigned_user_id ?: 'Belum ditentukan' }}</span>
        </div>
        <p class="hint">
            SO Ref: <span class="font-mono font-semibold text-slate-700">{{ $pick->source_so_no ?: '-' }}</span>
            @if ($pick->whse_shipment_no)
                · Shipment: <span class="font-mono text-slate-600">{{ $pick->whse_shipment_no }}</span>
            @endif
            @if ($pick->destination_no)
                · Customer: <span class="text-slate-600">{{ $pick->destination_no }}</span>
            @endif
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('nav-picks.index') }}" class="btn btn-ghost">Kembali</a>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-800">Daftar Activity Lines (Take &amp; Place)</h3>
        <span class="text-xs text-slate-500">{{ $pick->items->count() }} baris instruksi aktivitas</span>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-12 text-center">Line</th>
                    <th>Aksi</th>
                    <th>Item SKU</th>
                    <th>Deskripsi</th>
                    <th>Zone</th>
                    <th>Bin Code</th>
                    <th class="text-right">Qty Aktivitas</th>
                    <th class="text-right">Qty Base</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($pick->items as $line)
                <tr class="{{ $line->action_type === 'Take' ? 'bg-amber-50/30' : 'bg-emerald-50/30' }}">
                    <td class="text-center font-mono text-xs text-slate-500">{{ $line->line_no }}</td>
                    <td>
                        @if ($line->action_type === 'Take')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">
                                ⬇ Take (Ambil)
                            </span>
                        @elseif ($line->action_type === 'Place')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">
                                ⬆ Place (Taruh)
                            </span>
                        @else
                            <span class="text-xs font-semibold text-slate-600">{{ $line->action_type ?: '-' }}</span>
                        @endif
                    </td>
                    <td class="font-mono text-xs font-bold text-indigo-700">{{ $line->sku }}</td>
                    <td class="text-slate-700 text-xs">{{ $line->item_name }}</td>
                    <td class="font-mono text-xs text-slate-600">{{ $line->zone_code ?: '-' }}</td>
                    <td class="font-mono text-xs font-bold text-slate-800">{{ $line->bin_code ?: '-' }}</td>
                    <td class="text-right font-bold text-slate-800">{{ number_format($line->quantity, 2) }}</td>
                    <td class="text-right font-mono text-xs text-slate-600">{{ number_format($line->qty_base, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-8 text-center text-slate-400">Dokumen Pick ini belum memiliki baris aktivitas item.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
