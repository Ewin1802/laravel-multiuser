<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('pages.superadmin.plans.index', ['plans' => Plan::orderBy('duration_months')->get()]);
    }

    public function store(Request $request)
    {
        Plan::create($this->validated($request));

        return back()->with('success', 'Paket ditambahkan.');
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));

        return back()->with('success', 'Paket diperbarui. Harga baru berlaku untuk pembayaran berikutnya.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:100'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:36'],
            'price' => ['required', 'integer', 'min:0'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
