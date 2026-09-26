@extends('layouts.app')

@section('title', 'Packing List')
@section('subtitle', 'Pemecahan hasil picking ke dalam dus dengan barcode per box')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('packing.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[15rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="PKL-202609-0001">
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
    <a href="{{ route('packing.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Packing Baru</a>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>No. Packing</th><th>SO</th><th>Customer</th><th>Cabang</th><th class="text-right">Dus</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($packingLists as $pl)
                <tr>
                    <td>
                        <a href="{{ route('packing.show', $pl) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $pl->no_packing }}</a>
                        <p class="text-[11px] text-slate-400">{{ $pl->created_at?->format('d/m/Y H:i') }}</p>
                    </td>
                    <td class="font-mono text-xs text-slate-700">{{ $pl->salesOrder?->so_number }}</td>
                    <td class="text-slate-700">{{ $pl->customer?->name }}</td>
                    <td class="text-slate-600">{{ $pl->branch?->code }}</td>
                    <td class="text-right font-bold text-slate-800">{{ $pl->total_box }}</td>
                    <td><x-badge status="{{ $pl->status }}" /></td>
                    <td class="text-right"><a href="{{ route('packing.show', $pl) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-slate-400">Belum ada packing list.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $packingLists->links() }}</div>
</div>
@endsection
