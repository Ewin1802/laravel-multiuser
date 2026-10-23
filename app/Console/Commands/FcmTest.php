<?php

namespace App\Console\Commands;

use App\Services\FcmService;
use Illuminate\Console\Command;

/**
 * Cek koneksi FCM dari server:
 *   php artisan fcm:test               → HP yang login di aplikasi ADMIN
 *   php artisan fcm:test store_5       → HP kasir toko #5
 *   php artisan fcm:test all_users     → SEMUA HP kasir (hati-hati!)
 *   php artisan fcm:test --dry         → hanya cek kredensial (tidak ada notif terkirim)
 */
class FcmTest extends Command
{
    protected $signature = 'fcm:test {topic? : Topik kasir tujuan (kosong = aplikasi admin)} {--dry : Hanya cek kredensial}';

    protected $description = 'Kirim push notification uji coba lewat Firebase Cloud Messaging';

    public function handle(FcmService $fcm): int
    {
        if (! $fcm->enabled()) {
            $this->error('File service account tidak ditemukan: ' . config('services.fcm.credentials'));
            $this->line('Atur FIREBASE_CREDENTIALS di .env lalu jalankan: php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('Project Firebase: ' . $fcm->projectId());

        $title = 'Tes notifikasi My Cashier';
        $body = 'Jika pesan ini muncul, push notification sudah berjalan.';

        // topik tanpa pelanggan tetap diterima FCM → cara aman cek kredensial
        if ($this->option('dry')) {
            $r = $fcm->sendToTopic('dry_run_check', $title, $body, ['type' => 'test']);
            $r['ok'] ? $this->info('Kredensial OK, FCM menerima pesan.') : $this->error('Gagal: ' . $r['error']);

            return $r['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $topic = $this->argument('topic');

        if (! $topic) {
            $sent = $fcm->notifyAdmins($title, $body, ['type' => 'test']);
            $sent > 0
                ? $this->info("Terkirim ke {$sent} HP aplikasi admin.")
                : $this->warn('Belum ada HP aplikasi admin yang terdaftar (login di aplikasi admin dulu). Coba: php artisan fcm:test --dry');

            return self::SUCCESS;
        }

        if ($topic === FcmService::TOPIC_ALL && ! $this->confirm('Ini akan mengirim notif ke SEMUA pengguna. Lanjut?')) {
            return self::SUCCESS;
        }

        $r = $fcm->sendToTopic($topic, $title, $body, ['type' => 'test']);
        $r['ok'] ? $this->info('Terkirim: ' . $r['id']) : $this->error('Gagal: ' . $r['error']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
