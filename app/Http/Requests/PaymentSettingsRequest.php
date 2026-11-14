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
            'sandbox_secret_key' => ['nullable', 'string', 'min:8', 'max:255'],
            'production_secret_key' => ['nullable', 'string', 'min:8', 'max:255'],
            'sandbox_category_code' => ['nullable', 'alpha_num', 'max:64'],
            'production_category_code' => ['nullable', 'alpha_num', 'max:64'],
        ];
    }
}
