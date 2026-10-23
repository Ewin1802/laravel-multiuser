<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Services\FcmService;
use Illuminate\Http\Request;

/**
 * Aplikasi kasir mendaftarkan token FCM setelah login. Jawaban berisi
 * daftar topik yang harus di-subscribe HP (semua pengguna + toko sendiri).
 */
class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();

        PushToken::updateOrCreate(
            ['token_hash' => PushToken::hash($data['token'])],
            [
                'token' => $data['token'],
                'user_id' => $user->id,
                'store_id' => $user->store_id,
                'app' => PushToken::APP_KASIR,
                'access_token_id' => $user->currentAccessToken()?->id,
                'device_id' => mb_substr((string) $request->header('X-Device-Id'), 0, 100) ?: null,
                'platform' => $data['platform'] ?? 'android',
                'app_version' => mb_substr((string) $request->header('X-App-Version'), 0, 30) ?: null,
                'last_seen_at' => now(),
            ]
        );

        $topics = [FcmService::TOPIC_ALL];
        if ($user->store_id) {
            $topics[] = FcmService::storeTopic((int) $user->store_id);
        }

        return response()->json([
            'status' => 'success',
            'data' => ['topics' => $topics],
        ]);
    }

    public function destroy(Request $request)
    {
        $token = (string) $request->input('token', '');

        PushToken::where('user_id', $request->user()->id)
            ->where('app', PushToken::APP_KASIR)
            ->when(
                $token !== '',
                fn ($q) => $q->where('token_hash', PushToken::hash($token)),
                fn ($q) => $q->where('device_id', $request->header('X-Device-Id'))
            )
            ->delete();

        return response()->json(['status' => 'success']);
    }
}
