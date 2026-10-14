<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ClockTamperLog;
use App\Models\Store;
use Illuminate\Http\Request;

/** Semua kejadian jam HP mundur / lewat batas offline dari seluruh toko. */
class TamperLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ClockTamperLog::query()
            ->when($request->input('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // ringkasan: toko dengan kejadian terbanyak 30 hari terakhir
        $top = ClockTamperLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('store_id, COUNT(*) as total')
            ->groupBy('store_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $storeNames = Store::whereIn('id', $logs->pluck('store_id')->merge($top->pluck('store_id')))->pluck('name', 'id');

        return view('pages.superadmin.tamper.index', [
            'logs' => $logs,
            'top' => $top,
            'storeNames' => $storeNames,
            'kinds' => ClockTamperLog::KINDS,
            'kind' => $request->input('kind'),
        ]);
    }
}
