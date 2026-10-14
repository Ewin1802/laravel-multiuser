<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Status jatuh tempo pembayaran server / VPS, dihitung dari tabel settings.
 * Dipakai peringatan di navbar dan bagian "Server" di halaman Pengaturan.
 */
class ServerBilling
{
    public const CYCLES = [1 => 'Bulanan', 3 => '3 bulan', 6 => '6 bulan', 12 => 'Tahunan'];

    /**
     * @return object|null  null kalau tanggal jatuh tempo belum diisi.
     *   due        Carbon   tanggal jatuh tempo
     *   days_left  int      sisa hari (negatif = sudah lewat, 0 = hari ini)
     *   level      string   ok | soon | urgent | overdue
     *   show       bool     true kalau sudah waktunya diingatkan
     *   label      string   "12 hari lagi" · "Hari ini" · "Telat 3 hari"
     */
    public static function status(?object $setting): ?object
    {
        $raw = $setting->server_due_date ?? null;
        if (! $raw) {
            return null;
        }

        try {
            $due = Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        $daysLeft = (int) round(Carbon::today()->diffInDays($due, false));
        $remindDays = max(1, (int) ($setting->server_remind_days ?? 14));

        $level = match (true) {
            $daysLeft < 0 => 'overdue',
            $daysLeft <= 3 => 'urgent',
            $daysLeft <= $remindDays => 'soon',
            default => 'ok',
        };

        $label = match (true) {
            $daysLeft < 0 => 'Telat ' . abs($daysLeft) . ' hari',
            $daysLeft === 0 => 'Hari ini',
            $daysLeft === 1 => 'Besok',
            default => $daysLeft . ' hari lagi',
        };

        return (object) [
            'due' => $due,
            'days_left' => $daysLeft,
            'level' => $level,
            'show' => $level !== 'ok',
            'label' => $label,
            'provider' => $setting->server_provider ?? null,
            'cost' => $setting->server_cost ?? null,
            'months' => (int) ($setting->server_billing_months ?? 1) ?: 1,
        ];
    }
}
