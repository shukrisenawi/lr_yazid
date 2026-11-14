<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VipProgram;
use App\Services\ProgramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AnnounceVipProgram implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $programId)
    {
        $this->onConnection('database')->beforeCommit();
    }

    public function handle(ProgramNotificationService $notices): void
    {
        $program = VipProgram::findOrFail($this->programId);
        User::whereHas('memberships', fn ($query) => $query->where('status', 'active')
            ->where('starts_at', '<=', now())->where('expires_at', '>', $program->ends_at))
            ->select('users.*')->chunkById(100, function ($users) use ($notices) {
                DB::transaction(function () use ($users, $notices) {
                    $program = VipProgram::whereKey($this->programId)->lockForUpdate()->firstOrFail();
                    if ($program->status !== 'published' || $program->starts_at->lte(now())) {
                        return;
                    }
                    foreach ($users as $user) {
                        $notices->queue($user, $program, 'published', 'published');
                    }
                }, 3);
            });
    }
}
