<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ============================================================
     * TABEL LOG PENGHAPUSAN ORDER
     * ============================================================
     *
     * Order di admin panel dihapus PERMANEN (hard delete) — begitu
     * dihapus, tidak ada jejaknya lagi di tabel `orders`.
     *
     * Masalahnya: aplikasi kasir menyimpan salinan order itu juga
     * di SQLite lokal HP-nya. Kalau order dihapus dari server tapi
     * server tidak "mengingat" bahwa order itu PERNAH ada dan sudah
     * dihapus, endpoint sync tidak akan pernah bisa memberi tahu
     * kasir "order #123 sudah dihapus, buang juga dari HP kamu" —
     * karena begitu dihapus, order #123 sudah tidak ada bekasnya
     * sama sekali untuk dicari.
     *
     * Makanya sebelum sebuah order benar-benar dihapus dari tabel
     * `orders`, order_id-nya dicatat dulu ke sini beserta waktu
     * hapusnya. Endpoint sync nanti tinggal baca tabel ini untuk
     * tahu order mana saja yang perlu dibuang dari SQLite kasir.
     */
    public function up(): void
    {
        Schema::create('order_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            // ID order yang dihapus. BUKAN foreign key ke `orders`,
            // karena order aslinya justru sudah tidak ada lagi.
            $table->unsignedBigInteger('order_id');

            // Client order ID (UUID dari Flutter) kalau tersedia —
            // ini yang sebenarnya dipakai kasir untuk mencocokkan
            // order di SQLite lokalnya, jadi disimpan juga di sini
            // supaya endpoint sync tidak perlu balik nyari ke tabel
            // orders yang sudah kosong.
            $table->uuid('client_order_id')->nullable();

            $table->timestamp('deleted_at')->useCurrent();

            $table->index('order_id');
            $table->index('deleted_at');

            $table->index(['store_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_deletions');
    }
};
