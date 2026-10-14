<?php

namespace App\Support;

/**
 * PEMBAGIAN BATCH (FEFO) — fungsi murni, tanpa database, mudah diuji.
 *
 * Urutan: batch dengan expiry_date paling dekat dulu; batch tanpa tanggal
 * paling akhir; bila sama, yang diterima lebih dulu.
 *
 * $preferred = pembagian yang sudah dibuat HP saat transaksi offline.
 * Server memakainya lebih dulu (supaya hasil di HP dan di server sama),
 * dibatasi sisa batch di server. Kekurangannya ditutup FEFO dari batch
 * lain. Bila semua batch habis, sisanya dilaporkan sebagai $shortage.
 */
class Fefo
{
    /**
     * @param  array<int, array{id:int, remaining:float, expiry_date:?string, received_at:?string}>  $batches
     *         batch yang BOLEH dipakai (sudah difilter: aktif, belum kedaluwarsa pada tanggal transaksi)
     * @param  array<int, array{batch_id:int, quantity:float}>  $preferred
     * @return array{allocations: array<int, array{batch_id:int, quantity:float}>, shortage: float}
     */
    public static function allocate(array $batches, float $quantity, array $preferred = []): array
    {
        $need = round($quantity, 3);
        $remaining = [];
        foreach ($batches as $b) {
            $remaining[(int) $b['id']] = round((float) $b['remaining'], 3);
        }

        $alloc = [];
        $take = function (int $id, float $qty) use (&$alloc, &$remaining, &$need) {
            $qty = round(min($qty, $remaining[$id] ?? 0, $need), 3);
            if ($qty <= 0) {
                return;
            }
            $alloc[$id] = round(($alloc[$id] ?? 0) + $qty, 3);
            $remaining[$id] = round($remaining[$id] - $qty, 3);
            $need = round($need - $qty, 3);
        };

        foreach ($preferred as $p) {
            if ($need <= 0) {
                break;
            }
            $take((int) $p['batch_id'], (float) $p['quantity']);
        }

        foreach (self::sort($batches) as $b) {
            if ($need <= 0) {
                break;
            }
            $take((int) $b['id'], $need);
        }

        $out = [];
        foreach ($alloc as $id => $qty) {
            $out[] = ['batch_id' => $id, 'quantity' => $qty];
        }

        return ['allocations' => $out, 'shortage' => max(0, $need)];
    }

    public static function sort(array $batches): array
    {
        usort($batches, function ($a, $b) {
            $ea = $a['expiry_date'] ?? null;
            $eb = $b['expiry_date'] ?? null;
            if ($ea !== $eb) {
                if ($ea === null) {
                    return 1;
                }
                if ($eb === null) {
                    return -1;
                }

                return strcmp($ea, $eb);
            }
            $c = strcmp((string) ($a['received_at'] ?? ''), (string) ($b['received_at'] ?? ''));

            return $c !== 0 ? $c : ((int) $a['id'] <=> (int) $b['id']);
        });

        return $batches;
    }
}
