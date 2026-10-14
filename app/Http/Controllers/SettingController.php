<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Tampilkan form pengaturan website.
     *
     * Settings bersifat singleton (hanya 1 baris data),
     * jadi tidak ada index/create/destroy — hanya edit & update.
     */
    public function edit()
    {
        $setting = Setting::first() ?? new Setting();

        return view('pages.setting.edit', compact('setting'));
    }

    /**
     * Simpan perubahan pengaturan website.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            // Informasi Toko
            'store_name'         => ['required', 'string', 'max:255'],
            'store_tagline'      => ['nullable', 'string', 'max:255'],
            'store_description'  => ['nullable', 'string'],

            // Logo
            'logo'    => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],

            // Hero Landing Page
            'hero_title'    => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string'],
            'hero_button'   => ['nullable', 'string', 'max:100'],

            // Kontak
            'phone'       => ['nullable', 'string', 'max:30'],
            'whatsapp'    => ['nullable', 'string', 'max:30'],
            'email'       => ['nullable', 'email', 'max:255'],
            'address'     => ['nullable', 'string'],
            'google_maps' => ['nullable', 'string'],

            // Social Media
            'facebook'  => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'youtube'   => ['nullable', 'url', 'max:255'],
            'tiktok'    => ['nullable', 'url', 'max:255'],

            // SEO
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords'    => ['nullable', 'string', 'max:255'],

            // Footer
            'copyright' => ['nullable', 'string', 'max:255'],

            // Status Website
            'maintenance_mode' => ['nullable', 'boolean'],
            // Aturan langganan
            'trial_days'           => ['required', 'integer', 'min:1', 'max:60'],
            'grace_days'           => ['required', 'integer', 'min:1', 'max:60'],
            'offline_limit_hours'  => ['required', 'integer', 'min:6', 'max:720'],
            'offline_warn_hours'   => ['required', 'integer', 'min:1', 'max:720', 'lte:offline_limit_hours'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],

            // Server & Tagihan
            'server_provider'       => ['nullable', 'string', 'max:255'],
            'server_due_date'       => ['nullable', 'date'],
            'server_billing_months' => ['required', 'integer', 'in:1,3,6,12'],
            'server_cost'           => ['nullable', 'integer', 'min:0'],
            'server_remind_days'    => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $setting = Setting::first() ?? new Setting();

        // =====================================================
        // UPLOAD LOGO
        // =====================================================
        if ($request->hasFile('logo')) {
            if ($setting->logo) {
                Storage::disk('public')->delete($setting->logo);
            }

            $validated['logo'] = $request->file('logo')->store('settings', 'public');
        } else {
            unset($validated['logo']);
        }

        // =====================================================
        // UPLOAD FAVICON
        // =====================================================
        if ($request->hasFile('favicon')) {
            if ($setting->favicon) {
                Storage::disk('public')->delete($setting->favicon);
            }

            $validated['favicon'] = $request->file('favicon')->store('settings', 'public');
        } else {
            unset($validated['favicon']);
        }

        $validated['maintenance_mode'] = $request->boolean('maintenance_mode');

        $setting->fill($validated);
        $setting->save();

        return redirect()
            ->route('superadmin.settings.edit')
            ->with('success', 'Pengaturan berhasil diperbarui.');
    }

    /**
     * Tombol "Sudah dibayar" di bagian Server & Tagihan.
     *
     * Jatuh tempo dimajukan satu siklus (1 / 3 / 6 / 12 bulan) dari tanggal
     * jatuh tempo LAMA, bukan dari hari ini — supaya tanggal tagihannya tidak
     * bergeser walau dibayar lebih awal atau terlambat.
     */
    public function serverPaid()
    {
        $setting = Setting::first();

        if (! $setting || ! $setting->server_due_date) {
            return back()->with('error', 'Isi dulu tanggal jatuh tempo server di Pengaturan.');
        }

        $months = (int) ($setting->server_billing_months ?: 1);
        $next = Carbon::parse($setting->server_due_date)->startOfDay();

        // kalau sudah telat beberapa siklus, maju terus sampai tanggalnya di masa depan
        do {
            $next = $next->addMonthsNoOverflow($months);
        } while ($next->lte(Carbon::today()));

        DB::table('settings')->where('id', $setting->id)->update([
            'server_due_date' => $next->toDateString(),
            'updated_at' => now(),
        ]);

        return back()->with(
            'success',
            'Pembayaran server dicatat. Jatuh tempo berikutnya: ' . $next->translatedFormat('d F Y') . '.'
        );
    }
}
