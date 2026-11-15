<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\HomepageBanner;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class WebsiteContentService
{
    /** @param array<string, mixed> $data */
    public function settings(User $admin, array $data): WebsiteSetting
    {
        abort_unless($admin->role === UserRole::Admin, 403);
        WebsiteSetting::current();
        $paths = [];
        try {
            return DB::transaction(function () use ($admin, $data, &$paths) {
                $settings = WebsiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
                $this->checkVersion($settings, $data['version']);
                $settings->fill(Arr::only($data, ['website_name', 'contact_email', 'contact_phone', 'address', 'footer_information', 'social_links']));
                foreach (['logo', 'favicon'] as $field) {
                    if ($data['remove_'.$field]) {
                        $settings->{$field.'_path'} = null;
                    }
                    if (! empty($data[$field])) {
                        $path = app(ProductImageService::class)->store($data[$field], 'website');
                        $paths[] = $path;
                        $settings->{$field.'_path'} = $path;
                    }
                }
                $this->persist($settings, $admin, 'settings');

                return $settings;
            }, 1);
        } catch (Throwable $error) {
            Storage::disk('public')->delete($paths);
            throw $error;
        }
    }

    /** @param array<string, mixed> $data */
    public function banner(?HomepageBanner $banner, User $admin, array $data): HomepageBanner
    {
        abort_unless($admin->role === UserRole::Admin, 403);
        $paths = [];
        try {
            return DB::transaction(function () use ($banner, $admin, $data, &$paths) {
                $banner = $banner ? HomepageBanner::whereKey($banner->id)->lockForUpdate()->firstOrFail() : new HomepageBanner;
                if ($banner->exists) {
                    $this->checkVersion($banner, $data['version']);
                }
                $banner->fill(Arr::only($data, ['title', 'subtitle', 'image_alt', 'link_label', 'link_path', 'status', 'position', 'starts_at', 'ends_at']));
                if (! empty($data['image'])) {
                    $path = app(ProductImageService::class)->store($data['image'], 'banners');
                    $paths[] = $path;
                    $banner->image_path = $path;
                }
                if ($banner->status === 'published' && ! $banner->image_path) {
                    throw ValidationException::withMessages(['image' => __('website.image_required')]);
                }
                $this->persist($banner, $admin, 'banner');

                return $banner;
            }, 1);
        } catch (Throwable $error) {
            Storage::disk('public')->delete($paths);
            throw $error;
        }
    }

    private function checkVersion(Model $model, mixed $version): void
    {
        if ($model->getAttribute('version') !== (int) $version) {
            throw ValidationException::withMessages(['version' => __('website.stale')]);
        }
    }

    private function persist(Model $model, User $admin, string $type): void
    {
        if ($model->exists && ! $model->isDirty()) {
            return;
        }
        $model->setAttribute('version', $model->exists ? $model->getAttribute('version') + 1 : 1);
        $model->save();
        DB::table('website_changes')->insert([
            'entity_type' => $type, 'entity_id' => $model->getKey(), 'version' => $model->getAttribute('version'),
            'admin_id' => $admin->id, 'snapshot' => json_encode($model->attributesToArray(), JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
