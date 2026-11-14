<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }
        $category = Category::firstOrCreate(['slug' => 'everyday-essentials'], ['name' => 'Everyday Essentials']);
        foreach ([
            ['name' => 'Everyday Bottle', 'slug' => 'everyday-bottle', 'sku' => 'YC-BTL-01', 'price_sen' => 8900, 'image' => 'bottle.jpg'],
            ['name' => 'Canvas Carryall', 'slug' => 'canvas-carryall', 'sku' => 'YC-BAG-01', 'price_sen' => 12900, 'image' => 'bag.jpg'],
            ['name' => 'Daily Notebook', 'slug' => 'daily-notebook', 'sku' => 'YC-NTE-01', 'price_sen' => 3900, 'image' => 'notebook.jpg'],
        ] as $data) {
            $product = Product::firstOrCreate(['slug' => $data['slug']], [
                'category_id' => $category->id, 'name' => $data['name'], 'sku' => $data['sku'],
                'price_sen' => $data['price_sen'], 'stock' => 25, 'weight_grams' => 400,
                'status' => 'active', 'is_featured' => true,
                'short_description' => 'A sample product from the development collection. Not available for purchase.',
                'description' => '<h2>Designed for your day</h2><p>This is sample catalog content for development. Replace it with verified product details before launch.</p>',
            ]);
            $source = public_path('images/'.$data['image']);
            $path = 'products/sample-'.$data['image'];
            if (File::exists($source) && ! $product->images()->exists()) {
                Storage::disk('public')->put($path, File::get($source));
                $product->images()->create(['path' => $path, 'alt' => $data['name'], 'position' => 0]);
            }
        }
    }
}
