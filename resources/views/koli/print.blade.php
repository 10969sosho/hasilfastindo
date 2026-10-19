@extends('layouts.print')

@section('title', 'Label Koli '.$packing->no_packing)

@section('content')
<p class="mb-3 text-xs text-slate-500">
    {{ $packing->no_packing }} · SO {{ $packing->salesOrder?->so_number }} · {{ $packing->customer?->name ?? $packing->salesOrder?->customer?->name }}
    · dicetak {{ now()->format('d/m/Y H:i') }}
</p>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($packing->boxes->sortBy('box_number') as $box)
        <div class="rounded-lg border border-slate-400 p-4 text-center" style="page-break-inside:avoid;">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">HASIL FASTINDO · LABEL KOLI</p>
            <p class="text-base font-extrabold">KOLI {{ $box->box_number }} / {{ $packing->total_box }}</p>
            <div class="mx-auto" data-qr="{{ $box->box_barcode }}" data-qr-size="140"></div>
            <p class="font-mono text-xs font-bold tracking-widest">{{ $box->box_barcode }}</p>
            <p class="mt-1 text-[11px] font-semibold text-slate-600">
                SO {{ $packing->salesOrder?->so_number }} · {{ Str::limit($packing->customer?->name ?? $packing->salesOrder?->customer?->name ?? '', 30) }}
            </p>
            @if ($box->weight_kg !== null)
                <p class="text-[11px] font-bold text-slate-700">Berat: {{ rtrim(rtrim(number_format($box->weight_kg, 3, '.', ''), '0'), '.') }} kg</p>
            @endif
            <ul class="mt-1 space-y-0.5 text-[10px] text-slate-600">
                @foreach ($box->items as $bi)
                    <li>{{ $bi->item?->name }} — {{ number_format($bi->qty) }} {{ $bi->uom?->code }}</li>
                @endforeach
            </ul>
            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $packing->no_packing }}</p>
        </div>
    @endforeach
</div>
@endsection
