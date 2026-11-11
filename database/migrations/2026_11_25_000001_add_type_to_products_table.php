<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JENIS PRODUK
 *
 *   item        Barang     — dijual di kasir, stok dihitung (rokok, air botol)
 *   menu        Menu racikan — dijual di kasir, stok TIDAK dihitung (es kopi, nasi goreng)
 *   ingredient  Bahan baku — TIDAK tampil di kasir, stok dihitung (susu, biji kopi, cup)
 *
 * Produk lama otomatis "item", jadi toko yang sudah berjalan tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 12)->default('item')->after('category_id');
            $table->index(['store_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'type']);
            $table->dropColumn('type');
        });
    }
};
