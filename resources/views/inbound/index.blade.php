@extends('layouts.app')

@section('title', 'Penerimaan Barang')

@section('content')
<div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach (['draft' => 'Draft', 'received' => 'Diterima', 'putaway' => 'Putaway', 'completed' => 'Selesai'] as $key => $label)
        <div class="card p-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
            <p class="mt-0.5 text-xl font-extrabold {{ $key === 'completed' ? 'text-emerald-600' : 'text-slate-900' }}">{{ $summary[$key] }}</p>
        </div>
    @endforeach
</div>

<div class="card p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('inbound.index') }}" class="flex flex-wrap items-center gap-2.5">
        <div class="w-64">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Cari no. dokumen / SO...">
        </div>
        <div class="w-44">
            <select class="input searchable" name="status" placeholder="Semua Status">
                <option value="">Semua Status</option>
                @foreach (['draft', 'received', 'putaway', 'completed'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <div class="flex items-center gap-2">
        <a href="{{ route('nav-po.index') }}" class="btn btn-primary"><x-icon name="inbound" :size="15" /> Buka Daftar PO NAV</a>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>No. Dokumen</th>
                    <th>Supplier</th>
                    <th>Cabang</th>
                    <th>Gudang</th>
                    <th>Source SO</th>
                    <th class="text-right">Item</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($receipts as $gr)
                <tr class="hover:bg-slate-50/70">
                    <td>
                        <p class="font-mono text-xs font-bold text-indigo-700">{{ $gr->doc_number }}</p>
                        <p class="text-[11px] text-slate-400">{{ $gr->received_at?->format('d/m/Y H:i') ?: '-' }}</p>
                    </td>
                    <td class="font-medium text-slate-800">{{ $gr->supplier?->name }}</td>
                    <td class="text-slate-600">{{ $gr->branch?->code }}</td>
                    <td class="text-slate-600">{{ $gr->warehouse?->code }}</td>
                    <td class="font-mono text-xs text-slate-600">{{ $gr->source_so ?: '-' }}</td>
                    <td class="text-right font-bold text-slate-800">{{ $gr->items->count() }}</td>
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
    <div class="p-3 border-t border-slate-100">{{ $receipts->links() }}</div>
</div>
@endsection
