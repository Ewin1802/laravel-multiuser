<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Services\DeviceManager;
use App\Services\StoreManager;
use App\Support\License;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * AUTENTIKASI APLIKASI KASIR
 *
 * Login WAJIB online. Setiap login mengirim device_id supaya server tahu
 * HP mana yang dipakai dan bisa menerbitkan token lisensi untuk HP itu.
 */
class AuthController extends Controller
{
    public function __construct(
        private StoreManager $stores,
        private DeviceManager $devices,
    ) {}

    /**
     * Pendaftaran toko baru dari aplikasi. Akun belum bisa dipakai
     * transaksi sampai superadmin menyetujui (trial 3 hari dimulai saat itu).
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:20', 'unique:users,phone_number'],
            'address' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $this->stores->register($data, $data['device_id'] ?? null);

        return response()->json([
            'status' => 'success',
            'code' => 'store_pending',
            'message' => 'Pendaftaran berhasil. Toko Anda menunggu persetujuan admin. '
                . 'Masa uji coba 3 hari dimulai setelah disetujui.',
            'data' => [
                'user' => $this->userPayload($user),
                'store' => ['id' => $user->store_id, 'name' => $data['store_name'], 'status' => Store::STATUS_PENDING],
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_id' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::with('store')->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'code' => 'invalid_credentials',
                'message' => 'Email atau password salah.',
            ], 401);
        }

        if ($user->isSuperadmin() || ! $user->store) {
            return response()->json([
                'status' => 'error',
                'code' => 'no_store',
                'message' => 'Akun superadmin tidak dipakai di aplikasi kasir. Gunakan panel web.',
            ], 403);
        }

        $store = $user->store;

        if ($user->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'code' => 'store_pending',
                'message' => 'Toko "' . $store->name . '" masih menunggu persetujuan admin.',
            ], 403);
        }

        $device = $this->devices->touch($store, $request->only('device_id', 'device_name', 'platform', 'app_version'));

        // Toko belum punya kasir aktif → HP ini langsung jadi kasir.
        $this->devices->claimIfFree($store, $device, $user, $request);
        $store->refresh();

        $token = $user->createToken('kasir:' . $device->device_id)->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => array_merge(
                ['token' => $token, 'user' => $this->userPayload($user)],
                $this->sessionPayload($store, $device),
            ),
        ]);
    }

    public function logout(Request $request)
    {
        // HP ini tidak menerima push lagi setelah logout
        \App\Models\PushToken::where('user_id', $request->user()->id)
            ->where('device_id', $request->header('X-Device-Id'))
            ->delete();

        $request->user()->currentAccessToken()?->delete();

        return response()->json(['status' => 'success', 'message' => 'Logged out']);
    }

    // ------------------------------------------------------------------

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'role' => $user->role,
            'store_id' => $user->store_id,
            'photo_url' => $user->photo_url,
        ];
    }

    /**
     * Data toko + status kasir + lisensi bertanda tangan. Dipakai juga
     * oleh LicenseController supaya bentuknya selalu sama.
     */
    public static function sessionPayload(Store $store, \App\Models\StoreDevice $device): array
    {
        $active = $store->active_device_id
            ? Tenant::run($store->id, fn () => \App\Models\StoreDevice::find($store->active_device_id))
            : null;

        $isCashier = $active && (int) $active->id === (int) $device->id;

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'phone' => $store->phone,
                'address' => $store->address,
                'logo_url' => $store->logo_url,
                'receipt_footer' => $store->receipt_footer,
                'bank_name' => $store->bank_name,
                'bank_account' => $store->bank_account,
                'bank_holder' => $store->bank_holder,
                'feature_draft_order' => (bool) $store->feature_draft_order,
            ],
            'device' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                // this_device | other_device | none
                'cashier_status' => $isCashier ? 'this_device' : ($active ? 'other_device' : 'none'),
                'active_device_name' => $active?->device_name,
                'active_device_last_seen' => $active?->last_seen_at?->toIso8601String(),
            ],
            'license' => License::issue($store, $device),
        ];
    }
}
