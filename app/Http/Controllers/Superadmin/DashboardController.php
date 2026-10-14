<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ClockTamperLog;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Support\Subscription;

class DashboardController extends Controller
{
    public function index()
    {
        $stores = Store::with('owner')->get();

        $byState = $stores->groupBy(fn (Store $s) => $s->subscription()->state())->map->count();

        // berakhir dalam 7 hari (trial / berbayar) → bahan follow-up
        $endingSoon = $stores->filter(function (Store $s) {
            $end = $s->subscription()->endsAt();

            return $end && $end->lte(now()->addDays(7));
        })->sortBy(fn (Store $s) => $s->subscription()->endsAt())->values();

        return view('pages.superadmin.dashboard', [
            'byState' => $byState,
            'total' => $stores->count(),
            'pendingStores' => $stores->where('status', Store::STATUS_PENDING)->sortByDesc('created_at')->values(),
            'pendingPayments' => SubscriptionPayment::with(['plan'])->where('status', 'pending')->latest()->get(),
            'endingSoon' => $endingSoon,
            'recentTamper' => ClockTamperLog::latest()->limit(10)->get(),
            'storeNames' => $stores->pluck('name', 'id'),
            'labels' => Subscription::LABELS,
        ]);
    }
}
