<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cetak') · HASIL FASTINDO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family:"Plus Jakarta Sans", system-ui, sans-serif; background:#fff; color:#0f172a; }
        .table { width:100%; border-collapse:collapse; font-size:12px; }
        .table th, .table td { border:1px solid #cbd5e1; padding:6px 8px; text-align:left; }
        .table th { background:#f1f5f9; font-weight:700; }
        .label-btn { display:inline-flex; gap:8px; }
        @media print { .no-print { display:none !important; } body { margin:0; } }
    </style>
</head>
<body class="p-6">
    <div class="no-print mb-4 flex items-center justify-between gap-3 border-b pb-3">
        <div>
            <p class="text-sm font-extrabold">HASIL FASTINDO · WMS</p>
            <p class="text-xs text-slate-500">@yield('title', 'Dokumen Cetak')</p>
        </div>
        <div class="label-btn">
            <button onclick="window.print()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Print</button>
            <button onclick="history.back()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Kembali</button>
        </div>
    </div>

    @yield('content')

    @stack('scripts')
</body>
</html>
