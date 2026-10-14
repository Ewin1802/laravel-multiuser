<?php

namespace App\Models\Concerns;

use App\Models\Store;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dipasang di SEMUA model milik toko.
 *
 * - Setiap query otomatis "WHERE store_id = toko aktif".
 * - Data baru otomatis mendapat store_id toko aktif.
 * - Superadmin (panel) tidak difilter.
 *
 * Melewati filter secara sengaja: Model::withoutGlobalScope('store').
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $builder) {
            if (Tenant::bypass()) {
                return;
            }

            $storeId = Tenant::id();
            $column = $builder->getModel()->qualifyColumn('store_id');

            if ($storeId === null) {
                // tamu / akun tanpa toko: jangan pernah tampilkan apa pun
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($column, $storeId);
        });

        static::creating(function ($model) {
            if (empty($model->store_id)) {
                $model->store_id = Tenant::idOrFail();
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
