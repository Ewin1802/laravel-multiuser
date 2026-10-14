<?php

namespace App\Services;

use App\Models\DeviceSwitchLog;
use App\Models\Store;
use App\Models\StoreDevice;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SATU PERANGKAT KASIR AKTIF PER TOKO.
 *
 * - Login di HP baru butuh internet; bila toko belum punya kasir aktif,
 *   HP itu langsung menjadi kasir.
 * - Bila sudah ada kasir aktif di HP lain, aplikasi menawarkan:
 *   "Pindahkan kasir ke HP ini" (claim) atau "Mode lihat".
 * - HP lama tetap boleh mengirim transaksi yang belum terkirim; transaksi
 *   itu diterima dan ditandai from_inactive_device bila dibuat setelah
 *   kasir dipindah.
 */
class DeviceManager
{
    /** Catat / perbarui HP yang sedang dipakai login atau sinkron. */
    public function touch(Store $store, array $info): StoreDevice
    {
        return Tenant::run($store->id, function () use ($info) {
            $device = StoreDevice::firstOrNew(['device_id' => mb_substr((string) $info['device_id'], 0, 100)]);

            $device->fill(array_filter([
                'device_name' => $info['device_name'] ?? null,
                'platform' => $info['platform'] ?? null,
                'app_version' => $info['app_version'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));

            $device->first_seen_at ??= now();
            $device->last_seen_at = now();
            $device->save();

            return $device;
        });
    }

    /** Bila toko belum punya kasir aktif, jadikan HP ini kasir. */
    public function claimIfFree(Store $store, StoreDevice $device, ?User $user, ?Request $request = null): bool
    {
        if ($store->active_device_id) {
            return (int) $store->active_device_id === (int) $device->id;
        }

        $this->claim($store, $device, $user, 'app', $request);

        return true;
    }

    /** Pindahkan kasir aktif ke $device (HP lama berhenti bisa transaksi). */
    public function claim(Store $store, StoreDevice $device, ?User $user, string $source = 'app', ?Request $request = null): void
    {
        DB::transaction(function () use ($store, $device, $user, $source, $request) {
            $store = Store::whereKey($store->id)->lockForUpdate()->first();
            $fromId = $store->active_device_id;

            if ((int) $fromId === (int) $device->id) {
                return;
            }

            Tenant::run($store->id, function () use ($store, $device, $user, $source, $request, $fromId) {
                if ($fromId) {
                    StoreDevice::whereKey($fromId)->update(['deactivated_at' => now()]);
                }

                $device->forceFill(['activated_at' => now()])->save();

                $store->active_device_id = $device->id;
                $store->save();

                DeviceSwitchLog::create([
                    'from_store_device_id' => $fromId,
                    'to_store_device_id' => $device->id,
                    'user_id' => $user?->id,
                    'source' => $source,
                    'ip_address' => $request?->ip(),
                ]);
            });
        });

        $store->refresh();
    }

    /** Superadmin melepas kasir aktif (mis. HP hilang). Login berikutnya mengklaim ulang. */
    public function release(Store $store, User $by, ?Request $request = null): void
    {
        if (! $store->active_device_id) {
            return;
        }

        Tenant::run($store->id, function () use ($store, $by, $request) {
            StoreDevice::whereKey($store->active_device_id)->update(['deactivated_at' => now()]);

            DeviceSwitchLog::create([
                'from_store_device_id' => $store->active_device_id,
                'to_store_device_id' => null,
                'user_id' => $by->id,
                'source' => 'superadmin',
                'ip_address' => $request?->ip(),
            ]);
        });

        $store->active_device_id = null;
        $store->save();
    }

    /** Toko lain yang pernah memakai HP yang sama (deteksi trial berulang). */
    public function otherStoresUsingDevice(string $deviceId, int $exceptStoreId): \Illuminate\Support\Collection
    {
        $fromDevices = StoreDevice::withoutGlobalScope('store')
            ->where('device_id', $deviceId)
            ->where('store_id', '!=', $exceptStoreId)
            ->pluck('store_id');

        $fromRegistration = Store::where('registered_device_id', $deviceId)
            ->where('id', '!=', $exceptStoreId)
            ->pluck('id');

        return $fromDevices->merge($fromRegistration)->unique()->values();
    }
}
