<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpiryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductLabelController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockConversionController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StoreProfileController;
use App\Http\Controllers\Superadmin;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPriceReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIK
|--------------------------------------------------------------------------
*/

// Halaman depan aplikasi (menawarkan aplikasi kasir ke toko baru)
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Kebijakan privasi (URL ini diisi di Google Play Console)
Route::get('/kebijakan-privasi', [LandingController::class, 'privacy'])->name('privacy');
Route::redirect('/privacy', '/kebijakan-privasi', 301);
Route::redirect('/hapus-akun', '/kebijakan-privasi#hapus-akun', 302);

Route::middleware('auth')->group(function () {

    // Arah setelah login, tergantung role
    Route::get('/home', function (Request $request) {
        return match ($request->user()->role) {
            'superadmin' => redirect()->route('superadmin.dashboard'),
            'admin' => redirect()->route('dashboard'),
            default => redirect()->route('pending'),
        };
    })->name('home');

    // Toko yang baru daftar (role user) menunggu persetujuan
    Route::view('/menunggu-persetujuan', 'pages.auth.pending')->name('pending');

    /*
    |--------------------------------------------------------------------------
    | PEMILIK TOKO (role:admin)
    |--------------------------------------------------------------------------
    |
    | Semua data otomatis difilter ke toko milik user yang login
    | (App\Models\Concerns\BelongsToStore).
    |
    | store.writable: saat masa aktif habis / diblokir, halaman tetap bisa
    | dibuka tapi perubahan data ditolak (hanya-lihat). Halaman Langganan
    | dikecualikan supaya toko tetap bisa mengirim bukti pembayaran.
    |
    | ATURAN URUTAN: route khusus (products/import, products/labels, ...)
    | selalu SEBELUM Route::resource yang sama.
    |
    */

    Route::middleware('role:admin')->group(function () {

        // ---------------- langganan (selalu bisa diakses)
        Route::get('/langganan', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/langganan', [BillingController::class, 'store'])->name('billing.store');
        Route::get('/langganan/promo', [BillingController::class, 'promo'])->name('billing.promo');

        Route::middleware('store.writable')->group(function () {

            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

            // ---------------- profil toko & perangkat
            Route::get('/toko', [StoreProfileController::class, 'edit'])->name('store.profile');
            Route::put('/toko', [StoreProfileController::class, 'update'])->name('store.profile.update');

            Route::resource('categories', CategoryController::class)->except(['show']);

            // ---------------- produk
            Route::get('products/import', [ProductImportController::class, 'create'])->name('products.import');
            Route::post('products/import/preview', [ProductImportController::class, 'preview'])->name('products.import.preview');
            Route::post('products/import', [ProductImportController::class, 'store'])->name('products.import.store');
            Route::get('products/import/errors', [ProductImportController::class, 'errors'])->name('products.import.errors');
            Route::get('products/labels', [ProductLabelController::class, 'index'])->name('products.labels');
            Route::get('products/labels/print', [ProductLabelController::class, 'print'])->name('products.labels.print');
            Route::get('products/similar', [ProductController::class, 'similar'])->name('products.similar');
            Route::resource('products', ProductController::class)->except(['show']);

            Route::resource('discounts', DiscountController::class)->except(['show']);

            // ---------------- stok & pembelian
            Route::resource('suppliers', SupplierController::class)->except(['show']);
            Route::post('stock-ins/{id}/pay', [StockInController::class, 'pay'])->name('stock-ins.pay');
            Route::resource('stock-ins', StockInController::class);

            Route::get('stock-conversions', [StockConversionController::class, 'index'])->name('stock-conversions.index');
            Route::post('stock-conversions', [StockConversionController::class, 'store'])->name('stock-conversions.store');
            Route::delete('stock-conversions/{id}', [StockConversionController::class, 'destroy'])->name('stock-conversions.destroy');

            // expire & barang keluar (rusak / retur)
            Route::get('expire', [ExpiryController::class, 'index'])->name('expiry.index');
            Route::post('expire/barang-keluar', [ExpiryController::class, 'store'])->name('expiry.store');
            Route::post('expire/batch/{batch}/keluarkan', [ExpiryController::class, 'writeOffBatch'])
                ->whereNumber('batch')->name('expiry.batch.write-off');

            Route::get('reports/supplier-prices', SupplierPriceReportController::class)->name('reports.supplier-prices');

            Route::resource('expenses', ExpenseController::class)->except(['show']);

            // ---------------- transaksi
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/summary', [OrderController::class, 'summary'])->name('orders.summary');
            Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('orders.show');
            Route::get('/orders/{id}/edit', [OrderController::class, 'edit'])->whereNumber('id')->name('orders.edit');
            Route::put('/orders/{id}', [OrderController::class, 'update'])->whereNumber('id')->name('orders.update');
            Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->whereNumber('id')->name('orders.destroy');

            // ---------------- piutang pelanggan
            Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
            Route::get('/receivables/{id}', [ReceivableController::class, 'show'])->whereNumber('id')->name('receivables.show');
            Route::post('/receivables/{id}/payments', [ReceivableController::class, 'storePayment'])
                ->whereNumber('id')->name('receivables.payments.store');
            Route::delete('/receivables/{id}/payments/{paymentId}', [ReceivableController::class, 'destroyPayment'])
                ->whereNumber('id')->whereNumber('paymentId')->name('receivables.payments.destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | SUPERADMIN (pemilik aplikasi)
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [Superadmin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/toko', [Superadmin\StoreController::class, 'index'])->name('stores.index');
        Route::get('/toko/{store}', [Superadmin\StoreController::class, 'show'])->name('stores.show');
        Route::post('/toko/{store}/setujui', [Superadmin\StoreController::class, 'approve'])->name('stores.approve');
        Route::post('/toko/{store}/blokir', [Superadmin\StoreController::class, 'suspend'])->name('stores.suspend');
        Route::post('/toko/{store}/buka-blokir', [Superadmin\StoreController::class, 'unsuspend'])->name('stores.unsuspend');
        Route::post('/toko/{store}/lepas-kasir', [Superadmin\StoreController::class, 'releaseDevice'])->name('stores.release-device');
        Route::post('/toko/{store}/pembayaran', [Superadmin\StoreController::class, 'recordPayment'])->name('stores.record-payment');
        Route::post('/toko/{store}/catatan', [Superadmin\StoreController::class, 'updateNotes'])->name('stores.notes');

        Route::get('/pembayaran', [Superadmin\PaymentController::class, 'index'])->name('payments.index');
        Route::post('/pembayaran/{payment}/konfirmasi', [Superadmin\PaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('/pembayaran/{payment}/tolak', [Superadmin\PaymentController::class, 'reject'])->name('payments.reject');

        Route::get('/paket', [Superadmin\PlanController::class, 'index'])->name('plans.index');
        Route::post('/paket', [Superadmin\PlanController::class, 'store'])->name('plans.store');
        Route::put('/paket/{plan}', [Superadmin\PlanController::class, 'update'])->name('plans.update');

        Route::get('/promo', [Superadmin\PromoController::class, 'index'])->name('promos.index');
        Route::post('/promo', [Superadmin\PromoController::class, 'store'])->name('promos.store');
        Route::put('/promo/{promo}', [Superadmin\PromoController::class, 'update'])->name('promos.update');
        Route::delete('/promo/{promo}', [Superadmin\PromoController::class, 'destroy'])->name('promos.destroy');

        Route::get('/log-jam', [Superadmin\TamperLogController::class, 'index'])->name('tamper.index');

        // pengaturan platform: landing page, aturan langganan, tagihan server
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/server-paid', [SettingController::class, 'serverPaid'])->name('settings.server-paid');
    });
});
