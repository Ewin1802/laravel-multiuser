<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /**
     * Display a listing.
     */
    public function index(Request $request)
    {
        $categories = Category::query()

            ->when($request->search, function ($query) use ($request) {

                $query->where('name', 'like', '%' . $request->search . '%');

            })

            ->latest()

            ->paginate(10)

            ->withQueryString();

        return view('pages.categories.index', compact('categories'));
    }

    /**
     * Show create page.
     */
    public function create()
    {
        return view('pages.categories.create');
    }

    /**
     * Store new category.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|max:255',
            'description' => 'nullable',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $category = new Category();

        $category->name = $request->name;
        $category->description = $request->description;

        // simpan dulu agar mendapatkan ID
        $category->save();

        // upload gambar
        if ($request->hasFile('image')) {

            $image = $request->file('image');

            $filename = $category->id . '.' . $image->getClientOriginalExtension();

            $image->storeAs('categories', $filename, 'public');

            $category->image = 'storage/categories/' . $filename;

            $category->save();
        }

        return redirect()
            ->route('categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    /**
     * Show edit page.
     */
    public function edit($id)
    {
        $category = Category::findOrFail($id);

        return view('pages.categories.edit', compact('category'));
    }

    /**
     * Update category.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|max:255',
            'description' => 'nullable',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $category = Category::findOrFail($id);

        $category->name = $request->name;
        $category->description = $request->description;

        if ($request->hasFile('image')) {

            // hapus gambar lama
            if (
                $category->image &&
                Storage::disk('public')->exists(
                    str_replace('storage/', '', $category->image)
                )
            ) {

                Storage::disk('public')->delete(
                    str_replace('storage/', '', $category->image)
                );
            }

            $image = $request->file('image');

            $filename = $category->id . '.' . $image->getClientOriginalExtension();

            $image->storeAs('categories', $filename, 'public');

            $category->image = 'storage/categories/' . $filename;
        }

        $category->save();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    /**
     * Delete category.
     */
    public function destroy($id)
    {
        try {

            $category = Category::findOrFail($id);

            // hapus gambar
            if (
                $category->image &&
                Storage::disk('public')->exists(
                    str_replace('storage/', '', $category->image)
                )
            ) {

                Storage::disk('public')->delete(
                    str_replace('storage/', '', $category->image)
                );
            }

            $category->delete();

            return redirect()
                ->route('categories.index')
                ->with('success', 'Kategori berhasil dihapus.');

        } catch (QueryException $e) {

            return redirect()
                ->route('categories.index')
                ->with(
                    'error',
                    'Kategori tidak dapat dihapus karena masih digunakan oleh data lain.'
                );

        } catch (\Exception $e) {

            return redirect()
                ->route('categories.index')
                ->with(
                    'error',
                    'Terjadi kesalahan saat menghapus kategori.'
                );
        }
    }
}
