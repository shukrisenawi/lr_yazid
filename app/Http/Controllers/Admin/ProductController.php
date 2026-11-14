<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '';

        return Inertia::render('admin/products/Index', [
            'products' => Product::with('category')->where('name', 'like', '%'.$search.'%')->latest()->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('admin/products/Edit', ['product' => null, 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(ProductRequest $request, ProductService $service): RedirectResponse
    {
        $product = $service->save(new Product, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product created.']);

        return to_route('admin.products.edit', $product);
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('admin/products/Edit', [
            'product' => $product->load(['images', 'variants']), 'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        Gate::authorize('update', $product);
        $service->save($product, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product saved.']);

        return back();
    }

    public function duplicate(Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);
        $copy = DB::transaction(function () use ($product) {
            $copy = $product->replicate();
            $copy->name = Str::limit($product->name, 248, '').' (copy)';
            $copy->slug = Str::limit($product->slug, 240, '').'-'.Str::lower(Str::random(8));
            $copy->sku = Str::limit($product->sku, 80, '').'-'.Str::upper(Str::random(8));
            $copy->status = 'draft';
            $copy->save();
            $imageIds = [];
            foreach ($product->images as $image) {
                $path = 'products/'.Str::uuid().'.'.pathinfo($image->path, PATHINFO_EXTENSION);
                if (Storage::disk('public')->copy($image->path, $path)) {
                    $imageIds[$image->id] = $copy->images()->create(['path' => $path, 'alt' => $image->alt, 'position' => $image->position])->id;
                }
            }
            foreach ($product->variants as $variant) {
                $copy->variants()->create([
                    'name' => $variant->name, 'sku' => $variant->sku.'-'.Str::upper(Str::random(8)),
                    'price_sen' => $variant->price_sen, 'stock' => $variant->stock, 'is_active' => $variant->is_active,
                    'attributes' => $variant->attributes,
                    'product_image_id' => $imageIds[$variant->product_image_id] ?? null,
                ]);
            }

            return $copy;
        });

        return to_route('admin.products.edit', $copy);
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);
        $product->update(['status' => 'archived']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product archived.']);

        return to_route('admin.products.index');
    }

    public function removeImage(Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('update', $product);
        abort_unless($image->product_id === $product->id, 404);
        $path = $image->path;
        $image->delete();
        Storage::disk('public')->delete($path);

        return back();
    }

    public function upload(Request $request, ProductImageService $service): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000']]);
        $path = $service->store($request->file('image'));

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }

    public function replaceImage(Request $request, Product $product, ProductImage $image, ProductImageService $service): RedirectResponse
    {
        Gate::authorize('update', $product);
        abort_unless($image->product_id === $product->id, 404);
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000']]);
        $path = $service->store($request->file('image'));
        try {
            $oldPath = DB::transaction(function () use ($image, $path) {
                $locked = ProductImage::whereKey($image->id)->lockForUpdate()->firstOrFail();
                $old = $locked->path;
                $locked->update(['path' => $path]);

                return $old;
            });
        } catch (\Throwable $error) {
            Storage::disk('public')->delete($path);
            throw $error;
        }
        Storage::disk('public')->delete($oldPath);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image replaced.')]);

        return back();
    }
}
