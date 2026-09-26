@extends('layouts.app')

@section('title', 'SO '.$outbound->so_number)
@section('subtitle', $outbound->customer?->name.' · '.ucfirst($outbound->status))

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $outbound->status }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Cabang: {{ $outbound->branch?->name }}</span>
        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">Pemasok: {{ $outbound->fulfillmentBranch?->name ?? '-' }}</span>
        <a href="{{ route('summary.show', $outbound) }}" class="rounded-full bg-slate-900 px-3 py-1 text-xs font-bold text-white">Radar SO</a>
        <a href="{{ route('packing.create', ['so_id' => $outbound->id]) }}" class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white">Packing List</a>
    </div>
    <a href="{{ route('outbound.index') }}" class="btn btn-ghost">Kembali</a>
</div>

<form method="GET" class="card card-pad mb-5 flex flex-wrap items-end gap-3">
    <div class="min-w-[14rem]">
        <label class="label">Gudang pemasok (sumber barang)</label>
        <select class="input" name="source_branch_id" onchange="this.form.submit()">
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected($sourceBranchId == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>
    <p class="hint pb-2">Rekomendasi FIFO di bawah dihitung dari cabang terpilih.</p>
</form>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="card-pad border-b border-slate-100">
                <h2 class="text-sm font-extrabold text-slate-900">Detail Pemenuhan</h2>
                <p class="hint">Batch diurutkan FIFO (received_at paling awal lebih dulu dipakai).</p>
            </div>

            <form method="POST" action="{{ route('outbound.fulfill', $outbound) }}">
                @csrf
                <input type="hidden" name="source_branch_id" value="{{ $sourceBranchId }}">
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                            <tr><th>Item</th><th class="text-right">Dipesan</th><th class="text-right">Terpenuhi</th><th class="text-right">Sisa</th><th class="w-32">Qty Keluar</th><th>Rekomendasi FIFO</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($lines as $row)
                            @php $line = $row['line']; @endphp
                            <tr class="align-top">
                                <td>
                                    <p class="font-bold text-slate-800">{{ $line->item?->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $line->item?->sku }} · {{ $line->uom?->code }}</p>
                                </td>
                                <td class="text-right font-semibold">{{ number_format($line->requested_qty) }}</td>
                                <td class="text-right font-semibold text-emerald-600">{{ number_format($line->fulfilled_qty) }}</td>
                                <td class="text-right font-bold {{ $row['remaining'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ number_format($row['remaining']) }}</td>
                                <td>
                                    @if ($row['remaining'] > 0)
                                        <input type="hidden" name="lines[{{ $loop->index }}][sales_order_item_id]" value="{{ $line->id }}">
                                        <input class="input text-right" type="number" step="0.001" min="0" max="{{ $row['remaining'] }}"
                                               name="lines[{{ $loop->index }}][qty]" value="{{ $row['remaining'] }}">
                                    @else
                                        <span class="text-xs font-bold text-emerald-600">Lengkap</span>
                                    @endif
                                </td>
                                <td class="min-w-[14rem]">
                                    @if ($row['remaining'] <= 0)
                                        <span class="text-xs text-slate-400">-</span>
                                    @elseif ($row['canFifo'])
                                        <ul class="space-y-1">
                                            @foreach ($row['fifo'] as $alloc)
                                                <li class="rounded-md bg-slate-50 px-2 py-1 text-[11px] text-slate-600">
                                                    <span class="font-bold text-indigo-700">{{ $alloc['stock']->location?->code }}</span>
                                                    · {{ $alloc['batch']?->batch_number ?? 'Tanpa batch' }}
                                                    · masuk {{ $alloc['stock']->received_at?->format('d/m') ?? '-' }}
                                                    · <b>{{ number_format($alloc['qty']) }} PCS</b>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="rounded-md bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-600">Stok tidak mencukupi di cabang ini</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-400">Tidak ada item.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-pad flex flex-wrap gap-2 border-t border-slate-100">
                    <button class="btn btn-primary"><x-icon name="outbound" :size="16" /> Keluarkan Barang (FIFO)</button>
                </div>
            </form>

            <div class="card-pad border-t border-slate-100">
                <form method="POST" action="{{ route('outbound.auto', $outbound) }}" onsubmit="return wmsConfirm('Penuhi seluruh sisa SO dengan FIFO dari cabang terpilih?')">
                    @csrf
                    <input type="hidden" name="source_branch_id" value="{{ $sourceBranchId }}">
                    <button class="btn btn-amber"><x-icon name="repack" :size="16" /> Auto-Penuhi Seluruh Sisa (FIFO)</button>
                </form>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Informasi SO</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">No. SO</dt><dd class="font-mono font-bold">{{ $outbound->so_number }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Customer</dt><dd class="text-right font-semibold">{{ $outbound->customer?->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tanggal</dt><dd class="font-semibold">{{ $outbound->order_date?->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Nilai</dt><dd class="font-semibold">Rp {{ number_format($outbound->total_amount) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Catatan</dt><dd class="text-right text-slate-600">{{ $outbound->notes ?: '-' }}</dd></div>
            </dl>
        </div>

        @if ($outbound->packingLists->isNotEmpty())
            <div class="card card-pad">
                <h2 class="mb-3 text-sm font-extrabold text-slate-900">Packing List</h2>
                <div class="space-y-2">
                    @foreach ($outbound->packingLists as $pl)
                        <a href="{{ route('packing.show', $pl) }}" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 hover:border-indigo-400">
                            <span class="font-mono text-xs font-bold text-indigo-700">{{ $pl->no_packing }}</span>
                            <span class="text-xs font-bold text-slate-500">{{ $pl->total_box }} box</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($outbound->deliveries->isNotEmpty())
            <div class="card card-pad">
                <h2 class="mb-3 text-sm font-extrabold text-slate-900">Pengiriman</h2>
                <div class="space-y-2">
                    @foreach ($outbound->deliveries as $d)
                        <a href="{{ route('delivery.show', $d) }}" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 hover:border-indigo-400">
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
