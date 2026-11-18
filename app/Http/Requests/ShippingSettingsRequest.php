<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class ShippingSettingsRequest extends FormRequest
{
    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['shipping_fee_sen' => 'shipping charge (RM)'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['shipping_fee_sen.max' => 'The shipping charge must not exceed RM 10,000.00.'];
    }

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shipping_fee_sen' => ['required', 'integer', 'min:0', 'max:1000000'],
            'shipping_enabled' => ['required', 'boolean'],
            'reservation_minutes' => ['required', 'integer', 'min:5', 'max:120'],
        ];
    }
}
