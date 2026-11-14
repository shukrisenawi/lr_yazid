<?php

namespace App\Console\Commands;

use App\Services\MembershipLifecycleService;
use Illuminate\Console\Command;

class ExpireMemberships extends Command
{
    protected $signature = 'vip:expire-memberships';

    protected $description = 'Expire membership records without deleting customer history';

    public function handle(MembershipLifecycleService $lifecycle): int
    {
        $count = $lifecycle->process();
        $this->info($count.' membership records expired.');

        return self::SUCCESS;
    }
}
