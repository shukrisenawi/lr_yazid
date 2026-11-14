<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentGatewaySetting;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('customer/Orders', ['orders' => $request->user()->orders()->latest()->paginate(15)]);
    }

    public function show(Order $order): Response
    {
        Gate::authorize('view', $order);

        $settings = PaymentGatewaySetting::current();
        $order->load(['items', 'payments']);

        return Inertia::render('customer/Order', [
            'order' => $order,
            'paymentEnabled' => $settings->enabled && $settings->configured() && $order->expires_at->gt(now())
                && ! $order->payments->contains('requires_review', true),
        ]);
    }

    public function cancel(Order $order, CheckoutService $checkout): RedirectResponse
    {
        Gate::authorize('cancel', $order);
        $checkout->cancel($order);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.cancelled')]);

        return back();
    }
}
