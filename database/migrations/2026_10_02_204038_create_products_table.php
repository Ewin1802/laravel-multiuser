<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRODUK + MULTI-SATUAN
 *
 * - products.stock      : stok dalam SATUAN TERKECIL (base_unit), mis. batang.
 * - products.cost_price : modal per SATUAN TERKECIL.
 * - products.price      : harga jual satuan terkecil (cermin dari baris
 *                         product_units yang is_base = 1, supaya kode lama
 *                         yang membaca products.price tetap benar).
 * - products.barcode    : barcode satuan terkecil (cermin juga).
 *
 * product_units berisi SEMUA satuan jual produk, termasuk satuan terkecil
 * (conversion = 1). Harga diisi per satuan, bukan hasil perkalian.
 *
 *   Rokok A  base_unit = BATANG
 *   ├─ BATANG   conversion 1    harga 2.500   barcode -
 *   ├─ BUNGKUS  conversion 16   harga 32.000  barcode 8999...
 *   └─ SLOP     conversion 160  harga 310.000 barcode 8999...
 *
 * track_expiry = 1 → stok dicatat per batch (stock_batches) dan penjualan
 * memakai FEFO. track_expiry = 0 → stok biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('code', 50)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('cost_price', 14, 4)->nullable();
            $table->decimal('stock', 14, 3)->default(0);
            $table->string('base_unit')->default('PCS');
            $table->boolean('track_expiry')->default(false);
            $table->unsignedSmallInteger('expiry_warning_days')->default(30);
            $table->boolean('status')->default(1);
            $table->boolean('is_favorite')->default(0);
            $table->timestamps();

            // unik PER TOKO (dua toko boleh menjual barang dengan barcode sama)
            $table->unique(['store_id', 'code']);
            $table->unique(['store_id', 'barcode']);
            $table->index(['store_id', 'name']);
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 30);                    // BATANG, BUNGKUS, SLOP
            $table->decimal('conversion', 14, 3);          // isi dalam satuan terkecil
            $table->decimal('price', 12, 2);               // harga jual satuan ini
            $table->string('barcode', 64)->nullable();
            $table->boolean('is_base')->default(false);    // satuan terkecil (conversion 1)
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->unique(['store_id', 'barcode']);
            $table->index(['product_id', 'conversion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
    }
};
