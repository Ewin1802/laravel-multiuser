<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** Pengaturan platform (landing page + aturan langganan). */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(['id' => 1], [
            'store_name' => config('app.name', 'Kasir'),
            'store_tagline' => 'Aplikasi kasir Android untuk toko',
            'store_description' => 'Aplikasi kasir yang tetap jalan tanpa internet, dengan multi-satuan dan stok expire.',
            'hero_title' => 'Kasir toko yang <em>tetap jalan</em> walau internet mati',
            'hero_subtitle' => null,
            'hero_button' => 'Daftarkan toko',
            'copyright' => null,
            'maintenance_mode' => false,
            'server_billing_months' => 1,
            'server_remind_days' => 14,
            'trial_days' => 3,
            'grace_days' => 7,
            'offline_limit_hours' => 48,
            'offline_warn_hours' => 36,
            'payment_instructions' => "Transfer ke:\nBANK ... no. rek ...\na.n. ...\n\nLalu kirim bukti transfer di menu Langganan.",
        ]);
    }
}
