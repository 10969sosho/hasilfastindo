@extends('layouts.app')

@section('title', isset($warehouse) ? 'Edit Gudang' : 'Gudang Baru')

@section('content')
<form method="POST" action="{{ isset($warehouse) ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" class="max-w-2xl">
    @csrf
    @if (isset($warehouse)) @method('PUT') @endif

    <div class="card card-pad space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label required">Cabang</label>
                <select class="input" name="branch_id" required>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected(old('branch_id', $warehouse?->branch_id) == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label required">Kode Gudang</label>
                <input class="input" name="code" value="{{ old('code', $warehouse?->code) }}" placeholder="WH-SBY-M" required>
            </div>
            <div class="sm:col-span-2">
                <label class="label required">Nama Gudang</label>
                <input class="input" name="name" value="{{ old('name', $warehouse?->name) }}" placeholder="Gudang Utama Surabaya" required>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="is_default" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                           @checked(old('is_default', $warehouse?->is_default))> Jadikan gudang utama cabang ini
                </label>
            </div>
        </div>

        <div class="flex gap-2 border-t border-slate-100 pt-4">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan</button>
            <a href="{{ route('warehouses.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </div>
</form>
@endsection
