<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Support\PublicHttpsUrl;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentSettingsService
{
    /** @param array<string, mixed> $data */
    public function update(User $admin, array $data): PaymentGatewaySetting
    {
        abort_unless($admin->role === UserRole::Admin, 403);

        return DB::transaction(function () use ($admin, $data) {
            $defaults = PaymentGatewaySetting::current();
            if (! $defaults->exists) {
                // Seed the fixed singleton key, including encrypted environment defaults.
                DB::table('payment_gateway_settings')->insertOrIgnore([
                    ...$defaults->getAttributes(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $settings = PaymentGatewaySetting::whereKey(1)->lockForUpdate()->firstOrFail();
            if ($settings->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => __('payment_settings.stale')]);
            }
            $before = $this->safeSnapshot($settings);
            $settings->fill(Arr::only($data, ['enabled', 'environment', 'sandbox_category_code', 'production_category_code']));
            $changedKeys = [];
            foreach (['sandbox', 'production'] as $environment) {
                $field = $environment.'_secret_key';
                $clear = $data['clear_'.$field] ?? false;
                if ($clear && filled($data[$field] ?? null)) {
                    throw ValidationException::withMessages([$field => __('payment_settings.conflicting_key')]);
                }
                if ($clear) {
                    $settings->setAttribute($field, null);
                } elseif (filled($data[$field] ?? null)) {
                    // Avoid rewriting ciphertext and audit entries for an unchanged key.
                    if ($settings->getAttribute($field) !== $data[$field]) {
                        $settings->setAttribute($field, $data[$field]);
                    }
                }
                if ($settings->isDirty($field)) {
                    $changedKeys[] = $environment;
                }
            }
            if ($settings->enabled && ! $settings->configured()) {
                throw ValidationException::withMessages(['enabled' => __('payment_settings.credentials_required')]);
            }
            if ($settings->enabled && ! PublicHttpsUrl::configured(config('app.url'))) {
                throw ValidationException::withMessages(['enabled' => __('payment_settings.https_required')]);
            }
            if ($settings->version > 0 && ! $settings->isDirty()) {
                return $settings;
            }
            $settings->version++;
            $settings->save();
            PaymentCallback::create([
                'event' => 'settings_updated', 'outcome' => 'saved',
                'payload' => ['admin_id' => $admin->id, 'before' => $before, 'after' => $this->safeSnapshot($settings),
                    'secret_keys_changed' => $changedKeys],
            ]);

            return $settings;
        }, 3);
    }

    /** @return array<string, mixed> */
    private function safeSnapshot(PaymentGatewaySetting $settings): array
    {
        return [...$settings->only(['enabled', 'environment', 'sandbox_category_code', 'production_category_code', 'version']),
            'sandbox_key_available' => filled($settings->sandbox_secret_key),
            'production_key_available' => filled($settings->production_secret_key)];
    }
}
