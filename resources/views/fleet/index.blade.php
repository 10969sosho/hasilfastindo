@extends('layouts.app')

@section('title', 'Armada & Sopir')
@section('subtitle', 'Kendaraan pengiriman, sopir dan helper')

@section('content')
@php $tab = request('tab', 'vehicles') === 'drivers' ? 'drivers' : 'vehicles'; @endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex rounded-lg border border-slate-200 bg-white p-1">
        <a href="{{ route('fleet.index', ['tab' => 'vehicles']) }}" class="rounded-md px-4 py-2 text-sm font-bold {{ $tab === 'vehicles' ? 'bg-indigo-600 text-white' : 'text-slate-500' }}">Kendaraan</a>
        <a href="{{ route('fleet.index', ['tab' => 'drivers']) }}" class="rounded-md px-4 py-2 text-sm font-bold {{ $tab === 'drivers' ? 'bg-indigo-600 text-white' : 'text-slate-500' }}">Sopir & Helper</a>
    </div>

    @if ($tab === 'vehicles')
        <a href="{{ route('fleet.vehicles.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Kendaraan Baru</a>
    @else
        <a href="{{ route('fleet.drivers.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Sopir / Helper Baru</a>
    @endif
</div>

@if ($tab === 'vehicles')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($vehicles as $vehicle)
            @php $load = $activeLoads[$vehicle->id] ?? null; @endphp
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-white"><x-icon name="truck" :size="20" /></div>
                        <div>
                            <p class="font-mono text-sm font-extrabold text-slate-900">{{ $vehicle->plate_number }}</p>
                            <p class="text-xs text-slate-500">{{ $vehicle->vehicle_name }}</p>
                        </div>
                    </div>
                    <x-badge status="{{ $vehicle->status === 'available' ? 'ready' : ($vehicle->status === 'in_use' ? 'in_delivery' : 'problem') }}"
                             label="{{ ucwords(str_replace('_', ' ', $vehicle->status)) }}" />
                </div>

                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Tipe</dt><dd class="font-semibold text-slate-700">{{ $vehicle->type }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Kapasitas</dt><dd class="font-semibold text-slate-700">{{ $vehicle->capacity_kg ? number_format($vehicle->capacity_kg).' kg' : '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Sopir</dt><dd class="font-semibold text-slate-700">{{ $vehicle->driver_name ?: '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Helper</dt><dd class="font-semibold text-slate-700">{{ $vehicle->helper_name ?: '-' }}</dd></div>
                </dl>

                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700">
                    Pengiriman berjalan: {{ $load ? $load->items_count.' dus/barang' : '0' }}
                </div>

                <div class="mt-4 flex gap-2">
                    <a href="{{ route('fleet.vehicles.edit', $vehicle) }}" class="btn btn-primary btn-sm flex-1"><x-icon name="edit" :size="14" /> Edit</a>
                    <form method="POST" action="{{ route('fleet.vehicles.destroy', $vehicle) }}" onsubmit="return wmsConfirm('Hapus kendaraan {{ $vehicle->plate_number }}?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm"><x-icon name="trash" :size="14" /></button>
                    </form>
                </div>
            </div>
        @empty
            <div class="card card-pad text-center text-slate-400">Belum ada kendaraan.</div>
        @endforelse
    </div>
@else
    <div class="card overflow-hidden">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr><th>Nama</th><th>Peran</th><th>Telepon</th><th>No. SIM</th><th>Status</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                @forelse ($drivers as $driver)
                    <tr>
                        <td class="font-bold text-slate-800">{{ $driver->name }}</td>
                        <td><x-badge status="{{ $driver->role === 'driver' ? 'indigo' : 'slate' }}" label="{{ ucfirst($driver->role) }}" /></td>
                        <td class="text-slate-600">{{ $driver->phone ?: '-' }}</td>
                        <td class="font-mono text-xs text-slate-600">{{ $driver->license_number ?: '-' }}</td>
                        <td><x-badge status="{{ $driver->is_active ? 'ready' : 'cancelled' }}" label="{{ $driver->is_active ? 'Aktif' : 'Non-aktif' }}" /></td>
                        <td>
                            <div class="flex justify-end gap-1.5">
                                <a href="{{ route('fleet.drivers.edit', $driver) }}" class="btn btn-primary btn-sm"><x-icon name="edit" :size="14" /> Edit</a>
                                <form method="POST" action="{{ route('fleet.drivers.destroy', $driver) }}" onsubmit="return wmsConfirm('Hapus data {{ $driver->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm"><x-icon name="trash" :size="14" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-400">Belum ada sopir/helper.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
