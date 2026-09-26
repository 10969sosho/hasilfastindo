@extends('layouts.app')

@section('title', 'Data Item')
@section('subtitle', 'Master barang, konversi satuan KRT / DUS / PCS, dan barcode')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('items.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Nama, SKU, atau barcode">
        </div>
        <div class="min-w-[11rem]">
            <label class="label">Kategori</label>
            <select class="input" name="category_id">
                <option value="">Semua</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 pb-2 text-sm font-semibold text-slate-600">
            <input type="checkbox" name="inactive" value="1" @checked(request()->boolean('inactive')) class="h-4 w-4 rounded border-slate-300 text-indigo-600">
            Tampilkan non-aktif
        </label>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <div class="flex gap-2">
        <a href="{{ route('items.barcode') }}" class="btn btn-ghost"><x-icon name="barcode" :size="16" /> Cetak Barcode</a>
        <a href="{{ route('items.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Item Baru</a>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th><th>Nama Item</th><th>Kategori</th><th>Satuan Dasar</th>
                    <th class="text-right">Min. Stok</th><th class="text-right">Stok Total</th><th>Status</th><th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="font-mono text-xs font-bold text-slate-700">{{ $item->sku }}</td>
                    <td>
                        <p class="font-bold text-slate-800">{{ $item->name }}</p>
                        <p class="text-[11px] text-slate-400">Barcode: {{ $item->barcode ?: '-' }}</p>
                    </td>
                    <td class="text-slate-600">{{ $item->category?->name ?? '-' }}</td>
                    <td><span class="font-bold text-slate-700">{{ $item->baseUom?->code }}</span></td>
                    <td class="text-right text-slate-600">{{ number_format($item->min_stock) }}</td>
                    <td class="text-right font-bold {{ $item->total_pcs <= $item->min_stock ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ number_format($item->total_pcs) }}
                    </td>
                    <td><x-badge status="{{ $item->is_active ? 'ready' : 'cancelled' }}" label="{{ $item->is_active ? 'Aktif' : 'Non-aktif' }}" /></td>
                    <td>
                        <div class="flex justify-end gap-1.5">
                            <a href="{{ route('items.print', $item) }}" class="btn btn-ghost btn-sm" title="Cetak barcode"><x-icon name="printer" :size="14" /></a>
                            <a href="{{ route('items.edit', $item) }}" class="btn btn-primary btn-sm"><x-icon name="edit" :size="14" /> Edit</a>
                            <form method="POST" action="{{ route('items.destroy', $item) }}" onsubmit="return wmsConfirm('Hapus item {{ $item->sku }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm"><x-icon name="trash" :size="14" /></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-400">Belum ada item.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $items->links() }}</div>
</div>
@endsection
