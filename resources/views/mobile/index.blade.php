@extends('layouts.app')

@section('title', 'Mobile Warehouse')
@section('subtitle', 'Mode lapangan: scan in (putaway), scan out (muat), hitung fisik (opname)')

@section('content')
<div class="grid gap-4 sm:grid-cols-3 mb-5">
    @foreach (['scanin' => 'Scan In / Putaway', 'scanout' => 'Scan Out / Muat', 'opname' => 'Hitung Opname'] as $key => $label)
        <a href="{{ route('mobile.index', ['mode' => $key]) }}"
           class="card card-pad text-center {{ $mode === $key ? '!border-indigo-600 ring-2 ring-indigo-600/30' : '' }}">
            <p class="text-sm font-extrabold {{ $mode === $key ? 'text-indigo-700' : 'text-slate-700' }}">{{ $label }}</p>
            <p class="hint mt-1">{{ $mode === $key ? 'Mode aktif' : 'Klik untuk pindah' }}</p>
        </a>
    @endforeach
</div>

<div class="grid gap-5 lg:grid-cols-2">
    <div class="card card-pad">
        @if ($mode === 'scanin')
            <h2 class="text-sm font-extrabold text-slate-900">Scan In — Putaway</h2>
            <p class="hint mb-3">Scan barcode HF-RCV milik penerimaan yang sudah diterima, lalu pilih BIN tujuan.</p>

            <div class="mb-3">
                <label class="label">Pilih Penerimaan</label>
                <select class="input" onchange="location.href='{{ route('mobile.index') }}?mode=scanin&receipt_id='+this.value">
                    @foreach ($receipts as $r)
                        <option value="{{ $r->id }}" @selected($selectedReceiptId == $r->id)>
                            {{ $r->doc_number }} — {{ $r->supplier?->name }} ({{ $r->pending_lines }}/{{ $r->total_lines }} baris belum)
                        </option>
                    @endforeach
                </select>
            </div>

            <form method="POST" action="{{ route('mobile.lookup') }}" id="lookupForm">
                @csrf
                <input type="hidden" name="type" value="scanin">
                <label class="label">Barcode</label>
                <div class="flex gap-2">
                    <input class="input font-mono flex-1" name="barcode" id="mBarcode" placeholder="HF-RCV-..." autocomplete="off">
                    <button class="btn btn-primary">Cek</button>
                </div>
            </form>
            <div id="scaninResult" class="mt-4"></div>

        @elseif ($mode === 'scanout')
            <h2 class="text-sm font-extrabold text-slate-900">Scan Out — Muat ke Armada</h2>
            <p class="hint mb-3">Scan barcode dus (HF-BOX-...) untuk mencatat keluar muatan.</p>

            <div class="mb-3">
                <label class="label">Surat Jalan</label>
                <select class="input" onchange="location.href='{{ route('mobile.index') }}?mode=scanout&delivery_id='+this.value">
                    @foreach ($deliveries as $d)
                        <option value="{{ $d->id }}" @selected($selectedDelivery?->id == $d->id)>
                            {{ $d->delivery_no }} — {{ $d->vehicle?->plate_number ?? ($d->salesOrder?->customer?->name ?? '-') }}
                            ({{ $d->scanned_boxes }}/{{ $d->total_boxes }} dus)
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($selectedDelivery)
                <div class="mb-3 rounded-lg bg-slate-900 px-4 py-3 text-center text-white">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Progres Muat</p>
                    <p class="text-2xl font-extrabold">{{ $scanned }} / {{ $total }} <span class="text-sm text-slate-400">baris</span></p>
                </div>

                <form method="POST" action="{{ route('delivery.scan', $selectedDelivery) }}" class="flex gap-2">
                    @csrf
                    <input class="input font-mono flex-1" name="barcode" id="mBarcode" placeholder="Scan barcode dus" autocomplete="off" autofocus>
                    <button class="btn btn-primary">Muat</button>
                </form>
            @else
                <p class="text-sm text-slate-400">Tidak ada surat jalan menunggu scan.</p>
            @endif

        @else
            <h2 class="text-sm font-extrabold text-slate-900">Hitung Fisik — Opname</h2>
            <p class="hint mb-3">Scan barcode item di BIN sesi, lalu isi hasil hitungan.</p>

            <div class="mb-3">
                <label class="label">Sesi Opname</label>
                <select class="input" onchange="location.href='{{ route('mobile.index') }}?mode=opname&opname_id='+this.value">
                    @foreach ($sessions as $s)
                        <option value="{{ $s->id }}" @selected($selectedOpname?->id == $s->id)>
                            {{ $s->opname_no }} — {{ $s->location?->code }} ({{ ucfirst(str_replace('_', ' ', $s->status)) }})
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($selectedOpname)
                <form method="POST" action="{{ route('opname.scan', $selectedOpname) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="label">Barcode</label>
                        <input class="input font-mono" name="barcode" id="mBarcode" placeholder="Scan barcode" autocomplete="off">
                    </div>
                    <input type="hidden" name="item_id" id="mItemId">
                    <input type="hidden" name="batch_id" id="mBatchId">
                    <div class="mb-3">
                        <label class="label">Item</label>
                        <input class="input" id="mDesc" readonly placeholder="Hasil scan">
                    </div>
                    <div class="mb-4">
                        <label class="label required">Qty Fisik (PCS)</label>
                        <input class="input" type="number" step="0.001" min="0" name="physical_qty" id="mQty" required>
                        <p class="hint" id="mSys">Stok sistem: -</p>
                    </div>
                    <button class="btn btn-primary w-full"><x-icon name="check" :size="16" /> Simpan Hitungan</button>
                </form>
            @else
                <p class="text-sm text-slate-400">Tidak ada sesi opname berjalan.</p>
            @endif
        @endif

        <div class="mt-4 border-t border-slate-200 pt-3">
            <button type="button" id="camBtn" class="btn btn-ghost w-full">
                <x-icon name="camera" :size="16" /> Scan QR Code / Barcode lewat Kamera
            </button>
            <div id="qrReader" class="mt-3 hidden overflow-hidden rounded-lg"></div>
            <p class="hint mt-2">Kamera butuh HTTPS atau localhost. Mendukung QR Code 2D dan barcode 1D.</p>
        </div>
    </div>

    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-extrabold text-slate-900">Panduan Mode</h2>
        <ul class="space-y-3 text-sm text-slate-600">
            <li class="rounded-lg border border-slate-200 p-3">
                <p class="font-extrabold text-slate-800">Scan In</p>
                <p class="hint">Barcode penerimaan → tampilkan sisa → pilih BIN → lanjut ke putaway dokumen lengkap.</p>
            </li>
            <li class="rounded-lg border border-slate-200 p-3">
                <p class="font-extrabold text-slate-800">Scan Out</p>
                <p class="hint">Pilih surat jalan → scan tiap dus → status otomatis berubah setelah semua dus ter-scan.</p>
            </li>
            <li class="rounded-lg border border-slate-200 p-3">
                <p class="font-extrabold text-slate-800">Hitung Opname</p>
                <p class="hint">Scan item → isi qty fisik → approval dilakukan admin di halaman sesi opname.</p>
            </li>
        </ul>
        <p class="hint mt-4">Scanner USB/Bluetooth bekerja sebagai keyboard: fokus ke input lalu tekan Enter otomatis.</p>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
    const lookupUrl = @json(route('mobile.lookup'));
    const mode = @json($mode);

    async function doLookup(code) {
        if (!code) return;
        const refId = mode === 'scanout' ? {{ (int) ($selectedDelivery?->id ?? 0) }}
                    : mode === 'opname' ? {{ (int) ($selectedOpname?->id ?? 0) }}
                    : {{ (int) $selectedReceiptId }};

        try {
            const res = await fetch(lookupUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ type: mode, barcode: code, reference_id: refId }),
            });
            const data = await res.json();

            if (mode === 'scanin') {
                const box = document.getElementById('scaninResult');
                if (!res.ok) { box.innerHTML = `<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">${data.message}</p>`; return; }
                const p = data.payload;
                box.innerHTML = `
                    <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">${p.doc_number}</p>
                        <p class="text-lg font-extrabold text-slate-900">${p.sku}</p>
                        <p class="text-sm text-slate-600">Sisa ditempatkan: <b>${Number(p.remaining).toLocaleString('id-ID')} PCS</b></p>
                        <p class="hint mt-2">Buka dokumen penerimaan di menu Inbound untuk memilih BIN tujuan.</p>
                        <a class="btn btn-primary mt-3" href="{{ route('inbound.index') }}">Buka Penerimaan</a>
                    </div>`;
                return;
            }

            if (mode === 'opname') {
                if (!res.ok) { document.getElementById('mSys').textContent = data.message; return; }
                const p = data.payload;
                document.getElementById('mItemId').value = p.item_id;
                document.getElementById('mBatchId').value = p.batch_id ?? '';
                document.getElementById('mDesc').value = `${p.item ?? p.sku} (${p.sku})${p.batch ? ' · ' + p.batch : ''}`;
                document.getElementById('mQty').value = p.physical_qty ?? '';
                document.getElementById('mSys').textContent = 'Stok sistem: ' + Number(p.system_qty).toLocaleString('id-ID') + ' PCS';
            }
        } catch (e) {
            console.error(e);
        }
    }

    const input = document.getElementById('mBarcode');
    if (input) {
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); doLookup(input.value.trim()); }
        });
    }

    /* ------------------------------------------------ Kamera: scan QR 2D */
    const camBtn = document.getElementById('camBtn');
    const camReader = document.getElementById('qrReader');
    let html5Scanner = null;

    async function stopCamera() {
        if (!html5Scanner) return;
        try { await html5Scanner.stop(); } catch (e) { /* sudah berhenti */ }
        html5Scanner.clear();
        html5Scanner = null;
        camReader.classList.add('hidden');
        camBtn.textContent = 'Scan QR Code / Barcode lewat Kamera';
    }

    function applyScanned(text) {
        const target = document.getElementById('mBarcode');
        if (!target) return;

        target.value = text;

        if (mode === 'scanout') {
            if (target.form) { target.form.requestSubmit ? target.form.requestSubmit() : target.form.submit(); }
        } else {
            doLookup(text);
        }
    }

    if (camBtn && camReader) {
        camBtn.addEventListener('click', async () => {
            if (html5Scanner) { await stopCamera(); return; }

            if (!window.Html5Qrcode) {
                camReader.classList.remove('hidden');
                camReader.innerHTML = '<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">Library scanner belum termuat. Periksa koneksi internet lalu muat ulang halaman.</p>';
                return;
            }

            camReader.classList.remove('hidden');
            camReader.innerHTML = '';
            html5Scanner = new Html5Qrcode('qrReader', {
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                ],
                rememberLastUsedCamera: false,
            });

            try {
                await html5Scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    async (decodedText) => {
                        await stopCamera();
                        applyScanned(decodedText);
                    },
                    () => { /* frame tanpa kode: abaikan */ }
                );
                camBtn.textContent = 'Matikan Kamera';
            } catch (e) {
                camReader.innerHTML = '<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">Kamera tidak dapat diakses. Izinkan akses kamera dan gunakan HTTPS/localhost.</p>';
                html5Scanner = null;
            }
        });
    }
</script>
@endpush
