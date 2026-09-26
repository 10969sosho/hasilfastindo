@extends('layouts.app')

@section('title', 'Repack Baru')

@section('content')
<form method="POST" action="{{ route('repack.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf
    <input type="hidden" name="branch_id" value="{{ $branchId }}">

    <div class="space-y-5 lg:col-span-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Sumber (Barang Asal)</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label required">Gudang</label>
                    <select class="input" name="warehouse_id" id="warehouse_id" required>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label required">Stok Asal (FIFO per BIN)</label>
                    <select class="input" name="stock_id" id="stock_id" required onchange="fillSource()">
                        <option value="">- Pilih stok -</option>
                        @foreach ($stocks as $s)
                            <option value="{{ $s['id'] }}"
                                    data-item="{{ $s['item_id'] }}"
                                    data-location="{{ $s['location_id'] }}"
                                    data-location-code="{{ $s['location'] }}"
                                    data-pcs="{{ $s['pcs'] }}"
                                    data-batch="{{ $s['batch'] ?? '' }}">
                                {{ $s['sku'] }} — {{ $s['item'] }} · {{ $s['location'] }} · {{ number_format($s['pcs']) }} PCS
                                @if ($s['batch']) · batch {{ $s['batch'] }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Qty Asal</label>
                    <input class="input" type="number" step="0.001" min="0.001" name="source_qty" id="source_qty" value="1" required>
                    <p class="hint" id="availableHint">Pilih stok untuk melihat ketersediaan.</p>
                </div>
                <div>
                    <label class="label required">Satuan Asal</label>
                    <select class="input" name="source_uom_id" id="source_uom_id" required>
                        @foreach ($uoms as $u)<option value="{{ $u->id }}">{{ $u->code }}</option>@endforeach
                    </select>
                </div>
                <input type="hidden" name="source_item_id" id="source_item_id">
                <input type="hidden" name="source_location_id" id="source_location_id">
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Hasil (Barang Baru)</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label required">Item Hasil (kosong = item yang sama)</label>
                    <select class="input" name="target_item_id">
                        <option value="">- Ikut item sumber -</option>
                        @foreach ($items as $i)
                            <option value="{{ $i->id }}" data-base="{{ $i->base_uom_id }}">{{ $i->sku }} — {{ $i->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Qty Hasil</label>
                    <input class="input" type="number" step="0.001" min="0.001" name="target_qty" value="1" required>
                </div>
                <div>
                    <label class="label required">Satuan Hasil</label>
                    <select class="input" name="target_uom_id" required>
                        @foreach ($uoms as $u)<option value="{{ $u->id }}">{{ $u->code }}</option>@endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">BIN Tujuan</label>
                    <select class="input" name="target_location_id" id="target_location_id"></select>
                    <p class="hint">Kosong = tetap di BIN asal.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Catatan</label>
                    <textarea class="input" name="notes" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Simpan</h2>
            <div class="flex flex-col gap-2">
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Proses Repack</button>
                <a href="{{ route('repack.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </div>
        <div class="card card-pad bg-slate-50">
            <p class="text-xs font-bold text-slate-600">Barcode Baru</p>
            <p class="hint mt-1">Setiap hasil repack menerbitkan barcode <span class="font-mono font-bold">HF-RPK-YYYYMMDD-NNNN</span> yang tertaut ke batch asal.</p>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const binsUrl = @json(route('repack.bins'));
    const branchId = {{ (int) $branchId }};

    async function loadBins(warehouseId) {
        const select = document.getElementById('target_location_id');
        if (!warehouseId) { select.innerHTML = ''; return; }
        const res = await fetch(binsUrl + '?warehouse_id=' + warehouseId);
        const rows = await res.json();
        select.innerHTML = '<option value="">- Ikut BIN asal -</option>' + rows.map(b => `<option value="${b.id}">${b.code}</option>`).join('');
    }

    function fillSource() {
        const opt = document.getElementById('stock_id').selectedOptions[0];
        if (!opt || !opt.value) return;
        document.getElementById('source_item_id').value = opt.dataset.item;
        document.getElementById('source_location_id').value = opt.dataset.location;
        document.getElementById('source_qty').max = opt.dataset.pcs;
        document.getElementById('availableHint').textContent =
            'Tersedia: ' + Number(opt.dataset.pcs).toLocaleString('id-ID') + ' PCS di ' + opt.dataset.locationCode +
            (opt.dataset.batch ? ' (batch ' + opt.dataset.batch + ')' : '');
    }

    loadBins(document.getElementById('warehouse_id').value);
    document.getElementById('warehouse_id').addEventListener('change', e => loadBins(e.target.value));
</script>
@endpush
