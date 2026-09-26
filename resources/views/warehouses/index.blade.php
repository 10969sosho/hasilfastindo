@extends('layouts.app')

@section('title', 'Gudang & BIN Code')
@section('subtitle', 'Kelola gudang per cabang dan lokasi BIN (area, rak, shelf, bin)')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('warehouses.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[13rem]">
            <label class="label">Cari Gudang</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Kode / nama gudang">
        </div>
        <div class="min-w-[12rem]">
            <label class="label">Cabang</label>
            <select class="input" name="branch_id">
                <option value="">Semua</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <div class="flex gap-2">
        <a href="{{ route('bins.index') }}" class="btn btn-ghost"><x-icon name="map" :size="16" /> Daftar BIN</a>
        <a href="{{ route('warehouses.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Gudang Baru</a>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($warehouses as $warehouse)
        <div class="card card-pad">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-mono text-xs font-bold text-indigo-600">{{ $warehouse->code }}</p>
                    <h2 class="mt-0.5 text-base font-extrabold text-slate-900">{{ $warehouse->name }}</h2>
                    <p class="text-xs text-slate-500">{{ $warehouse->branch?->name }}</p>
                </div>
                @if ($warehouse->is_default)
                    <x-badge status="ready" label="Utama" />
                @endif
            </div>

            <div class="mt-3">
                <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">BIN di gudang ini ({{ $warehouse->locations->count() }})</p>
                <div class="flex flex-wrap gap-1.5">
                    @forelse ($warehouse->locations as $loc)
                        <a href="{{ route('bins.edit', $loc) }}" class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-[11px] font-bold text-slate-600 hover:border-indigo-400 hover:text-indigo-700">{{ $loc->code }}</a>
                    @empty
                        <span class="text-xs text-slate-400">Belum ada BIN</span>
                    @endforelse
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <a href="{{ route('bins.index', ['warehouse_id' => $warehouse->id]) }}" class="btn btn-ghost btn-sm flex-1">Lihat BIN</a>
                <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-primary btn-sm flex-1"><x-icon name="edit" :size="14" /> Edit</a>
                <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" onsubmit="return wmsConfirm('Hapus gudang {{ $warehouse->name }}?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm"><x-icon name="trash" :size="14" /></button>
                </form>
            </div>
        </div>
    @empty
        <div class="card card-pad text-center text-slate-400">Belum ada gudang.</div>
    @endforelse
</div>
@endsection
