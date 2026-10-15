@extends('layouts.app')

@section('title', 'Penerimaan Baru')
@section('subtitle', 'Pencatatan barang masuk dari Purchase Order Dynamics NAV, alokasi kemasan UOM, berat & lokasi simpan.')

@section('content')
<form method="POST" action="{{ route('inbound.store') }}" class="w-full max-w-full space-y-5">
    @csrf

    {{-- Panel atas: Tarik PO NAV + Alur (dipindah dari sidebar kanan agar tabel item lebar penuh) --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Integrasi Purchase Order (Dynamics NAV)</h2>
            @if ($navPos->isEmpty())
                <p class="hint">Belum ada Purchase Order NAV yang menunggu untuk diproses.</p>
            @else
                <p class="hint mb-2">Pilih dokumen PO aktif untuk memuat daftar item dan kuantitas acuan secara otomatis.</p>
                <select class="input mb-2" id="navPoPick">
                    @foreach ($navPos as $p)
                        <option value="{{ $p->id }}" @selected($navPo?->id === $p->id)>{{ $p->po_no }} — {{ $p->supplier_name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-dark w-full" onclick="pullNavPo()"><x-icon name="inbound" :size="16" /> Terapkan Data PO</button>
                <a href="{{ route('nav-po.index') }}" class="hint mt-2 block underline">Buka daftar PO NAV</a>
            @endif
        </div>

        <div class="card card-pad bg-slate-50 md:col-span-2 xl:col-span-1">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Tahapan Operasional Inbound</p>
            <ol class="hint mt-2 list-inside list-decimal space-y-1 sm:columns-2 xl:columns-1">
                <li>Verifikasi fisik &amp; kuantitas terhadap PO NAV.</li>
                <li>Penetapan identitas UOM, berat, dan staging BIN.</li>
                <li>Konfirmasi penerimaan &amp; generate QR serial unit.</li>
                <li>Cetak label identitas &amp; proses putaway ke rak tujuan.</li>
            </ol>
        </div>
    </div>

    <div class="w-full max-w-full space-y-5">
        @if ($navPo)
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-800">
                <x-icon name="inbound" :size="16" />
                <span>Form ditarik dari PO NAV <span class="font-mono font-bold">{{ $navPo->po_no }}</span> · {{ $navPo->supplier_name }} — Qty NAV (PCS) terisi otomatis.</span>
                <a href="{{ route('nav-po.show', $navPo) }}" class="ml-auto text-xs font-bold underline">Lihat detail PO</a>
            </div>
            @if (! $navSupplierId)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    Supplier <b>{{ $navPo->supplier_name }}</b> belum ada di Master Supplier — pilih supplier manual sebelum menyimpan.
                </div>
            @endif
            @if ($navMissingSkus)
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                    SKU NAV belum terdaftar di Master Item: <span class="font-mono">{{ implode(', ', $navMissingSkus) }}</span>
                </div>
            @endif
        @endif

        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Dokumen Penerimaan</h2>
            <div class="grid gap-4 sm:grid-cols-2">
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
                    <input class="input font-mono" name="po_no" value="{{ old('po_no', $navPo?->po_no) }}" placeholder="PO-2026-0001">
                </div>
                <div>
                    <label class="label">Source SO (opsional)</label>
                    <input class="input font-mono" name="source_so" value="{{ old('source_so') }}" placeholder="SO-202609-001">
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
                    <h2 class="text-sm font-extrabold text-slate-900">Item, Konversi UOM, Berat &amp; BIN</h2>
                    <p class="hint">Acuan <b>Qty NAV</b> (unit terkecil) diisi di baris pertama tiap SKU. Serial QR dibuat otomatis 1 per unit UOM.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="addRow"><x-icon name="plus" :size="14" /> Baris Item</button>
            </div>

            <div class="mb-4 grid gap-4 rounded-lg bg-slate-50 p-3 sm:grid-cols-2">
                <div>
                    <span class="label">METODE PERHITUNGAN UOM</span>
                    <div class="flex flex-col gap-2 pt-1 text-sm font-semibold text-slate-700">
                        <label class="flex items-start gap-2">
                            <input type="radio" class="mt-0.5" name="conversion_mode" value="total" @checked(old('conversion_mode', 'total') === 'total')>
                            <span>
                                Berdasarkan Total Qty Terkecil (PCS)
                                <span class="hint block font-normal">Input total unit fisik diterima, sistem mengalokasikan rata per kemasan</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input type="radio" class="mt-0.5" name="conversion_mode" value="per_uom" @checked(old('conversion_mode') === 'per_uom')>
                            <span>
                                Berdasarkan Kapasitas per Kemasan (PCS/UOM)
                                <span class="hint block font-normal">Input isi standar per unit kemasan untuk kalkulasi total fisik</span>
                            </span>
                        </label>
                    </div>
                </div>
                <div>
                    <span class="label">STANDARISASI BERAT &amp; LOKASI PENYIMPANAN</span>
                    <div class="flex flex-col gap-1.5 pt-1 text-sm font-semibold text-slate-700">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="weight_bin_mode" value="same" @checked(old('weight_bin_mode', 'same') === 'same')>
                            Seragam untuk Seluruh Kemasan
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="weight_bin_mode" value="per_unit" @checked(old('weight_bin_mode') === 'per_unit')>
                            Spesifik per Unit Kemasan (Koli/Dus)
                        </label>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="hint">Berat per unit (kg)</label>
                            <input class="input" type="number" step="0.001" min="0" name="weight" value="{{ old('weight') }}" placeholder="Contoh: 12.50">
                        </div>
                        <div>
                            <label class="hint">Kode BIN / Rak</label>
                            <input class="input" name="bin_no" value="{{ old('bin_no') }}" placeholder="Contoh: A-03-02">
                        </div>
                    </div>
                    <p class="hint mt-1">Data berat dan kode rak akan dicetak pada label QR identitas barang.</p>
                    <p class="hint" id="weightHint">Berat &amp; BIN diterapkan seragam ke seluruh kemasan.</p>
                </div>
            </div>

            <div id="navSummary" class="mb-4 flex flex-wrap gap-2"></div>

            <div class="table-scroll w-full">
                <table class="table w-full min-w-[72rem]">
                    <thead>
                        <tr><th class="min-w-[16rem]">Item</th><th class="min-w-[8rem]">Batch No</th><th class="min-w-[7rem]">Satuan</th><th class="w-28">Qty Kemasan</th><th class="w-32">Total Fisik (PCS)</th><th class="w-32">Kapasitas (PCS/UOM)</th><th class="w-32">Qty PO NAV (PCS)</th><th class="min-w-[9rem]">Berat / Unit (kg)</th><th class="min-w-[9rem]">Kode BIN / Rak</th><th class="w-14"></th></tr>
                    </thead>
                    <tbody id="itemBody">
                        @php
                            $oldRows = old('items');
                            $rowCount = max(count($navRows), is_array($oldRows) ? count($oldRows) : 0, 1);
                        @endphp
                        @for ($idx = 0; $idx < $rowCount; $idx++)
                            @php
                                $row = $navRows[$idx] ?? ['item_id' => null, 'uom_id' => null, 'received_qty' => 1, 'total_pcs' => null, 'nav_qty' => null];
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
                            <td><input class="input font-mono" name="items[{{ $idx }}][batch_no]" value="{{ old("items.$idx.batch_no") }}" placeholder="B-2026A"></td>
                            <td>
                                <select class="input uom-select" name="items[{{ $idx }}][received_uom_id]" required>
                                    @foreach ($uoms as $u)<option value="{{ $u->id }}" @selected(old("items.$idx.received_uom_id", $row['uom_id']) == $u->id) data-code="{{ $u->code }}">{{ $u->code }}</option>@endforeach
                                </select>
                            </td>
                            <td><input class="input qty-input" type="number" step="0.001" min="0" name="items[{{ $idx }}][received_qty]" value="{{ old("items.$idx.received_qty", $row['received_qty']) }}" required></td>
                            <td><input class="input total-input text-right" type="number" step="0.001" min="0" name="items[{{ $idx }}][total_pcs]" value="{{ old("items.$idx.total_pcs", $row['total_pcs']) }}" placeholder="90000"></td>
                            <td><input class="input per-input text-right" type="number" step="0.001" min="0" name="items[{{ $idx }}][pcs_per_uom]" value="{{ old("items.$idx.pcs_per_uom") }}" placeholder="10000"></td>
                            <td><input class="input nav-input text-right" type="number" step="0.001" min="0" name="items[{{ $idx }}][nav_qty]" value="{{ old("items.$idx.nav_qty", $row['nav_qty']) }}" placeholder="Opsional" disabled></td>
                            <td><input class="input weight-row text-right" type="number" step="0.001" min="0" name="items[{{ $idx }}][weight]" value="{{ old("items.$idx.weight") }}" placeholder="Mengikuti Standar"></td>
                            <td>
                                <select class="input" name="items[{{ $idx }}][target_bin_id]">
                                    <option value="">Sesuai Form Atas</option>
                                    @foreach ($bins as $b)<option value="{{ $b->id }}" @selected(old("items.$idx.target_bin_id") == $b->id)>{{ $b->code }}</option>@endforeach
                                </select>
                            </td>
                            <td class="w-10"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();syncNav();renderUnits();">✕</button></td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div id="unitBox" class="mt-4 hidden"></div>
        </div>
    </div>

    {{-- Aksi: bar bawah lebar penuh agar tombol simpan mudah dijangkau --}}
    <div class="card card-pad flex flex-wrap items-center justify-between gap-3">
        <p class="hint">Periksa kembali <b>Qty NAV</b>, berat &amp; BIN sebelum menyimpan. Serial QR dibuat otomatis saat barang diterima.</p>
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
    const oldUnits = @json(collect(old('items.0.units', [])));

    function pullNavPo() {
        const id = document.getElementById('navPoPick')?.value;
        if (id) location.href = "{{ route('inbound.create') }}?nav_po_id=" + id;
    }

    const mode = () => document.querySelector('input[name=conversion_mode]:checked')?.value || 'total';
    const perUnit = () => document.querySelector('input[name=weight_bin_mode]:checked')?.value === 'per_unit';

    function recalc(row) {
        const sel = row.querySelector('.item-select');
        const meta = JSON.parse(sel.selectedOptions[0]?.dataset.meta || 'null');
        const qty = parseFloat(row.querySelector('.qty-input').value || 0);
        const uomId = parseInt(row.querySelector('.uom-select').value);
        const totalInput = row.querySelector('.total-input');
        const perInput = row.querySelector('.per-input');

        if (mode() === 'per_uom') {
            const per = parseFloat(perInput.value || 0);
            perInput.readOnly = false;
            totalInput.readOnly = true;
            totalInput.value = per > 0 ? qty * per : '';
            if (per <= 0 && meta && uomId !== meta.base_uom_id) {
                const conv = parseFloat(meta.conversions?.[uomId]) || 1;
                totalInput.value = qty * conv;
            }
        } else {
            totalInput.readOnly = false;
            perInput.readOnly = true;
            if (!totalInput.value && meta) {
                const conv = (uomId === meta.base_uom_id) ? 1 : (parseFloat(meta.conversions?.[uomId]) || 1);
                totalInput.value = qty * conv;
            }
            perInput.value = qty > 0 && totalInput.value ? parseFloat(totalInput.value) / qty : '';
        }
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
            if (!e.hasNav) {
                return `<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">${e.label}: Qty PO NAV belum diisi</span>`;
            }
            const diff = e.physical - e.nav;
            const match = Math.abs(diff) < 0.001;
            return match
                ? `<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">✓ ${e.label}: Kuantitas Fisik Sesuai PO NAV (${fmt(e.nav)} PCS)</span>`
                : `<span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700">⚠ ${e.label}: Selisih Fisik (${diff > 0 ? '+' : ''}${fmt(diff)} PCS vs PO NAV ${fmt(e.nav)} PCS)</span>`;
        }).join('');
    }

    /* Input berat & BIN per unit dus/koli (mode "beda per unit"). */
    function renderUnits() {
        const box = document.getElementById('unitBox');
        const hint = document.getElementById('weightHint');
        if (!box) return;

        if (hint) hint.textContent = perUnit()
            ? 'Berat & BIN di atas menjadi nilai default bila kolom per unit dibiarkan kosong.'
            : 'Berat & BIN diterapkan seragam ke seluruh kemasan.';

        if (!perUnit()) {
            box.classList.add('hidden');
            box.innerHTML = '';
            return;
        }

        let html = '<p class="label">Rincian Berat &amp; BIN per Unit Kemasan (Koli/Dus)</p>'
            + '<p class="hint mb-2">Setiap unit di bawah menjadi 1 nomor serial + 1 label QR saat barang diterima. Kosongkan kolom unit untuk memakai standar di atas.</p>';

        document.querySelectorAll('#itemBody .item-row').forEach(row => {
            const name = row.querySelector('[name^="items["]')?.name || '';
            const idx = (name.match(/items\[(\d+)\]/) || [])[1];
            const qty = Math.floor(parseFloat(row.querySelector('.qty-input').value || 0));
            const label = (row.querySelector('.item-select').selectedOptions[0]?.textContent || '').trim();

            if (idx === undefined || isNaN(qty) || qty < 1) return;

            if (qty > 30) {
                html += `<p class="hint rounded-lg bg-amber-50 px-3 py-2">${label || 'Baris'}: ${qty} unit — input per unit dinonaktifkan (batas 30 unit), gunakan standar berat &amp; BIN di atas.</p>`;
                return;
            }

            html += `<p class="label mt-3">${label || 'Baris'} · ${qty} unit (serial QR)</p><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">`;

            for (let u = 0; u < qty; u++) {
                const o = (idx === '0' && oldUnits[u]) ? oldUnits[u] : {};
                html += `<div class="flex items-center gap-2 rounded-lg border border-slate-200 px-2 py-1.5">
                    <span class="text-xs font-bold text-slate-500">U${u + 1}</span>
                    <input class="input py-1 text-xs" type="number" step="0.001" min="0" placeholder="kg" name="items[${idx}][units][${u}][weight]" value="${o.weight ?? ''}">
                    <input class="input py-1 text-xs" placeholder="BIN / Rak" name="items[${idx}][units][${u}][bin_no]" value="${o.bin_no ?? ''}">
                </div>`;
            }

            html += '</div>';
        });

        box.innerHTML = html;
        box.classList.remove('hidden');
    }

    function refreshRow(row) { recalc(row); syncNav(); renderUnits(); }

    document.addEventListener('input', e => {
        const row = e.target.closest('.item-row');
        if (row && (e.target.classList.contains('qty-input') || e.target.classList.contains('uom-select') || e.target.classList.contains('item-select') || e.target.classList.contains('nav-input') || e.target.classList.contains('total-input') || e.target.classList.contains('per-input'))) refreshRow(row);
    });
    document.addEventListener('change', e => {
        const row = e.target.closest('.item-row');
        if (row) refreshRow(row);
    });

    document.querySelectorAll('input[name=conversion_mode], input[name=weight_bin_mode]').forEach(el => {
        el.addEventListener('change', () => {
            document.querySelectorAll('#itemBody .item-row').forEach(recalc);
            syncNav();
            renderUnits();
        });
    });

    document.getElementById('addRow').addEventListener('click', () => {
        const html = document.querySelector('#itemBody .item-row').outerHTML
            .replace(/items\[\d+\]/g, () => `items[${rowIndex}]`);
        document.getElementById('itemBody').insertAdjacentHTML('beforeend', html);

        // Baris baru selalu kosong — jangan menyalin nilai baris pertama (mis. hasil tarikan PO NAV).
        const row = document.getElementById('itemBody').lastElementChild;
        row.querySelectorAll('input').forEach(i => { i.value = ''; });
        row.querySelectorAll('select').forEach(s => { s.selectedIndex = 0; });
        row.querySelector('.qty-input').value = 1;

        rowIndex++;
        refreshRow(row);
    });

    document.querySelectorAll('.item-row').forEach(recalc);
    syncNav();
    renderUnits();
</script>
@endpush
