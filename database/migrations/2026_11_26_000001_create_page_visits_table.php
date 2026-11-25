<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pencatatan kunjungan halaman depan (landing) untuk statistik sederhana:
 * berapa kali situs dikunjungi dan dari IP mana saja. Dipakai di menu
 * Superadmin → Kunjungan Web. Baris lama boleh dibersihkan berkala.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();          // IPv4/IPv6
            $table->string('path', 255)->default('/');
            $table->string('referer', 255)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamp('visited_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
