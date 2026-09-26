@extends('layouts.app')

@section('title', isset($item) ? 'Edit Item' : 'Item Baru')
@section('subtitle', 'Identitas barang dan konversi satuan (KRT → DUS → PCS)')

@section('content')
<form method="POST" action="{{ isset($item) ? route('items.update', $item) : route('items.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf
    @if (isset($item)) @method('PUT') @endif

    <div class="space-y-5 lg:col-span-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Identitas Item</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label required">SKU</label>
                    <input class="input" name="sku" value="{{ old('sku', $item?->sku) }}" placeholder="HF-BHM8-40" required>
                </div>
                <div>
                    <label class="label required">Barcode</label>
                    <input class="input" name="barcode" value="{{ old('barcode', $item?->barcode) }}" placeholder="8991001001">
                </div>
                <div class="sm:col-span-2">
                    <label class="label required">Nama Item</label>
                    <input class="input" name="name" value="{{ old('name', $item?->name) }}" placeholder="Baut Hex Hitam M8 x 40mm Grade 8.8" required>
                </div>
                <div>
                    <label class="label required">Kategori</label>
                    <select class="input" name="category_id" required>
                        <option value="">- Pilih -</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('category_id', $item?->category_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Satuan Dasar</label>
                    <select class="input" name="base_uom_id" required>
                        @foreach ($uoms as $u)
                            <option value="{{ $u->id }}" @selected(old('base_uom_id', $item?->base_uom_id) == $u->id)>{{ $u->code }} — {{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Minimum Stok (PCS)</label>
                    <input class="input" type="number" step="0.001" min="0" name="min_stock" value="{{ old('min_stock', $item?->min_stock ?? 0) }}">
                </div>
                <div>
                    <label class="label">Harga Modal</label>
                    <input class="input" type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price', $item?->cost_price ?? 0) }}">
                </div>
                <div>
                    <label class="label">Harga Jual</label>
                    <input class="input" type="number" step="0.01" min="0" name="sell_price" value="{{ old('sell_price', $item?->sell_price ?? 0) }}">
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                               @checked(old('is_active', $item?->is_active ?? true))> Item aktif
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Deskripsi</label>
                    <textarea class="input" name="description" rows="3">{{ old('description', $item?->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Konversi Satuan</h2>
                    <p class="hint">Contoh: 1 KRT = 24 DUS, 1 DUS = 12 PCS. Konversi dipakai untuk pemenuhan SO & putaway.</p>
                </div>
                <button type="button" id="addConv" class="btn btn-ghost btn-sm"><x-icon name="plus" :size="14" /> Baris</button>
            </div>

            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr><th>Dari</th><th>Ke</th><th>Multiplier</th><th></th></tr>
                    </thead>
                    <tbody id="convBody">
                        @forelse ($conversions ?? collect() as $i => $conv)
                            <tr>
                                <td>
                                    <select class="input" name="conversions[{{ $i }}][from_uom_id]">
                                        @foreach ($uoms as $u)<option value="{{ $u->id }}" @selected($conv->from_uom_id == $u->id)>{{ $u->code }}</option>@endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="input" name="conversions[{{ $i }}][to_uom_id]">
                                        @foreach ($uoms as $u)<option value="{{ $u->id }}" @selected($conv->to_uom_id == $u->id)>{{ $u->code }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input class="input" type="number" step="0.0001" min="0" name="conversions[{{ $i }}][multiplier]" value="{{ $conv->multiplier }}"></td>
                                <td class="w-10"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>
                            </tr>
                        @empty
                            <tr class="conv-row">
                                <td>
                                    <select class="input" name="conversions[0][from_uom_id]">
                                        <option value="">- Pilih -</option>
                                        @foreach ($uoms as $u)<option value="{{ $u->id }}">{{ $u->code }}</option>@endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="input" name="conversions[0][to_uom_id]">
                                        <option value="">- Pilih -</option>
                                        @foreach ($uoms as $u)<option value="{{ $u->id }}" @selected($u->code === 'PCS')>{{ $u->code }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input class="input" type="number" step="0.0001" min="0" name="conversions[0][multiplier]" placeholder="24"></td>
                                <td class="w-10"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Simpan</h2>
            <div class="flex flex-col gap-2">
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Simpan Item</button>
                <a href="{{ route('items.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </div>
        <div class="card card-pad bg-slate-50">
            <p class="text-xs font-bold text-slate-600">Tips</p>
            <p class="hint mt-1">Konversi disimpan otomatis saat item disimpan. Baris yang dikosongkan akan dihapus dari daftar konversi.</p>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    let convIndex = {{ count($conversions ?? []) }};
    document.getElementById('addConv').addEventListener('click', () => {
        const uoms = @json($uoms->map(fn ($u) => ['id' => $u->id, 'code' => $u->code]));
        const opts = (selected) => '<option value="">- Pilih -</option>' + uoms.map(u => `<option value="${u.id}">${u.code}</option>`).join('');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><select class="input" name="conversions[${convIndex}][from_uom_id]">${opts()}</select></td>
            <td><select class="input" name="conversions[${convIndex}][to_uom_id]">${opts()}</select></td>
            <td><input class="input" type="number" step="0.0001" min="0" name="conversions[${convIndex}][multiplier]"></td>
            <td class="w-10"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>`;
        document.getElementById('convBody').appendChild(row);
        convIndex++;
    });
</script>
@endpush
