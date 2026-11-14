<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class MembershipAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expires_at' => ['required', 'date'], 'reason' => ['required', 'string', 'min:10', 'max:1000']];
    }
}
