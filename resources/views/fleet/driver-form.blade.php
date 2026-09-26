@extends('layouts.app')

@section('title', isset($driver) ? 'Edit Sopir / Helper' : 'Sopir / Helper Baru')

@section('content')
<form method="POST" action="{{ isset($driver) ? route('fleet.drivers.update', $driver) : route('fleet.drivers.store') }}" class="max-w-xl">
    @csrf
    @if (isset($driver)) @method('PUT') @endif

    <div class="card card-pad space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label required">Nama Lengkap</label>
                <input class="input" name="name" value="{{ old('name', $driver?->name) }}" placeholder="Bambang Supriyanto" required>
            </div>
            <div>
                <label class="label">Telepon</label>
                <input class="input" name="phone" value="{{ old('phone', $driver?->phone) }}" placeholder="081399887766">
            </div>
            <div>
                <label class="label">No. SIM</label>
                <input class="input font-mono" name="license_number" value="{{ old('license_number', $driver?->license_number) }}" placeholder="B-129849281">
            </div>
            <div>
                <label class="label required">Peran</label>
                <select class="input" name="role">
                    <option value="driver" @selected(old('role', $driver?->role ?? 'driver') === 'driver')>Sopir (Driver)</option>
                    <option value="helper" @selected(old('role', $driver?->role) === 'helper')>Helper</option>
                </select>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                           @checked(old('is_active', $driver?->is_active ?? true))> Aktif
                </label>
            </div>
        </div>

        <div class="flex gap-2 border-t border-slate-100 pt-4">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan</button>
            <a href="{{ route('fleet.index', ['tab' => 'drivers']) }}" class="btn btn-ghost">Batal</a>
        </div>
    </div>
</form>
@endsection
