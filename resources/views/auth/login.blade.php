@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="flex min-h-screen items-center justify-center p-4">
    <div class="grid w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl lg:grid-cols-2">
        {{-- Brand panel --}}
        <div class="relative hidden bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 p-10 text-white lg:block">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 font-extrabold">HF</div>
                <div>
                    <p class="text-lg font-extrabold leading-tight">HASIL FASTINDO</p>
                    <p class="text-xs text-slate-400">Warehouse Management System</p>
                </div>
            </div>

            <div class="mt-12 space-y-6">
                <h2 class="text-2xl font-extrabold leading-snug">Operasional gudang<br>multi-cabang dalam satu layar.</h2>
                <ul class="space-y-3 text-sm text-slate-300">
                    <li class="flex gap-2"><span class="text-emerald-400">✔</span> Penerimaan, putaway, dan BIN code per rak</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✔</span> Pemenuhan SO FIFO & cross-branch</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✔</span> Packing dus ber-barcode + scan out armada</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✔</span> Transfer, repack, stok opname, monitoring pusat</li>
                </ul>
            </div>

            <p class="absolute bottom-8 text-xs text-slate-500">Fastener · Baut · Mur · Anchor · Perkakas</p>
        </div>

        {{-- Login form --}}
        <div class="p-8 sm:p-10">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 font-extrabold text-white">HF</div>
                <div>
                    <p class="font-extrabold leading-tight">HASIL FASTINDO</p>
                    <p class="text-xs text-slate-500">Warehouse Management System</p>
                </div>
            </div>

            <h1 class="text-xl font-extrabold text-slate-900">Masuk ke sistem</h1>
            <p class="mt-1 text-sm text-slate-500">Gunakan akun sesuai peran: superadmin, pusat, atau cabang.</p>

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="nama@hasilfastindo.com" required autofocus>
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600"> Ingat saya
                </label>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-indigo-700" type="submit">
                    Masuk
                </button>
            </form>

            <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="mb-2 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Akun demo (password: password)</p>
                <div class="grid gap-1.5 text-xs">
                    @foreach ([
                        ['superadmin@hasilfastindo.com', 'Super Admin'],
                        ['pusat@hasilfastindo.com', 'Staf Pusat'],
                        ['cabang.sby@hasilfastindo.com', 'Cabang Surabaya'],
                        ['cabang.jkt@hasilfastindo.com', 'Cabang Jakarta'],
                    ] as $acc)
                        <button type="button" onclick="document.getElementById('email').value='{{ $acc[0] }}';document.getElementById('password').value='password';"
                                class="flex items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2 text-left font-semibold text-slate-700 hover:border-indigo-400 hover:text-indigo-700">
                            <span>{{ $acc[0] }}</span>
                            <span class="text-[10px] font-bold uppercase text-slate-400">{{ $acc[1] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
