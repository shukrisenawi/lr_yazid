<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class AppointmentServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'], 'default_capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'location' => ['required', 'string', 'max:500'], 'is_active' => ['required', 'boolean'],
            'minimum_notice_hours' => ['required', 'integer', 'min:0', 'max:2160'],
            'maximum_advance_days' => ['required', 'integer', 'min:1', 'max:60'],
            'cancellation_notice_hours' => ['required', 'integer', 'min:0', 'max:2160'],
            'maximum_active_bookings' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
