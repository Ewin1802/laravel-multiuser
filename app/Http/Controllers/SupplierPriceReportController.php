<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use App\Support\SupplierPrices;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierPriceReportController extends Controller
{
    /** Pilihan periode: jumlah hari ke belakang (0 = semua riwayat) */
    private const PERIODS = [30 => '30 hari', 90 => '3 bulan', 180 => '6 bulan', 365 => '1 tahun', 0 => 'Semua'];

    public function __invoke(Request $request)
    {
        $period = (int) $request->input('period', 90);
        if (! array_key_exists($period, self::PERIODS)) {
            $period = 90;
        }

        $q = trim((string) $request->input('q'));
        $category_id = $request->input('category_id');
        $onlyMulti = $request->boolean('multi');
        $sort = $request->input('sort', 'saving');

        $from = $period > 0 ? now()->subDays($period)->toDateString() : null;
        $compare = SupplierPrices::compare($from);

        $products = Tenant::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('products.id', $compare->keys())
            ->when($q !== '', fn ($query) => $query->where('products.name', 'like', "%{$q}%"))
            ->when($category_id, fn ($query) => $query->where('products.category_id', $category_id))
            ->get([
                'products.id', 'products.name', 'products.base_unit', 'products.price',
                'products.stock', 'products.image', 'categories.name as category_name',
            ]);

        $rows = $products->map(function ($product) use ($compare) {
            $c = $compare[$product->id];
            $product->suppliers = $c->suppliers;
            $product->cheapest = $c->cheapest;
            $product->latest = $c->latest;
            $product->spread = $c->spread;
            $product->overpaid = $c->overpaid;
            $product->margin = (float) $product->price - $c->cheapest->last_price;

            return $product;
        });

        // ---------- ringkasan (sebelum filter "≥ 2 supplier") ----------
        $ranking = $rows
            ->filter(fn ($r) => $r->suppliers->count() > 1)
            ->groupBy(fn ($r) => $r->cheapest->supplier_name)
            ->map->count()
            ->sortDesc();

        $stats = (object) [
            'products' => $rows->count(),
            'multi' => $rows->filter(fn ($r) => $r->suppliers->count() > 1)->count(),
            'overpaid_count' => $rows->filter(fn ($r) => $r->overpaid > 0)->count(),
            'overpaid_sum' => $rows->sum('overpaid'),
            'ranking' => $ranking,
        ];

        if ($onlyMulti) {
            $rows = $rows->filter(fn ($r) => $r->suppliers->count() > 1);
        }

        $rows = match ($sort) {
            'name' => $rows->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            'overpaid' => $rows->sortByDesc('overpaid'),
            default => $rows->sortByDesc('spread'),
        };

        // ---------- pagination manual (datanya sudah berupa collection) ----------
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $categories = Tenant::table('categories')->orderBy('name')->get(['id', 'name']);
        $periods = self::PERIODS;

        return view('pages.reports.supplier-prices', compact(
            'items', 'stats', 'categories', 'periods', 'period', 'q', 'category_id', 'onlyMulti', 'sort'
        ));
    }
}
