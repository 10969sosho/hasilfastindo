@extends('layouts.app')

@section('title', isset($vehicle) ? 'Edit Kendaraan' : 'Kendaraan Baru')

@section('content')
<form method="POST" action="{{ isset($vehicle) ? route('fleet.vehicles.update', $vehicle) : route('fleet.vehicles.store') }}" class="max-w-2xl">
    @csrf
    @if (isset($vehicle)) @method('PUT') @endif

    <div class="card card-pad space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label required">Plat Nomor</label>
                <input class="input font-mono" name="plate_number" value="{{ old('plate_number', $vehicle?->plate_number) }}" placeholder="L 9812 HF" required>
            </div>
            <div>
                <label class="label required">Tipe Kendaraan</label>
                <input class="input" name="type" value="{{ old('type', $vehicle?->type) }}" placeholder="Truk Box / Blind Van" required>
            </div>
            <div class="sm:col-span-2">
                <label class="label required">Nama / Model</label>
                <input class="input" name="vehicle_name" value="{{ old('vehicle_name', $vehicle?->vehicle_name) }}" placeholder="Truk Isuzu Elf Box 4 Ban" required>
            </div>
            <div>
                <label class="label">Kapasitas (kg)</label>
                <input class="input" type="number" step="0.01" min="0" name="capacity_kg" value="{{ old('capacity_kg', $vehicle?->capacity_kg) }}">
            </div>
            <div>
                <label class="label required">Status</label>
                <select class="input" name="status">
                    <option value="available" @selected(old('status', $vehicle?->status) === 'available')>Tersedia</option>
                    <option value="in_use" @selected(old('status', $vehicle?->status) === 'in_use')>Sedang Bertugas</option>
                    <option value="maintenance" @selected(old('status', $vehicle?->status) === 'maintenance')>Perawatan</option>
                </select>
            </div>
            <div>
                <label class="label">Sopir</label>
                <select class="input" name="driver_id">
                    <option value="">- Tanpa sopir -</option>
                    @foreach ($drivers as $d)
                        @if ($d->role === 'driver')
                            <option value="{{ $d->id }}" @selected(old('driver_id', $vehicle?->driver_id) == $d->id)>{{ $d->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Helper</label>
                <select class="input" name="helper_id">
                    <option value="">- Tanpa helper -</option>
                    @foreach ($drivers as $d)
                        @if ($d->role === 'helper')
                            <option value="{{ $d->id }}" @selected(old('helper_id', $vehicle?->helper_id) == $d->id)>{{ $d->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex gap-2 border-t border-slate-100 pt-4">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan</button>
            <a href="{{ route('fleet.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </div>
</form>
@endsection
