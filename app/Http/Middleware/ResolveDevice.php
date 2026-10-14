<?php

namespace App\Http\Middleware;

use App\Services\DeviceManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membaca header X-Device-Id dari aplikasi kasir, mencatat HP tersebut,
 * dan menyimpannya di $request->attributes('store_device').
 *
 *   device          → header wajib ada
 *   device:cashier  → HP harus perangkat kasir aktif (untuk operasi yang
 *                     mengubah data dari HP, selain mengirim transaksi lama)
 */
class ResolveDevice
{
    public function __construct(private DeviceManager $devices) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $deviceId = trim((string) $request->header('X-Device-Id'));

        if ($deviceId === '') {
            return response()->json([
                'status' => 'error',
                'code' => 'device_required',
                'message' => 'Perbarui aplikasi kasir ke versi terbaru.',
            ], 426);
        }

        $store = $request->user()->store;

        $device = $this->devices->touch($store, [
            'device_id' => $deviceId,
            'app_version' => $request->header('X-App-Version'),
        ]);

        $request->attributes->set('store_device', $device);

        if ($mode === 'cashier' && (int) $store->active_device_id !== (int) $device->id) {
            return response()->json([
                'status' => 'error',
                'code' => 'not_cashier_device',
                'message' => 'HP ini bukan perangkat kasir aktif toko. Pindahkan kasir ke HP ini dulu.',
            ], 403);
        }

        return $next($request);
    }
}
