<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * API aplikasi ADMIN: hanya akun superadmin yang login lewat
 * POST /api/admin/login (token berkemampuan "admin").
 *
 * Token aplikasi kasir (kemampuan "*") dan sesi web DITOLAK, jadi
 * token kasir yang bocor tidak bisa dipakai menyetujui toko.
 */
class EnsureAdminApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        $ok = $user
            && $user->role === 'superadmin'
            && $token instanceof PersonalAccessToken
            && in_array('admin', (array) $token->abilities, true);

        if (! $ok) {
            return response()->json([
                'status' => 'error',
                'code' => 'not_admin',
                'message' => 'Hanya untuk aplikasi admin. Silakan login ulang.',
            ], 403);
        }

        return $next($request);
    }
}
