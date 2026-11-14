<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class ShippingSettingsRequest extends FormRequest
{
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
