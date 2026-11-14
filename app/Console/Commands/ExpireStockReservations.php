<?php

namespace App\Console\Commands;

use App\Services\CheckoutService;
use Illuminate\Console\Command;

class ExpireStockReservations extends Command
{
    protected $signature = 'shop:expire-reservations';

    protected $description = 'Cancel expired pending orders and release their stock reservations';

    public function handle(CheckoutService $checkout): int
    {
        $this->info($checkout->expire().' expired orders processed.');

        return self::SUCCESS;
    }
}
