@extends('layouts.app')

@section('title', 'Daftar Pick NAV')

@section('content')
<div class="card p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('nav-picks.index') }}" class="flex flex-wrap items-center gap-2.5">
        <div class="w-72">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Cari No. Pick, No. SO, Shipment, User...">
        </div>
        <div class="w-48">
            <select class="input searchable" name="status" placeholder="Semua Status">
                <option value="">Semua Status</option>
                @php
                    $statusLabels = [
                        'open' => 'Menunggu Diproses',
                        'in_progress' => 'Sedang Picking',
                        'completed' => 'Selesai Pick',
                    ];
                @endphp
                @foreach ($statusLabels as $value => $text)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-ghost">Filter</button>
    </form>

    <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('nav-picks.sync') }}">
            @csrf
            <button class="btn btn-ghost" title="Tarik Warehouse Pick terbaru dari Microsoft Dynamics NAV">
                <x-icon name="sync" :size="15" /> Sinkronisasi Pick dari NAV
            </button>
        </form>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Nomor Pick</th>
                    <th>Nomor SO &amp; Ref</th>
                    <th>Tanggal Assignment</th>
                    <th>Lokasi &amp; Assigned User</th>
                    <th>Item &amp; Total Qty</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($picks as $pick)
                <tr class="hover:bg-slate-50/70">
                    <td>
                        <a href="{{ route('nav-picks.show', $pick) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">
                            {{ $pick->pick_no }}
                        </a>
                        <p class="text-[11px] text-slate-400">Type: {{ $pick->type ?: 'Pick' }}</p>
                    </td>
                    <td>
                        <span class="font-mono text-xs font-bold text-slate-800">{{ $pick->source_so_no ?: '-' }}</span>
                        @if ($pick->whse_shipment_no)
                            <p class="font-mono text-[11px] text-slate-400">Ship: {{ $pick->whse_shipment_no }}</p>
                        @endif
                        @if ($pick->destination_no)
                            <p class="text-[11px] text-slate-500 truncate max-w-[14rem]">Cust: {{ $pick->destination_no }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-slate-600">
                        {{ $pick->assignment_date?->format('d/m/Y') ?: '-' }}
                    </td>
                    <td>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-800">
                            {{ $pick->location_code ?: '-' }}
                        </span>
                        <p class="text-[11px] text-slate-500 mt-0.5">User: <span class="font-medium text-slate-700">{{ $pick->assigned_user_id ?: 'Belum diassign' }}</span></p>
                    </td>
                    <td>
                        @php
                            $takeLines = $pick->takeItems;
                            $firstLine = $takeLines->first();
                            $extraCount = $takeLines->count() - 1;
                        @endphp
                        <p class="font-bold text-slate-700">
                            {{ $takeLines->count() }} item · {{ number_format($pick->totalTakeQty()) }} PCS
                        </p>
                        @if ($firstLine)
                            <p class="max-w-[20rem] truncate text-[11px] text-slate-400">
                                {{ $firstLine->sku }} ({{ $firstLine->item_name }})
                                @if ($extraCount > 0)
                                    <span class="text-indigo-600 font-semibold">+{{ $extraCount }} lainnya</span>
                                @endif
                            </p>
                        @endif
                    </td>
                    <td>
                        <x-badge :status="$pick->status" :label="$statusLabels[$pick->status] ?? null" />
                    </td>
                    <td>
                        <div class="flex justify-end gap-1.5">
                            <a href="{{ route('nav-picks.show', $pick) }}" class="btn btn-ghost btn-sm" title="Lihat detail pick & bin location">
                                <x-icon name="eye" :size="14" />
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-8 text-center text-slate-400">
                        Belum ada dokumen Warehouse Pick dari NAV. Klik "Sinkronisasi Pick dari NAV" untuk menarik data.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-slate-100">{{ $picks->links() }}</div>
</div>
@endsection
