@extends('layouts.app')

@section('title', 'Portal Sopir')
@section('subtitle', 'Pilih surat jalan Anda — scan muat & scan tiba dari HP.')

@section('content')
<div class="mb-4 max-w-md">
    <form method="GET" action="{{ route('driver.index') }}" class="flex gap-2">
        <input class="input flex-1" name="q" value="{{ request('q') }}" placeholder="No. SJ / sopir / plat nomor">
        <button class="btn btn-ghost">Cari</button>
    </form>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($deliveries as $d)
        @php
            $stepIndex = collect($steps)->search(fn ($s) => in_array($d->status, $s['statuses'], true));
            $stepIndex = $stepIndex === false ? 0 : $stepIndex;
        @endphp
        <a href="{{ route('driver.show', $d) }}" class="card card-pad block transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-mono text-sm font-extrabold text-indigo-700">{{ $d->delivery_no }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $d->driver_name ?: '-' }} · {{ $d->vehicle?->plate_number ?? '-' }}</p>
                </div>
                <x-badge status="{{ $d->status }}" label="{{ \App\Http\Controllers\DeliveryController::STATUSES[$d->status] ?? $d->status }}" />
            </div>

            <p class="mt-2 line-clamp-2 text-xs text-slate-500">
                {{ $d->destination_address ?: ($d->packingList?->customer?->name ?? 'Tanpa alamat') }}
            </p>

            <div class="mt-3 flex items-center gap-1">
                @foreach ($steps as $i => $step)
                    <span class="h-1.5 flex-1 rounded-full {{ $i <= $stepIndex ? 'bg-indigo-600' : 'bg-slate-200' }}"></span>
                @endforeach
            </div>
            <p class="mt-1.5 text-[11px] font-bold text-slate-500">{{ $steps[$stepIndex]['label'] }}</p>
        </a>
    @empty
        <div class="card card-pad text-center text-slate-400 sm:col-span-2 lg:col-span-3">
            Belum ada surat jalan aktif untuk cabang ini.
        </div>
    @endforelse
</div>
@endsection
