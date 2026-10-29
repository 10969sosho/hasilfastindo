@extends('layouts.app')

@section('title', 'NAV Table Explorer')
@section('subtitle', 'Pilih table NAV mana saja untuk melihat langsung isi datanya secara real-time')

@section('content')
<div class="card p-4 mb-4">
    <form method="GET" action="{{ route('nav-explorer.index') }}" class="flex flex-wrap items-center gap-3">
        <div class="w-80">
            <label class="block text-xs font-bold text-slate-500 mb-1">Pilih Table / Service NAV (Total {{ count($tables) }} Tables):</label>
            <select class="input searchable" name="table" onchange="this.form.submit()">
                @foreach ($tables as $t)
                    <option value="{{ $t }}" @selected($selectedTable === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="pt-5">
            <button class="btn btn-primary btn-sm">Tampilkan Data</button>
        </div>
    </form>
</div>

@if ($errorMessage)
    <div class="card p-4 mb-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl">
        <p class="font-bold text-sm">❌ Table Terkunci / Tidak Bisa Diakses di NAV</p>
        <p class="text-xs mt-1 font-mono break-all">{{ $errorMessage }}</p>
    </div>
@else
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-800">Isi Data Table: <span class="font-mono text-indigo-700">{{ $selectedTable }}</span></h3>
            <span class="text-xs text-slate-500">Menampilkan {{ count($tableData) }} baris teratas</span>
        </div>
        <div class="table-scroll">
            <table class="table text-xs">
                <thead>
                    <tr>
                        @forelse ($columns as $col)
                            <th class="whitespace-nowrap">{{ $col }}</th>
                        @empty
                            <th>Status</th>
                        @endforelse
                    </tr>
                </thead>
                <tbody>
                @forelse ($tableData as $row)
                    @php $rowArray = (array) $row; @endphp
                    <tr class="hover:bg-slate-50">
                        @foreach ($columns as $col)
                            @php $val = $rowArray[$col] ?? ''; @endphp
                            <td class="whitespace-nowrap font-mono text-[11px]">
                                {{ is_array($val) ? json_encode($val) : $val }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(count($columns), 1) }}" class="py-8 text-center text-slate-400">
                            Table ini kosong / belum memiliki record data.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
