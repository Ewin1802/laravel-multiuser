<?php

namespace App\Support;

use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Pengingat hutang ke supplier (nota Stok Masuk bertempo yang belum lunas).
 * Dipakai sidebar (angka di menu) dan dashboard (kartu peringatan).
 *
 * Hasilnya disimpan selama satu request, jadi dipanggil dari dua tempat
 * pun query-nya hanya jalan sekali.
 */
class PayableAlerts
{
    /** Nota dianggap "segera jatuh tempo" kalau tinggal sekian hari lagi */
    public const SOON_DAYS = 3;

    private static ?object $cache = null;

    public static function summary(): object
    {
        if (self::$cache) {
            return self::$cache;
        }

        $today = Carbon::today();
        $soonLimit = $today->copy()->addDays(self::SOON_DAYS);

        try {
            $rows = Tenant::table('stock_ins as s')
            ->join('suppliers as sp', 'sp.id', '=', 's.supplier_id')
            ->where('s.payment_status', 'unpaid')
            ->whereNotNull('s.due_date')
            ->whereDate('s.due_date', '<=', $soonLimit->toDateString())
            ->orderBy('s.due_date')
            ->orderBy('s.id')
            ->get(['s.id', 's.total', 's.due_date', 's.invoice_number', 'sp.name as supplier_name']);
        } catch (QueryException $e) {
            // tabel / kolom pembayaran belum di-migrate → jangan sampai sidebar ikut error
            $rows = collect();
        }

        $rows = $rows
            ->map(function ($row) use ($today) {
                $due = Carbon::parse($row->due_date)->startOfDay();

                // negatif = sudah lewat · 0 = hari ini · positif = sisa hari
                $row->days_left = (int) round($today->diffInDays($due, false));
                $row->is_overdue = $row->days_left < 0;
                $row->due = $due;
                $row->total = (float) $row->total;

                return $row;
            });

        $overdue = $rows->where('is_overdue', true);
        $soon = $rows->where('is_overdue', false);

        return self::$cache = (object) [
            'items' => $rows,
            'count' => $rows->count(),
            'total' => $rows->sum('total'),
            'overdue_count' => $overdue->count(),
            'overdue_total' => $overdue->sum('total'),
            'soon_count' => $soon->count(),
            'soon_total' => $soon->sum('total'),
        ];
    }
}
