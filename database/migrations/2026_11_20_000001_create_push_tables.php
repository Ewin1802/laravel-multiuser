<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PUSH NOTIFICATION (Firebase Cloud Messaging).
 *
 * push_tokens   token FCM tiap HP yang login (satu baris per token).
 *               app = kasir (aplikasi kasir) | admin (aplikasi admin).
 *               Notif pendaftar baru hanya dikirim ke token app = admin.
 * announcements riwayat pengumuman yang dikirim superadmin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('app', 10)->default('kasir')->index();  // kasir | admin
            $table->unsignedBigInteger('access_token_id')->nullable()->index(); // token login Sanctum
            $table->string('device_id', 100)->nullable()->index();
            $table->char('token_hash', 64)->unique();      // sha256(token) untuk cari cepat
            $table->text('token');
            $table->string('platform', 20)->default('android');
            $table->string('app_version', 30)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('body', 500);
            $table->string('target', 20)->default('all');   // all | store
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('sent');   // sent | failed
            $table->string('message_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('push_tokens');
    }
};
