@extends('layouts.app')

@section('title', 'Sesi Opname Baru')

@section('content')
<form method="POST" action="{{ route('opname.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf

    <div class="space-y-5 lg:col-span-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Parameter Sesi</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label required">Cabang</label>
                    <select class="input" name="branch_id" id="branch_id" onchange="location.href='{{ route('opname.create') }}?branch_id='+this.value" required>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branchId == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Gudang</label>
                    <select class="input" name="warehouse_id" onchange="loadBins(this.value)" required>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">BIN yang Diopname</label>
                    <select class="input" name="location_id" id="location_id" required>
                        @foreach ($bins as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Tanggal Opname</label>
                    <input class="input" type="date" name="opname_date" value="{{ old('opname_date', now()->format('Y-m-d')) }}" required>
                </div>
                <div>
                    <label class="label">Judul</label>
                    <input class="input" name="title" placeholder="Opname mingguan rak A" value="{{ old('title') }}">
                </div>
                <div>
                    <label class="label">Catatan</label>
                    <input class="input" name="notes" value="{{ old('notes') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Simpan</h2>
            <div class="flex flex-col gap-2">
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Buat Sesi</button>
                <a href="{{ route('opname.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </div>
        <div class="card card-pad bg-slate-50">
            <p class="text-xs font-bold text-slate-600">Alur</p>
            <ol class="hint mt-2 list-inside list-decimal space-y-1">
                <li>Buat sesi (status open)</li>
                <li>Hitung fisik per item (scan barcode)</li>
                <li>Approval → stok disesuaikan otomatis</li>
            </ol>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const binsUrl = @json(route('opname.bins'));
    async function loadBins(warehouseId) {
        const select = document.getElementById('location_id');
        select.innerHTML = '<option value="">Memuat...</option>';
        const res = await fetch(binsUrl + '?warehouse_id=' + warehouseId);
        const rows = await res.json();
        select.innerHTML = rows.map(b => `<option value="${b.id}">${b.code}</option>`).join('') || '<option value="">Tidak ada BIN</option>';
    }
</script>
@endpush
