@extends('layouts.app')

@section('title', 'Pengeluaran Barang (SO)')
@section('subtitle', 'Sales order, pemenuhan FIFO, dan dukungan cross-branch')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('outbound.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[15rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="No. SO / nama customer">
        </div>
        <div class="min-w-[11rem]">
            <label class="label">Status</label>
            <select class="input" name="status">
                <option value="">Semua</option>
                @foreach (\App\Models\SalesOrder::STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <a href="{{ route('outbound.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> SO Baru</a>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr><th>No. SO</th><th>Tanggal</th><th>Customer</th><th>Cabang Pemilik</th><th>Gudang Pemasok</th><th>Progress</th><th>Status</th><th class="text-right">Nilai</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($orders as $so)
                @php
                    $ordered = $so->items->sum('requested_qty');
                    $fulfilled = $so->items->sum('fulfilled_qty');
                    $pct = $ordered > 0 ? min(100, round($fulfilled / $ordered * 100)) : 0;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('outbound.show', $so) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $so->so_number }}</a>
                        @if ($so->fulfillment_branch_id && $so->fulfillment_branch_id !== $so->branch_id)
                            <div class="mt-1"><x-badge status="in_transit" label="Cross-branch" /></div>
                        @endif
                    </td>
                    <td class="text-slate-600">{{ $so->order_date?->format('d/m/Y') }}</td>
                    <td class="text-slate-700">{{ $so->customer?->name }}</td>
                    <td class="text-slate-600">{{ $so->branch?->code }}</td>
                    <td class="text-slate-600">{{ $so->fulfillmentBranch?->code ?? '-' }}</td>
                    <td class="min-w-[9rem]">
                        <div class="flex items-center gap-2">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full {{ $pct === 100 ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="text-xs font-bold text-slate-600">{{ $pct }}%</span>
                        </div>
                        <p class="text-[11px] text-slate-400">{{ number_format($fulfilled) }} / {{ number_format($ordered) }}</p>
                    </td>
                    <td><x-badge status="{{ $so->status }}" /></td>
                    <td class="text-right text-slate-700">Rp {{ number_format($so->total_amount) }}</td>
                    <td class="text-right"><a href="{{ route('outbound.show', $so) }}" class="btn btn-primary btn-sm">Proses</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-8 text-center text-slate-400">Belum ada sales order.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $orders->links() }}</div>
</div>
@endsection
