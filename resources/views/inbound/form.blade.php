@extends('layouts.app')

@section('title', 'Penerimaan Baru')
@section('subtitle', 'Penerimaan barang dari Purchase Order Dynamics NAV')

@section('content')
<form method="POST" action="{{ route('inbound.store') }}" class="w-full max-w-full space-y-5">
    @csrf

    @if ($navPo)
        {{-- Ringkasan Ringkas PO NAV --}}
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="rounded bg-indigo-50 px-2.5 py-1 font-mono text-sm font-bold text-indigo-700">{{ $navPo->po_no }}</span>
                    <span class="text-sm font-semibold text-slate-800">{{ $navPo->supplier_name }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <a href="{{ route('nav-po.show', $navPo) }}" class="text-indigo-600 hover:underline">Detail PO NAV</a>
                    <span class="text-slate-300">·</span>
                    <a href="{{ route('nav-po.index') }}" class="text-slate-500 hover:underline">Pilih PO Lain</a>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg bg-slate-50 p-2.5">
                    <p class="text-[11px] font-medium text-slate-500">Total Item di PO</p>
                    <p class="mt-0.5 text-base font-bold text-slate-900">{{ $navTotalItems }} Item</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-2.5">
                    <p class="text-[11px] font-medium text-slate-500">Qty PO NAV</p>
                    <p class="mt-0.5 text-base font-bold text-slate-900">{{ number_format((float) $navTotalQty, 0) }} PCS</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-2.5">
                    <p class="text-[11px] font-medium text-slate-500">Qty Outstanding Base</p>
                    <p class="mt-0.5 text-base font-bold text-amber-700">{{ number_format((float) $navOutstanding, 0) }} PCS</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-2.5">
                    <p class="text-[11px] font-medium text-slate-500">Tanggal PO</p>
                    <p class="mt-0.5 text-base font-bold text-slate-900">{{ $navPo->order_date?->format('d/m/Y') ?: '-' }}</p>
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
                <select class="input" name="supplier_id" required>
                    <option value="">- Pilih supplier -</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" @selected(old('supplier_id', $navSupplierId) == $s->id)>{{ $s->code }} — {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">No. PO (NAV)</label>
                <input class="input font-mono bg-slate-50" name="po_no" value="{{ old('po_no', $navPo?->po_no) }}" readonly>
            </div>
            <div>
                <label class="label required">Gudang Tujuan</label>
                <select class="input" name="warehouse_id" id="warehouse_id" onchange="location.href='{{ route('inbound.create') }}?warehouse_id='+this.value+'&nav_po_id={{ $navPo?->id }}'" required>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" @selected($defaultWarehouseId == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Tanggal Terima</label>
                <input class="input" type="date" name="received_at" value="{{ old('received_at', now()->format('Y-m-d')) }}">
            </div>
            <div class="sm:col-span-2 lg:col-span-2">
                <label class="label">Source SO (opsional)</label>
                <input class="input font-mono" name="source_so" value="{{ old('source_so') }}" placeholder="SO-XXXXX">
            </div>
            <div class="sm:col-span-2 lg:col-span-2">
                <label class="label">Catatan</label>
                <input class="input" name="notes" value="{{ old('notes') }}" placeholder="Catatan penerimaan...">
            </div>
        </div>
    </div>

    {{-- Daftar Item Penerimaan --}}
    <div class="card card-pad">
        <div class="mb-3 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Daftar Item Penerimaan</h2>
                <p class="text-xs text-slate-500">Sesuaikan satuan dan kuantitas kemasan yang diterima fisik terhadap Qty NAV.</p>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" id="addRow"><x-icon name="plus" :size="14" /> Tambah Baris</button>
        </div>

        <div id="navSummary" class="mb-3 flex flex-wrap gap-2"></div>

        <div class="table-scroll w-full">
            <table class="table w-full min-w-[64rem]">
                <thead>
                    <tr>
                        <th class="min-w-[15rem]">Item</th>
                        <th class="min-w-[7rem]">Batch No</th>
                        <th class="w-28">Satuan</th>
                        <th class="w-28 text-right">Qty Kemasan</th>
                        <th class="w-32 text-right">Total Fisik (PCS)</th>
                        <th class="w-36 text-right">Kapasitas (PCS/UOM)</th>
                        <th class="w-32 text-right">Qty PO NAV (PCS)</th>
                        <th class="w-36 text-right">Qty Outstanding Base (PCS)</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody id="itemBody">
                    @php
                        $oldRows = old('items');
                        $rowCount = max(count($navRows), is_array($oldRows) ? count($oldRows) : 0, 1);
                    @endphp
                    @for ($idx = 0; $idx < $rowCount; $idx++)
                        @php
                            $row = $navRows[$idx] ?? ['item_id' => null, 'uom_id' => null, 'received_qty' => 1, 'pcs_per_uom' => 1, 'total_pcs' => null, 'nav_qty' => null, 'outstanding' => null];
                        @endphp
                    <tr class="item-row">
                        <td>
                            <select class="input item-select" name="items[{{ $idx }}][item_id]" required>
                                <option value="">- Pilih item -</option>
                                @foreach ($items as $i)
                                    <option value="{{ $i->id }}" @selected(old("items.$idx.item_id", $row['item_id']) == $i->id) data-meta="{{ json_encode($itemMeta[$i->id] ?? null) }}">{{ $i->sku }} — {{ $i->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input class="input font-mono" name="items[{{ $idx }}][batch_no]" value="{{ old("items.$idx.batch_no") }}" placeholder="B-XXXX">
                        </td>
                        <td>
                            <select class="input uom-select" name="items[{{ $idx }}][received_uom_id]" required>
                                @foreach ($uoms as $u)
                                    <option value="{{ $u->id }}" @selected(old("items.$idx.received_uom_id", $row['uom_id']) == $u->id) data-code="{{ $u->code }}">{{ $u->code }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input class="input qty-input text-right font-medium" type="number" step="0.001" min="0" name="items[{{ $idx }}][received_qty]" value="{{ old("items.$idx.received_qty", $row['received_qty']) }}" required>
                        </td>
                        <td>
                            <input class="input total-input text-right font-bold text-indigo-700" type="number" step="0.001" min="0" name="items[{{ $idx }}][total_pcs]" value="{{ old("items.$idx.total_pcs", $row['total_pcs']) }}">
                        </td>
                        <td>
                            <input class="input per-input text-right text-slate-700" type="number" step="0.001" min="0" name="items[{{ $idx }}][pcs_per_uom]" value="{{ old("items.$idx.pcs_per_uom", $row['pcs_per_uom'] ?? 1) }}">
                        </td>
                        <td>
                            <input class="input nav-input text-right bg-slate-50 font-mono text-xs font-bold text-slate-600" type="number" step="0.001" min="0" name="items[{{ $idx }}][nav_qty]" value="{{ old("items.$idx.nav_qty", $row['nav_qty']) }}" readonly>
                        </td>
                        <td class="text-right font-mono text-xs font-bold text-amber-700">
                            {{ isset($row['outstanding']) && $row['outstanding'] !== null ? number_format((float) $row['outstanding'], 0) : '-' }}
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();syncNav();">✕</button>
                        </td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bar Tombol Simpan --}}
    <div class="card card-pad flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-500">Barcode serial fisik akan digenerate otomatis berdasarkan Qty Kemasan.</p>
        <div class="flex gap-2">
            <a href="{{ route('inbound.index') }}" class="btn btn-ghost">Batal</a>
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan Penerimaan</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    let rowIndex = document.querySelectorAll('#itemBody .item-row').length;

    function getMultiplier(meta, uomId) {
        if (!meta) return 1;
        if (parseInt(uomId) === parseInt(meta.base_uom_id)) return 1;
        if (meta.conversions && meta.conversions[uomId]) {
            return parseFloat(meta.conversions[uomId]) || 1;
        }
        return 1;
    }

    function recalc(row, source) {
        const sel = row.querySelector('.item-select');
        const meta = JSON.parse(sel.selectedOptions[0]?.dataset.meta || 'null');
        const uomId = row.querySelector('.uom-select')?.value;
        const qtyInput = row.querySelector('.qty-input');
        const perInput = row.querySelector('.per-input');
        const totalInput = row.querySelector('.total-input');

        // Jika perubahan berasal dari pemilihan item atau satuan: set kapasitas dari master konversi
        if (source === 'uom' || source === 'item' || !perInput.value) {
            const mult = getMultiplier(meta, uomId);
            perInput.value = mult;
        }

        const qty = parseFloat(qtyInput.value || 0);
        const per = parseFloat(perInput.value || 1);

        if (source === 'total') {
            const total = parseFloat(totalInput.value || 0);
            if (per > 0) {
                qtyInput.value = Math.round((total / per) * 1000) / 1000;
            }
        } else {
            // Kalkulasi standar: Total Fisik (PCS) = Qty Kemasan * Kapasitas (PCS/UOM)
            totalInput.value = Math.round(qty * per * 1000) / 1000;
        }
    }

    function syncNav() {
        const seen = new Set();
        document.querySelectorAll('#itemBody .item-row').forEach(row => {
            const id = row.querySelector('.item-select').value;
            const nav = row.querySelector('.nav-input');
            if (!nav) return;
            if (!id || seen.has(id)) {
                nav.value = '';
                nav.classList.add('opacity-40');
            } else {
                nav.classList.remove('opacity-40');
                seen.add(id);
            }
        });
        renderNavSummary();
    }

    function renderNavSummary() {
        const box = document.getElementById('navSummary');
        if (!box) return;

        const map = new Map();
        document.querySelectorAll('#itemBody .item-row').forEach(row => {
            const sel = row.querySelector('.item-select');
            if (!sel.value) return;

            const entry = map.get(sel.value) || {
                label: sel.selectedOptions[0]?.textContent || sel.value,
                nav: 0, hasNav: false, physical: 0,
            };

            entry.physical += parseFloat(row.querySelector('.total-input')?.value || 0);
            const navVal = row.querySelector('.nav-input')?.value;
            if (navVal !== '' && navVal !== null && navVal !== undefined) {
                entry.nav += parseFloat(navVal);
                entry.hasNav = true;
            }
            map.set(sel.value, entry);
        });

        const fmt = n => n.toLocaleString('id-ID');

        box.innerHTML = [...map.values()].map(e => {
            if (!e.hasNav) return '';
            const diff = e.physical - e.nav;
            const match = Math.abs(diff) < 0.001;
            return match
                ? `<span class="rounded bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">✓ ${e.label}: Sesuai PO NAV (${fmt(e.nav)} PCS)</span>`
                : `<span class="rounded bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">⚠ ${e.label}: Selisih (${diff > 0 ? '+' : ''}${fmt(diff)} PCS vs PO ${fmt(e.nav)} PCS)</span>`;
        }).join('');
    }

    function refreshRow(row, source) {
        recalc(row, source);
        syncNav();
    }

    document.addEventListener('input', e => {
        const row = e.target.closest('.item-row');
        if (!row) return;

        if (e.target.classList.contains('qty-input') || e.target.classList.contains('per-input')) {
            refreshRow(row, 'qty');
        } else if (e.target.classList.contains('total-input')) {
            refreshRow(row, 'total');
        }
    });

    document.addEventListener('change', e => {
        const row = e.target.closest('.item-row');
        if (!row) return;

        if (e.target.classList.contains('item-select')) {
            refreshRow(row, 'item');
        } else if (e.target.classList.contains('uom-select')) {
            refreshRow(row, 'uom');
        }
    });

    document.getElementById('addRow')?.addEventListener('click', () => {
        const firstRow = document.querySelector('#itemBody .item-row');
        if (!firstRow) return;

        const html = firstRow.outerHTML.replace(/items\[\d+\]/g, () => `items[${rowIndex}]`);
        document.getElementById('itemBody').insertAdjacentHTML('beforeend', html);

        const newRow = document.getElementById('itemBody').lastElementChild;
        newRow.querySelectorAll('input').forEach(i => { i.value = ''; });
        newRow.querySelectorAll('select').forEach(s => { s.selectedIndex = 0; });
        newRow.querySelector('.qty-input').value = 1;

        rowIndex++;
        refreshRow(newRow, 'item');
    });

    document.querySelectorAll('.item-row').forEach(row => recalc(row, 'init'));
    syncNav();
</script>
@endpush
