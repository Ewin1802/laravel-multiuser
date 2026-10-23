<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;

/**
 * Aplikasi admin mendaftarkan token FCM setelah login. Notif "pendaftar
 * baru" hanya dikirim ke token yang terdaftar di sini.
 */
class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();

        PushToken::updateOrCreate(
            ['token_hash' => PushToken::hash($data['token'])],
            [
                'token' => $data['token'],
                'user_id' => $user->id,
                'store_id' => null,
                'app' => PushToken::APP_ADMIN,
                'access_token_id' => $user->currentAccessToken()->id,
                'device_id' => mb_substr((string) $request->header('X-Device-Id'), 0, 100) ?: null,
                'platform' => $data['platform'] ?? 'android',
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['status' => 'success']);
    }

    public function destroy(Request $request)
    {
        $token = (string) $request->input('token', '');

        PushToken::where('user_id', $request->user()->id)
            ->where('app', PushToken::APP_ADMIN)
            ->when(
                $token !== '',
                fn ($q) => $q->where('token_hash', PushToken::hash($token)),
                fn ($q) => $q->where('access_token_id', $request->user()->currentAccessToken()->id)
            )
            ->delete();

        return response()->json(['status' => 'success']);
    }
}
