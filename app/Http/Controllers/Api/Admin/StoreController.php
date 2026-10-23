<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\DeviceManager;
use App\Services\StoreManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Daftar toko & persetujuan dari aplikasi admin. Memakai StoreManager
 * yang sama dengan panel web, jadi aturan trial dll. tidak berbeda.
 */
class StoreController extends Controller
{
    private const STATES = ['pending', 'trial', 'active', 'grace', 'expired', 'suspended', 'all'];

    public function __construct(
        private StoreManager $stores,
        private DeviceManager $devices,
    ) {}

    /** GET /api/admin/stores?state=pending&q=nama&page=1 */
    public function index(Request $request)
    {
        $data = $request->validate([
            'state' => ['nullable', Rule::in(self::STATES)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $state = $data['state'] ?? 'pending';
        $q = trim((string) ($data['q'] ?? ''));
        $page = (int) ($data['page'] ?? 1);
        $perPage = 20;

        $items = Store::with('owner')
            ->when($state === 'pending', fn ($w) => $w->where('status', Store::STATUS_PENDING))
            ->when($state === 'suspended', fn ($w) => $w->where('status', Store::STATUS_SUSPENDED))
            ->when($q !== '', fn ($w) => $w->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                    ->orWhere('phone', 'like', "%$q%")
                    ->orWhereHas('users', fn ($u) => $u->where('email', 'like', "%$q%")->orWhere('phone_number', 'like', "%$q%"));
            }))
            // pending: yang paling lama menunggu di atas
            ->orderBy('created_at', $state === 'pending' ? 'asc' : 'desc')
            ->get()
            ->when(! in_array($state, ['all', 'pending', 'suspended'], true),
                fn ($c) => $c->filter(fn (Store $s) => $s->subscription()->state() === $state))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $items->forPage($page, $perPage)->values()->map(fn (Store $s) => $this->payload($s)),
            'meta' => [
                'state' => $state,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $items->count(),
                'last_page' => max(1, (int) ceil($items->count() / $perPage)),
            ],
        ]);
    }

    public function show(Store $store)
    {
        $store->load('owner');

        // HP pendaftar yang juga dipakai toko lain → kemungkinan trial berulang
        $deviceIds = $store->devices()->withoutGlobalScope('store')->pluck('device_id')
            ->push($store->registered_device_id)->filter()->unique();
        $otherIds = $deviceIds->flatMap(fn ($id) => $this->devices->otherStoresUsingDevice($id, $store->id))->unique();
        $sameDevice = Store::whereIn('id', $otherIds)->get()
            ->map(fn (Store $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'state' => $s->subscription()->state(),
                'state_label' => $s->subscription()->label(),
                'had_trial' => $s->trial_started_at !== null,
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->payload($store) + ['same_device_stores' => $sameDevice],
        ]);
    }

    public function approve(Request $request, Store $store)
    {
        if ($store->status === Store::STATUS_ACTIVE) {
            return response()->json(['status' => 'error', 'code' => 'already_active', 'message' => 'Toko ini sudah aktif.'], 422);
        }

        $this->stores->approve($store, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => "Toko {$store->name} disetujui. Trial berjalan sampai "
                . $store->trial_ends_at?->translatedFormat('d M Y H:i') . '.',
            'data' => $this->payload($store->fresh('owner')),
        ]);
    }

    /** Tolak pendaftar / blokir toko (toko jadi hanya-lihat). Alasan wajib. */
    public function reject(Request $request, Store $store)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan']);

        $this->stores->suspend($store, $data['reason']);

        return response()->json([
            'status' => 'success',
            'message' => "Toko {$store->name} " . ($store->approved_at ? 'diblokir.' : 'ditolak.'),
            'data' => $this->payload($store->fresh('owner')),
        ]);
    }

    public function unsuspend(Store $store)
    {
        $this->stores->unsuspend($store);

        return response()->json([
            'status' => 'success',
            'message' => "Blokir toko {$store->name} dibuka.",
            'data' => $this->payload($store->fresh('owner')),
        ]);
    }

    private function payload(Store $s): array
    {
        $sub = $s->subscription();

        return [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'address' => $s->address,
            'status' => $s->status,                 // pending | active | suspended
            'state' => $sub->state(),               // pending | trial | active | grace | expired | suspended
            'state_label' => $sub->label(),
            'is_cafe' => (bool) $s->feature_draft_order,
            'suspend_reason' => $s->suspend_reason,
            'registered_at' => $s->created_at?->toIso8601String(),
            'approved_at' => $s->approved_at?->toIso8601String(),
            'trial_ends_at' => $s->trial_ends_at?->toIso8601String(),
            'paid_until' => $s->paid_until?->toIso8601String(),
            'owner' => $s->owner ? [
                'name' => $s->owner->name,
                'email' => $s->owner->email,
                'phone_number' => $s->owner->phone_number,
            ] : null,
        ];
    }
}
