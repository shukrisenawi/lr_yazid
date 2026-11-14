<?php

namespace App\Http\Requests;

use App\Support\MalaysianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressRequest extends FormRequest
{
    public const STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Malacca',
        'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya',
        'Sabah', 'Sarawak', 'Selangor', 'Terengganu',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => MalaysianPhone::normalize($this->input('phone'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\+60[1-9][0-9]{7,9}$/'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', Rule::in(self::STATES)],
            'postcode' => ['required', 'regex:/^[0-9]{5}$/'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}
