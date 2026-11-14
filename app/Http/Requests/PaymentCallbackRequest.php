<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentCallbackRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['1', '2', '3'])],
            'order_id' => ['required', 'string', 'max:40'],
            'billcode' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{4,64}$/'],
            'refno' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'string', 'max:30'],
            'hash' => ['required', 'string', 'regex:/^[a-fA-F0-9]{32}$/'],
        ];
    }
}
