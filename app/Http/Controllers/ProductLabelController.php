<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use App\Models\Product;
use App\Support\Barcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cetak label barcode produk.
 *
 *   GET products/labels        → pilih produk & jumlah label
 *   GET products/labels/print  → halaman cetak (tanpa sidebar/navbar)
 */
class ProductLabelController extends Controller
{
    public const SIZES = [
        'roll' => 'Printer label 50 × 30 mm (gulungan)',
        'a4' => 'Kertas stiker A4 — 3 × 8 (64 × 34 mm)',
    ];

    private const MAX_LABELS = 500;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $selected = $this->selectedQty($request);

        $products = Tenant::table('products')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select('products.id', 'products.name', 'products.code', 'products.barcode', 'products.price', 'products.stock', 'products.base_unit', 'categories.name as category_name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('products.name', 'like', "%{$search}%")
                        ->orWhere('products.code', 'like', "%{$search}%")
                        ->orWhere('products.barcode', $search);
                });
            })
            ->orderBy('products.name')
            ->limit(300)
            ->get();

        // produk terpilih yang tidak tampil di hasil cari tetap dibawa
        $hiddenSelected = collect($selected)->except($products->pluck('id')->all());

        $selectedProducts = Tenant::table('products')
            ->whereIn('id', array_keys($selected))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pages.products.labels', [
            'products' => $products,
            'selected' => $selected,
            'hiddenSelected' => $hiddenSelected,
            'selectedProducts' => $selectedProducts,
            'search' => $search,
            'sizes' => self::SIZES,
            'size' => $this->size($request),
            'showPrice' => $request->boolean('price', true),
            'totalLabels' => array_sum($selected),
        ]);
    }

    public function print(Request $request)
    {
        $selected = $this->selectedQty($request);

        if (! $selected) {
            return redirect()
                ->route('products.labels')
                ->with('error', 'Pilih minimal satu produk dan isi jumlah labelnya.');
        }

        $products = Product::whereIn('id', array_keys($selected))
            ->orderBy('name')
            ->get();

        $labels = [];

        foreach ($products as $product) {
            // produk lama yang belum punya barcode → dibuatkan sekarang
            if (blank($product->barcode)) {
                $product->barcode = Barcode::internal($product->id);
                $product->saveQuietly();
            }

            $svg = Barcode::svg($product->barcode, 40);

            for ($i = 0; $i < $selected[$product->id]; $i++) {
                $labels[] = ['product' => $product, 'svg' => $svg];
            }
        }

        $store = $request->user()?->store;

        return view('pages.products.labels-print', [
            'labels' => $labels,
            'size' => $this->size($request),
            'showPrice' => $request->boolean('price', true),
            'storeName' => $store->name ?? config('app.name'),
        ]);
    }

    /** qty[id] => jumlah, hanya yang > 0, total dibatasi supaya browser tidak berat. */
    private function selectedQty(Request $request): array
    {
        $result = [];
        $total = 0;

        foreach ((array) $request->query('qty', []) as $id => $qty) {
            $id = (int) $id;
            $qty = max(0, min(100, (int) $qty));

            if ($id <= 0 || $qty === 0) {
                continue;
            }

            $qty = min($qty, self::MAX_LABELS - $total);

            if ($qty <= 0) {
                break;
            }

            $result[$id] = $qty;
            $total += $qty;
        }

        return $result;
    }

    private function size(Request $request): string
    {
        $size = (string) $request->query('size', 'roll');

        return array_key_exists($size, self::SIZES) ? $size : 'roll';
    }
}
