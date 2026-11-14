<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\CommerceSetting;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function index(Request $request, CartService $cart): Response
    {
        return Inertia::render('public/Checkout', [
            'cart' => $cart->summary($request),
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
            'checkoutKey' => (string) Str::uuid(),
            'reservationMinutes' => CommerceSetting::current()->reservation_minutes,
        ]);
    }

    public function store(CheckoutRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $data = $request->validated();
        $order = $checkout->create($request->user(), $data['checkout_key'], isset($data['address_id']) ? (int) $data['address_id'] : null, $data['notes'] ?? null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.order_created')]);

        return to_route('orders.show', $order);
    }
}
