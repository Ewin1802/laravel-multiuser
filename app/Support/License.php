<?php

namespace App\Support;

use App\Models\Store;
use App\Models\StoreDevice;
use RuntimeException;

/**
 * TOKEN LISENSI untuk aplikasi kasir.
 *
 * Format: base64url(json_payload) . "." . base64url(signature_ed25519)
 *
 * Isi payload (semua waktu dalam milidetik epoch UTC, JAM SERVER):
 *   v                  versi format (1)
 *   store_id, store_name
 *   device_id          token hanya berlaku untuk HP ini
 *   is_cashier         HP ini perangkat kasir aktif?
 *   state              pending | trial | active | grace | expired | suspended
 *   can_transact       boleh membuat transaksi baru?
 *   server_time        jam server saat token dibuat
 *   transact_until     batas transaksi baru (trial / paid_until + tenggang)
 *   ends_at            akhir masa aktif tanpa tenggang (untuk ditampilkan)
 *   trial_ends_at, paid_until
 *   offline_limit_ms   batas offline (48 jam)
 *   offline_warn_ms    mulai peringatan offline (36 jam)
 *   cashier_since      kapan HP ini menjadi kasir aktif
 */
class License
{
    public static function issue(Store $store, StoreDevice $device): array
    {
        $sub = Subscription::for($store);
        $rules = Subscription::rules();
        $isCashier = (int) $store->active_device_id === (int) $device->id;

        $payload = [
            'v' => 1,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'device_id' => $device->device_id,
            'is_cashier' => $isCashier,
            'state' => $sub->state(),
            'can_transact' => $sub->canTransact() && $isCashier,
            'server_time' => self::ms(now()),
            'transact_until' => self::ms($sub->transactUntil()),
            'ends_at' => self::ms($sub->endsAt()),
            'trial_ends_at' => self::ms($store->trial_ends_at),
            'paid_until' => self::ms($store->paid_until),
            'grace_days' => (int) ($store->grace_days ?: 7),
            'offline_limit_ms' => $rules->offline_limit_hours * 3600 * 1000,
            'offline_warn_ms' => $rules->offline_warn_hours * 3600 * 1000,
            'cashier_since' => $isCashier ? self::ms($device->activated_at) : null,
        ];

        return [
            'token' => self::sign($payload),
            'payload' => $payload, // salinan terbaca; HP WAJIB memakai isi token yang sudah diverifikasi
        ];
    }

    public static function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = sodium_crypto_sign_detached($json, self::secretKey());

        return self::b64($json) . '.' . self::b64($signature);
    }

    /** Verifikasi token (dipakai tes & bila server perlu membaca token dari HP). */
    public static function verify(string $token, ?string $publicKey = null): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$json, $sig] = [self::unb64($parts[0]), self::unb64($parts[1])];

        if ($json === false || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        $pk = $publicKey ?? base64_decode((string) config('license.public_key'), true);

        if (! $pk || ! sodium_crypto_sign_verify_detached($sig, $json, $pk)) {
            return null;
        }

        return json_decode($json, true);
    }

    /** @return array{private: string, public: string} base64 */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'private' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }

    private static function secretKey(): string
    {
        $key = base64_decode((string) config('license.private_key'), true);

        if (! $key || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException(
                'LICENSE_PRIVATE_KEY belum diatur atau tidak valid. Jalankan: php artisan license:keygen'
            );
        }

        return $key;
    }

    private static function ms($date): ?int
    {
        return $date ? (int) round($date->getTimestamp() * 1000 + (int) $date->format('v')) : null;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string|false
    {
        $s = strtr($s, '-_', '+/');

        return base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }
}
