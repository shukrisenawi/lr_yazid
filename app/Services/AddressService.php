<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /** @param array<string, mixed> $data */
    public function save(User $user, ?Address $address, array $data): Address
    {
        return DB::transaction(function () use ($user, $address, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $default = ($data['is_default'] ?? false) || ! $user->addresses()->exists();
            // Keep an existing default until another address is explicitly selected.
            if ($address?->is_default) {
                $default = true;
            }
            if ($default) {
                $user->addresses()->update(['is_default' => false]);
            }
            $address ??= $user->addresses()->make();
            $address->fill($data);
            $address->is_default = $default;
            $address->save();

            return $address;
        });
    }

    public function delete(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $address->delete();
            if (! $user->addresses()->where('is_default', true)->exists()) {
                $user->addresses()->oldest('id')->first()?->update(['is_default' => true]);
            }
        });
    }
}
