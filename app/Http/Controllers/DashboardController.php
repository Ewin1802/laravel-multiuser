<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\PayableAlerts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * =========================================================
     * TRANSACTION DATE
     * =========================================================
     *
     * transaction_time disimpan sebagai string:
     *
     * 2026-08-17T13:25:00
     *
     * sehingga perlu dikonversi menjadi DATETIME MySQL.
     */
    private function transactionDate(): string
    {
        return "STR_TO_DATE(transaction_time,'%Y-%m-%dT%H:%i:%s')";
    }


    /**
     * =========================================================
     * RINGKASAN UANG & MODAL PER PERIODE
     * =========================================================
     *
     * $orderWhere : kondisi SQL untuk orders (pakai transaction_time)
     * $paidFrom / $paidTo : rentang tanggal untuk order_payments.paid_at
     *
     * - tempo      : penjualan Tempo (nilai nota) pada periode ini
     * - cash_in    : UANG YANG BENAR-BENAR MASUK =
     *                nota Cash/Transfer + DP & cicilan Tempo yang
     *                diterima pada periode ini (tabel order_payments)
     * - cogs       : modal barang terjual (qty × harga beli per baris)
     * - no_cost    : jumlah baris penjualan yang belum punya harga beli
     */
    private function periodMoney(string $orderWhere, Carbon $paidFrom, Carbon $paidTo): array
    {
        $tempo = (float) Order::whereRaw($orderWhere)
            ->whereRaw('LOWER(payment_method) = ?', ['tempo'])
            ->sum('total');

        $paidNow = (float) Order::whereRaw($orderWhere)
            ->whereRaw('LOWER(payment_method) <> ?', ['tempo'])
            ->sum('total');

        $tempoReceived = (float) Tenant::table('order_payments')
            ->whereBetween('paid_at', [$paidFrom->copy()->startOfDay(), $paidTo->copy()->endOfDay()])
            ->sum('amount');

        $cost = Tenant::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereRaw(str_replace('transaction_time', 'orders.transaction_time', $orderWhere))
            ->selectRaw('
                COALESCE(SUM(COALESCE(order_items.cost_total, order_items.base_quantity * COALESCE(order_items.cost_price, 0))), 0) AS cogs,
                COALESCE(SUM(CASE WHEN order_items.cost_price IS NULL OR order_items.cost_price <= 0 THEN 1 ELSE 0 END), 0) AS no_cost
            ')
            ->first();

        // laba kotor per nota (total − pajak − modal), dihitung SEBANDING
        // dengan uang yang sudah dibayar: Cash/Transfer = 100%, Tempo =
        // paid_amount ÷ total. Sisanya = laba yang masih di piutang.
        $realized = Tenant::table('orders')
            ->leftJoinSub(
                Tenant::table('order_items')
                    ->selectRaw('order_id, SUM(COALESCE(cost_total, base_quantity * COALESCE(cost_price, 0))) AS cogs')
                    ->groupBy('order_id'),
                'c',
                'c.order_id',
                '=',
                'orders.id'
            )
            ->whereRaw(str_replace('transaction_time', 'orders.transaction_time', $orderWhere))
            ->selectRaw("
                COALESCE(SUM(
                    (orders.total - orders.tax - COALESCE(c.cogs, 0)) *
                    CASE
                        WHEN LOWER(orders.payment_method) <> 'tempo' OR orders.payment_status = 'paid' THEN 1
                        WHEN orders.total > 0 THEN LEAST(orders.paid_amount, orders.total) / orders.total
                        ELSE 0
                    END
                ), 0) AS gross_realized
            ")
            ->value('gross_realized');

        return [
            'gross_realized' => (float) $realized,
            'tempo' => $tempo,
            'paid_now' => $paidNow,
            'tempo_received' => $tempoReceived,
            'cash_in' => $paidNow + $tempoReceived,
            'cogs' => (float) ($cost->cogs ?? 0),
            'no_cost' => (int) ($cost->no_cost ?? 0),
        ];
    }


    /**
     * =========================================================
     * DASHBOARD
     * =========================================================
     */
    public function index()
    {
        $transactionDate = $this->transactionDate();


        /*
        |--------------------------------------------------------------------------
        | DASHBOARD HARI INI
        |--------------------------------------------------------------------------
        */

        $todayRevenue = Order::whereRaw("
            DATE($transactionDate) = CURDATE()
        ")
            ->sum('total');


        $todayOrders = Order::whereRaw("
            DATE($transactionDate) = CURDATE()
        ")
            ->count();


        $todayCash = Order::whereRaw("
            DATE($transactionDate) = CURDATE()
        ")
            ->whereRaw(
                'LOWER(payment_method) = ?',
                ['cash']
            )
            ->sum('total');


        $todayTransfer = Order::whereRaw("
            DATE($transactionDate) = CURDATE()
        ")
            ->whereRaw(
                'LOWER(payment_method) = ?',
                ['transfer']
            )
            ->sum('total');


        /*
        |--------------------------------------------------------------------------
        | PAJAK HARI INI (disetor ke pemda — BUKAN pendapatan cafe)
        |--------------------------------------------------------------------------
        */

        $todayTax = Order::whereRaw("
            DATE($transactionDate) = CURDATE()
        ")
            ->sum('tax');


        /*
        |--------------------------------------------------------------------------
        | PENGELUARAN HARI INI
        |--------------------------------------------------------------------------
        */

        $todayExpense = Expense::whereDate(
            'expense_date',
            Carbon::today()
        )
            ->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | LABA BERSIH HARI INI
        |--------------------------------------------------------------------------
        |
        | FIX: dulu cuma dikurangi pengeluaran (Revenue - Expense),
        | padahal `todayRevenue` (SUM total) itu masih mengandung
        | komponen pajak di dalamnya. Pajak BUKAN hak/pendapatan
        | cafe — wajib disetor ke pemda — jadi harus ikut dikurangi
        | juga supaya laba bersih tidak "kelihatan" lebih besar dari
        | yang sebenarnya bisa dipakai cafe.
        |
        */

        $todayMoney = $this->periodMoney(
            "DATE($transactionDate) = CURDATE()",
            Carbon::today(),
            Carbon::today()
        );

        $todayTempo = $todayMoney['tempo'];
        $todayCashIn = $todayMoney['cash_in'];
        $todayTempoReceived = $todayMoney['tempo_received'];
        $todayCogs = $todayMoney['cogs'];

        // Laba = penjualan − modal barang − pajak − pengeluaran
        $todayNetIncome =
            $todayRevenue - $todayCogs - $todayTax - $todayExpense;

        // bagian laba yang sudah jadi uang vs masih di piutang
        $todayProfitPending = ($todayRevenue - $todayCogs - $todayTax) - $todayMoney['gross_realized'];
        $todayProfitReceived = $todayNetIncome - $todayProfitPending;


        /*
        |--------------------------------------------------------------------------
        | DASHBOARD BULAN INI
        |--------------------------------------------------------------------------
        */

        $monthRevenue = Order::whereRaw("
            YEAR($transactionDate) = YEAR(CURDATE())
            AND
            MONTH($transactionDate) = MONTH(CURDATE())
        ")
            ->sum('total');


        $monthOrders = Order::whereRaw("
            YEAR($transactionDate) = YEAR(CURDATE())
            AND
            MONTH($transactionDate) = MONTH(CURDATE())
        ")
            ->count();


        $monthCash = Order::whereRaw("
            YEAR($transactionDate) = YEAR(CURDATE())
            AND
            MONTH($transactionDate) = MONTH(CURDATE())
        ")
            ->whereRaw(
                'LOWER(payment_method) = ?',
                ['cash']
            )
            ->sum('total');


        $monthTransfer = Order::whereRaw("
            YEAR($transactionDate) = YEAR(CURDATE())
            AND
            MONTH($transactionDate) = MONTH(CURDATE())
        ")
            ->whereRaw(
                'LOWER(payment_method) = ?',
                ['transfer']
            )
            ->sum('total');


        /*
        |--------------------------------------------------------------------------
        | PAJAK BULAN INI (disetor ke pemda — BUKAN pendapatan cafe)
        |--------------------------------------------------------------------------
        */

        $monthTax = Order::whereRaw("
            YEAR($transactionDate) = YEAR(CURDATE())
            AND
            MONTH($transactionDate) = MONTH(CURDATE())
        ")
            ->sum('tax');


        /*
        |--------------------------------------------------------------------------
        | PENGELUARAN BULAN INI
        |--------------------------------------------------------------------------
        */

        $monthExpense = Expense::whereYear(
            'expense_date',
            Carbon::now()->year
        )
            ->whereMonth(
                'expense_date',
                Carbon::now()->month
            )
            ->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | LABA BERSIH BULAN INI
        |--------------------------------------------------------------------------
        |
        | FIX: dikurangi pajak juga — lihat catatan di todayNetIncome.
        |
        */

        $monthMoney = $this->periodMoney(
            "YEAR($transactionDate) = YEAR(CURDATE()) AND MONTH($transactionDate) = MONTH(CURDATE())",
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        );

        $monthTempo = $monthMoney['tempo'];
        $monthCashIn = $monthMoney['cash_in'];
        $monthTempoReceived = $monthMoney['tempo_received'];
        $monthCogs = $monthMoney['cogs'];
        $monthNoCost = $monthMoney['no_cost'];

        // Laba = penjualan − modal barang − pajak − pengeluaran
        $monthNetIncome =
            $monthRevenue - $monthCogs - $monthTax - $monthExpense;

        // bagian laba yang sudah jadi uang vs masih di piutang
        $monthProfitPending = ($monthRevenue - $monthCogs - $monthTax) - $monthMoney['gross_realized'];
        $monthProfitReceived = $monthNetIncome - $monthProfitPending;

        /*
        |--------------------------------------------------------------------------
        | PIUTANG BERJALAN (semua nota Tempo yang belum lunas)
        |--------------------------------------------------------------------------
        */

        $receivable = Order::whereRaw('LOWER(payment_method) = ?', ['tempo'])
            ->where('payment_status', '!=', 'paid')
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) AS outstanding, COUNT(*) AS notes')
            ->first();

        $receivableOutstanding = (float) ($receivable->outstanding ?? 0);
        $receivableNotes = (int) ($receivable->notes ?? 0);


        /*
        |--------------------------------------------------------------------------
        | PENGELUARAN BERDASARKAN KATEGORI
        |--------------------------------------------------------------------------
        */

        $expenseGaji = Expense::whereYear(
            'expense_date',
            Carbon::now()->year
        )
            ->whereMonth(
                'expense_date',
                Carbon::now()->month
            )
            ->where('category', 'Gaji')
            ->sum('amount');


        $expenseDapur = Expense::whereYear(
            'expense_date',
            Carbon::now()->year
        )
            ->whereMonth(
                'expense_date',
                Carbon::now()->month
            )
            ->where('category', 'Operasional Toko')
            ->sum('amount');


        $expenseListrik = Expense::whereYear(
            'expense_date',
            Carbon::now()->year
        )
            ->whereMonth(
                'expense_date',
                Carbon::now()->month
            )
            ->where('category', 'Listrik')
            ->sum('amount');


        $expenseTakTerduga = Expense::whereYear(
            'expense_date',
            Carbon::now()->year
        )
            ->whereMonth(
                'expense_date',
                Carbon::now()->month
            )
            ->where('category', 'Tak Terduga')
            ->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | TOTAL PENGELUARAN
        |--------------------------------------------------------------------------
        */

        $totalExpense = $monthExpense;


        /*
        |--------------------------------------------------------------------------
        | PERSENTASE PENGELUARAN
        |--------------------------------------------------------------------------
        */

        $expensePercent = $monthRevenue > 0
            ? round(
                ($monthExpense / $monthRevenue) * 100,
                1
            )
            : 0;


        /*
        |--------------------------------------------------------------------------
        | MARGIN BERSIH
        |--------------------------------------------------------------------------
        */

        $netIncomePercent = $monthRevenue > 0
            ? round(
                ($monthNetIncome / $monthRevenue) * 100,
                1
            )
            : 0;


        /*
        |--------------------------------------------------------------------------
        | MASTER DATA
        |--------------------------------------------------------------------------
        */

        $totalProducts = Product::count();

        $activeProducts = Product::where(
            'status',
            1
        )->count();

        $inactiveProducts = Product::where(
            'status',
            0
        )->count();

        $favoriteProductsCount = Product::where(
            'is_favorite',
            1
        )->count();

        $totalCategories = Category::count();

        $totalUsers = User::where('store_id', Tenant::idOrFail())->count();


        /*
        |--------------------------------------------------------------------------
        | INVENTORY
        |--------------------------------------------------------------------------
        */

        $totalStock = Product::sum('stock');


        // Nilai jual stok: stok yang BELUM terjual × harga jual
        $stockValue = Product::selectRaw("
            SUM(stock * price) as total
        ")
            ->value('total') ?? 0;


        // Modal stok: stok yang belum terjual × harga beli terakhir
        // (cost_price diisi otomatis saat mencatat Stok Masuk)
        $stockCost = Product::selectRaw("
            SUM(stock * COALESCE(cost_price, 0)) as total
        ")
            ->value('total') ?? 0;


        // Potensi untung, hanya dari produk yang harga belinya sudah diketahui
        $stockMargin = Product::where('cost_price', '>', 0)
            ->selectRaw("
                SUM(stock * (price - cost_price)) as total
            ")
            ->value('total') ?? 0;


        // Produk berstok yang belum punya harga beli → modalnya belum terhitung
        $stockNoCost = Product::where('stock', '>', 0)
            ->where(function ($query) {
                $query->whereNull('cost_price')
                    ->orWhere('cost_price', '<=', 0);
            })
            ->count();


        /*
        |--------------------------------------------------------------------------
        | STOK MENIPIS
        |--------------------------------------------------------------------------
        */

        $lowStockProducts = Product::with('category')
            ->where('status', 1)
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->limit(10)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | PRODUK FAVORIT
        |--------------------------------------------------------------------------
        */

        $favoriteProducts = Product::with('category')
            ->where('status', 1)
            ->where('is_favorite', 1)
            ->latest()
            ->limit(10)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | GRAFIK PENDAPATAN 30 HARI
        |--------------------------------------------------------------------------
        */

        $chart = Order::selectRaw("
            DATE($transactionDate) as trx_date,
            SUM(total) as total
        ")
            ->whereRaw("
                DATE($transactionDate) >= ?
            ", [
                Carbon::now()
                    ->subDays(29)
                    ->toDateString()
            ])
            ->groupBy('trx_date')
            ->orderBy('trx_date')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | GRAFIK PENDAPATAN VS PENGELUARAN 30 HARI
        |--------------------------------------------------------------------------
        */

        $expenseChart = Expense::selectRaw("
            expense_date,
            SUM(amount) as total
        ")
            ->whereDate(
                'expense_date',
                '>=',
                Carbon::now()
                    ->subDays(29)
                    ->toDateString()
            )
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | SUSUN GRAFIK: 30 HARI PENUH
        |--------------------------------------------------------------------------
        |
        | Dulu label hanya berisi hari yang ADA transaksinya, dan label
        | pengeluaran dibuat terpisah — akibatnya grafik hanya berisi
        | beberapa titik dan garis pengeluaran bisa jatuh di tanggal yang
        | salah. Sekarang kedua garis memakai 30 tanggal yang sama; hari
        | tanpa data bernilai 0.
        |
        */

        $revenueByDate = $chart->filter(fn ($row) => $row->trx_date)->mapWithKeys(fn ($row) => [
            Carbon::parse($row->trx_date)->toDateString() => (float) $row->total,
        ]);

        $expenseByDate = $expenseChart->mapWithKeys(fn ($row) => [
            Carbon::parse($row->expense_date)->toDateString() => (float) $row->total,
        ]);

        $chartDays = collect(range(29, 0))
            ->map(fn ($i) => Carbon::today()->subDays($i));

        $chartLabels = $chartDays
            ->map(fn ($day) => $day->translatedFormat('d M'));

        $chartSeries = $chartDays
            ->map(fn ($day) => $revenueByDate[$day->toDateString()] ?? 0);

        $expenseChartLabels = $chartLabels;

        $expenseChartSeries = $chartDays
            ->map(fn ($day) => $expenseByDate[$day->toDateString()] ?? 0);


        /*
        |--------------------------------------------------------------------------
        | TOP PRODUK TERLARIS
        |--------------------------------------------------------------------------
        */

        $topProducts = Tenant::table('order_items')

            ->join(
                'products',
                'products.id',
                '=',
                'order_items.product_id'
            )

            ->select(

                'products.id',

                'products.name',

                'products.image',

                DB::raw(
                    'SUM(order_items.base_quantity) as total_qty'
                ),

                DB::raw(
                    'SUM(order_items.quantity * order_items.price) as omzet'
                ),

                // modal barang terjual (harga beli dikunci per transaksi)
                DB::raw(
                    'SUM(COALESCE(order_items.cost_total, order_items.base_quantity * COALESCE(order_items.cost_price, 0))) as modal'
                ),

                // baris penjualan yang belum punya harga beli
                DB::raw(
                    'SUM(CASE WHEN order_items.cost_price IS NULL OR order_items.cost_price <= 0 THEN 1 ELSE 0 END) as no_cost'
                )

            )

            ->groupBy(

                'products.id',

                'products.name',

                'products.image'

            )

            ->orderByDesc('total_qty')

            ->limit(10)

            ->get();


        /*
        |--------------------------------------------------------------------------
        | TRANSAKSI TERBARU
        |--------------------------------------------------------------------------
        */

        $recentOrders = Order::orderByRaw("
            $transactionDate DESC
        ")
            ->limit(10)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | USER TERBARU
        |--------------------------------------------------------------------------
        */

        $recentUsers = User::where('store_id', Tenant::idOrFail())->latest()
            ->limit(10)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | PENGELUARAN TERBARU
        |--------------------------------------------------------------------------
        */

        $recentExpenses = Expense::with('user')
            ->latest('expense_date')
            ->latest('id')
            ->limit(10)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | CASH VS TRANSFER
        |--------------------------------------------------------------------------
        */

        $totalPayment =
            $monthCash +
            $monthTransfer +
            $monthTempo;


        $cashPercent = $totalPayment > 0
            ? round(
                ($monthCash / $totalPayment) * 100,
                1
            )
            : 0;


        $transferPercent = $totalPayment > 0
            ? round(
                ($monthTransfer / $totalPayment) * 100,
                1
            )
            : 0;

        $tempoPercent = $totalPayment > 0
            ? round(
                ($monthTempo / $totalPayment) * 100,
                1
            )
            : 0;


        /*
        |--------------------------------------------------------------------------
        | RATA-RATA TRANSAKSI
        |--------------------------------------------------------------------------
        */

        $averageOrder = $todayOrders > 0
            ? round(
                $todayRevenue / $todayOrders
            )
            : 0;


        /*
        |--------------------------------------------------------------------------
        | AKTIVITAS DASHBOARD
        |--------------------------------------------------------------------------
        */

        $activities = collect();


        foreach ($recentOrders as $order) {

            $activities->push([

                'type' => 'order',

                'icon' => 'receipt-text',

                'title' => 'Transaksi Baru',

                'subtitle' => $order->customer_name
                    ?: 'Customer Umum',

                'amount' => $order->payment_amount,

                'payment_method' => ucfirst(
                    $order->payment_method
                ),

                'time' => Carbon::parse(
                    str_replace(
                        'T',
                        ' ',
                        $order->transaction_time
                    )
                )->diffForHumans(),

            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | AKTIVITAS PENGELUARAN
        |--------------------------------------------------------------------------
        */

        foreach ($recentExpenses as $expense) {

            $activities->push([

                'type' => 'expense',

                'icon' => 'wallet',

                'title' => 'Pengeluaran',

                'subtitle' => $expense->description,

                'category' => $expense->category,

                'amount' => $expense->amount,

                'time' => Carbon::parse(
                    $expense->expense_date
                )->diffForHumans(),

            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | URUTKAN AKTIVITAS TERBARU
        |--------------------------------------------------------------------------
        */

        $activities = $activities
            ->sortByDesc(function ($activity) {

                return $activity['time'];

            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | PENJUALAN TERAKHIR 7 HARI
        |--------------------------------------------------------------------------
        */

        $weeklyRevenue = Order::whereRaw("
            DATE($transactionDate) >= ?
        ", [
            Carbon::now()
                ->subDays(6)
                ->toDateString()
        ])
            ->sum('total');


        /*
        |--------------------------------------------------------------------------
        | PAJAK 7 HARI (disetor ke pemda — BUKAN pendapatan cafe)
        |--------------------------------------------------------------------------
        */

        $weeklyTax = Order::whereRaw("
            DATE($transactionDate) >= ?
        ", [
            Carbon::now()
                ->subDays(6)
                ->toDateString()
        ])
            ->sum('tax');


        /*
        |--------------------------------------------------------------------------
        | PENGELUARAN 7 HARI
        |--------------------------------------------------------------------------
        */

        $weeklyExpense = Expense::whereDate(
            'expense_date',
            '>=',
            Carbon::now()
                ->subDays(6)
                ->toDateString()
        )
            ->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | LABA BERSIH 7 HARI
        |--------------------------------------------------------------------------
        |
        | FIX: dikurangi pajak juga — lihat catatan di todayNetIncome.
        |
        */

        $weeklyNetIncome =
            $weeklyRevenue -
            $weeklyTax -
            $weeklyExpense;


        /*
        |--------------------------------------------------------------------------
        | PENJUALAN TERAKHIR 30 HARI
        |--------------------------------------------------------------------------
        */

        $monthlyRevenue = Order::whereRaw("
            DATE($transactionDate) >= ?
        ", [
            Carbon::now()
                ->subDays(29)
                ->toDateString()
        ])
            ->sum('total');


        /*
        |--------------------------------------------------------------------------
        | PAJAK TERAKHIR 30 HARI (disetor ke pemda — BUKAN pendapatan cafe)
        |--------------------------------------------------------------------------
        */

        $monthlyTax = Order::whereRaw("
            DATE($transactionDate) >= ?
        ", [
            Carbon::now()
                ->subDays(29)
                ->toDateString()
        ])
            ->sum('tax');


        /*
        |--------------------------------------------------------------------------
        | PENGELUARAN TERAKHIR 30 HARI
        |--------------------------------------------------------------------------
        */

        $monthlyExpense = Expense::whereDate(
            'expense_date',
            '>=',
            Carbon::now()
                ->subDays(29)
                ->toDateString()
        )
            ->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | LABA BERSIH 30 HARI
        |--------------------------------------------------------------------------
        |
        | FIX: dikurangi pajak juga — lihat catatan di todayNetIncome.
        |
        */

        $monthlyNetIncome =
            $monthlyRevenue -
            $monthlyTax -
            $monthlyExpense;


        /*
        |--------------------------------------------------------------------------
        | TOTAL ORDER 30 HARI
        |--------------------------------------------------------------------------
        */

        $monthlyOrders = Order::whereRaw("
            DATE($transactionDate) >= ?
        ", [
            Carbon::now()
                ->subDays(29)
                ->toDateString()
        ])
            ->count();


        /*
        |--------------------------------------------------------------------------
        | HUTANG SUPPLIER (nota tempo lewat / segera jatuh tempo)
        |--------------------------------------------------------------------------
        */

        $payables = PayableAlerts::summary();


        /*
        |--------------------------------------------------------------------------
        | RETURN VIEW
        |--------------------------------------------------------------------------
        */

        return view(
            'pages.dashboard.index',
            compact(

                // Hari Ini
                'todayRevenue',
                'todayOrders',
                'todayCash',
                'todayTransfer',
                'todayTax',
                'todayExpense',
                'todayNetIncome',
                'todayTempo',
                'todayCashIn',
                'todayTempoReceived',
                'todayCogs',
                'todayProfitPending',
                'todayProfitReceived',

                // Bulan Ini
                'monthRevenue',
                'monthOrders',
                'monthCash',
                'monthTransfer',
                'monthTax',
                'monthExpense',
                'monthNetIncome',
                'monthTempo',
                'monthCashIn',
                'monthTempoReceived',
                'monthCogs',
                'monthNoCost',
                'monthProfitPending',
                'monthProfitReceived',
                'receivableOutstanding',
                'receivableNotes',

                // Pengeluaran
                'totalExpense',
                'expenseGaji',
                'expenseDapur',
                'expenseListrik',
                'expenseTakTerduga',
                'expensePercent',
                'netIncomePercent',

                // Master
                'totalProducts',
                'activeProducts',
                'inactiveProducts',
                'favoriteProductsCount',
                'totalCategories',
                'totalUsers',

                // Inventory
                'totalStock',
                'stockValue',
                'stockCost',
                'stockMargin',
                'stockNoCost',

                // Produk
                'favoriteProducts',
                'lowStockProducts',
                'topProducts',

                // Chart
                'chartLabels',
                'chartSeries',
                'expenseChartLabels',
                'expenseChartSeries',

                // Dashboard
                'recentOrders',
                'recentUsers',
                'recentExpenses',
                'activities',

                // Statistik
                'cashPercent',
                'transferPercent',
                'tempoPercent',
                'averageOrder',
                'weeklyRevenue',
                'weeklyTax',
                'weeklyExpense',
                'weeklyNetIncome',
                'monthlyRevenue',
                'monthlyTax',
                'monthlyExpense',
                'monthlyNetIncome',
                'monthlyOrders',

                // Pengingat hutang supplier
                'payables'
            )
        );
    }
}
