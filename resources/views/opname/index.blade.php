@extends('layouts.app')

@section('title', 'Stok Opname')
@section('subtitle', 'Sesi opname per BIN dengan approval rekonsiliasi stok')

@section('content')
<div class="grid gap-4 sm:grid-cols-3 mb-5">
    <div class="card card-pad"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Sesi Berjalan</p><p class="mt-1 text-3xl font-extrabold text-amber-600">{{ number_format($summary['open']) }}</p></div>
    <div class="card card-pad"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Selesai</p><p class="mt-1 text-3xl font-extrabold text-emerald-600">{{ number_format($summary['completed']) }}</p></div>
    <div class="card card-pad flex items-end justify-between">
        <p class="hint pb-1">Approval menyesuaikan stok sistem<br>sesuai hitungan fisik.</p>
        <a href="{{ route('opname.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> Sesi Baru</a>
    </div>
</div>

<form method="GET" action="{{ route('opname.index') }}" class="card card-pad mb-5 flex flex-wrap items-end gap-3">
    <div class="min-w-[13rem]">
        <label class="label">Status</label>
        <select class="input" name="status">
            <option value="">Semua</option>
            @foreach (['open', 'in_progress', 'completed', 'cancelled'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-ghost">Filter</button>
</form>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>No. Opname</th><th>Tanggal</th><th>Cabang</th><th>Gudang</th><th>BIN</th><th>Judul</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($sessions as $s)
                <tr>
                    <td>
                        <a href="{{ route('opname.show', $s) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $s->opname_no }}</a>
                        <p class="text-[11px] text-slate-400">oleh {{ $s->creator?->name }}</p>
                    </td>
                    <td class="text-slate-600">{{ \Carbon\Carbon::parse($s->opname_date)->format('d/m/Y') }}</td>
                    <td class="text-slate-700">{{ $s->branch?->code }}</td>
                    <td class="text-slate-600">{{ $s->warehouse?->code }}</td>
                    <td class="font-mono text-xs">{{ $s->location?->code }}</td>
                    <td class="text-slate-700">{{ $s->title ?: '-' }}</td>
                    <td><x-badge status="{{ $s->status === 'open' ? 'open' : $s->status }}" label="{{ ucfirst(str_replace('_', ' ', $s->status)) }}" /></td>
                    <td class="text-right"><a href="{{ route('opname.show', $s) }}" class="btn btn-ghost btn-sm">Buka</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-400">Belum ada sesi opname.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-pad border-t border-slate-100">{{ $sessions->links() }}</div>
</div>
@endsection
