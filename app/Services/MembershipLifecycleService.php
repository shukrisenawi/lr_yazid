<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipAdjustment;
use App\Models\MembershipReminder;
use App\Models\User;
use App\Notifications\MembershipNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipLifecycleService
{
    public function process(): int
    {
        $count = 0;
        Membership::where('status', 'active')->select('id')->chunkById(100, function ($records) use (&$count) {
            foreach ($records as $record) {
                DB::transaction(function () use ($record, &$count) {
                    $userId = Membership::whereKey($record->id)->value('user_id');
                    $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
                    $membership = Membership::with('plan')->whereKey($record->id)->lockForUpdate()->firstOrFail();
                    if ($membership->status !== 'active') {
                        return;
                    }
                    $kind = null;
                    if ($membership->expires_at->lte(now())) {
                        $membership->update(['status' => 'expired']);
                        $kind = 'expired';
                        $count++;
                    } else {
                        $days = (int) now()->startOfDay()->diffInDays($membership->expires_at->startOfDay(), false);
                        if (in_array($days, [30, 7, 1], true)) {
                            $kind = $days.'_days';
                        }
                    }
                    if ($kind === null) {
                        return;
                    }
                    $reminder = MembershipReminder::firstOrCreate([
                        'membership_id' => $membership->id, 'expires_at' => $membership->expires_at, 'kind' => $kind,
                    ], ['user_id' => $user->id, 'queued_at' => now()]);
                    if ($reminder->wasRecentlyCreated) {
                        $user->notify(new MembershipNotice($kind, $membership->plan->name, $membership->expires_at->format('d M Y H:i'), $reminder->id));
                    }
                }, 3);
            }
        });

        return $count;
    }

    public function adjust(Membership $membership, User $admin, CarbonImmutable $expiry, string $reason): void
    {
        DB::transaction(function () use ($membership, $admin, $expiry, $reason) {
            $user = User::whereKey($membership->user_id)->lockForUpdate()->firstOrFail();
            $membership = Membership::with('plan')->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            if ($expiry->lte($membership->starts_at)) {
                throw ValidationException::withMessages(['expires_at' => 'The expiry must be after the membership start date.']);
            }
            $status = $membership->status === 'revoked' ? 'revoked' : ($expiry->gt(now()) ? 'active' : 'expired');
            MembershipAdjustment::create([
                'membership_id' => $membership->id, 'admin_id' => $admin->id, 'old_expires_at' => $membership->expires_at,
                'new_expires_at' => $expiry, 'old_status' => $membership->status, 'new_status' => $status, 'reason' => $reason,
            ]);
            $membership->update(['expires_at' => $expiry, 'status' => $status]);
            $user->notify(new MembershipNotice('adjustment', $membership->plan->name, $expiry->format('d M Y H:i')));
        }, 3);
    }
}
