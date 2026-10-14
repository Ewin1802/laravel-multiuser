<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPayment;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PIUTANG PELANGGAN (penjualan Tempo) — dipakai aplikasi kasir.
 *
 * GET  /api/receivables           daftar nota tempo yang belum lunas
 * POST /api/receivables/payments  catat pembayaran / cicilan
 *
 * Pembayaran dikirim dengan client_payment_id (UUID dari HP kasir), jadi
 * aman dikirim ulang saat antrean offline tersinkron: kiriman kedua dan
 * seterusnya dijawab status "exists" tanpa mencatat ulang.
 */
class ReceivableController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()
            ->credit()
            ->openReceivable()
            ->with('payments:id,order_id,client_payment_id,amount,payment_method,paid_at,note,nama_kasir')
            ->orderByRaw('due_date IS NULL, due_date')
            ->orderBy('id')
            ->get([
                'id', 'client_order_id', 'customer_name', 'customer_phone',
                'total', 'paid_amount', 'payment_status', 'payment_method', 'due_date',
                'transaction_time', 'id_kasir', 'nama_kasir', 'total_item',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $orders,
            'meta' => [
                'synced_at' => now()->toIso8601String(),
                'total_outstanding' => (int) $orders->sum(fn ($o) => $o->remaining_amount),
            ],
        ]);
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'client_payment_id' => ['required', 'uuid'],
            'client_order_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'paid_at' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
            'id_kasir' => ['nullable', 'integer'],
            'nama_kasir' => ['nullable', 'string', 'max:255'],
        ]);

        // sudah pernah tercatat → bukan error (kiriman ulang dari antrean)
        if ($existing = OrderPayment::where('client_payment_id', $data['client_payment_id'])->first()) {
            return $this->paymentResponse('exists', $existing->order()->first(), $existing);
        }

        $order = Order::where('client_order_id', $data['client_order_id'])->first();

        if (! $order) {
            // Nota-nya sendiri belum sampai di server (masih di antrean HP).
            // Aplikasi akan mencoba lagi pada sinkron berikutnya.
            return response()->json([
                'status' => 'order_not_found',
                'message' => 'Nota belum ada di server. Pembayaran akan dikirim ulang setelah nota tersinkron.',
            ], 404);
        }

        try {
            $payment = DB::transaction(function () use ($data, $order) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                $payment = OrderPayment::create([
                    'order_id' => $locked->id,
                    'client_payment_id' => $data['client_payment_id'],
                    'amount' => (int) round((float) $data['amount']),
                    'payment_method' => $data['payment_method'] ?? 'Cash',
                    'paid_at' => $this->parseTime($data['paid_at'] ?? null),
                    'note' => $data['note'] ?? null,
                    'source' => 'app',
                    'id_kasir' => $data['id_kasir'] ?? $locked->id_kasir,
                    'nama_kasir' => $data['nama_kasir'] ?? null,
                ]);

                $locked->syncPaymentState();

                return $payment;
            });
        } catch (QueryException $e) {
            // dua kiriman bersamaan dengan UUID sama → yang kedua kena UNIQUE
            $existing = OrderPayment::where('client_payment_id', $data['client_payment_id'])->first();

            if ($existing) {
                return $this->paymentResponse('exists', $existing->order()->first(), $existing);
            }

            throw $e;
        }

        return $this->paymentResponse('success', $order->fresh(), $payment);
    }

    private function paymentResponse(string $status, ?Order $order, OrderPayment $payment)
    {
        return response()->json([
            'status' => $status,
            'message' => $status === 'exists' ? 'Pembayaran sudah pernah dicatat.' : 'Pembayaran dicatat.',
            'data' => [
                'payment' => $payment,
                'order' => $order?->only([
                    'id', 'client_order_id', 'total', 'paid_amount', 'payment_status', 'due_date', 'remaining_amount',
                ]),
            ],
        ], 200);
    }

    private function parseTime(?string $value): Carbon
    {
        try {
            return OrderController::parseDeviceTime($value, request()->input('tz_offset')) ?? now();
        } catch (\Throwable) {
            return now();
        }
    }
}
