<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class AppointmentSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'], 'location' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'], 'status' => ['required', 'in:available,disabled'],
        ];
    }
}
