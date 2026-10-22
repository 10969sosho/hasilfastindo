@extends('layouts.app')

@section('title', 'Surat Jalan '.$delivery->delivery_no)

@section('content')
<div class="w-full max-w-full space-y-4">
    {{-- Top Action Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-badge status="{{ $delivery->status }}" label="{{ $statuses[$delivery->status] ?? ucfirst(str_replace('_', ' ', $delivery->status)) }}" />
            <span class="rounded bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">SO: {{ $delivery->packingLists->pluck('salesOrder.so_number')->filter()->unique()->join(', ') ?: ($delivery->salesOrder?->so_number ?: '-') }}</span>
            @foreach ($delivery->packingLists as $pl)
                <a href="{{ route('packing.show', $pl) }}" class="rounded bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100">{{ $pl->no_packing }}</a>
            @endforeach
            <span class="rounded {{ $delivery->method === 'pickup_sendiri' ? 'bg-amber-50 text-amber-700' : 'bg-indigo-50 text-indigo-700' }} px-2.5 py-1 text-xs font-bold">
                {{ $delivery->method === 'pickup_sendiri' ? 'Pickup Sendiri' : 'Armada' }}
            </span>
            @if ($delivery->ekspedisi)<span class="rounded bg-slate-900 px-2.5 py-1 text-xs font-bold text-white">Ekspedisi: {{ $delivery->ekspedisi }}</span>@endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('delivery.manifest', $delivery) }}" class="btn btn-ghost text-xs"><x-icon name="printer" :size="15" /> Manifest</a>
            <a href="{{ route('delivery.index') }}" class="btn btn-ghost text-xs">Kembali</a>
        </div>
    </div>

    {{-- Info Pengiriman (Grid 5 Kolom Sejajar & Simetris) --}}
    <div class="card p-4">
        <div class="border-b border-slate-100 pb-2.5 mb-3.5">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Informasi Surat Jalan &amp; Ekspedisi</h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 text-xs">
            <div class="rounded-lg bg-slate-50/80 border border-slate-100 p-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">No. SJ</span>
                <span class="font-mono text-sm font-extrabold text-indigo-700 mt-0.5 block">{{ $delivery->delivery_no }}</span>
            </div>
            <div class="rounded-lg bg-slate-50/80 border border-slate-100 p-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Armada / Nopol</span>
                <span class="font-bold text-slate-900 mt-0.5 block">{{ $delivery->vehicle?->plate_number ?? ($delivery->ekspedisi ?: '-') }}</span>
            </div>
            <div class="rounded-lg bg-slate-50/80 border border-slate-100 p-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Sopir / Kurir</span>
                <span class="font-medium text-slate-800 mt-0.5 block">{{ $delivery->driver_name ?? '-' }}</span>
            </div>
            <div class="rounded-lg bg-slate-50/80 border border-slate-100 p-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Waktu Berangkat / Tiba</span>
                <span class="font-medium text-slate-800 mt-0.5 block">
                    {{ $delivery->departure_time?->format('d/m H:i') ?: '-' }} / {{ $delivery->arrival_time?->format('d/m H:i') ?: '-' }}
                </span>
            </div>
            <div class="rounded-lg bg-slate-50/80 border border-slate-100 p-2.5 col-span-2 sm:col-span-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Alamat Tujuan</span>
                <span class="text-slate-700 mt-0.5 block truncate" title="{{ $delivery->destination_address ?: '-' }}">{{ $delivery->destination_address ?: '-' }}</span>
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            {{-- Scan out Muatan --}}
            <div class="card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-3">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Scan Out Muatan Kendaraan</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Pindai barcode dus (HF-BOX-...) atau master box (HF-MB-...) sebelum naik ke armada.</p>
                    </div>
                    <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-extrabold text-white">
                        {{ $scanned }} / {{ $total }} Dus Ter-scan
                    </span>
                </div>

                @if ($delivery->status === 'pending_scan' || $delivery->status === 'scanned_out')
                    <div class="space-y-2.5">
                        <form method="POST" action="{{ route('delivery.scan', $delivery) }}" id="scanOutForm" class="flex gap-2">
                            @csrf
                            <input class="input flex-1 font-mono text-xs" name="barcode" id="scanInput"
                                   placeholder="Scan barcode dus/koli fisik..." autocomplete="off" autofocus>
                            <button class="btn btn-primary text-xs" type="submit"><x-icon name="inbound" :size="15" /> Scan Out</button>
                        </form>

                        <button type="button" class="btn btn-ghost text-xs w-full flex items-center justify-center gap-1.5" id="scanOutCamBtn">
                            <x-icon name="smartphone" :size="15" /> <span id="scanOutCamBtnText">Scan dengan Kamera</span>
                        </button>
                        <div id="scanOutQrReader" class="hidden overflow-hidden rounded-xl border border-indigo-200 bg-black/90 p-2"></div>
                    </div>
                    @error('barcode')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                @else
                    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">Seluruh muatan telah selesai discan out.</p>
                @endif

                <div class="table-scroll mt-3.5 border border-slate-200 rounded-lg overflow-hidden">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Barcode Dus / Koli</th>
                                <th>Master Box</th>
                                <th>Isi Barang</th>
                                <th class="text-right">Qty</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($manifest as $m)
                            <tr class="hover:bg-slate-50/70">
                                <td class="font-mono text-xs font-bold text-indigo-700">{{ $m['barcode'] }}</td>
                                <td class="font-mono text-[11px] font-bold text-slate-700">{{ $m['master_box_no'] ?: '-' }}</td>
                                <td>
                                    @foreach ($m['items'] as $mi)
                                        <p class="text-xs text-slate-700">{{ $mi->item?->name }} <span class="font-semibold text-slate-500">— {{ number_format($mi->qty) }} {{ $mi->uom?->code }}</span></p>
                                    @endforeach
                                </td>
                                <td class="text-right font-bold text-slate-800">{{ number_format($m['qty']) }}</td>
                                <td class="text-center">
                                    @if ($m['status'] === 'scanned_out')
                                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-100">Ter-scan</span>
                                        <p class="text-[10px] text-slate-400 mt-0.5">{{ $m['scanned_out_at']?->format('d/m H:i') }}</p>
                                    @else
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 border border-slate-200">Belum</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Konsolidasi koli/kardus sebelum naik truk --}}
            @if (in_array($delivery->status, ['pending_scan', 'scanned_out'], true) && $manifest->contains('status', 'pending'))
                <div class="card p-4">
                    <div class="border-b border-slate-100 pb-2 mb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Konsolidasi Koli / Kardus</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Gabungkan beberapa dus ke dalam 1 master box pengiriman sebelum naik truk.</p>
                    </div>

                    <form method="POST" action="{{ route('delivery.consolidate', $delivery) }}">
                        @csrf
                        <div class="table-scroll border border-slate-200 rounded-lg overflow-hidden">
                            <table class="table w-full">
                                <thead>
                                    <tr>
                                        <th class="w-10 text-center">Pilih</th>
                                        <th>Barcode Dus</th>
                                        <th>Isi</th>
                                        <th class="text-right">Qty</th>
                                        <th>Master Box</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($manifest as $m)
                                    @if ($m['status'] === 'pending')
                                        <tr class="hover:bg-slate-50/70">
                                            <td class="text-center"><input type="checkbox" name="item_ids[]" value="{{ implode(',', $m['item_ids']) }}" class="cons-check rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"></td>
                                            <td class="font-mono text-xs font-bold text-indigo-700">{{ $m['barcode'] }}</td>
                                            <td class="text-xs text-slate-600">
                                                @foreach ($m['items'] as $mi)
                                                    {{ $mi->item?->name }} ({{ number_format($mi->qty) }} {{ $mi->uom?->code }}){{ $loop->last ? '' : ', ' }}
                                                @endforeach
                                            </td>
                                            <td class="text-right font-bold">{{ number_format($m['qty']) }}</td>
                                            <td class="font-mono text-[11px] text-slate-600">{{ $m['master_box_no'] ?: '-' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <button class="btn btn-dark text-xs" onclick="return collectChecks(this.form)">
                                <x-icon name="transfer" :size="15" /> Konsolidasi jadi 1 Master Box
                            </button>
                            <span class="text-xs text-slate-500" id="consCount">Belum ada dus dipilih</span>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Status Update Form --}}
            <div class="card p-4">
                <div class="border-b border-slate-100 pb-2 mb-3">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Update Status Pengiriman</h2>
                </div>
                <form method="POST" action="{{ route('delivery.status', $delivery) }}" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label class="label required">Status Baru</label>
                        <select class="input searchable text-xs" name="status" required>
                            @foreach (['in_delivery' => 'Dalam Pengiriman', 'arrived' => 'Tiba', 'received' => 'Selesai / Diterima', 'problem' => 'Bermasalah'] as $k => $l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Diterima oleh</label>
                        <input class="input text-xs" name="received_by" value="{{ old('received_by', $delivery->received_by) }}" placeholder="Nama penerima barang...">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Keterangan / Catatan</label>
                        <input class="input text-xs" name="description" placeholder="Catatan opsional perjalanan / serah terima...">
                    </div>
                    <div>
                        <label class="label">Bukti Foto / Surat Jalan TTD</label>
                        <input class="input text-xs file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700" type="file" name="proof" accept="image/*" capture="environment">
                    </div>
                    <div class="flex items-end">
                        <button class="btn btn-primary text-xs w-full"><x-icon name="check" :size="15" /> Simpan Status Pengiriman</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sidebar Timeline & Bukti --}}
        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2.5 mb-3">Timeline Perjalanan</h2>
                <ol class="space-y-3">
                    @forelse ($delivery->tracks as $t)
                        <li class="flex gap-2.5">
                            <span class="mt-1 h-2 w-2 flex-none rounded-full {{ $t->status === 'problem' ? 'bg-rose-500' : 'bg-indigo-500' }}"></span>
                            <div class="flex-1">
                                <p class="text-xs font-bold text-slate-800">{{ $statuses[$t->status] ?? ucfirst(str_replace('_', ' ', $t->status)) }}</p>
                                @if ($t->description)
                                    <p class="text-[11px] text-slate-600 mt-0.5">{{ $t->description }}</p>
                                @endif
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ $t->created_at?->format('d/m/Y H:i') }} · {{ $t->user?->name }}</p>
                                @if ($t->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($t->photo))
                                    <img class="mt-2 w-full max-h-40 object-cover rounded-lg border border-slate-200" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($t->photo) }}" alt="Bukti">
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-xs text-slate-400 italic">Belum ada aktivitas tercatat.</li>
                    @endforelse
                </ol>
            </div>

            @if ($delivery->received_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($delivery->received_photo))
                <div class="card p-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2.5 mb-3">Foto Bukti Terima</h2>
                    <img class="w-full rounded-lg border border-slate-200"
                         src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($delivery->received_photo) }}" alt="Bukti terima">
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
    const input = document.getElementById('scanInput');
    const form = document.getElementById('scanOutForm');
    const scanOutCamBtn = document.getElementById('scanOutCamBtn');
    const scanOutCamBtnText = document.getElementById('scanOutCamBtnText');
    const scanOutQrReader = document.getElementById('scanOutQrReader');
    let scanOutHtml5Scanner = null;

    async function stopScanOutCamera() {
        if (!scanOutHtml5Scanner) return;
        try { await scanOutHtml5Scanner.stop(); } catch (e) { }
        scanOutHtml5Scanner.clear();
        scanOutHtml5Scanner = null;
        scanOutQrReader.classList.add('hidden');
        scanOutCamBtnText.textContent = 'Scan dengan Kamera';
        scanOutCamBtn.classList.remove('btn-danger');
        scanOutCamBtn.classList.add('btn-ghost');
    }

    if (scanOutCamBtn && scanOutQrReader) {
        scanOutCamBtn.addEventListener('click', async () => {
            if (scanOutHtml5Scanner) {
                await stopScanOutCamera();
                return;
            }

            if (!window.Html5Qrcode) {
                alert('Library kamera belum termuat.');
                return;
            }

            scanOutQrReader.classList.remove('hidden');
            scanOutQrReader.innerHTML = '';
            scanOutHtml5Scanner = new Html5Qrcode('scanOutQrReader', {
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                ],
                rememberLastUsedCamera: false,
            });

            try {
                await scanOutHtml5Scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    async (decodedText) => {
                        await stopScanOutCamera();
                        if (input) {
                            input.value = decodedText;
                            form.submit();
                        }
                    },
                    () => {}
                );
                scanOutCamBtnText.textContent = 'Tutup Kamera';
                scanOutCamBtn.classList.remove('btn-ghost');
                scanOutCamBtn.classList.add('btn-danger');
            } catch (err) {
                scanOutQrReader.innerHTML = '<p class="p-3 text-xs font-bold text-rose-600 bg-rose-50 rounded-lg">Kamera tidak dapat diakses.</p>';
                scanOutHtml5Scanner = null;
            }
        });
    }

    if (input) {
        input.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            if (input.value.trim() && form) form.submit();
        });
    }

    const consCount = document.getElementById('consCount');
    document.querySelectorAll('.cons-check').forEach(el => {
        el.addEventListener('change', () => {
            const n = document.querySelectorAll('.cons-check:checked').length;
            if (consCount) consCount.textContent = n ? n + ' dus siap digabung' : 'Belum ada dus dipilih';
        });
    });

    window.collectChecks = function (f) {
        const n = f.querySelectorAll('.cons-check:checked').length;
        if (!n) {
            alert('Pilih minimal satu dus untuk dikonsolidasi.');
            return false;
        }
        return confirm('Gabungkan ' + n + ' dus ke dalam 1 master box sebelum naik truk?');
    };
</script>
@endpush

@push('scripts')
<script>
    const input = document.getElementById('scanInput');
    if (input) {
        input.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const form = input.closest('form');
            if (input.value.trim()) form.submit();
        });
    }

    const consCount = document.getElementById('consCount');
    document.querySelectorAll('.cons-check').forEach(el => {
        el.addEventListener('change', () => {
            const n = document.querySelectorAll('.cons-check:checked').length;
            if (consCount) consCount.textContent = n ? n + ' dus siap digabung' : 'Belum ada dus dipilih';
        });
    });

    window.collectChecks = function (form) {
        const n = form.querySelectorAll('.cons-check:checked').length;
        if (!n) {
            alert('Pilih minimal satu dus untuk dikonsolidasi.');
            return false;
        }
        return confirm('Gabungkan ' + n + ' dus ke dalam 1 master box sebelum naik truk?');
    };
</script>
@endpush
