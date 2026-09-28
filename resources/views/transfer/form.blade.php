@extends('layouts.app')

@section('title', 'Transfer Baru')

@section('content')
<form method="POST" action="{{ route('transfer.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf

    <div class="space-y-5 lg:col-span-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Rute Transfer</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label required">Cabang Asal</label>
                    <select class="input" name="from_branch_id" id="from_branch_id" onchange="loadWarehouses('from')" required>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($fromBranchId == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Gudang Asal</label>
                    <select class="input" name="from_warehouse_id" id="from_warehouse_id" onchange="loadBins('from')" required>
                        @foreach ($fromWarehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Cabang Tujuan</label>
                    <select class="input" name="to_branch_id" id="to_branch_id" required>
                        <option value="">- Pilih cabang -</option>
                        @foreach ($toBranches as $b)
                            @unless ($b->id == $fromBranchId)
                                <option value="{{ $b->id }}" @selected(old('to_branch_id') == $b->id)>{{ $b->name }}</option>
                            @endunless
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Gudang Tujuan</label>
                    <select class="input" name="to_warehouse_id" id="to_warehouse_id" required>
                        <option value="">- Pilih dulu cabang tujuan -</option>
                    </select>
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
                    <h2 class="text-sm font-extrabold text-slate-900">Item yang Dikirim</h2>
                    <p class="hint">Stok dipotong FIFO dari BIN asal saat tombol Kirim ditekan.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="addRow"><x-icon name="plus" :size="14" /> Baris</button>
            </div>

            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th class="min-w-[15rem]">Item</th><th>BIN Asal</th><th>Satuan</th><th class="w-32">Qty</th><th>BIN Tujuan</th><th></th></tr></thead>
                    <tbody id="itemBody">
                        <tr class="item-row">
                            <td>
                                <select class="input" name="items[0][item_id]" required>
                                    <option value="">- Pilih item -</option>
                                    @foreach ($items as $i)
                                        <option value="{{ $i->id }}" data-base="{{ $i->base_uom_id }}">{{ $i->sku }} — {{ $i->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="input bin-from" name="items[0][from_location_id]" required>
                                    @foreach ($bins as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach
                                </select>
                            </td>
                            <td>
                                <select class="input" name="items[0][uom_id]" required>
                                    @foreach ($uoms as $u)<option value="{{ $u->id }}">{{ $u->code }}</option>@endforeach
                                </select>
                            </td>
                            <td><input class="input" type="number" step="0.001" min="0.001" name="items[0][qty]" value="1" required></td>
                            <td><select class="input bin-to" name="items[0][to_location_id]"><option value="">- Opsional -</option></select></td>
                            <td class="w-10"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Simpan</h2>
            <div class="flex flex-col gap-2">
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Buat Transfer</button>
                <a href="{{ route('transfer.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </div>
        <div class="card card-pad bg-slate-50">
            <p class="text-xs font-bold text-slate-600">Alur</p>
            <ol class="hint mt-2 list-inside list-decimal space-y-1">
                <li>Draft → Kirim (stok terpotong)</li>
                <li>In transit</li>
                <li>Diterima di cabang tujuan (catat selisih bila ada)</li>
            </ol>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const warehousesUrl = @json(route('transfer.warehouses'));
    const binsUrl = @json(route('transfer.bins'));
    const stockBinsUrl = @json(route('transfer.stockBins'));
    let rowIndex = 1;

    /** Semua BIN milik gudang asal yang sedang dipilih, urut kode. */
    let allBins = @json($bins->map(fn ($b) => ['id' => $b->id, 'code' => $b->code])->values());

    /** Semua cabang sebagai kandidat tujuan (cabang asal dikeluarkan, lihat renderToBranches). */
    let allToBranches = @json($toBranches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values());

    /** Ketersediaan stok, kunci `${item_id}|${location_id}` -> qty PCS. */
    let stockIndex = {};

    /** Supaya pemilihan item tidak membaca stok sebelum fetch-nya selesai. */
    let stockIndexReady = Promise.resolve();

    const fmtQty = (qty) => Number(qty).toLocaleString('id-ID');

    async function loadStockIndex() {
        const branchId = document.getElementById('from_branch_id').value;
        const warehouseId = document.getElementById('from_warehouse_id').value;

        stockIndex = {};
        if (!branchId || !warehouseId) return;

        const res = await fetch(`${stockBinsUrl}?branch_id=${branchId}&warehouse_id=${warehouseId}`);
        if (!res.ok) return;

        (await res.json()).forEach((row) => {
            stockIndex[`${row.item_id}|${row.location_id}`] = row.qty;
        });
    }

    function refreshStockIndex() {
        stockIndexReady = loadStockIndex();
        return stockIndexReady;
    }

    /**
     * Isi BIN asal satu baris: hanya BIN yang berisi stok untuk item baris itu,
     * lengkap dengan sisa stoknya. Kalau belum ada item dipilih — atau indeks
     * stok kosong (gudang kosong / fetch gagal) — semua BIN tetap ditawarkan
     * supaya form tidak pernah menutup pilihan secara keliru.
     */
    function renderFromBins(row) {
        const select = row.querySelector('.bin-from');
        const itemId = row.querySelector('[name$="[item_id]"]').value;
        const previous = select.value;
        const hasIndex = Object.keys(stockIndex).length > 0;

        const stocked = (!itemId || !hasIndex)
            ? allBins
            : allBins.filter((b) => stockIndex[`${itemId}|${b.id}`] !== undefined);

        if (itemId && hasIndex && !stocked.length) {
            select.innerHTML = '<option value="">Tidak ada stok di gudang ini</option>';
            return;
        }

        select.innerHTML = stocked.map((b) => {
            const qty = hasIndex && itemId ? stockIndex[`${itemId}|${b.id}`] : undefined;
            const label = qty === undefined ? b.code : `${b.code} — stok ${fmtQty(qty)}`;
            return `<option value="${b.id}">${label}</option>`;
        }).join('');

        if (stocked.some((b) => String(b.id) === previous)) select.value = previous;
    }

    function renderAllFromBins() {
        document.querySelectorAll('#itemBody .item-row').forEach(renderFromBins);
    }

    /**
     * Isi dropdown cabang tujuan dengan semua cabang, kecuali cabang asal yang
     * sedang dipilih — validasi store() menolak from == to, jadi menampilkannya
     * hanya mengundang user memilih yang mustahil diterima.
     *
     * @return {boolean} true kalau pilihan tujuan ikut hilang, artinya gudang
     *                   tujuan harus dimuat ulang.
     */
    function renderToBranches() {
        const select = document.getElementById('to_branch_id');
        const fromId = String(document.getElementById('from_branch_id').value);
        const previous = select.value;

        const branches = allToBranches.filter((b) => String(b.id) !== fromId);

        select.innerHTML = '<option value="">- Pilih cabang -</option>'
            + branches.map((b) => `<option value="${b.id}">${b.name}</option>`).join('');

        const kept = branches.some((b) => String(b.id) === previous);
        if (kept) select.value = previous;

        return previous !== '' && !kept;
    }

    async function loadWarehouses(side) {
        const branchId = document.getElementById(side + '_branch_id').value;
        const select = document.getElementById(side + '_warehouse_id');

        if (!branchId) {
            select.innerHTML = '<option value="">- Pilih dulu cabang tujuan -</option>';
            return;
        }

        select.innerHTML = '<option value="">Memuat...</option>';
        const res = await fetch(warehousesUrl + '?branch_id=' + branchId);
        const rows = await res.json();
        select.innerHTML = rows.map(w => `<option value="${w.id}">${w.code} — ${w.name}</option>`).join('') || '<option value="">Tidak ada gudang</option>';

        if (side === 'from') {
            // Cabang asal berubah -> daftar cabang tujuan ikut menyesuaikan.
            if (renderToBranches()) loadWarehouses('to');
            loadBins('from');
        } else select.dispatchEvent(new Event('change'));
    }

    async function loadBins(side) {
        const warehouseId = document.getElementById(side + '_warehouse_id').value;
        if (!warehouseId) return;
        const res = await fetch(binsUrl + '?warehouse_id=' + warehouseId);
        const rows = await res.json();

        if (side === 'from') {
            allBins = rows;
            await refreshStockIndex();
            renderAllFromBins();
            return;
        }

        const options = rows.map(b => `<option value="${b.id}">${b.code}</option>`).join('');
        document.querySelectorAll('.bin-to').forEach(s => s.innerHTML = '<option value="">- Opsional -</option>' + options);
    }

    document.getElementById('to_branch_id').addEventListener('change', () => loadWarehouses('to'));

    // Ganti item di satu baris -> BIN asal baris itu ikut menyesuaikan.
    document.getElementById('itemBody').addEventListener('change', (e) => {
        if (e.target.matches('[name$="[item_id]"]')) {
            stockIndexReady.then(() => renderFromBins(e.target.closest('.item-row')));
        }
    });

    document.getElementById('addRow').addEventListener('click', () => {
        const html = document.querySelector('#itemBody .item-row').outerHTML.replace(/items\[\d+\]/g, () => `items[${rowIndex}]`);
        document.getElementById('itemBody').insertAdjacentHTML('beforeend', html);
        rowIndex++;

        const rows = document.querySelectorAll('#itemBody .item-row');
        stockIndexReady.then(() => renderFromBins(rows[rows.length - 1]));

        loadBins('to');
    });

    renderToBranches();
    refreshStockIndex();
</script>
@endpush
