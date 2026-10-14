<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockWriteOff;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/stock-write-offs — barang keluar dari HP (kedaluwarsa, rusak,
 * retur), termasuk yang dicatat saat offline. Idempotent lewat client_uuid.
 *
 * items[*]: client_uuid, product_id, quantity (satuan terkecil),
 *           reason (expired|damaged|returned), stock_batch_id?, note?, occurred_at
 */
class StockWriteOffController extends Controller
{
    public function store(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:200'],
            'items.*.client_uuid' => ['required', 'uuid'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['required', 'in:' . implode(',', array_keys(StockWriteOff::REASONS))],
            'items.*.stock_batch_id' => ['nullable', 'integer'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.occurred_at' => ['nullable', 'string', 'max:40'],
        ]);

        $done = [];
        $failed = [];

        foreach ($data['items'] as $row) {
            if (StockWriteOff::withoutGlobalScope('store')->where('client_uuid', $row['client_uuid'])->exists()) {
                $done[] = $row['client_uuid'];
                continue;
            }

            $product = Product::find($row['product_id']);
            $batch = ! empty($row['stock_batch_id'])
                ? StockBatch::where('product_id', $row['product_id'])->find($row['stock_batch_id'])
                : null;

            if (! $product) {
                $failed[] = ['client_uuid' => $row['client_uuid'], 'message' => 'Produk tidak ditemukan.'];
                continue;
            }

            try {
                DB::transaction(fn () => $inventory->writeOff(
                    $product,
                    (float) $row['quantity'],
                    $row['reason'],
                    $batch,
                    $row['note'] ?? null,
                    $request->user()->id,
                    $this->parse($row['occurred_at'] ?? null),
                    $row['client_uuid'],
                ));
                $done[] = $row['client_uuid'];
            } catch (\Throwable $e) {
                $failed[] = ['client_uuid' => $row['client_uuid'], 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'status' => $failed ? 'partial' : 'success',
            'data' => ['done' => $done, 'failed' => $failed],
        ]);
    }

    private function parse(?string $v): Carbon
    {
        try {
            $t = OrderController::parseDeviceTime($v, request()->input('tz_offset')) ?? now();

            return $t->gt(now()->addMinutes(10)) ? now() : $t;
        } catch (\Throwable) {
            return now();
        }
    }
}
