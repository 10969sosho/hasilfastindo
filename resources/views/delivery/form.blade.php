@extends('layouts.app')

@section('title', 'Surat Jalan Baru')

@section('content')
<form method="POST" action="{{ route('delivery.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf

    <div class="space-y-5 lg:col-span-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Muatan</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label required">Packing List</label>
                    <select class="input" name="packing_list_id" onchange="location.href='{{ route('delivery.create') }}?packing_list_id='+this.value" required>
                        <option value="">- Pilih packing list -</option>
                        @foreach ($packingLists as $pl)
                            <option value="{{ $pl->id }}" @selected($packingList?->id == $pl->id)>
                                {{ $pl->no_packing }} — {{ $pl->customer?->name ?? $pl->salesOrder?->customer?->name }} ({{ $pl->total_box }} dus)
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($packingList)
                    <div class="sm:col-span-2">
                        <label class="label required">Dus yang Dimuat</label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($boxes as $box)
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input type="checkbox" name="boxes[]" value="{{ $box->id }}" checked>
                                    <span class="font-mono text-xs font-bold text-indigo-700">{{ $box->box_barcode }}</span>
                                    <span class="text-slate-500">Box {{ $box->box_number }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="hint">Kosongkan semua = muat seluruh dus.</p>
                    </div>
                @endif
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
                <div id="vehicleFields">
                    <label class="label">Kendaraan</label>
                    <select class="input" name="vehicle_id" id="vehicle_id">
                        <option value="">- Pilih armada -</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->plate_number }} — {{ $v->vehicle_name }} ({{ $v->status }})</option>
                        @endforeach
                    </select>
                </div>
                <div id="driverFields">
                    <label class="label">Sopir</label>
                    <select class="input" name="driver_id">
                        <option value="">- Pilih sopir -</option>
                        @foreach ($drivers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div id="helperFields">
                    <label class="label">Helper</label>
                    <select class="input" name="helper_id">
                        <option value="">- Opsional -</option>
                        @foreach ($helpers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Alamat Tujuan</label>
                    <textarea class="input" name="destination_address" rows="2">{{ old('destination_address', $packingList?->customer?->address ?? $packingList?->salesOrder?->customer?->address ?? '') }}</textarea>
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
                <li>Buat SJ (belum scan)</li>
                <li>Scan out tiap dus ke armada</li>
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
    toggleMethod();
</script>
@endpush
