@extends('layouts.app')

@section('title', 'Generate Barcode Koli · '.$packing->no_packing)
@section('subtitle', 'Pembagian barang ke koli, berat koli, dan penerbitan barcode KOLI-YYYYMMDD-XXXX.')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ $packing->no_packing }}</span>
        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">SO {{ $packing->salesOrder?->so_number ?: '-' }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ $packing->customer?->name ?? $packing->salesOrder?->customer?->name }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ $packing->boxes->count() }} koli</span>
    </div>
    <a href="{{ route('koli.index') }}" class="btn btn-ghost">Kembali</a>
</div>

@if ($packing->boxes->isEmpty())
    <div class="card card-pad">
        <p class="font-semibold text-slate-700">Packing list ini belum memiliki koli.</p>
        <p class="hint mt-1">Selesaikan proses picking &amp; packing pada modul Picking &amp; Packing terlebih dahulu.</p>
        <a href="{{ route('packing.create') }}" class="btn btn-primary mt-3">Buka Picking &amp; Packing</a>
    </div>
@else
    <form method="POST" action="{{ route('koli.store', $packing) }}">
        @csrf
        <div class="card overflow-hidden mb-5">
            <div class="card-pad border-b border-slate-100">
                <h2 class="text-sm font-extrabold text-slate-900">Pembagian Barang &amp; Berat per Koli</h2>
                <p class="hint">Barcode koli baru terbit untuk koli yang belum memiliki kode <span class="font-mono">KOLI-...</span>. Isi berat dalam kg.</p>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr><th class="w-24">Koli</th><th>Isi Koli (Ringkasan)</th><th class="text-right">Total Qty</th><th class="w-40">Berat Koli (kg)</th><th class="min-w-[14rem]">Barcode Saat Ini</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($packing->boxes->sortBy('box_number')->values() as $idx => $box)
                        <tr>
                            <td class="font-bold text-slate-800">Koli {{ $box->box_number }}</td>
                            <td>
                                <ul class="space-y-0.5">
                                    @foreach ($box->items as $bi)
                                        <li class="text-xs text-slate-600">{{ $bi->item?->name }} — <b>{{ number_format($bi->qty) }} {{ $bi->uom?->code }}</b></li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="text-right font-bold">{{ number_format($box->items->sum('qty')) }}</td>
                            <td>
                                <input class="input text-right" type="number" step="0.001" min="0"
                                       name="weights[{{ $idx }}]" value="{{ old("weights.$idx", $box->weight_kg) }}" placeholder="kg">
                            </td>
                            <td>
                                @if (str_starts_with((string) $box->box_barcode, 'KOLI-'))
                                    <span class="font-mono text-xs font-bold text-emerald-700">{{ $box->box_barcode }}</span>
                                    <span class="ml-1 text-[11px] font-bold text-slate-400">sudah terbit</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">Akan digenerate: KOLI-{{ now()->format('Ymd') }}-XXXX</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-pad flex flex-wrap items-center justify-between gap-3 border-t border-slate-100">
                <p class="hint">Barcode koli menjadi identitas resmi kemasan pengiriman dan dipakai driver saat scan muat / tiba.</p>
                <div class="flex gap-2">
                    <a href="{{ route('koli.index') }}" class="btn btn-ghost">Batal</a>
                    <button class="btn btn-primary" type="submit"><x-icon name="barcode" :size="16" /> Generate Barcode Koli</button>
                </div>
            </div>
        </div>
    </form>
@endif
@endsection
