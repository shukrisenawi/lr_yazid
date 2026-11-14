<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
