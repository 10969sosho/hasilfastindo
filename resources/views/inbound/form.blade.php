@extends('layouts.app')

@section('title', 'Penerimaan Baru')
@section('subtitle', 'Penerimaan barang dari Purchase Order Dynamics NAV')

@section('content')
<form method="POST" action="{{ route('inbound.store') }}" id="inboundForm" class="w-full max-w-full space-y-5">
    @csrf

    @if ($navPo)
        {{-- Penunjuk QTY BASE PO NAV --}}
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="rounded bg-indigo-50 px-2.5 py-1 font-mono text-sm font-bold text-indigo-700">{{ $navPo->po_no }}</span>
                    <span class="text-sm font-bold text-slate-800">{{ $navPo->supplier_name }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <a href="{{ route('nav-po.show', $navPo) }}" class="text-indigo-600 font-semibold hover:underline">Detail PO NAV</a>
                    <span class="text-slate-300">·</span>
                    <a href="{{ route('nav-po.index') }}" class="text-slate-500 font-semibold hover:underline">Pilih PO Lain</a>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Qty Base (PO NAV)</p>
                    <p class="mt-1 text-lg font-extrabold text-slate-900">{{ number_format((float) $navTotalQty, 0) }} PCS</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Qty Base Sudah Diterima</p>
                    <p class="mt-1 text-lg font-extrabold text-slate-700">{{ number_format((float) ($navTotalQty - $navOutstanding), 0) }} PCS</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Sisa Qty Outstanding Base</p>
                    <p class="mt-1 text-lg font-extrabold text-amber-700">{{ number_format((float) $navOutstanding, 0) }} PCS</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Item (SKU)</p>
                    <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $navTotalItems }} Item</p>
                </div>
            </div>

            @if (! $navSupplierId)
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                    Supplier <b>{{ $navPo->supplier_name }}</b> belum terdaftar di Master Supplier lokal. Silakan pilih supplier pada form di bawah.
                </div>
            @endif
            @if ($navMissingSkus)
                <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                    SKU belum terdaftar di Master Item: <span class="font-mono">{{ implode(', ', $navMissingSkus) }}</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Dokumen Penerimaan --}}
    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-bold text-slate-900">Dokumen Penerimaan</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label required">Supplier</label>
                <select class="input searchable" name="supplier_id" required placeholder="Cari Supplier...">
                    <option value="">- Cari Supplier -</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" @selected(old('supplier_id', $navSupplierId) == $s->id)>{{ $s->code }} — {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">No. PO (NAV)</label>
                <input class="input font-mono bg-slate-50 text-slate-700" name="po_no" value="{{ old('po_no', $navPo?->po_no) }}" readonly>
            </div>
            <div>
                <label class="label required">Gudang Tujuan</label>
                <select class="input searchable" name="warehouse_id" id="warehouse_id" required placeholder="Pilih Gudang...">
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" @selected($defaultWarehouseId == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Tanggal Terima</label>
                <input class="input" type="date" name="received_at" value="{{ old('received_at', now()->format('Y-m-d')) }}">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Source SO (opsional)</label>
                <input class="input font-mono" name="source_so" value="{{ old('source_so') }}" placeholder="SO-XXXXX">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Catatan Penerimaan</label>
                <input class="input" name="notes" value="{{ old('notes') }}" placeholder="Catatan penerimaan...">
            </div>
        </div>
    </div>

    {{-- Penerimaan Dikelompokkan Per Item --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Daftar Item &amp; Kemasan Fisik Penerimaan</h2>
                <p class="text-xs text-slate-500">Kuantitas acuan Qty NAV diisi otomatis. Pilih satuan kemasan fisik per item dan tentukan Batch No per dus.</p>
            </div>
            <div id="navSummary" class="flex flex-wrap gap-2 text-xs"></div>
        </div>

        @php
            $globalRowIdx = 0;
        @endphp

        @foreach ($navPoItems as $itemIdx => $itemRow)
            @php
                $targetBase = (float) $itemRow['remaining_qty_pcs'];
                $navTotalBase = (float) $itemRow['nav_qty_pcs'];
                $uoms = $itemRow['available_uoms'] ?? [];
                $bestUom = collect($uoms)->first(fn($u) => $u['multiplier'] > 1 && $targetBase % $u['multiplier'] === 0)
                           ?: collect($uoms)->first();
                $mult = $bestUom['multiplier'] ?? 1;
                $uomId = $bestUom['id'] ?? null;
                $uomCode = $bestUom['code'] ?? 'PCS';
                $unitCount = $mult > 0 && $targetBase > 0 ? (int) floor($targetBase / $mult) : 0;
                $renderCount = min($unitCount, 50);
            @endphp
            <div class="card card-pad item-card border-l-4 border-l-indigo-600"
                 id="item-card-{{ $itemRow['item_id'] }}"
                 data-item-id="{{ $itemRow['item_id'] }}"
                 data-sku="{{ $itemRow['sku'] }}"
                 data-base-qty="{{ $targetBase }}"
                 data-nav-qty="{{ $navTotalBase }}">

                {{-- Header Item --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-700">{{ $itemRow['sku'] }}</span>
                            <span class="text-sm font-bold text-slate-900">{{ $itemRow['item_name'] }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Target Qty Base: <span class="font-bold text-slate-800">{{ number_format($targetBase, 0) }} PCS</span>
                            (Total PO: {{ number_format($navTotalBase, 0) }} PCS · Satuan Dasar: {{ $itemRow['base_uom'] }})
                        </p>
                    </div>

                    {{-- Badge Status Pemenuhan --}}
                    <div class="item-status-badge">
                        <span class="status-indicator rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                            0 / {{ number_format($targetBase, 0) }} PCS
                        </span>
                    </div>
                </div>

                {{-- Alat Tambah Kemasan Cepat --}}
                <div class="mt-3 rounded-lg bg-slate-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-2">Terima Barang dalam Satuan Apa?</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[14rem] flex-1">
                            <label class="label text-[11px]">Pilih Satuan (UOM)</label>
                            <select class="input text-xs gen-uom" onchange="updateUomInfo(this)">
                                @foreach ($itemRow['available_uoms'] as $uomOpt)
                                    <option value="{{ $uomOpt['id'] }}" data-mult="{{ $uomOpt['multiplier'] }}" data-code="{{ $uomOpt['code'] }}" @selected($bestUom && $bestUom['id'] === $uomOpt['id'])>
                                        {{ $uomOpt['code'] }} (1 {{ $uomOpt['code'] }} = {{ number_format($uomOpt['multiplier'], 0) }} {{ $itemRow['base_uom'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-32">
                            <label class="label text-[11px]">Jumlah Kemasan</label>
                            <input type="number" min="1" step="1" class="input text-xs gen-qty" placeholder="Contoh: 10" value="{{ $renderCount ?: 10 }}">
                        </div>
                        <div class="w-36">
                            <label class="label text-[11px]">Batch No (Lot)</label>
                            <input type="text" class="input font-mono text-xs gen-batch" placeholder="B-2026A" value="B-{{ date('Y') }}">
                        </div>
                        <button type="button" class="btn btn-primary text-xs" onclick="generateUnitsForCard(this, {{ $itemRow['item_id'] }})">
                            <x-icon name="plus" :size="14" /> Buat Baris Kemasan
                        </button>
                    </div>
                </div>

                {{-- Tabel Kemasan Fisik (1 Baris = 1 Kemasan/Dus) --}}
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-700">Rincian Kemasan Fisik (1 baris = 1 kemasan/dus):</span>
                        <div class="flex items-center gap-2">
                            <button type="button" class="text-xs text-indigo-600 hover:underline" onclick="applyBatchToAll({{ $itemRow['item_id'] }})">
                                Terapkan Batch ke Semua Dus
                            </button>
                            <span class="text-slate-300">·</span>
                            <button type="button" class="text-xs text-rose-600 hover:underline" onclick="clearAllUnits({{ $itemRow['item_id'] }})">
                                Hapus Semua
                            </button>
                        </div>
                    </div>

                    <div class="table-scroll w-full border border-slate-200 rounded-lg overflow-hidden">
                        <table class="table w-full">
                            <thead>
                                <tr class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-600">
                                    <th class="w-14 text-center">No</th>
                                    <th class="w-32">Satuan</th>
                                    <th class="w-36 text-right">Kapasitas (PCS)</th>
                                    <th class="w-36 text-right">Fisik (PCS)</th>
                                    <th>Batch No (Lot)</th>
                                    <th class="w-12 text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="unit-table-body" id="unit-body-{{ $itemRow['item_id'] }}">
                                @for ($u = 0; $u < $renderCount; $u++)
                                    <tr class="unit-row border-b border-slate-100 hover:bg-slate-50">
                                        <td class="font-mono text-xs text-slate-500 text-center py-2">#{{ $u + 1 }}</td>
                                        <td class="font-bold text-slate-700 py-2">
                                            <span class="rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700 font-mono">{{ $uomCode }}</span>
                                        </td>
                                        <td class="text-right font-mono text-xs text-slate-600 py-2">{{ $mult }} PCS</td>
                                        <td class="text-right font-mono text-xs font-bold text-slate-800 py-2">{{ $mult }} PCS</td>
                                        <td class="py-2">
                                            <input type="text" name="items[{{ $globalRowIdx }}][batch_no]" value="B-{{ date('Y') }}" placeholder="B-XXXX" class="input font-mono py-1 px-2 text-xs row-batch">
                                            <input type="hidden" name="items[{{ $globalRowIdx }}][item_id]" value="{{ $itemRow['item_id'] }}">
                                            <input type="hidden" name="items[{{ $globalRowIdx }}][received_uom_id]" value="{{ $uomId }}">
                                            <input type="hidden" name="items[{{ $globalRowIdx }}][received_qty]" value="1">
                                            <input type="hidden" name="items[{{ $globalRowIdx }}][pcs_per_uom]" value="{{ $mult }}">
                                            <input type="hidden" name="items[{{ $globalRowIdx }}][total_pcs]" value="{{ $mult }}">
                                            <input type="hidden" name="items[{{ $globalRowIdx }}][nav_qty]" value="{{ $targetBase }}" class="row-nav-qty">
                                        </td>
                                        <td class="text-center py-2">
                                            <button type="button" class="btn btn-danger btn-sm py-0.5 px-2 text-xs" onclick="removeUnitRow(this, {{ $itemRow['item_id'] }})">✕</button>
                                        </td>
                                    </tr>
                                    @php $globalRowIdx++; @endphp
                                @endfor
                            </tbody>
                        </table>
                    </div>

                    {{-- Footer Subtotal Item --}}
                    <div class="mt-2 flex flex-wrap items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                        <div class="text-slate-600">
                            Total Kemasan: <span class="font-bold text-slate-900 unit-count">{{ $renderCount }} Kemasan</span>
                        </div>
                        <div class="font-bold">
                            Total Fisik: <span class="text-sm font-extrabold text-indigo-700 total-pcs-val">{{ number_format($renderCount * $mult, 0) }}</span> PCS
                            <span class="text-slate-500 font-normal">/ Target {{ number_format($targetBase, 0) }} PCS</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Bar Simpan Penerimaan --}}
    <div class="card card-pad flex flex-wrap items-center justify-between gap-3 sticky bottom-3 z-30 shadow-lg border-indigo-200">
        <div>
            <p class="text-xs font-bold text-slate-800" id="globalSummary">Memuat ringkasan penerimaan...</p>
            <p class="text-[11px] text-slate-500">Pastikan Total Fisik per item tidak melebihi Qty Base PO NAV.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('inbound.index') }}" class="btn btn-ghost">Batal</a>
            <button class="btn btn-primary" id="btnSubmit" type="submit">
                <x-icon name="check" :size="16" /> Simpan Penerimaan
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    let globalIndex = {{ $globalRowIdx }};
    const navItemsData = @json($navPoItems);

    function updateUomInfo(selectEl) {
        // Dropdown UOM change hook
    }

    function generateUnitsForCard(btn, itemId) {
        const card = document.getElementById(`item-card-${itemId}`);
        if (!card) return;

        const uomSelect = card.querySelector('.gen-uom');
        const qtyInput = card.querySelector('.gen-qty');
        const batchInput = card.querySelector('.gen-batch');

        const uomId = parseInt(uomSelect.value);
        const selectedOpt = uomSelect.selectedOptions[0];
        const uomCode = selectedOpt.getAttribute('data-code') || 'DUS';
        const mult = parseFloat(selectedOpt.getAttribute('data-mult') || 1);
        const qtyCount = parseInt(qtyInput.value || 0);
        const batchNo = (batchInput.value || '').trim();

        if (qtyCount < 1) {
            alert('Masukkan jumlah kemasan minimal 1.');
            return;
        }

        const tbody = document.getElementById(`unit-body-${itemId}`);
        const baseQty = parseFloat(card.getAttribute('data-base-qty') || 0);

        for (let i = 0; i < qtyCount; i++) {
            const currentRows = tbody.querySelectorAll('tr').length + 1;
            const tr = document.createElement('tr');
            tr.className = 'unit-row border-b border-slate-100 hover:bg-slate-50';
            tr.innerHTML = `
                <td class="font-mono text-xs text-slate-500 text-center py-2">#${currentRows}</td>
                <td class="font-bold text-slate-700 py-2">
                    <span class="rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700 font-mono">${uomCode}</span>
                </td>
                <td class="text-right font-mono text-xs text-slate-600 py-2">${mult} PCS</td>
                <td class="text-right font-mono text-xs font-bold text-slate-800 py-2">${mult} PCS</td>
                <td class="py-2">
                    <input type="text" name="items[${globalIndex}][batch_no]" value="${batchNo}" placeholder="B-XXXX" class="input font-mono py-1 px-2 text-xs row-batch">
                    <input type="hidden" name="items[${globalIndex}][item_id]" value="${itemId}">
                    <input type="hidden" name="items[${globalIndex}][received_uom_id]" value="${uomId}">
                    <input type="hidden" name="items[${globalIndex}][received_qty]" value="1">
                    <input type="hidden" name="items[${globalIndex}][pcs_per_uom]" value="${mult}">
                    <input type="hidden" name="items[${globalIndex}][total_pcs]" value="${mult}">
                    <input type="hidden" name="items[${globalIndex}][nav_qty]" value="${baseQty}" class="row-nav-qty">
                </td>
                <td class="text-center py-2">
                    <button type="button" class="btn btn-danger btn-sm py-0.5 px-2 text-xs" onclick="removeUnitRow(this, ${itemId})">✕</button>
                </td>
            `;
            tbody.appendChild(tr);
            globalIndex++;
        }

        recalculateCard(itemId);
    }

    function removeUnitRow(btn, itemId) {
        const row = btn.closest('tr');
        row.remove();

        const tbody = document.getElementById(`unit-body-${itemId}`);
        if (tbody) {
            tbody.querySelectorAll('tr').forEach((tr, idx) => {
                const td = tr.querySelector('td:first-child');
                if (td) td.textContent = `#${idx + 1}`;
            });
        }

        recalculateCard(itemId);
    }

    function applyBatchToAll(itemId) {
        const card = document.getElementById(`item-card-${itemId}`);
        if (!card) return;
        const batchInput = card.querySelector('.gen-batch');
        const batch = (batchInput.value || '').trim();
        if (!batch) {
            alert('Masukkan Batch No pada kotak atas terlebih dahulu.');
            return;
        }

        const tbody = document.getElementById(`unit-body-${itemId}`);
        tbody.querySelectorAll('.row-batch').forEach(input => {
            input.value = batch;
        });
    }

    function clearAllUnits(itemId) {
        if (!confirm('Hapus seluruh baris kemasan untuk item ini?')) return;
        const tbody = document.getElementById(`unit-body-${itemId}`);
        if (tbody) tbody.innerHTML = '';
        recalculateCard(itemId);
    }

    function recalculateCard(itemId) {
        const card = document.getElementById(`item-card-${itemId}`);
        if (!card) return;

        const tbody = document.getElementById(`unit-body-${itemId}`);
        const rows = tbody.querySelectorAll('tr');
        const targetBase = parseFloat(card.getAttribute('data-base-qty') || 0);

        let totalPcs = 0;
        rows.forEach(tr => {
            const pcsInput = tr.querySelector('input[name$="[total_pcs]"]');
            totalPcs += parseFloat(pcsInput?.value || 0);
        });

        card.querySelector('.unit-count').textContent = `${rows.length} Kemasan`;
        card.querySelector('.total-pcs-val').textContent = totalPcs.toLocaleString('id-ID');

        const badgeContainer = card.querySelector('.item-status-badge');
        const diff = totalPcs - targetBase;
        const match = Math.abs(diff) < 0.001;

        if (totalPcs === 0) {
            card.classList.remove('border-l-emerald-500', 'border-l-rose-500', 'border-l-amber-500');
            card.classList.add('border-l-indigo-600');
            badgeContainer.innerHTML = `<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">0 / ${targetBase.toLocaleString('id-ID')} PCS (Belum Ada Kemasan)</span>`;
        } else if (match) {
            card.classList.remove('border-l-indigo-600', 'border-l-rose-500', 'border-l-amber-500');
            card.classList.add('border-l-emerald-500');
            badgeContainer.innerHTML = `<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">✓ Pas Sesuai Qty Base (${totalPcs.toLocaleString('id-ID')} PCS)</span>`;
        } else if (diff < 0) {
            card.classList.remove('border-l-indigo-600', 'border-l-rose-500', 'border-l-emerald-500');
            card.classList.add('border-l-amber-500');
            badgeContainer.innerHTML = `<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Diterima Sebagian (${totalPcs.toLocaleString('id-ID')} / ${targetBase.toLocaleString('id-ID')} PCS — Sisa ${Math.abs(diff).toLocaleString('id-ID')} PCS)</span>`;
        } else {
            card.classList.remove('border-l-indigo-600', 'border-l-emerald-500', 'border-l-amber-500');
            card.classList.add('border-l-rose-500');
            badgeContainer.innerHTML = `<span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-800">⚠ Melebihi Qty Base (${totalPcs.toLocaleString('id-ID')} PCS — Maksimal ${targetBase.toLocaleString('id-ID')} PCS)</span>`;
        }

        recalculateGlobal();
    }

    function recalculateGlobal() {
        const cards = document.querySelectorAll('.item-card');
        const submitBtn = document.getElementById('btnSubmit');
        const summaryText = document.getElementById('globalSummary');
        const navSummaryBox = document.getElementById('navSummary');

        let hasExcess = false;
        let totalReceived = 0;
        let totalTarget = 0;
        let completeItems = 0;

        const navSummaryBadges = [];

        cards.forEach(card => {
            const targetBase = parseFloat(card.getAttribute('data-base-qty') || 0);
            const sku = card.getAttribute('data-sku') || '';
            let itemPcs = 0;
            card.querySelectorAll('.unit-table-body tr').forEach(tr => {
                const p = tr.querySelector('input[name$="[total_pcs]"]');
                itemPcs += parseFloat(p?.value || 0);
            });

            totalReceived += itemPcs;
            totalTarget += targetBase;

            if (itemPcs > targetBase + 0.001) {
                hasExcess = true;
                navSummaryBadges.push(`<span class="rounded bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700">⚠ ${sku}: Lebih (${itemPcs} > ${targetBase})</span>`);
            } else if (Math.abs(itemPcs - targetBase) < 0.001 && targetBase > 0) {
                completeItems++;
                navSummaryBadges.push(`<span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">✓ ${sku}: Sesuai PO (${itemPcs} PCS)</span>`);
            } else if (itemPcs > 0) {
                navSummaryBadges.push(`<span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700">⏳ ${sku}: Sebagian (${itemPcs} / ${targetBase} PCS)</span>`);
            }
        });

        if (navSummaryBox) {
            navSummaryBox.innerHTML = navSummaryBadges.join('');
        }

        if (hasExcess) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            summaryText.innerHTML = `<span class="text-rose-600 font-bold">Peringatan: Terdapat item yang melebihi Qty Base PO NAV. Total fisik tidak boleh melebihi target.</span>`;
        } else {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            summaryText.innerHTML = `Total Fisik Diterima: <b class="text-indigo-700">${totalReceived.toLocaleString('id-ID')} PCS</b> dari target <b class="text-slate-800">${totalTarget.toLocaleString('id-ID')} PCS</b> (${completeItems}/${cards.length} Item Selesai).`;
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.item-card').forEach(card => {
            const itemId = card.getAttribute('data-item-id');
            if (itemId) recalculateCard(itemId);
        });
        recalculateGlobal();
    });
</script>
@endpush
