@extends('layouts.app')

@section('title', 'Penerimaan Baru')
@section('subtitle', 'Input supplier, nomor dokumen, item & batch — barcode HF-RCV dibuat otomatis')

@section('content')
<form method="POST" action="{{ route('inbound.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf

    <div class="space-y-5 lg:col-span-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Dokumen Penerimaan</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label required">Supplier</label>
                    <select class="input" name="supplier_id" required>
                        <option value="">- Pilih supplier -</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected(old('supplier_id') == $s->id)>{{ $s->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">No. PO (NAV)</label>
                    <input class="input font-mono" name="po_no" value="{{ old('po_no') }}" placeholder="PO-2026-0001">
                </div>
                <div>
                    <label class="label">Source SO (opsional)</label>
                    <input class="input font-mono" name="source_so" value="{{ old('source_so') }}" placeholder="SO-202609-001">
                </div>
                <div>
                    <label class="label required">Gudang Tujuan</label>
                    <select class="input" name="warehouse_id" id="warehouse_id" onchange="location.href='{{ route('inbound.create') }}?warehouse_id='+this.value" required>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" @selected($defaultWarehouseId == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">BIN Staging (penerimaan sementara)</label>
                    <select class="input" name="destination_location_id">
                        <option value="">- BIN pertama gudang -</option>
                        @foreach ($bins as $b)
                            <option value="{{ $b->id }}" @selected(old('destination_location_id', $defaultBinId) == $b->id)>{{ $b->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Tanggal Terima</label>
                    <input class="input" type="date" name="received_at" value="{{ old('received_at', now()->format('Y-m-d')) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Catatan</label>
                    <textarea class="input" name="notes" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Item & Batch</h2>
                    <p class="hint">Konversi satuan otomatis ke PCS. Isi <b>Qty NAV</b> (unit terkecil) di baris pertama tiap SKU untuk pembanding. Barcode <span class="font-mono font-bold">HF-RCV-YYYYMMDD-XXXX</span> dibuat otomatis per baris.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="addRow"><x-icon name="plus" :size="14" /> Baris Item</button>
            </div>

            <div id="navSummary" class="mb-4 flex flex-wrap gap-2"></div>

            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr><th class="min-w-[14rem]">Item</th><th>Batch No</th><th>Satuan</th><th class="w-28">Qty</th><th class="w-32">Total PCS</th><th class="w-28">Qty NAV (PCS)</th><th>BIN Tujuan</th><th></th></tr>
                    </thead>
                    <tbody id="itemBody">
                        <tr class="item-row">
                            <td>
                                <select class="input item-select" name="items[0][item_id]" required>
                                    <option value="">- Pilih item -</option>
                                    @foreach ($items as $i)
                                        <option value="{{ $i->id }}" data-meta="{{ json_encode($itemMeta[$i->id] ?? null) }}">{{ $i->sku }} — {{ $i->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input class="input font-mono" name="items[0][batch_no]" placeholder="B-2026A"></td>
                            <td>
                                <select class="input uom-select" name="items[0][received_uom_id]" required>
                                    @foreach ($uoms as $u)<option value="{{ $u->id }}" data-code="{{ $u->code }}">{{ $u->code }}</option>@endforeach
                                </select>
                            </td>
                            <td><input class="input qty-input" type="number" step="0.001" min="0" name="items[0][received_qty]" value="1" required></td>
                            <td class="pcs-cell font-bold text-indigo-700">0</td>
                            <td><input class="input nav-input text-right" type="number" step="0.001" min="0" name="items[0][nav_qty]" placeholder="Opsional" disabled></td>
                            <td>
                                <select class="input" name="items[0][target_bin_id]">
                                    <option value="">- Ikut BIN staging -</option>
                                    @foreach ($bins as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach
                                </select>
                            </td>
                            <td class="w-10"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();syncNav();">✕</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Aksi</h2>
            <div class="flex flex-col gap-2">
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan Penerimaan</button>
                <a href="{{ route('inbound.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </div>
        <div class="card card-pad bg-slate-50">
            <p class="text-xs font-bold text-slate-600">Alur</p>
            <ol class="hint mt-2 list-inside list-decimal space-y-1">
                <li>Simpan dokumen (draft)</li>
                <li>Terima barang → stok masuk BIN staging</li>
                <li>Putaway ke BIN tujuan</li>
                <li>Cetak label barcode</li>
            </ol>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    let rowIndex = 1;

    function recalc(row) {
        const sel = row.querySelector('.item-select');
        const meta = JSON.parse(sel.selectedOptions[0]?.dataset.meta || 'null');
        const qty = parseFloat(row.querySelector('.qty-input').value || 0);
        const uomId = parseInt(row.querySelector('.uom-select').value);
        let pcs = qty;
        if (meta) {
            if (uomId === meta.base_uom_id) pcs = qty;
            else pcs = qty * (parseFloat(meta.conversions?.[uomId]) || 1);
        }
        const cell = row.querySelector('.pcs-cell');
        cell.textContent = pcs.toLocaleString('id-ID');
        cell.dataset.value = pcs;
    }

    /* Qty NAV diisi hanya di baris pertama tiap SKU (baris duplikat dinonaktifkan). */
    function syncNav() {
        const seen = new Set();
        document.querySelectorAll('#itemBody .item-row').forEach(row => {
            const id = row.querySelector('.item-select').value;
            const nav = row.querySelector('.nav-input');
            if (!nav) return;
            if (!id || seen.has(id)) {
                nav.value = '';
                nav.disabled = true;
            } else {
                nav.disabled = false;
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

            entry.physical += parseFloat(row.querySelector('.pcs-cell')?.dataset.value || 0);
            const navVal = row.querySelector('.nav-input')?.value;
            if (navVal !== '' && navVal !== null && navVal !== undefined) {
                entry.nav += parseFloat(navVal);
                entry.hasNav = true;
            }
            map.set(sel.value, entry);
        });

        const fmt = n => n.toLocaleString('id-ID');

        box.innerHTML = [...map.values()].map(e => {
            if (!e.hasNav) {
                return `<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">${e.label}: Qty NAV belum diisi</span>`;
            }
            const diff = e.physical - e.nav;
            const match = Math.abs(diff) < 0.001;
            return match
                ? `<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">${e.label}: NAV ${fmt(e.nav)} = Fisik ${fmt(e.physical)} PCS</span>`
                : `<span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700">⚠ ${e.label}: NAV ${fmt(e.nav)} vs Fisik ${fmt(e.physical)} PCS (selisih ${diff > 0 ? '+' : ''}${fmt(diff)})</span>`;
        }).join('');
    }

    function refreshRow(row) { recalc(row); syncNav(); }

    document.addEventListener('input', e => {
        const row = e.target.closest('.item-row');
        if (row && (e.target.classList.contains('qty-input') || e.target.classList.contains('uom-select') || e.target.classList.contains('item-select') || e.target.classList.contains('nav-input'))) refreshRow(row);
    });
    document.addEventListener('change', e => {
        const row = e.target.closest('.item-row');
        if (row) refreshRow(row);
    });

    document.getElementById('addRow').addEventListener('click', () => {
        const html = document.querySelector('#itemBody .item-row').outerHTML
            .replace(/items\[\d+\]/g, () => `items[${rowIndex}]`);
        document.getElementById('itemBody').insertAdjacentHTML('beforeend', html);
        rowIndex++;
        syncNav();
    });

    document.querySelectorAll('.item-row').forEach(recalc);
    syncNav();
</script>
@endpush
