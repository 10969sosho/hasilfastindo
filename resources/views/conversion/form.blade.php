@extends('layouts.app')

@section('title', 'Perintah Konversi Baru')
@section('subtitle', 'Superadmin menetapkan item, UOM asal, UOM tujuan, dan rencana qty konversi.')

@section('content')
<form method="POST" action="{{ route('conversion.store') }}" class="max-w-3xl space-y-5">
    @csrf

    <div class="card card-pad">
        <h2 class="mb-4 text-sm font-extrabold text-slate-900">Detail Perintah Konversi</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label required">Item</label>
                <select class="input" name="item_id" id="item_id" required>
                    <option value="">- Pilih item -</option>
                    @foreach ($items as $i)
                        <option value="{{ $i->id }}" @selected(old('item_id') == $i->id)>{{ $i->sku }} — {{ $i->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label required">Dari UOM (Kemasan Asal)</label>
                <select class="input" name="from_uom_id" required>
                    <option value="">- Pilih UOM -</option>
                    @foreach ($uoms as $u)
                        <option value="{{ $u->id }}" @selected(old('from_uom_id') == $u->id)>{{ $u->code }} — {{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label required">Ke UOM (Hasil Konversi)</label>
                <select class="input" name="to_uom_id" required>
                    <option value="">- Pilih UOM -</option>
                    @foreach ($uoms as $u)
                        <option value="{{ $u->id }}" @selected(old('to_uom_id') == $u->id)>{{ $u->code }} — {{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label required">Rencana Qty (satuan tujuan)</label>
                <input class="input text-right" type="number" step="0.001" min="0.001" name="planned_qty" value="{{ old('planned_qty') }}" required placeholder="Contoh: 12">
            </div>
            <div>
                <label class="label required">Cabang</label>
                <select class="input" name="branch_id" required>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected(old('branch_id', request('branch_id')) == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label required">Gudang</label>
                <select class="input" name="warehouse_id" id="warehouse_id" onchange="location.href='{{ route('conversion.create') }}?warehouse_id='+this.value" required>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('warehouse_id', request('warehouse_id')) == $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Catatan</label>
                <input class="input" name="notes" value="{{ old('notes') }}" placeholder="Alasan / keterangan konversi">
            </div>
        </div>

        <p class="hint mt-4 rounded-lg bg-slate-50 px-3 py-2">
            Eksekusi dilakukan operator: scan barcode kemasan asal (wajib aktif &amp; belum pernah dikonversi), catat jumlah hasil konversi,
            tentukan BIN hasil, lalu cetak label barcode baru. Total qty di NAV tidak berubah sehingga tidak ada posting jurnal.
        </p>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('conversion.index') }}" class="btn btn-ghost">Batal</a>
        <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan Perintah</button>
    </div>
</form>
@endsection
