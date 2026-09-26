@extends('layouts.app')

@section('title', 'Transfer '.$transfer->transfer_no)
@section('subtitle', $transfer->fromBranch?->name.' → '.$transfer->toBranch?->name.' · '.ucfirst(str_replace('_', ' ', $transfer->status)))

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $transfer->status }}" label="{{ ucfirst(str_replace('_', ' ', $transfer->status)) }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $transfer->fromWarehouse?->code }} → {{ $transfer->toWarehouse?->code }}</span>
    </div>

    <div class="flex flex-wrap gap-2">
        @if ($canShip)
            <form method="POST" action="{{ route('transfer.ship', $transfer) }}"
                  onsubmit="return wmsConfirm('Kirim transfer ini? Stok cabang asal akan dipotong.')">
                @csrf
                <button class="btn btn-primary"><x-icon name="outbound" :size="16" /> Kirim Barang</button>
            </form>
            <form method="POST" action="{{ route('transfer.destroy', $transfer) }}"
                  onsubmit="return wmsConfirm('Batalkan transfer draft ini?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Batalkan</button>
            </form>
        @endif
        <a href="{{ route('transfer.index') }}" class="btn btn-ghost">Kembali</a>
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="card-pad border-b border-slate-100">
                <h2 class="text-sm font-extrabold text-slate-900">Item Transfer</h2>
                <p class="hint">Penerimaan di cabang tujuan bisa mencatat selisih (lebih/kurang) per baris.</p>
            </div>

            @if ($canReceive)
                <form method="POST" action="{{ route('transfer.receive', $transfer) }}">
                    @csrf
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr><th>Item</th><th class="text-right">Dikirim</th><th class="w-32">Diterima</th><th>BIN Tujuan</th><th>Catatan</th></tr>
                            </thead>
                            <tbody>
                            @foreach ($transfer->items as $line)
                                <tr>
                                    <td>
                                        <input type="hidden" name="lines[{{ $loop->index }}][stock_transfer_item_id]" value="{{ $line->id }}">
                                        <p class="font-bold text-slate-800">{{ $line->item?->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $line->item?->sku }} · dari {{ $line->fromLocation?->code }}</p>
                                    </td>
                                    <td class="text-right font-semibold">{{ number_format($line->qty) }} {{ $line->uom?->code }}</td>
                                    <td><input class="input text-right" type="number" step="0.001" min="0" name="lines[{{ $loop->index }}][received_qty]" value="{{ $line->qty }}" required></td>
                                    <td>
                                        <select class="input" name="lines[{{ $loop->index }}][to_location_id]" required>
                                            @foreach ($toBins as $b)
                                                <option value="{{ $b->id }}" @selected($line->to_location_id == $b->id)>{{ $b->code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input class="input" name="lines[{{ $loop->index }}][notes]" placeholder="Opsional"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-pad flex gap-2 border-t border-slate-100">
                        <button class="btn btn-success"><x-icon name="inbound" :size="16" /> Terima Barang</button>
                    </div>
                </form>
            @else
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                            <tr><th>Item</th><th class="text-right">Dikirim</th><th class="text-right">Diterima</th><th>BIN Tujuan</th><th>Catatan</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($transfer->items as $line)
                            <tr>
                                <td>
                                    <p class="font-bold text-slate-800">{{ $line->item?->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $line->item?->sku }}</p>
                                </td>
                                <td class="text-right font-semibold">{{ number_format($line->qty) }} {{ $line->uom?->code }}</td>
                                <td class="text-right font-semibold {{ $line->received_qty < $line->qty ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($line->received_qty) }}</td>
                                <td class="font-mono text-xs">{{ $line->toLocation?->code ?: '-' }}</td>
                                <td class="text-slate-600">{{ $line->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Info Transfer</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">No. TRF</dt><dd class="font-mono font-bold">{{ $transfer->transfer_no }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Asal</dt><dd class="text-right font-semibold">{{ $transfer->fromBranch?->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tujuan</dt><dd class="text-right font-semibold">{{ $transfer->toBranch?->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Dikirim</dt><dd class="font-semibold">{{ $transfer->shipped_at?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Diterima</dt><dd class="font-semibold">{{ $transfer->received_at?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Catatan</dt><dd class="text-right text-slate-600">{{ $transfer->notes ?: '-' }}</dd></div>
            </dl>
        </div>

        @if ($transfer->status === 'discrepancy')
            <div class="card card-pad border border-amber-200 bg-amber-50">
                <p class="text-sm font-extrabold text-amber-700">Perlu Review Pusat</p>
                <p class="mt-1 text-xs text-amber-700/80">Terdapat selisih antara qty dikirim dan diterima.</p>
            </div>
        @endif
    </div>
</div>
@endsection
