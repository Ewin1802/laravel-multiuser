<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Discount;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $discounts = Discount::query()
            ->when($request->name, function ($query, $name) {
                $query->where('name', 'like', "%{$name}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('pages.discounts.index', compact('discounts'));
    }

    // create
    public function create()
    {
        return view('pages.discounts.create');
    }

    // store
    public function store(Request $request)
    {
        // validate the request...
        $request->validate([
            'name' => 'required',
            'description' => 'required',
            'value' => 'required',
        ]);

        // store the request...
        $discount = new Discount;
        $discount->name = $request->name;
        $discount->description = $request->description;
        $discount->value = $request->value;

        $discount->save();


        return redirect()->route('discounts.index')->with('success', 'Discount created successfully');
    }


    // edit
    public function edit($id)
    {
        $discount = Discount::findOrFail($id);
        return view('pages.discounts.edit', compact('discount'));
    }

    // update
    public function update(Request $request, $id)
    {
        // validate the request...
        $request->validate([
            'name' => 'required',
            'description' => 'required',
            'value' => 'required',
        ]);

        // update the request...
        $discount = Discount::find($id);
        $discount->name = $request->name;
        $discount->description = $request->description;
        $discount->value = $request->value;

        $discount->save();



        return redirect()->route('discounts.index')->with('success', 'Discount updated successfully');
    }

    // destroy
    public function destroy($id)
    {
        // delete the request...
        $discount = Discount::find($id);
        $discount->delete();

        return redirect()->route('discounts.index')->with('success', 'Discount deleted successfully');
    }
}
