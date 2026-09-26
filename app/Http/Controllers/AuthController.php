<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah, atau akun tidak aktif.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget('active_branch_id');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchBranch(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isCentral(), 403, 'Hanya superadmin/pusat yang bisa ganti cabang.');

        $payload = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $branchId = $payload['branch_id'] ?? null;

        if ($branchId) {
            $request->session()->put('active_branch_id', (int) $branchId);
            $request->session()->flash('toast', 'Cabang aktif: '.Branch::whereKey($branchId)->value('name'));
        } else {
            $request->session()->forget('active_branch_id');
            $request->session()->flash('toast', 'Menampilkan seluruh cabang.');
        }

        return back();
    }
}
