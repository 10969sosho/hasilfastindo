@extends('layouts.app')

@section('title', 'Radar '.$order->so_number)
@section('subtitle', $order->customer?->name.' · '.ucfirst($order->status))

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $order->status }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $order->branch?->name }}</span>
        @if ($order->fulfillment_branch_id)
            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">Pemasok: {{ $order->fulfillmentBranch?->name }}</span>
        @endif
    </div>
    <div class="flex gap-2">
        <a href="{{ route('outbound.show', $order) }}" class="btn btn-ghost">Pemenuhan (FIFO)</a>
        <a href="{{ route('packing.create', ['so_id' => $order->id]) }}" class="btn btn-ghost">Packing</a>
        <a href="{{ route('summary.index') }}" class="btn btn-ghost">Kembali</a>
    </div>
</div>

{{-- Timeline progres --}}
<div class="card card-pad mb-5">
    <div class="flex flex-wrap items-center gap-2">
        @php
            $steps = [
                ['label' => 'Order', 'done' => true],
                ['label' => 'Pemenuhan', 'done' => (float) $order->items->sum('fulfilled_qty') > 0],
                ['label' => 'Picking', 'done' => $state['picked']],
                ['label' => 'Packing', 'done' => $state['packed']],
                ['label' => 'Pengiriman', 'done' => (bool) $state['delivery']],
                ['label' => 'Diterima', 'done' => $state['delivered']],
            ];
        @endphp
        @foreach ($steps as $i => $step)
            <span class="flex items-center gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-extrabold {{ $step['done'] ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                    {{ $step['done'] ? '✓' : ($i + 1) }} {{ $step['label'] }}
                </span>
                @if ($i < count($steps) - 1)<span class="text-slate-300">—</span>@endif
            </span>
        @endforeach
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="card-pad border-b border-slate-100">
                <h2 class="text-sm font-extrabold text-slate-900">Per Item</h2>
                <p class="hint">Sisa barang belum diambil dari gudang.</p>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Item</th><th class="text-right">Dipesan</th><th class="text-right">Sisa</th><th>Lokasi Stok</th></tr></thead>
                    <tbody>
                    @foreach ($lines as $l)
                        @php $line = $l['line']; @endphp
                        <tr>
                            <td>
                                <p class="font-bold text-slate-800">{{ $line->item?->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $line->item?->sku }} · {{ $line->uom?->code }}</p>
                            </td>
                            <td class="text-right font-semibold">{{ number_format($line->requested_qty) }}</td>
                            <td class="text-right font-bold {{ $l['remaining'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($l['remaining']) }}</td>
                            <td class="text-[11px] text-slate-500">{{ $l['locations'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($order->packingLists->isNotEmpty())
            <div class="card mt-5 overflow-hidden">
                <div class="card-pad border-b border-slate-100"><h2 class="text-sm font-extrabold text-slate-900">Packing List</h2></div>
                <div class="table-scroll">
                    <table class="table">
                        <thead><tr><th>No. Packing</th><th>Dus</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($order->packingLists as $pl)
                            <tr>
                                <td class="font-mono text-xs font-bold text-indigo-700">{{ $pl->no_packing }}</td>
                                <td>{{ $pl->total_box }}</td>
                                <td><x-badge status="{{ $pl->status }}" /></td>
                                <td class="text-right"><a class="btn btn-ghost btn-sm" href="{{ route('packing.show', $pl) }}">Detail</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Info SO</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">No. SO</dt><dd class="font-mono font-bold">{{ $order->so_number }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Customer</dt><dd class="text-right font-semibold">{{ $order->customer?->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tanggal</dt><dd class="font-semibold">{{ $order->order_date?->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Nilai</dt><dd class="font-semibold">Rp {{ number_format($order->total_amount) }}</dd></div>
            </dl>
        </div>

        @if ($order->deliveries->isNotEmpty())
            <div class="card card-pad">
                <h2 class="mb-3 text-sm font-extrabold text-slate-900">Pengiriman</h2>
                <div class="space-y-2">
                    @foreach ($order->deliveries as $d)
                        <a href="{{ route('delivery.show', $d) }}" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 hover:border-indigo-400">
                            <span class="font-mono text-xs font-bold text-indigo-700">{{ $d->delivery_no }}</span>
                            <x-badge status="{{ $d->status }}" label="{{ ucfirst(str_replace('_', ' ', $d->status)) }}" />
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
