<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga session "active_branch_id" tetap valid (dipakai switch cabang superadmin/pusat).
 */
class EnsureActiveBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $branchId = $request->session()->get('active_branch_id');

        if ($user && $branchId !== null) {
            $allowed = in_array($user->role, ['super_admin', 'central'], true);

            if (! $allowed || ! Branch::whereKey($branchId)->exists()) {
                $request->session()->forget('active_branch_id');
            }
        }

        return $next($request);
    }
}
