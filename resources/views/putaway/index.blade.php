@extends('layouts.app')

@section('title', 'Putaway / Taruh Rak')

@section('content')
<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <div class="border-b border-slate-100 pb-2.5 mb-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Scan Barcode Barang</h2>
            <p class="text-[11px] text-slate-500 mt-0.5">Pindai QR label kemasan fisik atau masukkan kode barcode.</p>
        </div>

        <div class="space-y-2.5">
            <div class="flex gap-2">
                <input class="input font-mono flex-1 text-xs" id="paBarcode" placeholder="Scan atau ketik kode barcode kemasan..." autocomplete="off" autofocus>
                <button type="button" class="btn btn-primary text-xs" id="paLookup"><x-icon name="scan" :size="15" /> Cari</button>
            </div>

            <div class="flex gap-2">
                <button type="button" class="btn btn-ghost text-xs w-full flex items-center justify-center gap-1.5" id="paCamBtn">
                    <x-icon name="smartphone" :size="15" /> <span id="paCamBtnText">Scan dengan Kamera</span>
                </button>
            </div>

            {{-- Scanner Viewport --}}
            <div id="paQrReader" class="hidden overflow-hidden rounded-xl border border-indigo-200 bg-black/90 p-2"></div>
        </div>

        <div id="paResult" class="mt-3"></div>

        <form method="POST" action="{{ route('putaway.store') }}" id="paForm" class="mt-3.5 hidden">
            @csrf
            <input type="hidden" name="kode_uom_serialno" id="paSerial">

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label required">Berat Aktual (kg)</label>
                    <input class="input text-right font-mono" type="number" step="0.001" min="0" name="weight" id="paWeight" placeholder="0.000">
                </div>
                <div>
                    <label class="label required">BIN / Rak Tujuan</label>
                    <select class="input searchable" name="location_id" id="paBins" required placeholder="- Pilih BIN / Rak -">
                        <option value="">- Pilih BIN / Rak -</option>
                    </select>
                </div>
            </div>

            <div class="mt-3.5 flex items-center justify-end">
                <button class="btn btn-success text-xs"><x-icon name="check" :size="15" /> Simpan Penempatan Rak</button>
            </div>
        </form>
    </div>

    <div class="card p-4 bg-slate-50 border-slate-200/80">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-200 pb-2 mb-3">Panduan Penataan Rak</h2>
        <ol class="list-inside list-decimal space-y-2 text-xs text-slate-600">
            <li>Scan QR kemasan fisik via scanner atau <b>Kamera</b>.</li>
            <li>Sistem menampilkan info SKU, nama barang, dan Qty Base.</li>
            <li>Timbang fisik &amp; isi <b>Berat Aktual</b> (kg).</li>
            <li>Pilih atau scan <b>BIN / Rak Tujuan</b> penyimpanan.</li>
            <li>Klik <b>Simpan Penempatan Rak</b> untuk mencatat perpindahan stok.</li>
        </ol>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
    const paUrl = @json(route('putaway.lookup'));
    const paResult = document.getElementById('paResult');
    const paForm = document.getElementById('paForm');
    const paInput = document.getElementById('paBarcode');
    const paCamBtn = document.getElementById('paCamBtn');
    const paCamBtnText = document.getElementById('paCamBtnText');
    const paQrReader = document.getElementById('paQrReader');
    let paHtml5Scanner = null;

    async function stopCamera() {
        if (!paHtml5Scanner) return;
        try { await paHtml5Scanner.stop(); } catch (e) { }
        paHtml5Scanner.clear();
        paHtml5Scanner = null;
        paQrReader.classList.add('hidden');
        paCamBtnText.textContent = 'Scan dengan Kamera';
        paCamBtn.classList.remove('btn-danger');
        paCamBtn.classList.add('btn-ghost');
    }

    if (paCamBtn && paQrReader) {
        paCamBtn.addEventListener('click', async () => {
            if (paHtml5Scanner) {
                await stopCamera();
                return;
            }

            if (!window.Html5Qrcode) {
                alert('Library kamera belum termuat. Periksa koneksi internet Anda.');
                return;
            }

            paQrReader.classList.remove('hidden');
            paQrReader.innerHTML = '';
            paHtml5Scanner = new Html5Qrcode('paQrReader', {
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                ],
                rememberLastUsedCamera: false,
            });

            try {
                await paHtml5Scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    async (decodedText) => {
                        await stopCamera();
                        paInput.value = decodedText;
                        paLookup();
                    },
                    () => {}
                );
                paCamBtnText.textContent = 'Tutup Kamera';
                paCamBtn.classList.remove('btn-ghost');
                paCamBtn.classList.add('btn-danger');
            } catch (err) {
                paQrReader.innerHTML = '<p class="p-3 text-xs font-bold text-rose-600 bg-rose-50 rounded-lg">Kamera tidak dapat diakses. Pastikan izin kamera aktif pada browser.</p>';
                paHtml5Scanner = null;
            }
        });
    }

    async function paLookup() {
        const code = paInput.value.trim();
        if (!code) return;

        paResult.innerHTML = '<p class="text-xs text-slate-500">Memeriksa barcode...</p>';
        paForm.classList.add('hidden');

        try {
            const res = await fetch(paUrl + '?barcode=' + encodeURIComponent(code), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            const data = await res.json();

            if (!res.ok) {
                paResult.innerHTML = `<p class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600">${data.message || 'Barcode tidak ditemukan.'}</p>`;
                return;
            }

            const p = data.payload;
            paResult.innerHTML = `
                <div class="rounded-lg border border-indigo-200 bg-indigo-50/70 p-3 text-xs text-indigo-950">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs font-bold text-indigo-700">${p.kode_uom_serialno}</span>
                        <span class="rounded bg-indigo-100 px-2 py-0.5 font-bold text-[11px] text-indigo-800">${p.uom}</span>
                    </div>
                    <p class="mt-1 font-bold text-slate-900">${p.item || p.sku} <span class="font-normal font-mono text-slate-500">(${p.sku})</span></p>
                    <p class="mt-1 text-slate-600">
                        Qty Base: <b class="text-slate-900">${Number(p.qty_smallest_unit_in).toLocaleString('id-ID')} PCS</b> · 
                        BIN Sekarang: <b class="font-mono text-slate-900">${p.bin_no || '-'}</b>
                        ${p.weight ? ' · Berat: <b class="text-slate-900">' + p.weight + ' kg</b>' : ''}
                    </p>
                </div>`;

            const sel = document.getElementById('paBins');
            sel.innerHTML = '<option value="">- Pilih BIN / Rak -</option>' + (data.bins || [])
                .map(b => `<option value="${b.id}">${b.code}</option>`).join('');

            // Inisialisasi TomSelect bila tersedia
            if (sel.tomselect) {
                sel.tomselect.destroy();
            }
            if (window.initSearchableSelects) {
                window.initSearchableSelects(paForm);
            }

            document.getElementById('paSerial').value = p.kode_uom_serialno;
            document.getElementById('paWeight').value = p.weight ?? '';
            paForm.classList.remove('hidden');
        } catch (e) {
            paResult.innerHTML = '<p class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600">Gagal terhubung ke server.</p>';
        }
    }

    document.getElementById('paLookup').addEventListener('click', paLookup);
    paInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); paLookup(); } });
</script>
@endpush

