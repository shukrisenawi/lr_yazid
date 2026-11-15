<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WebsiteSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['social_links' => 'array', 'version' => 'integer'];
    }

    public static function current(): self
    {
        $settings = self::find(1);
        if ($settings) {
            return $settings;
        }
        self::query()->insertOrIgnore(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return self::findOrFail(1);
    }

    /** @return array<string, mixed> */
    public static function publicData(): array
    {
        $settings = self::find(1);

        return [
            'website_name' => $settings->website_name ?? __('interface.brand'),
            'logo_url' => $settings?->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
            'favicon_url' => $settings?->favicon_path ? Storage::disk('public')->url($settings->favicon_path) : null,
            'contact_email' => $settings?->contact_email,
            'contact_phone' => $settings?->contact_phone,
            'address' => $settings?->address,
            'footer_information' => $settings->footer_information ?? __('interface.footer'),
            'social_links' => $settings->social_links ?? [],
        ];
    }
}
