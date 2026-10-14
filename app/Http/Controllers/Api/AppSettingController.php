<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * GET /api/app-settings
 *
 * Penanda fitur untuk aplikasi kasir. Aplikasi membacanya saat dibuka,
 * saat koneksi kembali, dan saat tombol sinkron produk ditekan, lalu
 * menyimpannya di HP supaya tetap berlaku saat offline.
 */
class AppSettingController extends Controller
{
    public function show(Request $request)
    {
        $store = $request->user()->store;

        return response()->json([
            'status' => 'success',
            'data' => [
                'store_name' => $store->name,
                'features' => [
                    'draft_order' => (bool) $store->feature_draft_order,
                ],
            ],
        ]);
    }
}
