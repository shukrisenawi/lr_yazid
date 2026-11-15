<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class WebsiteSettingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Multipart FormData omits empty arrays.
        $this->mergeIfMissing(['social_links' => []]);
    }

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'website_name' => ['required', 'string', 'max:80'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9() .-]+$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'footer_information' => ['required', 'string', 'max:300'],
            'social_links' => ['present', 'array', 'max:6'],
            'social_links.*' => ['array:label,url'],
            'social_links.*.label' => ['required', 'string', 'max:40'],
            'social_links.*.url' => ['required', 'url:https', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && (parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null || preg_match('/[\\\\\s\p{C}]/u', $value))) {
                    $fail(__('website.social_url'));
                }
            }],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
            'favicon' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:512', 'dimensions:min_width=32,min_height=32,max_width=512,max_height=512,ratio=1/1'],
            'remove_logo' => ['required', 'boolean'],
            'remove_favicon' => ['required', 'boolean'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
