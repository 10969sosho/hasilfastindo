@props(['status' => '', 'label' => null])
@php
    $map = [
        'completed' => 'emerald', 'received' => 'emerald', 'matched' => 'emerald', 'approved' => 'emerald',
        'available' => 'emerald', 'ready' => 'emerald', 'done' => 'emerald', 'delivered' => 'emerald',
        'in_transit' => 'amber', 'pending' => 'amber', 'draft' => 'amber', 'open' => 'amber',
        'pending_scan' => 'amber', 'in_progress' => 'amber', 'putaway' => 'amber', 'reserved' => 'amber',
        'partial' => 'amber', 'arrived' => 'amber', 'pickup_sendiri' => 'amber',
        'discrepancy' => 'rose', 'problem' => 'rose', 'cancelled' => 'rose', 'low' => 'rose',
        'processing' => 'indigo', 'packed' => 'indigo', 'loaded' => 'indigo', 'in_delivery' => 'indigo',
        'scanned_out' => 'indigo', 'received_pending' => 'indigo',
        'draft_status' => 'slate',
    ];
    $tone = $map[$status] ?? 'slate';
    $tones = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
    $text = $label ?? ucwords(str_replace('_', ' ', (string) $status));
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.$tones[$tone]]) }}>
    {{ $text }}
</span>
