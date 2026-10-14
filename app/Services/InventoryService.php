<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockInItem;
use App\Models\StockWriteOff;
use App\Support\Fefo;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * SEMUA PERUBAHAN STOK lewat sini, supaya aturan berikut selalu sama:
 *
 *  - Stok (products.stock) & sisa batch dalam SATUAN TERKECIL.
 *  - products.stock = stok fisik: + stok masuk, − penjualan, − barang keluar.
 *    Boleh minus (transaksi offline yang sudah terjadi tetap dicatat).
 *  - Produk track_expiry: penjualan memotong batch secara FEFO; modal =
 *    rata-rata modal batch yang terpakai.
 *  - Produk biasa: modal = products.cost_price (harga beli terakhir per
 *    satuan terkecil).
 *
 * Panggil di dalam DB::transaction().
 */
class InventoryService
{
    // ==================================================================
    // PENJUALAN
    // ==================================================================

    /**
     * Potong stok untuk satu baris order yang SUDAH disimpan (base_quantity
     * terisi). Mengisi cost_price & cost_total di baris itu.
     *
     * @param  array<int, array{batch_id:int, quantity:float}>  $deviceAllocations  pembagian batch dari HP
     * @return bool true bila stok server tidak cukup (order perlu ditandai stock_conflict)
     */
    public function applySale(OrderItem $item, CarbonInterface $soldAt, array $deviceAllocations = []): bool
    {
        /** @var Product $product */
        $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
        $qty = round((float) $item->base_quantity, 3);
        $conflict = false;

        if (! $product->track_expiry) {
            $conflict = (float) $product->stock < $qty;
            $cost = $product->cost_price !== null ? (float) $product->cost_price : null;

            $product->decrement('stock', $qty);

            $item->forceFill([
                'cost_price' => $cost,
                'cost_total' => $cost !== null ? round($cost * $qty, 2) : null,
            ])->save();

            return $conflict;
        }

        // ---------------- FEFO ----------------
        $batches = StockBatch::where('product_id', $product->id)
            ->sellable($soldAt)
            ->lockForUpdate()
            ->get();

        // Batch yang dipilih HP boleh dipakai walau menurut server sudah
        // kedaluwarsa per hari ini, asal belum kedaluwarsa pada tanggal jual.
        $result = Fefo::allocate(
            $batches->map(fn (StockBatch $b) => [
                'id' => $b->id,
                'remaining' => (float) $b->remaining_quantity,
                'expiry_date' => $b->expiry_date?->toDateString(),
                'received_at' => $b->received_at?->toDateString(),
            ])->all(),
            $qty,
            $deviceAllocations,
        );

        $costSum = 0.0;
        $byId = $batches->keyBy('id');

        foreach ($result['allocations'] as $a) {
            /** @var StockBatch $batch */
            $batch = $byId[$a['batch_id']];
            $batch->remaining_quantity = round((float) $batch->remaining_quantity - $a['quantity'], 3);
            if ($batch->remaining_quantity <= 0) {
                $batch->remaining_quantity = 0;
                $batch->status = StockBatch::STATUS_DEPLETED;
            }
            $batch->save();

            OrderItemBatch::create([
                'order_item_id' => $item->id,
                'stock_batch_id' => $batch->id,
                'quantity' => $a['quantity'],
                'cost_price' => $batch->cost_price,
            ]);

            $costSum += $a['quantity'] * (float) $batch->cost_price;
        }

        if ($result['shortage'] > 0) {
            $conflict = true;
            $fallbackCost = (float) ($product->cost_price ?? 0);

            OrderItemBatch::create([
                'order_item_id' => $item->id,
                'stock_batch_id' => null,
                'quantity' => $result['shortage'],
                'cost_price' => $fallbackCost,
            ]);

            $costSum += $result['shortage'] * $fallbackCost;
        }

        $product->decrement('stock', $qty);

        $item->forceFill([
            'cost_price' => $qty > 0 ? round($costSum / $qty, 4) : null,
            'cost_total' => round($costSum, 2),
        ])->save();

        return $conflict;
    }

    /** Kembalikan stok sebuah baris order (order dihapus / dikoreksi di web). */
    public function reverseSale(OrderItem $item): void
    {
        $product = Product::whereKey($item->product_id)->lockForUpdate()->first();

        foreach (OrderItemBatch::where('order_item_id', $item->id)->get() as $used) {
            if ($used->stock_batch_id) {
                $batch = StockBatch::whereKey($used->stock_batch_id)->lockForUpdate()->first();
                if ($batch) {
                    $batch->remaining_quantity = round((float) $batch->remaining_quantity + (float) $used->quantity, 3);
                    if ($batch->status === StockBatch::STATUS_DEPLETED) {
                        $batch->status = StockBatch::STATUS_ACTIVE;
                    }
                    $batch->save();
                }
            }
            $used->delete();
        }

        $product?->increment('stock', (float) $item->base_quantity);
    }

    // ==================================================================
    // STOK MASUK
    // ==================================================================

    /** Tambah stok dari satu baris stok masuk (+ batch bila produk ber-expire). */
    public function receive(StockInItem $line, CarbonInterface $receivedAt): void
    {
        $product = Product::whereKey($line->product_id)->lockForUpdate()->firstOrFail();

        if ($product->track_expiry) {
            if (! $line->expiry_date) {
                throw ValidationException::withMessages([
                    'items' => "Tanggal expire wajib diisi untuk {$product->name}.",
                ]);
            }

            StockBatch::create([
                'product_id' => $product->id,
                'stock_in_item_id' => $line->id,
                'expiry_date' => $line->expiry_date,
                'batch_code' => $line->batch_code,
                'received_at' => $receivedAt->toDateString(),
                'initial_quantity' => $line->base_quantity,
                'remaining_quantity' => $line->base_quantity,
                'cost_price' => $line->cost_per_base,
                'status' => StockBatch::STATUS_ACTIVE,
            ]);
        }

        $product->increment('stock', (float) $line->base_quantity);
        $this->refreshLatestCost($product);
    }

    /**
     * Batalkan satu baris stok masuk (nota diedit / dihapus). Ditolak bila
     * batch-nya sudah terpakai penjualan atau dikeluarkan.
     */
    public function unreceive(StockInItem $line): void
    {
        $product = Product::whereKey($line->product_id)->lockForUpdate()->first();
        $batch = StockBatch::where('stock_in_item_id', $line->id)->lockForUpdate()->first();

        if ($batch) {
            if (round((float) $batch->remaining_quantity, 3) < round((float) $batch->initial_quantity, 3)
                || $batch->status === StockBatch::STATUS_WRITTEN_OFF) {
                throw ValidationException::withMessages([
                    'items' => "Batch {$product?->name} (exp {$batch->expiry_date?->format('d/m/Y')}) sudah terpakai, "
                        . 'nota ini tidak bisa diubah/dihapus. Catat koreksi lewat barang keluar.',
                ]);
            }
            $batch->delete();
        }

        $product?->decrement('stock', (float) $line->base_quantity);
    }

    /** cost_price produk = modal per satuan terkecil dari stok masuk terakhir. */
    public function refreshLatestCost(Product $product): void
    {
        $latest = StockInItem::query()
            ->join('stock_ins', 'stock_ins.id', '=', 'stock_in_items.stock_in_id')
            ->where('stock_in_items.product_id', $product->id)
            ->orderByDesc('stock_ins.received_at')
            ->orderByDesc('stock_in_items.id')
            ->value('stock_in_items.cost_per_base');

        if ($latest !== null) {
            $product->forceFill(['cost_price' => $latest])->saveQuietly();
        }
    }

    // ==================================================================
    // BARANG KELUAR (kedaluwarsa / rusak / retur)
    // ==================================================================

    public function writeOff(
        Product $product,
        float $quantity,
        string $reason,
        ?StockBatch $batch = null,
        ?string $note = null,
        ?int $userId = null,
        ?CarbonInterface $at = null,
        ?string $clientUuid = null,
    ): StockWriteOff {
        if (! array_key_exists($reason, StockWriteOff::REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Alasan tidak dikenal.']);
        }

        $quantity = round($quantity, 3);
        $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        $cost = $product->cost_price;

        if ($batch) {
            $batch = StockBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $quantity = min($quantity, (float) $batch->remaining_quantity);
            $cost = $batch->cost_price;

            $batch->remaining_quantity = round((float) $batch->remaining_quantity - $quantity, 3);
            if ($batch->remaining_quantity <= 0) {
                $batch->remaining_quantity = 0;
                $batch->status = StockBatch::STATUS_WRITTEN_OFF;
            }
            $batch->save();
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah barang keluar harus lebih dari 0.']);
        }

        $product->decrement('stock', $quantity);

        return StockWriteOff::create([
            'client_uuid' => $clientUuid,
            'product_id' => $product->id,
            'stock_batch_id' => $batch?->id,
            'quantity' => $quantity,
            'cost_price' => $cost,
            'reason' => $reason,
            'note' => $note,
            'user_id' => $userId,
            'occurred_at' => $at ?? now(),
        ]);
    }

    // ==================================================================
    // INFO STOK
    // ==================================================================

    /** Stok yang bisa dijual (produk ber-expire: hanya batch belum kedaluwarsa). */
    public function sellableStock(Product $product, ?CarbonInterface $date = null): float
    {
        if (! $product->track_expiry) {
            return (float) $product->stock;
        }

        return (float) StockBatch::where('product_id', $product->id)->sellable($date)->sum('remaining_quantity');
    }
}
