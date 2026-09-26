@extends('layouts.app')

@section('title', 'Supplier & Customer')
@section('subtitle', 'Master partner bisnis')

@section('content')
<div class="card card-pad mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="GET" action="{{ route('partners.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="flex rounded-lg border border-slate-200 bg-white p-1">
            <button type="submit" name="tab" value="suppliers" class="rounded-md px-4 py-2 text-sm font-bold {{ $tab === 'suppliers' ? 'bg-indigo-600 text-white' : 'text-slate-500' }}">Supplier</button>
            <button type="submit" name="tab" value="customers" class="rounded-md px-4 py-2 text-sm font-bold {{ $tab === 'customers' ? 'bg-indigo-600 text-white' : 'text-slate-500' }}">Customer</button>
        </div>
        <div class="min-w-[14rem]">
            <label class="label">Cari</label>
            <input class="input" name="q" value="{{ $q }}" placeholder="Kode atau nama partner">
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <a href="{{ route('partners.create', ['type' => $tab]) }}" class="btn btn-primary">
        <x-icon name="plus" :size="16" /> {{ $tab === 'suppliers' ? 'Supplier' : 'Customer' }} Baru
    </a>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th><th>Nama</th><th>Telepon</th><th>Email</th><th>Alamat</th>
                    @if ($tab === 'customers')<th>Kota</th>@endif
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($partners as $partner)
                <tr>
                    <td class="font-mono text-xs font-bold text-indigo-700">{{ $partner->code }}</td>
                    <td class="font-bold text-slate-800">{{ $partner->name }}</td>
                    <td class="text-slate-600">{{ $partner->phone ?: '-' }}</td>
                    <td class="text-slate-600">{{ $partner->email ?: '-' }}</td>
                    <td class="max-w-[20rem] truncate text-slate-600">{{ $partner->address ?: '-' }}</td>
                    @if ($tab === 'customers')<td class="text-slate-600">{{ $partner->city ?: '-' }}</td>@endif
                    <td>
                        <div class="flex justify-end gap-1.5">
                            <a href="{{ route('partners.edit', [$tab, $partner->id]) }}" class="btn btn-primary btn-sm"><x-icon name="edit" :size="14" /> Edit</a>
                            <form method="POST" action="{{ route('partners.destroy', [$tab, $partner->id]) }}" onsubmit="return wmsConfirm('Hapus {{ $partner->name }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm"><x-icon name="trash" :size="14" /></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-slate-400">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $partners->links() }}</div>
</div>
@endsection
