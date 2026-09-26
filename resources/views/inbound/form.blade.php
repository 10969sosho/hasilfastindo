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
                    <p class="hint">Konversi satuan otomatis ke PCS. Barcode <span class="font-mono font-bold">HF-RCV-YYYYMMDD-XXXX</span> dibuat otomatis per baris.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="addRow"><x-icon name="plus" :size="14" /> Baris Item</button>
            </div>

            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr><th class="min-w-[14rem]">Item</th><th>Batch No</th><th>Satuan</th><th class="w-28">Qty</th><th class="w-32">Total PCS</th><th>BIN Tujuan</th><th></th></tr>
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
                            <td>
                                <select class="input" name="items[0][target_bin_id]">
                                    <option value="">- Ikut BIN staging -</option>
                                    @foreach ($bins as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach
                                </select>
                            </td>
                            <td class="w-10"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>
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
        row.querySelector('.pcs-cell').textContent = pcs.toLocaleString('id-ID');
    }

    document.addEventListener('input', e => {
        const row = e.target.closest('.item-row');
        if (row && (e.target.classList.contains('qty-input') || e.target.classList.contains('uom-select') || e.target.classList.contains('item-select'))) recalc(row);
    });
    document.addEventListener('change', e => {
        const row = e.target.closest('.item-row');
        if (row) recalc(row);
    });

    document.getElementById('addRow').addEventListener('click', () => {
        const html = document.querySelector('#itemBody .item-row').outerHTML
            .replace(/items\[\d+\]/g, () => `items[${rowIndex}]`);
        document.getElementById('itemBody').insertAdjacentHTML('beforeend', html);
        const added = document.getElementById('itemBody').lastElementChild;
        added.querySelectorAll('select, input').forEach(el => { if (!el.name.includes('items[0]')) return; });
        rowIndex++;
    });

    document.querySelectorAll('.item-row').forEach(recalc);
</script>
@endpush
