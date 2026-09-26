@extends('layouts.app')

@section('title', 'Sales Order Baru')

@section('content')
<form method="POST" action="{{ route('outbound.store') }}" class="grid gap-5 lg:grid-cols-3">
    @csrf

    <div class="space-y-5 lg:col-span-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-sm font-extrabold text-slate-900">Informasi SO</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label required">Customer</label>
                    <select class="input" name="customer_id" required>
                        <option value="">- Pilih customer -</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label required">Tanggal Order</label>
                    <input class="input" type="date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required>
                </div>
                <div>
                    <label class="label required">Cabang Pencatat SO</label>
                    <select class="input" name="branch_id" required>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Gudang Pemasok (cross-branch)</label>
                    <select class="input" name="fulfillment_branch_id">
                        <option value="">- Ikut cabang pencatat -</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('fulfillment_branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Catatan</label>
                    <textarea class="input" name="notes" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Item Pesanan</h2>
                    <p class="hint">Qty dalam satuan pilihan (mis. DUS/KRT), dikonversi otomatis ke PCS saat pemenuhan.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="addRow"><x-icon name="plus" :size="14" /> Baris</button>
            </div>

            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th class="min-w-[16rem]">Item</th><th>Satuan</th><th class="w-32">Qty Pesan</th><th class="w-36">Harga Satuan</th><th></th></tr></thead>
                    <tbody id="itemBody">
                        <tr class="item-row">
                            <td>
                                <select class="input" name="items[0][item_id]" required>
                                    <option value="">- Pilih item -</option>
                                    @foreach ($items as $i)
                                        <option value="{{ $i->id }}" data-price="{{ $i->sell_price }}" data-base="{{ $i->base_uom_id }}">{{ $i->sku }} — {{ $i->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="input uom-select" name="items[0][uom_id]" required>
                                    @foreach ($uoms as $u)<option value="{{ $u->id }}">{{ $u->code }}</option>@endforeach
                                </select>
                            </td>
                            <td><input class="input" type="number" step="0.001" min="0.001" name="items[0][requested_qty]" value="1" required></td>
                            <td><input class="input price-input" type="number" step="0.01" min="0" name="items[0][unit_price]" value="0"></td>
                            <td class="w-10"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">✕</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Simpan</h2>
            <div class="flex flex-col gap-2">
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Buat SO</button>
                <a href="{{ route('outbound.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </div>
        <div class="card card-pad bg-slate-50">
            <p class="text-xs font-bold text-slate-600">Cross-branch</p>
            <p class="hint mt-1">SO bisa dicatat di Cabang A namun dikeluarkan dari gudang Cabang B — pilih "Gudang Pemasok".</p>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    let rowIndex = 1;
    document.getElementById('addRow').addEventListener('click', () => {
        const html = document.querySelector('#itemBody .item-row').outerHTML.replace(/items\[\d+\]/g, () => `items[${rowIndex}]`);
        document.getElementById('itemBody').insertAdjacentHTML('beforeend', html);
        rowIndex++;
    });

    document.addEventListener('change', e => {
        if (!e.target.classList.contains('item-select')) return;
        const row = e.target.closest('.item-row');
        const opt = e.target.selectedOptions[0];
        if (!opt) return;
        row.querySelector('.price-input').value = opt.dataset.price || 0;
        const base = opt.dataset.base;
        [...row.querySelector('.uom-select').options].forEach(o => o.selected = o.value === base);
    });
    document.querySelectorAll('#itemBody select[name$="[item_id]"]').forEach(el => el.classList.add('item-select'));
</script>
@endpush
