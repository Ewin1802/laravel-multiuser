<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KODE PROMO langganan (dibuat superadmin, berlaku untuk semua toko).
 *
 * Potongan dihitung saat toko mengirim bukti transfer dan disimpan di
 * subscription_payments, jadi mengubah / menghapus promo nanti tidak
 * mengubah pembayaran yang sudah tercatat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();            // selalu huruf besar
            $table->string('description')->nullable();       // catatan internal / teks promo

            // percent : value = persen (1–100), max_discount = batas Rp (opsional)
            // amount  : value = potongan Rp
            $table->string('discount_type', 10)->default('percent');
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('max_discount')->nullable();

            $table->json('plan_ids')->nullable();             // null = semua paket
            $table->date('starts_at');                        // berlaku mulai (termasuk)
            $table->date('ends_at');                          // berlaku sampai (termasuk)

            $table->unsignedInteger('max_uses')->nullable();  // kuota total, null = tanpa batas
            $table->unsignedSmallInteger('per_store_limit')->default(1);
            $table->boolean('first_payment_only')->default(false); // hanya toko yang belum pernah bayar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('amount')
                ->constrained('promo_codes')->nullOnDelete();
            $table->string('promo_code', 30)->nullable()->after('promo_code_id'); // salinan teks kode
            $table->unsignedInteger('original_amount')->nullable()->after('promo_code'); // harga paket sebelum potongan
            $table->unsignedInteger('discount_amount')->default(0)->after('original_amount');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['promo_code', 'original_amount', 'discount_amount']);
        });
        Schema::dropIfExists('promo_codes');
    }
};
