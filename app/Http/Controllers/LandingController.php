<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\Setting;

/**
 * Halaman depan aplikasi: menawarkan aplikasi kasir ke toko baru.
 * Isi teks (hero, kontak, SEO) diatur superadmin di Pengaturan.
 */
class LandingController extends Controller
{
    public function index()
    {
        return view('landing', [
            'setting' => Setting::first() ?? new Setting(),
            'plans' => Plan::where('is_active', true)->orderBy('duration_months')->get(),
            // promo aktif yang ditandai tampil di depan (kuota habis disaring)
            'promos' => PromoCode::forLanding()->get()
                ->filter(fn (PromoCode $promo) => $promo->status() === 'aktif')
                ->take(3)
                ->values(),
        ]);
    }

    /** Kebijakan privasi publik (dipakai juga untuk Google Play Console). */
    public function privacy()
    {
        return view('privacy', [
            'setting' => Setting::first() ?? new Setting(),
        ]);
    }
}
