<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Services\StoreManager;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private StoreManager $stores) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $payments = SubscriptionPayment::with(['plan', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $storeNames = Store::whereIn('id', $payments->pluck('store_id'))->pluck('name', 'id');

        return view('pages.superadmin.payments.index', compact('payments', 'status', 'storeNames'));
    }

    public function confirm(Request $request, SubscriptionPayment $payment)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->stores->confirmPayment($payment, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Pembayaran dikonfirmasi. Aktif sampai '
            . $payment->fresh()->period_end->translatedFormat('d M Y') . '.');
    }

    public function reject(Request $request, SubscriptionPayment $payment)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']], [
            'note.required' => 'Tulis alasan penolakan supaya toko tahu apa yang salah.',
        ]);
        $this->stores->rejectPayment($payment, $request->user(), $data['note']);

        return back()->with('success', 'Pembayaran ditolak.');
    }
}
