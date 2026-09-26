@extends('layouts.app')

@section('title', isset($location) ? 'Edit BIN' : 'BIN Baru')

@section('content')
<form method="POST" action="{{ isset($location) ? route('bins.update', $location) : route('bins.store') }}" class="max-w-2xl">
    @csrf
    @if (isset($location)) @method('PUT') @endif

    <div class="card card-pad space-y-4">
        <p class="hint">Format kode BIN otomatis: <span class="font-mono font-bold">BIN-{AREA}-{RAK}-{BIN}</span> (kosongkan kode untuk generate otomatis).</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label required">Gudang</label>
                <select class="input" name="warehouse_id" id="warehouse_id" required>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('warehouse_id', $defaultWarehouse) == $w->id)>{{ $w->code }} — {{ $w->name }} ({{ $w->branch?->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Kode BIN (opsional)</label>
                <input class="input font-mono" name="code" value="{{ old('code', $location?->code) }}" placeholder="BIN-A-01">
            </div>
            <div>
                <label class="label">Status</label>
                <select class="input" name="is_active">
                    <option value="1" @selected(old('is_active', $location?->is_active ?? true))>Aktif</option>
                    <option value="0" @selected(old('is_active', $location?->is_active ?? true) === false || old('is_active') === '0')>Non-aktif</option>
                </select>
            </div>
            <div>
                <label class="label">Area / Zone</label>
                <input class="input" name="area" value="{{ old('area', $location?->area) }}" placeholder="Zone A">
            </div>
            <div>
                <label class="label">Rak</label>
                <input class="input" name="rack" value="{{ old('rack', $location?->rack) }}" placeholder="Rack A">
            </div>
            <div>
                <label class="label">Shelf</label>
                <input class="input" name="shelf" value="{{ old('shelf', $location?->shelf) }}" placeholder="Shelf 1">
            </div>
            <div>
                <label class="label">Bin</label>
                <input class="input" name="bin" value="{{ old('bin', $location?->bin) }}" placeholder="01">
            </div>
        </div>

        <div class="flex gap-2 border-t border-slate-100 pt-4">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan</button>
            <a href="{{ route('bins.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </div>
</form>
@endsection
