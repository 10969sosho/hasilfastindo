@extends('layouts.app')

@section('title', 'Master Cabang')
@section('subtitle', 'Daftar cabang beserta gudang, BIN, dan total stok')

@section('content')
<div class="mb-5 flex justify-end">
    <a href="{{ route('branches.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Cabang Baru</a>
</div>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($branches as $branch)
        <div class="card card-pad">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-mono text-xs font-bold text-indigo-600">{{ $branch->code }}</p>
                    <h2 class="mt-0.5 text-base font-extrabold text-slate-900">{{ $branch->name }}</h2>
                </div>
                @if ($branch->is_central)
                    <x-badge status="ready" label="Pusat" />
                @else
                    <x-badge status="slate" label="Cabang" />
                @endif
            </div>

            <p class="mt-2 text-sm text-slate-500">{{ $branch->address ?: '-' }}</p>
            <p class="mt-1 text-sm text-slate-500">Telp: {{ $branch->phone ?: '-' }}</p>

            <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                <div class="rounded-lg bg-slate-50 p-2">
                    <p class="text-[10px] font-bold uppercase text-slate-400">Gudang</p>
                    <p class="text-sm font-extrabold text-slate-700">{{ $branch->warehouses_count }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-2">
                    <p class="text-[10px] font-bold uppercase text-slate-400">BIN</p>
                    <p class="text-sm font-extrabold text-slate-700">{{ $branch->stats->bins }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-2">
                    <p class="text-[10px] font-bold uppercase text-slate-400">Stok</p>
                    <p class="text-sm font-extrabold text-indigo-700">{{ number_format($branch->stats->pcs) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-2">
                    <p class="text-[10px] font-bold uppercase text-slate-400">User</p>
                    <p class="text-sm font-extrabold text-slate-700">{{ $branch->stats->users }}</p>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <a href="{{ route('branches.edit', $branch) }}" class="btn btn-primary btn-sm flex-1"><x-icon name="edit" :size="14" /> Edit</a>
                <a href="{{ route('warehouses.index', ['branch_id' => $branch->id]) }}" class="btn btn-ghost btn-sm flex-1"><x-icon name="warehouse" :size="14" /> Gudang</a>
                <form method="POST" action="{{ route('branches.destroy', $branch) }}" onsubmit="return wmsConfirm('Hapus cabang {{ $branch->name }}?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm"><x-icon name="trash" :size="14" /></button>
                </form>
            </div>
        </div>
    @empty
        <div class="card card-pad text-center text-slate-400">Belum ada cabang.</div>
    @endforelse
</div>
@endsection
