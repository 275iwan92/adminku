<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Master Kategori barang. Awalan 3 karakter kategori menjadi dasar kode barang otomatis (Product::nextCode). Hanya Supervisor.
 *                Awalan tidak berubah setelah disimpan (kode barang lama memakainya); kategori tidak dihapus, hanya dinonaktifkan.
 * Sumber hulu  : m_category (+ jumlah barang).
 * Output       : grid Kategori, form Kategori.
 * Dipakai oleh : routes/web.php (categories.*, grup supervisor).
 * Status       : Aktif.
 */
class CategoryController extends Controller
{
    public function index()
    {
        $rows = Category::query()->withCount('products')->orderBy('code')->get()->map(fn (Category $c) => [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'hue' => Palette::item($c->code, $c->code), 'items' => $c->products_count, 'active' => $c->is_active,
        ])->all();

        return view('categories.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('categories.form', ['category' => new Category]);
    }

    public function show(Category $category)
    {
        return view('categories.form', ['category' => $category->loadCount('products')]);
    }

    public function store(CategoryRequest $request): RedirectResponse|JsonResponse
    {
        $category = Category::create($request->validated());
        if ($request->expectsJson()) {   // tambah cepat dari dropdown; id = kunci dropdown kategori
            return response()->json($category->only(['id', 'name']), 201);
        }

        return redirect()->route('categories.show', $category)->with('status', 'Kategori disimpan.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $category->update(['is_active' => $action === 'activate']);

            return redirect()->route('categories.show', $category)->with('status', $action === 'activate' ? 'Kategori diaktifkan.' : 'Kategori dinonaktifkan.');
        }
        $category->update($request->safe()->only('name'));

        return redirect()->route('categories.show', $category)->with('status', 'Kategori disimpan.');
    }

    public function toggle(Category $category): JsonResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return response()->json(['active' => $category->is_active]);
    }
}
