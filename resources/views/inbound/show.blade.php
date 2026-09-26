@extends('layouts.app')

@section('title', 'Detail Penerimaan '.$gr->doc_number)
@section('subtitle', $gr->supplier?->name.' · '.$gr->warehouse?->code.' · '.ucfirst($gr->status))

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $gr->status }}" label="{{ ucfirst($gr->status) }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Cabang: {{ $gr->branch?->name }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Gudang: {{ $gr->warehouse?->code }}</span>
        @if ($gr->source_so)<span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">Source SO: {{ $gr->source_so }}</span>@endif
    </div>

    <div class="flex gap-2">
        <a href="{{ route('inbound.print', $gr) }}" class="btn btn-ghost"><x-icon name="printer" :size="16" /> Cetak Label</a>
        <a href="{{ route('inbound.index') }}" class="btn btn-ghost">Kembali</a>
        @if ($canReceive)
            <form method="POST" action="{{ route('inbound.receive', $gr) }}">
                @csrf
                <button class="btn btn-success"><x-icon name="inbound" :size="16" /> Terima Barang</button>
            </form>
        @endif
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="card-pad border-b border-slate-100">
                <h2 class="text-sm font-extrabold text-slate-900">Item Penerimaan</h2>
                <p class="hint">Barcode unik dibuat otomatis per baris (HF-RCV-...)</p>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr><th>Barcode</th><th>Item</th><th>Batch</th><th class="text-right">Qty</th><th>UoM</th><th class="text-right">× Konversi</th><th class="text-right">Total PCS</th><th class="text-right">Ditempatkan</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($gr->items as $line)
                        <tr>
                            <td class="font-mono text-[11px] font-bold text-indigo-700">{{ $line->original_barcode }}</td>
                            <td>
                                <p class="font-bold text-slate-800">{{ $line->item?->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $line->item?->sku }}</p>
                            </td>
                            <td class="font-mono text-xs text-slate-600">{{ $line->batch_no ?: '-' }}</td>
                            <td class="text-right font-semibold">{{ number_format($line->received_qty, 3) }}</td>
                            <td><span class="font-bold">{{ $line->receivedUom?->code }}</span></td>
                            <td class="text-right text-slate-600">{{ rtrim(rtrim(number_format($line->conversion_multiplier, 4), '0'), '.') }}</td>
                            <td class="text-right font-bold text-slate-800">{{ number_format($line->total_pcs) }}</td>
                            <td class="text-right font-bold {{ $line->qty_placed >= $line->total_pcs ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ number_format($line->qty_placed) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-slate-400">Tidak ada item.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Putaway --}}
        @if ($canPutaway)
            <div class="card mt-5 card-pad">
                <h2 class="mb-1 text-sm font-extrabold text-slate-900">Putaway ke BIN Tujuan</h2>
                <p class="hint mb-4">Pindahkan barang dari BIN staging ke rak tujuan (boleh sebagian per item).</p>

                <form method="POST" action="{{ route('inbound.putaway', $gr) }}">
                    @csrf
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr><th>Item</th><th class="text-right">Sisa</th><th>BIN Tujuan</th><th class="w-32">Qty (PCS)</th></tr>
                            </thead>
                            <tbody>
                            @php $rowIndex = 0; @endphp
                            @foreach ($gr->items as $line)
                                @php $remaining = $line->total_pcs - $line->qty_placed; @endphp
                                @if ($remaining > 0.001)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="lines[{{ $rowIndex }}][goods_receipt_item_id]" value="{{ $line->id }}">
                                            <p class="font-bold text-slate-800">{{ $line->item?->name }}</p>
                                            <p class="text-[11px] text-slate-400">{{ $line->original_barcode }}</p>
                                        </td>
                                        <td class="text-right font-bold text-amber-600">{{ number_format($remaining) }}</td>
                                        <td>
                                            <select class="input" name="lines[{{ $rowIndex }}][location_id]" required>
                                                @foreach ($bins as $b)
                                                    <option value="{{ $b->id }}" @selected($line->target_bin_id == $b->id)>{{ $b->code }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input class="input" type="number" step="0.001" min="0.001" max="{{ $remaining }}" name="lines[{{ $rowIndex }}][qty]" value="{{ $remaining }}" required></td>
                                    </tr>
                                    @php $rowIndex++; @endphp
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($rowIndex > 0)
                        <div class="mt-4">
                            <button class="btn btn-primary"><x-icon name="transfer" :size="16" /> Proses Putaway</button>
                        </div>
                    @else
                        <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">Seluruh barang sudah ditempatkan di BIN tujuan.</p>
                    @endif
                </form>
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Info Dokumen</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">No. Dokumen</dt><dd class="font-mono font-bold text-slate-800">{{ $gr->doc_number }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Supplier</dt><dd class="text-right font-semibold">{{ $gr->supplier?->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">BIN Staging</dt><dd class="font-mono font-bold">{{ $gr->location?->code ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Diterima</dt><dd class="font-semibold">{{ $gr->received_at?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Oleh</dt><dd class="font-semibold">{{ $gr->user?->name ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Catatan</dt><dd class="text-right text-slate-600">{{ $gr->notes ?: '-' }}</dd></div>
            </dl>
        </div>

        <div class="card card-pad bg-slate-50">
            <p class="text-xs font-bold text-slate-600">Total Penerimaan</p>
            <p class="mt-1 text-2xl font-extrabold text-indigo-700">{{ number_format($gr->items->sum('total_pcs')) }} <span class="text-xs text-slate-400">PCS</span></p>
            <p class="hint mt-1">Ditempatkan: {{ number_format($gr->items->sum('qty_placed')) }} PCS</p>
        </div>
    </div>
</div>
@endsection
