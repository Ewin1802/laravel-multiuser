<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * GET /api/license
 *
 * Dipanggil aplikasi setiap kali online (buka app, kembali dari
 * background, koneksi pulih, sesudah sinkron). Mengembalikan jam server
 * dan status langganan bertanda tangan → batas offline 2 hari dihitung
 * ulang dari sini.
 */
class LicenseController extends Controller
{
    public function show(Request $request)
    {
        $store = $request->user()->store->refresh();
        $device = $request->attributes->get('store_device');

        $device->forceFill(['last_sync_at' => now()])->save();

        return response()->json([
            'status' => 'success',
            'data' => AuthController::sessionPayload($store, $device),
        ]);
    }
}
