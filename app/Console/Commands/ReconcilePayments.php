<?php

namespace App\Console\Commands;

use App\Exceptions\GatewayException;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Services\PaymentVerificationService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'shop:reconcile-payments';

    protected $description = 'Verify recent unresolved ToyyibPay bills against server-side transactions';

    public function handle(PaymentVerificationService $verification): int
    {
        $payments = Payment::whereIn('status', ['pending', 'failed', 'expired'])->whereNotNull('bill_code')
            ->where('created_at', '>=', now()->subDays(7))
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<=', now()->subMinutes(5)))
            ->orderBy('checked_at')->limit(100)->get();
        foreach ($payments as $payment) {
            try {
                $verification->reconcile($payment);
            } catch (GatewayException) {
                $payment->update(['checked_at' => now()]);
                PaymentCallback::create(['payment_id' => $payment->id, 'event' => 'reconciliation', 'outcome' => 'gateway_verification_failed']);
            }
        }
        $this->info($payments->count().' payment bills checked.');

        return self::SUCCESS;
    }
}
