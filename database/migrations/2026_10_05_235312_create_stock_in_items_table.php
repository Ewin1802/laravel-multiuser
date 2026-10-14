<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BARIS STOK MASUK + BATCH EXPIRE
 *
 * stock_in_items mencatat satuan yang dipakai saat menerima barang
 * beserta hasil konversinya (sama seperti order_items):
 *   quantity        = 5 SLOP
 *   unit_conversion = 160
 *   base_quantity   = 800 batang
 *   cost_price      = harga beli per SATUAN YANG DIPAKAI (per slop)
 *   cost_per_base   = cost_price / unit_conversion (modal per batang)
 *
 * Produk dengan track_expiry = 1: setiap baris stok masuk membuat SATU
 * stock_batches. Sisa batch disimpan dalam satuan terkecil. Penjualan
 * mengambil batch dengan expiry_date paling dekat (FEFO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_in_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('stock_in_id')->constrained('stock_ins')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_unit_id')->nullable()->constrained('product_units')->nullOnDelete();

            $table->string('unit_name', 30)->nullable();
            $table->decimal('unit_conversion', 14, 3)->default(1);
            $table->decimal('quantity', 14, 3);
            $table->decimal('base_quantity', 14, 3);

            $table->decimal('cost_price', 14, 2);          // per satuan yang dipakai
            $table->decimal('cost_per_base', 14, 4);       // per satuan terkecil

            $table->date('expiry_date')->nullable();       // wajib bila track_expiry
            $table->string('batch_code', 50)->nullable();  // no. batch dari pabrik (opsional)
            $table->timestamps();

            $table->index(['product_id', 'stock_in_id']);
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('stock_in_item_id')->nullable()->constrained('stock_in_items')->cascadeOnDelete();

            $table->date('expiry_date')->nullable();
            $table->string('batch_code', 50)->nullable();
            $table->date('received_at');

            $table->decimal('initial_quantity', 14, 3);     // satuan terkecil
            $table->decimal('remaining_quantity', 14, 3);   // satuan terkecil
            $table->decimal('cost_price', 14, 4);           // modal per satuan terkecil

            // active   : bisa dijual (selama belum lewat expiry_date)
            // depleted : habis terjual
            // written_off : sisa dikeluarkan (kedaluwarsa → rusak / retur)
            $table->string('status', 20)->default('active');
            $table->timestamps();

            // urutan FEFO
            $table->index(['product_id', 'status', 'expiry_date']);
            $table->index(['store_id', 'status', 'expiry_date']);
        });

        /**
         * Barang keluar BUKAN karena penjualan: kedaluwarsa, rusak, retur
         * ke supplier. Bisa dibuat dari web admin atau dari HP kasir saat
         * offline (client_uuid → aman dikirim ulang).
         */
        Schema::create('stock_write_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);            // satuan terkecil
            $table->decimal('cost_price', 14, 4)->nullable();
            $table->string('reason', 20);                  // expired | damaged | returned
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['store_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_write_offs');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('stock_in_items');
    }
};
