@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('subtitle', 'Goods receipt, konversi satuan, barcode HF-RCV, dan putaway ke BIN')

@section('content')
<div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach (['draft' => 'Draft', 'received' => 'Diterima', 'putaway' => 'Putaway', 'completed' => 'Selesai'] as $key => $label)
        <div class="kpi">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-extrabold {{ $key === 'completed' ? 'text-emerald-600' : 'text-slate-900' }}">{{ $summary[$key] }}</p>
        </div>
    @endforeach
</div>

<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('inbound.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="No. dokumen / SO sumber">
        </div>
        <div class="min-w-[11rem]">
            <label class="label">Status</label>
            <select class="input" name="status">
                <option value="">Semua</option>
                @foreach (['draft', 'received', 'putaway', 'completed'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <a href="{{ route('inbound.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Penerimaan Baru</a>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr><th>No. Dokumen</th><th>Supplier</th><th>Cabang</th><th>Gudang</th><th>Source SO</th><th class="text-right">Item</th><th>Status</th><th class="text-right">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse ($receipts as $gr)
                <tr>
                    <td>
                        <p class="font-mono text-xs font-bold text-indigo-700">{{ $gr->doc_number }}</p>
                        <p class="text-[11px] text-slate-400">{{ $gr->received_at?->format('d/m/Y H:i') ?: 'Belum diterima' }}</p>
                    </td>
                    <td class="text-slate-700">{{ $gr->supplier?->name }}</td>
                    <td class="text-slate-600">{{ $gr->branch?->code }}</td>
                    <td class="text-slate-600">{{ $gr->warehouse?->code }}</td>
                    <td class="font-mono text-xs text-slate-600">{{ $gr->source_so ?: '-' }}</td>
                    <td class="text-right font-bold text-slate-700">{{ $gr->items->count() }}</td>
                    <td><x-badge status="{{ $gr->status }}" /></td>
                    <td>
                        <div class="flex justify-end gap-1.5">
                            <a href="{{ route('inbound.print', $gr) }}" class="btn btn-ghost btn-sm" title="Cetak label"><x-icon name="printer" :size="14" /></a>
                            <a href="{{ route('inbound.show', $gr) }}" class="btn btn-primary btn-sm">Detail</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-400">Belum ada penerimaan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $receipts->links() }}</div>
</div>
@endsection
