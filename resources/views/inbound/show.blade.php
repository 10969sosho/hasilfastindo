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

<div class="w-full max-w-full space-y-5">
    {{-- Info dokumen & total dipindah ke atas (dari sidebar) agar tabel item/serial lebar penuh --}}
    <div class="card card-pad">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-extrabold text-slate-900">Info Dokumen</h2>
            <p class="text-2xl font-extrabold text-indigo-700">{{ number_format($gr->items->sum('total_pcs')) }} <span class="text-xs text-slate-400">PCS</span></p>
        </div>
        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">No. Dokumen</dt><dd class="font-mono font-bold text-slate-800">{{ $gr->doc_number }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">No. PO</dt><dd class="font-mono font-bold">{{ $gr->po_no ?: '-' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Supplier</dt><dd class="text-right font-semibold">{{ $gr->supplier?->name }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">BIN Staging</dt><dd class="font-mono font-bold">{{ $gr->location?->code ?: '-' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Diterima</dt><dd class="font-semibold">{{ $gr->received_at?->format('d/m/Y H:i') ?: '-' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Oleh</dt><dd class="font-semibold">{{ $gr->user?->name ?: '-' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Ditempatkan</dt><dd class="font-semibold">{{ number_format($gr->items->sum('qty_placed')) }} PCS</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Catatan</dt><dd class="text-right text-slate-600">{{ $gr->notes ?: '-' }}</dd></div>
        </dl>
    </div>

    <div>
        <div class="card overflow-hidden">
            <div class="card-pad border-b border-slate-100">
                <h2 class="text-sm font-extrabold text-slate-900">Item Penerimaan</h2>
                <p class="hint">Barcode unik dibuat otomatis per baris (HF-RCV-...)</p>
                @if ($navSummary->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($navSummary as $row)
                            @php $hasDiff = abs($row['diff']) > 0.001; @endphp
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $hasDiff ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $hasDiff ? '⚠ ' : '✓ ' }}{{ $row['sku'] }}: NAV {{ rtrim(rtrim(number_format($row['nav'], 2, '.', ''), '0'), '.') }}
                                · Fisik {{ rtrim(rtrim(number_format($row['physical'], 2, '.', ''), '0'), '.') }} PCS
                                @if ($hasDiff)
                                    · Selisih {{ $row['diff'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($row['diff'], 2, '.', ''), '0'), '.') }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="table-scroll w-full">
                <table class="table w-full min-w-[76rem]">
                    <thead>
                        <tr><th class="min-w-[12rem]">Barcode</th><th class="min-w-[14rem]">Item</th><th>Batch</th><th class="text-right">Qty</th><th>UoM</th><th class="text-right">× Konversi</th><th class="text-right">Total PCS</th><th class="text-right">Berat (kg)</th><th>BIN / Rak</th><th class="text-right">Ditempatkan</th></tr>
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
                            <td class="text-right">{{ $line->weight !== null ? rtrim(rtrim(number_format($line->weight, 3, '.', ''), '0'), '.') : '-' }}</td>
                            <td class="font-mono text-xs">{{ $line->bin_no ?: '-' }}</td>
                            <td class="text-right font-bold {{ $line->qty_placed >= $line->total_pcs ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ number_format($line->qty_placed) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-6 text-center text-slate-400">Tidak ada item.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Serial UOM (1 record = 1 unit fisik) --}}
        @if ($serials->isNotEmpty())
            <div class="card mt-5 overflow-hidden">
                <div class="card-pad border-b border-slate-100">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-extrabold text-slate-900">Serial UOM ({{ $serials->count() }} unit)</h2>
                            <p class="hint">QR Code label memakai kode_uom_serialno. Scan untuk melihat detail / picking.</p>
                        </div>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
                            {{ $serials->where('qty_uom_out', '>', 0)->count() }} terpicking · {{ $serials->where('qty_uom_out', 0)->count() }} belum
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="hint font-bold">Cetak QR per item:</span>
                        @foreach ($serials->groupBy('sku') as $sku => $group)
                            <a href="{{ route('inbound.print', ['inbound' => $gr, 'sku' => $sku]) }}" target="_blank"
                               class="btn btn-ghost btn-sm">
                                <x-icon name="barcode" :size="14" /> {{ $sku }} ({{ $group->count() }})
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="table-scroll w-full" style="max-height:32rem;overflow-y:auto">
                    <table class="table w-full min-w-[56rem]">
                        <thead>
                            <tr><th class="min-w-[16rem]">Serial No</th><th>UoM</th><th class="text-right">PCS</th><th class="text-right">Berat (kg)</th><th>BIN</th><th>Status</th><th>SO / Pick</th><th class="w-28 text-right">Label QR</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($serials as $s)
                            <tr>
                                <td class="font-mono text-[11px] font-bold text-indigo-700">{{ $s->kode_uom_serialno }}</td>
                                <td class="font-bold">{{ $s->uom }}</td>
                                <td class="text-right">{{ rtrim(rtrim(number_format($s->qty_smallest_unit_in, 2, '.', ''), '0'), '.') }}</td>
                                <td class="text-right">{{ $s->weight !== null ? rtrim(rtrim(number_format($s->weight, 3, '.', ''), '0'), '.') : '-' }}</td>
                                <td class="font-mono text-xs">{{ $s->bin_no ?: '-' }}</td>
                                <td>
                                    @if ($s->isPicked())
                                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700">Dipick</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-500">Belum</span>
                                    @endif
                                </td>
                                <td class="text-[11px] text-slate-600">{{ $s->so_no ?: '-' }}{{ $s->pick_no ? ' · '.$s->pick_no : '' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('inbound.print', ['inbound' => $gr, 'serial' => $s->kode_uom_serialno]) }}" target="_blank"
                                       class="btn btn-ghost btn-sm" title="Cetak label QR unit ini">
                                        <x-icon name="printer" :size="14" /> QR
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Putaway --}}
        @if ($canPutaway)
            <div class="card mt-5 card-pad">
                <h2 class="mb-1 text-sm font-extrabold text-slate-900">Putaway ke BIN Tujuan</h2>
                <p class="hint mb-4">Pindahkan barang dari BIN staging ke rak tujuan (boleh sebagian per item).</p>

                <form method="POST" action="{{ route('inbound.putaway', $gr) }}">
                    @csrf
                    <div class="table-scroll w-full">
                        <table class="table w-full min-w-[40rem]">
                            <thead>
                                <tr><th class="min-w-[16rem]">Item</th><th class="text-right">Sisa</th><th class="min-w-[10rem]">BIN Tujuan</th><th class="w-40">Qty (PCS)</th></tr>
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
</div>
@endsection
