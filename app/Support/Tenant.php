<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * TOKO YANG SEDANG AKTIF (tenant).
 *
 * Sumber store_id, berurutan:
 *  1. Tenant::set() / Tenant::run() — dipakai seeder, command, job.
 *  2. User yang login (users.store_id).
 *
 * Superadmin tidak punya store_id → model toko TIDAK difilter (panel
 * superadmin melihat semua toko). Tamu / user tanpa toko → filter
 * "tidak ada baris" supaya data toko lain tidak pernah bocor.
 *
 * PENTING untuk query mentah (DB::table, join, validasi exists/unique):
 * filter global Eloquent TIDAK berlaku di sana. Pakai Tenant::id(),
 * Tenant::exists(), Tenant::unique().
 */
class Tenant
{
    private static ?int $override = null;

    private static bool $overridden = false;

    /** store_id toko aktif, null untuk superadmin/console tanpa set(). */
    public static function id(): ?int
    {
        if (self::$overridden) {
            return self::$override;
        }

        $user = Auth::user();

        return $user?->store_id ? (int) $user->store_id : null;
    }

    /** store_id toko aktif; gagal keras bila tidak ada (untuk menulis data). */
    public static function idOrFail(): int
    {
        $id = self::id();

        if ($id === null) {
            throw new \LogicException('Tidak ada toko aktif untuk operasi ini.');
        }

        return $id;
    }

    /**
     * Boleh melihat semua toko tanpa filter? Superadmin, atau proses
     * console/queue yang belum memilih toko.
     */
    public static function bypass(): bool
    {
        if (self::$overridden) {
            return self::$override === null;
        }

        $user = Auth::user();

        if ($user) {
            return $user->role === 'superadmin';
        }

        return app()->runningInConsole();
    }

    public static function set(?int $storeId): void
    {
        self::$override = $storeId;
        self::$overridden = true;
    }

    public static function forget(): void
    {
        self::$override = null;
        self::$overridden = false;
    }

    /** Jalankan $callback seolah-olah toko $storeId sedang aktif. */
    public static function run(?int $storeId, callable $callback): mixed
    {
        $prevOverride = self::$override;
        $prevOverridden = self::$overridden;

        self::set($storeId);

        try {
            return $callback();
        } finally {
            self::$override = $prevOverride;
            self::$overridden = $prevOverridden;
        }
    }

    /** Rule validasi exists yang dibatasi toko aktif. */
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('store_id', self::idOrFail());
    }

    /** Rule validasi unique yang dibatasi toko aktif. */
    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('store_id', self::idOrFail());
    }

    /**
     * DB::table() yang langsung difilter toko aktif.
     * Mendukung alias: Tenant::table('stock_ins as s') → WHERE s.store_id = ?
     * Tabel yang di-join lewat id (order_items → orders) otomatis ikut
     * toko yang sama, jadi cukup tabel utamanya yang difilter.
     */
    public static function table(string $table): Builder
    {
        $parts = preg_split('/\s+as\s+/i', trim($table));
        $ref = trim(end($parts));

        return DB::table($table)->where($ref . '.store_id', self::idOrFail());
    }
}
