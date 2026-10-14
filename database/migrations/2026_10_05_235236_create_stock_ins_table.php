<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number', 100)->nullable();
            $table->date('received_at')->index();
            $table->text('notes')->nullable();
            $table->decimal('total', 14, 2)->default(0);

            // pembayaran: paid = lunas · unpaid = tempo / belum dibayar
            $table->string('payment_status', 10)->default('paid')->index();
            $table->date('due_date')->nullable();          // batas bayar (hanya tempo)
            $table->date('paid_at')->nullable()->index();  // tanggal uang benar-benar keluar

            $table->timestamps();

            $table->index(['store_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ins');
    }
};
