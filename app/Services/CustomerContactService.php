<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CustomerContactChange;
use App\Models\User;
use App\Support\MalaysianPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerContactService
{
    /** @param array<string, mixed> $input */
    public function update(User $customer, User $admin, array $input): void
    {
        abort_unless($admin->role === UserRole::Admin, 403);
        if (isset($input['phone']) && is_string($input['phone'])) {
            $input['phone'] = MalaysianPhone::normalize($input['phone']);
        }
        if (isset($input['name']) && is_string($input['name'])) {
            $input['name'] = trim($input['name']);
        }
        if (isset($input['reason']) && is_string($input['reason'])) {
            $input['reason'] = trim($input['reason']);
        }
        $data = Validator::make($input, ['name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+60[1-9][0-9]{7,9}$/'],
            'profile_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'email' => ['prohibited'], 'password' => ['prohibited'], 'role' => ['prohibited'],
            'email_verified_at' => ['prohibited'], 'remember_token' => ['prohibited'],
            'two_factor_secret' => ['prohibited'], 'two_factor_recovery_codes' => ['prohibited'],
        ])->validate();
        DB::transaction(function () use ($customer, $admin, $data) {
            $customer = User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            abort_unless($customer->role === UserRole::Customer, 404);
            if ($customer->profile_version !== (int) $data['profile_version']) {
                throw ValidationException::withMessages(['profile_version' => __('customer_contacts.conflict')]);
            }
            $before = $customer->only(['name', 'phone']);
            $customer->fill(['name' => $data['name'], 'phone' => $data['phone']]);
            if (! $customer->isDirty(['name', 'phone'])) {
                return;
            }
            $customer->save();
            CustomerContactChange::create(['customer_id' => $customer->id, 'admin_id' => $admin->id,
                'profile_version' => $customer->profile_version, 'previous_values' => $before,
                'new_values' => $customer->only(['name', 'phone']), 'reason' => $data['reason']]);
        }, 3);
    }
}
