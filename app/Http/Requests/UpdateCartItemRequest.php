<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['quantity' => ['required', 'integer', 'min:1', 'max:99']];
    }
}
