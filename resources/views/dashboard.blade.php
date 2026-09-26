@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'KPI operasional gudang, mutasi stok, dan alert')

@section('content')
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    @php
        $cards = [
            ['label' => 'Total Stok (PCS)', 'value' => number_format($kpi['total_pcs']), 'icon' => 'boxes', 'tone' => 'text-indigo-600 bg-indigo-50'],
            ['label' => 'Nilai Stok', 'value' => 'Rp '.number_format($kpi['stock_value']), 'icon' => 'chart', 'tone' => 'text-emerald-600 bg-emerald-50'],
            ['label' => 'SO Aktif', 'value' => $kpi['open_so'], 'icon' => 'radar', 'tone' => 'text-amber-600 bg-amber-50'],
            ['label' => 'SO Bulan Ini', 'value' => $kpi['month_so'], 'icon' => 'clipboard', 'tone' => 'text-slate-600 bg-slate-100'],
            ['label' => 'Penerimaan Pending', 'value' => $kpi['pending_receipts'], 'icon' => 'inbound', 'tone' => 'text-amber-600 bg-amber-50'],
            ['label' => 'Transfer In-Transit', 'value' => $kpi['in_transit'], 'icon' => 'transfer', 'tone' => 'text-amber-600 bg-amber-50'],
            ['label' => 'Pengiriman Berjalan', 'value' => $kpi['deliveries'], 'icon' => 'truck', 'tone' => 'text-indigo-600 bg-indigo-50'],
            ['label' => 'Item Aktif', 'value' => $kpi['items'], 'icon' => 'package', 'tone' => 'text-emerald-600 bg-emerald-50'],
        ];
    @endphp

    @foreach ($cards as $card)
        <div class="kpi flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-1 truncate text-xl font-extrabold text-slate-900">{{ $card['value'] }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $card['tone'] }}">
                <x-icon :name="$card['icon']" :size="18" />
            </div>
        </div>
    @endforeach
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-3">
    {{-- Grafik mutasi --}}
    <div class="card card-pad lg:col-span-2">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900">Grafik Mutasi Stok (30 hari)</h2>
                <p class="hint">Barang masuk vs keluar seluruh gudang yang diakses</p>
            </div>
            <div class="flex items-center gap-3 text-[11px] font-bold">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span> IN</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-indigo-500"></span> OUT</span>
            </div>
        </div>

        <div class="flex h-48 items-end gap-1.5 overflow-x-auto pb-1">
            @foreach ($chart as $day)
                @php $scale = 100 / $chartMax; @endphp
                <div class="flex min-w-[10px] flex-1 flex-col items-center gap-1" title="{{ $day['label'] }} · IN {{ number_format($day['in']) }} / OUT {{ number_format($day['out']) }}">
                    <div class="flex h-40 w-full items-end justify-center gap-0.5">
                        <div class="w-1/2 rounded-t bg-emerald-500" style="height: {{ max(2, $day['in'] * $scale) }}%"></div>
                        <div class="w-1/2 rounded-t bg-indigo-500" style="height: {{ max(2, $day['out'] * $scale) }}%"></div>
                    </div>
                    <span class="text-[9px] text-slate-400">{{ $loop->iteration % 5 === 0 ? $day['label'] : '' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Stok multi-cabang --}}
    <div class="card card-pad">
        <h2 class="text-sm font-extrabold text-slate-900">Stok Multi-Cabang</h2>
        <p class="hint mb-3">Total PCS & nilai persediaan per cabang</p>
        <div class="space-y-3">
            @foreach ($branchStock as $row)
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-sm font-bold text-slate-800">{{ $row['branch']->name }}</p>
                        @if ($row['branch']->is_central)
                            <x-badge status="ready" label="Pusat" />
                        @endif
                    </div>
                    <div class="mt-2 flex items-end justify-between">
                        <p class="text-lg font-extrabold text-indigo-700">{{ number_format($row['pcs']) }} <span class="text-xs font-bold text-slate-400">PCS</span></p>
                        <p class="text-xs font-semibold text-slate-500">Rp {{ number_format($row['value']) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    {{-- Barang belum diambil --}}
    <div class="card overflow-hidden">
        <div class="card-pad border-b border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900">Barang Belum Diambil dari SO</h2>
            <p class="hint">Sisa qty yang belum dipenuhi gudang</p>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr><th>SO</th><th>Customer</th><th>Item</th><th class="text-right">Sisa</th></tr>
                </thead>
                <tbody>
                @forelse ($notTaken as $row)
                    <tr>
                        <td>
                            <a href="{{ route('outbound.show', $row['so']) }}" class="font-bold text-indigo-600 hover:underline">{{ $row['so']->so_number }}</a>
                            <div class="text-[11px] text-slate-400">{{ $row['so']->branch?->code }} · {{ $row['so']->status }}</div>
                        </td>
                        <td class="text-slate-600">{{ $row['so']->customer?->name }}</td>
                        <td class="text-slate-700">{{ $row['item']->item?->name }}</td>
                        <td class="text-right font-bold text-rose-600">{{ number_format($row['remaining']) }} {{ $row['item']->uom?->code }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-400">Semua SO sudah terpenuhi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Alert stok minimum --}}
    <div class="card overflow-hidden">
        <div class="card-pad border-b border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900">Alert Stok Minimum</h2>
            <p class="hint">Item yang berada di bawah batas minimum</p>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr><th>Item</th><th>Kategori</th><th class="text-right">Stok</th><th class="text-right">Min.</th></tr>
                </thead>
                <tbody>
                @forelse ($lowStock as $row)
                    <tr>
                        <td>
                            <p class="font-bold text-slate-800">{{ $row['item']->name }}</p>
                            <p class="text-[11px] text-slate-400">{{ $row['item']->sku }}</p>
                        </td>
                        <td class="text-slate-600">{{ $row['item']->category?->name ?? '-' }}</td>
                        <td class="text-right font-bold text-rose-600">{{ number_format($row['pcs']) }}</td>
                        <td class="text-right font-semibold text-slate-500">{{ number_format($row['item']->min_stock) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-400">Tidak ada item di bawah minimum.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Karteks terbaru --}}
<div class="card mt-5 overflow-hidden">
    <div class="card-pad border-b border-slate-100">
        <h2 class="text-sm font-extrabold text-slate-900">Mutasi Terbaru</h2>
        <p class="hint">Riwayat karteks terakhir</p>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr><th>Tanggal</th><th>Tipe</th><th>Item</th><th>Cabang</th><th>Ref</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th><th>Oleh</th></tr>
            </thead>
            <tbody>
            @forelse ($recentMovements as $m)
                <tr>
                    <td class="text-slate-600">{{ $m->date->format('d/m/Y') }}</td>
                    <td><x-badge status="{{ $m->type === 'IN' ? 'ready' : ($m->type === 'OUT' ? 'processing' : 'in_transit') }}" label="{{ $m->type }}" /></td>
                    <td class="text-slate-700">{{ $m->item?->name }}</td>
                    <td class="text-slate-600">{{ $m->branch?->code }}</td>
                    <td class="font-mono text-xs text-slate-500">{{ $m->reference_no ?: '-' }}</td>
                    <td class="text-right font-bold text-emerald-600">{{ $m->qty_in > 0 ? number_format($m->qty_in) : '-' }}</td>
                    <td class="text-right font-bold text-rose-600">{{ $m->qty_out > 0 ? number_format($m->qty_out) : '-' }}</td>
                    <td class="text-slate-500">{{ $m->user?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-6 text-center text-slate-400">Belum ada mutasi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
