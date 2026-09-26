@extends('layouts.app')

@section('title', isset($branch) ? 'Edit Cabang' : 'Cabang Baru')

@section('content')
<form method="POST" action="{{ isset($branch) ? route('branches.update', $branch) : route('branches.store') }}" class="max-w-2xl">
    @csrf
    @if (isset($branch)) @method('PUT') @endif

    <div class="card card-pad space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label required">Kode Cabang</label>
                <input class="input" name="code" value="{{ old('code', $branch?->code) }}" placeholder="SBY-01" required>
            </div>
            <div>
                <label class="label required">Nama Cabang</label>
                <input class="input" name="name" value="{{ old('name', $branch?->name) }}" placeholder="Cabang Surabaya (Pusat)" required>
            </div>
            <div class="sm:col-span-2">
                <label class="label">Alamat</label>
                <textarea class="input" name="address" rows="3">{{ old('address', $branch?->address) }}</textarea>
            </div>
            <div>
                <label class="label">Telepon</label>
                <input class="input" name="phone" value="{{ old('phone', $branch?->phone) }}" placeholder="031-8412345">
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="is_central" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                           @checked(old('is_central', $branch?->is_central))> Tandai sebagai cabang pusat
                </label>
            </div>
        </div>

        <div class="flex gap-2 border-t border-slate-100 pt-4">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan</button>
            <a href="{{ route('branches.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </div>
</form>
@endsection
