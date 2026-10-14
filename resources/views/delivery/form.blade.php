@extends('layouts.app')

@section('title', 'Surat Jalan Baru')

@php
    $oldSelected = array_map('strval', (array) old('packing_list_ids', $selectedIds));
    $checkedBox = array_map('strval', (array) old('boxes', []));
@endphp

@section('content')
<form method="POST" action="{{ route('delivery.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf

    <div class="space-y-5 lg:col-span-2">
        <div class="card card-pad">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Packing List / SO (boleh lebih dari satu)</h2>
                    <p class="hint">Centang beberapa packing list untuk digabung dalam 1 Surat Jalan.</p>
                </div>
                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700" id="plCount">0 packing list dipilih</span>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($packingLists as $pl)
                    <label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" class="pl-check mt-0.5" name="packing_list_ids[]" value="{{ $pl->id }}"
                               data-pl="{{ $pl->id }}" @checked(in_array((string) $pl->id, $oldSelected, true))>
                        <span class="min-w-0">
                            <span class="font-mono text-xs font-bold text-indigo-700">{{ $pl->no_packing }}</span>
                            <span class="block truncate text-slate-700">{{ $pl->customer?->name ?? $pl->salesOrder?->customer?->name }}</span>
                            <span class="text-[11px] text-slate-400">{{ $pl->total_box }} dus · {{ $pl->salesOrder?->so_number ?: '-' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('packing_list_ids')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror

            <div class="mt-4 grid gap-3" id="boxGroups">
                @foreach ($packingLists as $pl)
                    @php $plBoxes = $boxes->where('packing_list_id', $pl->id); @endphp
                    @if ($plBoxes->isNotEmpty())
                        <div class="pl-boxes" data-pl="{{ $pl->id }}">
                            <p class="label">Dus — {{ $pl->no_packing }}</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($plBoxes as $box)
                                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <input type="checkbox" name="boxes[]" value="{{ $box->id }}"
                                               @checked($checkedBox ? in_array((string) $box->id, $checkedBox, true) : true)>
                                        <span class="font-mono text-xs font-bold text-indigo-700">{{ $box->box_barcode }}</span>
                                        <span class="text-slate-500">Box {{ $box->box_number }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
                <p class="hint">Kosongkan centang dus = muat seluruh dus dari packing list terpilih.</p>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Pengiriman</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label required">Metode</label>
                    <select class="input" name="method" id="method" onchange="toggleMethod()" required>
                        <option value="armada">Armada Perusahaan</option>
                        <option value="pickup_sendiri">Pickup Sendiri (customer)</option>
                    </select>
                </div>
                <div>
                    <label class="label">Ekspedisi</label>
                    <input class="input" name="ekspedisi" value="{{ old('ekspedisi') }}" placeholder="JNE / Truk Sendiri / Cargo">
                </div>
                <div id="vehicleFields">
                    <label class="label">Truk / Kendaraan</label>
                    <select class="input" name="vehicle_id" id="vehicle_id">
                        <option value="">- Pilih armada -</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}" @selected(old('vehicle_id') == $v->id)>{{ $v->plate_number }} — {{ $v->vehicle_name }} ({{ $v->status }})</option>
                        @endforeach
                    </select>
                </div>
                <div id="driverFields">
                    <label class="label">Sopir</label>
                    <select class="input" name="driver_id">
                        <option value="">- Pilih sopir -</option>
                        @foreach ($drivers as $d)<option value="{{ $d->id }}" @selected(old('driver_id') == $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div id="helperFields">
                    <label class="label">Helper</label>
                    <select class="input" name="helper_id">
                        <option value="">- Opsional -</option>
                        @foreach ($helpers as $d)<option value="{{ $d->id }}" @selected(old('helper_id') == $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Alamat Tujuan</label>
                    <textarea class="input" name="destination_address" rows="2">{{ old('destination_address', $defaultAddress) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Catatan</label>
                    <textarea class="input" name="notes" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Simpan</h2>
            <div class="flex flex-col gap-2">
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Buat Surat Jalan</button>
                <a href="{{ route('delivery.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </div>
        <div class="card card-pad bg-slate-50">
            <p class="text-xs font-bold text-slate-600">Alur</p>
            <ol class="hint mt-2 list-inside list-decimal space-y-1">
                <li>Buat SJ (bisa gabung beberapa PL/SO)</li>
                <li>Scan out tiap dus / master box ke armada</li>
                <li>Update status: berangkat → tiba → diterima</li>
            </ol>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function toggleMethod() {
        const pickup = document.getElementById('method').value === 'pickup_sendiri';
        ['vehicleFields', 'driverFields', 'helperFields'].forEach(id => {
            document.getElementById(id).style.display = pickup ? 'none' : '';
        });
    }

    function syncPl() {
        const checked = [...document.querySelectorAll('.pl-check:checked')].map(el => el.value);
        document.getElementById('plCount').textContent = checked.length + ' packing list dipilih';

        document.querySelectorAll('.pl-boxes').forEach(group => {
            group.style.display = checked.length === 0 || checked.includes(group.dataset.pl) ? '' : 'none';
        });
    }

    document.querySelectorAll('.pl-check').forEach(el => el.addEventListener('change', syncPl));
    toggleMethod();
    syncPl();
</script>
@endpush
