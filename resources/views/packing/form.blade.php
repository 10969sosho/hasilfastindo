@extends('layouts.app')

@section('title', 'Buat Packing List')

@section('content')
@if ($order)
    <form method="POST" action="{{ route('packing.store') }}" class="grid gap-5 lg:grid-cols-3">
        @csrf
        <input type="hidden" name="sales_order_id" value="{{ $order->id }}">

        <div class="space-y-5 lg:col-span-2">
            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-extrabold text-slate-900">{{ $order->so_number }}</h2>
                        <p class="hint">{{ $order->customer?->name }} · {{ $order->branch?->name }}</p>
                    </div>
                    <x-badge status="{{ $order->status }}" />
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="mb-4 text-sm font-extrabold text-slate-900">Qty yang Di-pack</h2>
                <div class="table-scroll">
                    <table class="table">
                        <thead><tr><th>Item</th><th class="text-right">Terpenuhi</th><th class="text-right">Sudah Di-pack</th><th class="w-36">Qty Masuk Box</th></tr></thead>
                        <tbody>
                        @foreach ($items as $row)
                            @php $line = $row['line']; @endphp
                            @if ($row['remaining'] > 0)
                                <tr>
                                    <td>
                                        <input type="hidden" name="lines[{{ $loop->index }}][sales_order_item_id]" value="{{ $line->id }}">
                                        <p class="font-bold text-slate-800">{{ $line->item?->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $line->item?->sku }} · {{ $line->uom?->code }}</p>
                                    </td>
                                    <td class="text-right font-semibold">{{ number_format($line->fulfilled_qty) }}</td>
                                    <td class="text-right text-slate-600">{{ number_format($line->packed_qty) }}</td>
                                    <td><input class="input text-right" type="number" step="0.001" min="0.001" max="{{ $row['remaining'] }}"
                                               name="lines[{{ $loop->index }}][qty]" value="{{ $row['remaining'] }}" required></td>
                                </tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <div class="mb-3">
                    <label class="label required">Jumlah Dus (1–99)</label>
                    <input class="input" type="number" name="total_box" min="1" max="99" value="{{ old('total_box', 1) }}" required>
                    <p class="hint">Qty tiap item dibagi rata ke seluruh dus.</p>
                </div>
                <div class="mb-3">
                    <label class="label">Berat per Dus (kg)</label>
                    <input class="input" type="number" step="0.1" min="0" name="weight_kg">
                </div>
                <div class="mb-4">
                    <label class="label">Catatan</label>
                    <textarea class="input" name="notes" rows="2"></textarea>
                </div>
                <div class="flex flex-col gap-2">
                    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Buat Packing List</button>
                    <a href="{{ route('packing.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </div>
        </div>
    </form>
@else
    <div class="card card-pad">
        <h2 class="text-sm font-extrabold text-slate-900">Pilih Sales Order</h2>
        <p class="hint mb-4">Hanya SO dengan status pending/processing/partial.</p>
        <div class="space-y-2">
            @forelse ($candidates as $c)
                <a href="{{ route('packing.create', ['so_id' => $c->id]) }}"
                   class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 hover:border-indigo-400">
                    <span>
                        <span class="font-mono text-xs font-bold text-indigo-700">{{ $c->so_number }}</span>
                        <span class="ml-3 text-sm font-semibold text-slate-700">{{ $c->customer?->name }}</span>
                    </span>
                    <x-badge status="{{ $c->status }}" />
                </a>
            @empty
                <p class="text-slate-400">Tidak ada SO menunggu packing.</p>
            @endforelse
        </div>
        <div class="mt-4"><a href="{{ route('packing.index') }}" class="btn btn-ghost">Kembali</a></div>
    </div>
@endif
@endsection
