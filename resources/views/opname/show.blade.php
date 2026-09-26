@extends('layouts.app')

@section('title', 'Opname '.$opname->opname_no)
@section('subtitle', ($opname->title ?: $opname->location?->code).' · '.ucfirst(str_replace('_', ' ', $opname->status)))

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $opname->status }}" label="{{ ucfirst(str_replace('_', ' ', $opname->status)) }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $opname->warehouse?->code }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 font-mono">{{ $opname->location?->code }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Oleh {{ $opname->creator?->name }}</span>
    </div>

    <div class="flex flex-wrap gap-2">
        @if ($canEdit)
            <form method="POST" action="{{ route('opname.approve', $opname) }}"
                  onsubmit="return wmsConfirm('Approve opname? Stok sistem akan disesuaikan dengan hitungan fisik.')">
                @csrf
                <button class="btn btn-success"><x-icon name="check" :size="16" /> Approval & Rekonsiliasi</button>
            </form>
            <form method="POST" action="{{ route('opname.destroy', $opname) }}"
                  onsubmit="return wmsConfirm('Batalkan sesi opname ini?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Batalkan</button>
            </form>
        @endif
        <a href="{{ route('opname.index') }}" class="btn btn-ghost">Kembali</a>
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5">
        {{-- Input hitungan fisik --}}
        <div class="card card-pad">
            <h2 class="mb-1 text-sm font-extrabold text-slate-900">Input / Scan Fisik</h2>
            <p class="hint mb-3">Scan barcode atau cari item, lalu masukkan hasil hitungan.</p>

            <div class="mb-3">
                <label class="label">Barcode</label>
                <input class="input font-mono" id="barcode" placeholder="Scan / ketik barcode" autocomplete="off">
                <p class="hint mt-1">Enter untuk lookup → form fisik terisi otomatis.</p>
                <p class="mt-1 text-xs font-bold" id="lookupMsg"></p>
            </div>

            <form method="POST" action="{{ route('opname.scan', $opname) }}">
                @csrf
                <input type="hidden" name="item_id" id="f_item_id">
                <input type="hidden" name="batch_id" id="f_batch_id">
                <div class="mb-3">
                    <label class="label">Item (SKU / batch)</label>
                    <input class="input" id="f_desc" placeholder="Hasil lookup barcode" readonly>
                </div>
                <div class="mb-3">
                    <label class="label">Barcode</label>
                    <input class="input font-mono" name="barcode" id="f_barcode">
                </div>
                <div class="mb-4">
                    <label class="label required">Qty Fisik (PCS)</label>
                    <input class="input" type="number" step="0.001" min="0" name="physical_qty" id="f_qty" required>
                    <p class="hint" id="sysHint">Stok sistem: -</p>
                </div>
                <button class="btn btn-primary w-full" {{ $canEdit ? '' : 'disabled' }}><x-icon name="check" :size="16" /> Simpan Hasil Hitung</button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2 space-y-5">
        <div class="card overflow-hidden">
            <div class="card-pad border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Hasil Perhitungan</h2>
                    <p class="hint">Sistem = stok di BIN ini. Fisik = hasil hitung petugas.</p>
                </div>
                @php
                    $diffTotal = $opname->items->sum('difference_qty');
                @endphp
                <span class="rounded-lg px-3 py-1.5 text-sm font-extrabold {{ abs($diffTotal) < 0.0001 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                    Selisih total: {{ number_format($diffTotal, 0) }} PCS
                </span>
            </div>

            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr><th>Item</th><th>Batch</th><th class="text-right">Sistem</th><th class="text-right">Fisik</th><th class="text-right">Selisih</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($opname->items as $row)
                        <tr>
                            <td>
                                <p class="font-bold text-slate-800">{{ $row->item?->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $row->item?->sku }}</p>
                            </td>
                            <td class="font-mono text-xs text-slate-600">{{ $row->batch?->batch_number ?: '-' }}</td>
                            <td class="text-right">{{ number_format($row->system_qty) }}</td>
                            <td class="text-right font-bold">{{ number_format($row->physical_qty) }}</td>
                            <td class="text-right font-extrabold {{ $row->difference_qty > 0 ? 'text-emerald-600' : ($row->difference_qty < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                                {{ number_format($row->difference_qty) }}
                            </td>
                            <td><x-badge status="{{ $row->status }}" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">Belum ada hasil hitung.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-pad border-b border-slate-100">
                <h2 class="text-sm font-extrabold text-slate-900">Stok Sistem di BIN Ini</h2>
                <p class="hint">Baris ini belum dihitung fisik (belum discan).</p>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Item</th><th>Batch</th><th class="text-right">Qty Sistem</th></tr></thead>
                    <tbody>
                    @forelse ($systemRows as $s)
                        @php $counted = $opname->items->contains(fn ($i) => $i->item_id === $s->item_id && (int) $i->batch_id === (int) $s->batch_id); @endphp
                        @if (! $counted)
                            <tr>
                                <td>
                                    <p class="font-bold text-slate-800">{{ $s->item?->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $s->item?->sku }} · {{ $s->original_barcode }}</p>
                                </td>
                                <td class="font-mono text-xs text-slate-600">{{ $s->batch?->batch_number ?: '-' }}</td>
                                <td class="text-right font-semibold">{{ number_format($s->quantity_pcs) }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-slate-400">BIN kosong.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const lookupUrl = @json(route('opname.lookup'));
    const sessionOpnameId = {{ (int) $opname->id }};
    const barcodeInput = document.getElementById('barcode');
    const msg = document.getElementById('lookupMsg');

    async function lookup(code) {
        if (!code) return;
        msg.className = 'mt-1 text-xs font-bold text-slate-500';
        msg.textContent = 'Memeriksa...';
        try {
            const res = await fetch(`${lookupUrl}?stock_opname_id=${sessionOpnameId}&barcode=${encodeURIComponent(code)}`);
            const data = await res.json();
            if (!res.ok) {
                msg.className = 'mt-1 text-xs font-bold text-rose-600';
                msg.textContent = data.message || 'Tidak ditemukan.';
                return;
            }
            document.getElementById('f_item_id').value = data.item_id;
            document.getElementById('f_batch_id').value = data.batch_id ?? '';
            document.getElementById('f_desc').value = `${data.item} (${data.sku})${data.batch ? ' · batch ' + data.batch : ''}`;
            document.getElementById('f_barcode').value = data.barcode;
            document.getElementById('f_qty').value = data.physical_qty ?? '';
            document.getElementById('sysHint').textContent = 'Stok sistem: ' + Number(data.system_qty).toLocaleString('id-ID') + ' PCS';
            msg.className = 'mt-1 text-xs font-bold text-emerald-600';
            msg.textContent = 'Barang ditemukan.';
            document.getElementById('f_qty').focus();
        } catch (e) {
            msg.className = 'mt-1 text-xs font-bold text-rose-600';
            msg.textContent = 'Gagal memeriksa barcode.';
        }
    }

    barcodeInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); lookup(barcodeInput.value.trim()); }
    });
</script>
@endpush
