<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * Format tampilan Log Jam HP.
 *
 * Jam dari HP dikirim sebagai teks ISO-8601:
 *   - "2026-10-14T15:08:30.884"      → jam lokal HP (tanpa zona)
 *   - "2026-10-15T07:08:30.822Z"     → UTC
 * Keduanya ditampilkan dalam zona waktu aplikasi (config app.timezone).
 */
class ClockFormat
{
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public static function parse(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $tz = config('app.timezone');

        try {
            // teks tanpa zona dianggap jam lokal (zona aplikasi)
            return Carbon::parse($value, $tz)->setTimezone($tz);
        } catch (Throwable) {
            return null;
        }
    }

    /** "Rab, 14 Okt 2026" */
    public static function date(?Carbon $t): string
    {
        if (! $t) {
            return '-';
        }

        return mb_substr(self::HARI[$t->dayOfWeek], 0, 3) . ', ' . $t->day . ' ' . self::BULAN[$t->month - 1] . ' ' . $t->year;
    }

    /** "15:08:30" */
    public static function time(?Carbon $t): string
    {
        return $t ? $t->format('H:i:s') : '';
    }

    /**
     * Selisih jam HP terhadap jam asli, dalam kata.
     * Negatif = jam HP mundur (di belakang jam asli), positif = maju.
     *
     * @return array{text: string, tone: string}
     */
    public static function drift(?int $seconds): array
    {
        if ($seconds === null) {
            return ['text' => '-', 'tone' => ''];
        }

        $abs = abs($seconds);
        if ($abs < 60) {
            return ['text' => 'kurang dari 1 menit', 'tone' => ''];
        }

        $days = intdiv($abs, 86400);
        $hours = intdiv($abs % 86400, 3600);
        $mins = intdiv($abs % 3600, 60);

        $parts = [];
        if ($days) {
            $parts[] = "{$days} hari";
        }
        if ($hours) {
            $parts[] = "{$hours} jam";
        }
        if ($mins && ! $days) {
            $parts[] = "{$mins} mnt";
        }

        return [
            'text' => ($seconds < 0 ? 'mundur ' : 'maju ') . implode(' ', $parts),
            'tone' => $seconds < 0 ? 'tone-red' : 'tone-amber',
        ];
    }
}
