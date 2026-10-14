<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

/**
 * LANGGANAN — halaman pemilik toko: status masa aktif, paket, kirim bukti
 * transfer, riwayat pembayaran. Tetap bisa dibuka saat masa aktif habis.
 */
class BillingController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;

        return view('pages.billing.index', [
            'store' => $store,
            'sub' => $store->subscription(),
            'plans' => Plan::where('is_active', true)->orderBy('duration_months')->get(),
            'payments' => SubscriptionPayment::with('plan')->latest()->paginate(10),
            'hasPending' => SubscriptionPayment::where('status', 'pending')->exists(),
            'instructions' => Setting::query()->value('payment_instructions'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'sender_name' => ['required', 'string', 'max:100'],
            'transferred_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ], [
            'proof.required' => 'Lampirkan foto bukti transfer.',
        ]);

        if (SubscriptionPayment::where('status', 'pending')->exists()) {
            return back()->with('error', 'Masih ada pembayaran yang menunggu konfirmasi.');
        }

        $plan = Plan::where('is_active', true)->findOrFail($data['plan_id']);

        $payment = new SubscriptionPayment([
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'bank_name' => $data['bank_name'] ?? null,
            'sender_name' => $data['sender_name'],
            'transferred_at' => $data['transferred_at'],
            'note' => $data['note'] ?? null,
            'proof_image' => $request->file('proof')->store('payment-proofs', 'public'),
        ]);
        $payment->save();

        return redirect()->route('billing.index')
            ->with('success', 'Bukti transfer terkirim. Masa aktif diperpanjang setelah dikonfirmasi admin.');
    }
}
