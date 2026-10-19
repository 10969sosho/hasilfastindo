@extends('layouts.app')

@section('title', 'Portal Sopir · '.$delivery->delivery_no)
@section('subtitle', ($delivery->vehicle?->plate_number ?? '-').' · '.($delivery->driver_name ?: '-'))

@section('content')
@php
    $stepIndex = collect($steps)->search(fn ($s) => in_array($delivery->status, $s['statuses'], true));
    $stepIndex = $stepIndex === false ? 0 : $stepIndex;
@endphp

<div class="mb-4 flex items-center justify-between gap-3">
    <div>
        <p class="font-mono text-lg font-extrabold text-indigo-700">{{ $delivery->delivery_no }}</p>
        <p class="text-xs text-slate-500">{{ $delivery->destination_address ?: '-' }}</p>
    </div>
    <a href="{{ route('driver.index') }}" class="btn btn-ghost">Kembali</a>
</div>

<div class="card card-pad mb-5">
    <div class="flex items-center gap-1">
        @foreach ($steps as $i => $step)
            <div class="flex-1">
                <div class="h-2 rounded-full {{ $i <= $stepIndex ? 'bg-indigo-600' : 'bg-slate-200' }}"></div>
                <p class="mt-1.5 text-center text-[11px] font-bold {{ $i <= $stepIndex ? 'text-indigo-700' : 'text-slate-400' }}">{{ $step['label'] }}</p>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-center text-xs text-slate-500">
        Koli termuat: <b class="text-slate-800">{{ $scanned }} / {{ $total }}</b>
        @if ($delivery->departure_time) · Berangkat {{ $delivery->departure_time->format('d/m H:i') }}@endif
        @if ($delivery->arrival_time) · Tiba {{ $delivery->arrival_time->format('d/m H:i') }}@endif
    </p>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div class="card card-pad">
        <h2 class="text-sm font-extrabold text-slate-900"><x-icon name="inbound" :size="16" /> Aksi 1 — Scan Muat</h2>
        <p class="hint mb-3">Pindai barcode koli saat barang dinaikkan ke armada. Setelah semua koli termuat, status otomatis <b>Sedang Diangkut</b>.</p>
        <input class="input font-mono" id="loadBarcode" placeholder="HF-BOX-202610-0001" autocomplete="off">
        <button type="button" class="btn btn-primary mt-3 w-full justify-center" id="loadBtn"><x-icon name="scan" :size="16" /> Scan Muat</button>
        <div id="loadResult" class="mt-3"></div>
    </div>

    <div class="card card-pad">
        <h2 class="text-sm font-extrabold text-slate-900"><x-icon name="outbound" :size="16" /> Aksi 2 — Scan Tiba</h2>
        <p class="hint mb-3">Pindai barcode koli saat tiba di tujuan. Status berubah menjadi <b>Sampai di Tujuan</b>.</p>
        <input class="input font-mono" id="arriveBarcode" placeholder="HF-BOX-202610-0001" autocomplete="off">
        <button type="button" class="btn btn-success mt-3 w-full justify-center" id="arriveBtn"><x-icon name="check" :size="16" /> Scan Tiba</button>
        <div id="arriveResult" class="mt-3"></div>
    </div>
</div>

<div class="card card-pad mt-4">
    <h2 class="mb-2 text-sm font-extrabold text-slate-900">Daftar Muatan</h2>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>Koli / Barcode</th><th>Item</th><th class="text-right">Qty</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($delivery->items as $item)
                <tr>
                    <td class="font-mono text-xs">{{ $item->barcode }}{{ $item->master_box_no ? ' ('.$item->master_box_no.')' : '' }}</td>
                    <td class="text-slate-700">{{ $item->item?->name }}</td>
                    <td class="text-right font-semibold">{{ number_format($item->qty) }} {{ $item->uom?->code }}</td>
                    <td>
                        <x-badge status="{{ $item->status }}" label="{{ $item->status === 'scanned_out' ? 'Termuat' : 'Pending' }}" />
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    async function driverScan(action, url, inputId, resultId) {
        const input = document.getElementById(inputId);
        const result = document.getElementById(resultId);
        const code = input.value.trim();
        if (!code) return;

        result.innerHTML = '<p class="hint">Memeriksa...</p>';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ barcode: code }),
            });
            const data = await res.json();

            result.innerHTML = `<p class="rounded-lg px-3 py-2 text-sm font-bold ${res.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600'}">${data.message}</p>`;

            if (res.ok) {
                input.value = '';
                if (data.done || action === 'arrive') setTimeout(() => location.reload(), 900);
            }
        } catch (e) {
            result.innerHTML = '<p class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-bold text-rose-600">Gagal terhubung ke server.</p>';
        }
    }

    const loadUrl = @json(route('driver.load', $delivery));
    const arriveUrl = @json(route('driver.arrive', $delivery));

    document.getElementById('loadBtn').addEventListener('click', () => driverScan('load', loadUrl, 'loadBarcode', 'loadResult'));
    document.getElementById('arriveBtn').addEventListener('click', () => driverScan('arrive', arriveUrl, 'arriveBarcode', 'arriveResult'));

    ['loadBarcode', 'arriveBarcode'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            driverScan(id === 'loadBarcode' ? 'load' : 'arrive', id === 'loadBarcode' ? loadUrl : arriveUrl, id, id === 'loadBarcode' ? 'loadResult' : 'arriveResult');
        });
    });
</script>
@endpush
