<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API aplikasi kasir: hanya pemilik toko yang SUDAH DISETUJUI.
 * Pending → 403 store_pending. Superadmin tidak memakai aplikasi kasir.
 */
class EnsureApiStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->store_id) {
            return response()->json([
                'status' => 'error',
                'code' => 'no_store',
                'message' => 'Akun ini tidak terhubung ke toko mana pun.',
            ], 403);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'code' => 'store_pending',
                'message' => 'Toko Anda masih menunggu persetujuan.',
            ], 403);
        }

        return $next($request);
    }
}
