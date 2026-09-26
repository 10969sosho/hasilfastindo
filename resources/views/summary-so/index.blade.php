@extends('layouts.app')

@section('title', 'Radar Summary SO')
@section('subtitle', 'Monitoring real-time pemenuhan, packing, pengiriman & barang yang belum diambil')

@section('content')
<div class="grid gap-4 sm:grid-cols-4 mb-5">
    <div class="card card-pad"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">SO Terbuka</p><p class="mt-1 text-3xl font-extrabold text-amber-600">{{ number_format($stats['open']) }}</p></div>
    <div class="card card-pad"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Partial</p><p class="mt-1 text-3xl font-extrabold text-indigo-600">{{ number_format($stats['partial']) }}</p></div>
    <div class="card card-pad"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Completed</p><p class="mt-1 text-3xl font-extrabold text-emerald-600">{{ number_format($stats['completed']) }}</p></div>
    <div class="card card-pad"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Belum Diambil (PCS)</p><p class="mt-1 text-3xl font-extrabold text-rose-600">{{ number_format($stats['not_taken']) }}</p></div>
</div>

<form method="GET" action="{{ route('summary.index') }}" class="card card-pad mb-5 flex flex-wrap items-end gap-3">
    <div class="min-w-[16rem]">
        <label class="label">Cari</label>
        <input class="input" name="q" value="{{ request('q') }}" placeholder="No. SO / customer">
    </div>
    <label class="flex items-center gap-2 pb-2 text-sm font-semibold text-slate-600">
        <input type="checkbox" name="all" value="1" @checked(request('all'))> Tampilkan semua (termasuk selesai)
    </label>
    <button class="btn btn-ghost">Filter</button>
</form>

<div class="space-y-4">
@forelse ($rows as $row)
    @php
        $so = $row['so'];
        $state = $row['state'];
    @endphp
    <div class="card overflow-hidden">
        <div class="card-pad flex flex-wrap items-center justify-between gap-3 border-b border-slate-100">
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('summary.show', $so) }}" class="font-mono text-sm font-extrabold text-indigo-700 hover:underline">{{ $so->so_number }}</a>
                <span class="text-sm font-semibold text-slate-700">{{ $so->customer?->name }}</span>
                <span class="text-xs text-slate-400">{{ $so->order_date?->format('d/m/Y') }}</span>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600">{{ $so->branch?->code }}</span>
                @if ($so->fulfillment_branch_id && $so->fulfillment_branch_id !== $so->branch_id)
                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700">Pemasok: {{ $so->fulfillmentBranch?->code }}</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if ($state['picking'])<span class="text-xs font-bold text-amber-600">▸ Picking</span>@endif
                @if ($state['picked'])<span class="text-xs font-bold text-indigo-600">✓ Picked</span>@endif
                @if ($state['packing'])<span class="text-xs font-bold text-amber-600">▸ Packing</span>@endif
                @if ($state['packed'])<span class="text-xs font-bold text-indigo-600">✓ Packed</span>@endif
                @if ($state['delivery'])<span class="text-xs font-bold text-indigo-700">▸ {{ ucfirst(str_replace('_', ' ', $state['delivery'])) }}</span>@endif
                @if ($state['delivered'])<span class="text-xs font-bold text-emerald-600">✓ Diterima</span>@endif
                <x-badge status="{{ $so->status }}" />
            </div>
        </div>

        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>Item</th><th class="text-right">Dipesan</th><th class="text-right">Diambil</th><th class="text-right">Di-pack</th><th class="text-right">Dikirim</th><th class="text-right">Sisa</th><th>Lokasi Stok</th></tr></thead>
                <tbody>
                @foreach ($row['lines'] as $l)
                    @php $line = $l['line']; @endphp
                    <tr>
                        <td>
                            <p class="font-bold text-slate-800">{{ $line->item?->name }}</p>
                            <p class="text-[11px] text-slate-400">{{ $line->item?->sku }} · {{ $line->uom?->code }}</p>
                        </td>
                        <td class="text-right">{{ number_format($l['requested']) }}</td>
                        <td class="text-right font-semibold text-slate-700">{{ number_format($line->fulfilled_qty) }}</td>
                        <td class="text-right font-semibold text-slate-700">{{ number_format($l['packed']) }}</td>
                        <td class="text-right font-semibold text-slate-700">{{ number_format($l['delivering']) }}</td>
                        <td class="text-right font-bold {{ $l['remaining'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($l['remaining']) }}</td>
                        <td class="text-[11px] text-slate-500">{{ $l['locations'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card card-pad text-center text-slate-400">Tidak ada SO.</div>
@endforelse
</div>

<div class="mt-5">{{ $orders->links() }}</div>
@endsection
