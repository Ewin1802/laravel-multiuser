<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERANGKAT, PEMBAYARAN LANGGANAN, dan LOG PENGAMAN JAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Setiap HP yang pernah login ke sebuah toko. Hanya SATU yang
         * menjadi perangkat kasir aktif (stores.active_device_id); yang
         * lain hanya boleh mode lihat (harus online).
         */
        Schema::create('store_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('device_id', 100);              // UUID instalasi + ID hardware
            $table->string('device_name')->nullable();     // mis. "Samsung SM-A155F"
            $table->string('platform', 20)->nullable();    // android | ios
            $table->string('app_version', 20)->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('activated_at')->nullable();    // terakhir jadi kasir aktif
            $table->timestamp('deactivated_at')->nullable();  // terakhir dilepas
            $table->timestamps();

            $table->unique(['store_id', 'device_id']);
            $table->index('device_id');                    // deteksi HP yang sama di banyak toko
        });

        Schema::create('device_switch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('from_store_device_id')->nullable();
            $table->unsignedBigInteger('to_store_device_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('app');  // app | superadmin
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
        });

        /**
         * Kejadian jam HP mundur / tidak wajar yang dilaporkan aplikasi saat
         * sinkron. client_uuid mencegah laporan ganda saat dikirim ulang.
         */
        Schema::create('clock_tamper_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('store_device_id')->nullable()->index();
            $table->string('device_id', 100)->nullable();
            $table->uuid('client_uuid')->nullable()->unique();

            // clock_rollback : jam HP lebih awal dari waktu tepercaya terakhir
            // clock_jump     : selisih jam HP vs penghitung waktu perangkat tidak wajar
            // offline_limit  : melewati batas offline
            // license_expired: mencoba transaksi saat masa aktif habis
            $table->string('kind', 30);
            $table->string('device_time')->nullable();      // jam HP saat terdeteksi
            $table->string('trusted_time')->nullable();     // perkiraan waktu sebenarnya
            $table->bigInteger('drift_seconds')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('reported_at')->nullable();   // jam server saat diterima
            $table->timestamps();

            $table->index(['store_id', 'kind']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->unsignedInteger('amount');
            $table->string('bank_name', 50)->nullable();
            $table->string('sender_name', 100)->nullable();
            $table->date('transferred_at')->nullable();
            $table->string('proof_image')->nullable();      // foto bukti transfer
            $table->text('note')->nullable();

            // pending | confirmed | rejected
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // periode yang dibuka oleh pembayaran ini (diisi saat dikonfirmasi)
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('clock_tamper_logs');
        Schema::dropIfExists('device_switch_logs');
        Schema::dropIfExists('store_devices');
    }
};
