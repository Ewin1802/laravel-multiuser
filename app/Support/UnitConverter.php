<?php

namespace App\Support;

/**
 * Konversi multi-satuan. Semua stok disimpan dalam satuan terkecil.
 */
class UnitConverter
{
    /**
     * Uraikan jumlah satuan terkecil ke satuan-satuan produk, dari terbesar.
     *
     *   breakdown(605, [BATANG=1, BUNGKUS=16, SLOP=160])
     *   605 = 3×160 + 7×16 + 13 → "3 SLOP, 7 BUNGKUS, 13 BATANG"
     *
     * @param  iterable  $units  objek/array dengan name & conversion
     */
    public static function breakdown(float $baseQty, iterable $units, ?string $baseUnit = null): string
    {
        $list = [];
        foreach ($units as $u) {
            $name = is_array($u) ? $u['name'] : $u->name;
            $conv = (float) (is_array($u) ? $u['conversion'] : $u->conversion);
            $active = is_array($u) ? ($u['is_active'] ?? true) : ($u->is_active ?? true);
            if ($conv > 0 && $active) {
                $list[] = ['name' => $name, 'conversion' => $conv];
            }
        }

        $baseName = $baseUnit;
        if ($baseName === null) {
            foreach ($list as $u) {
                if ($u['conversion'] == 1.0) {
                    $baseName = $u['name'];
                }
            }
        }
        $baseName ??= '';

        if (! $list) {
            return self::num($baseQty) . ' ' . $baseName;
        }

        usort($list, fn ($a, $b) => $b['conversion'] <=> $a['conversion']);

        $negative = $baseQty < 0;
        $rest = round(abs($baseQty), 3);
        $parts = [];

        foreach ($list as $unit) {
            if ($unit['conversion'] <= 1) {
                continue;
            }
            $count = floor(($rest + 1e-9) / $unit['conversion']);
            if ($count >= 1) {
                $parts[] = self::num($count) . ' ' . $unit['name'];
                $rest = round($rest - $count * $unit['conversion'], 3);
            }
        }

        if ($rest > 0 || ! $parts) {
            $parts[] = self::num($rest) . ' ' . $baseName;
        }

        return ($negative ? '-' : '') . implode(', ', $parts);
    }

    public static function toBase(float $qty, float $conversion): float
    {
        return round($qty * ($conversion > 0 ? $conversion : 1), 3);
    }

    public static function num(float $n): string
    {
        $s = number_format($n, 3, ',', '.');

        return rtrim(rtrim($s, '0'), ',');
    }
}
