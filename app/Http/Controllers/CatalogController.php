<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HomepageBanner;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('public/Home', [
            'banners' => HomepageBanner::visible(),
            'featured' => Product::with(['images', 'category'])->withCount('variants')->where('status', 'active')->where('is_featured', true)->latest()->limit(4)->get(),
            'latest' => Product::with(['images', 'category'])->withCount('variants')->where('status', 'active')->where('is_featured', false)->latest()->limit(4)->get(),
            'categories' => Category::whereHas('products', fn ($query) => $query->where('status', 'active'))->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
        ]);
        // An empty PHP array becomes [] in JSON; its JS `.sort` is a function.
        // Materialize defaults so first-visit filter props are always an object.
        $filters = [...['search' => null, 'category' => null, 'sort' => 'newest', 'min' => null, 'max' => null], ...$filters];
        $filters['sort'] ??= 'newest';
        $query = Product::with(['images', 'category'])->withCount('variants')->where('status', 'active')
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where('name', 'like', '%'.$value.'%'))
            ->when($filters['category'] ?? null, fn ($q, $value) => $q->where('category_id', $value));
        $priceSql = 'CASE WHEN sale_price_sen IS NOT NULL AND (sale_starts_at IS NULL OR sale_starts_at <= ?) AND (sale_ends_at IS NULL OR sale_ends_at > ?) THEN sale_price_sen ELSE price_sen END';
        foreach (['min' => '>=', 'max' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->whereRaw("($priceSql) $operator CAST(? AS DECIMAL(20,0))", [now(), now(), $filters[$key]]);
            }
        }
        match ($filters['sort']) {
            'price_asc' => $query->orderByRaw("($priceSql) ASC", [now(), now()]),
            'price_desc' => $query->orderByRaw("($priceSql) DESC", [now(), now()]),
            default => $query->latest(),
        };

        return Inertia::render('public/Products', [
            'products' => $query->paginate(12)->withQueryString(),
            'categories' => Category::orderBy('name')->get(), 'filters' => $filters,
        ]);
    }

    public function show(string $slug): Response
    {
        $product = Product::with(['images', 'category', 'variants' => fn ($q) => $q->where('is_active', true)])->withCount('variants')
            ->where('status', 'active')->where('slug', $slug)->firstOrFail();

        return Inertia::render('public/Product', [
            'product' => $product,
            'related' => Product::with('images')->withCount('variants')->where('status', 'active')
                ->where('category_id', $product->category_id)->where('id', '!=', $product->id)->limit(3)->get(),
        ]);
    }
}
