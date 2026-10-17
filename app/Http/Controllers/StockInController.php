<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Services\InventoryService;
use App\Support\UnitConverter;
use Carbon\Carbon;
use App\Support\SupplierPrices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockInController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $start_date = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end_date = $request->input('end_date', now()->toDateString());
        $supplier_id = $request->input('supplier_id');
        $payment = in_array($request->input('payment'), ['paid', 'unpaid'], true) ? $request->input('payment') : null;
        $today = now()->toDateString();

        $bySupplier = fn ($q) => $q->when($supplier_id, fn ($q) => $q->where('stock_ins.supplier_id', $supplier_id));

        $base = $bySupplier(Tenant::table('stock_ins'))
            ->whereDate('stock_ins.received_at', '>=', $start_date)
            ->whereDate('stock_ins.received_at', '<=', $end_date)
            ->when($payment, fn ($q) => $q->where('stock_ins.payment_status', $payment));

        $summary = (clone $base)
            ->selectRaw('COUNT(*) as notes, COALESCE(SUM(total), 0) as total, COUNT(DISTINCT supplier_id) as suppliers')
            ->first();

        // UANG KELUAR = nota yang DIBAYAR pada periode ini (dilihat dari tanggal bayar,
        // bukan tanggal barang datang) → nota tempo baru dihitung saat dilunasi.
        $summary->cash_out = (float) $bySupplier(Tenant::table('stock_ins'))
            ->where('payment_status', 'paid')
            ->whereDate('paid_at', '>=', $start_date)
            ->whereDate('paid_at', '<=', $end_date)
            ->sum('total');

        // HUTANG = semua nota yang belum lunas, kapan pun barangnya datang
        $debt = $bySupplier(Tenant::table('stock_ins'))
            ->where('payment_status', 'unpaid')
            ->selectRaw(
                'COUNT(*) as notes, COALESCE(SUM(total), 0) as total, '
                . 'COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date < ? THEN total ELSE 0 END), 0) as overdue_total, '
                . 'COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END), 0) as overdue_notes',
                [$today, $today]
            )
            ->first();

        $stockIns = (clone $base)
            ->join('suppliers', 'suppliers.id', '=', 'stock_ins.supplier_id')
            ->leftJoin('users', 'users.id', '=', 'stock_ins.user_id')
            ->select(
                'stock_ins.*',
                'suppliers.name as supplier_name',
                'users.name as user_name',
                DB::raw('(SELECT COUNT(*) FROM stock_in_items WHERE stock_in_items.stock_in_id = stock_ins.id) as items_count')
            )
            ->orderByDesc('stock_ins.received_at')
            ->orderByDesc('stock_ins.id')
            ->paginate(15)
            ->withQueryString();

        $suppliers = Tenant::table('suppliers')->orderBy('name')->get(['id', 'name']);

        return view('pages.stock-ins.index', compact(
            'stockIns', 'suppliers', 'summary', 'debt', 'start_date', 'end_date', 'supplier_id', 'payment'
        ));
    }

    public function create()
    {
        return view('pages.stock-ins.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $stockIn = StockIn::create([
                'supplier_id' => $data['supplier_id'],
                'user_id' => Auth::id(),
                'invoice_number' => $data['invoice_number'] ?? null,
                'received_at' => $data['received_at'],
                'notes' => $data['notes'] ?? null,
                'total' => 0,
            ] + $this->paymentFields($data));

            $this->applyItems($stockIn, $data['items']);

            return $stockIn;
        });

        return redirect()
            ->route('stock-ins.index')
            ->with('success', 'Stok masuk tersimpan. Stok produk sudah bertambah.');
    }

    public function show(Request $request, $id)
    {
        /** @var StockIn $stockIn */
        $stockIn = StockIn::with(['supplier', 'user', 'items.product'])->findOrFail($id);

        // Dipanggil dari modal di halaman daftar (fetch) → kirim JSON saja
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'stock_in' => [
                    'id' => $stockIn->id,
                    'title' => $stockIn->invoice_number ?: 'Nota #' . $stockIn->id,
                    'supplier' => $stockIn->supplier->name ?? '-',
                    'received_at' => $stockIn->received_at->translatedFormat('l, d F Y'),
                    'invoice_number' => $stockIn->invoice_number,
                    'user' => $stockIn->user->name ?? '-',
                    'notes' => $stockIn->notes,
                    'total' => (float) $stockIn->total,
                    'is_paid' => $stockIn->isPaid(),
                    'paid_at' => $stockIn->paid_at?->translatedFormat('d M Y'),
                    'due_date' => $stockIn->due_date?->translatedFormat('d M Y'),
                    'is_overdue' => ! $stockIn->isPaid() && $stockIn->due_date && $stockIn->due_date->lt(today()),
                ],
                'items' => $stockIn->items->map(function ($item) {
                    $product = $item->product;

                    return [
                        'name' => $product->name ?? 'Produk dihapus',
                        'unit' => $item->unit_name ?: ($product->base_unit ?? 'PCS'),
                        'unit_conversion' => (float) $item->unit_conversion,
                        'base_quantity' => (float) $item->base_quantity,
                        'base_unit' => $product->base_unit ?? 'PCS',
                        'expiry_date' => $item->expiry_date?->translatedFormat('d M Y'),
                        'batch_code' => $item->batch_code,
                        'quantity' => (float) $item->quantity,
                        'cost_price' => (float) $item->cost_price,
                        'subtotal' => round($item->quantity * $item->cost_price, 2),
                        'sell_price' => $product ? (float) $product->price : null,
                    ];
                })->values(),
            ]);
        }

        return view('pages.stock-ins.show', compact('stockIn'));
    }

    public function edit($id)
    {
        /** @var StockIn $stockIn */
        $stockIn = StockIn::with('items')->findOrFail($id);

        return view('pages.stock-ins.edit', $this->formData() + compact('stockIn'));
    }

    public function update(Request $request, $id)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $id) {
            /** @var StockIn $stockIn */
            $stockIn = StockIn::with('items')->lockForUpdate()->findOrFail($id);

            // 1) batalkan isi lama (stok dikembalikan) → 2) terapkan isi baru
            $affected = $this->revertItems($stockIn);

            $stockIn->update([
                'supplier_id' => $data['supplier_id'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'received_at' => $data['received_at'],
                'notes' => $data['notes'] ?? null,
            ] + $this->paymentFields($data, $stockIn));

            $this->applyItems($stockIn, $data['items']);
            $this->refreshCostPrice($affected);
        });

        return redirect()
            ->route('stock-ins.index')
            ->with('success', 'Stok masuk diperbarui. Stok produk sudah disesuaikan.');
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            /** @var StockIn $stockIn */
            $stockIn = StockIn::with('items')->lockForUpdate()->findOrFail($id);

            $affected = $this->revertItems($stockIn);
            $stockIn->delete();

            $this->refreshCostPrice($affected);
        });

        return redirect()
            ->route('stock-ins.index')
            ->with('success', 'Stok masuk dihapus. Stok produk sudah dikurangi kembali.');
    }

    /** Tandai nota tempo sebagai LUNAS (uang keluar pada tanggal bayar) */
    public function pay(Request $request, $id)
    {
        $data = $request->validate([
            'paid_at' => 'nullable|date|before_or_equal:today',
        ]);

        /** @var StockIn $stockIn */
        $stockIn = StockIn::findOrFail($id);

        if (! $stockIn->isPaid()) {
            $stockIn->update([
                'payment_status' => 'paid',
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
            ]);
        }

        return back()->with('success', 'Nota ditandai lunas.');
    }

    // ============================================================
    // HELPER
    // ============================================================

    /**
     * Kolom pembayaran dari isian form.
     * - Lunas : paid_at = tanggal bayar lama (kalau sudah pernah lunas) atau tanggal terima.
     * - Tempo : paid_at dikosongkan, due_date diisi.
     */
    private function paymentFields(array $data, ?StockIn $existing = null): array
    {
        if ($data['payment_status'] === 'paid') {
            return [
                'payment_status' => 'paid',
                'due_date' => null,
                'paid_at' => $existing?->isPaid() && $existing->paid_at
                    ? $existing->paid_at->toDateString()
                    : $data['received_at'],
            ];
        }

        return [
            'payment_status' => 'unpaid',
            'due_date' => $data['due_date'] ?? null,
            'paid_at' => null,
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', Tenant::exists('suppliers')],
            'received_at' => 'required|date|before_or_equal:today',
            'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'payment_status' => 'required|in:paid,unpaid',
            'due_date' => 'nullable|date|after_or_equal:received_at',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Tenant::exists('products')],
            'items.*.product_unit_id' => ['nullable', Tenant::exists('product_units')],
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.batch_code' => 'nullable|string|max:50',
        ], [
            'items.required' => 'Tambahkan minimal satu barang.',
            'items.*.quantity.gt' => 'Jumlah barang harus lebih dari 0.',
            'received_at.before_or_equal' => 'Tanggal terima tidak boleh di masa depan.',
            'due_date.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal terima.',
        ]);
    }

    /** Data untuk form tambah / edit */
    private function formData(): array
    {
        $suppliers = Tenant::table('suppliers')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        $products = Product::with(['units' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get(['id', 'name', 'base_unit', 'stock', 'price', 'cost_price', 'track_expiry'])
            ->map(fn (Product $p) => (object) [
                'id' => $p->id,
                'name' => $p->name,
                'base_unit' => $p->base_unit,
                'stock' => (float) $p->stock,
                'stock_text' => $p->stockBreakdown(),
                'price' => (float) $p->price,
                'cost_price' => (float) $p->cost_price,
                'track_expiry' => (bool) $p->track_expiry,
                'decimal' => UnitConverter::allowsDecimal($p->base_unit),
                'units' => $p->units->map(fn ($u) => [
                    'id' => $u->id, 'name' => $u->name, 'conversion' => (float) $u->conversion,
                ])->values()->all(),
            ]);

        // harga terakhir tiap supplier per produk → isi otomatis & peringatan "ada yang lebih murah"
        $priceMap = SupplierPrices::compare()->map(fn ($p) => $p->suppliers->map(fn ($s) => [
            'id' => $s->supplier_id,
            'name' => $s->supplier_name,
            'price' => $s->last_price,
        ])->all());

        return compact('suppliers', 'products', 'priceMap');
    }

    /**
     * Simpan item + tambah stok (batch untuk produk ber-expire) + total nota.
     *
     * Jumlah & harga diisi dalam satuan yang dipilih (mis. 5 SLOP @ 300.000);
     * stok bertambah dalam satuan terkecil (5 × 160 = 800 batang) dan modal
     * per batang = 300.000 / 160.
     */
    private function applyItems(StockIn $stockIn, array $items): void
    {
        $total = 0;
        $receivedAt = Carbon::parse($stockIn->received_at);

        foreach ($items as $i => $item) {
            $product = Product::findOrFail($item['product_id']);
            $unit = ! empty($item['product_unit_id'])
                ? ProductUnit::where('product_id', $product->id)->find($item['product_unit_id'])
                : null;

            if (! empty($item['product_unit_id']) && ! $unit) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items.$i.product_unit_id" => "Satuan tidak cocok dengan produk {$product->name}.",
                ]);
            }

            if ($product->track_expiry && empty($item['expiry_date'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items.$i.expiry_date" => "Tanggal expire wajib diisi untuk {$product->name}.",
                ]);
            }

            $conversion = (float) ($unit?->conversion ?? 1);
            $qty = round((float) $item['quantity'], 3);

            // rokok, botol, dus... tidak bisa pecahan; hanya KG / LITER / METER dst.
            if (! UnitConverter::allowsDecimal($product->base_unit) && abs($qty - round($qty)) > 0.0001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items.$i.quantity" => "Jumlah {$product->name} harus bilangan bulat (tertulis {$qty} " . ($unit?->name ?? $product->base_unit) . ').',
                ]);
            }
            $cost = round((float) $item['cost_price'], 2);

            $line = StockInItem::create([
                'stock_in_id' => $stockIn->id,
                'product_id' => $product->id,
                'product_unit_id' => $unit?->id,
                'unit_name' => $unit?->name ?? $product->base_unit,
                'unit_conversion' => $conversion,
                'quantity' => $qty,
                'base_quantity' => UnitConverter::toBase($qty, $conversion),
                'cost_price' => $cost,
                'cost_per_base' => round($cost / $conversion, 4),
                'expiry_date' => $product->track_expiry ? $item['expiry_date'] : ($item['expiry_date'] ?? null),
                'batch_code' => $item['batch_code'] ?? null,
            ]);

            $this->inventory->receive($line, $receivedAt);

            $total += $qty * $cost;
        }

        $stockIn->update(['total' => round($total, 2)]);
    }

    /**
     * Batalkan item lama (stok & batch dikembalikan) lalu hapus. Ditolak bila
     * batch-nya sudah terpakai. Mengembalikan id produk yang terdampak.
     */
    private function revertItems(StockIn $stockIn): array
    {
        $ids = [];

        foreach ($stockIn->items as $item) {
            $this->inventory->unreceive($item);
            $ids[] = $item->product_id;
        }

        $stockIn->items()->delete();

        return $ids;
    }

    /** cost_price produk = modal per satuan terkecil dari stok masuk terakhir. */
    private function refreshCostPrice(array $productIds): void
    {
        foreach (array_unique($productIds) as $productId) {
            if ($product = Product::find($productId)) {
                $this->inventory->refreshLatestCost($product);
            }
        }
    }
}
