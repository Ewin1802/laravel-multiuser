<?php

namespace App\Http\Controllers;

use App\Support\ProductMatcher;
use App\Support\Tenant;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockInItem;
use App\Services\InventoryService;
use App\Services\ProductUnitService;
use App\Support\Barcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;

class ProductController extends Controller
{
    public function __construct(
        private ProductUnitService $unitService,
        private InventoryService $inventory,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['units' => fn ($q) => $q->where('is_active', true)])
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('products.*', 'categories.name as category_name')
            // cari nama, kode part, atau barcode (termasuk barcode satuan lain)
            ->when($request->name, function ($query, $name) {
                $query->where(function ($q) use ($name) {
                    $q->where('products.name', 'like', "%{$name}%")
                        ->orWhere('products.code', 'like', "%{$name}%")
                        ->orWhere('products.barcode', trim($name))
                        ->orWhereIn('products.id', \App\Models\ProductUnit::where('barcode', trim($name))->select('product_id'));
                });
            })
            ->orderByDesc('products.id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.products.index', compact('products'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $categories = Tenant::table('categories')
            ->orderBy('name')
            ->get();

        return view('pages.products.create', compact('categories'));
    }

    /**
     * Store new product.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|max:255',
            'description'   => 'required',
            'price'         => 'required|numeric|min:0',
            'category_id'   => ['required', Tenant::exists('categories')],
            'stock'         => 'required|numeric|min:0',
            'status'        => 'required|boolean',
            'is_favorite'   => 'required|boolean',
            'base_unit'     => 'required|string|max:20',
            'image'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'cost_price'    => 'nullable|numeric|min:0',
            'confirm_duplicate' => 'nullable|boolean',
            ...$this->extraRules(),
            ...$this->codeRules(),
        ], $this->codeMessages());

        // Cegah produk dobel (mis. lupa sudah pernah di-import). Nama yang
        // hampir sama harus dikonfirmasi dulu lewat centang "produk berbeda".
        if (! $request->boolean('confirm_duplicate')) {
            $strong = array_values(array_filter(
                ProductMatcher::find((string) $request->name, null, 3),
                fn ($m) => $m['score'] >= ProductMatcher::STRONG,
            ));

            if ($strong) {
                return back()->withInput()
                    ->withErrors(['name' => 'Produk "' . $strong[0]['product']->name . '" sudah ada. '
                        . 'Buka produk itu, atau centang "Ini produk berbeda" untuk tetap menyimpan.'])
                    ->with('similar_products', $this->similarPayload($strong));
            }
        }

        $product = new Product();

        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        // modal per satuan terkecil; boleh kosong (terisi otomatis dari Stok Masuk)
        $product->cost_price = $request->filled('cost_price') ? $request->cost_price : null;
        $product->category_id = $request->category_id;
        $product->track_expiry = $request->boolean('track_expiry');
        $product->expiry_warning_days = (int) $request->input('expiry_warning_days', 30);
        // produk ber-expire: stok awal lewat Stok Masuk (supaya punya batch)
        $product->stock = $product->track_expiry ? 0 : $request->stock;
        $product->status = $request->status;
        $product->is_favorite = $request->is_favorite;
        $product->base_unit = strtoupper(trim($request->base_unit));
        $product->code = Barcode::normalize($request->code);
        // kosong → kode toko dibuat otomatis setelah tersimpan
        $product->barcode = Barcode::normalize($request->barcode);

        DB::transaction(function () use ($product, $request) {
            $product->save();
            $product->refresh();
            $this->unitService->sync($product, $request->input('units', []));
        });

        // upload gambar
        if ($request->hasFile('image')) {

            $image = $request->file('image');

            $filename = $product->id . '.' . $image->getClientOriginalExtension();

            $image->storeAs(
                'products',
                $filename,
                'public'
            );

            $product->image = 'storage/products/' . $filename;

            $product->save();
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $product = Product::findOrFail($id);

        $categories = Tenant::table('categories')
            ->orderBy('name')
            ->get();

        return view('pages.products.edit', compact(
            'product',
            'categories'
        ));
    }

    /**
     * Update product.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'          => 'required|max:255',
            'description'   => 'required',
            'price'         => 'required|numeric|min:0',
            'category_id'   => ['required', Tenant::exists('categories')],
            // boleh minus (oversell saat HP offline); angka minus BARU ditolak di bawah
            'stock'         => 'required|numeric',
            'status'        => 'required|boolean',
            'is_favorite'   => 'required|boolean',
            'base_unit'     => 'required|string|max:20',
            'image'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'opening_expiry_date' => 'nullable|date',
            'cost_price'    => 'nullable|numeric|min:0',
            ...$this->extraRules(),
            ...$this->codeRules($id),
        ], $this->codeMessages());

        $product = Product::findOrFail($id);
        $wasTracking = (bool) $product->track_expiry;

        if (! $wasTracking && (float) $request->stock < 0
            && round((float) $request->stock, 3) !== round((float) $product->stock, 3)) {
            return back()->withInput()->withErrors(['stock' => 'Stok tidak boleh diisi minus.']);
        }
        $nowTracking = $request->boolean('track_expiry');

        // mengaktifkan expire untuk produk yang sudah punya stok → stok itu
        // dijadikan satu batch, jadi tanggal expire-nya wajib diisi
        if (! $wasTracking && $nowTracking && (float) $product->stock > 0 && ! $request->filled('opening_expiry_date')) {
            return back()->withInput()->withErrors([
                'opening_expiry_date' => 'Isi tanggal expire untuk stok yang sudah ada.',
            ]);
        }

        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $oldCost = $product->cost_price;
        if ($request->has('cost_price')) {
            $product->cost_price = $request->filled('cost_price') ? $request->cost_price : null;
        }
        $product->category_id = $request->category_id;
        // produk ber-expire: stok hanya berubah lewat stok masuk / barang keluar
        if (! $wasTracking) {
            $product->stock = $request->stock;
        }
        $product->status = $request->status;
        $product->base_unit = strtoupper(trim($request->base_unit));
        $product->is_favorite = $request->is_favorite;
        $product->track_expiry = $nowTracking;
        $product->expiry_warning_days = (int) $request->input('expiry_warning_days', $product->expiry_warning_days ?: 30);
        $product->code = Barcode::normalize($request->code);
        // dikosongkan → kembali ke kode toko (trait HasBarcode)
        $product->barcode = Barcode::normalize($request->barcode);

        if ($request->hasFile('image')) {

            // hapus gambar lama
            if (
                $product->image &&
                Storage::disk('public')->exists(
                    str_replace('storage/', '', $product->image)
                )
            ) {

                Storage::disk('public')->delete(
                    str_replace('storage/', '', $product->image)
                );
            }

            $image = $request->file('image');

            $filename = $product->id . '.' . $image->getClientOriginalExtension();

            $image->storeAs(
                'products',
                $filename,
                'public'
            );

            $product->image = 'storage/products/' . $filename;
        }

        DB::transaction(function () use ($product, $request, $wasTracking, $nowTracking, $oldCost) {
            $product->save();
            $product->refresh();

            // modal baru diisi → batch yang belum punya modal (mis. stok awal) ikut memakainya
            if ($product->cost_price !== null && (float) $product->cost_price > 0
                && (float) $oldCost !== (float) $product->cost_price) {
                StockBatch::where('product_id', $product->id)
                    ->where('status', StockBatch::STATUS_ACTIVE)
                    ->where(fn ($q) => $q->whereNull('cost_price')->orWhere('cost_price', '<=', 0))
                    ->update(['cost_price' => $product->cost_price]);
            }
            $this->unitService->sync($product, $request->input('units', []));

            if (! $wasTracking && $nowTracking && (float) $product->stock > 0) {
                // stok lama → satu batch pembuka
                StockBatch::create([
                    'product_id' => $product->id,
                    'expiry_date' => $request->opening_expiry_date,
                    'received_at' => now()->toDateString(),
                    'initial_quantity' => $product->stock,
                    'remaining_quantity' => $product->stock,
                    'cost_price' => (float) ($product->cost_price ?? 0),
                    'batch_code' => 'STOK-AWAL',
                ]);
            }

            if ($wasTracking && ! $nowTracking) {
                // berhenti melacak expire: batch tidak dipakai lagi
                StockBatch::where('product_id', $product->id)
                    ->where('status', StockBatch::STATUS_ACTIVE)
                    ->update(['status' => StockBatch::STATUS_DEPLETED]);
            }
        });

        return redirect()
            ->route('products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Produk dengan nama mirip (dipanggil form saat mengetik nama).
     * GET /products/similar?name=...&exclude=ID
     */
    public function similar(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'exclude' => 'nullable|integer',
        ]);

        $matches = ProductMatcher::find((string) $request->input('name', ''), $request->integer('exclude') ?: null);

        return response()->json([
            'status' => 'success',
            'strong' => ProductMatcher::STRONG,
            'data' => $this->similarPayload($matches),
        ]);
    }

    private function similarPayload(array $matches): array
    {
        return array_map(fn ($m) => [
            'id' => $m['product']->id,
            'name' => $m['product']->name,
            'code' => $m['product']->code,
            'barcode' => $m['product']->barcode,
            'stock_text' => $m['product']->stockBreakdown(),
            'price' => (int) $m['product']->price,
            'has_cost' => (float) $m['product']->cost_price > 0,
            'active' => (bool) $m['product']->status,
            'score' => $m['score'],
            'edit_url' => route('products.edit', $m['product']->id),
            'stock_in_url' => route('stock-ins.create', ['product_id' => $m['product']->id]),
        ], $matches);
    }

    /**
     * Aturan kode part & barcode (dipakai store dan update).
     */
    private function codeRules($ignoreId = null): array
    {
        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Tenant::unique('products', 'code')->ignore($ignoreId),
            ],
            'barcode' => [
                'nullable', 'string', 'max:64',
                // hanya huruf/angka/tanda baca biasa — sama dengan yang bisa dicetak Code 128
                'regex:/^[\x20-\x7E]+$/',
                Tenant::unique('products', 'barcode')->ignore($ignoreId),
            ],
        ];
    }

    /** Satuan lain & expire. */
    private function extraRules(): array
    {
        return [
            'track_expiry' => 'nullable|boolean',
            'expiry_warning_days' => 'nullable|integer|min:1|max:365',
            'units' => 'nullable|array|max:10',
            'units.*.id' => 'nullable|integer',
            'units.*.name' => 'required|string|max:30',
            'units.*.conversion' => 'required|numeric|gt:1',
            'units.*.price' => 'required|numeric|min:0',
            'units.*.barcode' => ['nullable', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/'],
        ];
    }

    private function codeMessages(): array
    {
        return [
            'code.unique' => 'Kode part ini sudah dipakai produk lain.',
            'barcode.unique' => 'Barcode ini sudah dipakai produk lain.',
            'barcode.regex' => 'Barcode hanya boleh berisi huruf, angka, dan tanda baca biasa.',
        ];
    }

    /**
     * Hapus produk. Produk yang sudah punya riwayat penjualan atau stok
     * masuk tidak bisa dihapus (laporan tetap utuh) — nonaktifkan saja.
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if (OrderItem::where('product_id', $product->id)->exists()
            || StockInItem::where('product_id', $product->id)->exists()) {
            return redirect()
                ->route('products.index')
                ->with('error', 'Produk sudah punya riwayat penjualan / stok masuk, tidak dapat dihapus. Nonaktifkan saja produknya.');
        }

        try {
            $image = $product->image;
            DB::transaction(fn () => $product->delete());

            if ($image && Storage::disk('public')->exists(str_replace('storage/', '', $image))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $image));
            }

            return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus.');
        } catch (QueryException $e) {
            return redirect()
                ->route('products.index')
                ->with('error', 'Produk tidak dapat dihapus. Nonaktifkan saja produknya.');
        }
    }
}
