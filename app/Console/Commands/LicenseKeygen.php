<?php

namespace App\Console\Commands;

use App\Support\License;
use Illuminate\Console\Command;

class LicenseKeygen extends Command
{
    protected $signature = 'license:keygen {--force : Tampilkan kunci baru walau sudah ada di .env}';

    protected $description = 'Buat pasangan kunci Ed25519 untuk token lisensi aplikasi kasir';

    public function handle(): int
    {
        if (config('license.private_key') && ! $this->option('force')) {
            $this->warn('LICENSE_PRIVATE_KEY sudah ada. Mengganti kunci membuat semua HP menolak token baru');
            $this->warn('sampai aplikasinya diperbarui. Pakai --force bila memang ingin membuat kunci baru.');

            return self::FAILURE;
        }

        $keys = License::generateKeyPair();

        $this->line('Tambahkan ke .env server:');
        $this->newLine();
        $this->line('LICENSE_PRIVATE_KEY=' . $keys['private']);
        $this->line('LICENSE_PUBLIC_KEY=' . $keys['public']);
        $this->newLine();
        $this->line('Tempel kunci PUBLIK ini di Flutter: lib/core/license/license_keys.dart');
        $this->line($keys['public']);
        $this->newLine();
        $this->warn('Simpan kunci privat baik-baik dan jangan pernah dimasukkan ke aplikasi.');

        return self::SUCCESS;
    }
}
