<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderDeletion;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private \App\Services\InventoryService $inventory) {}

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    |
    | Menampilkan laporan transaksi.
    |
    | Default:
    | - range = 30 hari
    | - end_date = hari ini
    | - start_date = 29 hari sebelum hari ini
    |
    */

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | RANGE
        |--------------------------------------------------------------------------
        */

        $range = (int) $request->input('range', 30);

        if ($range < 1) {
            $range = 30;
        }


        /*
        |--------------------------------------------------------------------------
        | DATE FILTER
        |--------------------------------------------------------------------------
        */

        $end_date = $request->input(
            'end_date',
            now()->toDateString()
        );

        $start_date = $request->input(
            'start_date',
            now()
                ->subDays($range - 1)
                ->toDateString()
        );


        /*
        |--------------------------------------------------------------------------
        | VALIDASI TANGGAL
        |--------------------------------------------------------------------------
        */

        try {

            $startCarbon = Carbon::parse($start_date);
            $endCarbon = Carbon::parse($end_date);

        } catch (\Throwable $e) {

            return redirect()
                ->route('orders.index')
                ->with(
                    'error',
                    'Format tanggal tidak valid.'
                );
        }


        if ($startCarbon->greaterThan($endCarbon)) {

            return redirect()
                ->back()
                ->with(
                    'error',
                    'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | FORMAT TRANSACTION TIME
        |--------------------------------------------------------------------------
        */

        $start = $startCarbon
            ->startOfDay()
            ->format('Y-m-d\TH:i:s');

        $end = $endCarbon
            ->endOfDay()
            ->format('Y-m-d\TH:i:s');


        /*
        |--------------------------------------------------------------------------
        | BASE ORDER QUERY
        |--------------------------------------------------------------------------
        */

        $query = Order::query()
            ->whereRaw(
                "STR_TO_DATE(
                    transaction_time,
                    '%Y-%m-%dT%H:%i:%s'
                ) BETWEEN ? AND ?",
                [
                    $start,
                    $end,
                ]
            );


        /*
        |--------------------------------------------------------------------------
        | ORDER LIST
        |--------------------------------------------------------------------------
        */

        $orders = (clone $query)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();


        /*
        |--------------------------------------------------------------------------
        | ORDER SUMMARY
        |--------------------------------------------------------------------------
        */

        // ================================================================
        // PENTING — SOAL "total_revenue" (Total Pendapatan):
        //
        // Dulu dihitung dari SUM(payment_amount), padahal payment_amount
        // adalah UANG TUNAI YANG DISERAHKAN KONSUMEN (termasuk uang yang
        // nanti dikembalikan sebagai kembalian). Contoh: tagihan Rp50.000
        // dibayar pakai Rp100.000 → payment_amount = 100.000, padahal
        // yang benar-benar jadi pendapatan cafe cuma Rp50.000.
        //
        // Sekarang dihitung dari SUM(total) — nilai TAGIHAN YANG SEHARUSNYA
        // DIBAYAR, yang sudah otomatis bersih dari diskon (karena kolom
        // `total` dihitung sebagai: sub_total - discount_amount + tax +
        // service_charge). Ini juga sekaligus memperbaiki laporan saat
        // diskon member diterapkan — sebelumnya seolah uang penuh (sebelum
        // diskon) ikut tercatat sebagai pemasukan.
        // ================================================================

        $totalRevenue = (clone $query)->sum('total');
        $totalTax = (clone $query)->sum('tax');

        $summary = [

            'total_revenue' => $totalRevenue,

            'total_discount' => (clone $query)
                ->sum('discount_amount'),

            'total_tax' => $totalTax,

            // Pendapatan bersih cafe SETELAH pajak dipisahkan — pajak
            // ini bukan hak cafe, jadi wajib disetorkan ke pemda dan
            // tidak dihitung sebagai pendapatan riil.
            'net_revenue' => $totalRevenue - $totalTax,

            'total_service_charge' => (clone $query)
                ->sum('service_charge'),

            'total_cash' => (clone $query)
                ->whereRaw(
                    'LOWER(payment_method) = ?',
                    ['cash']
                )
                ->sum('total'),

            'total_transfer' => (clone $query)
                ->whereRaw(
                    'LOWER(payment_method) = ?',
                    ['transfer']
                )
                ->sum('total'),

            // penjualan Tempo (piutang) — dipisah dari Cash/Transfer
            'total_tempo' => (clone $query)
                ->whereRaw(
                    'LOWER(payment_method) = ?',
                    ['tempo']
                )
                ->sum('total'),

            'total_order' => (clone $query)
                ->count(),

            'total_item' => (clone $query)
                ->sum('total_item'),

            // Rata-rata dihitung dari `total` (tagihan bersih), bukan
            // dari `payment_amount` (uang tunai yang diserahkan).
            'average_order' => (clone $query)
                ->avg('total') ?? 0,
        ];


        /*
        |--------------------------------------------------------------------------
        | UANG MASUK, PIUTANG & MODAL (periode ini)
        |--------------------------------------------------------------------------
        |
        | - tempo_received : DP & cicilan Tempo yang DITERIMA di periode ini
        | - cash_in        : uang yang benar-benar masuk (cash + transfer +
        |                    DP/cicilan tempo)
        | - tempo_unpaid   : sisa tagihan nota Tempo periode ini yang belum
        |                    dibayar
        | - cogs           : modal barang terjual (qty × harga beli per baris)
        | - gross_profit   : penjualan − modal − pajak
        */

        $tempoReceived = (float) Tenant::table('order_payments')
            ->whereBetween('paid_at', [
                $startCarbon->copy()->startOfDay(),
                $endCarbon->copy()->endOfDay(),
            ])
            ->sum('amount');

        $summary['tempo_received'] = $tempoReceived;
        $summary['cash_in'] = $summary['total_cash'] + $summary['total_transfer'] + $tempoReceived;

        $summary['tempo_unpaid'] = (float) (clone $query)
            ->whereRaw('LOWER(payment_method) = ?', ['tempo'])
            ->where('payment_status', '!=', 'paid')
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) AS s')
            ->value('s');

        $cost = Tenant::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereRaw(
                "STR_TO_DATE(orders.transaction_time, '%Y-%m-%dT%H:%i:%s') BETWEEN ? AND ?",
                [$start, $end]
            )
            ->selectRaw('
                COALESCE(SUM(COALESCE(order_items.cost_total, order_items.base_quantity * COALESCE(order_items.cost_price, 0))), 0) AS cogs,
                COALESCE(SUM(CASE WHEN order_items.cost_price IS NULL OR order_items.cost_price <= 0 THEN 1 ELSE 0 END), 0) AS no_cost
            ')
            ->first();

        $summary['cogs'] = (float) ($cost->cogs ?? 0);
        $summary['no_cost'] = (int) ($cost->no_cost ?? 0);
        $summary['gross_profit'] = $summary['total_revenue'] - $summary['cogs'] - $summary['total_tax'];

        // laba kotor yang sudah jadi uang (Tempo dihitung sebanding
        // dengan yang sudah dibayar); sisanya masih di piutang
        $summary['gross_realized'] = (float) Tenant::table('orders')
            ->leftJoinSub(
                Tenant::table('order_items')
                    ->selectRaw('order_id, SUM(COALESCE(cost_total, base_quantity * COALESCE(cost_price, 0))) AS cogs')
                    ->groupBy('order_id'),
                'c',
                'c.order_id',
                '=',
                'orders.id'
            )
            ->whereRaw(
                "STR_TO_DATE(orders.transaction_time, '%Y-%m-%dT%H:%i:%s') BETWEEN ? AND ?",
                [$start, $end]
            )
            ->selectRaw("
                COALESCE(SUM(
                    (orders.total - orders.tax - COALESCE(c.cogs, 0)) *
                    CASE
                        WHEN LOWER(orders.payment_method) <> 'tempo' OR orders.payment_status = 'paid' THEN 1
                        WHEN orders.total > 0 THEN LEAST(orders.paid_amount, orders.total) / orders.total
                        ELSE 0
                    END
                ), 0) AS r
            ")
            ->value('r');

        $summary['profit_pending'] = $summary['gross_profit'] - $summary['gross_realized'];


        /*
        |--------------------------------------------------------------------------
        | EXPENSE QUERY
        |--------------------------------------------------------------------------
        */

        $expenseQuery = Expense::query()
            ->whereBetween(
                'expense_date',
                [
                    $startCarbon->toDateString(),
                    $endCarbon->toDateString(),
                ]
            );


        /*
        |--------------------------------------------------------------------------
        | TOTAL EXPENSE
        |--------------------------------------------------------------------------
        */

        $totalExpense = (clone $expenseQuery)
            ->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | EXPENSE BY CATEGORY
        |--------------------------------------------------------------------------
        */

        $expenseByCategory = (clone $expenseQuery)
            ->selectRaw(
                'category, SUM(amount) as total'
            )
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | NET INCOME
        |--------------------------------------------------------------------------
        */

        $netIncome =
            ($summary['total_revenue'] ?? 0)
            -
            ($totalExpense ?? 0);


        /*
        |--------------------------------------------------------------------------
        | CHART DATA
        |--------------------------------------------------------------------------
        */

        $chartData = (clone $query)
            ->selectRaw("
                DATE(
                    STR_TO_DATE(
                        transaction_time,
                        '%Y-%m-%dT%H:%i:%s'
                    )
                ) AS trx_date,

                SUM(total) AS total,

                SUM(CASE WHEN LOWER(payment_method) = 'tempo'
                    THEN total ELSE 0 END) AS tempo_total,

                SUM(CASE WHEN LOWER(payment_method) = 'tempo'
                    THEN 0 ELSE total END) AS paid_total
            ")
            ->groupBy('trx_date')
            ->orderBy('trx_date')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | RETURN VIEW
        |--------------------------------------------------------------------------
        */

        return view(
            'pages.order_reports.index',
            compact(
                'orders',
                'summary',
                'chartData',
                'start_date',
                'end_date',
                'range',
                'totalExpense',
                'expenseByCategory',
                'netIncome'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUMMARY API
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Order::query();

        if ($startDate && $endDate) {

            try {

                $start = Carbon::parse($startDate)
                    ->startOfDay();

                $end = Carbon::parse($endDate)
                    ->endOfDay();

                $query->whereBetween(
                    'created_at',
                    [
                        $start,
                        $end,
                    ]
                );

            } catch (\Throwable $e) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Format tanggal tidak valid.',
                ], 422);

            }
        }

        $totalRevenue = (clone $query)->sum('total');
        $totalDiscount = (clone $query)->sum('discount_amount');
        $totalTax = (clone $query)->sum('tax');
        $totalServiceCharge = (clone $query)->sum('service_charge');
        $totalSubtotal = (clone $query)->sum('sub_total');

        $total =
            $totalSubtotal
            - $totalDiscount
            - $totalTax
            + $totalServiceCharge;

        return response()->json([

            'status' => 'success',

            'data' => [
                'total_revenue' => $totalRevenue,
                'total_discount' => $totalDiscount,
                'total_tax' => $totalTax,
                'total_subtotal' => $totalSubtotal,
                'total_service_charge' => $totalServiceCharge,
                'total' => $total,
            ],

        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    |
    | Mengambil detail satu transaksi (dipakai modal detail).
    |
    */

    public function show(int $id)
    {
        $order = Order::with([
            'orderItems.product'
        ])->findOrFail($id);

        $orderData = [
            'id' => $order->id,
            'payment_amount' => $order->payment_amount,
            'sub_total' => $order->sub_total,
            'tax' => $order->tax,
            'discount' => $order->discount,
            'discount_amount' => $order->discount_amount,
            'service_charge' => $order->service_charge,
            'total' => $order->total,
            'payment_method' => $order->payment_method,
            'total_item' => $order->total_item,
            'table_number' => $order->table_number,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'id_kasir' => $order->id_kasir,
            'nama_kasir' => $order->nama_kasir,
            'transaction_time' => $order->transaction_time,
            'created_at' => $order->created_at,
            'received_at' => $order->received_at,
            'device_id' => $order->device_id,
            'clock_suspect' => (bool) $order->clock_suspect,
            'stock_conflict' => (bool) $order->stock_conflict,
            'from_inactive_device' => (bool) $order->from_inactive_device,
            'sync_notes' => $order->sync_notes,
        ];

        $items = $order->orderItems
            ->map(function ($item) {

                $productName =
                    $item->product_name
                    ?? optional($item->product)->name
                    ?? 'Produk';

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $productName,
                    'quantity' => $item->quantity,
                    'unit_name' => $item->unit_name,
                    'unit_conversion' => (float) $item->unit_conversion,
                    'base_quantity' => (float) $item->base_quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->quantity * (float) $item->price,
                ];

            })
            ->values();

        return response()->json([
            'status' => 'success',
            'order' => $orderData,
            'items' => $items,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY — HAPUS ORDER (misal: kasir double-input)
    |--------------------------------------------------------------------------
    |
    | Stok (dan batch FEFO) yang dipakai order ini dikembalikan, order
    | dicatat di order_deletions supaya HP kasir ikut membuangnya saat
    | sinkron, lalu order dihapus.
    |
    */

    public function destroy(int $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $order = Order::with('orderItems')->lockForUpdate()->findOrFail($id);

                foreach ($order->orderItems as $item) {
                    $this->inventory->reverseSale($item);
                }

                OrderDeletion::create([
                    'order_id' => $order->id,
                    'client_order_id' => $order->client_order_id,
                    'deleted_at' => now(),
                ]);

                $order->orderItems()->delete();
                $order->delete();
            });
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menghapus order: ' . $e->getMessage());
        }

        return redirect()
            ->route('orders.index')
            ->with('success', 'Order berhasil dihapus. Stok sudah otomatis dikembalikan.');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT — FORM KOREKSI ORDER
    |--------------------------------------------------------------------------
    */

    public function edit(int $id)
    {
        $order = Order::with('orderItems.product')->findOrFail($id);

        $products = Product::with(['units' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('pages.order_reports.edit', compact('order', 'products'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — SIMPAN KOREKSI ORDER
    |--------------------------------------------------------------------------
    |
    | Stok & batch lama dikembalikan, lalu item baru dipotong ulang (FEFO
    | memakai tanggal transaksi). Koreksi dari admin boleh membuat stok
    | minus — ditandai stock_conflict, tidak ditolak.
    |
    */

    public function update(Request $request, int $id)
    {
        $request->validate([
            'payment_method' => ['required', 'string'],
            'table_number' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'transaction_time' => ['required', 'string'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'numeric', 'min:0'],
            'service_charge' => ['required', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Tenant::exists('products')],
            'items.*.product_unit_id' => ['nullable', 'integer', Tenant::exists('product_units')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'gte:0'],
        ]);

        try {
            DB::transaction(function () use ($request, $id) {
                $order = Order::with('orderItems')->lockForUpdate()->findOrFail($id);

                // 1) kembalikan stok & batch lama
                foreach ($order->orderItems as $oldItem) {
                    $this->inventory->reverseSale($oldItem);
                }

                // konversi lama per (produk, satuan) dipertahankan supaya
                // koreksi tidak mengubah isi satuan transaksi lama
                $oldConv = $order->orderItems->mapWithKeys(fn ($i) => [
                    $i->product_id . ':' . ($i->product_unit_id ?? 0) => (float) $i->unit_conversion,
                ]);

                $order->orderItems()->delete();

                // 2) item baru
                $soldAt = $this->parseTransactionTime($request->transaction_time);
                $subTotal = 0;
                $totalItem = 0;
                $conflict = false;

                foreach ($request->items as $row) {
                    $product = Product::findOrFail($row['product_id']);
                    $unit = ! empty($row['product_unit_id'])
                        ? \App\Models\ProductUnit::where('product_id', $product->id)->find($row['product_unit_id'])
                        : null;

                    $key = $product->id . ':' . ($unit?->id ?? 0);
                    $conversion = $oldConv[$key] ?? (float) ($unit?->conversion ?? 1);
                    $quantity = round((float) $row['quantity'], 3);

                    $item = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_unit_id' => $unit?->id,
                        'product_name' => $product->name,
                        'unit_name' => $unit?->name ?? $product->base_unit,
                        'unit_conversion' => $conversion,
                        'quantity' => $quantity,
                        'base_quantity' => \App\Support\UnitConverter::toBase($quantity, $conversion),
                        'price' => $row['price'],
                    ]);

                    $conflict = $this->inventory->applySale($item, $soldAt) || $conflict;

                    $subTotal += $quantity * (float) $row['price'];
                    $totalItem += $quantity;
                }

                // 3) total
                $tax = (float) $request->tax;
                $discountAmount = (float) $request->discount_amount;
                $serviceCharge = (float) $request->service_charge;
                $total = $subTotal - $discountAmount + $tax + $serviceCharge;

                $order->update([
                    'payment_method' => $request->payment_method,
                    'table_number' => $request->table_number,
                    'customer_name' => $request->customer_name,
                    'transaction_time' => $request->transaction_time,
                    'sub_total' => $subTotal,
                    'tax' => $tax,
                    'discount_amount' => $discountAmount,
                    'service_charge' => $serviceCharge,
                    'total' => $total,
                    'payment_amount' => $request->filled('payment_amount') ? (float) $request->payment_amount : $total,
                    'total_item' => $totalItem,
                    'stock_conflict' => $conflict,
                ]);

                $order->refresh();
                $order->syncPaymentState();
            });
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan perubahan: ' . $e->getMessage());
        }

        return redirect()
            ->route('orders.index')
            ->with('success', 'Order berhasil diperbarui. Stok sudah otomatis dikoreksi.');
    }

    private function parseTransactionTime(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : now();
        } catch (\Throwable) {
            return now();
        }
    }
}
