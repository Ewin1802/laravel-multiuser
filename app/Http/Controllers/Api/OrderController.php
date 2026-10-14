<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StoreDevice;
use App\Services\InventoryService;
use App\Support\UnitConverter;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /** Toleransi beda jam HP vs server sebelum ditandai janggal. */
    private const CLOCK_TOLERANCE_MINUTES = 10;

    public function __construct(private InventoryService $inventory) {}

    /**
     * ============================================================
     * POST /api/save-order — kirim transaksi dari HP kasir
     * ============================================================
     *
     * ATURAN UTAMA: transaksi yang SUDAH TERJADI di HP (termasuk saat
     * offline) SELALU diterima, walau:
     *   - stok server tidak cukup        → stock_conflict, stok boleh minus
     *   - langganan sudah habis           → clock_suspect bila tanggalnya
     *                                       melewati batas transaksi
     *   - HP sudah bukan kasir aktif      → from_inactive_device
     * Semua tanda itu tampil di web admin untuk dicek. Yang mencegah
     * transaksi BARU adalah kunci di aplikasi (lisensi bertanda tangan).
     *
     * Idempotent lewat client_order_id: kirim ulang → status "exists".
     *
     * order_items[*]:
     *   id_product        wajib
     *   quantity, price   dalam satuan yang dijual
     *   product_unit_id   opsional (kosong = satuan terkecil)
     *   unit_name         opsional, nama satuan saat transaksi
     *   unit_conversion   opsional, isi satuan SAAT transaksi (dipakai apa adanya)
     *   batches[]         opsional, pembagian FEFO dari HP: {batch_id, quantity}
     */
    public function saveOrder(Request $request)
    {
        $request->validate([
            'client_order_id' => ['required', 'uuid'],
            'payment_amount' => 'required|numeric',
            'sub_total' => 'required|numeric',
            'tax' => 'required|numeric',
            'discount' => 'required|numeric',
            'discount_amount' => 'required|numeric',
            'service_charge' => 'required|numeric',
            'total' => 'required|numeric',
            'payment_method' => ['required', 'string', 'max:30'],
            'total_item' => 'required|numeric',
            'id_kasir' => 'required',
            'nama_kasir' => 'required|string',
            'transaction_time' => ['required', 'string', 'max:40'],
            'device_elapsed_ms' => ['nullable', 'integer', 'min:0'],
            // selisih zona waktu HP dari UTC dalam menit (WIB 420, WITA 480, WIT 540).
            // transaction_time dari HP tidak membawa zona waktu.
            'tz_offset' => ['nullable', 'integer', 'between:-720,840'],
            'table_number' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'due_date' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'down_payment_method' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string'],

            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.id_product' => ['required', 'integer'],
            'order_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'order_items.*.price' => ['required', 'numeric', 'gte:0'],
            'order_items.*.product_unit_id' => ['nullable', 'integer'],
            'order_items.*.unit_name' => ['nullable', 'string', 'max:30'],
            'order_items.*.unit_conversion' => ['nullable', 'numeric', 'gt:0'],
            'order_items.*.batches' => ['nullable', 'array'],
            'order_items.*.batches.*.batch_id' => ['required_with:order_items.*.batches', 'integer'],
            'order_items.*.batches.*.quantity' => ['required_with:order_items.*.batches', 'numeric', 'gt:0'],
        ]);

        $isCredit = strcasecmp((string) $request->payment_method, Order::METHOD_CREDIT) === 0;

        if ($isCredit && blank($request->customer_name)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nama pelanggan wajib diisi untuk penjualan tempo.',
                'errors' => ['customer_name' => ['Nama pelanggan wajib diisi untuk penjualan tempo.']],
            ], 422);
        }

        $clientOrderId = $request->client_order_id;

        // ---------------- kirim ulang → kembalikan order yang sudah ada
        if ($existing = $this->findExisting($clientOrderId)) {
            return $existing;
        }

        /** @var StoreDevice $device */
        $device = $request->attributes->get('store_device');
        $store = $request->user()->store;

        try {
            return DB::transaction(function () use ($request, $isCredit, $device, $store) {

                // ============================================================
                // PEMERIKSAAN WAKTU (jam HP vs jam server)
                // ============================================================
                $soldAt = $this->parseTime($request->transaction_time, $request->input('tz_offset'));
                $notes = [];
                $clockSuspect = false;
                $now = now();

                if (! $soldAt) {
                    $clockSuspect = true;
                    $notes[] = 'Format waktu transaksi tidak dikenali; memakai jam server.';
                    $soldAt = $now->copy();
                } elseif ($soldAt->gt($now->copy()->addMinutes(self::CLOCK_TOLERANCE_MINUTES))) {
                    $clockSuspect = true;
                    $notes[] = 'Waktu transaksi di masa depan menurut jam server.';
                } elseif ($store->approved_at && $soldAt->lt($store->approved_at->copy()->subDay())) {
                    $clockSuspect = true;
                    $notes[] = 'Waktu transaksi sebelum toko disetujui.';
                }

                $transactUntil = $store->subscription()->transactUntil();
                if ($transactUntil && $soldAt->gt($transactUntil->addMinutes(self::CLOCK_TOLERANCE_MINUTES))) {
                    $clockSuspect = true;
                    $notes[] = 'Transaksi setelah masa aktif berakhir.';
                }

                // HP bukan kasir aktif: tandai bila HP ini tidak pernah jadi
                // kasir, atau transaksinya dibuat setelah kasir dipindah
                $fromInactive = (int) $store->active_device_id !== (int) $device->id
                    && (! $device->activated_at
                        || ! $device->deactivated_at
                        || $soldAt->gt($device->deactivated_at));
                if ($fromInactive) {
                    $notes[] = 'Dibuat HP yang sudah bukan kasir aktif.';
                }

                // ============================================================
                // ORDER
                // ============================================================
                $order = Order::create([
                    'client_order_id' => $request->client_order_id,
                    'payment_amount' => $request->payment_amount,
                    'sub_total' => $request->sub_total,
                    'tax' => $request->tax,
                    'discount' => $request->discount,
                    'discount_amount' => $request->discount_amount,
                    'service_charge' => $request->service_charge,
                    'total' => $request->total,
                    'payment_method' => $request->payment_method,
                    'total_item' => $request->total_item,
                    'table_number' => $request->table_number,
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'payment_status' => $isCredit ? 'unpaid' : 'paid',
                    'paid_amount' => $isCredit ? 0 : (int) $request->total,
                    'due_date' => $isCredit ? $request->due_date : null,
                    'status' => $request->status,
                    'id_kasir' => $request->id_kasir,
                    'nama_kasir' => $request->nama_kasir,
                    'transaction_time' => $request->transaction_time,

                    'store_device_id' => $device->id,
                    'device_id' => $device->device_id,
                    'received_at' => $now,
                    'device_elapsed_ms' => $request->device_elapsed_ms,
                    'clock_suspect' => $clockSuspect,
                    'from_inactive_device' => $fromInactive,
                ]);

                // ============================================================
                // BARIS ORDER + STOK
                // ============================================================
                $stockConflict = false;

                foreach ($request->order_items as $row) {
                    $product = Product::find($row['id_product']);

                    if (! $product) {
                        throw new \RuntimeException('Produk ID ' . $row['id_product'] . ' tidak ditemukan di toko ini.');
                    }

                    $unit = ! empty($row['product_unit_id'])
                        ? ProductUnit::where('product_id', $product->id)->find($row['product_unit_id'])
                        : null;

                    // konversi yang dipakai HP saat transaksi menang (riwayat tetap benar)
                    $conversion = (float) ($row['unit_conversion'] ?? 0) ?: (float) ($unit?->conversion ?? 1);
                    $quantity = round((float) $row['quantity'], 3);

                    $item = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_unit_id' => $unit?->id,
                        'product_name' => $product->name,
                        'unit_name' => $row['unit_name'] ?? $unit?->name ?? $product->base_unit,
                        'unit_conversion' => $conversion,
                        'quantity' => $quantity,
                        'base_quantity' => UnitConverter::toBase($quantity, $conversion),
                        'price' => $row['price'],
                    ]);

                    if ($this->inventory->applySale($item, $soldAt, $row['batches'] ?? [])) {
                        $stockConflict = true;
                        $notes[] = "Stok {$product->name} di server tidak cukup.";
                    }
                }

                // ============================================================
                // PIUTANG: UANG MUKA (DP)
                // ============================================================
                if ($isCredit) {
                    $downPayment = (int) round((float) $request->input('paid_amount', 0));

                    if ($downPayment > 0) {
                        OrderPayment::create([
                            'order_id' => $order->id,
                            'client_payment_id' => \Ramsey\Uuid\Uuid::uuid5(
                                \Ramsey\Uuid\Uuid::NAMESPACE_URL,
                                'order-dp:' . $order->client_order_id
                            )->toString(),
                            'amount' => $downPayment,
                            'payment_method' => $request->input('down_payment_method', 'Cash') ?: 'Cash',
                            'paid_at' => $soldAt,
                            'note' => 'Uang muka saat transaksi',
                            'source' => 'app',
                            'id_kasir' => $order->id_kasir,
                            'nama_kasir' => $order->nama_kasir,
                        ]);
                    }
                }

                $order->forceFill([
                    'stock_conflict' => $stockConflict,
                    'sync_notes' => $notes ? implode(' ', array_unique($notes)) : null,
                ])->save();

                $order->syncPaymentState();
                $order->load('orderItems.batches');

                $device->forceFill(['last_sync_at' => $now])->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Order berhasil disimpan.',
                    'data' => ['order' => $order],
                ]);
            });
        } catch (QueryException $e) {
            // dua kiriman bersamaan → UNIQUE client_order_id menolak yang kedua
            if ($existing = $this->findExisting($clientOrderId)) {
                return $existing;
            }

            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan order.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan order: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function findExisting(string $clientOrderId)
    {
        // Dicari tanpa filter toko: UUID unik global. Bila milik toko lain,
        // jangan bocorkan isinya.
        $existing = Order::withoutGlobalScope('store')
            ->where('client_order_id', $clientOrderId)
            ->first();

        if (! $existing) {
            return null;
        }

        if ((int) $existing->store_id !== (int) request()->user()->store_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'client_order_id sudah dipakai.',
            ], 409);
        }

        return response()->json([
            'status' => 'exists',
            'message' => 'Order sudah pernah disimpan.',
            'data' => ['order' => $existing->load('orderItems')],
        ]);
    }

    /**
     * Waktu transaksi dari HP → Carbon di zona waktu aplikasi.
     * HP mengirim jam lokal tanpa zona (mis. 2026-10-13T13:00:00 WITA) +
     * tz_offset (480). Tanpa tz_offset dianggap zona waktu aplikasi.
     */
    private function parseTime(?string $value, $offsetMinutes = null): ?Carbon
    {
        return self::parseDeviceTime($value, $offsetMinutes);
    }

    public static function parseDeviceTime(?string $value, $offsetMinutes = null): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            $hasZone = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', trim($value));

            if (! $hasZone && $offsetMinutes !== null && $offsetMinutes !== '') {
                $m = (int) $offsetMinutes;
                $tz = sprintf('%s%02d:%02d', $m < 0 ? '-' : '+', intdiv(abs($m), 60), abs($m) % 60);

                return Carbon::parse($value, $tz)->setTimezone(config('app.timezone'));
            }

            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    public function index(Request $request)
    {
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        if ($start_date && $end_date) {

            $start = substr($start_date, 0, 10);
            $end = substr($end_date, 0, 10);

            $orders = Order::with('orderItems')->whereRaw("
                DATE(SUBSTRING_INDEX(transaction_time, 'T', 1)) BETWEEN ? AND ?
            ", [$start, $end])->get();
        } else {
            $orders = Order::all();
        }

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ], 200);
    }

    public function summary(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Order::query();

        if ($startDate && $endDate) {

            $start = substr($startDate, 0, 10);
            $end = substr($endDate, 0, 10);

            $query->whereRaw(
                "DATE(SUBSTRING_INDEX(transaction_time, 'T', 1)) BETWEEN ? AND ?",
                [$start, $end]
            );
        }

        $totalRevenue = $query->sum('total');

        $totalDiscount = $query->sum('discount_amount');

        $totalTax = $query->sum('tax');

        $totalServiceCharge = $query->sum('service_charge');

        $totalSubtotal = $query->sum('sub_total');

        // Total penjualan mengikuti nilai total transaksi
        $total = $totalRevenue;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_revenue' => (int) $totalRevenue,
                'total_discount' => (float) $totalDiscount,
                'total_tax' => (int) $totalTax,
                'total_subtotal' => (int) $totalSubtotal,
                'total_service_charge' => (int) $totalServiceCharge,
                'total' => (int) $total,
            ]
        ], 200);
    }
}
