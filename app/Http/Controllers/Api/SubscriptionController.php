<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Services\PromoService;
use App\Services\StoreManager;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Langganan dari aplikasi kasir: lihat status & paket, kirim bukti transfer.
 */
class SubscriptionController extends Controller
{
    public function __construct(private StoreManager $stores, private PromoService $promos) {}

    public function show(Request $request)
    {
        $store = $request->user()->store;
        $sub = $store->subscription();

        return response()->json([
            'status' => 'success',
            'data' => [
                'state' => $sub->state(),
                'label' => $sub->label(),
                'can_transact' => $sub->canTransact(),
                'ends_at' => $sub->endsAt()?->toIso8601String(),
                'days_left' => $sub->daysLeft(),
                'trial_ends_at' => $store->trial_ends_at?->toIso8601String(),
                'paid_until' => $store->paid_until?->toIso8601String(),
                'grace_ends_at' => $sub->graceEndsAt()?->toIso8601String(),
                'plans' => Plan::where('is_active', true)->orderBy('duration_months')
                    ->get(['id', 'code', 'name', 'duration_months', 'price']),
                'payment_instructions' => Setting::query()->value('payment_instructions'),
                'payments' => SubscriptionPayment::with('plan:id,name')->latest()->limit(20)->get()
                    ->map(fn ($p) => [
                        'id' => $p->id,
                        'plan' => $p->plan?->name,
                        'amount' => $p->amount,
                        'original_amount' => $p->original_amount,
                        'discount_amount' => $p->discount_amount,
                        'promo_code' => $p->promo_code,
                        'status' => $p->status,
                        'transferred_at' => $p->transferred_at?->toDateString(),
                        'period_end' => $p->period_end?->toIso8601String(),
                        'review_note' => $p->review_note,
                        'created_at' => $p->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    /** GET /api/subscription/promo?code=...&plan_id=... → harga setelah potongan. */
    public function promo(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'plan_id' => ['required', 'integer'],
        ]);

        $plan = Plan::where('is_active', true)->find($data['plan_id']);
        if (! $plan) {
            return response()->json(['status' => 'error', 'message' => 'Paket tidak ditemukan.'], 422);
        }

        try {
            $q = $this->promos->quote($request->user()->store, $plan, $data['code'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['status' => 'success', 'data' => PromoService::payload($q)]);
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'sender_name' => ['required', 'string', 'max:100'],
            'transferred_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'promo_code' => ['nullable', 'string', 'max:30'],
            'proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        try {
            $payment = $this->stores->submitPayment($request->user()->store, $data, $request->file('proof'));
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bukti transfer terkirim. Masa aktif diperpanjang setelah dikonfirmasi admin.',
            'data' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'discount_amount' => $payment->discount_amount,
                'promo_code' => $payment->promo_code,
            ],
        ], 201);
    }
}
