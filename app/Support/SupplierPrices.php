<?php

namespace App\Support;

use App\Support\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Perbandingan harga beli antar supplier, dihitung dari riwayat Stok Masuk.
 *
 * "Termurah" = supplier dengan HARGA BELI TERAKHIR paling rendah
 * (paling mendekati harga kalau pesan lagi sekarang).
 */
class SupplierPrices
{
    /**
     * @return Collection<int, object> dikunci product_id. Tiap isi:
     *   suppliers      → daftar supplier, urut dari harga terakhir termurah
     *   cheapest       → supplier termurah
     *   latest         → pembelian paling akhir (dari supplier mana pun)
     *   spread         → selisih termahal − termurah (per satuan)
     *   overpaid       → selisih pembelian terakhir − termurah (0 kalau sudah termurah)
     */
    public static function compare(?string $from = null, ?string $to = null): Collection
    {
        $rows = Tenant::table('stock_in_items as i')
            ->join('stock_ins as s', 's.id', '=', 'i.stock_in_id')
            ->join('suppliers as sp', 'sp.id', '=', 's.supplier_id')
            ->when($from, fn ($q) => $q->whereDate('s.received_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('s.received_at', '<=', $to))
            ->orderByDesc('s.received_at')
            ->orderByDesc('i.id')
            ->get([
                'i.product_id', 'i.base_quantity as quantity', 'i.cost_per_base as cost_price', // dibandingkan per satuan terkecil
                's.supplier_id', 's.received_at', 'sp.name as supplier_name',
            ]);

        return $rows->groupBy('product_id')->map(function (Collection $items) {
            $suppliers = $items->groupBy('supplier_id')->map(function (Collection $group) {
                $last = $group->first(); // sudah urut terbaru dulu
                $prices = $group->map(fn ($r) => (float) $r->cost_price);
                $qty = (float) $group->sum(fn ($r) => (float) $r->quantity);
                $value = (float) $group->sum(fn ($r) => (float) $r->quantity * (float) $r->cost_price);

                return (object) [
                    'supplier_id' => (int) $last->supplier_id,
                    'supplier_name' => $last->supplier_name,
                    'last_price' => (float) $last->cost_price,
                    'last_date' => $last->received_at,
                    'avg_price' => $qty > 0 ? $value / $qty : (float) $last->cost_price,
                    'min_price' => $prices->min(),
                    'max_price' => $prices->max(),
                    'total_qty' => $qty,
                    'times' => $group->count(),
                ];
            })->sort(function ($a, $b) {
                return [$a->last_price, $b->last_date] <=> [$b->last_price, $a->last_date];
            })->values();

            $cheapest = $suppliers->first();
            $latest = $items->first();
            $latestPrice = (float) $latest->cost_price;

            return (object) [
                'suppliers' => $suppliers,
                'cheapest' => $cheapest,
                'latest' => (object) [
                    'supplier_id' => (int) $latest->supplier_id,
                    'supplier_name' => $latest->supplier_name,
                    'price' => $latestPrice,
                    'date' => $latest->received_at,
                ],
                'spread' => $suppliers->max('last_price') - $cheapest->last_price,
                'overpaid' => max(0, $latestPrice - $cheapest->last_price),
            ];
        });
    }
}
