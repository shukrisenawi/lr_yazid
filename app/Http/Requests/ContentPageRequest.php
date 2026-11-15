<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentPageRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('content_pages')->ignore($this->route('contentPage'))],
            'content' => ['nullable', 'string', 'max:100000'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'show_in_footer' => ['required', 'boolean'],
            'version' => [$this->isMethod('put') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
