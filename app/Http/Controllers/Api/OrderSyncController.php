<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDeletion;
use Illuminate\Http\Request;

class OrderSyncController extends Controller
{
    /**
     * ============================================================
     * SYNC ORDER — DIPANGGIL DARI APLIKASI KASIR
     * ============================================================
     *
     * GET /api/orders/sync?since=2026-09-29T00:00:00
     *
     * Dipanggil kasir untuk menyamakan SQLite lokalnya dengan server
     * — order baru/diubah dan order yang dihapus sejak `since`,
     * tapi khusus order, dan ada dua arah info yang dikirim balik:
     *
     * 1. `updated_orders` — order yang BARU DIBUAT atau DIUBAH
     *    (lewat edit di admin panel) sejak waktu `since`. Kasir
     *    perlu ini untuk menyamakan data kalau ada order yang
     *    dikoreksi admin (misal salah harga/qty).
     *
     * 2. `deleted_order_ids` — order_id (dan client_order_id kalau
     *    ada) yang SUDAH DIHAPUS dari server sejak waktu `since`.
     *    Kasir pakai ini untuk MENGHAPUS order yang sama dari
     *    SQLite lokalnya, supaya order yang dihapus admin (misal
     *    karena double-input) tidak lagi muncul di riwayat HP
     *    kasir.
     *
     * PENTING SOAL `since`:
     *
     * - Kalau `since` TIDAK dikirim, dianggap sinkron pertama kali
     *   — server kirim SEMUA order (biar SQLite kasir terisi penuh)
     *   dan SEMUA log penghapusan yang ada.
     * - Kalau `since` dikirim, cukup kirim order & penghapusan yang
     *   terjadi SETELAH waktu itu saja (supaya hemat, tidak perlu
     *   kirim ulang semua data tiap sinkron).
     * - Kasir WAJIB menyimpan `synced_at` dari response ini, dan
     *   mengirimnya lagi sebagai `since` di request sinkron
     *   berikutnya — supaya sinkron berikutnya cuma ambil
     *   perubahan terbaru, bukan mulai dari nol lagi.
     *
     * CATATAN: endpoint ini BELUM otomatis dipanggil dari mana pun
     * di aplikasi Flutter — itu baru langkah berikutnya (perlu
     * ditambahkan datasource + logic apply-ke-SQLite di sisi
     * Flutter, dan dipicu misal saat buka halaman riwayat transaksi
     * atau tombol "Sinkron").
     */
    public function sync(Request $request)
    {
        $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $since = $request->filled('since')
            ? $request->date('since')
            : null;

        // ============================================================
        // SERVER MENANDAI WAKTU SEKARANG SEBELUM QUERY DIJALANKAN.
        // ============================================================
        //
        // Ini yang dikirim balik sebagai `synced_at`, dan yang kasir
        // simpan untuk dipakai sebagai `since` di sinkron berikutnya.
        // Ditandai di AWAL (bukan di akhir) supaya order/penghapusan
        // yang terjadi PERSIS di tengah-tengah proses sync ini tidak
        // ada yang "kelewat" pada sinkron berikutnya.
        //
        // ============================================================

        $syncedAt = now();

        // ============================================================
        // ORDER YANG BARU/DIUBAH
        // ============================================================

        $updatedOrdersQuery = Order::with('orderItems')
            ->orderBy('id');

        if ($since) {
            $updatedOrdersQuery->where('updated_at', '>=', $since);
        }

        $updatedOrders = $updatedOrdersQuery->get();

        // ============================================================
        // ORDER YANG SUDAH DIHAPUS
        // ============================================================

        $deletionsQuery = OrderDeletion::query()
            ->orderBy('id');

        if ($since) {
            $deletionsQuery->where('deleted_at', '>=', $since);
        }

        $deletions = $deletionsQuery->get([
            'order_id',
            'client_order_id',
            'deleted_at',
        ]);

        return response()->json([
            'status' => 'success',

            'data' => [

                'updated_orders' => $updatedOrders,

                'deleted_order_ids' => $deletions
                    ->pluck('order_id')
                    ->values(),

                'deleted_client_order_ids' => $deletions
                    ->pluck('client_order_id')
                    ->filter()
                    ->values(),
            ],

            'meta' => [
                // WAJIB disimpan kasir & dikirim lagi sebagai `since`
                // pada request sync berikutnya.
                'synced_at' => $syncedAt->toIso8601String(),

                'is_full_sync' => is_null($since),

                'total_updated_orders' => $updatedOrders->count(),

                'total_deleted_orders' => $deletions->count(),
            ],
        ], 200);
    }
}
