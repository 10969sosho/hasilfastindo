@extends('layouts.app')

@section('title', 'Putaway Barang')
@section('subtitle', 'Pindahkan barang dari BIN staging ke rak tujuan: scan barcode, isi berat aktual, pilih BIN.')

@section('content')
<div class="grid gap-5 lg:grid-cols-2">
    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-extrabold text-slate-900">1. Scan Barcode Barang</h2>
        <p class="hint mb-3">Pindai QR label <span class="font-mono">SKU-UOM-RECEIVE-SEQ</span> yang ditempel pada kemasan fisik.</p>

        <div class="flex gap-2">
            <input class="input font-mono flex-1" id="paBarcode" placeholder="ABC123-DUS-GRN-202610-0001-001" autocomplete="off" autofocus>
            <button type="button" class="btn btn-primary" id="paLookup"><x-icon name="scan" :size="16" /> Cari</button>
        </div>

        <div id="paResult" class="mt-4"></div>

        <form method="POST" action="{{ route('putaway.store') }}" id="paForm" class="mt-4 hidden">
            @csrf
            <input type="hidden" name="kode_uom_serialno" id="paSerial">

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label required">Berat Aktual Barang (kg)</label>
                    <input class="input text-right" type="number" step="0.001" min="0" name="weight" id="paWeight" placeholder="Contoh: 12.500">
                </div>
                <div>
                    <label class="label required">BIN / Rak Tujuan</label>
                    <select class="input" name="location_id" id="paBins" required>
                        <option value="">- Pilih BIN -</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <p class="hint">Baris stok akan dipindahkan dari BIN asal ke BIN tujuan dan dicatat sebagai gerakan <b>PUTAWAY</b>.</p>
                <button class="btn btn-success"><x-icon name="check" :size="16" /> Simpan Putaway</button>
            </div>
        </form>
    </div>

    <div class="card card-pad bg-slate-50">
        <h2 class="mb-3 text-sm font-extrabold text-slate-900">Alur Penempatan ke Rak</h2>
        <ol class="hint list-inside list-decimal space-y-2">
            <li>Scan barcode kemasan fisik (ItemSerial) — sistem menampilkan SKU, nama barang, UOM, dan Qty Base.</li>
            <li>Masukkan <b>berat aktual</b> barang dalam kg.</li>
            <li>Pilih / pindai <b>BIN / Rak</b> tujuan penyimpanan.</li>
            <li>Simpan — stok pada tabel <span class="font-mono">stocks</span> dipindah dan gerakan <span class="font-mono">StockMovement</span> bertipe PUTAWAY dicatat.</li>
            <li>Ulangi untuk kemasan berikutnya sampai seluruh penerimaan selesai.</li>
        </ol>
        <p class="hint mt-4 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">Barcode barang yang sudah dipindahkan tetap aktif sampai dipakai picking / konversi satuan.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const paUrl = @json(route('putaway.lookup'));
    const paResult = document.getElementById('paResult');
    const paForm = document.getElementById('paForm');
    const paInput = document.getElementById('paBarcode');

    async function paLookup() {
        const code = paInput.value.trim();
        if (!code) return;

        paResult.innerHTML = '<p class="hint">Memeriksa barcode...</p>';
        paForm.classList.add('hidden');

        try {
            const res = await fetch(paUrl + '?barcode=' + encodeURIComponent(code), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            const data = await res.json();

            if (!res.ok) {
                paResult.innerHTML = `<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">${data.message || 'Barcode tidak ditemukan.'}</p>`;
                return;
            }

            const p = data.payload;
            paResult.innerHTML = `
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                    <p class="font-mono text-xs font-bold">${p.kode_uom_serialno}</p>
                    <p class="mt-1 font-bold">${p.item || p.sku}</p>
                    <p class="text-xs">UOM <b>${p.uom}</b> · Qty Base <b>${Number(p.qty_smallest_unit_in).toLocaleString('id-ID')} PCS</b>
                        · BIN saat ini <b>${p.bin_no || '-'}</b> (${p.location || '-'})
                        ${p.weight ? ' · berat ' + p.weight + ' kg' : ''}</p>
                </div>`;

            const sel = document.getElementById('paBins');
            sel.innerHTML = '<option value="">- Pilih BIN -</option>' + (data.bins || [])
                .map(b => `<option value="${b.id}">${b.code}</option>`).join('');

            document.getElementById('paSerial').value = p.kode_uom_serialno;
            document.getElementById('paWeight').value = p.weight ?? '';
            paForm.classList.remove('hidden');
        } catch (e) {
            paResult.innerHTML = '<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">Gagal terhubung ke server.</p>';
        }
    }

    document.getElementById('paLookup').addEventListener('click', paLookup);
    paInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); paLookup(); } });
</script>
@endpush
