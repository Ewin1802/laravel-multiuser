<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Subscription;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Siklus hidup toko: daftar → disetujui (trial) → bayar → diperpanjang.
 */
class StoreManager
{
    /**
     * Pendaftaran mandiri (dari aplikasi atau web). Toko berstatus
     * pending dan akunnya ber-role "user" sampai disetujui superadmin.
     *
     * @param  array{store_name:string, name:string, email:string, phone_number:string, password:string, address?:string}  $data
     */
    public function register(array $data, ?string $deviceId = null): User
    {
        return DB::transaction(function () use ($data, $deviceId) {
            $store = new Store([
                'name' => $data['store_name'],
                'phone' => $data['phone_number'],
                'address' => $data['address'] ?? null,
            ]);
            $store->status = Store::STATUS_PENDING;
            $store->registered_device_id = $deviceId ? mb_substr($deviceId, 0, 100) : null;
            $store->grace_days = Subscription::rules()->grace_days;
            $store->save();

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
                'password' => Hash::make($data['password']),
            ]);
            $user->role = 'user';
            $user->store_id = $store->id;
            $user->save();

            return $user;
        });
    }

    /**
     * Superadmin menyetujui toko. Trial dimulai SEKARANG (jam server) dan
     * hanya sekali per toko: menyetujui ulang toko yang pernah trial tidak
     * mengulang trial-nya.
     */
    public function approve(Store $store, User $by): Store
    {
        return DB::transaction(function () use ($store, $by) {
            $store->status = Store::STATUS_ACTIVE;
            $store->approved_at ??= now();
            $store->approved_by ??= $by->id;
            $store->suspend_reason = null;

            if ($store->trial_started_at === null) {
                $store->trial_started_at = now();
                $store->trial_ends_at = now()->addDays(Subscription::rules()->trial_days);
            }

            $store->save();

            User::where('store_id', $store->id)->where('role', 'user')->update(['role' => 'admin']);

            return $store;
        });
    }

    public function suspend(Store $store, ?string $reason): Store
    {
        $store->status = Store::STATUS_SUSPENDED;
        $store->suspend_reason = $reason;
        $store->save();

        return $store;
    }

    public function unsuspend(Store $store): Store
    {
        $store->status = $store->approved_at ? Store::STATUS_ACTIVE : Store::STATUS_PENDING;
        $store->suspend_reason = null;
        $store->save();

        return $store;
    }

    /**
     * Toko mengirim bukti transfer (dari web atau aplikasi), opsional dengan
     * kode promo. Kode dicek ulang di dalam transaksi + dikunci supaya kuota
     * tidak terlewati bila dua toko memakai kode yang sama bersamaan.
     *
     * @param  array{plan_id:int, sender_name:string, bank_name:?string, transferred_at:string, note:?string, promo_code:?string}  $data
     */
    public function submitPayment(Store $store, array $data, ?\Illuminate\Http\UploadedFile $proof): SubscriptionPayment
    {
        return DB::transaction(function () use ($store, $data, $proof) {
            if (SubscriptionPayment::withoutGlobalScope('store')->where('store_id', $store->id)
                ->where('status', 'pending')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['payment' => 'Masih ada pembayaran yang menunggu konfirmasi.']);
            }

            $plan = Plan::where('is_active', true)->findOrFail($data['plan_id']);
            $q = app(PromoService::class)->quote($store, $plan, $data['promo_code'] ?? null, lock: true);

            if ($q['amount'] > 0 && ! $proof) {
                throw ValidationException::withMessages(['proof' => 'Lampirkan foto bukti transfer.']);
            }

            return Tenant::run($store->id, function () use ($plan, $data, $proof, $q) {
                $payment = new SubscriptionPayment([
                    'plan_id' => $plan->id,
                    'amount' => $q['amount'],
                    'original_amount' => $q['price'],
                    'discount_amount' => $q['discount'],
                    'promo_code_id' => $q['promo']?->id,
                    'promo_code' => $q['promo']?->code,
                    'bank_name' => $data['bank_name'] ?? null,
                    'sender_name' => $data['sender_name'],
                    'transferred_at' => $data['transferred_at'],
                    'note' => $data['note'] ?? null,
                    'proof_image' => $proof?->store('payment-proofs', 'public'),
                ]);
                $payment->save();

                return $payment;
            });
        });
    }

    /**
     * Konfirmasi transfer manual → masa aktif diperpanjang sesuai paket.
     */
    public function confirmPayment(SubscriptionPayment $payment, User $by, ?string $note = null): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $by, $note) {
            // baca ulang + kunci: mencegah konfirmasi ganda bila tombol ditekan dua kali
            $locked = SubscriptionPayment::withoutGlobalScope('store')
                ->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
            }

            /** @var Store $store */
            $store = Store::whereKey($locked->store_id)->lockForUpdate()->firstOrFail();
            $plan = Plan::findOrFail($locked->plan_id);

            [$start, $end] = Subscription::for($store)->nextPeriod($plan);

            $store->paid_until = $end;
            $store->plan_id = $plan->id;
            $store->save();

            $locked->forceFill([
                'status' => 'confirmed',
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'period_start' => $start,
                'period_end' => $end,
            ])->save();

            $payment->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    public function rejectPayment(SubscriptionPayment $payment, User $by, ?string $note): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $by, $note) {
            $locked = SubscriptionPayment::withoutGlobalScope('store')
                ->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
            }

            $locked->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $locked;
        });
    }

    /**
     * Superadmin mencatat pembayaran atas nama toko (mis. bayar tunai /
     * transfer yang dikonfirmasi lewat WA) lalu langsung mengonfirmasinya.
     */
    public function recordAndConfirm(Store $store, Plan $plan, User $by, array $extra = []): SubscriptionPayment
    {
        $payment = Tenant::run($store->id, function () use ($plan, $extra) {
            $p = new SubscriptionPayment(array_merge([
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'transferred_at' => now()->toDateString(),
            ], $extra));
            $p->save();

            return $p;
        });

        return $this->confirmPayment($payment, $by, 'Dicatat oleh superadmin');
    }
}
