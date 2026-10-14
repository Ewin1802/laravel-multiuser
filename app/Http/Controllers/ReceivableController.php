<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PIUTANG PELANGGAN (penjualan Tempo) — panel admin.
 *
 * Nota tempo dibuat dari aplikasi kasir (metode bayar "Tempo").
 * Pembayaran/cicilan bisa dicatat dari aplikasi kasir maupun dari sini.
 */
class ReceivableController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'open');   // open | overdue | paid | all
        $search = trim((string) $request->input('q', ''));
        $today = Carbon::today();

        $query = Order::query()->credit();

        match ($status) {
            'overdue' => $query->openReceivable()->whereNotNull('due_date')->whereDate('due_date', '<', $today),
            'paid' => $query->where('payment_status', 'paid'),
            'all' => null,
            default => $query->openReceivable(),
        };

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");

                $digits = ltrim(preg_replace('/\D/', '', $search), '0');
                if ($digits !== '') {
                    $q->orWhere('id', (int) $digits);
                }
            });
        }

        $orders = $query
            ->orderByRaw("CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END")
            ->orderByRaw('due_date IS NULL, due_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // ---------------- ringkasan (selalu dari semua nota terbuka) ----------------
        $open = Order::query()->credit()->openReceivable();

        $summary = (object) [
            'outstanding' => (int) (clone $open)->sum(DB::raw('total - paid_amount')),
            'open_count' => (clone $open)->count(),
            'customers' => (clone $open)->distinct('customer_name')->count('customer_name'),
            'overdue_amount' => (int) (clone $open)->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)->sum(DB::raw('total - paid_amount')),
            'overdue_count' => (clone $open)->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)->count(),
            'collected_month' => (int) OrderPayment::query()
                ->whereBetween('paid_at', [$today->copy()->startOfMonth(), $today->copy()->endOfDay()])
                ->sum('amount'),
        ];

        // pelanggan dengan piutang terbesar
        $topCustomers = (clone $open)
            ->select('customer_name', DB::raw('MAX(customer_phone) as customer_phone'),
                DB::raw('COUNT(*) as notes'), DB::raw('SUM(total - paid_amount) as remaining'))
            ->groupBy('customer_name')
            ->orderByDesc('remaining')
            ->limit(5)
            ->get();

        return view('pages.receivables.index', compact('orders', 'summary', 'topCustomers', 'status', 'search'));
    }

    public function show(int $id)
    {
        $order = Order::credit()->with(['orderItems', 'payments'])->findOrFail($id);

        return view('pages.receivables.show', compact('order'));
    }

    public function storePayment(Request $request, int $id)
    {
        $order = Order::credit()->findOrFail($id);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:Cash,Transfer'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.min' => 'Nominal pembayaran harus lebih dari 0.',
        ]);

        DB::transaction(function () use ($order, $data, $request) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            OrderPayment::create([
                'order_id' => $locked->id,
                'client_payment_id' => (string) Str::uuid(),
                'amount' => (int) $data['amount'],
                'payment_method' => $data['payment_method'],
                'paid_at' => isset($data['paid_at'])
                    ? Carbon::parse($data['paid_at'])->setTimeFrom(now())
                    : now(),
                'note' => $data['note'] ?? null,
                'source' => 'web',
                'id_kasir' => $request->user()?->id,
                'nama_kasir' => $request->user()?->name,
            ]);

            $locked->syncPaymentState();
        });

        $order->refresh();

        return back()->with(
            'success',
            $order->payment_status === 'paid'
                ? 'Pembayaran dicatat. Nota ' . $this->invoice($order) . ' LUNAS.'
                : 'Pembayaran dicatat. Sisa piutang Rp ' . number_format($order->remaining_amount, 0, ',', '.') . '.'
        );
    }

    public function destroyPayment(int $id, int $paymentId)
    {
        $order = Order::credit()->findOrFail($id);
        $payment = $order->payments()->whereKey($paymentId)->firstOrFail();

        DB::transaction(function () use ($order, $payment) {
            $payment->delete();
            $order->syncPaymentState();
        });

        return back()->with('success', 'Pembayaran dihapus. Status nota dihitung ulang.');
    }

    private function invoice(Order $order): string
    {
        return 'INV' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
    }
}
