<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Paket awal. Harga bisa diubah superadmin di menu Paket. */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::firstOrCreate(['code' => 'monthly'], [
            'name' => 'Bulanan',
            'duration_months' => 1,
            'price' => 100000,
            'is_active' => true,
        ]);

        Plan::firstOrCreate(['code' => 'yearly'], [
            'name' => 'Tahunan',
            'duration_months' => 12,
            'price' => 1000000,
            'is_active' => true,
        ]);
    }
}
