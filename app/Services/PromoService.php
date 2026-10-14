<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use Illuminate\Validation\ValidationException;

/**
 * MENGHITUNG HARGA PAKET + KODE PROMO
 *
 * quote() dipakai di 3 tempat dengan aturan yang sama:
 *  - cek kode di form perpanjang (web & aplikasi) sebelum transfer
 *  - saat toko mengirim bukti transfer (dikunci, supaya kuota tidak terlewati)
 *
 * Kode yang sudah dipakai pada pembayaran "pending" dihitung terpakai;
 * kalau pembayaran itu ditolak, kuotanya kembali.
 */
class PromoService
{
    /**
     * @return array{price:int, discount:int, amount:int, promo:?PromoCode}
     */
    public function quote(Store $store, Plan $plan, ?string $code, bool $lock = false): array
    {
        $price = (int) $plan->price;
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return ['price' => $price, 'discount' => 0, 'amount' => $price, 'promo' => null];
        }

        $query = PromoCode::where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }
        $promo = $query->first();

        $fail = fn (string $msg) => throw ValidationException::withMessages(['promo_code' => $msg]);
        $today = now()->startOfDay();

        if (! $promo || ! $promo->is_active) {
            $fail("Kode promo $code tidak ditemukan.");
        }
        if ($today->lt($promo->starts_at)) {
            $fail("Kode $code baru berlaku mulai " . $promo->starts_at->translatedFormat('d M Y') . '.');
        }
        if ($today->gt($promo->ends_at)) {
            $fail("Kode $code sudah berakhir pada " . $promo->ends_at->translatedFormat('d M Y') . '.');
        }
        if (! $promo->appliesToPlan($plan->id)) {
            $names = Plan::whereIn('id', $promo->plan_ids ?? [])->pluck('name')->join(', ');
            $fail("Kode $code hanya berlaku untuk paket $names.");
        }
        if ($promo->max_uses !== null && $promo->usedCount() >= $promo->max_uses) {
            $fail("Kuota kode $code sudah habis.");
        }

        $mine = SubscriptionPayment::withoutGlobalScope('store')
            ->where('store_id', $store->id)
            ->where('promo_code_id', $promo->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
        if ($mine >= max(1, $promo->per_store_limit)) {
            $fail("Kode $code sudah pernah Anda pakai.");
        }

        if ($promo->first_payment_only) {
            $paidBefore = SubscriptionPayment::withoutGlobalScope('store')
                ->where('store_id', $store->id)
                ->where('status', 'confirmed')
                ->exists();
            if ($paidBefore) {
                $fail("Kode $code khusus untuk pembayaran pertama.");
            }
        }

        $discount = $promo->discountFor($price);

        return ['price' => $price, 'discount' => $discount, 'amount' => $price - $discount, 'promo' => $promo];
    }

    /** Bentuk JSON untuk form (web & aplikasi). */
    public static function payload(array $q): array
    {
        return [
            'price' => $q['price'],
            'discount' => $q['discount'],
            'amount' => $q['amount'],
            'promo' => $q['promo'] ? [
                'code' => $q['promo']->code,
                'label' => $q['promo']->label(),
                'description' => $q['promo']->description,
                'ends_at' => $q['promo']->ends_at->toDateString(),
            ] : null,
        ];
    }
}
