<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $product = $this->route('product');
        $id = $product instanceof Product ? $product->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('products')->ignore($id)],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($id)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:100000'],
            'price_sen' => ['required', 'integer', 'min:0', 'max:100000000'],
            'sale_price_sen' => ['nullable', 'integer', 'min:0', 'lte:price_sen'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'stock' => ['required', 'integer', 'min:0', 'max:10000000'],
            'weight_grams' => ['required', 'integer', 'min:0', 'max:10000000'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'archived'])],
            'is_featured' => ['required', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'images' => ['sometimes', 'array', 'max:12'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'image_order' => ['sometimes', 'array', 'max:50'],
            'image_order.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $id ?? 0)],
            'variants' => ['sometimes', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $id ?? 0)],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.price_sen' => ['required', 'integer', 'min:0', 'max:100000000'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:10000000'],
            'variants.*.is_active' => ['required', 'boolean'],
            'variants.*.product_image_id' => ['nullable', 'integer', Rule::exists('product_images', 'id')->where('product_id', $id ?? 0)],
            'variants.*.attributes' => ['sometimes', 'array', 'max:10'],
            'variants.*.attributes.*' => ['array:name,value'],
            'variants.*.attributes.*.name' => ['required', 'string', 'max:50'],
            'variants.*.attributes.*.value' => ['required', 'string', 'max:100'],
        ];
    }
}
