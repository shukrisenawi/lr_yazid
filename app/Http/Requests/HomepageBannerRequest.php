<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomepageBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
            'image_alt' => ['nullable', 'required_if:status,published', 'string', 'max:200'],
            'link_label' => ['nullable', 'required_with:link_path', 'string', 'max:40'],
            'link_path' => ['nullable', 'required_with:link_label', 'string', 'max:500', 'regex:~^/(?!/)[a-zA-Z0-9/_?=&.#-]*$~D'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'position' => ['required', 'integer', 'min:0', 'max:10000'],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', ...($this->filled('starts_at') ? ['after:starts_at'] : [])],
            'version' => [$this->isMethod('put') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
