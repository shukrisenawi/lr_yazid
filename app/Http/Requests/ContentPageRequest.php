<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentPageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $domain = $this->input('custom_domain');
        if (is_string($domain)) {
            $this->merge(['custom_domain' => strtolower(trim($domain)) ?: null]);
        }
    }

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
            'is_landing' => ['sometimes', 'boolean'],
            'custom_domain' => ['nullable', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', Rule::notIn([parse_url(config('app.url'), PHP_URL_HOST)]), Rule::unique('content_pages')->ignore($this->route('contentPage'))],
            'version' => [$this->isMethod('put') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
