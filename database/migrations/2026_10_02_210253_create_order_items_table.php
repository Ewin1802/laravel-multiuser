<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris order menyimpan satuan yang dipakai BESERTA hasil konversinya,
 * supaya riwayat tetap benar walau konversi produk diubah kemudian.
 *
 *   quantity        = jumlah dalam satuan yang dijual (mis. 2 BUNGKUS)
 *   unit_conversion = isi satuan itu SAAT transaksi (16)
 *   base_quantity   = quantity × unit_conversion (32 batang)
 *   price           = harga per satuan yang dijual
 *   cost_price      = modal per SATUAN TERKECIL (rata-rata dari batch terpakai)
 *   cost_total      = base_quantity × cost_price
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->string('product_name')->nullable();

            $table->string('unit_name', 30)->nullable();
            $table->decimal('unit_conversion', 14, 3)->default(1);
            $table->decimal('quantity', 14, 3);
            $table->decimal('base_quantity', 14, 3);

            $table->integer('price');
            $table->decimal('cost_price', 14, 4)->nullable();
            $table->decimal('cost_total', 16, 2)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'product_id']);
        });

        /**
         * Batch yang terpakai oleh satu baris order (FEFO). stock_batch_id
         * NULL = jumlah yang tidak tertutup batch mana pun (stok server
         * kurang saat order diterima) → order ditandai stock_conflict.
         */
        Schema::create('order_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedBigInteger('stock_batch_id')->nullable()->index();
            $table->decimal('quantity', 14, 3);           // satuan terkecil
            $table->decimal('cost_price', 14, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_batches');
        Schema::dropIfExists('order_items');
    }
};
