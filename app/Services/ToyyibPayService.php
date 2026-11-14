<?php

namespace App\Services;

use App\Exceptions\GatewayException;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ToyyibPayService
{
    public function baseUrl(string $environment): string
    {
        return match ($environment) {
            'sandbox' => 'https://dev.toyyibpay.com',
            'production' => 'https://toyyibpay.com',
            default => throw new GatewayException('Invalid payment environment.'),
        };
    }

    public function billUrl(Payment $payment): string
    {
        if (! preg_match('/^[a-zA-Z0-9]{4,64}$/', $payment->bill_code ?? '')) {
            throw new GatewayException('The payment bill is not available.');
        }

        return $this->baseUrl($payment->environment).'/'.$payment->bill_code;
    }

    public function createBill(Order $order, Payment $payment, string $category): string
    {
        try {
            $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)
                ->post($this->baseUrl($payment->environment).'/index.php/api/createBill', [
                    'userSecretKey' => $payment->secret_key_snapshot, 'categoryCode' => $category,
                    'billName' => 'Order '.$order->id, 'billDescription' => 'Payment for order '.$order->id,
                    'billPriceSetting' => 1, 'billPayorInfo' => 1, 'billAmount' => $payment->amount_sen,
                    'billReturnUrl' => rtrim(config('app.url'), '/').route('payments.return', absolute: false),
                    'billCallbackUrl' => rtrim(config('app.url'), '/').route('payments.callback', absolute: false),
                    'billExternalReferenceNo' => $payment->reference, 'billTo' => $order->customer_name,
                    'billEmail' => $order->customer_email, 'billPhone' => preg_replace('/\D/', '', $order->customer_phone),
                    'billPaymentChannel' => '0', 'billSplitPayment' => 0,
                    'billExpiryDate' => $order->expires_at->format('d-m-Y H:i:s'),
                ]);
        } catch (ConnectionException) {
            // Bill creation is not idempotent at the provider. Never blindly retry a timeout.
            throw new GatewayException('Bill creation could not be confirmed. Please contact support before retrying.', true);
        }
        $body = $response->json();
        if ($response->successful() && is_array($body) && isset($body[0]['BillCode'])
            && is_string($body[0]['BillCode']) && preg_match('/^[a-zA-Z0-9]{4,64}$/', $body[0]['BillCode'])) {
            return $body[0]['BillCode'];
        }
        if (($body['status'] ?? null) === 'error' || preg_match('/^\[[A-Z0-9-]+\](?:\s.*)?$/', trim($response->body()))) {
            throw new GatewayException('The gateway rejected bill creation. Please check payment settings or try again later.');
        }

        throw new GatewayException('The gateway returned an unrecognized bill response. Please contact support.', true);
    }

    /** @return list<array<string, mixed>> */
    public function transactions(Payment $payment): array
    {
        try {
            $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)
                ->post($this->baseUrl($payment->environment).'/index.php/api/getBillTransactions', [
                    'billCode' => $payment->bill_code, 'userSecretKey' => $payment->secret_key_snapshot,
                ]);
        } catch (ConnectionException) {
            throw new GatewayException('The gateway is unavailable. Payment remains unconfirmed.');
        }
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ! array_is_list($body)) {
            throw new GatewayException('The gateway transaction response could not be verified.');
        }

        return array_values(array_filter($body, is_array(...)));
    }

    public function toSen(mixed $amount): int
    {
        if ((! is_string($amount) && ! is_int($amount)) || ! preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D', (string) $amount, $matches)) {
            throw new GatewayException('The gateway returned an invalid payment amount.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    public function paymentTime(mixed $value): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::now();
        }
        if (! is_string($value)) {
            throw new GatewayException('The verified payment date is invalid.');
        }
        try {
            $date = CarbonImmutable::createFromFormat('!d-m-Y H:i:s', $value, 'Asia/Kuala_Lumpur');
        } catch (\Throwable) {
            throw new GatewayException('The verified payment date is invalid.');
        }
        if (! $date || $date->format('d-m-Y H:i:s') !== $value || $date->gt(now()->addMinutes(5))) {
            throw new GatewayException('The verified payment date is invalid.');
        }

        return $date;
    }
}
