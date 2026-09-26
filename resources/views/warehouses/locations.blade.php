@extends('layouts.app')

@section('title', 'BIN Code')
@section('subtitle', 'Lokasi penyimpanan: area, rak, shelf, bin')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('bins.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[13rem]">
            <label class="label">Cari BIN</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="BIN-A-01 / Zone A">
        </div>
        <div class="min-w-[14rem]">
            <label class="label">Gudang</label>
            <select class="input" name="warehouse_id">
                <option value="">Semua</option>
                @foreach ($warehouses as $w)
                    <option value="{{ $w->id }}" @selected(request('warehouse_id') == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <a href="{{ route('bins.create', request()->has('warehouse_id') ? ['warehouse_id' => request('warehouse_id')] : []) }}" class="btn btn-primary">
        <x-icon name="plus" :size="16" /> BIN Baru
    </a>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr><th>Kode BIN</th><th>Gudang</th><th>Cabang</th><th>Area</th><th>Rak</th><th>Shelf</th><th>Bin</th><th class="text-right">Stok (PCS)</th><th>Status</th><th class="text-right">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse ($locations as $loc)
                <tr>
                    <td class="font-mono text-xs font-bold text-indigo-700">{{ $loc->code }}</td>
                    <td class="text-slate-700">{{ $loc->warehouse?->code }}</td>
                    <td class="text-slate-600">{{ $loc->warehouse?->branch?->code }}</td>
                    <td class="text-slate-600">{{ $loc->area ?: '-' }}</td>
                    <td class="text-slate-600">{{ $loc->rack ?: '-' }}</td>
                    <td class="text-slate-600">{{ $loc->shelf ?: '-' }}</td>
                    <td class="text-slate-600">{{ $loc->bin ?: '-' }}</td>
                    <td class="text-right font-bold {{ ($binStocks[$loc->id]->pcs ?? 0) > 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                        {{ number_format($binStocks[$loc->id]->pcs ?? 0) }}
                    </td>
                    <td><x-badge status="{{ $loc->is_active ? 'ready' : 'cancelled' }}" label="{{ $loc->is_active ? 'Aktif' : 'Non-aktif' }}" /></td>
                    <td>
                        <div class="flex justify-end gap-1.5">
                            <a href="{{ route('bins.edit', $loc) }}" class="btn btn-primary btn-sm"><x-icon name="edit" :size="14" /> Edit</a>
                            <form method="POST" action="{{ route('bins.destroy', $loc) }}" onsubmit="return wmsConfirm('Hapus BIN {{ $loc->code }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm"><x-icon name="trash" :size="14" /></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="py-8 text-center text-slate-400">Belum ada BIN.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $locations->links() }}</div>
</div>
@endsection
