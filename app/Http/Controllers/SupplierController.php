<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        // Ringkasan pembelian per supplier dihitung di subquery, lalu di-join.
        // (Tidak memakai suppliers.* + GROUP BY: MySQL/MariaDB mode ketat
        // menolak kolom yang tidak ada di GROUP BY → server error.)
        $stats = Tenant::table('stock_ins')
            ->select(
                'supplier_id',
                DB::raw('COUNT(*) as stock_ins_count'),
                DB::raw('COALESCE(SUM(total), 0) as purchases_total'),
                DB::raw('MAX(received_at) as last_received_at')
            )
            ->groupBy('supplier_id');

        $suppliers = Tenant::table('suppliers')
            ->leftJoinSub($stats, 'si', 'si.supplier_id', '=', 'suppliers.id')
            ->select(
                'suppliers.*',
                DB::raw('COALESCE(si.stock_ins_count, 0) as stock_ins_count'),
                DB::raw('COALESCE(si.purchases_total, 0) as purchases_total'),
                'si.last_received_at'
            )
            ->when($request->name, function ($query, $name) {
                $query->where('suppliers.name', 'like', "%{$name}%");
            })
            ->orderBy('suppliers.name')
            ->paginate(15)
            ->withQueryString();

        return view('pages.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('pages.suppliers.create');
    }

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);

        return view('pages.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, $id)
    {
        Supplier::findOrFail($id)->update($this->validated($request));

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy($id)
    {
        try {
            Supplier::findOrFail($id)->delete();

            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Supplier berhasil dihapus.');
        } catch (QueryException $e) {
            return redirect()
                ->route('suppliers.index')
                ->with('error', 'Supplier tidak dapat dihapus karena sudah punya riwayat stok masuk. Nonaktifkan saja.');
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}