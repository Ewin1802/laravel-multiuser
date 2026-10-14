<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use App\Models\Product;
use App\Models\StockConversion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BUKA KEMASAN — memecah stok kemasan (GALON, PAIL, ROL, ...) menjadi
 * stok produk eceran (LITER, KG, METER, ...) dalam satu langkah, supaya
 * stok & nilai modal tidak terhitung dua kali.
 */
class StockConversionController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'base_unit', 'stock', 'cost_price', 'status']);

        // pasangan terakhir per produk kemasan → isi otomatis di form
        $lastPairs = StockConversion::query()
            ->whereNull('reverted_at')
            ->orderByDesc('id')
            ->get(['source_product_id', 'target_product_id', 'ratio'])
            ->unique('source_product_id')
            ->mapWithKeys(fn ($c) => [$c->source_product_id => [
                'target' => $c->target_product_id,
                'ratio' => (float) $c->ratio,
            ]]);

        $conversions = StockConversion::with(['source:id,name,base_unit', 'target:id,name,base_unit', 'user:id,name'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $monthStart = now()->startOfMonth();
        $summary = (object) [
            'month_count' => StockConversion::whereNull('reverted_at')->where('created_at', '>=', $monthStart)->count(),
            'month_value' => (float) StockConversion::whereNull('reverted_at')
                ->where('created_at', '>=', $monthStart)
                ->selectRaw('COALESCE(SUM(target_qty * COALESCE(unit_cost, 0)), 0) as v')
                ->value('v'),
            'pairs' => $lastPairs->count(),
        ];

        return view('pages.stock-conversions.index', compact('products', 'lastPairs', 'conversions', 'summary'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source_product_id' => ['required', 'integer', Tenant::exists('products')],
            'target_product_id' => ['required', 'integer', Tenant::exists('products'), 'different:source_product_id'],
            'source_qty' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'ratio' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'target_product_id.different' => 'Produk eceran harus berbeda dari produk kemasan.',
            'source_qty.min' => 'Jumlah kemasan yang dibuka minimal 0,01.',
            'ratio.min' => 'Isi per kemasan harus lebih dari 0.',
        ], [
            'source_product_id' => 'produk kemasan',
            'target_product_id' => 'produk eceran',
            'source_qty' => 'jumlah dibuka',
            'ratio' => 'isi per kemasan',
        ]);

        $conversion = DB::transaction(function () use ($data) {
            // kunci kedua produk (urut id → hindari deadlock)
            $ids = [(int) $data['source_product_id'], (int) $data['target_product_id']];
            sort($ids);
            $locked = Product::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $source = $locked[(int) $data['source_product_id']];
            $target = $locked[(int) $data['target_product_id']];

            $qty = round((float) $data['source_qty'], 2);
            $ratio = round((float) $data['ratio'], 3);
            $targetQty = round($qty * $ratio, 2);

            // produk ber-expire memakai batch → buka kemasan antar produk tidak
            // bisa melacak tanggal expire-nya. Pakai multi-satuan untuk produk itu.
            if ($source->track_expiry || $target->track_expiry) {
                throw ValidationException::withMessages([
                    'source_product_id' => 'Buka Kemasan tidak bisa dipakai untuk produk ber-expire. '
                        . 'Atur satuan jual (mis. DUS isi 24) di produknya.',
                ]);
            }

            if ((float) $source->stock + 0.0001 < $qty) {
                throw ValidationException::withMessages([
                    'source_qty' => 'Stok ' . $source->name . ' hanya '
                        . $this->qty($source->stock) . ' ' . ($source->base_unit ?: 'PCS') . '.',
                ]);
            }

            $unitCost = $source->cost_price !== null && (float) $source->cost_price > 0
                ? round((float) $source->cost_price / $ratio, 2)
                : null;

            $source->stock = round((float) $source->stock - $qty, 2);
            $source->save();

            $target->stock = round((float) $target->stock + $targetQty, 2);
            if ($unitCost !== null) {
                // sama seperti Stok Masuk: harga beli = harga terakhir
                $target->cost_price = $unitCost;
            }
            $target->save();

            return StockConversion::create([
                'source_product_id' => $source->id,
                'target_product_id' => $target->id,
                'source_qty' => $qty,
                'ratio' => $ratio,
                'target_qty' => $targetQty,
                'unit_cost' => $unitCost,
                'note' => $data['note'] ?? null,
                'user_id' => Auth::id(),
            ]);
        });

        $conversion->load('source', 'target');

        return redirect()
            ->route('stock-conversions.index')
            ->with('success', sprintf(
                '%s %s %s dibuka → %s %s %s ditambahkan.',
                $this->qty($conversion->source_qty),
                $conversion->source->base_unit ?: 'PCS',
                $conversion->source->name,
                $this->qty($conversion->target_qty),
                $conversion->target->base_unit ?: 'PCS',
                $conversion->target->name,
            ));
    }

    /** Batalkan (mis. salah pilih produk) — stok dikembalikan. */
    public function destroy(int $id)
    {
        DB::transaction(function () use ($id) {
            $conversion = StockConversion::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($conversion->isReverted()) {
                throw ValidationException::withMessages(['conversion' => 'Buka kemasan ini sudah dibatalkan.']);
            }

            $ids = [$conversion->source_product_id, $conversion->target_product_id];
            sort($ids);
            $locked = Product::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            $source = $locked[$conversion->source_product_id] ?? null;
            $target = $locked[$conversion->target_product_id] ?? null;

            if ($target && (float) $target->stock + 0.0001 < $conversion->target_qty) {
                throw ValidationException::withMessages([
                    'conversion' => 'Tidak bisa dibatalkan: sebagian ' . $target->name
                        . ' sudah terjual (stok tinggal ' . $this->qty($target->stock) . ').',
                ]);
            }

            if ($target) {
                $target->stock = round((float) $target->stock - $conversion->target_qty, 2);
                $target->save();
            }
            if ($source) {
                $source->stock = round((float) $source->stock + $conversion->source_qty, 2);
                $source->save();
            }

            $conversion->update(['reverted_at' => now()]);
        });

        return redirect()
            ->route('stock-conversions.index')
            ->with('success', 'Buka kemasan dibatalkan. Stok kedua produk sudah dikembalikan.');
    }

    private function qty($v): string
    {
        $v = (float) $v;

        return fmod($v, 1.0) == 0.0
            ? number_format($v, 0, ',', '.')
            : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }
}
