<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate --seed
     *
     * Data toko (kategori, produk, dst.) TIDAK di-seed: setiap toko mengisi
     * datanya sendiri. Untuk mencoba alur lengkap pakai toko contoh:
     *     php artisan db:seed --class=DemoStoreSeeder
     */
    public function run(): void
    {
        $this->call([
            SuperadminSeeder::class,
            PlanSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
