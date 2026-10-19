@extends('layouts.app')

@section('title', 'Konversi Satuan')
@section('subtitle', 'Perintah konversi (breakdown / repack) berbasis scan barcode — tanpa posting jurnal ke NAV.')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('conversion.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[15rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="No. perintah konversi">
        </div>
        <div class="min-w-[11rem]">
            <label class="label">Status</label>
            <select class="input" name="status">
                <option value="">Semua</option>
                @foreach (['open', 'completed', 'cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    @if ($canCreate)
        <a href="{{ route('conversion.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Perintah Konversi Baru</a>
    @else
        <p class="hint max-w-xs text-right">Perintah konversi dibuat oleh Superadmin / Pusat.</p>
    @endif
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr><th>No. Perintah</th><th>Item</th><th>Dari UOM</th><th>Ke UOM</th><th class="text-right">Rencana</th><th class="text-right">Dieksekusi</th><th>Cabang</th><th>Status</th><th class="text-right">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse ($conversions as $c)
                <tr>
                    <td class="font-mono text-xs font-bold text-indigo-700">{{ $c->conversion_no }}</td>
                    <td>
                        <p class="font-bold text-slate-800">{{ $c->item?->name }}</p>
                        <p class="text-[11px] text-slate-400">{{ $c->item?->sku }}</p>
                    </td>
                    <td>{{ $c->fromUom?->code }}</td>
                    <td>{{ $c->toUom?->code }}</td>
                    <td class="text-right font-semibold">{{ number_format((float) $c->planned_qty, 0) }}</td>
                    <td class="text-right font-bold text-emerald-600">{{ number_format((float) $c->executed_qty, 0) }}</td>
                    <td class="text-slate-600">{{ $c->branch?->code }}</td>
                    <td><x-badge status="{{ $c->status }}" /></td>
                    <td class="text-right"><a href="{{ route('conversion.show', $c) }}" class="btn btn-primary btn-sm">Eksekusi</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-8 text-center text-slate-400">Belum ada perintah konversi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $conversions->links() }}</div>
</div>
@endsection
