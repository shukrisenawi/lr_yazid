<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VipProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('vip_programs')->ignore($this->route('program'))],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:100000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'registration_opens_at' => ['required', 'date', 'before:registration_closes_at'],
            'registration_closes_at' => ['required', 'date', 'before_or_equal:starts_at'],
            'location' => ['required', 'string', 'max:500'],
            'maps_url' => ['nullable', 'url:https', 'max:2000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled', 'completed', 'archived'])],
            'registration_enabled' => ['required', 'boolean'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'remove_image_ids' => ['nullable', 'array', 'max:10'],
            'remove_image_ids.*' => ['integer', 'distinct'],
        ];
    }
}
