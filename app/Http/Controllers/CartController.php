<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request, CartService $cart): Response
    {
        return Inertia::render('public/Cart', ['cart' => $cart->summary($request)]);
    }

    public function store(AddCartItemRequest $request, CartService $cart): RedirectResponse
    {
        $data = $request->validated();
        $cart->add($request, (int) $data['product_id'], isset($data['product_variant_id']) ? (int) $data['product_variant_id'] : null, (int) $data['quantity']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.added')]);

        return back();
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem, CartService $cart): RedirectResponse
    {
        $cart->update($request, $cartItem, (int) $request->validated('quantity'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.updated')]);

        return back();
    }

    public function destroy(Request $request, CartItem $cartItem, CartService $cart): RedirectResponse
    {
        $cart->update($request, $cartItem, null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.removed')]);

        return back();
    }

    public function clear(Request $request, CartService $cart): RedirectResponse
    {
        $cart->clear($request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop.cleared')]);

        return back();
    }
}
