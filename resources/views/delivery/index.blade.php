@extends('layouts.app')

@section('title', 'Pengiriman / Surat Jalan')
@section('subtitle', 'Scan out dus ke armada & pelacakan status pengiriman')

@section('content')
<div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-5">
    @foreach ($stats as $key => $s)
        <div class="kpi">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $s['label'] }}</p>
            <p class="mt-1 text-2xl font-extrabold {{ $key === 'problem' ? 'text-rose-600' : ($key === 'received' ? 'text-emerald-600' : 'text-slate-800') }}">{{ $s['count'] }}</p>
        </div>
    @endforeach
</div>

<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('delivery.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[15rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="No. SJ / plat nomor">
        </div>
        <div class="min-w-[13rem]">
            <label class="label">Status</label>
            <select class="input" name="status">
                <option value="">Semua</option>
                @foreach (\App\Http\Controllers\DeliveryController::STATUSES as $k => $l)
                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>
    <a href="{{ route('delivery.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Surat Jalan Baru</a>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>No. SJ</th><th>SO</th><th>Armada</th><th>Sopir</th><th>Metode</th><th>Live Track</th><th>Status</th><th>Waktu</th><th></th></tr></thead>
            <tbody>
            @forelse ($deliveries as $d)
                @php
                    $steps = \App\Http\Controllers\DriverDeliveryController::STEPS;
                    $stepIndex = collect($steps)->search(fn ($s) => in_array($d->status, $s['statuses'], true));
                    $stepIndex = $stepIndex === false ? 0 : $stepIndex;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('delivery.show', $d) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $d->delivery_no }}</a>
                        <p class="text-[11px] text-slate-400">{{ $d->packingLists->pluck('no_packing')->join(', ') ?: '-' }}</p>
                    </td>
                    <td class="font-mono text-xs text-slate-700">{{ $d->salesOrder?->so_number }}</td>
                    <td class="text-slate-700">{{ $d->vehicle?->plate_number ?? '-' }}</td>
                    <td class="text-slate-700">{{ $d->driver_name ?? '-' }}</td>
                    <td class="text-slate-600">{{ $d->method === 'pickup_sendiri' ? 'Pickup Sendiri' : 'Armada' }}</td>
                    <td>
                        <div class="flex items-center gap-1" title="{{ $steps[$stepIndex]['label'] }}">
                            @foreach ($steps as $i => $step)
                                <span class="h-1.5 w-8 rounded-full {{ $i <= $stepIndex ? 'bg-indigo-600' : 'bg-slate-200' }}"></span>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[10px] font-bold text-slate-500">{{ $steps[$stepIndex]['label'] }}</p>
                    </td>
                    <td><x-badge status="{{ $d->status }}" label="{{ \App\Http\Controllers\DeliveryController::STATUSES[$d->status] ?? $d->status }}" /></td>
                    <td class="text-[11px] text-slate-500">
                        {{ $d->departure_time?->format('d/m H:i') ?? '-' }}
                        @if ($d->arrival_time) → {{ $d->arrival_time->format('d/m H:i') }}@endif
                    </td>
                    <td class="text-right"><a href="{{ route('delivery.show', $d) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-8 text-center text-slate-400">Belum ada surat jalan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $deliveries->links() }}</div>
</div>
@endsection
