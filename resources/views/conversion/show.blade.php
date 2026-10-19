@extends('layouts.app')

@section('title', 'Eksekusi Konversi '.$conversion->conversion_no)
@section('subtitle', $conversion->item?->sku.' · '.$conversion->fromUom?->code.' → '.$conversion->toUom?->code)

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $conversion->status }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ $conversion->conversion_no }}</span>
        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">{{ $conversion->fromUom?->code }} → {{ $conversion->toUom?->code }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">
            Rencana {{ number_format((float) $conversion->planned_qty, 0) }} ·
            Dieksekusi {{ number_format((float) $conversion->executed_qty, 0) }} ·
            Sisa {{ number_format($conversion->remainingQty(), 0) }} {{ $conversion->toUom?->code }}
        </span>
    </div>
    <div class="flex gap-2">
        @if (($conversion->results ?? []) !== [])
            <a href="{{ route('conversion.print', $conversion) }}" class="btn btn-ghost"><x-icon name="printer" :size="16" /> Cetak Label Baru</a>
        @endif
        <a href="{{ route('conversion.index') }}" class="btn btn-ghost">Kembali</a>
    </div>
</div>

@if (! $conversion->isOpen())
    <div class="mb-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
        Perintah konversi ini sudah {{ $conversion->status === 'completed' ? 'selesai' : 'ditutup' }} — barcode tidak bisa dieksekusi lagi.
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-2">
    <div class="card card-pad">
        <h2 class="mb-3 text-sm font-extrabold text-slate-900">1. Scan Barcode Kemasan Asal</h2>
        <p class="hint mb-3">Barcode wajib <b>aktif</b> dan belum pernah dikonversi. Format: <span class="font-mono">SKU-UOM-RECEIVE-SEQ</span>.</p>

        <div class="flex gap-2">
            <input class="input font-mono flex-1" id="cvBarcode" placeholder="ABC123-DUS-GRN-202610-0001-001" autocomplete="off" @disabled(! $conversion->isOpen())>
            <button type="button" class="btn btn-primary" id="cvLookup" @disabled(! $conversion->isOpen())><x-icon name="scan" :size="16" /> Cari</button>
        </div>

        <div id="cvResult" class="mt-4"></div>

        @if ($conversion->isOpen())
            <form method="POST" action="{{ route('conversion.execute', $conversion) }}" id="cvForm" class="mt-4 hidden">
                @csrf
                <input type="hidden" name="barcode" id="cvSerial">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label required">Hasil Konversi ({{ $conversion->toUom?->code }})</label>
                        <input class="input text-right" type="number" step="0.001" min="0.001" name="produced_qty" id="cvProduced" required placeholder="Contoh: 12">
                    </div>
                    <div>
                        <label class="label required">BIN / Rak Hasil Konversi</label>
                        <select class="input" name="location_id" required>
                            <option value="">- Pilih BIN -</option>
                            @foreach ($bins as $b)
                                <option value="{{ $b->id }}" @selected(old('location_id') == $b->id)>{{ $b->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3">
                    <p class="hint">Barcode asal menjadi <b>invalid</b>, {{ $conversion->toUom?->code }} barcode baru diterbitkan. Tanpa posting jurnal ke NAV.</p>
                    <button class="btn btn-success"><x-icon name="repack" :size="16" /> Eksekusi Konversi</button>
                </div>
            </form>
        @endif
    </div>

    <div class="card card-pad bg-slate-50">
        <h2 class="mb-3 text-sm font-extrabold text-slate-900">Instruksi Eksekusi</h2>
        <ol class="hint list-inside list-decimal space-y-2">
            <li>Scan barcode kemasan asal (mis. 1 DUS) — sistem memvalidasi barcode aktif &amp; sesuai perintah.</li>
            <li>Catat <b>jumlah hasil konversi</b> (mis. 12 PCS).</li>
            <li>Tentukan <b>BIN / Rak</b> tempat hasil konversi disimpan.</li>
            <li>Simpan — barcode asal di-invalid, barcode baru terbit, stok dipindah ke BIN hasil, dan gerakan StockMovement bertipe CONVERSION dicatat.</li>
            <li>Cetak label barcode baru untuk satuan hasil konversi.</li>
        </ol>

        <div class="mt-4 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Rincian Perintah</p>
            <p class="mt-1 text-sm text-slate-700">{{ $conversion->item?->name }} ({{ $conversion->item?->sku }})</p>
            <p class="text-xs text-slate-500">Cabang {{ $conversion->branch?->name }} · Gudang {{ $conversion->warehouse?->code }} · Dibuat {{ $conversion->created_at?->format('d/m/Y H:i') }}</p>
            @if ($conversion->notes)<p class="mt-1 text-xs text-slate-500">Catatan: {{ $conversion->notes }}</p>@endif
        </div>
    </div>
</div>

<div class="card overflow-hidden mt-5">
    <div class="card-pad border-b border-slate-100">
        <h2 class="text-sm font-extrabold text-slate-900">Riwayat Eksekusi &amp; Barcode Terbit</h2>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Barcode Asal</th><th class="text-right">Hasil</th><th>BIN Hasil</th><th>Barcode Baru</th></tr></thead>
            <tbody>
            @forelse ($conversion->results ?? [] as $log)
                <tr>
                    <td class="text-slate-600">{{ \Carbon\Carbon::parse($log['executed_at'])->format('d/m/Y H:i') }}</td>
                    <td class="font-mono text-xs font-bold text-rose-600">{{ $log['source_serial'] }}</td>
                    <td class="text-right font-bold">{{ number_format((float) $log['produced_qty'], 0) }} {{ $conversion->toUom?->code }}</td>
                    <td class="font-bold text-slate-700">{{ $log['location'] }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach (($log['new_serials'] ?? []) as $code)
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-600">{{ $code }}</span>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-400">Belum ada eksekusi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const cvUrl = @json(route('conversion.lookup'));
    const cvId = @json($conversion->id);
    const cvResult = document.getElementById('cvResult');
    const cvForm = document.getElementById('cvForm');
    const cvInput = document.getElementById('cvBarcode');

    async function cvLookup() {
        const code = cvInput.value.trim();
        if (!code) return;

        cvResult.innerHTML = '<p class="hint">Memeriksa barcode...</p>';
        if (cvForm) cvForm.classList.add('hidden');

        try {
            const res = await fetch(`${cvUrl}?barcode=${encodeURIComponent(code)}&conversion_id=${cvId}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();

            if (!res.ok) {
                cvResult.innerHTML = `<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">${data.message || 'Barcode tidak valid.'}</p>`;
                return;
            }

            const p = data.payload;
            cvResult.innerHTML = `
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                    <p class="font-mono text-xs font-bold">${p.kode_uom_serialno}</p>
                    <p class="mt-1 font-bold">${p.item || p.sku}</p>
                    <p class="text-xs">${p.qty_uom_in} ${p.uom} = ${Number(data.source_pcs).toLocaleString('id-ID')} PCS · BIN ${p.bin_no || '-'} (${p.location || '-'})</p>
                </div>`;

            document.getElementById('cvSerial').value = p.kode_uom_serialno;
            const produced = document.getElementById('cvProduced');
            produced.value = Number(data.remaining).toLocaleString('id-ID').replace(/\./g, '');
            if (cvForm) cvForm.classList.remove('hidden');
        } catch (e) {
            cvResult.innerHTML = '<p class="rounded-lg bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600">Gagal terhubung ke server.</p>';
        }
    }

    document.getElementById('cvLookup')?.addEventListener('click', cvLookup);
    cvInput?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); cvLookup(); } });
</script>
@endpush
