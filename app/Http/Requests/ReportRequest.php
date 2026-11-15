<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: now()->startOfMonth()->subMonths(5)->toDateString(),
            'to' => $this->input('to') ?: now()->toDateString(),
            'environment' => $this->input('environment') ?: 'production',
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'environment' => ['required', 'in:production,sandbox']];
    }

    /** @return array{from: string, to: string, environment: string} */
    public function filters(): array
    {
        return ['from' => $this->string('from')->toString(), 'to' => $this->string('to')->toString(),
            'environment' => $this->string('environment')->toString()];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->any() && CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 365) {
                $validator->errors()->add('to', __('reports.range'));
            }
        }];
    }
}
