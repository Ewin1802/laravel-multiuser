<?php

namespace App\Http\Controllers;

use App\Models\DeviceSwitchLog;
use App\Models\StoreDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * PROFIL TOKO — nama, kontak, logo, footer struk, fitur kasir, dan daftar
 * HP yang pernah dipakai (mana yang menjadi kasir aktif).
 */
class StoreProfileController extends Controller
{
    public function edit(Request $request)
    {
        $store = $request->user()->store;

        return view('pages.store.profile', [
            'store' => $store,
            'devices' => StoreDevice::orderByDesc('last_seen_at')->get(),
            'switches' => DeviceSwitchLog::with(['fromDevice', 'toDevice', 'user'])->latest()->limit(10)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $store = $request->user()->store;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'bank_holder' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($store->logo) {
                Storage::disk('public')->delete($store->logo);
            }
            $data['logo'] = $request->file('logo')->store('stores', 'public');
        } else {
            unset($data['logo']);
        }

        $data['feature_draft_order'] = $request->boolean('feature_draft_order');

        $store->fill($data)->save();

        return back()->with('success', 'Profil toko tersimpan. HP kasir memakai data baru setelah sinkron.');
    }
}
