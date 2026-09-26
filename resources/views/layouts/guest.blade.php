<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Masuk') · HASIL FASTINDO WMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui'] } } } };
    </script>
    <style>
        body { font-family:"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif; }
        .input { width:100%; border:1px solid #cbd5e1; border-radius:.7rem; padding:.7rem .9rem; font-size:.9rem; background:#fff; }
        .input:focus { outline:2px solid #6366f1; outline-offset:-1px; border-color:#6366f1; }
        .label { display:block; font-size:.72rem; font-weight:700; color:#475569; margin-bottom:.35rem; text-transform:uppercase; letter-spacing:.05em; }
    </style>
</head>
<body class="min-h-screen bg-slate-900 text-slate-800 antialiased">
@yield('content')
</body>
</html>
