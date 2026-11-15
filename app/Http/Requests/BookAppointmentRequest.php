<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasActiveMembership() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['slot_id' => ['required', 'integer'], 'booking_key' => ['required', 'uuid'],
            'purpose' => ['required', 'string', 'min:5', 'max:1000'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
