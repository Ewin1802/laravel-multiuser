<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate --seed
     *
     * Akun superadmin TIDAK di-seed. Buat dari terminal server:
     *     php artisan superadmin:create
     * (password diketik tersembunyi, tidak disimpan di .env / seeder)
     *
     * Data toko (kategori, produk, dst.) TIDAK di-seed: setiap toko mengisi
     * datanya sendiri. Untuk mencoba alur lengkap pakai toko contoh:
     *     php artisan db:seed --class=DemoStoreSeeder
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
