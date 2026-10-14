<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    private array $categories = [
        'Gaji',
        'Operasional Toko',
        'Listrik',
        'Tak Terduga',
    ];

    public function index(Request $request)
    {
        $start_date = $request->input(
            'start_date',
            now()->startOfMonth()->toDateString()
        );

        $end_date = $request->input(
            'end_date',
            now()->endOfMonth()->toDateString()
        );

        $category = $request->input('category');

        $query = Expense::query()
            ->with('user')
            ->whereBetween(
                'expense_date',
                [$start_date, $end_date]
            );

        if ($category) {
            $query->where('category', $category);
        }

        $expenses = $query
            ->latest('expense_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $totalExpense = (clone $query)->sum('amount');

        $expenseSummary = [];

        foreach ($this->categories as $item) {
            $expenseSummary[$item] = (clone $query)
                ->where('category', $item)
                ->sum('amount');
        }

        return view(
            'pages.expenses.index',
            compact(
                'expenses',
                'start_date',
                'end_date',
                'category',
                'totalExpense',
                'expenseSummary'
            )
        );
    }

    public function create()
    {
        $categories = $this->categories;

        return view(
            'pages.expenses.create',
            compact('categories')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                'in:' . implode(',', $this->categories),
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'expense_date' => [
                'required',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $validated['user_id'] = Auth::id();

        Expense::create($validated);

        return redirect()
            ->route('expenses.index')
            ->with(
                'success',
                'Pengeluaran berhasil ditambahkan.'
            );
    }

    public function edit(Expense $expense)
    {
        $categories = $this->categories;

        return view(
            'pages.expenses.edit',
            compact(
                'expense',
                'categories'
            )
        );
    }

    public function update(
        Request $request,
        Expense $expense
    ) {
        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                'in:' . implode(',', $this->categories),
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'expense_date' => [
                'required',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $expense->update($validated);

        return redirect()
            ->route('expenses.index')
            ->with(
                'success',
                'Pengeluaran berhasil diperbarui.'
            );
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with(
                'success',
                'Pengeluaran berhasil dihapus.'
            );
    }
}
