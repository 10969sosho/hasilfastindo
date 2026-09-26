@extends('layouts.app')

@section('title', 'Monitoring Pusat')
@section('subtitle', 'Matriks stok nasional: ready, reserved SO, in-transit, on delivery')

@section('content')
<div class="grid gap-4 sm:grid-cols-4 mb-5">
    <div class="kpi"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Ready</p><p class="mt-1 text-2xl font-extrabold text-emerald-600">{{ number_format($totals['ready']) }}</p></div>
    <div class="kpi"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Reserved SO</p><p class="mt-1 text-2xl font-extrabold text-amber-600">{{ number_format($totals['reserved']) }}</p></div>
    <div class="kpi"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">In-Transit</p><p class="mt-1 text-2xl font-extrabold text-indigo-600">{{ number_format($totals['in_transit']) }}</p></div>
    <div class="kpi"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">On Delivery</p><p class="mt-1 text-2xl font-extrabold text-rose-600">{{ number_format($totals['on_delivery']) }}</p></div>
</div>

<form method="GET" action="{{ route('monitoring.index') }}" class="card card-pad mb-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <div class="sm:col-span-2">
        <label class="label">Cari (SKU / nama / barcode / batch)</label>
        <input class="input" name="q" value="{{ request('q') }}" placeholder="HF-... / Screws">
    </div>
    <div>
        <label class="label">Cabang</label>
        <select class="input" name="branch_id">
            <option value="">Semua</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Gudang</label>
        <select class="input" name="warehouse_id">
            <option value="">Semua</option>
            @foreach ($warehouses as $w)
                <option value="{{ $w->id }}" @selected(request('warehouse_id') == $w->id)>{{ $w->code }} — {{ $w->branch?->code }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Kategori</label>
        <select class="input" name="category_id">
            <option value="">Semua</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Status</label>
        <select class="input" name="status">
            <option value="">Semua</option>
            @foreach ($statuses as $k => $l)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-3 lg:col-span-6 flex flex-wrap items-end gap-3">
        <div class="min-w-[13rem]">
            <label class="label">BIN</label>
            <select class="input" name="location_id">
                <option value="">Semua</option>
                @foreach ($bins as $b)
                    <option value="{{ $b->id }}" @selected(request('location_id') == $b->id)>{{ $b->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[15rem]">
            <label class="label">Batch</label>
            <select class="input" name="batch_id">
                <option value="">Semua</option>
                @foreach ($batches as $b)
                    <option value="{{ $b->id }}" @selected(request('batch_id') == $b->id)>{{ $b->batch_number }} — {{ $b->item?->sku }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-primary">Terapkan</button>
            <a href="{{ route('monitoring.index') }}" class="btn btn-ghost">Reset</a>
        </div>
    </div>
</form>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Cabang</th><th>Gudang</th><th>BIN</th><th>Item</th><th>Batch</th>
                    <th class="text-right">Ready</th><th class="text-right">Reserved</th><th class="text-right">In-Transit</th><th class="text-right">On Delivery</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($matrix as $row)
                @php $stock = $row['stock']; @endphp
                <tr>
                    <td class="text-slate-700">{{ $stock->branch?->code }}</td>
                    <td class="text-slate-600">{{ $stock->warehouse?->code }}</td>
                    <td class="font-mono text-xs">{{ $stock->location?->code }}</td>
                    <td>
                        <p class="font-bold text-slate-800">{{ $stock->item?->name }}</p>
                        <p class="text-[11px] text-slate-400">{{ $stock->item?->sku }} · {{ $stock->item?->category?->name }}</p>
                    </td>
                    <td class="font-mono text-xs text-slate-600">{{ $stock->batch?->batch_number ?: '-' }}</td>
                    <td class="text-right font-extrabold text-slate-800">{{ number_format($stock->quantity_pcs) }}</td>
                    <td class="text-right font-bold {{ $row['reserved'] > 0 ? 'text-amber-600' : 'text-slate-300' }}">{{ number_format($row['reserved']) }}</td>
                    <td class="text-right font-bold {{ $row['in_transit'] > 0 ? 'text-indigo-600' : 'text-slate-300' }}">{{ number_format($row['in_transit']) }}</td>
                    <td class="text-right font-bold {{ $row['on_delivery'] > 0 ? 'text-rose-600' : 'text-slate-300' }}">{{ number_format($row['on_delivery']) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-8 text-center text-slate-400">Tidak ada data stok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $matrix->links() }}</div>
</div>
@endsection
