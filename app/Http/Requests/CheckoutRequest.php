<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'checkout_key' => ['required', 'uuid'],
            'address_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
