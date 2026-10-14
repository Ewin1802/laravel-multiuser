<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            // UUID dibuat aplikasi kasir SEBELUM pembayaran. Satu UUID =
            // satu transaksi. UNIQUE mencegah order ganda saat kirim ulang.
            $table->uuid('client_order_id')->unique();

            // ---------------- perangkat pembuat transaksi
            $table->unsignedBigInteger('store_device_id')->nullable()->index();
            $table->string('device_id', 100)->nullable();

            // ---------------- pembayaran
            $table->integer('payment_amount');
            $table->integer('sub_total');
            $table->integer('tax');
            $table->integer('discount');
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->integer('service_charge');
            $table->integer('total');
            $table->string('payment_method');             // Cash | Transfer | QRIS | Tempo

            // ---------------- piutang (penjualan Tempo)
            $table->string('payment_status', 20)->default('paid')->index();
            $table->integer('paid_amount')->default(0);
            $table->date('due_date')->nullable()->index();
            $table->timestamp('paid_off_at')->nullable();

            $table->decimal('total_item', 14, 3);

            $table->integer('table_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('status')->nullable();

            $table->integer('id_kasir');
            $table->string('nama_kasir');

            // waktu transaksi menurut HP (format ISO dari aplikasi)
            $table->string('transaction_time');

            // ---------------- pengaman sinkron
            // received_at      : kapan server menerima order (jam server)
            // device_elapsed_ms: penghitung waktu perangkat saat transaksi
            //                    (tidak ikut berubah saat tanggal HP diganti)
            // clock_suspect    : tanggal transaksi janggal (di masa depan,
            //                    lebih awal dari waktu tepercaya terakhir, dll)
            // stock_conflict   : stok server tidak cukup saat diterima —
            //                    order TETAP disimpan, stok boleh minus,
            //                    ditandai untuk dicek admin
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('device_elapsed_ms')->nullable();
            $table->boolean('clock_suspect')->default(false)->index();
            $table->boolean('stock_conflict')->default(false)->index();
            // dibuat HP yang sudah bukan kasir aktif (transaksi offline yang
            // belum terkirim saat kasir dipindah) — tetap diterima, ditandai
            $table->boolean('from_inactive_device')->default(false);
            $table->text('sync_notes')->nullable();

            $table->timestamps();

            $table->index(['store_id', 'transaction_time']);
            $table->index(['store_id', 'payment_method', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
