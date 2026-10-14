<?php

namespace App\Http\Controllers\Api;

use App\Support\Tenant;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockInItem;
use App\Services\ProductUnitService;
use App\Support\Barcode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(private ProductUnitService $unitService) {}

    /**
     * Daftar produk untuk sinkron aplikasi kasir.
     *
     * Setiap produk membawa:
     *   units[]   semua satuan jual aktif (termasuk satuan terkecil, conversion 1)
     *   batches[] batch yang masih bisa dijual (produk track_expiry) — supaya
     *             FEFO tetap berjalan saat HP offline
     *   stock_text  stok terurai, mis. "3 SLOP, 7 BUNGKUS, 13 BATANG"
     */
    public function index()
    {
        $products = Product::with([
            'category',
            'units' => fn ($q) => $q->where('is_active', true),
            'batches' => fn ($q) => $q->where('status', StockBatch::STATUS_ACTIVE)
                ->where('remaining_quantity', '>', 0)
                ->fefo(),
        ])
            ->orderBy('id', 'desc')
            ->get();

        $data = $products->map(function (Product $p) {
            $row = $p->toArray();
            $row['stock_text'] = $p->stockBreakdown();
            $row['units'] = $p->units->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'conversion' => (float) $u->conversion,
                'price' => (float) $u->price,
                'barcode' => $u->barcode,
                'is_base' => (bool) $u->is_base,
            ])->values();
            $row['batches'] = $p->track_expiry
                ? $p->batches->map(fn ($b) => [
                    'id' => $b->id,
                    'expiry_date' => $b->expiry_date?->toDateString(),
                    'received_at' => $b->received_at?->toDateString(),
                    'remaining_quantity' => (float) $b->remaining_quantity,
                    'cost_price' => (float) $b->cost_price,
                    'batch_code' => $b->batch_code,
                ])->values()
                : [];

            return $row;
        });

        return response()->json([
            'success' => true,
            'message' => 'List Data Product',
            'server_time' => now()->toIso8601String(),
            'data' => $data,
        ], 200);
    }

    /**
     * Tambah produk dari aplikasi.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            // desimal boleh: selang per meter, oli per liter
            'stock' => 'required|numeric|min:0',
            'category_id' => ['required', Tenant::exists('categories')],
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'status' => 'required|in:1,0',
            'is_favorite' => 'required|in:1,0',
            'base_unit' => 'required|string|max:20',
            'track_expiry' => 'sometimes|in:1,0',
            'expiry_warning_days' => 'sometimes|integer|min:1|max:365',
            ...$this->unitRules(),
            ...$this->codeRules(),
        ]);

        $product = new Product();
        $product->name = $data['name'];
        $product->description = $data['description'] ?? null;
        $product->price = (int) $data['price'];
        $product->track_expiry = (bool) ($data['track_expiry'] ?? false);
        $product->expiry_warning_days = (int) ($data['expiry_warning_days'] ?? 30);
        // produk ber-expire: stok awal WAJIB lewat stok masuk (supaya ada batch)
        $product->stock = $product->track_expiry ? 0 : $data['stock'];
        $product->category_id = $data['category_id'];
        $product->status = (int) $data['status'];
        $product->is_favorite = (int) $data['is_favorite'];
        $product->base_unit = strtoupper(trim($data['base_unit']));
        $product->code = Barcode::normalize($data['code'] ?? null);
        // kosong → kode toko dibuat otomatis oleh model Product
        $product->barcode = Barcode::normalize($data['barcode'] ?? null);

        DB::transaction(function () use ($product, $request) {
            $product->save();
            $product->refresh(); // barcode otomatis dari model
            $this->unitService->sync($product, $request->input('units', []));
        });

        if ($request->hasFile('image')) {
            $product->image = $this->storeImage($request->file('image'), $product);
            $product->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan.',
            'data' => $product->fresh('category'),
        ], 201);
    }

    /**
     * Ubah produk dari aplikasi. Kolom yang tidak dikirim tidak diubah.
     */
    public function update(Request $request)
    {
        $request->validate(['id' => ['required', 'integer', Tenant::exists('products')]]);

        $product = Product::findOrFail($request->id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            // stok hanya bisa dikoreksi langsung untuk produk tanpa expire;
            // produk ber-expire berubah lewat stok masuk / barang keluar
            'stock' => 'sometimes|numeric|min:0',
            'category_id' => ['required', Tenant::exists('categories')],
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'base_unit' => 'required|string|max:20',
            'status' => 'sometimes|in:1,0',
            'is_favorite' => 'sometimes|in:1,0',
            'expiry_warning_days' => 'sometimes|integer|min:1|max:365',
            ...$this->unitRules(),
            ...$this->codeRules($product->id),
        ]);

        $product->name = $data['name'];
        $product->price = (int) $data['price'];
        $product->category_id = $data['category_id'];
        if ($request->has('stock') && ! $product->track_expiry) {
            $product->stock = $data['stock'];
        }
        if ($request->has('expiry_warning_days')) {
            $product->expiry_warning_days = (int) $data['expiry_warning_days'];
        }
        $product->base_unit = strtoupper(trim($data['base_unit']));

        if ($request->has('description')) {
            $product->description = $data['description'];
        }

        if ($request->has('status')) {
            $product->status = (int) $data['status'];
        }

        if ($request->has('is_favorite')) {
            $product->is_favorite = (int) $data['is_favorite'];
        }

        if ($request->has('code')) {
            $product->code = Barcode::normalize($data['code'] ?? null);
        }

        if ($request->has('barcode')) {
            // dikosongkan → kembali ke kode toko (model Product)
            $product->barcode = Barcode::normalize($data['barcode'] ?? null);
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $product->image = $this->storeImage($request->file('image'), $product);
        }

        DB::transaction(function () use ($product, $request) {
            $product->save();
            $product->refresh();
            // daftar satuan hanya diganti bila dikirim
            $extra = $request->has('units')
                ? $request->input('units', [])
                : $product->units()->where('is_base', false)->where('is_active', true)->get()->toArray();
            $this->unitService->sync($product, $extra);
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product->fresh('category'),
        ]);
    }

    /**
     * Update HANYA status ketersediaan produk (aktif/nonaktif).
     *
     * Sengaja dipisah dari update(), supaya kasir yang cuma ingin
     * menandai produk "tidak tersedia sementara" tidak perlu (dan tidak
     * bisa) mengirim ulang seluruh data produk.
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer', Tenant::exists('products')],
            'status' => 'required|in:0,1',
        ]);

        $product = Product::findOrFail($request->id);

        $product->status = (int) $request->status;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Product status updated',
            'data' => $product,
        ]);
    }

    /**
     * Hapus produk. Produk yang sudah punya riwayat penjualan atau stok
     * masuk tidak bisa dihapus (laporan harus tetap utuh) — nonaktifkan saja.
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $image = $product->image;

        $used = OrderItem::where('product_id', $product->id)->exists()
            || StockInItem::where('product_id', $product->id)->exists();

        if ($used) {
            return response()->json([
                'success' => false,
                'message' => 'Produk sudah punya riwayat penjualan/stok masuk, tidak dapat dihapus. Nonaktifkan saja produknya.',
            ], 409);
        }

        try {
            DB::transaction(fn () => $product->delete());
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak dapat dihapus. Nonaktifkan saja produknya.',
            ], 409);
        }

        $this->deleteImage($image);

        return response()->json([
            'success' => true,
            'message' => 'Product Deleted',
        ]);
    }

    // ============================================================
    // HELPER
    // ============================================================

    /** Satuan tambahan: units[i][name|conversion|price|barcode|id]. */
    private function unitRules(): array
    {
        return [
            'units' => 'sometimes|array|max:10',
            'units.*.id' => 'nullable|integer',
            'units.*.name' => 'required_with:units|string|max:30',
            'units.*.conversion' => 'required_with:units|numeric|gt:1',
            'units.*.price' => 'required_with:units|numeric|min:0',
            'units.*.barcode' => ['nullable', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/'],
        ];
    }

    /** Kode part & barcode: opsional, tidak boleh kembar. */
    private function codeRules($ignoreId = null): array
    {
        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Tenant::unique('products', 'code')->ignore($ignoreId),
            ],
            'barcode' => [
                'nullable', 'string', 'max:64',
                'regex:/^[\x20-\x7E]+$/',
                Tenant::unique('products', 'barcode')->ignore($ignoreId),
            ],
        ];
    }

    /**
     * Simpan foto dengan pola yang sama seperti panel web:
     * file di storage/app/public/products/{id}.{ext}, kolom image = "storage/products/{id}.{ext}".
     */
    private function storeImage(UploadedFile $file, Product $product): string
    {
        $filename = $product->id . '.' . $file->getClientOriginalExtension();

        $file->storeAs('products', $filename, 'public');

        return 'storage/products/' . $filename;
    }

    /** Hapus foto lama, termasuk format lama ("products/x.jpg" atau nama file saja). */
    private function deleteImage(?string $image): void
    {
        if (! $image) {
            return;
        }

        $path = preg_replace('#^storage/#', '', $image);

        if (! str_contains($path, '/')) {
            $path = 'products/' . $path;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
