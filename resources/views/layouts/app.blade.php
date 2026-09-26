<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · HASIL FASTINDO WMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        body { font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:.9rem; box-shadow:0 1px 2px rgba(15,23,42,.05); }
        .card-pad { padding:1.1rem 1.25rem; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:.45rem; border-radius:.65rem; padding:.5rem .95rem; font-size:.85rem; font-weight:600; line-height:1.2; transition:.15s ease; cursor:pointer; white-space:nowrap; }
        .btn-primary { background:#4f46e5; color:#fff; } .btn-primary:hover { background:#4338ca; }
        .btn-success { background:#059669; color:#fff; } .btn-success:hover { background:#047857; }
        .btn-amber { background:#d97706; color:#fff; } .btn-amber:hover { background:#b45309; }
        .btn-ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; } .btn-ghost:hover { background:#f1f5f9; }
        .btn-danger { background:#e11d48; color:#fff; } .btn-danger:hover { background:#be123c; }
        .btn-dark { background:#0f172a; color:#fff; } .btn-dark:hover { background:#1e293b; }
        .btn-sm { padding:.35rem .7rem; font-size:.78rem; }
        .input { width:100%; border:1px solid #cbd5e1; border-radius:.65rem; padding:.55rem .8rem; font-size:.875rem; background:#fff; color:#0f172a; }
        .input:focus { outline:2px solid #6366f1; outline-offset:-1px; border-color:#6366f1; }
        .label { display:block; font-size:.72rem; font-weight:700; color:#475569; margin-bottom:.3rem; text-transform:uppercase; letter-spacing:.05em; }
        .table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .table thead th { background:#f8fafc; color:#475569; text-align:left; padding:.65rem .8rem; font-weight:700; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
        .table tbody td { padding:.65rem .8rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .table tbody tr:hover { background:#f8fafc; }
        .nav-link { display:flex; align-items:center; gap:.7rem; padding:.55rem .8rem; border-radius:.6rem; color:#94a3b8; font-size:.875rem; font-weight:600; transition:.15s; }
        .nav-link:hover { background:rgba(255,255,255,.06); color:#e2e8f0; }
        .nav-link.active { background:#4f46e5; color:#fff; box-shadow:0 4px 14px rgba(79,70,229,.35); }
        .nav-section { font-size:.66rem; font-weight:800; letter-spacing:.12em; color:#475569; text-transform:uppercase; padding:1rem .9rem .35rem; }
        .kpi { background:#fff; border:1px solid #e2e8f0; border-radius:.9rem; padding:1rem 1.1rem; }
        .hint { font-size:.78rem; color:#64748b; }
        .required::after { content:"*"; color:#e11d48; margin-left:.15rem; }
        .table-scroll { overflow-x:auto; }
        @media print {
            .no-print { display:none !important; }
            body { background:#fff !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
@php
    $user = auth()->user();
    $canSeeAll = $user && in_array($user->role, ['super_admin', 'central'], true);
    $currentRoute = request()->route()?->getName() ?? '';
    $activeBranch = \App\Models\Branch::find(session('active_branch_id'));
    $branchLabel = $activeBranch?->name ?? ($user?->branch?->name ?? '-');
    $branches = $canSeeAll ? \App\Models\Branch::orderByDesc('is_central')->orderBy('name')->get() : collect();

    $nav = [
        ['type' => 'section', 'label' => 'Operasional'],
        ['type' => 'link', 'label' => 'Dashboard', 'icon' => 'dashboard', 'match' => 'dashboard', 'route' => 'dashboard'],
        ['type' => 'link', 'label' => 'Penerimaan Barang', 'icon' => 'inbound', 'match' => 'inbound.', 'route' => 'inbound.index'],
        ['type' => 'link', 'label' => 'Pengeluaran (SO)', 'icon' => 'outbound', 'match' => 'outbound.', 'route' => 'outbound.index'],
        ['type' => 'link', 'label' => 'Radar Summary SO', 'icon' => 'radar', 'match' => 'summary.', 'route' => 'summary.index'],
        ['type' => 'link', 'label' => 'Picking & Packing', 'icon' => 'clipboard', 'match' => 'packing.', 'route' => 'packing.index'],
        ['type' => 'link', 'label' => 'Pengiriman & Scan Out', 'icon' => 'truck', 'match' => 'delivery.', 'route' => 'delivery.index'],
        ['type' => 'link', 'label' => 'Transfer Antar Cabang', 'icon' => 'transfer', 'match' => 'transfer.', 'route' => 'transfer.index'],
        ['type' => 'link', 'label' => 'Repack & Konversi', 'icon' => 'repack', 'match' => 'repack.', 'route' => 'repack.index'],
        ['type' => 'link', 'label' => 'Stok Opname', 'icon' => 'scan', 'match' => 'opname.', 'route' => 'opname.index'],
        ['type' => 'section', 'label' => 'Monitoring'],
        ['type' => 'link', 'label' => 'Monitoring Pusat', 'icon' => 'chart', 'match' => 'monitoring.', 'route' => 'monitoring.index', 'roles' => ['super_admin', 'central']],
        ['type' => 'link', 'label' => 'Mobile Warehouse', 'icon' => 'smartphone', 'match' => 'mobile.', 'route' => 'mobile.index'],
        ['type' => 'section', 'label' => 'Master Data'],
        ['type' => 'link', 'label' => 'Data Item', 'icon' => 'package', 'match' => 'items.', 'route' => 'items.index'],
        ['type' => 'link', 'label' => 'Cetak Barcode', 'icon' => 'barcode', 'match' => 'items.barcode', 'route' => 'items.barcode'],
        ['type' => 'link', 'label' => 'Cabang', 'icon' => 'building', 'match' => 'branches.', 'route' => 'branches.index', 'roles' => ['super_admin', 'central']],
        ['type' => 'link', 'label' => 'Gudang & BIN', 'icon' => 'warehouse', 'match' => 'warehouses.', 'route' => 'warehouses.index'],
        ['type' => 'link', 'label' => 'Armada & Sopir', 'icon' => 'truck', 'match' => 'fleet.', 'route' => 'fleet.index'],
        ['type' => 'link', 'label' => 'Supplier & Customer', 'icon' => 'users', 'match' => 'partners.', 'route' => 'partners.index'],
    ];

    $isActive = function (array $item) use ($currentRoute) {
        if ($item['type'] !== 'link') {
            return false;
        }
        if ($item['match'] === 'dashboard') {
            return $currentRoute === 'dashboard';
        }
        if ($item['match'] === 'items.barcode') {
            return $currentRoute === 'items.barcode';
        }
        if ($item['match'] === 'items.' && in_array($currentRoute, ['items.barcode'], true)) {
            return false;
        }
        return $currentRoute !== '' && str_starts_with($currentRoute, $item['match']);
    };
@endphp

<div class="flex min-h-screen">
    {{-- Overlay mobile --}}
    <div id="sidebarOverlay" class="fixed inset-0 z-40 bg-slate-900/60 hidden lg:hidden"></div>

    {{-- Sidebar --}}
    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-slate-900 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
        <div class="flex h-16 items-center gap-3 border-b border-slate-800 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white font-extrabold text-sm">HF</div>
            <div class="leading-tight">
                <p class="text-sm font-extrabold text-white">HASIL FASTINDO</p>
                <p class="text-[11px] font-medium text-slate-400">Warehouse Management System</p>
            </div>
            <button id="sidebarClose" class="ml-auto text-slate-400 hover:text-white lg:hidden"><x-icon name="close" /></button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 pb-6">
            @foreach ($nav as $item)
                @if ($item['type'] === 'section')
                    <p class="nav-section">{{ $item['label'] }}</p>
                @elseif (! isset($item['roles']) || in_array($user?->role, $item['roles'], true))
                    <a href="{{ route($item['route']) }}"
                       class="nav-link mb-1 {{ $isActive($item) ? 'active' : '' }}">
                        <x-icon :name="$item['icon']" :size="18" />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="border-t border-slate-800 p-3">
            <div class="flex items-center gap-3 rounded-lg bg-slate-800/70 px-3 py-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">
                    {{ strtoupper(substr($user?->name ?? '?', 0, 2)) }}
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-sm font-semibold text-white">{{ $user?->name }}</p>
                    <p class="truncate text-[11px] text-slate-400">{{ ucfirst(str_replace('_', ' ', $user?->role ?? '')) }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-slate-400 hover:text-rose-400" title="Keluar"><x-icon name="logout" :size="18" /></button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:px-6">
            <button id="sidebarOpen" class="text-slate-600 hover:text-slate-900 lg:hidden"><x-icon name="menu" :size="22" /></button>

            <div class="min-w-0">
                <h1 class="truncate text-base font-extrabold text-slate-900 lg:text-lg">@yield('title', 'Dashboard')</h1>
                <p class="hidden truncate text-xs text-slate-500 sm:block">@yield('subtitle', '')</p>
            </div>

            <div class="ml-auto flex items-center gap-3">
                @if ($canSeeAll)
                    <form method="POST" action="{{ route('branch.switch') }}" class="flex items-center gap-2">
                        @csrf
                        <span class="hidden text-xs font-bold uppercase tracking-wide text-slate-400 xl:inline">Cabang</span>
                        <select name="branch_id" onchange="this.form.submit()"
                                class="input w-auto max-w-[13rem] py-1.5 text-xs font-semibold">
                            <option value="">Semua Cabang</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected((string) session('active_branch_id') === (string) $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <span class="hidden items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-600/20 md:inline-flex">
                    <x-icon name="pin" :size="13" /> {{ $branchLabel }}
                </span>

                <a href="{{ route('mobile.index') }}" class="btn btn-ghost btn-sm lg:hidden" title="Mode Gudang"><x-icon name="smartphone" :size="16" /></a>
            </div>
        </header>

        <main class="min-w-0 flex-1 p-4 lg:p-6">
            @if (session('toast'))
                <div id="toast" class="mb-4 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    <x-icon name="check" :size="18" class="mt-0.5 shrink-0" />
                    <span>{{ session('toast') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const open = () => { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); };
    const close = () => { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); };
    document.getElementById('sidebarOpen')?.addEventListener('click', open);
    document.getElementById('sidebarClose')?.addEventListener('click', close);
    overlay?.addEventListener('click', close);

    const toast = document.getElementById('toast');
    if (toast) setTimeout(() => { toast.style.display = 'none'; }, 6000);

    window.wmsConfirm = (message) => confirm(message || 'Lanjutkan aksi ini?');
</script>
@stack('scripts')
</body>
</html>
