<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Statistik kunjungan halaman depan: total, pengunjung unik (per IP),
 * dan daftar yang bisa difilter per IP.
 */
class VisitController extends Controller
{
    public function index(Request $request)
    {
        $ip = trim((string) $request->input('ip'));
        $withBots = $request->boolean('bots'); // default: sembunyikan bot

        $base = fn () => PageVisit::query()
            ->when(! $withBots, fn ($q) => $q->where('is_bot', false));

        // Ringkasan
        $summary = [
            'total' => $base()->count(),
            'unique' => $base()->distinct()->count('ip'),
            'today' => $base()->whereDate('visited_at', Carbon::today())->count(),
            'week' => $base()->where('visited_at', '>=', Carbon::now()->subDays(7))->count(),
            'bots' => PageVisit::where('is_bot', true)->count(),
        ];

        // Tren 14 hari (untuk mini-chart)
        $rows = $base()
            ->where('visited_at', '>=', Carbon::now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(visited_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $daily = collect(range(13, 0))->map(function ($back) use ($rows) {
            $date = Carbon::today()->subDays($back);
            return [
                'label' => $date->translatedFormat('d/m'),
                'total' => (int) ($rows[$date->toDateString()] ?? 0),
            ];
        });
        $dailyMax = max(1, (int) $daily->max('total'));

        // Kelompok per IP (bisa difilter)
        $perIp = $base()
            ->when($ip !== '', fn ($q) => $q->where('ip', 'like', '%' . $ip . '%'))
            ->selectRaw('ip, COUNT(*) as total, MIN(visited_at) as first_at, MAX(visited_at) as last_at')
            ->groupBy('ip')
            ->orderByDesc('last_at')
            ->paginate(25)
            ->withQueryString();

        // Kunjungan terbaru (ikut filter IP)
        $recent = $base()
            ->when($ip !== '', fn ($q) => $q->where('ip', 'like', '%' . $ip . '%'))
            ->latest('visited_at')
            ->limit(50)
            ->get();

        return view('pages.superadmin.visits.index', [
            'summary' => $summary,
            'daily' => $daily,
            'dailyMax' => $dailyMax,
            'perIp' => $perIp,
            'recent' => $recent,
            'ip' => $ip,
            'withBots' => $withBots,
        ]);
    }
}
