<?php

use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClockEventController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\OrderSyncController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReceivableController;
use App\Http\Controllers\Api\StockWriteOffController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API APLIKASI KASIR (prefix /api)
|--------------------------------------------------------------------------
|
| Setiap request terautentikasi WAJIB membawa header:
|   Authorization: Bearer <token>
|   X-Device-Id:   <id perangkat>      (ditolak 426 bila tidak ada)
|   X-App-Version: <versi aplikasi>    (opsional)
|
| Lapisan middleware:
|   api.store        akun pemilik toko yang sudah disetujui
|   device           catat HP, simpan di request
|   store.writable   tolak perubahan data saat langganan habis (hanya-lihat)
|
| Transaksi (save-order, pembayaran piutang, barang keluar) TIDAK memakai
| store.writable: transaksi yang sudah terjadi di HP selalu diterima.
|
*/

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:10,1')->name('auth.register');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:20,1')->name('auth.login');

Route::middleware(['auth:sanctum', 'api.store', 'device'])->group(function () {

    // ---------------------------------------------------------------- sesi
    Route::get('/user', fn (Request $request) => response()->json([
        'status' => 'success',
        'data' => $request->user(),
    ]))->name('auth.user');

    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');

    // ------------------------------------------- lisensi & perangkat kasir
    Route::get('/license', [LicenseController::class, 'show'])->name('api.license');
    Route::post('/device/claim', [DeviceController::class, 'claim'])->name('api.device.claim');
    Route::post('/clock-events', [ClockEventController::class, 'store'])->name('api.clock-events');

    // --------------------------------------------------------- langganan
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('api.subscription');
    Route::get('/subscription/promo', [SubscriptionController::class, 'promo'])->name('api.subscription.promo');
    Route::post('/subscription/payments', [SubscriptionController::class, 'storePayment'])
        ->name('api.subscription.payments');

    Route::get('/app-settings', [AppSettingController::class, 'show'])->name('api.app-settings');

    // ------------------------------------------------- transaksi dari HP
    // Selalu diterima (lihat Api\OrderController@saveOrder).
    Route::post('/save-order', [OrderController::class, 'saveOrder'])->name('orders.save');
    Route::post('/receivables/payments', [ReceivableController::class, 'storePayment'])
        ->name('api.receivables.payments.store');
    Route::post('/stock-write-offs', [StockWriteOffController::class, 'store'])
        ->name('api.stock-write-offs.store');

    // ------------------------------------------------------------- baca
    Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
    Route::get('/api-categories', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::get('/api-discounts', [DiscountController::class, 'index'])->name('api.discounts.index');
    Route::get('/receivables', [ReceivableController::class, 'index'])->name('api.receivables.index');

    // /orders/sync HARUS di atas /orders/{date?} supaya tidak tertangkap sebagai {date}
    Route::get('/orders/sync', [OrderSyncController::class, 'sync'])->name('orders.sync');
    Route::get('/orders/{date?}', [OrderController::class, 'index'])->name('reports.orders');
    Route::get('/summary/{date?}', [OrderController::class, 'summary'])->name('reports.summary');
    Route::get('/order-item/{date?}', [OrderItemController::class, 'index'])->name('reports.order-item');
    Route::get('/order-sales', [OrderItemController::class, 'orderSales'])->name('reports.order-sales');

    // ------------------------------- ubah master data (hanya saat aktif)
    Route::middleware('store.writable')->group(function () {
        Route::post('/products', [ProductController::class, 'store'])->name('api.products.store');
        Route::post('/products/edit', [ProductController::class, 'update'])->name('api.products.update');
        Route::post('/products/status', [ProductController::class, 'updateStatus'])->name('api.products.status');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('api.products.destroy');

        Route::post('/api-discounts', [DiscountController::class, 'store'])->name('api.discounts.store');
    });
});
