@extends('layouts.app')

@section('title', 'Dokumen Penerimaan — '.$gr->doc_number)

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2">
        <x-badge status="{{ $gr->status }}" label="{{ ucfirst($gr->status) }}" />
        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">Cabang: {{ $gr->branch?->name }}</span>
        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">Gudang: {{ $gr->warehouse?->code }}</span>
        @if ($gr->source_so)<span class="rounded bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">SO: {{ $gr->source_so }}</span>@endif
    </div>

    <div class="flex gap-2">
        <a href="{{ route('inbound.print', $gr) }}" class="btn btn-ghost text-xs"><x-icon name="printer" :size="15" /> Cetak Label QR</a>
        <a href="{{ route('inbound.index') }}" class="btn btn-ghost text-xs">Kembali</a>
        @if ($canReceive)
            <form method="POST" action="{{ route('inbound.receive', $gr) }}">
                @csrf
                <button class="btn btn-success text-xs"><x-icon name="inbound" :size="15" /> Terima Barang</button>
            </form>
        @endif
    </div>
</div>

<div class="w-full max-w-full space-y-4">
    {{-- Info Dokumen Minimalist --}}
    <div class="card p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-2.5">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Info Dokumen</h2>
            <div class="text-right">
                <span class="text-lg font-extrabold text-indigo-700">{{ number_format($gr->items->sum('total_pcs')) }}</span>
                <span class="text-xs font-semibold text-slate-500">PCS Total</span>
            </div>
        </div>
        <dl class="grid gap-x-6 gap-y-2.5 text-xs sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">No. Dokumen</dt>
                <dd class="mt-0.5 font-mono font-bold text-slate-900">{{ $gr->doc_number }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Referensi PO NAV</dt>
                <dd class="mt-0.5 font-mono font-bold text-slate-900">{{ $gr->po_no ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Supplier</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $gr->supplier?->name }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Waktu Terima</dt>
                <dd class="mt-0.5 text-slate-800">{{ $gr->received_at?->format('d/m/Y H:i') ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Petugas</dt>
                <dd class="mt-0.5 text-slate-800">{{ $gr->user?->name ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Lokasi Staging</dt>
                <dd class="mt-0.5 font-mono font-bold text-slate-800">{{ $gr->location?->code ?: '-' }}</dd>
            </div>
            <div class="lg:col-span-2">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Catatan</dt>
                <dd class="mt-0.5 text-slate-700">{{ $gr->notes ?: '-' }}</dd>
            </div>
        </dl>
    </div>

    <div>
        <div class="card overflow-hidden">
            <div class="p-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Item Penerimaan</h2>
                @if ($navSummary->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($navSummary as $row)
                            @php $hasDiff = abs($row['diff']) > 0.001; @endphp
                            <span class="rounded px-2 py-0.5 text-[11px] font-bold {{ $hasDiff ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                @if ($hasDiff)
                                    ⚠ {{ $row['sku'] }}: Selisih {{ $row['diff'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($row['diff'], 2, '.', ''), '0'), '.') }} PCS
                                @else
                                    ✓ {{ $row['sku'] }}: Sesuai PO NAV ({{ rtrim(rtrim(number_format($row['nav'], 2, '.', ''), '0'), '.') }} PCS)
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="table-scroll w-full">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Barcode</th>
                            <th>Item</th>
                            <th>Batch</th>
                            <th class="text-right">Qty</th>
                            <th>UoM</th>
                            <th class="text-right">Total PCS</th>
                            <th class="text-right">Ditempatkan</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($gr->items as $line)
                        <tr class="hover:bg-slate-50/70">
                            <td class="font-mono text-xs font-bold text-indigo-700">{{ $line->original_barcode }}</td>
                            <td>
                                <p class="font-bold text-slate-900">{{ $line->item?->name }}</p>
                                <p class="text-[11px] font-mono text-slate-400">{{ $line->item?->sku }}</p>
                            </td>
                            <td class="font-mono text-xs text-slate-700">{{ $line->batch_no ?: '-' }}</td>
                            <td class="text-right font-semibold text-slate-800">{{ number_format($line->received_qty, 0) }}</td>
                            <td><span class="font-bold text-slate-700">{{ $line->receivedUom?->code }}</span></td>
                            <td class="text-right font-bold text-slate-900">{{ number_format($line->total_pcs) }} PCS</td>
                            <td class="text-right font-bold {{ $line->qty_placed >= $line->total_pcs ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ number_format($line->qty_placed) }} PCS
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-400">Tidak ada item.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Serial UOM (1 record = 1 unit fisik) --}}
        @if ($serials->isNotEmpty())
            <div class="card mt-4 overflow-hidden">
                <div class="p-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Identitas Serial Unit / QR ({{ $serials->count() }} unit)</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach ($serials->groupBy('sku') as $sku => $group)
                            <a href="{{ route('inbound.print', ['inbound' => $gr, 'sku' => $sku]) }}" target="_blank"
                               class="btn btn-ghost btn-sm text-xs">
                                <x-icon name="printer" :size="13" /> Cetak {{ $sku }} ({{ $group->count() }})
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="table-scroll w-full" style="max-height:30rem;overflow-y:auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Serial No</th>
                                <th>Satuan</th>
                                <th class="text-right">Isi (PCS)</th>
                                <th>Status</th>
                                <th class="w-32 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($serials as $s)
                            <tr class="hover:bg-slate-50/70">
                                <td class="font-mono text-xs font-bold text-indigo-700">{{ $s->kode_uom_serialno }}</td>
                                <td class="font-bold text-slate-700">{{ $s->uom }}</td>
                                <td class="text-right font-mono font-bold text-slate-900">{{ rtrim(rtrim(number_format($s->qty_smallest_unit_in, 2, '.', ''), '0'), '.') }} PCS</td>
                                <td>
                                    @if ($s->isPicked())
                                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Dipick</span>
                                    @else
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">Tersedia</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('inbound.print', ['inbound' => $gr, 'serial' => $s->kode_uom_serialno]) }}" target="_blank"
                                       class="btn btn-ghost btn-sm text-xs" title="Cetak Label">
                                        <x-icon name="printer" :size="13" /> Cetak
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
            <div class="card mt-4 p-4">
                <div class="border-b border-slate-100 pb-2 mb-3">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Putaway ke Rak</h2>
                </div>

                <form method="POST" action="{{ route('inbound.putaway', $gr) }}">
                    @csrf
                    <div class="table-scroll w-full">
                        <table class="table w-full">
                            <thead>
                                <tr><th>Item</th><th class="text-right">Sisa (PCS)</th><th>Rak Tujuan</th><th class="w-40">Qty (PCS)</th></tr>
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
                                            <p class="text-[11px] font-mono text-slate-400">{{ $line->original_barcode }}</p>
                                        </td>
                                        <td class="text-right font-bold text-amber-600">{{ number_format($remaining) }}</td>
                                        <td>
                                            <select class="input searchable" name="lines[{{ $rowIndex }}][location_id]" required>
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
                        <div class="mt-3">
                            <button class="btn btn-primary text-xs"><x-icon name="transfer" :size="15" /> Proses Putaway</button>
                        </div>
                    @else
                        <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">Seluruh barang sudah ditempatkan di rak tujuan.</p>
                    @endif
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
