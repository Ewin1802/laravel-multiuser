<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tampilkan promo di halaman depan.
 *
 * Bila dinyalakan (default), promo yang sedang aktif otomatis muncul
 * sebagai pita di paling atas halaman depan. Matikan untuk kode privat
 * (mis. khusus WA/brosur) yang tidak ingin dipajang ke umum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->boolean('show_on_landing')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn('show_on_landing');
        });
    }
};
