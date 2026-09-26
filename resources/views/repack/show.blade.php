@extends('layouts.app')

@section('title', 'Detail Repack '.$repack->repack_no)
@section('subtitle', $repack->branch?->name.' · '.ucfirst($repack->status))

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $repack->status }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $repack->warehouse?->code }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $repack->user?->name }}</span>
    </div>
    <a href="{{ route('repack.index') }}" class="btn btn-ghost">Kembali</a>
</div>

@forelse ($repack->items as $line)
    <div class="card card-pad mb-5">
        <div class="grid gap-6 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 p-4">
                <p class="label !mb-1">Barang Asal</p>
                <p class="text-lg font-extrabold text-slate-900">{{ $line->item?->name }}</p>
                <p class="text-xs text-slate-400">{{ $line->item?->sku }}</p>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Qty</dt><dd class="font-bold">{{ number_format($line->source_qty) }} {{ $line->sourceUom?->code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Total PCS</dt><dd class="font-bold">{{ number_format($line->source_qty_pcs) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Batch</dt><dd class="font-mono text-xs">{{ $line->batch?->batch_number ?: '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Barcode asal</dt><dd class="font-mono text-xs">{{ $line->source_barcode ?: '-' }}</dd></div>
                </dl>
            </div>

            <div class="flex items-center justify-center">
                <div class="text-center text-slate-400">
                    <x-icon name="repack" :size="40" />
                    <p class="mt-1 text-xs font-bold uppercase tracking-widest">Pecah Satuan</p>
                </div>
            </div>

            <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4">
                <p class="label !mb-1 text-indigo-700">Barang Hasil</p>
                <p class="text-lg font-extrabold text-slate-900">{{ $line->targetItem?->name ?? $line->item?->name }}</p>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Qty</dt><dd class="font-bold">{{ number_format($line->target_qty) }} {{ $line->targetUom?->code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">BIN Tujuan</dt><dd class="font-mono text-xs">{{ $line->targetLocation?->code ?: '-' }}</dd></div>
                </dl>
                <div class="mt-3 rounded-lg border border-indigo-200 bg-white p-3 text-center">
                    <p class="font-mono text-sm font-extrabold tracking-widest text-indigo-700">{{ $line->target_barcode_new }}</p>
                    <p class="text-[11px] text-slate-400">Barcode baru — cetak dari menu Barcode Item</p>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="card card-pad text-center text-slate-400">Tidak ada item.</div>
@endforelse
@endsection
