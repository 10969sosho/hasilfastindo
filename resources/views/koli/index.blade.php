@extends('layouts.app')

@section('title', 'Generate Barcode Koli')
@section('subtitle', 'Tentukan pembagian barang ke koli, berat koli, dan cetak label barcode koli.')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('koli.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[15rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="No. packing list / No. SO">
        </div>
        <div class="min-w-[11rem]">
            <label class="label">Status</label>
            <select class="input" name="status">
                <option value="">Semua</option>
                @foreach (['draft', 'packed', 'loaded'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>
    <p class="hint max-w-sm">Pilih packing list / SO yang sudah selesai dipick untuk menerbitkan barcode koli.</p>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr><th>No. Packing List</th><th>SO</th><th>Customer</th><th class="text-right">Koli</th><th>Koli Berbarcode</th><th>Status</th><th class="text-right">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse ($packings as $packing)
                @php
                    $koliCount = $packing->boxes->count();
                    $generated = $packing->boxes->filter(fn ($b) => str_starts_with((string) $b->box_barcode, 'KOLI-'))->count();
                @endphp
                <tr>
                    <td class="font-mono text-xs font-bold text-indigo-700">{{ $packing->no_packing }}</td>
                    <td class="font-mono text-xs text-slate-600">{{ $packing->salesOrder?->so_number ?: '-' }}</td>
                    <td class="text-slate-700">{{ $packing->customer?->name ?? $packing->salesOrder?->customer?->name }}</td>
                    <td class="text-right font-bold">{{ $koliCount }}</td>
                    <td>
                        @if ($koliCount === 0)
                            <span class="text-xs text-slate-400">Belum ada koli</span>
                        @elseif ($generated === $koliCount)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Lengkap ({{ $generated }})</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">{{ $generated }} / {{ $koliCount }}</span>
                        @endif
                    </td>
                    <td><x-badge status="{{ $packing->status }}" /></td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1.5">
                            @if ($generated > 0)
                                <a href="{{ route('koli.print', $packing) }}" class="btn btn-ghost btn-sm"><x-icon name="printer" :size="14" /> Cetak</a>
                            @endif
                            <a href="{{ route('koli.create', $packing) }}" class="btn btn-primary btn-sm">Generate Koli</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-slate-400">Belum ada packing list.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $packings->links() }}</div>
</div>
@endsection
