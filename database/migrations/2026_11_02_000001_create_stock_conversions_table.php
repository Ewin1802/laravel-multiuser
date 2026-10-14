<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUKA KEMASAN — riwayat pemecahan stok kemasan menjadi eceran.
 *
 * Contoh: 1 GALON "Oli Jumbo Hydro 68" (isi 20) dibuka →
 *   stok produk galon  − 1
 *   stok produk eceran + 20 (LITER)
 *   harga beli eceran  = harga beli galon ÷ 20
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('source_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('target_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('source_qty', 10, 2);      // jumlah kemasan yang dibuka
            $table->decimal('ratio', 10, 3);           // isi per kemasan (mis. 20 liter)
            $table->decimal('target_qty', 10, 2);      // = source_qty × ratio
            $table->decimal('unit_cost', 12, 2)->nullable(); // harga beli per satuan eceran
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['source_product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_conversions');
    }
};
