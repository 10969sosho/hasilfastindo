@extends('layouts.app')

@section('title', 'SO '.$outbound->so_number)

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2">
        <x-badge status="{{ $outbound->status }}" />
        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">Cabang: {{ $outbound->branch?->name }}</span>
        <span class="rounded bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">Pemasok: {{ $outbound->fulfillmentBranch?->name ?? '-' }}</span>
        <a href="{{ route('summary.show', $outbound) }}" class="rounded bg-slate-900 px-2.5 py-0.5 text-xs font-bold text-white hover:bg-slate-800 transition">Radar SO</a>
        <a href="{{ route('packing.create', ['so_id' => $outbound->id]) }}" class="rounded bg-emerald-600 px-2.5 py-0.5 text-xs font-bold text-white hover:bg-emerald-700 transition">Packing List</a>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($outbound->status === 'pending')
            <form method="POST" action="{{ route('outbound.confirm', $outbound) }}">
                @csrf
                <button class="btn btn-success text-xs"><x-icon name="check" :size="15" /> Konfirmasi SO</button>
            </form>
        @endif
        <a href="{{ route('outbound.index') }}" class="btn btn-ghost text-xs">Kembali</a>
    </div>
</div>

{{-- Filter Gudang Pemasok Minimalist --}}
<form method="GET" class="card p-3 mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Gudang Pemasok:</span>
        <div class="w-64">
            <select class="input searchable text-xs" name="source_branch_id" onchange="this.form.submit()">
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected($sourceBranchId == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        {{-- Tabel Pemenuhan --}}
        <div class="card overflow-hidden">
            <div class="p-3.5 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Detail Pemenuhan SO</h2>
            </div>

            <form method="POST" action="{{ route('outbound.fulfill', $outbound) }}">
                @csrf
                <input type="hidden" name="source_branch_id" value="{{ $sourceBranchId }}">
                <div class="table-scroll">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-right">Dipesan</th>
                                <th class="text-right">Terpenuhi</th>
                                <th class="text-right">Sisa</th>
                                <th class="w-28 text-center">Qty Keluar</th>
                                <th>Rekomendasi FIFO</th>
                                <th>Rak &amp; Serial</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($lines as $row)
                            @php $line = $row['line']; @endphp
                            <tr class="align-top hover:bg-slate-50/70">
                                <td>
                                    <p class="font-bold text-slate-900">{{ $line->item?->name }}</p>
                                    <p class="text-[11px] font-mono text-slate-400">{{ $line->item?->sku }} · {{ $line->uom?->code }}</p>
                                </td>
                                <td class="text-right font-semibold text-slate-800">{{ number_format($line->requested_qty) }}</td>
                                <td class="text-right font-semibold text-emerald-600">{{ number_format($line->fulfilled_qty) }}</td>
                                <td class="text-right font-bold {{ $row['remaining'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ number_format($row['remaining']) }}</td>
                                <td>
                                    @if ($row['remaining'] > 0)
                                        <input type="hidden" name="lines[{{ $loop->index }}][sales_order_item_id]" value="{{ $line->id }}">
                                        <input class="input text-center font-bold !h-8 text-xs" type="number" step="0.001" min="0" max="{{ $row['remaining'] }}"
                                               name="lines[{{ $loop->index }}][qty]" value="{{ $row['remaining'] }}">
                                    @else
                                        <span class="text-xs font-bold text-emerald-600 text-center block">Lengkap</span>
                                    @endif
                                </td>
                                <td class="min-w-[12rem]">
                                    @if ($row['remaining'] <= 0)
                                        <span class="text-xs text-slate-400">-</span>
                                    @elseif ($row['canFifo'])
                                        <ul class="space-y-1">
                                            @foreach ($row['fifo'] as $alloc)
                                                <li class="rounded bg-slate-50 border border-slate-200/60 px-2 py-0.5 text-[11px] text-slate-700">
                                                    <span class="font-bold text-indigo-700 font-mono">{{ $alloc['stock']->location?->code }}</span>
                                                    · {{ $alloc['batch']?->batch_number ?? 'Tanpa batch' }}
                                                    · <b>{{ number_format($alloc['qty']) }} PCS</b>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="rounded bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-600 border border-rose-100 inline-block">Stok tidak mencukupi</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['serials']->isEmpty())
                                        <span class="text-xs text-slate-400">-</span>
                                    @else
                                        <ul class="space-y-1">
                                            @foreach ($row['serials']->take(4) as $serial)
                                                <li class="rounded bg-slate-50 border border-slate-200/60 px-2 py-0.5 text-[11px] text-slate-700">
                                                    <b class="text-indigo-700 font-mono">{{ $serial->bin_no ?: '-' }}</b>
                                                    · <span class="font-mono text-[10px] text-slate-500">{{ $serial->kode_uom_serialno }}</span>
                                                </li>
                                            @endforeach
                                            @if ($row['serials']->count() > 4)
                                                <li class="text-[10px] font-bold text-slate-400">+{{ $row['serials']->count() - 4 }} serial lainnya</li>
                                            @endif
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-slate-400">Tidak ada item.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Action Bar Pemenuhan --}}
                <div class="p-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2.5 bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <button class="btn btn-primary text-xs"><x-icon name="outbound" :size="15" /> Keluarkan Barang (FIFO)</button>
                    </div>
                </div>
            </form>

            <div class="p-3 border-t border-slate-100 bg-white">
                <form method="POST" action="{{ route('outbound.auto', $outbound) }}" onsubmit="return wmsConfirm('Penuhi seluruh sisa SO dengan FIFO dari cabang terpilih?')">
                    @csrf
                    <input type="hidden" name="source_branch_id" value="{{ $sourceBranchId }}">
                    <button class="btn btn-amber text-xs"><x-icon name="repack" :size="15" /> Auto-Penuhi Seluruh Sisa (FIFO)</button>
                </form>
            </div>
        </div>

        {{-- Picking: scan serial UOM --}}
        <div class="card p-4">
            <div class="border-b border-slate-100 pb-2 mb-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Picking — Scan Barcode Serial</h2>
            </div>
            @if ($outbound->status === 'pending')
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">
                    SO menunggu konfirmasi admin — klik tombol "Konfirmasi SO" di atas untuk memulai picking.
                </div>
            @endif
            <div class="space-y-2.5">
                <div class="flex gap-2">
                    <input class="input font-mono flex-1 text-xs" id="pickBarcode" placeholder="Scan barcode serial kemasan (SKU-UOM-RECEIVE-SEQ)..." autocomplete="off">
                    <button type="button" class="btn btn-primary text-xs" id="pickBtn"><x-icon name="check" :size="15" /> Pick</button>
                </div>
                <button type="button" class="btn btn-ghost text-xs w-full flex items-center justify-center gap-1.5" id="pickCamBtn">
                    <x-icon name="smartphone" :size="15" /> <span id="pickCamBtnText">Scan dengan Kamera</span>
                </button>
                <div id="pickQrReader" class="hidden overflow-hidden rounded-xl border border-indigo-200 bg-black/90 p-2"></div>
            </div>
            <div id="pickResult" class="mt-3"></div>
        </div>
    </div>

    {{-- Sidebar Informasi SO --}}
    <div class="space-y-4">
        <div class="card p-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2.5 mb-3">Informasi SO</h2>
            <dl class="space-y-2 text-xs">
                <div class="flex justify-between gap-3"><dt class="text-slate-400 font-semibold uppercase text-[10px]">No. SO</dt><dd class="font-mono font-bold text-slate-900">{{ $outbound->so_number }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-400 font-semibold uppercase text-[10px]">Customer</dt><dd class="text-right font-medium text-slate-900">{{ $outbound->customer?->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-400 font-semibold uppercase text-[10px]">Tanggal</dt><dd class="text-slate-800 font-medium">{{ $outbound->order_date?->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-400 font-semibold uppercase text-[10px]">Nilai Total</dt><dd class="font-extrabold text-slate-900">Rp {{ number_format($outbound->total_amount) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-400 font-semibold uppercase text-[10px]">Catatan</dt><dd class="text-right text-slate-600">{{ $outbound->notes ?: '-' }}</dd></div>
            </dl>
        </div>

        @if ($outbound->packingLists->isNotEmpty())
            <div class="card p-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-2.5">Packing List</h2>
                <div class="space-y-2">
                    @foreach ($outbound->packingLists as $pl)
                        <a href="{{ route('packing.show', $pl) }}" class="flex items-center justify-between rounded-lg border border-slate-200 p-2.5 hover:border-indigo-500 hover:bg-indigo-50/20 transition">
                            <span class="font-mono text-xs font-bold text-indigo-700">{{ $pl->no_packing }}</span>
                            <span class="text-xs font-semibold text-slate-500">{{ $pl->total_box }} box</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($outbound->deliveries->isNotEmpty())
            <div class="card p-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-2.5">Pengiriman</h2>
                <div class="space-y-2">
                    @foreach ($outbound->deliveries as $d)
                        <a href="{{ route('delivery.show', $d) }}" class="flex items-center justify-between rounded-lg border border-slate-200 p-2.5 hover:border-indigo-500 hover:bg-indigo-50/20 transition">
                            <span class="font-mono text-xs font-bold text-indigo-700">{{ $d->delivery_no }}</span>
                            <x-badge status="{{ $d->status }}" />
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
    const pickUrl = @json(route('outbound.pick', $outbound));
    const pickBox = document.getElementById('pickResult');
    const pickInput = document.getElementById('pickBarcode');
    const pickCamBtn = document.getElementById('pickCamBtn');
    const pickCamBtnText = document.getElementById('pickCamBtnText');
    const pickQrReader = document.getElementById('pickQrReader');
    let pickHtml5Scanner = null;

    async function stopPickCamera() {
        if (!pickHtml5Scanner) return;
        try { await pickHtml5Scanner.stop(); } catch (e) { }
        pickHtml5Scanner.clear();
        pickHtml5Scanner = null;
        pickQrReader.classList.add('hidden');
        pickCamBtnText.textContent = 'Scan dengan Kamera';
        pickCamBtn.classList.remove('btn-danger');
        pickCamBtn.classList.add('btn-ghost');
    }

    if (pickCamBtn && pickQrReader) {
        pickCamBtn.addEventListener('click', async () => {
            if (pickHtml5Scanner) {
                await stopPickCamera();
                return;
            }

            if (!window.Html5Qrcode) {
                alert('Library kamera belum termuat.');
                return;
            }

            pickQrReader.classList.remove('hidden');
            pickQrReader.innerHTML = '';
            pickHtml5Scanner = new Html5Qrcode('pickQrReader', {
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                ],
                rememberLastUsedCamera: false,
            });

            try {
                await pickHtml5Scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    async (decodedText) => {
                        await stopPickCamera();
                        pickInput.value = decodedText;
                        pickSerial();
                    },
                    () => {}
                );
                pickCamBtnText.textContent = 'Tutup Kamera';
                pickCamBtn.classList.remove('btn-ghost');
                pickCamBtn.classList.add('btn-danger');
            } catch (err) {
                pickQrReader.innerHTML = '<p class="p-3 text-xs font-bold text-rose-600 bg-rose-50 rounded-lg">Kamera tidak dapat diakses.</p>';
                pickHtml5Scanner = null;
            }
        });
    }

    async function pickSerial() {
        const code = pickInput.value.trim();
        if (!code) return;

        pickBox.innerHTML = '<p class="text-xs text-slate-500">Memeriksa barcode...</p>';

        try {
            const res = await fetch(pickUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ barcode: code }),
            });
            const data = await res.json();
            const p = data.payload;
            const detail = p ? `
                <div class="mt-1 text-xs text-slate-600">
                    <span class="font-mono font-bold">${p.kode_uom_serialno}</span>
                    · ${p.item || p.sku} · ${p.qty_uom_in} ${p.uom} (${Number(p.qty_smallest_unit_in).toLocaleString('id-ID')} PCS)
                    · BIN ${p.bin_no || '-'} · ${p.receive_no}
                    · ${p.picked ? 'Sudah dipick ' + (p.pick_at || '') : 'Belum dipick'}
                </div>` : '';

            pickBox.innerHTML = `
                <div class="rounded-lg p-3 text-xs font-bold ${res.ok ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'}">
                    ${data.message}${detail}
                </div>`;

            if (res.ok) {
                pickInput.value = '';
                pickInput.focus();
            }
        } catch (e) {
            pickBox.innerHTML = '<p class="rounded-lg bg-rose-50 p-3 text-xs font-bold text-rose-600">Gagal terhubung ke server.</p>';
        }
    }

    document.getElementById('pickBtn').addEventListener('click', pickSerial);
    pickInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); pickSerial(); } });
</script>
@endpush
