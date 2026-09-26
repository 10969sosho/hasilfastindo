@extends('layouts.app')

@section('title', ($tab === 'suppliers' ? 'Supplier' : 'Customer') . (isset($partner) ? ' — Edit' : ' Baru'))

@section('content')
<form method="POST" action="{{ isset($partner) ? route('partners.update', [$tab, $partner->id]) : route('partners.store') }}" class="max-w-2xl">
    @csrf
    @if (isset($partner)) @method('PUT') @endif
    <input type="hidden" name="type" value="{{ $tab }}">

    <div class="card card-pad space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label required">Kode</label>
                <input class="input font-mono" name="code" value="{{ old('code', $partner?->code) }}" placeholder="{{ $tab === 'suppliers' ? 'SUP-001' : 'CUST-001' }}" required>
            </div>
            <div>
                <label class="label required">Nama</label>
                <input class="input" name="name" value="{{ old('name', $partner?->name) }}" placeholder="PT. ..." required>
            </div>
            <div>
                <label class="label">Telepon</label>
                <input class="input" name="phone" value="{{ old('phone', $partner?->phone) }}">
            </div>
            <div>
                <label class="label">Email</label>
                <input class="input" type="email" name="email" value="{{ old('email', $partner?->email) }}">
            </div>
            @if ($tab === 'customers')
                <div>
                    <label class="label">Kota</label>
                    <input class="input" name="city" value="{{ old('city', $partner?->city) }}" placeholder="Surabaya">
                </div>
            @endif
            <div class="sm:col-span-2">
                <label class="label">Alamat</label>
                <textarea class="input" name="address" rows="3">{{ old('address', $partner?->address) }}</textarea>
            </div>
        </div>

        <div class="flex gap-2 border-t border-slate-100 pt-4">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan</button>
            <a href="{{ route('partners.index', ['tab' => $tab]) }}" class="btn btn-ghost">Batal</a>
        </div>
    </div>
</form>
@endsection
