@extends('layouts.print')

@section('title', 'Label QR — '.$gr->doc_number)

@section('content')
@if ($filtered && $serials->isEmpty())
    <p class="rounded-lg bg-slate-100 px-4 py-3 text-sm font-bold text-slate-600">Tidak ada serial untuk filter ini.</p>
@elseif ($serials->isNotEmpty())
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
        @foreach ($serials as $s)
            <div class="rounded-xl border border-slate-300 bg-white p-3.5 text-center flex flex-col justify-between items-center shadow-xs">
                <div class="w-full">
                    <p class="text-xs font-extrabold uppercase text-slate-900 truncate">{{ $itemNames[$s->sku] ?? $s->sku }}</p>
                    <p class="text-[11px] font-mono text-slate-500">{{ $s->sku }} · {{ $s->batch_no ?: $s->uom }}</p>
                </div>

                <div class="my-2 p-1 bg-white inline-block" data-qr="{{ $s->kode_uom_serialno }}" data-qr-size="140"></div>

                <div class="w-full">
                    <p class="font-mono text-xs font-bold text-slate-900 tracking-tight">{{ $s->kode_uom_serialno }}</p>
                    <p class="mt-0.5 text-xs font-extrabold text-indigo-700">
                        1 {{ $s->uom }} = {{ rtrim(rtrim(number_format($s->qty_smallest_unit_in, 0, '.', ''), '0'), '.') }} PCS
                    </p>
                    @if ($s->weight !== null || $s->bin_no)
                        <p class="text-[10px] text-slate-500 font-semibold">
                            {{ $s->weight !== null ? rtrim(rtrim(number_format($s->weight, 3, '.', ''), '0'), '.') . ' kg' : '' }}
                            {{ $s->weight !== null && $s->bin_no ? ' · ' : '' }}
                            {{ $s->bin_no ?: '' }}
                        </p>
                    @endif
                    <p class="mt-1 text-[9px] font-bold text-slate-400 uppercase tracking-widest border-t border-slate-100 pt-1">
                        {{ $gr->doc_number }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        @foreach ($gr->items as $line)
            <div class="rounded-xl border border-slate-300 bg-white p-4 text-center flex flex-col justify-between items-center shadow-xs">
                <div class="w-full">
                    <p class="text-xs font-extrabold uppercase text-slate-900 truncate">{{ $line->item?->name }}</p>
                    <p class="text-[11px] font-mono text-slate-500">{{ $line->item?->sku }} · Batch {{ $line->batch_no ?: '-' }}</p>
                </div>

                <div class="my-2 p-1 bg-white inline-block" data-qr="{{ $line->original_barcode }}" data-qr-size="140"></div>

                <div class="w-full">
                    <p class="font-mono text-xs font-bold text-slate-900 tracking-wider">{{ $line->original_barcode }}</p>
                    <p class="mt-0.5 text-xs font-extrabold text-indigo-700">
                        {{ number_format($line->received_qty, 0) }} {{ $line->receivedUom?->code }} = {{ number_format($line->total_pcs, 0) }} PCS
                    </p>
                    @if ($line->weight !== null || $line->bin_no)
                        <p class="text-[10px] text-slate-500 font-semibold">
                            {{ $line->weight !== null ? rtrim(rtrim(number_format($line->weight, 3, '.', ''), '0'), '.') . ' kg' : '' }}
                            {{ $line->weight !== null && $line->bin_no ? ' · ' : '' }}
                            {{ $line->bin_no ?: '' }}
                        </p>
                    @endif
                    <p class="mt-1 text-[9px] font-bold text-slate-400 uppercase tracking-widest border-t border-slate-100 pt-1">
                        {{ $gr->doc_number }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
