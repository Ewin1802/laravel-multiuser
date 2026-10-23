<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Login aplikasi admin (khusus superadmin). */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // pesan sama untuk email salah / password salah / bukan superadmin
        if (! $user || $user->role !== 'superadmin' || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        $name = 'admin-app:' . mb_substr($data['device_name'] ?? 'HP admin', 0, 80);
        $token = $user->createToken($name, ['admin'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            ],
        ]);
    }

    /** Profil + angka ringkas untuk beranda aplikasi admin. */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'summary' => [
                    'pending_stores' => Store::where('status', Store::STATUS_PENDING)->count(),
                    'pending_payments' => SubscriptionPayment::withoutGlobalScope('store')->where('status', 'pending')->count(),
                    'stores_total' => Store::count(),
                    'kasir_devices' => PushToken::where('app', PushToken::APP_KASIR)->count(),
                ],
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        // HP ini berhenti menerima notif admin
        PushToken::where('access_token_id', $token->id)->delete();
        $token->delete();

        return response()->json(['status' => 'success']);
    }
}
