<?php

namespace App\Services;

use App\Models\ProgramNoticeDelivery;
use App\Models\User;
use App\Models\VipProgram;
use App\Notifications\ProgramNotice;

class ProgramNotificationService
{
    public function queue(User $user, VipProgram $program, string $kind, string $eventKey): void
    {
        $delivery = ProgramNoticeDelivery::firstOrCreate([
            'vip_program_id' => $program->id, 'user_id' => $user->id, 'event_key' => $eventKey,
        ], ['queued_at' => now()]);
        if ($delivery->wasRecentlyCreated) {
            $url = $kind === 'published' ? '/vip/programs/'.$program->slug : '/my-programs';
            $user->notify(new ProgramNotice($kind, $program->title, $program->starts_at->format('d M Y H:i'), $delivery->id, $url));
        }
    }
}
