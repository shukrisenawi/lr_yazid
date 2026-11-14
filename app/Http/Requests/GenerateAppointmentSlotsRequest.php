<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class GenerateAppointmentSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'day_start' => ['required', 'date_format:H:i'],
            'day_end' => ['required', 'date_format:H:i', 'after:day_start'],
            'break_start' => ['nullable', 'required_with:break_end', 'date_format:H:i', 'after_or_equal:day_start'],
            'break_end' => ['nullable', 'required_with:break_start', 'date_format:H:i', 'after:break_start', 'before_or_equal:day_end'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'location' => ['required', 'string', 'max:500'],
            'preview' => ['required', 'boolean'],
        ];
    }
}
