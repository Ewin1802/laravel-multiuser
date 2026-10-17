<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockWriteOff;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * EXPIRE & BARANG KELUAR
 *
 * - Batch yang sudah kedaluwarsa (sudah otomatis tidak dijual) menunggu
 *   dicatat sebagai RUSAK atau RETUR.
 * - Batch yang mendekati expire (sesuai expiry_warning_days per produk).
 * - Catat barang keluar manual (rusak / retur) untuk produk apa pun.
 */
class ExpiryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $today = now()->startOfDay();

        $active = StockBatch::with('product:id,name,base_unit,expiry_warning_days')
            ->where('status', StockBatch::STATUS_ACTIVE)
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date')
            ->get();

        $expired = $active->filter(fn ($b) => $b->expiry_date->lt($today))->values();
        $soon = $active->filter(function ($b) use ($today) {
            $days = (int) ($b->product->expiry_warning_days ?? 30);

            return $b->expiry_date->gte($today) && $b->expiry_date->lte($today->copy()->addDays($days));
        })->values();

        $writeOffs = StockWriteOff::with(['product:id,name,base_unit', 'batch:id,expiry_date', 'user:id,name'])
            ->latest('occurred_at')
            ->paginate(15)
            ->withQueryString();

        $products = Product::orderBy('name')->get(['id', 'name', 'base_unit', 'stock', 'track_expiry']);

        return view('pages.expiry.index', compact('expired', 'soon', 'writeOffs', 'products', 'today'));
    }

    /** Keluarkan sisa batch (kedaluwarsa / rusak / retur). */
    public function writeOffBatch(Request $request, int $batchId)
    {
        $data = $request->validate([
            'reason' => ['required', 'in:' . implode(',', array_keys(StockWriteOff::REASONS))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $batch = StockBatch::with('product')->findOrFail($batchId);

        DB::transaction(fn () => $this->inventory->writeOff(
            $batch->product,
            (float) $batch->remaining_quantity,
            $data['reason'],
            $batch,
            $data['note'] ?? null,
            Auth::id(),
        ));

        return back()->with('success', 'Sisa batch ' . $batch->product->name . ' dicatat sebagai '
            . strtolower(StockWriteOff::REASONS[$data['reason']]) . '.');
    }

    /** Barang keluar manual. Produk ber-expire: dipotong FEFO bila batch tidak dipilih. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', \App\Support\Tenant::exists('products')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'in:' . implode(',', array_keys(StockWriteOff::REASONS))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        // BUNGKUS, BOTOL, PCS... tidak bisa pecahan; hanya KG / LITER / METER dst.
        if (! \App\Support\UnitConverter::allowsDecimal($product->base_unit)
            && abs((float) $data['quantity'] - round((float) $data['quantity'])) > 0.0001) {
            return back()->withInput()->withErrors([
                'quantity' => "Jumlah {$product->name} harus bilangan bulat ({$product->base_unit}).",
            ]);
        }

        DB::transaction(function () use ($product, $data) {
            $qty = round((float) $data['quantity'], 3);

            if (! $product->track_expiry) {
                $this->inventory->writeOff($product, $qty, $data['reason'], null, $data['note'] ?? null, Auth::id());

                return;
            }

            // produk ber-expire: keluarkan dari batch FEFO (expire terdekat dulu)
            $batches = StockBatch::where('product_id', $product->id)
                ->where('status', StockBatch::STATUS_ACTIVE)
                ->where('remaining_quantity', '>', 0)
                ->fefo()
                ->get();

            foreach ($batches as $batch) {
                if ($qty <= 0) {
                    break;
                }
                $take = min($qty, (float) $batch->remaining_quantity);
                $this->inventory->writeOff($product, $take, $data['reason'], $batch, $data['note'] ?? null, Auth::id());
                $qty = round($qty - $take, 3);
            }

            if ($qty > 0) {
                $this->inventory->writeOff($product, $qty, $data['reason'], null, $data['note'] ?? null, Auth::id());
            }
        });

        return back()->with('success', 'Barang keluar tercatat. Stok ' . $product->name . ' sudah dikurangi.');
    }
}
