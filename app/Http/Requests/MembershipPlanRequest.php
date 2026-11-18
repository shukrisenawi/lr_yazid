<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class MembershipPlanRequest extends FormRequest
{
    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['price_sen' => 'price (RM)'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'price_sen.min' => 'The price must be at least RM 1.00.',
            'price_sen.max' => 'The price must not exceed RM 1,000,000.00.',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'], 'price_sen' => ['required', 'integer', 'min:100', 'max:100000000'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'], 'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'], 'benefits' => ['required', 'array', 'min:1', 'max:20'],
            'benefits.*' => ['required', 'string', 'max:200'],
        ];
    }
}
