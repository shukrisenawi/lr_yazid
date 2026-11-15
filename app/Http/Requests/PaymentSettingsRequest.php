<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'], 'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'sandbox_secret_key' => ['nullable', 'string', 'min:8', 'max:255', 'regex:/^[^\s\p{C}]+$/uD'],
            'production_secret_key' => ['nullable', 'string', 'min:8', 'max:255', 'regex:/^[^\s\p{C}]+$/uD'],
            'sandbox_category_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]+$/D', 'max:64'],
            'production_category_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]+$/D', 'max:64'],
            'clear_sandbox_secret_key' => ['sometimes', 'boolean'],
            'clear_production_secret_key' => ['sometimes', 'boolean'],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }
}
