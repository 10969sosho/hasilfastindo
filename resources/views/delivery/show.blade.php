@extends('layouts.app')

@section('title', 'Surat Jalan '.$delivery->delivery_no)
@section('subtitle', $statuses[$delivery->status] ?? $delivery->status.' · '.$delivery->vehicle?->plate_number)

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <x-badge status="{{ $delivery->status }}" label="{{ $statuses[$delivery->status] ?? ucfirst(str_replace('_', ' ', $delivery->status)) }}" />
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">SO {{ $delivery->salesOrder?->so_number }}</span>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $delivery->packingList?->no_packing }}</span>
        <span class="rounded-full {{ $delivery->method === 'pickup_sendiri' ? 'bg-amber-50 text-amber-700' : 'bg-indigo-50 text-indigo-700' }} px-3 py-1 text-xs font-bold">
            {{ $delivery->method === 'pickup_sendiri' ? 'Pickup Sendiri' : 'Armada' }}
        </span>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('delivery.manifest', $delivery) }}" class="btn btn-ghost"><x-icon name="printer" :size="16" /> Manifest</a>
        <a href="{{ route('delivery.index') }}" class="btn btn-ghost">Kembali</a>
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-5">
        {{-- Scan out --}}
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Scan Out Muatan</h2>
                    <p class="hint">Scan barcode dus (HF-BOX-...) sebelum masuk kendaraan.</p>
                </div>
                <span class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-extrabold text-white">
                    {{ $scanned }} / {{ $total }} dus
                </span>
            </div>

            @if ($delivery->status === 'pending_scan' || $delivery->status === 'scanned_out')
                <form method="POST" action="{{ route('delivery.scan', $delivery) }}" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    <input class="input flex-1 min-w-[16rem] font-mono" name="barcode" id="scanInput"
                           placeholder="Scan / ketik barcode dus lalu Enter" autocomplete="off" autofocus>
                    <button class="btn btn-primary"><x-icon name="inbound" :size="16" /> Scan Out</button>
                </form>
                @error('barcode')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
            @else
                <p class="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">Seluruh muatan sudah discan out.</p>
            @endif

            <div class="table-scroll mt-4">
                <table class="table">
                    <thead><tr><th>Barcode</th><th>Isi</th><th class="text-right">Qty</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($manifest as $m)
                        <tr>
                            <td class="font-mono text-xs font-bold text-indigo-700">{{ $m['barcode'] }}</td>
                            <td>
                                @foreach ($m['items'] as $mi)
                                    <p class="text-xs text-slate-600">{{ $mi->item?->name }} — {{ number_format($mi->qty) }} {{ $mi->uom?->code }}</p>
                                @endforeach
                            </td>
                            <td class="text-right font-bold">{{ number_format($m['qty']) }}</td>
                            <td>
                                @if ($m['status'] === 'scanned_out')
                                    <x-badge status="scanned_out" label="Ter-scan" />
                                    <p class="text-[11px] text-slate-400">{{ $m['scanned_out_at']?->format('d/m H:i') }}</p>
                                @else
                                    <x-badge status="pending_scan" label="Belum" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Status update --}}
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Update Status Pengiriman</h2>
            <form method="POST" action="{{ route('delivery.status', $delivery) }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label class="label required">Status Baru</label>
                    <select class="input" name="status" required>
                        @foreach (['in_delivery' => 'Dalam Pengiriman', 'arrived' => 'Tiba', 'received' => 'Selesai / Diterima', 'problem' => 'Bermasalah'] as $k => $l)
                            <option value="{{ $k }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Diterima oleh</label>
                    <input class="input" name="received_by" value="{{ old('received_by', $delivery->received_by) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Keterangan</label>
                    <input class="input" name="description" placeholder="Opsional">
                </div>
                <div>
                    <label class="label">Bukti Foto (maks 4MB)</label>
                    <input class="input" type="file" name="proof" accept="image/*">
                </div>
                <div class="flex items-end">
                    <button class="btn btn-primary"><x-icon name="check" :size="16" /> Simpan Status</button>
                </div>
            </form>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Info Pengiriman</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">No. SJ</dt><dd class="font-mono font-bold">{{ $delivery->delivery_no }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Armada</dt><dd class="font-semibold">{{ $delivery->vehicle?->plate_number ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Sopir</dt><dd class="font-semibold">{{ $delivery->driver_name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Helper</dt><dd class="font-semibold">{{ $delivery->helper_name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Berangkat</dt><dd class="font-semibold">{{ $delivery->departure_time?->format('d/m H:i') ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tiba</dt><dd class="font-semibold">{{ $delivery->arrival_time?->format('d/m H:i') ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Penerima</dt><dd class="font-semibold">{{ $delivery->received_by ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tujuan</dt><dd class="text-right text-slate-600">{{ $delivery->destination_address ?: '-' }}</dd></div>
            </dl>
            @if ($delivery->received_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($delivery->received_photo))
                <img class="mt-3 w-full rounded-lg border border-slate-200"
                     src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($delivery->received_photo) }}" alt="Bukti terima">
            @endif
        </div>

        <div class="card card-pad">
            <h2 class="mb-3 text-sm font-extrabold text-slate-900">Timeline</h2>
            <ol class="space-y-3">
                @forelse ($delivery->tracks as $t)
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 flex-none rounded-full {{ $t->status === 'problem' ? 'bg-rose-500' : 'bg-indigo-500' }}"></span>
                        <div>
                            <p class="text-xs font-bold text-slate-800">{{ $statuses[$t->status] ?? ucfirst(str_replace('_', ' ', $t->status)) }}</p>
                            <p class="text-[11px] text-slate-500">{{ $t->description }}</p>
                            <p class="text-[10px] text-slate-400">{{ $t->created_at?->format('d/m/Y H:i') }} · {{ $t->user?->name }}</p>
                            @if ($t->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($t->photo))
                                <img class="mt-1 w-24 rounded border border-slate-200" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($t->photo) }}" alt="Bukti">
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-400">Belum ada aktivitas.</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const input = document.getElementById('scanInput');
    if (input) {
        input.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const form = input.closest('form');
            if (input.value.trim()) form.submit();
        });
    }
</script>
@endpush
