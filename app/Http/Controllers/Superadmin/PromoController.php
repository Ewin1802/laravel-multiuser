<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * KODE PROMO — dibuat superadmin, dibagikan ke toko (WA, brosur, dsb.),
 * dimasukkan toko saat memperpanjang langganan.
 */
class PromoController extends Controller
{
    public function index(Request $request)
    {
        $promos = PromoCode::withCount([
            'payments as used_count' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed']),
            'payments as confirmed_count' => fn ($q) => $q->where('status', 'confirmed'),
        ])
            ->withSum(['payments as discount_total' => fn ($q) => $q->where('status', 'confirmed')], 'discount_amount')
            ->orderByDesc('is_active')
            ->orderByDesc('ends_at')
            ->get();

        return view('pages.superadmin.promos.index', [
            'promos' => $promos,
            'plans' => Plan::orderBy('duration_months')->get(),
            'edit' => $request->filled('edit') ? $promos->firstWhere('id', (int) $request->edit) : null,
        ]);
    }

    public function store(Request $request)
    {
        $promo = PromoCode::create($this->validated($request));

        return redirect()->route('superadmin.promos.index')
            ->with('success', "Kode {$promo->code} dibuat. Bagikan ke toko — berlaku {$promo->starts_at->translatedFormat('d M')} s/d {$promo->ends_at->translatedFormat('d M Y')}.");
    }

    public function update(Request $request, PromoCode $promo)
    {
        $promo->update($this->validated($request, $promo));

        return redirect()->route('superadmin.promos.index')
            ->with('success', "Kode {$promo->code} diperbarui. Pembayaran yang sudah tercatat tidak berubah.");
    }

    /** Hapus hanya bila belum pernah dipakai; kalau sudah, nonaktifkan saja. */
    public function destroy(PromoCode $promo)
    {
        if ($promo->payments()->exists()) {
            $promo->update(['is_active' => false]);

            return back()->with('success', "Kode {$promo->code} sudah pernah dipakai, jadi dinonaktifkan (tidak dihapus).");
        }

        $promo->delete();

        return back()->with('success', "Kode {$promo->code} dihapus.");
    }

    private function validated(Request $request, ?PromoCode $promo = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->code))]);

        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('promo_codes', 'code')->ignore($promo?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in([PromoCode::PERCENT, PromoCode::AMOUNT])],
            'discount_value' => ['required', 'integer', 'min:1',
                $request->discount_type === PromoCode::PERCENT ? 'max:100' : 'max:100000000'],
            'max_discount' => ['nullable', 'integer', 'min:1'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['integer', 'exists:plans,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'per_store_limit' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'code.alpha_dash' => 'Kode hanya boleh huruf, angka, - dan _ (tanpa spasi).',
            'code.unique' => 'Kode ini sudah dipakai promo lain.',
            'discount_value.max' => 'Diskon persen maksimal 100.',
            'ends_at.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
        ]);

        $data['plan_ids'] = ! empty($data['plan_ids']) ? array_values(array_map('intval', $data['plan_ids'])) : null;
        if ($data['discount_type'] === PromoCode::AMOUNT) {
            $data['max_discount'] = null;
        }
        $data['first_payment_only'] = $request->boolean('first_payment_only');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
