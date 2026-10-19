@extends('layouts.app')

@section('title', 'Transfer Antar Bin')
@section('subtitle', 'Pindahkan barang antar rak: scan barcode, lihat BIN asal, pilih BIN tujuan.')

@section('content')
<div class="grid gap-5 lg:grid-cols-2">
    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-extrabold text-slate-900">1. Scan Barcode Barang</h2>
        <p class="hint mb-3">Sistem menampilkan info item beserta <b>BIN / Rak asal</b> saat ini.</p>

        <div class="flex gap-2">
            <input class="input font-mono flex-1" id="btBarcode" placeholder="ABC123-DUS-GRN-202610-0001-001" autocomplete="off" autofocus>
            <button type="button" class="btn btn-primary" id="btLookup"><x-icon name="scan" :size="16" /> Cari</button>
        </div>

        <div id="btResult" class="mt-4"></div>

        <form method="POST" action="{{ route('bin-transfers.store') }}" id="btForm" class="mt-4 hidden">
            @csrf
            <input type="hidden" name="kode_uom_serialno" id="btSerial">

            <div>
                <label class="label required">BIN / Rak Tujuan Baru</label>
                <select class="input" name="location_id" id="btBins" required>
                    <option value="">- Pilih BIN tujuan -</option>
                </select>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <p class="hint">Stok pada tabel <span class="font-mono">stocks</span> ikut dipindah dan gerakan <span class="font-mono">StockMovement</span> bertipe TRANSFER_BIN dicatat.</p>
                <button class="btn btn-success"><x-icon name="transfer" :size="16" /> Simpan Pindah BIN</button>
            </div>
        </form>
    </div>

    <div class="card card-pad bg-slate-50">
        <h2 class="mb-3 text-sm font-extrabold text-slate-900">Alur Transfer Antar Bin</h2>
        <ol class="hint list-inside list-decimal space-y-2">
            <li>Scan barcode barang (<span class="font-mono">kode_uom_serialno</span>).</li>
            <li>Sistem menampilkan SKU, nama barang, UOM, Qty Base, serta rak asal saat ini.</li>
            <li>Pilih / pindai <b>BIN tujuan</b> baru dalam gudang yang sama.</li>
            <li>Simpan — kolom <span class="font-mono">bin_no</span> &amp; <span class="font-mono">location_id</span> pada ItemSerial dan stocks diperbarui.</li>
        </ol>
        <p class="hint mt-4 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">Perpindahan bin tidak mengubah total stok — hanya lokasi penyimpanan.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const btUrl = @json(route('bin-transfers.lookup'));
    const btResult = document.getElementById('btResult');
    const btForm = document.getElementById('btForm');
    const btInput = document.getElementById('btBarcode');

    async function btLookup() {
        const code = btInput.value.trim();
        if (!code) return;

        btResult.innerHTML = '<p class="hint">Memeriksa barcode...</p>';
        btForm.classList.add('hidden');

        try {
            const res = await fetch(btUrl + '?barcode=' + encodeURIComponent(code), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!res.ok) {
                btResult.innerHTML = `<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">${data.message || 'Barcode tidak ditemukan.'}</p>`;
                return;
            }

            const p = data.payload;
            btResult.innerHTML = `
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                    <p class="font-mono text-xs font-bold">${p.kode_uom_serialno}</p>
                    <p class="mt-1 font-bold">${p.item || p.sku}</p>
                    <p class="text-xs">UOM <b>${p.uom}</b> · Qty Base <b>${Number(p.qty_smallest_unit_in).toLocaleString('id-ID')} PCS</b>
                        · BIN asal <b>${p.bin_no || '-'}</b> (${p.location || '-'})</p>
                </div>`;

            const sel = document.getElementById('btBins');
            sel.innerHTML = '<option value="">- Pilih BIN tujuan -</option>' + (data.bins || [])
                .map(b => `<option value="${b.id}" ${b.code === p.bin_no ? 'disabled' : ''}>${b.code}${b.code === p.bin_no ? ' (asal)' : ''}</option>`).join('');

            document.getElementById('btSerial').value = p.kode_uom_serialno;
            btForm.classList.remove('hidden');
        } catch (e) {
            btResult.innerHTML = '<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">Gagal terhubung ke server.</p>';
        }
    }

    document.getElementById('btLookup').addEventListener('click', btLookup);
    btInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); btLookup(); } });
</script>
@endpush
