<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeviceManager;
use Illuminate\Http\Request;

/**
 * POST /api/device/claim — "Pindahkan kasir ke HP ini".
 *
 * HP lama berhenti bisa membuat transaksi baru begitu ia online dan
 * menerima lisensi barunya (is_cashier = false). Transaksi HP lama yang
 * belum terkirim tetap diterima saat ia sinkron.
 */
class DeviceController extends Controller
{
    public function claim(Request $request, DeviceManager $devices)
    {
        $request->validate([
            'confirm' => ['accepted'],
        ]);

        $store = $request->user()->store;
        $device = $request->attributes->get('store_device');

        $devices->claim($store, $device, $request->user(), 'app', $request);

        return response()->json([
            'status' => 'success',
            'message' => 'HP ini sekarang menjadi perangkat kasir toko.',
            'data' => AuthController::sessionPayload($store->refresh(), $device->refresh()),
        ]);
    }
}
