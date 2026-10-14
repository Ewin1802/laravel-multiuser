<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HANYA-LIHAT saat langganan habis / toko diblokir.
 *
 * Request baca (GET/HEAD) selalu lolos. Request ubah data ditolak
 * kecuali rute yang dikecualikan (mis. kirim bukti pembayaran).
 *
 * Catatan: transaksi dari HP (save-order) TIDAK memakai middleware ini,
 * karena transaksi yang sudah terjadi harus tetap diterima server.
 */
class EnsureStoreWritable
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $store = $request->user()?->store;

        if (! $store || $store->subscription()->canTransact()) {
            return $next($request);
        }

        $sub = $store->subscription();
        $message = 'Masa aktif toko ' . strtolower($sub->label())
            . '. Data hanya bisa dilihat sampai pembayaran dikonfirmasi.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'error',
                'code' => 'subscription_inactive',
                'state' => $sub->state(),
                'message' => $message,
            ], 403);
        }

        return redirect()->route('billing.index')->with('warning', $message);
    }
}
