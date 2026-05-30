<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->category) {
            $query->where('category', $request->category);
        }
        $products = $query->latest()->paginate(15)->appends($request->query());
        $categories = Product::whereNotNull('category')->distinct()->pluck('category');
        return view('products.index', compact('products', 'categories'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'unit' => 'required|string',
            'category' => 'nullable|string|max:100',
            'mrp' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        Product::create($request->all());
        return redirect()->route('products.index')->with('success', 'Product added successfully!');
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'unit' => 'required|string',
            'category' => 'nullable|string|max:100',
            'mrp' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        $product->update($request->all());
        return redirect()->route('products.index')->with('success', 'Product updated successfully!');
    }

    public function destroy(Product $product)
    {
        if ($product->purchaseItems()->exists() || $product->saleItems()->exists()) {
            return back()->with('error', 'Cannot delete product with existing transactions.');
        }
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted successfully!');
    }
}
