@extends('layouts.app')

@section('title', 'Repack / Pecah Satuan')
@section('subtitle', 'Konversi satuan dengan barcode baru & riwayat batch asal')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('repack.index') }}" class="flex items-end gap-3">
        <div class="min-w-[16rem]">
            <label class="label">Cari No. RPK</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="RPK-202609-0001">
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>
    <a href="{{ route('repack.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Repack Baru</a>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>No. RPK</th><th>Tanggal</th><th>Cabang</th><th>Gudang</th><th>Item Asal</th><th>Hasil</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($repacks as $r)
                @php $line = $r->items->first(); @endphp
                <tr>
                    <td>
                        <a href="{{ route('repack.show', $r) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $r->repack_no }}</a>
                    </td>
                    <td class="text-slate-600">{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
                    <td class="text-slate-700">{{ $r->branch?->code }}</td>
                    <td class="text-slate-600">{{ $r->warehouse?->code }}</td>
                    <td class="text-slate-700">{{ $line?->item?->name }} <span class="text-xs text-slate-400">({{ number_format($line?->source_qty ?? 0) }} {{ $line?->sourceUom?->code }})</span></td>
                    <td class="text-slate-700">{{ number_format($line?->target_qty ?? 0) }} {{ $line?->targetUom?->code }}
                        <p class="font-mono text-[11px] text-indigo-600">{{ $line?->target_barcode_new }}</p>
                    </td>
                    <td><x-badge status="{{ $r->status }}" /></td>
                    <td class="text-right"><a href="{{ route('repack.show', $r) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-400">Belum ada repack.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $repacks->links() }}</div>
</div>
@endsection
