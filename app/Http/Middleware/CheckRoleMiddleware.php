<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * role:admin       → pemilik toko yang sudah disetujui
 * role:superadmin  → pemilik aplikasi
 * role:admin,superadmin → salah satu
 */
class CheckRoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if ($user && in_array($user->role, $roles, true)) {
            if ($user->role === 'admin' && ! $user->store_id) {
                return response()->view('components.403', [], 403);
            }

            return $next($request);
        }

        return response()->view('components.403', [], 403);
    }
}
