<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageVisit;
use App\Support\UserAgentInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Statistik kunjungan halaman depan untuk aplikasi admin.
 * GET /api/admin/visits?ip=&bots=0|1
 */
class VisitController extends Controller
{
    public function index(Request $request)
    {
        $ip = trim((string) $request->query('ip', ''));
        $withBots = $request->boolean('bots');

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

        // Tren 14 hari
        $byDay = $base()
            ->where('visited_at', '>=', Carbon::now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(visited_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $trend = collect(range(13, 0))->map(function ($back) use ($byDay) {
            $date = Carbon::today()->subDays($back);
            return [
                'label' => $date->translatedFormat('d/m'),
                'total' => (int) ($byDay[$date->toDateString()] ?? 0),
            ];
        })->values();

        // Komposisi perangkat (OS) — parsing UA dilakukan di PHP
        $devices = $base()
            ->selectRaw('user_agent, COUNT(*) as total')
            ->groupBy('user_agent')
            ->get()
            ->reduce(function ($acc, $row) {
                $os = UserAgentInfo::os((string) $row->user_agent) ?: 'Lainnya';
                $acc[$os] = ($acc[$os] ?? 0) + (int) $row->total;
                return $acc;
            }, []);
        arsort($devices);
        $devices = collect($devices)->map(fn ($total, $label) => [
            'label' => $label,
            'total' => $total,
        ])->values();

        // Kelompok per IP (bisa difilter)
        $topIps = $base()
            ->when($ip !== '', fn ($q) => $q->where('ip', 'like', '%' . $ip . '%'))
            ->selectRaw('ip, COUNT(*) as total, MIN(visited_at) as first_at, MAX(visited_at) as last_at')
            ->groupBy('ip')
            ->orderByDesc('last_at')
            ->limit(80)
            ->get()
            ->map(fn ($r) => [
                'ip' => $r->ip,
                'total' => (int) $r->total,
                'first_at' => optional($r->first_at)->toIso8601String(),
                'last_at' => optional($r->last_at)->toIso8601String(),
            ]);

        // Kunjungan terbaru (ikut filter IP)
        $recent = $base()
            ->when($ip !== '', fn ($q) => $q->where('ip', 'like', '%' . $ip . '%'))
            ->latest('visited_at')
            ->limit(60)
            ->get()
            ->map(fn (PageVisit $v) => [
                'ip' => $v->ip,
                'path' => $v->path,
                'visited_at' => optional($v->visited_at)->toIso8601String(),
                'device' => UserAgentInfo::summary($v->user_agent),
                'is_bot' => (bool) $v->is_bot,
            ]);

        return response()->json([
            'data' => [
                'summary' => $summary,
                'trend' => $trend,
                'devices' => $devices,
                'top_ips' => $topIps,
                'recent' => $recent,
            ],
        ]);
    }
}
