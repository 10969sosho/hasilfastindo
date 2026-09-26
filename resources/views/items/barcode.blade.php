@extends('layouts.print')

@section('title', 'Cetak Barcode Item')

@section('content')
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-lg font-extrabold">Label Barcode Item</h1>
        <p class="text-xs text-slate-500">Total {{ $items->count() }} label · dicetak {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <form method="GET" action="{{ route('items.barcode') }}" class="flex gap-2">
        <input class="w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm" name="q" value="{{ $q }}" placeholder="Cari item...">
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Filter</button>
    </form>
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($items as $item)
        <div class="rounded-lg border border-slate-300 p-4 text-center">
            <p class="text-sm font-extrabold uppercase tracking-wide">{{ $item->name }}</p>
            <p class="mb-2 text-xs text-slate-500">{{ $item->sku }} · {{ $item->category?->name ?? '-' }}</p>
            <svg class="mx-auto" data-barcode="{{ $item->barcode ?: $item->sku }}"></svg>
            <p class="mt-1 font-mono text-xs tracking-widest">{{ $item->barcode ?: $item->sku }}</p>
            <p class="mt-2 text-[11px] text-slate-500">Rp {{ number_format($item->sell_price) }} / {{ $item->baseUom?->code }} · Min {{ number_format($item->min_stock) }} PCS</p>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">HASIL FASTINDO</p>
        </div>
    @empty
        <p class="text-sm text-slate-400">Item tidak ditemukan.</p>
    @endforelse
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
    document.querySelectorAll('[data-barcode]').forEach(el => {
        try {
            JsBarcode(el, el.dataset.barcode, { format: 'CODE128', width: 2, height: 60, fontSize: 14, margin: 6 });
        } catch (e) { el.textContent = el.dataset.barcode; }
    });
</script>
@endpush
