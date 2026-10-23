<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Kirim push notification lewat Firebase Cloud Messaging (HTTP v1).
 *
 * Tidak butuh package tambahan: token akses Google dibuat sendiri dari
 * file service account (JWT RS256) lalu disimpan di cache ±50 menit.
 *
 * File service account RAHASIA — simpan di luar folder public dan jangan
 * masuk git (lihat FIREBASE_CREDENTIALS di .env).
 *
 * Dua aplikasi, satu project Firebase:
 *
 *   Aplikasi KASIR  → lewat TOPIK (subscribe di HP setelah login)
 *     all_users     semua HP kasir
 *     store_{id}    HP kasir satu toko
 *
 *   Aplikasi ADMIN  → lewat TOKEN per HP (tabel push_tokens, app = admin).
 *     Sengaja TIDAK memakai topik: siapa pun bisa subscribe ke topik dari
 *     aplikasi modifikasi, sedangkan token admin hanya terdaftar setelah
 *     login superadmin di aplikasi admin.
 *
 * Channel Android: kasir "pengumuman", admin "toko_baru" (dibuat oleh
 * masing-masing aplikasi; kalau belum ada, Android memakai channel bawaan).
 */
class FcmService
{
    public const TOPIC_ALL = 'all_users';

    public const CHANNEL_KASIR = 'pengumuman';
    public const CHANNEL_ADMIN = 'toko_baru';

    private ?array $credentials = null;

    public static function storeTopic(int $storeId): string
    {
        return 'store_' . $storeId;
    }

    /** File service account ada & bisa dibaca. */
    public function enabled(): bool
    {
        $path = $this->credentialsPath();

        return $path !== '' && is_file($path) && is_readable($path);
    }

    public function projectId(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return config('services.fcm.project_id') ?: ($this->credentials()['project_id'] ?? null);
    }

    // ------------------------------------------------------------ kirim

    /**
     * @param  array<string, scalar>  $data  ikut terkirim ke aplikasi (semua jadi string)
     * @return array{ok: bool, id?: string, error?: string}
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = [], ?string $channel = null): array
    {
        if (! preg_match('/^[a-zA-Z0-9\-_.~%]{1,900}$/', $topic)) {
            return ['ok' => false, 'error' => 'Nama topik tidak valid.'];
        }

        return $this->send(['topic' => $topic], $title, $body, $data, $channel);
    }

    /** @return array{ok: bool, id?: string, error?: string} */
    public function sendToToken(string $token, string $title, string $body, array $data = [], ?string $channel = null): array
    {
        $result = $this->send(['token' => $token], $title, $body, $data, $channel);

        // token sudah tidak berlaku (aplikasi dihapus / data dibersihkan)
        if (! $result['ok'] && str_contains($result['error'] ?? '', 'UNREGISTERED')) {
            PushToken::where('token_hash', PushToken::hash($token))->delete();
        }

        return $result;
    }

    /**
     * Notif ke semua HP yang login di APLIKASI ADMIN (akun superadmin).
     * Kegagalan hanya dicatat di log, tidak pernah melempar error.
     *
     * @return int jumlah HP yang berhasil dikirimi
     */
    public function notifyAdmins(string $title, string $body, array $data = []): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $sent = 0;

        try {
            PushToken::query()
                ->where('app', PushToken::APP_ADMIN)
                ->whereHas('user', fn ($q) => $q->where('role', 'superadmin'))
                ->pluck('token')
                ->each(function ($token) use ($title, $body, $data, &$sent) {
                    if ($this->sendToToken($token, $title, $body, $data, self::CHANNEL_ADMIN)['ok']) {
                        $sent++;
                    }
                });
        } catch (Throwable $e) {
            Log::warning('FCM notifyAdmins gagal: ' . $e->getMessage());
        }

        return $sent;
    }

    /** @return array{ok: bool, id?: string, error?: string} */
    private function send(array $target, string $title, string $body, array $data, ?string $channel = null): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'FCM belum diatur (file service account tidak ditemukan).'];
        }

        $message = $target + [
            'notification' => [
                'title' => mb_substr($title, 0, 100),
                'body' => mb_substr($body, 0, 500),
            ],
            'data' => array_map('strval', $data + ['sent_at' => now()->toIso8601String()]),
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'channel_id' => $channel ?? self::CHANNEL_KASIR,
                    'sound' => 'default',
                ],
            ],
            'apns' => [
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ];

        try {
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(10)
                ->post(
                    'https://fcm.googleapis.com/v1/projects/' . $this->projectId() . '/messages:send',
                    ['message' => $message]
                );
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Tidak bisa menghubungi FCM: ' . $e->getMessage()];
        }

        if ($response->successful()) {
            return ['ok' => true, 'id' => (string) $response->json('name')];
        }

        // token akses kedaluwarsa lebih cepat dari perkiraan → buang cache
        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
        }

        $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
        $error = trim(($status ? $status . ': ' : '') . ($response->json('error.message') ?? $response->body()));

        return ['ok' => false, 'error' => mb_substr($error, 0, 500)];
    }

    // ------------------------------------------------ token akses Google

    private function accessToken(): string
    {
        return Cache::remember($this->cacheKey(), now()->addMinutes(50), function () {
            $c = $this->credentials();
            $now = time();

            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $b64(json_encode([
                'iss' => $c['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $c['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';
            if (! openssl_sign("$header.$claims", $signature, $c['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Kunci service account Firebase tidak valid.');
            }

            $response = Http::asForm()->timeout(10)->post($c['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "$header.$claims." . $b64($signature),
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Gagal mengambil token akses Google: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $json = json_decode((string) file_get_contents($this->credentialsPath()), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('File service account Firebase tidak lengkap.');
        }

        return $this->credentials = $json;
    }

    private function credentialsPath(): string
    {
        $path = (string) config('services.fcm.credentials', '');

        // path relatif dihitung dari folder project Laravel
        return ($path !== '' && ! str_starts_with($path, '/')) ? base_path($path) : $path;
    }

    private function cacheKey(): string
    {
        return 'fcm.access_token.' . md5($this->credentialsPath());
    }
}
