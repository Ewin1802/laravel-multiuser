<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ClockTamperLog;
use App\Models\DeviceSwitchLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreDevice;
use App\Models\SubscriptionPayment;
use App\Services\DeviceManager;
use App\Services\StoreManager;
use Illuminate\Http\Request;

/**
 * Daftar & detail toko untuk superadmin: setujui, blokir, catat
 * pembayaran, lepas perangkat kasir, lihat log jam mundur.
 */
class StoreController extends Controller
{
    public function __construct(
        private StoreManager $stores,
        private DeviceManager $devices,
    ) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $state = $request->input('state');
        $mode = $request->input('mode'); // cafe | retail

        $stores = Store::with('owner')
            ->when($mode === 'cafe', fn ($query) => $query->where('feature_draft_order', true))
            ->when($mode === 'retail', fn ($query) => $query->where(fn ($w) => $w->where('feature_draft_order', false)->orWhereNull('feature_draft_order')))
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                    ->orWhereHas('users', fn ($u) => $u->where('email', 'like', "%$q%")->orWhere('phone_number', 'like', "%$q%"));
            }))
            ->latest()
            ->get()
            ->when($state, fn ($c) => $c->filter(fn (Store $s) => $s->subscription()->state() === $state))
            ->values();

        return view('pages.superadmin.stores.index', compact('stores', 'q', 'state', 'mode'));
    }

    public function show(Store $store)
    {
        $store->load('owner', 'plan');

        $devices = StoreDevice::where('store_id', $store->id)->orderByDesc('last_seen_at')->get();

        // HP toko ini yang juga dipakai / mendaftar di toko lain (trial berulang?)
        $sharedDevices = [];
        foreach ($devices->pluck('device_id')->push($store->registered_device_id)->filter()->unique() as $deviceId) {
            $others = $this->devices->otherStoresUsingDevice($deviceId, $store->id);
            if ($others->isNotEmpty()) {
                $sharedDevices[$deviceId] = Store::whereIn('id', $others)->get(['id', 'name', 'status', 'trial_started_at']);
            }
        }

        return view('pages.superadmin.stores.show', [
            'store' => $store,
            'sub' => $store->subscription(),
            'devices' => $devices,
            'sharedDevices' => $sharedDevices,
            'switches' => DeviceSwitchLog::with(['fromDevice', 'toDevice'])->where('store_id', $store->id)->latest()->limit(20)->get(),
            'tamper' => ClockTamperLog::where('store_id', $store->id)->latest()->limit(50)->get(),
            'payments' => SubscriptionPayment::with('plan', 'reviewer')->where('store_id', $store->id)->latest()->get(),
            'plans' => Plan::where('is_active', true)->orderBy('duration_months')->get(),
            'stats' => (object) [
                'orders' => Order::where('store_id', $store->id)->count(),
                'flagged' => Order::where('store_id', $store->id)
                    ->where(fn ($w) => $w->where('clock_suspect', true)->orWhere('from_inactive_device', true))->count(),
                'last_order' => Order::where('store_id', $store->id)->max('received_at'),
            ],
        ]);
    }

    public function approve(Request $request, Store $store)
    {
        $this->stores->approve($store, $request->user());

        return back()->with('success', "Toko {$store->name} disetujui. Trial berjalan sampai "
            . $store->trial_ends_at->translatedFormat('d M Y H:i') . '.');
    }

    public function suspend(Request $request, Store $store)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->stores->suspend($store, $data['reason'] ?? null);

        return back()->with('success', "Toko {$store->name} diblokir (hanya-lihat).");
    }

    public function unsuspend(Store $store)
    {
        $this->stores->unsuspend($store);

        return back()->with('success', "Blokir toko {$store->name} dibuka.");
    }

    public function releaseDevice(Request $request, Store $store)
    {
        $this->devices->release($store, $request->user(), $request);

        return back()->with('success', 'Perangkat kasir dilepas. HP berikutnya yang login akan menjadi kasir.');
    }

    public function recordPayment(Request $request, Store $store)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $payment = $this->stores->recordAndConfirm($store, $plan, $request->user(), array_filter([
            'amount' => $data['amount'] ?? null,
            'note' => $data['note'] ?? null,
            'sender_name' => 'Dicatat superadmin',
        ], fn ($v) => $v !== null));

        return back()->with('success', 'Pembayaran dicatat. Aktif sampai ' . $payment->period_end->translatedFormat('d M Y') . '.');
    }

    public function updateNotes(Request $request, Store $store)
    {
        $data = $request->validate(['admin_notes' => ['nullable', 'string', 'max:2000']]);
        $store->admin_notes = $data['admin_notes'] ?? null;
        $store->save();

        return back()->with('success', 'Catatan disimpan.');
    }
}
