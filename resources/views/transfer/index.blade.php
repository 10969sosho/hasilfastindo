@extends('layouts.app')

@section('title', 'Transfer Antar Cabang')
@section('subtitle', 'Kirim barang antar cabang dengan pelacakan selisih penerimaan')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('transfer.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem]">
            <label class="label">Cari No. TRF</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="TRF-202609-0001">
        </div>
        <div class="min-w-[11rem]">
            <label class="label">Status</label>
            <select class="input" name="status">
                <option value="">Semua</option>
                @foreach (['draft', 'in_transit', 'received', 'discrepancy', 'cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>
    <a href="{{ route('transfer.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Transfer Baru</a>
</div>

<div class="grid gap-5 lg:grid-cols-4">
    <div class="lg:col-span-3">
        <div class="card overflow-hidden">
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr><th>No. TRF</th><th>Dari</th><th>Ke</th><th>Item</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($transfers as $t)
                        <tr>
                            <td>
                                <a href="{{ route('transfer.show', $t) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $t->transfer_no }}</a>
                                <p class="text-[11px] text-slate-400">{{ $t->created_at?->format('d/m/Y H:i') }}</p>
                            </td>
                            <td class="text-slate-700">{{ $t->fromBranch?->code }}</td>
                            <td class="text-slate-700">{{ $t->toBranch?->code }}</td>
                            <td class="text-slate-600">{{ $t->items->count() }} item</td>
                            <td><x-badge status="{{ $t->status }}" label="{{ ucfirst(str_replace('_', ' ', $t->status)) }}" /></td>
                            <td class="text-right"><a href="{{ route('transfer.show', $t) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">Belum ada transfer.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-pad border-t border-slate-100">{{ $transfers->links() }}</div>
        </div>
    </div>

    <div class="card card-pad h-fit">
        <h2 class="mb-3 text-sm font-extrabold text-slate-900">Menunggu Diterima</h2>
        @if ($incoming->isEmpty())
            <p class="hint">Tidak ada transfer in-transit.</p>
        @else
            <ul class="space-y-2">
                @foreach ($incoming as $t)
                    <li>
                        <a href="{{ route('transfer.show', $t) }}" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 hover:border-indigo-400">
                            <span class="font-mono text-xs font-bold text-indigo-700">{{ $t->transfer_no }}</span>
                            <span class="text-xs font-bold text-amber-600">{{ $t->fromBranch?->code }} → {{ $t->toBranch?->code }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
