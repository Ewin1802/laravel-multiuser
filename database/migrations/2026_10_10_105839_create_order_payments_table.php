<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat pembayaran / cicilan untuk nota TEMPO.
 *
 * client_payment_id dibuat aplikasi kasir (UUID) saat pembayaran dicatat,
 * termasuk saat offline. UNIQUE → kalau dikirim ulang, tidak tercatat dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->uuid('client_payment_id')->unique();
            $table->integer('amount');
            $table->string('payment_method', 30)->default('Cash');
            $table->dateTime('paid_at')->index();
            $table->string('note')->nullable();
            $table->string('source', 10)->default('app'); // app | web
            $table->integer('id_kasir')->nullable();
            $table->string('nama_kasir')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
