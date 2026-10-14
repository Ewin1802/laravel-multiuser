<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOKO (tenant) dan PAKET LANGGANAN.
 *
 * Alur:
 *  1. Toko daftar sendiri  → stores.status = pending, user role = user.
 *  2. Superadmin menyetujui → status = active, user role = admin,
 *     trial 3 hari mulai dihitung dari approved_at.
 *  3. Transfer manual dikonfirmasi superadmin → paid_until diperpanjang.
 *
 * Semua tanggal di sini adalah WAKTU SERVER. Jam HP tidak pernah
 * menentukan masa aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();          // monthly | yearly
            $table->string('name');
            $table->unsignedSmallInteger('duration_months'); // 1 | 12
            $table->unsignedInteger('price');                // Rupiah
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            // ---------------- profil toko (tampil di struk & web admin)
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('receipt_footer')->nullable();
            // rekening toko, dicetak di faktur A4 untuk pelanggan
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_holder', 100)->nullable();
            $table->boolean('feature_draft_order')->default(false);

            // ---------------- status pendaftaran
            // pending   : baru daftar, menunggu persetujuan superadmin
            // active    : disetujui (masa aktif ditentukan trial/paid_until)
            // suspended : diblokir superadmin (hanya-lihat)
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable(); // user superadmin

            // ---------------- trial (sekali per toko, menempel pada akun)
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            // ---------------- langganan berbayar
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamp('paid_until')->nullable();     // akhir periode berbayar
            $table->unsignedSmallInteger('grace_days')->default(7);

            // ---------------- perangkat kasir aktif (satu per toko)
            // Tanpa FK supaya tidak melingkar dengan store_devices.
            $table->unsignedBigInteger('active_device_id')->nullable()->index();

            // ID perangkat saat mendaftar — untuk mendeteksi trial berulang
            $table->string('registered_device_id', 100)->nullable()->index();

            $table->text('suspend_reason')->nullable();
            $table->text('admin_notes')->nullable();          // catatan superadmin
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
        Schema::dropIfExists('plans');
    }
};
