<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentReturnRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['order_id' => ['required', 'string', 'max:40'], 'billcode' => ['required', 'string', 'max:64']];
    }
}
